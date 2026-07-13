<?php
require_once '/home/suggawayz/public_html/app/bootstrap.php';
$rows = db()->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_value LIKE '%GOES LIVE%' OR setting_value LIKE '%countdown%' OR setting_key LIKE '%launch%' OR setting_key LIKE '%coming%'")->fetchAll();
if (empty($rows)) {
    echo "No matching settings found.\n";
    // Check hero settings
    $hero = db()->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'hero%'")->fetchAll();
    foreach ($hero as $h) {
        echo "{$h['setting_key']}: {$h['setting_value']}\n";
    }
} else {
    foreach ($rows as $r) {
        echo "{$r['setting_key']}: {$r['setting_value']}\n";
    }
}
