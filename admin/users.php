<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/layout.php';
$user = require_role('admin');
$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');
    if (strlen($name) < 2 || strlen($name) > 120) $errors[] = 'Enter the administrator name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen($password) < 12) $errors[] = 'Administrator passwords must contain at least 12 characters.';
    if ($password !== $confirmation) $errors[] = 'Password confirmation does not match.';
    if (!$errors) {
        try {
            $insert = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
            $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $newId = (int) $pdo->lastInsertId();
            audit($pdo, (int) $user['id'], 'create_admin', 'user', $newId);
            flash('success', 'Administrator account created successfully.');
            redirect('admin/users.php');
        } catch (PDOException $exception) {
            $errors[] = $exception->getCode() === '23000' ? 'That email address is already registered.' : 'The administrator could not be created.';
        }
    }
}

$users = $pdo->query("SELECT u.id, u.name, u.email, u.role, u.is_active, u.created_at, COUNT(a.id) application_count FROM users u LEFT JOIN applications a ON a.user_id = u.id GROUP BY u.id ORDER BY FIELD(u.role, 'admin', 'applicant'), u.created_at DESC")->fetchAll();
render_app_header('User Accounts', 'users');
?>
<div class="section-heading"><div><p class="eyebrow">Access management</p><h2>Create administrator account</h2><p class="muted">Only a signed-in administrator can add another administrator.</p></div></div>
<?php if ($errors): ?><div class="form-alert form-alert-error"><ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="content-grid user-layout"><article class="panel"><div class="panel-header"><div><p class="eyebrow">New administrator</p><h3>Account credentials</h3></div></div><form method="post" class="form-grid admin-create-form"><?= csrf_field() ?><label class="field field-wide">Complete name<input name="name" required maxlength="120" value="<?= e($_POST['name'] ?? '') ?>"></label><label class="field field-wide">Email address<input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"></label><label class="field">Password<input type="password" name="password" minlength="12" required></label><label class="field">Confirm password<input type="password" name="password_confirmation" minlength="12" required></label><div class="field-wide"><button class="button" type="submit">Create administrator</button></div></form></article><aside class="panel"><div class="panel-header"><div><p class="eyebrow">Security rule</p><h3>Role separation</h3></div></div><div class="security-copy"><p>Public sign-up always creates an <strong>Applicant</strong> account. Administrator accounts can only be created here or during the locked first-admin setup.</p><p>Passwords are stored using PHP's secure password hashing functions.</p></div></aside></div>

<div class="section-heading"><div><p class="eyebrow">Directory</p><h2>Registered users</h2></div></div><div class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Applications</th><th>Created</th><th>Status</th></tr></thead><tbody><?php foreach ($users as $account): ?><tr><td><strong><?= e($account['name']) ?></strong></td><td><?= e($account['email']) ?></td><td><span class="role-badge <?= e($account['role']) ?>"><?= e(ucfirst($account['role'])) ?></span></td><td><?= (int) $account['application_count'] ?></td><td><?= e(date('M j, Y', strtotime($account['created_at']))) ?></td><td><?= (int) $account['is_active'] ? 'Active' : 'Disabled' ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php render_app_footer(); ?>
