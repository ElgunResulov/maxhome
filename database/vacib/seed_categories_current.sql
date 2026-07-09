  -- MAXHOME current structure category seed
  -- Safe to re-run (idempotent), parent/brand links are rebuilt.

  USE maxhome_db;
  SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
  SET collation_connection = 'utf8mb4_unicode_ci';

  START TRANSACTION;

  -- ------------------------------------------------------------
  -- 1) Ensure brands (needed for brand leaf categories)
  -- ------------------------------------------------------------
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

  -- ------------------------------------------------------------
  -- 2) Category model (slug + parent_slug + optional brand_slug)
  -- ------------------------------------------------------------
  CREATE TEMPORARY TABLE tmp_cat_seed (
    slug VARCHAR(180) COLLATE utf8mb4_unicode_ci PRIMARY KEY,
    name VARCHAR(140) COLLATE utf8mb4_unicode_ci NOT NULL,
    parent_slug VARCHAR(180) COLLATE utf8mb4_unicode_ci NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    brand_slug VARCHAR(160) COLLATE utf8mb4_unicode_ci NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  INSERT INTO tmp_cat_seed (slug, name, parent_slug, sort_order, is_active, brand_slug) VALUES
  -- Root (12)
  ('smartfonlar-ve-planshetler','Smartfonlar və planşetlər',NULL,1,1,NULL),
  ('noutbuklar-ve-komp','Noutbuklar və kompüter texnikası',NULL,2,1,NULL),
  ('oyun-konsollar','Oyun konsolları və aksesuarlar',NULL,3,1,NULL),
  ('tv-audio-video','TV, audio və video',NULL,4,1,NULL),
  ('boyuk-meiset','Böyük məişət texnikası',NULL,5,1,NULL),
  ('kicik-meiset','Kiçik məişət texnikası',NULL,6,1,NULL),
  ('iqlim-texnikasi','İqlim texnikası',NULL,7,1,NULL),
  ('gozellik-baxim','Gözəllik və baxım',NULL,8,1,NULL),
  ('tibbi-mehsullar','Tibbi məhsullar',NULL,9,1,NULL),
  ('ev-bag','Ev və bağ',NULL,10,1,NULL),
  ('mebel-tekstil-dekor','Mebel, tekstil və dekor',NULL,11,1,NULL),
  ('qab-qacaq','Qab-qacaq, tava-qazan',NULL,12,1,NULL),

  -- Smartfonlar və planşetlər
  ('sf-smartfonlar','Smartfonlar','smartfonlar-ve-planshetler',1,1,NULL),
  ('pl-planshetler','Planşetlər','smartfonlar-ve-planshetler',2,1,NULL),
  ('dt-duymeli-telefonlar','Düyməli telefonlar','smartfonlar-ve-planshetler',3,1,NULL),
  ('ss-smart-saatlar','Smart saatlar','smartfonlar-ve-planshetler',4,1,NULL),
  ('ek-elektron-kitablar','Elektron kitablar','smartfonlar-ve-planshetler',5,1,NULL),
  ('ql-qulaqliqlar','Qulaqlıqlar','smartfonlar-ve-planshetler',6,1,NULL),
  ('qs-qol-saatlari','Qol saatları','smartfonlar-ve-planshetler',7,1,NULL),
  ('eo-ev-ofis-telefonlari','Ev və ofis telefonları','smartfonlar-ve-planshetler',8,1,NULL),

  ('sf-smartfonlar-apple','Apple','sf-smartfonlar',1,1,'apple'),
  ('sf-smartfonlar-samsung','Samsung','sf-smartfonlar',2,1,'samsung'),
  ('sf-smartfonlar-xiaomi','Xiaomi','sf-smartfonlar',3,1,'xiaomi'),
  ('sf-smartfonlar-huawei','Huawei','sf-smartfonlar',4,1,'huawei'),
  ('sf-smartfonlar-motorola','Motorola','sf-smartfonlar',5,1,'motorola'),
  ('sf-smartfonlar-honor','Honor','sf-smartfonlar',6,1,'honor'),
  ('sf-smartfonlar-poco','Poco','sf-smartfonlar',7,1,'poco'),
  ('sf-smartfonlar-infinix','Infinix','sf-smartfonlar',8,1,'infinix'),
  ('sf-smartfonlar-tecno','Tecno','sf-smartfonlar',9,1,'tecno'),
  ('sf-smartfonlar-vivo','Vivo','sf-smartfonlar',10,1,'vivo'),
  ('sf-smartfonlar-nokia','Nokia','sf-smartfonlar',11,1,'nokia'),
  ('sf-smartfonlar-butun-brendler','Bütün brendlər','sf-smartfonlar',12,1,NULL),

  ('pl-planshetler-apple','Apple','pl-planshetler',1,1,'apple'),
  ('pl-planshetler-samsung','Samsung','pl-planshetler',2,1,'samsung'),
  ('pl-planshetler-xiaomi','Xiaomi','pl-planshetler',3,1,'xiaomi'),
  ('pl-planshetler-lenovo','Lenovo','pl-planshetler',4,1,'lenovo'),
  ('pl-planshetler-huawei','Huawei','pl-planshetler',5,1,'huawei'),
  ('pl-planshetler-poco','Poco','pl-planshetler',6,1,'poco'),
  ('pl-planshetler-butun-brendler','Bütün brendlər','pl-planshetler',7,1,NULL),

  ('dt-duymeli-energizer','Energizer','dt-duymeli-telefonlar',1,1,'energizer'),
  ('dt-duymeli-nokia','Nokia','dt-duymeli-telefonlar',2,1,'nokia'),
  ('dt-duymeli-panasonic','Panasonic','dt-duymeli-telefonlar',3,1,'panasonic'),
  ('dt-duymeli-butun-brendler','Bütün brendlər','dt-duymeli-telefonlar',4,1,NULL),

  ('ss-smart-saatlar-apple','Apple','ss-smart-saatlar',1,1,'apple'),
  ('ss-smart-saatlar-borofone','Borofone','ss-smart-saatlar',2,1,'borofone'),
  ('ss-smart-saatlar-garmin','Garmin','ss-smart-saatlar',3,1,'garmin'),
  ('ss-smart-saatlar-huawei','Huawei','ss-smart-saatlar',4,1,'huawei'),
  ('ss-smart-saatlar-samsung','Samsung','ss-smart-saatlar',5,1,'samsung'),
  ('ss-smart-saatlar-ttec','Ttec','ss-smart-saatlar',6,1,'ttec'),
  ('ss-smart-saatlar-wonlex','Wonlex','ss-smart-saatlar',7,1,'wonlex'),
  ('ss-smart-saatlar-xiaomi','Xiaomi','ss-smart-saatlar',8,1,'xiaomi'),
  ('ss-smart-saatlar-aksesuarlar','Smart saatlar üçün aksesuarlar','ss-smart-saatlar',9,1,NULL),
  ('ss-smart-saatlar-butun-brendler','Bütün brendlər','ss-smart-saatlar',10,1,NULL),

  ('ek-pocketbook','PocketBook','ek-elektron-kitablar',1,1,'pocketbook'),
  ('ql-simsiz','Simsiz qulaqlıqlar','ql-qulaqliqlar',1,1,NULL),
  ('ql-simli','Simli qulaqlıqlar','ql-qulaqliqlar',2,1,NULL),
  ('ql-usaq','Uşaqlar üçün qulaqlıqlar','ql-qulaqliqlar',3,1,NULL),
  ('ql-oyun','Oyun üçün qulaqlıqlar','ql-qulaqliqlar',4,1,NULL),
  ('ql-butun','Bütün qulaqlıqlar','ql-qulaqliqlar',5,1,NULL),
  ('qs-kisi','Kişi saatları','qs-qol-saatlari',1,1,NULL),
  ('qs-qadin','Qadın saatları','qs-qol-saatlari',2,1,NULL),
  ('qs-usaq','Uşaq saatları','qs-qol-saatlari',3,1,NULL),
  ('qs-butun','Bütün saatlar','qs-qol-saatlari',4,1,NULL),
  ('eo-gigaset','Gigaset','eo-ev-ofis-telefonlari',1,1,'gigaset'),

  -- Noutbuklar və kompüter texnikası
  ('noutbuklar-ve-komp-b-01-noutbuklar','Noutbuklar','noutbuklar-ve-komp',1,1,NULL),
  ('noutbuklar-ve-komp-b-02-masaustu-komputerler','Masaüstü kompüterlər','noutbuklar-ve-komp',2,1,NULL),
  ('noutbuklar-ve-komp-b-03-monitorlar','Monitorlar','noutbuklar-ve-komp',3,1,NULL),
  ('noutbuklar-ve-komp-b-04-printer-ve-ofis','Printer və ofis','noutbuklar-ve-komp',4,1,NULL),
  ('noutbuklar-ve-komp-b-05-sebeke-ve-saxlama','Şəbəkə və saxlama','noutbuklar-ve-komp',5,1,NULL),
  ('noutbuklar-ve-komp-b-06-periferiya','Periferiya','noutbuklar-ve-komp',6,1,NULL),
  ('noutbuklar-ve-komp-b-07-proqram-teminati','Proqram təminatı','noutbuklar-ve-komp',7,1,NULL),
  ('nb-oyun','Oyun noutbukları','noutbuklar-ve-komp-b-01-noutbuklar',1,1,NULL),
  ('nb-is','İş üçün noutbuklar','noutbuklar-ve-komp-b-01-noutbuklar',2,1,NULL),
  ('nb-ultra-yungul','Ultra yüngül','noutbuklar-ve-komp-b-01-noutbuklar',3,1,NULL),
  ('nb-butun','Bütün noutbuklar','noutbuklar-ve-komp-b-01-noutbuklar',4,1,NULL),
  ('pc-hazir-sistem','Hazır sistemlər','noutbuklar-ve-komp-b-02-masaustu-komputerler',1,1,NULL),
  ('pc-monoblok','Monobloklar','noutbuklar-ve-komp-b-02-masaustu-komputerler',2,1,NULL),
  ('mn-oyun','Oyun monitorları','noutbuklar-ve-komp-b-03-monitorlar',1,1,NULL),
  ('mn-ofis','Ofis və dizayn','noutbuklar-ve-komp-b-03-monitorlar',2,1,NULL),
  ('mn-butun','Bütün monitorlar','noutbuklar-ve-komp-b-03-monitorlar',3,1,NULL),
  ('pr-printer','Printerlər','noutbuklar-ve-komp-b-04-printer-ve-ofis',1,1,NULL),
  ('pr-skaner','Skanerlər','noutbuklar-ve-komp-b-04-printer-ve-ofis',2,1,NULL),
  ('pr-serencam','Sərəncəm','noutbuklar-ve-komp-b-04-printer-ve-ofis',3,1,NULL),
  ('nw-router','Router və Wi-Fi','noutbuklar-ve-komp-b-05-sebeke-ve-saxlama',1,1,NULL),
  ('nw-nas','NAS','noutbuklar-ve-komp-b-05-sebeke-ve-saxlama',2,1,NULL),
  ('nw-disk','HDD / SSD','noutbuklar-ve-komp-b-05-sebeke-ve-saxlama',3,1,NULL),
  ('ak-sican-klaviatura','Siçan və klaviatura','noutbuklar-ve-komp-b-06-periferiya',1,1,NULL),
  ('ak-canta','Noutbuk çantaları','noutbuklar-ve-komp-b-06-periferiya',2,1,NULL),
  ('ak-diger','Digər aksesuarlar','noutbuklar-ve-komp-b-06-periferiya',3,1,NULL),
  ('sw-os','Əməliyyat sistemləri','noutbuklar-ve-komp-b-07-proqram-teminati',1,1,NULL),
  ('sw-antivirus','Antivirus','noutbuklar-ve-komp-b-07-proqram-teminati',2,1,NULL),

  -- Oyun konsolları və aksesuarlar
  ('oyun-konsollar-b-01-oyun-konsollari','Oyun konsolları','oyun-konsollar',1,1,NULL),
  ('oyun-konsollar-b-02-oyun-aksesuarlari','Oyun aksesuarları','oyun-konsollar',2,1,NULL),
  ('oyun-konsollar-b-03-vr-ve-eylence','VR və əyləncə','oyun-konsollars',3,1,NULL),
  ('oyun-konsollar-b-04-oyun-ucun-mehsullar','Oyun üçün məhsullar','oyun-konsollar',4,1,NULL),
  ('gm-playstation','PlayStation','oyun-konsollar-b-01-oyun-konsollari',1,1,'playstation'),
  ('gm-xbox','Xbox','oyun-konsollar-b-01-oyun-konsollari',2,1,'xbox'),
  ('gm-nintendo','Nintendo Switch','oyun-konsollar-b-01-oyun-konsollari',3,1,'nintendo-switch'),
  ('gm-butun-konsol','Bütün konsollar','oyun-konsollar-b-01-oyun-konsollari',4,1,NULL),
  ('ga-qeympad','Qeympadlar','oyun-konsollar-b-02-oyun-aksesuarlari',1,1,NULL),
  ('ga-qulaqliq','Oyun üçün qulaqlıqlar','oyun-konsollar-b-02-oyun-aksesuarlari',2,1,NULL),
  ('ga-usaq','Uşaq üçün','oyun-konsollar-b-02-oyun-aksesuarlari',3,1,NULL),
  ('vr-eynek','VR eynəkləri','oyun-konsollar-b-03-vr-ve-eylence',1,1,NULL),
  ('vr-racing','Racing və stendlər','oyun-konsollar-b-03-vr-ve-eylence',2,1,NULL),
  ('gx-kreslo','Oyun kresloları','oyun-konsollar-b-04-oyun-ucun-mehsullar',1,1,NULL),
  ('gx-isiq','Masaüstü işıqlandırma','oyun-konsollar-b-04-oyun-ucun-mehsullar',2,1,NULL),

  -- TV, audio və video
  ('tv-audio-video-b-01-televizorlar','Televizorlar','tv-audio-video',1,1,NULL),
  ('tv-audio-video-b-02-audio','Audio','tv-audio-video',2,1,NULL),
  ('tv-audio-video-b-03-video-texnika','Video texnika','tv-audio-video',3,1,NULL),
  ('tv-audio-video-b-04-aksesuarlar','Aksesuarlar','tv-audio-video',4,1,NULL),
  ('tv-led','LED TV','tv-audio-video-b-01-televizorlar',1,1,NULL),
  ('tv-oled-qled','OLED / QLED','tv-audio-video-b-01-televizorlar',2,1,NULL),
  ('tv-butun','Bütün televizorlar','tv-audio-video-b-01-televizorlar',3,1,NULL),
  ('au-saundbar','Saundbar','tv-audio-video-b-02-audio',1,1,NULL),
  ('au-kinoteatr','Ev kinoteatrı','tv-audio-video-b-02-audio',2,1,NULL),
  ('au-kalonka','Kalonkalar','tv-audio-video-b-02-audio',3,1,NULL),
  ('vd-proyektor','Proyektorlar','tv-audio-video-b-03-video-texnika',1,1,NULL),
  ('vd-pleyer','Media pleyerlər','tv-audio-video-b-03-video-texnika',2,1,NULL),
  ('tv-ak-asilqan','TV asılqanları','tv-audio-video-b-04-aksesuarlar',1,1,NULL),
  ('tv-ak-kabel','Kabellər və adapterlər','tv-audio-video-b-04-aksesuarlar',2,1,NULL),

  -- Böyük məişət texnikası
  ('boyuk-meiset-b-01-metbex','Mətbəx','boyuk-meiset',1,1,NULL),
  ('boyuk-meiset-b-02-temizlik','Təmizlik','boyuk-meiset',2,1,NULL),
  ('boyuk-meiset-b-03-bisirme','Bişirmə','boyuk-meiset',3,1,NULL),
  ('boyuk-meiset-b-04-diger','Digər','boyuk-meiset',4,1,NULL),
  ('bk-soyuducu','Soyuducular','boyuk-meiset-b-01-metbex',1,1,NULL),
  ('bk-dondurucu-kamera','Dondurucu kameralar','boyuk-meiset-b-01-metbex',2,1,NULL),
  ('bk-qabyuyan','Qabyuyan maşınlar','boyuk-meiset-b-01-metbex',3,1,NULL),
  ('bk-panel','Bişirmə panelləri','boyuk-meiset-b-01-metbex',4,1,NULL),
  ('bk-paltaryuyan','Paltaryuyan maşınlar','boyuk-meiset-b-02-temizlik',1,1,NULL),
  ('bk-quruducu','Quruducular','boyuk-meiset-b-02-temizlik',2,1,NULL),
  ('bk-soba','Sobalar və plitələr','boyuk-meiset-b-03-bisirme',1,1,NULL),
  ('bk-mikro','Mikrodalğa sobalar','boyuk-meiset-b-03-bisirme',2,1,NULL),
  ('bk-aspirator','Aspiratorlar','boyuk-meiset-b-04-diger',1,1,NULL),
  ('bk-butun','Bütün böyük məişət','boyuk-meiset-b-04-diger',2,1,NULL),

  -- Kiçik məişət texnikası
  ('kicik-meiset-b-01-yemek-hazirlanmasi','Yemək hazırlanması','kicik-meiset',1,1,NULL),
  ('kicik-meiset-b-02-temizlik','Təmizlik','kicik-meiset',2,1,NULL),
  ('kicik-meiset-b-03-sexsi-qulluq','Şəxsi qulluq','kicik-meiset',3,1,NULL),
  ('kicik-meiset-b-04-diger','Digər','kicik-meiset',4,1,NULL),
  ('km-fritoz','Fritozlar','kicik-meiset-b-01-yemek-hazirlanmasi',1,1,NULL),
  ('km-toster','Tosterlər','kicik-meiset-b-01-yemek-hazirlanmasi',2,1,NULL),
  ('km-corek-bisiren','Çörək bişirən','kicik-meiset-b-01-yemek-hazirlanmasi',3,1,NULL),
  ('km-multibisirici','Multibişiricilər','kicik-meiset-b-01-yemek-hazirlanmasi',4,1,NULL),
  ('km-mikrodalga-soba','Mikrodalğalı sobalar','kicik-meiset-b-01-yemek-hazirlanmasi',5,1,NULL),
  ('km-terevez-meyve-qurudan','Tərəvəz və meyvə qurudan','kicik-meiset-b-01-yemek-hazirlanmasi',6,1,NULL),
  ('km-elektrik-soba','Elektrik sobalar','kicik-meiset-b-01-yemek-hazirlanmasi',7,1,NULL),
  ('km-buxarli-bisirici','Buxarlı bişiricilər','kicik-meiset-b-01-yemek-hazirlanmasi',8,1,NULL),
  ('km-yemek-butun','Bütün yemək hazırlanması məhsulları','kicik-meiset-b-01-yemek-hazirlanmasi',9,1,NULL),
  ('km-qehve','Qəhvə maşınları','kicik-meiset-b-01-yemek-hazirlanmasi',10,1,NULL),
  ('km-blender','Blender və mikser','kicik-meiset-b-01-yemek-hazirlanmasi',11,1,NULL),
  ('km-tozsoran','Tozsoranlar','kicik-meiset-b-02-temizlik',1,1,NULL),
  ('km-utu','Ütülər','kicik-meiset-b-02-temizlik',2,1,NULL),
  ('km-fen','Fenlər','kicik-meiset-b-03-sexsi-qulluq',1,1,NULL),
  ('km-trimmer','Trimmer və maşınlar','kicik-meiset-b-03-sexsi-qulluq',2,1,NULL),
  ('km-terezi','Tərəzi','kicik-meiset-b-04-diger',1,1,NULL),
  ('km-butun','Bütün kiçik məişət','kicik-meiset-b-04-diger',2,1,NULL),

  -- İqlim texnikası
  ('iqlim-texnikasi-b-01-soyutma-ve-isitme','Soyutma və isitmə','iqlim-texnikasi',1,1,NULL),
  ('iqlim-texnikasi-b-02-hava-keyfiyyeti','Hava keyfiyyəti','iqlim-texnikasi',2,1,NULL),
  ('iqlim-texnikasi-b-03-su-qizdiricilari','Su qızdırıcıları','iqlim-texnikasi',3,1,NULL),
  ('iqlim-texnikasi-b-04-diger','Digər','iqlim-texnikasi',4,1,NULL),
  ('iq-kondisioner','Kondisionerlər','iqlim-texnikasi-b-01-soyutma-ve-isitme',1,1,NULL),
  ('iq-ventilyator','Ventilyatorlar','iqlim-texnikasi-b-01-soyutma-ve-isitme',2,1,NULL),
  ('iq-isitme','İsitmə cihazları','iqlim-texnikasi-b-01-soyutma-ve-isitme',3,1,NULL),
  ('iq-temizleyici','Təmizləyicilər','iqlim-texnikasi-b-02-hava-keyfiyyeti',1,1,NULL),
  ('iq-nemlendirici','Nəmləndiricilər','iqlim-texnikasi-b-02-hava-keyfiyyeti',2,1,NULL),
  ('iq-boiler','Boilerlər','iqlim-texnikasi-b-03-su-qizdiricilari',1,1,NULL),
  ('iq-qizdirici','Qızdırıcılar','iqlim-texnikasi-b-03-su-qizdiricilari',2,1,NULL),
  ('iq-butun','Bütün iqlim texnikası','iqlim-texnikasi-b-04-diger',1,1,NULL),

  -- Gözəllik və baxım
  ('gozellik-baxim-b-01-sac-qullugu','Saç qulluğu','gozellik-baxim',1,1,NULL),
  ('gozellik-baxim-b-02-uz-ve-beden','Üz və bədən','gozellik-baxim',2,1,NULL),
  ('gozellik-baxim-b-03-kisi-qullugu','Kişi qulluğu','gozellik-baxim',3,1,NULL),
  ('gozellik-baxim-b-04-diger','Digər','gozellik-baxim',4,1,NULL),
  ('gz-sac-fen','Fen və ütülər','gozellik-baxim-b-01-sac-qullugu',1,1,NULL),
  ('gz-sac-masin','Maşınlar və trimmerlər','gozellik-baxim-b-01-sac-qullugu',2,1,NULL),
  ('gz-epilyator','Epilyatorlar','gozellik-baxim-b-02-uz-ve-beden',1,1,NULL),
  ('gz-masaj','Masaj cihazları','gozellik-baxim-b-02-uz-ve-beden',2,1,NULL),
  ('gz-kisi-qirxan','Üzqırxan','gozellik-baxim-b-03-kisi-qullugu',1,1,NULL),
  ('gz-kisi-trimmer','Trimmer','gozellik-baxim-b-03-kisi-qullugu',2,1,NULL),
  ('gz-butun','Bütün gözəllik','gozellik-baxim-b-04-diger',1,1,NULL),

  -- Tibbi məhsullar
  ('tibbi-mehsullar-b-01-olcu-cihazlari','Ölçü cihazları','tibbi-mehsullar',1,1,NULL),
  ('tibbi-mehsullar-b-02-ortoopediya','Ortoopediya','tibbi-mehsullar',2,1,NULL),
  ('tibbi-mehsullar-b-03-reabilitasiya','Reabilitasiya','tibbi-mehsullar',3,1,NULL),
  ('tibbi-mehsullar-b-04-diger','Digər','tibbi-mehsullar',4,1,NULL),
  ('tb-tonometr','Tonometrlər','tibbi-mehsullar-b-01-olcu-cihazlari',1,1,NULL),
  ('tb-termometr','Termometrlər','tibbi-mehsullar-b-01-olcu-cihazlari',2,1,NULL),
  ('tb-ortopediya','Bandaj və korsetlər','tibbi-mehsullar-b-02-ortoopediya',1,1,NULL),
  ('tb-inhalyator','İnhalyatorlar','tibbi-mehsullar-b-03-reabilitasiya',1,1,NULL),
  ('tb-butun','Bütün tibbi məhsullar','tibbi-mehsullar-b-04-diger',1,1,NULL),

  -- Ev və bağ
  ('ev-bag-b-01-bag','Bağ','ev-bag',1,1,NULL),
  ('ev-bag-b-02-isiqlandirma','İşıqlandırma','ev-bag',2,1,NULL),
  ('ev-bag-b-03-temir-ve-alet','Təmir və alət','ev-bag',3,1,NULL),
  ('ev-bag-b-04-diger','Digər','ev-bag',4,1,NULL),
  ('eb-bag-alt','Bağ alətləri','ev-bag-b-01-bag',1,1,NULL),
  ('eb-suvarma','Suvarma','ev-bag-b-01-bag',2,1,NULL),
  ('eb-lampa','Lampalar','ev-bag-b-02-isiqlandirma',1,1,NULL),
  ('eb-xarici','Xarici işıqlar','ev-bag-b-02-isiqlandirma',2,1,NULL),
  ('eb-elektrik-alt','Elektrik alətləri','ev-bag-b-03-temir-ve-alet',1,1,NULL),
  ('eb-el-alt','Əl alətləri','ev-bag-b-03-temir-ve-alet',2,1,NULL),
  ('eb-butun','Bütün ev və bağ','ev-bag-b-04-diger',1,1,NULL),

  -- Mebel, tekstil və dekor
  ('mebel-tekstil-dekor-b-01-mebel','Mebel','mebel-tekstil-dekor',1,1,NULL),
  ('mebel-tekstil-dekor-b-02-tekstil','Tekstil','mebel-tekstil-dekor',2,1,NULL),
  ('mebel-tekstil-dekor-b-03-dekor','Dekor','mebel-tekstil-dekor',3,1,NULL),
  ('mebel-tekstil-dekor-b-04-diger','Digər','mebel-tekstil-dekor',4,1,NULL),
('mb-qonaq','Qonaq otağı','mebel-tekstil-dekor-b-01-mebel',1,1,NULL),
('mb-yataq','Yataq otağı','mebel-tekstil-dekor-b-01-mebel',2,1,NULL),
('mb-yumsaq','Yumşaq mebel','mebel-tekstil-dekor-b-01-mebel',3,1,NULL),
('mb-genc','Gənc otağı','mebel-tekstil-dekor-b-01-mebel',4,1,NULL),
('mb-tv-stendler','TV stendlər','mebel-tekstil-dekor-b-01-mebel',5,1,NULL),
('mb-jurnal-masalari','Jurnal masaları','mebel-tekstil-dekor-b-01-mebel',6,1,NULL),
('mb-bag-mebeli','Bağ mebeli','mebel-tekstil-dekor-b-01-mebel',7,1,NULL),
('tx-ev-tekstili','Ev tekstili','mebel-tekstil-dekor-b-02-tekstil',1,1,NULL),
('dk-dekor-interyer','Dekor və interyer','mebel-tekstil-dekor-b-03-dekor',1,1,NULL),
('mb-butun','Bütün mebel və dekor','mebel-tekstil-dekor-b-04-diger',1,1,NULL),

('mb-qonaq-destler','Dəstlər','mb-qonaq',1,1,NULL),
('mb-qonaq-masa','Masa','mb-qonaq',2,1,NULL),
('mb-qonaq-konsollar','Konsollar','mb-qonaq',3,1,NULL),
('mb-qonaq-vitrin','Vitrin','mb-qonaq',4,1,NULL),
('mb-qonaq-oturacaqlar','Oturacaqlar','mb-qonaq',5,1,NULL),
('mb-qonaq-guzguler','Güzgülər','mb-qonaq',6,1,NULL),
('mb-qonaq-butun','Bütün qonaq otağı mebelləri','mb-qonaq',7,1,NULL),

('mb-yataq-destler','Dəstlər','mb-yataq',1,1,NULL),
('mb-yataq-dolablar','Dolablar','mb-yataq',2,1,NULL),
('mb-yataq-komod-makiyaj','Komod və makiyaj masası','mb-yataq',3,1,NULL),
('mb-yataq-tumba','Yataq yanı tumbalar','mb-yataq',4,1,NULL),
('mb-yataq-yataqlar','Yataqlar','mb-yataq',5,1,NULL),
('mb-yataq-dosekler','Döşəklər','mb-yataq',6,1,NULL),
('mb-yataq-guzguler','Güzgülər','mb-yataq',7,1,NULL),
('mb-yataq-puflar','Puflar','mb-yataq',8,1,NULL),
('mb-yataq-butun','Bütün yataq otağı mebelləri','mb-yataq',9,1,NULL),

('mb-yumsaq-destler','Dəstlər','mb-yumsaq',1,1,NULL),
('mb-yumsaq-kunc-divanlar','Künc divanlar','mb-yumsaq',2,1,NULL),
('mb-yumsaq-divanlar','Divanlar','mb-yumsaq',3,1,NULL),
('mb-yumsaq-kreslolar','Kreslolar','mb-yumsaq',4,1,NULL),
('mb-yumsaq-puflar','Puflar','mb-yumsaq',5,1,NULL),
('mb-yumsaq-butun','Bütün yumşaq mebel məhsulları','mb-yumsaq',6,1,NULL),

('mb-genc-dolab','Dolab','mb-genc',1,1,NULL),
('mb-genc-kitab-dolabi','Kitab dolabı','mb-genc',2,1,NULL),
('mb-genc-is-masasi','İş masası','mb-genc',3,1,NULL),
('mb-genc-komod','Komod','mb-genc',4,1,NULL),
('mb-genc-yataq','Yataq','mb-genc',5,1,NULL),
('mb-genc-destler','Dəstlər','mb-genc',6,1,NULL),
('mb-genc-guzgu','Güzgü','mb-genc',7,1,NULL),
('mb-genc-refler','Rəflər','mb-genc',8,1,NULL),
('mb-genc-oturacaq','Oturacaq','mb-genc',9,1,NULL),
('mb-genc-butun','Bütün gənc otağı mebelləri','mb-genc',10,1,NULL),

('mb-tv-dest','Dəst TV stendlər','mb-tv-stendler',1,1,NULL),
('mb-tv-alt','Alt TV stendlər','mb-tv-stendler',2,1,NULL),
('mb-tv-ust','Üst TV stendlər','mb-tv-stendler',3,1,NULL),
('mb-tv-butun','Bütün TV stendlər','mb-tv-stendler',4,1,NULL),

('mb-jurnal-butun-brendler','Bütün brendlər','mb-jurnal-masalari',1,1,NULL),
('mb-bag-butun-brendler','Bütün brendlər','mb-bag-mebeli',1,1,NULL),

('tx-yataq-destleri','Yataq dəstləri','tx-ev-tekstili',1,1,NULL),
('tx-yataq-ortukleri','Yataq örtükləri','tx-ev-tekstili',2,1,NULL),
('tx-yastiqlar','Yastıqlar','tx-ev-tekstili',3,1,NULL),
('tx-yorganlar','Yorğanlar','tx-ev-tekstili',4,1,NULL),
('tx-hamam','Hamam üçün tekstil','tx-ev-tekstili',5,1,NULL),
('tx-metbex','Mətbəx tekstili','tx-ev-tekstili',6,1,NULL),
('tx-sufreler','Süfrələr','tx-ev-tekstili',7,1,NULL),
('tx-xalcalar','Xalçalar','tx-ev-tekstili',8,1,NULL),
('tx-muxtelif','Müxtəlif','tx-ev-tekstili',9,1,NULL),
('tx-butun','Bütün ev tekstili','tx-ev-tekstili',10,1,NULL),

('dk-cercive','Çərçivələr','dk-dekor-interyer',1,1,NULL),
('dk-vaza','Vazalar və aksesuarlar','dk-dekor-interyer',2,1,NULL),
('dk-butun','Bütün dekor və interyer','dk-dekor-interyer',3,1,NULL),

  -- Qab-qacaq, tava-qazan
  ('qab-qacaq-b-01-qab-qacaq','Qab-qacaq','qab-qacaq',1,1,NULL),
  ('qab-qacaq-b-02-bisirme','Bişirmə','qab-qacaq',2,1,NULL),
  ('qab-qacaq-b-03-bicaq-ve-lovhe','Bıçaq və lövhə','qab-qacaq',3,1,NULL),
  ('qab-qacaq-b-04-diger','Digər','qab-qacaq',4,1,NULL),
  ('qq-dest','Qab dəstləri','qab-qacaq-b-01-qab-qacaq',1,1,NULL),
  ('qq-fincan','Fincan və stəkan','qab-qacaq-b-01-qab-qacaq',2,1,NULL),
  ('qq-tava','Tavalar','qab-qacaq-b-02-bisirme',1,1,NULL),
  ('qq-qazan','Qazanlar','qab-qacaq-b-02-bisirme',2,1,NULL),
  ('qq-bicak','Bıçaq dəstləri','qab-qacaq-b-03-bicaq-ve-lovhe',1,1,NULL),
  ('qq-lovhe','Kəsmə lövhələri','qab-qacaq-b-03-bicaq-ve-lovhe',2,1,NULL),
  ('qq-butun','Bütün qab-qacaq','qab-qacaq-b-04-diger',1,1,NULL);

  -- typo protection
  UPDATE tmp_cat_seed
  SET parent_slug = 'oyun-konsollar'
  WHERE parent_slug = 'oyun-konsollars';

  -- ------------------------------------------------------------
  -- 3) Upsert categories by slug
  -- ------------------------------------------------------------
  INSERT INTO categories (name, slug, description, image_url, is_active, sort_order, parent_id, brand_id)
  SELECT s.name, s.slug, NULL, NULL, s.is_active, s.sort_order, NULL, NULL
  FROM tmp_cat_seed s
  ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    is_active = VALUES(is_active),
    sort_order = VALUES(sort_order);

  -- Rebuild parent_id from parent_slug
  UPDATE categories c
  LEFT JOIN tmp_cat_seed s
    ON s.slug = c.slug COLLATE utf8mb4_unicode_ci
  LEFT JOIN categories p
    ON p.slug COLLATE utf8mb4_unicode_ci = s.parent_slug
  SET c.parent_id = p.id
  WHERE s.slug IS NOT NULL;

  -- Remove stale categories under Mebel and Tekstil branches
  -- (keep only slugs defined in tmp_cat_seed)
  DELETE c
  FROM categories c
  LEFT JOIN tmp_cat_seed keep_s
    ON keep_s.slug = c.slug COLLATE utf8mb4_unicode_ci
  WHERE keep_s.slug IS NULL
    AND (
      c.parent_id IN (
        SELECT r.id
        FROM (
          SELECT id
          FROM categories
          WHERE slug COLLATE utf8mb4_unicode_ci IN (
            'mebel-tekstil-dekor-b-01-mebel',
            'mebel-tekstil-dekor-b-02-tekstil'
          )
        ) r
      )
      OR c.parent_id IN (
        SELECT lvl1.id
        FROM (
          SELECT c1.id
          FROM categories c1
          WHERE c1.parent_id IN (
            SELECT rr.id
            FROM (
              SELECT id
              FROM categories
              WHERE slug COLLATE utf8mb4_unicode_ci IN (
                'mebel-tekstil-dekor-b-01-mebel',
                'mebel-tekstil-dekor-b-02-tekstil'
              )
            ) rr
          )
        ) lvl1
      )
    );

  -- Rebuild brand_id from brand_slug
  UPDATE categories c
  LEFT JOIN tmp_cat_seed s
    ON s.slug = c.slug COLLATE utf8mb4_unicode_ci
  LEFT JOIN brands b
    ON b.slug COLLATE utf8mb4_unicode_ci = s.brand_slug
  SET c.brand_id = b.id
  WHERE s.slug IS NOT NULL;

  DROP TEMPORARY TABLE IF EXISTS tmp_cat_seed;

  COMMIT;

  -- Quick checks:
  -- SELECT id, name, slug, parent_id, brand_id, sort_order FROM categories ORDER BY id;
  -- SELECT id, name, slug FROM categories WHERE parent_id IS NULL ORDER BY sort_order;
