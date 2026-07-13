<?php
$dbPath = '/var/vmail/postfixadmin.db';
$sqldb = new PDO("sqlite:$dbPath");
$pass = 'BarbaraBrooks1952!';
$hash = password_hash($pass, PASSWORD_BCRYPT);

$emails = ['admin@suggawayz.com', 'suggawayz@suggawayz.com', 'scoutman@suggawayz.com'];
foreach ($emails as $email) {
    $sqldb->prepare("UPDATE mailbox SET password=? WHERE username=?")->execute([$hash, $email]);
    echo "Updated $email\n";
}

// Also update site settings for webmail access
require_once '/home/suggawayz/public_html/app/bootstrap.php';
$creds = json_decode(site_setting('_mailbox_creds', '{}'), true);
foreach ($emails as $email) {
    $creds[$email] = $pass;
}
set_site_setting('_mailbox_creds', json_encode($creds));
echo "Webmail credentials updated\n";
echo "All passwords set to: $pass\n";
