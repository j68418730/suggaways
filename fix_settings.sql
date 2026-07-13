CREATE TABLE IF NOT EXISTS subscribers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(160) NOT NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS site_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(120) NOT NULL UNIQUE,
  setting_value TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES
('footer_tagline', 'Futuristic streetwear for the next generation. Be different. Be you.'),
('hero_title', 'Be Different.<br>Be You.'),
('hero_subtitle', 'Premium cyberwear apparel for creators, trendsetters, and the next generation.'),
('hero_subscribe', 'Sign up for exclusive drops, early access, and 20% off your first order.'),
('site_icon_text', 'SW'),
('social_links', '{"instagram":{"url":"","enabled":true},"tiktok":{"url":"","enabled":true},"twitter":{"url":"","enabled":true},"youtube":{"url":"","enabled":true},"facebook":{"url":"","enabled":false}}'),
('email_smtp_host', ''),
('email_smtp_port', '587'),
('email_smtp_username', ''),
('email_smtp_password', ''),
('email_smtp_encryption', 'tls'),
('email_from_address', 'noreply@suggawayz.com'),
('email_from_name', 'SUGGAWAYZ');
