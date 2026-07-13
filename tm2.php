<?php
require_once '/home/suggawayz/public_html/app/bootstrap.php';
// Try sending via SMTP with port 25 instead of 587
$result = send_email('admin@suggawayz.com', 'Test from SUGGAWAYZ', '<h1>Test</h1><p>Mail server working.</p>');
echo $result ? "SENT\n" : "FAILED\n";

// Check what the site settings say
echo "SMTP Host: " . site_setting('email_smtp_host', 'not set') . "\n";
echo "SMTP Port: " . site_setting('email_smtp_port', 'not set') . "\n";
