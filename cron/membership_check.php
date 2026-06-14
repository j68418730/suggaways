<?php
/**
 * Cron: Check unpaid memberships, auto-pay renewals, and unpaid invoices.
 * Runs daily via cron.
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';

$log = [];
$alerts = [];

// 1. Find memberships past end_date with auto_pay on — renew them
$renewals = db()->query("
    SELECT m.*, p.price, p.name as plan_name, u.email, u.full_name, u.id as uid
    FROM user_memberships m
    JOIN membership_plans p ON p.id = m.plan_id
    JOIN users u ON u.id = m.user_id
    WHERE m.status = 'active'
      AND m.auto_pay = 1
      AND m.end_date IS NOT NULL
      AND m.end_date <= NOW()
")->fetchAll();

foreach ($renewals as $mem) {
    try {
        db()->beginTransaction();
        $invNum = 'INV-MEM-' . time() . '-' . $mem['uid'] . '-' . $mem['id'];
        $pm = $mem['last_payment_method'] ?: 'cash_app';
        db()->prepare("INSERT INTO membership_invoices (user_id, invoice_number, amount, payment_method, status, due_date) VALUES (?,?,?,?,'pending',DATE_ADD(NOW(), INTERVAL 7 DAY))")
            ->execute([(int)$mem['uid'], $invNum, (float)$mem['price'], $pm]);
        db()->prepare("UPDATE user_memberships SET start_date=NOW(), end_date=DATE_ADD(NOW(), INTERVAL 1 MONTH) WHERE id=?")
            ->execute([(int)$mem['id']]);
        db()->commit();
        $log[] = "Renewed membership {$mem['id']} for user {$mem['uid']} — invoice $invNum created";

        // Send renewal email
        $subject = "Your Sugga Gang Membership has been renewed";
        $body = "<h2>Hello " . e($mem['full_name'] ?: 'Valued Member') . ",</h2>";
        $body .= "<p>Your <strong>" . e($mem['plan_name']) . "</strong> membership has been automatically renewed.</p>";
        $body .= "<p>A new invoice of <strong>\$" . number_format((float)$mem['price'], 2) . "</strong> has been generated.</p>";
        $body .= "<p><strong>Payment Method:</strong> " . e(ucfirst(str_replace('_', ' ', $pm))) . "</p>";
        $body .= "<p style='font-size:12px;color:#888'>SUGGAWAYZ</p>";
        send_email($mem['email'], $subject, $body);

        // In-app notification
        db()->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'membership', ?, ?, ?)")
            ->execute([(int)$mem['uid'], $subject, "Your membership has been renewed. Invoice #{$invNum} is pending.", '/?page=account']);

        $alerts[] = "Membership #{$mem['id']} renewed — Invoice #{$invNum} (\${$mem['price']})";
    } catch (Exception $e) {
        db()->rollBack();
        $log[] = "FAILED to renew membership {$mem['id']}: " . $e->getMessage();
    }
}

// 2. Find expired memberships WITHOUT auto_pay — send expiration notice
$expired = db()->query("
    SELECT m.*, p.name as plan_name, u.email, u.full_name, u.id as uid
    FROM user_memberships m
    JOIN membership_plans p ON p.id = m.plan_id
    JOIN users u ON u.id = m.user_id
    WHERE m.status = 'active'
      AND m.auto_pay = 0
      AND m.end_date IS NOT NULL
      AND m.end_date <= NOW()
")->fetchAll();

foreach ($expired as $mem) {
    $subject = "Your Sugga Gang Membership has expired";
    $body = "<h2>Hello " . e($mem['full_name'] ?: 'Valued Member') . ",</h2>";
    $body .= "<p>Your <strong>" . e($mem['plan_name']) . "</strong> membership has expired.</p>";
    $body .= "<p>To keep your benefits, please renew as soon as possible.</p>";
    $body .= "<p><a href='https://suggawayz.com/?page=membership' style='display:inline-block;padding:10px 24px;background:#00d632;color:#fff;text-decoration:none;border-radius:6px'>Renew Now</a></p>";
    $body .= "<p style='font-size:12px;color:#888'>SUGGAWAYZ</p>";
    send_email($mem['email'], $subject, $body);
    db()->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'membership', ?, ?, ?)")
        ->execute([(int)$mem['uid'], $subject, "Your membership has expired. Renew to keep your benefits.", '/?page=membership']);
    $log[] = "Expired membership {$mem['id']} for user {$mem['uid']} — notification sent";
}

// 3. Unpaid invoices older than 7 days — send reminders
$unpaid = db()->query("
    SELECT i.*, u.email, u.full_name, u.id as uid
    FROM membership_invoices i
    JOIN users u ON u.id = i.user_id
    WHERE i.status = 'pending'
      AND i.due_date < NOW()
")->fetchAll();

foreach ($unpaid as $inv) {
    $subject = "Payment Reminder — Invoice #{$inv['invoice_number']}";
    $body = "<h2>Hello " . e($inv['full_name'] ?: 'Valued Member') . ",</h2>";
    $body .= "<p>This is a reminder that invoice <strong>#{$inv['invoice_number']}</strong> for <strong>\$" . number_format((float)$inv['amount'], 2) . "</strong> is past due.</p>";
    if ($inv['payment_method']) {
        $body .= "<p><strong>Payment Method:</strong> " . e(ucfirst(str_replace('_', ' ', $inv['payment_method']))) . "</p>";
    }
    $body .= "<p>Please complete your payment as soon as possible to avoid service interruption.</p>";
    $body .= "<p style='font-size:12px;color:#888'>SUGGAWAYZ</p>";
    send_email($inv['email'], $subject, $body);
    $log[] = "Reminder sent for invoice #{$inv['invoice_number']} to {$inv['email']}";
}

// 4. Save admin alerts
if (!empty($alerts)) {
    foreach ($alerts as $alert) {
        db()->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES ((SELECT id FROM users WHERE role='webmaster' LIMIT 1), 'admin_alert', 'Membership Renewal', ?, '/?page=admin')")
            ->execute([$alert]);
    }
}

// Log results
$logFile = dirname(__DIR__) . '/storage/logs/membership_cron.log';
$dir = dirname($logFile);
if (!is_dir($dir)) mkdir($dir, 0755, true);
$timestamp = date('Y-m-d H:i:s');
$summary = "[$timestamp] Renewals: " . count($renewals) . ", Expired: " . count($expired) . ", Unpaid reminders: " . count($unpaid);
file_put_contents($logFile, $summary . "\n", FILE_APPEND);
foreach ($log as $l) file_put_contents($logFile, "  $l\n", FILE_APPEND);

echo $summary . "\n";
foreach ($log as $l) echo "  $l\n";
