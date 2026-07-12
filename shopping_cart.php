<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/user_auth.php';

$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $itemId = (int) ($_POST['cart_item_id'] ?? 0);

    if ($action === 'increase' && $itemId > 0) {
        $qty = (int) ($_POST['current_quantity'] ?? 1) + 1;
        updateCartItemQuantity($pdo, $itemId, $qty);
    } elseif ($action === 'decrease' && $itemId > 0) {
        $qty = (int) ($_POST['current_quantity'] ?? 1) - 1;
        updateCartItemQuantity($pdo, $itemId, $qty);
    } elseif ($action === 'remove' && $itemId > 0) {
        updateCartItemQuantity($pdo, $itemId, 0);
    }

    header('Location: shopping_cart.php');
    exit;
}

$items = fetchCartItems($pdo);
$totals = cartTotals($items);
$canOfficePay = isUserLoggedIn() && userHasAnyRole(['seller', 'satici', 'satıcı', 'admin']);
$selectedPaymentMethod = trim((string) ($_GET['payment_method'] ?? ''));
if ($selectedPaymentMethod === 'cash' || $selectedPaymentMethod === 'office_payment') {
    $selectedPaymentMethod = 'cash_on_delivery';
} elseif ($selectedPaymentMethod === 'installment') {
    $selectedPaymentMethod = 'card';
}
if (!in_array($selectedPaymentMethod, ['', 'card', 'cash_on_delivery'], true)) {
    $selectedPaymentMethod = '';
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;family=Manrope:wght@400;500;600&amp;family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/navbar.css">
    <link rel="stylesheet" href="assets/css/shopping_cart.css">
</head>
<body>
    <?php $currentPage = ''; ?>
    <?php include 'navbar.php'; ?>
    <main class="main-content">
        <header class="header">
            <h1 class="header__title">Sizin Səbət</h1>
            <p class="header__subtitle">Səbət verilənlər bazası ilə real vaxtda sinxronlaşdırılır.</p>
        </header>
        <div class="layout-grid">
            <div class="cart-items">
                <?php if (empty($items)): ?>
                    <div class="cart-item">
                        <div class="cart-item__content">
                            <div class="cart-item__details">
                                <h3 class="cart-item__name">Səbətiniz boşdur</h3>
                                <p class="cart-item__specs">Ödənişə davam etmək üçün mağaza səhifəsindən məhsul əlavə edin.</p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <div class="cart-item">
                            <div class="cart-item__img-wrapper">
                                <img alt="<?php echo e($item['name']); ?>" class="cart-item__img" src="<?php echo e($item['image_url'] ?: 'https://via.placeholder.com/300x300?text=No+Image'); ?>" />
                            </div>
                            <div class="cart-item__content">
                                <div class="cart-item__details">
                                    <h3 class="cart-item__name"><?php echo e($item['name']); ?></h3>
                                    <span class="cart-item__badge"><?php echo (int) $item['stock_qty'] > 0 ? 'Stokda var' : 'Stokda yoxdur'; ?></span>
                                </div>
                                <div class="cart-item__actions">
                                    <div class="quantity-picker">
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="cart_item_id" value="<?php echo (int) $item['cart_item_id']; ?>">
                                            <input type="hidden" name="current_quantity" value="<?php echo (int) $item['quantity']; ?>">
                                            <button class="quantity-picker__btn material-symbols-outlined" type="submit" name="action" value="decrease">remove</button>
                                        </form>
                                        <span class="quantity-picker__value"><?php echo (int) $item['quantity']; ?></span>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="cart_item_id" value="<?php echo (int) $item['cart_item_id']; ?>">
                                            <input type="hidden" name="current_quantity" value="<?php echo (int) $item['quantity']; ?>">
                                            <button class="quantity-picker__btn material-symbols-outlined" type="submit" name="action" value="increase">add</button>
                                        </form>
                                    </div>
                                    <div class="cart-item__price-block">
                                        <span class="cart-item__price"><?php echo number_format((float) $item['unit_price'] * (int) $item['quantity'], 2); ?> ₼</span>
                                        <form method="post">
                                            <input type="hidden" name="cart_item_id" value="<?php echo (int) $item['cart_item_id']; ?>">
                                            <button class="cart-item__remove" type="submit" name="action" value="remove">Sil</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <aside class="sidebar">
                <div class="summary">
                    <h2 class="summary__title">Sifariş xülasəsi</h2>
                    <div class="summary__footer">
                        <div class="summary__total-row">
                            <span class="summary__total-label">Ümumi</span>
                            <span class="summary__total-value"><?php echo number_format($totals['total'], 2); ?> ₼</span>
                        </div>
                        <?php if (isUserLoggedIn()): ?>
                            <div class="summary__payment">
                                <label class="summary__payment-label" for="payment_method">Ödəniş üsulu</label>
                                <select id="payment_method" class="summary__payment-select">
                                    <option value="card" <?php echo $selectedPaymentMethod === 'card' || $selectedPaymentMethod === '' ? 'selected' : ''; ?>>Kartla ödəniş</option>
                                    <option value="cash_on_delivery" <?php echo $selectedPaymentMethod === 'cash_on_delivery' ? 'selected' : ''; ?>>Nağd ödəniş</option>
                                </select>
                            </div>
                            <div class="summary__actions">
                                <a class="checkout-btn" id="checkoutLink" href="checkout.php<?php echo $selectedPaymentMethod !== '' ? ('?payment_method=' . urlencode($selectedPaymentMethod)) : '?payment_method=card'; ?>">İndi ödəniş et</a>
                            </div>
                            <script>
                            (function () {
                                var sel = document.getElementById('payment_method');
                                var link = document.getElementById('checkoutLink');
                                if (!sel || !link) return;
                                function update() {
                                    var v = sel.value || 'card';
                                    link.href = 'checkout.php?payment_method=' + encodeURIComponent(v);
                                }
                                sel.addEventListener('change', update);
                                update();
                            })();
                            </script>
                        <?php else: ?>
                            <div class="summary__actions">
                                <a class="checkout-btn" href="user_login.php?redirect=checkout.php">Ödəniş üçün daxil olun</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </aside>
        </div>
    </main>
    <footer class="footer">
        <div class="footer__grid">
            <div>
                <div class="footer__brand-title">MAXHOME</div>
                <p class="footer__brand-text">
                    Seçilmiş rəqəmsal təcrübələr və premium keyfiyyət ilə insan-texnologiya əlaqəsini yeni səviyyəyə qaldırırıq.
                </p>
            </div>
            <div>
                <h4 class="footer__section-title">Dəstək</h4>
                <ul class="footer__list">
                    <li><a class="footer__link" href="support_center.php">Çatdırılma</a></li>
                    <li><a class="footer__link" href="support_center.php">Geri qaytarma</a></li>
                    <li><a class="footer__link" href="support_center.php">Sifarişi izlə</a></li>
                </ul>
            </div>
            <div>
                <h4 class="footer__section-title">Resurslar</h4>
                <ul class="footer__list">
                    <li><a class="footer__link" href="support_center.php">Etibar mərkəzi</a></li>
                    <li><a class="footer__link" href="support_center.php">Məxfilik siyasəti</a></li>
                    <li><a class="footer__link" href="support_center.php">Xəbər bülleteni</a></li>
                </ul>
            </div>
            <div>
                <h4 class="footer__section-title">Əlaqə</h4>
                <div class="footer__socials">
                    <span class="footer__social-icon material-symbols-outlined">public</span>
                    <span class="footer__social-icon material-symbols-outlined">mail</span>
                    <span class="footer__social-icon material-symbols-outlined">chat</span>
                </div>
                <p class="footer__copyright">© 2024 MAXHOME Electronics. Rəqəmsal konsyerj təcrübəsi.</p>
            </div>
        </div>
    </footer>
</body>

</html>