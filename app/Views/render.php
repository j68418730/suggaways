<?php

function render_home(array $featured, array $newDrops, array $collections, array $comingSoon = []): string
{
    // Get earliest coming soon date
    $earliestRelease = null;
    $earliestName = '';
    if (!empty($comingSoon)) {
        $dates = array_filter(array_column($comingSoon, 'release_date'));
        if (!empty($dates)) {
            $minDate = min($dates);
            $earliestRelease = strtotime($minDate);
            foreach ($comingSoon as $cs) {
                if ($cs['release_date'] === $minDate) { $earliestName = $cs['name']; break; }
            }
        }
    }
    ob_start(); ?>
    <div id="dropCountdown" style="text-align:center;padding:16px;margin-bottom:20px;background:rgba(0,200,255,0.05);border:1px solid rgba(0,200,255,0.15);border-radius:8px;font-family:var(--mono)">
      <p style="font-size:12px;color:var(--cyan);margin-bottom:6px">🚀 SITE GOES LIVE IN</p>
      <div style="display:flex;justify-content:center;gap:16px;font-size:28px;font-weight:800;color:var(--text)">
        <span><span id="cdDays">00</span><span style="display:block;font-size:10px;color:var(--text2);font-weight:400">DAYS</span></span>
        <span style="color:var(--cyan)">:</span>
        <span><span id="cdHours">00</span><span style="display:block;font-size:10px;color:var(--text2);font-weight:400">HOURS</span></span>
        <span style="color:var(--cyan)">:</span>
        <span><span id="cdMins">00</span><span style="display:block;font-size:10px;color:var(--text2);font-weight:400">MINS</span></span>
        <span style="color:var(--cyan)">:</span>
        <span><span id="cdSecs">00</span><span style="display:block;font-size:10px;color:var(--text2);font-weight:400">SECS</span></span>
      </div>
    </div>
    <script>
    (function(){
      var now = new Date();
      var t = new Date(now.getFullYear(), now.getMonth(), 16, 13, 0, 0);
      if (now.getDate() > 16 || (now.getDate() === 16 && now.getHours() >= 13)) { document.getElementById('dropCountdown').style.display = 'none'; return; }
      function update() {
        var diff = Math.max(0, Math.floor((t - new Date()) / 1000));
        document.getElementById('cdDays').textContent = String(Math.floor(diff / 86400)).padStart(2,'0');
        document.getElementById('cdHours').textContent = String(Math.floor((diff % 86400) / 3600)).padStart(2,'0');
        document.getElementById('cdMins').textContent = String(Math.floor((diff % 3600) / 60)).padStart(2,'0');
        document.getElementById('cdSecs').textContent = String(diff % 60).padStart(2,'0');
        if (diff <= 0) document.getElementById('dropCountdown').style.display = 'none';
      }
      update();
      setInterval(update, 1000);
    })();
    </script>
    <section class="section-title">
      <p class="eyebrow">Featured</p>
      <h2>Featured Collection</h2>
    </section>
    <section class="product-grid">
      <?php foreach ($featured as $product): ?>
        <article class="product-card">
          <a href="/?page=product&slug=<?= e($product['slug']) ?>">
            <div class="product-image" style="background-image: url('<?= e(json_decode($product['images'], true)[0] ?? '/assets/img/background.png') ?>');background-size:cover;background-position:center"></div>
            <h3><?= e($product['name']) ?></h3>
            <p><?= e($product['short_description'] ?: $product['description']) ?></p>
            <div class="product-meta">
              <strong>$<?= e(number_format((float)($product['sale_price'] ?: $product['price']), 2)) ?></strong>
              <?php if ($product['sale_price']): ?><span class="original-price">$<?= e(number_format((float)$product['price'], 2)) ?></span><?php endif; ?>
              <span><?= $product['stock_quantity'] > 0 ? e((string)$product['stock_quantity']) . ' in stock' : 'Sold Out' ?></span>
            </div>
          </a>
          <form method="post" class="quick-add">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_to_cart">
            <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
            <button class="button primary" type="submit" <?= $product['stock_quantity'] < 1 ? 'disabled' : '' ?>><?= $product['stock_quantity'] < 1 ? 'Sold Out' : 'Add to Cart' ?></button>
          </form>
        </article>
      <?php endforeach; ?>
    </section>

    <?php if (!empty($newDrops)): ?>
    <section class="section-title" style="margin-top:60px">
      <p class="eyebrow">Just Landed</p>
      <h2>New Drops</h2>
    </section>
    <section class="product-grid grid-four">
      <?php foreach ($newDrops as $product): ?>
        <article class="product-card new-badge">
          <a href="/?page=product&slug=<?= e($product['slug']) ?>">
            <div class="product-image" style="background-image: url('<?= e(json_decode($product['images'], true)[0] ?? '/assets/img/background.png') ?>');background-size:cover;background-position:center"></div>
            <h3><?= e($product['name']) ?></h3>
            <p><?= e($product['short_description']) ?></p>
            <div class="product-meta">
              <strong>$<?= e(number_format((float)($product['sale_price'] ?: $product['price']), 2)) ?></strong>
              <span>New</span>
            </div>
          </a>
        </article>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <?php if (!empty($comingSoon)): ?>
    <section class="section-title" style="margin-top:60px">
      <p class="eyebrow">Coming Soon</p>
      <h2>Upcoming Releases</h2>
    </section>
    <section class="product-grid grid-four">
      <?php foreach ($comingSoon as $cs): ?>
        <article class="product-card">
          <div class="product-image" style="background-image: url('<?= e($cs['image'] ?: '/assets/img/products/swag.jpg') ?>');opacity:0.7"></div>
          <h3><?= e($cs['name']) ?></h3>
          <p><?= e($cs['description'] ?: '') ?></p>
          <div class="product-meta">
            <strong>$<?= e(number_format((float)$cs['price'], 2)) ?></strong>
            <span style="color:var(--green);font-size:11px">Releases <?= e(date('M j', strtotime($cs['release_date']))) ?></span>
          </div>
          <form method="post" style="margin-top:8px">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_preorder_to_cart">
            <input type="hidden" name="coming_soon_id" value="<?= (int)$cs['id'] ?>">
            <button class="button" type="submit" style="width:100%;padding:6px;font-size:12px;border-color:var(--cyan);background:rgba(0,188,212,0.08)">Preorder</button>
          </form>
        </article>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <section class="grid three" style="margin-top:60px">
      <?php foreach ($collections as $cat): ?>
        <a href="/?page=shop&category=<?= e($cat['slug']) ?>" class="panel collection-card">
          <h3><?= e($cat['name']) ?></h3>
          <p><?= e($cat['description']) ?></p>
        </a>
      <?php endforeach; ?>
    </section>

    <section class="panel cta-section" style="margin-top:60px;text-align:center">
      <p class="eyebrow">Join the Movement</p>
      <h2><?= site_setting('hero_title', 'Be Different.<br>Be You.') ?></h2>
      <p><?= site_setting('hero_subscribe', 'Sign up for exclusive drops, early access, and 20% off your first order.') ?></p>
      <div class="actions" style="justify-content:center">
        <a class="button primary" href="/?page=register">Join Now</a>
        <a class="button" href="/?page=shop">Shop All</a>
      </div>
    </section>
    <?php
    return ob_get_clean();
}

function render_shop(array $products, array $categories, ?string $currentCategory, string $sort, string $search, int $page = 1, int $totalPages = 1, array $membershipPlans = []): string
{
    ob_start(); ?>
    <div class="shop-controls">
      <div class="shop-filters">
        <a href="/?page=shop" class="button <?= !$currentCategory ? 'primary' : '' ?>">All</a>
        <?php foreach ($categories as $cat): ?>
          <a href="/?page=shop&category=<?= e($cat['slug']) ?>" class="button <?= $currentCategory === $cat['slug'] ? 'primary' : '' ?>"><?= e($cat['name']) ?></a>
        <?php endforeach; ?>
      </div>
      <div class="shop-sort">
        <form method="get" class="inline-form">
          <input type="hidden" name="page" value="shop">
          <?php if ($currentCategory): ?><input type="hidden" name="category" value="<?= e($currentCategory) ?>"><?php endif; ?>
          <input type="search" name="search" placeholder="Search products..." value="<?= e($search) ?>">
          <select name="sort" onchange="this.form.submit()">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
            <option value="price-low" <?= $sort === 'price-low' ? 'selected' : '' ?>>Price: Low to High</option>
            <option value="price-high" <?= $sort === 'price-high' ? 'selected' : '' ?>>Price: High to Low</option>
            <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name: A-Z</option>
          </select>
        </form>
      </div>
    </div>

    <?php if (empty($products)): ?>
      <div class="panel" style="text-align:center">
        <p>No products found. <?= $search ? 'Try a different search.' : '' ?></p>
      </div>
    <?php else: ?>
      <section class="product-grid">
        <?php foreach ($products as $product): ?>
          <article class="product-card <?= $product['is_new'] ? 'new-badge' : '' ?>">
            <a href="/?page=product&slug=<?= e($product['slug']) ?>">
              <div class="product-image" style="background-image: url('<?= e(json_decode($product['images'], true)[0] ?? '/assets/img/background.png') ?>');background-size:cover;background-position:center"></div>
              <?php if (!empty($product['category_name'])): ?><span class="badge" style="position:absolute;top:8px;left:8px;z-index:2;font-size:10px"><?= e($product['category_name']) ?></span><?php endif; ?>
              <h3><?= e($product['name']) ?></h3>
              <p><?= e($product['short_description'] ?: $product['description']) ?></p>
              <div class="product-meta">
                <strong>$<?= e(number_format((float)($product['sale_price'] ?: $product['price']), 2)) ?></strong>
                <?php if ($product['sale_price']): ?><span class="original-price">$<?= e(number_format((float)$product['price'], 2)) ?></span><?php endif; ?>
              </div>
            </a>
            <form method="post" class="quick-add">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="add_to_cart">
              <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
              <button class="button primary" type="submit" <?= $product['stock_quantity'] < 1 ? 'disabled' : '' ?>><?= $product['stock_quantity'] < 1 ? 'Sold Out' : 'Add to Cart' ?></button>
            </form>
          </article>
        <?php endforeach; ?>
      </section>

      <?php if (!empty($membershipPlans)): ?>
      <section class="section-title" style="margin-top:40px">
        <p class="eyebrow">Membership</p>
        <h2>Choose Your Plan</h2>
      </section>
      <section class="product-grid">
        <?php foreach ($membershipPlans as $plan): $benefits = json_decode($plan['benefits'] ?? '[]', true); ?>
          <div class="panel product-card" style="text-align:center;padding:24px 16px">
            <h3 style="font-size:18px;margin-bottom:8px"><?= e($plan['name']) ?></h3>
            <p style="font-size:32px;font-weight:800;color:var(--cyan);margin:12px 0">$<?= e(number_format((float)$plan['price'], 2)) ?><span style="font-size:13px;color:var(--muted)">/month</span></p>
            <p style="font-size:12px;color:var(--muted);margin-bottom:12px"><?= e($plan['description'] ?? '') ?></p>
            <ul style="text-align:left;list-style:none;padding:0;margin:12px 0">
              <?php foreach ($benefits as $b): ?>
                <li style="padding:3px 0;font-size:12px">✅ <?= e($b) ?></li>
              <?php endforeach; ?>
            </ul>
            <form method="post" action="/?page=membership">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="join_membership">
              <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
              <button class="button primary" type="submit" style="width:100%">Join Now — $<?= e(number_format((float)$plan['price'], 2)) ?>/mo</button>
              <label style="display:flex;align-items:center;gap:6px;justify-content:center;margin-top:8px;font-size:11px;color:var(--muted);cursor:pointer">
                <input type="checkbox" name="auto_pay" value="1" checked> Auto-pay monthly
              </label>
            </form>
          </div>
        <?php endforeach; ?>
      </section>
      <?php endif; ?>

      <?php if ($totalPages > 1): ?>
        <div class="pagination" style="display:flex;justify-content:center;gap:6px;margin-top:24px">
          <?php if ($page > 1): ?>
            <a href="?page=shop<?= $currentCategory ? '&category=' . e($currentCategory) : '' ?><?= $search ? '&search=' . e($search) : '' ?>&sort=<?= e($sort) ?>&p=<?= $page - 1 ?>" class="button" style="padding:8px 14px;min-height:auto">&laquo; Prev</a>
          <?php endif; ?>
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=shop<?= $currentCategory ? '&category=' . e($currentCategory) : '' ?><?= $search ? '&search=' . e($search) : '' ?>&sort=<?= e($sort) ?>&p=<?= $i ?>" class="button <?= $i === $page ? 'primary' : '' ?>" style="padding:8px 14px;min-height:auto"><?= $i ?></a>
          <?php endfor; ?>
          <?php if ($page < $totalPages): ?>
            <a href="?page=shop<?= $currentCategory ? '&category=' . e($currentCategory) : '' ?><?= $search ? '&search=' . e($search) : '' ?>&sort=<?= e($sort) ?>&p=<?= $page + 1 ?>" class="button" style="padding:8px 14px;min-height:auto">Next &raquo;</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}

function render_product_detail(array $product, array $images, array $sizes, array $colors, array $reviews, array $related, ?array $user): string
{
    $rating = db()->prepare('SELECT COALESCE(AVG(rating), 0) as avg_rating, COUNT(*) as count FROM reviews WHERE product_id = ? AND is_approved = 1');
    $rating->execute([(int)$product['id']]);
    $ratingData = $rating->fetch();
    $inWishlist = $user ? db()->prepare('SELECT id FROM wishlist_items WHERE user_id = ? AND product_id = ?')->execute([(int)$user['id'], (int)$product['id']]) && db()->query('SELECT 1 FROM wishlist_items WHERE user_id = ' . (int)$user['id'] . ' AND product_id = ' . (int)$product['id'])->fetch() : false;

    ob_start(); ?>
    <div class="product-detail">
      <div class="product-gallery">
        <div class="gallery-main">
          <img src="<?= e($images[0] ?? '/assets/img/background.png') ?>" alt="<?= e($product['name']) ?>">
        </div>
        <?php if (count($images) > 1): ?>
          <div class="gallery-thumbs">
            <?php foreach ($images as $img): ?>
              <img src="<?= e($img) ?>" alt="" class="thumb" onclick="this.parentElement.previousElementSibling.querySelector('img').src = this.src">
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="product-info">
        <p class="eyebrow"><?= e($product['sku']) ?></p>
        <h2><?= e($product['name']) ?></h2>
        <?php if (!empty($product['category_name'])): ?><a href="/?page=shop&category=<?= e($product['category_slug'] ?? '') ?>" class="badge" style="display:inline-block;margin-bottom:8px"><?= e($product['category_name']) ?></a><?php endif; ?>
        <div class="rating">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <span class="star <?= $i <= round((float)$ratingData['avg_rating']) ? 'filled' : '' ?>">★</span>
          <?php endfor; ?>
          <span>(<?= (int)$ratingData['count'] ?> reviews)</span>
        </div>
        <div class="product-price">
          <?php if ($product['sale_price']): ?>
            <span class="sale-price">$<?= e(number_format((float)$product['sale_price'], 2)) ?></span>
            <span class="original-price">$<?= e(number_format((float)$product['price'], 2)) ?></span>
          <?php else: ?>
            <span>$<?= e(number_format((float)$product['price'], 2)) ?></span>
          <?php endif; ?>
        </div>
        <p><?= nl2br(e($product['description'])) ?></p>

        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_to_cart">
          <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">

          <?php if (!empty($sizes)): ?>
            <div class="option-group">
              <label>Size</label>
              <div class="option-buttons">
                <?php foreach ($sizes as $size): ?>
                  <label class="option-btn">
                    <input type="radio" name="size" value="<?= e($size) ?>" required>
                    <span><?= e($size) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <?php if (!empty($colors)): ?>
            <div class="option-group">
              <label>Color</label>
              <div class="option-buttons">
                <?php foreach ($colors as $color): ?>
                  <label class="option-btn">
                    <input type="radio" name="color" value="<?= e($color) ?>" required>
                    <span><?= e($color) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <div class="option-group">
            <label>Quantity</label>
            <input type="number" name="quantity" value="1" min="1" max="99" style="width:80px">
          </div>

          <div class="product-actions">
            <button class="button primary" type="submit" <?= $product['stock_quantity'] < 1 ? 'disabled' : '' ?>>
              <?= $product['stock_quantity'] < 1 ? 'Sold Out' : 'Add to Cart' ?>
            </button>
            <?php if ($user): ?>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $inWishlist ? 'remove_wishlist' : 'add_to_wishlist' ?>">
                <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                <button class="button wishlist-btn" type="submit"><?= $inWishlist ? '♥ In Wishlist' : '♡ Add to Wishlist' ?></button>
              </form>
            <?php endif; ?>
          </div>
        </form>

        <div class="product-meta-info">
          <p><strong>SKU:</strong> <?= e($product['sku']) ?></p>
          <p><strong>Stock:</strong> <?= $product['stock_quantity'] > 0 ? e((string)$product['stock_quantity']) . ' available' : 'Sold Out' ?></p>
          <p><strong>Category:</strong> <a href="/?page=shop&category=<?= e($product['category_slug'] ?? '') ?>"><?= e($product['category_name'] ?? 'Uncategorized') ?></a></p>
        </div>
      </div>
    </div>

    <section class="panel" style="margin-top:40px">
      <h3>Customer Reviews</h3>
      <?php if (empty($reviews)): ?>
        <p>No reviews yet. Be the first to review this product.</p>
      <?php else: ?>
        <?php foreach ($reviews as $review): ?>
          <div class="review-card">
            <div class="review-header">
              <strong><?= e($review['full_name'] ?? 'Anonymous') ?></strong>
              <div class="rating">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <span class="star <?= $i <= (int)$review['rating'] ? 'filled' : '' ?>">★</span>
                <?php endfor; ?>
              </div>
            </div>
            <?php if ($review['title']): ?><h4><?= e($review['title']) ?></h4><?php endif; ?>
            <p><?= e($review['body']) ?></p>
            <small><?= e($review['created_at']) ?></small>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if ($user): ?>
        <form method="post" class="form" style="margin-top:20px;max-width:500px">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_review">
          <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
          <label>Rating
            <select name="rating" required>
              <option value="5">★★★★★ (5)</option>
              <option value="4">★★★★☆ (4)</option>
              <option value="3">★★★☆☆ (3)</option>
              <option value="2">★★☆☆☆ (2)</option>
              <option value="1">★☆☆☆☆ (1)</option>
            </select>
          </label>
          <label>Title<input name="review_title"></label>
          <label>Review<textarea name="review_body" required></textarea></label>
          <button class="button primary" type="submit">Submit Review</button>
        </form>
      <?php endif; ?>
    </section>

    <?php if (!empty($related)): ?>
      <section class="section-title" style="margin-top:40px">
        <h2>Related Products</h2>
      </section>
      <section class="product-grid">
        <?php foreach ($related as $rp): ?>
          <article class="product-card">
            <a href="/?page=product&slug=<?= e($rp['slug']) ?>">
              <div class="product-image" style="background-image: url('<?= e(json_decode($rp['images'], true)[0] ?? '/assets/img/background.png') ?>')"></div>
              <h3><?= e($rp['name']) ?></h3>
              <div class="product-meta">
                <strong>$<?= e(number_format((float)($rp['sale_price'] ?: $rp['price']), 2)) ?></strong>
              </div>
            </a>
          </article>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}

function render_collections(array $collections): string
{
    ob_start(); ?>
    <section class="collection-grid">
      <?php foreach ($collections as $cat): ?>
        <a href="/?page=shop&category=<?= e($cat['slug']) ?>" class="panel collection-card featured">
          <h2><?= e($cat['name']) ?></h2>
          <p><?= e($cat['description']) ?></p>
          <span class="count"><?= (int)$cat['product_count'] ?> Products</span>
          <span class="button primary">Browse Collection</span>
        </a>
      <?php endforeach; ?>
    </section>
    <?php
    return ob_get_clean();
}

function render_new_drops(array $products): string
{
    // Get earliest coming soon date
    $earliestRelease = null;
    $earliestName = '';
    $csItems = db()->query("SELECT name, release_date FROM coming_soon WHERE release_date IS NOT NULL AND release_date > NOW() ORDER BY release_date ASC LIMIT 1")->fetchAll();
    if (!empty($csItems)) {
        $cs = $csItems[0];
        $earliestRelease = strtotime($cs['release_date']);
        $earliestName = $cs['name'];
    }
    ob_start(); ?>
    <?php if ($earliestRelease): ?>
    <div id="cdCountdown2" style="text-align:center;padding:16px;margin-bottom:20px;background:rgba(0,200,255,0.05);border:1px solid rgba(0,200,255,0.15);border-radius:8px;font-family:var(--mono)">
      <p style="font-size:12px;color:var(--cyan);margin-bottom:6px">🚀 NEXT DROP: <strong><?= e(strtoupper($earliestName)) ?></strong></p>
      <div style="display:flex;justify-content:center;gap:16px;font-size:28px;font-weight:800;color:var(--text)">
        <span><span id="cd2Days">00</span><span style="display:block;font-size:10px;color:var(--text2);font-weight:400">DAYS</span></span>
        <span style="color:var(--cyan)">:</span>
        <span><span id="cd2Hours">00</span><span style="display:block;font-size:10px;color:var(--text2);font-weight:400">HOURS</span></span>
        <span style="color:var(--cyan)">:</span>
        <span><span id="cd2Mins">00</span><span style="display:block;font-size:10px;color:var(--text2);font-weight:400">MINS</span></span>
        <span style="color:var(--cyan)">:</span>
        <span><span id="cd2Secs">00</span><span style="display:block;font-size:10px;color:var(--text2);font-weight:400">SECS</span></span>
      </div>
    </div>
    <script>
    (function(){
      var now2 = new Date();
      var t2 = new Date(now2.getFullYear(), now2.getMonth(), 16, 13, 0, 0);
      if (now2.getDate() > 16 || (now2.getDate() === 16 && now2.getHours() >= 13)) { var el2 = document.getElementById('cdCountdown2'); if (el2) el2.style.display = 'none'; return; }
      function update2() {
        var diff = Math.max(0, Math.floor((t2 - new Date()) / 1000));
        document.getElementById('cd2Days').textContent = String(Math.floor(diff / 86400)).padStart(2,'0');
        document.getElementById('cd2Hours').textContent = String(Math.floor((diff % 86400) / 3600)).padStart(2,'0');
        document.getElementById('cd2Mins').textContent = String(Math.floor((diff % 3600) / 60)).padStart(2,'0');
        document.getElementById('cd2Secs').textContent = String(diff % 60).padStart(2,'0');
        if (diff <= 0) { var el2 = document.getElementById('cdCountdown2'); if (el2) el2.style.display = 'none'; }
      }
      update2();
      setInterval(update2, 1000);
    })();
    </script>
    <?php endif; ?>
    <section class="product-grid">
      <?php foreach ($products as $product): ?>
        <article class="product-card new-badge">
          <a href="/?page=product&slug=<?= e($product['slug']) ?>">
            <div class="product-image" style="background-image: url('<?= e(json_decode($product['images'], true)[0] ?? '/assets/img/background.png') ?>');background-size:cover;background-position:center"></div>
            <h3><?= e($product['name']) ?></h3>
            <p><?= e($product['short_description']) ?></p>
            <div class="product-meta">
              <strong>$<?= e(number_format((float)($product['sale_price'] ?: $product['price']), 2)) ?></strong>
              <span class="badge-new">New Drop</span>
            </div>
          </a>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_to_cart">
            <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
            <button class="button primary" type="submit">Add to Cart</button>
          </form>
        </article>
      <?php endforeach; ?>
    </section>
    <?php
    return ob_get_clean();
}

function render_events(): string
{
    $events = db()->query('SELECT * FROM lookbook_events WHERE status = "published" ORDER BY sort_order, event_date DESC')->fetchAll();
    ob_start(); ?>
    <section class="lookbook-grid">
      <?php if (empty($events)): ?>
        <div class="panel"><p>No events yet. Check back soon.</p></div>
      <?php endif; ?>
      <?php foreach ($events as $i => $ev): ?>
        <div class="panel lookbook-item <?= $i === 0 ? 'featured-look' : '' ?>">
          <div class="lookbook-image" style="background:linear-gradient(145deg,rgba(0,140,255,0.3),rgba(0,0,0,0.5)),url('<?= e($ev['image'] ?: '/assets/img/background.png') ?>') center/cover"></div>
          <div class="lookbook-info">
            <p class="eyebrow"><?= e($ev['title']) ?></p>
            <?php if ($ev['event_date']): ?><p class="hint"><?= e(date('F j, Y', strtotime($ev['event_date']))) ?></p><?php endif; ?>
            <?php if ($ev['description']): ?><p><?= e($ev['description']) ?></p><?php endif; ?>
            <?php if ($ev['location_name']): ?><p class="hint"><?= e($ev['location_name']) ?><?php if ($ev['address'] || $ev['city']): ?>, <?= e($ev['address'] ?: '') ?><?= e($ev['address'] && $ev['city'] ? ', ' : '') ?><?= e($ev['city'] ?: '') ?><?php endif; ?></p><?php endif; ?>
            <?php if ($ev['lat'] && $ev['lng']): ?>
              <a href="https://www.google.com/maps/dir/?api=1&destination=<?= e($ev['lat']) ?>,<?= e($ev['lng']) ?>" target="_blank" class="button">Get Directions</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </section>
    <?php
    return ob_get_clean();
}

function render_about(?array $page): string
{
    ob_start(); ?>
    <div class="panel about-content">
      <?php if ($page): ?>
        <?= $page['content'] ?>
      <?php else: ?>
        <h2>The Future of Streetwear</h2>
        <p>SUGGAWAYZ was founded on the belief that clothing should be more than fabric — it should be an expression of identity, technology, and vision.</p>
        <p>We bridge the gap between cyberpunk aesthetics and everyday wearability. Every piece is designed with meticulous attention to detail using premium materials.</p>
      <?php endif; ?>
    </div>
    <div class="grid three" style="margin-top:24px">
      <div class="panel">
        <h3>Mission</h3>
        <p>To outfit the next generation with clothing that pushes boundaries — where fashion meets function, and style meets substance.</p>
      </div>
      <div class="panel">
        <h3>Vision</h3>
        <p>A world where everyone can express their unique identity through bold, futuristic design without compromising on quality or comfort.</p>
      </div>
      <div class="panel">
        <h3>Values</h3>
        <p>Innovation, inclusivity, sustainability, and authenticity. We build for the future while respecting the planet and its people.</p>
      </div>
    </div>
    <?php
    return ob_get_clean();
}

function render_contact(): string
{
    ob_start(); ?>
    <div class="split" style="margin-bottom:40px">
      <div class="panel">
        <h2>Get in Touch</h2>
        <p>Have a question, collaboration idea, or just want to say hi? We would love to hear from you.</p>
        <div class="contact-info">
          <p><strong>Email:</strong> support@suggawayz.com</p>
          <p><strong>Press:</strong> press@suggawayz.com</p>
          <p><strong>Collaborations:</strong> partners@suggawayz.com</p>
        </div>
      </div>
      <div class="panel">
        <form method="post" class="form" action="/?page=contact">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="contact">
          <label>Name<input name="name" required></label>
          <label>Email<input name="email" type="email" required></label>
          <label>Subject<select name="subject">
            <option>General Inquiry</option>
            <option>Order Support</option>
            <option>Collaboration</option>
            <option>Press</option>
            <option>Bulk Orders</option>
          </select></label>
          <label>Message<textarea name="message" required style="min-height:150px"></textarea></label>
          <button class="button primary" type="submit">Send Message</button>
        </form>
      </div>
    </div>
    <?php
    return ob_get_clean();
}

function render_faq(array $faqs, array $categories): string
{
    ob_start(); ?>
    <?php if (!empty($categories)): ?>
      <div class="faq-nav">
        <?php foreach ($categories as $cat): ?>
          <a href="#<?= e(slugify($cat)) ?>" class="button"><?= e($cat) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="faq-list">
      <?php foreach ($faqs as $faq): ?>
        <details class="faq-item panel">
          <summary><h3><?= e($faq['question']) ?></h3></summary>
          <p><?= nl2br(e($faq['answer'])) ?></p>
        </details>
      <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

function render_blog(array $posts): string
{
    ob_start(); ?>
    <section class="blog-grid">
      <?php foreach ($posts as $post): ?>
        <a href="/?page=blog-post&slug=<?= e($post['slug']) ?>" class="panel blog-card">
          <div class="blog-image" style="background:linear-gradient(145deg,rgba(0,140,255,0.2),rgba(0,0,0,0.4)),url('/assets/img/background.png') center/cover;height:200px"></div>
          <div class="blog-info">
            <small><?= e(date('F j, Y', strtotime($post['published_at']))) ?></small>
            <h3><?= e($post['title']) ?></h3>
            <p><?= e($post['excerpt']) ?></p>
            <span class="button">Read More</span>
          </div>
        </a>
      <?php endforeach; ?>
    </section>
    <?php
    return ob_get_clean();
}

function render_blog_post(array $post): string
{
    ob_start(); ?>
    <article class="panel blog-article">
      <small><?= e(date('F j, Y', strtotime($post['published_at']))) ?> by <?= e($post['author'] ?? 'SUGGAWAYZ Team') ?></small>
      <h2><?= e($post['title']) ?></h2>
      <?= $post['content'] ?>
    </article>
    <a href="/?page=blog" class="button">&larr; Back to Blog</a>
    <?php
    return ob_get_clean();
}

function render_static_page(?array $page): string
{
    ob_start(); ?>
    <div class="panel static-content">
      <?php if ($page): ?>
        <?= $page['content'] ?>
      <?php else: ?>
        <p>Page not found.</p>
      <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function render_login(): string
{
    ob_start(); ?>
    <section class="panel narrow">
      <form method="post" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="login">
        <label>Username or Email<input name="username" required autocomplete="username"></label>
        <label>Password<input name="password" type="password" required autocomplete="current-password"></label>
        <label class="checkbox-label"><input type="checkbox" name="remember"> Remember me</label>
        <button class="button primary" type="submit">Login</button>
      </form>
      <p class="hint" style="margin-top:16px"><a href="/?page=forgot-password">Forgot password?</a></p>
      <p class="hint">No account? <a href="/?page=register">Register here</a></p>
    </section>
    <?php
    return ob_get_clean();
}

function render_register(): string
{
    ob_start(); ?>
    <section class="panel narrow">
      <form method="post" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="register">
        <label>Full Name<input name="full_name" required></label>
        <label>Username<input name="username" required autocomplete="username"></label>
        <label>Email<input name="email" type="email" required autocomplete="email"></label>
        <label>Password<input name="password" type="password" required minlength="8" autocomplete="new-password"></label>
        <label>Confirm Password<input name="password_confirm" type="password" required></label>
        <p class="hint">By registering, you agree to our <a href="/?page=terms">Terms</a> and <a href="/?page=privacy">Privacy Policy</a>.</p>
        <button class="button primary" type="submit">Create Account</button>
      </form>
      <p class="hint" style="margin-top:16px">Already have an account? <a href="/?page=login">Login</a></p>
    </section>
    <?php
    return ob_get_clean();
}

function render_forgot_password(): string
{
    ob_start(); ?>
    <section class="panel narrow">
      <form method="post" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="forgot_password">
        <label>Email Address<input name="email" type="email" required></label>
        <button class="button primary" type="submit">Send Reset Link</button>
      </form>
      <p class="hint" style="margin-top:16px"><a href="/?page=login">Back to Login</a></p>
    </section>
    <?php
    return ob_get_clean();
}

function render_cart(array $items, float $subtotal, float $discount, ?string $couponCode, array $shippingMethods): string
{
    $taxRate = config('app.tax_rate', 8.25);
    $tax = round(($subtotal - $discount) * ($taxRate / 100), 2);
    $shipping = $subtotal >= config('app.shipping_threshold', 75) ? 0 : ($shippingMethods[0]['base_rate'] ?? 7.99);
    $total = $subtotal - $discount + $tax + (float)$shipping;

    ob_start(); ?>
    <?php if (empty($items)): ?>
      <div class="panel" style="text-align:center">
        <h2>Your cart is empty</h2>
        <p>Start shopping and add some gear to your cart.</p>
        <a href="/?page=shop" class="button primary">Shop Now</a>
      </div>
    <?php else: ?>
      <div class="cart-layout">
        <div class="cart-items">
          <?php foreach ($items as $key => $item): ?>
            <div class="cart-item panel">
              <div class="cart-item-image" style="background:url('<?= e($item['image'] ?? '/assets/img/background.png') ?>') center/cover;width:120px;height:120px;border:1px solid var(--line-soft)"></div>
              <div class="cart-item-info">
                <?php if (!empty($item['is_preorder'])): ?>
                  <span class="badge" style="background:var(--cyan);font-size:10px">Preorder</span>
                <?php endif; ?>
                <h3><?= e($item['name']) ?></h3>
                <?php if (!empty($item['slug'])): ?>
                  <p><a href="/?page=product&slug=<?= e($item['slug']) ?>">View product</a></p>
                <?php endif; ?>
                <?php if ($item['size']): ?><p>Size: <?= e($item['size']) ?></p><?php endif; ?>
                <?php if ($item['color']): ?><p>Color: <?= e($item['color']) ?></p><?php endif; ?>
                <p class="cart-item-price">$<?= e(number_format((float)$item['price'], 2)) ?></p>
              </div>
              <div class="cart-item-qty">
                <form method="post" class="inline-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="update_cart">
                  <label>-</label>
                  <input type="number" name="quantity[<?= e($key) ?>]" value="<?= (int)$item['quantity'] ?>" min="0" max="99" onchange="this.form.submit()">
                  <button type="submit" class="button" style="padding:6px 12px;min-height:auto">Update</button>
                </form>
              </div>
              <div class="cart-item-total">
                <strong>$<?= e(number_format((float)$item['price'] * (int)$item['quantity'], 2)) ?></strong>
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="remove_from_cart">
                  <input type="hidden" name="key" value="<?= e($key) ?>">
                  <button type="submit" class="button" style="padding:6px 12px;min-height:auto;border-color:rgba(255,76,76,0.5)">Remove</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="cart-summary panel">
          <h3>Order Summary</h3>
          <div class="summary-row"><span>Subtotal</span><span>$<?= e(number_format($subtotal, 2)) ?></span></div>
          <?php if ($discount > 0): ?>
            <div class="summary-row discount"><span>Discount (<?= e($couponCode) ?>)</span><span>-$<?= e(number_format($discount, 2)) ?></span></div>
          <?php endif; ?>
          <div class="summary-row"><span>Tax (<?= e((string)$taxRate) ?>%)</span><span>$<?= e(number_format($tax, 2)) ?></span></div>
          <div class="summary-row"><span>Shipping</span><span><?= $shipping == 0 ? 'FREE' : '$' . e(number_format((float)$shipping, 2)) ?></span></div>
          <div class="summary-row total"><span>Total</span><span>$<?= e(number_format(max(0, $total), 2)) ?></span></div>

          <form method="post" class="form" style="margin-top:16px">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="apply_coupon">
            <label>Discount Code
              <div class="coupon-input">
                <input name="coupon" placeholder="Enter code" value="<?= e($couponCode ?? '') ?>">
                <button type="submit" class="button" style="padding:8px 12px;min-height:auto">Apply</button>
              </div>
            </label>
          </form>
          <?php if ($couponCode): ?>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="remove_coupon">
              <button type="submit" class="button" style="margin-top:8px;padding:6px 12px;min-height:auto">Remove Coupon</button>
            </form>
          <?php endif; ?>

          <a href="/?page=checkout" class="button primary" style="margin-top:20px;width:100%;text-align:center">Proceed to Checkout</a>
          <a href="/?page=shop" class="button" style="margin-top:8px;width:100%;text-align:center">Continue Shopping</a>
        </div>
      </div>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}

function render_checkout(array $items, array $addresses, float $subtotal, float $discount, float $tax, array $shippingMethods, array $user): string
{
    $total = $subtotal - $discount + $tax;
    $cashtag = '';
    $pmt = db()->query("SELECT extra_settings FROM payment_settings WHERE provider = 'cash_app'")->fetchColumn();
    if ($pmt) { $extra = json_decode($pmt, true); $cashtag = $extra['cashtag'] ?? ''; }
    $paypalClientId = db()->query("SELECT public_key FROM payment_settings WHERE provider='paypal'")->fetchColumn();
    ob_start(); ?>
    <div class="checkout-layout">
      <div class="checkout-form">
        <?php if (!$user): ?>
          <div class="panel" style="border-color:var(--cyan);text-align:center;margin-bottom:20px">
            <h3>Already have an account?</h3>
            <p style="margin:8px 0"><a href="/?page=login" class="button primary" style="padding:8px 24px">Sign In</a> <a href="/?page=register" class="button" style="padding:8px 24px">Create Account</a></p>
            <p class="hint">Or checkout as a guest — your info will be saved with the order.</p>
          </div>
          <div class="panel" style="margin-bottom:16px">
            <h3>Your Information</h3>
            <div class="grid two">
              <label>Full Name <input name="guest_name" value="<?= e(old('guest_name')) ?>" required></label>
              <label>Email Address <input name="guest_email" type="email" value="<?= e(old('guest_email')) ?>" required></label>
            </div>
          </div>
        <?php endif; ?>

        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="checkout">

          <div class="panel">
            <h3>Shipping Address</h3>
            <?php if (!empty($addresses)): ?>
              <?php foreach ($addresses as $addr): ?>
                <label class="address-option panel" style="cursor:pointer;margin-bottom:8px">
                  <input type="radio" name="address_id" value="<?= (int)$addr['id'] ?>" <?= $addr['is_default_shipping'] ? 'checked' : '' ?>>
                  <div>
                    <strong><?= e($addr['label']) ?></strong>
                    <p><?= e($addr['full_name']) ?>, <?= e($addr['street_line1']) ?>, <?= e($addr['city']) ?>, <?= e($addr['state']) ?> <?= e($addr['postal_code']) ?></p>
                  </div>
                </label>
              <?php endforeach; ?>
              <hr style="border-color:var(--line-soft);margin:12px 0">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:var(--muted)">
                <input type="checkbox" id="useNewAddress" onchange="document.getElementById('newAddressFields').style.display=this.checked?'block':'none'">
                Ship to a different address
              </label>
            <?php endif; ?>
            <div id="newAddressFields" <?= empty($addresses) ? '' : 'style="display:none"' ?>>
              <div class="form" style="margin-top:8px">
                <label>Full Name <input name="addr_name" value="<?= e($user['full_name'] ?? '') ?>" <?= empty($addresses) ? 'required' : '' ?>></label>
                <label>Street Address <input name="addr_street" required></label>
                <div class="grid two">
                  <label>City <input name="addr_city" required></label>
                  <label>State <input name="addr_state" required></label>
                </div>
                <div class="grid two">
                  <label>ZIP Code <input name="addr_zip" required></label>
                  <label>Country <input name="addr_country" value="United States" required></label>
                </div>
              </div>
            </div>
          </div>

          <div class="panel" id="cashapp-fields" style="display:none;border-color:var(--green)">
            <h3>Cash App Payment Info</h3>
            <p class="hint">Enter your name and phone number to generate a Cash App payment QR code.</p>
            <label>Full Name <input name="cashapp_name" placeholder="John Doe"></label>
            <label>Phone Number <input name="cashapp_phone" type="tel" placeholder="+1 (555) 000-0000"></label>
            <?php if ($cashtag): ?>
              <div style="margin-top:12px;text-align:center">
                <p><strong>Pay via Cash App:</strong></p>
                <p><code>$<?= e($cashtag) ?></code></p>
                <p class="hint">Amount: <strong>$<?= e(number_format($total, 2)) ?></strong></p>
                <div id="cashapp-qr" style="margin-top:8px">
                  <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=https%3A%2F%2Fcash.app%2F%24<?= e(urlencode($cashtag)) ?>" alt="Cash App QR" style="border-radius:8px;max-width:200px">
                  <p class="hint" style="margin-top:4px">Scan with your phone to pay</p>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <div class="panel">
            <h3>Shipping Method</h3>
            <select name="shipping_method" class="form-input" style="width:100%;padding:10px 14px;font-size:14px">
              <?php foreach ($shippingMethods as $method): ?>
                <option value="<?= (int)$method['id'] ?>" <?= $method === $shippingMethods[0] ? 'selected' : '' ?>>
                  <?= e($method['carrier']) ?> — <?= e($method['service_name']) ?> ($<?= e(number_format((float)$method['base_rate'], 2)) ?>, <?= (int)$method['estimated_days_min'] ?>-<?= (int)$method['estimated_days_max'] ?> days)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="panel">
            <h3>Payment Method</h3>
            <div class="payment-methods-grid">
              <label class="payment-option"><input type="radio" name="payment_method" value="stripe" checked onchange="toggleCashApp();togglePayPal()"> <span>Credit/Debit Card</span></label>
              <label class="payment-option"><input type="radio" name="payment_method" value="paypal" onchange="toggleCashApp();togglePayPal()"> <span>PayPal</span></label>
              <label class="payment-option"><input type="radio" name="payment_method" value="square" onchange="toggleCashApp();togglePayPal()"> <span>Square</span></label>
              <label class="payment-option"><input type="radio" name="payment_method" value="cash_app" onchange="toggleCashApp();togglePayPal()"> <span>Cash App</span></label>
              <label class="payment-option"><input type="radio" name="payment_method" value="apple_pay" onchange="toggleCashApp();togglePayPal()"> <span>Apple Pay</span></label>
              <label class="payment-option"><input type="radio" name="payment_method" value="google_pay" onchange="toggleCashApp();togglePayPal()"> <span>Google Pay</span></label>
              <label class="payment-option"><input type="radio" name="payment_method" value="bank_transfer" onchange="toggleCashApp();togglePayPal()"> <span>Bank Transfer</span></label>
            </div>
            <div id="paypal-button-container" style="display:none;margin-top:16px"></div>
          </div>

          <div class="panel">
            <h3>Order Notes</h3>
            <textarea name="notes" placeholder="Special instructions, delivery preferences..." style="min-height:80px"></textarea>
          </div>

          <button type="submit" class="button primary" style="width:100%;text-align:center;padding:18px" id="placeOrderBtn">Place Order — $<?= e(number_format($total, 2)) ?></button>
        </form>
      </div>

      <div class="checkout-summary panel">
        <h3>Order Summary</h3>
        <div class="checkout-items">
          <?php foreach ($items as $item): ?>
            <div class="checkout-item">
              <span><?= !empty($item['is_preorder']) ? '<span class="badge" style="background:var(--cyan);font-size:10px;margin-right:4px">Preorder</span>' : '' ?><?= e($item['name']) ?> × <?= (int)$item['quantity'] ?></span>
              <span>$<?= e(number_format((float)$item['price'] * (int)$item['quantity'], 2)) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="summary-row"><span>Subtotal</span><span>$<?= e(number_format($subtotal, 2)) ?></span></div>
        <?php if ($discount > 0): ?>
          <div class="summary-row discount"><span>Discount</span><span>-$<?= e(number_format($discount, 2)) ?></span></div>
        <?php endif; ?>
        <div class="summary-row"><span>Tax</span><span>$<?= e(number_format($tax, 2)) ?></span></div>
        <div class="summary-row total"><span>Total</span><span>$<?= e(number_format($total, 2)) ?></span></div>
      </div>
    </div>
    <script src="https://www.paypal.com/sdk/js?client-id=<?= e($paypalClientId) ?>&currency=USD" data-sdk-integration-source="button-factory"></script>
    <script>
    function toggleCashApp() {
      var sel = document.querySelector('input[name="payment_method"]:checked');
      var fields = document.getElementById('cashapp-fields');
      var inputs = fields.querySelectorAll('input[name="cashapp_name"], input[name="cashapp_phone"]');
      if (sel && sel.value === 'cash_app') {
        fields.style.display = 'block';
        inputs.forEach(function(el) { el.required = true; });
      } else {
        fields.style.display = 'none';
        inputs.forEach(function(el) { el.required = false; });
      }
    }
    function togglePayPal() {
      var sel = document.querySelector('input[name="payment_method"]:checked');
      var container = document.getElementById('paypal-button-container');
      var btn = document.getElementById('placeOrderBtn');
      if (sel && sel.value === 'paypal') {
        container.style.display = 'block';
        btn.style.display = 'none';
        if (!container.hasChildNodes()) {
          paypal.Buttons({
            createOrder: function(data, actions) {
              return fetch('/?action=paypal_create_order', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'csrf=' + encodeURIComponent(document.querySelector('input[name=\"csrf\"]').value) }).then(function(r) { return r.json(); }).then(function(d) { return d.id; });
            },
            onApprove: function(data, actions) {
              var form = document.querySelector('.checkout-form form');
              var fd = new FormData(form);
              fd.append('order_id', data.orderID);
              fd.append('csrf', document.querySelector('input[name=\"csrf\"]').value);
              return fetch('/?action=paypal_capture_order', { method: 'POST', body: new URLSearchParams(fd) }).then(function(r) { return r.json(); }).then(function(d) { if (d.success) window.location = d.redirect; });
            }
          }).render('#paypal-button-container');
        }
      } else {
        container.style.display = 'none';
        btn.style.display = '';
      }
    }
    toggleCashApp();
    togglePayPal();
    </script>
    <?php
    return ob_get_clean();
}

function render_order_confirmed(array $order, array $items): string
{
    $cashtag = '';
    $isCashApp = ($order['payment_method'] ?? '') === 'cash_app';
    if ($isCashApp) {
        $pmt = db()->query("SELECT extra_settings FROM payment_settings WHERE provider = 'cash_app'")->fetchColumn();
        if ($pmt) { $extra = json_decode($pmt, true); $cashtag = $extra['cashtag'] ?? ''; }
    }
    ob_start(); ?>
    <div class="panel order-confirmed" style="text-align:center">
      <div class="success-icon">✓</div>
      <h2>Thank You for Your Order!</h2>
      <p class="eyebrow">Order #<?= e($order['order_number']) ?></p>
      <p>A confirmation has been sent. You can track your order from your account dashboard.</p>
      <div class="actions" style="justify-content:center;margin-top:20px">
        <a href="/?page=account&tab=orders" class="button primary">View Orders</a>
        <a href="/?page=shop" class="button">Continue Shopping</a>
      </div>
    </div>

    <?php if ($isCashApp && $cashtag): ?>
      <div class="panel" style="margin-top:24px;text-align:center;border-color:var(--green)">
        <h3>Cash App Payment</h3>
        <p class="hint">Scan this QR code with the Cash App to complete your payment.</p>
        <div style="margin:16px 0">
          <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=https%3A%2F%2Fcash.app%2F%24<?= e(urlencode($cashtag)) ?>" alt="Cash App QR" style="border-radius:8px;max-width:250px">
        </div>
        <p><strong>Amount:</strong> $<?= e(number_format((float)$order['total'], 2)) ?></p>
        <p><strong>Pay to:</strong> <code>$<?= e($cashtag) ?></code></p>
        <p class="hint">Once paid, the status will be updated manually.</p>
      </div>
    <?php endif; ?>

    <div class="panel" style="margin-top:24px">
      <h3>Order Details</h3>
      <p><strong>Type:</strong> <span class="badge" style="background:<?= ($order['order_type'] ?? 'standard') === 'preorder' ? 'var(--cyan)' : 'var(--muted)' ?>"><?= e(ucfirst($order['order_type'] ?? 'standard')) ?></span></p>
      <p><strong>Status:</strong> <?= e(ucfirst($order['status'])) ?></p>
      <p><strong>Payment:</strong> <?= e(ucfirst(str_replace('_', ' ', $order['payment_method'] ?? 'Pending'))) ?></p>
      <p><strong>Total:</strong> $<?= e(number_format((float)$order['total'], 2)) ?></p>
      <h4 style="margin-top:16px">Items</h4>
      <?php foreach ($items as $item): ?>
        <div class="order-item-row">
          <span><?= e($item['product_name']) ?> × <?= (int)$item['quantity'] ?></span>
          <span>$<?= e(number_format((float)$item['line_total'], 2)) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

function render_account_dashboard(array $user, string $tab, array $recentOrders, array $allOrders, array $addresses, array $wishlist, array $devices, array $notifications): string
{
    ob_start(); ?>
    <div class="account-layout">
      <div class="account-sidebar">
        <div class="panel">
          <div class="account-avatar">
            <?php if (!empty($user['avatar'])): ?>
              <img src="<?= e($user['avatar']) ?>" alt="Avatar" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:2px solid var(--line)">
            <?php else: ?>
              <div class="avatar-placeholder"><?= e(strtoupper(substr($user['full_name'] ?: $user['username'], 0, 2))) ?></div>
            <?php endif; ?>
            <h3><?= e($user['full_name'] ?: $user['username']) ?></h3>
            <p class="hint"><?= e($user['email']) ?></p>
            <span class="badge"><?= e(ucfirst($user['role'])) ?></span>
          </div>
          <nav class="account-nav">
            <a href="/?page=account&tab=dashboard" class="<?= $tab === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
            <a href="/?page=account&tab=orders" class="<?= $tab === 'orders' ? 'active' : '' ?>">Orders</a>
            <a href="/?page=account&tab=profile" class="<?= $tab === 'profile' ? 'active' : '' ?>">Profile</a>
            <a href="/?page=account&tab=addresses" class="<?= $tab === 'addresses' ? 'active' : '' ?>">Addresses</a>
            <a href="/?page=account&tab=wishlist" class="<?= $tab === 'wishlist' ? 'active' : '' ?>">Wishlist</a>
            <a href="/?page=account&tab=notifications" class="<?= $tab === 'notifications' ? 'active' : '' ?>">Notifications</a>
            <a href="/?page=account&tab=devices" class="<?= $tab === 'devices' ? 'active' : '' ?>">Devices</a>
            <a href="/?page=account&tab=security" class="<?= $tab === 'security' ? 'active' : '' ?>">Security</a>
          </nav>
        </div>
      </div>

      <div class="account-content">
        <?php if ($tab === 'dashboard'): ?>
          <div class="panel">
            <h2>Welcome, <?= e($user['full_name'] ?: $user['username']) ?></h2>
            <p>Member since <?= e(date('F Y', strtotime($user['created_at']))) ?></p>
          </div>
          <div class="stats">
            <div><strong><?= e((string)count($allOrders)) ?></strong><span>Orders</span></div>
            <div><strong><?= e((string)count($wishlist)) ?></strong><span>Wishlist</span></div>
            <div><strong><?= e((string)count($addresses)) ?></strong><span>Addresses</span></div>
          </div>
          <?php if (!empty($recentOrders)): ?>
            <div class="panel">
              <h3>Recent Orders</h3>
              <table class="table">
                <tr><th>Order</th><th>Date</th><th>Total</th><th>Status</th><th></th></tr>
                <?php foreach ($recentOrders as $ord): ?>
                  <tr>
                    <td>#<?= e($ord['order_number']) ?></td>
                    <td><?= e(date('M j, Y', strtotime($ord['created_at']))) ?></td>
                    <td>$<?= e(number_format((float)$ord['total'], 2)) ?></td>
                    <td><span class="status-<?= e($ord['status']) ?>"><?= e(ucfirst($ord['status'])) ?></span></td>
                    <td><a href="/?page=account&tab=orders" class="button" style="padding:4px 10px;min-height:auto">View</a></td>
                  </tr>
                <?php endforeach; ?>
              </table>
            </div>
          <?php endif; ?>
          <div class="panel">
            <h3>Quick Links</h3>
            <div class="grid three" style="margin-top:12px">
              <a href="/?page=shop" class="button">Shop</a>
              <a href="/?page=account&tab=orders" class="button">Orders</a>
              <a href="/?page=account&tab=wishlist" class="button">Wishlist</a>
              <a href="/?page=account&tab=profile" class="button">Settings</a>
              <a href="/?page=account&tab=addresses" class="button">Addresses</a>
              <a href="/?page=account&tab=security" class="button">Security</a>
            </div>
          </div>

        <?php elseif ($tab === 'orders'): ?>
          <div class="panel">
            <h2>Order History</h2>
            <?php if (empty($allOrders)): ?>
              <p>No orders yet. <a href="/?page=shop">Start shopping</a></p>
            <?php else: ?>
              <table class="table">
                <tr><th>Order #</th><th>Type</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th><th>Tracking</th></tr>
                <?php foreach ($allOrders as $ord): ?>
                  <tr>
                    <td>#<?= e($ord['order_number']) ?></td>
                    <td><span class="badge" style="background:<?= ($ord['order_type'] ?? 'standard') === 'preorder' ? 'var(--cyan)' : 'var(--muted)' ?>;font-size:10px"><?= e(ucfirst($ord['order_type'] ?? 'standard')) ?></span></td>
                    <td><?= e(date('M j, Y', strtotime($ord['created_at']))) ?></td>
                    <td><?php $oi = db()->prepare('SELECT COUNT(*) FROM order_items WHERE order_id = ?')->execute([(int)$ord['id']]) ? (int)db()->query('SELECT COUNT(*) FROM order_items WHERE order_id = ' . (int)$ord['id'])->fetchColumn() : 0; echo $oi; ?></td>
                    <td>$<?= e(number_format((float)$ord['total'], 2)) ?></td>
                    <td><span class="status-<?= e($ord['status']) ?>"><?= e(ucfirst($ord['status'])) ?></span></td>
                    <td><?= $ord['tracking_number'] ? e($ord['tracking_number']) : '—' ?></td>
                  </tr>
                <?php endforeach; ?>
              </table>
            <?php endif; ?>
          </div>

        <?php elseif ($tab === 'profile'): ?>
          <div class="panel">
            <h2>Profile Settings</h2>
            <form method="post" class="form" style="max-width:500px" enctype="multipart/form-data">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="update_profile">
              <div class="account-avatar" style="text-align:center;margin-bottom:16px">
                <?php if (!empty($user['avatar'])): ?>
                  <img src="<?= e($user['avatar']) ?>" alt="Avatar" style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:2px solid var(--line);margin-bottom:8px">
                <?php else: ?>
                  <div class="avatar-placeholder" style="width:100px;height:100px;font-size:36px;margin:0 auto 8px"><?= e(strtoupper(substr($user['full_name'] ?: $user['username'], 0, 2))) ?></div>
                <?php endif; ?>
                <label style="display:block;font-size:13px;cursor:pointer;color:var(--accent)">Change Photo<input name="avatar" type="file" accept="image/*" style="display:none" onchange="this.closest('form').querySelector('.avatar-name').textContent=this.files[0].name"></label>
                <span class="avatar-name hint" style="font-size:11px"></span>
              </div>
              <label>Username<input value="<?= e($user['username']) ?>" disabled><span class="hint">Username cannot be changed</span></label>
              <label>Full Name<input name="full_name" value="<?= e($user['full_name'] ?? '') ?>" required></label>
              <label>Email<input value="<?= e($user['email']) ?>" disabled><span class="hint">Email cannot be changed</span></label>
              <label>Phone<input name="phone" value="<?= e($user['phone'] ?? '') ?>"></label>
              <label>Bio<textarea name="bio" placeholder="Tell us about yourself..."><?= e($user['bio'] ?? '') ?></textarea></label>
              <button class="button primary" type="submit">Save Changes</button>
            </form>
          </div>

        <?php elseif ($tab === 'addresses'): ?>
          <div class="panel">
            <h2>Saved Addresses</h2>
            <?php foreach ($addresses as $addr): ?>
              <div class="address-card panel" style="margin-bottom:12px">
                <strong><?= e($addr['label']) ?></strong>
                <p><?= e($addr['full_name'] ?? $user['full_name']) ?></p>
                <p><?= e($addr['street_line1']) ?><?= $addr['street_line2'] ? ', ' . e($addr['street_line2']) : '' ?></p>
                <p><?= e($addr['city']) ?>, <?= e($addr['state']) ?> <?= e($addr['postal_code']) ?></p>
                <p><?= e($addr['country']) ?></p>
                <?php if ($addr['is_default_shipping']): ?><span class="badge">Default Shipping</span><?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="panel">
            <h3>Add New Address</h3>
            <form method="post" class="form" style="max-width:500px">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="add_address">
              <label>Label<input name="label" placeholder="Home, Work, etc."></label>
              <label>Full Name<input name="full_name" required></label>
              <label>Phone<input name="phone"></label>
              <label>Street Address<input name="street_line1" required></label>
              <label>Apt/Suite<input name="street_line2"></label>
              <div class="form-row">
                <label>City<input name="city" required></label>
                <label>State<input name="state" required></label>
              </div>
              <div class="form-row">
                <label>Postal Code<input name="postal_code" required></label>
                <label>Country<input name="country" value="United States"></label>
              </div>
              <button class="button primary" type="submit">Add Address</button>
            </form>
          </div>

        <?php elseif ($tab === 'wishlist'): ?>
          <div class="panel">
            <h2>Wishlist</h2>
            <?php if (empty($wishlist)): ?>
              <p>Your wishlist is empty. <a href="/?page=shop">Browse products</a></p>
            <?php else: ?>
              <div class="product-grid">
                <?php foreach ($wishlist as $item): ?>
                  <article class="product-card">
                    <a href="/?page=product&slug=<?= e($item['slug']) ?>">
                      <div class="product-image" style="background-image:url('<?= e(json_decode($item['images'], true)[0] ?? '/assets/img/background.png') ?>')"></div>
                      <h3><?= e($item['name']) ?></h3>
                      <div class="product-meta">
                        <strong>$<?= e(number_format((float)($item['sale_price'] ?: $item['price']), 2)) ?></strong>
                      </div>
                    </a>
                    <form method="post">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="remove_wishlist">
                      <input type="hidden" name="product_id" value="<?= (int)$item['product_id'] ?>">
                      <button class="button" type="submit">Remove</button>
                    </form>
                  </article>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

        <?php elseif ($tab === 'notifications'): ?>
          <div class="panel">
            <h2>Notifications</h2>
            <?php if (empty($notifications)): ?>
              <p>No notifications yet.</p>
            <?php else: ?>
              <?php foreach ($notifications as $notif): ?>
                <div class="notification-item <?= $notif['is_read'] ? '' : 'unread' ?>">
                  <strong><?= e($notif['title']) ?></strong>
                  <p><?= e($notif['message']) ?></p>
                  <small><?= e(date('M j, Y g:i A', strtotime($notif['created_at']))) ?></small>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

        <?php elseif ($tab === 'devices'): ?>
          <div class="panel">
            <h2>Device Login History</h2>
            <?php if (empty($devices)): ?>
              <p>No devices recorded.</p>
            <?php else: ?>
              <table class="table">
                <tr><th>Device / Browser</th><th>IP Address</th><th>Last Seen</th><th>Trusted</th></tr>
                <?php foreach ($devices as $dev): ?>
                  <tr>
                    <td><?= e(substr($dev['device_name'], 0, 60)) ?></td>
                    <td><?= e($dev['ip_address']) ?></td>
                    <td><?= e(date('M j, Y g:i A', strtotime($dev['last_seen_at']))) ?></td>
                    <td><?= $dev['is_trusted'] ? 'Yes' : '—' ?></td>
                  </tr>
                <?php endforeach; ?>
              </table>
            <?php endif; ?>
          </div>

        <?php elseif ($tab === 'security'): ?>
          <div class="panel">
            <h2>Security Settings</h2>
            <form method="post" class="form" style="max-width:500px">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="change_password">
              <label>Current Password<input name="current_password" type="password" required></label>
              <label>New Password<input name="new_password" type="password" required minlength="8"></label>
              <label>Confirm New Password<input name="confirm_password" type="password" required></label>
              <button class="button primary" type="submit">Update Password</button>
            </form>
            <hr style="border-color:var(--line-soft);margin:24px 0">
            <h3>Two-Factor Authentication</h3>
            <p>Enhance your account security with 2FA (coming in Phase 2).</p>
            <?php if ($user['two_factor_enabled']): ?>
              <span class="badge">2FA Enabled</span>
            <?php else: ?>
              <p class="hint">Two-factor authentication is not yet configured.</p>
            <?php endif; ?>
            <hr style="border-color:var(--line-soft);margin:24px 0">
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="logout">
              <button class="button" type="submit" style="border-color:rgba(255,76,76,0.5)">Sign Out of All Devices</button>
            </form>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php
    return ob_get_clean();
}

function render_receipt(array $transaction, array $order, array $orderItems): string
{
    $isPos = empty($order) && $transaction['type'] === 'sale' && empty($transaction['order_id']);
    $posItems = [];
    if ($isPos) {
        $decoded = json_decode($transaction['description'] ?? '[]', true);
        $posItems = is_array($decoded) ? $decoded : [];
    }
    ob_start();
    ?>
    <!DOCTYPE html>
    <html><head>
    <meta charset="utf-8">
    <title>Receipt #<?= (int)$transaction['id'] ?></title>
    <style>
      * { margin:0; padding:0; box-sizing:border-box; }
      body { font-family:'Courier New',monospace; font-size:12px; padding:20px; color:#000; }
      .receipt { max-width:300px; margin:0 auto; }
      h1 { font-size:18px; text-align:center; margin-bottom:4px; }
      .sub { text-align:center; font-size:11px; margin-bottom:12px; }
      hr { border:none; border-top:1px dashed #000; margin:8px 0; }
      table { width:100%; border-collapse:collapse; }
      th, td { text-align:left; padding:3px 0; }
      .right { text-align:right; }
      .total-row td { font-weight:700; border-top:1px dashed #000; padding-top:6px; }
      .footer { text-align:center; margin-top:12px; font-size:10px; }
      .print-btn { display:block; width:100%; padding:10px; margin-top:16px; font-size:14px; }
      @media print { .print-btn { display:none; } }
    </style>
    </head><body>
    <div class="receipt">
      <h1>Suggawayz</h1>
      <div class="sub"><?= $isPos ? 'POS Sale' : 'Online Store' ?><br>Receipt #<?= (int)$transaction['id'] ?></div>
      <hr>
      <p><strong>Date:</strong> <?= e(date('M j, Y g:i A', strtotime($transaction['created_at']))) ?></p>
      <?php if (!$isPos): ?>
        <p><strong>Order:</strong> #<?= e($order['order_number'] ?? '—') ?></p>
      <?php endif; ?>
      <p><strong>Payment:</strong> <?= e(ucfirst($transaction['payment_method'] ?? '—')) ?></p>
      <?php if (!$isPos && $order['shipping_method']): ?>
        <p><strong>Shipping:</strong> <?= e($order['shipping_method']) ?></p>
      <?php endif; ?>
      <hr>
      <table>
        <tr><th>Item</th><th class="right">Qty</th><th class="right">Price</th></tr>
        <?php if ($isPos): ?>
          <?php foreach ($posItems as $item): ?>
            <tr>
              <td><?= e($item['name']) ?></td>
              <td class="right"><?= (int)$item['qty'] ?></td>
              <td class="right">$<?= e(number_format((float)$item['price'] * (int)$item['qty'], 2)) ?></td>
            </tr>
          <?php endforeach; ?>
          <tr class="total-row"><td colspan="2">Total</td><td class="right">$<?= e(number_format((float)$transaction['amount'], 2)) ?></td></tr>
        <?php else: ?>
          <?php foreach ($orderItems as $item): ?>
            <tr>
              <td><?= e($item['product_name']) ?><?= $item['size'] ? ' ('.e($item['size']).')' : '' ?></td>
              <td class="right"><?= (int)$item['quantity'] ?></td>
              <td class="right">$<?= e(number_format((float)$item['line_total'], 2)) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if ($order): ?>
            <tr class="total-row"><td colspan="2">Subtotal</td><td class="right">$<?= e(number_format((float)$order['subtotal'], 2)) ?></td></tr>
            <?php if ((float)$order['discount'] > 0): ?>
              <tr><td colspan="2">Discount</td><td class="right">-$<?= e(number_format((float)$order['discount'], 2)) ?></td></tr>
            <?php endif; ?>
            <?php if ((float)$order['tax'] > 0): ?>
              <tr><td colspan="2">Tax</td><td class="right">$<?= e(number_format((float)$order['tax'], 2)) ?></td></tr>
            <?php endif; ?>
            <?php if ((float)$order['shipping'] > 0): ?>
              <tr><td colspan="2">Shipping</td><td class="right">$<?= e(number_format((float)$order['shipping'], 2)) ?></td></tr>
            <?php endif; ?>
            <tr class="total-row"><td colspan="2">Total</td><td class="right">$<?= e(number_format((float)$order['total'], 2)) ?></td></tr>
          <?php endif; ?>
        <?php endif; ?>
      </table>
      <hr>
      <div class="footer">
        <p>Thank you for your purchase!</p>
        <p>Suggawayz</p>
      </div>
      <button class="print-btn" onclick="window.print()">Print Receipt</button>
    </div>
    <script>window.onload = function() { setTimeout(function() { window.print(); }, 500); }</script>
    </body></html>
    <?php
    return ob_get_clean();
}

function render_pos_end_of_day(array $session, array $transactions, array $employee): string
{
    ob_start();
    $opening = (float)$session['opening_balance'];
    $closing = (float)($session['closing_balance'] ?? 0);
    $tIn = 0; $tOut = 0;
    foreach ($transactions as $t) {
        if (in_array($t['type'], ['cash_in', 'sale'])) $tIn += (float)$t['amount'];
        else $tOut += (float)$t['amount'];
    }
    ?>
    <!DOCTYPE html>
    <html><head>
    <meta charset="utf-8">
    <title>End of Day — Session #<?= (int)$session['id'] ?></title>
    <style>
      * { margin:0; padding:0; box-sizing:border-box; }
      body { font-family:'Courier New',monospace; font-size:12px; padding:20px; color:#000; }
      .report { max-width:350px; margin:0 auto; }
      h1 { font-size:16px; text-align:center; margin-bottom:2px; }
      h2 { font-size:13px; text-align:center; margin-bottom:4px; }
      .sub { text-align:center; font-size:10px; margin-bottom:12px; }
      hr { border:none; border-top:1px dashed #000; margin:6px 0; }
      table { width:100%; border-collapse:collapse; }
      th, td { text-align:left; padding:2px 0; font-size:11px; }
      .right { text-align:right; }
      .bold { font-weight:700; }
      .big { font-size:24px; font-weight:800; text-align:center; margin:8px 0; }
      .footer { text-align:center; margin-top:12px; font-size:9px; }
      .print-btn { display:block; width:100%; padding:10px; margin-top:16px; font-size:14px; }
      @media print { .print-btn { display:none; } }
    </style>
    </head><body>
    <div class="report">
      <h1>SUGGAWAYZ</h1>
      <h2>End of Day Report</h2>
      <div class="sub">
        Session #<?= (int)$session['id'] ?><br>
        <?= e(date('M j, Y', strtotime($session['opened_at']))) ?>
      </div>
      <hr>
      <p><strong>Employee:</strong> <?= e($employee['full_name'] ?: $employee['username']) ?></p>
      <p><strong>Opened:</strong> <?= e(date('g:i A', strtotime($session['opened_at']))) ?></p>
      <p><strong>Closed:</strong> <?= $session['closed_at'] ? e(date('g:i A', strtotime($session['closed_at']))) : '—' ?></p>
      <hr>
      <p><strong>Opening Balance:</strong> $<?= e(number_format($opening, 2)) ?></p>
      <div class="big">$<?= e(number_format($closing, 2)) ?></div>
      <p style="text-align:center;font-size:10px">Closing Balance</p>
      <hr>
      <table>
        <tr><th>Type</th><th class="right">Count</th><th class="right">Total</th></tr>
        <?php
        $grouped = [];
        foreach ($transactions as $t) {
            $type = $t['type'];
            if (!isset($grouped[$type])) $grouped[$type] = ['count' => 0, 'total' => 0];
            $grouped[$type]['count']++;
            $grouped[$type]['total'] += (float)$t['amount'];
        }
        foreach ($grouped as $type => $g):
        ?>
          <tr>
            <td><?= e(ucfirst(str_replace('_', ' ', $type))) ?></td>
            <td class="right"><?= (int)$g['count'] ?></td>
            <td class="right">$<?= e(number_format($g['total'], 2)) ?></td>
          </tr>
        <?php endforeach; ?>
        <tr class="bold"><td colspan="2">Net Total</td><td class="right">$<?= e(number_format($tIn - $tOut, 2)) ?></td></tr>
      </table>
      <hr>
      <div class="footer">
        <p>End of Day Report &mdash; SUGGAWAYZ</p>
      </div>
      <button class="print-btn" onclick="window.print()">Print Report</button>
    </div>
    <script>window.onload = function() { setTimeout(function() { window.print(); }, 500); }</script>
    </body></html>
    <?php
    return ob_get_clean();
}

function render_bug_report_form(): string
{
    ob_start(); ?>
    <section class="page-title">
      <div class="container">
        <h1>Report a Bug / Debug</h1>
        <p class="hint">Found something wrong? Let us know so we can fix it.</p>
      </div>
    </section>
    <section class="container" style="max-width:600px;margin-top:24px">
      <form method="post" class="form panel" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="bug_report">
        <label>Your Name (optional)<input name="reporter_name" placeholder="John Doe"></label>
        <label>Email (for follow-up)<input name="reporter_email" type="email" placeholder="you@example.com"></label>
        <label>Subject *<input name="subject" required placeholder="Brief description of the issue"></label>
        <label>Description *<textarea name="description" required rows="5" placeholder="Tell us what happened, steps to reproduce, etc."></textarea></label>
        <label>Page URL (where it happened)<input name="page_url" placeholder="https://suggawayz.com/page"></label>
        <label>Screenshot (optional)<input name="screenshot" type="file" accept="image/*"></label>
        <button class="button primary" type="submit">Submit Bug Report</button>
      </form>
    </section>
    <?php
    return ob_get_clean();
}

function render_membership_page(array $plans, ?array $userMembership): string
{
    ob_start(); ?>
    <section class="page-title">
      <div class="container">
        <h1>🔥 Sugga Gang Membership</h1>
        <p class="hint">Join the gang. Get early access, exclusive gear, and monthly perks.</p>
      </div>
    </section>
    <section class="container" style="margin-top:24px">
      <?php if ($userMembership && $userMembership['status'] === 'active'): ?>
        <div class="panel" style="border-color:var(--green);text-align:center">
          <h2>You're a Member! 🎉</h2>
          <p><?= e($userMembership['plan_name']) ?> — Active since <?= e(date('F j, Y', strtotime($userMembership['start_date']))) ?></p>
          <p class="hint"><?= $userMembership['auto_pay'] ? 'Auto-pay is ON' : 'Auto-pay is OFF' ?></p>
        </div>
      <?php endif; ?>
      <div class="product-grid">
        <?php foreach ($plans as $plan): ?>
          <div class="panel product-card" style="text-align:center;padding:32px 20px">
            <h2 style="font-size:24px;margin-bottom:8px"><?= e($plan['name']) ?></h2>
            <p style="font-size:36px;font-weight:800;color:var(--cyan);margin:16px 0">$<?= e(number_format((float)$plan['price'], 2)) ?><span style="font-size:14px;color:var(--muted)">/<?= e($plan['billing_interval'] ?? 'monthly') ?></span></p>
            <p style="margin:12px 0"><?= e($plan['description']) ?></p>
            <ul style="text-align:left;margin:16px 0;list-style:none;padding:0">
              <?php $benefits = json_decode($plan['benefits'] ?? '[]', true); ?>
              <?php foreach ($benefits as $b): ?>
                <li style="padding:4px 0">✅ <?= e($b) ?></li>
              <?php endforeach; ?>
            </ul>
            <?php if ($userMembership && $userMembership['status'] === 'active'): ?>
              <span class="badge" style="background:var(--green)">Enrolled</span>
            <?php else: ?>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="join_membership">
                <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
                <button class="button primary" type="submit" style="width:100%">Join Now</button>
                <label style="display:flex;align-items:center;gap:6px;justify-content:center;margin-top:8px;font-size:12px;color:var(--muted);cursor:pointer">
                  <input type="checkbox" name="auto_pay" value="1" checked> Auto-pay monthly
                </label>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php
    return ob_get_clean();
}

function render_size_guide(?array $page, array $sizeCharts): string
{
    ob_start(); ?>
    <section class="page-title">
      <div class="container">
        <h1>📏 Size Guide</h1>
        <p class="hint">Find your perfect fit with our size charts.</p>
      </div>
    </section>
    <section class="container" style="margin-top:24px">
      <?php if (!empty($page)): ?>
        <div class="panel" style="max-width:800px"><?= $page['content'] ?></div>
      <?php endif; ?>
      <?php foreach ($sizeCharts as $chart): $data = json_decode($chart['data'], true) ?: []; ?>
        <div class="panel" style="margin-top:16px;overflow-x:auto">
          <h3 style="margin-bottom:12px"><?= e($chart['name']) ?></h3>
          <table class="table" style="font-size:13px;min-width:400px">
            <tr><th>Size</th><th>Chest</th><th>Waist</th><th>Hips</th><th>Length</th></tr>
            <?php foreach ($data as $row): ?>
              <tr><td><strong><?= e($row['size'] ?? '') ?></strong></td><td><?= e($row['chest'] ?? '—') ?></td><td><?= e($row['waist'] ?? '—') ?></td><td><?= e($row['hips'] ?? '—') ?></td><td><?= e($row['length'] ?? '—') ?></td></tr>
            <?php endforeach; ?>
          </table>
        </div>
      <?php endforeach; ?>
      <?php if (empty($sizeCharts)): ?>
        <div class="panel" style="text-align:center"><p class="hint">No size charts available yet.</p></div>
      <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
}
