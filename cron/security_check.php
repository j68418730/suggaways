<?php
/**
 * Weekly security check cron script.
 * Run via: php /www/wwwroot/suggawayz/cron/security_check.php
 */
define('CRON_RUN', true);
require_once dirname(__DIR__) . '/app/bootstrap.php';

$root = dirname(__DIR__);
$issues = [];
$log = [];

// 1. File permissions
$writableDirs = [
    "$root/storage/sessions" => 'Session storage',
    "$root/public/assets/img/products" => 'Product images',
    "$root/public/assets/img/avatars" => 'Avatar uploads',
];
foreach ($writableDirs as $dir => $label) {
    $ok = is_writable($dir);
    $log[] = ($ok ? 'OK' : 'FAIL') . " - $label writable";
    if (!$ok) $issues[] = "$label is not writable";
}

// 2. Display errors
$displayErrors = ini_get('display_errors');
$log[] = ($displayErrors ? 'FAIL' : 'OK') . ' - display_errors is ' . ($displayErrors ? 'ON' : 'OFF');
if ($displayErrors) $issues[] = 'display_errors is ON';

// 3. SMTP password encryption
$smtpPass = site_setting('email_smtp_password', '');
$decrypted = $smtpPass ? decrypt_value($smtpPass) : '';
$isPlaintext = $smtpPass && !$decrypted;
$log[] = ($isPlaintext ? 'FAIL' : 'OK') . ' - SMTP password encrypted';
if ($isPlaintext) $issues[] = 'SMTP password is stored as plaintext';

// 4. Database password
$dbFile = "$root/config/database.php";
$dbConfig = file_exists($dbFile) ? require $dbFile : [];
$defaultPass = $dbConfig['password'] ?? '';
$isDefault = ($defaultPass === 'suggawayz_secret' || $defaultPass === 'root' || $defaultPass === '');
$log[] = ($isDefault ? 'FAIL' : 'OK') . ' - Database password strength';
if ($isDefault) $issues[] = 'Database password is weak or default';

// 5. SSL certificate
$sslDaysLeft = 0;
$sslValid = false;
$ctx = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => false]]);
$client = @stream_socket_client('ssl://suggawayz.com:443', $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
if ($client) {
    $params = stream_context_get_params($client);
    fclose($client);
    if (!empty($params['options']['ssl']['peer_certificate'])) {
        $cert = @openssl_x509_parse($params['options']['ssl']['peer_certificate']);
        if ($cert && isset($cert['validTo_time_t'])) {
            $sslDaysLeft = floor(($cert['validTo_time_t'] - time()) / 86400);
            $sslValid = $sslDaysLeft > 0;
        }
    }
}
$log[] = ($sslValid ? 'OK' : 'FAIL') . " - SSL certificate (" . ($sslValid ? "{$sslDaysLeft}d left" : 'issue detected') . ")";
if (!$sslValid) $issues[] = 'SSL certificate issue detected';
elseif ($sslDaysLeft < 30) $issues[] = "SSL certificate expires in {$sslDaysLeft} days";

// 6. Recent backups
$backupDir = "$root/storage/backups";
$backups = is_dir($backupDir) ? array_diff(scandir($backupDir), ['.','..']) : [];
$backupList = array_values($backups);
$latestBackup = !empty($backupList) ? $backupDir . '/' . $backupList[0] : null;
$backupAge = $latestBackup ? floor((time() - filemtime($latestBackup)) / 86400) : -1;
$backupOk = $backupAge >= 0 && $backupAge <= 7;
$log[] = ($backupOk ? 'OK' : 'FAIL') . " - Database backup (" . ($latestBackup ? basename($latestBackup) . " {$backupAge}d old" : 'none') . ")";
if (!$backupOk) $issues[] = 'No recent database backup';

// Log results
$logFile = "$root/storage/logs/security_cron.log";
$dir = dirname($logFile);
if (!is_dir($dir)) mkdir($dir, 0755, true);
$timestamp = date('Y-m-d H:i:s');
$result = "[$timestamp] " . (empty($issues) ? 'ALL OK' : count($issues) . ' ISSUE(S): ' . implode('; ', $issues));
file_put_contents($logFile, $result . "\n", FILE_APPEND);

// Output
echo $result . "\n";
foreach ($log as $l) echo "  $l\n";

// Send email alert if issues found
if (!empty($issues)) {
    $adminEmail = site_setting('email_from_address', '');
    if ($adminEmail) {
        $subject = '⚠️ Security issues found on suggawayz.com';
        $body = '<h2>Weekly Security Check Results</h2>';
        $body .= '<p>The following issues were detected:</p><ul>';
        foreach ($issues as $issue) $body .= '<li>' . e($issue) . '</li>';
        $body .= '</ul><p>Log in to the admin panel to run fixes: <a href="https://suggawayz.com/?page=admin&tab=security">Security Dashboard</a></p>';
        send_email($adminEmail, $subject, $body);
        echo "  Alert email sent to $adminEmail\n";
    }
}
