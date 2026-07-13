<?php
require_once '/home/suggawayz/public_html/app/bootstrap.php';

$sql = file_get_contents('/home/suggawayz/public_html/storage/backup.sql');
if (!$sql) { die("Failed to read backup SQL\n"); }

db()->exec("SET FOREIGN_KEY_CHECKS = 0");

// Only restore tables that exist in current schema
$existing = db()->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$tables = array_intersect(['categories', 'products', 'inventory', 'coupons', 'coming_soon', 'shipping', 'size_charts', 'faq_items', 'blog_posts'], $existing);
$imported = 0; $skipped = 0;

foreach ($tables as $table) {
    db()->exec("TRUNCATE TABLE `$table`");
    
    // Get column count for validation
    $colCount = (int)db()->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='suggawayz' AND TABLE_NAME='$table'")->fetchColumn();
    
    $lines = explode("\n", $sql);
    $currentInsert = '';
    $inTable = false;
    
    foreach ($lines as $line) {
        $trimmed = trim($line);
        
        if (preg_match("/^INSERT INTO `$table` VALUES/i", $trimmed)) {
            $inTable = true;
            $currentInsert = $trimmed;
            continue;
        }
        
        if ($inTable) {
            $currentInsert .= "\n" . $trimmed;
        }
        
        if ($inTable && (str_ends_with(trim($currentInsert), ';') || str_ends_with(trim($currentInsert), ');'))) {
            $valueCount = substr_count($currentInsert, "),(") + 1;
            // Quick column check: count values in first row
            $firstVals = substr($currentInsert, strpos($currentInsert, "VALUES (") + 8);
            $firstVals = substr($firstVals, 0, strpos($firstVals, ")"));
            $valCount = substr_count($firstVals, "','") + 1;
            
            if ($valCount == $colCount) {
                try {
                    db()->exec($currentInsert);
                    $imported++;
                } catch (\Throwable $e) {
                    $skipped++;
                }
            } else {
                $skipped++;
            }
            $inTable = false;
            $currentInsert = '';
        }
    }
    echo "Restored $table\n";
}

db()->exec("SET FOREIGN_KEY_CHECKS = 1");

echo "\nImported: $imported, Skipped: $skipped\n";

$counts = [
    'categories' => 'SELECT COUNT(*) FROM categories',
    'products' => "SELECT COUNT(*) FROM products WHERE status='active'",
    'coupons' => 'SELECT COUNT(*) FROM coupons',
    'inventory' => 'SELECT COUNT(*) FROM inventory',
];
foreach ($counts as $label => $q) {
    echo "$label: " . db()->query($q)->fetchColumn() . "\n";
}
