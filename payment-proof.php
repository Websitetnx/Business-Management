<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(404); exit('Payment confirmation not found.'); }
$statement = db()->prepare('SELECT p.proof_original_name, p.proof_stored_name, p.proof_mime_type, a.user_id FROM payments p JOIN applications a ON a.id = p.application_id WHERE p.id = ?');
$statement->execute([$id]);
$payment = $statement->fetch();
if (!$payment || !$payment['proof_stored_name']) { http_response_code(404); exit('Payment confirmation not found.'); }
if (!can_manage_payments($user['role'] ?? '') && (int) $payment['user_id'] !== (int) $user['id']) { http_response_code(403); exit('Access denied.'); }
$path = (string) app_config('upload_dir') . '/' . basename((string) $payment['proof_stored_name']);
if (!is_file($path)) { http_response_code(404); exit('Payment confirmation not found.'); }
header('Content-Type: ' . ($payment['proof_mime_type'] ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . str_replace(['"', "\r", "\n"], '', basename((string) $payment['proof_original_name'])) . '"');
header('Cache-Control: private, no-store, max-age=0');
readfile($path);
exit;
