<?php
// Send test email first, then fix mailboxes
require_once '/home/suggawayz/public_html/app/bootstrap.php';

// 1. Send test email
$r = send_email('nd2no_19@hotmail.com', 'Test from SUGGAWAYZ mail server', '<h1>Test Email</h1><p>If you receive this, the SUGGAWAYZ mail server is working correctly.</p><p>From: suggawayz.com</p>');
echo "Test email to nd2no_19@hotmail.com: " . ($r ? 'SENT' : 'FAILED') . "\n";

// 2. Fix SQLite permissions
$dbPath = '/var/vmail/postfixadmin.db';
$dir = dirname($dbPath);
chmod($dir, 0777);
chmod($dbPath, 0666);

// 3. Create mailboxes via sqlite3 exec
try {
    $sqldb = new PDO("sqlite:$dbPath");
    $sqldb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pass = 'Skylinehosting171';
    $domain = 'suggawayz.com';
    $emails = ['suggawayz' => 'SUGGAWAYZ', 'scoutman' => 'Scoutman'];
    
    foreach ($emails as $local => $name) {
        $email = "$local@$domain";
        $check = $sqldb->prepare("SELECT id FROM mailbox WHERE username=?");
        $check->execute([$email]);
        if ($check->fetch()) {
            echo "$email already exists\n";
            continue;
        }
        
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $maildir = "$domain/$local/";
        $stmt = $sqldb->prepare("INSERT INTO mailbox (username, password, password_encode, full_name, maildir, quota, local_part, domain, created, modified, active) VALUES (?,?,'{BLF-CRYPT}',?,?,?,?,?,datetime('now'),datetime('now'),1)");
        $stmt->execute([$email, $hash, $name, $maildir, 1048576, $local, $domain]);
        
        $dir = "/var/vmail/$domain/$local";
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        
        echo "Created $email\n";
    }
    
} catch (\Throwable $e) {
    echo "SQLite error: " . $e->getMessage() . "\n";
}
