<?php
require_once '/home/suggawayz/public_html/app/bootstrap.php';
// Set SMTP host to empty so it uses local mail() which calls Postfix directly
set_site_setting('email_smtp_host', '');
echo "SMTP host cleared - using local mail()\n";
// Test send
$r = send_email('admin@suggawayz.com', 'Test', '<p>test</p>');
echo "Result: " . ($r ? 'SENT' : 'FAILED') . "\n";
