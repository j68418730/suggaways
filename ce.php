<?php
require_once '/home/suggawayz/public_html/app/bootstrap.php';

$dbPath = '/var/vmail/postfixadmin.db';
$sqldb = new PDO("sqlite:$dbPath");
$pass = 'Skylinehosting171';
$domain = 'suggawayz.com';

$emails = [
    'admin' => 'Admin',
    'suggawayz' => 'SUGGAWAYZ',
    'scoutman' => 'Scoutman',
];

foreach ($emails as $local => $name) {
    $email = "$local@$domain";
    $existing = $sqldb->prepare("SELECT id FROM mailbox WHERE username=?");
    $existing->execute([$email]);
    if ($existing->fetch()) {
        echo "$email already exists\n";
        continue;
    }
    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $maildir = "$domain/$local/";
    $stmt = $sqldb->prepare("INSERT INTO mailbox (username, password, password_encode, full_name, maildir, quota, local_part, domain, created, modified, active) VALUES (?,?,'{BLF-CRYPT}',?,?,?,?,?,datetime('now'),datetime('now'),1)");
    $stmt->execute([$email, $hash, $name, $maildir, 1048576, $local, $domain]);
    
    // Create maildir
    $dir = "/var/vmail/$domain/$local";
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    
    // Store password for webmail
    $creds = json_decode(site_setting('_mailbox_creds', '{}'), true);
    $creds[$email] = $pass;
    set_site_setting('_mailbox_creds', json_encode($creds));
    
    // Grant access to all super_admin users
    $admins = db()->query("SELECT id FROM users WHERE role IN ('webmaster','super_admin') AND is_deleted=0")->fetchAll();
    foreach ($admins as $a) {
        db()->prepare("INSERT IGNORE INTO email_access (user_id, mailbox_email) VALUES (?,?)")->execute([(int)$a['id'], $email]);
    }
    
    echo "Created $email\n";
}

echo "\nDone. All passwords: $pass\n";
