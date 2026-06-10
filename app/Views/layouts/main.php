<?php
$footerTagline = site_setting('footer_tagline', 'Futuristic streetwear for the next generation. Be different. Be you.');
$siteIcon = site_setting('site_icon_text', 'SW');
$socialRaw = site_setting('social_links', '{}');
$socialLinks = json_decode($socialRaw, true) ?: [];
$socialIcons = ['instagram'=>'IG','tiktok'=>'TK','twitter'=>'X','youtube'=>'YT','facebook'=>'FB'];
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="description" content="<?= e($seo_description ?? 'SUGGAWAYZ — Futuristic Streetwear') ?>">
  <?php if (isset($seo_title)): ?><title><?= e($seo_title) ?></title><?php endif; ?>
  <meta property="og:title" content="<?= e($seo_title ?? 'SUGGAWAYZ') ?>">
  <meta property="og:description" content="<?= e($seo_description ?? 'Futuristic streetwear for the next generation.') ?>">
  <meta property="og:image" content="<?= e($og_image ?? '/assets/img/og-default.png') ?>">
  <?php if (isset($canonical_url)): ?><link rel="canonical" href="<?= e($canonical_url) ?>"><?php endif; ?>
  <link rel="icon" href="/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <img src="/assets/img/header2.jpg" alt="Header" style="width:100%;display:block;max-height:400px;object-fit:cover">
  <header class="header">
    <div class="header-inner">

      <nav class="nav">
        <a href="/">⌂ Home</a>
        <a href="/?page=shop">🛒 Shop</a>
        <a href="/?page=shop&category=sugga-gang-member">🔥 Join Sugga Gang</a>
        <a href="/?page=collections">📦 Collections</a>
        <a href="/?page=new-drops">🔥 New Drops</a>
        <a href="/?page=lookbook">📸 Events</a>
        <a href="/?page=about">ℹ About</a>
        <a href="/?page=blog">📝 Blog</a>
        <a href="/?page=cart" class="cart-link">🛒 Cart <?php if (cart_count() > 0): ?><span class="cart-badge"><?= e((string)cart_count()) ?></span><?php endif; ?></a>
        <?php if ($user): ?>
          <a href="/?page=account" class="nav-account">👤 <?= e($user['full_name'] ?: $user['username']) ?></a>
          <?php if (is_admin($user)): ?><a href="/?page=admin" class="nav-admin">⚙ Admin</a><?php endif; ?>
          <form method="post" class="inline-form" style="margin:0"><input type="hidden" name="action" value="logout"><?= csrf_field() ?><button class="button" style="padding:4px 8px;min-height:auto;font-size:11px">🚪 Logout</button></form>
        <?php else: ?>
          <a href="/?page=login" class="nav-account">🔑 Sign In</a>
        <?php endif; ?>
      </nav>
      <button class="menu-toggle" aria-label="Menu">☰</button>
    </div>
  </header>

  <?php if (site_setting('maintenance_mode')): ?>
    <div id="maintBanner" style="text-align:center;padding:12px;background:rgba(0,200,255,0.08);border-bottom:1px solid rgba(0,200,255,0.2);color:var(--cyan);font-size:13px;font-family:var(--mono);line-height:1.7">
      <span id="maintText"></span>
    </div>
    <script>
    (function(){
      var lines = [
        '> SYSTEM INITIALIZING...',
        '> Some features are still under construction while we finalize the SUGGAWAYZ experience.',
        '> If you need to place an order, feel free to contact us via Facebook.'
      ];
      var el = document.getElementById('maintText');
      var lineIdx = 0, charIdx = 0;
      function type() {
        if (lineIdx >= lines.length) return;
        var line = lines[lineIdx];
        if (charIdx < line.length) {
          el.textContent += line[charIdx];
          charIdx++;
          setTimeout(type, 25 + Math.random() * 30);
        } else {
          el.textContent += '\n';
          lineIdx++;
          charIdx = 0;
          setTimeout(type, 400);
        }
      }
      type();
    })();
    </script>
  <?php endif; ?>
  <?php $flashNotice = session_flash('notice'); $flashError = session_flash('error'); ?>
  <?php if ($flashNotice): ?>
    <div class="flash flash-ok" style="text-align:center;padding:10px;background:rgba(0,255,136,0.1);border-bottom:1px solid rgba(0,255,136,0.3);color:var(--green);font-size:13px"><?= e($flashNotice) ?></div>
  <?php endif; ?>
  <?php if ($flashError): ?>
    <div class="flash flash-bad" style="text-align:center;padding:10px;background:rgba(255,76,76,0.1);border-bottom:1px solid rgba(255,76,76,0.3);color:var(--red);font-size:13px"><?= e($flashError) ?></div>
  <?php endif; ?>
  <main class="main <?= $hero_class ?? '' ?>">
    <?= $content ?? '' ?>
  </main>

  <footer class="footer">
    <div class="footer-inner">
      <div class="footer-grid">
        <div class="footer-col">
          <div class="brand">
            <span class="brand-mark"><?= e($siteIcon) ?></span>
            <span>SUGGAWAYZ</span>
          </div>
          <p><?= e($footerTagline) ?></p>
          <div class="social-links">
            <?php foreach ($socialLinks as $platform => $cfg): if (empty($cfg['enabled']) || empty($cfg['url'])) continue; ?>
              <a href="<?= e($cfg['url']) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucfirst($platform)) ?>"><?= e($socialIcons[$platform] ?? strtoupper(substr($platform,0,2))) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="footer-col">
          <h4>Shop</h4>
          <a href="/?page=shop">🛒 All Products</a>
          <a href="/?page=shop&category=sugga-gang-member">🔥 Join Sugga Gang</a>
          <a href="/?page=collections">📦 Collections</a>
          <a href="/?page=new-drops">🔥 New Drops</a>
          <a href="/?page=lookbook">📸 Events</a>
        </div>
        <div class="footer-col">
          <h4>Customer Care</h4>
          <a href="/?page=contact">📧 Contact</a>
          <a href="/?page=shipping">🚚 Shipping</a>
          <a href="/?page=returns">↩ Returns</a>
          <a href="/?page=size-guide">📏 Size Guide</a>
          <a href="/?page=bug-report">🐛 Report a Bug</a>
        </div>
        <div class="footer-col">
          <h4>Company</h4>
          <a href="/?page=about">ℹ️ About</a>
          <a href="/?page=blog">📝 Blog</a>
          <a href="/?page=terms">📜 Terms</a>
          <a href="/?page=privacy">🔒 Privacy</a>
          <a href="/?page=webmaster">👤 Webmaster</a>
        </div>
        <div class="footer-col">
          <h4>Stay in the Loop</h4>
          <p><?= e(site_setting('hero_subscribe', 'Subscribe for exclusive drops and early access.')) ?></p>
          <form method="post" action="/?page=subscribe" class="footer-form">
            <?= csrf_field() ?>
            <input type="email" name="email" placeholder="Your email" required>
            <button type="submit" class="button primary">Subscribe</button>
          </form>
        </div>
      </div>
      <div class="footer-bottom">
        <p>&copy; <?= date('Y') ?> SUGGAWAYZ. All rights reserved.</p>
      </div>
    </div>
  </footer>

  <script src="/assets/js/app.js"></script>
</body>
</html>
