<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('permitflow_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/auth_otp.php';
require_once __DIR__ . '/openai.php';
require_once __DIR__ . '/analytics.php';
require_once __DIR__ . '/prediction.php';
require_once __DIR__ . '/notifications.php';

date_default_timezone_set((string) app_config('timezone'));

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: geolocation=(self)');
}
