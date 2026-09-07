<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/layout.php';
$user = require_role(['admin', 'treasurer']);
$paymentWorkflowReady = true;
try {
    $records = db()->query(
        "SELECT p.*, a.reference, a.id application_id, a.status application_status,
                b.business_name, u.name applicant_name
         FROM payments p
         JOIN applications a ON a.id = p.application_id
         JOIN businesses b ON b.id = a.business_id
         JOIN users u ON u.id = a.user_id
         ORDER BY
             FIELD(p.status, 'Pending', 'Failed', 'Paid', 'Refunded'),
             p.submitted_at DESC,
             p.id DESC"
    )->fetchAll();
} catch (PDOException) {
    $paymentWorkflowReady = false;
    $records = [];
}

$pendingCount = 0;
foreach ($records as $r) {
    if ($r['submitted_at'] && $r['status'] === 'Pending') $pendingCount++;
}

render_app_header('Payment Verification', 'payments');
?>
<div class="section-heading">
  <div>
    <p class="eyebrow">City Treasurer workflow</p>
    <h2>Payment verification</h2>
    <p class="muted">Review submitted references and confirmations before permit release.</p>
  </div>
  <?php if ($pendingCount > 0): ?>
    <span class="status revision"><?= $pendingCount ?> awaiting verification</span>
  <?php endif; ?>
</div>
<?php if (!$paymentWorkflowReady): ?><div class="form-alert form-alert-error">Import <strong>database/migrations/004_payment_workflow.sql</strong> to enable payment verification.</div><?php endif; ?>
<article class="panel">
  <div class="panel-header">
    <div><p class="eyebrow">Payment records</p><h3>Assessed applications</h3></div>
    <span class="document-count"><?= count($records) ?> records</span>
  </div>
  <?php if (!$records): ?><div class="empty-state"><p>No payment assessments yet.</p></div><?php endif; ?>
  <div class="payment-list">
    <?php foreach ($records as $record):
        $statusLabel = $record['submitted_at'] ? $record['status'] : 'Awaiting applicant';
        $statusClass = match ($statusLabel) {
            'Pending' => 'revision',
            'Paid'    => 'approved',
            'Failed'  => 'revision',
            default   => 'review',
        };
        $submittedLabel = $record['submitted_at']
            ? date('M j, Y g:i A', strtotime($record['submitted_at']))
            : 'Not yet submitted';
    ?>
      <a href="payment.php?id=<?= (int) $record['id'] ?>">
        <span class="payment-list-icon">₱</span>
        <span>
          <strong><?= e($record['business_name']) ?></strong>
          <small>
            <?= e($record['reference']) ?> · <?= e($record['applicant_name']) ?>
            · ₱<?= e(number_format((float) $record['amount'], 2)) ?>
            <br>Submitted: <?= e($submittedLabel) ?>
          </small>
        </span>
        <em class="status <?= e($statusClass) ?>"><?= e($statusLabel) ?></em>
      </a>
    <?php endforeach; ?>
  </div>
</article>
<?php render_app_footer(); ?>
