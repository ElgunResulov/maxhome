-- Katalog filtreleri, sıralama ve birincil görsel seçimi için indeksler.
-- Mevcut kurulumlarda bir kez çalıştırılmalıdır.

ALTER TABLE products
  ADD INDEX idx_products_catalog_newest (status, online_visible, created_at, id),
  ADD INDEX idx_products_catalog_price (status, online_visible, base_price, id),
  ADD INDEX idx_products_category_catalog (category_id, status, online_visible, created_at, id);

ALTER TABLE product_images
  ADD INDEX idx_product_images_primary (product_id, is_primary DESC, sort_order, id);
