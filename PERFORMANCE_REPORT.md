# MAXHOME Performans Ölçüm Raporu

Tarih: 19 Temmuz 2026

## Yöntem

- TTFB, toplam süre ve HTML boyutu: `curl.exe`
- Yaklaşık DB ifade sayısı: MySQL global `Questions` sayacı farkı
- Network ve ana iş parçacığı: Lighthouse 12.8.2, mobil throttling
- PHP doğrulaması: `php -l`
- Apache doğrulaması: `httpd -t`

## Önce / sonra

| Sayfa | Önce TTFB | Sonra medyan TTFB | Önce HTML | Sonra HTML |
|---|---:|---:|---:|---:|
| `/` | 578 ms | 129 ms | 269.074 B | 269.285 B |
| `/shop_page.php` | 2.678 ms | 65 ms | 586.506 B | 391.246 B |
| `/categories.php` | 164 ms | 168 ms | 87.049 B | 81.727 B |

Mağaza TTFB değeri yaklaşık %97,6, HTML çıktısı yaklaşık %33,3 azaldı. Ana sayfa TTFB değeri yaklaşık %77,7 azaldı. Kategori TTFB farkı ölçüm gürültüsü sınırında kalırken Tailwind runtime isteği ve yaklaşık 5,3 KB HTML kaldırıldı.

Mağaza isteğinde MySQL `Questions` farkı yaklaşık 9 olarak ölçüldü. İlk teşhisteki yaklaşık 2.116 DB ifadesiyle yöntem birebir aynı olmamakla birlikte sorgu patlamasının kaldırıldığını doğruluyor.

## Network ve Performance

### Mağaza

- 36 network isteği, 21 image isteği
- Toplam transfer: 4.786.042 B
- Total Blocking Time: 0 ms
- CLS: 0
- Ana iş parçacığı çalışması: 2,1 sn
- 5 uzun görev
- Lighthouse gözlenen TTFB: 453 ms

Ürün kartları artık `loading="lazy"` ve `decoding="async"` kullanıyor. İlk teşhiste ilk sayfadaki 51 ürün görselinin tamamı eager yükleniyordu.

### Kategoriler

- 20 network isteği, 7 image isteği
- Toplam transfer: 3.500.718 B
- Total Blocking Time: 0 ms
- CLS: 0,016
- Ana iş parçacığı çalışması: 1,1 sn
- 2 uzun görev
- Tailwind CDN/runtime isteği: 0

Mobil throttling altında FCP/LCP değerleri harici ve büyük görseller nedeniyle yüksek kaldı. Sonraki en yüksek etkili adım görselleri yerelleştirmek, WebP/AVIF türevleri ve uygun `srcset` üretmektir.

## Sunucu ayarları

- Statik CSS/JS ve görsel cache başlıkları canlı yanıtta doğrulandı.
- Apache `mod_deflate` ve `mod_filter` etkinleştirildi.
- PHP 8.2.12 ile eşleşen resmi `php_opcache.dll` kuruldu ve OPcache ayarları açıldı.
- Apache yapılandırması `Syntax OK` sonucunu verdi.

Apache Windows hizmeti yönetici hesabıyla çalıştığı için bu oturumdan tam hizmet yeniden başlatması `Erişim engellendi` sonucunu verdi. Bu nedenle OPcache ve gzip çalışma zamanı doğrulaması, yönetici yetkili Apache yeniden başlatmasından sonra yapılmalıdır.

## Doğrulama sonuçları

- Değiştirilen tüm PHP dosyaları sözdizimi kontrolünden geçti.
- AJAX arama yanıtları geçerli JSON döndürüyor.
- AZ/EN dil oturumu korunuyor.
- Mega menü dosya cache'i üretildi.
- Tailwind CDN referansı kaldırıldı.
- Git diff whitespace kontrolü geçti.
