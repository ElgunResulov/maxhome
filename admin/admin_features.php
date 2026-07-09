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
            $icon = trim((string) ($_POST['icon'] ?? ''));
            $title = trim((string) ($_POST['title'] ?? ''));
            $body = trim((string) ($_POST['body'] ?? ''));
            $sortOrder = (int) ($_POST['sort_order'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($icon === '' || $title === '' || $body === '') {
                throw new RuntimeException('Icon, title ve body mecburidir.');
            }

            $dupStmt = $pdo->prepare(
                "SELECT id
                 FROM site_features
                 WHERE LOWER(TRIM(title)) = LOWER(TRIM(:title))
                 LIMIT 1"
            );
            $dupStmt->execute(['title' => $title]);
            if ($dupStmt->fetch()) {
                throw new RuntimeException('Bu title artiq movcuddur. Tekrar kart elave etmek olmaz.');
            }

            $stmt = $pdo->prepare(
                "INSERT INTO site_features (icon, title, body, sort_order, is_active)
                 VALUES (:icon, :title, :body, :sort_order, :is_active)"
            );
            $stmt->execute([
                'icon' => $icon,
                'title' => $title,
                'body' => $body,
                'sort_order' => $sortOrder,
                'is_active' => $isActive,
            ]);
            $message = 'Feature ugurla elave edildi.';
        } elseif ($action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $icon = trim((string) ($_POST['icon'] ?? ''));
            $title = trim((string) ($_POST['title'] ?? ''));
            $body = trim((string) ($_POST['body'] ?? ''));
            $sortOrder = (int) ($_POST['sort_order'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($id <= 0 || $icon === '' || $title === '' || $body === '') {
                throw new RuntimeException('Yenileme melumatlari sehvdir.');
            }

            $dupStmt = $pdo->prepare(
                "SELECT id
                 FROM site_features
                 WHERE LOWER(TRIM(title)) = LOWER(TRIM(:title))
                   AND id <> :id
                 LIMIT 1"
            );
            $dupStmt->execute([
                'title' => $title,
                'id' => $id,
            ]);
            if ($dupStmt->fetch()) {
                throw new RuntimeException('Bu title artiq movcuddur. Tekrar title istifade etmek olmaz.');
            }

            $stmt = $pdo->prepare(
                "UPDATE site_features
                 SET icon = :icon, title = :title, body = :body, sort_order = :sort_order, is_active = :is_active
                 WHERE id = :id"
            );
            $stmt->execute([
                'id' => $id,
                'icon' => $icon,
                'title' => $title,
                'body' => $body,
                'sort_order' => $sortOrder,
                'is_active' => $isActive,
            ]);
            $message = 'Feature ugurla yenilendi.';
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Silmek ucun feature secin.');
            }
            $pdo->prepare("DELETE FROM site_features WHERE id = :id")->execute(['id' => $id]);
            $message = 'Feature silindi.';
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
    $stmt = $pdo->prepare("SELECT * FROM site_features WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $editId]);
    $editing = $stmt->fetch() ?: null;
}

$features = $pdo->query(
    "SELECT id, icon, title, body, sort_order, is_active
     FROM site_features
     ORDER BY sort_order ASC, id ASC"
)->fetchAll() ?: [];
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Why Choose Us</title>
    <link rel="stylesheet" href="../assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <?php adminSidebar('features'); ?>
    <main class="admin-main">
        <header class="admin-top">
            <div>
                <h1 class="admin-title">Why Choose Us</h1>
                <p class="admin-subtitle">`index.php` “Why Choose Us” kartlarını buradan dəyişirsən.</p>
            </div>
        </header>

        <?php if ($message !== ''): ?>
            <div class="flash <?php echo $messageType === 'error' ? 'flash-error' : 'flash-success'; ?>">
                <?php echo e($message); ?>
            </div>
        <?php endif; ?>

        <section class="card" style="margin-bottom: 14px;">
            <h3 class="section-title"><?php echo $editing ? 'Kart redakte et' : 'Yeni kart'; ?></h3>
            <form method="post">
                <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
                <?php if ($editing): ?>
                    <input type="hidden" name="id" value="<?php echo (int) $editing['id']; ?>">
                <?php endif; ?>
                <div class="form-grid">
                    <div><label>Icon (Material Symbols)</label><input name="icon" required value="<?php echo $editing ? e((string) $editing['icon']) : ''; ?>" placeholder="verified / support_agent ..."></div>
                    <div><label>Sort</label><input name="sort_order" type="number" value="<?php echo $editing ? (int) $editing['sort_order'] : 0; ?>"></div>
                    <div>
                        <label style="margin-bottom: 8px;">Aktiv</label>
                        <label style="display:flex; align-items:center; gap:8px; font-weight:500;">
                            <input type="checkbox" name="is_active" value="1" style="width:auto;" <?php echo !$editing || (int) $editing['is_active'] === 1 ? 'checked' : ''; ?>>
                            Is_active
                        </label>
                    </div>
                    <div style="grid-column: 1 / -1;"><label>Title</label><input name="title" required value="<?php echo $editing ? e((string) $editing['title']) : ''; ?>"></div>
                    <div style="grid-column: 1 / -1;"><label>Body</label><textarea name="body" required><?php echo $editing ? e((string) $editing['body']) : ''; ?></textarea></div>
                </div>
                <div class="stack-sm" style="margin-top: 12px;">
                    <button class="btn btn-primary" type="submit"><?php echo $editing ? 'Yenile' : 'Elave et'; ?></button>
                    <?php if ($editing): ?>
                        <a class="btn btn-muted" href="admin_features.php">Legv et</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="card table-wrap">
            <h3 class="section-title">Kartlar</h3>
            <table>
                <thead>
                <tr>
                    <th>№</th>
                    <th>Icon</th>
                    <th>Title</th>
                    <th>Aktiv</th>
                    <th>Sort</th>
                    <th>Emeliyyat</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($features)): ?>
                    <tr><td colspan="6">Kart yoxdur.</td></tr>
                <?php else: ?>
                    <?php foreach ($features as $idx => $f): ?>
                        <tr>
                            <td><?php echo $idx + 1; ?></td>
                            <td><code><?php echo e((string) $f['icon']); ?></code></td>
                            <td><?php echo e((string) $f['title']); ?><br><small class="spec-help">DB ID: <?php echo (int) $f['id']; ?></small></td>
                            <td><?php echo (int) $f['is_active'] === 1 ? '1' : '0'; ?></td>
                            <td><?php echo (int) $f['sort_order']; ?></td>
                            <td>
                                <div class="stack-sm">
                                    <a class="btn btn-muted" href="admin_features.php?edit=<?php echo (int) $f['id']; ?>">Duzenle</a>
                                    <form class="inline-form" method="post" onsubmit="return confirm('Bu kart silinsin?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int) $f['id']; ?>">
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

