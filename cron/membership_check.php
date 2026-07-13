<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';

// Process membership renewals
$stmt = db()->query("SELECT um.*, u.email, u.full_name, mp.price FROM user_memberships um JOIN users u ON u.id=um.user_id JOIN membership_plans mp ON mp.id=um.plan_id WHERE um.status='active' AND um.auto_pay=1 AND um.expires_at < DATE_ADD(NOW(), INTERVAL 3 DAY)");
$members = $stmt->fetchAll();

$renewed = 0;
foreach ($members as $m) {
    $invNum = 'INV-MEM-' . time() . '-' . $m['user_id'];
    db()->prepare("INSERT INTO membership_invoices (user_id, invoice_number, amount, status, due_date) VALUES (?,?,?,'pending',DATE_ADD(NOW(), INTERVAL 7 DAY))")->execute([$m['user_id'], $invNum, $m['price']]);
    $renewed++;
}

$log = dirname(__DIR__) . '/storage/logs/membership_cron.log';
file_put_contents($log, '[' . date('Y-m-d H:i:s') . "] Processed $renewed membership renewals\n", FILE_APPEND);
