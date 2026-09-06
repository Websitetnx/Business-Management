<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_guest();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    try {
        $statement = db()->prepare('SELECT id, name, email, password_hash, role, is_active FROM users WHERE email = ? LIMIT 1');
        $statement->execute([$email]);
        $user = $statement->fetch();
        if ($user && (int) $user['is_active'] === 1 && password_verify($password, $user['password_hash'])) {
            login_user($user);
            audit(db(), (int) $user['id'], 'login', 'user', (int) $user['id']);
            redirect(role_home_path($user['role']));
        }
        $error = 'The email address or password is incorrect.';
    } catch (PDOException) {
        $error = 'Database connection failed. Import database/schema.sql and check config.php.';
    }
}
$flashes = pull_flashes();
?>
<!doctype html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sign in | PERMIT</title><link rel="stylesheet" href="styles.css?v=20260903-certificate1"><link rel="icon" type="image/png" href="assets/logo-light.png"></head>
<body class="auth-page">
  <main class="auth-shell">
    <section class="auth-visual">
      <a class="brand brand-logo-link auth-brand" href="index.php"><img class="brand-logo" src="assets/logo-light.png" alt="PERMIT — Web-Based Business Permit Management System"></a>
      <div><p class="eyebrow light">City Government Services</p><h1>Business permits, without the long lines.</h1><p>Apply, upload requirements, renew permits, and track every update through one secure account.</p></div>
      <ul class="auth-points"><li>✓ Secure applicant accounts</li><li>✓ Online permit applications</li><li>✓ Real-time status tracking</li></ul>
    </section>
    <section class="auth-card">
      <div><p class="eyebrow">Welcome back</p><h2>Sign in to your account</h2><p class="muted">Use your registered applicant or administrator account.</p></div>
      <?php foreach ($flashes as $message): ?><div class="form-alert <?= $message['type'] === 'error' ? 'form-alert-error' : '' ?>"><?= e($message['message']) ?></div><?php endforeach; ?>
      <?php if ($error): ?><div class="form-alert form-alert-error"><?= e($error) ?></div><?php endif; ?>
      <form method="post" class="auth-form">
        <?= csrf_field() ?>
        <label class="field">Email address<input type="email" name="email" autocomplete="email" required value="<?= e($_POST['email'] ?? '') ?>" placeholder="you@example.com"></label>
        <label class="field">Password<input type="password" name="password" autocomplete="current-password" required placeholder="Enter your password"></label>
        <button class="button" type="submit">Sign in</button>
      </form>
      <p class="auth-setup"><a href="forgot-password.php">Forgot your applicant password?</a></p>
      <p class="auth-switch">No applicant account? <a href="register.php">Create one</a></p>
      <p class="auth-setup"><a href="setup-admin.php">Set up the first administrator</a></p>
    </section>
  </main>
  <script src="app.js?v=20260903-certificate1"></script>
</body>
</html>
