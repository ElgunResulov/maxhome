<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/user_auth.php';

$pdo = db();
requireUserAuth('checkout.php');
$items = fetchCartItems($pdo);
$totals = cartTotals($items);

if (empty($items)) {
    header('Location: shopping_cart.php');
    exit;
}

$form = [
    'first_name' => '',
    'last_name' => '',
    'phone' => '',
    'city' => '',
    'zip_code' => '',
    'address_line1' => '',
    'address_line2' => '',
    'payment_method' => 'card',
];
$error = '';
$success = '';
$whatsAppNumber = (string) (getSetting($pdo, 'orders_whatsapp_number', '994559375801') ?? ''); // Empty = open WhatsApp chat picker.
$canOfficePay = isUserLoggedIn() && userHasAnyRole(['seller', 'satici', 'satıcı', 'admin']);

// Allow preselect from cart page.
$pmFromGet = trim((string) ($_GET['payment_method'] ?? ''));
if ($pmFromGet === 'cash' || $pmFromGet === 'office_payment') {
    $form['payment_method'] = 'cash_on_delivery';
} elseif ($pmFromGet === 'installment') {
    $form['payment_method'] = 'card';
} elseif (in_array($pmFromGet, ['card', 'cash_on_delivery'], true)) {
    $form['payment_method'] = $pmFromGet;
}

if (!in_array($form['payment_method'], ['card', 'cash_on_delivery'], true)) {
    $form['payment_method'] = 'card';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $default) {
        $form[$key] = trim((string) ($_POST[$key] ?? $default));
    }
    if ($form['payment_method'] === 'installment' || $form['payment_method'] === 'office_payment') {
        $form['payment_method'] = $form['payment_method'] === 'office_payment' ? 'cash_on_delivery' : 'card';
    }

    if ($form['first_name'] === '' || $form['last_name'] === '' || $form['city'] === '' || $form['address_line1'] === '') {
        $error = 'Lutfen zorunlu alanlari doldurun.';
    } elseif (!in_array($form['payment_method'], ['card', 'cash_on_delivery'], true)) {
        $error = 'Gecersiz odeme yontemi.';
    } else {
        try {
            $orderedItems = $items;
            $form['in_store_sale'] = $canOfficePay && $form['payment_method'] === 'cash_on_delivery';
            $orderNumber = createOrderFromActiveCart($pdo, $form, currentUserId());

            // Seller/admin nağd satış: anbar panelinə yönləndir.
            if ($canOfficePay && $form['payment_method'] === 'cash_on_delivery') {
                header('Location: warehouse_sales.php');
                exit;
            }

            $baseUrl = (
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://'
            ) . ($_SERVER['HTTP_HOST'] ?? 'localhost');

            $messageLines = [
                'Yeni sifaris var',
                'Sifaris No: ' . $orderNumber,
                'Musteri: ' . $form['first_name'] . ' ' . $form['last_name'],
                'Telefon: ' . ($form['phone'] !== '' ? $form['phone'] : '-'),
                '',
                'Mehsullar:',
            ];

            foreach ($orderedItems as $item) {
                $slug = trim((string) ($item['slug'] ?? ''));
                $productId = (int) ($item['product_id'] ?? 0);
                $productLink = $slug !== ''
                    ? $baseUrl . '/maxhome/product_details.php?slug=' . urlencode($slug)
                    : $baseUrl . '/maxhome/product_details.php?id=' . $productId;

                $messageLines[] = '- ' . (string) $item['name'] . ' x' . (int) $item['quantity'];
                $messageLines[] = '  Link: ' . $productLink;
            }

            $messageLines[] = '';
            $messageLines[] = 'Toplam: ' . number_format((float) $totals['total'], 2, '.', '') . ' ₼';

            $waBase = 'https://wa.me/' . preg_replace('/\D+/', '', $whatsAppNumber);
            if (trim($whatsAppNumber) === '') {
                $waBase = 'https://wa.me/';
            }
            $waUrl = $waBase . '?text=' . rawurlencode(implode("\n", $messageLines));

            header('Location: ' . $waUrl);
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Ödəniş | MAXHOME</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;family=Manrope:wght@400;500;600&amp;family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?php echo filemtime(__DIR__ . '/assets/css/navbar.css'); ?>">
    <link rel="stylesheet" href="assets/css/checkout.css">
</head>
<body>
    <?php $currentPage = ''; ?>
    <?php include 'navbar.php'; ?>
    <main class="main-content">
        <?php if ($error !== ''): ?>
            <div style="margin-bottom: 12px; padding: 12px; border-radius: 8px; background:#b91c1c; color:#fff;"><?php echo e($error); ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div style="margin-bottom: 12px; padding: 12px; border-radius: 8px; background:#047857; color:#fff;"><?php echo e($success); ?></div>
        <?php endif; ?>

        <div class="checkout-grid">
            <div class="checkout-grid__forms section-stack">
                <form method="post" class="form-section">
                    <div class="section-header">
                        <h2 class="section-title">Çatdırılma məlumatları</h2>
                        <div class="header-line"></div>
                    </div>
                    <div class="input-grid">
                        <div class="input-group">
                            <label class="input-label">Ad</label>
                            <input class="text-input" name="first_name" value="<?php echo e($form['first_name']); ?>" type="text" required />
                        </div>
                        <div class="input-group">
                            <label class="input-label">Soyad</label>
                            <input class="text-input" name="last_name" value="<?php echo e($form['last_name']); ?>" type="text" required />
                        </div>
                        <div class="input-group span-2">
                            <label class="input-label">Küçə ünvanı</label>
                            <input class="text-input" name="address_line1" value="<?php echo e($form['address_line1']); ?>" type="text" required />
                        </div>
                        <div class="input-group">
                            <label class="input-label">Şəhər</label>
                            <input class="text-input" name="city" value="<?php echo e($form['city']); ?>" type="text" required />
                        </div>
                        <div class="input-group">
                            <label class="input-label">Poçt indeksi</label>
                            <input class="text-input" name="zip_code" value="<?php echo e($form['zip_code']); ?>" type="text" />
                        </div>
                        <div class="input-group">
                            <label class="input-label">Telefon</label>
                            <input class="text-input" name="phone" value="<?php echo e($form['phone']); ?>" type="text" />
                        </div>
                        <div class="input-group">
                            <label class="input-label">Ünvan sətri 2</label>
                            <input class="text-input" name="address_line2" value="<?php echo e($form['address_line2']); ?>" type="text" />
                        </div>
                        <div class="input-group span-2">
                            <label class="input-label">Ödəniş üsulu</label>
                            <select class="text-input" name="payment_method">
                                <option value="card" <?php echo $form['payment_method'] === 'card' ? 'selected' : ''; ?>>Kartla ödəniş</option>
                                <option value="cash_on_delivery" <?php echo $form['payment_method'] === 'cash_on_delivery' ? 'selected' : ''; ?>>Nağd ödəniş</option>
                            </select>
                        </div>
                    </div>
                    <button class="btn-purchase" type="submit" style="margin-top: 18px;">
                        Təhlükəsiz sifarişi tamamla
                        <span class="material-symbols-outlined">arrow_forward</span>
                    </button>
                </form>
            </div>

            <div class="checkout-grid__sidebar">
                <div class="order-summary">
                    <h3 class="order-summary__title">Sifariş xülasəsi</h3>
                    <div class="cart-items">
                        <?php foreach ($items as $item): ?>
                            <div class="cart-item">
                                <div class="cart-item__img-box">
                                    <img alt="<?php echo e($item['name']); ?>" src="<?php echo e($item['image_url'] ?: 'https://via.placeholder.com/160x160?text=No+Image'); ?>" />
                                </div>
                                <div class="cart-item__details">
                                    <div>
                                        <h4 class="cart-item__name"><?php echo e($item['name']); ?></h4>
                                        <p class="cart-item__meta">Miqdar: <?php echo (int) $item['quantity']; ?></p>
                                    </div>
                                    <p class="cart-item__price"><?php echo number_format((float) $item['unit_price'] * (int) $item['quantity'], 2); ?> ₼</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="divider"></div>
                    <div class="price-breakdown">
                        <div class="total-row">
                            <span class="total-label">Ümumi</span>
                            <span class="total-value"><?php echo number_format($totals['total'], 2); ?> ₼</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <footer class="footer main-footer">
        <div class="footer-container">
            <div class="footer-brand">
                <div class="footer-logo">MAXHOME</div>
                <p class="footer-tagline">
                    Dəqiq mühəndisliklə hazırlanmış ev texnologiyaları ilə insan təcrübəsini yüksəldirik.
                </p>
            </div>
            <div class="footer-col">
                <h4 class="footer-col__title">Dəstək</h4>
                <ul class="footer-list">
                    <li><a class="footer-link" href="support_center.php">Geri qaytarma</a></li>
                    <li><a class="footer-link" href="support_center.php">Çatdırılma</a></li>
                    <li><a class="footer-link" href="support_center.php">Sifarişi izlə</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4 class="footer-col__title">Hüquqi</h4>
                <ul class="footer-list">
                    <li><a class="footer-link" href="support_center.php">Etibar mərkəzi</a></li>
                    <li><a class="footer-link" href="support_center.php">Məxfilik siyasəti</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4 class="footer-col__title">Xəbər bülleteni</h4>
                <p class="newsletter-desc">Yeni premium buraxılışlara eksklüziv çıxış.</p>
                <div class="newsletter-form">
                    <input class="newsletter-input" placeholder="E-poçt ünvanı" type="email" />
                    <button class="newsletter-btn">
                        <span class="material-symbols-outlined">send</span>
                    </button>
                </div>
            </div>
        </div>
        <div class="copyright-section">
            <p class="copyright-text">© 2026 | Developed by INTBAKU LLC</p>
        </div>
    </footer>
</body>

</html>