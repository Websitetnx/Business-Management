<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
$user = require_role('applicant');
$paymentWorkflowReady = true;
try {
    $statement = db()->prepare(
        "SELECT a.id application_id, a.reference, a.status application_status,
                a.submitted_at, b.business_name, a.permit_number,
                p.id payment_id, p.amount, p.status payment_status,
                p.submitted_at payment_submitted_at, p.paid_at
         FROM applications a
         JOIN businesses b ON b.id = a.business_id
         LEFT JOIN payments p ON p.application_id = a.id
         WHERE a.user_id = ?
         ORDER BY a.submitted_at DESC, a.id DESC"
    );
    $statement->execute([$user['id']]);
    $records = $statement->fetchAll();
} catch (PDOException) {
    $paymentWorkflowReady = false;
    $records = [];
}

// Separate approved/payment-eligible from others
$paymentRecords = [];
$otherRecords   = [];
foreach ($records as $r) {
    if (in_array($r['application_status'], ['Approved', 'Released'], true)) {
        $paymentRecords[] = $r;
    } else {
        $otherRecords[] = $r;
    }
}

render_app_header('Payments', 'payments');
?>
<div class="section-heading">
  <div>
    <p class="eyebrow">Permit transactions</p>
    <h2>Payments &amp; receipts</h2>
    <p class="muted">Submit assessed permit payments and monitor verification status.</p>
  </div>
</div>

<?php if (!$paymentWorkflowReady): ?>
  <div class="form-alert form-alert-error">The administrator must import <strong>database/migrations/004_payment_workflow.sql</strong> before payments can be used.</div>
<?php endif; ?>

<?php if (empty($records)): ?>
  <article class="panel empty-state">
    <h3>No applications yet</h3>
    <p>Submit a business permit application first. Payment becomes available after BPLO approval.</p>
    <a class="button" href="apply.php">Start new application</a>
  </article>

<?php elseif (empty($paymentRecords)): ?>
  <article class="panel">
    <div class="panel-header"><div><p class="eyebrow">Applications</p><h3>Awaiting approval</h3></div></div>
    <div class="form-alert" style="margin:0 0 16px">
      Payment becomes available after the BPLO reviews and approves your application.
      You will be notified by email and in-app once payment is ready.
    </div>
    <div class="payment-list">
      <?php foreach ($otherRecords as $record): ?>
        <a href="application.php?id=<?= (int) $record['application_id'] ?>">
          <span class="payment-list-icon">▤</span>
          <span>
            <strong><?= e($record['business_name']) ?></strong>
            <small><?= e($record['reference']) ?> · Submitted <?= e(date('M j, Y', strtotime($record['submitted_at']))) ?></small>
          </span>
          <em class="status <?= e(status_class($record['application_status'])) ?>"><?= e($record['application_status'] ?: 'Submitted') ?></em>
        </a>
      <?php endforeach; ?>
    </div>
  </article>

<?php else: ?>
  <article class="panel">
    <div class="panel-header">
      <div><p class="eyebrow">Approved applications</p><h3>Payment records</h3></div>
      <span class="document-count"><?= count($paymentRecords) ?> record<?= count($paymentRecords) !== 1 ? 's' : '' ?></span>
    </div>
    <div class="payment-list">
      <?php foreach ($paymentRecords as $record):
        // Determine link target and status label
        if ($record['application_status'] === 'Released' && $record['payment_id']) {
            $href        = 'receipt.php?id=' . (int) $record['payment_id'];
        } else {
            $href        = 'payment.php?application_id=' . (int) $record['application_id'];
        }
        $payStatus   = $record['payment_status'] ?? null;
        $statusLabel = match(true) {
            $record['application_status'] === 'Released' && $payStatus === 'Paid' => 'Permit Released',
            $payStatus === 'Paid'     => 'Paid — awaiting release',
            $payStatus === 'Pending'  => 'Verification pending',
            $payStatus === 'Failed'   => 'Correction needed',
            $record['payment_id'] === null => 'Awaiting assessment',
            default => $payStatus ?? 'Not submitted',
        };
        $statusClass = match(true) {
            $record['application_status'] === 'Released' && $payStatus === 'Paid' => 'approved',
            $payStatus === 'Paid'    => 'approved',
            $payStatus === 'Pending' => 'review',
            $payStatus === 'Failed'  => 'revision',
            default => 'review',
        };
      ?>
        <a href="<?= e(url($href)) ?>">
          <span class="payment-list-icon">₱</span>
          <span>
            <strong><?= e($record['business_name']) ?></strong>
            <small>
              <?= e($record['reference']) ?>
              <?= $record['payment_id'] ? ' · ₱' . e(number_format((float) $record['amount'], 2)) : ' · Fee not yet assessed' ?>
              <?php if ($record['payment_submitted_at']): ?>
                · Submitted <?= e(date('M j, Y', strtotime($record['payment_submitted_at']))) ?>
              <?php endif; ?>
            </small>
          </span>
          <em class="status <?= e($statusClass) ?>"><?= e($statusLabel) ?></em>
        </a>
      <?php endforeach; ?>
    </div>
  </article>

  <?php if (!empty($otherRecords)): ?>
    <article class="panel" style="margin-top:20px">
      <div class="panel-header"><div><p class="eyebrow">Other applications</p><h3>Pending review</h3></div></div>
      <div class="payment-list">
        <?php foreach ($otherRecords as $record): ?>
          <a href="application.php?id=<?= (int) $record['application_id'] ?>">
            <span class="payment-list-icon">▤</span>
            <span>
              <strong><?= e($record['business_name']) ?></strong>
              <small><?= e($record['reference']) ?> · Submitted <?= e(date('M j, Y', strtotime($record['submitted_at']))) ?></small>
            </span>
            <em class="status <?= e(status_class($record['application_status'])) ?>"><?= e($record['application_status'] ?: 'Submitted') ?></em>
          </a>
        <?php endforeach; ?>
      </div>
    </article>
  <?php endif; ?>
<?php endif; ?>

<?php render_app_footer(); ?>
