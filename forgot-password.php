<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_guest();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if (!$errors) {
        $previousRequest = $_SESSION['password_reset_request'] ?? null;
        $previousChallengeId = is_array($previousRequest)
            && strtolower((string) ($previousRequest['email'] ?? '')) === $email
            ? (int) ($previousRequest['challenge_id'] ?? 0)
            : 0;
        $previousRequestToken = is_array($previousRequest)
            && strtolower((string) ($previousRequest['email'] ?? '')) === $email
            ? (string) ($previousRequest['request_token'] ?? '')
            : '';
        unset($_SESSION['password_reset_authorized']);
        $_SESSION['password_reset_request'] = [
            'email' => $email,
            'challenge_id' => $previousChallengeId ?: null,
            'request_token' => preg_match('/^[a-f0-9]{64}$/D', $previousRequestToken) === 1
                ? $previousRequestToken
                : null,
        ];

        try {
            $statement = db()->prepare("SELECT id, name, email FROM users WHERE email = ? AND role = 'applicant' AND is_active = 1 LIMIT 1");
            $statement->execute([$email]);
            $user = $statement->fetch();
            if ($user) {
                $challenge = issue_auth_otp(db(), [
                    'email' => (string) $user['email'],
                    'name' => (string) $user['name'],
                    'purpose' => 'password_reset',
                    'user_id' => (int) $user['id'],
                    'pending_name' => null,
                    'pending_password_hash' => null,
                ]);
                $_SESSION['password_reset_request']['challenge_id'] = (int) $challenge['id'];
                $_SESSION['password_reset_request']['request_token'] = (string) $challenge['request_token'];
            }
        } catch (Throwable $exception) {
            // Keep the browser response identical for unknown, disabled, throttled,
            // and temporarily undeliverable accounts to prevent account discovery.
            error_log('Password reset OTP request was not issued: ' . $exception->getMessage());
        }

        flash('success', 'If an active applicant account uses that email, a verification code has been sent.');
        redirect('verify-reset-otp.php');
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Forgot password | PERMIT</title><link rel="stylesheet" href="styles.css?v=20260903-certificate1"><link rel="icon" type="image/png" href="assets/logo-light.png"></head>
<body class="auth-page simple-auth">
  <main class="auth-card setup-card">
    <a class="auth-logo auth-logo-image" href="login.php"><img src="assets/logo-light.png" alt="PERMIT — Web-Based Business Permit Management System"></a>
    <div><p class="eyebrow">Applicant recovery</p><h1>Forgot your password?</h1><p class="muted">Enter your applicant email. If it matches an active account, we will send a one-time verification code.</p></div>
    <?php if ($errors): ?><div class="form-alert form-alert-error"><ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" class="auth-form">
      <?= csrf_field() ?>
      <label class="field">Email address<input type="email" name="email" autocomplete="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>" placeholder="you@example.com"></label>
      <button class="button" type="submit">Email reset code</button>
    </form>
    <p class="auth-switch"><a href="login.php">Return to sign in</a></p>
  </main>
  <script src="app.js?v=20260903-certificate1"></script>
</body>
</html>
