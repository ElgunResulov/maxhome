-- Anbar panelindən əlavə olunan məhsullar online_visible = 0 ilə vitrindən gizlədilir.
-- Mövcud sətirlər üçün default 1 (mağazada görünsün).

ALTER TABLE products
  ADD COLUMN online_visible TINYINT(1) NOT NULL DEFAULT 1
  AFTER status;

-- Köhnə anbar qeydləri: ambar paneli brendsiz + maya_deyeri spec yazır.
UPDATE products p
SET p.online_visible = 0
WHERE p.brand_id IS NULL
  AND EXISTS (
    SELECT 1 FROM product_specs ps
    WHERE ps.product_id = p.id AND ps.spec_key = 'maya_deyeri'
    LIMIT 1
  );
