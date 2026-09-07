<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_guest();

$pending = $_SESSION['pending_registration'] ?? null;
if (!is_array($pending)
    || empty($pending['challenge_id'])
    || preg_match('/^[a-f0-9]{64}$/D', (string) ($pending['request_token'] ?? '')) !== 1
    || !filter_var($pending['email'] ?? null, FILTER_VALIDATE_EMAIL)) {
    flash('error', 'Start registration again to receive a verification code.');
    redirect('register.php');
}

$challengeId = (int) $pending['challenge_id'];
$requestToken = (string) $pending['request_token'];
$email = strtolower((string) $pending['email']);
$otpLifetimeMinutes = max(1, (int) ceil(auth_otp_settings()['expiry_seconds'] / 60));
$pendingName = trim((string) ($pending['pending_name'] ?? ''));
$pendingPasswordHash = (string) ($pending['pending_password_hash'] ?? '');
$errors = [];
$notices = pull_flashes();
$challengeLookupFailed = false;

try {
    $challenge = auth_otp_challenge(db(), $challengeId, $requestToken, $email, 'registration');
} catch (PDOException) {
    $challenge = null;
    $challengeLookupFailed = true;
    $errors[] = 'Email verification is unavailable. Import the latest database migration and try again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'verify');

    if ($action === 'resend') {
        $resendName = trim((string) ($challenge['pending_name'] ?? $pendingName));
        $resendPasswordHash = (string) ($challenge['pending_password_hash'] ?? $pendingPasswordHash);
        if ($resendName === '' || $resendPasswordHash === '') {
            $errors[] = 'This registration request is no longer available. Please start again.';
        } else {
            try {
                $existing = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $existing->execute([$email]);
                if ($existing->fetchColumn()) {
                    unset($_SESSION['pending_registration']);
                    flash('success', 'Your account already exists. Sign in to continue.');
                    redirect('login.php');
                }
                $challenge = resend_auth_otp(
                    db(),
                    $challengeId,
                    $requestToken,
                    $email,
                    'registration',
                    [
                    'name' => $resendName,
                    'pending_name' => $resendName,
                    'pending_password_hash' => $resendPasswordHash,
                    ]
                );
                $_SESSION['pending_registration'] = [
                    'challenge_id' => (int) $challenge['id'],
                    'request_token' => (string) $challenge['request_token'],
                    'email' => $email,
                    'pending_name' => $resendName,
                    'pending_password_hash' => $resendPasswordHash,
                ];
                flash('success', 'A new verification code was sent.');
                redirect('verify-registration.php');
            } catch (AuthOtpRateLimitException $exception) {
                $errors[] = $exception->getMessage();
            } catch (Throwable $exception) {
                error_log('Registration OTP resend failed: ' . $exception->getMessage());
                $errors[] = 'We could not resend the code. Check the mail settings or try again shortly.';
            }
        }
    } else {
        $code = trim((string) ($_POST['otp_code'] ?? ''));
        if (!preg_match('/^\d{6}$/', $code)) {
            $errors[] = 'Enter the 6-digit verification code.';
        } else {
            $newUser = null;
            try {
                $verified = consume_auth_otp(
                    db(),
                    $challengeId,
                    $requestToken,
                    $email,
                    'registration',
                    $code,
                    static function (array $registration, PDO $pdo) use (&$newUser): void {
                        $name = trim((string) ($registration['pending_name'] ?? ''));
                        $passwordHash = (string) ($registration['pending_password_hash'] ?? '');
                        if ($name === '' || $passwordHash === '') {
                            throw new RuntimeException('The pending registration is incomplete.');
                        }

                        $insert = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'applicant')");
                        $insert->execute([$name, (string) $registration['email'], $passwordHash]);
                        $id = (int) $pdo->lastInsertId();
                        audit($pdo, $id, 'register', 'user', $id);
                        $newUser = [
                            'id' => $id,
                            'name' => $name,
                            'email' => (string) $registration['email'],
                            'role' => 'applicant',
                        ];
                    }
                );

                if (!$verified || !$newUser) {
                    $errors[] = 'The code is incorrect, expired, or has reached its attempt limit.';
                } else {
                    unset($_SESSION['pending_registration']);
                    login_user($newUser);
                    flash('success', 'Your email was verified and your applicant account was created.');
                    redirect('dashboard.php');
                }
            } catch (PDOException $exception) {
                $errors[] = $exception->getCode() === '23000'
                    ? 'An account already uses this email address. Sign in instead.'
                    : 'Account verification failed. Import the latest database migration and try again.';
            } catch (Throwable $exception) {
                error_log('Registration OTP verification failed: ' . $exception->getMessage());
                $errors[] = 'Account verification failed. Please start registration again.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Verify email | PERMIT</title><link rel="stylesheet" href="styles.css?v=20260903-certificate1"><link rel="icon" type="image/png" href="assets/logo-light.png"></head>
<body class="auth-page simple-auth">
  <main class="auth-card setup-card">
    <a class="auth-logo auth-logo-image" href="index.php"><img src="assets/logo-light.png" alt="PERMIT — Web-Based Business Permit Management System"></a>
    <div><p class="eyebrow">Email verification</p><h1>Enter your one-time code</h1><p class="muted">We sent a 6-digit code to <strong><?= e(mask_email($email)) ?></strong>. It expires in <?= $otpLifetimeMinutes ?> minutes.</p></div>
    <?php foreach ($notices as $message): ?><div class="form-alert <?= $message['type'] === 'error' ? 'form-alert-error' : '' ?>"><?= e($message['message']) ?></div><?php endforeach; ?>
    <?php if ($errors): ?><div class="form-alert form-alert-error"><ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if ($challenge): ?>
      <form method="post" class="auth-form">
        <?= csrf_field() ?>
        <label class="field">Verification code<input name="otp_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus placeholder="000000"></label>
        <button class="button" type="submit" name="action" value="verify">Verify and create account</button>
        <button class="button button-secondary" type="submit" name="action" value="resend" formnovalidate>Resend code</button>
      </form>
    <?php elseif (!$challengeLookupFailed && $pendingName !== '' && $pendingPasswordHash !== ''): ?>
      <div class="form-alert form-alert-error">This code is no longer valid. Request a new code to continue.</div>
      <form method="post" class="auth-form">
        <?= csrf_field() ?>
        <button class="button" type="submit" name="action" value="resend">Email a new code</button>
      </form>
    <?php else: ?>
      <a class="button" href="register.php">Start registration again</a>
    <?php endif; ?>
    <p class="auth-switch">Used a different email? <a href="register.php">Go back</a></p>
  </main>
  <script src="app.js?v=20260903-certificate1"></script>
</body>
</html>
