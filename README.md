# SUGGAWAYZ Platform

Phase 1 complete — fully built futuristic streetwear e-commerce platform.

## Architecture

- **Web Server:** PHP 8.3 Apache (Docker)
- **Database:** MySQL 8.4 (Docker)
- **DB Admin:** phpMyAdmin 5 (Docker)
- **Frontend:** HTML5 + CSS3 (custom cyber theme, Orbitron + Inter fonts)
- **JavaScript:** Minimal vanilla JS (interactions only)
- **Security:** Argon2ID hashing, CSRF protection, HTTP-only sessions, audit logging

## Quick Start

```powershell
docker compose up -d --build
```

Then open:

- **Website:** http://localhost:8080
- **phpMyAdmin:** http://localhost:8081

## Login Accounts

| Role | Username | Password |
|------|----------|----------|
| Webmaster | `spectre` | `admin` |
| Customer | `user` | `user` |

## Phase 1 — Complete Features

### Storefront (13 pages)
- Home, Shop (with filters/sort/search), Product Details (gallery, sizes, colors, reviews, related)
- Collections, New Drops, Lookbook, About, Contact, FAQ, Blog, Blog Post, Terms, Privacy

### Authentication (full system)
- Register, Login (with remember-me), Logout
- Password reset flow, Email verification scaffolding
- Device login tracking with history UI
- 2FA scaffolding (toggle in account security)

### Customer Dashboard (8 tabs)
- Dashboard overview with stats and quick links
- Orders — full history with status tracking
- Profile editing (name, phone, bio)
- Addresses — saved addresses + add new address form
- Wishlist — add/remove products
- Notifications — read/unread messages
- Devices — login device history
- Security — password change, 2FA status, session management

### Shopping System (full cart + checkout)
- Products with variants (sizes, colors), images, stock
- Add to cart (guest + logged in) with size/color selection
- Cart page with quantity update, item removal
- Coupon system (percent + fixed discounts, min order, max uses)
- Tax calculations (configurable rate)
- Shipping methods (multiple carriers, regions, free threshold)
- Multi-currency support (USD, EUR, GBP, JPY, CAD, AUD)
- Checkout flow with address selection, shipping method, payment method choice
- Order confirmation page with order summary
- Guest checkout support

### Payment Gateway Support (8 methods)
- Credit/Debit Card (Stripe), PayPal, Square, Cash App, Apple Pay, Google Pay
- Crypto (Bitcoin, Ethereum, Litecoin, USDT)
- Bank transfer
- All configured with sandbox/test mode, ready for live credentials

### Admin Dashboard (9 tabs)
- Dashboard with revenue, orders, products, customers, low stock, failed payments stats
- Product Manager — add products with full details, bulk fields, featured toggle
- Order Manager — update status, add tracking numbers, filter/sort
- Customer Manager — view all customers, roles, verification, 2FA status
- Inventory System — stock levels, low stock warnings, warehouse tracking
- Payment Dashboard — all transactions, revenue analytics, failed payments
- SEO Manager — view all page meta settings
- Coupon Manager — discount codes with usage tracking
- Audit Log — full action history with timestamps, user, IP

### Database (28 tables)
`users`, `password_resets`, `email_verifications`, `device_tracking`, `admins`, `categories`, `products`, `product_variants`, `product_images`, `product_videos`, `inventory`, `inventory_movements`, `cart`, `wishlist_items`, `addresses`, `orders`, `order_items`, `payments`, `transactions`, `coupons`, `reviews`, `seo_settings`, `redirects`, `shipping`, `notifications`, `blog_posts`, `faq_items`, `pages`, `affiliates`, `affiliate_clicks`, `flash_sales`, `flash_sale_products`, `audit_logs`

### SEO System
- Meta title, description, OG image per page
- Canonical URLs, schema markup scaffolding
- Sitemap-ready, robots.txt-ready
- Blog content for organic search
- 301 redirects table

### Content (seeded demo data)
- 10 products across 6 categories
- 3 blog posts, 10 FAQ items, 3 static pages
- 4 coupon codes, 9 shipping methods
- SEO settings for 4 pages
- Webmaster + customer accounts

## Phase 2 (Next)

- [ ] Admin dashboard: fraud detection, KYC verification hub
- [ ] Inventory: warehouse management, restock alerts, product movement tracking
- [ ] SEO: full meta editor UI, sitemap generator, robots.txt editor, redirect manager
- [ ] Analytics: sales charts, conversion, abandoned carts, traffic, returning customers
- [ ] Marketing: email campaigns, push/SMS notifications, affiliate system, referral rewards
- [ ] Flash sales system with countdown timers
- [ ] Bulk product import/export (CSV)

## Phase 3 (Future)

- [ ] Crypto payments: live blockchain verification, cold wallet integration
- [ ] AI chatbot and sizing assistant
- [ ] NFT drops and loyalty points
- [ ] AR clothing previews
- [ ] Mobile app (React Native)
- [ ] Subscription boxes
