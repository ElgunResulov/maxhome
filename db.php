<?php declare(strict_types=1);
require_once __DIR__ . '/includes/i18n.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

maxhome_start_i18n_buffer();

function productBundleItemsTableExists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    $stmt = $pdo->query(
        "SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_bundle_items' LIMIT 1"
    );
    $exists = (bool) $stmt->fetchColumn();

    return $exists;
}

function maxhomeProductsOnlineVisibleColumnExists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `products` LIKE 'online_visible'");
        $exists = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $exists = false;
    }

    return $exists;
}

/**
 * Vitrin sorğularına əlavə: anbar panelindən əlavə olunan məhsullar (online_visible = 0) saytda göstərilmir.
 */
function maxhomeProductsOnlineVisibleSql(PDO $pdo, string $tableAlias = 'p'): string
{
    return maxhomeProductsOnlineVisibleColumnExists($pdo) ? " AND {$tableAlias}.online_visible = 1" : '';
}

/**
 * Admin məhsullar panelində yalnız vitrin (online_visible = 1) məhsulları idarə olunur.
 */
function maxhomeAssertCatalogProduct(PDO $pdo, int $productId): void
{
    if ($productId <= 0) {
        throw new RuntimeException('Mehsul tapilmadi.');
    }
    if (!maxhomeProductsOnlineVisibleColumnExists($pdo)) {
        return;
    }
    $stmt = $pdo->prepare('SELECT online_visible FROM products WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $productId]);
    $row = $stmt->fetch();
    if (!$row || (int) ($row['online_visible'] ?? 1) !== 1) {
        throw new RuntimeException('Bu mehsul anbar panelinde idare olunur; mehsullar sehifesinde gorunmur.');
    }
}

function settingsTableExists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    $stmt = $pdo->query(
        "SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' LIMIT 1"
    );
    $exists = (bool) $stmt->fetchColumn();

    return $exists;
}

function maxhomeCategoryExtraSpecTemplatesTableExists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    $stmt = $pdo->query(
        "SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'category_extra_spec_templates' LIMIT 1"
    );
    $exists = (bool) $stmt->fetchColumn();

    return $exists;
}

function maxhomeEnsureCategoryExtraSpecTemplatesTable(PDO $pdo): void
{
    if (maxhomeCategoryExtraSpecTemplatesTableExists($pdo)) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS category_extra_spec_templates (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            category_id BIGINT UNSIGNED NOT NULL,
            label VARCHAR(120) NOT NULL,
            sort_order INT NOT NULL DEFAULT 9000,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_category_extra_spec_label (category_id, label),
            CONSTRAINT fk_category_extra_spec_template_category
                FOREIGN KEY (category_id) REFERENCES categories(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

/**
 * @return list<int>
 */
function maxhomeCategoryAncestorIds(PDO $pdo, int $categoryId): array
{
    $ids = [];
    $seen = [];
    $current = $categoryId;
    $parentStmt = $pdo->prepare(
        "SELECT parent_id
         FROM categories
         WHERE id = ?
         LIMIT 1"
    );
    while ($current > 0 && !isset($seen[$current])) {
        $ids[] = $current;
        $seen[$current] = true;
        $parentStmt->execute([$current]);
        $current = (int) ($parentStmt->fetchColumn() ?: 0);
    }

    return $ids;
}

/**
 * @return list<string>
 */
function maxhomeFetchCategoryExtraSpecTemplateLabels(PDO $pdo, int $categoryId): array
{
    if ($categoryId <= 0) {
        return [];
    }

    $categoryIds = maxhomeCategoryAncestorIds($pdo, $categoryId);
    if ($categoryIds === []) {
        return [];
    }

    $labels = [];
    $seen = [];

    if (maxhomeCategoryExtraSpecTemplatesTableExists($pdo)) {
        $stmt = $pdo->prepare(
            "SELECT label
             FROM category_extra_spec_templates
             WHERE category_id = ?
             ORDER BY sort_order ASC, label ASC"
        );
        foreach ($categoryIds as $ancestorId) {
            $stmt->execute([$ancestorId]);
            foreach ($stmt->fetchAll() ?: [] as $row) {
                $label = trim((string) ($row['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $dedupeKey = mb_strtolower($label);
                if (isset($seen[$dedupeKey])) {
                    continue;
                }
                $seen[$dedupeKey] = true;
                $labels[] = $label;
            }
        }
    }

    if ($labels !== []) {
        return $labels;
    }

    $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
    $fallbackStmt = $pdo->prepare(
        "SELECT ps.spec_key AS label, MIN(ps.sort_order) AS sort_order
         FROM product_specs ps
         INNER JOIN products p ON p.id = ps.product_id
         WHERE p.category_id IN ({$placeholders})
           AND ps.sort_order >= 9000
         GROUP BY ps.spec_key
         ORDER BY sort_order ASC, label ASC"
    );
    $fallbackStmt->execute($categoryIds);
    foreach ($fallbackStmt->fetchAll() ?: [] as $row) {
        $label = trim((string) ($row['label'] ?? ''));
        if ($label === '') {
            continue;
        }
        $dedupeKey = mb_strtolower($label);
        if (isset($seen[$dedupeKey])) {
            continue;
        }
        $seen[$dedupeKey] = true;
        $labels[] = $label;
    }

    return $labels;
}

/**
 * @param list<array{spec_key:string,spec_value:string,sort_order:int}> $customSpecs
 */
function maxhomeSyncCategoryExtraSpecTemplates(PDO $pdo, int $categoryId, array $customSpecs): void
{
    if ($categoryId <= 0 || $customSpecs === []) {
        return;
    }

    maxhomeEnsureCategoryExtraSpecTemplatesTable($pdo);

    $stmt = $pdo->prepare(
        "INSERT INTO category_extra_spec_templates (category_id, label, sort_order)
         VALUES (:category_id, :label, :sort_order)
         ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order)"
    );
    foreach ($customSpecs as $spec) {
        $label = trim((string) ($spec['spec_key'] ?? ''));
        if ($label === '') {
            continue;
        }
        $stmt->execute([
            'category_id' => $categoryId,
            'label' => $label,
            'sort_order' => (int) ($spec['sort_order'] ?? 9000),
        ]);
    }
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = '127.0.0.1';
    $port = 3306;
    $dbname = 'maxhome_db';
    $username = 'root';
    $password = '';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function ensureCart(PDO $pdo): int
{
    if (isset($_SESSION['cart_id'])) {
        $stmt = $pdo->prepare("SELECT id FROM carts WHERE id = :id AND status = 'active' LIMIT 1");
        $stmt->execute(['id' => (int) $_SESSION['cart_id']]);
        $existing = $stmt->fetchColumn();

        if ($existing) {
            return (int) $existing;
        }
    }

    $token = session_id();
    $stmt = $pdo->prepare("SELECT id FROM carts WHERE session_token = :token AND status = 'active' LIMIT 1");
    $stmt->execute(['token' => $token]);
    $cartId = $stmt->fetchColumn();

    if (!$cartId) {
        $upsert = $pdo->prepare(
            "INSERT INTO carts (session_token, status)
             VALUES (:token, 'active')
             ON DUPLICATE KEY UPDATE
                id = LAST_INSERT_ID(id),
                status = 'active',
                updated_at = CURRENT_TIMESTAMP"
        );
        $upsert->execute(['token' => $token]);
        $cartId = (int) $pdo->lastInsertId();
    }

    $_SESSION['cart_id'] = (int) $cartId;
    return (int) $cartId;
}

function addToCart(PDO $pdo, int $productId, int $quantity = 1, bool $allowOfflineCatalogProduct = false): void
{
    $quantity = max(1, $quantity);
    $cartId = ensureCart($pdo);

    $cols = 'id, base_price, stock_qty, status';
    if (maxhomeProductsOnlineVisibleColumnExists($pdo)) {
        $cols .= ', online_visible';
    }
    $productStmt = $pdo->prepare("SELECT {$cols} FROM products WHERE id = :id LIMIT 1");
    $productStmt->execute(['id' => $productId]);
    $product = $productStmt->fetch();

    if (!$product || $product['status'] !== 'active') {
        throw new RuntimeException('Urun bulunamadi veya aktif degil.');
    }

    if (maxhomeProductsOnlineVisibleColumnExists($pdo) && !$allowOfflineCatalogProduct) {
        if ((int) ($product['online_visible'] ?? 1) !== 1) {
            throw new RuntimeException('Urun bulunamadi veya aktif degil.');
        }
    }

    if ((int) $product['stock_qty'] <= 0) {
        throw new RuntimeException('Stokta urun kalmadi.');
    }

    $itemStmt = $pdo->prepare("SELECT id, quantity FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id AND variant_id IS NULL LIMIT 1");
    $itemStmt->execute([
        'cart_id' => $cartId,
        'product_id' => $productId,
    ]);
    $existingItem = $itemStmt->fetch();

    if ($existingItem) {
        $newQty = min((int) $product['stock_qty'], (int) $existingItem['quantity'] + $quantity);
        $update = $pdo->prepare("UPDATE cart_items SET quantity = :qty, unit_price = :price, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $update->execute([
            'qty' => $newQty,
            'price' => $product['base_price'],
            'id' => $existingItem['id'],
        ]);
    } else {
        $insert = $pdo->prepare(
            "INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, unit_price)
             VALUES (:cart_id, :product_id, NULL, :qty, :price)"
        );
        $insert->execute([
            'cart_id' => $cartId,
            'product_id' => $productId,
            'qty' => min((int) $product['stock_qty'], $quantity),
            'price' => $product['base_price'],
        ]);
    }
}

function fetchCartItems(PDO $pdo): array
{
    $cartId = ensureCart($pdo);
    $stmt = $pdo->prepare(
        "SELECT
            ci.id AS cart_item_id,
            ci.product_id,
            ci.quantity,
            ci.unit_price,
            p.name,
            p.slug,
            p.stock_qty,
            pi.image_url
         FROM cart_items ci
         JOIN products p ON p.id = ci.product_id
         LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
         WHERE ci.cart_id = :cart_id
         ORDER BY ci.id DESC"
    );
    $stmt->execute(['cart_id' => $cartId]);
    return $stmt->fetchAll() ?: [];
}

function updateCartItemQuantity(PDO $pdo, int $cartItemId, int $quantity): void
{
    $quantity = max(0, $quantity);
    $cartId = ensureCart($pdo);

    if ($quantity === 0) {
        $delete = $pdo->prepare("DELETE FROM cart_items WHERE id = :id AND cart_id = :cart_id");
        $delete->execute(['id' => $cartItemId, 'cart_id' => $cartId]);
        return;
    }

    $stmt = $pdo->prepare(
        "SELECT ci.id, p.stock_qty
         FROM cart_items ci
         JOIN products p ON p.id = ci.product_id
         WHERE ci.id = :id AND ci.cart_id = :cart_id LIMIT 1"
    );
    $stmt->execute(['id' => $cartItemId, 'cart_id' => $cartId]);
    $row = $stmt->fetch();

    if (!$row) {
        return;
    }

    $quantity = min((int) $row['stock_qty'], $quantity);
    $update = $pdo->prepare("UPDATE cart_items SET quantity = :qty, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
    $update->execute(['qty' => $quantity, 'id' => $cartItemId]);
}

function cartTotals(array $items): array
{
    $subtotal = 0.0;
    foreach ($items as $item) {
        $subtotal += (float) $item['unit_price'] * (int) $item['quantity'];
    }

    $shipping = 0.0;
    $tax = 0.0;
    $total = $subtotal;

    return [
        'subtotal' => $subtotal,
        'shipping' => $shipping,
        'tax' => $tax,
        'total' => $total,
    ];
}

function createOrderFromActiveCart(PDO $pdo, array $payload, ?int $userId = null): string
{
    $cartId = ensureCart($pdo);
    $items = fetchCartItems($pdo);
    if (empty($items)) {
        throw new RuntimeException('Sepet bos.');
    }

    $totals = cartTotals($items);
    $orderNumber = 'MAX-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $paymentMethod = trim((string) ($payload['payment_method'] ?? 'card'));
    if ($paymentMethod === 'installment' || $paymentMethod === 'office_payment') {
        $paymentMethod = $paymentMethod === 'office_payment' ? 'cash_on_delivery' : 'card';
    }

    $inStoreSale = !empty($payload['in_store_sale']);
    $isCashOnDelivery = $paymentMethod === 'cash_on_delivery';

    $orderStatus = $inStoreSale ? 'completed' : 'pending';
    $orderPaymentStatus = ($inStoreSale || !$isCashOnDelivery) ? 'paid' : 'unpaid';
    $paymentRowStatus = ($inStoreSale || !$isCashOnDelivery) ? 'captured' : 'pending';

    $pdo->beginTransaction();
    try {
        $orderStmt = $pdo->prepare(
            "INSERT INTO orders (
                user_id, cart_id, order_number, status, payment_status,
                shipping_first_name, shipping_last_name, shipping_phone,
                shipping_city, shipping_zip_code, shipping_address_line1, shipping_address_line2,
                subtotal, shipping_amount, tax_amount, discount_amount, total_amount
            ) VALUES (
                :user_id, :cart_id, :order_number, :status, :payment_status,
                :first_name, :last_name, :phone,
                :city, :zip_code, :address1, :address2,
                :subtotal, :shipping, :tax, 0.00, :total
            )"
        );
        $orderStmt->execute([
            'user_id' => $userId,
            'cart_id' => $cartId,
            'order_number' => $orderNumber,
            'status' => $orderStatus,
            'payment_status' => $orderPaymentStatus,
            'first_name' => $payload['first_name'],
            'last_name' => $payload['last_name'],
            'phone' => $payload['phone'],
            'city' => $payload['city'],
            'zip_code' => $payload['zip_code'],
            'address1' => $payload['address_line1'],
            'address2' => $payload['address_line2'],
            'subtotal' => $totals['subtotal'],
            'shipping' => $totals['shipping'],
            'tax' => $totals['tax'],
            'total' => $totals['total'],
        ]);

        $orderId = (int) $pdo->lastInsertId();

        $orderItemStmt = $pdo->prepare(
            "INSERT INTO order_items (
                order_id, product_id, variant_id, product_name, product_sku, unit_price, quantity, line_total
            ) VALUES (
                :order_id, :product_id, NULL, :name, :sku, :unit_price, :quantity, :line_total
            )"
        );

        $stockStmt = $pdo->prepare("UPDATE products SET stock_qty = GREATEST(stock_qty - :qty, 0) WHERE id = :product_id");

        foreach ($items as $item) {
            $lineTotal = (float) $item['unit_price'] * (int) $item['quantity'];
            $orderItemStmt->execute([
                'order_id' => $orderId,
                'product_id' => $item['product_id'],
                'name' => $item['name'],
                'sku' => 'SKU-' . $item['product_id'],
                'unit_price' => $item['unit_price'],
                'quantity' => $item['quantity'],
                'line_total' => $lineTotal,
            ]);

            $stockStmt->execute([
                'qty' => (int) $item['quantity'],
                'product_id' => (int) $item['product_id'],
            ]);
        }

        $paymentStmt = $pdo->prepare(
            "INSERT INTO payments (order_id, method, amount, status)
             VALUES (:order_id, :method, :amount, :status)"
        );
        $paymentStmt->execute([
            'order_id' => $orderId,
            'method' => $paymentMethod,
            'amount' => $totals['total'],
            'status' => $paymentRowStatus,
        ]);

        if ($paymentRowStatus === 'captured') {
            $pdo->prepare("UPDATE payments SET paid_at = NOW() WHERE order_id = :order_id")
                ->execute(['order_id' => $orderId]);
        }
        $pdo->prepare("UPDATE carts SET status = 'converted', updated_at = CURRENT_TIMESTAMP WHERE id = :id")
            ->execute(['id' => $cartId]);
        $pdo->prepare("DELETE FROM cart_items WHERE cart_id = :cart_id")
            ->execute(['cart_id' => $cartId]);

        unset($_SESSION['cart_id']);
        $pdo->commit();
        return $orderNumber;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function saveProductImageFromUpload(PDO $pdo, int $productId, array $file, bool $isPrimary = false, int $sortOrder = 0): string
{
    if ($productId <= 0) {
        throw new RuntimeException('Gecersiz urun ID.');
    }

    $productStmt = $pdo->prepare("SELECT id FROM products WHERE id = :id LIMIT 1");
    $productStmt->execute(['id' => $productId]);
    if (!$productStmt->fetch()) {
        throw new RuntimeException('Urun tapilmadi.');
    }

    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Yukleme verisi yanlisdir.');
    }

    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Fayl yuklenmesi ugursuz oldu.');
    }

    $maxBytes = 20 * 1024 * 1024;
    if ((int) $file['size'] > $maxBytes) {
        throw new RuntimeException('Fayl olcusu 20MB-den boyuk ola bilmez.');
    }

    $tmpPath = (string) $file['tmp_name'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = (string) $finfo->file($tmpPath);

    $allowedMimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowedMimeToExt[$mimeType])) {
        throw new RuntimeException('Yalniz JPG, PNG ve WEBP sekilleri qebul edilir.');
    }

    $extension = $allowedMimeToExt[$mimeType];
    $targetDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Uploads qovlugu yaradilamadi.');
    }

    $filename = 'product_' . $productId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $destination = $targetDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmpPath, $destination)) {
        throw new RuntimeException('Sekil uploads qovluguna kopyalana bilmedi.');
    }

    $relativePath = 'uploads/products/' . $filename;

    $pdo->beginTransaction();
    try {
        if ($isPrimary) {
            $resetPrimary = $pdo->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = :product_id");
            $resetPrimary->execute(['product_id' => $productId]);
        }

        $insertImage = $pdo->prepare(
            "INSERT INTO product_images (product_id, image_url, alt_text, is_primary, sort_order)
             VALUES (:product_id, :image_url, :alt_text, :is_primary, :sort_order)"
        );
        $insertImage->execute([
            'product_id' => $productId,
            'image_url' => $relativePath,
            'alt_text' => 'Product image',
            'is_primary' => $isPrimary ? 1 : 0,
            'sort_order' => $sortOrder,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        if (is_file($destination)) {
            unlink($destination);
        }
        throw $e;
    }

    return $relativePath;
}

/**
 * Mövcud product_images sətirinin faylını yeni yükləmə ilə əvəz edir (URL yenilənir, köhnə uploads faylı silinir).
 */
function replaceProductImageFromUpload(PDO $pdo, int $imageId, array $file): string
{
    if ($imageId <= 0) {
        throw new RuntimeException('Gecersiz sekil ID.');
    }

    $rowStmt = $pdo->prepare("SELECT id, product_id, image_url FROM product_images WHERE id = :id LIMIT 1");
    $rowStmt->execute(['id' => $imageId]);
    $row = $rowStmt->fetch();
    if (!$row) {
        throw new RuntimeException('Sekil tapilmadi.');
    }

    $productId = (int) $row['product_id'];
    $oldUrl = (string) $row['image_url'];

    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Yukleme verisi yanlisdir.');
    }

    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Fayl yuklenmesi ugursuz oldu.');
    }

    $maxBytes = 20 * 1024 * 1024;
    if ((int) $file['size'] > $maxBytes) {
        throw new RuntimeException('Fayl olcusu 20MB-den boyuk ola bilmez.');
    }

    $tmpPath = (string) $file['tmp_name'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = (string) $finfo->file($tmpPath);

    $allowedMimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowedMimeToExt[$mimeType])) {
        throw new RuntimeException('Yalniz JPG, PNG ve WEBP sekilleri qebul edilir.');
    }

    $extension = $allowedMimeToExt[$mimeType];
    $targetDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Uploads qovlugu yaradilamadi.');
    }

    $filename = 'product_' . $productId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $destination = $targetDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmpPath, $destination)) {
        throw new RuntimeException('Sekil uploads qovluguna kopyalana bilmedi.');
    }

    $relativePath = 'uploads/products/' . $filename;

    $pdo->beginTransaction();
    try {
        $upd = $pdo->prepare("UPDATE product_images SET image_url = :image_url WHERE id = :id");
        $upd->execute(['image_url' => $relativePath, 'id' => $imageId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        if (is_file($destination)) {
            unlink($destination);
        }
        throw $e;
    }

    if (str_starts_with($oldUrl, 'uploads/')) {
        $fullPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $oldUrl);
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    return $relativePath;
}

function productReviewsTableExists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    $stmt = $pdo->query(
        "SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_reviews' LIMIT 1"
    );
    $exists = (bool) $stmt->fetchColumn();

    return $exists;
}

/**
 * @return array{rating_avg: float, rating_count: int}
 */
function maxhomeGetProductRatingStats(PDO $pdo, int $productId, float $fallbackAvg = 0.0, int $fallbackCount = 0): array
{
    if ($productId > 0 && productReviewsTableExists($pdo)) {
        $stmt = $pdo->prepare(
            "SELECT ROUND(AVG(rating), 1) AS rating_avg, COUNT(*) AS rating_count
             FROM product_reviews
             WHERE product_id = :product_id AND is_visible = 1"
        );
        $stmt->execute(['product_id' => $productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && (int) ($row['rating_count'] ?? 0) > 0) {
            return [
                'rating_avg' => (float) ($row['rating_avg'] ?? 0),
                'rating_count' => (int) ($row['rating_count'] ?? 0),
            ];
        }
    }

    return [
        'rating_avg' => $fallbackAvg,
        'rating_count' => $fallbackCount,
    ];
}

/**
 * Endirimli qiymət və siyahı qiyməti (compare_at_price > base_price).
 *
 * @return array{base_price: float, list_price: ?float}
 */
function maxhomeProductPriceDisplay(array $product): array
{
    $basePrice = (float) ($product['base_price'] ?? 0);
    $comparePrice = (float) ($product['compare_at_price'] ?? 0);
    $listPrice = $comparePrice > $basePrice ? $comparePrice : null;

    return [
        'base_price' => $basePrice,
        'list_price' => $listPrice,
    ];
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * @return list<string>
 */
function maxhomeInternalProductSpecKeys(): array
{
    return ['source_url', 'currency', 'source_id', 'gtin'];
}

function maxhomeIsInternalProductSpecKey(string $specKey): bool
{
    return in_array(strtolower(trim($specKey)), maxhomeInternalProductSpecKeys(), true);
}

/**
 * @return list<string>
 */
function maxhomeAllowedProductHtmlTags(): array
{
    return ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'span', 'div'];
}

function maxhomeSanitizeProductHtml(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    if (str_contains($html, '&lt;') && !str_contains($html, '<')) {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    $allowedTags = maxhomeAllowedProductHtmlTags();
    $allowed = '<' . implode('><', $allowedTags) . '>';
    $html = strip_tags($html, $allowed);
    if ($html === '') {
        return '';
    }

    if (!class_exists('DOMDocument')) {
        return $html;
    }

    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML(
        '<?xml encoding="utf-8" ?><div id="maxhome-root">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $root = $document->getElementById('maxhome-root');
    if (!$root) {
        return $html;
    }

    $allowedTagMap = array_fill_keys($allowedTags, true);
    $nodes = $document->getElementsByTagName('*');
    for ($i = $nodes->length - 1; $i >= 0; $i--) {
        $node = $nodes->item($i);
        if (!$node instanceof DOMElement) {
            continue;
        }

        $tag = strtolower($node->tagName);
        if ($tag === 'div' && $node->getAttribute('id') === 'maxhome-root') {
            while ($node->attributes->length > 0) {
                $node->removeAttribute($node->attributes->item(0)->nodeName);
            }
            continue;
        }

        if (!isset($allowedTagMap[$tag])) {
            $parent = $node->parentNode;
            if ($parent) {
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
            }
            continue;
        }

        while ($node->attributes->length > 0) {
            $node->removeAttribute($node->attributes->item(0)->nodeName);
        }
    }

    $output = '';
    foreach ($root->childNodes as $child) {
        $output .= $document->saveHTML($child);
    }

    return trim($output);
}

function maxhomeProductPlainText(string $html, int $maxLength = 0): string
{
    $plain = trim(preg_replace('/\s+/u', ' ', strip_tags(maxhomeSanitizeProductHtml($html))) ?? '');
    if ($maxLength > 0 && mb_strlen($plain) > $maxLength) {
        $plain = mb_substr($plain, 0, $maxLength) . '...';
    }

    return $plain;
}

function maxhomeRenderProductHtml(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    $sanitized = maxhomeSanitizeProductHtml($html);
    if ($sanitized !== '') {
        return $sanitized;
    }

    $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return nl2br(htmlspecialchars($decoded, ENT_QUOTES, 'UTF-8'));
}

/**
 * Vitrin şəkilləri üçün URL: shop_page.php ilə eyni qaydada işləsin.
 * DB-də /uploads/... və ya uploads/... ola bilər.
 */
function maxhome_public_asset_url(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    if (str_starts_with($path, '/')) {
        return ltrim($path, '/');
    }

    return $path;
}

function getSetting(PDO $pdo, string $key, ?string $default = null): ?string
{
    if (!settingsTableExists($pdo)) {
        return $default;
    }
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = :key LIMIT 1");
    $stmt->execute(['key' => $key]);
    $value = $stmt->fetchColumn();

    if ($value === false || $value === null) {
        return $default;
    }

    return (string) $value;
}

function setSetting(PDO $pdo, string $key, string $value): void
{
    if (!settingsTableExists($pdo)) {
        throw new RuntimeException("settings cedveli tapilmadi. Migration-i icra edin.");
    }
    $stmt = $pdo->prepare(
        "INSERT INTO settings (`key`, value, updated_at)
         VALUES (:key, :value, CURRENT_TIMESTAMP)
         ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = CURRENT_TIMESTAMP"
    );
    $stmt->execute([
        'key' => $key,
        'value' => $value,
    ]);
}
