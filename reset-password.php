<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_guest();

$authorization = $_SESSION['password_reset_authorized'] ?? null;
if (!is_array($authorization)
    || empty($authorization['user_id'])
    || !filter_var($authorization['email'] ?? null, FILTER_VALIDATE_EMAIL)
    || (int) ($authorization['expires_at'] ?? 0) < time()) {
    unset($_SESSION['password_reset_authorized']);
    flash('error', 'Your password reset authorization expired. Request a new code.');
    redirect('forgot-password.php');
}

$userId = (int) $authorization['user_id'];
$email = strtolower((string) $authorization['email']);
$otpLifetimeMinutes = max(1, (int) ceil(auth_otp_settings()['expiry_seconds'] / 60));
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');
    if (strlen($password) < 8) $errors[] = 'Password must contain at least 8 characters.';
    if ($password !== $confirmation) $errors[] = 'Password confirmation does not match.';

    if (!$errors) {
        $pdo = null;
        try {
            $pdo = db();
            $pdo->beginTransaction();
            $statement = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND email = ? AND role = 'applicant' AND is_active = 1");
            $statement->execute([password_hash($password, PASSWORD_DEFAULT), $userId, $email]);
            if ($statement->rowCount() !== 1) {
                throw new RuntimeException('The applicant account is unavailable.');
            }
            $clearCodes = $pdo->prepare("DELETE FROM auth_otp_challenges WHERE user_id = ? AND purpose = 'password_reset'");
            $clearCodes->execute([$userId]);
            audit($pdo, $userId, 'reset_password', 'user', $userId);
            $pdo->commit();

            unset($_SESSION['password_reset_authorized'], $_SESSION['password_reset_request']);
            session_regenerate_id(true);
            flash('success', 'Your password was updated. You can now sign in.');
            redirect('login.php');
        } catch (Throwable $exception) {
            if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
            error_log('Password reset failed: ' . $exception->getMessage());
            $errors[] = 'We could not update your password. Please request a new reset code.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Choose new password | PERMIT</title><link rel="stylesheet" href="styles.css?v=20260903-certificate1"><link rel="icon" type="image/png" href="assets/logo-light.png"></head>
<body class="auth-page simple-auth">
  <main class="auth-card setup-card">
    <a class="auth-logo auth-logo-image" href="login.php"><img src="assets/logo-light.png" alt="PERMIT — Web-Based Business Permit Management System"></a>
    <div><p class="eyebrow">Secure your account</p><h1>Choose a new password</h1><p class="muted">Set a new password for <strong><?= e(mask_email($email)) ?></strong>. This authorization expires in <?= $otpLifetimeMinutes ?> minutes.</p></div>
    <?php if ($errors): ?><div class="form-alert form-alert-error"><ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" class="auth-form">
      <?= csrf_field() ?>
      <label class="field">New password<input type="password" name="password" autocomplete="new-password" minlength="8" required autofocus><small class="muted">Use at least 8 characters.</small></label>
      <label class="field">Confirm new password<input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required></label>
      <button class="button" type="submit">Update password</button>
    </form>
    <p class="auth-switch"><a href="login.php">Cancel and return to sign in</a></p>
  </main>
  <script src="app.js?v=20260903-certificate1"></script>
</body>
</html>
