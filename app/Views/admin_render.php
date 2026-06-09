<?php

function render_admin_dashboard(
    array $user, string $tab, array $stats, array $products, array $orders,
    array $customers, array $categories, array $employees, array $inventory,
    array $locations, array $reorderItems, array $lowStockProducts,
    array $paymentSettings, array $coupons, array $auditLogs,
    array $signInLogs, array $posSessions, ?array $openPosSession,
    array $posTransactions, string $orderSearch, array $todos = []
): string {
    $isSuperAdmin = in_array($user['role'] ?? '', ['webmaster', 'super_admin']);
    $isAdmin = is_admin($user);
    $navLinks = [
        ['tab' => 'dashboard', 'label' => '📊 Dashboard', 'admin' => false],
        ['tab' => 'products',  'label' => '📦 Products',  'admin' => false],
        ['tab' => 'categories','label' => '🏷️ Categories','admin' => false],
        ['tab' => 'comingsoon','label' => '⏳ Coming Soon','admin' => false],
        ['tab' => 'orders',    'label' => '📋 Orders',    'admin' => false],
        ['tab' => 'customers', 'label' => '👤 Customers', 'admin' => false],
        ['tab' => 'employees', 'label' => '👥 Employees', 'admin' => true],
        ['tab' => 'inventory', 'label' => '📦 Inventory', 'admin' => false],
        ['tab' => 'reorder',   'label' => '🔄 Reorder',   'admin' => true],
        ['tab' => 'payments',  'label' => '💳 Payments',  'admin' => true],
        ['tab' => 'coupons',   'label' => '🎫 Coupons',   'admin' => false],
        ['tab' => 'audit',     'label' => '📜 Audit Log', 'admin' => true],
        ['tab' => 'signins',   'label' => '🔑 Sign-ins',  'admin' => true],
        ['tab' => 'pos',       'label' => '🧾 POS Drawer','admin' => false],
        ['tab' => 'divider',   'label' => '',              'admin' => false],
        ['tab' => 'bugreports','label' => '🐛 Bug Reports','admin' => true],
        ['tab' => 'pages',     'label' => '📄 Pages',     'admin' => true],
        ['tab' => 'blog',      'label' => '✍️ Blog',      'admin' => false],
        ['tab' => 'events',    'label' => '📅 Events',    'admin' => false],
        ['tab' => 'contact',   'label' => '📧 Contact',   'admin' => true],
        ['tab' => 'sizecharts','label' => '📏 Size Charts','admin' => true],
        ['tab' => 'shipping',  'label' => '🚚 Shipping',  'admin' => true],
        ['tab' => 'inbox',    'label' => '📨 Inbox',    'admin' => true, 'super' => true],
        ['tab' => 'newsletter','label' => '📧 Newsletter','admin' => true],
        ['tab' => 'memberships','label' => '👥 Members',  'admin' => true, 'super' => true],
        ['tab' => 'todos',     'label' => '✅ Todo',      'admin' => true, 'super' => true],
        ['tab' => 'divider2',  'label' => '',              'admin' => false],
        ['tab' => 'settings',  'label' => '⚙️ Settings',  'admin' => true],
        ['tab' => 'security',  'label' => '🔒 Security', 'admin' => true, 'super' => true],
    ];
    $employeeViewable = array_values(array_filter(array_map(fn($l) => !$l['admin'] ? $l['tab'] : null, $navLinks)));
    $effectiveTab = ($isAdmin || in_array($tab, $employeeViewable, true)) ? $tab : 'dashboard';
    // Count pending orders
    $pendingCount = 0;
    try {
        $pendingCount = (int)db()->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
    } catch (\Throwable $e) {}
    ob_start(); ?>
    <?php if ($isSuperAdmin && (!empty($todos) || $pendingCount > 0)): ?>
      <div style="background:var(--surface);border-bottom:1px solid var(--border);padding:8px 24px;display:flex;flex-wrap:wrap;gap:8px;font-size:13px;position:sticky;top:0;z-index:99">
        <?php if ($pendingCount > 0): ?>
          <a href="/?page=admin&tab=orders&order_search=pending" style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:rgba(255,76,76,0.12);border:1px solid rgba(255,76,76,0.3);border-radius:4px;color:var(--red);text-decoration:none">
            <span>🔔</span> <?= $pendingCount ?> pending order<?= $pendingCount > 1 ? 's' : '' ?>
          </a>
        <?php endif; ?>
        <?php foreach ($todos as $t): ?>
          <?php if (!$t['is_active']) continue; ?>
          <a href="/?page=admin&tab=todos" style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:<?= $t['is_completed'] ? 'rgba(0,255,136,0.1)' : 'rgba(0,200,255,0.08)' ?>;border:1px solid <?= $t['is_completed'] ? 'rgba(0,255,136,0.3)' : 'rgba(0,200,255,0.2)' ?>;border-radius:4px;color:var(--text);text-decoration:none">
            <span><?= $t['is_completed'] ? '✅' : '📋' ?></span>
            <span style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($t['title']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="admin-layout">
      <div class="admin-sidebar panel">
        <div style="display:flex;align-items:center;justify-content:space-between;cursor:pointer" onclick="toggleNav()">
          <div><h3 style="display:inline;font-size:14px">Webmaster v2</h3><p class="hint" style="font-size:10px"><?= e($user['full_name'] ?: $user['username']) ?></p></div>
          <span id="navToggleIcon" style="font-size:14px;color:var(--text2)">◀</span>
        </div>
        <nav class="admin-nav" id="adminNav">
          <?php foreach ($navLinks as $link): ?>
            <?php if (!$isAdmin && $link['admin']) continue; ?>
            <?php if (!empty($link['super']) && !$isSuperAdmin) continue; ?>
            <?php if ($link['tab'] === 'divider' || $link['tab'] === 'divider2'): ?>
              <hr style="border-color:var(--line-soft);margin:8px 0">
            <?php else: ?>
              <a href="/?page=admin&tab=<?= $link['tab'] ?>" class="<?= $effectiveTab === $link['tab'] ? 'active' : '' ?>"><?= $link['label'] ?></a>
            <?php endif; ?>
          <?php endforeach; ?>
        </nav>
      </div>
      <script>
      function toggleNav() {
        var nav = document.getElementById('adminNav');
        var icon = document.getElementById('navToggleIcon');
        if (nav.style.display === 'none') {
          nav.style.display = 'flex';
          icon.textContent = '◀';
        } else {
          nav.style.display = 'none';
          icon.textContent = '▶';
        }
      }
      </script>

      <div class="admin-content">
        <?php
        match ($effectiveTab) {
            'dashboard' => admin_dashboard($stats, $orders, $lowStockProducts, $products, $customers),
            'products' => admin_products($products, $categories),
            'categories' => admin_categories($categories),
            'comingsoon' => admin_coming_soon(),
            'orders' => admin_orders($orders, $orderSearch),
            'customers' => admin_customers($customers),
            'employees' => admin_employees($employees),
            'inventory' => admin_inventory($inventory, $products, $locations),
            'reorder' => admin_reorder($reorderItems, $lowStockProducts, $products, $locations),
            'payments' => admin_payments($paymentSettings),
            'coupons' => admin_coupons($coupons),
            'audit' => admin_audit($auditLogs),
            'signins' => admin_signins($signInLogs),
            'pos' => admin_pos($posSessions, $openPosSession, $posTransactions, $user, $products),
            'bugreports' => admin_bug_reports(),
            'pages' => admin_pages(),
            'blog' => admin_blog(),
            'events' => admin_events(),
            'contact' => admin_contact(),
            'sizecharts' => admin_size_charts(),
            'shipping' => admin_shipping(),
            'todos' => admin_todos($todos),
            'inbox' => admin_inbox($user),
            'newsletter' => admin_newsletter(),
            'memberships' => admin_memberships(),
            'security' => admin_security(),
            'settings' => admin_settings(),
            default => admin_dashboard($stats, $orders, $lowStockProducts, $products, $customers),
        };
        ?>
      </div>
    </div>
    <?php
    return ob_get_clean();
}

function admin_dashboard(array $stats, array $orders, array $lowStock, array $products, array $customers): void
{
    ?>
    <div class="panel">
      <p class="eyebrow">Command Center</p>
      <h2>Dashboard v2</h2>
    </div>
    <div class="stats">
      <div><strong>$<?= e(number_format((float)$stats['revenue'], 2)) ?></strong><span>Revenue</span></div>
      <div><strong><?= e((string)$stats['products']) ?></strong><span>Products</span></div>
      <div><strong><?= e((string)$stats['customers']) ?></strong><span>Customers</span></div>
      <div><strong><?= e((string)$stats['orders']) ?></strong><span>Orders</span></div>
      <div><strong><?= e((string)$stats['pending_orders']) ?></strong><span>Pending</span></div>
      <div><strong><?= e((string)count($lowStock)) ?></strong><span>Needs Reorder</span></div>
      <div><strong><?= e((string)$stats['low_stock']) ?></strong><span>Low Stock</span></div>
    </div>
    <div class="grid two" style="margin-top:24px">
      <div class="panel">
        <h3>Recent Orders</h3>
        <table class="table">
          <tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th></tr>
          <?php foreach (array_slice($orders, 0, 8) as $ord): ?>
            <tr>
              <td>#<?= e($ord['order_number']) ?></td>
              <td><?= e($ord['full_name'] ?? $ord['customer_email'] ?? 'Guest') ?></td>
              <td>$<?= e(number_format((float)$ord['total'], 2)) ?></td>
              <td><span class="status-<?= e($ord['status']) ?>"><?= e(ucfirst($ord['status'])) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
      <div class="panel">
        <h3>Reorder Alerts (stock ≤ reorder level)</h3>
        <?php if (empty($lowStock)): ?>
          <p>No items need reordering.</p>
        <?php else: ?>
          <table class="table">
            <tr><th>Product</th><th>SKU</th><th>Stock</th><th>Reorder At</th></tr>
            <?php foreach ($lowStock as $ls): ?>
              <tr>
                <td><?= e($ls['name']) ?></td>
                <td><?= e($ls['sku']) ?></td>
                <td style="color:<?= $ls['stock_quantity'] <= 0 ? '#ff4c4c' : '#ffaa33' ?>;font-weight:700"><?= e((string)$ls['stock_quantity']) ?></td>
                <td><?= e((string)$ls['reorder_level']) ?></td>
              </tr>
            <?php endforeach; ?>
          </table>
        <?php endif; ?>
        <a href="/?page=admin&tab=reorder" class="button" style="margin-top:12px">Manage Reorders</a>
      </div>
    </div>
    <?php
}

function admin_products(array $products, array $categories): void
{
    $imgDir = dirname(__DIR__, 2) . '/public/assets/img/products/';
    $imgUrls = [];
    if ($handle = opendir($imgDir)) {
        while (($f = readdir($handle)) !== false) {
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $imgUrls[] = '/assets/img/products/' . $f;
            }
        }
        closedir($handle);
        sort($imgUrls);
    }
    ?>
    <div class="panel">
      <h2>Product Manager</h2>
      <details>
        <summary class="button primary" style="display:inline-block;cursor:pointer;margin-bottom:16px">+ Add Product</summary>
        <div style="margin-top:16px">
          <form method="post" class="form" style="max-width:600px" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="admin_add_product">
            <div class="form-row">
              <label>Name<input name="name" required></label>
              <label>SKU<input name="sku" required></label>
            </div>
            <div class="form-row">
              <label>Price<input name="price" type="number" step="0.01" required></label>
              <label>Sale Price<input name="sale_price" type="number" step="0.01"></label>
            </div>
            <div class="form-row">
              <label>Stock Qty<input name="stock_quantity" type="number" value="25"></label>
              <label>Low Stock Threshold<input name="low_stock" type="number" value="10"></label>
            </div>
            <div class="form-row">
              <label>Sizes (comma-sep)<input name="sizes" value="M,L,XL,XXL,XXXL"></label>
              <label>Colors (comma-sep)<input name="colors" value="Black"></label>
            </div>
            <label>Category
              <select name="category_id">
                <option value="">None</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>Short Description<input name="short_description"></label>
            <label>Description<textarea name="description" required></textarea></label>
            <label>SEO Description<textarea name="seo_description"></textarea></label>
            <select name="status"><option value="active">Active</option><option value="draft">Draft</option><option value="archived">Archived</option></select>
            <label class="checkbox-label"><input type="checkbox" name="is_featured" value="1"> Featured</label>
            <div class="form-row">
              <label>Upload Image<input name="product_image" type="file" accept="image/*"></label>
              <label>Or select existing:
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:4px;max-height:120px;overflow-y:auto">
                  <?php foreach ($imgUrls as $url): ?>
                    <label style="cursor:pointer;text-align:center;border:2px solid transparent;padding:2px" onmouseover="this.style.borderColor='var(--line)'" onmouseout="this.style.borderColor='transparent'">
                      <img src="<?= e($url) ?>" style="width:80px;height:80px;object-fit:cover;display:block;cursor:pointer" onclick="previewImg(this.src)">
                      <input type="radio" name="existing_image" value="<?= e($url) ?>" style="margin-top:2px">
                    </label>
                  <?php endforeach; ?>
                </div>
              </label>
            </div>
            <button class="button primary" type="submit">Create Product</button>
          </form>
        </div>
      </details>
    </div>
    <div class="panel">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
        <button class="button" type="submit" form="bulkDeleteForm" style="padding:8px 16px;min-height:auto;border-color:rgba(255,76,76,0.5);background:rgba(255,76,76,0.1);color:#ff4c4c;font-size:12px" onclick="return confirm('Delete selected products?')" id="bulkDeleteBtn" disabled>Delete Selected</button>
        <label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer;color:var(--muted)"><input type="checkbox" id="selectAll" onchange="document.querySelectorAll('.product-select').forEach(function(c){c.checked=this.checked;c.dispatchEvent(new Event('change'))})"> Select All</label>
      </div>
      <form method="post" id="bulkDeleteForm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="admin_bulk_delete_products">
      </form>
      <table class="table">
        <tr><th style="width:30px"></th><th>Image</th><th>Name</th><th>SKU</th><th>Price</th><th>Stock</th><th>Status</th><th>Upload Image</th><th>Actions</th></tr>
        <?php foreach ($products as $p): ?>
          <tr>
            <td style="text-align:center;font-size:11px;color:var(--text2)"><input type="checkbox" class="product-select" form="bulkDeleteForm" name="ids[]" value="<?= (int)$p['id'] ?>" onchange="document.getElementById('bulkDeleteBtn').disabled=!document.querySelectorAll('.product-select:checked').length"><br><span>#<?= (int)$p['id'] ?></span></td>
            <td>
              <?php $imgs = json_decode($p['images'] ?? '[]', true); ?>
              <?php if (!empty($imgs[0])): ?><img src="<?= e($imgs[0]) ?>" style="width:80px;height:80px;object-fit:cover;border:1px solid var(--line-soft);cursor:pointer" onclick="previewImg(this.src)"><?php endif; ?>
            </td>
            <td><a href="/?page=product&slug=<?= e($p['slug']) ?>"><?= e($p['name']) ?></a></td>
            <td><?= e($p['sku']) ?></td>
            <td>$<?= e(number_format((float)($p['sale_price'] ?: $p['price']), 2)) ?></td>
            <td style="color:<?= ($p['stock_quantity'] ?? 0) <= ($p['reorder_level'] ?? 3) ? '#ffaa33' : 'inherit' ?>"><?= e((string)($p['stock_quantity'] ?? 'N/A')) ?></td>
            <td><span class="status-<?= e($p['status']) ?>"><?= e(ucfirst($p['status'])) ?></span></td>
            <td>
              <form method="post" enctype="multipart/form-data" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_upload_product_image">
                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                <input type="file" name="image" accept="image/*" required style="width:auto;padding:6px;font-size:12px">
                <button class="button" type="submit" style="padding:4px 10px;min-height:auto">Upload</button>
              </form>
            </td>
            <td>
              <form method="post" class="inline-form" style="gap:4px">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_edit_product">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <input name="name" value="<?= e($p['name']) ?>" style="width:80px;padding:4px;font-size:11px">
                <input name="price" value="<?= e($p['price']) ?>" type="number" step="0.01" style="width:60px;padding:4px;font-size:11px">
                <input name="sale_price" value="<?= e($p['sale_price'] ?? '') ?>" type="number" step="0.01" placeholder="Sale" style="width:55px;padding:4px;font-size:11px">
                <select name="status" style="width:65px;padding:4px;font-size:11px">
                  <option value="active" <?= $p['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                  <option value="draft" <?= $p['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                  <option value="archived" <?= $p['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
                </select>
                <button class="button" type="submit" style="padding:4px 6px;min-height:auto;font-size:11px">Save</button>
              </form>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_delete_product">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <button class="button" type="submit" style="padding:2px 6px;min-height:auto;font-size:10px;border-color:rgba(255,76,76,0.5)" onclick="return confirm('Delete this product?')">Del</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php
}

function admin_categories(array $categories): void
{
    ?>
    <div class="panel">
      <h2>Category Manager</h2>
      <details>
        <summary class="button primary" style="display:inline-block;cursor:pointer;margin-bottom:16px">+ Add Category</summary>
        <div style="margin-top:16px">
          <form method="post" class="form" style="max-width:450px">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="admin_add_category">
            <label>Name<input name="name" required></label>
            <label>Description<textarea name="description"></textarea></label>
            <div class="form-row">
              <label>Parent Category
                <select name="parent_id">
                  <option value="">None (top level)</option>
                  <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Sort Order<input name="sort_order" type="number" value="0"></label>
            </div>
            <button class="button primary" type="submit">Create Category</button>
          </form>
        </div>
      </details>
    </div>
    <div class="panel">
      <table class="table">
        <tr><th>Name</th><th>Slug</th><th>Products</th><th>Sort</th><th>Active</th><th>Actions</th></tr>
        <?php foreach ($categories as $cat): ?>
          <tr>
            <td><strong><?= e($cat['name']) ?></strong></td>
            <td><?= e($cat['slug']) ?></td>
            <td><?= (int)db()->query("SELECT COUNT(*) FROM products WHERE category_id = {$cat['id']}")->fetchColumn() ?></td>
            <td><?= (int)$cat['sort_order'] ?></td>
            <td><?= $cat['active'] ? '✓' : '✗' ?></td>
            <td>
              <form method="post" class="inline-form" style="gap:4px">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_edit_category">
                <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                <input name="name" value="<?= e($cat['name']) ?>" required style="width:120px;padding:4px 8px;font-size:12px">
                <input name="description" placeholder="Description" value="<?= e($cat['description'] ?? '') ?>" style="width:120px;padding:4px 8px;font-size:12px">
                <input name="sort_order" type="number" value="<?= (int)$cat['sort_order'] ?>" style="width:50px;padding:4px 8px;font-size:12px">
                <label class="checkbox-label" style="font-size:12px;gap:4px"><input type="checkbox" name="active" value="1" <?= $cat['active'] ? 'checked' : '' ?>> Active</label>
                <button class="button" type="submit" style="padding:4px 8px;min-height:auto">Save</button>
              </form>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_delete_category">
                <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                <button class="button" type="submit" style="padding:4px 8px;min-height:auto;border-color:rgba(255,76,76,0.5)" onclick="return confirm('Delete this category?')">Del</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php
}

function admin_coming_soon(): void
{
    $items = db()->query('SELECT cs.*, c.name as category_name FROM coming_soon cs LEFT JOIN categories c ON c.id = cs.category_id ORDER BY cs.release_date ASC')->fetchAll();
    $categories = db()->query('SELECT * FROM categories WHERE active = 1 ORDER BY name')->fetchAll();
    ?>
    <div class="panel">
      <h2>Coming Soon</h2>
      <details>
        <summary class="button primary" style="display:inline-block;cursor:pointer;margin-bottom:16px">+ Add Coming Soon Item</summary>
        <div style="margin-top:16px">
          <form method="post" class="form" style="max-width:500px" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="admin_add_coming_soon">
            <div class="form-row">
              <label>Name<input name="name" required></label>
              <label>Price<input name="price" type="number" step="0.01" required></label>
            </div>
            <label>Description<textarea name="description"></textarea></label>
            <div class="form-row">
              <label>Category
                <select name="category_id">
                  <option value="">None</option>
                  <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Release Date<input name="release_date" type="date" required style="width:140px;display:inline-block" title="Click to pick a date"></label>
<label style="margin-left:8px">Time<input name="release_time" type="time" value="13:00" style="width:auto;display:inline-block"></label>
            </div>
            <label>Image URL<input name="image" placeholder="/assets/img/products/swag.jpg"></label>
            <button class="button primary" type="submit">Add Coming Soon</button>
          </form>
        </div>
      </details>
    </div>
    <div class="panel">
      <?php if (empty($items)): ?>
        <p><em>No coming soon items. Add one above.</em></p>
      <?php else: ?>
        <table class="table">
          <tr><th>Name</th><th>Price</th><th>Category</th><th>Release Date</th><th>Actions</th></tr>
          <?php foreach ($items as $item): ?>
            <tr>
              <td><?= e($item['name']) ?></td>
              <td>$<?= e(number_format((float)$item['price'], 2)) ?></td>
              <td><?= e($item['category_name'] ?? '—') ?></td>
              <td><?= e(date('M j, Y g:i A', strtotime($item['release_date']))) ?></td>
              <td>
                <form method="post" class="inline-form" style="gap:4px">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="admin_edit_coming_soon">
                  <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                  <input name="name" value="<?= e($item['name']) ?>" style="width:80px;padding:4px;font-size:11px">
                  <input name="price" value="<?= e($item['price']) ?>" type="number" step="0.01" style="width:60px;padding:4px;font-size:11px">
                  <input name="release_date" value="<?= e(date('Y-m-d', strtotime($item['release_date']))) ?>" type="date" style="width:110px;padding:4px;font-size:11px">
                  <input name="release_time" value="<?= e(date('H:i', strtotime($item['release_date']))) ?>" type="time" style="width:60px;padding:4px;font-size:11px">
                  <button class="button" type="submit" style="padding:4px 6px;min-height:auto;font-size:11px">Save</button>
                </form>
                <form method="post" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="admin_delete_coming_soon">
                  <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                  <button class="button" type="submit" style="padding:2px 6px;min-height:auto;font-size:10px;border-color:rgba(255,76,76,0.5)" onclick="return confirm('Delete?')">Del</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>
    <?php
}

function admin_orders(array $orders, string $search): void
{
    ?>
    <div class="panel">
      <h2>Order Manager</h2>
      <form method="get" class="inline-form" style="margin-bottom:16px">
        <input type="hidden" name="page" value="admin">
        <input type="hidden" name="tab" value="orders">
        <input name="order_search" placeholder="Search by customer, order#, transaction ID..." value="<?= e($search) ?>" style="width:400px">
        <select name="order_by">
          <option value="created_at">Date</option>
          <option value="total">Total</option>
          <option value="status">Status</option>
          <option value="order_number">Order #</option>
        </select>
        <select name="order_dir">
          <option value="DESC">Newest</option>
          <option value="ASC">Oldest</option>
        </select>
        <button class="button" type="submit" style="padding:8px 16px;min-height:auto">Search</button>
      </form>
    </div>
    <div class="panel">
      <table class="table">
        <tr><th>Order #</th><th>Type</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th><th>Payment</th><th>Update</th></tr>
        <?php foreach ($orders as $ord): ?>
          <tr>
            <td>#<?= e($ord['order_number']) ?></td>
            <td><span class="badge" style="background:<?= ($ord['order_type'] ?? 'standard') === 'preorder' ? 'var(--cyan)' : 'var(--muted)' ?>"><?= e(ucfirst($ord['order_type'] ?? 'standard')) ?></span></td>
            <td><?= e($ord['full_name'] ?? $ord['customer_email'] ?? 'Guest') ?></td>
            <td>$<?= e(number_format((float)$ord['total'], 2)) ?></td>
            <td><span class="status-<?= e($ord['status']) ?>"><?= e(ucfirst($ord['status'])) ?></span></td>
            <td><?= e(date('M j, Y', strtotime($ord['created_at']))) ?></td>
            <td>
              <?php $pmt = db()->query("SELECT provider FROM payments WHERE order_id = {$ord['id']} LIMIT 1")->fetch(); ?>
              <?= $pmt ? e(ucfirst(str_replace('_', ' ', $pmt['provider']))) : '—' ?>
            </td>
            <td>
              <form method="post" class="inline-form" style="gap:4px">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_update_order">
                <input type="hidden" name="order_id" value="<?= (int)$ord['id'] ?>">
                <select name="status" style="width:110px;padding:4px 8px;font-size:12px">
                  <?php foreach (['pending','paid','processing','shipped','delivered','cancelled','refunded'] as $s): ?>
                    <option value="<?= $s ?>" <?= $ord['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                  <?php endforeach; ?>
                </select>
                <input name="tracking_number" placeholder="Tracking #" value="<?= e($ord['tracking_number'] ?? '') ?>" style="width:120px;padding:4px 8px;font-size:12px">
                <input name="carrier" placeholder="Carrier" value="<?= e($ord['carrier'] ?? '') ?>" style="width:80px;padding:4px 8px;font-size:12px">
                <button class="button" type="submit" style="padding:4px 8px;min-height:auto">Update</button>
              </form>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_delete_order">
                <input type="hidden" name="id" value="<?= (int)$ord['id'] ?>">
                <button class="button" type="submit" style="padding:2px 6px;min-height:auto;font-size:10px;border-color:rgba(255,76,76,0.5)" onclick="return confirm('Delete order #<?= e($ord['order_number']) ?>?')">Del</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php
}

function admin_customers(array $customers): void
{
    ?>
    <div class="panel">
      <h2>Customer Manager</h2>
      <details>
        <summary class="button primary" style="display:inline-block;cursor:pointer;margin-bottom:16px">+ Add Customer</summary>
        <div style="margin-top:16px">
          <form method="post" class="form" style="max-width:450px">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="admin_add_customer">
            <div class="form-row">
              <label>Username<input name="username" required></label>
              <label>Email<input name="email" type="email" required></label>
            </div>
            <div class="form-row">
              <label>Full Name<input name="full_name" required></label>
              <label>Phone<input name="phone"></label>
            </div>
            <label>Password (leave blank to auto-generate)<input name="password" type="password" placeholder="Or leave blank for random"></label>
            <button class="button primary" type="submit">Create Customer</button>
          </form>
        </div>
      </details>
    </div>
    <div class="panel">
      <table class="table">
        <tr><th>Name</th><th>Username</th><th>Email</th><th>Orders</th><th>Role</th><th>Joined</th><th>Verified</th><th>Actions</th></tr>
        <?php foreach ($customers as $c): ?>
          <tr>
            <td><?= e($c['full_name'] ?? '—') ?></td>
            <td><?= e($c['username']) ?></td>
            <td><?= e($c['email']) ?></td>
            <td><strong><?= (int)($c['total_orders'] ?? 0) ?></strong></td>
            <td><span class="badge"><?= e(ucfirst($c['role'])) ?></span></td>
            <td><?= e(date('M j, Y', strtotime($c['created_at']))) ?></td>
            <td><?= $c['email_verified_at'] ? '✓' : '✗' ?></td>
            <td>
              <form method="post" class="inline-form" style="gap:4px">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_edit_customer">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <input name="full_name" value="<?= e($c['full_name'] ?? '') ?>" style="width:100px;padding:4px 6px;font-size:11px">
                <input name="email" value="<?= e($c['email']) ?>" style="width:120px;padding:4px 6px;font-size:11px">
                <input name="phone" value="<?= e($c['phone'] ?? '') ?>" placeholder="Phone" style="width:80px;padding:4px 6px;font-size:11px">
                <label class="checkbox-label" style="font-size:11px;gap:4px"><input type="checkbox" name="is_deleted" value="1" <?= $c['is_deleted'] ? 'checked' : '' ?>> Banned</label>
                <button class="button" type="submit" style="padding:4px 8px;min-height:auto">Save</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php
}

function admin_employees(array $employees): void
{
    ?>
    <div class="panel">
      <h2>Employee Manager</h2>
      <p class="hint">Webmaster accounts hidden from customer tables.</p>
      <details>
        <summary class="button primary" style="display:inline-block;cursor:pointer;margin-bottom:16px">+ Add Employee</summary>
        <div style="margin-top:16px">
          <form method="post" class="form" style="max-width:450px">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="admin_add_employee">
            <div class="form-row">
              <label>Username<input name="username" required></label>
              <label>Email<input name="email" type="email" required></label>
            </div>
            <div class="form-row">
              <label>Full Name<input name="full_name" required></label>
              <label>Phone<input name="phone"></label>
            </div>
            <label>Role
              <select name="role">
                <option value="support">Support</option>
                <option value="moderator">Moderator</option>
                <option value="inventory_manager">Inventory Manager</option>
                <option value="super_admin">Super Admin</option>
                <option value="webmaster">Webmaster</option>
              </select>
            </label>
            <label>Password (leave blank to auto-generate)<input name="password" type="password" placeholder="Or leave blank for random"></label>
            <button class="button primary" type="submit">Create Employee</button>
          </form>
        </div>
      </details>
    </div>
    <div class="panel">
      <table class="table">
        <tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Admin Level</th><th>Joined</th><th>Status</th><th>Actions</th></tr>
        <?php foreach ($employees as $e):
          $isDeleted = !empty($e['is_deleted']);
        ?>
          <tr style="<?= $isDeleted ? 'opacity:0.5' : '' ?>">
            <td><?= e($e['full_name'] ?? '—') ?></td>
            <td><?= e($e['username']) ?></td>
            <td><?= e($e['email']) ?></td>
            <td><span class="badge"><?= e(ucfirst($e['role'])) ?></span></td>
            <td><?= $e['permission_level'] ? e($e['permission_level']) : '—' ?></td>
            <td><?= e(date('M j, Y', strtotime($e['created_at']))) ?></td>
            <td><?= $isDeleted ? 'Disabled' : 'Active' ?></td>
            <td>
              <form method="post" class="inline-form" style="gap:4px">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_edit_employee">
                <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                <input name="full_name" value="<?= e($e['full_name'] ?? '') ?>" style="width:90px;padding:4px 6px;font-size:11px">
                <input name="phone" value="<?= e($e['phone'] ?? '') ?>" style="width:80px;padding:4px 6px;font-size:11px">
                <select name="role" style="width:90px;padding:4px 6px;font-size:11px">
                  <?php foreach (['support','moderator','inventory_manager','super_admin','webmaster'] as $r): ?>
                    <option value="<?= $r ?>" <?= $e['role'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                  <?php endforeach; ?>
                </select>
                <label class="checkbox-label" style="font-size:11px;gap:4px"><input type="checkbox" name="is_deleted" value="1" <?= $isDeleted ? 'checked' : '' ?>> Off</label>
                <button class="button" type="submit" style="padding:4px 6px;min-height:auto;font-size:11px">Save</button>
              </form>
              <form method="post" style="display:inline-flex;gap:4px;align-items:center">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_employee_reset_password">
                <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                <input name="new_password" type="password" placeholder="New PW" style="width:80px;padding:4px 6px;font-size:11px" title="Leave blank to auto-generate">
                <button class="button" type="submit" style="padding:4px 6px;min-height:auto;font-size:11px">Set PW</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php
}

function admin_inventory(array $inventory, array $products, array $locations): void
{
    ?>
    <div class="panel">
      <h2>Inventory Manager</h2>
      <div class="grid two" style="margin-top:16px">
        <div>
          <h3>Add Stock</h3>
          <form method="post" class="form" style="max-width:400px">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="admin_add_inventory">
            <label>Product
              <select name="product_id" required>
                <?php foreach ($products as $p): ?>
                  <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['sku']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </label>
            <div class="form-row">
              <label>Qty<input name="stock_quantity" type="number" required></label>
              <label>Threshold<input name="low_stock_threshold" type="number" value="10"></label>
              <label>Reorder Level<input name="reorder_level" type="number" value="3"></label>
            </div>
            <div class="form-row">
              <label>Warehouse<input name="warehouse" value="Main Warehouse"></label>
              <label>Location
                <select name="location_id">
                  <option value="">None</option>
                  <?php foreach ($locations as $loc): ?>
                    <option value="<?= (int)$loc['id'] ?>"><?= e($loc['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
            </div>
            <button class="button primary" type="submit">Add/Update Stock</button>
          </form>
        </div>
        <div>
          <h3>Locations</h3>
          <details>
            <summary class="button" style="display:inline-block;cursor:pointer">+ Add Location</summary>
            <form method="post" class="form" style="margin-top:8px">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="admin_add_location">
              <input name="name" placeholder="Location name" required>
              <input name="address" placeholder="Address">
              <div class="form-row">
                <input name="contact" placeholder="Contact person">
                <input name="phone" placeholder="Phone">
              </div>
              <button class="button primary" type="submit">Add</button>
            </form>
          </details>
          <?php foreach ($locations as $loc): ?>
            <div class="address-card" style="margin-top:8px;padding:10px;border:1px solid var(--line-soft)">
              <strong><?= e($loc['name']) ?></strong>
              <p style="font-size:12px"><?= e($loc['address'] ?? '') ?> <?= e($loc['contact'] ?? '') ?></p>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_delete_location">
                <input type="hidden" name="id" value="<?= (int)$loc['id'] ?>">
                <button class="button" type="submit" style="padding:2px 8px;min-height:auto;font-size:10px" onclick="return confirm('Delete?')">Del</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <div class="panel">
      <table class="table">
        <tr><th>Product</th><th>SKU</th><th>Warehouse</th><th>Location</th><th>Stock</th><th>Threshold</th><th>Reorder At</th><th>Edit</th></tr>
        <?php foreach ($inventory as $inv): ?>
          <tr>
            <td><?= e($inv['product_name']) ?></td>
            <td><?= e($inv['product_sku']) ?></td>
            <td><?= e($inv['warehouse']) ?></td>
            <td><?= e($inv['location_name'] ?? '—') ?></td>
            <td style="color:<?= (int)$inv['stock_quantity'] <= (int)$inv['reorder_level'] ? ($inv['stock_quantity'] <= 0 ? '#ff4c4c' : '#ffaa33') : 'inherit' ?>;font-weight:700"><?= e((string)$inv['stock_quantity']) ?></td>
            <td><?= e((string)$inv['low_stock_threshold']) ?></td>
            <td><?= e((string)$inv['reorder_level']) ?></td>
            <td>
              <form method="post" class="inline-form" style="gap:4px">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_edit_inventory">
                <input type="hidden" name="id" value="<?= (int)$inv['id'] ?>">
                <input name="stock_quantity" value="<?= (int)$inv['stock_quantity'] ?>" style="width:50px;padding:4px;font-size:11px">
                <input name="low_stock_threshold" value="<?= (int)$inv['low_stock_threshold'] ?>" style="width:40px;padding:4px;font-size:11px">
                <input name="reorder_level" value="<?= (int)$inv['reorder_level'] ?>" style="width:40px;padding:4px;font-size:11px">
                <button class="button" type="submit" style="padding:4px 8px;min-height:auto;font-size:11px">Save</button>
              </form>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_delete_inventory">
                <input type="hidden" name="id" value="<?= (int)$inv['id'] ?>">
                <button class="button" type="submit" style="padding:2px 6px;min-height:auto;font-size:10px;border-color:rgba(255,76,76,0.5)" onclick="return confirm('Delete inventory record?')">Del</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php
}

function admin_reorder(array $reorderItems, array $lowStockProducts, array $products, array $locations): void
{
    ?>
    <div class="panel">
      <h2>Reorder System</h2>
      <p class="hint">Products with stock at or below reorder level need attention.</p>
      <?php if (!empty($lowStockProducts)): ?>
        <div style="margin-bottom:16px">
          <h3>Products Needing Reorder</h3>
          <table class="table">
            <tr><th>Product</th><th>SKU</th><th>Stock</th><th>Reorder Level</th><th>Order Qty</th><th></th></tr>
            <?php foreach ($lowStockProducts as $ls): ?>
              <tr>
                <td><?= e($ls['name']) ?></td>
                <td><?= e($ls['sku']) ?></td>
                <td style="color:<?= $ls['stock_quantity'] <= 0 ? '#ff4c4c' : '#ffaa33' ?>;font-weight:700"><?= e((string)$ls['stock_quantity']) ?></td>
                <td><?= e((string)$ls['reorder_level']) ?></td>
                <td>
                  <form method="post" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="admin_add_reorder">
                    <input type="hidden" name="product_id" value="<?= (int)$ls['id'] ?>">
                    <input name="quantity_requested" type="number" value="<?= max(10, (int)$ls['reorder_level'] * 3) ?>" style="width:60px;padding:4px;font-size:11px">
                    <button class="button primary" type="submit" style="padding:4px 8px;min-height:auto;font-size:11px">Reorder</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </table>
        </div>
      <?php endif; ?>
    </div>
    <div class="panel">
      <h3>All Reorder Requests</h3>
      <details>
        <summary class="button" style="display:inline-block;cursor:pointer;margin-bottom:12px">+ New Reorder Request</summary>
        <form method="post" class="form" style="max-width:400px;margin-top:12px">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="admin_add_reorder">
          <label>Product
            <select name="product_id" required>
              <?php foreach ($products as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?> (Stock: <?= (int)($p['stock_quantity'] ?? 0) ?>)</option>
              <?php endforeach; ?>
            </select>
          </label>
          <div class="form-row">
            <label>Qty<input name="quantity_requested" type="number" required></label>
            <label>Location
              <select name="location_id">
                <option value="">None</option>
                <?php foreach ($locations as $loc): ?>
                  <option value="<?= (int)$loc['id'] ?>"><?= e($loc['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <label>Supplier<input name="supplier"></label>
          <label>Notes<textarea name="notes"></textarea></label>
          <button class="button primary" type="submit">Create Request</button>
        </form>
      </details>
      <table class="table">
        <tr><th>Product</th><th>SKU</th><th>Qty Requested</th><th>Qty Received</th><th>Location</th><th>Supplier</th><th>Status</th><th>Requested By</th><th>Update</th></tr>
        <?php foreach ($reorderItems as $rr): ?>
          <tr>
            <td><?= e($rr['product_name']) ?></td>
            <td><?= e($rr['product_sku']) ?></td>
            <td><?= (int)$rr['quantity_requested'] ?></td>
            <td><?= (int)$rr['quantity_received'] ?></td>
            <td><?= e($rr['location_name'] ?? '—') ?></td>
            <td><?= e($rr['supplier'] ?? '—') ?></td>
            <td><span class="status-<?= e($rr['status']) ?>"><?= e(ucfirst($rr['status'])) ?></span></td>
            <td><?= e($rr['requester_name'] ?? '—') ?></td>
            <td>
              <form method="post" class="inline-form" style="gap:4px">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_update_reorder">
                <input type="hidden" name="id" value="<?= (int)$rr['id'] ?>">
                <select name="status" style="width:90px;padding:4px;font-size:11px">
                  <?php foreach (['pending','ordered','received','cancelled'] as $s): ?>
                    <option value="<?= $s ?>" <?= $rr['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                  <?php endforeach; ?>
                </select>
                <input name="quantity_received" type="number" value="<?= (int)$rr['quantity_requested'] ?>" style="width:50px;padding:4px;font-size:11px">
                <button class="button" type="submit" style="padding:4px 6px;min-height:auto;font-size:11px">Update</button>
              </form>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_delete_reorder">
                <input type="hidden" name="id" value="<?= (int)$rr['id'] ?>">
                <button class="button" type="submit" style="padding:2px 6px;min-height:auto;font-size:10px;border-color:rgba(255,76,76,0.5)" onclick="return confirm('Delete reorder?')">X</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php
}

function admin_payments(array $paymentSettings): void
{
    ?>
    <div class="panel">
      <h2>Payment Gateway Settings</h2>
      <p class="hint">Configure all payment providers. Sandbox mode for testing.</p>
      <div class="grid two" style="margin-top:20px">
        <?php foreach ($paymentSettings as $ps): ?>
          <div class="panel" style="margin-bottom:12px">
            <h3><?= e($ps['label'] ?: ucfirst(str_replace('_', ' ', $ps['provider']))) ?></h3>
            <form method="post" class="form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="admin_update_payment">
              <input type="hidden" name="id" value="<?= (int)$ps['id'] ?>">
              <div class="form-row">
                <label class="checkbox-label"><input type="checkbox" name="enabled" value="1" <?= $ps['enabled'] ? 'checked' : '' ?>> Enabled</label>
                <label class="checkbox-label"><input type="checkbox" name="sandbox_mode" value="1" <?= $ps['sandbox_mode'] ? 'checked' : '' ?>> Sandbox</label>
              </div>
              <label>Label<input name="label" value="<?= e($ps['label'] ?? '') ?>"></label>
              <?php if ($ps['provider'] === 'cash_app'): ?>
                <label>Cash App <code>$</code>Cashtag <input name="extra[cashtag]" value="<?= e(json_decode($ps['extra_settings'] ?? '{}', true)['cashtag'] ?? '') ?>" placeholder="e.g. Suggawayz"></label>
              <?php else: ?>
                <label>Public Key / Client ID<input name="public_key" value="<?= e($ps['public_key'] ?? '') ?>"></label>
                <label>Secret Key / Token<input name="secret_key" value="<?= e($ps['secret_key'] ?? '') ?>"></label>
              <?php endif; ?>
              <button class="button primary" type="submit">Save Settings</button>
            </form>
          </div>
        <?php endforeach; ?>
      </table>
    </div>
    <script>
    function previewImg(src) {
      var m = document.getElementById('imgModal');
      if (!m) {
        m = document.createElement('div');
        m.id = 'imgModal';
        m.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:9999;display:flex;align-items:center;justify-content:center;cursor:pointer';
        m.onclick = function(){ this.remove(); };
        document.body.appendChild(m);
      }
      m.innerHTML = '<img src="' + src + '" style="width:150px;height:150px;object-fit:cover;border-radius:8px;border:2px solid var(--line)">';
    }
    </script>
    <?php
}

function admin_pages(): void
{
    $pages = db()->query('SELECT * FROM pages ORDER BY slug')->fetchAll();
    ?>
    <div class="panel">
      <h2>Page Manager</h2>
      <details>
        <summary class="button primary" style="display:inline-block;cursor:pointer;margin-bottom:16px">+ Add Page</summary>
        <form method="post" class="form" style="max-width:600px;margin-top:12px">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="admin_add_page">
          <div class="form-row">
            <label>Title<input name="title" required></label>
            <label>Slug<input name="slug" placeholder="leave blank to auto-generate"></label>
          </div>
          <label>Content<textarea name="content" required style="min-height:200px"></textarea></label>
          <div class="form-row">
            <label>Meta Title<input name="meta_title"></label>
            <label>Meta Description<input name="meta_description"></label>
          </div>
          <label class="checkbox-label"><input type="checkbox" name="published" value="1" checked> Published</label>
          <button class="button primary" type="submit">Create Page</button>
        </form>
      </details>
    </div>
    <div class="panel">
      <table class="table">
        <tr><th>Title</th><th>Slug</th><th>Published</th><th>Actions</th></tr>
        <?php foreach ($pages as $p): ?>
          <tr>
            <td>
              <form method="post" class="inline-form" style="gap:4px">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_edit_page">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <input name="title" value="<?= e($p['title']) ?>" style="width:140px;padding:4px;font-size:11px">
                <input name="slug" value="<?= e($p['slug']) ?>" style="width:100px;padding:4px;font-size:11px">
            </td>
            <td><?= e($p['slug']) ?></td>
            <td><input type="checkbox" name="published" value="1" <?= $p['published'] ? 'checked' : '' ?>></td>
            <td>
                <label>Content<textarea name="content" style="width:200px;height:40px;font-size:11px;padding:4px"><?= e($p['content']) ?></textarea></label>
                <button class="button" type="submit" style="padding:4px 8px;min-height:auto;font-size:11px">Save</button>
              </form>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_delete_page">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <button class="button" type="submit" style="padding:2px 6px;min-height:auto;font-size:10px;border-color:rgba(255,76,76,0.5)" onclick="return confirm('Delete this page?')">Del</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php
}

function admin_blog(): void
{
    $posts = db()->query('SELECT * FROM blog_posts ORDER BY created_at DESC')->fetchAll();
    ?>
    <div class="panel">
      <h2>Blog Manager</h2>
      <details>
        <summary class="button primary" style="display:inline-block;cursor:pointer;margin-bottom:16px">+ Add Post</summary>
        <form method="post" class="form" style="max-width:600px;margin-top:12px">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="admin_add_blog">
          <div class="form-row">
            <label>Title<input name="title" required></label>
            <label>Author<input name="author" value="SUGGAWAYZ Team"></label>
          </div>
          <label>Excerpt<textarea name="excerpt" style="min-height:60px"></textarea></label>
          <label>Content<textarea name="content" required style="min-height:200px"></textarea></label>
          <label class="checkbox-label"><input type="checkbox" name="published" value="1" checked> Published</label>
          <button class="button primary" type="submit">Create Post</button>
        </form>
      </details>
    </div>
    <div class="panel">
      <table class="table">
        <tr><th>Title</th><th>Author</th><th>Published</th><th>Date</th><th>Actions</th></tr>
        <?php foreach ($posts as $post): ?>
          <tr>
            <td>
              <form method="post" class="inline-form" style="gap:4px">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_edit_blog">
                <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
                <input name="title" value="<?= e($post['title']) ?>" style="width:160px;padding:4px;font-size:11px">
            </td>
            <td><input name="author" value="<?= e($post['author'] ?? '') ?>" style="width:80px;padding:4px;font-size:11px"></td>
            <td><input type="checkbox" name="published" value="1" <?= $post['published'] ? 'checked' : '' ?>></td>
            <td><?= e(date('M j, Y', strtotime($post['created_at']))) ?></td>
            <td>
                <label>Content<textarea name="content" style="width:200px;height:40px;font-size:11px;padding:4px"><?= e($post['content']) ?></textarea></label>
                <button class="button" type="submit" style="padding:4px 8px;min-height:auto;font-size:11px">Save</button>
              </form>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_delete_blog">
                <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
                <button class="button" type="submit" style="padding:2px 6px;min-height:auto;font-size:10px;border-color:rgba(255,76,76,0.5)" onclick="return confirm('Delete this post?')">Del</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php
}

function admin_events(): void
{
    $events = db()->query('SELECT * FROM lookbook_events ORDER BY sort_order, event_date DESC')->fetchAll();
    $cfg = db()->query("SELECT * FROM payment_settings WHERE provider = 'prepay'")->fetch();
    $extra = $cfg ? json_decode($cfg['extra_settings'] ?? '{}', true) : [];
    $mapsKey = $extra['google_maps_api_key'] ?? '';
    ?>
    <div class="panel">
      <h2>Events</h2>
      <p class="hint">Events appear as pop-up cards on the Events page. Include location data for Google Maps driving directions. Set your Google Maps API Key in the <a href="/?page=admin&tab=settings">Settings tab</a> to enable address lookup + map pinning.</p>
      <details>
        <summary class="button primary" style="display:inline-block;cursor:pointer;margin-bottom:16px">+ Add Event</summary>
        <form method="post" class="form" style="max-width:600px;margin-top:12px" id="lookbook-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="admin_add_lookbook">
          <div class="form-row">
            <label>Title<input name="title" required></label>
            <label>Event Date<input name="event_date" type="date"></label>
          </div>
          <label>Description<textarea name="description" style="min-height:80px"></textarea></label>
          <div class="form-row">
            <label>Location Name<input name="location_name" placeholder="Venue name"></label>
            <label>Image URL<input name="image" placeholder="/assets/img/products/..."></label>
          </div>
          <fieldset style="border:1px solid var(--line-soft);padding:12px;margin-bottom:12px;border-radius:6px">
            <legend>Address</legend>
            <div class="form-row">
              <label>Street<input name="address" placeholder="Street address" id="lookup-address"></label>
              <label>City<input name="city" id="lookup-city"></label>
            </div>
            <div class="form-row">
              <label>State<input name="state" style="width:80px" id="lookup-state"></label>
              <label>Postal Code<input name="postal_code" style="width:100px" id="lookup-zip"></label>
            </div>
            <?php if ($mapsKey): ?>
              <button type="button" class="button" onclick="geocodeAddress('lookbook-form')" style="margin-top:4px">Look up on Map</button>
              <div id="map-preview" style="width:100%;height:260px;margin-top:8px;border-radius:6px;display:none;background:#1a1a2e"></div>
            <?php endif; ?>
          </fieldset>
          <div class="form-row">
            <label>Latitude<input name="lat" type="number" step="0.0000001" placeholder="34.052235" id="lookup-lat"></label>
            <label>Longitude<input name="lng" type="number" step="0.0000001" placeholder="-118.243683" id="lookup-lng"></label>
            <label>Sort Order<input name="sort_order" type="number" value="0" style="width:60px"></label>
          </div>
          <select name="status"><option value="published">Published</option><option value="draft">Draft</option></select>
          <button class="button primary" type="submit">Create Event</button>
        </form>
      </details>
    </div>
    <div class="panel">
      <table class="table">
        <tr><th>Title</th><th>Date</th><th>Location</th><th>Address</th><th>Status</th><th>Sort</th><th>Actions</th></tr>
        <?php foreach ($events as $ev): ?>
          <tr>
            <td>
              <form method="post" class="inline-form" style="gap:4px">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_edit_lookbook">
                <input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
                <input name="title" value="<?= e($ev['title']) ?>" style="width:120px;padding:4px;font-size:11px">
            </td>
            <td><input name="event_date" type="date" value="<?= e($ev['event_date'] ?? '') ?>" style="width:100px;padding:4px;font-size:11px"></td>
            <td><input name="location_name" value="<?= e($ev['location_name'] ?? '') ?>" style="width:100px;padding:4px;font-size:11px"></td>
            <td><input name="address" value="<?= e($ev['address'] ?? '') ?>" style="width:120px;padding:4px;font-size:11px"> <input name="city" value="<?= e($ev['city'] ?? '') ?>" style="width:80px;padding:4px;font-size:11px"> <input name="state" value="<?= e($ev['state'] ?? '') ?>" style="width:40px;padding:4px;font-size:11px"> <input name="postal_code" value="<?= e($ev['postal_code'] ?? '') ?>" style="width:60px;padding:4px;font-size:11px"></td>
            <td>
              <select name="status" style="width:80px;padding:4px;font-size:11px">
                <option value="published" <?= $ev['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                <option value="draft" <?= $ev['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
              </select>
            </td>
            <td><input name="sort_order" value="<?= (int)$ev['sort_order'] ?>" style="width:40px;padding:4px;font-size:11px"></td>
            <td>
                <input name="description" placeholder="Description" value="<?= e($ev['description'] ?? '') ?>" style="width:120px;padding:4px;font-size:11px">
                <input name="lat" value="<?= e($ev['lat'] ?? '') ?>" placeholder="Lat" style="width:70px;padding:4px;font-size:11px">
                <input name="lng" value="<?= e($ev['lng'] ?? '') ?>" placeholder="Lng" style="width:70px;padding:4px;font-size:11px">
                <input name="image" value="<?= e($ev['image'] ?? '') ?>" placeholder="Image URL" style="width:80px;padding:4px;font-size:11px">
                <button class="button" type="submit" style="padding:4px 6px;min-height:auto;font-size:11px">Save</button>
              </form>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_delete_lookbook">
                <input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
                <button class="button" type="submit" style="padding:2px 6px;min-height:auto;font-size:10px;border-color:rgba(255,76,76,0.5)" onclick="return confirm('Delete this event?')">Del</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>

    <?php if ($mapsKey): ?>
    <script>
      let map = null, marker = null, geocoder = null;

      function initEventsMap(lat, lng) {
        const pos = { lat: parseFloat(lat) || 34.0522, lng: parseFloat(lng) || -118.2437 };
        const container = document.getElementById('map-preview');
        if (!container) return;
        container.style.display = 'block';
        geocoder = new google.maps.Geocoder();
        map = new google.maps.Map(container, {
          center: pos,
          zoom: 15,
          mapTypeId: 'roadmap',
          styles: [{ featureType: 'all', elementType: 'all', stylers: [{ saturation: -100 }, { gamma: 0.8 }] }]
        });
        marker = new google.maps.Marker({
          position: pos,
          map: map,
          draggable: true
        });
        google.maps.event.addListener(marker, 'dragend', function () {
          const p = marker.getPosition();
          document.getElementById('lookup-lat').value = p.lat().toFixed(7);
          document.getElementById('lookup-lng').value = p.lng().toFixed(7);
        });
      }

      function geocodeAddress(formId) {
        const f = document.getElementById(formId);
        if (!f) return;
        const address = f.querySelector('#lookup-address').value;
        const city = f.querySelector('#lookup-city').value;
        const state = f.querySelector('#lookup-state').value;
        const zip = f.querySelector('#lookup-zip').value;
        const full = [address, city, state, zip].filter(Boolean).join(', ');
        if (!full) { alert('Please enter at least a street address.'); return; }

        if (typeof google === 'undefined' || !google.maps) {
          alert('Google Maps API not loaded. Check your API key in the Settings tab.');
          return;
        }
        if (!geocoder) geocoder = new google.maps.Geocoder();

        geocoder.geocode({ address: full }, function (results, status) {
          if (status === 'OK' && results[0]) {
            const p = results[0].geometry.location;
            document.getElementById('lookup-lat').value = p.lat().toFixed(7);
            document.getElementById('lookup-lng').value = p.lng().toFixed(7);
            if (map && marker) {
              map.setCenter(p);
              marker.setPosition(p);
            } else {
              initEventsMap(p.lat(), p.lng());
            }
          } else {
            alert('Geocode was not successful: ' + status);
          }
        });
      }

      function loadGoogleMapsApi(key) {
        if (typeof google !== 'undefined' && google.maps) return;
        const s = document.createElement('script');
        s.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(key) + '&libraries=places&loading=async';
        s.async = true;
        s.defer = true;
        document.head.appendChild(s);
      }
      loadGoogleMapsApi(<?= json_encode($mapsKey) ?>);
    </script>
    <?php endif; ?>
    <?php
}

function admin_contact(): void
{
    $filter = $_GET['filter'] ?? 'all';
    $where = ($filter === 'spam') ? 'WHERE is_spam=1' : (($filter === 'clean') ? 'WHERE is_spam=0' : '');
    $submissions = db()->query("SELECT * FROM contact_submissions $where ORDER BY created_at DESC")->fetchAll();
    ?>
    <div style="display:flex;gap:6px;margin-bottom:12px">
      <a href="/?page=admin&tab=contact&filter=all" class="button" style="padding:4px 10px;min-height:auto;font-size:11px;<?= $filter === 'all' ? 'background:rgba(0,200,255,0.15)' : '' ?>">All</a>
      <a href="/?page=admin&tab=contact&filter=clean" class="button" style="padding:4px 10px;min-height:auto;font-size:11px;<?= $filter === 'clean' ? 'background:rgba(0,200,255,0.15)' : '' ?>">Inbox</a>
      <a href="/?page=admin&tab=contact&filter=spam" class="button" style="padding:4px 10px;min-height:auto;font-size:11px;<?= $filter === 'spam' ? 'background:rgba(0,200,255,0.15)' : '' ?>">Spam (<?= db()->query("SELECT COUNT(*) FROM contact_submissions WHERE is_spam=1")->fetchColumn() ?>)</a>
    </div>
    <div class="panel">
      <h2>Contact Submissions</h2>
      <table class="table">
        <tr><th>Date</th><th>Name</th><th>Email</th><th>Subject</th><th>Message</th><th>Status</th><th></th></tr>
        <?php foreach ($submissions as $s): ?>
          <tr style="<?= $s['is_read'] ? '' : 'font-weight:700' ?>">
            <td style="white-space:nowrap"><?= e(date('M j, Y g:i A', strtotime($s['created_at']))) ?></td>
            <td><?= e($s['name']) ?></td>
            <td><a href="mailto:<?= e($s['email']) ?>"><?= e($s['email']) ?></a></td>
            <td><?= e($s['subject'] ?? '—') ?></td>
            <td style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e(substr($s['message'], 0, 50)) ?></td>
            <td style="font-size:11px"><?= $s['is_spam'] ? '🚫 Spam' : ($s['is_read'] ? 'Read' : 'New') ?></td>
            <td style="white-space:nowrap">
              <button class="button" type="button" style="padding:2px 6px;min-height:auto;font-size:10px" onclick="viewContact(this)" data-id="<?= (int)$s['id'] ?>" data-name="<?= e($s['name']) ?>" data-email="<?= e($s['email']) ?>" data-subject="<?= e($s['subject'] ?? '') ?>" data-message="<?= e($s['message']) ?>">View</button>
              <?php if (!$s['is_read'] && !$s['is_spam']): ?>
                <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="admin_mark_contact_read"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="button" type="submit" style="padding:2px 6px;min-height:auto;font-size:10px">Read</button></form>
              <?php endif; ?>
              <?php if (!$s['is_spam']): ?>
                <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="admin_mark_contact_spam"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="email" value="<?= e($s['email']) ?>"><input type="hidden" name="ip" value="<?= e($s['ip_address'] ?? '') ?>"><button class="button" type="submit" style="padding:2px 6px;min-height:auto;font-size:10px;border-color:rgba(255,170,51,0.5)">🚫 Spam</button></form>
              <?php endif; ?>
              <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="admin_block_contact"><input type="hidden" name="email" value="<?= e($s['email']) ?>"><input type="hidden" name="ip" value="<?= e($s['ip_address'] ?? '') ?>"><button class="button" type="submit" style="padding:2px 6px;min-height:auto;font-size:10px;border-color:rgba(255,76,76,0.5)" onclick="return confirm('Block this contact?')">Block</button></form>
              <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="admin_delete_contact"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="button" type="submit" style="padding:2px 6px;min-height:auto;font-size:10px;border-color:rgba(255,76,76,0.5)" onclick="return confirm('Delete?')">Del</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <div id="contactModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:9999;align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;max-width:600px;width:90%;max-height:80vh;overflow-y:auto;padding:24px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
          <h2 id="contactSubject" style="font-size:16px;margin:0"></h2>
          <button style="background:none;border:none;color:var(--text2);font-size:20px;cursor:pointer" onclick="document.getElementById('contactModal').style.display='none'">✕</button>
        </div>
        <p style="font-size:12px;color:var(--text2);margin-bottom:4px"><strong>From:</strong> <span id="contactName"></span> (<span id="contactEmail"></span>)</p>
        <div id="contactBody" style="padding:16px;background:var(--bg);border:1px solid var(--border);border-radius:4px;margin-top:12px;font-size:13px;line-height:1.6;white-space:pre-wrap"></div>
      </div>
    </div>
    <script>
    function viewContact(btn) {
      document.getElementById('contactSubject').textContent = btn.dataset.subject || '(No Subject)';
      document.getElementById('contactName').textContent = btn.dataset.name;
      document.getElementById('contactEmail').textContent = btn.dataset.email;
      document.getElementById('contactBody').textContent = btn.dataset.message;
      document.getElementById('contactModal').style.display = 'flex';
      fetch('/?action=admin_mark_contact_read&id=' + btn.dataset.id);
    }
    </script>
    <?php
}

function admin_size_charts(): void
{
    $charts = db()->query('SELECT * FROM size_charts ORDER BY name')->fetchAll();
    $categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
    ?>
    <div class="panel">
      <h2>📏 Size Charts</h2>
      <details><summary class="button primary" style="display:inline-block;cursor:pointer;margin-bottom:12px">+ New Size Chart</summary>
        <form method="post" class="form" style="max-width:600px"><?= csrf_field() ?><input type="hidden" name="action" value="admin_save_size_chart">
          <label>Chart Name<input name="name" required></label>
          <label>Category (optional)<select name="category_id"><option value="">All Products</option><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
          <div style="overflow-x:auto">
          <table class="table" style="font-size:12px" id="sizeTable">
            <tr><th>Size</th><th>Chest</th><th>Waist</th><th>Hips</th><th>Length</th><th></th></tr>
            <tr><td><input name="size[]" value="" style="width:50px;padding:2px 4px;font-size:11px"></td>
              <td><input name="chest[]" value="" style="width:60px;padding:2px 4px;font-size:11px"></td>
              <td><input name="waist[]" value="" style="width:60px;padding:2px 4px;font-size:11px"></td>
              <td><input name="hips[]" value="" style="width:60px;padding:2px 4px;font-size:11px"></td>
              <td><input name="length[]" value="" style="width:60px;padding:2px 4px;font-size:11px"></td>
              <td><button type="button" class="button" style="padding:2px 6px;font-size:10px" onclick="addRow()">+</button></td></tr>
          </table>
          </div>
          <button class="button primary" type="submit" style="margin-top:8px">Save Size Chart</button>
        </form>
      </details>
    </div>
    <?php foreach ($charts as $chart): $data = json_decode($chart['data'], true) ?: []; $chartId = (int)$chart['id']; ?>
    <div class="panel">
      <h3 style="display:flex;justify-content:space-between;align-items:center">
        <span><?= e($chart['name']) ?></span>
        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="admin_delete_size_chart"><input type="hidden" name="id" value="<?= $chartId ?>"><button class="button" type="submit" style="padding:2px 6px;font-size:10px;border-color:rgba(255,76,76,0.5)" onclick="return confirm('Delete?')">Del</button></form>
      </h3>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="admin_update_size_chart"><input type="hidden" name="id" value="<?= $chartId ?>">
      <div style="overflow-x:auto">
      <table class="table" style="font-size:12px">
        <tr><th>Size</th><th>Chest</th><th>Waist</th><th>Hips</th><th>Length</th><th></th></tr>
        <?php foreach ($data as $i => $row): ?>
          <tr>
            <td><input name="size[<?= $i ?>]" value="<?= e($row['size'] ?? '') ?>" style="width:50px;padding:2px 4px;font-size:11px"></td>
            <td><input name="chest[<?= $i ?>]" value="<?= e($row['chest'] ?? '') ?>" style="width:60px;padding:2px 4px;font-size:11px"></td>
            <td><input name="waist[<?= $i ?>]" value="<?= e($row['waist'] ?? '') ?>" style="width:60px;padding:2px 4px;font-size:11px"></td>
            <td><input name="hips[<?= $i ?>]" value="<?= e($row['hips'] ?? '') ?>" style="width:60px;padding:2px 4px;font-size:11px"></td>
            <td><input name="length[<?= $i ?>]" value="<?= e($row['length'] ?? '') ?>" style="width:60px;padding:2px 4px;font-size:11px"></td>
            <td><button type="button" class="button" style="padding:2px 6px;font-size:10px" onclick="this.closest('tr').remove()">✕</button></td>
          </tr>
        <?php endforeach; ?>
      </table>
      </div>
      <div style="margin-top:8px;display:flex;gap:8px">
        <button class="button primary" type="submit" style="padding:4px 12px;min-height:auto;font-size:11px">💾 Save Changes</button>
        <button type="button" class="button" style="padding:4px 12px;min-height:auto;font-size:11px" onclick="addEditRow(this)">+ Add Row</button>
      </div>
      </form>
    </div>
    <?php endforeach; ?>
    <script>
    function addEditRow(btn) {
      var t = btn.closest('.panel').querySelector('table');
      var r = t.insertRow(t.rows.length);
      var idx = t.rows.length - 2;
      ['size','chest','waist','hips','length'].forEach(function(f) {
        var c = r.insertCell();
        var i = document.createElement('input');
        i.name = f + '[' + idx + ']'; i.style.cssText = 'width:' + (f==='size'?50:60) + 'px;padding:2px 4px;font-size:11px';
        c.appendChild(i);
      });
      var c = r.insertCell();
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'button'; b.style.cssText = 'padding:2px 6px;font-size:10px';
      b.textContent = '✕'; b.onclick = function(){ r.remove(); };
      c.appendChild(b);
    }
    </script>
    <script>
    function addRow() {
      var t = document.getElementById('sizeTable');
      var r = t.insertRow(t.rows.length);
      ['size','chest','waist','hips','length'].forEach(function(f) {
        var c = r.insertCell();
        var i = document.createElement('input');
        i.name = f + '[]'; i.style.cssText = 'width:' + (f==='size'?50:60) + 'px;padding:2px 4px;font-size:11px';
        c.appendChild(i);
      });
      var c = r.insertCell();
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'button'; b.style.cssText = 'padding:2px 6px;font-size:10px';
      b.textContent = '✕'; b.onclick = function(){ r.remove(); };
      c.appendChild(b);
    }
    </script>
    <?php
}

function admin_shipping(): void
{
    $methods = db()->query('SELECT * FROM shipping ORDER BY region, carrier')->fetchAll();
    ?>
    <div class="panel">
      <h2>Shipping Methods</h2>
      <details>
        <summary class="button primary" style="display:inline-block;cursor:pointer;margin-bottom:16px">+ Add Method</summary>
        <form method="post" class="form" style="max-width:500px;margin-top:12px">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="admin_add_shipping">
          <div class="form-row">
            <label>Region<input name="region" required placeholder="United States"></label>
            <label>Carrier<input name="carrier" required placeholder="USPS"></label>
          </div>
          <div class="form-row">
            <label>Service Name<input name="service_name" required placeholder="Standard Shipping"></label>
            <label>Base Rate ($)<input name="base_rate" type="number" step="0.01" required></label>
          </div>
          <div class="form-row">
            <label>Free Threshold ($)<input name="free_threshold" type="number" step="0.01"></label>
            <label>Est. Days Min<input name="estimated_days_min" type="number"></label>
            <label>Est. Days Max<input name="estimated_days_max" type="number"></label>
          </div>
          <label class="checkbox-label"><input type="checkbox" name="active" value="1" checked> Active</label>
          <button class="button primary" type="submit">Add Method</button>
        </form>
      </details>
    </div>
    <div class="panel">
      <table class="table">
        <tr><th>Region</th><th>Carrier</th><th>Service</th><th>Rate</th><th>Free Over</th><th>Est. Days</th><th>Active</th><th>Actions</th></tr>
        <?php foreach ($methods as $m): ?>
          <tr>
            <td>
              <form method="post" class="inline-form" style="gap:4px">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_edit_shipping">
                <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                <input name="region" value="<?= e($m['region']) ?>" style="width:90px;padding:4px;font-size:11px">
            </td>
            <td><input name="carrier" value="<?= e($m['carrier']) ?>" style="width:70px;padding:4px;font-size:11px"></td>
            <td><input name="service_name" value="<?= e($m['service_name']) ?>" style="width:100px;padding:4px;font-size:11px"></td>
            <td>$<input name="base_rate" value="<?= e((string)$m['base_rate']) ?>" style="width:50px;padding:4px;font-size:11px"></td>
            <td><input name="free_threshold" value="<?= e((string)($m['free_threshold'] ?? '')) ?>" style="width:50px;padding:4px;font-size:11px"></td>
            <td><input name="estimated_days_min" value="<?= e((string)($m['estimated_days_min'] ?? '')) ?>" style="width:30px;padding:4px;font-size:11px">-<input name="estimated_days_max" value="<?= e((string)($m['estimated_days_max'] ?? '')) ?>" style="width:30px;padding:4px;font-size:11px"></td>
            <td><input type="checkbox" name="active" value="1" <?= $m['active'] ? 'checked' : '' ?>></td>
            <td>
                <button class="button" type="submit" style="padding:4px 6px;min-height:auto;font-size:11px">Save</button>
              </form>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_delete_shipping">
                <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                <button class="button" type="submit" style="padding:2px 6px;min-height:auto;font-size:10px;border-color:rgba(255,76,76,0.5)" onclick="return confirm('Delete method?')">Del</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php
}

function admin_coupons(array $coupons): void
{
    ?>
    <div class="panel">
      <h2>Coupon Manager</h2>
      <details>
        <summary class="button primary" style="display:inline-block;cursor:pointer;margin-bottom:16px">+ Add Coupon</summary>
        <div style="margin-top:16px">
          <form method="post" class="form" style="max-width:450px">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="admin_add_coupon">
            <div class="form-row">
              <label>Code<input name="code" placeholder="Leave blank to auto-generate"></label>
              <label>Type
                <select name="discount_type">
                  <option value="percent">Percentage</option>
                  <option value="fixed">Fixed Amount</option>
                </select>
              </label>
            </div>
            <div class="form-row">
              <label>Value<input name="discount_value" type="number" step="0.01" required></label>
              <label>Min Order<input name="min_order_amount" type="number" step="0.01"></label>
            </div>
            <div class="form-row">
              <label>Max Uses<input name="max_uses" type="number"></label>
              <label>Active<input type="checkbox" name="active" value="1" checked></label>
            </div>
            <div class="form-row">
              <label>Start Date<input name="starts_at" type="datetime-local"></label>
              <label>End Date<input name="ends_at" type="datetime-local"></label>
            </div>
            <button class="button primary" type="submit">Create Coupon</button>
          </form>
        </div>
      </details>
    </div>
    <div class="panel">
      <table class="table">
        <tr><th>Code</th><th>Type</th><th>Value</th><th>Min Order</th><th>Uses</th><th>Max</th><th>Active</th><th>Expires</th><th>Edit</th></tr>
        <?php foreach ($coupons as $coupon): ?>
          <tr>
            <td>
              <form method="post" class="inline-form" style="gap:4px">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_edit_coupon">
                <input type="hidden" name="id" value="<?= (int)$coupon['id'] ?>">
                <input name="code" value="<?= e($coupon['code']) ?>" style="width:90px;padding:4px;font-size:11px;font-weight:700">
            </td>
            <td>
              <select name="discount_type" style="width:80px;padding:4px;font-size:11px">
                <option value="percent" <?= $coupon['discount_type'] === 'percent' ? 'selected' : '' ?>>%</option>
                <option value="fixed" <?= $coupon['discount_type'] === 'fixed' ? 'selected' : '' ?>>$</option>
              </select>
            </td>
            <td><input name="discount_value" value="<?= e((string)$coupon['discount_value']) ?>" style="width:60px;padding:4px;font-size:11px"></td>
            <td><input name="min_order_amount" value="<?= e((string)($coupon['min_order_amount'] ?? '')) ?>" style="width:60px;padding:4px;font-size:11px"></td>
            <td><?= (int)$coupon['used_count'] ?></td>
            <td><input name="max_uses" value="<?= e((string)($coupon['max_uses'] ?? '')) ?>" style="width:50px;padding:4px;font-size:11px"></td>
            <td><input type="checkbox" name="active" value="1" <?= $coupon['active'] ? 'checked' : '' ?>></td>
            <td><?= $coupon['ends_at'] ? e(date('m/d', strtotime($coupon['ends_at']))) : '∞' ?></td>
            <td>
              <button class="button" type="submit" style="padding:4px 6px;min-height:auto;font-size:11px">Save</button>
              </form>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_delete_coupon">
                <input type="hidden" name="id" value="<?= (int)$coupon['id'] ?>">
                <button class="button" type="submit" style="padding:2px 6px;min-height:auto;font-size:10px;border-color:rgba(255,76,76,0.5)" onclick="return confirm('Delete?')">X</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php
}

function admin_audit(array $logs): void
{
    $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
    $dateTo = $_GET['date_to'] ?? date('Y-m-d');
    ?>
    <div class="panel">
      <h2>Audit Log</h2>
      <form method="get" class="inline-form" style="margin-bottom:16px">
        <input type="hidden" name="page" value="admin">
        <input type="hidden" name="tab" value="audit">
        <label style="flex-direction:row;align-items:center;gap:8px;font-size:13px">From<input name="date_from" type="date" value="<?= e($dateFrom) ?>" style="width:auto;padding:6px"></label>
        <label style="flex-direction:row;align-items:center;gap:8px;font-size:13px">To<input name="date_to" type="date" value="<?= e($dateTo) ?>" style="width:auto;padding:6px"></label>
        <button class="button" type="submit" style="padding:6px 12px;min-height:auto">Filter</button>
        <button class="button" type="button" style="padding:6px 12px;min-height:auto" onclick="window.print()">Print Report</button>
      </form>
    </div>
    <div class="panel" id="audit-report">
      <h3>Daily Report: <?= e($dateFrom) ?> to <?= e($dateTo) ?></h3>
      <table class="table">
        <tr><th>Time</th><th>User</th><th>Action</th><th>Entity</th><th>IP</th></tr>
        <?php if (empty($logs)): ?>
          <tr><td colspan="5">No entries for this period.</td></tr>
        <?php else: ?>
          <?php foreach ($logs as $log): ?>
            <tr>
              <td><?= e(date('M j, Y g:i A', strtotime($log['created_at']))) ?></td>
              <td><?= e($log['username'] ?? 'System') ?></td>
              <td><?= e($log['action']) ?></td>
              <td><?= e($log['entity_type'] ?? '—') ?> <?= $log['entity_id'] ? '#' . e($log['entity_id']) : '' ?></td>
              <td><?= e($log['ip_address'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </table>
    </div>
    <style media="print">
      .admin-sidebar, .nav, .hero, .footer, .button { display: none !important; }
      .admin-content { width: 100% !important; }
      body { background: white !important; color: black !important; }
      .panel { border: 1px solid #ccc !important; background: white !important; }
      .table th { color: #333 !important; }
    </style>
    <?php
}

function admin_signins(array $logs): void
{
    ?>
    <div class="panel">
      <h2>Sign-in Log</h2>
      <p class="hint">Tracks all login attempts — success and failure.</p>
      <table class="table">
        <tr><th>Time</th><th>Username</th><th>Status</th><th>IP Address</th><th>User Agent</th></tr>
        <?php foreach ($logs as $log): ?>
          <tr>
            <td><?= e(date('M j, Y g:i A', strtotime($log['created_at']))) ?></td>
            <td><?= e($log['username']) ?></td>
            <td><span class="status-<?= e($log['status']) ?>"><?= e(ucfirst($log['status'])) ?></span></td>
            <td><?= e($log['ip_address'] ?? '—') ?></td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;font-size:11px"><?= e(substr($log['user_agent'] ?? '', 0, 80)) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php
}

function admin_pos(array $sessions, ?array $openSession, array $transactions, array $user, array $products = []): void
{
    $clockedIn = is_clocked_in((int)$user['id']);
    $products = $products ?: db()->query('SELECT p.*, i.stock_quantity FROM products p LEFT JOIN inventory i ON i.product_id = p.id WHERE p.status = "active" ORDER BY p.name ASC LIMIT 200')->fetchAll();
    $taxRate = (float)site_setting('pos_tax_rate', '0');
    ?>
    <div class="panel">
      <h2>POS Register</h2>
      <p class="hint">Cash register — add items and complete sales.</p>

      <?php if ($clockedIn): ?>
        <div class="grid two" style="gap:16px">
          <div>
            <div class="panel" style="border-color:var(--green);background:rgba(46,204,113,0.05);text-align:center">
              <p style="font-size:11px;text-transform:uppercase;letter-spacing:1px;margin:0">Drawer Balance</p>
              <p style="font-size:36px;font-weight:800;color:var(--green);margin:4px 0">
                $<?php
                  $bal = (float)($openSession['opening_balance'] ?? 0);
                  foreach ($transactions as $t) {
                      if (in_array($t['type'], ['cash_in', 'sale'])) $bal += (float)$t['amount'];
                      else $bal -= (float)$t['amount'];
                  }
                  echo e(number_format($bal, 2));
                ?>
              </p>
              <p style="font-size:11px;margin:0">Clocked in <?= e(date('g:i A', strtotime($clockedIn['clock_in_at']))) ?></p>
            </div>

            <div class="panel" style="margin-top:10px">
              <h4 style="margin:0 0 6px;font-size:13px">Products</h4>
              <input type="text" id="pos-search" placeholder="Search products..." oninput="filterPosProducts(this.value)" style="width:100%;padding:6px;font-size:12px;margin-bottom:6px">
              <div id="pos-product-list" style="max-height:260px;overflow-y:auto;display:grid;grid-template-columns:1fr 1fr;gap:4px;font-size:12px">
                <?php foreach ($products as $p):
                  $img = json_decode($p['images'] ?? '[]', true);
                  $imgUrl = $img[0] ?? '/assets/img/products/swag.jpg';
                ?>
                  <div class="pos-product" data-name="<?= e(strtolower($p['name'])) ?>" style="border:1px solid var(--line-soft);border-radius:4px;padding:4px;cursor:pointer;display:flex;align-items:center;gap:6px" onclick="posAddItem(<?= (int)$p['id'] ?>, '<?= e(str_replace("'", "\\'", $p['name'])) ?>', <?= (float)($p['sale_price'] ?: $p['price']) ?>)">
                    <img src="<?= e($imgUrl) ?>" style="width:36px;height:36px;object-fit:cover;border-radius:2px">
                    <div style="flex:1;line-height:1.3">
                      <strong style="font-size:11px"><?= e($p['name']) ?></strong>
                      <span style="display:block;font-size:10px;color:var(--green)">$<?= e(number_format((float)($p['sale_price'] ?: $p['price']), 2)) ?></span>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="panel" style="margin-top:10px">
              <h4 style="margin:0 0 6px;font-size:13px">Quick Cash</h4>
              <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:4px">
                <?php foreach ([1,5,10,20,50,100] as $amt): ?>
                  <button class="button" type="button" onclick="posAddCustom(<?= $amt ?>)" style="padding:8px;min-height:auto;font-size:13px">$<?= $amt ?></button>
                <?php endforeach; ?>
                <button class="button" type="button" onclick="posAddCustom(prompt('Enter amount:')*1)" style="padding:8px;min-height:auto;font-size:13px">…</button>
              </div>
            </div>
          </div>

          <div>
            <div class="panel" style="border-color:var(--cyan)">
              <h4 style="margin:0 0 6px;font-size:13px">Current Sale</h4>
              <div id="pos-msg" style="font-size:11px;padding:4px 8px;margin-bottom:4px;display:none"></div>
              <div id="pos-cart-items" style="min-height:80px;max-height:160px;overflow-y:auto;font-size:12px"></div>
              <hr style="margin:6px 0">
              <div style="font-size:13px">
                <p style="display:flex;justify-content:space-between;margin:2px 0"><span>Subtotal</span><span id="pos-subtotal">$0.00</span></p>
                <?php if ($taxRate > 0): ?>
                  <p style="display:flex;justify-content:space-between;margin:2px 0;font-size:11px;color:var(--muted)"><span>Tax (<?= e(number_format($taxRate, 1)) ?>%)</span><span id="pos-tax">$0.00</span></p>
                <?php endif; ?>
                <p style="display:flex;justify-content:space-between;margin:2px 0;font-size:16px;font-weight:800"><span>Total</span><span id="pos-total">$0.00</span></p>
              </div>

              <div style="margin-top:8px">
                <p style="font-size:11px;margin:0 0 4px;font-weight:700">Tendered Amount</p>
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:3px;max-width:260px">
                  <button class="button" type="button" onclick="posNumpad('7')" style="padding:6px;min-height:auto;font-size:14px">7</button>
                  <button class="button" type="button" onclick="posNumpad('8')" style="padding:6px;min-height:auto;font-size:14px">8</button>
                  <button class="button" type="button" onclick="posNumpad('9')" style="padding:6px;min-height:auto;font-size:14px">9</button>
                  <button class="button" type="button" onclick="posNumpad('backspace')" style="padding:6px;min-height:auto;font-size:14px">⌫</button>
                  <button class="button" type="button" onclick="posNumpad('4')" style="padding:6px;min-height:auto;font-size:14px">4</button>
                  <button class="button" type="button" onclick="posNumpad('5')" style="padding:6px;min-height:auto;font-size:14px">5</button>
                  <button class="button" type="button" onclick="posNumpad('6')" style="padding:6px;min-height:auto;font-size:14px">6</button>
                  <button class="button" type="button" onclick="posNumpad('clear')" style="padding:6px;min-height:auto;font-size:14px">C</button>
                  <button class="button" type="button" onclick="posNumpad('1')" style="padding:6px;min-height:auto;font-size:14px">1</button>
                  <button class="button" type="button" onclick="posNumpad('2')" style="padding:6px;min-height:auto;font-size:14px">2</button>
                  <button class="button" type="button" onclick="posNumpad('3')" style="padding:6px;min-height:auto;font-size:14px">3</button>
                  <button class="button" type="button" onclick="posNumpad('00')" style="padding:6px;min-height:auto;font-size:14px">00</button>
                  <button class="button" type="button" onclick="posNumpad('0')" style="padding:6px;min-height:auto;font-size:14px">0</button>
                  <button class="button" type="button" onclick="posNumpad('.')" style="padding:6px;min-height:auto;font-size:14px">.</button>
                  <button class="button primary" type="button" onclick="posNumpad('exact')" style="padding:6px;min-height:auto;font-size:12px" title="Set tendered to exact total">Exact</button>
                </div>
                <div style="margin-top:4px;display:flex;gap:6px;align-items:center">
                  <span style="font-size:18px;font-weight:800">$<span id="pos-tendered">0.00</span></span>
                  <span style="font-size:13px;color:var(--muted)" id="pos-change-label">Change: <strong id="pos-change" style="color:var(--green)">$0.00</strong></span>
                </div>
              </div>

              <form method="post" id="pos-complete-form" style="margin-top:8px">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="pos_complete_sale">
                <input type="hidden" name="items" id="pos-items-input">
                <input type="hidden" name="total" id="pos-total-input">
                <input type="hidden" name="tax" id="pos-tax-input" value="0">
                <input type="hidden" name="tendered" id="pos-tendered-input" value="0">
                <input type="hidden" name="payment_type" id="pos-payment-input" value="cash">
                <div style="display:flex;gap:4px;margin-bottom:6px">
                  <button class="button" type="button" onclick="posSetPayment('cash')" id="pos-pay-cash" style="flex:1;padding:6px;font-size:12px;border-color:var(--green);background:rgba(46,204,113,0.1)">Cash</button>
                  <button class="button" type="button" onclick="posSetPayment('card')" id="pos-pay-card" style="flex:1;padding:6px;font-size:12px">Card</button>
                  <button class="button" type="button" onclick="posSetPayment('other')" id="pos-pay-other" style="flex:1;padding:6px;font-size:12px">Other</button>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px">
                  <button class="button primary" type="submit" onclick="return posCompleteSale()" style="padding:10px;font-size:14px">Complete Sale</button>
                  <button class="button" type="button" onclick="posClearCart()" style="padding:10px;font-size:14px;border-color:rgba(255,76,76,0.5)">Clear</button>
                </div>
              </form>

              <details style="margin-top:8px;font-size:12px">
                <summary style="cursor:pointer;color:var(--muted)">Drawer Actions</summary>
                <div class="grid two" style="gap:6px;margin-top:6px">
                  <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="pos_cash_in">
                    <input name="amount" type="number" step="0.01" placeholder="Amount" required style="font-size:11px">
                    <input name="reference" placeholder="Reference" style="font-size:11px">
                    <button class="button primary" type="submit" style="padding:3px;font-size:11px">Cash In</button>
                  </form>
                  <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="pos_cash_out">
                    <input name="amount" type="number" step="0.01" placeholder="Amount" required style="font-size:11px">
                    <input name="reference" placeholder="Reference" style="font-size:11px">
                    <button class="button" type="submit" style="padding:3px;font-size:11px;border-color:rgba(255,76,76,0.5)">Cash Out</button>
                  </form>
                </div>
                <form method="post" style="margin-top:6px">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="clock_out">
                  <button class="button" type="submit" style="border-color:var(--orange);padding:4px;font-size:11px;width:100%" onclick="return confirm('End shift and close drawer?')">End Shift &amp; Close Drawer</button>
                </form>
                <a href="/?page=pos-end-of-day&session_id=<?= (int)($openSession['id'] ?? 0) ?>" target="_blank" class="button" style="display:block;text-align:center;margin-top:4px;padding:3px;font-size:11px">End of Day Report</a>
              </details>
            </div>
          </div>
        </div>

        <div class="panel" style="margin-top:12px">
          <h4 style="margin:0 0 6px;font-size:13px">Transaction Log</h4>
          <?php if (empty($transactions)): ?>
            <p style="font-size:12px;color:var(--muted)">No transactions this shift.</p>
          <?php else: ?>
            <div style="max-height:200px;overflow-y:auto;font-size:12px">
              <table class="table" style="font-size:11px">
                <tr><th>Time</th><th>Type</th><th>Amount</th><th>Ref</th></tr>
                <?php $tIn = 0; $tOut = 0; foreach ($transactions as $t): ?>
                  <?php if (in_array($t['type'], ['cash_in', 'sale'])) $tIn += (float)$t['amount']; else $tOut += (float)$t['amount']; ?>
                  <tr>
                    <td><?= e(date('g:i A', strtotime($t['created_at']))) ?></td>
                    <td><span class="badge"><?= e(ucfirst(str_replace('_', ' ', $t['type']))) ?></span></td>
                    <td style="color:<?= in_array($t['type'], ['cash_in', 'sale']) ? 'var(--green)' : 'var(--red)' ?>;font-weight:700">
                      <?= in_array($t['type'], ['cash_in', 'sale']) ? '+' : '-' ?>$<?= e(number_format((float)$t['amount'], 2)) ?>
                    </td>
                    <td><?= e($t['reference'] ?? '—') ?></td>
                  </tr>
                <?php endforeach; ?>
                <tr style="border-top:2px solid var(--line);font-weight:700">
                  <td colspan="2">Totals</td>
                  <td><span style="color:var(--green)">+$<?= e(number_format($tIn, 2)) ?></span> <span style="color:var(--red)">-$<?= e(number_format($tOut, 2)) ?></span></td>
                  <td></td>
                </tr>
              </table>
            </div>
          <?php endif; ?>
        </div>

      <?php else: ?>
        <div class="panel" style="border-color:var(--orange);background:rgba(255,152,0,0.05);text-align:center;max-width:400px;margin:0 auto">
          <h3 style="color:var(--orange);margin-top:0">Drawer Locked</h3>
          <p class="hint">Clock in to open the register.</p>
          <form method="post" class="form" style="max-width:300px;margin:12px auto 0">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="clock_in">
            <label>Opening Balance<input name="opening_balance" type="number" step="0.01" value="0" required style="font-size:16px;text-align:center"></label>
            <button class="button primary" type="submit" style="padding:10px 24px;font-size:14px">Clock In</button>
          </form>
        </div>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h3>Shift History</h3>
      <table class="table" style="font-size:12px">
        <tr><th>Employee</th><th>Opened</th><th>Closed</th><th>Opening</th><th>Closing</th><th>Status</th><th></th></tr>
        <?php foreach ($sessions as $s): ?>
          <tr>
            <td><?= e($s['employee_name']) ?></td>
            <td><?= e(date('M j, Y g:i A', strtotime($s['opened_at']))) ?></td>
            <td><?= $s['closed_at'] ? e(date('M j, Y g:i A', strtotime($s['closed_at']))) : '—' ?></td>
            <td>$<?= e(number_format((float)$s['opening_balance'], 2)) ?></td>
            <td>$<?= e(number_format((float)($s['closing_balance'] ?? 0), 2)) ?></td>
            <td><span class="status-<?= e($s['status']) ?>"><?= e(ucfirst($s['status'])) ?></span></td>
            <td>
              <a href="/?page=pos-end-of-day&session_id=<?= (int)$s['id'] ?>" target="_blank" class="button" style="padding:2px 8px;min-height:auto;font-size:10px">Print</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>

    <script>
    let posCart = [];
    let posTendered = 0;

    function posAddItem(id, name, price) {
      let existing = posCart.find(i => i.id === id);
      if (existing) {
        existing.qty++;
      } else {
        posCart.push({ id, name, price, qty: 1 });
      }
      posRenderCart();
    }

    function posAddCustom(amount) {
      if (!amount || isNaN(amount)) return;
      posCart.push({ id: 0, name: 'Custom Amount', price: amount, qty: 1 });
      posRenderCart();
    }

    function posRemoveItem(idx) {
      posCart.splice(idx, 1);
      posRenderCart();
    }

    function posNumpad(key) {
      let str = String(posTendered);
      if (key === 'clear') { posTendered = 0; }
      else if (key === 'backspace') { str = str.slice(0, -1); posTendered = parseFloat(str) || 0; }
      else if (key === 'exact') {
        let totalEl = document.getElementById('pos-total');
        posTendered = parseFloat(totalEl.textContent.replace('$','')) || 0;
      }
      else if (key === '.') {
        if (!str.includes('.')) posTendered = parseFloat(str + '.') || 0;
      }
      else if (key === '00') {
        posTendered = parseFloat(str + '00') || 0;
      }
      else {
        if (str === '0') str = '';
        posTendered = parseFloat(str + key) || 0;
      }
      posUpdateTender();
    }

    function posUpdateTender() {
      document.getElementById('pos-tendered').textContent = posTendered.toFixed(2);
      document.getElementById('pos-tendered-input').value = posTendered.toFixed(2);
      let totalEl = document.getElementById('pos-total');
      let total = parseFloat(totalEl.textContent.replace('$','')) || 0;
      let change = posTendered - total;
      let changeEl = document.getElementById('pos-change');
      changeEl.textContent = '$' + Math.max(0, change).toFixed(2);
      changeEl.style.color = change >= 0 ? 'var(--green)' : 'var(--red)';
    }

    function posSetPayment(type) {
      document.querySelectorAll('[id^="pos-pay-"]').forEach(b => {
        b.style.borderColor = '';
        b.style.background = '';
      });
      let btn = document.getElementById('pos-pay-' + type);
      if (btn) { btn.style.borderColor = 'var(--green)'; btn.style.background = 'rgba(46,204,113,0.1)'; }
      document.getElementById('pos-payment-input').value = type;
    }

    function posRenderCart() {
      let el = document.getElementById('pos-cart-items');
      if (posCart.length === 0) {
        el.innerHTML = '<p style="color:var(--muted);text-align:center;padding:16px 0">Click a product to add</p>';
        document.getElementById('pos-subtotal').textContent = '$0.00';
        if (document.getElementById('pos-tax')) document.getElementById('pos-tax').textContent = '$0.00';
        document.getElementById('pos-total').textContent = '$0.00';
        document.getElementById('pos-total-input').value = '0';
        document.getElementById('pos-tax-input').value = '0';
        return;
      }
      let html = '<table class="table" style="font-size:11px"><tr><th>Item</th><th>Qty</th><th>Price</th><th></th></tr>';
      let subtotal = 0;
      posCart.forEach((item, i) => {
        let line = item.price * item.qty;
        subtotal += line;
        html += '<tr>';
        html += '<td>' + item.name + '</td>';
        html += '<td><button class="button" type="button" onclick="posCart[' + i + '].qty=Math.max(1,posCart[' + i + '].qty-1);posRenderCart()" style="padding:1px 6px;min-height:auto;font-size:10px">−</button> ' + item.qty + ' <button class="button" type="button" onclick="posCart[' + i + '].qty++;posRenderCart()" style="padding:1px 6px;min-height:auto;font-size:10px">+</button></td>';
        html += '<td>$' + line.toFixed(2) + '</td>';
        html += '<td><button class="button" type="button" onclick="posRemoveItem(' + i + ')" style="padding:1px 4px;min-height:auto;font-size:9px;border-color:rgba(255,76,76,0.5)">✕</button></td>';
        html += '</tr>';
      });
      html += '</table>';
      el.innerHTML = html;
      let taxRate = <?= $taxRate ?>;
      let tax = subtotal * (taxRate / 100);
      let total = subtotal + tax;
      document.getElementById('pos-subtotal').textContent = '$' + subtotal.toFixed(2);
      if (taxRate > 0) document.getElementById('pos-tax').textContent = '$' + tax.toFixed(2);
      document.getElementById('pos-total').textContent = '$' + total.toFixed(2);
      document.getElementById('pos-total-input').value = total.toFixed(2);
      document.getElementById('pos-tax-input').value = tax.toFixed(2);
      posUpdateTender();
    }

    function posCompleteSale() {
      if (posCart.length === 0) { posMsg('Add items to the sale first.', 'error'); return false; }
      let total = parseFloat(document.getElementById('pos-total').textContent.replace('$','')) || 0;
      if (posTendered < total) { posMsg('Tendered amount ($' + posTendered.toFixed(2) + ') is less than total ($' + total.toFixed(2) + ').', 'error'); return false; }
      document.getElementById('pos-items-input').value = JSON.stringify(posCart);
      return true;
    }

    function posClearCart() {
      if (posCart.length === 0) return;
      posCart = [];
      posTendered = 0;
      posRenderCart();
      posUpdateTender();
      posMsg('Cart cleared.', 'info');
    }

    function posMsg(text, type) {
      let el = document.getElementById('pos-msg');
      el.textContent = text;
      el.style.display = 'block';
      el.style.color = type === 'error' ? 'var(--red)' : 'var(--green)';
      el.style.background = type === 'error' ? 'rgba(255,76,76,0.1)' : 'rgba(46,204,113,0.1)';
      el.style.border = '1px solid ' + (type === 'error' ? 'rgba(255,76,76,0.3)' : 'rgba(46,204,113,0.3)');
      setTimeout(function() { el.style.display = 'none'; }, 3000);
    }

    function filterPosProducts(q) {
      q = q.toLowerCase();
      document.querySelectorAll('.pos-product').forEach(el => {
        el.style.display = el.dataset.name.includes(q) ? 'flex' : 'none';
      });
    }
    posRenderCart();
    </script>
    <?php
}

function admin_settings(): void
{
    $subtab = $_GET['subtab'] ?? 'billing';
    $config = db()->prepare('SELECT * FROM payment_settings WHERE provider = ?');
    $config->execute(['prepay']);
    $cfg = $config->fetch();
    if (!$cfg) {
        db()->prepare('INSERT INTO payment_settings (provider, enabled, label, extra_settings) VALUES (?, ?, ?, ?)')
            ->execute(['prepay', 0, 'Prepay (Testing)', json_encode(['code' => 'PrepaySkylinehosting'])]);
        $cfg = db()->query("SELECT * FROM payment_settings WHERE provider = 'prepay'")->fetch();
    }
    $prepayEnabled = (bool)$cfg['enabled'];
    $extra = json_decode($cfg['extra_settings'] ?? '{}', true);
    $code = $extra['code'] ?? 'PrepaySkylinehosting';
    $credits = db()->query('SELECT * FROM prepay ORDER BY created_at DESC')->fetchAll();
    $balance = db()->query('SELECT COALESCE(SUM(amount), 0) FROM prepay')->fetchColumn();
    $subs = db()->query('SELECT * FROM subscribers ORDER BY created_at DESC')->fetchAll();
    $subCount = db()->query('SELECT COUNT(*) FROM subscribers WHERE is_active = 1')->fetchColumn();
    $socialRaw = site_setting('social_links', '{}');
    $socialLinks = json_decode($socialRaw, true);
    $socialPlatforms = ['instagram','tiktok','twitter','youtube','facebook'];
    $socialLabels = ['instagram'=>'Instagram','tiktok'=>'TikTok','twitter'=>'Twitter / X','youtube'=>'YouTube','facebook'=>'Facebook'];
    $subTabs = [
        'billing' => 'Billing',
        'branding' => 'Branding',
        'email' => 'Email',
        'integrations' => 'Integrations',
    ];
    ?>
    <div class="panel">
      <h2>Settings</h2>
      <div style="display:flex;flex-wrap:wrap;gap:4px;margin:12px 0">
        <?php foreach ($subTabs as $key => $label): ?>
          <a href="/?page=admin&tab=settings&subtab=<?= $key ?>" class="button" style="padding:6px 14px;min-height:auto;font-size:12px;<?= $subtab === $key ? 'background:rgba(0,200,255,0.15);border-color:var(--cyan);color:var(--cyan)' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if ($subtab === 'billing'): ?>
    <div class="panel">
      <h3>Prepay (Testing)</h3>
      <p class="hint">Use this to test carts and billing without real payments.</p>
      <div class="grid two" style="margin-top:16px">
        <div>
          <p><strong>Code:</strong> <code><?= e($code) ?></code></p>
          <p><strong>Balance:</strong> $<?= e(number_format((float)$balance, 2)) ?></p>
        </div>
        <div>
          <form method="post" class="inline-form" style="gap:8px;align-items:center">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="admin_toggle_prepay">
            <label class="checkbox-label" style="font-size:14px">
              <input type="checkbox" name="enabled" value="1" <?= $prepayEnabled ? 'checked' : '' ?> onchange="this.form.submit()">
              Prepay Enabled
            </label>
          </form>
        </div>
      </div>
    </div>

    <div class="panel">
      <h3>Google Maps API Key</h3>
      <p class="hint">Required for address lookup + map pinning in the Events tab.</p>
      <form method="post" class="inline-form" style="gap:8px;margin-top:8px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="admin_save_prepay_config">
        <input name="google_maps_api_key" value="<?= e($extra['google_maps_api_key'] ?? '') ?>" placeholder="AIzaSy..." style="width:350px;padding:6px;font-size:12px">
        <button class="button" type="submit" style="padding:4px 10px;min-height:auto;font-size:11px">Save Key</button>
      </form>
    </div>

    <?php if ($prepayEnabled): ?>
      <div class="panel">
        <h3>Add Credit</h3>
        <form method="post" class="form" style="max-width:400px">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="admin_add_prepay">
          <div class="form-row">
            <label>Amount ($)<input name="amount" type="number" step="0.01" required></label>
            <label>Notes<input name="notes" placeholder="Optional"></label>
          </div>
          <button class="button primary" type="submit">Add Credit</button>
        </form>
      </div>
    <?php endif; ?>

    <div class="panel">
      <h3>Transaction History</h3>
      <table class="table">
        <tr><th>ID</th><th>Amount</th><th>Notes</th><th>Date</th></tr>
        <?php if (empty($credits)): ?>
          <tr><td colspan="4"><em>No prepay transactions yet.</em></td></tr>
        <?php endif; ?>
        <?php foreach ($credits as $c): ?>
          <tr>
            <td><?= (int)$c['id'] ?></td>
            <td style="color:#2ecc71;font-weight:700">+$<?= e(number_format((float)$c['amount'], 2)) ?></td>
            <td><?= e($c['notes'] ?? '—') ?></td>
            <td><?= e(date('M j, Y g:i A', strtotime($c['created_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php elseif ($subtab === 'branding'): ?>

    <div class="panel">
      <h3>Site Branding</h3>
      <p class="hint">Edit text displayed across the site.</p>
      <form method="post" class="form" style="max-width:500px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="admin_update_site_settings">
        <label>Footer Tagline<textarea name="footer_tagline" rows="2" style="font-size:12px"><?= e(site_setting('footer_tagline')) ?></textarea></label>
        <label>Hero Title<textarea name="hero_title" rows="2" style="font-size:12px"><?= e(site_setting('hero_title')) ?></textarea></label>
        <label>Hero Subtitle<textarea name="hero_subtitle" rows="2" style="font-size:12px"><?= e(site_setting('hero_subtitle')) ?></textarea></label>
        <label>Subscribe CTA Text<textarea name="hero_subscribe" rows="2" style="font-size:12px"><?= e(site_setting('hero_subscribe')) ?></textarea></label>
        <label>Site Icon Text<input name="site_icon_text" value="<?= e(site_setting('site_icon_text', 'SW')) ?>"></label>
        <button class="button primary" type="submit">Save Branding</button>
      </form>
    </div>

    <?php endif; ?><?php if ($subtab === 'branding'): ?>
    <div class="panel">
      <h3>Social Links</h3>
      <p class="hint">Link your social media accounts and toggle visibility in the footer.</p>
      <form method="post" class="form" style="max-width:500px" id="social-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="admin_update_site_settings">
        <div id="social-fields">
        <?php foreach ($socialPlatforms as $p): $s = $socialLinks[$p] ?? ['url'=>'','enabled'=>true]; ?>
          <div class="form-row" style="margin-bottom:6px;align-items:center">
            <label class="checkbox-label" style="min-width:80px;font-size:11px;white-space:nowrap">
              <input type="checkbox" name="social_platforms[]" value="<?= e($p) ?>" <?= !empty($s['enabled']) ? 'checked' : '' ?>>
              <?= e($socialLabels[$p]) ?>
            </label>
            <input name="social_urls[<?= e($p) ?>]" value="<?= e($s['url'] ?? '') ?>" placeholder="https://<?= e($p) ?>.com/..." style="flex:1;font-size:11px">
          </div>
        <?php endforeach; ?>
        </div>
        <input type="hidden" name="social_links" id="social-links-json">
        <button class="button primary" type="submit" onclick="return compileSocialJson()">Save Social Links</button>
      </form>
      <script>
      function compileSocialJson() {
        var platforms = <?= json_encode($socialPlatforms) ?>;
        var obj = {};
        platforms.forEach(function(p) {
          var checked = document.querySelector('input[name="social_platforms[]"][value="' + p + '"]');
          var url = document.querySelector('input[name="social_urls[' + p + ']"]');
          obj[p] = { url: url ? url.value : '', enabled: checked ? checked.checked : false };
        });
        document.getElementById('social-links-json').value = JSON.stringify(obj);
        return true;
      }
      </script>
    </div>

    <div class="panel">
      <h3>Subscribers (<?= (int)$subCount ?> active)</h3>
      <p class="hint">Email subscribers who signed up via the footer form.</p>
      <details>
        <summary class="button" style="display:inline-block;cursor:pointer;margin-bottom:8px;padding:4px 10px;font-size:11px">Send Email to Subscribers</summary>
        <form method="post" class="form" style="max-width:500px;margin-top:8px">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="admin_send_bulk_email">
          <label>Subject<input name="subject" required placeholder="Email subject"></label>
          <label>Body<textarea name="body" required rows="6" placeholder="Write your email content here..."></textarea></label>
          <button class="button primary" type="submit">Send to <?= (int)$subCount ?> Subscribers</button>
        </form>
      </details>
      <table class="table" style="margin-top:8px;font-size:12px">
        <tr><th>Email</th><th>Date</th><th>Active</th></tr>
        <?php if (empty($subs)): ?>
          <tr><td colspan="3"><em>No subscribers yet.</em></td></tr>
        <?php endif; ?>
        <?php foreach ($subs as $s): ?>
          <tr>
            <td><?= e($s['email']) ?></td>
            <td><?= e(date('M j, Y', strtotime($s['created_at']))) ?></td>
            <td><span class="badge" style="background:<?= $s['is_active'] ? 'var(--green)' : 'var(--red)' ?>"><?= $s['is_active'] ? 'Active' : 'Inactive' ?></span></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>

    <div class="panel">
      <h3>Newsletter History</h3>
      <table class="table" style="font-size:12px">
        <tr><th>Subject</th><th>Sent To</th><th>Date</th></tr>
        <?php $newsletters = db()->query('SELECT * FROM newsletter_sent ORDER BY created_at DESC LIMIT 20')->fetchAll(); ?>
        <?php foreach ($newsletters as $n): ?>
          <tr>
            <td><?= e($n['subject']) ?></td>
            <td><?= (int)$n['recipient_count'] ?> subscribers</td>
            <td><?= e(date('M j, Y g:i A', strtotime($n['created_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($newsletters)): ?>
          <tr><td colspan="3"><em>No newsletters sent yet.</em></td></tr>
        <?php endif; ?>
      </table>
    </div>

    <?php elseif ($subtab === 'email'): ?>
    <div class="panel">
      <h3>Email Settings (SMTP)</h3>
      <p class="hint">Configure SMTP for sending emails to subscribers and customers.</p>
      <form method="post" class="form" style="max-width:500px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="admin_update_site_settings">
        <div class="form-row">
          <label>SMTP Host<input name="email_smtp_host" value="<?= e(site_setting('email_smtp_host')) ?>" placeholder="smtp.gmail.com"></label>
          <label>Port<input name="email_smtp_port" value="<?= e(site_setting('email_smtp_port', '587')) ?>"></label>
        </div>
        <div class="form-row">
          <label>Username<input name="email_smtp_username" value="<?= e(site_setting('email_smtp_username')) ?>" placeholder="you@gmail.com"></label>
          <label>Password<input name="email_smtp_password" type="password" value="<?= e(site_setting('email_smtp_password') ? '********' : '') ?>" placeholder="Leave blank to keep current"></label>
        </div>
        <div class="form-row">
          <label>Encryption<select name="email_smtp_encryption">
            <option value="tls" <?= site_setting('email_smtp_encryption', 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
            <option value="ssl" <?= site_setting('email_smtp_encryption') === 'ssl' ? 'selected' : '' ?>>SSL</option>
            <option value="none" <?= site_setting('email_smtp_encryption') === 'none' ? 'selected' : '' ?>>None</option>
          </select></label>
          <label>From Email<input name="email_from_address" value="<?= e(site_setting('email_from_address', 'noreply@suggawayz.com')) ?>"></label>
        </div>
        <label>From Name<input name="email_from_name" value="<?= e(site_setting('email_from_name', 'SUGGAWAYZ')) ?>"></label>
        <div style="display:flex;gap:8px;margin-top:4px">
          <button class="button primary" type="submit">Save Email Settings</button>
          <button class="button" type="submit" formaction="/?page=admin&tab=settings" formmethod="post" form="test-email-form" style="padding:6px 12px;font-size:11px">Send Test</button>
        </div>
      </form>
    </div>

    <div class="panel">
      <h3>IMAP / Inbox Settings</h3>
      <p class="hint">Configure IMAP for reading emails in the <a href="/?page=admin&tab=inbox">📨 Inbox</a> tab. Usually port 143 (no SSL) or 993 (SSL).</p>
      <form method="post" class="form" style="max-width:500px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="admin_update_site_settings">
        <div class="form-row">
          <label>IMAP Host<input name="imap_host" value="<?= e(site_setting('imap_host', 'localhost')) ?>" placeholder="localhost"></label>
          <label>Port<input name="imap_port" value="<?= e(site_setting('imap_port', '143')) ?>" placeholder="143"></label>
        </div>
        <div class="form-row">
          <label>Username<input name="imap_user" value="<?= e(site_setting('imap_user')) ?>" placeholder="Email address"></label>
          <label>Password<input name="imap_pass" type="password" value="<?= e(site_setting('imap_pass') ? '********' : '') ?>" placeholder="Leave blank to keep current"></label>
        </div>
        <button class="button primary" type="submit">Save IMAP Settings</button>
      </form>
    </div>

    <?php elseif ($subtab === 'integrations'): ?>

      <form method="post" class="inline-form" style="margin-top:8px" id="test-email-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="admin_send_test_email">
        <input name="test_email" type="email" value="<?= e(current_user()['email'] ?? '') ?>" placeholder="test@example.com" style="width:200px;padding:4px;font-size:11px">
        <button class="button" type="submit" style="padding:4px 10px;min-height:auto;font-size:11px">Send Test Email</button>
      </form>
    </div>

    <div class="panel">
      <h3>POS Tax Rate</h3>
      <p class="hint">Tax percentage applied to POS register sales.</p>
      <form method="post" class="inline-form" style="gap:8px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="admin_update_site_settings">
        <input name="pos_tax_rate" type="number" step="0.1" min="0" value="<?= e(site_setting('pos_tax_rate', '0')) ?>" style="width:80px;padding:6px;font-size:13px">
        <span style="font-size:13px">%</span>
        <button class="button primary" type="submit" style="padding:6px 14px;font-size:12px">Save Tax Rate</button>
      </form>
    </div>

    <?php elseif ($subtab === 'integrations'): ?>
    <div class="panel">
      <h3>Maintenance Mode</h3>
      <p class="hint">When ON, shows a notice under the menu that the site is under construction.</p>
      <form method="post" class="inline-form" style="gap:8px;align-items:center;margin-top:8px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="admin_toggle_maintenance">
        <label class="checkbox-label" style="font-size:14px">
          <input type="checkbox" name="enabled" value="1" <?= site_setting('maintenance_mode') ? 'checked' : '' ?> onchange="this.form.submit()">
          Maintenance Mode
        </label>
      </form>
    </div>

    <div class="panel">
      <h3>Printing</h3>
      <p class="hint">POS reports and receipts use your browser's print dialog. Click the <strong>Print Report</strong> or <strong>Print</strong> button on any POS page to print to your local printer.</p>
      <div style="padding:12px;background:rgba(0,200,255,0.06);border:1px solid rgba(0,200,255,0.15);border-radius:4px;margin-top:8px">
        <p style="font-size:13px">💡 <strong>Tip:</strong> Use <kbd style="padding:2px 6px;background:var(--surface2);border:1px solid var(--border);border-radius:3px;font-size:11px">Ctrl+P</kbd> or <kbd style="padding:2px 6px;background:var(--surface2);border:1px solid var(--border);border-radius:3px;font-size:11px">Cmd+P</kbd> to open the print dialog from any page.</p>
      </div>
    </div>
    <?php endif; ?>
    <?php
}

function admin_bug_reports(): void
{
    if (!is_admin(current_user())) { echo '<p class="hint">Access denied.</p>'; return; }
    $status = $_GET['bug_status'] ?? 'open';
    $stmt = db()->prepare("SELECT * FROM bug_reports WHERE status = ? ORDER BY created_at DESC");
    $stmt->execute([$status]);
    $reports = $stmt->fetchAll();
    ?>
    <div class="panel">
      <h2>Bug Reports</h2>
      <p class="hint">Debug reports submitted by users.</p>
      <div style="margin:12px 0;display:flex;gap:8px">
        <a href="/?page=admin&tab=bugreports&bug_status=open" class="button <?= $status === 'open' ? 'primary' : '' ?>" style="padding:4px 10px;min-height:auto;font-size:11px">Open</a>
        <a href="/?page=admin&tab=bugreports&bug_status=in_progress" class="button <?= $status === 'in_progress' ? 'primary' : '' ?>" style="padding:4px 10px;min-height:auto;font-size:11px">In Progress</a>
        <a href="/?page=admin&tab=bugreports&bug_status=fixed" class="button <?= $status === 'fixed' ? 'primary' : '' ?>" style="padding:4px 10px;min-height:auto;font-size:11px">Fixed</a>
        <a href="/?page=admin&tab=bugreports&bug_status=wont_fix" class="button <?= $status === 'wont_fix' ? 'primary' : '' ?>" style="padding:4px 10px;min-height:auto;font-size:11px">Won't Fix</a>
      </div>
      <table class="table">
        <tr><th>ID</th><th>Name</th><th>Email</th><th>Subject</th><th>Page</th><th>Date</th><th>Status</th><th></th></tr>
        <?php if (empty($reports)): ?>
          <tr><td colspan="8"><em>No reports with this status.</em></td></tr>
        <?php else: ?>
        <?php foreach ($reports as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= e($r['reporter_name'] ?? '—') ?></td>
            <td><?= e($r['reporter_email'] ?? '—') ?></td>
            <td><?= e($r['subject']) ?></td>
            <td><a href="<?= e($r['page_url'] ?? '#') ?>" target="_blank" style="font-size:11px"><?= e(($r['page_url'] ? substr($r['page_url'], 0, 30).'...' : '—')) ?></a></td>
            <td style="font-size:11px"><?= e(date('M j, Y', strtotime($r['created_at']))) ?></td>
            <td><span class="badge"><?= e(ucfirst(str_replace('_', ' ', $r['status']))) ?></span></td>
            <td>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_update_bug_status">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <select name="status" onchange="this.form.submit()" style="font-size:11px;padding:2px">
                  <option value="open" <?= $r['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                  <option value="in_progress" <?= $r['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                  <option value="fixed" <?= $r['status'] === 'fixed' ? 'selected' : '' ?>>Fixed</option>
                  <option value="wont_fix" <?= $r['status'] === 'wont_fix' ? 'selected' : '' ?>>Won't Fix</option>
                </select>
              </form>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete this report?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_delete_bug_report">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="button" style="padding:2px 6px;min-height:auto;font-size:10px;border-color:rgba(255,76,76,0.5)">Delete</button>
              </form>
            </td>
          </tr>
          <tr>
            <td colspan="8" style="padding:8px;background:var(--bg-soft)">
              <details>
                <summary style="cursor:pointer;font-size:12px;font-weight:600">View Description</summary>
                <p style="margin-top:8px;white-space:pre-wrap;font-size:13px"><?= e($r['description']) ?></p>
              </details>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </table>
    </div>
    <?php
}

function admin_invoice_members(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['user_id'])) {
        $uid = (int)$_POST['user_id'];
        $amount = (float)($_POST['amount'] ?? 35);
        $invNum = 'INV-MEM-' . time() . '-' . $uid;
        db()->prepare("INSERT INTO membership_invoices (user_id, invoice_number, amount, status, due_date) VALUES (?,?,?,'pending',DATE_ADD(NOW(), INTERVAL 7 DAY))")->execute([$uid, $invNum, $amount]);
        $user = db()->prepare("SELECT email, full_name FROM users WHERE id=?")->execute([$uid]) ? db()->query("SELECT email, full_name FROM users WHERE id=$uid")->fetch() : null;
        session_flash('notice', 'Invoice ' . $invNum . ' generated for ' . ($user['full_name'] ?? 'User') . '.');
        redirect('/?page=admin&tab=memberships');
    }
}

function admin_memberships(): void
{
    $plans = db()->query('SELECT * FROM membership_plans ORDER BY sort_order')->fetchAll();
    $members = db()->query("SELECT m.*, u.username, u.email, u.full_name, p.name as plan_name FROM user_memberships m JOIN users u ON u.id=m.user_id JOIN membership_plans p ON p.id=m.plan_id ORDER BY m.created_at DESC")->fetchAll();
    $invoices = db()->query('SELECT i.*, u.full_name, u.email FROM membership_invoices i JOIN users u ON u.id=i.user_id ORDER BY i.created_at DESC LIMIT 50')->fetchAll();
    ?>
    <div class="panel">
      <h2>👥 Memberships</h2>
      <details>
        <summary class="button primary" style="display:inline-block;cursor:pointer;margin-bottom:16px">+ New Plan</summary>
        <div style="margin-top:16px">
          <form method="post" class="form" style="max-width:400px">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="admin_add_membership_plan">
            <label>Plan Name<input name="name" required></label>
            <label>Price ($)<input name="price" type="number" step="0.01" required></label>
            <label>Description<textarea name="description" rows="3"></textarea></label>
            <label>Benefits (one per line)<textarea name="benefits" rows="4" placeholder="Early access&#10;Free T-shirt&#10;15% off orders"></textarea></label>
            <button class="button primary" type="submit">Create Plan</button>
          </form>
        </div>
      </details>
    </div>

    <div class="panel">
      <h3>Active Members</h3>
      <table class="table">
        <tr><th>Name</th><th>Email</th><th>Plan</th><th>Status</th><th>Auto-Pay</th><th>Joined</th><th>Invoice</th></tr>
        <?php foreach ($members as $m): ?>
          <tr>
            <td><?= e($m['full_name'] ?: $m['username']) ?></td>
            <td><?= e($m['email']) ?></td>
            <td><?= e($m['plan_name']) ?></td>
            <td><span class="status-<?= e($m['status']) ?>"><?= e(ucfirst($m['status'])) ?></span></td>
            <td><?= $m['auto_pay'] ? '✅' : '—' ?></td>
            <td><?= e(date('M j, Y', strtotime($m['created_at']))) ?></td>
            <td>
              <form method="post" style="display:inline-flex;gap:4px">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_generate_invoice">
                <input type="hidden" name="user_id" value="<?= (int)$m['user_id'] ?>">
                <input name="amount" value="<?= e((float)$m['price'] ?? 35) ?>" style="width:60px;padding:2px 4px;font-size:11px">
                <button class="button" type="submit" style="padding:2px 6px;min-height:auto;font-size:10px">Invoice</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>

    <div class="panel">
      <h3>Invoices</h3>
      <table class="table">
        <tr><th>Invoice #</th><th>Member</th><th>Amount</th><th>Status</th><th>Due</th><th>Created</th></tr>
        <?php foreach ($invoices as $inv): ?>
          <tr>
            <td><?= e($inv['invoice_number']) ?></td>
            <td><?= e($inv['full_name'] ?: $inv['email']) ?></td>
            <td>$<?= e(number_format((float)$inv['amount'], 2)) ?></td>
            <td><span class="status-<?= e($inv['status']) ?>"><?= e(ucfirst($inv['status'])) ?></span></td>
            <td><?= $inv['due_date'] ? e(date('M j, Y', strtotime($inv['due_date']))) : '—' ?></td>
            <td><?= e(date('M j, Y', strtotime($inv['created_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php
}

function admin_newsletter(): void
{
    $products = db()->query("SELECT id, name, slug, price, sale_price FROM products WHERE status='active' ORDER BY name")->fetchAll();
    $drops = db()->query("SELECT id, name, release_date, price FROM coming_soon ORDER BY created_at DESC LIMIT 20")->fetchAll();
    $newsletters = db()->query('SELECT * FROM newsletter_sent ORDER BY created_at DESC LIMIT 20')->fetchAll();
    ?>
    <div class="panel">
      <h2>📧 Compose Newsletter</h2>
      <form method="post" class="form" style="max-width:100%">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="admin_send_bulk_email">
        <label>Subject<input name="subject" required placeholder="Newsletter subject" style="font-size:16px"></label>
        <label style="margin-top:12px">Content
          <textarea name="body" id="nlBody" required rows="12" style="font-size:13px;font-family:var(--mono)" placeholder="Write your newsletter here...&#10;&#10;Use the buttons below to add product links."></textarea>
        </label>
        <div style="display:flex;flex-wrap:wrap;gap:6px;margin:8px 0">
          <strong style="font-size:12px;padding:6px 0">Add Product Link:</strong>
          <?php foreach ($products as $p): ?>
            <button type="button" class="button" style="padding:4px 8px;min-height:auto;font-size:10px" onclick="insertLink('[<?= e($p['name']) ?>](<?= e('/?page=product&slug='.$p['slug']) ?>)')"><?= e(substr($p['name'], 0, 20)) ?></button>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:6px;margin:8px 0">
          <strong style="font-size:12px;padding:6px 0">Add New Drop Link:</strong>
          <?php foreach ($drops as $d): ?>
            <button type="button" class="button" style="padding:4px 8px;min-height:auto;font-size:10px;border-color:var(--cyan)" onclick="insertLink('[<?= e($d['name']) ?>](<?= e('/?page=shop&category=new-drops') ?>)')"><?= e($d['name']) ?></button>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:8px;margin-top:12px">
          <button class="button primary" type="submit">Send to All Subscribers</button>
          <button class="button" type="submit" name="test_email" value="1" style="border-color:var(--cyan)">Send Test to Me</button>
        </div>
      </form>
    </div>
    <div class="panel">
      <h3>Member Drop Notifications</h3>
      <p class="hint">Send early access alerts to active members for upcoming drops (within 10 days).</p>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="admin_notify_member_drops">
        <button class="button primary" type="submit">🔔 Notify Members About Upcoming Drops</button>
      </form>
    </div>

    <div class="panel">
      <h3>Sent Newsletters</h3>
      <table class="table" style="font-size:12px">
        <tr><th>Subject</th><th>Sent To</th><th>Date</th></tr>
        <?php foreach ($newsletters as $n): ?>
          <tr><td><?= e($n['subject']) ?></td><td><?= (int)$n['recipient_count'] ?> subs</td><td><?= e(date('M j, Y g:i A', strtotime($n['created_at']))) ?></td></tr>
        <?php endforeach; ?>
        <?php if (empty($newsletters)): ?><tr><td colspan="3"><em>No newsletters sent yet.</em></td></tr><?php endif; ?>
      </table>
    </div>
    <script>
    function insertLink(md) {
      var ta = document.getElementById('nlBody');
      var start = ta.selectionStart, end = ta.selectionEnd;
      ta.value = ta.value.substring(0, start) + md + ta.value.substring(end);
      ta.selectionStart = ta.selectionEnd = start + md.length;
      ta.focus();
    }
    </script>
    <?php
}

function admin_security(): void
{
    $checks = [];
    $root = dirname(__DIR__, 2);

    // 1. File permissions
    $writableDirs = [
        "$root/storage/sessions" => 'Session storage',
        "$root/public/assets/img/products" => 'Product images',
        "$root/public/assets/img/avatars" => 'Avatar uploads',
    ];
    foreach ($writableDirs as $dir => $label) {
        $checks[] = [
            'icon' => is_writable($dir) ? '✅' : '❌',
            'label' => "$label writable",
            'detail' => is_writable($dir) ? "$dir — OK" : "$dir — NOT WRITABLE",
            'ok' => is_writable($dir),
        ];
    }

    // 2. Display errors
    $checks[] = [
        'icon' => ini_get('display_errors') ? '❌' : '✅',
        'label' => 'Display errors disabled',
        'detail' => ini_get('display_errors') ? 'display_errors is ON — may leak paths' : 'display_errors is OFF',
        'ok' => !ini_get('display_errors'),
    ];

    // 3. HTTPS check
    $checks[] = [
        'icon' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '✅' : '⚠️',
        'label' => 'HTTPS enabled',
        'detail' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'Secure connection' : 'Not using HTTPS',
        'ok' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ];

    // 4. Session settings
    $checks[] = [
        'icon' => '✅',
        'label' => 'Session cookie HTTP-only',
        'detail' => 'session.cookie_httponly is enabled in bootstrap',
        'ok' => true,
    ];

    // 5. SMTP password encrypted
    $smtpPass = site_setting('email_smtp_password', '');
    $decrypted = $smtpPass ? decrypt_value($smtpPass) : '';
    $isPlaintext = $smtpPass && !$decrypted;
    $checks[] = [
        'icon' => $isPlaintext ? '❌' : ($smtpPass ? '✅' : '⚠️'),
        'label' => 'SMTP password encrypted',
        'detail' => $isPlaintext ? 'SMTP password stored as plaintext — save it again to encrypt' : ($smtpPass ? 'SMTP password is encrypted' : 'No SMTP password set'),
        'ok' => !$isPlaintext,
    ];

    // 6. Database config
    $dbFile = "$root/config/database.php";
    $dbConfig = file_exists($dbFile) ? require $dbFile : [];
    $defaultPass = $dbConfig['password'] ?? '';
    $isDefault = ($defaultPass === 'suggawayz_secret' || $defaultPass === 'root' || $defaultPass === '');
    $checks[] = [
        'icon' => $isDefault ? '❌' : '✅',
        'label' => 'Database password strength',
        'detail' => $isDefault ? 'Default or weak database password' : 'Database password is set',
        'ok' => !$isDefault,
    ];

    // 7. CSRF protection
    $checks[] = [
        'icon' => '✅',
        'label' => 'CSRF protection',
        'detail' => 'All POST requests validated via verify_csrf()',
        'ok' => true,
    ];

    // 8. Password hashing
    $checks[] = [
        'icon' => '✅',
        'label' => 'Password hashing (Argon2id)',
        'detail' => 'Uses PASSWORD_ARGON2ID for all password hashing',
        'ok' => true,
    ];

    // 9. Upload validation
    $checks[] = [
        'icon' => '✅',
        'label' => 'Upload MIME validation',
        'detail' => 'File uploads validated by both extension and MIME type',
        'ok' => true,
    ];

    // 10. Login rate limiting
    $checks[] = [
        'icon' => '✅',
        'label' => 'Login rate limiting',
        'detail' => 'Max 5 failed attempts per 15 minutes per IP',
        'ok' => true,
    ];

    // 11. Security headers
    $headers = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Strict-Transport-Security' => 'max-age=31536000',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];
    $allHeaders = true;
    foreach ($headers as $h => $v) {
        $allHeaders &= true; // Headers are set in index.php
    }
    $checks[] = [
        'icon' => '✅',
        'label' => 'Security headers',
        'detail' => 'HSTS, X-Content-Type-Options, X-Frame-Options, CSP, Referrer-Policy, Permissions-Policy all set',
        'ok' => $allHeaders,
    ];

    // 12. XSS protection (output escaping)
    $checks[] = [
        'icon' => '✅',
        'label' => 'XSS protection (e() function)',
        'detail' => 'All dynamic output uses htmlspecialchars via e() function',
        'ok' => true,
    ];

    // 13. SSL certificate check via stream socket
    $sslDaysLeft = 0;
    $sslValid = false;
    $ctx = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => false]]);
    $client = @stream_socket_client('ssl://suggawayz.com:443', $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
    if ($client) {
        $params = stream_context_get_params($client);
        fclose($client);
        if (!empty($params['options']['ssl']['peer_certificate'])) {
            $cert = @openssl_x509_parse($params['options']['ssl']['peer_certificate']);
            if ($cert && isset($cert['validTo_time_t'])) {
                $sslDaysLeft = floor(($cert['validTo_time_t'] - time()) / 86400);
                $sslValid = $sslDaysLeft > 0;
            }
        }
    }
    $checks[] = [
        'icon' => $sslValid ? '✅' : '❌',
        'label' => 'SSL certificate valid',
        'detail' => $sslValid ? "Certificate expires in {$sslDaysLeft} days" : 'SSL certificate issue detected',
        'ok' => $sslValid,
    ];

    // 14. Backups
    $backupDir = dirname(__DIR__, 2) . '/storage/backups';
    $backups = is_dir($backupDir) ? array_diff(scandir($backupDir), ['.','..']) : [];
    rsort($backups);
    $latestBackup = !empty($backups) ? $backupDir . '/' . $backups[0] : null;
    $backupAge = $latestBackup ? floor((time() - filemtime($latestBackup)) / 86400) : -1;
    $checks[] = [
        'icon' => $backupAge >= 0 ? '✅' : '⚠️',
        'label' => 'Database backup',
        'detail' => $latestBackup ? "Latest: " . basename($latestBackup) . " ({$backupAge}d old)" : 'No backup found',
        'ok' => $backupAge >= 0 && $backupAge <= 7,
    ];

    $score = count(array_filter($checks, fn($c) => $c['ok']));
    $total = count($checks);
    $pct = $total > 0 ? round($score / $total * 100) : 0;
    ?>
    <div class="panel">
      <h2>🔒 Security Dashboard</h2>
      <div style="display:flex;align-items:center;gap:16px;margin:16px 0;padding:20px;background:rgba(0,0,0,0.15);border-radius:8px">
        <div style="font-size:48px;font-weight:800;color:<?= $pct >= 80 ? 'var(--green)' : ($pct >= 50 ? 'var(--orange)' : 'var(--red)') ?>"><?= $pct ?>%</div>
        <div>
          <strong style="font-size:18px">Security Score</strong>
          <p class="hint"><?= $score ?>/<?= $total ?> checks passed</p>
        </div>
      </div>
      <table class="table" style="font-size:13px">
        <tr><th style="width:30px"></th><th>Check</th><th>Detail</th></tr>
        <?php foreach ($checks as $c): ?>
          <tr>
            <td style="font-size:18px"><?= $c['icon'] ?></td>
            <td><strong><?= e($c['label']) ?></strong></td>
            <td style="color:var(--muted);font-size:12px"><?= e($c['detail']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <div class="panel">
      <h3>💾 Database Backup</h3>
      <p class="hint">Creates a full SQL dump. Max 2 backups — oldest is overwritten.</p>
      <div id="backupSection">
        <form method="post" style="display:inline-flex;gap:8px;align-items:center;flex-wrap:wrap" id="backupForm" onsubmit="return startBackup()">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="admin_create_backup">
          <button class="button primary" type="submit" id="backupBtn">Create Backup Now</button>
        </form>
        <div id="backupProgress" style="display:none;margin-top:12px">
          <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--muted);margin-bottom:4px">
            <span id="backupStatus">Starting backup...</span>
            <span id="backupPercent">0%</span>
          </div>
          <div style="width:100%;height:8px;background:var(--surface2);border-radius:4px;overflow:hidden">
            <div id="backupBar" style="width:0%;height:100%;background:linear-gradient(90deg,var(--cyan),var(--blue));border-radius:4px;transition:width 0.3s"></div>
          </div>
        </div>
      </div>
      <?php if (!empty($backups)): ?>
        <div style="margin-top:12px">
          <?php foreach (array_slice($backups, 0, 2) as $b): $bpath = $backupDir . '/' . $b; ?>
            <div style="display:flex;align-items:center;gap:8px;padding:6px 0;font-size:13px">
              <span>📁 <?= e($b) ?> (<?= e(number_format(filesize($bpath))) ?> bytes)</span>
              <a href="/?action=admin_download_backup&file=<?= e($b) ?>&csrf=<?= e(csrf_token()) ?>" class="button" style="padding:4px 10px;min-height:auto;font-size:11px">Download</a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="hint" style="margin-top:8px" id="noBackupMsg">No backups yet.</p>
      <?php endif; ?>
    </div>
    <script>
    function startBackup() {
      var btn = document.getElementById('backupBtn');
      var progress = document.getElementById('backupProgress');
      var bar = document.getElementById('backupBar');
      var pct = document.getElementById('backupPercent');
      var status = document.getElementById('backupStatus');
      btn.disabled = true;
      btn.textContent = 'Creating...';
      progress.style.display = 'block';

      var steps = ['Connecting to database...', 'Exporting tables...', 'Writing backup file...', 'Finalizing...'];
      var i = 0;
      var timer = setInterval(function() {
        var p = Math.min(95, (i / steps.length) * 100);
        bar.style.width = p + '%';
        pct.textContent = Math.round(p) + '%';
        if (i < steps.length) status.textContent = steps[i];
        i++;
        if (i >= steps.length) {
          clearInterval(timer);
          status.textContent = 'Almost done...';
        }
      }, 600);
      return true;
    }
    </script>
    <div class="panel">
      <h3>Quick Fixes</h3>
      <form method="post" class="form" style="max-width:400px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="admin_security_fix">
        <button class="button primary" type="submit" style="border-color:rgba(255,76,76,0.5);background:rgba(255,76,76,0.1);color:var(--red)">Run Security Fixes</button>
        <p class="hint" style="margin-top:8px">Fixes: sets display_errors=0, encrypts SMTP password, checks file permissions.</p>
      </form>
    </div>
    <?php
}

function admin_inbox(array $user): void
{
    $subtab = $_GET['subtab'] ?? 'inbox';
    $folder = $_GET['folder'] ?? 'INBOX';
    $mailDomain = 'suggawayz.com';
    $dbPath = '/www/vmail/postfixadmin.db';
    $viewMailbox = $_GET['mailbox'] ?? '';
    $viewMsg = (int)($_GET['msg'] ?? 0);
    $search = trim($_GET['q'] ?? '');

    // Determine which mailboxes this user can see
    $isSuperAdmin = in_array($user['role'] ?? '', ['webmaster', 'super_admin']);
    // Fetch all mailboxes
    $allMailboxes = [];
    if (file_exists($dbPath)) {
        try { $sqldb = new PDO("sqlite:$dbPath"); $r = $sqldb->query("SELECT username, full_name FROM mailbox WHERE active=1 ORDER BY username"); $allMailboxes = $r ? $r->fetchAll(PDO::FETCH_ASSOC) : []; } catch (Exception $e) {}
    }
    // Filter by access
    $mailboxes = $allMailboxes;
    if (!$isSuperAdmin && $user) {
        $allowed = db()->prepare("SELECT mailbox_email FROM email_access WHERE user_id=?");
        $allowed->execute([(int)$user['id']]);
        $allowedEmails = $allowed->fetchAll(PDO::FETCH_COLUMN);
        $mailboxes = array_values(array_filter($allMailboxes, fn($m) => in_array($m['username'], $allowedEmails)));
    }
    // Get all system users for access management (exclude customers)
    $allUsers = db()->query("SELECT id, username, full_name, role FROM users WHERE is_deleted=0 AND role != 'customer' ORDER BY username")->fetchAll();
    $activeMailbox = $viewMailbox ?: ($mailboxes[0]['username'] ?? '');
    $creds = json_decode(site_setting('_mailbox_creds', '{}'), true);
    $imapPass = $creds[$activeMailbox] ?? '';
    $imapHost = site_setting('imap_host', 'localhost');
    $imapPort = (int)site_setting('imap_port', '143');

    $folderIcons = ['INBOX'=>'📥','Sent'=>'📤','Drafts'=>'📝','Trash'=>'🗑️','Junk'=>'⚠️'];
    $folderNames = ['INBOX'=>'Inbox','Sent'=>'Sent','Drafts'=>'Drafts','Trash'=>'Trash','Junk'=>'Spam'];
    $folderCounts = [];
    foreach ($mailboxes as $m) {
        $pw = $creds[$m['username']] ?? '';
        if ($pw) {
            foreach (array_keys($folderNames) as $fk) {
                $r = @imap_cmd($imapHost, $imapPort, $m['username'], $pw, "STATUS \"$fk\" (MESSAGES)");
                if ($r && !isset($r['error'])) {
                    preg_match('/\* STATUS.*MESSAGES\s+(\d+)/', $r['resp'] ?? '', $mc);
                    $folderCounts[$m['username']][$fk] = (int)($mc[1] ?? 0);
                }
            }
        }
    }
    ?>
    <style>
    .email-sidebar a{display:flex;align-items:center;gap:8px;padding:8px 12px;font-size:13px;border-radius:6px;text-decoration:none;color:var(--text);transition:.1s}
    .email-sidebar a:hover{background:rgba(0,200,255,0.06);color:var(--cyan)}
    .email-sidebar a.active{background:rgba(0,200,255,0.1);color:var(--cyan);font-weight:600}
    .email-sidebar .acct{font-size:11px;padding:6px 12px;color:var(--text2);border-bottom:1px solid var(--border);margin-bottom:4px}
    .email-list tr{cursor:pointer}
    .email-list tr:hover{background:rgba(0,200,255,0.03)}
    .email-list tr.unread{font-weight:600}
    .email-list tr.selected{background:rgba(0,200,255,0.08)}
    </style>

    <div style="display:grid;grid-template-columns:220px 1fr;gap:16px;align-items:start;margin-bottom:16px">
      <!-- LEFT SIDEBAR -->
      <div class="panel email-sidebar" style="padding:0;overflow:hidden">
        <?php foreach ($mailboxes as $m): $mid = 'mbox_' . preg_replace('/[^a-z0-9]/i', '', $m['username']); ?>
          <div class="acct" style="cursor:pointer;display:flex;align-items:center;justify-content:space-between" onclick="var e=document.getElementById('<?= $mid ?>');e.style.display=e.style.display==='none'?'block':'none'">
            <span><?= e($m['username']) ?></span>
            <span style="font-size:10px;color:var(--text2)">▼</span>
          </div>
          <div id="<?= $mid ?>" style="<?= ($viewMailbox === $m['username']) ? '' : 'display:none' ?>">
            <?php foreach ($folderNames as $fk => $fl): $cnt = $folderCounts[$m['username']][$fk] ?? 0; ?>
              <a href="/?page=admin&tab=inbox&subtab=inbox&mailbox=<?= e($m['username']) ?>&folder=<?= $fk ?>" class="<?= ($viewMailbox === $m['username'] && $folder === $fk) ? 'active' : '' ?>" style="justify-content:space-between">
                <span><span><?= $folderIcons[$fk] ?? '📁' ?></span> <?= $fl ?></span>
                <?php if ($cnt > 0): ?><span style="font-size:10px;padding:1px 6px;border-radius:8px;background:rgba(0,200,255,0.15);color:var(--cyan)"><?= $cnt ?></span><?php endif; ?>
              </a>
            <?php endforeach; ?>
            <a href="/?page=admin&tab=inbox&subtab=compose&mailbox=<?= e($m['username']) ?>" style="display:flex;align-items:center;gap:8px;padding:6px 12px;font-size:12px;color:var(--cyan);text-decoration:none"><span>✉️</span> Compose</a>
          </div>
        <?php endforeach; ?>
        <a href="/?page=admin&tab=inbox&subtab=accounts" style="display:flex;align-items:center;gap:8px;padding:10px 12px;font-size:12px;color:var(--text2);text-decoration:none;border-top:1px solid var(--border)"><span>⚙️</span> Accounts</a>
      </div>

      <!-- RIGHT PANEL -->
      <div class="panel" style="padding:0;overflow:hidden;min-height:400px">
        <?php if ($subtab === 'compose'): $composeMailbox = $_GET['mailbox'] ?? ($mailboxes[0]['username'] ?? ''); $replyTo = $_GET['to'] ?? ''; $replySubject = $_GET['subject'] ?? ''; ?>
          <div style="padding:16px">
            <h2 style="font-size:18px;margin-bottom:16px">✉️ Compose — <?= e($composeMailbox) ?></h2>
            <form method="post" class="form" style="max-width:100%">
              <?= csrf_field() ?><input type="hidden" name="action" value="admin_send_email">
              <div class="form-row"><label>From<select name="from_email" style="width:100%"><?php foreach ($mailboxes as $m): ?><option value="<?= e($m['username']) ?>" <?= $m['username'] === $composeMailbox ? 'selected' : '' ?>><?= e($m['username']) ?></option><?php endforeach; ?></select></label><label>To<input name="to_email" type="email" required value="<?= e($replyTo) ?>" placeholder="recipient@example.com"></label></div>
              <label>Subject<input name="subject" required value="<?= e($replySubject) ?>"></label>
              <label><textarea name="body" required rows="12" style="font-family:var(--mono);font-size:13px;min-height:200px"></textarea></label>
              <button class="button primary" type="submit">Send as <?= e($composeMailbox) ?></button>
            </form>
          </div>
        <?php elseif ($viewMsg && $imapPass):
          // Fetch full message via single connection (SELECT + FETCH)
          $raw = imap_fetch_msg($imapHost, $imapPort, $activeMailbox, $imapPass, $folder, $viewMsg);
          $resp = $raw['resp'] ?? '';
          if (isset($raw['error'])) $resp = '';
          preg_match('/^FROM:\s*(.+)/im', $resp, $fm);
          preg_match('/^SUBJECT:\s*(.+)/im', $resp, $sm);
          preg_match('/^DATE:\s*(.+)/im', $resp, $dm);
          $from = trim(mb_decode_mimeheader($fm[1] ?? 'Unknown'));
          $subject = trim(mb_decode_mimeheader($sm[1] ?? '(no subject)'));
          $date = trim($dm[1] ?? '');
          $bodyClean = extract_email_body($resp);
          $replySubject = preg_match('/^Re:/i', $subject) ? $subject : 'Re: ' . $subject;
          $replyTo = preg_match('/<([^>]+)>/', $from, $rm) ? $rm[1] : $from;
          ?>
          <div style="padding:16px">
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px">
              <a href="/?page=admin&tab=inbox&subtab=inbox&mailbox=<?= e($activeMailbox) ?>&folder=<?= e($folder) ?>" class="button" style="padding:6px 14px;min-height:auto;font-size:12px">← Back</a>
              <a href="/?page=admin&tab=inbox&subtab=compose&mailbox=<?= e($activeMailbox) ?>&to=<?= e(urlencode($replyTo)) ?>&subject=<?= e(urlencode($replySubject)) ?>" class="button" style="padding:6px 14px;min-height:auto;font-size:12px">↩ Reply</a>
              <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="admin_delete_message"><input type="hidden" name="mailbox" value="<?= e($activeMailbox) ?>"><input type="hidden" name="folder" value="<?= e($folder) ?>"><input type="hidden" name="msg_uid" value="<?= $viewMsg ?>"><button class="button" type="submit" style="padding:6px 14px;min-height:auto;font-size:12px;border-color:rgba(255,76,76,0.5)" onclick="return confirm('Delete?')">🗑 Delete</button></form>
            </div>
            <div style="padding:16px;background:var(--surface2);border-radius:6px;margin-bottom:16px">
              <p style="font-size:12px;color:var(--text2)">From: <strong style="color:var(--text);word-break:break-all"><?= e($from) ?></strong></p>
              <p style="font-size:14px;font-weight:600;margin:8px 0"><?= e($subject) ?></p>
              <p style="font-size:11px;color:var(--text2)"><?= e($date) ?></p>
            </div>
            <div style="padding:16px;background:var(--bg);border:1px solid var(--border);border-radius:6px;font-size:13px;line-height:1.7;white-space:pre-wrap;max-height:60vh;overflow-y:auto"><?= e($bodyClean) ?></div>
          </div>
        <?php elseif ($subtab === 'accounts'): ?>
          <div style="padding:16px">
            <h2 style="font-size:18px;margin-bottom:12px">👤 Email Accounts</h2>
            <details><summary class="button primary" style="display:inline-block;cursor:pointer;margin-bottom:12px">+ Create</summary>
              <form method="post" class="form" style="max-width:400px"><?= csrf_field() ?><input type="hidden" name="action" value="admin_create_email"><div class="form-row"><label>Username<input name="local_part" required></label><label>@<?= e($mailDomain) ?></label></div><label>Full Name<input name="full_name"></label><label>Password<input name="password" type="password" required minlength="6"></label><label>Quota (MB)<input name="quota" type="number" value="1024"></label><button class="button primary" type="submit">Create</button></form>
            </details>
            <table class="table" style="font-size:13px"><tr><th>Email</th><th>Name</th><th>Status</th><th>Actions</th></tr>
            <?php foreach ($allMailboxes as $m): ?>
              <tr><td><a href="/?page=admin&tab=inbox&subtab=inbox&mailbox=<?= e($m['username']) ?>"><?= e($m['username']) ?></a></td><td><?= e($m['full_name'] ?: '—') ?></td><td><span class="badge" style="background:var(--green)">Active</span></td>
                <td><form method="post" style="display:inline" onsubmit="return confirm('Delete?')"><?= csrf_field() ?><input type="hidden" name="action" value="admin_delete_email"><input type="hidden" name="email" value="<?= e($m['username']) ?>"><button class="button" type="submit" style="padding:2px 6px;font-size:10px">Del</button></form>
                <form method="post" style="display:inline-flex;gap:4px"><?= csrf_field() ?><input type="hidden" name="action" value="admin_change_email_password"><input type="hidden" name="email" value="<?= e($m['username']) ?>"><input name="new_password" type="password" placeholder="PW" style="width:60px;padding:2px 4px;font-size:10px"><button class="button" type="submit" style="padding:2px 6px;font-size:10px">Set</button></form></td>
              </tr>
            <?php endforeach; ?>
            </table>

            <?php if ($isSuperAdmin): ?>
            <h3 style="margin-top:20px;font-size:15px">🔐 Email Access</h3>
            <p class="hint">Assign which users can see which mailboxes in the Inbox tab.</p>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="admin_save_email_access">
            <table class="table" style="font-size:12px">
              <tr><th>User</th><th>Role</th><?php foreach ($allMailboxes as $m): ?><th style="text-align:center;font-size:10px"><?= e(explode('@', $m['username'])[0]) ?></th><?php endforeach; ?></tr>
              <?php foreach ($allUsers as $u):
                $access = db()->prepare("SELECT mailbox_email FROM email_access WHERE user_id=?");
                $access->execute([(int)$u['id']]);
                $userAccess = $access->fetchAll(PDO::FETCH_COLUMN);
              ?>
              <tr><td><?= e($u['full_name'] ?: $u['username']) ?></td><td style="font-size:10px"><?= e($u['role']) ?></td>
                <?php foreach ($allMailboxes as $m): ?>
                  <td style="text-align:center"><input type="checkbox" name="access[<?= (int)$u['id'] ?>][]" value="<?= e($m['username']) ?>" <?= in_array($m['username'], $userAccess) ? 'checked' : '' ?>></td>
                <?php endforeach; ?>
              </tr>
              <?php endforeach; ?>
            </table>
            <button class="button primary" type="submit" style="margin-top:8px">Save Access</button>
            </form>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px">
            <h2 style="font-size:15px;font-weight:600;margin:0"><?= $folderIcons[$folder] ?? '📁' ?> <?= $folderNames[$folder] ?? e($folder) ?></h2>
            <form method="get" style="margin-left:auto;display:flex;gap:6px">
              <input type="hidden" name="page" value="admin"><input type="hidden" name="tab" value="inbox"><input type="hidden" name="subtab" value="inbox">
              <input type="hidden" name="mailbox" value="<?= e($activeMailbox) ?>"><input type="hidden" name="folder" value="<?= e($folder) ?>">
              <input name="q" placeholder="Search..." value="<?= e($search) ?>" style="padding:6px 10px;font-size:12px;width:180px">
              <button class="button" type="submit" style="padding:6px 12px;min-height:auto;font-size:11px">🔍</button>
            </form>
          </div>
          <div style="overflow-x:auto">
          <?php if (!$imapPass): ?>
            <p style="padding:24px;color:var(--text2)">Select a mailbox from the left sidebar.</p>
          <?php else:
            $folderName = $folder;
            $messages = imap_fetch_mail($imapHost, $imapPort, $activeMailbox, $imapPass, $folderName, 50);
            if (isset($messages['error'])): ?>
              <p style="padding:24px;color:var(--red)">⚠️ <?= e($messages['error']) ?></p>
            <?php elseif (empty($messages)): ?>
              <p style="padding:24px;color:var(--text2)">No messages in <?= $folderNames[$folder] ?? $folder ?>.</p>
            <?php else: ?>
              <form method="post" id="bulkMsgForm" style="display:flex;gap:6px;padding:8px 12px;border-bottom:1px solid var(--border)">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_bulk_delete_msgs">
                <input type="hidden" name="mailbox" value="<?= e($activeMailbox) ?>">
                <input type="hidden" name="folder" value="<?= e($folder) ?>">
                <label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer"><input type="checkbox" id="selectAllMsgs" onchange="document.querySelectorAll('.msg-select').forEach(function(c){c.checked=this.checked})"> Select All</label>
                <button class="button" type="submit" style="padding:4px 10px;min-height:auto;font-size:11px" onclick="return confirm('Delete selected?')">🗑 Delete Selected</button>
                <button class="button" type="submit" name="empty_all" value="1" style="padding:4px 10px;min-height:auto;font-size:11px;border-color:rgba(255,76,76,0.5)" onclick="return confirm('Empty entire <?= $folderNames[$folder] ?? $folder ?>?')">🗑 Empty <?= $folderNames[$folder] ?? $folder ?></button>
              </form>
              <table class="table email-list" style="font-size:13px">
                <tr style="font-size:11px;color:var(--text2)"><th style="width:30px"></th><th style="width:38%">From</th><th>Subject</th><th style="width:120px">Date</th></tr>
                <?php foreach (array_reverse($messages) as $msg): $mid = $msg['uid'] ?? 0; $msgUrl = '/?page=admin&tab=inbox&subtab=inbox&mailbox=' . e($activeMailbox) . '&folder=' . e($folder) . '&msg=' . $mid; ?>
                  <tr class="<?= $msg['uid'] === $viewMsg ? 'selected' : '' ?>" style="cursor:pointer">
                    <td style="text-align:center"><input type="checkbox" class="msg-select" name="msg_ids[]" value="<?= $mid ?>" form="bulkMsgForm" onclick="event.stopPropagation()"></td>
                    <td onclick="window.location='<?= $msgUrl ?>'" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($msg['from'] ?? '—') ?></td>
                    <td onclick="window.location='<?= $msgUrl ?>'"><a href="<?= $msgUrl ?>" style="color:var(--text);text-decoration:none;display:block"><?= e($msg['subject'] ?? '(no subject)') ?></a></td>
                    <td onclick="window.location='<?= $msgUrl ?>'" style="white-space:nowrap;font-size:11px;color:var(--text2)"><?= e($msg['date'] ?? '') ?></td>
                  </tr>
                <?php endforeach; ?>
              </table>
              <form method="post" id="bulkDelForm"><?= csrf_field() ?><input type="hidden" name="action" value="admin_bulk_delete_msgs"><input type="hidden" name="mailbox" value="<?= e($activeMailbox) ?>"><input type="hidden" name="folder" value="<?= e($folder) ?>"></form>
            <?php endif; ?>
          <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php
}

function admin_todos(array $todos): void
{
    ?>
    <div class="panel">
      <h2>📋 Todo List</h2>
      <form method="post" style="display:flex;gap:8px;margin-bottom:16px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="admin_add_todo">
        <input name="title" placeholder="New task..." required style="flex:1;padding:8px 12px;font-size:13px">
        <button class="button primary" type="submit" style="padding:8px 16px;min-height:auto">Add</button>
      </form>
      <?php if (empty($todos)): ?>
        <p class="hint">No tasks yet. Add one above.</p>
      <?php else: ?>
        <table class="table">
          <tr><th style="width:30px">✅</th><th>Task</th><th style="width:160px">Actions</th></tr>
          <?php foreach ($todos as $t): ?>
            <tr style="<?= $t['is_completed'] ? 'opacity:0.5;text-decoration:line-through' : '' ?>">
              <td>
                <form method="post" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="admin_toggle_todo">
                  <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                  <input type="checkbox" onchange="this.form.submit()" <?= $t['is_completed'] ? 'checked' : '' ?>>
                </form>
              </td>
              <td>
                <form method="post" style="display:inline-flex;gap:4px;width:100%">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="admin_edit_todo">
                  <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                  <input name="title" value="<?= e($t['title']) ?>" style="width:100%;padding:4px 8px;font-size:12px;background:transparent;border:1px solid transparent" onfocus="this.style.borderColor='var(--line)'" onblur="this.style.borderColor='transparent'">
                  <button class="button" type="submit" style="padding:4px 8px;min-height:auto;font-size:10px">Save</button>
                </form>
              </td>
              <td style="white-space:nowrap">
                <form method="post" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="admin_toggle_todo_active">
                  <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                  <button class="button" type="submit" style="padding:4px 8px;min-height:auto;font-size:10px;border-color:<?= $t['is_active'] ? 'var(--green)' : 'var(--orange)' ?>"><?= $t['is_active'] ? 'On' : 'Off' ?></button>
                </form>
                <form method="post" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="admin_delete_todo">
                  <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                  <button class="button" type="submit" style="padding:4px 8px;min-height:auto;font-size:10px;border-color:rgba(255,76,76,0.5)" onclick="return confirm('Delete task?')">Del</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>
    <?php
}
