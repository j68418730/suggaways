INSERT IGNORE INTO categories (name, slug, description, sort_order, active) VALUES
('Hoodies', 'hoodies', 'Heavyweight hoodies and crewnecks with futuristic detailing.', 1, 1),
('T-Shirts', 't-shirts', 'Graphic tees and essential everyday wear.', 2, 1),
('Jackets', 'jackets', 'Techwear outerwear with premium materials.', 3, 1),
('Pants', 'pants', 'Cargo pants, joggers, and street-ready bottoms.', 4, 1),
('Accessories', 'accessories', 'Hats, bags, masks, and cyber accessories.', 5, 1),
('New Drops', 'new-drops', 'Limited edition drops and new arrivals.', 0, 1);

INSERT IGNORE INTO products (name, sku, slug, description, short_description, seo_description, price, sale_price, sizes, colors, images, is_featured, is_new, status, category_id) VALUES
('Shadow Hoodie', 'SW-HOOD-001', 'shadow-hoodie', 'Heavyweight black cyberwear hoodie with blue chrome detailing and oversized streetwear fit. Features ribbed cuffs, adjustable drawcord hood, and premium fleece interior.', 'Premium black hoodie with cyber details.', 'Premium black SUGGAWAYZ Shadow Hoodie with futuristic blue details.', 89.99, NULL, '[\"S\",\"M\",\"L\",\"XL\",\"2XL\"]', '[\"Black\",\"Chrome Blue\"]', '[\"/assets/img/products/shadow-hoodie-1.png\"]', 1, 0, 'active', 1),
('Cyber Tee', 'SW-TEE-002', 'cyber-tee', 'Soft graphic tee built for daily wear, drops, shows, and late-night city movement. Features screen-printed front graphic with reflective ink detailing.', 'Graphic tee with reflective detailing.', 'SUGGAWAYZ Cyber Tee futuristic streetwear graphic shirt.', 49.99, 39.99, '[\"S\",\"M\",\"L\",\"XL\"]', '[\"Black\",\"White\",\"Blue\"]', '[\"/assets/img/products/cyber-tee-1.png\"]', 1, 0, 'active', 2),
('Future Jacket', 'SW-JKT-003', 'future-jacket', 'Structured techwear jacket with reflective panels, deep pockets, and storm-ready fabric. Water-resistant shell with breathable mesh lining.', 'Techwear jacket with reflective panels.', 'SUGGAWAYZ Future Jacket black techwear outerwear.', 149.99, NULL, '[\"M\",\"L\",\"XL\",\"2XL\"]', '[\"Black\",\"Graphite\"]', '[\"/assets/img/products/future-jacket-1.png\"]', 1, 0, 'active', 3),
('Neon Joggers', 'SW-PNT-004', 'neon-joggers', 'Slim-fit joggers with neon reflective stripes, zip pockets, and elastic cuffs. Built for movement and style.', 'Slim joggers with neon stripes.', 'SUGGAWAYZ Neon Joggers street-ready bottoms.', 74.99, 59.99, '[\"S\",\"M\",\"L\",\"XL\"]', '[\"Black\",\"Gray\"]', '[\"/assets/img/products/neon-joggers-1.png\"]', 1, 0, 'active', 4),
('Holographic Cap', 'SW-ACC-005', 'holographic-cap', 'Six-panel cap with holographic SUGGAWAYZ logo, adjustable strap, and breathable mesh back.', 'Holographic logo cap.', 'SUGGAWAYZ Holographic Cap accessory.', 34.99, NULL, '[\"OS\"]', '[\"Black\",\"White\"]', '[\"/assets/img/products/holo-cap-1.png\"]', 0, 0, 'active', 5),
('Cybermask Pack', 'SW-ACC-006', 'cybermask', 'Reusable face mask with cyber print, filter pocket, and adjustable ear loops. Pack of 3.', 'Pack of 3 cyber print masks.', 'SUGGAWAYZ Cybermask pack accessories.', 24.99, 19.99, '[\"OS\"]', '[\"Black\",\"Blue\",\"White\"]', '[\"/assets/img/products/cybermask-1.png\"]', 0, 0, 'active', 5),
('Cargo Tech Pants', 'SW-PNT-007', 'cargo-tech-pants', 'Technical cargo pants with 8 pockets, knee articulation, and DWR coating. Tapered fit with zip hem.', 'Technical cargo pants with 8 pockets.', 'SUGGAWAYZ Cargo Tech Pants streetwear bottoms.', 119.99, 89.99, '[\"S\",\"M\",\"L\",\"XL\",\"2XL\"]', '[\"Black\",\"Olive\"]', '[\"/assets/img/products/cargo-tech-1.png\"]', 1, 0, 'active', 4),
('Neon Genesis Hoodie', 'SW-LTD-008', 'neon-genesis-hoodie', 'Limited edition run of 500. UV-reactive neon print on heavyweight black fleece. Each piece individually numbered.', 'Limited edition UV-reactive hoodie.', 'SUGGAWAYZ Neon Genesis limited drop hoodie.', 129.99, NULL, '[\"S\",\"M\",\"L\",\"XL\",\"2XL\"]', '[\"Black\"]', '[\"/assets/img/products/neon-genesis-1.png\"]', 1, 1, 'active', 6),
('Phantom Mesh Tee', 'SW-TEE-009', 'phantom-mesh-tee', 'Breathable mesh tee with phantom gradient print. Perfect for layering or standalone wear.', 'Mesh tee with phantom gradient.', 'SUGGAWAYZ Phantom Mesh Tee.', 44.99, NULL, '[\"S\",\"M\",\"L\",\"XL\"]', '[\"Black\",\"White\"]', '[\"/assets/img/products/phantom-mesh-1.png\"]', 0, 1, 'active', 2),
('Signal Blue Hoodie', 'SW-HOOD-010', 'signal-blue-hoodie', 'Vibrant signal blue hoodie with tonal embroidery and oversized cut. Made from 400gsm organic cotton fleece.', 'Signal blue oversized hoodie.', 'SUGGAWAYZ Signal Blue Hoodie.', 99.99, NULL, '[\"S\",\"M\",\"L\",\"XL\",\"2XL\"]', '[\"Signal Blue\"]', '[\"/assets/img/products/signal-blue-1.png\"]', 1, 1, 'active', 1);

INSERT IGNORE INTO inventory (product_id, warehouse, stock_quantity, low_stock_threshold, restock_alert)
SELECT id, 'Main Warehouse',
  CASE sku
    WHEN 'SW-HOOD-001' THEN 48 WHEN 'SW-TEE-002' THEN 120 WHEN 'SW-JKT-003' THEN 18
    WHEN 'SW-PNT-004' THEN 35 WHEN 'SW-ACC-005' THEN 60 WHEN 'SW-ACC-006' THEN 90
    WHEN 'SW-PNT-007' THEN 25 WHEN 'SW-LTD-008' THEN 42 WHEN 'SW-TEE-009' THEN 75
    WHEN 'SW-HOOD-010' THEN 30 ELSE 50
  END, 15, 0
FROM products;

INSERT IGNORE INTO coupons (code, discount_type, discount_value, min_order_amount, max_uses, active) VALUES
('DROP10', 'percent', 10.00, 25.00, 500, 1),
('SUGGAWAYZ25', 'fixed', 25.00, 75.00, 200, 1),
('FREESHIP', 'fixed', 7.99, 75.00, NULL, 1),
('WELCOME20', 'percent', 20.00, 50.00, 1000, 1);

INSERT IGNORE INTO seo_settings (page_key, meta_title, meta_description, canonical_url, og_image) VALUES
('home', 'SUGGAWAYZ | Futuristic Streetwear Brand', 'Premium cyberwear apparel for creators, trendsetters, and the next generation.', '/', '/assets/img/og-default.png'),
('shop', 'Shop SUGGAWAYZ — Futuristic Streetwear Clothing', 'Shop the latest hoodies, tees, jackets, joggers, and accessories.', '/?page=shop', '/assets/img/og-default.png'),
('collections', 'Collections — SUGGAWAYZ', 'Explore SUGGAWAYZ collections: hoodies, tees, jackets, pants, accessories, and limited drops.', '/?page=collections', '/assets/img/og-default.png'),
('lookbook', 'Lookbook — SUGGAWAYZ', 'Browse the SUGGAWAYZ lookbook.', '/?page=lookbook', '/assets/img/og-default.png');

INSERT IGNORE INTO shipping (region, carrier, service_name, base_rate, free_threshold, estimated_days_min, estimated_days_max, active) VALUES
('United States', 'USPS', 'Standard Shipping', 7.99, 75.00, 5, 8, 1),
('United States', 'UPS', 'Express Shipping', 18.99, 150.00, 2, 3, 1),
('United States', 'FedEx', 'Overnight Shipping', 29.99, 200.00, 1, 1, 1),
('Canada', 'USPS', 'Standard International', 14.99, 100.00, 7, 14, 1),
('Canada', 'UPS', 'Express International', 29.99, 200.00, 3, 5, 1),
('Europe', 'DHL', 'Standard International', 19.99, 150.00, 7, 14, 1),
('Europe', 'DHL', 'Express International', 39.99, 250.00, 3, 5, 1),
('Rest of World', 'DHL', 'Standard International', 24.99, 200.00, 10, 21, 1),
('Rest of World', 'DHL', 'Express International', 49.99, 300.00, 5, 8, 1);

INSERT IGNORE INTO pages (title, slug, content, published) VALUES
('About SUGGAWAYZ', 'about', '<h2>The Future of Streetwear</h2><p>SUGGAWAYZ was founded on the belief that clothing should be more than fabric. We bridge the gap between cyberpunk aesthetics and everyday wearability.</p><p>Every piece is designed with meticulous attention to detail, using premium materials and innovative construction techniques.</p><p>We are not just a clothing brand. We are a movement.</p>', 1),
('Terms & Conditions', 'terms', '<h2>Terms of Service</h2><p>By accessing and using the SUGGAWAYZ website and services, you agree to be bound by these terms.</p><h3>Orders</h3><p>All orders are subject to acceptance and availability. We reserve the right to cancel any order.</p><h3>Pricing</h3><p>All prices are in USD and subject to change without notice. Sale prices apply at the time of purchase.</p><h3>Shipping</h3><p>Shipping rates and estimated delivery times are calculated at checkout. We are not responsible for customs delays.</p><h3>Returns</h3><p>Items may be returned within 30 days of delivery in unused condition.</p>', 1),
('Privacy Policy', 'privacy', '<h2>Privacy Policy</h2><p>SUGGAWAYZ respects your privacy. This policy outlines how we collect, use, and protect your personal information.</p><h3>Information We Collect</h3><p>We collect information you provide: name, email, shipping address, payment details. We also collect browsing data through cookies.</p><h3>How We Use Your Information</h3><p>We use your information to process orders, improve our store, send marketing communications, and prevent fraud.</p><h3>Data Security</h3><p>We implement industry-standard encryption and security measures. We do not sell your personal data.</p>', 1),
('Returns & Exchanges', 'returns', '<h2>Returns & Exchanges</h2><p>We want you to love your purchase. If something isn''t right, we''re here to help.</p><h3>Return Policy</h3><p>Items may be returned within 30 days of delivery in unused, unworn condition with original tags attached.</p><h3>Refunds</h3><p>Refunds are processed within 5-7 business days after we receive your return. Shipping costs are non-refundable.</p><h3>Exchanges</h3><p>For size exchanges, please return the original item and place a new order. This ensures the fastest turnaround.</p><h3>Damaged or Incorrect Items</h3><p>If you received a damaged or incorrect item, contact us within 48 hours of delivery for a replacement.</p>', 1),
('Size Guide', 'size-guide', '<h2>Size Guide</h2><p>Find your perfect fit with our detailed sizing information.</p><h3>Tops (Hoodies, Tees, Jackets)</h3><table><tr><th>Size</th><th>Chest (in)</th><th>Length (in)</th><th>Sleeve (in)</th></tr><tr><td>S</td><td>36-38</td><td>27</td><td>33</td></tr><tr><td>M</td><td>39-41</td><td>28</td><td>34</td></tr><tr><td>L</td><td>42-44</td><td>29</td><td>35</td></tr><tr><td>XL</td><td>45-47</td><td>30</td><td>36</td></tr><tr><td>XXL</td><td>48-50</td><td>31</td><td>37</td></tr><tr><td>XXXL</td><td>51-53</td><td>32</td><td>38</td></tr></table><h3>Bottoms (Pants, Joggers)</h3><table><tr><th>Size</th><th>Waist (in)</th><th>Inseam (in)</th><th>Hip (in)</th></tr><tr><td>S</td><td>28-30</td><td>30</td><td>36-38</td></tr><tr><td>M</td><td>31-33</td><td>31</td><td>39-41</td></tr><tr><td>L</td><td>34-36</td><td>32</td><td>42-44</td></tr><tr><td>XL</td><td>37-39</td><td>33</td><td>45-47</td></tr><tr><td>XXL</td><td>40-42</td><td>34</td><td>48-50</td></tr></table><p>Fit notes: Our sizing runs true to size. If between sizes, size up for an oversized fit.</p>', 1);

INSERT IGNORE INTO faq_items (question, answer, category, sort_order, published) VALUES
('What payment methods do you accept?', 'We accept credit/debit cards, PayPal, Square, Cash App, Apple Pay, Google Pay, and bank transfers.', 'Payments', 1, 1),
('How long does shipping take?', 'US standard shipping takes 5-8 business days. Express 2-3 days. International varies by region (7-21 days).', 'Shipping', 2, 1),
('Do you ship internationally?', 'Yes, we ship to over 60 countries. International rates are calculated at checkout.', 'Shipping', 3, 1),
('What is your return policy?', '30-day return policy for unworn items in original condition. Refunds processed within 5-7 business days.', 'Returns', 4, 1),
('How do I track my order?', 'You will receive a tracking number via email. Track orders from your account dashboard.', 'Orders', 5, 1),
('Can I change or cancel my order?', 'Orders can be modified within 1 hour of placement. Contact support immediately.', 'Orders', 6, 1),
('Do you have a size guide?', 'Yes, each product page has a detailed size chart. General sizing runs true to size.', 'Products', 7, 1),
('How do I use a discount code?', 'Enter your coupon code at checkout in the Discount Code field.', 'Orders', 8, 1),
('Is my payment information secure?', 'Yes. We use industry-standard encryption and never store sensitive payment data on our servers.', 'Security', 9, 1),
('Do you have a loyalty program?', 'Coming soon! We are developing a loyalty rewards program.', 'Account', 10, 1);

INSERT IGNORE INTO blog_posts (title, slug, content, excerpt, author, published, published_at) VALUES
('The Future of Streetwear: Where Tech Meets Style', 'future-of-streetwear-tech-style', '<p>Streetwear has evolved beyond its roots into a fusion of fashion, technology, and culture.</p><p>From moisture-wicking materials to reflective coatings, modern streetwear incorporates performance fabrics that look as good as they function.</p><p>The cyberpunk influence on fashion continues to grow. Neon accents, techwear silhouettes, and futuristic branding define this movement.</p>', 'How SUGGAWAYZ is shaping the future of fashion with technology.', 'SUGGAWAYZ Team', 1, NOW()),
('How to Style Your Techwear: A Complete Guide', 'how-to-style-techwear-guide', '<p>Techwear is about function meeting fashion. Here is how to build the perfect techwear outfit.</p><p>Start with a standout piece like the Future Jacket or Cargo Tech Pants. Build your outfit around this item.</p><p>Techwear is built for layering: base tee, hoodie or mid-layer, then a jacket. Each layer adds depth and utility.</p><p>Complete your look with a holographic cap, mask, and chunky sneakers or futuristic boots.</p>', 'Your complete guide to building the perfect techwear outfit.', 'Style Team', 1, NOW()),
('Behind the Design: Neon Genesis Limited Drop', 'behind-design-neon-genesis', '<p>The Neon Genesis hoodie represents our most ambitious design yet.</p><p>Inspired by retro-futurism and neon-lit cityscapes, the design combines UV-reactive ink with premium heavyweight fleece.</p><p>Only 500 pieces were produced worldwide, each individually numbered. Each hoodie takes 45 minutes of screen printing with UV ink applied in three layers.</p>', 'The story behind our limited edition UV-reactive hoodie drop.', 'Design Team', 1, NOW());

INSERT IGNORE INTO lookbook_events (title, description, event_date, location_name, address, city, state, postal_code, lat, lng, image, status, sort_order) VALUES
('Urban Night Pop-Up', 'Experience the future of streetwear at our flagship pop-up event. Live DJ, exclusive drops, and immersive installations.', '2026-07-15', 'SUGGAWAYZ Flagship', '456 Neon Boulevard', 'Los Angeles', 'CA', '90012', 34.052235, -118.243683, '/assets/img/products/swag.jpg', 'published', 1);

INSERT IGNORE INTO inventory_locations (name, address, contact, phone, active) VALUES
('Main Warehouse', '123 Cyber Street, Tech City, US', 'John Manager', '+1-555-0100', 1),
('West Coast Hub', '456 Neon Blvd, Los Angeles, CA', 'Sarah West', '+1-555-0101', 1),
('East Coast Hub', '789 Signal Ave, New York, NY', 'Mike East', '+1-555-0102', 1),
('Overseas Fulfillment', '100 Global Way, London, UK', 'Emma Overseas', '+44-20-5555-0103', 1);

INSERT IGNORE INTO payment_settings (provider, enabled, sandbox_mode, label, public_key, secret_key) VALUES
('paypal', 1, 1, 'PayPal', 'sandbox_paypal_client_id', 'sandbox_paypal_secret'),
('stripe', 1, 1, 'Credit / Debit Card (Stripe)', 'pk_test_stripe_publishable', 'sk_test_stripe_secret'),
('square', 1, 1, 'Square', 'sq_sandbox_access_token', 'sq_sandbox_location_id'),
('cash_app', 1, 1, 'Cash App', 'sandbox_cash_app_client', 'sandbox_cash_app_secret'),
('apple_pay', 1, 1, 'Apple Pay', 'merchant.com.suggawayz', 'sandbox_apple_merchant'),
('google_pay', 1, 1, 'Google Pay', 'sandbox_google_merchant_id', ''),
('bank_transfer', 1, 1, 'Bank Transfer', 'SUGGAWAYZ Financial', 'Account: 123456789');
