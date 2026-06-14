<?php
declare(strict_types=1);

$sessPath = dirname(__DIR__) . '/storage/sessions';
if (!is_dir($sessPath)) { @mkdir($sessPath, 0755, true); }
session_save_path($sessPath);
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
]);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/Helpers/functions.php';
require_once __DIR__ . '/Views/render.php';
require_once __DIR__ . '/Views/admin_render.php';

function bootstrap_database(): void
{
    static $booted = false;
    if ($booted) return;
    $booted = true;

    $schema = dirname(__DIR__) . '/database/schema.sql';
    if (file_exists($schema)) {
        db()->exec(file_get_contents($schema));
    }

    seed_user('spectre', 'admin', 'webmaster', 'spectre@suggawayz.local', 'SUGGAWAYZ Webmaster');
    seed_user('user', 'admin', 'customer', 'user@suggawayz.local', 'SUGGAWAYZ Customer');
}

function seed_user(string $username, string $password, string $role, string $email, string $fullName): void
{
    $stmt = db()->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    if ($stmt->fetch()) return;

    $hash = password_hash($password, PASSWORD_ARGON2ID);
    $isEmployee = $role !== 'customer' ? 1 : 0;
    db()->prepare('INSERT INTO users (role, username, email, password_hash, full_name, is_employee, email_verified_at) VALUES (?, ?, ?, ?, ?, ?, NOW())')
        ->execute([$role, $username, $email, $hash, $fullName, $isEmployee]);
    $userId = (int)db()->lastInsertId();

    if ($role === 'webmaster') {
        db()->prepare('INSERT INTO admins (user_id, permission_level) VALUES (?, ?)')
            ->execute([$userId, 'webmaster']);
    }
}

function record_signin(?int $userId, string $username, string $status): void
{
    db()->prepare('INSERT INTO sign_in_log (user_id, username, ip_address, user_agent, status) VALUES (?, ?, ?, ?, ?)')
        ->execute([$userId, $username, $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255), $status]);
}

bootstrap_database();
