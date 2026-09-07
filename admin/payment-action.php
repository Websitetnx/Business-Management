<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/mailer.php';
$user = require_role(['admin', 'treasurer']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/payments.php');
verify_csrf();
$paymentId = filter_input(INPUT_POST, 'payment_id', FILTER_VALIDATE_INT);
$action = (string) ($_POST['action'] ?? '');
$notes = trim((string) ($_POST['admin_notes'] ?? ''));
if (!$paymentId || !in_array($action, ['verify', 'reject'], true)) { flash('error', 'Invalid payment action.'); redirect('admin/payments.php'); }

$pdo = db();
$statement = $pdo->prepare(
    'SELECT p.*, a.id application_id, a.user_id, a.reference,
            b.business_name, b.email applicant_email,
            u.name applicant_name
     FROM payments p
     JOIN applications a ON a.id = p.application_id
     JOIN businesses b ON b.id = a.business_id
     JOIN users u ON u.id = a.user_id
     WHERE p.id = ?'
);
$statement->execute([$paymentId]);
$payment = $statement->fetch();
if (!$payment || !$payment['submitted_at'] || $payment['status'] !== 'Pending') {
    flash('error', 'This payment is not awaiting verification.');
    redirect('admin/payments.php');
}
$returnPath = 'admin/payment.php?id=' . $paymentId;
if ($action === 'reject' && strlen($notes) < 5) {
    flash('error', 'Explain why the payment must be corrected.');
    redirect($returnPath);
}

try {
    $pdo->beginTransaction();
    if ($action === 'verify') {
        $receiptNumber = create_receipt_number($pdo);
        $update = $pdo->prepare("UPDATE payments SET status = 'Paid', receipt_number = ?, paid_at = NOW(), verified_by = ?, verified_at = NOW(), admin_notes = ? WHERE id = ? AND status = 'Pending'");
        $update->execute([$receiptNumber, $user['id'], $notes ?: null, $paymentId]);
        $message = 'Payment for application ' . $payment['reference'] . ' was verified. Receipt ' . $receiptNumber . ' is available. Your permit is now eligible for release.';
        $auditAction = 'verify_payment';
        $success = 'Payment verified and receipt ' . $receiptNumber . ' was issued.';
    } else {
        $update = $pdo->prepare("UPDATE payments SET status = 'Failed', verified_by = ?, verified_at = NOW(), admin_notes = ? WHERE id = ? AND status = 'Pending'");
        $update->execute([$user['id'], $notes, $paymentId]);
        $message = 'Payment for application ' . $payment['reference'] . ' needs correction: ' . $notes;
        $auditAction = 'reject_payment';
        $success = 'The applicant was asked to correct the payment submission.';
    }
    if ($update->rowCount() !== 1) throw new RuntimeException('Payment was already processed.');
    $eventKey='payment:'.$paymentId.':'.$payment['submitted_at'].':'.$action;
    notify_application($pdo,(int)$payment['application_id'],$eventKey,'Payment '.($action==='verify'?'verified':'requires correction'),$message);
    audit($pdo, (int) $user['id'], $auditAction, 'payment', (int) $paymentId);
    $pdo->commit();

    flash('success', $success . ' Notifications queued.');
} catch (Throwable) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash('error', 'The payment decision could not be saved.');
}
redirect($returnPath);
