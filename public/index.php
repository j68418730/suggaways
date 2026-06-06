<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$page = $_GET['page'] ?? 'home';
$action = $_POST['action'] ?? null;
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    switch ($action) {
        case 'login':
            $username = trim((string)$_POST['username']);
            $password = (string)$_POST['password'];
            $remember = !empty($_POST['remember']);
            if (login_user($username, $password)) {
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $stmt = db()->prepare('UPDATE users SET remember_token = ? WHERE id = ?');
                    $stmt->execute([password_hash($token, PASSWORD_ARGON2ID), (int)$_SESSION['user_id']]);
                    setcookie('remember', $token, time() + 86400 * 30, '/', '', false, true);
                }
                session_flash('notice', 'Welcome back to SUGGAWAYZ.');
                redirect('/?page=' . (is_admin(current_user()) ? 'admin' : 'account'));
            }
            session_flash('error', 'Login failed. Check your credentials.');
            redirect('/?page=login');

        case 'register':
            $username = trim((string)$_POST['username']);
            $email = trim((string)$_POST['email']);
            $password = (string)$_POST['password'];
            $confirm = (string)$_POST['password_confirm'];
            $fullName = trim((string)$_POST['full_name']);

            if ($password !== $confirm) {
                session_flash('error', 'Passwords do not match.');
                redirect('/?page=register');
            }
            if (strlen($password) < 8) {
                session_flash('error', 'Password must be at least 8 characters.');
                redirect('/?page=register');
            }

            $check = db()->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
            $check->execute([$username, $email]);
            if ($check->fetch()) {
                session_flash('error', 'Username or email already taken.');
                redirect('/?page=register');
            }

            $hash = password_hash($password, PASSWORD_ARGON2ID);
            $stmt = db()->prepare('INSERT INTO users (role, username, email, password_hash, full_name) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute(['customer', $username, $email, $hash, $fullName]);
            $userId = (int)db()->lastInsertId();

            $verifyToken = bin2hex(random_bytes(32));
            db()->prepare('INSERT INTO email_verifications (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))')
                ->execute([$userId, $verifyToken]);

            audit('registered', 'users', (string)$userId);
            session_flash('notice', 'Account created! Please check your email to verify.');
            redirect('/?page=login');

        case 'logout':
            logout_user();
            session_flash('notice', 'You have been logged out.');
            redirect('/');

        case 'add_to_cart':
            $productId = (int)$_POST['product_id'];
            $quantity = max(1, (int)($_POST['quantity'] ?? 1));
            $size = $_POST['size'] ?? null;
            $color = $_POST['color'] ?? null;
            add_to_cart($productId, $quantity, $size, $color);
            session_flash('notice', 'Added to cart.');
            redirect('/?page=cart');

        case 'add_preorder_to_cart':
            $csId = (int)$_POST['coming_soon_id'];
            $quantity = max(1, (int)($_POST['quantity'] ?? 1));
            add_preorder_to_cart($csId, $quantity);
            session_flash('notice', 'Preorder added to cart.');
            redirect('/?page=cart');

        case 'update_cart':
            foreach (($_POST['quantity'] ?? []) as $key => $qty) {
                update_cart($key, (int)$qty);
            }
            redirect('/?page=cart');

        case 'remove_from_cart':
            remove_from_cart((string)$_POST['key']);
            redirect('/?page=cart');

        case 'apply_coupon':
            $code = trim((string)$_POST['coupon']);
            $_SESSION['coupon'] = $code;
            session_flash('notice', 'Coupon applied.');
            redirect('/?page=cart');

        case 'remove_coupon':
            unset($_SESSION['coupon']);
            redirect('/?page=cart');

        case 'checkout':
            $addressId = (int)($_POST['address_id'] ?? 0);
            $shippingMethod = (string)$_POST['shipping_method'];
            $paymentMethod = (string)$_POST['payment_method'];
            $notes = trim((string)$_POST['notes']);

            // Guest checkout — create user if not logged in
            if (!$user) {
                $guestName = trim((string)($_POST['guest_name'] ?? ''));
                $guestEmail = trim((string)($_POST['guest_email'] ?? ''));
                if (!$guestName || !$guestEmail) {
                    session_flash('error', 'Please provide your name and email.');
                    redirect('/?page=checkout');
                }
                $guestUsername = 'guest_' . bin2hex(random_bytes(6));
                $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
                $stmt->execute([$guestEmail]);
                $existing = $stmt->fetch();
                if ($existing) {
                    $userId = (int)$existing['id'];
                } else {
                    $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_ARGON2ID);
                    $stmt = db()->prepare('INSERT INTO users (role, username, email, password_hash, full_name) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute(['customer', $guestUsername, $guestEmail, $hash, $guestName]);
                    $userId = (int)db()->lastInsertId();
                }
                // Auto-login guest
                $_SESSION['user_id'] = $userId;
                $user = current_user();
            }

            // Create address from inline fields if provided
            if (!$addressId && !empty($_POST['addr_street'])) {
                $stmt = db()->prepare('INSERT INTO addresses (user_id, label, full_name, street_line1, city, state, postal_code, country, is_default_shipping) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)');
                $stmt->execute([
                    (int)$user['id'],
                    $_POST['addr_name'] ?? $user['full_name'],
                    $_POST['addr_name'] ?? $user['full_name'],
                    $_POST['addr_street'],
                    $_POST['addr_city'],
                    $_POST['addr_state'],
                    $_POST['addr_zip'],
                    $_POST['addr_country'] ?? 'United States',
                ]);
                $addressId = (int)db()->lastInsertId();
            }

            $items = cart_items();
            if (empty($items)) {
                session_flash('error', 'Your cart is empty.');
                redirect('/?page=cart');
            }

            $hasPreorders = cart_has_preorders();
            $subtotal = cart_total();
            $couponCode = $_SESSION['coupon'] ?? null;
            $discount = 0.0;
            if ($couponCode) {
                $result = apply_coupon($couponCode, $subtotal);
                if ($result['success']) {
                    $discount = $result['discount'];
                    db()->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE code = ?')->execute([$couponCode]);
                }
            }

            $taxRate = config('app.tax_rate', 8.25);
            $tax = round(($subtotal - $discount) * ($taxRate / 100), 2);

            $shippingStmt = db()->prepare('SELECT * FROM shipping WHERE id = ? AND active = 1');
            $shippingStmt->execute([(int)$shippingMethod]);
            $shippingRow = $shippingStmt->fetch();
            $shippingCost = $shippingRow ? (float)$shippingRow['base_rate'] : 0.0;

            $total = $subtotal - $discount + $tax + $shippingCost;
            $orderNumber = generate_order_number();

            db()->beginTransaction();
            try {
                $stmt = db()->prepare('INSERT INTO orders (user_id, order_number, order_type, status, subtotal, discount, coupon_code, tax, shipping, total, shipping_address_id, shipping_method, notes' . ($hasPreorders ? ', preorder_data, paid_at' : '') . ') VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?' . ($hasPreorders ? ', ?, NOW()' : '') . ')');
                $params = [
                    (int)$user['id'], $orderNumber,
                    $hasPreorders ? 'preorder' : 'standard',
                    $hasPreorders ? 'paid' : 'pending',
                    $subtotal, $discount, $couponCode, $tax, $shippingCost, $total,
                    $addressId ?: null, $shippingRow['service_name'] ?? null, $notes
                ];
                if ($hasPreorders) {
                    $preorderData = [];
                    foreach ($items as $item) {
                        if (!empty($item['is_preorder'])) {
                            $preorderData[] = [
                                'coming_soon_id' => $item['coming_soon_id'] ?? 0,
                                'name' => $item['name'],
                                'price' => $item['price'],
                                'quantity' => $item['quantity'],
                            ];
                        }
                    }
                    $params[] = json_encode($preorderData);
                }
                $stmt->execute($params);
                $orderId = (int)db()->lastInsertId();

                foreach ($items as $item) {
                    if (!empty($item['is_preorder'])) continue;
                    db()->prepare('INSERT INTO order_items (order_id, product_id, product_name, sku, size, color, quantity, unit_price, line_total, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                        ->execute([
                            $orderId, $item['product_id'], $item['name'],
                            '', $item['size'] ?? null, $item['color'] ?? null,
                            $item['quantity'], $item['price'], $item['price'] * $item['quantity'],
                            $item['image'] ?? null
                        ]);
                }

                db()->prepare('INSERT INTO payments (order_id, provider, status, amount, currency) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$orderId, $paymentMethod, $hasPreorders ? 'paid' : 'pending', $total, 'USD']);

                if (!empty($_SESSION['user_id'])) {
                    db()->prepare('DELETE FROM cart WHERE user_id = ?')->execute([(int)$_SESSION['user_id']]);
                }
                $_SESSION['cart'] = [];
                cart_clear_preorders();
                unset($_SESSION['coupon']);

                db()->commit();
                audit('order_placed', 'orders', (string)$orderId, ['order_number' => $orderNumber, 'order_type' => $hasPreorders ? 'preorder' : 'standard']);
                $msg = $hasPreorders ? "Preorder {$orderNumber} placed and paid!" : "Order {$orderNumber} placed successfully!";
                session_flash('notice', $msg);
                redirect("/?page=order-confirmed&order={$orderNumber}");

            } catch (Exception $e) {
                db()->rollBack();
                session_flash('error', 'Checkout failed. Please try again.');
                redirect('/?page=checkout');
            }

        case 'contact':
            db()->prepare('INSERT INTO contact_submissions (name, email, subject, message) VALUES (?, ?, ?, ?)')
                ->execute([$_POST['name'], $_POST['email'], $_POST['subject'] ?? null, $_POST['message']]);
            session_flash('notice', 'Message sent! We will get back to you soon.');
            redirect('/?page=contact');

        case 'subscribe':
            $email = trim((string)$_POST['email']);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                db()->prepare('INSERT IGNORE INTO subscribers (email) VALUES (?)')->execute([$email]);
                session_flash('notice', 'Thanks for subscribing!');
            }
            redirect_back();

        case 'add_review':
            if (!$user) {
                session_flash('error', 'Please log in to leave a review.');
                redirect('/?page=login');
            }
            $productId = (int)$_POST['product_id'];
            $rating = (int)$_POST['rating'];
            $title = trim((string)$_POST['review_title']);
            $body = trim((string)$_POST['review_body']);
            if ($rating >= 1 && $rating <= 5) {
                db()->prepare('INSERT INTO reviews (product_id, user_id, rating, title, body) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$productId, (int)$user['id'], $rating, $title, $body]);
                session_flash('notice', 'Review submitted!');
            }
            redirect_back();

        case 'add_to_wishlist':
            if (!$user) { session_flash('error', 'Please log in.'); redirect('/?page=login'); }
            $pid = (int)$_POST['product_id'];
            db()->prepare('INSERT IGNORE INTO wishlist_items (user_id, product_id) VALUES (?, ?)')
                ->execute([(int)$user['id'], $pid]);
            redirect_back();

        case 'remove_wishlist':
            if (!$user) { redirect('/?page=login'); }
            db()->prepare('DELETE FROM wishlist_items WHERE user_id = ? AND product_id = ?')
                ->execute([(int)$user['id'], (int)$_POST['product_id']]);
            redirect_back();

        case 'add_address':
            if (!$user) { redirect('/?page=login'); }
            db()->prepare('INSERT INTO addresses (user_id, label, full_name, phone, street_line1, street_line2, city, state, postal_code, country) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([
                    (int)$user['id'],
                    $_POST['label'] ?? 'Home',
                    $_POST['full_name'], $_POST['phone'],
                    $_POST['street_line1'], $_POST['street_line2'] ?? null,
                    $_POST['city'], $_POST['state'], $_POST['postal_code'],
                    $_POST['country'] ?? 'United States'
                ]);
            session_flash('notice', 'Address added.');
            redirect('/?page=account&tab=addresses');

        case 'update_profile':
            if (!$user) { redirect('/?page=login'); }
            $avatarUrl = $user['avatar'];
            if (!empty($_FILES['avatar']['tmp_name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','gif','webp'];
                if (in_array($ext, $allowed)) {
                    $filename = 'avatar-' . $user['id'] . '-' . time() . '.' . $ext;
                    $dest = dirname(__DIR__) . '/public/assets/img/avatars/' . $filename;
                    if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0755, true);
                    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) {
                        $avatarUrl = '/assets/img/avatars/' . $filename;
                    }
                }
            }
            db()->prepare('UPDATE users SET full_name = ?, phone = ?, bio = ?, avatar = ? WHERE id = ?')
                ->execute([$_POST['full_name'], $_POST['phone'], $_POST['bio'], $avatarUrl, (int)$user['id']]);
            audit('profile_updated', 'users', (string)$user['id']);
            session_flash('notice', 'Profile updated.');
            redirect('/?page=account&tab=profile');

        case 'change_password':
            if (!$user) { redirect('/?page=login'); }
            $current = (string)$_POST['current_password'];
            $newPass = (string)$_POST['new_password'];
            $confirm = (string)$_POST['confirm_password'];
            if (!password_verify($current, $user['password_hash'])) {
                session_flash('error', 'Current password is incorrect.');
                redirect('/?page=account&tab=security');
            }
            if ($newPass !== $confirm || strlen($newPass) < 8) {
                session_flash('error', 'Passwords do not match or too short.');
                redirect('/?page=account&tab=security');
            }
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($newPass, PASSWORD_ARGON2ID), (int)$user['id']]);
            audit('password_changed', 'users', (string)$user['id']);
            session_flash('notice', 'Password updated.');
            redirect('/?page=account&tab=security');

        case 'admin_add_product':
            if (!$user || !is_admin($user)) { abort(403); }
            $name = trim((string)$_POST['name']);
            $sku = strtoupper(trim((string)$_POST['sku']));
            $slug = slugify($name);

            // Determine product image
            $imageUrl = '/assets/img/products/swag.jpg';
            if (!empty($_FILES['product_image']['tmp_name']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                    $filename = 'product-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $dest = dirname(__DIR__) . '/public/assets/img/products/' . $filename;
                    if (move_uploaded_file($_FILES['product_image']['tmp_name'], $dest)) {
                        $imageUrl = '/assets/img/products/' . $filename;
                    }
                }
            } elseif (!empty($_POST['existing_image'])) {
                $imageUrl = $_POST['existing_image'];
            }

            $stmt = db()->prepare('INSERT INTO products (name, sku, slug, description, short_description, seo_description, price, sale_price, sizes, colors, images, is_featured, status, category_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $name, $sku, $slug,
                $_POST['description'], $_POST['short_description'] ?? null, $_POST['seo_description'] ?? null,
                (float)$_POST['price'], $_POST['sale_price'] ? (float)$_POST['sale_price'] : null,
                json_encode(explode(',', str_replace(' ', '', $_POST['sizes'] ?? 'M,L,XL,XXL,XXXL'))),
                json_encode(explode(',', $_POST['colors'] ?? 'Black')),
                json_encode([$imageUrl]),
                !empty($_POST['is_featured']) ? 1 : 0,
                $_POST['status'] ?? 'active',
                $_POST['category_id'] ? (int)$_POST['category_id'] : null
            ]);
            $productId = (int)db()->lastInsertId();
            if ($imageUrl !== '/assets/img/products/swag.jpg') {
                db()->prepare('INSERT INTO product_images (product_id, url) VALUES (?, ?)')->execute([$productId, $imageUrl]);
            }
            db()->prepare('INSERT INTO inventory (product_id, stock_quantity, low_stock_threshold) VALUES (?, ?, ?)')
                ->execute([$productId, (int)$_POST['stock_quantity'], (int)$_POST['low_stock']]);
            audit('product_created', 'products', (string)$productId);
            session_flash('notice', "Product {$name} created.");
            redirect('/?page=admin&tab=products');

        case 'admin_edit_product':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('UPDATE products SET name=?, price=?, sale_price=?, status=? WHERE id=?')
                ->execute([$_POST['name'], (float)$_POST['price'], $_POST['sale_price'] ? (float)$_POST['sale_price'] : null, $_POST['status'], (int)$_POST['id']]);
            audit('product_updated', 'products', (string)$_POST['id']);
            session_flash('notice', 'Product updated.');
            redirect('/?page=admin&tab=products');

        case 'admin_delete_product':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('DELETE FROM products WHERE id = ?')->execute([(int)$_POST['id']]);
            audit('product_deleted', 'products', (string)$_POST['id']);
            session_flash('notice', 'Product deleted.');
            redirect('/?page=admin&tab=products');

        case 'admin_bulk_delete_products':
            if (!$user || !is_admin($user)) { abort(403); }
            $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                db()->prepare("DELETE FROM products WHERE id IN ($placeholders)")->execute($ids);
                audit('products_bulk_deleted', 'products', implode(',', $ids));
                session_flash('notice', count($ids) . ' products deleted.');
            }
            redirect('/?page=admin&tab=products');

        case 'admin_add_coming_soon':
            if (!$user || !is_admin($user)) { abort(403); }
            $name = trim($_POST['name']);
            db()->prepare('INSERT INTO coming_soon (name, description, price, image, category_id, release_date) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$name, $_POST['description'] ?? '', (float)$_POST['price'], $_POST['image'] ?? '', $_POST['category_id'] ? (int)$_POST['category_id'] : null, $_POST['release_date']]);
            session_flash('notice', "{$name} added to Coming Soon.");
            redirect('/?page=admin&tab=comingsoon');

        case 'admin_edit_coming_soon':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('UPDATE coming_soon SET name=?, price=?, release_date=? WHERE id=?')
                ->execute([$_POST['name'], (float)$_POST['price'], $_POST['release_date'], (int)$_POST['id']]);
            session_flash('notice', 'Coming soon item updated.');
            redirect('/?page=admin&tab=comingsoon');

        case 'admin_delete_coming_soon':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('DELETE FROM coming_soon WHERE id = ?')->execute([(int)$_POST['id']]);
            session_flash('notice', 'Coming soon item deleted.');
            redirect('/?page=admin&tab=comingsoon');

        case 'admin_update_order':
            if (!$user || !is_admin($user)) { abort(403); }
            $orderId = (int)$_POST['order_id'];
            $status = (string)$_POST['status'];
            $tracking = trim((string)$_POST['tracking_number']);
            $carrier = trim((string)$_POST['carrier']);
            $updates = ['status = ?'];
            $params = [$status];
            if ($tracking) { $updates[] = 'tracking_number = ?'; $params[] = $tracking; }
            if ($carrier) { $updates[] = 'carrier = ?'; $params[] = $carrier; }
            if ($status === 'shipped') { $updates[] = 'shipped_at = NOW()'; }
            if ($status === 'delivered') { $updates[] = 'delivered_at = NOW()'; }
            if (in_array($status, ['paid', 'processing', 'shipped', 'delivered'])) {
                $updates[] = 'paid_at = COALESCE(paid_at, NOW())';
            }
            $params[] = $orderId;
            db()->prepare('UPDATE orders SET ' . implode(', ', $updates) . ' WHERE id = ?')->execute($params);
            audit('order_updated', 'orders', (string)$orderId, ['status' => $status]);

            // Send shipped notification email
            if ($status === 'shipped') {
                $ord = db()->prepare('SELECT o.*, u.email, u.full_name FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?');
                $ord->execute([$orderId]);
                $ordData = $ord->fetch();
                if ($ordData && $ordData['email']) {
                    $subject = "Your order #{$ordData['order_number']} has shipped!";
                    $body = "<h2>Good news, " . e($ordData['full_name'] ?: 'Valued Customer') . "!</h2>";
                    $body .= "<p>Your order <strong>#{$ordData['order_number']}</strong> has been shipped.</p>";
                    if ($tracking) {
                        $body .= "<p><strong>Tracking Number:</strong> " . e($tracking) . "</p>";
                    }
                    if ($carrier) {
                        $body .= "<p><strong>Carrier:</strong> " . e($carrier) . "</p>";
                    }
                    $body .= "<p><strong>Order Total:</strong> \$" . number_format((float)$ordData['total'], 2) . "</p>";
                    $body .= "<p>Thank you for your purchase!</p>";
                    $body .= "<p style='font-size:12px;color:#888'>Suggawayz</p>";
                    send_email($ordData['email'], $subject, $body);
                }
            }

            session_flash('notice', "Order #{$orderId} updated.");
            redirect('/?page=admin&tab=orders');

        case 'admin_delete_order':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('DELETE FROM orders WHERE id = ?')->execute([(int)$_POST['id']]);
            audit('order_deleted', 'orders', (string)$_POST['id']);
            session_flash('notice', 'Order deleted.');
            redirect('/?page=admin&tab=orders');

        // === CATEGORIES ===
        case 'admin_add_category':
            if (!$user || !is_admin($user)) { abort(403); }
            $name = trim((string)$_POST['name']);
            $slug = slugify($name);
            db()->prepare('INSERT INTO categories (name, slug, description, parent_id, sort_order) VALUES (?, ?, ?, ?, ?)')
                ->execute([$name, $slug, $_POST['description'], $_POST['parent_id'] ? (int)$_POST['parent_id'] : null, (int)$_POST['sort_order']]);
            audit('category_created', 'categories', $slug);
            session_flash('notice', "Category {$name} created.");
            redirect('/?page=admin&tab=categories');

        case 'admin_edit_category':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('UPDATE categories SET name=?, slug=?, description=?, parent_id=?, sort_order=?, active=? WHERE id=?')
                ->execute([$_POST['name'], slugify($_POST['name']), $_POST['description'], $_POST['parent_id'] ? (int)$_POST['parent_id'] : null, (int)$_POST['sort_order'], !empty($_POST['active']) ? 1 : 0, (int)$_POST['id']]);
            session_flash('notice', 'Category updated.');
            redirect('/?page=admin&tab=categories');

        case 'admin_delete_category':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('DELETE FROM categories WHERE id = ?')->execute([(int)$_POST['id']]);
            session_flash('notice', 'Category deleted.');
            redirect('/?page=admin&tab=categories');

        // === CUSTOMERS ===
        case 'admin_add_customer':
            if (!$user || !is_admin($user)) { abort(403); }
            $customPw = trim((string)($_POST['password'] ?? ''));
            $pw = $customPw ?: bin2hex(random_bytes(8));
            if (!empty($customPw) && strlen($customPw) < 6) {
                session_flash('error', 'Password must be at least 6 characters.');
                redirect('/?page=admin&tab=customers');
            }
            $hash = password_hash($pw, PASSWORD_ARGON2ID);
            db()->prepare('INSERT INTO users (role, username, email, password_hash, full_name, phone, is_employee, email_verified_at) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())')
                ->execute(['customer', $_POST['username'], $_POST['email'], $hash, $_POST['full_name'], $_POST['phone'] ?? null]);
            $msg = $customPw ? 'Customer created with your chosen password.' : 'Customer created. Temp password: ' . $pw;
            session_flash('notice', $msg);
            redirect('/?page=admin&tab=customers');

        case 'admin_edit_customer':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('UPDATE users SET full_name=?, email=?, phone=?, role=?, is_deleted=? WHERE id=? AND is_employee=0')
                ->execute([$_POST['full_name'], $_POST['email'], $_POST['phone'], $_POST['role'], !empty($_POST['is_deleted']) ? 1 : 0, (int)$_POST['id']]);
            session_flash('notice', 'Customer updated.');
            redirect('/?page=admin&tab=customers');

        // === EMPLOYEES ===
        case 'admin_add_employee':
            if (!$user || !is_admin($user)) { abort(403); }
            $customPw = trim((string)($_POST['password'] ?? ''));
            $pw = $customPw ?: bin2hex(random_bytes(8));
            if (!empty($customPw) && strlen($customPw) < 6) {
                session_flash('error', 'Password must be at least 6 characters.');
                redirect('/?page=admin&tab=employees');
            }
            $hash = password_hash($pw, PASSWORD_ARGON2ID);
            $role = $_POST['role'] ?? 'support';
            db()->prepare('INSERT INTO users (role, username, email, password_hash, full_name, phone, is_employee, email_verified_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())')
                ->execute([$role, $_POST['username'], $_POST['email'], $hash, $_POST['full_name'], $_POST['phone'] ?? null]);
            $uid = (int)db()->lastInsertId();
            if (in_array($role, ['webmaster', 'super_admin'])) {
                db()->prepare('INSERT IGNORE INTO admins (user_id, permission_level) VALUES (?, ?)')->execute([$uid, $role]);
            }
            audit('employee_created', 'users', (string)$uid);
            $msg = $customPw ? "Employee created with your chosen password." : "Employee created. Temp password: {$pw}. Tell them to change it.";
            session_flash('notice', $msg);
            redirect('/?page=admin&tab=employees');

        case 'admin_edit_employee':
            if (!$user || !is_admin($user)) { abort(403); }
            $role = $_POST['role'];
            db()->prepare('UPDATE users SET full_name=?, email=?, phone=?, role=?, is_deleted=? WHERE id=? AND is_employee=1')
                ->execute([$_POST['full_name'], $_POST['email'], $_POST['phone'], $role, !empty($_POST['is_deleted']) ? 1 : 0, (int)$_POST['id']]);
            if (in_array($role, ['webmaster', 'super_admin'])) {
                db()->prepare('INSERT IGNORE INTO admins (user_id, permission_level) VALUES (?, ?)')->execute([(int)$_POST['id'], $role]);
            } else {
                db()->prepare('DELETE FROM admins WHERE user_id = ?')->execute([(int)$_POST['id']]);
            }
            audit('employee_updated', 'users', (string)$_POST['id']);
            session_flash('notice', 'Employee updated.');
            redirect('/?page=admin&tab=employees');

        case 'admin_employee_reset_password':
            if (!$user || !is_admin($user)) { abort(403); }
            $customPw = trim((string)($_POST['new_password'] ?? ''));
            $pw = $customPw ?: bin2hex(random_bytes(8));
            if (!empty($customPw) && strlen($customPw) < 6) {
                session_flash('error', 'Password must be at least 6 characters.');
                redirect('/?page=admin&tab=employees');
            }
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ? AND is_employee = 1')
                ->execute([password_hash($pw, PASSWORD_ARGON2ID), (int)$_POST['id']]);
            $msg = $customPw ? "Password updated to your chosen password." : "Password reset to: {$pw}";
            session_flash('notice', $msg);
            redirect('/?page=admin&tab=employees');

        // === PRODUCT IMAGE UPLOAD ===
        case 'admin_upload_product_image':
            if (!$user || !is_admin($user)) { abort(403); }
            $productId = (int)$_POST['product_id'];
            if (!empty($_FILES['image']['tmp_name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','gif','webp'];
                if (in_array($ext, $allowed)) {
                    $filename = 'product-' . $productId . '-' . time() . '.' . $ext;
                    $dest = dirname(__DIR__) . '/public/assets/img/products/' . $filename;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                        $url = '/assets/img/products/' . $filename;
                        $stmt = db()->prepare('SELECT images FROM products WHERE id = ?');
                        $stmt->execute([$productId]);
                        $existing = $stmt->fetchColumn() ?: '[]';
                        $imgs = json_decode($existing, true) ?: [];
                        array_unshift($imgs, $url);
                        db()->prepare('UPDATE products SET images = ? WHERE id = ?')->execute([json_encode($imgs), $productId]);
                        db()->prepare('INSERT INTO product_images (product_id, url) VALUES (?, ?)')->execute([$productId, $url]);
                        session_flash('notice', 'Image uploaded.');
                    } else {
                        session_flash('error', 'Failed to save file. Check directory permissions.');
                    }
                } else {
                    session_flash('error', 'Invalid file type. Allowed: jpg, jpeg, png, gif, webp.');
                }
            } else {
                $errMsg = 'Upload failed.';
                $errCode = $_FILES['image']['error'] ?? 'no file';
                if ($errCode === UPLOAD_ERR_INI_SIZE || $errCode === UPLOAD_ERR_FORM_SIZE) {
                    $errMsg = 'File too large. Max upload size: ' . ini_get('upload_max_filesize');
                } elseif ($errCode !== UPLOAD_ERR_OK && $errCode !== 'no file') {
                    $errMsg = "Upload error code: $errCode";
                }
                session_flash('error', $errMsg);
            }
            redirect('/?page=admin&tab=products');

        // === INVENTORY ===
        case 'admin_add_inventory':
            if (!$user || !is_admin($user)) { abort(403); }
            $pid = (int)$_POST['product_id'];
            $qty = (int)$_POST['stock_quantity'];
            $locId = $_POST['location_id'] ? (int)$_POST['location_id'] : null;
            $warehouse = trim((string)$_POST['warehouse']);
            $threshold = (int)$_POST['low_stock_threshold'];
            $reorder = (int)$_POST['reorder_level'];
            $stmt = db()->prepare('SELECT id FROM inventory WHERE product_id = ? AND warehouse = ?');
            $stmt->execute([$pid, $warehouse]);
            $existing = $stmt->fetch();
            if ($existing) {
                db()->prepare('UPDATE inventory SET stock_quantity = stock_quantity + ?, low_stock_threshold = ?, reorder_level = ? WHERE id = ?')
                    ->execute([$qty, $threshold, $reorder, $existing['id']]);
            } else {
                db()->prepare('INSERT INTO inventory (product_id, warehouse, location_id, stock_quantity, low_stock_threshold, reorder_level) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$pid, $warehouse, $locId, $qty, $threshold, $reorder]);
            }
            db()->prepare('INSERT INTO inventory_movements (product_id, warehouse, type, quantity, note) VALUES (?, ?, "in", ?, "Manual add")')
                ->execute([$pid, $warehouse, $qty]);
            session_flash('notice', 'Inventory updated.');
            redirect('/?page=admin&tab=inventory');

        case 'admin_edit_inventory':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('UPDATE inventory SET stock_quantity=?, low_stock_threshold=?, reorder_level=?, warehouse=?, location_id=? WHERE id=?')
                ->execute([(int)$_POST['stock_quantity'], (int)$_POST['low_stock_threshold'], (int)$_POST['reorder_level'], $_POST['warehouse'], $_POST['location_id'] ? (int)$_POST['location_id'] : null, (int)$_POST['id']]);
            session_flash('notice', 'Inventory record updated.');
            redirect('/?page=admin&tab=inventory');

        case 'admin_delete_inventory':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('DELETE FROM inventory WHERE id = ?')->execute([(int)$_POST['id']]);
            session_flash('notice', 'Inventory record deleted.');
            redirect('/?page=admin&tab=inventory');

        case 'admin_add_location':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('INSERT INTO inventory_locations (name, address, contact, phone) VALUES (?, ?, ?, ?)')
                ->execute([$_POST['name'], $_POST['address'], $_POST['contact'], $_POST['phone']]);
            session_flash('notice', 'Location added.');
            redirect('/?page=admin&tab=inventory');

        case 'admin_delete_location':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('DELETE FROM inventory_locations WHERE id = ?')->execute([(int)$_POST['id']]);
            session_flash('notice', 'Location deleted.');
            redirect('/?page=admin&tab=inventory');

        // === REORDER ===
        case 'admin_add_reorder':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('INSERT INTO reorder_requests (product_id, location_id, quantity_requested, supplier, notes, requested_by) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([(int)$_POST['product_id'], $_POST['location_id'] ? (int)$_POST['location_id'] : null, (int)$_POST['quantity_requested'], $_POST['supplier'], $_POST['notes'], (int)$user['id']]);
            session_flash('notice', 'Reorder request created.');
            redirect('/?page=admin&tab=reorder');

        case 'admin_update_reorder':
            if (!$user || !is_admin($user)) { abort(403); }
            $status = (string)$_POST['status'];
            $updates = ['status = ?', 'notes = ?'];
            $params = [$status, $_POST['notes']];
            if ($status === 'received') {
                $updates[] = 'quantity_received = ?';
                $params[] = (int)$_POST['quantity_received'];
                $updates[] = 'received_at = NOW()';
            }
            if ($status === 'ordered') { $updates[] = 'ordered_at = NOW()'; }
            $params[] = (int)$_POST['id'];
            db()->prepare('UPDATE reorder_requests SET ' . implode(', ', $updates) . ' WHERE id = ?')->execute($params);
            if ($status === 'received') {
                $rr = db()->query('SELECT product_id, quantity_received FROM reorder_requests WHERE id = ' . (int)$_POST['id'])->fetch();
                if ($rr) {
                    db()->prepare('UPDATE inventory SET stock_quantity = stock_quantity + ? WHERE product_id = ?')
                        ->execute([(int)$rr['quantity_received'], (int)$rr['product_id']]);
                }
            }
            session_flash('notice', 'Reorder updated.');
            redirect('/?page=admin&tab=reorder');

        case 'admin_delete_reorder':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('DELETE FROM reorder_requests WHERE id = ?')->execute([(int)$_POST['id']]);
            session_flash('notice', 'Reorder deleted.');
            redirect('/?page=admin&tab=reorder');

        // === PAYMENT SETTINGS ===
        case 'admin_update_payment':
            if (!$user || !is_admin($user)) { abort(403); }
            $id = (int)$_POST['id'];
            db()->prepare('UPDATE payment_settings SET enabled=?, sandbox_mode=?, label=?, public_key=?, secret_key=?, extra_settings=? WHERE id=?')
                ->execute([
                    !empty($_POST['enabled']) ? 1 : 0,
                    !empty($_POST['sandbox_mode']) ? 1 : 0,
                    $_POST['label'],
                    $_POST['public_key'],
                    $_POST['secret_key'],
                    json_encode($_POST['extra'] ?? []),
                    $id
                ]);
            session_flash('notice', 'Payment settings updated.');
            redirect('/?page=admin&tab=payments');

        // === COUPONS ===
        case 'admin_add_coupon':
            if (!$user || !is_admin($user)) { abort(403); }
            $code = strtoupper(trim((string)$_POST['code']));
            if (empty($code)) { $code = strtoupper(bin2hex(random_bytes(4))); }
            db()->prepare('INSERT INTO coupons (code, discount_type, discount_value, min_order_amount, max_uses, starts_at, ends_at, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$code, $_POST['discount_type'], (float)$_POST['discount_value'], $_POST['min_order_amount'] ? (float)$_POST['min_order_amount'] : null, $_POST['max_uses'] ? (int)$_POST['max_uses'] : null, $_POST['starts_at'] ?: null, $_POST['ends_at'] ?: null, !empty($_POST['active']) ? 1 : 0]);
            session_flash('notice', "Coupon {$code} created.");
            redirect('/?page=admin&tab=coupons');

        case 'admin_edit_coupon':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('UPDATE coupons SET code=?, discount_type=?, discount_value=?, min_order_amount=?, max_uses=?, starts_at=?, ends_at=?, active=? WHERE id=?')
                ->execute([strtoupper(trim((string)$_POST['code'])), $_POST['discount_type'], (float)$_POST['discount_value'], $_POST['min_order_amount'] ? (float)$_POST['min_order_amount'] : null, $_POST['max_uses'] ? (int)$_POST['max_uses'] : null, $_POST['starts_at'] ?: null, $_POST['ends_at'] ?: null, !empty($_POST['active']) ? 1 : 0, (int)$_POST['id']]);
            session_flash('notice', 'Coupon updated.');
            redirect('/?page=admin&tab=coupons');

        case 'admin_delete_coupon':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('DELETE FROM coupons WHERE id = ?')->execute([(int)$_POST['id']]);
            session_flash('notice', 'Coupon deleted.');
            redirect('/?page=admin&tab=coupons');

        // === Pages ===
        case 'admin_add_page':
            if (!$user || !is_admin($user)) { abort(403); }
            $slug = $_POST['slug'] ?: preg_replace('/[^a-z0-9-]/', '', str_replace(' ', '-', strtolower(trim($_POST['title']))));
            db()->prepare('INSERT INTO pages (title, slug, content, meta_title, meta_description, published) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$_POST['title'], $slug, $_POST['content'], $_POST['meta_title'] ?? null, $_POST['meta_description'] ?? null, (int)(bool)($_POST['published'] ?? 0)]);
            session_flash('notice', 'Page created.');
            redirect('/?page=admin&tab=pages');

        case 'admin_edit_page':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('UPDATE pages SET title=?, slug=?, content=?, published=? WHERE id=?')
                ->execute([$_POST['title'], $_POST['slug'], $_POST['content'], (int)(bool)($_POST['published'] ?? 0), (int)$_POST['id']]);
            session_flash('notice', 'Page updated.');
            redirect('/?page=admin&tab=pages');

        case 'admin_delete_page':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('DELETE FROM pages WHERE id = ?')->execute([(int)$_POST['id']]);
            session_flash('notice', 'Page deleted.');
            redirect('/?page=admin&tab=pages');

        // === Blog ===
        case 'admin_add_blog':
            if (!$user || !is_admin($user)) { abort(403); }
            $slug = preg_replace('/[^a-z0-9-]/', '', str_replace(' ', '-', strtolower(trim($_POST['title']))));
            db()->prepare('INSERT INTO blog_posts (title, slug, author, excerpt, content, published, published_at) VALUES (?, ?, ?, ?, ?, ?, NOW())')
                ->execute([$_POST['title'], $slug, $_POST['author'] ?? 'SUGGAWAYZ Team', $_POST['excerpt'] ?? null, $_POST['content'], (int)(bool)($_POST['published'] ?? 0)]);
            session_flash('notice', 'Blog post created.');
            redirect('/?page=admin&tab=blog');

        case 'admin_edit_blog':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('UPDATE blog_posts SET title=?, author=?, content=?, published=? WHERE id=?')
                ->execute([$_POST['title'], $_POST['author'], $_POST['content'], (int)(bool)($_POST['published'] ?? 0), (int)$_POST['id']]);
            session_flash('notice', 'Blog post updated.');
            redirect('/?page=admin&tab=blog');

        case 'admin_delete_blog':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('DELETE FROM blog_posts WHERE id = ?')->execute([(int)$_POST['id']]);
            session_flash('notice', 'Blog post deleted.');
            redirect('/?page=admin&tab=blog');

        // === Lookbook Events ===
        case 'admin_add_lookbook':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('INSERT INTO lookbook_events (title, description, event_date, location_name, address, city, state, postal_code, lat, lng, image, status, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([
                    $_POST['title'], $_POST['description'] ?? null, $_POST['event_date'] ?: null,
                    $_POST['location_name'] ?? null, $_POST['address'] ?? null, $_POST['city'] ?? null,
                    $_POST['state'] ?? null, $_POST['postal_code'] ?? null,
                    $_POST['lat'] !== '' ? (float)$_POST['lat'] : null,
                    $_POST['lng'] !== '' ? (float)$_POST['lng'] : null,
                    $_POST['image'] ?? null, $_POST['status'] ?? 'published', (int)($_POST['sort_order'] ?? 0)
                ]);
            session_flash('notice', 'Lookbook event created.');
            redirect('/?page=admin&tab=events');

        case 'admin_edit_lookbook':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('UPDATE lookbook_events SET title=?, description=?, event_date=?, location_name=?, address=?, city=?, state=?, postal_code=?, lat=?, lng=?, image=?, status=?, sort_order=? WHERE id=?')
                ->execute([
                    $_POST['title'], $_POST['description'] ?? null, $_POST['event_date'] ?: null,
                    $_POST['location_name'] ?? null, $_POST['address'] ?? null, $_POST['city'] ?? null,
                    $_POST['state'] ?? null, $_POST['postal_code'] ?? null,
                    $_POST['lat'] !== '' ? (float)$_POST['lat'] : null,
                    $_POST['lng'] !== '' ? (float)$_POST['lng'] : null,
                    $_POST['image'] ?? null, $_POST['status'] ?? 'published', (int)($_POST['sort_order'] ?? 0),
                    (int)$_POST['id']
                ]);
            session_flash('notice', 'Lookbook event updated.');
            redirect('/?page=admin&tab=events');

        case 'admin_delete_lookbook':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('DELETE FROM lookbook_events WHERE id = ?')->execute([(int)$_POST['id']]);
            session_flash('notice', 'Lookbook event deleted.');
            redirect('/?page=admin&tab=events');

        // === Contact ===
        case 'admin_mark_contact_read':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('UPDATE contact_submissions SET is_read=1 WHERE id=?')->execute([(int)$_POST['id']]);
            redirect('/?page=admin&tab=contact');

        case 'admin_delete_contact':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('DELETE FROM contact_submissions WHERE id = ?')->execute([(int)$_POST['id']]);
            session_flash('notice', 'Submission deleted.');
            redirect('/?page=admin&tab=contact');

        // === Shipping ===
        case 'admin_add_shipping':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('INSERT INTO shipping (region, carrier, service_name, base_rate, free_threshold, estimated_days_min, estimated_days_max, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$_POST['region'], $_POST['carrier'], $_POST['service_name'], (float)$_POST['base_rate'], $_POST['free_threshold'] !== '' ? (float)$_POST['free_threshold'] : null, $_POST['estimated_days_min'] !== '' ? (int)$_POST['estimated_days_min'] : null, $_POST['estimated_days_max'] !== '' ? (int)$_POST['estimated_days_max'] : null, (int)(bool)($_POST['active'] ?? 0)]);
            session_flash('notice', 'Shipping method added.');
            redirect('/?page=admin&tab=shipping');

        case 'admin_edit_shipping':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('UPDATE shipping SET region=?, carrier=?, service_name=?, base_rate=?, free_threshold=?, estimated_days_min=?, estimated_days_max=?, active=? WHERE id=?')
                ->execute([$_POST['region'], $_POST['carrier'], $_POST['service_name'], (float)$_POST['base_rate'], $_POST['free_threshold'] !== '' ? (float)$_POST['free_threshold'] : null, $_POST['estimated_days_min'] !== '' ? (int)$_POST['estimated_days_min'] : null, $_POST['estimated_days_max'] !== '' ? (int)$_POST['estimated_days_max'] : null, (int)(bool)($_POST['active'] ?? 0), (int)$_POST['id']]);
            session_flash('notice', 'Shipping method updated.');
            redirect('/?page=admin&tab=shipping');

        case 'admin_delete_shipping':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('DELETE FROM shipping WHERE id = ?')->execute([(int)$_POST['id']]);
            session_flash('notice', 'Shipping method deleted.');
            redirect('/?page=admin&tab=shipping');

        // === Prepay ===
        case 'admin_toggle_prepay':
            if (!$user || !is_admin($user)) { abort(403); }
            $enabled = !empty($_POST['enabled']) ? 1 : 0;
            db()->prepare("UPDATE payment_settings SET enabled = ? WHERE provider = 'prepay'")->execute([$enabled]);
            session_flash('notice', $enabled ? 'Prepay enabled.' : 'Prepay disabled.');
            redirect('/?page=admin&tab=settings');

        case 'admin_add_prepay':
            if (!$user || !is_admin($user)) { abort(403); }
            $amount = (float)$_POST['amount'];
            if ($amount <= 0) { session_flash('error', 'Amount must be positive.'); redirect('/?page=admin&tab=settings'); }
            db()->prepare('INSERT INTO prepay (amount, notes) VALUES (?, ?)')->execute([$amount, $_POST['notes'] ?? null]);
            session_flash('notice', '$' . number_format($amount, 2) . ' prepay credit added.');
            redirect('/?page=admin&tab=settings');

        case 'admin_save_prepay_config':
            if (!$user || !is_admin($user)) { abort(403); }
            $cfg = db()->query("SELECT * FROM payment_settings WHERE provider = 'prepay'")->fetch();
            $extra = $cfg ? json_decode($cfg['extra_settings'] ?? '{}', true) : [];
            $extra['google_maps_api_key'] = $_POST['google_maps_api_key'] ?? '';
            $json = json_encode($extra);
            if ($cfg) {
                db()->prepare("UPDATE payment_settings SET extra_settings=? WHERE provider='prepay'")->execute([$json]);
            } else {
                db()->prepare("INSERT INTO payment_settings (provider, enabled, label, extra_settings) VALUES ('prepay', 0, 'Prepay (Testing)', ?)")->execute([$json]);
            }
            session_flash('notice', 'Config saved.');
            redirect('/?page=admin&tab=settings');

        case 'admin_update_site_settings':
            if (!$user || !is_admin($user)) { abort(403); }
            $fields = ['footer_tagline','hero_title','hero_subtitle','hero_subscribe','site_icon_text',
                       'email_smtp_host','email_smtp_port','email_smtp_username',
                       'email_smtp_encryption','email_from_address','email_from_name',
                       'printer_type','printer_ip','printer_port','pos_tax_rate'];
            foreach ($fields as $f) {
                if (isset($_POST[$f])) set_site_setting($f, $_POST[$f]);
            }
            if (!empty($_POST['email_smtp_password'])) {
                set_site_setting('email_smtp_password', $_POST['email_smtp_password']);
            }
            if (isset($_POST['social_links'])) {
                set_site_setting('social_links', $_POST['social_links']);
            }
            session_flash('notice', 'Site settings saved.');
            redirect('/?page=admin&tab=settings');

        case 'admin_send_test_email':
            if (!$user || !is_admin($user)) { abort(403); }
            $to = $_POST['test_email'] ?? $user['email'];
            $subject = 'Test Email from SUGGAWAYZ';
            $message = 'This is a test email from your SUGGAWAYZ store settings.';
            $from = site_setting('email_from_address', 'noreply@suggawayz.com');
            $fromName = site_setting('email_from_name', 'SUGGAWAYZ');
            $headers = 'From: ' . $fromName . ' <' . $from . '>' . "\r\n" . 'Content-Type: text/plain; charset=utf-8' . "\r\n";
            $sent = mail($to, $subject, $message, $headers);
            session_flash($sent ? 'notice' : 'error', $sent ? 'Test email sent to ' . e($to) : 'Failed to send email. Check SMTP settings.');
            redirect('/?page=admin&tab=settings');

        case 'admin_send_bulk_email':
            if (!$user || !is_admin($user)) { abort(403); }
            $subject = trim((string)$_POST['subject']);
            $body = trim((string)$_POST['body']);
            if (empty($subject) || empty($body)) { session_flash('error', 'Subject and body required.'); redirect('/?page=admin&tab=settings'); }
            $subs = db()->query('SELECT email FROM subscribers WHERE is_active = 1')->fetchAll(PDO::FETCH_COLUMN);
            if (empty($subs)) { session_flash('error', 'No active subscribers.'); redirect('/?page=admin&tab=settings'); }
            $from = site_setting('email_from_address', 'noreply@suggawayz.com');
            $fromName = site_setting('email_from_name', 'SUGGAWAYZ');
            $headers = 'From: ' . $fromName . ' <' . $from . '>' . "\r\n" . 'Content-Type: text/html; charset=utf-8' . "\r\n";
            $sent = 0; $failed = 0;
            foreach ($subs as $email) {
                if (mail($email, $subject, nl2br(e($body)), $headers)) $sent++; else $failed++;
            }
            session_flash('notice', "Email sent to {$sent} subscribers" . ($failed ? ", {$failed} failed." : '.'));
            redirect('/?page=admin&tab=settings');

        // === POS ===
        case 'clock_in':
            if (!$user) { abort(403); }
            $result = clock_in((int)$user['id'], (float)($_POST['opening_balance'] ?? 0));
            if ($result['success']) {
                audit('clocked_in', 'clock_events', (string)$user['id']);
                session_flash('notice', 'Clocked in. POS drawer is open.');
            } else {
                session_flash('error', $result['message']);
            }
            redirect('/?page=admin&tab=pos');

        case 'clock_out':
            if (!$user) { abort(403); }
            $result = clock_out((int)$user['id']);
            if ($result['success']) {
                audit('clocked_out', 'clock_events', (string)$user['id'], ['closing_balance' => $result['closing_balance']]);
                $parts = ["Clocked out. Opening: \$" . number_format($result['opening_balance'], 2) . " — Closing: \$" . number_format($result['closing_balance'], 2)];
                foreach ($result['summary'] as $s) {
                    $parts[] = e(ucfirst(str_replace('_', ' ', $s['type']))) . ': ' . (int)$s['count'] . 'x $' . number_format((float)$s['total'], 2);
                }
                session_flash('notice', implode(' | ', $parts));
            } else {
                session_flash('error', $result['message']);
            }
            redirect('/?page=admin&tab=pos');

        case 'pos_cash_in':
            if (!$user) { abort(403); }
            $clocked = is_clocked_in((int)$user['id']);
            if (!$clocked) { session_flash('error', 'You must be clocked in.'); redirect('/?page=admin&tab=pos'); }
            $session = db()->query('SELECT id FROM pos_sessions WHERE employee_id = ' . (int)$user['id'] . ' AND status = "open" ORDER BY id DESC')->fetch();
            if (!$session) { session_flash('error', 'No open POS session.'); redirect('/?page=admin&tab=pos'); }
            db()->prepare('INSERT INTO pos_transactions (pos_session_id, type, amount, payment_method, reference, description) VALUES (?, "cash_in", ?, ?, ?, ?)')
                ->execute([(int)$session['id'], (float)$_POST['amount'], $_POST['payment_method'] ?? 'cash', $_POST['reference'] ?? null, $_POST['description'] ?? 'Cash in']);
            session_flash('notice', 'Cash in recorded.');
            redirect('/?page=admin&tab=pos');

        case 'pos_cash_out':
            if (!$user) { abort(403); }
            $clocked = is_clocked_in((int)$user['id']);
            if (!$clocked) { session_flash('error', 'You must be clocked in.'); redirect('/?page=admin&tab=pos'); }
            $session = db()->query('SELECT id FROM pos_sessions WHERE employee_id = ' . (int)$user['id'] . ' AND status = "open" ORDER BY id DESC')->fetch();
            if (!$session) { session_flash('error', 'No open POS session.'); redirect('/?page=admin&tab=pos'); }
            db()->prepare('INSERT INTO pos_transactions (pos_session_id, type, amount, payment_method, reference, description) VALUES (?, "cash_out", ?, ?, ?, ?)')
                ->execute([(int)$session['id'], (float)$_POST['amount'], 'cash', $_POST['reference'] ?? null, $_POST['description'] ?? 'Cash out']);
            session_flash('notice', 'Cash out recorded.');
            redirect('/?page=admin&tab=pos');

        case 'pos_complete_sale':
            if (!$user) { abort(403); }
            $clocked = is_clocked_in((int)$user['id']);
            if (!$clocked) { session_flash('error', 'You must be clocked in.'); redirect('/?page=admin&tab=pos'); }
            $session = db()->query('SELECT id FROM pos_sessions WHERE employee_id = ' . (int)$user['id'] . ' AND status = "open" ORDER BY id DESC')->fetch();
            if (!$session) { session_flash('error', 'No open POS session.'); redirect('/?page=admin&tab=pos'); }
            $items = json_decode($_POST['items'] ?? '[]', true);
            $total = (float)($_POST['total'] ?? 0);
            $paymentType = $_POST['payment_type'] ?? 'cash';
            $itemNames = [];
            foreach ($items as $item) {
                $itemNames[] = $item['name'] . ' x' . $item['qty'];
            }
            db()->prepare('INSERT INTO pos_transactions (pos_session_id, type, amount, payment_method, reference, description) VALUES (?, "sale", ?, ?, ?, ?)')
                ->execute([(int)$session['id'], $total, $paymentType, implode(', ', $itemNames), json_encode($items)]);
            $txId = (int)db()->lastInsertId();
            session_flash('notice', 'Sale completed: $' . number_format($total, 2));
            redirect('/?page=receipt&transaction_id=' . $txId);

        // === Bug Reports ===
        case 'bug_report':
            $name = trim((string)$_POST['reporter_name']);
            $email = trim((string)$_POST['reporter_email']);
            $subject = trim((string)$_POST['subject']);
            $desc = trim((string)$_POST['description']);
            $pageUrl = trim((string)$_POST['page_url']);
            $screenshotPath = null;

            if (!empty($_FILES['screenshot']['tmp_name']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                    $filename = 'bug-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $dest = dirname(__DIR__) . '/public/assets/img/bugs/' . $filename;
                    $bugDir = dirname(__DIR__) . '/public/assets/img/bugs';
                    if (!is_dir($bugDir)) { mkdir($bugDir, 0777, true); }
                    if (move_uploaded_file($_FILES['screenshot']['tmp_name'], $dest)) {
                        $screenshotPath = '/assets/img/bugs/' . $filename;
                    }
                }
            }

            db()->prepare('INSERT INTO bug_reports (reporter_name, reporter_email, subject, description, page_url, screenshot) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$name ?: null, $email ?: null, $subject, $desc, $pageUrl ?: null, $screenshotPath]);
            session_flash('notice', 'Bug report submitted. Thank you for helping us improve!');
            redirect('/?page=bug-report');

        case 'admin_update_bug_status':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('UPDATE bug_reports SET status = ? WHERE id = ?')
                ->execute([$_POST['status'], (int)$_POST['id']]);
            session_flash('notice', 'Bug status updated.');
            redirect('/?page=admin&tab=bugreports&bug_status=' . urlencode($_POST['status']));

        case 'admin_delete_bug_report':
            if (!$user || !is_admin($user)) { abort(403); }
            db()->prepare('DELETE FROM bug_reports WHERE id = ?')->execute([(int)$_POST['id']]);
            session_flash('notice', 'Bug report deleted.');
            redirect('/?page=admin&tab=bugreports');

        default:
            redirect_back();
    }
}

$user = current_user();

switch ($page) {
    case 'home':
        // Auto-release: move expired coming_soon items to products
        $expired = db()->query('SELECT * FROM coming_soon WHERE release_date <= NOW()')->fetchAll();
        foreach ($expired as $item) {
            $slug = slugify($item['name']) . '-' . time();
            $sku = 'CS-' . strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', $item['name']), 0, 8)) . time();
            $images = $item['image'] ? json_encode([$item['image']]) : json_encode(['/assets/img/products/swag.jpg']);
            db()->prepare('INSERT INTO products (name, sku, slug, description, price, images, status, category_id) VALUES (?, ?, ?, ?, ?, ?, "active", ?)')
                ->execute([$item['name'], $sku, $slug, $item['description'] ?: '', (float)$item['price'], $images, $item['category_id'] ? (int)$item['category_id'] : null]);
            $pid = (int)db()->lastInsertId();
            db()->prepare('INSERT INTO inventory (product_id, stock_quantity) VALUES (?, 25)')->execute([$pid]);
            db()->prepare('DELETE FROM coming_soon WHERE id = ?')->execute([(int)$item['id']]);
        }
        $featured = db()->query('SELECT p.*, i.stock_quantity FROM products p LEFT JOIN inventory i ON i.product_id = p.id WHERE p.status = "active" AND p.is_featured = 1 ORDER BY p.created_at DESC LIMIT 6')->fetchAll();
        $newDrops = db()->query('SELECT p.*, i.stock_quantity FROM products p LEFT JOIN inventory i ON i.product_id = p.id WHERE p.status = "active" AND p.is_new = 1 ORDER BY p.created_at DESC LIMIT 4')->fetchAll();
        $collections = db()->query('SELECT * FROM categories WHERE active = 1 ORDER BY sort_order')->fetchAll();
        $comingSoon = db()->query('SELECT * FROM coming_soon ORDER BY release_date ASC LIMIT 6')->fetchAll();
        $hero_class = 'hero-home';
        $seo_settings = db()->prepare('SELECT * FROM seo_settings WHERE page_key = ?')->execute(['home']) ? db()->query('SELECT * FROM seo_settings WHERE page_key = "home"')->fetch() : null;
        $seo_title = $seo_settings['meta_title'] ?? null;
        $seo_description = $seo_settings['meta_description'] ?? null;
        $hero_content = '<p class="eyebrow">Futuristic Streetwear</p><h1>Be Different.<br>Be You.</h1><p>Premium cyberwear apparel for creators, trendsetters, and the next generation.</p><div class="actions"><a class="button primary" href="/?page=shop">Shop Now</a><a class="button" href="/?page=collections">Explore Collections</a></div>';
        $content = render_home($featured, $newDrops, $collections, $comingSoon);
        break;

    case 'shop':
        $categorySlug = $_GET['category'] ?? null;
        $sort = $_GET['sort'] ?? 'newest';
        $search = trim($_GET['search'] ?? '');
        $sql = 'SELECT p.*, i.stock_quantity FROM products p LEFT JOIN inventory i ON i.product_id = p.id WHERE p.status = "active"';
        $params = [];
        if ($categorySlug) {
            $sql .= ' AND p.category_id = (SELECT id FROM categories WHERE slug = ?)';
            $params[] = $categorySlug;
        }
        if ($search) {
            $sql .= ' AND (p.name LIKE ? OR p.description LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        $sql .= match($sort) {
            'price-low' => ' ORDER BY p.sale_price IS NOT NULL, COALESCE(p.sale_price, p.price) ASC',
            'price-high' => ' ORDER BY p.sale_price IS NOT NULL, COALESCE(p.sale_price, p.price) DESC',
            'name' => ' ORDER BY p.name ASC',
            default => ' ORDER BY p.created_at DESC',
        };
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $allProducts = $stmt->fetchAll();
        $categories = db()->query('SELECT * FROM categories WHERE active = 1 ORDER BY sort_order')->fetchAll();
        $seo_settings = db()->query('SELECT * FROM seo_settings WHERE page_key = "shop"')->fetch();
        $seo_title = $seo_settings['meta_title'] ?? null;
        $seo_description = $seo_settings['meta_description'] ?? null;
        $hero_class = 'hero-shop';
        $hero_content = '<p class="eyebrow">The Collection</p><h1>Shop All</h1><p>Explore the latest drops and classic essentials.</p>';
        $content = render_shop($allProducts, $categories, $categorySlug, $sort, $search);
        break;

    case 'product':
        $slug = $_GET['slug'] ?? '';
        $stmt = db()->prepare('SELECT p.*, i.stock_quantity, c.name as category_name, c.slug as category_slug FROM products p LEFT JOIN inventory i ON i.product_id = p.id LEFT JOIN categories c ON c.id = p.category_id WHERE p.slug = ? AND p.status = "active"');
        $stmt->execute([$slug]);
        $product = $stmt->fetch();
        if (!$product) abort(404, 'Product not found');
        $images = json_decode($product['images'] ?? '[]', true);
        $sizes = json_decode($product['sizes'] ?? '[]', true);
        $colors = json_decode($product['colors'] ?? '[]', true);
        $reviews = db()->prepare('SELECT r.*, u.full_name, u.avatar FROM reviews r JOIN users u ON u.id = r.user_id WHERE r.product_id = ? AND r.is_approved = 1 ORDER BY r.created_at DESC')->execute([(int)$product['id']]) ? db()->query('SELECT r.*, u.full_name, u.avatar FROM reviews r JOIN users u ON u.id = r.user_id WHERE r.product_id = 0')->fetchAll() : [];
        $related = db()->prepare('SELECT p.*, i.stock_quantity FROM products p LEFT JOIN inventory i ON i.product_id = p.id WHERE p.category_id = ? AND p.id != ? AND p.status = "active" LIMIT 4');
        $related->execute([(int)$product['category_id'], (int)$product['id']]);
        $relatedProducts = $related->fetchAll();
        $seo_title = $product['meta_title'] ?: $product['name'] . ' — SUGGAWAYZ';
        $seo_description = $product['meta_description'] ?: $product['seo_description'];
        $hero_class = 'hero-product';
        $content = render_product_detail($product, $images, $sizes, $colors, $reviews, $relatedProducts, $user);
        break;

    case 'collections':
        $collections = db()->query('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.status = "active") as product_count FROM categories c WHERE c.active = 1 ORDER BY c.sort_order')->fetchAll();
        $seo_settings = db()->query('SELECT * FROM seo_settings WHERE page_key = "collections"')->fetch();
        $seo_title = $seo_settings['meta_title'] ?? null;
        $seo_description = $seo_settings['meta_description'] ?? null;
        $hero_content = '<p class="eyebrow">Explore</p><h1>Collections</h1><p>Browse by category and find your fit.</p>';
        $content = render_collections($collections);
        break;

    case 'new-drops':
        $newProducts = db()->query('SELECT p.*, i.stock_quantity FROM products p LEFT JOIN inventory i ON i.product_id = p.id WHERE p.status = "active" AND p.is_new = 1 ORDER BY p.created_at DESC')->fetchAll();
        $hero_content = '<p class="eyebrow">Fresh Arrivals</p><h1>New Drops</h1><p>Limited edition pieces and the latest releases.</p>';
        $content = render_new_drops($newProducts);
        break;

    case 'lookbook':
        if (!$user) { $seo_title = 'Events'; $hero_class = 'hero-sub'; }
        $content = render_events();
        break;

    case 'about':
        $page = db()->query("SELECT * FROM pages WHERE slug = 'about' AND published = 1")->fetch();
        $seo_title = 'About — SUGGAWAYZ';
        $hero_content = '<p class="eyebrow">Our Story</p><h1>About</h1>';
        $content = render_about($page);
        break;

    case 'contact':
        $hero_content = '<p class="eyebrow">Get in Touch</p><h1>Contact</h1><p>Questions, collaborations, or just saying hi.</p>';
        $content = render_contact();
        break;

    case 'faq':
        $faqs = db()->query('SELECT * FROM faq_items WHERE published = 1 ORDER BY sort_order')->fetchAll();
        $categories = db()->query('SELECT DISTINCT category FROM faq_items WHERE published = 1 AND category IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);
        $hero_content = '<p class="eyebrow">Help Center</p><h1>FAQ</h1>';
        $content = render_faq($faqs, $categories);
        break;

    case 'blog':
        $posts = db()->query('SELECT * FROM blog_posts WHERE published = 1 ORDER BY published_at DESC')->fetchAll();
        $hero_content = '<p class="eyebrow">Read</p><h1>Blog</h1>';
        $content = render_blog($posts);
        break;

    case 'blog-post':
        $slug = $_GET['slug'] ?? '';
        $stmt = db()->prepare('SELECT * FROM blog_posts WHERE slug = ? AND published = 1');
        $stmt->execute([$slug]);
        $post = $stmt->fetch();
        if (!$post) abort(404);
        $seo_title = $post['title'] . ' — SUGGAWAYZ Blog';
        $content = render_blog_post($post);
        break;

    case 'terms':
        $page = db()->query("SELECT * FROM pages WHERE slug = 'terms' AND published = 1")->fetch();
        $hero_content = '<p class="eyebrow">Legal</p><h1>Terms & Conditions</h1>';
        $content = render_static_page($page);
        break;

case 'privacy':
    $page = db()->query("SELECT * FROM pages WHERE slug = 'privacy' AND published = 1")->fetch();
    $hero_content = '<p class="eyebrow">Legal</p><h1>Privacy Policy</h1>';
    $content = render_static_page($page);
    break;

case 'returns':
    $page = db()->query("SELECT * FROM pages WHERE slug = 'returns' AND published = 1")->fetch();
    $hero_content = '<p class="eyebrow">Customer Care</p><h1>Returns & Exchanges</h1>';
    $content = render_static_page($page);
    break;

case 'size-guide':
    $page = db()->query("SELECT * FROM pages WHERE slug = 'size-guide' AND published = 1")->fetch();
    $hero_content = '<p class="eyebrow">Customer Care</p><h1>Size Guide</h1>';
    $content = render_static_page($page);
    break;

case 'shipping':
    $methods = db()->query('SELECT * FROM shipping WHERE active = 1 ORDER BY region, carrier')->fetchAll();
    $hero_content = '<p class="eyebrow">Customer Care</p><h1>Shipping Information</h1>';
    ob_start(); ?>
    <div class="panel">
      <?php
      $regions = [];
      foreach ($methods as $m) {
          $regions[$m['region']][] = $m;
      }
      foreach ($regions as $region => $items): ?>
        <h3><?= e($region) ?></h3>
        <table class="table">
          <tr><th>Carrier</th><th>Service</th><th>Rate</th><th>Free Over</th><th>Est. Delivery</th></tr>
          <?php foreach ($items as $m): ?>
            <tr>
              <td><?= e($m['carrier']) ?></td>
              <td><?= e($m['service_name']) ?></td>
              <td>$<?= e(number_format((float)$m['base_rate'], 2)) ?></td>
              <td><?= $m['free_threshold'] ? '$' . e(number_format((float)$m['free_threshold'], 2)) : '—' ?></td>
              <td><?= $m['estimated_days_min'] && $m['estimated_days_max'] ? e($m['estimated_days_min'] . '-' . $m['estimated_days_max'] . ' days') : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endforeach; ?>
    </div>
    <?php $content = ob_get_clean();
    break;

    case 'login':
        if ($user) redirect('/?page=account');
        $hero_content = '<p class="eyebrow">Secure Access</p><h1>Login</h1>';
        $content = render_login();
        break;

    case 'register':
        if ($user) redirect('/?page=account');
        $hero_content = '<p class="eyebrow">Join</p><h1>Register</h1>';
        $content = render_register();
        break;

    case 'forgot-password':
        $hero_content = '<p class="eyebrow">Recovery</p><h1>Reset Password</h1>';
        $content = render_forgot_password();
        break;

    case 'cart':
        $items = cart_items();
        $subtotal = cart_total();
        $couponCode = $_SESSION['coupon'] ?? null;
        $discount = 0.0;
        if ($couponCode) {
            $result = apply_coupon($couponCode, $subtotal);
            if ($result['success']) $discount = $result['discount'];
            else { unset($_SESSION['coupon']); $couponCode = null; }
        }
        $shippingMethods = db()->query('SELECT * FROM shipping WHERE region = "United States" AND active = 1')->fetchAll();
        $hero_content = '<p class="eyebrow">Your Cart</p><h1>Shopping Cart</h1>';
        $content = render_cart($items, $subtotal, $discount, $couponCode, $shippingMethods);
        break;

    case 'checkout':
        if (!$user) { session_flash('error', 'Please log in to checkout.'); redirect('/?page=login'); }
        $items = cart_items();
        if (empty($items)) { session_flash('error', 'Your cart is empty.'); redirect('/?page=cart'); }
        $addresses = db()->prepare('SELECT * FROM addresses WHERE user_id = ?')->execute([(int)$user['id']]) ? db()->query('SELECT * FROM addresses WHERE user_id = ' . (int)$user['id'])->fetchAll() : [];
        $subtotal = cart_total();
        $couponCode = $_SESSION['coupon'] ?? null;
        $discount = 0.0;
        if ($couponCode) {
            $result = apply_coupon($couponCode, $subtotal);
            if ($result['success']) $discount = $result['discount'];
        }
        $taxRate = config('app.tax_rate', 8.25);
        $tax = round(($subtotal - $discount) * ($taxRate / 100), 2);
        $shippingMethods = db()->query('SELECT * FROM shipping WHERE region = "United States" AND active = 1')->fetchAll();
        $hero_content = '<p class="eyebrow">Checkout</p><h1>Complete Your Order</h1>';
        $content = render_checkout($items, $addresses, $subtotal, $discount, $tax, $shippingMethods, $user);
        break;

    case 'order-confirmed':
        $orderNumber = $_GET['order'] ?? '';
        $stmt = db()->prepare('SELECT o.*, (SELECT provider FROM payments WHERE order_id = o.id LIMIT 1) as payment_method FROM orders o WHERE o.order_number = ?');
        $stmt->execute([$orderNumber]);
        $order = $stmt->fetch();
        if (!$order) abort(404);
        $orderItems = db()->prepare('SELECT * FROM order_items WHERE order_id = ?')->execute([(int)$order['id']]) ? db()->query('SELECT * FROM order_items WHERE order_id = ' . (int)$order['id'])->fetchAll() : [];
        $hero_content = '<p class="eyebrow">Success</p><h1>Order Confirmed</h1>';
        $content = render_order_confirmed($order, $orderItems);
        break;

    case 'account':
        if (!$user) { session_flash('error', 'Please log in.'); redirect('/?page=login'); }
        $tab = $_GET['tab'] ?? 'dashboard';
        $orders = db()->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC')->execute([(int)$user['id']]) ? db()->query('SELECT * FROM orders WHERE user_id = ' . (int)$user['id'] . ' ORDER BY created_at DESC')->fetchAll() : [];
        $addresses = db()->prepare('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default_shipping DESC')->execute([(int)$user['id']]) ? db()->query('SELECT * FROM addresses WHERE user_id = ' . (int)$user['id'] . ' ORDER BY is_default_shipping DESC')->fetchAll() : [];
        $wishlist = db()->prepare('SELECT w.*, p.name, p.price, p.sale_price, p.slug, p.images FROM wishlist_items w JOIN products p ON p.id = w.product_id WHERE w.user_id = ?')->execute([(int)$user['id']]) ? db()->query('SELECT w.*, p.name, p.price, p.sale_price, p.slug, p.images FROM wishlist_items w JOIN products p ON p.id = w.product_id WHERE w.user_id = ' . (int)$user['id'])->fetchAll() : [];
        $devices = db()->prepare('SELECT * FROM device_tracking WHERE user_id = ? ORDER BY last_seen_at DESC LIMIT 10')->execute([(int)$user['id']]) ? db()->query('SELECT * FROM device_tracking WHERE user_id = ' . (int)$user['id'] . ' ORDER BY last_seen_at DESC LIMIT 10')->fetchAll() : [];
        $notifications = db()->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20')->execute([(int)$user['id']]) ? db()->query('SELECT * FROM notifications WHERE user_id = ' . (int)$user['id'] . ' ORDER BY created_at DESC LIMIT 20')->fetchAll() : [];
        $recentOrders = array_slice($orders, 0, 5);
        $content = render_account_dashboard($user, $tab, $recentOrders, $orders, $addresses, $wishlist, $devices, $notifications);
        break;

    case 'admin':
        if (!$user || !is_admin($user)) { session_flash('error', 'Access denied.'); redirect('/?page=login'); }
        $tab = $_GET['tab'] ?? 'dashboard';
        $stats = db()->query('SELECT (SELECT COUNT(*) FROM products) as products, (SELECT COUNT(*) FROM users WHERE role = "customer" AND is_deleted=0) as customers, (SELECT COUNT(*) FROM orders) as orders, (SELECT COUNT(*) FROM orders WHERE status = "pending") as pending_orders, (SELECT COUNT(*) FROM inventory WHERE stock_quantity <= low_stock_threshold) as low_stock, (SELECT COUNT(*) FROM payments WHERE status = "failed") as failed_payments, (SELECT COALESCE(SUM(total), 0) FROM orders WHERE status NOT IN ("cancelled", "refunded")) as revenue')->fetch();
        $allProducts = db()->query('SELECT p.*, (SELECT stock_quantity FROM inventory WHERE product_id = p.id LIMIT 1) as stock_quantity, (SELECT low_stock_threshold FROM inventory WHERE product_id = p.id LIMIT 1) as low_stock_threshold FROM products p ORDER BY p.created_at DESC')->fetchAll();
        $orderSearch = trim($_GET['order_search'] ?? '');
        $orderBy = $_GET['order_by'] ?? 'created_at';
        $orderDir = $_GET['order_dir'] ?? 'DESC';
        $orderSql = 'SELECT o.*, u.full_name, u.email as customer_email FROM orders o LEFT JOIN users u ON u.id = o.user_id';
        $orderParams = [];
        if ($orderSearch) {
            $orderSql .= ' WHERE (o.order_number LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR u.username LIKE ? OR o.id = ? OR o.id IN (SELECT order_id FROM payments WHERE provider_reference LIKE ?))';
            $s = "%{$orderSearch}%";
            $orderParams = [$s, $s, $s, $s, is_numeric($orderSearch) ? (int)$orderSearch : 0, $s];
        }
        $allowedOrderBy = ['created_at', 'total', 'status', 'order_number'];
        if (!in_array($orderBy, $allowedOrderBy)) $orderBy = 'created_at';
        if (!in_array(strtoupper($orderDir), ['ASC', 'DESC'])) $orderDir = 'DESC';
        $orderSql .= " ORDER BY o.{$orderBy} {$orderDir} LIMIT 100";
        $allOrders = db()->prepare($orderSql)->execute($orderParams) ? db()->query($orderSql)->fetchAll() : [];
        $allCustomers = db()->query('SELECT u.*, (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as total_orders FROM users u WHERE u.is_employee=0 AND u.is_deleted=0 ORDER BY u.created_at DESC LIMIT 100')->fetchAll();
        $allEmployees = db()->query('SELECT u.*, a.permission_level FROM users u LEFT JOIN admins a ON a.user_id = u.id WHERE u.is_employee=1 ORDER BY u.created_at DESC')->fetchAll();
        $categories = db()->query('SELECT * FROM categories ORDER BY sort_order')->fetchAll();
        $inventory = db()->query('SELECT i.*, p.name as product_name, p.sku as product_sku, l.name as location_name FROM inventory i JOIN products p ON p.id = i.product_id LEFT JOIN inventory_locations l ON l.id = i.location_id ORDER BY i.stock_quantity ASC')->fetchAll();
        $locations = db()->query('SELECT * FROM inventory_locations WHERE active = 1')->fetchAll();
        $reorderItems = db()->query('SELECT r.*, p.name as product_name, p.sku as product_sku, l.name as location_name, u.full_name as requester_name FROM reorder_requests r JOIN products p ON p.id = r.product_id LEFT JOIN inventory_locations l ON l.id = r.location_id LEFT JOIN users u ON u.id = r.requested_by ORDER BY r.created_at DESC')->fetchAll();
        $lowStockProducts = db()->query('SELECT p.id, p.name, p.sku, i.stock_quantity, i.reorder_level, i.low_stock_threshold FROM products p JOIN inventory i ON i.product_id = p.id WHERE i.stock_quantity <= i.reorder_level ORDER BY i.stock_quantity ASC')->fetchAll();
        $paymentSettings = db()->query('SELECT * FROM payment_settings ORDER BY provider')->fetchAll();
        $coupons = db()->query('SELECT * FROM coupons ORDER BY created_at DESC')->fetchAll();
        $auditDateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
        $auditDateTo = $_GET['date_to'] ?? date('Y-m-d');
        $auditStmt = db()->prepare('SELECT a.*, u.username FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE DATE(a.created_at) BETWEEN ? AND ? ORDER BY a.created_at DESC LIMIT 200');
        $auditStmt->execute([$auditDateFrom, $auditDateTo]);
        $auditLogs = $auditStmt->fetchAll();
        $signInLogs = db()->query('SELECT * FROM sign_in_log ORDER BY created_at DESC LIMIT 200')->fetchAll();
        $posSessions = db()->query('SELECT ps.*, u.full_name as employee_name FROM pos_sessions ps JOIN users u ON u.id = ps.employee_id ORDER BY ps.opened_at DESC LIMIT 50')->fetchAll();
        $openPosSession = db()->query('SELECT * FROM pos_sessions WHERE employee_id = ' . (int)$user['id'] . ' AND status = "open" ORDER BY id DESC')->fetch() ?: null;
        $posTransactions = [];
        if ($openPosSession) {
            $posTransactions = db()->query('SELECT * FROM pos_transactions WHERE pos_session_id = ' . (int)$openPosSession['id'] . ' ORDER BY created_at ASC')->fetchAll();
        }
        $content = render_admin_dashboard($user, $tab, $stats, $allProducts, $allOrders, $allCustomers, $categories, $allEmployees, $inventory, $locations, $reorderItems, $lowStockProducts, $paymentSettings, $coupons, $auditLogs, $signInLogs, $posSessions, $openPosSession, $posTransactions, $orderSearch);
        break;

    case 'bug-report':
        $seo_title = 'Report a Bug';
        $hero_class = 'hero-sub';
        $content = render_bug_report_form();
        break;

    case 'receipt':
        if (!$user) { abort(403); }
        $tid = (int)($_GET['transaction_id'] ?? 0);
        $stmt = db()->prepare('SELECT t.*, s.employee_id FROM pos_transactions t JOIN pos_sessions s ON s.id = t.pos_session_id WHERE t.id = ?');
        $stmt->execute([$tid]);
        $transaction = $stmt->fetch();
        if (!$transaction) { abort(404); }
        if (!is_admin($user) && (int)$transaction['employee_id'] !== (int)$user['id']) { abort(403); }

        $order = [];
        $orderItems = [];
        if ($transaction['order_id']) {
            $o = db()->prepare('SELECT * FROM orders WHERE id = ?');
            $o->execute([(int)$transaction['order_id']]);
            $order = $o->fetch() ?: [];
            if ($order) {
                $oi = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
                $oi->execute([(int)$order['id']]);
                $orderItems = $oi->fetchAll();
            }
        }

        $receiptHtml = render_receipt($transaction, $order, $orderItems);
        echo $receiptHtml;
        exit;

    case 'pos-end-of-day':
        if (!$user) { abort(403); }
        $sid = (int)($_GET['session_id'] ?? 0);
        $session = db()->prepare('SELECT * FROM pos_sessions WHERE id = ?');
        $session->execute([$sid]);
        $s = $session->fetch();
        if (!$s) { abort(404); }
        $employee = db()->prepare('SELECT * FROM users WHERE id = ?');
        $employee->execute([(int)$s['employee_id']]);
        $emp = $employee->fetch();
        if (!$emp) { abort(404); }
        $txns = db()->prepare('SELECT * FROM pos_transactions WHERE pos_session_id = ? ORDER BY created_at ASC');
        $txns->execute([$sid]);
        $transactions = $txns->fetchAll();
        echo render_pos_end_of_day($s, $transactions, $emp);
        exit;

    case 'logout':
        logout_user();
        session_flash('notice', 'You have been logged out.');
        redirect('/');

    default:
        abort(404);
}

require dirname(__DIR__) . '/app/Views/layouts/main.php';
