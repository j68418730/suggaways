<?php
require_once '/home/suggawayz/public_html/app/bootstrap.php';
$hash = password_hash('BarbaraBrooks1952!', PASSWORD_ARGON2ID);
db()->prepare("UPDATE users SET password_hash = ? WHERE username = 'suggawayz'")->execute([$hash]);
echo "Password updated for suggawayz\n";
echo "Verify: " . (password_verify('BarbaraBrooks1952!', db()->query("SELECT password_hash FROM users WHERE username='suggawayz'")->fetchColumn()) ? 'OK' : 'FAIL') . "\n";
