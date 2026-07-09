-- MAXHOME: Kateqoriya agacini duzeltme (COLLATION-safe)
-- Problem: #1267 Illegal mix of collations
-- Hell: butun slug muqayiseleri utf8mb4_unicode_ci ile edilir

USE maxhome_db;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET collation_connection = 'utf8mb4_unicode_ci';

START TRANSACTION;

-- =========================================================
-- 1) Root kateqoriyalar (sekildeki 12 esas basliq)
-- =========================================================
CREATE TEMPORARY TABLE tmp_root_categories (
  slug VARCHAR(180) COLLATE utf8mb4_unicode_ci PRIMARY KEY,
  name VARCHAR(140) COLLATE utf8mb4_unicode_ci NOT NULL,
  sort_order INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tmp_root_categories (slug, name, sort_order) VALUES
('smartfonlar-ve-planshetler', 'Smartfonlar və planşetlər', 1),
('noutbuklar-ve-komp', 'Noutbuklar və kompüter texnikası', 2),
('oyun-konsollar', 'Oyun konsolları və aksesuarlar', 3),
('tv-audio-video', 'TV, audio və video', 4),
('boyuk-meiset', 'Böyük məişət texnikası', 5),
('kicik-meiset', 'Kiçik məişət texnikası', 6),
('iqlim-texnikasi', 'İqlim texnikası', 7),
('gozellik-baxim', 'Gözəllik və baxım', 8),
('tibbi-mehsullar', 'Tibbi məhsullar', 9),
('ev-bag', 'Ev və bağ', 10),
('mebel-tekstil-dekor', 'Mebel, tekstil və dekor', 11),
('qab-qacaq', 'Qab-qacaq, tava-qazan', 12);

INSERT INTO categories (parent_id, brand_id, name, slug, is_active, sort_order)
SELECT NULL, NULL, r.name, r.slug, 1, r.sort_order
FROM tmp_root_categories r
LEFT JOIN categories c
  ON c.slug COLLATE utf8mb4_unicode_ci = r.slug
WHERE c.id IS NULL;

UPDATE categories c
JOIN tmp_root_categories r
  ON c.slug COLLATE utf8mb4_unicode_ci = r.slug
SET
  c.parent_id = NULL,
  c.brand_id = NULL,
  c.name = r.name,
  c.sort_order = r.sort_order,
  c.is_active = 1;

-- =========================================================
-- 2) Direct parent map (deqiq baglar)
-- =========================================================
CREATE TEMPORARY TABLE tmp_parent_map (
  child_slug VARCHAR(180) COLLATE utf8mb4_unicode_ci PRIMARY KEY,
  parent_slug VARCHAR(180) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tmp_parent_map (child_slug, parent_slug) VALUES
-- Smartfon/planset bolmesi
('sf-smartfonlar', 'smartfonlar-ve-planshetler'),
('pl-planshetler', 'smartfonlar-ve-planshetler'),
('dt-duymeli-telefonlar', 'smartfonlar-ve-planshetler'),
('ss-smart-saatlar', 'smartfonlar-ve-planshetler'),
('ek-elektron-kitablar', 'smartfonlar-ve-planshetler'),
('ql-qulaqliqlar', 'smartfonlar-ve-planshetler'),
('qs-qol-saatlari', 'smartfonlar-ve-planshetler'),
('eo-ev-ofis-telefonlari', 'smartfonlar-ve-planshetler'),

-- Noutbuklar
('noutbuklar-ve-komp-b-01-noutbuklar', 'noutbuklar-ve-komp'),
('noutbuklar-ve-komp-b-02-masaustu-komputerler', 'noutbuklar-ve-komp'),
('noutbuklar-ve-komp-b-03-monitorlar', 'noutbuklar-ve-komp'),
('noutbuklar-ve-komp-b-04-printer-ve-ofis', 'noutbuklar-ve-komp'),
('noutbuklar-ve-komp-b-05-sebeke-ve-saxlama', 'noutbuklar-ve-komp'),
('noutbuklar-ve-komp-b-06-periferiya', 'noutbuklar-ve-komp'),
('noutbuklar-ve-komp-b-07-proqram-teminati', 'noutbuklar-ve-komp'),

-- Oyun konsollari
('oyun-konsollar-b-01-oyun-konsollari', 'oyun-konsollar'),
('oyun-konsollar-b-02-oyun-aksesuarlari', 'oyun-konsollar'),
('oyun-konsollar-b-03-vr-ve-eylence', 'oyun-konsollar'),
('oyun-konsollar-b-04-oyun-ucun-mehsullar', 'oyun-konsollars'),

-- TV audio video
('tv-audio-video-b-01-televizorlar', 'tv-audio-video'),
('tv-audio-video-b-02-audio', 'tv-audio-video'),
('tv-audio-video-b-03-video-texnika', 'tv-audio-video'),
('tv-audio-video-b-04-aksesuarlar', 'tv-audio-video'),

-- Boyuk meiset
('boyuk-meiset-b-01-metbex', 'boyuk-meiset'),
('boyuk-meiset-b-02-temizlik', 'boyuk-meiset'),
('boyuk-meiset-b-03-bisirme', 'boyuk-meiset'),
('boyuk-meiset-b-04-diger', 'boyuk-meiset'),

('kicik-meiset-b-01-yemek-hazirlanmasi', 'kicik-meiset'),
('kicik-meiset-b-02-temizlik', 'kicik-meiset'),
('kicik-meiset-b-03-sexsi-qulluq', 'kicik-meiset'),
('kicik-meiset-b-04-diger', 'kicik-meiset'),

-- Iqlim
('iqlim-texnikasi-b-01-soyutma-ve-isitme', 'iqlim-texnikasi'),
('iqlim-texnikasi-b-02-hava-keyfiyyeti', 'iqlim-texnikasi'),
('iqlim-texnikasi-b-03-su-qizdiricilari', 'iqlim-texnikasi'),
('iqlim-texnikasi-b-04-diger', 'iqlim-texnikasi'),

-- Gozellik
('gozellik-baxim-b-01-sac-qullugu', 'gozellik-baxim'),
('gozellik-baxim-b-02-uz-ve-beden', 'gozellik-baxim'),
('gozellik-baxim-b-03-kisi-qullugu', 'gozellik-baxim'),
('gozellik-baxim-b-04-diger', 'gozellik-baxim'),

-- Tibbi
('tibbi-mehsullar-b-01-olcu-cihazlari', 'tibbi-mehsullar'),
('tibbi-mehsullar-b-02-ortoopediya', 'tibbi-mehsullar'),
('tibbi-mehsullar-b-03-reabilitasiya', 'tibbi-mehsullar'),
('tibbi-mehsullar-b-04-diger', 'tibbi-mehsullar'),

-- Ev bag
('ev-bag-b-01-bag', 'ev-bag'),
('ev-bag-b-02-isiqlandirma', 'ev-bag'),
('ev-bag-b-03-temir-ve-alet', 'ev-bag'),
('ev-bag-b-04-diger', 'ev-bag'),

-- Mebel tekstil dekor
('mebel-tekstil-dekor-b-01-mebel', 'mebel-tekstil-dekor'),
('mebel-tekstil-dekor-b-02-tekstil', 'mebel-tekstil-dekor'),
('mebel-tekstil-dekor-b-03-dekor', 'mebel-tekstil-dekor'),
('mebel-tekstil-dekor-b-04-diger', 'mebel-tekstil-dekor'),

-- Qab qacaq
('qab-qacaq-b-01-qab-qacaq', 'qab-qacaq'),
('qab-qacaq-b-02-bisirme', 'qab-qacaq'),
('qab-qacaq-b-03-bicaq-ve-lovhe', 'qab-qacaq'),
('qab-qacaq-b-04-diger', 'qab-qacaq');

-- typo qorunmasi: oyun-konsollars -> oyun-konsollar
UPDATE tmp_parent_map
SET parent_slug = 'oyun-konsollar'
WHERE parent_slug = 'oyun-konsollars';

UPDATE categories child
JOIN tmp_parent_map m
  ON child.slug COLLATE utf8mb4_unicode_ci = m.child_slug
JOIN categories parent
  ON parent.slug COLLATE utf8mb4_unicode_ci = m.parent_slug
SET child.parent_id = parent.id;

-- =========================================================
-- 3) Prefix parent map (yarpaq kateqoriyalar)
-- =========================================================
CREATE TEMPORARY TABLE tmp_prefix_map (
  slug_prefix VARCHAR(40) COLLATE utf8mb4_unicode_ci PRIMARY KEY,
  parent_slug VARCHAR(180) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tmp_prefix_map (slug_prefix, parent_slug) VALUES
('sf-', 'sf-smartfonlar'),
('pl-', 'pl-planshetler'),
('dt-', 'dt-duymeli-telefonlar'),
('ss-', 'ss-smart-saatlar'),
('ek-', 'ek-elektron-kitablar'),
('ql-', 'ql-qulaqliqlar'),
('qs-', 'qs-qol-saatlari'),
('eo-', 'eo-ev-ofis-telefonlari'),
('nb-', 'noutbuklar-ve-komp-b-01-noutbuklar'),
('pc-', 'noutbuklar-ve-komp-b-02-masaustu-komputerler'),
('mn-', 'noutbuklar-ve-komp-b-03-monitorlar'),
('pr-', 'noutbuklar-ve-komp-b-04-printer-ve-ofis'),
('nw-', 'noutbuklar-ve-komp-b-05-sebeke-ve-saxlama'),
('ak-', 'noutbuklar-ve-komp-b-06-periferiya'),
('sw-', 'noutbuklar-ve-komp-b-07-proqram-teminati'),
('gm-', 'oyun-konsollar-b-01-oyun-konsollari'),
('ga-', 'oyun-konsollar-b-02-oyun-aksesuarlari'),
('vr-', 'oyun-konsollar-b-03-vr-ve-eylence'),
('gx-', 'oyun-konsollar-b-04-oyun-ucun-mehsullar'),
('au-', 'tv-audio-video-b-02-audio'),
('vd-', 'tv-audio-video-b-03-video-texnika'),
('tv-ak-', 'tv-audio-video-b-04-aksesuarlar'),
('bk-', 'boyuk-meiset-b-01-metbex'),
('km-', 'kicik-meiset-b-01-yemek-hazirlanmasi'),
('iq-', 'iqlim-texnikasi-b-01-soyutma-ve-isitme'),
('gz-', 'gozellik-baxim-b-01-sac-qullugu'),
('tb-', 'tibbi-mehsullar-b-01-olcu-cihazlari'),
('eb-', 'ev-bag-b-01-bag'),
('mb-', 'mebel-tekstil-dekor-b-01-mebel'),
('tx-', 'mebel-tekstil-dekor-b-02-tekstil'),
('dk-', 'mebel-tekstil-dekor-b-03-dekor'),
('qq-', 'qab-qacaq-b-01-qab-qacaq');

UPDATE categories child
JOIN tmp_prefix_map pm
  ON child.slug COLLATE utf8mb4_unicode_ci LIKE CONCAT(pm.slug_prefix, '%')
JOIN categories parent
  ON parent.slug COLLATE utf8mb4_unicode_ci = pm.parent_slug
SET child.parent_id = parent.id
WHERE child.slug COLLATE utf8mb4_unicode_ci <> parent.slug COLLATE utf8mb4_unicode_ci;

-- =========================================================
-- 4) Brendleri ve categories.brand_id baglarini duzelt
-- =========================================================
INSERT INTO brands (name, slug) VALUES
('Apple', 'apple'),
('Borofone', 'borofone'),
('Energizer', 'energizer'),
('Garmin', 'garmin'),
('Gigaset', 'gigaset'),
('Honor', 'honor'),
('Huawei', 'huawei'),
('Infinix', 'infinix'),
('Lenovo', 'lenovo'),
('Motorola', 'motorola'),
('Nintendo Switch', 'nintendo-switch'),
('Nokia', 'nokia'),
('Panasonic', 'panasonic'),
('PlayStation', 'playstation'),
('PocketBook', 'pocketbook'),
('Poco', 'poco'),
('Samsung', 'samsung'),
('Tecno', 'tecno'),
('Ttec', 'ttec'),
('Vivo', 'vivo'),
('Wonlex', 'wonlex'),
('Xbox', 'xbox'),
('Xiaomi', 'xiaomi')
ON DUPLICATE KEY UPDATE name = VALUES(name);

CREATE TEMPORARY TABLE tmp_category_brand_map (
  category_slug VARCHAR(180) COLLATE utf8mb4_unicode_ci PRIMARY KEY,
  brand_slug VARCHAR(160) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tmp_category_brand_map (category_slug, brand_slug) VALUES
('sf-smartfonlar-apple', 'apple'),
('sf-smartfonlar-samsung', 'samsung'),
('sf-smartfonlar-xiaomi', 'xiaomi'),
('sf-smartfonlar-huawei', 'huawei'),
('sf-smartfonlar-motorola', 'motorola'),
('sf-smartfonlar-honor', 'honor'),
('sf-smartfonlar-poco', 'poco'),
('sf-smartfonlar-infinix', 'infinix'),
('sf-smartfonlar-tecno', 'tecno'),
('sf-smartfonlar-vivo', 'vivo'),
('sf-smartfonlar-nokia', 'nokia'),
('pl-planshetler-apple', 'apple'),
('pl-planshetler-samsung', 'samsung'),
('pl-planshetler-xiaomi', 'xiaomi'),
('pl-planshetler-lenovo', 'lenovo'),
('pl-planshetler-huawei', 'huawei'),
('pl-planshetler-poco', 'poco'),
('dt-duymeli-energizer', 'energizer'),
('dt-duymeli-nokia', 'nokia'),
('dt-duymeli-panasonic', 'panasonic'),
('ss-smart-saatlar-apple', 'apple'),
('ss-smart-saatlar-borofone', 'borofone'),
('ss-smart-saatlar-garmin', 'garmin'),
('ss-smart-saatlar-huawei', 'huawei'),
('ss-smart-saatlar-samsung', 'samsung'),
('ss-smart-saatlar-ttec', 'ttec'),
('ss-smart-saatlar-wonlex', 'wonlex'),
('ss-smart-saatlar-xiaomi', 'xiaomi'),
('ek-pocketbook', 'pocketbook'),
('eo-gigaset', 'gigaset'),
('gm-playstation', 'playstation'),
('gm-xbox', 'xbox'),
('gm-nintendo', 'nintendo-switch');

UPDATE categories c
JOIN tmp_category_brand_map m
  ON c.slug COLLATE utf8mb4_unicode_ci = m.category_slug
JOIN brands b
  ON b.slug COLLATE utf8mb4_unicode_ci = m.brand_slug
SET c.brand_id = b.id;

-- "butun brendler" ve brend olmayan yarpaqlarda brand_id-ni temizle
UPDATE categories
SET brand_id = NULL
WHERE slug COLLATE utf8mb4_unicode_ci LIKE '%-butun-brendler';

-- =========================================================
-- 5) Temizleme
-- =========================================================
DROP TEMPORARY TABLE IF EXISTS tmp_root_categories;
DROP TEMPORARY TABLE IF EXISTS tmp_parent_map;
DROP TEMPORARY TABLE IF EXISTS tmp_prefix_map;
DROP TEMPORARY TABLE IF EXISTS tmp_category_brand_map;

COMMIT;

-- Yoxlama:
-- SELECT id, name, slug, parent_id, sort_order
-- FROM categories
-- WHERE parent_id IS NULL
-- ORDER BY sort_order, id;
