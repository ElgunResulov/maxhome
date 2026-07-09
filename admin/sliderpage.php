<?php
declare(strict_types=1);
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/admin_layout.php';

requireAdminAuth();
$pdo = db();

function homepageSlidesTableExists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    $stmt = $pdo->query(
        "SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'homepage_slides' LIMIT 1"
    );
    $exists = (bool) $stmt->fetchColumn();
    return $exists;
}

function normalizeUrl(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    if (str_starts_with($value, '/')) {
        return $value;
    }
    if (preg_match('~^(https?://)~i', $value)) {
        return $value;
    }
    if (preg_match('~^[a-z0-9_\-]+\.php(\?.*)?$~i', $value)) {
        return $value;
    }
    return $value;
}

function saveHeroImageFromUpload(array $file): ?string
{
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Yukleme verisi yanlisdir.');
    }
    if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Fayl yuklenmesi ugursuz oldu.');
    }
    $maxBytes = 25 * 1024 * 1024;
    if ((int) $file['size'] > $maxBytes) {
        throw new RuntimeException('Fayl olcusu 25MB-den boyuk ola bilmez.');
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
    $targetDir = __DIR__ . '/../uploads/hero';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Uploads qovlugu yaradilamadi.');
    }

    $filename = 'hero_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $extension;
    $destination = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($tmpPath, $destination)) {
        throw new RuntimeException('Sekil uploads qovluguna kopyalana bilmedi.');
    }

    return 'uploads/hero/' . $filename;
}

function deleteUploadIfLocal(?string $imageUrl): void
{
    $imageUrl = (string) $imageUrl;
    if ($imageUrl === '' || !str_starts_with($imageUrl, 'uploads/')) {
        return;
    }
    $fullPath = __DIR__ . '/../' . str_replace('/', DIRECTORY_SEPARATOR, $imageUrl);
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

$flash = '';
$flashType = 'success';

if (!homepageSlidesTableExists($pdo)) {
    $flash = "Slider cədvəli tapılmadı. `database/migration_homepage_slides.sql` faylını MySQL-də işə salın.";
    $flashType = 'error';
}

$action = (string) ($_GET['action'] ?? '');
$editId = (int) ($_GET['id'] ?? 0);

$editRow = [
    'id' => 0,
    'badge_text' => '',
    'title' => '',
    'body_text' => '',
    'primary_label' => '',
    'primary_url' => '',
    'secondary_label' => '',
    'secondary_url' => '',
    'image_url' => '',
    'sort_order' => 0,
    'is_active' => 1,
];

if ($action === 'edit' && $editId > 0 && homepageSlidesTableExists($pdo)) {
    $stmt = $pdo->prepare("SELECT * FROM homepage_slides WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $editId]);
    $row = $stmt->fetch();
    if ($row) {
        $editRow = array_merge($editRow, $row);
    } else {
        $flash = 'Slayd tapılmadı.';
        $flashType = 'error';
        $action = '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && homepageSlidesTableExists($pdo)) {
    try {
        $postAction = (string) ($_POST['action'] ?? '');

        if ($postAction === 'save') {
            $id = (int) ($_POST['id'] ?? 0);
            $badgeText = trim((string) ($_POST['badge_text'] ?? ''));
            $title = trim((string) ($_POST['title'] ?? ''));
            $bodyText = trim((string) ($_POST['body_text'] ?? ''));
            $primaryLabel = trim((string) ($_POST['primary_label'] ?? ''));
            $primaryUrl = normalizeUrl($_POST['primary_url'] ?? null);
            $secondaryLabel = trim((string) ($_POST['secondary_label'] ?? ''));
            $secondaryUrl = normalizeUrl($_POST['secondary_url'] ?? null);
            $sortOrder = (int) ($_POST['sort_order'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $removeImage = isset($_POST['remove_image']);

            if ($title === '') {
                throw new RuntimeException('Basliq (Title) bos ola bilmez.');
            }

            $newImageUrl = null;
            if (isset($_FILES['image']) && is_array($_FILES['image'])) {
                $newImageUrl = saveHeroImageFromUpload($_FILES['image']);
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("SELECT image_url FROM homepage_slides WHERE id = :id LIMIT 1");
                $stmt->execute(['id' => $id]);
                $oldImageUrl = (string) ($stmt->fetchColumn() ?: '');

                $finalImageUrl = $oldImageUrl;
                if ($removeImage) {
                    $finalImageUrl = null;
                    deleteUploadIfLocal($oldImageUrl);
                }
                if ($newImageUrl !== null) {
                    $finalImageUrl = $newImageUrl;
                    deleteUploadIfLocal($oldImageUrl);
                }

                $upd = $pdo->prepare(
                    "UPDATE homepage_slides SET
                        badge_text = :badge_text,
                        title = :title,
                        body_text = :body_text,
                        primary_label = :primary_label,
                        primary_url = :primary_url,
                        secondary_label = :secondary_label,
                        secondary_url = :secondary_url,
                        image_url = :image_url,
                        sort_order = :sort_order,
                        is_active = :is_active
                     WHERE id = :id"
                );
                $upd->execute([
                    'badge_text' => $badgeText !== '' ? $badgeText : null,
                    'title' => $title,
                    'body_text' => $bodyText !== '' ? $bodyText : null,
                    'primary_label' => $primaryLabel !== '' ? $primaryLabel : null,
                    'primary_url' => $primaryUrl,
                    'secondary_label' => $secondaryLabel !== '' ? $secondaryLabel : null,
                    'secondary_url' => $secondaryUrl,
                    'image_url' => $finalImageUrl,
                    'sort_order' => $sortOrder,
                    'is_active' => $isActive,
                    'id' => $id,
                ]);

                $flash = 'Slayd yenilendi.';
                $flashType = 'success';
            } else {
                $ins = $pdo->prepare(
                    "INSERT INTO homepage_slides (
                        badge_text, title, body_text,
                        primary_label, primary_url,
                        secondary_label, secondary_url,
                        image_url, sort_order, is_active
                    ) VALUES (
                        :badge_text, :title, :body_text,
                        :primary_label, :primary_url,
                        :secondary_label, :secondary_url,
                        :image_url, :sort_order, :is_active
                    )"
                );
                $ins->execute([
                    'badge_text' => $badgeText !== '' ? $badgeText : null,
                    'title' => $title,
                    'body_text' => $bodyText !== '' ? $bodyText : null,
                    'primary_label' => $primaryLabel !== '' ? $primaryLabel : null,
                    'primary_url' => $primaryUrl,
                    'secondary_label' => $secondaryLabel !== '' ? $secondaryLabel : null,
                    'secondary_url' => $secondaryUrl,
                    'image_url' => $newImageUrl,
                    'sort_order' => $sortOrder,
                    'is_active' => $isActive,
                ]);

                $flash = 'Slayd elave edildi.';
                $flashType = 'success';
            }

            header('Location: sliderpage.php');
            exit;
        }

        if ($postAction === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Gecersiz ID.');
            }
            $stmt = $pdo->prepare("SELECT image_url FROM homepage_slides WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $id]);
            $img = (string) ($stmt->fetchColumn() ?: '');

            $del = $pdo->prepare("DELETE FROM homepage_slides WHERE id = :id");
            $del->execute(['id' => $id]);
            deleteUploadIfLocal($img);

            header('Location: sliderpage.php');
            exit;
        }
    } catch (Throwable $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
    }
}

$slides = [];
if (homepageSlidesTableExists($pdo)) {
    $slides = $pdo->query("SELECT * FROM homepage_slides ORDER BY sort_order ASC, id DESC")->fetchAll() ?: [];
}

$isEditing = $action === 'edit' && (int) $editRow['id'] > 0;
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Homepage slider</title>
    <link rel="stylesheet" href="../assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <?php adminSidebar('sliderpage'); ?>
    <main class="admin-main">
        <header class="admin-top">
            <div>
                <h1 class="admin-title">Homepage slider</h1>
                <p class="admin-subtitle">Anasəhifədəki hero/slider slaydlarını buradan idarə edin.</p>
            </div>
            <?php if ($isEditing): ?>
                <a class="btn btn-muted" href="sliderpage.php">Yeni slayd</a>
            <?php endif; ?>
        </header>

        <?php if ($flash !== ''): ?>
            <div class="flash <?php echo $flashType === 'error' ? 'flash-error' : 'flash-success'; ?>">
                <?php echo e($flash); ?>
            </div>
        <?php endif; ?>

        <section class="card">
            <h3 class="section-title"><?php echo $isEditing ? 'Slaydi redakte et' : 'Yeni slayd elave et'; ?></h3>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?php echo (int) $editRow['id']; ?>">
                <div class="form-grid">
                    <div>
                        <label for="badge_text">Badge (kicik etiket)</label>
                        <input id="badge_text" name="badge_text" type="text" maxlength="80" value="<?php echo e((string) ($editRow['badge_text'] ?? '')); ?>" placeholder="Önə çıxan">
                    </div>
                    <div>
                        <label for="sort_order">Siralama</label>
                        <input id="sort_order" name="sort_order" type="number" value="<?php echo (int) ($editRow['sort_order'] ?? 0); ?>">
                        <small class="spec-help">Kicik reqem yuxarida gosterilir.</small>
                    </div>
                    <div class="u-col-span-full">
                        <label for="title">Basliq</label>
                        <input id="title" name="title" type="text" maxlength="160" required value="<?php echo e((string) ($editRow['title'] ?? '')); ?>" placeholder="Hüdudlardan kənarda.">
                    </div>
                    <div class="u-col-span-full">
                        <label for="body_text">Metn</label>
                        <textarea id="body_text" name="body_text" maxlength="420" placeholder="Seçilmiş flaqman cihazlar və premium xidmət ilə..."><?php echo e((string) ($editRow['body_text'] ?? '')); ?></textarea>
                    </div>
                    <div>
                        <label for="primary_label">1-ci button metni</label>
                        <input id="primary_label" name="primary_label" type="text" maxlength="60" value="<?php echo e((string) ($editRow['primary_label'] ?? '')); ?>" placeholder="İndi al">
                    </div>
                    <div>
                        <label for="primary_url">1-ci button link</label>
                        <input id="primary_url" name="primary_url" type="text" maxlength="255" value="<?php echo e((string) ($editRow['primary_url'] ?? '')); ?>" placeholder="shop_page.php veya /shop_page.php">
                    </div>
                    <div>
                        <label for="secondary_label">2-ci button metni</label>
                        <input id="secondary_label" name="secondary_label" type="text" maxlength="60" value="<?php echo e((string) ($editRow['secondary_label'] ?? '')); ?>" placeholder="Endirimlərə bax">
                    </div>
                    <div>
                        <label for="secondary_url">2-ci button link</label>
                        <input id="secondary_url" name="secondary_url" type="text" maxlength="255" value="<?php echo e((string) ($editRow['secondary_url'] ?? '')); ?>" placeholder="shop_page.php?sort=rating">
                    </div>
                    <div class="u-col-span-full">
                        <label for="image">Slayd sekli (JPG/PNG/WEBP)</label>
                        <input id="image" name="image" type="file" accept="image/png,image/jpeg,image/webp">
                        <?php if (!empty($editRow['image_url'])): ?>
                            <div class="u-flex u-items-center u-gap-12 u-mt-12 u-flex-wrap">
                                <img class="thumb" src="../<?php echo e((string) $editRow['image_url']); ?>" alt="Slide image" onerror="this.remove();">
                                <label style="display:flex; align-items:center; gap:8px; font-weight:500;">
                                    <input type="checkbox" name="remove_image" value="1" style="width:auto;">
                                    Sekli sil
                                </label>
                                <small class="spec-help table-cell-break"><?php echo e((string) $editRow['image_url']); ?></small>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label style="margin-bottom: 8px;">Aktivlik</label>
                        <label style="display:flex; align-items:center; gap:8px; font-weight:500;">
                            <input type="checkbox" name="is_active" value="1" style="width:auto;" <?php echo ((int) ($editRow['is_active'] ?? 1)) === 1 ? 'checked' : ''; ?>>
                            Slaydi aktiv et
                        </label>
                    </div>
                </div>
                <div class="stack-sm u-mt-12">
                    <button class="btn btn-primary" type="submit"><?php echo $isEditing ? 'Yenile' : 'Elave et'; ?></button>
                    <?php if ($isEditing): ?>
                        <a class="btn btn-muted" href="sliderpage.php">Imtina</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="card u-mt-12 table-wrap">
            <h3 class="section-title">Mövcud slaydlar</h3>
            <table>
                <thead>
                <tr>
                    <th>Preview</th>
                    <th>Basliq</th>
                    <th>Badge</th>
                    <th>Siralama</th>
                    <th>Status</th>
                    <th>Emeliyyat</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($slides)): ?>
                    <tr><td colspan="6">Slayd yoxdur.</td></tr>
                <?php else: ?>
                    <?php foreach ($slides as $s): ?>
                        <?php
                        $sid = (int) $s['id'];
                        $imgUrl = (string) ($s['image_url'] ?? '');
                        $isActiveRow = (int) ($s['is_active'] ?? 0) === 1;
                        ?>
                        <tr>
                            <td>
                                <?php if ($imgUrl !== ''): ?>
                                    <img class="thumb" src="../<?php echo e($imgUrl); ?>" alt="thumb" onerror="this.remove();">
                                <?php else: ?>
                                    <span class="status-badge status-draft">NO IMG</span>
                                <?php endif; ?>
                                <small class="spec-help">DB ID: <?php echo $sid; ?></small>
                            </td>
                            <td class="table-cell-break">
                                <strong><?php echo e((string) $s['title']); ?></strong>
                                <?php if (!empty($s['body_text'])): ?>
                                    <br><small class="spec-help"><?php echo e((string) $s['body_text']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e((string) ($s['badge_text'] ?? '')); ?></td>
                            <td><?php echo (int) ($s['sort_order'] ?? 0); ?></td>
                            <td>
                                <span class="status-badge <?php echo $isActiveRow ? 'status-active' : 'status-draft'; ?>">
                                    <?php echo $isActiveRow ? 'active' : 'draft'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="stack-sm u-stack-mobile">
                                    <a class="btn btn-muted btn-mobile-full" href="sliderpage.php?action=edit&id=<?php echo $sid; ?>">Redakte</a>
                                    <form method="post" class="inline-form" onsubmit="return confirm('Silinsin?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $sid; ?>">
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

