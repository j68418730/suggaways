<?php
require_once '/home/suggawayz/public_html/app/bootstrap.php';

// Test SMTP authentication
$host = 'localhost';
$port = 587;
$user = 'admin@suggawayz.com';
$pass = 'Skylinehosting171';

$fp = @fsockopen($host, $port, $errno, $errstr, 10);
if (!$fp) { echo "Connect failed: $errstr\n"; exit; }

$response = '';
$read = function() use ($fp) { return fread($fp, 512); };
$write = function($cmd) use ($fp) { fwrite($fp, $cmd); fflush($fp); };

echo "Banner: " . $read();

$write("EHLO suggawayz.com\r\n");
echo "EHLO: " . $read();

$write("STARTTLS\r\n");
$r = $read();
echo "STARTTLS: $r";
if (substr($r, 0, 3) !== '220') { echo "TLS failed\n"; exit; }

stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
$write("EHLO suggawayz.com\r\n");
echo "EHLO2: " . $read();

$write("AUTH LOGIN\r\n");
echo "AUTH: " . $read();

$write(base64_encode($user) . "\r\n");
echo "USER: " . $read();

$write(base64_encode($pass) . "\r\n");
echo "PASS: " . $read();

$write("MAIL FROM:<admin@suggawayz.com>\r\n");
echo "MAIL FROM: " . $read();

$write("RCPT TO:<admin@suggawayz.com>\r\n");
echo "RCPT TO: " . $read();

$write("DATA\r\n");
echo "DATA: " . $read();

$write("Subject: Test\r\n\r\nHello\r\n.\r\n");
echo "SEND: " . $read();

$write("QUIT\r\n");
fclose($fp);
echo "DONE\n";
