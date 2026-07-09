<?php
declare(strict_types=1);
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/admin_layout.php';

requireAdminAuth();
$pdo = db();
$catalogOnlineSql = maxhomeProductsOnlineVisibleSql($pdo);

$message = '';
$messageType = 'success';

function upsertSetting(PDO $pdo, string $key, ?string $value): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO site_settings (setting_key, setting_value)
         VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    $stmt->execute(['k' => $key, 'v' => $value]);
}

function fetchSettings(PDO $pdo, array $keys): array
{
    if (empty($keys)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ({$placeholders})");
    $stmt->execute(array_values($keys));
    $rows = $stmt->fetchAll() ?: [];
    $map = [];
    foreach ($rows as $r) {
        $map[(string) $r['setting_key']] = (string) ($r['setting_value'] ?? '');
    }
    return $map;
}

$settings = fetchSettings($pdo, ['flash_start_at', 'flash_end_at', 'flash_product_id', 'flash_product_ids', 'flash_hide_on_expire']);
$currentFlashStartAt = (string) ($settings['flash_start_at'] ?? '');
$currentFlashEndAt = (string) ($settings['flash_end_at'] ?? '');
$currentFlashProductId = (string) ($settings['flash_product_id'] ?? '');
$currentFlashProductIds = (string) ($settings['flash_product_ids'] ?? '');
$currentFlashHideOnExpire = (string) ($settings['flash_hide_on_expire'] ?? '1');

/** @var list<int> $selectedFlashProductIds */
$selectedFlashProductIds = [];
if ($currentFlashProductIds !== '') {
    $parts = preg_split('/[,\s]+/', $currentFlashProductIds) ?: [];
    foreach ($parts as $part) {
        $id = (int) trim((string) $part);
        if ($id > 0) {
            $selectedFlashProductIds[$id] = $id;
        }
    }
}
if (empty($selectedFlashProductIds) && $currentFlashProductId !== '') {
    $legacyId = (int) $currentFlashProductId;
    if ($legacyId > 0) {
        $selectedFlashProductIds[$legacyId] = $legacyId;
    }
}
// map gibi kullandığımız için key'leri normalize et.
$selectedFlashProductIds = array_values($selectedFlashProductIds);

$products = $pdo->query("SELECT p.id, p.name, p.status FROM products p WHERE p.status = 'active'{$catalogOnlineSql} ORDER BY p.id DESC")->fetchAll() ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $rawStart = trim((string) ($_POST['flash_start_at'] ?? ''));
        $rawEnd = trim((string) ($_POST['flash_end_at'] ?? ''));
        $rawProductIds = $_POST['flash_product_ids'] ?? [];
        $hideOnExpire = isset($_POST['flash_hide_on_expire']) ? '1' : '0';

        $startValue = null;
        if ($rawStart !== '') {
            $normalized = str_replace('T', ' ', $rawStart);
            if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized)) {
                throw new RuntimeException('Flash start time formati sehvdir.');
            }
            $startValue = $normalized . ':00';
        }

        $endValue = null;
        if ($rawEnd !== '') {
            $normalized = str_replace('T', ' ', $rawEnd);
            if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized)) {
                throw new RuntimeException('Flash end time formati sehvdir.');
            }
            $endValue = $normalized . ':00';
        }

        $productIdList = [];
        if (!is_array($rawProductIds)) {
            $rawProductIds = [];
        }
        foreach ($rawProductIds as $rawId) {
            $pid = (int) trim((string) $rawId);
            if ($pid <= 0) {
                continue;
            }
            $productIdList[$pid] = $pid;
        }
        $productIdList = array_values($productIdList);
        $productIdsValue = !empty($productIdList) ? implode(',', $productIdList) : null;
        $productIdValue = !empty($productIdList) ? (string) $productIdList[0] : null; // geri uyumluluk

        if ($startValue !== null && $endValue !== null) {
            if (strtotime($endValue) <= strtotime($startValue)) {
                throw new RuntimeException('Flash end time, start time-dan sonra olmalidir.');
            }
        }

        upsertSetting($pdo, 'flash_start_at', $startValue);
        upsertSetting($pdo, 'flash_end_at', $endValue);
        upsertSetting($pdo, 'flash_product_id', $productIdValue);
        upsertSetting($pdo, 'flash_product_ids', $productIdsValue);
        upsertSetting($pdo, 'flash_hide_on_expire', $hideOnExpire);
        $message = 'Homepage ayarlari yenilendi.';

        $settings = fetchSettings($pdo, ['flash_start_at', 'flash_end_at', 'flash_product_id', 'flash_product_ids', 'flash_hide_on_expire']);
        $currentFlashStartAt = (string) ($settings['flash_start_at'] ?? '');
        $currentFlashEndAt = (string) ($settings['flash_end_at'] ?? '');
        $currentFlashProductId = (string) ($settings['flash_product_id'] ?? '');
        $currentFlashProductIds = (string) ($settings['flash_product_ids'] ?? '');
        $currentFlashHideOnExpire = (string) ($settings['flash_hide_on_expire'] ?? '1');

        $selectedFlashProductIds = [];
        if ($currentFlashProductIds !== '') {
            $parts = preg_split('/[,\s]+/', $currentFlashProductIds) ?: [];
            foreach ($parts as $part) {
                $id = (int) trim((string) $part);
                if ($id > 0) {
                    $selectedFlashProductIds[$id] = $id;
                }
            }
        }
        if (empty($selectedFlashProductIds) && $currentFlashProductId !== '') {
            $legacyId = (int) $currentFlashProductId;
            if ($legacyId > 0) {
                $selectedFlashProductIds[$legacyId] = $legacyId;
            }
        }
        $selectedFlashProductIds = array_values($selectedFlashProductIds);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Convert to datetime-local value if present
$dtLocalStart = '';
$dtLocalEnd = '';
if ($currentFlashStartAt !== '') {
    $dtLocalStart = str_replace(' ', 'T', substr($currentFlashStartAt, 0, 16));
}
if ($currentFlashEndAt !== '') {
    $dtLocalEnd = str_replace(' ', 'T', substr($currentFlashEndAt, 0, 16));
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Homepage</title>
    <link rel="stylesheet" href="../assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <?php adminSidebar('homepage'); ?>
    <main class="admin-main">
        <header class="admin-top">
            <div>
                <h1 class="admin-title">Homepage ayarlari</h1>
                <p class="admin-subtitle">Anasəhifədəki Flash Sale sayğacı kimi hissələr buradan idarə olunur.</p>
            </div>
        </header>

        <?php if ($message !== ''): ?>
            <div class="flash <?php echo $messageType === 'error' ? 'flash-error' : 'flash-success'; ?>">
                <?php echo e($message); ?>
            </div>
        <?php endif; ?>

        <section class="card">
            <h3 class="section-title">Flash Sale countdown</h3>
            <form method="post">
                <div class="form-grid">
                    <div class="flash-picker" style="grid-column: 1 / -1;">
                        <label for="flash_product_ids">Flash məhsulları (bir neçəsini seç)</label>
                        <input
                            id="flash_product_search"
                            type="search"
                            class="flash-picker__search"
                            placeholder="Məhsul axtar: ad və ya ID yaz..."
                            autocomplete="off"
                        >
                        <select id="flash_product_ids" name="flash_product_ids[]" multiple size="10" class="flash-picker__select">
                            <?php foreach ($products as $p): ?>
                                <?php $pid = (int) $p['id']; ?>
                                <option value="<?php echo $pid; ?>" <?php echo in_array($pid, $selectedFlashProductIds, true) ? 'selected' : ''; ?>>
                                    #<?php echo (int) $p['id']; ?> - <?php echo e((string) $p['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="flash_product_search_empty" class="flash-picker__empty" hidden>Axtarışa uyğun məhsul tapılmadı.</div>
                        <div class="flash-picker__meta">
                            <small class="flash-picker__hint">Secim etməsən sistem avtomatik məhsul göstərəcək. Ctrl/Cmd ilə çoxlu seç.</small>
                            <span class="flash-picker__count" id="flash_selected_count"></span>
                        </div>
                    </div>
                    <div>
                        <label for="flash_start_at">Flash start vaxtı</label>
                        <input id="flash_start_at" name="flash_start_at" type="datetime-local" value="<?php echo e($dtLocalStart); ?>">
                    </div>
                    <div>
                        <label for="flash_end_at">Flash bitiş vaxtı</label>
                        <input id="flash_end_at" name="flash_end_at" type="datetime-local" value="<?php echo e($dtLocalEnd); ?>">
                    </div>
                    <div>
                        <label style="margin-bottom: 8px;">Bitəndə davranış</label>
                        <label style="display:flex; align-items:center; gap:8px; font-weight:500;">
                            <input type="checkbox" name="flash_hide_on_expire" value="1" style="width:auto;" <?php echo $currentFlashHideOnExpire !== '0' ? 'checked' : ''; ?>>
                            Bitəndə hissəni gizlət (əks halda “Expired” göstərəcək)
                        </label>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <p class="admin-subtitle" style="margin-top:8px;">
                            Start və ya end boş olarsa, kampaniya “aktiv” sayılmayacaq.
                        </p>
                    </div>
                </div>
                <button class="btn btn-primary" type="submit" style="margin-top:12px;">Yadda saxla</button>
            </form>
        </section>
    </main>
</div>
<script>
(() => {
    const select = document.getElementById('flash_product_ids');
    const search = document.getElementById('flash_product_search');
    const emptyState = document.getElementById('flash_product_search_empty');
    const counter = document.getElementById('flash_selected_count');
    if (!select || !counter) return;

    const syncCount = () => {
        const selected = Array.from(select.selectedOptions).length;
        counter.textContent = selected > 0 ? `${selected} məhsul seçilib` : 'Auto seçim aktivdir';
    };

    const applyFilter = () => {
        if (!search) return;
        const q = search.value.trim().toLowerCase();
        let visible = 0;
        Array.from(select.options).forEach((opt) => {
            const text = (opt.textContent || '').toLowerCase();
            const matches = q === '' || text.includes(q);
            opt.hidden = !matches;
            if (matches) visible++;
        });
        if (emptyState) {
            emptyState.hidden = visible > 0;
        }
    };

    select.addEventListener('change', syncCount);
    if (search) {
        search.addEventListener('input', applyFilter);
    }
    applyFilter();
    syncCount();
})();
</script>
</body>
</html>

