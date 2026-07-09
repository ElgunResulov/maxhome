<?php
declare(strict_types=1);
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/admin_layout.php';

requireAdminAuth();
$pdo = db();
$message = '';
$messageType = 'success';

function orderStatusLabel(string $status): string
{
    return match ($status) {
        'pending' => 'gozlenilir',
        'paid' => 'tesdiq edildi',
        'processing' => 'hazirlanir',
        'shipped' => 'gonderilib',
        'completed' => 'tamamlanib',
        'cancelled' => 'legv edilib',
        'refunded' => 'geri qaytarilib',
        default => $status,
    };
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = (int) ($_POST['order_id'] ?? 0);
    if ($orderId > 0) {
        $stmt = $pdo->prepare("UPDATE orders SET status = 'paid', updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->execute(['id' => $orderId]);
        $message = 'Sifaris tesdiq edildi.';
    } else {
        $message = 'Sifaris tesdiqi ugursuz oldu.';
        $messageType = 'error';
    }
}

$orders = $pdo->query(
    "SELECT id, order_number, shipping_first_name, shipping_last_name, shipping_city, total_amount, status, created_at
     FROM orders
     ORDER BY id DESC
     LIMIT 100"
)->fetchAll() ?: [];
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Sifarisler</title>
    <link rel="stylesheet" href="../assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <?php adminSidebar('orders'); ?>
    <main class="admin-main">
        <header class="admin-top">
            <div>
                <h1 class="admin-title">Sifaris idareetmesi</h1>
                <p class="admin-subtitle">Sifarisleri izle ve statuslari yenile.</p>
            </div>
        </header>

        <?php if ($message !== ''): ?>
            <div class="flash <?php echo $messageType === 'error' ? 'flash-error' : 'flash-success'; ?>">
                <?php echo e($message); ?>
            </div>
        <?php endif; ?>

        <section class="card table-wrap">
            <table>
                <thead>
                <tr>
                    <th>№</th>
                    <th>Nomre</th>
                    <th>Musteri</th>
                    <th>Seher</th>
                    <th>Mebleg</th>
                    <th>Status</th>
                    <th>Tarix</th>
                    <th>Yenile</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="9">Sifaris tapilmadi.</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $idx => $order): ?>
                        <tr>
                            <td><?php echo $idx + 1; ?></td>
                            <td><?php echo e((string) $order['order_number']); ?><br><small class="spec-help">DB ID: <?php echo (int) $order['id']; ?></small></td>
                            <td><?php echo e((string) $order['shipping_first_name'] . ' ' . $order['shipping_last_name']); ?></td>
                            <td><?php echo e((string) $order['shipping_city']); ?></td>
                            <td><?php echo number_format((float) $order['total_amount'], 2); ?> ₼</td>
                            <td><span class="status-badge status-<?php echo e((string) $order['status']); ?>"><?php echo e(orderStatusLabel((string) $order['status'])); ?></span></td>
                            <td><?php echo e((string) $order['created_at']); ?></td>
                            <td>
                                <form method="post">
                                    <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                                    <?php if ((string) $order['status'] === 'paid'): ?>
                                        <button class="btn btn-muted" type="button" disabled>Tesdiq edildi</button>
                                    <?php else: ?>
                                        <button class="btn btn-muted" type="submit">Tesdiq et</button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>
</body>
</html>
