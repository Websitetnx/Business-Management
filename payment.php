<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
$user = require_role('applicant');
$pdo = db();
$applicationId = filter_input(INPUT_GET, 'application_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
if (!$applicationId) { http_response_code(404); exit('Payment record not found.'); }

try {
    $statement = $pdo->prepare('SELECT a.id application_id, a.reference application_reference, a.status application_status, a.user_id, b.business_name, p.* FROM applications a JOIN businesses b ON b.id = a.business_id LEFT JOIN payments p ON p.application_id = a.id WHERE a.id = ? AND a.user_id = ?');
    $statement->execute([$applicationId, $user['id']]);
} catch (PDOException) {
    flash('error', 'Import database/migrations/004_payment_workflow.sql before using permit payments.');
    redirect('application.php?id=' . $applicationId);
}
$payment = $statement->fetch();
if (!$payment) { http_response_code(404); exit('Payment record not found.'); }
if (!in_array($payment['application_status'], ['Approved', 'Released'], true)) { http_response_code(403); exit('Payment is available only after BPLO approval.'); }

$errors = [];
$methods = ['GCash', 'Maya', 'Bank Transfer', 'City Treasurer Counter'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$payment['id']) $errors[] = 'The BPLO has not assessed the permit fee yet.';
    if (($payment['status'] ?? '') === 'Paid') $errors[] = 'This payment has already been verified.';
    if ($payment['application_status'] === 'Released' && ($payment['status'] ?? '') === 'Paid') $errors[] = 'The permit has been released and the payment is already verified.';
    $method = trim((string) ($_POST['payment_method'] ?? ''));
    $payerName = trim((string) ($_POST['payer_name'] ?? ''));
    $reference = trim((string) ($_POST['payment_reference'] ?? ''));
    if (!in_array($method, $methods, true)) $errors[] = 'Select a valid payment method.';
    if (strlen($payerName) < 2 || strlen($payerName) > 120) $errors[] = 'Enter the payer name.';
    if (strlen($reference) < 3 || strlen($reference) > 100) $errors[] = 'Enter the transaction or treasury reference number.';

    $file = $_FILES['payment_proof'] ?? null;
    $fileError = is_array($file) ? (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
    $requiresProof = $method !== 'City Treasurer Counter';
    $mime = null;
    $extension = null;
    if ($fileError === UPLOAD_ERR_NO_FILE && $requiresProof) {
        $errors[] = 'Upload a payment confirmation for online or bank payment.';
    } elseif ($fileError !== UPLOAD_ERR_NO_FILE) {
        if ($fileError !== UPLOAD_ERR_OK) {
            $errors[] = 'The payment confirmation could not be uploaded.';
        } elseif ((int) $file['size'] > (int) app_config('max_upload_bytes')) {
            $errors[] = 'The payment confirmation must be 5 MB or smaller.';
        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $allowedTypes = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
            if (!isset($allowedTypes[$mime])) $errors[] = 'The payment confirmation must be a PDF, JPG, or PNG file.';
            else $extension = $allowedTypes[$mime];
        }
    }

    if (!$errors) {
        $newStoredName = null;
        $oldStoredName = $payment['proof_stored_name'] ?? null;
        try {
            if ($fileError === UPLOAD_ERR_OK && $extension) {
                $uploadDirectory = (string) app_config('upload_dir');
                if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0750, true) && !is_dir($uploadDirectory)) throw new RuntimeException('The secure upload directory could not be created.');
                $newStoredName = bin2hex(random_bytes(24)) . '.' . $extension;
                if (!move_uploaded_file($file['tmp_name'], $uploadDirectory . '/' . $newStoredName)) throw new RuntimeException('The payment confirmation could not be saved.');
            }
            $pdo->beginTransaction();
            $update = $pdo->prepare("UPDATE payments SET payment_method = ?, payer_name = ?, payment_reference = ?, status = 'Pending', proof_original_name = COALESCE(?, proof_original_name), proof_stored_name = COALESCE(?, proof_stored_name), proof_mime_type = COALESCE(?, proof_mime_type), submitted_at = NOW(), verified_by = NULL, verified_at = NULL, admin_notes = NULL WHERE id = ? AND application_id = ?");
            $update->execute([$method, $payerName, $reference, $newStoredName ? basename((string) $file['name']) : null, $newStoredName, $mime, $payment['id'], $applicationId]);
            $message = 'Payment submitted for application ' . $payment['application_reference'] . ' and is awaiting verification.';
            $notice = $pdo->prepare("INSERT INTO notifications (user_id, application_id, message) SELECT id, ?, ? FROM users WHERE role = 'treasurer' AND is_active = 1");
            $notice->execute([$applicationId, $message]);
            notify_application($pdo,(int)$applicationId,'payment-submitted:'.$payment['id'].':'.hash('sha256',$reference.':'.($newStoredName??'').':'.date('Y-m-d H:i:s')),'Payment submitted',$message);
            audit($pdo, (int) $user['id'], 'submit_payment', 'payment', (int) $payment['id']);
            $pdo->commit();
            if ($newStoredName && $oldStoredName && $oldStoredName !== $newStoredName) {
                $oldPath = (string) app_config('upload_dir') . '/' . basename((string) $oldStoredName);
                if (is_file($oldPath)) unlink($oldPath);
            }
            flash('success', 'Payment information submitted. The City Treasurer or administrator must verify it before permit release.');
            redirect('payment.php?application_id=' . $applicationId);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($newStoredName) {
                $newPath = (string) app_config('upload_dir') . '/' . $newStoredName;
                if (is_file($newPath)) unlink($newPath);
            }
            $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'The payment could not be submitted.';
        }
    }
}

$statement->execute([$applicationId, $user['id']]);
$payment = $statement->fetch();
$feeBreakdown = decoded_fee_breakdown($payment['assessment_breakdown'] ?? null);
render_app_header('Permit Payment', 'payments');
?>
<div class="section-heading"><div><p class="eyebrow"><?= e($payment['application_reference']) ?></p><h2>Permit Payment</h2><p class="muted"><?= e($payment['business_name']) ?></p></div><?php if ($payment['id']): ?><span class="status <?= e(payment_status_class($payment['status'])) ?>"><?= e($payment['status']) ?></span><?php endif; ?></div>
<?php if ($errors): ?><div class="form-alert form-alert-error"><ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if (!$payment['id']): ?>
  <article class="panel empty-state"><h3>Fee assessment is not ready</h3><p>BPLO must enter the assessed permit amount before payment can be submitted.</p><a class="button button-secondary" href="application.php?id=<?= (int) $applicationId ?>">Back to application</a></article>
<?php else: ?>
  <div class="content-grid payment-layout">
    <article class="panel payment-form-panel"><div class="panel-header"><div><p class="eyebrow">Amount due</p><h3>₱<?= e(number_format((float) $payment['amount'], 2)) ?></h3></div></div>
      <?php if ($feeBreakdown): ?><div class="applicant-fee-breakdown"><div><strong>Official assessment breakdown</strong><small><?= e($feeBreakdown['lgu_name']) ?> · <?= e($feeBreakdown['tax_basis_label']) ?> ₱<?= e(number_format((float) $feeBreakdown['tax_basis'], 2)) ?></small></div><dl><?php foreach ($feeBreakdown['components'] as $component): ?><div><dt><?= e($component['label']) ?></dt><dd>₱<?= e(number_format((float) $component['amount'], 2)) ?></dd></div><?php endforeach; ?><div class="fee-total"><dt>Total amount due</dt><dd>₱<?= e(number_format((float) $feeBreakdown['total'], 2)) ?></dd></div></dl><p>Assessment is based on the LGU schedule saved by BPLO. Contact the BPLO or City Treasurer if any business information is incorrect.</p></div><?php endif; ?>
      <?php if ($payment['status'] === 'Paid'): ?>
        <div class="payment-success"><span>✓</span><div><h3>Payment verified</h3><p>Verified on <?= e(date('F j, Y g:i A', strtotime($payment['paid_at']))) ?>.</p><div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px"><a class="button" href="receipt.php?id=<?= (int) $payment['id'] ?>">View payment receipt</a><?php if ($payment['application_status'] === 'Released'): ?><a class="button button-secondary" href="certificate.php?id=<?= (int) $applicationId ?>">View certificate →</a><?php endif; ?></div></div></div>
      <?php else: ?>
        <?php if ($payment['status'] === 'Failed'): ?><div class="form-alert form-alert-error"><strong>Payment needs correction:</strong> <?= e($payment['admin_notes'] ?: 'Submit valid payment details again.') ?></div><?php elseif ($payment['submitted_at'] && $payment['status'] === 'Pending'): ?><div class="form-alert"><strong>Verification pending:</strong> Your payment was submitted on <?= e(date('F j, Y g:i A', strtotime($payment['submitted_at']))) ?>. The City Treasurer or administrator will verify it shortly.</div><?php endif; ?>
        <?php if ($payment['application_status'] === 'Released' && ($payment['status'] ?? '') !== 'Paid'): ?>
          <div class="form-alert form-alert-error"><strong>Permit released without verified payment.</strong> Contact the BPLO for assistance.</div>
        <?php elseif ($payment['application_status'] !== 'Released'): ?>
        <?php if ($payment['submitted_at'] && $payment['status'] === 'Pending'): ?>
          <div class="detail-list detail-list-padded" style="margin-bottom:16px">
            <div><small>Payment method</small><strong><?= e($payment['payment_method']) ?></strong></div>
            <div><small>Payer name</small><strong><?= e($payment['payer_name']) ?></strong></div>
            <div><small>Reference</small><strong><?= e($payment['payment_reference']) ?></strong></div>
            <div><small>Submitted</small><strong><?= e(date('F j, Y g:i A', strtotime($payment['submitted_at']))) ?></strong></div>
          </div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="payment-form"><?= csrf_field() ?><input type="hidden" name="application_id" value="<?= (int) $applicationId ?>">
          <div class="form-grid no-side-padding">
            <label class="field">Payment method
              <select name="payment_method" required>
                <option value="">Select method</option>
                <?php foreach ($methods as $option): ?>
                  <option value="<?= e($option) ?>" <?= ($_POST['payment_method'] ?? $payment['payment_method']) === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="field">Payer name
              <input name="payer_name" required maxlength="120" value="<?= e($_POST['payer_name'] ?? $payment['payer_name'] ?? $user['name']) ?>">
            </label>
            <label class="field field-wide">Transaction or treasury reference
              <input name="payment_reference" required maxlength="100" value="<?= e($_POST['payment_reference'] ?? $payment['payment_reference'] ?? '') ?>" placeholder="Transaction ID or official treasury reference">
            </label>
            <label class="field field-wide">Payment confirmation
              <small>Required for GCash, Maya, and bank transfer</small>
              <input type="file" name="payment_proof" accept=".pdf,.jpg,.jpeg,.png">
              <span class="field-help">PDF, JPG, or PNG · Maximum 5 MB</span>
            </label>
          </div>
          <label class="declaration">
            <input type="checkbox" required>
            <span>I confirm that these payment details are accurate. Submitting payment details does not automatically release the permit.</span>
          </label>
          <button class="button" type="submit"><?= $payment['submitted_at'] ? 'Resubmit payment details' : 'Submit payment for verification' ?></button>
        </form>
        <?php endif; ?>
      <?php endif; ?>
    </article>
    <aside class="panel payment-instructions"><div class="panel-header"><div><p class="eyebrow">Payment instructions</p><h3>Before submitting</h3></div></div><ol><li>Pay the exact assessed amount through an LGU-authorized channel.</li><li>Keep the transaction reference or treasury receipt.</li><li>Upload a clear confirmation for online payments.</li><li>Wait for City Treasurer or administrator verification.</li></ol><p class="hint">PERMIT records payment evidence but does not directly transfer funds. Use only payment channels officially announced by your LGU.</p></aside>
  </div>
<?php endif; ?>
<div class="form-actions"><a class="button button-secondary" href="application.php?id=<?= (int) $applicationId ?>">← Back to application</a></div>
<?php render_app_footer(); ?>
