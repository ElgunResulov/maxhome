<?php
declare(strict_types=1);
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/admin_layout.php';

requireAdminAuth();
$pdo = db();

$stats = [
    'products' => (int) $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'orders' => (int) $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'images' => (int) $pdo->query("SELECT COUNT(*) FROM product_images")->fetchColumn(),
    'active_carts' => (int) $pdo->query("SELECT COUNT(*) FROM carts WHERE status = 'active'")->fetchColumn(),
];

$recentOrders = $pdo->query(
    "SELECT id, order_number, status, total_amount, created_at
     FROM orders
     ORDER BY id DESC
     LIMIT 6"
)->fetchAll() ?: [];

$lowStockProducts = $pdo->query(
    "SELECT id, name, stock_qty, status
     FROM products
     ORDER BY stock_qty ASC, id DESC
     LIMIT 6"
)->fetchAll() ?: [];
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="admin-layout">
        <?php adminSidebar('dashboard'); ?>

        <main class="admin-main">
            <header class="admin-top">
                <div>
                    <h1 class="admin-title">Idareetme paneli</h1>
                    <p class="admin-subtitle">Xos gelmisiniz, <?php echo e((string) ($_SESSION['admin_username'] ?? 'admin')); ?></p>
                </div>
                <a class="btn btn-primary" href="admin_products.php">Mehsul elave et</a>
            </header>

            <section class="stats-grid">
                <div class="card"><div class="card-k">Mehsul sayi</div><div class="card-v"><?php echo $stats['products']; ?></div></div>
                <div class="card"><div class="card-k">Sifaris sayi</div><div class="card-v"><?php echo $stats['orders']; ?></div></div>
                <div class="card"><div class="card-k">Sekil sayi</div><div class="card-v"><?php echo $stats['images']; ?></div></div>
                <div class="card"><div class="card-k">Aktiv sepet</div><div class="card-v"><?php echo $stats['active_carts']; ?></div></div>
            </section>

            <section class="card-row">
                <div class="card table-wrap">
                    <h3 class="section-title">Son sifarisler</h3>
                    <table>
                        <thead>
                        <tr>
                            <th>№</th>
                            <th>Nomre</th>
                            <th>Status</th>
                            <th>Mebleg</th>
                            <th>Tarix</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($recentOrders)): ?>
                            <tr><td colspan="5">Sifaris yoxdur.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentOrders as $idx => $order): ?>
                                <tr>
                                    <td><?php echo $idx + 1; ?></td>
                                    <td><?php echo e((string) $order['order_number']); ?><br><small class="spec-help">DB ID: <?php echo (int) $order['id']; ?></small></td>
                                    <td><span class="status-badge status-<?php echo e((string) $order['status']); ?>"><?php echo e((string) $order['status']); ?></span></td>
                                    <td><?php echo number_format((float) $order['total_amount'], 2); ?> ₼</td>
                                    <td><?php echo e((string) $order['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card table-wrap">
                    <h3 class="section-title">Stok kontrolu</h3>
                    <table>
                        <thead>
                        <tr>
                            <th>№</th>
                            <th>Mehsul</th>
                            <th>Stok</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($lowStockProducts)): ?>
                            <tr><td colspan="4">Mehsul yoxdur.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lowStockProducts as $idx => $product): ?>
                                <tr>
                                    <td><?php echo $idx + 1; ?></td>
                                    <td><?php echo e((string) $product['name']); ?><br><small class="spec-help">DB ID: <?php echo (int) $product['id']; ?></small></td>
                                    <td><?php echo (int) $product['stock_qty']; ?></td>
                                    <td><span class="status-badge status-<?php echo e((string) $product['status']); ?>"><?php echo e((string) $product['status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card" style="margin-top:16px;">
                <h3 class="section-title">Qisa kecidler</h3>
                <div class="stack-sm">
                    <a class="btn btn-primary" href="admin_products.php">Mehsullari idare et</a>
                    <a class="btn btn-muted" href="admin_warehouse_cash.php">Ambar ve kassa</a>
                    <a class="btn btn-muted" href="admin_discounts.php">Endirim paneli</a>
                    <a class="btn btn-muted" href="admin_orders.php">Sifaris statusu yenile</a>
                    <a class="btn btn-muted" href="upload_product_image.php">Sekil yukle</a>
                    <a class="btn btn-muted" href="../shop_page.php">Magazaya bax</a>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
