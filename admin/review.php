<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/layout.php';
$user = require_role('admin');
$pdo = db();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(404); exit('Application not found.'); }

$statement = $pdo->prepare('SELECT a.*, b.business_name, b.business_type, b.organization_type, b.tin, b.contact, b.email, b.address, b.latitude, b.longitude, b.location_accuracy_m, b.location_captured_at, u.name owner_name FROM applications a JOIN businesses b ON b.id = a.business_id JOIN users u ON u.id = a.user_id WHERE a.id = ?');
$statement->execute([$id]);
$application = $statement->fetch();
if (!$application) { http_response_code(404); exit('Application not found.'); }

$paymentWorkflowReady = true;
try {
    $paymentStatement = $pdo->prepare('SELECT * FROM payments WHERE application_id = ? LIMIT 1');
    $paymentStatement->execute([$id]);
    $payment = $paymentStatement->fetch() ?: null;
} catch (PDOException) {
    $paymentWorkflowReady = false;
    $payment = null;
}

$feeFormulaInstalled = true;
try {
    $feeAssessment = permit_fee_assessment($pdo, $application);
} catch (PDOException) {
    $feeFormulaInstalled = false;
    $feeAssessment = null;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $decision = (string) ($_POST['decision'] ?? '');
    $notes = trim((string) ($_POST['admin_notes'] ?? ''));
    $allowed = ['For Review', 'Needs Revision', 'Approved', 'Released', 'Rejected'];
    $assessedAmountRaw = $feeAssessment ? (string) $feeAssessment['total'] : str_replace([',', '₱', ' '], '', (string) ($_POST['assessed_amount'] ?? ''));
    if (!in_array($decision, $allowed, true)) $errors[] = 'Select a valid decision.';
    if (in_array($decision, ['Needs Revision', 'Rejected'], true) && strlen($notes) < 5) $errors[] = 'Explain what the applicant must correct or why the application was rejected.';
    if ($decision === 'Approved') {
        if (!$paymentWorkflowReady) $errors[] = 'Import database/migrations/004_payment_workflow.sql before approving and assessing fees.';
        if ($feeFormulaInstalled && !$feeAssessment) $errors[] = 'Configure the official permit fee schedule before approving this application.';
        if (!is_numeric($assessedAmountRaw) || (float) $assessedAmountRaw <= 0 || (float) $assessedAmountRaw > 99999999.99) $errors[] = 'Enter a valid assessed permit fee before approval.';
    }
    if ($decision === 'Released' && (!$payment || $payment['status'] !== 'Paid')) $errors[] = 'Payment must be verified as paid before releasing the permit.';
    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $permitNumber = $application['permit_number'];
            if (in_array($decision, ['Approved', 'Released'], true) && !$permitNumber) $permitNumber = create_permit_number($pdo);
            $stage = application_stage($decision);
            $update = $pdo->prepare('UPDATE applications SET status = ?, stage = ?, admin_notes = ?, permit_number = ?, reviewed_at = NOW(), approved_at = CASE WHEN ? IN (\'Approved\', \'Released\') THEN COALESCE(approved_at, NOW()) ELSE approved_at END WHERE id = ?');
            $update->execute([$decision, $stage, $notes ?: null, $permitNumber ?: null, $decision, $id]);
            if ($decision === 'Approved') {
                if ($feeAssessment) {
                    $assessment = $pdo->prepare("INSERT INTO payments (application_id, amount, assessment_breakdown, assessed_by, assessed_at, status) VALUES (?, ?, ?, ?, NOW(), 'Pending') ON DUPLICATE KEY UPDATE amount = IF(status = 'Paid', amount, VALUES(amount)), assessment_breakdown = IF(status = 'Paid', assessment_breakdown, VALUES(assessment_breakdown)), assessed_by = IF(status = 'Paid', assessed_by, VALUES(assessed_by)), assessed_at = IF(status = 'Paid', assessed_at, NOW())");
                    $assessment->execute([$id, number_format((float) $assessedAmountRaw, 2, '.', ''), json_encode($feeAssessment, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $user['id']]);
                } else {
                    $assessment = $pdo->prepare("INSERT INTO payments (application_id, amount, status) VALUES (?, ?, 'Pending') ON DUPLICATE KEY UPDATE amount = IF(status = 'Paid', amount, VALUES(amount))");
                    $assessment->execute([$id, number_format((float) $assessedAmountRaw, 2, '.', '')]);
                }
            }
            record_status($pdo, (int) $id, $decision, (int) $user['id'], $notes);
            $message = $decision === 'Approved'
                ? 'Application ' . $application['reference'] . ' was approved. Assessed permit fee: ₱' . number_format((float) $assessedAmountRaw, 2) . '. Submit payment from your application page.'
                : ($decision === 'Released'
                    ? 'Business permit certificate ready for ' . $application['business_name'] . '. Permit no. ' . ($permitNumber ?: $application['permit_number']) . ' has been released. View and download your certificate now.'
                    : 'Application ' . $application['reference'] . ' was updated to ' . $decision . '.');
            $notice = $pdo->prepare('INSERT INTO notifications (user_id, application_id, message) VALUES (?, ?, ?)');
            $notice->execute([$application['user_id'], $id, $message]);
            audit($pdo, (int) $user['id'], 'update_status_' . strtolower(str_replace(' ', '_', $decision)), 'application', (int) $id);
            $pdo->commit();

            // Send release email to applicant after commit
            if ($decision === 'Released') {
                try {
                    require_once dirname(__DIR__) . '/includes/mailer.php';
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $certUrl = $protocol . '://' . $host . url('certificate.php?id=' . $id);
                    send_permit_released_email(
                        (string) $application['email'],
                        (string) $application['owner_name'],
                        (string) $application['business_name'],
                        (string) ($permitNumber ?: $application['permit_number']),
                        (string) $application['reference'],
                        $certUrl
                    );
                } catch (Throwable) {
                    // Email failure must never block the release — certificate is still accessible in-app
                }
            }

            flash('success', 'The application status was updated to ' . $decision . ($decision === 'Released' ? '. A notification email has been sent to the applicant.' : '') . '.');
            redirect('admin/review.php?id=' . $id);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'The decision could not be saved. ' . $e->getMessage();
        }
    }
}

$aiTablesReady = true;
try {
    $documents = $pdo->prepare('SELECT d.id, d.document_type, d.original_name, d.file_size, s.scan_status, s.detected_document_type, s.matches_expected_type, s.quality_score, s.confidence_score, s.extracted_fields, s.issues, s.summary, s.requires_human_review, s.model, s.error_message, s.scanned_at FROM application_documents d LEFT JOIN document_ai_scans s ON s.document_id = d.id WHERE d.application_id = ? ORDER BY d.id');
    $documents->execute([$id]);
} catch (PDOException) {
    $aiTablesReady = false;
    $documents = $pdo->prepare('SELECT id, document_type, original_name, file_size FROM application_documents WHERE application_id = ? ORDER BY id');
    $documents->execute([$id]);
}
$definitions = document_definitions();
$autoScannedCount = 0;
if ($aiTablesReady) {
    try {
        $autoScanStatement = $pdo->prepare("SELECT COUNT(DISTINCT al.entity_id) FROM audit_logs al JOIN application_documents d ON d.id = al.entity_id WHERE d.application_id = ? AND al.action IN ('ai_scan_document', 'ai_auto_scan_document') AND al.entity_type = 'application_document' AND al.user_id = ?");
        $autoScanStatement->execute([$id, $application['user_id']]);
        $autoScannedCount = (int) $autoScanStatement->fetchColumn();
    } catch (PDOException) {
        $autoScannedCount = 0;
    }
}
render_app_header('Review Application', 'review');
$prediction = estimate_application_timeline($pdo, $application);
?>
<div class="section-heading"><div><p class="eyebrow"><?= e($application['reference']) ?></p><h2><?= e($application['business_name']) ?></h2><p class="muted"><?= e($application['owner_name']) ?> · <?= e($application['application_type']) ?> application</p></div><span class="status <?= e(status_class($application['status'])) ?>"><?= e($application['status']) ?></span></div>
<?php if ($autoScannedCount > 0): ?><div class="auto-scan-banner"><span>AI</span><div><strong>Pre-scanned automatically on upload</strong><small><?= $autoScannedCount ?> eligible document<?= $autoScannedCount === 1 ? '' : 's' ?> already have automated results below. Open each original before deciding; manual re-scans remain available.</small></div></div><?php endif; ?>
<div class="prediction-card">
  <div class="prediction-header">
    <div class="prediction-title-group">
      <span class="prediction-badge">Predictive Analytics</span>
      <h4>Turnaround Time Estimate & Queue Load</h4>
    </div>
    <div class="prediction-accuracy-badge">
      <span class="accuracy-score"><?= e((string)$prediction['confidence_percentage']) ?>%</span>
      <small>Prediction Confidence</small>
    </div>
  </div>
  <div class="prediction-body-grid">
    <div class="prediction-metric">
      <small>Estimated Approval Window</small>
      <strong><?= $prediction['is_completed'] ? 'Completed' : e($prediction['estimated_range_days']) ?></strong>
      <span>Target: <?= e($prediction['estimated_approval_date']) ?></span>
    </div>
    <div class="prediction-metric">
      <small>Queue Backlog Position</small>
      <strong>#<?= e((string)$prediction['queue_position']) ?></strong>
      <span>of <?= e((string)$prediction['total_in_queue']) ?> pending in queue</span>
    </div>
    <div class="prediction-metric">
      <small>Document Completeness</small>
      <strong><?= e((string)$prediction['completeness']['completeness_percent']) ?>%</strong>
      <span><?= e((string)$prediction['completeness']['required_uploaded']) ?> of <?= e((string)$prediction['completeness']['total_required']) ?> key requirements uploaded</span>
    </div>
  </div>
</div>
<?php if ($errors): ?><div class="form-alert form-alert-error"><ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if (!$paymentWorkflowReady): ?><div class="form-alert form-alert-error">Import <strong>database/migrations/004_payment_workflow.sql</strong> to enable fee assessment, payment verification, and receipts.</div><?php endif; ?>
<?php if (!$feeFormulaInstalled): ?><div class="form-alert form-alert-error">Import <strong>database/migrations/005_fee_assessment_formula.sql</strong> to collect capital/inspection information and calculate permit fees.</div><?php elseif (!$feeAssessment): ?><div class="form-alert form-alert-error">The automatic assessment is not ready. <a href="fee-settings.php">Configure the official LGU fee schedule</a> and confirm that the application contains its tax basis.</div><?php endif; ?>
<div class="content-grid review-layout"><article class="panel"><div class="panel-header"><div><p class="eyebrow">Applicant record</p><h3>Business information</h3></div><a href="<?= e(url('application.php?id=' . $id)) ?>">Full timeline</a></div><div class="detail-list detail-list-padded"><div><small>Applicant</small><strong><?= e($application['owner_name']) ?></strong></div><div><small>TIN</small><strong><?= e($application['tin']) ?></strong></div><div><small>Business type</small><strong><?= e($application['business_type']) ?></strong></div><div><small>Organization</small><strong><?= e($application['organization_type']) ?></strong></div><div><small><?= $application['application_type'] === 'New' ? 'Declared capital' : 'Previous-year gross sales' ?></small><strong>₱<?= e(number_format((float) ($application['application_type'] === 'New' ? ($application['declared_capital'] ?? 0) : ($application['gross_sales'] ?? 0)), 2)) ?></strong></div><div><small>Additional inspections</small><strong><?= e(implode(', ', array_filter([!empty($application['requires_building_inspection']) ? 'Building' : null, !empty($application['requires_electrical_inspection']) ? 'Electrical' : null, !empty($application['requires_plumbing_inspection']) ? 'Plumbing' : null])) ?: 'None selected') ?></strong></div><div><small>Contact</small><strong><?= e($application['contact']) ?></strong></div><div><small>Email</small><strong><?= e($application['email']) ?></strong></div><div class="detail-wide"><small>Address</small><strong><?= e($application['address']) ?></strong></div><div class="detail-wide business-location"><small>Applicant-captured location</small><?php if ($application['latitude'] !== null && $application['longitude'] !== null): ?><strong><?= e(number_format((float) $application['latitude'], 7)) ?>, <?= e(number_format((float) $application['longitude'], 7)) ?></strong><span><?= $application['location_accuracy_m'] !== null ? 'Accuracy ±' . e(number_format((float) $application['location_accuracy_m'], 0)) . ' m · ' : '' ?><a href="<?= e(openstreetmap_url($application['latitude'], $application['longitude'])) ?>" target="_blank" rel="noopener">Verify on map ↗</a></span><?php else: ?><strong>Not provided</strong><span>Verify using the written address and submitted documents.</span><?php endif; ?></div></div></article>
<aside class="panel decision-panel">
  <div class="panel-header"><div><p class="eyebrow">LGU decision</p><h3>Review &amp; decision</h3></div></div>

  <?php if ($application['status'] === 'Released'): ?>
    <div class="certificate-admin-action">
      <div><strong>Business certificate issued</strong><small>Permit <?= e($application['permit_number']) ?></small></div>
      <a class="button button-secondary" href="<?= e(url('certificate.php?id=' . (int) $id)) ?>">View certificate</a>
    </div>
  <?php elseif ($application['status'] === 'Approved'): ?>
    <div class="form-alert" style="margin-bottom:16px">
      <strong>&#10003; Application approved.</strong>
      The applicant has been notified and can now submit permit payment.
      This decision panel will unlock &ldquo;Released&rdquo; once payment is verified by the Treasurer.
    </div>
  <?php endif; ?>

  <?php if ($feeAssessment): ?>
    <div class="assessment-preview">
      <div><span>Calculated amount due</span><strong>&#8369;<?= e(number_format((float) $feeAssessment['total'], 2)) ?></strong><small><?= e($feeAssessment['tax_basis_label']) ?> &middot; &#8369;<?= e(number_format((float) $feeAssessment['tax_basis'], 2)) ?></small></div>
      <details><summary>View fee breakdown</summary><dl>
        <?php foreach ($feeAssessment['components'] as $component): ?><div><dt><?= e($component['label']) ?></dt><dd>&#8369;<?= e(number_format((float) $component['amount'], 2)) ?></dd></div><?php endforeach; ?>
      </dl></details>
    </div>
  <?php endif; ?>

  <form method="post" class="decision-form" id="decisionForm">
    <?= csrf_field() ?>
    <input type="hidden" name="application_id" value="<?= (int) $id ?>">

    <label class="field">Decision
      <select name="decision" required id="decisionSelect">
        <?php foreach (['For Review', 'Needs Revision', 'Approved', 'Released', 'Rejected'] as $option):
          $isSelected  = $application['status'] === $option ? 'selected' : '';
          $isDisabled  = ($option === 'Released' && (!$payment || $payment['status'] !== 'Paid')) ? 'disabled' : '';
          $suffix = '';
          if ($option === 'Approved') $suffix = ' — notifies applicant to pay';
          if ($option === 'Released') $suffix = (!$payment || $payment['status'] !== 'Paid') ? ' (locked — awaiting payment)' : ' — issues certificate';
        ?>
          <option value="<?= e($option) ?>" <?= $isSelected ?> <?= $isDisabled ?>><?= e($option . $suffix) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <div id="feeField" <?= !in_array($application['status'], ['', 'For Review', 'Needs Revision', 'Approved'], true) ? 'hidden' : '' ?>>
      <label class="field">Assessed permit fee
        <small><?= $feeAssessment ? 'Auto-calculated from LGU schedule' : 'Required when approving' ?></small>
        <input name="assessed_amount" inputmode="decimal"
               value="<?= e((string) ($feeAssessment['total'] ?? $_POST['assessed_amount'] ?? $payment['amount'] ?? '')) ?>"
               placeholder="0.00" <?= $feeAssessment ? 'readonly' : '' ?>>
      </label>
    </div>

    <label class="field">BPLO notes
      <textarea name="admin_notes" rows="5" placeholder="Instructions, findings, or decision notes"><?= e($_POST['admin_notes'] ?? $application['admin_notes']) ?></textarea>
    </label>

    <?php if (!$payment || $payment['status'] !== 'Paid'): ?>
      <p class="release-guard">&#128274; Permit release is locked until payment is verified by the City Treasurer.</p>
    <?php endif; ?>

    <button class="button" type="submit" id="decisionSubmit">Save decision</button>
  </form>

  <script>
  (function () {
    var select    = document.getElementById('decisionSelect');
    var feeField  = document.getElementById('feeField');
    var feeInput  = feeField ? feeField.querySelector('input[name="assessed_amount"]') : null;
    var submitBtn = document.getElementById('decisionSubmit');
    var labels = {
      'For Review':    'Save decision',
      'Needs Revision':'Request revision',
      'Approved':      'Approve & notify applicant',
      'Released':      'Release permit & issue certificate',
      'Rejected':      'Reject application'
    };
    function update() {
      var val = select.value;
      var isApproved = (val === 'Approved');
      feeField.hidden = !isApproved;
      if (feeInput) feeInput.disabled = !isApproved;
      submitBtn.textContent = labels[val] || 'Save decision';
    }
    select.addEventListener('change', update);
    update();
  })();
  </script>
</aside>
</div>
<?php if ($paymentWorkflowReady && $payment): ?>
<article class="panel admin-payment-panel"><div class="panel-header"><div><p class="eyebrow">City Treasurer workflow</p><h3>Payment verification</h3></div><span class="status <?= e(payment_status_class($payment['status'])) ?>"><?= e($payment['status']) ?></span></div><div class="admin-payment-grid"><div class="payment-summary"><dl><div><dt>Assessed amount</dt><dd>₱<?= e(number_format((float) $payment['amount'], 2)) ?></dd></div><div><dt>Method</dt><dd><?= e($payment['payment_method'] ?: 'Not submitted') ?></dd></div><div><dt>Payer</dt><dd><?= e($payment['payer_name'] ?: '—') ?></dd></div><div><dt>Reference</dt><dd><?= e($payment['payment_reference'] ?: '—') ?></dd></div><div><dt>Submitted</dt><dd><?= $payment['submitted_at'] ? e(date('M j, Y g:i A', strtotime($payment['submitted_at']))) : 'Awaiting applicant' ?></dd></div></dl><?php if ($payment['proof_stored_name']): ?><a class="button button-secondary" href="<?= e(url('payment-proof.php?id=' . $payment['id'])) ?>" target="_blank" rel="noopener">View payment confirmation ↗</a><?php endif; ?><?php if ($payment['status'] === 'Paid'): ?><a class="button" href="<?= e(url('receipt.php?id=' . $payment['id'])) ?>">View receipt</a><?php endif; ?></div>
  <div class="payment-actions"><?php if ($payment['submitted_at'] && $payment['status'] === 'Pending'): ?><form method="post" action="payment-action.php"><?= csrf_field() ?><input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>"><input type="hidden" name="action" value="verify"><label class="field">Verification note<textarea name="admin_notes" rows="3" placeholder="Treasury verification note"></textarea></label><button class="button" type="submit">Verify payment and issue receipt</button></form><form method="post" action="payment-action.php" class="reject-payment-form"><?= csrf_field() ?><input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>"><input type="hidden" name="action" value="reject"><label class="field">Reason for rejection<textarea name="admin_notes" rows="3" required placeholder="Explain what must be corrected"></textarea></label><button class="button button-danger" type="submit">Reject payment proof</button></form><?php elseif (!$payment['submitted_at']): ?><div class="empty-state"><p>Waiting for the applicant to submit payment details.</p></div><?php elseif ($payment['status'] === 'Failed'): ?><div class="form-alert form-alert-error"><strong>Correction requested:</strong> <?= e($payment['admin_notes']) ?></div><?php else: ?><div class="payment-success compact"><span>✓</span><div><strong>Payment verified</strong><p><?= e($payment['receipt_number']) ?></p></div></div><?php endif; ?></div></div></article>
<?php endif; ?>
<article class="panel document-panel ai-document-panel">
  <div class="panel-header"><div><p class="eyebrow">AI-assisted verification</p><h3>Submitted requirements</h3><p class="muted">AI findings are advisory. Open and verify every original document before making a decision.</p></div><span class="ai-provider">OpenAI · <?= e(openai_settings()['model']) ?></span></div>
  <div class="ai-privacy-note"><strong>Privacy notice:</strong> Scanning sends the selected document to the configured OpenAI API project with response storage disabled. Confirm that your LGU is authorized to process it. Medical-result scanning is blocked unless explicitly enabled by the server administrator.</div>
  <?php if (!$aiTablesReady): ?><div class="form-alert form-alert-error ai-migration-alert">Import <strong>database/migrations/002_ai_features.sql</strong> to enable AI scanning.</div><?php endif; ?>
  <div class="ai-document-list">
    <?php foreach ($documents->fetchAll() as $document):
      $label = $definitions[$document['document_type']][0] ?? $document['document_type'];
      $issues = isset($document['issues']) ? (json_decode((string) $document['issues'], true) ?: []) : [];
      $fields = isset($document['extracted_fields']) ? (json_decode((string) $document['extracted_fields'], true) ?: []) : [];
      $sensitiveBlocked = $document['document_type'] === 'health_results_doc' && !openai_settings()['allow_sensitive_documents'];
    ?>
      <section class="ai-document-row">
        <div class="ai-document-main">
          <a class="ai-document-link" href="<?= e(url('document.php?id=' . $document['id'])) ?>" target="_blank" rel="noopener"><span class="document-icon">▧</span><span><strong><?= e($label) ?></strong><small><?= e($document['original_name']) ?> · <?= e(number_format((int) $document['file_size'] / 1024, 1)) ?> KB</small></span></a>
          <form method="post" action="scan-document.php"><?= csrf_field() ?><input type="hidden" name="document_id" value="<?= (int) $document['id'] ?>"><button class="button button-secondary ai-scan-button" type="submit" <?= (!$aiTablesReady || $sensitiveBlocked) ? 'disabled' : '' ?>><?= $sensitiveBlocked ? 'Sensitive file' : ((($document['scan_status'] ?? '') === 'Completed') ? 'Scan again' : 'AI scan') ?></button></form>
        </div>
        <?php if (($document['scan_status'] ?? '') === 'Completed'): ?>
          <div class="ai-scan-result">
            <div class="ai-score-row"><span class="ai-chip <?= (int) $document['matches_expected_type'] === 1 ? 'good' : 'warning' ?>"><?= (int) $document['matches_expected_type'] === 1 ? 'Type matched' : 'Check type' ?></span><span class="ai-chip">Quality <?= (int) $document['quality_score'] ?>%</span><span class="ai-chip">Confidence <?= (int) $document['confidence_score'] ?>%</span><?php if ((int) $document['requires_human_review'] === 1): ?><span class="ai-chip review">Human review required</span><?php endif; ?></div>
            <p><strong>Detected:</strong> <?= e($document['detected_document_type']) ?></p><p><?= e($document['summary']) ?></p>
            <?php if ($issues): ?><div class="ai-findings"><strong>Items to check</strong><ul><?php foreach ($issues as $issue): ?><li><?= e($issue) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
            <?php if ($fields): ?><details><summary>Extracted fields</summary><div class="ai-field-grid"><?php foreach ($fields as $field): ?><div><small><?= e($field['field'] ?? 'Field') ?></small><strong><?= e($field['value'] ?? '') ?></strong><em><?= (int) ($field['confidence'] ?? 0) ?>% confidence</em></div><?php endforeach; ?></div></details><?php endif; ?>
            <small class="ai-timestamp">Scanned <?= e(date('M j, Y g:i A', strtotime($document['scanned_at']))) ?> · <?= e($document['model']) ?></small>
          </div>
        <?php elseif (($document['scan_status'] ?? '') === 'Failed'): ?>
          <div class="ai-scan-error"><strong>Last scan failed.</strong> <?= e($document['error_message']) ?></div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  </div>
</article>
<div class="form-actions"><a class="button button-secondary" href="index.php#queue">← Back to review queue</a></div>
<?php render_app_footer(); ?>
