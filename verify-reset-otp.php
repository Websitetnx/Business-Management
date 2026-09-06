<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_guest();

$request = $_SESSION['password_reset_request'] ?? null;
if (!is_array($request) || !filter_var($request['email'] ?? null, FILTER_VALIDATE_EMAIL)) {
    flash('error', 'Request a new password reset code to continue.');
    redirect('forgot-password.php');
}

$email = strtolower((string) $request['email']);
$challengeId = isset($request['challenge_id']) ? (int) $request['challenge_id'] : 0;
$requestToken = (string) ($request['request_token'] ?? '');
$otpLifetimeSeconds = auth_otp_settings()['expiry_seconds'];
$otpLifetimeMinutes = max(1, (int) ceil($otpLifetimeSeconds / 60));
$errors = [];
$notices = pull_flashes();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'verify');

    if ($action === 'resend') {
        try {
            $statement = db()->prepare("SELECT id, name, email FROM users WHERE email = ? AND role = 'applicant' AND is_active = 1 LIMIT 1");
            $statement->execute([$email]);
            $user = $statement->fetch();
            if ($user) {
                if ($challengeId > 0 && preg_match('/^[a-f0-9]{64}$/D', $requestToken) === 1) {
                    $challenge = resend_auth_otp(
                        db(),
                        $challengeId,
                        $requestToken,
                        $email,
                        'password_reset',
                        ['name' => (string) $user['name']]
                    );
                } else {
                    $challenge = issue_auth_otp(db(), [
                        'email' => (string) $user['email'],
                        'name' => (string) $user['name'],
                        'purpose' => 'password_reset',
                        'user_id' => (int) $user['id'],
                    ]);
                }
                $_SESSION['password_reset_request']['challenge_id'] = (int) $challenge['id'];
                $_SESSION['password_reset_request']['request_token'] = (string) $challenge['request_token'];
            }
        } catch (Throwable $exception) {
            error_log('Password reset OTP resend was not issued: ' . $exception->getMessage());
        }

        flash('success', 'If an active applicant account uses that email, a valid verification code has been sent.');
        redirect('verify-reset-otp.php');
    }

    $code = trim((string) ($_POST['otp_code'] ?? ''));
    if (!preg_match('/^\d{6}$/', $code)) {
        $errors[] = 'Enter the 6-digit verification code.';
    } else {
        $authorizedUser = null;
        try {
            $verified = $challengeId > 0 && consume_auth_otp(
                db(),
                $challengeId,
                $requestToken,
                $email,
                'password_reset',
                $code,
                static function (array $challenge, PDO $pdo) use (&$authorizedUser): void {
                    $statement = $pdo->prepare("SELECT id, email FROM users WHERE id = ? AND email = ? AND role = 'applicant' AND is_active = 1 LIMIT 1");
                    $statement->execute([(int) ($challenge['user_id'] ?? 0), (string) $challenge['email']]);
                    $authorizedUser = $statement->fetch() ?: null;
                    if (!$authorizedUser) {
                        throw new RuntimeException('The password reset account is unavailable.');
                    }
                }
            );

            if (!$verified || !$authorizedUser) {
                $errors[] = 'The code is incorrect, expired, or has reached its attempt limit.';
            } else {
                unset($_SESSION['password_reset_request']);
                session_regenerate_id(true);
                $_SESSION['password_reset_authorized'] = [
                    'user_id' => (int) $authorizedUser['id'],
                    'email' => (string) $authorizedUser['email'],
                    'expires_at' => time() + $otpLifetimeSeconds,
                ];
                redirect('reset-password.php');
            }
        } catch (Throwable $exception) {
            error_log('Password reset OTP verification failed: ' . $exception->getMessage());
            $errors[] = 'The code is incorrect, expired, or has reached its attempt limit.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Verify reset code | PERMIT</title><link rel="stylesheet" href="styles.css?v=20260903-certificate1"><link rel="icon" type="image/png" href="assets/logo-light.png"></head>
<body class="auth-page simple-auth">
  <main class="auth-card setup-card">
    <a class="auth-logo auth-logo-image" href="login.php"><img src="assets/logo-light.png" alt="PERMIT — Web-Based Business Permit Management System"></a>
    <div><p class="eyebrow">Password verification</p><h1>Enter your reset code</h1><p class="muted">If the account is eligible, the 6-digit code sent to <strong><?= e(mask_email($email)) ?></strong> is valid for <?= $otpLifetimeMinutes ?> minutes.</p></div>
    <?php foreach ($notices as $message): ?><div class="form-alert <?= $message['type'] === 'error' ? 'form-alert-error' : '' ?>"><?= e($message['message']) ?></div><?php endforeach; ?>
    <?php if ($errors): ?><div class="form-alert form-alert-error"><ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" class="auth-form">
      <?= csrf_field() ?>
      <label class="field">Verification code<input name="otp_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus placeholder="000000"></label>
      <button class="button" type="submit" name="action" value="verify">Verify code</button>
      <button class="button button-secondary" type="submit" name="action" value="resend" formnovalidate>Resend code</button>
    </form>
    <p class="auth-switch"><a href="forgot-password.php">Use a different email</a></p>
  </main>
  <script src="app.js?v=20260903-certificate1"></script>
</body>
</html>
