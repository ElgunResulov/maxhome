-- MAXHOME: butun cedvellerde novbeti INSERT ucun AUTO_INCREMENT saygacini
-- minimuma endirir. Bos cedvelde ilk yeni setir id = 1 olacaq.
--
-- DIQQET:
-- - Bu skript movcud setirlerin `id` deyerlerini DEYISDIRMEZ / yeniden nomrelemez.
-- - Cedvelde setir varsa, InnoDB novbeti id ucun MAX(id)+1 istifade edir
--   (MySQL-in AUTO_INCREMENT qaydasi).
-- - Id-lari 1,2,3... ardicil ve bosluqsuz etmek ucun setirleri silmek ve
--   FK-larla yeniden INSERT lazimdir — bu fayl yalniz saygac ucundur.
--
-- Istifade: maxhome_db secilib bu fayli icra edin (phpMyAdmin SQL ve s.).

USE maxhome_db;

ALTER TABLE brands AUTO_INCREMENT = 1;
ALTER TABLE cart_items AUTO_INCREMENT = 1;
ALTER TABLE carts AUTO_INCREMENT = 1;
ALTER TABLE categories AUTO_INCREMENT = 1;
ALTER TABLE category_spec_map AUTO_INCREMENT = 1;
ALTER TABLE coupons AUTO_INCREMENT = 1;
ALTER TABLE footer_links AUTO_INCREMENT = 1;
ALTER TABLE newsletter_subscribers AUTO_INCREMENT = 1;
ALTER TABLE order_items AUTO_INCREMENT = 1;
ALTER TABLE orders AUTO_INCREMENT = 1;
ALTER TABLE payments AUTO_INCREMENT = 1;
ALTER TABLE product_bundle_items AUTO_INCREMENT = 1;
ALTER TABLE product_images AUTO_INCREMENT = 1;
ALTER TABLE product_reviews AUTO_INCREMENT = 1;
ALTER TABLE product_specs AUTO_INCREMENT = 1;
ALTER TABLE product_variants AUTO_INCREMENT = 1;
ALTER TABLE products AUTO_INCREMENT = 1;
ALTER TABLE shipments AUTO_INCREMENT = 1;
ALTER TABLE site_features AUTO_INCREMENT = 1;
ALTER TABLE site_settings AUTO_INCREMENT = 1;
ALTER TABLE spec_definitions AUTO_INCREMENT = 1;
ALTER TABLE support_contact_cards AUTO_INCREMENT = 1;
ALTER TABLE support_tickets AUTO_INCREMENT = 1;
ALTER TABLE user_addresses AUTO_INCREMENT = 1;
ALTER TABLE users AUTO_INCREMENT = 1;
ALTER TABLE wishlist_items AUTO_INCREMENT = 1;
ALTER TABLE wishlists AUTO_INCREMENT = 1;
