<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (!current_user()) {
    redirect('login.php');
}
redirect(role_home_path(current_user()['role'] ?? ''));
