<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';

// Delete all successful entries every 4 hours — keep failed attempts for rate limiting
$stmt = db()->prepare("DELETE FROM sign_in_log WHERE status = 'success'");
$stmt->execute();
$deleted = $stmt->rowCount();

// Log the run
$log = dirname(__DIR__) . '/storage/logs/signin_cron.log';
file_put_contents($log, '[' . date('Y-m-d H:i:s') . "] Cleaned $deleted successful sign-in entries (kept failed)\n", FILE_APPEND);
