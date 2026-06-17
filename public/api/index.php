<?php
// Public API router — accepts API key authentication
// Other sites call: GET https://suggawayz.com/api/products?key=YOUR_KEY

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Authenticate via ?key= or X-API-Key header
$apiKey = $_GET['key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
if (!$apiKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Missing API key. Provide ?key= or X-API-Key header.']);
    exit;
}

$stmt = db()->prepare("SELECT id, name FROM api_nodes WHERE api_key = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$apiKey]);
$node = $stmt->fetch();
if (!$node) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid or inactive API key.']);
    exit;
}

$endpoint = $_GET['endpoint'] ?? '';

$routes = [
    'products' => function () use ($node) {
        $products = db()->query("SELECT id, name, slug, price, sale_price, description, images, is_new, is_featured, created_at FROM products WHERE status='active' ORDER BY name")->fetchAll();
        foreach ($products as &$p) { $p['images'] = json_decode($p['images'] ?? '[]', true); }
        return ['products' => $products];
    },
    'product' => function () {
        $slug = $_GET['slug'] ?? '';
        if (!$slug) return ['error' => 'Missing ?slug='];
        $stmt = db()->prepare("SELECT id, name, slug, price, sale_price, description, images, sizes, colors, created_at FROM products WHERE slug=? AND status='active'");
        $stmt->execute([$slug]);
        $p = $stmt->fetch();
        if (!$p) { http_response_code(404); return ['error' => 'Product not found']; }
        $p['images'] = json_decode($p['images'] ?? '[]', true);
        return ['product' => $p];
    },
    'categories' => function () {
        $cats = db()->query("SELECT id, name, slug, description FROM categories WHERE active=1 ORDER BY sort_order")->fetchAll();
        return ['categories' => $cats];
    },
    'orders' => function () {
        $limit = min(100, (int)($_GET['limit'] ?? 20));
        $orders = db()->query("SELECT o.id, o.order_number, o.status, o.total, o.created_at, u.email as customer_email FROM orders o JOIN users u ON u.id=o.user_id ORDER BY o.created_at DESC LIMIT $limit")->fetchAll();
        return ['orders' => $orders];
    },
    'ping' => function () use ($node) {
        return ['pong' => true, 'time' => date('c'), 'node' => $node['name'] ?? null];
    },
];

$response = isset($routes[$endpoint]) ? $routes[$endpoint]() : ['error' => 'Unknown endpoint. Available: ' . implode(', ', array_keys($routes))];
if (isset($response['error']) && !isset($http_response_code)) http_response_code(404);
echo json_encode($response);
