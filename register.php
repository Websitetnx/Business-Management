<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_guest();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');
    if (strlen($name) < 2 || strlen($name) > 120) $errors[] = 'Enter your complete name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen($password) < 8) $errors[] = 'Password must contain at least 8 characters.';
    if ($password !== $confirmation) $errors[] = 'Password confirmation does not match.';

    if (!$errors) {
        try {
            $pdo = db();
            $statement = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $statement->execute([$email]);
            if ($statement->fetchColumn()) {
                $errors[] = 'An account already uses this email address.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $challenge = issue_auth_otp($pdo, [
                    'email' => $email,
                    'name' => $name,
                    'purpose' => 'registration',
                    'user_id' => null,
                    'pending_name' => $name,
                    'pending_password_hash' => $passwordHash,
                ]);
                $_SESSION['pending_registration'] = [
                    'challenge_id' => (int) $challenge['id'],
                    'request_token' => (string) $challenge['request_token'],
                    'email' => $email,
                    'pending_name' => $name,
                    'pending_password_hash' => $passwordHash,
                ];
                redirect('verify-registration.php');
            }
        } catch (AuthOtpRateLimitException $exception) {
            $errors[] = $exception->getMessage();
        } catch (PDOException $exception) {
            $errors[] = $exception->getCode() === '23000'
                ? 'An account already uses this email address.'
                : 'Account creation failed. Import the latest database migration and try again.';
        } catch (Throwable $exception) {
            error_log('Registration OTP delivery failed: ' . $exception->getMessage());
            $errors[] = 'We could not send the verification code. Check the mail settings or try again shortly.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Create account | PERMIT</title><link rel="stylesheet" href="styles.css?v=20260903-certificate1"><link rel="icon" type="image/png" href="assets/logo-light.png"></head>
<body class="auth-page">
  <main class="auth-shell">
    <section class="auth-visual"><a class="brand brand-logo-link auth-brand" href="index.php"><img class="brand-logo" src="assets/logo-light.png" alt="PERMIT — Web-Based Business Permit Management System"></a><div><p class="eyebrow light">Applicant registration</p><h1>Create one secure permit account.</h1><p>Your applications, renewals, documents, and status history remain connected to your account.</p></div><ul class="auth-points"><li>✓ Apply for a new permit</li><li>✓ Renew an approved permit</li><li>✓ Track LGU review updates</li></ul></section>
    <section class="auth-card">
      <div><p class="eyebrow">Get started</p><h2>Create applicant account</h2><p class="muted">We will email you a one-time code before creating your account.</p></div>
      <?php if ($errors): ?><div class="form-alert form-alert-error"><ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      <form method="post" class="auth-form">
        <?= csrf_field() ?>
        <label class="field">Complete name<input name="name" autocomplete="name" required maxlength="120" value="<?= e($_POST['name'] ?? '') ?>"></label>
        <label class="field">Email address<input type="email" name="email" autocomplete="email" required value="<?= e($_POST['email'] ?? '') ?>"></label>
        <label class="field">Password<input type="password" name="password" autocomplete="new-password" minlength="8" required><small class="muted">Use at least 8 characters.</small></label>
        <label class="field">Confirm password<input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required></label>
        <button class="button" type="submit">Email verification code</button>
      </form>
      <p class="auth-switch">Already registered? <a href="login.php">Sign in</a></p>
    </section>
  </main>
  <script src="app.js?v=20260903-certificate1"></script>
</body>
</html>
