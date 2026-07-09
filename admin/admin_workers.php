<?php
declare(strict_types=1);
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/admin_layout.php';

requireAdminAuth();
$pdo = db();

/** @return array<string, string> */
function workerPositionOptions(): array
{
    return [
        'Seller' => 'Seller',
        'Storekeeper' => 'Storekeeper',
    ];
}

function workersTableExists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    $stmt = $pdo->query(
        "SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'workers' LIMIT 1"
    );
    $exists = (bool) $stmt->fetchColumn();
    return $exists;
}

$flash = '';
$flashType = 'success';

if (!workersTableExists($pdo)) {
    $flash = "Isci cedveli tapilmadi. `database/migration_workers.sql` faylini MySQL-de ise salin.";
    $flashType = 'error';
}

$action = (string) ($_GET['action'] ?? '');
$editId = (int) ($_GET['id'] ?? 0);

$editRow = [
    'id' => 0,
    'full_name' => '',
    'phone' => '',
    'position_title' => '',
    'notes' => '',
    'sort_order' => 0,
    'is_active' => 1,
];

if ($action === 'edit' && $editId > 0 && workersTableExists($pdo)) {
    $stmt = $pdo->prepare("SELECT * FROM workers WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $editId]);
    $row = $stmt->fetch();
    if ($row) {
        $editRow = array_merge($editRow, $row);
    } else {
        $flash = 'Isci tapilmadi.';
        $flashType = 'error';
        $action = '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && workersTableExists($pdo)) {
    try {
        $postAction = (string) ($_POST['action'] ?? '');

        if ($postAction === 'save') {
            $id = (int) ($_POST['id'] ?? 0);
            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $positionTitle = trim((string) ($_POST['position_title'] ?? ''));
            $notes = trim((string) ($_POST['notes'] ?? ''));
            $sortOrder = (int) ($_POST['sort_order'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($fullName === '') {
                throw new RuntimeException('Ad soyad bos ola bilmez.');
            }

            $phone = $phone !== '' ? $phone : null;
            $positionTitle = $positionTitle !== '' ? $positionTitle : null;
            $notes = $notes !== '' ? $notes : null;

            $allowedPositions = array_keys(workerPositionOptions());
            if ($positionTitle === null || !in_array($positionTitle, $allowedPositions, true)) {
                throw new RuntimeException('Vezife yalniz Seller ve ya Storekeeper ola biler.');
            }

            if ($id > 0) {
                $upd = $pdo->prepare(
                    "UPDATE workers SET
                        full_name = :full_name,
                        phone = :phone,
                        position_title = :position_title,
                        notes = :notes,
                        sort_order = :sort_order,
                        is_active = :is_active
                     WHERE id = :id"
                );
                $upd->execute([
                    'full_name' => $fullName,
                    'phone' => $phone,
                    'position_title' => $positionTitle,
                    'notes' => $notes,
                    'sort_order' => $sortOrder,
                    'is_active' => $isActive,
                    'id' => $id,
                ]);
            } else {
                $ins = $pdo->prepare(
                    "INSERT INTO workers (full_name, phone, position_title, notes, sort_order, is_active)
                     VALUES (:full_name, :phone, :position_title, :notes, :sort_order, :is_active)"
                );
                $ins->execute([
                    'full_name' => $fullName,
                    'phone' => $phone,
                    'position_title' => $positionTitle,
                    'notes' => $notes,
                    'sort_order' => $sortOrder,
                    'is_active' => $isActive,
                ]);
            }

            header('Location: admin_workers.php');
            exit;
        }

        if ($postAction === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Gecersiz ID.');
            }
            $del = $pdo->prepare("DELETE FROM workers WHERE id = :id");
            $del->execute(['id' => $id]);

            header('Location: admin_workers.php');
            exit;
        }
    } catch (Throwable $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
    }
}

$workers = [];
if (workersTableExists($pdo)) {
    $workers = $pdo->query("SELECT * FROM workers ORDER BY sort_order ASC, id DESC")->fetchAll() ?: [];
}

$isEditing = $action === 'edit' && (int) $editRow['id'] > 0;
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Isciler</title>
    <link rel="stylesheet" href="../assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <?php adminSidebar('workers'); ?>
    <main class="admin-main">
        <header class="admin-top">
            <div>
                <h1 class="admin-title">Isciler</h1>
                <p class="admin-subtitle">Isci elave et, redakte et ve siyahini idare et.</p>
            </div>
            <?php if ($isEditing): ?>
                <a class="btn btn-muted" href="admin_workers.php">Yeni isci</a>
            <?php endif; ?>
        </header>

        <?php if ($flash !== ''): ?>
            <div class="flash <?php echo $flashType === 'error' ? 'flash-error' : 'flash-success'; ?>">
                <?php echo e($flash); ?>
            </div>
        <?php endif; ?>

        <section class="card">
            <h3 class="section-title"><?php echo $isEditing ? 'Isci redaktesi' : 'Yeni isci elave et'; ?></h3>
            <form method="post">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?php echo (int) $editRow['id']; ?>">

                <div class="form-grid">
                    <?php
                    $positionOptions = workerPositionOptions();
                    $selectedPosition = trim((string) ($editRow['position_title'] ?? ''));
                    ?>
                    <div class="u-col-span-full">
                        <label for="full_name">Ad soyad</label>
                        <input id="full_name" name="full_name" type="text" maxlength="120" required value="<?php echo e((string) ($editRow['full_name'] ?? '')); ?>" placeholder="Mes: Ali Veliyev">
                    </div>

                    <div>
                        <label for="phone">Telefon</label>
                        <input id="phone" name="phone" type="text" maxlength="40" value="<?php echo e((string) ($editRow['phone'] ?? '')); ?>" placeholder="+994 ...">
                    </div>

                    <div>
                        <label for="position_title">Vezife</label>
                        <select id="position_title" name="position_title" required>
                            <option value="" disabled <?php echo $selectedPosition === '' ? 'selected' : ''; ?>>Secin...</option>
                            <?php foreach ($positionOptions as $value => $label): ?>
                                <option value="<?php echo e($value); ?>" <?php echo $selectedPosition === $value ? 'selected' : ''; ?>>
                                    <?php echo e($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="sort_order">Siralama</label>
                        <input id="sort_order" name="sort_order" type="number" value="<?php echo (int) ($editRow['sort_order'] ?? 0); ?>">
                        <small class="spec-help">Kicik reqem yuxarida gosterilir.</small>
                    </div>

                    <div>
                        <label style="margin-bottom: 8px;">Aktivlik</label>
                        <label style="display:flex; align-items:center; gap:8px; font-weight:500;">
                            <input type="checkbox" name="is_active" value="1" style="width:auto;" <?php echo ((int) ($editRow['is_active'] ?? 1)) === 1 ? 'checked' : ''; ?>>
                            Aktiv et
                        </label>
                    </div>

                    <div class="u-col-span-full">
                        <label for="notes">Qeyd</label>
                        <textarea id="notes" name="notes" maxlength="500" placeholder="Daxili qeyd..."><?php echo e((string) ($editRow['notes'] ?? '')); ?></textarea>
                    </div>
                </div>

                <div class="stack-sm u-mt-12">
                    <button class="btn btn-primary" type="submit"><?php echo $isEditing ? 'Yenile' : 'Elave et'; ?></button>
                    <?php if ($isEditing): ?>
                        <a class="btn btn-muted" href="admin_workers.php">Imtina</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="card u-mt-12 table-wrap">
            <h3 class="section-title">Movcud isciler</h3>
            <table>
                <thead>
                <tr>
                    <th>Ad soyad</th>
                    <th>Telefon</th>
                    <th>Vezife</th>
                    <th>Siralama</th>
                    <th>Status</th>
                    <th>Emeliyyat</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($workers)): ?>
                    <tr><td colspan="6">Isci yoxdur.</td></tr>
                <?php else: ?>
                    <?php foreach ($workers as $w): ?>
                        <?php
                        $wid = (int) $w['id'];
                        $isActiveRow = (int) ($w['is_active'] ?? 0) === 1;
                        ?>
                        <tr>
                            <td class="table-cell-break">
                                <strong><?php echo e((string) $w['full_name']); ?></strong>
                                <br><small class="spec-help">DB ID: <?php echo $wid; ?></small>
                                <?php if (!empty($w['notes'])): ?>
                                    <br><small class="spec-help"><?php echo e((string) $w['notes']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e((string) ($w['phone'] ?? '')); ?></td>
                            <td><?php echo e((string) ($w['position_title'] ?? '')); ?></td>
                            <td><?php echo (int) ($w['sort_order'] ?? 0); ?></td>
                            <td>
                                <span class="status-badge <?php echo $isActiveRow ? 'status-active' : 'status-draft'; ?>">
                                    <?php echo $isActiveRow ? 'active' : 'draft'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="stack-sm u-stack-mobile">
                                    <a class="btn btn-muted btn-mobile-full" href="admin_workers.php?action=edit&id=<?php echo $wid; ?>">Redakte</a>
                                    <form method="post" class="inline-form" onsubmit="return confirm('Silinsin?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $wid; ?>">
                                        <button class="btn btn-danger btn-mobile-full" type="submit">Sil</button>
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

