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
    $notice = $pdo->prepare('INSERT INTO notifications (user_id, application_id, message) VALUES (?, ?, ?)');
    $notice->execute([$payment['user_id'], $payment['application_id'], $message]);
    audit($pdo, (int) $user['id'], $auditAction, 'payment', (int) $paymentId);
    $pdo->commit();

    // ── Send email notification to applicant (non-blocking after commit) ──
    try {
        $protocol    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host        = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $appName     = 'PERMIT';
        $displayName = trim((string) $payment['applicant_name']) ?: 'Applicant';
        $safeName    = htmlspecialchars($displayName,                  ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeBiz     = htmlspecialchars((string) $payment['business_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeRef     = htmlspecialchars((string) $payment['reference'],    ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeApp     = htmlspecialchars($appName,                          ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        if ($action === 'verify') {
            $receiptUrl    = $protocol . '://' . $host . url('receipt.php?id=' . $paymentId);
            $appUrl        = $protocol . '://' . $host . url('application.php?id=' . (int) $payment['application_id']);
            $safeReceiptUrl = htmlspecialchars($receiptUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeAppUrl     = htmlspecialchars($appUrl,     ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeReceipt    = htmlspecialchars((string) $receiptNumber, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $htmlBody = '<!doctype html><html><body style="font-family:Arial,sans-serif;color:#172033;max-width:600px;margin:0 auto">'
                . '<div style="background:linear-gradient(135deg,#075f80,#087b88);padding:32px 36px;border-radius:12px 12px 0 0">'
                . '<h2 style="margin:0;color:#fff;font-size:22px">&#10003; Payment Verified!</h2>'
                . '</div>'
                . '<div style="background:#fff;padding:32px 36px;border:1px solid #dce6eb;border-top:0;border-radius:0 0 12px 12px">'
                . '<p>Hello <strong>' . $safeName . '</strong>,</p>'
                . '<p>Your permit fee payment for <strong>' . $safeBiz . '</strong> has been verified by the City Treasurer.</p>'
                . '<div style="background:#f3f7f8;border-left:4px solid #075f80;padding:16px 20px;margin:20px 0;border-radius:0 8px 8px 0">'
                . '<span style="color:#647687">Application Reference: <strong style="color:#172033">' . $safeRef . '</strong></span><br>'
                . '<span style="color:#647687">Official Receipt No.: <strong style="color:#172033">' . $safeReceipt . '</strong></span>'
                . '</div>'
                . '<p>Your permit is now <strong>eligible for release</strong>. The BPLO will review and release it shortly. '
                . 'You will receive another notification when your permit certificate is ready.</p>'
                . '<div style="text-align:center;margin:28px 0">'
                . '<a href="' . $safeReceiptUrl . '" style="display:inline-block;background:#075f80;color:#fff;text-decoration:none;padding:14px 28px;border-radius:8px;font-weight:700;font-size:15px;margin:4px">View Receipt</a>'
                . ' <a href="' . $safeAppUrl . '" style="display:inline-block;background:#f3f7f8;color:#172033;text-decoration:none;padding:14px 28px;border-radius:8px;font-weight:700;font-size:15px;margin:4px;border:1px solid #dce6eb">Track Application</a>'
                . '</div>'
                . '<hr style="border:0;border-top:1px solid #dce6eb;margin:24px 0">'
                . '<p style="font-size:12px;color:#647687">Automated message from <strong>' . $safeApp . '</strong>. Do not reply.</p>'
                . '</div></body></html>';

            $textBody = "Hello {$displayName},\n\n"
                . "Your permit fee payment for {$payment['business_name']} has been verified.\n\n"
                . "Application Reference: {$payment['reference']}\n"
                . "Official Receipt No.: {$receiptNumber}\n\n"
                . "Your permit is now eligible for release. The BPLO will release your certificate shortly.\n\n"
                . "View receipt: {$receiptUrl}\n"
                . "Track application: {$appUrl}\n\n{$appName}";

            send_app_mail([
                'to_email'  => (string) $payment['applicant_email'],
                'to_name'   => $displayName,
                'subject'   => 'Payment verified — Receipt ' . $receiptNumber,
                'html_body' => $htmlBody,
                'text_body' => $textBody,
            ]);
        } else {
            // Rejection email
            $paymentUrl  = $protocol . '://' . $host . url('payment.php?application_id=' . (int) $payment['application_id']);
            $safePaymentUrl = htmlspecialchars($paymentUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeNotes      = htmlspecialchars($notes,      ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $htmlBody = '<!doctype html><html><body style="font-family:Arial,sans-serif;color:#172033;max-width:600px;margin:0 auto">'
                . '<div style="background:#c43d4b;padding:32px 36px;border-radius:12px 12px 0 0">'
                . '<h2 style="margin:0;color:#fff;font-size:22px">&#9888; Payment Correction Required</h2>'
                . '</div>'
                . '<div style="background:#fff;padding:32px 36px;border:1px solid #dce6eb;border-top:0;border-radius:0 0 12px 12px">'
                . '<p>Hello <strong>' . $safeName . '</strong>,</p>'
                . '<p>The payment submission for <strong>' . $safeBiz . '</strong> (Reference: ' . $safeRef . ') requires correction.</p>'
                . '<div style="background:#fff5f5;border-left:4px solid #c43d4b;padding:16px 20px;margin:20px 0;border-radius:0 8px 8px 0">'
                . '<strong>Reason from City Treasurer:</strong><br><br>' . $safeNotes
                . '</div>'
                . '<p>Please log in and resubmit the correct payment details.</p>'
                . '<div style="text-align:center;margin:28px 0">'
                . '<a href="' . $safePaymentUrl . '" style="display:inline-block;background:#075f80;color:#fff;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:700;font-size:15px">Resubmit Payment &#8594;</a>'
                . '</div>'
                . '<hr style="border:0;border-top:1px solid #dce6eb;margin:24px 0">'
                . '<p style="font-size:12px;color:#647687">Automated message from <strong>' . $safeApp . '</strong>. Do not reply.</p>'
                . '</div></body></html>';

            $textBody = "Hello {$displayName},\n\n"
                . "The payment submission for {$payment['business_name']} (Ref: {$payment['reference']}) needs correction.\n\n"
                . "Reason: {$notes}\n\n"
                . "Log in and resubmit: {$paymentUrl}\n\n{$appName}";

            send_app_mail([
                'to_email'  => (string) $payment['applicant_email'],
                'to_name'   => $displayName,
                'subject'   => 'Payment correction required — ' . $payment['reference'],
                'html_body' => $htmlBody,
                'text_body' => $textBody,
            ]);
        }
    } catch (Throwable) {
        // Email failure must never roll back a committed payment decision
    }

    flash('success', $success);
} catch (Throwable) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash('error', 'The payment decision could not be saved.');
}
redirect($returnPath);
