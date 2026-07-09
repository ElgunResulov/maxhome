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
            $description = trim((string) ($_POST['description'] ?? ''));
            $value = trim((string) ($_POST['value'] ?? ''));
            $actionUrl = trim((string) ($_POST['action_url'] ?? ''));
            $sortOrder = (int) ($_POST['sort_order'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($icon === '' || $title === '' || $description === '' || $value === '') {
                throw new RuntimeException('Icon, title, description ve value mecburidir.');
            }

            $stmt = $pdo->prepare(
                "INSERT INTO support_contact_cards (icon, title, description, value, action_url, sort_order, is_active)
                 VALUES (:icon, :title, :description, :value, :action_url, :sort_order, :is_active)"
            );
            $stmt->execute([
                'icon' => $icon,
                'title' => $title,
                'description' => $description,
                'value' => $value,
                'action_url' => $actionUrl,
                'sort_order' => $sortOrder,
                'is_active' => $isActive,
            ]);
            $message = 'Contact karti ugurla elave edildi.';
        } elseif ($action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $icon = trim((string) ($_POST['icon'] ?? ''));
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $value = trim((string) ($_POST['value'] ?? ''));
            $actionUrl = trim((string) ($_POST['action_url'] ?? ''));
            $sortOrder = (int) ($_POST['sort_order'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($id <= 0 || $icon === '' || $title === '' || $description === '' || $value === '') {
                throw new RuntimeException('Yenileme melumatlari sehvdir.');
            }

            $stmt = $pdo->prepare(
                "UPDATE support_contact_cards
                 SET icon = :icon, title = :title, description = :description, value = :value, action_url = :action_url,
                     sort_order = :sort_order, is_active = :is_active
                 WHERE id = :id"
            );
            $stmt->execute([
                'id' => $id,
                'icon' => $icon,
                'title' => $title,
                'description' => $description,
                'value' => $value,
                'action_url' => $actionUrl,
                'sort_order' => $sortOrder,
                'is_active' => $isActive,
            ]);
            $message = 'Contact karti ugurla yenilendi.';
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Silmek ucun kart secin.');
            }
            $pdo->prepare("DELETE FROM support_contact_cards WHERE id = :id")->execute(['id' => $id]);
            $message = 'Contact karti silindi.';
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
    $stmt = $pdo->prepare("SELECT * FROM support_contact_cards WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $editId]);
    $editing = $stmt->fetch() ?: null;
}

$cards = $pdo->query(
    "SELECT id, icon, title, description, value, action_url, sort_order, is_active
     FROM support_contact_cards
     ORDER BY sort_order ASC, id ASC"
)->fetchAll() ?: [];
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Support contact kartlari</title>
    <link rel="stylesheet" href="../assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <?php adminSidebar('support_contacts'); ?>
    <main class="admin-main">
        <header class="admin-top">
            <div>
                <h1 class="admin-title">Support contact kartlari</h1>
                <p class="admin-subtitle">`support_center.php` sehifesindeki "Still need assistance?" kartlari buradan idare olunur.</p>
            </div>
        </header>

        <?php if ($message !== ''): ?>
            <div class="flash <?php echo $messageType === 'error' ? 'flash-error' : 'flash-success'; ?>">
                <?php echo e($message); ?>
            </div>
        <?php endif; ?>

        <section class="card" style="margin-bottom: 14px;">
            <h3 class="section-title"><?php echo $editing ? 'Karti redakte et' : 'Yeni kart'; ?></h3>
            <form method="post">
                <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
                <?php if ($editing): ?>
                    <input type="hidden" name="id" value="<?php echo (int) $editing['id']; ?>">
                <?php endif; ?>
                <div class="form-grid">
                    <div><label>Sort</label><input name="sort_order" type="number" value="<?php echo $editing ? (int) $editing['sort_order'] : 0; ?>"></div>
                    <div>
                        <label style="margin-bottom: 8px;">Aktiv</label>
                        <label style="display:flex; align-items:center; gap:8px; font-weight:500;">
                            <input type="checkbox" name="is_active" value="1" style="width:auto;" <?php echo !$editing || (int) $editing['is_active'] === 1 ? 'checked' : ''; ?>>
                            Is_active
                        </label>
                    </div>
                    <div><label>Icon (Material Symbol)</label><input name="icon" required value="<?php echo $editing ? e((string) $editing['icon']) : ''; ?>" placeholder="call, mail, forum, location_on"></div>
                    <div><label>Title</label><input name="title" required value="<?php echo $editing ? e((string) $editing['title']) : ''; ?>" placeholder="Call Us"></div>
                    <div style="grid-column: 1 / -1;"><label>Description</label><input name="description" required value="<?php echo $editing ? e((string) $editing['description']) : ''; ?>" placeholder="Immediate help for urgent issues."></div>
                    <div style="grid-column: 1 / -1;"><label>Value</label><input name="value" required value="<?php echo $editing ? e((string) $editing['value']) : ''; ?>" placeholder="1-800-MAX-HOME"></div>
                    <div style="grid-column: 1 / -1;"><label>Action URL</label><input name="action_url" value="<?php echo $editing ? e((string) ($editing['action_url'] ?? '')) : ''; ?>" placeholder="tel:1800MAXHOME, mailto:concierge@maxhome.tech, https://..."></div>
                </div>
                <div class="stack-sm" style="margin-top: 12px;">
                    <button class="btn btn-primary" type="submit"><?php echo $editing ? 'Yenile' : 'Elave et'; ?></button>
                    <?php if ($editing): ?>
                        <a class="btn btn-muted" href="admin_support_contacts.php">Legv et</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="card table-wrap">
            <h3 class="section-title">Butun kartlar</h3>
            <table>
                <thead>
                <tr>
                    <th>№</th>
                    <th>Icon</th>
                    <th>Title</th>
                    <th>Description</th>
                    <th>Value</th>
                    <th>Action URL</th>
                    <th>Aktiv</th>
                    <th>Sort</th>
                    <th>Emeliyyat</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($cards)): ?>
                    <tr><td colspan="9">Kart yoxdur.</td></tr>
                <?php else: ?>
                    <?php foreach ($cards as $idx => $item): ?>
                        <tr>
                            <td><?php echo $idx + 1; ?></td>
                            <td><code><?php echo e((string) $item['icon']); ?></code></td>
                            <td><?php echo e((string) $item['title']); ?><br><small class="spec-help">DB ID: <?php echo (int) $item['id']; ?></small></td>
                            <td><?php echo e((string) $item['description']); ?></td>
                            <td><?php echo e((string) $item['value']); ?></td>
                            <td><code><?php echo e((string) ($item['action_url'] ?? '')); ?></code></td>
                            <td><?php echo (int) $item['is_active'] === 1 ? '1' : '0'; ?></td>
                            <td><?php echo (int) $item['sort_order']; ?></td>
                            <td>
                                <div class="stack-sm">
                                    <a class="btn btn-muted" href="admin_support_contacts.php?edit=<?php echo (int) $item['id']; ?>">Duzenle</a>
                                    <form class="inline-form" method="post" onsubmit="return confirm('Bu kart silinsin?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
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
