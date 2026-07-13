<?php
require_once '/home/suggawayz/public_html/app/bootstrap.php';
set_site_setting('email_smtp_host', 'mail.planet-hosts.com');
echo "SMTP host updated to mail.planet-hosts.com\n";
// Also update IMAP
set_site_setting('imap_host', 'mail.planet-hosts.com');
echo "IMAP host updated to mail.planet-hosts.com\n";
