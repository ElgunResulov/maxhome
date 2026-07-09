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
            $question = trim((string) ($_POST['question'] ?? ''));
            $answer = trim((string) ($_POST['answer'] ?? ''));
            $sortOrder = (int) ($_POST['sort_order'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($question === '' || $answer === '') {
                throw new RuntimeException('Sual ve cavab mecburidir.');
            }

            $stmt = $pdo->prepare(
                "INSERT INTO support_faqs (question, answer, sort_order, is_active)
                 VALUES (:question, :answer, :sort_order, :is_active)"
            );
            $stmt->execute([
                'question' => $question,
                'answer' => $answer,
                'sort_order' => $sortOrder,
                'is_active' => $isActive,
            ]);
            $message = 'Sual ugurla elave edildi.';
        } elseif ($action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $question = trim((string) ($_POST['question'] ?? ''));
            $answer = trim((string) ($_POST['answer'] ?? ''));
            $sortOrder = (int) ($_POST['sort_order'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($id <= 0 || $question === '' || $answer === '') {
                throw new RuntimeException('Yenileme melumatlari sehvdir.');
            }

            $stmt = $pdo->prepare(
                "UPDATE support_faqs
                 SET question = :question, answer = :answer, sort_order = :sort_order, is_active = :is_active
                 WHERE id = :id"
            );
            $stmt->execute([
                'id' => $id,
                'question' => $question,
                'answer' => $answer,
                'sort_order' => $sortOrder,
                'is_active' => $isActive,
            ]);
            $message = 'Sual ugurla yenilendi.';
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Silmek ucun sual secin.');
            }
            $pdo->prepare("DELETE FROM support_faqs WHERE id = :id")->execute(['id' => $id]);
            $message = 'Sual silindi.';
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
    $stmt = $pdo->prepare("SELECT * FROM support_faqs WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $editId]);
    $editing = $stmt->fetch() ?: null;
}

$faqs = $pdo->query(
    "SELECT id, question, answer, sort_order, is_active
     FROM support_faqs
     ORDER BY sort_order ASC, id ASC"
)->fetchAll() ?: [];
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Support suallari</title>
    <link rel="stylesheet" href="../assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <?php adminSidebar('support_faqs'); ?>
    <main class="admin-main">
        <header class="admin-top">
            <div>
                <h1 class="admin-title">Support suallari</h1>
                <p class="admin-subtitle">`support_center.php` ucun FAQ melumatlari buradan idare olunur.</p>
            </div>
        </header>

        <?php if ($message !== ''): ?>
            <div class="flash <?php echo $messageType === 'error' ? 'flash-error' : 'flash-success'; ?>">
                <?php echo e($message); ?>
            </div>
        <?php endif; ?>

        <section class="card" style="margin-bottom: 14px;">
            <h3 class="section-title"><?php echo $editing ? 'Suali redakte et' : 'Yeni sual'; ?></h3>
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
                    <div style="grid-column: 1 / -1;"><label>Sual</label><input name="question" required value="<?php echo $editing ? e((string) $editing['question']) : ''; ?>"></div>
                    <div style="grid-column: 1 / -1;">
                        <label>Cavab</label>
                        <textarea name="answer" rows="5" required><?php echo $editing ? e((string) $editing['answer']) : ''; ?></textarea>
                    </div>
                </div>
                <div class="stack-sm" style="margin-top: 12px;">
                    <button class="btn btn-primary" type="submit"><?php echo $editing ? 'Yenile' : 'Elave et'; ?></button>
                    <?php if ($editing): ?>
                        <a class="btn btn-muted" href="admin_support_faqs.php">Legv et</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="card table-wrap">
            <h3 class="section-title">Butun suallar</h3>
            <table>
                <thead>
                <tr>
                    <th>№</th>
                    <th>Sual</th>
                    <th>Cavab</th>
                    <th>Aktiv</th>
                    <th>Sort</th>
                    <th>Emeliyyat</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($faqs)): ?>
                    <tr><td colspan="6">Sual yoxdur.</td></tr>
                <?php else: ?>
                    <?php foreach ($faqs as $idx => $item): ?>
                        <tr>
                            <td><?php echo $idx + 1; ?></td>
                            <td><?php echo e((string) $item['question']); ?><br><small class="spec-help">DB ID: <?php echo (int) $item['id']; ?></small></td>
                            <td><?php echo e(mb_strimwidth((string) $item['answer'], 0, 110, '...')); ?></td>
                            <td><?php echo (int) $item['is_active'] === 1 ? '1' : '0'; ?></td>
                            <td><?php echo (int) $item['sort_order']; ?></td>
                            <td>
                                <div class="stack-sm">
                                    <a class="btn btn-muted" href="admin_support_faqs.php?edit=<?php echo (int) $item['id']; ?>">Duzenle</a>
                                    <form class="inline-form" method="post" onsubmit="return confirm('Bu sual silinsin?');">
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
