<?php
require_once '/home/suggawayz/public_html/app/bootstrap.php';

// Update SUGGAWAYZ email settings for the new mail server
$settings = [
    'email_smtp_host' => 'localhost',
    'email_smtp_port' => '587',
    'email_smtp_username' => 'admin@suggawayz.com',
    'email_smtp_password' => encrypt_value('Skylinehosting171'),
    'email_smtp_encryption' => 'tls',
    'email_from_address' => 'admin@suggawayz.com',
    'email_from_name' => 'SUGGAWAYZ',
    'imap_host' => 'localhost',
    'imap_port' => '143',
];

foreach ($settings as $key => $value) {
    set_site_setting($key, $value);
}

echo "Email settings updated for new mail server.\n";
echo "SMTP: localhost:587 (TLS) as admin@suggawayz.com\n";
echo "IMAP: localhost:143\n";
echo "Password: Skylinehosting171\n";
