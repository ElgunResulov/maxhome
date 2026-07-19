-- MAXHOME e-commerce database schema
-- Engine: MySQL 8+

CREATE DATABASE IF NOT EXISTS maxhome_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE maxhome_db;

-- -----------------------------------------------------------------------------
-- ID (PRIMARY KEY) qaydasi
-- - Her cedvelde `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`: ilk INSERT
--   bos cedvelde adeten id = 1, sonra 2, 3, ... (MySQL-in susqunluğu).
-- - DELETE sonrasi id araliginda "bosluq" qala biler — bu normaldir; DB sirf
--   novbeti bos id vermir, movcud setirlerin id-sini yeniden nomrelemez.
-- - Butun cedvellerde novbeti id saygacini minimuma endirmek ucun (xususen
--   TRUNCATE ve ya tam temizlikden sonra) ayrica skript:
--   database/reset_auto_increment.sql
-- -----------------------------------------------------------------------------

-- =========================
-- Identity and user tables
-- =========================
CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(30) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('customer', 'seller', 'admin') NOT NULL DEFAULT 'customer',
  status ENUM('active', 'blocked', 'pending') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE user_addresses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(80) NULL,
  country VARCHAR(80) NOT NULL DEFAULT 'Azerbaijan',
  city VARCHAR(120) NOT NULL,
  district VARCHAR(120) NULL,
  zip_code VARCHAR(20) NULL,
  address_line1 VARCHAR(255) NOT NULL,
  address_line2 VARCHAR(255) NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_addresses_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
);

-- =========================
-- Catalog tables
-- =========================
-- brands əvvəl: categories.brand_id FK üçün (mega menyu brend yarpaqları bu sütunla brands-a bağlanır).
CREATE TABLE brands (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  slug VARCHAR(160) NOT NULL UNIQUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id BIGINT UNSIGNED NULL,
  brand_id BIGINT UNSIGNED NULL,
  name VARCHAR(140) NOT NULL,
  slug VARCHAR(180) NOT NULL UNIQUE,
  description TEXT NULL,
  image_url VARCHAR(500) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_categories_parent
    FOREIGN KEY (parent_id) REFERENCES categories(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_categories_brand
    FOREIGN KEY (brand_id) REFERENCES brands(id)
    ON DELETE SET NULL
);

CREATE TABLE category_brands (
  category_id BIGINT UNSIGNED NOT NULL,
  brand_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (category_id, brand_id),
  CONSTRAINT fk_category_brands_category
    FOREIGN KEY (category_id) REFERENCES categories(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_category_brands_brand
    FOREIGN KEY (brand_id) REFERENCES brands(id)
    ON DELETE CASCADE
);

CREATE TABLE products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id BIGINT UNSIGNED NOT NULL,
  brand_id BIGINT UNSIGNED NULL,
  name VARCHAR(200) NOT NULL,
  slug VARCHAR(220) NOT NULL UNIQUE,
  short_description VARCHAR(280) NULL,
  description LONGTEXT NULL,
  sku VARCHAR(80) NOT NULL UNIQUE,
  base_price DECIMAL(12,2) NOT NULL,
  compare_at_price DECIMAL(12,2) NULL,
  rating_avg DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  rating_count INT UNSIGNED NOT NULL DEFAULT 0,
  stock_qty INT NOT NULL DEFAULT 0,
  status ENUM('draft', 'active', 'archived') NOT NULL DEFAULT 'active',
  online_visible TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_products_catalog_newest (status, online_visible, created_at, id),
  KEY idx_products_catalog_price (status, online_visible, base_price, id),
  KEY idx_products_category_catalog (category_id, status, online_visible, created_at, id),
  CONSTRAINT fk_products_category
    FOREIGN KEY (category_id) REFERENCES categories(id),
  CONSTRAINT fk_products_brand
    FOREIGN KEY (brand_id) REFERENCES brands(id)
);

CREATE TABLE product_images (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  image_url VARCHAR(500) NOT NULL,
  alt_text VARCHAR(255) NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_product_images_primary (product_id, is_primary DESC, sort_order, id),
  CONSTRAINT fk_product_images_product
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE CASCADE
);

CREATE TABLE product_specs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  spec_key VARCHAR(120) NOT NULL,
  spec_value VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_product_specs_product
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE CASCADE
);

CREATE TABLE product_bundle_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_product_id BIGINT UNSIGNED NOT NULL,
  bundle_product_id BIGINT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_parent_bundle (parent_product_id, bundle_product_id),
  CONSTRAINT fk_bundle_parent_product
    FOREIGN KEY (parent_product_id) REFERENCES products(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_bundle_child_product
    FOREIGN KEY (bundle_product_id) REFERENCES products(id)
    ON DELETE CASCADE
);

CREATE TABLE spec_definitions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  spec_key VARCHAR(120) NOT NULL UNIQUE,
  label VARCHAR(160) NOT NULL,
  input_type ENUM('text', 'number', 'select', 'boolean') NOT NULL DEFAULT 'text',
  unit VARCHAR(30) NULL,
  options_json JSON NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE category_spec_map (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id BIGINT UNSIGNED NOT NULL,
  spec_definition_id BIGINT UNSIGNED NOT NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT uq_category_spec UNIQUE (category_id, spec_definition_id),
  CONSTRAINT fk_category_spec_map_category
    FOREIGN KEY (category_id) REFERENCES categories(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_category_spec_map_spec
    FOREIGN KEY (spec_definition_id) REFERENCES spec_definitions(id)
    ON DELETE CASCADE
);

-- Optional variant model for color / RAM / storage combinations
CREATE TABLE product_variants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  variant_sku VARCHAR(90) NOT NULL UNIQUE,
  color_name VARCHAR(80) NULL,
  ram_gb SMALLINT UNSIGNED NULL,
  storage_gb SMALLINT UNSIGNED NULL,
  price DECIMAL(12,2) NOT NULL,
  stock_qty INT NOT NULL DEFAULT 0,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_product_variants_product
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE CASCADE
);

-- =========================
-- Cart and checkout tables
-- =========================
CREATE TABLE carts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  session_token VARCHAR(120) NULL UNIQUE,
  status ENUM('active', 'converted', 'abandoned') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_carts_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL
);

CREATE TABLE cart_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cart_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  variant_id BIGINT UNSIGNED NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  unit_price DECIMAL(12,2) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cart_product_variant (cart_id, product_id, variant_id),
  CONSTRAINT fk_cart_items_cart
    FOREIGN KEY (cart_id) REFERENCES carts(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_cart_items_product
    FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_cart_items_variant
    FOREIGN KEY (variant_id) REFERENCES product_variants(id)
    ON DELETE SET NULL
);

CREATE TABLE coupons (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(60) NOT NULL UNIQUE,
  discount_type ENUM('percent', 'fixed') NOT NULL,
  discount_value DECIMAL(12,2) NOT NULL,
  min_order_amount DECIMAL(12,2) NULL,
  max_discount_amount DECIMAL(12,2) NULL,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  usage_limit INT UNSIGNED NULL,
  used_count INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  cart_id BIGINT UNSIGNED NULL,
  order_number VARCHAR(40) NOT NULL UNIQUE,
  status ENUM('pending', 'paid', 'processing', 'shipped', 'completed', 'cancelled', 'refunded') NOT NULL DEFAULT 'pending',
  payment_status ENUM('unpaid', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'unpaid',
  shipping_first_name VARCHAR(100) NOT NULL,
  shipping_last_name VARCHAR(100) NOT NULL,
  shipping_phone VARCHAR(30) NULL,
  shipping_city VARCHAR(120) NOT NULL,
  shipping_zip_code VARCHAR(20) NULL,
  shipping_address_line1 VARCHAR(255) NOT NULL,
  shipping_address_line2 VARCHAR(255) NULL,
  coupon_id BIGINT UNSIGNED NULL,
  subtotal DECIMAL(12,2) NOT NULL,
  shipping_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_amount DECIMAL(12,2) NOT NULL,
  notes TEXT NULL,
  placed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_orders_cart
    FOREIGN KEY (cart_id) REFERENCES carts(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_orders_coupon
    FOREIGN KEY (coupon_id) REFERENCES coupons(id)
    ON DELETE SET NULL
);

CREATE TABLE order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  variant_id BIGINT UNSIGNED NULL,
  product_name VARCHAR(200) NOT NULL,
  product_sku VARCHAR(90) NOT NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  line_total DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_order_items_order
    FOREIGN KEY (order_id) REFERENCES orders(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_order_items_product
    FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_order_items_variant
    FOREIGN KEY (variant_id) REFERENCES product_variants(id)
    ON DELETE SET NULL
);

CREATE TABLE payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  method ENUM('card', 'installment', 'cash_on_delivery') NOT NULL,
  provider VARCHAR(100) NULL,
  transaction_ref VARCHAR(150) NULL UNIQUE,
  amount DECIMAL(12,2) NOT NULL,
  status ENUM('pending', 'authorized', 'captured', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
  paid_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payments_order
    FOREIGN KEY (order_id) REFERENCES orders(id)
    ON DELETE CASCADE
);

CREATE TABLE shipments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  carrier VARCHAR(100) NULL,
  tracking_number VARCHAR(120) NULL UNIQUE,
  shipment_status ENUM('pending', 'packed', 'shipped', 'in_transit', 'delivered', 'failed', 'returned') NOT NULL DEFAULT 'pending',
  shipped_at DATETIME NULL,
  delivered_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_shipments_order
    FOREIGN KEY (order_id) REFERENCES orders(id)
    ON DELETE CASCADE
);

-- =========================
-- Experience and support tables
-- =========================
CREATE TABLE product_reviews (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  title VARCHAR(180) NULL,
  comment TEXT NULL,
  is_verified_purchase TINYINT(1) NOT NULL DEFAULT 0,
  is_visible TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_product_reviews_rating CHECK (rating BETWEEN 1 AND 5),
  CONSTRAINT fk_product_reviews_product
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_product_reviews_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
);

CREATE TABLE wishlists (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL DEFAULT 'Default',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wishlists_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
);

CREATE TABLE wishlist_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  wishlist_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  variant_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wishlist_item (wishlist_id, product_id, variant_id),
  CONSTRAINT fk_wishlist_items_wishlist
    FOREIGN KEY (wishlist_id) REFERENCES wishlists(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_wishlist_items_product
    FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_wishlist_items_variant
    FOREIGN KEY (variant_id) REFERENCES product_variants(id)
    ON DELETE SET NULL
);

CREATE TABLE support_tickets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  order_id BIGINT UNSIGNED NULL,
  topic ENUM('order', 'shipping', 'return', 'warranty', 'technical', 'general') NOT NULL,
  subject VARCHAR(180) NOT NULL,
  message TEXT NOT NULL,
  channel ENUM('web', 'email', 'phone', 'chat', 'social') NOT NULL DEFAULT 'web',
  status ENUM('open', 'in_progress', 'resolved', 'closed') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_support_tickets_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_support_tickets_order
    FOREIGN KEY (order_id) REFERENCES orders(id)
    ON DELETE SET NULL
);

CREATE TABLE support_faqs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question VARCHAR(255) NOT NULL,
  answer TEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_support_faq_question (question)
);

CREATE TABLE support_contact_cards (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  icon VARCHAR(80) NOT NULL,
  title VARCHAR(180) NOT NULL,
  description VARCHAR(255) NOT NULL,
  value VARCHAR(255) NOT NULL,
  action_url VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE newsletter_subscribers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  source_page VARCHAR(80) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  subscribed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- =========================
-- Marketing content tables
-- =========================
CREATE TABLE site_features (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  icon VARCHAR(80) NOT NULL,
  title VARCHAR(180) NOT NULL,
  body TEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE footer_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  section VARCHAR(80) NOT NULL,
  label VARCHAR(160) NOT NULL,
  url VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Simple key/value settings (homepage timers, etc.)
CREATE TABLE site_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(120) NOT NULL UNIQUE,
  setting_value TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =========================
-- Useful indexes
-- =========================
CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_categories_brand ON categories(brand_id);
CREATE INDEX idx_category_brands_brand ON category_brands(brand_id);
CREATE INDEX idx_products_brand ON products(brand_id);
CREATE INDEX idx_products_status ON products(status);
CREATE INDEX idx_bundle_parent ON product_bundle_items (parent_product_id, sort_order);
CREATE INDEX idx_product_specs_lookup ON product_specs(product_id, spec_key);
CREATE INDEX idx_spec_definitions_active ON spec_definitions(is_active, sort_order);
CREATE INDEX idx_category_spec_map_category ON category_spec_map(category_id, sort_order);
CREATE INDEX idx_orders_user ON orders(user_id);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_payments_order ON payments(order_id);
CREATE INDEX idx_shipments_order ON shipments(order_id);
CREATE INDEX idx_reviews_product ON product_reviews(product_id);
CREATE INDEX idx_tickets_user_status ON support_tickets(user_id, status);
