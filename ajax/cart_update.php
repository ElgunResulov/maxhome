<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Yalnız POST icazəlidir.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = trim((string) ($_POST['action'] ?? ''));
$itemId = (int) ($_POST['cart_item_id'] ?? 0);
$currentQuantity = max(0, (int) ($_POST['current_quantity'] ?? 0));

if ($itemId <= 0 || !in_array($action, ['increase', 'decrease', 'remove'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Sorğu etibarsızdır.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = db();

    if ($action === 'increase') {
        updateCartItemQuantity($pdo, $itemId, $currentQuantity + 1);
    } elseif ($action === 'decrease') {
        updateCartItemQuantity($pdo, $itemId, $currentQuantity - 1);
    } else {
        updateCartItemQuantity($pdo, $itemId, 0);
    }

    $items = fetchCartItems($pdo);
    $totals = cartTotals($items);
    $quantityTotal = 0;
    foreach ($items as $item) {
        $quantityTotal += (int) ($item['quantity'] ?? 0);
    }
    $updatedItem = null;

    foreach ($items as $item) {
        if ((int) $item['cart_item_id'] === $itemId) {
            $qty = (int) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $updatedItem = [
                'cart_item_id' => $itemId,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => round($unitPrice * $qty, 2),
                'removed' => false,
            ];
            break;
        }
    }

    if ($updatedItem === null) {
        $updatedItem = [
            'cart_item_id' => $itemId,
            'quantity' => 0,
            'unit_price' => 0.0,
            'line_total' => 0.0,
            'removed' => true,
        ];
    }

    echo json_encode([
        'ok' => true,
        'item' => $updatedItem,
        'totals' => [
            'subtotal' => round((float) $totals['subtotal'], 2),
            'total' => round((float) $totals['total'], 2),
        ],
        'item_count' => count($items),
        'quantity_total' => $quantityTotal,
        'empty' => empty($items),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
