<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin_auth.php';
require_once __DIR__ . '/../../db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'message' => 'Admin sessiyasi bitib. Yeniden login olun.',
        'products' => [],
        'missing' => [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$excludeId = (int) ($_GET['exclude_id'] ?? 0);
$idsRaw = trim((string) ($_GET['ids'] ?? ''));
$q = trim((string) ($_GET['q'] ?? ''));

try {
    $pdo = db();
    $catalogOnlineSql = maxhomeProductsOnlineVisibleSql($pdo);

    if ($idsRaw !== '') {
        $parts = preg_split('/[\s,;]+/', $idsRaw, -1, PREG_SPLIT_NO_EMPTY);
        $ids = [];
        foreach ($parts as $part) {
            if (ctype_digit((string) $part)) {
                $n = (int) $part;
                if ($n > 0 && ($excludeId <= 0 || $n !== $excludeId)) {
                    $ids[] = $n;
                }
            }
        }
        $ids = array_values(array_unique($ids));
        if (count($ids) > 25) {
            $ids = array_slice($ids, 0, 25);
        }
        if ($ids === []) {
            echo json_encode(['ok' => true, 'products' => [], 'missing' => []], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $named = [];
        $paramsIn = [];
        foreach ($ids as $i => $idVal) {
            $key = 'bid' . $i;
            $named[] = ':' . $key;
            $paramsIn[$key] = $idVal;
        }
        $sql = "SELECT p.id, p.name, p.sku, p.base_price, p.status
                FROM products p
                WHERE p.id IN (" . implode(',', $named) . ") AND p.status = 'active'{$catalogOnlineSql}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($paramsIn);
        $rows = $stmt->fetchAll() ?: [];
        $foundIds = array_map(static fn (array $r): int => (int) $r['id'], $rows);
        $missing = array_values(array_diff($ids, $foundIds));

        $orderMap = array_flip($ids);
        usort($rows, static function (array $a, array $b) use ($orderMap): int {
            return ($orderMap[(int) $a['id']] ?? 0) <=> ($orderMap[(int) $b['id']] ?? 0);
        });

        $products = array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'sku' => (string) $row['sku'],
                'base_price' => (float) $row['base_price'],
                'status' => (string) $row['status'],
            ];
        }, $rows);

        echo json_encode(['ok' => true, 'products' => $products, 'missing' => $missing], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $qLen = function_exists('mb_strlen') ? mb_strlen($q, 'UTF-8') : strlen($q);
    if ($qLen < 2) {
        echo json_encode(['ok' => true, 'products' => [], 'missing' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $like = '%' . $q . '%';
    $sql = "SELECT p.id, p.name, p.sku, p.base_price, p.status
            FROM products p
            WHERE p.status = 'active'{$catalogOnlineSql}
              AND (p.name LIKE :q_name OR p.sku LIKE :q_sku)";
    $params = ['q_name' => $like, 'q_sku' => $like];
    if ($excludeId > 0) {
        $sql .= ' AND p.id != :exclude';
        $params['exclude'] = $excludeId;
    }
    $sql .= ' ORDER BY p.name ASC LIMIT 20';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];

    $products = array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'sku' => (string) $row['sku'],
            'base_price' => (float) $row['base_price'],
            'status' => (string) $row['status'],
        ];
    }, $rows);

    echo json_encode(['ok' => true, 'products' => $products, 'missing' => []], JSON_UNESCAPED_UNICODE);
} catch (Throwable) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Server xetasi',
        'products' => [],
        'missing' => [],
    ], JSON_UNESCAPED_UNICODE);
}
