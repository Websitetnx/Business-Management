<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (is_treasurer_role(current_user()['role'] ?? '')) {
    deny_access('Access denied. Treasurer accounts are limited to payment management.');
}

$errors = [];
$databaseReady = true;
$adminExists = false;
try {
    $adminExists = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn() > 0;
} catch (PDOException) {
    $databaseReady = false;
}

$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocal = in_array($remoteAddress, ['127.0.0.1', '::1'], true);
$configuredKey = (string) app_config('admin_setup_key');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $databaseReady && !$adminExists) {
    verify_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');
    if (!$isLocal && ($configuredKey === '' || !hash_equals($configuredKey, (string) ($_POST['setup_key'] ?? '')))) $errors[] = 'The administrator setup key is invalid.';
    if (strlen($name) < 2 || strlen($name) > 120) $errors[] = 'Enter the administrator name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid administrator email.';
    if (strlen($password) < 12) $errors[] = 'Administrator passwords must contain at least 12 characters.';
    if ($password !== $confirmation) $errors[] = 'Password confirmation does not match.';
    if (!$errors) {
        try {
            $statement = db()->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
            $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $id = (int) db()->lastInsertId();
            audit(db(), $id, 'create_first_admin', 'user', $id);
            flash('success', 'Administrator account created. You can now sign in.');
            redirect('login.php');
        } catch (PDOException $exception) {
            $errors[] = $exception->getCode() === '23000' ? 'That email address is already registered.' : 'Administrator creation failed.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Administrator setup | PERMIT</title><link rel="stylesheet" href="styles.css?v=20260903-certificate1"><link rel="icon" type="image/png" href="assets/logo-light.png"></head>
<body class="auth-page simple-auth">
  <main class="auth-card setup-card">
    <a class="auth-logo auth-logo-image" href="login.php"><img src="assets/logo-light.png" alt="PERMIT — Web-Based Business Permit Management System"></a>
    <div><p class="eyebrow">One-time setup</p><h1>Create administrator account</h1></div>
    <?php if (!$databaseReady): ?>
      <div class="form-alert form-alert-error">The database is not ready. Import <strong>database/schema.sql</strong>, update <strong>config.php</strong>, then reload this page.</div>
    <?php elseif ($adminExists): ?>
      <div class="form-alert">An administrator already exists, so first-admin setup is locked.</div><a class="button" href="login.php">Return to sign in</a>
    <?php else: ?>
      <p class="muted">This page works only until the first administrator is created. Public registration always creates applicant accounts.</p>
      <?php if ($errors): ?><div class="form-alert form-alert-error"><ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      <form method="post" class="auth-form">
        <?= csrf_field() ?>
        <?php if (!$isLocal): ?><label class="field">Setup key<input type="password" name="setup_key" required><small class="muted">Set ADMIN_SETUP_KEY on the server before running setup.</small></label><?php endif; ?>
        <label class="field">Administrator name<input name="name" autocomplete="name" required maxlength="120" value="<?= e($_POST['name'] ?? '') ?>"></label>
        <label class="field">Administrator email<input type="email" name="email" autocomplete="email" required value="<?= e($_POST['email'] ?? '') ?>"></label>
        <label class="field">Password<input type="password" name="password" autocomplete="new-password" minlength="12" required><small class="muted">Use at least 12 characters.</small></label>
        <label class="field">Confirm password<input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required></label>
        <button class="button" type="submit">Create administrator</button>
      </form>
    <?php endif; ?>
  </main>
  <script src="app.js?v=20260903-certificate1"></script>
</body>
</html>
