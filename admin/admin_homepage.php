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

/**
 * @return list<int>
 */
function parseProductIdList(string $raw, int $max = 0): array
{
    $ids = [];
    if ($raw === '') {
        return $ids;
    }
    $parts = preg_split('/[,\s]+/', $raw) ?: [];
    foreach ($parts as $part) {
        $id = (int) trim((string) $part);
        if ($id > 0) {
            $ids[$id] = $id;
        }
        if ($max > 0 && count($ids) >= $max) {
            break;
        }
    }
    return array_values($ids);
}

/**
 * @param mixed $rawProductIds
 * @return list<int>
 */
function collectPostedProductIds($rawProductIds, int $max = 0): array
{
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
        if ($max > 0 && count($productIdList) >= $max) {
            break;
        }
    }
    return array_values($productIdList);
}

function normalizeDatetimeLocal(string $raw, string $label): ?string
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    $normalized = str_replace('T', ' ', $raw);
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized)) {
        throw new RuntimeException($label . ' formati sehvdir.');
    }
    return $normalized . ':00';
}

function toDatetimeLocalValue(string $dbValue): string
{
    if ($dbValue === '') {
        return '';
    }
    return str_replace(' ', 'T', substr($dbValue, 0, 16));
}

$settingKeys = [
    'flash_start_at',
    'flash_end_at',
    'flash_product_id',
    'flash_product_ids',
    'flash_hide_on_expire',
    'weekly_offer_enabled',
    'weekly_offer_product_ids',
    'weekly_offer_end_at',
    'weekly_offer_cta_url',
    'weekly_offer_hide_on_expire',
];

$settings = fetchSettings($pdo, $settingKeys);

$currentFlashStartAt = (string) ($settings['flash_start_at'] ?? '');
$currentFlashEndAt = (string) ($settings['flash_end_at'] ?? '');
$currentFlashProductId = (string) ($settings['flash_product_id'] ?? '');
$currentFlashProductIds = (string) ($settings['flash_product_ids'] ?? '');
$currentFlashHideOnExpire = (string) ($settings['flash_hide_on_expire'] ?? '1');

$selectedFlashProductIds = parseProductIdList($currentFlashProductIds);
if (empty($selectedFlashProductIds) && $currentFlashProductId !== '') {
    $legacyId = (int) $currentFlashProductId;
    if ($legacyId > 0) {
        $selectedFlashProductIds = [$legacyId];
    }
}

$currentWeeklyEnabled = (string) ($settings['weekly_offer_enabled'] ?? '1');
$currentWeeklyProductIds = (string) ($settings['weekly_offer_product_ids'] ?? '');
$currentWeeklyEndAt = (string) ($settings['weekly_offer_end_at'] ?? '');
$currentWeeklyCtaUrl = (string) ($settings['weekly_offer_cta_url'] ?? 'shop_page.php?sort=price_low');
$currentWeeklyHideOnExpire = (string) ($settings['weekly_offer_hide_on_expire'] ?? '1');
$selectedWeeklyProductIds = parseProductIdList($currentWeeklyProductIds, 3);

$products = $pdo->query("SELECT p.id, p.name, p.status FROM products p WHERE p.status = 'active'{$catalogOnlineSql} ORDER BY p.id DESC")->fetchAll() ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = (string) ($_POST['action'] ?? 'save_flash');

        if ($action === 'save_weekly_offer') {
            $rawEnd = (string) ($_POST['weekly_offer_end_at'] ?? '');
            $rawProductIds = $_POST['weekly_offer_product_ids'] ?? [];
            $ctaUrl = trim((string) ($_POST['weekly_offer_cta_url'] ?? ''));
            $enabled = isset($_POST['weekly_offer_enabled']) ? '1' : '0';
            $hideOnExpire = isset($_POST['weekly_offer_hide_on_expire']) ? '1' : '0';

            $endValue = normalizeDatetimeLocal($rawEnd, 'Həftənin təklifi bitiş vaxtı');
            $productIdList = collectPostedProductIds($rawProductIds, 3);
            $productIdsValue = !empty($productIdList) ? implode(',', $productIdList) : null;

            if ($ctaUrl === '') {
                $ctaUrl = 'shop_page.php?sort=price_low';
            }

            upsertSetting($pdo, 'weekly_offer_enabled', $enabled);
            upsertSetting($pdo, 'weekly_offer_product_ids', $productIdsValue);
            upsertSetting($pdo, 'weekly_offer_end_at', $endValue);
            upsertSetting($pdo, 'weekly_offer_cta_url', $ctaUrl);
            upsertSetting($pdo, 'weekly_offer_hide_on_expire', $hideOnExpire);

            $message = 'Həftənin təklifi ayarları yeniləndi.';

            $settings = fetchSettings($pdo, $settingKeys);
            $currentWeeklyEnabled = (string) ($settings['weekly_offer_enabled'] ?? '1');
            $currentWeeklyProductIds = (string) ($settings['weekly_offer_product_ids'] ?? '');
            $currentWeeklyEndAt = (string) ($settings['weekly_offer_end_at'] ?? '');
            $currentWeeklyCtaUrl = (string) ($settings['weekly_offer_cta_url'] ?? 'shop_page.php?sort=price_low');
            $currentWeeklyHideOnExpire = (string) ($settings['weekly_offer_hide_on_expire'] ?? '1');
            $selectedWeeklyProductIds = parseProductIdList($currentWeeklyProductIds, 3);
        } else {
            $rawStart = (string) ($_POST['flash_start_at'] ?? '');
            $rawEnd = (string) ($_POST['flash_end_at'] ?? '');
            $rawProductIds = $_POST['flash_product_ids'] ?? [];
            $hideOnExpire = isset($_POST['flash_hide_on_expire']) ? '1' : '0';

            $startValue = normalizeDatetimeLocal($rawStart, 'Flash start time');
            $endValue = normalizeDatetimeLocal($rawEnd, 'Flash end time');

            $productIdList = collectPostedProductIds($rawProductIds);
            $productIdsValue = !empty($productIdList) ? implode(',', $productIdList) : null;
            $productIdValue = !empty($productIdList) ? (string) $productIdList[0] : null;

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

            $settings = fetchSettings($pdo, $settingKeys);
            $currentFlashStartAt = (string) ($settings['flash_start_at'] ?? '');
            $currentFlashEndAt = (string) ($settings['flash_end_at'] ?? '');
            $currentFlashProductId = (string) ($settings['flash_product_id'] ?? '');
            $currentFlashProductIds = (string) ($settings['flash_product_ids'] ?? '');
            $currentFlashHideOnExpire = (string) ($settings['flash_hide_on_expire'] ?? '1');

            $selectedFlashProductIds = parseProductIdList($currentFlashProductIds);
            if (empty($selectedFlashProductIds) && $currentFlashProductId !== '') {
                $legacyId = (int) $currentFlashProductId;
                if ($legacyId > 0) {
                    $selectedFlashProductIds = [$legacyId];
                }
            }
        }
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

$dtLocalStart = toDatetimeLocalValue($currentFlashStartAt);
$dtLocalEnd = toDatetimeLocalValue($currentFlashEndAt);
$dtWeeklyEnd = toDatetimeLocalValue($currentWeeklyEndAt);
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
                <p class="admin-subtitle">Anasəhifədəki Flash Sale və Həftənin Təklifi hissələri buradan idarə olunur.</p>
            </div>
        </header>

        <?php if ($message !== ''): ?>
            <div class="flash <?php echo $messageType === 'error' ? 'flash-error' : 'flash-success'; ?>">
                <?php echo e($message); ?>
            </div>
        <?php endif; ?>

        <section class="card">
            <h3 class="section-title">Həftənin Təklifi</h3>
            <p class="admin-subtitle" style="margin-bottom:12px;">
                Hero bannerın altında görünən 3 məhsulluq kampaniya bloku. Maksimum 3 məhsul seçin.
            </p>
            <form method="post">
                <input type="hidden" name="action" value="save_weekly_offer">
                <div class="form-grid">
                    <div style="grid-column: 1 / -1;">
                        <label style="display:flex; align-items:center; gap:8px; font-weight:500;">
                            <input type="checkbox" name="weekly_offer_enabled" value="1" style="width:auto;" <?php echo $currentWeeklyEnabled !== '0' ? 'checked' : ''; ?>>
                            Həftənin Təklifi blokunu göstər
                        </label>
                    </div>
                    <div class="flash-picker" style="grid-column: 1 / -1;">
                        <label for="weekly_offer_product_ids">Təklif məhsulları (maks. 3)</label>
                        <input
                            id="weekly_offer_product_search"
                            type="search"
                            class="flash-picker__search"
                            placeholder="Məhsul axtar: ad və ya ID yaz..."
                            autocomplete="off"
                        >
                        <select id="weekly_offer_product_ids" name="weekly_offer_product_ids[]" multiple size="10" class="flash-picker__select">
                            <?php foreach ($products as $p): ?>
                                <?php $pid = (int) $p['id']; ?>
                                <option value="<?php echo $pid; ?>" <?php echo in_array($pid, $selectedWeeklyProductIds, true) ? 'selected' : ''; ?>>
                                    #<?php echo (int) $p['id']; ?> - <?php echo e((string) $p['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="weekly_offer_product_search_empty" class="flash-picker__empty" hidden>Axtarışa uyğun məhsul tapılmadı.</div>
                        <div class="flash-picker__meta">
                            <small class="flash-picker__hint">Ctrl/Cmd ilə seç. Maksimum 3 məhsul saxlanılacaq.</small>
                            <span class="flash-picker__count" id="weekly_selected_count"></span>
                        </div>
                    </div>
                    <div>
                        <label for="weekly_offer_end_at">Təklif bitiş vaxtı</label>
                        <input id="weekly_offer_end_at" name="weekly_offer_end_at" type="datetime-local" value="<?php echo e($dtWeeklyEnd); ?>">
                    </div>
                    <div>
                        <label for="weekly_offer_cta_url">"Bütün təklifləri gör" linki</label>
                        <input id="weekly_offer_cta_url" name="weekly_offer_cta_url" type="text" value="<?php echo e($currentWeeklyCtaUrl); ?>" placeholder="shop_page.php?sort=price_low">
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label style="display:flex; align-items:center; gap:8px; font-weight:500;">
                            <input type="checkbox" name="weekly_offer_hide_on_expire" value="1" style="width:auto;" <?php echo $currentWeeklyHideOnExpire !== '0' ? 'checked' : ''; ?>>
                            Bitəndə hissəni gizlət
                        </label>
                    </div>
                </div>
                <button class="btn btn-primary" type="submit" style="margin-top:12px;">Yadda saxla</button>
            </form>
        </section>

        <section class="card">
            <h3 class="section-title">Flash Sale countdown</h3>
            <form method="post">
                <input type="hidden" name="action" value="save_flash">
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
    const bindProductPicker = ({ selectId, searchId, emptyId, counterId, maxSelected = 0 }) => {
        const select = document.getElementById(selectId);
        const search = document.getElementById(searchId);
        const emptyState = document.getElementById(emptyId);
        const counter = document.getElementById(counterId);
        if (!select || !counter) return;

        const syncCount = () => {
            const selected = Array.from(select.selectedOptions).length;
            if (maxSelected > 0) {
                counter.textContent = selected > 0
                    ? `${selected} / ${maxSelected} məhsul seçilib`
                    : `Maksimum ${maxSelected} məhsul`;
                return;
            }
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

        if (maxSelected > 0) {
            select.addEventListener('change', () => {
                const selected = Array.from(select.selectedOptions);
                if (selected.length > maxSelected) {
                    selected.slice(maxSelected).forEach((opt) => {
                        opt.selected = false;
                    });
                }
                syncCount();
            });
        } else {
            select.addEventListener('change', syncCount);
        }

        if (search) {
            search.addEventListener('input', applyFilter);
        }
        applyFilter();
        syncCount();
    };

    bindProductPicker({
        selectId: 'weekly_offer_product_ids',
        searchId: 'weekly_offer_product_search',
        emptyId: 'weekly_offer_product_search_empty',
        counterId: 'weekly_selected_count',
        maxSelected: 3,
    });

    bindProductPicker({
        selectId: 'flash_product_ids',
        searchId: 'flash_product_search',
        emptyId: 'flash_product_search_empty',
        counterId: 'flash_selected_count',
    });
})();
</script>
</body>
</html>
