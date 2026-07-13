<?php
$path = '/home/suggawayz/public_html/public/index.php';
$code = file_get_contents($path);

$old = "    case 'forgot-password':";
$new = "    case 'coupons':
        \$coupons = db()->query(\"SELECT code, discount_type, discount_value, min_order_amount, starts_at, ends_at FROM coupons WHERE active=1 AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW()) ORDER BY discount_value DESC\")->fetchAll();
        \$hero_content = '<p class=\"eyebrow\">Save Money</p><h1>🏷️ Coupons & Discounts</h1>';
        \$content = render_coupons_page(\$coupons);
        break;

    case 'forgot-password':";

$code = str_replace($old, $new, $code, $c1);
echo "Page route: $c1\n";
file_put_contents($path, $code);

// Add render function to render.php
$rpath = '/home/suggawayz/public_html/app/Views/render.php';
$rcode = file_get_contents($rpath);
$func = "\n\nfunction render_coupons_page(array \$coupons): string\n{\n    ob_start(); ?>\n    <section class=\"container\" style=\"margin-top:24px\">\n      <?php if (empty(\$coupons)): ?>\n        <div class=\"panel\" style=\"text-align:center;padding:40px\"><h2>No active coupons right now</h2><p class=\"hint\">Check back later.</p></div>\n      <?php else: ?>\n        <div class=\"product-grid\" style=\"grid-template-columns:repeat(auto-fill,minmax(280px,1fr))\">\n        <?php foreach (\$coupons as \$c): \$val = \$c['discount_type'] === 'percent' ? \$c['discount_value'] . '% OFF' : '\$' . number_format((float)\$c['discount_value'], 2) . ' OFF'; ?>\n          <div class=\"panel\" style=\"text-align:center;padding:24px;border-color:var(--cyan);background:rgba(0,200,255,0.03)\">\n            <div style=\"font-size:28px;font-weight:800;color:var(--cyan);letter-spacing:2px;font-family:mono;padding:12px;background:rgba(0,0,0,0.2);border-radius:6px;border:1px dashed var(--cyan)\"><?= e(\$c['code']) ?></div>\n            <div style=\"font-size:22px;font-weight:700;color:var(--green);margin:8px 0\"><?= e(\$val) ?></div>\n            <?php if (\$c['min_order_amount']): ?><p class=\"hint\">Min: \$<?= e(number_format((float)\$c['min_order_amount'], 2)) ?></p><?php endif; ?>\n            <?php if (\$c['ends_at']): ?><p class=\"hint\">Exp: <?= e(date('M j, Y', strtotime(\$c['ends_at']))) ?></p><?php endif; ?>\n            <button class=\"button primary\" style=\"margin-top:12px;width:100%\" onclick=\"navigator.clipboard.writeText('<?= e(\$c['code']) ?>');this.textContent='Copied!';\">Copy Code</button>\n          </div>\n        <?php endforeach; ?>\n        </div>\n      <?php endif; ?>\n    </section>\n    <?php\n    return ob_get_clean();\n}\n";
$rcode = rtrim($rcode) . $func;
file_put_contents($rpath, $rcode);
echo "Render function added\n";

exec("php -l $path 2>&1", $out, $rc);
echo implode("\n", $out) . "\n";
exec("php -l $rpath 2>&1", $out, $rc);
echo implode("\n", $out) . "\n";
