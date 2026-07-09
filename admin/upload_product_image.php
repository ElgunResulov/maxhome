<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/admin_layout.php';

requireAdminAuth();

$pdo = db();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = (string) ($_POST['action'] ?? 'upload');

        if ($action === 'upload') {
            $productId = (int) ($_POST['product_id'] ?? 0);
            $isPrimary = isset($_POST['is_primary']) && $_POST['is_primary'] === '1';
            $sortOrder = (int) ($_POST['sort_order'] ?? 0);

            if (!isset($_FILES['image'])) {
                throw new RuntimeException('Lutfen bir sekil secin.');
            }
            $fileField = $_FILES['image'];
            $files = [];
            if (is_array($fileField['name'] ?? null)) {
                foreach (($fileField['name'] ?? []) as $idx => $name) {
                    $files[] = [
                        'name' => $name,
                        'type' => $fileField['type'][$idx] ?? '',
                        'tmp_name' => $fileField['tmp_name'][$idx] ?? '',
                        'error' => $fileField['error'][$idx] ?? UPLOAD_ERR_NO_FILE,
                        'size' => $fileField['size'][$idx] ?? 0,
                    ];
                }
            } else {
                $files[] = $fileField;
            }

            $uploadedCount = 0;
            $failedUploads = [];
            foreach ($files as $idx => $oneFile) {
                $imgErr = (int) ($oneFile['error'] ?? UPLOAD_ERR_NO_FILE);
                if ($imgErr === UPLOAD_ERR_NO_FILE || empty($oneFile['name'])) {
                    continue;
                }
                if ($imgErr !== UPLOAD_ERR_OK) {
                    $failedUploads[] = 'fayl #' . ($idx + 1) . ' xetasi';
                    continue;
                }
                $isPrimaryForThis = $isPrimary && $uploadedCount === 0;
                $sortForThis = $sortOrder + $uploadedCount;
                saveProductImageFromUpload($pdo, $productId, $oneFile, $isPrimaryForThis, $sortForThis);
                $uploadedCount++;
            }
            if ($uploadedCount <= 0 && empty($failedUploads)) {
                throw new RuntimeException('Lutfen bir sekil secin.');
            }
            $message = $uploadedCount . ' sekil ugurla yuklendi.';
            if (!empty($failedUploads)) {
                $message .= ' Bezi sekiller yuklenmedi: ' . implode('; ', $failedUploads);
            }
        } elseif ($action === 'set_primary') {
            $imageId = (int) ($_POST['image_id'] ?? 0);
            $productId = (int) ($_POST['product_id'] ?? 0);
            if ($imageId <= 0 || $productId <= 0) {
                throw new RuntimeException('Gecersiz image melumati.');
            }

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = :product_id")
                ->execute(['product_id' => $productId]);
            $pdo->prepare("UPDATE product_images SET is_primary = 1 WHERE id = :id AND product_id = :product_id")
                ->execute(['id' => $imageId, 'product_id' => $productId]);
            $pdo->commit();
            $message = 'Primary sekil yenilendi.';
        } elseif ($action === 'update') {
            $imageId = (int) ($_POST['image_id'] ?? 0);
            $sortOrder = (int) ($_POST['sort_order'] ?? 0);
            $altText = trim((string) ($_POST['alt_text'] ?? ''));
            $isPrimary = isset($_POST['is_primary']) && $_POST['is_primary'] === '1';

            if ($imageId <= 0) {
                throw new RuntimeException('Gecersiz image ID.');
            }

            $rowStmt = $pdo->prepare(
                "SELECT id, product_id FROM product_images WHERE id = :id LIMIT 1"
            );
            $rowStmt->execute(['id' => $imageId]);
            $imgRow = $rowStmt->fetch();
            if (!$imgRow) {
                throw new RuntimeException('Sekil tapilmadi.');
            }

            $productId = (int) $imgRow['product_id'];
            $altForDb = $altText !== '' ? $altText : null;

            $pdo->beginTransaction();
            try {
                if ($isPrimary) {
                    $pdo->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = :product_id")
                        ->execute(['product_id' => $productId]);
                    $pdo->prepare(
                        "UPDATE product_images SET is_primary = 1, sort_order = :sort_order, alt_text = :alt_text
                         WHERE id = :id AND product_id = :product_id"
                    )->execute([
                        'sort_order' => $sortOrder,
                        'alt_text' => $altForDb,
                        'id' => $imageId,
                        'product_id' => $productId,
                    ]);
                } else {
                    $pdo->prepare(
                        "UPDATE product_images SET is_primary = 0, sort_order = :sort_order, alt_text = :alt_text
                         WHERE id = :id AND product_id = :product_id"
                    )->execute([
                        'sort_order' => $sortOrder,
                        'alt_text' => $altForDb,
                        'id' => $imageId,
                        'product_id' => $productId,
                    ]);
                    $cntStmt = $pdo->prepare(
                        "SELECT COUNT(*) FROM product_images WHERE product_id = :pid AND is_primary = 1"
                    );
                    $cntStmt->execute(['pid' => $productId]);
                    if ((int) $cntStmt->fetchColumn() === 0) {
                        $fb = $pdo->prepare(
                            "SELECT id FROM product_images WHERE product_id = :pid ORDER BY sort_order ASC, id ASC LIMIT 1"
                        );
                        $fb->execute(['pid' => $productId]);
                        $fbId = $fb->fetchColumn();
                        if ($fbId) {
                            $pdo->prepare("UPDATE product_images SET is_primary = 1 WHERE id = :id")
                                ->execute(['id' => (int) $fbId]);
                        }
                    }
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }

            if (isset($_FILES['image']) && is_array($_FILES['image'])) {
                $imgErr = (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE);
                if ($imgErr === UPLOAD_ERR_OK && !empty($_FILES['image']['name'])) {
                    replaceProductImageFromUpload($pdo, $imageId, $_FILES['image']);
                    $message = 'Görsel güncellendi (dosya ve bilgiler).';
                } elseif ($imgErr !== UPLOAD_ERR_NO_FILE) {
                    $message = 'Bilgiler kaydedildi; yeni dosya yüklenemedi.';
                } else {
                    $message = 'Görsel bilgileri güncellendi.';
                }
            } else {
                $message = 'Görsel bilgileri güncellendi.';
            }
        } elseif ($action === 'delete') {
            $imageId = (int) ($_POST['image_id'] ?? 0);
            if ($imageId <= 0) {
                throw new RuntimeException('Gecersiz image ID.');
            }

            $stmt = $pdo->prepare("SELECT id, product_id, image_url FROM product_images WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $imageId]);
            $image = $stmt->fetch();
            if (!$image) {
                throw new RuntimeException('Sekil tapilmadi.');
            }

            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM product_images WHERE id = :id")->execute(['id' => $imageId]);

            $primaryStmt = $pdo->prepare("SELECT id FROM product_images WHERE product_id = :product_id AND is_primary = 1 LIMIT 1");
            $primaryStmt->execute(['product_id' => (int) $image['product_id']]);
            $hasPrimary = $primaryStmt->fetchColumn();

            if (!$hasPrimary) {
                $fallbackStmt = $pdo->prepare("SELECT id FROM product_images WHERE product_id = :product_id ORDER BY sort_order ASC, id ASC LIMIT 1");
                $fallbackStmt->execute(['product_id' => (int) $image['product_id']]);
                $fallbackId = $fallbackStmt->fetchColumn();
                if ($fallbackId) {
                    $pdo->prepare("UPDATE product_images SET is_primary = 1 WHERE id = :id")
                        ->execute(['id' => (int) $fallbackId]);
                }
            }
            $pdo->commit();

            $url = (string) $image['image_url'];
            if (str_starts_with($url, 'uploads/')) {
                $fullPath = __DIR__ . '/../' . str_replace('/', DIRECTORY_SEPARATOR, $url);
                if (is_file($fullPath)) {
                    @unlink($fullPath);
                }
            }

            $message = 'Sekil silindi.';
        } else {
            throw new RuntimeException('Bilinmeyen emeliyyat.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

$products = $pdo->query("SELECT id, name FROM products WHERE status = 'active' ORDER BY id DESC")->fetchAll() ?: [];
$images = $pdo->query(
    "SELECT pi.id, pi.product_id, pi.image_url, pi.is_primary, pi.sort_order, pi.alt_text, p.name AS product_name
     FROM product_images pi
     JOIN products p ON p.id = pi.product_id
     ORDER BY pi.product_id DESC, pi.is_primary DESC, pi.sort_order ASC, pi.id ASC"
)->fetchAll() ?: [];

$editId = (int) ($_GET['edit'] ?? 0);
$editingImage = null;
if ($editId > 0) {
    $editStmt = $pdo->prepare(
        "SELECT pi.id, pi.product_id, pi.image_url, pi.is_primary, pi.sort_order, pi.alt_text, p.name AS product_name
         FROM product_images pi
         JOIN products p ON p.id = pi.product_id
         WHERE pi.id = :id
         LIMIT 1"
    );
    $editStmt->execute(['id' => $editId]);
    $editingImage = $editStmt->fetch() ?: null;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Ürün Görselleri</title>
    <link rel="stylesheet" href="../assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <?php adminSidebar('products'); ?>
    <main class="admin-main">
        <header class="admin-top">
            <div>
                <h1 class="admin-title">Ürün görsel yönetimi</h1>
                <p class="admin-subtitle">Görsel yükle, birincil resmi ayarla ve gereksiz görselleri sil.</p>
            </div>
            <div class="stack-sm">
                <a class="btn btn-muted" href="admin_products.php">Ürünlere dön</a>
                <a class="btn btn-primary" href="../shop_page.php">Mağazayı aç</a>
            </div>
        </header>

        <?php if ($message !== ''): ?>
            <div class="flash flash-success"><?php echo e($message); ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="flash flash-error"><?php echo e($error); ?></div>
        <?php endif; ?>

        <?php if ($editingImage): ?>
            <section class="card" style="margin-bottom: 16px;">
                <h3 class="section-title">Görseli redaktə et #<?php echo (int) $editingImage['id']; ?></h3>
                <p class="admin-subtitle" style="margin-bottom: 14px;">
                    #<?php echo (int) $editingImage['product_id']; ?> — <?php echo e((string) $editingImage['product_name']); ?>
                </p>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="image_id" value="<?php echo (int) $editingImage['id']; ?>">
                    <div class="form-grid">
                        <div>
                            <label for="edit_sort_order">Sıralama dəyəri</label>
                            <input id="edit_sort_order" name="sort_order" type="number" value="<?php echo (int) $editingImage['sort_order']; ?>">
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <label for="edit_alt_text">Alt mətn (SEO / əlçatanlıq)</label>
                            <input id="edit_alt_text" name="alt_text" type="text" value="<?php echo e((string) ($editingImage['alt_text'] ?? '')); ?>" placeholder="Məs: Noutbuk ön görünüş">
                        </div>
                        <div>
                            <label style="margin-bottom: 8px;">Birincil görsel</label>
                            <label style="display:flex; align-items:center; gap:8px; font-weight:500;">
                                <input type="checkbox" name="is_primary" value="1" style="width:auto;" <?php echo (int) $editingImage['is_primary'] === 1 ? 'checked' : ''; ?>>
                                Bu görseli birincil et
                            </label>
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <label for="edit_image">Yeni fayl (istəyə bağlı)</label>
                            <div class="file-input" data-file-input>
                                <input class="file-input__native" id="edit_image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp">
                                <button class="btn btn-muted file-input__btn" type="button" data-file-btn>Dosyaları Seç</button>
                                <div class="file-input__label" data-file-label>Dosya seçilmedi</div>
                            </div>
                            <p class="admin-subtitle" style="margin-top:8px;">Boş buraxsanız yalnız sıra, alt mətn və birincil ayarı yenilənir. Yeni fayl köhnəni əvəz edir.</p>
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <p class="admin-subtitle" style="margin:0;">Cari önizləmə:</p>
                            <img class="thumb" src="../<?php echo e((string) $editingImage['image_url']); ?>" alt="" style="max-width:200px; margin-top:8px; border-radius:8px;">
                        </div>
                    </div>
                    <div class="stack-sm" style="margin-top: 12px;">
                        <button class="btn btn-primary" type="submit">Yadda saxla</button>
                        <a class="btn btn-muted" href="upload_product_image.php">Ləğv et</a>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <section class="card" style="margin-bottom: 16px;">
            <h3 class="section-title">Yeni görsel yükle</h3>
            <p class="admin-subtitle" style="margin-bottom: 14px;">Desteklenen formatlar: JPG, PNG, WEBP (maks. 20MB)</p>
            <form method="post" enctype="multipart/form-data">
                <div class="form-grid">
                    <div>
                        <label for="product_id">Ürün</label>
                        <select id="product_id" name="product_id" required>
                            <option value="">Ürün seçin</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo (int) $product['id']; ?>">
                                    #<?php echo (int) $product['id']; ?> - <?php echo e($product['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="image">Görsel dosyası</label>
                        <div class="file-input" data-file-input>
                            <input class="file-input__native" id="image" name="image[]" type="file" accept=".jpg,.jpeg,.png,.webp" required multiple>
                            <button class="btn btn-muted file-input__btn" type="button" data-file-btn>Dosyaları Seç</button>
                            <div class="file-input__label" data-file-label>Dosya seçilmedi</div>
                        </div>
                    </div>

                    <div>
                        <label for="sort_order">Sıralama değeri</label>
                        <input id="sort_order" name="sort_order" type="number" value="0">
                    </div>

                    <div>
                        <label style="margin-bottom: 8px;">Ayar</label>
                        <label style="display:flex; align-items:center; gap:8px; font-weight:500;">
                            <input type="checkbox" name="is_primary" value="1" style="width:auto;">
                            Birincil görsel olarak ayarla
                        </label>
                    </div>
                </div>

                <button class="btn btn-primary" type="submit" name="action" value="upload" style="margin-top: 12px;">Görsel yükle</button>
            </form>
        </section>

        <section class="card table-wrap">
            <h3 class="section-title">Yüklenen görseller</h3>
            <?php if (empty($images)): ?>
                <p class="admin-subtitle">Henüz görsel yok.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>№</th>
                            <th>Ürün</th>
                            <th>Önizleme</th>
                            <th>Dosya yolu</th>
                            <th>Birincil</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($images as $idx => $img): ?>
                            <tr>
                                <td><?php echo $idx + 1; ?></td>
                                <td>#<?php echo (int) $img['product_id']; ?> - <?php echo e($img['product_name']); ?><br><small class="spec-help">Şəkil DB ID: <?php echo (int) $img['id']; ?></small></td>
                                <td><img class="thumb" src="../<?php echo e($img['image_url']); ?>" alt="product image"></td>
                                <td><code><?php echo e($img['image_url']); ?></code></td>
                                <td>
                                    <?php if ((int) $img['is_primary'] === 1): ?>
                                        <span class="status-badge status-active">Evet</span>
                                    <?php else: ?>
                                        <span class="status-badge status-archived">Hayır</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="stack-sm">
                                        <a class="btn btn-primary" href="upload_product_image.php?edit=<?php echo (int) $img['id']; ?>">Redakte et</a>
                                        <?php if ((int) $img['is_primary'] !== 1): ?>
                                            <form method="post">
                                                <input type="hidden" name="image_id" value="<?php echo (int) $img['id']; ?>">
                                                <input type="hidden" name="product_id" value="<?php echo (int) $img['product_id']; ?>">
                                                <button class="btn btn-muted" type="submit" name="action" value="set_primary">Birincil yap</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" onsubmit="return confirm('Bu görsel silinsin mi?');">
                                            <input type="hidden" name="image_id" value="<?php echo (int) $img['id']; ?>">
                                            <button class="btn btn-danger" type="submit" name="action" value="delete">Sil</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>
</div>
</main>
</div>
<script>
(() => {
    document.querySelectorAll('[data-file-input]').forEach((wrap) => {
        if (!(wrap instanceof HTMLElement)) return;
        const input = wrap.querySelector('input[type="file"]');
        const btn = wrap.querySelector('[data-file-btn]');
        const label = wrap.querySelector('[data-file-label]');
        if (!(input instanceof HTMLInputElement) || !(btn instanceof HTMLElement) || !(label instanceof HTMLElement)) return;

        const sync = () => {
            const files = input.files ? Array.from(input.files) : [];
            if (files.length === 0) {
                label.textContent = 'Dosya seçilmedi';
                return;
            }
            label.textContent = files.length === 1 ? files[0].name : `${files.length} dosya seçildi`;
        };

        btn.addEventListener('click', () => input.click());
        input.addEventListener('change', sync);
        sync();
    });
})();
</script>
</body>
</html>
