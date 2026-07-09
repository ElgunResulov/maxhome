-- MAXHOME: butun mehsul kataloqunu TAM SILMEK (yalniz manual sifirlama ucun)
--
-- phpMyAdmin: maxhome_db sec → SQL → butun skripti bir defe icra et.
-- TEK CEDVEL TRUNCATE etmeyin — asagidaki tam skript lazimdir.
--
-- VACIB: Yeni mehsul elave etmek / API-JSON idxal ucun BU SKRIPTI ISLETMEYIN.
--        Normal idxal movcud mehsullari silmir; yalniz yeni mehsul elave edir.
--        Bu fayl yalniz butun kataloqu sifirdan baslatmaq istediyinizde lazimdir.
--
-- DIQQET: order_items ve cart_items de silinir. orders cedveli qalir.

USE maxhome_db;

SET FOREIGN_KEY_CHECKS = 0;

-- 1) En ashagi asili cedveller (variant/product istinad edenler)
DELETE FROM wishlist_items;
DELETE FROM cart_items;
DELETE FROM order_items;

-- 2) Mehsula birbasa bagli cedveller
DELETE FROM product_bundle_items;
DELETE FROM product_images;
DELETE FROM product_specs;
DELETE FROM product_reviews;

-- 3) Variantlar, sonra mehsullar
DELETE FROM product_variants;
DELETE FROM products;

-- 4) Sayaclari sifirla (TRUNCATE evezine DELETE + AUTO_INCREMENT)
ALTER TABLE wishlist_items AUTO_INCREMENT = 1;
ALTER TABLE cart_items AUTO_INCREMENT = 1;
ALTER TABLE order_items AUTO_INCREMENT = 1;
ALTER TABLE product_bundle_items AUTO_INCREMENT = 1;
ALTER TABLE product_images AUTO_INCREMENT = 1;
ALTER TABLE product_specs AUTO_INCREMENT = 1;
ALTER TABLE product_reviews AUTO_INCREMENT = 1;
ALTER TABLE product_variants AUTO_INCREMENT = 1;
ALTER TABLE products AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;
