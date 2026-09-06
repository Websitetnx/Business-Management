<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/layout.php';
$user = require_role('admin');
$pdo = db();
$errors = [];
$ready = true;
$fixedFields = [
    'sanitary_fee' => 'Sanitary / health inspection fee',
    'zoning_fee' => 'Zoning / locational clearance fee',
    'general_inspection_fee' => 'General inspection fee',
    'building_inspection_fee' => 'Building inspection fee',
    'electrical_inspection_fee' => 'Electrical inspection fee',
    'plumbing_inspection_fee' => 'Plumbing inspection fee',
    'barangay_clearance_fee' => 'Barangay clearance fee',
    'community_tax_fee' => 'Community tax certificate fee',
    'bfp_rate_percent' => 'BFP fee rate (%)',
    'bfp_minimum_fee' => 'BFP minimum fee',
];

try {
    $settings = $pdo->query('SELECT * FROM permit_fee_settings WHERE id = 1')->fetch();
    $rateRows = $pdo->query('SELECT * FROM permit_business_type_rates ORDER BY id')->fetchAll();
} catch (PDOException) {
    $ready = false;
    $settings = null;
    $rateRows = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$ready) $errors[] = 'Import database/migrations/005_fee_assessment_formula.sql before saving rates.';
    $lguName = trim((string) ($_POST['lgu_name'] ?? ''));
    if (strlen($lguName) < 2 || strlen($lguName) > 190) $errors[] = 'Enter the city or municipality name.';
    $fixedValues = [];
    foreach ($fixedFields as $field => $label) {
        $raw = str_replace([',', '₱', ' '], '', (string) ($_POST[$field] ?? ''));
        $maximum = $field === 'bfp_rate_percent' ? 100 : 99999999.99;
        if (!is_numeric($raw) || (float) $raw < 0 || (float) $raw > $maximum) $errors[] = 'Enter a valid value for ' . $label . '.';
        $fixedValues[$field] = is_numeric($raw) ? (float) $raw : 0.0;
    }
    $types = business_type_options();
    $typeValues = [];
    foreach ($types as $type) {
        foreach (['new_lbt_rate_percent', 'renewal_lbt_rate_percent', 'mayors_permit_fee'] as $field) {
            $raw = str_replace([',', '₱', ' '], '', (string) ($_POST[$field][$type] ?? ''));
            $maximum = str_contains($field, 'percent') ? 100 : 99999999.99;
            if (!is_numeric($raw) || (float) $raw < 0 || (float) $raw > $maximum) $errors[] = 'Enter a valid ' . str_replace('_', ' ', $field) . ' for ' . $type . '.';
            $typeValues[$type][$field] = is_numeric($raw) ? (float) $raw : 0.0;
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $update = $pdo->prepare('UPDATE permit_fee_settings SET lgu_name = ?, sanitary_fee = ?, zoning_fee = ?, general_inspection_fee = ?, building_inspection_fee = ?, electrical_inspection_fee = ?, plumbing_inspection_fee = ?, barangay_clearance_fee = ?, community_tax_fee = ?, bfp_rate_percent = ?, bfp_minimum_fee = ?, is_configured = 1, updated_by = ? WHERE id = 1');
            $update->execute([$lguName, $fixedValues['sanitary_fee'], $fixedValues['zoning_fee'], $fixedValues['general_inspection_fee'], $fixedValues['building_inspection_fee'], $fixedValues['electrical_inspection_fee'], $fixedValues['plumbing_inspection_fee'], $fixedValues['barangay_clearance_fee'], $fixedValues['community_tax_fee'], $fixedValues['bfp_rate_percent'], $fixedValues['bfp_minimum_fee'], $user['id']]);
            $rateUpdate = $pdo->prepare('UPDATE permit_business_type_rates SET new_lbt_rate_percent = ?, renewal_lbt_rate_percent = ?, mayors_permit_fee = ?, is_active = 1 WHERE business_type = ?');
            foreach ($typeValues as $type => $values) $rateUpdate->execute([$values['new_lbt_rate_percent'], $values['renewal_lbt_rate_percent'], $values['mayors_permit_fee'], $type]);
            audit($pdo, (int) $user['id'], 'update_permit_fee_schedule', 'permit_fee_settings', 1);
            $pdo->commit();
            flash('success', 'The permit fee schedule was saved. New assessments will use these rates.');
            redirect('admin/fee-settings.php');
        } catch (Throwable) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'The permit fee schedule could not be saved.';
        }
    }
}

$rates = [];
foreach ($rateRows as $row) $rates[$row['business_type']] = $row;
render_app_header('Permit Fee Settings', 'fee-settings');
?>
<div class="section-heading"><div><p class="eyebrow">Assessment configuration</p><h2>Permit fee schedule</h2><p class="muted">Enter only rates approved in your current local tax ordinance and official fee schedule.</p></div><span class="secure-badge">◆ Admin only</span></div>
<?php if (!$ready): ?><div class="form-alert form-alert-error">Import <strong>database/migrations/005_fee_assessment_formula.sql</strong> first.</div><?php endif; ?>
<?php if ($errors): ?><div class="form-alert form-alert-error"><ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($ready): ?>
<form method="post" class="fee-settings-form"><?= csrf_field() ?>
  <article class="panel fee-settings-panel"><div class="panel-header"><div><p class="eyebrow">LGU information</p><h3>General and regulatory fees</h3></div><span class="status <?= !empty($settings['is_configured']) ? 'approved' : 'revision' ?>"><?= !empty($settings['is_configured']) ? 'Configured' : 'Setup required' ?></span></div>
    <div class="form-grid"><label class="field field-wide">City or municipality<input name="lgu_name" required maxlength="190" value="<?= e($_POST['lgu_name'] ?? $settings['lgu_name']) ?>"></label><?php foreach ($fixedFields as $field => $label): ?><label class="field"><?= e($label) ?><input name="<?= e($field) ?>" inputmode="decimal" required value="<?= e($_POST[$field] ?? $settings[$field]) ?>"></label><?php endforeach; ?></div>
  </article>
  <article class="panel fee-settings-panel"><div class="panel-header"><div><p class="eyebrow">Business classification</p><h3>LBT and Mayor's Permit rates</h3><p class="muted">Percentages are applied to declared capital for new applications and prior-year gross sales for renewals.</p></div></div>
    <div class="fee-rate-table"><div class="fee-rate-header"><strong>Business type</strong><strong>New LBT %</strong><strong>Renewal LBT %</strong><strong>Mayor's permit</strong></div><?php foreach (business_type_options() as $type): $rate = $rates[$type] ?? []; ?><div class="fee-rate-row"><strong><?= e($type) ?></strong><input aria-label="<?= e($type) ?> new LBT rate" name="new_lbt_rate_percent[<?= e($type) ?>]" inputmode="decimal" required value="<?= e($_POST['new_lbt_rate_percent'][$type] ?? $rate['new_lbt_rate_percent'] ?? '0.0000') ?>"><input aria-label="<?= e($type) ?> renewal LBT rate" name="renewal_lbt_rate_percent[<?= e($type) ?>]" inputmode="decimal" required value="<?= e($_POST['renewal_lbt_rate_percent'][$type] ?? $rate['renewal_lbt_rate_percent'] ?? '0.0000') ?>"><input aria-label="<?= e($type) ?> mayor permit fee" name="mayors_permit_fee[<?= e($type) ?>]" inputmode="decimal" required value="<?= e($_POST['mayors_permit_fee'][$type] ?? $rate['mayors_permit_fee'] ?? '0.00') ?>"></div><?php endforeach; ?></div>
  </article>
  <div class="formula-notice"><strong>Formula used</strong><span>Total = LBT + Mayor's Permit + regulatory and applicable inspection fees + BFP fee + barangay clearance + community tax.</span><small>The BFP percentage is applied to the Mayor's Permit and regulatory-fee subtotal. Set fees collected separately to 0.00. BPLO must verify the final assessment before approval.</small></div>
  <div class="form-actions"><span></span><button class="button" type="submit">Save official fee schedule</button></div>
</form>
<?php endif; ?>
<?php render_app_footer(); ?>
