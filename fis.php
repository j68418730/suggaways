<?php
require_once '/home/suggawayz/public_html/app/bootstrap.php';
set_site_setting('imap_host', 'localhost');
set_site_setting('imap_port', '143');
echo "IMAP set to localhost:143\n";

// Store mailbox credentials for admin accounts
$creds = json_decode(site_setting('_mailbox_creds', '{}'), true);
$creds['admin@suggawayz.com'] = 'Skylinehosting171';
$creds['admin@planet-hosts.com'] = 'Skylinehosting171';
set_site_setting('_mailbox_creds', json_encode($creds));
echo "Mailbox credentials stored\n";
