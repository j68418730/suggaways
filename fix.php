<?php
require_once '/home/suggawayz/public_html/app/bootstrap.php';

// Reset passwords
$hash = password_hash('Skylinehosting171', PASSWORD_ARGON2ID);
db()->prepare("UPDATE users SET password_hash = ? WHERE username = 'spectre'")->execute([$hash]);
db()->prepare("UPDATE users SET password_hash = ? WHERE username = 'user'")->execute([$hash]);
echo "Passwords reset\n";

// Remove seed_user
db()->prepare("DELETE FROM users WHERE username = 'seed_user'")->execute();
echo "Seed user removed\n";

// Fix payment settings to only have PayPal + Cash App
db()->prepare("DELETE FROM payment_settings WHERE provider NOT IN ('paypal','cash_app')")->execute();
db()->prepare("UPDATE payment_settings SET sandbox_mode=0 WHERE provider='paypal'")->execute();
db()->prepare("UPDATE payment_settings SET sandbox_mode=0, extra_settings='{\"cashtag\":\"SuggaWayz\"}' WHERE provider='cash_app'")->execute();
echo "Payment settings updated\n";

// Check current state
$prods = db()->query("SELECT COUNT(*) as c FROM products WHERE status='active'")->fetchColumn();
$coups = db()->query("SELECT COUNT(*) as c FROM coupons")->fetchColumn();
echo "Products: $prods, Coupons: $coups\n";
echo "Done\n";
