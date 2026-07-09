<?php
declare(strict_types=1);
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/admin_layout.php';

requireAdminAuth();
$pdo = db();

$message = '';
$messageType = 'success';
$allowedSections = ['explore', 'assistance', 'legal'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    try {
        if ($action === 'create') {
            $section = trim((string) ($_POST['section'] ?? ''));
            $label = trim((string) ($_POST['label'] ?? ''));
            $url = trim((string) ($_POST['url'] ?? ''));
            $sortOrder = (int) ($_POST['sort_order'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if (!in_array($section, $allowedSections, true)) {
                throw new RuntimeException('Section sehvdir.');
            }
            if ($label === '' || $url === '') {
                throw new RuntimeException('Label ve url mecburidir.');
            }

            $stmt = $pdo->prepare(
                "INSERT INTO footer_links (section, label, url, sort_order, is_active)
                 VALUES (:section, :label, :url, :sort_order, :is_active)"
            );
            $stmt->execute([
                'section' => $section,
                'label' => $label,
                'url' => $url,
                'sort_order' => $sortOrder,
                'is_active' => $isActive,
            ]);
            $message = 'Footer link ugurla elave edildi.';
        } elseif ($action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $section = trim((string) ($_POST['section'] ?? ''));
            $label = trim((string) ($_POST['label'] ?? ''));
            $url = trim((string) ($_POST['url'] ?? ''));
            $sortOrder = (int) ($_POST['sort_order'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($id <= 0 || !in_array($section, $allowedSections, true) || $label === '' || $url === '') {
                throw new RuntimeException('Yenileme melumatlari sehvdir.');
            }

            $stmt = $pdo->prepare(
                "UPDATE footer_links
                 SET section = :section, label = :label, url = :url, sort_order = :sort_order, is_active = :is_active
                 WHERE id = :id"
            );
            $stmt->execute([
                'id' => $id,
                'section' => $section,
                'label' => $label,
                'url' => $url,
                'sort_order' => $sortOrder,
                'is_active' => $isActive,
            ]);
            $message = 'Footer link ugurla yenilendi.';
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Silmek ucun link secin.');
            }
            $pdo->prepare("DELETE FROM footer_links WHERE id = :id")->execute(['id' => $id]);
            $message = 'Footer link silindi.';
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
    $stmt = $pdo->prepare("SELECT * FROM footer_links WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $editId]);
    $editing = $stmt->fetch() ?: null;
}

$links = $pdo->query(
    "SELECT id, section, label, url, sort_order, is_active
     FROM footer_links
     ORDER BY section ASC, sort_order ASC, id ASC"
)->fetchAll() ?: [];
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Footer linkleri</title>
    <link rel="stylesheet" href="../assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <?php adminSidebar('footer'); ?>
    <main class="admin-main">
        <header class="admin-top">
            <div>
                <h1 class="admin-title">Footer linkleri</h1>
                <p class="admin-subtitle">`index.php` footer menuleri buradan idarə olunur.</p>
            </div>
        </header>

        <?php if ($message !== ''): ?>
            <div class="flash <?php echo $messageType === 'error' ? 'flash-error' : 'flash-success'; ?>">
                <?php echo e($message); ?>
            </div>
        <?php endif; ?>

        <section class="card" style="margin-bottom: 14px;">
            <h3 class="section-title"><?php echo $editing ? 'Link redakte et' : 'Yeni link'; ?></h3>
            <form method="post">
                <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
                <?php if ($editing): ?>
                    <input type="hidden" name="id" value="<?php echo (int) $editing['id']; ?>">
                <?php endif; ?>
                <div class="form-grid">
                    <div>
                        <label>Section</label>
                        <select name="section" required>
                            <?php foreach ($allowedSections as $sec): ?>
                                <option value="<?php echo e($sec); ?>" <?php echo $editing && $editing['section'] === $sec ? 'selected' : ''; ?>>
                                    <?php echo e($sec); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div><label>Sort</label><input name="sort_order" type="number" value="<?php echo $editing ? (int) $editing['sort_order'] : 0; ?>"></div>
                    <div>
                        <label style="margin-bottom: 8px;">Aktiv</label>
                        <label style="display:flex; align-items:center; gap:8px; font-weight:500;">
                            <input type="checkbox" name="is_active" value="1" style="width:auto;" <?php echo !$editing || (int) $editing['is_active'] === 1 ? 'checked' : ''; ?>>
                            Is_active
                        </label>
                    </div>
                    <div style="grid-column: 1 / -1;"><label>Label</label><input name="label" required value="<?php echo $editing ? e((string) $editing['label']) : ''; ?>"></div>
                    <div style="grid-column: 1 / -1;"><label>URL</label><input name="url" required value="<?php echo $editing ? e((string) $editing['url']) : ''; ?>" placeholder="shop_page.php?category=Smartphones veya https://..."></div>
                </div>
                <div class="stack-sm" style="margin-top: 12px;">
                    <button class="btn btn-primary" type="submit"><?php echo $editing ? 'Yenile' : 'Elave et'; ?></button>
                    <?php if ($editing): ?>
                        <a class="btn btn-muted" href="admin_footer_links.php">Legv et</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="card table-wrap">
            <h3 class="section-title">Linkler</h3>
            <table>
                <thead>
                <tr>
                    <th>№</th>
                    <th>Section</th>
                    <th>Label</th>
                    <th>URL</th>
                    <th>Aktiv</th>
                    <th>Sort</th>
                    <th>Emeliyyat</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($links)): ?>
                    <tr><td colspan="7">Link yoxdur.</td></tr>
                <?php else: ?>
                    <?php foreach ($links as $idx => $l): ?>
                        <tr>
                            <td><?php echo $idx + 1; ?></td>
                            <td><code><?php echo e((string) $l['section']); ?></code></td>
                            <td><?php echo e((string) $l['label']); ?><br><small class="spec-help">DB ID: <?php echo (int) $l['id']; ?></small></td>
                            <td><code><?php echo e((string) $l['url']); ?></code></td>
                            <td><?php echo (int) $l['is_active'] === 1 ? '1' : '0'; ?></td>
                            <td><?php echo (int) $l['sort_order']; ?></td>
                            <td>
                                <div class="stack-sm">
                                    <a class="btn btn-muted" href="admin_footer_links.php?edit=<?php echo (int) $l['id']; ?>">Duzenle</a>
                                    <form class="inline-form" method="post" onsubmit="return confirm('Bu link silinsin?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int) $l['id']; ?>">
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

