<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$rawBody = file_get_contents('php://input');
$payload = json_decode((string) $rawBody, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$orderNumber = trim((string) ($payload['order_number'] ?? ''));
$contact = trim((string) ($payload['contact'] ?? ''));

if ($orderNumber === '' || $contact === '') {
    echo json_encode([
        'ok' => false,
        'found' => false,
        'message' => 'Order number and contact are required.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = db();
    $sql = "SELECT
                o.order_number,
                o.status AS order_status,
                s.carrier,
                s.shipment_status,
                s.tracking_number,
                s.shipped_at,
                s.delivered_at
            FROM orders o
            LEFT JOIN shipments s ON s.order_id = o.id
            LEFT JOIN users u ON u.id = o.user_id
            WHERE o.order_number = :order_number
              AND (
                  LOWER(TRIM(COALESCE(u.email, ''))) = LOWER(TRIM(:contact_exact))
                  OR REPLACE(TRIM(COALESCE(o.shipping_phone, '')), ' ', '') = REPLACE(TRIM(:contact_phone), ' ', '')
              )
            ORDER BY s.id DESC
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'order_number' => $orderNumber,
        'contact_exact' => $contact,
        'contact_phone' => $contact,
    ]);
    $row = $stmt->fetch();

    if (!$row) {
        echo json_encode([
            'ok' => true,
            'found' => false,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'found' => true,
        'order_number' => (string) $row['order_number'],
        'order_status' => (string) ($row['order_status'] ?? ''),
        'carrier' => (string) ($row['carrier'] ?? ''),
        'shipment_status' => (string) ($row['shipment_status'] ?? 'pending'),
        'tracking_number' => (string) ($row['tracking_number'] ?? ''),
        'shipped_at' => (string) ($row['shipped_at'] ?? ''),
        'delivered_at' => (string) ($row['delivered_at'] ?? ''),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'found' => false,
        'message' => 'Server error.',
    ], JSON_UNESCAPED_UNICODE);
}
