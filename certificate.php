<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_role(['applicant', 'admin']);
$canManageApplications = can_manage_applications($user['role'] ?? '');
$applicationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$applicationId) {
    http_response_code(404);
    exit('Business certificate not found.');
}

$pdo = db();
$sql = "SELECT a.id, a.user_id, a.reference, a.permit_number, a.application_type,
               a.reviewed_at, a.approved_at, a.updated_at,
               b.business_name, b.business_type, b.organization_type, b.tin, b.address,
               u.name owner_name, p.assessment_breakdown, s.lgu_name
        FROM applications a
        JOIN businesses b ON b.id = a.business_id
        JOIN users u ON u.id = a.user_id
        LEFT JOIN payments p ON p.application_id = a.id AND p.status = 'Paid'
        LEFT JOIN permit_fee_settings s ON s.id = 1
        WHERE a.id = ? AND a.status = 'Released' AND a.permit_number IS NOT NULL";
$parameters = [$applicationId];
if (!$canManageApplications) {
    $sql .= ' AND a.user_id = ?';
    $parameters[] = $user['id'];
}

$statement = $pdo->prepare($sql);
$statement->execute($parameters);
$certificate = $statement->fetch();
if (!$certificate) {
    http_response_code(404);
    exit('Released business certificate not found.');
}

$releaseStatement = $pdo->prepare("SELECT h.created_at, u.name released_by_name
    FROM application_status_history h
    LEFT JOIN users u ON u.id = h.changed_by
    WHERE h.application_id = ? AND h.status = 'Released'
    ORDER BY h.created_at ASC, h.id ASC
    LIMIT 1");
$releaseStatement->execute([$applicationId]);
$release = $releaseStatement->fetch() ?: [];

$issuedAtValue = $release['created_at']
    ?? $certificate['reviewed_at']
    ?? $certificate['approved_at']
    ?? $certificate['updated_at'];
$issuedAt = new DateTimeImmutable((string) $issuedAtValue);
$validUntil = $issuedAt->setDate((int) $issuedAt->format('Y'), 12, 31)->setTime(23, 59, 59);
$isExpired = new DateTimeImmutable() > $validUntil;

$assessment = decoded_fee_breakdown($certificate['assessment_breakdown'] ?? null);
$lguName = trim((string) ($assessment['lgu_name'] ?? $certificate['lgu_name'] ?? ''));
if ($lguName === '') {
    $lguName = 'Local Government Unit';
}
$certificateType = $certificate['application_type'] === 'Renewal'
    ? 'Business Permit Renewal Certificate'
    : 'Business Permit Certificate';
$backPath = $canManageApplications
    ? 'admin/review.php?id=' . $applicationId
    : 'application.php?id=' . $applicationId;

render_app_header('Business Permit Certificate', $canManageApplications ? 'review' : 'track');
?>
<div class="certificate-toolbar">
  <a class="button button-secondary" href="<?= e(url($backPath)) ?>">&larr; Back to application</a>
  <button class="button" type="button" onclick="window.print()">Print / Save as PDF</button>
</div>

<article class="certificate-card" aria-labelledby="certificateTitle">
  <div class="certificate-frame">
    <header class="certificate-header">
      <img src="<?= e(url('assets/logo-light.png')) ?>" alt="PERMIT">
      <div>
        <p>Republic of the Philippines</p>
        <h2><?= e($lguName) ?></h2>
        <span>Business Permits and Licensing Office</span>
      </div>
    </header>

    <div class="certificate-heading">
      <p>Official Electronic Record</p>
      <h1 id="certificateTitle"><?= e($certificateType) ?></h1>
      <span class="certificate-validity <?= $isExpired ? 'expired' : '' ?>"><?= $isExpired ? 'Expired record' : 'Valid permit' ?></span>
    </div>

    <section class="certificate-declaration">
      <p>This is to certify that</p>
      <strong><?= e($certificate['business_name']) ?></strong>
      <p>owned and operated by <b><?= e($certificate['owner_name']) ?></b>, with business address at</p>
      <address><?= nl2br(e($certificate['address'])) ?></address>
      <p>is authorized to operate as a <b><?= e($certificate['business_type']) ?></b>, subject to applicable national and local laws, ordinances, and permit conditions.</p>
    </section>

    <dl class="certificate-details">
      <div><dt>Business permit no.</dt><dd><?= e($certificate['permit_number']) ?></dd></div>
      <div><dt>Application reference</dt><dd><?= e($certificate['reference']) ?></dd></div>
      <div><dt>Organization</dt><dd><?= e($certificate['organization_type']) ?></dd></div>
      <div><dt>Tax identification no.</dt><dd><?= e($certificate['tin']) ?></dd></div>
      <div><dt>Date issued</dt><dd><time datetime="<?= e($issuedAt->format('Y-m-d')) ?>"><?= e($issuedAt->format('F j, Y')) ?></time></dd></div>
      <div><dt>Valid until</dt><dd><time datetime="<?= e($validUntil->format('Y-m-d')) ?>"><?= e($validUntil->format('F j, Y')) ?></time></dd></div>
    </dl>

    <footer class="certificate-footer">
      <div class="certificate-issued-by">
        <span>Released by</span>
        <strong><?= e($release['released_by_name'] ?? 'Authorized BPLO Officer') ?></strong>
        <small>Authorized releasing officer</small>
      </div>
      <div class="certificate-verification">
        <strong>System-generated certificate</strong>
        <p>Verify this record in PERMIT using permit number <b><?= e($certificate['permit_number']) ?></b> or application reference <b><?= e($certificate['reference']) ?></b>.</p>
      </div>
    </footer>
  </div>
</article>
<?php render_app_footer(); ?>
