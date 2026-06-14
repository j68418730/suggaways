<?php
/**
 * Cron: Sign-in log cleanup
 * - Delete successful sign-ins older than 6 hours
 * - Delete failed sign-ins older than 10 days
 * Runs every few hours via cron.
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';

$log = [];

// Delete successful sign-ins older than 6 hours
$deletedSuccess = db()->exec("DELETE FROM sign_in_log WHERE status='success' AND created_at < DATE_SUB(NOW(), INTERVAL 6 HOUR)");
$log[] = "Deleted $deletedSuccess successful sign-ins (older than 6 hours)";

// Delete failed sign-ins older than 10 days
$deletedFailed = db()->exec("DELETE FROM sign_in_log WHERE status='failed' AND created_at < DATE_SUB(NOW(), INTERVAL 10 DAY)");
$log[] = "Deleted $deletedFailed failed sign-ins (older than 10 days)";

// Log
$logFile = dirname(__DIR__) . '/storage/logs/signin_cron.log';
$dir = dirname($logFile);
if (!is_dir($dir)) mkdir($dir, 0755, true);
$timestamp = date('Y-m-d H:i:s');
file_put_contents($logFile, "[$timestamp] " . implode('; ', $log) . "\n", FILE_APPEND);

echo implode("\n", $log) . "\n";
