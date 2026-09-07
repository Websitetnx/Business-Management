<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
$user = require_role('applicant');
$pdo = db();

// Check for automated renewal alerts
$renewalAlerts = check_and_generate_renewal_notices($pdo, (int) $user['id']);

$countStatement = $pdo->prepare("SELECT COUNT(*) total,
    SUM(status = 'For Review') for_review,
    SUM(status IN ('Approved','Released')) approved,
    SUM(status = 'Needs Revision') needs_revision
    FROM applications WHERE user_id = ?");
$countStatement->execute([$user['id']]);
$counts = $countStatement->fetch() ?: [];

$recentStatement = $pdo->prepare('SELECT a.id, a.reference, a.permit_number, a.application_type, a.status, a.submitted_at, a.stage, a.requires_building_inspection, a.requires_electrical_inspection, a.requires_plumbing_inspection, b.business_name FROM applications a JOIN businesses b ON b.id = a.business_id WHERE a.user_id = ? ORDER BY a.submitted_at DESC LIMIT 5');
$recentStatement->execute([$user['id']]);
$recent = $recentStatement->fetchAll();

render_app_header('Dashboard', 'dashboard');
?>
<?php if (!empty($renewalAlerts)): ?>
  <?php foreach ($renewalAlerts as $alert): ?>
    <div class="renewal-alert-banner">
      <div class="renewal-alert-icon"><?= $alert['is_expired'] ? '⚠️' : '🔔' ?></div>
      <div class="renewal-alert-content">
        <strong><?= $alert['is_expired'] ? 'Business Permit Expired' : 'Annual Renewal Notice' ?>: <?= e($alert['business_name']) ?></strong>
        <p>Permit <strong><?= e($alert['permit_number']) ?></strong> <?= $alert['is_expired'] ? 'expired on ' . e($alert['expiry_date']) . '. Avoid late penalties by renewing now.' : 'will expire on ' . e($alert['expiry_date']) . ' (' . $alert['days_until_expiry'] . ' days left). Early renewal is open.' ?></p>
      </div>
      <a class="button button-warning" href="renew.php?permit_number=<?= urlencode($alert['permit_number']) ?>">Renew permit now →</a>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<div class="welcome-card">
  <div>
    <p class="eyebrow light">Welcome back, <?= e($user['name']) ?></p>
    <h2>Manage your permits without the long lines.</h2>
    <p>Submit requirements, monitor progress, receive automated renewal updates, and track predictions in one secure place.</p>
    <div class="welcome-actions">
      <a class="button button-light" href="apply.php">Start new application <span>→</span></a>
      <a class="button button-light-secondary" href="renew.php">Renew existing permit ↻</a>
    </div>
  </div>
  <div class="welcome-art" aria-hidden="true"><span>✓</span></div>
</div>

<div class="dashboard-search-wrap">
  <div class="search-wrapper">
    <span class="search-icon">🔍</span>
    <input type="search" class="quick-search-input" data-suggest-search placeholder="Personalized search: Find your application by Reference, Business Name, or type 'renew'..." aria-label="Personalized search">
  </div>
</div>

<div class="section-heading"><div><p class="eyebrow">At a glance</p><h2>Your permit activity</h2></div><p class="muted"><?= e(date('F j, Y')) ?></p></div>
<div class="stat-grid">
  <article class="stat-card"><span>▤</span><strong><?= (int) ($counts['total'] ?? 0) ?></strong><small>Total applications</small></article>
  <article class="stat-card"><span>◷</span><strong><?= (int) ($counts['for_review'] ?? 0) ?></strong><small>Under review</small></article>
  <article class="stat-card"><span>✓</span><strong><?= (int) ($counts['approved'] ?? 0) ?></strong><small>Approved permits</small></article>
  <article class="stat-card"><span>!</span><strong><?= (int) ($counts['needs_revision'] ?? 0) ?></strong><small>Needs attention</small></article>
</div>

<div class="content-grid">
  <article class="panel">
    <div class="panel-header"><div><p class="eyebrow">Applications</p><h3>Recent activity</h3></div><a href="track.php">Track with live timeline →</a></div>
    <?php if (!$recent): ?><p class="empty-state">No applications yet. Start your first application when your documents are ready.</p><?php endif; ?>
    <?php foreach ($recent as $application):
      $pred = estimate_application_timeline($pdo, $application);
    ?>
      <a class="application-item application-link" href="application.php?id=<?= (int) $application['id'] ?>">
        <div class="application-icon"><?= $application['application_type'] === 'Renewal' ? '↻' : '＋' ?></div>
        <div class="application-copy">
          <strong><?= e($application['business_name']) ?></strong>
          <small><?= e($application['reference']) ?> · <?= e(date('M j, Y', strtotime($application['submitted_at']))) ?>
            <?php if (!$pred['is_completed'] && $application['status'] === 'For Review'): ?>
              · <span class="prediction-pill">⏱ Est. <?= e($pred['estimated_range_days']) ?></span>
            <?php endif; ?>
          </small>
        </div>
        <span class="status <?= e(status_class($application['status'])) ?>"><?= e($application['status']) ?></span>
      </a>
    <?php endforeach; ?>
  </article>
  <aside class="panel checklist-panel">
    <div class="panel-header"><div><p class="eyebrow">Before you apply</p><h3>Standard requirements</h3></div></div>
    <ul class="checklist">
      <li><span>✓</span> DTI / SEC / CDA registration</li>
      <li><span>✓</span> BFP application form</li>
      <li><span>✓</span> BFP questionnaire</li>
      <li><span>✓</span> Signed consent form</li>
      <li><span>✓</span> Occupancy permit or affidavit alternative</li>
    </ul>
    <p class="hint">Accepted files: PDF, JPG, or PNG up to 5 MB per document.</p>
    <div class="assistant-prompt-box">
      <strong>Need assistance?</strong>
      <p>Ask our <strong>BPLO AI Assistant</strong> (bottom-right 💬) anytime for requirement checklists, fee estimates, and real-time status tracking!</p>
    </div>
  </aside>
</div>
<?php render_app_footer(); ?>
