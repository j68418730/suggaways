<?php

function env(string $key, mixed $default = null): mixed
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    $lower = strtolower($value);
    if (in_array($lower, ['true', '(true)'])) return true;
    if (in_array($lower, ['false', '(false)'])) return false;
    if (in_array($lower, ['null', '(null)'])) return null;
    return $value;
}

function config(string $key, mixed $default = null): mixed
{
    static $loaded = [];
    $parts = explode('.', $key, 2);
    $file = $parts[0];
    if (!isset($loaded[$file])) {
        $path = dirname(__DIR__, 2) . "/config/{$file}.php";
        $loaded[$file] = file_exists($path) ? require $path : [];
    }
    if (!isset($parts[1])) return $loaded[$file];
    $keys = explode('.', $parts[1]);
    $value = $loaded[$file];
    foreach ($keys as $k) {
        if (!is_array($value) || !array_key_exists($k, $value)) return $default;
        $value = $value[$k];
    }
    return $value;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;

    $cfg = config('database');
    $pdo = new PDO(
        "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset={$cfg['charset']}",
        $cfg['username'],
        $cfg['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (empty($token) || !is_string($token) || !hash_equals(csrf_token(), $token)) {
        session_flash('error', 'Session expired or security token invalid. Please try again.');
        $ref = $_SERVER['HTTP_REFERER'] ?? '/';
        header("Location: {$ref}", true, 302);
        exit;
    }
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

function session_flash(string $key, mixed $value = null): mixed
{
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }
    $val = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $val;
}

function session_get(string $key, mixed $default = null): mixed
{
    return $_SESSION[$key] ?? $default;
}

function session_set(string $key, mixed $value): void
{
    $_SESSION[$key] = $value;
}

function session_has(string $key): bool
{
    return isset($_SESSION[$key]);
}

function redirect(string $url, int $status = 302): never
{
    header("Location: {$url}", true, $status);
    exit;
}

function redirect_back(): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '/';
    redirect($ref);
}

function abort(int $code = 404, string $message = 'Not Found'): never
{
    http_response_code($code);
    view("errors.{$code}", ['message' => $message], true);
    exit;
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) return null;
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function is_admin(?array $user): bool
{
    return $user && in_array($user['role'], ['webmaster', 'super_admin', 'support', 'inventory_manager'], true);
}

function employee_can(?array $user, string $tab, string $action = 'view'): bool
{
    if (is_admin($user)) return true;
    if (!$user) return false;
    if ($action === 'edit' || $action === 'delete') return false;
    $viewable = ['dashboard', 'products', 'categories', 'orders', 'customers', 'inventory', 'coupons', 'pos', 'blog', 'events'];
    return in_array($tab, $viewable, true);
}

function is_clocked_in(int $employeeId): ?array
{
    $stmt = db()->prepare("SELECT ce.*, ps.status as session_status FROM clock_events ce LEFT JOIN pos_sessions ps ON ps.id = ce.pos_session_id WHERE ce.employee_id = ? AND ce.clock_out_at IS NULL ORDER BY ce.clock_in_at DESC LIMIT 1");
    $stmt->execute([$employeeId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function clock_in(int $employeeId, float $openingBalance = 0): array
{
    $existing = is_clocked_in($employeeId);
    if ($existing) return ['success' => false, 'message' => 'Already clocked in.'];

    db()->beginTransaction();
    try {
        db()->prepare('INSERT INTO pos_sessions (employee_id, opening_balance, status, notes) VALUES (?, ?, "open", ?)')
            ->execute([$employeeId, $openingBalance, 'Clock-in session']);
        $sessionId = (int)db()->lastInsertId();

        db()->prepare('INSERT INTO clock_events (employee_id, pos_session_id, clock_in_at, notes) VALUES (?, ?, NOW(), ?)')
            ->execute([$employeeId, $sessionId, 'Clocked in']);
        db()->commit();
        return ['success' => true, 'session_id' => $sessionId];
    } catch (Exception $e) {
        db()->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function clock_out(int $employeeId): array
{
    $clockEvent = is_clocked_in($employeeId);
    if (!$clockEvent) return ['success' => false, 'message' => 'Not clocked in.'];

    db()->beginTransaction();
    try {
        $sessionId = (int)$clockEvent['pos_session_id'];
        $opening = (float)db()->query('SELECT opening_balance FROM pos_sessions WHERE id = ' . $sessionId)->fetchColumn();
        $total = db()->query("SELECT COALESCE(SUM(CASE WHEN type='cash_in' OR type='sale' THEN amount WHEN type='cash_out' OR type='refund' OR type='payout' THEN -amount ELSE 0 END), 0) as balance FROM pos_transactions WHERE pos_session_id = " . $sessionId)->fetch();
        $summary = db()->query("SELECT type, COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM pos_transactions WHERE pos_session_id = " . $sessionId . " GROUP BY type")->fetchAll();
        $closing = $opening + (float)$total['balance'];

        db()->prepare('UPDATE pos_sessions SET status="closed", closed_at=NOW(), closing_balance=? WHERE id=?')
            ->execute([$closing, $sessionId]);
        db()->prepare('UPDATE clock_events SET clock_out_at=NOW() WHERE id=?')
            ->execute([(int)$clockEvent['id']]);
        db()->commit();
        return [
            'success' => true,
            'closing_balance' => $closing,
            'opening_balance' => $opening,
            'session_id' => $sessionId,
            'summary' => $summary,
        ];
    } catch (Exception $e) {
        db()->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function site_setting(string $key, string $default = ''): string
{
    $stmt = db()->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    return $val !== false && $val !== null ? (string)$val : $default;
}

function encrypt_value(string $value): string
{
    $key = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : site_setting('_encryption_key', '');
    if (!$key) {
        $key = bin2hex(random_bytes(32));
        set_site_setting('_encryption_key', $key);
    }
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($value, 'aes-256-cbc', hex2bin($key), OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $encrypted);
}

function decrypt_value(string $encoded): string
{
    $key = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : site_setting('_encryption_key', '');
    if (!$key) return '';
    $data = base64_decode($encoded);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    return openssl_decrypt($encrypted, 'aes-256-cbc', hex2bin($key), OPENSSL_RAW_DATA, $iv) ?: '';
}

function set_site_setting(string $key, string $value): void
{
    db()->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?')
        ->execute([$key, $value, $value]);
}

function login_user(string $username, string $password): bool
{
    // Rate limiting: max 5 failed attempts in 15 minutes
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $attempts = db()->prepare("SELECT COUNT(*) FROM sign_in_log WHERE ip_address = ? AND status = 'failed' AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $attempts->execute([$ip]);
    if ((int)$attempts->fetchColumn() >= 5) {
        return false;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        record_signin(null, $username, 'failed');
        return false;
    }

    if (!empty($user['is_deleted'])) {
        record_signin(null, $username, 'failed');
        return false;
    }

    if ($user['two_factor_enabled'] && !session_has('2fa_passed')) {
        $_SESSION['2fa_user_id'] = (int)$user['id'];
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int)$user['id']]);
    record_device((int)$user['id']);
    record_signin((int)$user['id'], $username, 'success');
    audit('login', 'users', (string)$user['id']);
    return true;
}

function logout_user(): void
{
    if (!empty($_SESSION['user_id'])) {
        audit('logout', 'users', (string)$_SESSION['user_id']);
    }
    $_SESSION = [];
    session_destroy();
}

function record_device(int $userId): void
{
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 500);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = db()->prepare(
        'INSERT INTO device_tracking (user_id, device_name, ip_address, user_agent, last_seen_at) VALUES (?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE last_seen_at = NOW(), ip_address = VALUES(ip_address)'
    );
    $stmt->execute([$userId, $ua, $ip, $ua]);
}

function audit(string $action, ?string $entityType = null, ?string $entityId = null, array $metadata = []): void
{
    $userId = $_SESSION['user_id'] ?? null;
    $stmt = db()->prepare(
        'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, user_agent, metadata) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $userId,
        $action,
        $entityType,
        $entityId,
        $_SERVER['REMOTE_ADDR'] ?? null,
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        json_encode($metadata),
    ]);
}

function money(float $amount, string $currency = 'USD'): string
{
    $symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥', 'CAD' => 'C$', 'AUD' => 'A$'];
    $symbol = $symbols[$currency] ?? '$';
    return $symbol . number_format($amount, 2);
}

function cart_count(): int
{
    if (!empty($_SESSION['cart'])) {
        return array_sum(array_column($_SESSION['cart'], 'quantity'));
    }
    if (!empty($_SESSION['user_id'])) {
        $stmt = db()->prepare('SELECT SUM(quantity) FROM cart WHERE user_id = ?');
        $stmt->execute([(int)$_SESSION['user_id']]);
        return (int)$stmt->fetchColumn();
    }
    return 0;
}

function cart_total(): float
{
    $total = 0.0;
    $items = cart_items();
    foreach ($items as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    return $total;
}

function cart_items(): array
{
    $items = $_SESSION['cart'] ?? [];
    if (empty($items) && !empty($_SESSION['user_id'])) {
        $stmt = db()->query('SELECT c.*, p.name, p.price, p.sale_price, p.slug, p.images, p.sizes, p.colors FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ' . (int)$_SESSION['user_id']);
        $dbItems = $stmt->fetchAll();
        foreach ($dbItems as $dbItem) {
            $items[] = [
                'product_id' => $dbItem['product_id'],
                'name' => $dbItem['name'],
                'price' => $dbItem['sale_price'] ?: $dbItem['price'],
                'slug' => $dbItem['slug'],
                'quantity' => (int)$dbItem['quantity'],
                'size' => $dbItem['size'],
                'color' => $dbItem['color'],
                'image' => json_decode($dbItem['images'], true)[0] ?? '/assets/img/background.png',
            ];
        }
    }
    // Merge preorder items from session
    if (!empty($_SESSION['preorder_cart'])) {
        foreach ($_SESSION['preorder_cart'] as $key => $po) {
            $items[$key] = $po;
        }
    }
    return $items;
}

function add_preorder_to_cart(int $comingSoonId, int $quantity = 1): void
{
    $idx = "preorder-{$comingSoonId}";
    if (isset($_SESSION['preorder_cart'][$idx])) {
        $_SESSION['preorder_cart'][$idx]['quantity'] += $quantity;
        return;
    }
    $stmt = db()->prepare('SELECT * FROM coming_soon WHERE id = ?');
    $stmt->execute([$comingSoonId]);
    $cs = $stmt->fetch();
    if (!$cs) return;
    $_SESSION['preorder_cart'][$idx] = [
        'coming_soon_id' => $comingSoonId,
        'name' => $cs['name'],
        'price' => (float)$cs['price'],
        'quantity' => $quantity,
        'image' => $cs['image'] ?: '/assets/img/products/swag.jpg',
        'is_preorder' => true,
    ];
}

function remove_preorder_from_cart(string $key): void
{
    unset($_SESSION['preorder_cart'][$key]);
}

function cart_has_preorders(): bool
{
    return !empty($_SESSION['preorder_cart']);
}

function cart_clear_preorders(): void
{
    $_SESSION['preorder_cart'] = [];
}

function add_to_cart(int $productId, int $quantity = 1, ?string $size = null, ?string $color = null): void
{
    $item = [
        'product_id' => $productId,
        'quantity' => $quantity,
        'size' => $size,
        'color' => $color,
    ];

    if (!empty($_SESSION['user_id'])) {
        $existing = db()->prepare('SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ? AND (size = ? OR (size IS NULL AND ? IS NULL)) AND (color = ? OR (color IS NULL AND ? IS NULL))');
        $uid = (int)$_SESSION['user_id'];
        $existing->execute([$uid, $productId, $size, $size, $color, $color]);
        $row = $existing->fetch();
        if ($row) {
            db()->prepare('UPDATE cart SET quantity = quantity + ? WHERE id = ?')->execute([$quantity, $row['id']]);
        } else {
            db()->prepare('INSERT INTO cart (user_id, product_id, quantity, size, color) VALUES (?, ?, ?, ?, ?)')->execute([$uid, $productId, $quantity, $size, $color]);
        }
        return;
    }

    $idx = "{$productId}-{$size}-{$color}";
    if (isset($_SESSION['cart'][$idx])) {
        $_SESSION['cart'][$idx]['quantity'] += $quantity;
    } else {
        $stmt = db()->prepare('SELECT name, price, sale_price, slug, images FROM products WHERE id = ?');
        $stmt->execute([$productId]);
        $p = $stmt->fetch();
        if (!$p) return;
        $_SESSION['cart'][$idx] = [
            'product_id' => $productId,
            'name' => $p['name'],
            'price' => $p['sale_price'] ?: $p['price'],
            'slug' => $p['slug'],
            'quantity' => $quantity,
            'size' => $size,
            'color' => $color,
            'image' => json_decode($p['images'], true)[0] ?? '/assets/img/background.png',
        ];
    }
}

function remove_from_cart(string $key): void
{
    if (str_starts_with($key, 'preorder-')) {
        remove_preorder_from_cart($key);
        return;
    }
    if (!empty($_SESSION['user_id'])) {
        db()->prepare('DELETE FROM cart WHERE id = ? AND user_id = ?')->execute([(int)$key, (int)$_SESSION['user_id']]);
        return;
    }
    unset($_SESSION['cart'][$key]);
}

function update_cart(string $key, int $quantity): void
{
    if (!empty($_SESSION['user_id'])) {
        if ($quantity <= 0) {
            db()->prepare('DELETE FROM cart WHERE id = ? AND user_id = ?')->execute([(int)$key, (int)$_SESSION['user_id']]);
        } else {
            db()->prepare('UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?')->execute([$quantity, (int)$key, (int)$_SESSION['user_id']]);
        }
        return;
    }
    if ($quantity <= 0) {
        unset($_SESSION['cart'][$key]);
    } else {
        $_SESSION['cart'][$key]['quantity'] = $quantity;
    }
}

function apply_coupon(string $code, float $subtotal): array
{
    $stmt = db()->prepare('SELECT * FROM coupons WHERE code = ? AND active = 1 AND (max_uses IS NULL OR used_count < max_uses) AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW())');
    $stmt->execute([strtoupper($code)]);
    $coupon = $stmt->fetch();
    if (!$coupon) {
        return ['success' => false, 'message' => 'Invalid or expired coupon code.'];
    }

    $discount = $coupon['discount_type'] === 'percent'
        ? round($subtotal * ($coupon['discount_value'] / 100), 2)
        : min($coupon['discount_value'], $subtotal);

    return [
        'success' => true,
        'coupon' => $coupon,
        'discount' => $discount,
    ];
}

function validate_uploaded_image(array $file): ?string
{
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return null;
    $allowedExts = ['jpg','jpeg','png','gif','webp'];
    $allowedMimes = ['image/jpeg','image/png','image/gif','image/webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($ext, $allowedExts) || !in_array($mime, $allowedMimes)) return null;
    // Reject files over 5MB
    if ($file['size'] > 5 * 1024 * 1024) return null;
    return $ext;
}

function send_email(string $to, string $subject, string $body): bool
{
    $host = site_setting('email_smtp_host', '');
    if ($host) {
        return send_email_smtp($to, $subject, $body);
    }
    $headers = "From: noreply@suggawayz.com\r\n";
    $headers .= "Reply-To: support@suggawayz.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    return mail($to, $subject, $body, $headers);
}

function send_email_smtp(string $to, string $subject, string $body): bool
{
    $host = site_setting('email_smtp_host', '');
    $port = (int)site_setting('email_smtp_port', '587');
    $user = site_setting('email_smtp_username', '');
    $passEnc = site_setting('email_smtp_password', '');
    $pass = decrypt_value($passEnc) ?: $passEnc;
    $enc = site_setting('email_smtp_encryption', 'tls');
    $from = site_setting('email_from_address', $user ?: 'noreply@suggawayz.com');
    $fromName = site_setting('email_from_name', 'SUGGAWAYZ');

    if (!$host || !$user || !$pass) return false;

    $prefix = ($enc === 'ssl') ? 'ssl://' : '';
    $errno = 0; $errstr = '';
    $fp = @fsockopen($prefix . $host, $port, $errno, $errstr, 15);
    if (!$fp) return false;

    $response = '';
    $smtp_ok = function($fp, $expected = 250) use (&$response) {
        $response = fgets($fp, 512);
        return (int)substr($response, 0, 3) === $expected;
    };

    fread($fp, 512); // server banner
    fwrite($fp, "EHLO suggawayz.com\r\n"); fflush($fp); $smtp_ok($fp);

    if ($enc === 'tls') {
        fwrite($fp, "STARTTLS\r\n"); fflush($fp);
        if (!$smtp_ok($fp, 220)) { fclose($fp); return false; }
        stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        fwrite($fp, "EHLO suggawayz.com\r\n"); fflush($fp); $smtp_ok($fp);
    }

    fwrite($fp, "AUTH LOGIN\r\n"); fflush($fp); $smtp_ok($fp, 334);
    fwrite($fp, base64_encode($user) . "\r\n"); fflush($fp); $smtp_ok($fp, 334);
    fwrite($fp, base64_encode($pass) . "\r\n"); fflush($fp); $smtp_ok($fp, 235);

    fwrite($fp, "MAIL FROM:<{$from}>\r\n"); fflush($fp); $smtp_ok($fp);
    fwrite($fp, "RCPT TO:<{$to}>\r\n"); fflush($fp); $smtp_ok($fp);
    fwrite($fp, "DATA\r\n"); fflush($fp); $smtp_ok($fp, 354);

    $headers = "From: {$fromName} <{$from}>\r\nReply-To: {$from}\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
    fwrite($fp, "Subject: {$subject}\r\n{$headers}\r\n{$body}\r\n.\r\n"); fflush($fp);
    $result = $smtp_ok($fp);

    fwrite($fp, "QUIT\r\n"); fflush($fp);
    fclose($fp);
    return $result;
}

function imap_fetch_mail(string $host, int $port, string $user, string $pass, string $mailbox = 'INBOX', int $limit = 20): array
{
    $errno = 0; $errstr = '';
    $fp = @fsockopen($host, $port, $errno, $errstr, 10);
    if (!$fp) return ['error' => "Connection failed: $errstr"];
    fread($fp, 8192);
    $tag = 1;
    $send = function($c) use ($fp, &$tag) { fwrite($fp, "A$tag $c\r\n"); fflush($fp); $tag++; };
    $recv = function() use ($fp) {
        $lines = [];
        while ($line = fgets($fp, 8192)) { $lines[] = rtrim($line); if (preg_match('/^A\d+ (OK|NO|BAD|BYE)/', $line)) break; }
        return $lines;
    };
    $send("AUTH LOGIN");
    $r = $recv();
    if (preg_match('/^\+/', $r[0] ?? '')) { fwrite($fp, base64_encode($user) . "\r\n"); fflush($fp); $r = $recv(); }
    if (preg_match('/^\+/', $r[0] ?? '')) { fwrite($fp, base64_encode($pass) . "\r\n"); fflush($fp); $r = $recv(); }
    if (!preg_match('/^A\d+ OK/', end($r))) { fclose($fp); return ['error' => 'Login failed']; }
    $send("SELECT \"$mailbox\"");
    $r = $recv();
    $exists = 0;
    foreach ($r as $line) { if (preg_match('/^\* (\d+) EXISTS/', $line, $m)) $exists = (int)$m[1]; }
    if (!$exists) { fclose($fp); return []; }
    $start = max(1, $exists - $limit + 1);
    $send("FETCH $start:$exists (FLAGS BODY[HEADER.FIELDS (FROM SUBJECT DATE)])");
    $r = $recv();
    $messages = []; $current = [];
    foreach ($r as $line) {
        if (preg_match('/^\* (\d+) FETCH/', $line, $m)) { if (!empty($current)) $messages[] = $current; $current = ['uid' => (int)$m[1]]; }
        elseif (preg_match('/^FROM:\s*(.+)/i', $line, $m)) $current['from'] = trim(mb_decode_mimeheader($m[1]));
        elseif (preg_match('/^SUBJECT:\s*(.+)/i', $line, $m)) $current['subject'] = trim(mb_decode_mimeheader($m[1]));
        elseif (preg_match('/^DATE:\s*(.+)/i', $line, $m)) $current['date'] = trim($m[1]);
    }
    if (!empty($current)) $messages[] = $current;
    fclose($fp);
    return $messages;
}

function view(string $view, array $data = [], bool $direct = false): string
{
    $path = dirname(__DIR__) . '/Views/' . str_replace('.', '/', $view) . '.php';
    if (!file_exists($path)) {
        if ($direct) {
            echo "<h1>View not found: {$view}</h1>";
            return '';
        }
        throw new RuntimeException("View not found: {$view} ({$path})");
    }

    extract($data);

    if ($direct) {
        require $path;
        return '';
    }

    ob_start();
    require dirname(__DIR__) . '/Views/layouts/main.php';
    return ob_get_clean();
}

function paginate(string $query, int $perPage = 12): array
{
    $page = max(1, (int)($_GET['page_num'] ?? 1));
    $total = (int)db()->query("SELECT COUNT(*) FROM ({$query}) _sub")->fetchColumn();
    $lastPage = max(1, (int)ceil($total / $perPage));
    $offset = ($page - 1) * $perPage;

    $stmt = db()->prepare("{$query} LIMIT {$perPage} OFFSET {$offset}");
    $stmt->execute();
    $items = $stmt->fetchAll();

    return [
        'items' => $items,
        'current_page' => $page,
        'last_page' => $lastPage,
        'total' => $total,
        'per_page' => $perPage,
    ];
}

function slugify(string $text): string
{
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
    return strtolower(trim($text, '-'));
}

function generate_order_number(): string
{
    return 'SW-' . strtoupper(bin2hex(random_bytes(4))) . '-' . date('Ymd');
}
