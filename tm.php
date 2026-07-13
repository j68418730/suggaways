<?php
require_once '/home/suggawayz/public_html/app/bootstrap.php';
$result = send_email('admin@suggawayz.com', 'Test from SUGGAWAYZ', '<h1>Test</h1><p>Mail server is working.</p>');
echo $result ? "SENT\n" : "FAILED\n";
