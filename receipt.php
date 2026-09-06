<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
$user = require_login();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(404); exit('Receipt not found.'); }
$statement = db()->prepare("SELECT p.*, a.user_id, a.reference application_reference, a.permit_number, b.business_name, b.address, u.name applicant_name, v.name verified_by_name FROM payments p JOIN applications a ON a.id = p.application_id JOIN businesses b ON b.id = a.business_id JOIN users u ON u.id = a.user_id LEFT JOIN users v ON v.id = p.verified_by WHERE p.id = ? AND p.status = 'Paid'");
$statement->execute([$id]);
$payment = $statement->fetch();
if (!$payment) { http_response_code(404); exit('Verified receipt not found.'); }
if (!can_manage_payments($user['role'] ?? '') && (int) $payment['user_id'] !== (int) $user['id']) { http_response_code(403); exit('Access denied.'); }
$feeBreakdown = decoded_fee_breakdown($payment['assessment_breakdown'] ?? null);
$canManagePayments = can_manage_payments($user['role'] ?? '');
render_app_header('Payment Receipt', 'payments');
?>
<div class="receipt-toolbar"><a class="button button-secondary" href="<?= e(url($canManagePayments ? 'admin/payment.php?id=' . $payment['id'] : 'payments.php')) ?>">← Back</a><button class="button" type="button" onclick="window.print()">Print receipt</button></div>
<article class="panel receipt-card"><header><img src="<?= e(url('assets/logo-light.png')) ?>" alt="PERMIT"><div><p>City Government Services</p><h2>Permit Payment Receipt</h2><span><?= e($payment['receipt_number']) ?></span></div></header><div class="receipt-paid">PAID</div><dl><div><dt>Application reference</dt><dd><?= e($payment['application_reference']) ?></dd></div><div><dt>Permit number</dt><dd><?= e($payment['permit_number'] ?: 'Pending release') ?></dd></div><div><dt>Business</dt><dd><?= e($payment['business_name']) ?></dd></div><div><dt>Applicant</dt><dd><?= e($payment['applicant_name']) ?></dd></div><div><dt>Payer</dt><dd><?= e($payment['payer_name']) ?></dd></div><div><dt>Payment method</dt><dd><?= e($payment['payment_method']) ?></dd></div><div><dt>Payment reference</dt><dd><?= e($payment['payment_reference']) ?></dd></div><div><dt>Date verified</dt><dd><?= e(date('F j, Y g:i A', strtotime($payment['paid_at']))) ?></dd></div></dl><?php if ($feeBreakdown): ?><div class="receipt-breakdown"><strong>Assessment breakdown</strong><dl><?php foreach ($feeBreakdown['components'] as $component): ?><div><dt><?= e($component['label']) ?></dt><dd>₱<?= e(number_format((float) $component['amount'], 2)) ?></dd></div><?php endforeach; ?></dl></div><?php endif; ?><dl><div class="receipt-total"><dt>Amount paid</dt><dd>₱<?= e(number_format((float) $payment['amount'], 2)) ?></dd></div></dl><footer><p>Verified by <?= e($payment['verified_by_name'] ?: 'Authorized administrator') ?>.</p><small>This system-generated receipt confirms the payment recorded in PERMIT. Retain it with any official City Treasurer receipt.</small></footer></article>
<?php render_app_footer(); ?>
