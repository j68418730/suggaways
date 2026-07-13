<?php
require_once '/home/suggawayz/public_html/app/bootstrap.php';

// Check if email_access table exists
$tables = db()->query("SHOW TABLES LIKE 'email_access'")->fetchAll();
if (empty($tables)) {
    db()->query("CREATE TABLE email_access (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        mailbox_email VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_email_access (user_id, mailbox_email)
    )");
    echo "Created email_access table\n";
}

// Get Edward's user ID
$edward = db()->prepare("SELECT id FROM users WHERE username='suggawayz'")->execute() ? db()->query("SELECT id FROM users WHERE username='suggawayz'")->fetch() : null;
if (!$edward) { echo "Edward not found\n"; exit; }
$uid = (int)$edward['id'];
echo "Edward ID: $uid\n";

// Grant access to all suggawayz.com mailboxes
$emails = ['admin@suggawayz.com', 'suggawayz@suggawayz.com', 'scoutman@suggawayz.com'];
foreach ($emails as $email) {
    db()->prepare("INSERT IGNORE INTO email_access (user_id, mailbox_email) VALUES (?,?)")->execute([$uid, $email]);
    echo "Granted $email to Edward\n";
}

echo "Done\n";
