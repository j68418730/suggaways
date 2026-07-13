<?php
require_once '/home/suggawayz/public_html/app/bootstrap.php';

$low = db()->query("SELECT p.id, p.name, COALESCE(i.stock_quantity,0) as stock
    FROM products p LEFT JOIN inventory i ON i.product_id=p.id
    WHERE p.status='active' AND (i.stock_quantity IS NULL OR i.stock_quantity <= 0)")->fetchAll();

echo "Products needing stock: " . count($low) . "\n";
foreach ($low as $p) {
    $inv = db()->prepare("SELECT id FROM inventory WHERE product_id=?")->execute([(int)$p['id']]) ? db()->query("SELECT id FROM inventory WHERE product_id=" . (int)$p['id'])->fetch() : null;
    if ($inv) {
        db()->prepare("UPDATE inventory SET stock_quantity=20 WHERE product_id=?")->execute([(int)$p['id']]);
    } else {
        db()->prepare("INSERT INTO inventory (product_id, stock_quantity) VALUES (?,20)")->execute([(int)$p['id']]);
    }
    echo "  {$p['name']} ({$p['stock']} -> 20)\n";
}

// Also list all products with current stock
echo "\n=== All products stock ===\n";
$all = db()->query("SELECT p.name, COALESCE(i.stock_quantity,0) as stock FROM products p LEFT JOIN inventory i ON i.product_id=p.id WHERE p.status='active' ORDER BY p.name")->fetchAll();
foreach ($all as $p) {
    echo "  " . ($p['stock'] > 0 ? '✅' : '❌') . " {$p['name']}: {$p['stock']}\n";
}
