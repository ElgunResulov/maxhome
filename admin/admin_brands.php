<?php
declare(strict_types=1);
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/admin_layout.php';

requireAdminAuth();
$pdo = db();

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    try {
        if ($action === 'create') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $slug = trim((string) ($_POST['slug'] ?? ''));
            if ($name === '' || $slug === '') {
                throw new RuntimeException('Ad ve slug mecburidir.');
            }
            $stmt = $pdo->prepare("INSERT INTO brands (name, slug) VALUES (:name, :slug)");
            $stmt->execute(['name' => $name, 'slug' => $slug]);
            $message = 'Brend ugurla elave edildi.';
        } elseif ($action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $slug = trim((string) ($_POST['slug'] ?? ''));
            if ($id <= 0 || $name === '' || $slug === '') {
                throw new RuntimeException('Yenileme melumatlari sehvdir.');
            }
            $stmt = $pdo->prepare("UPDATE brands SET name = :name, slug = :slug WHERE id = :id");
            $stmt->execute(['id' => $id, 'name' => $name, 'slug' => $slug]);
            $message = 'Brend ugurla yenilendi.';
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Silmek ucun brend secin.');
            }
            $pdo->beginTransaction();
            try {
                $pdo->prepare('UPDATE products SET brand_id = NULL WHERE brand_id = :id')->execute(['id' => $id]);
                $pdo->prepare('DELETE FROM brands WHERE id = :id')->execute(['id' => $id]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
            $message = 'Brend silindi.';
        } elseif ($action === 'delete_all') {
            $pdo->beginTransaction();
            try {
                $pdo->exec('UPDATE products SET brand_id = NULL WHERE brand_id IS NOT NULL');
                $pdo->exec('DELETE FROM brands');
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
            $message = 'Butun brendler silindi (mehsullarin brend baglantisi legv edildi).';
        } else {
            throw new RuntimeException('Bilinmeyen emeliyyat.');
        }
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
$editing = null;
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM brands WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $editId]);
    $editing = $stmt->fetch() ?: null;
}

$brands = $pdo->query("SELECT id, name, slug, created_at FROM brands ORDER BY name ASC")->fetchAll() ?: [];
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Brendler</title>
    <link rel="stylesheet" href="../assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <?php adminSidebar('brands'); ?>
    <main class="admin-main">
        <header class="admin-top">
            <div>
                <h1 class="admin-title">Brend idareetmesi</h1>
                <p class="admin-subtitle">
                    Anasəhifə “Brands” hissəsi buradan idarə olunur.
                    <a href="admin_brand_categories.php">Kateqoriya / Brend əlaqələri</a> üçün ayrıca bölməyə keçin.
                </p>
            </div>
        </header>

        <?php if ($message !== ''): ?>
            <div class="flash <?php echo $messageType === 'error' ? 'flash-error' : 'flash-success'; ?>">
                <?php echo e($message); ?>
            </div>
        <?php endif; ?>

        <section class="card" style="margin-bottom: 14px;">
            <h3 class="section-title"><?php echo $editing ? 'Brendi redakte et' : 'Yeni brend'; ?></h3>
            <form method="post">
                <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
                <?php if ($editing): ?>
                    <input type="hidden" name="id" value="<?php echo (int) $editing['id']; ?>">
                <?php endif; ?>
                <div class="form-grid">
                    <div><label>Ad</label><input name="name" required value="<?php echo $editing ? e((string) $editing['name']) : ''; ?>"></div>
                    <div><label>Slug</label><input name="slug" required value="<?php echo $editing ? e((string) $editing['slug']) : ''; ?>"></div>
                </div>
                <div class="stack-sm" style="margin-top: 12px;">
                    <button class="btn btn-primary" type="submit"><?php echo $editing ? 'Yenile' : 'Elave et'; ?></button>
                    <?php if ($editing): ?>
                        <a class="btn btn-muted" href="admin_brands.php">Legv et</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="card table-wrap">
            <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; margin-bottom:12px;">
                <h3 class="section-title" style="margin:0;">Brendler</h3>
                <?php if (!empty($brands)): ?>
                    <form method="post" class="inline-form" onsubmit="return confirm('Butun brendler silinsin? Mehsullarda brend secimi legv olunacaq (brand_id = NULL). Kateqoriya brend yarpaqlari avtomatik null olacaq. Geri qaytarila bilmez.');">
                        <input type="hidden" name="action" value="delete_all">
                        <button class="btn btn-danger" type="submit">Hamisini sil</button>
                    </form>
                <?php endif; ?>
            </div>
            <table>
                <thead>
                <tr>
                    <th>№</th>
                    <th>Ad</th>
                    <th>Slug</th>
                    <th>Tarix</th>
                    <th>Emeliyyat</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($brands)): ?>
                    <tr><td colspan="5">Brend yoxdur.</td></tr>
                <?php else: ?>
                    <?php foreach ($brands as $idx => $b): ?>
                        <tr>
                            <td><?php echo $idx + 1; ?></td>
                            <td><?php echo e((string) $b['name']); ?><br><small class="spec-help">DB ID: <?php echo (int) $b['id']; ?></small></td>
                            <td><code><?php echo e((string) $b['slug']); ?></code></td>
                            <td><?php echo e((string) $b['created_at']); ?></td>
                            <td>
                                <div class="stack-sm">
                                    <a class="btn btn-muted" href="admin_brands.php?edit=<?php echo (int) $b['id']; ?>">Duzenle</a>
                                    <form class="inline-form" method="post" onsubmit="return confirm('Bu brend silinsin?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int) $b['id']; ?>">
                                        <button class="btn btn-danger" type="submit">Sil</button>
                                    </form>
                                </div>
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

