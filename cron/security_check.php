<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';

// Security checks: verify file permissions
$root = dirname(__DIR__);
$checks = [
    "$root/public/assets/img/avatars" => 'Avatar uploads',
    "$root/public/assets/img/products" => 'Product images',
    "$root/storage/sessions" => 'Sessions',
];

$issues = [];
foreach ($checks as $dir => $label) {
    if (!is_writable($dir)) {
        $issues[] = "$label ($dir) not writable";
    }
}

$log = dirname(__DIR__) . '/storage/logs/security_cron.log';
$msg = '[' . date('Y-m-d H:i:s') . '] Security check: ' . (empty($issues) ? 'All OK' : 'Issues: ' . implode(', ', $issues)) . "\n";
file_put_contents($log, $msg, FILE_APPEND);
