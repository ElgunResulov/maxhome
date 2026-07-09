<?php
declare(strict_types=1);
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/admin_layout.php';

requireAdminAuth();
$pdo = db();
$message = '';
$messageType = 'success';

$bundleTableExists = productBundleItemsTableExists($pdo);
$parentId = (int) ($_GET['parent'] ?? 0);
$listQ = trim((string) ($_GET['q'] ?? ''));

if (isset($_GET['saved']) && $_GET['saved'] === '1') {
    $message = 'Ekosistem siyahisi yadda saxlanildi.';
}

/**
 * @param mixed $rawBundleField
 * @return list<int>
 */
function adminParseBundleProductIds(mixed $rawBundleField, int $parentId): array
{
    $bundleIds = [];
    if (is_array($rawBundleField)) {
        foreach ($rawBundleField as $part) {
            $bid = (int) $part;
            if ($bid > 0 && $bid !== $parentId) {
                $bundleIds[] = $bid;
            }
        }
        $bundleIds = array_values(array_unique($bundleIds));
    } else {
        $rawBundle = trim((string) (is_scalar($rawBundleField) ? (string) $rawBundleField : ''));
        $parts = $rawBundle === '' ? [] : preg_split('/[\s,;]+/', $rawBundle, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($parts as $part) {
            if (ctype_digit((string) $part)) {
                $bid = (int) $part;
                if ($bid > 0 && $bid !== $parentId) {
                    $bundleIds[] = $bid;
                }
            }
        }
        $bundleIds = array_values(array_unique($bundleIds));
    }

    return $bundleIds;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_ecosystem') {
    try {
        if (!$bundleTableExists) {
            throw new RuntimeException('product_bundle_items cedveli yoxdur; migrasiya ishledin.');
        }
        $pid = (int) ($_POST['parent_id'] ?? 0);
        if ($pid <= 0) {
            throw new RuntimeException('Ana mehsul secilmeyib.');
        }
        $parentStmt = $pdo->prepare('SELECT id FROM products WHERE id = :id LIMIT 1');
        $parentStmt->execute(['id' => $pid]);
        if (!$parentStmt->fetch()) {
            throw new RuntimeException('Ana mehsul tapilmadi.');
        }

        $bundleIds = adminParseBundleProductIds($_POST['bundle_product_ids'] ?? null, $pid);
        if (count($bundleIds) > 20) {
            throw new RuntimeException('Ekosistem siyahisinda 20-den cox mehsul ID ola bilmez.');
        }
        foreach ($bundleIds as $bid) {
            $existsStmt = $pdo->prepare(
                "SELECT pr.id FROM products pr WHERE pr.id = :id AND pr.status = 'active'" . maxhomeProductsOnlineVisibleSql($pdo, 'pr') . " LIMIT 1"
            );
            $existsStmt->execute(['id' => $bid]);
            if (!$existsStmt->fetch()) {
                throw new RuntimeException('Ekosistem siyahisinda movcud olmayan ve ya aktiv olmayan ID: ' . $bid);
            }
        }

        $pdo->prepare('DELETE FROM product_bundle_items WHERE parent_product_id = :pid')
            ->execute(['pid' => $pid]);
        if (!empty($bundleIds)) {
            $insBundle = $pdo->prepare(
                'INSERT INTO product_bundle_items (parent_product_id, bundle_product_id, sort_order)
                 VALUES (:parent_id, :bundle_id, :sort_order)'
            );
            foreach ($bundleIds as $sort => $bid) {
                $insBundle->execute([
                    'parent_id' => $pid,
                    'bundle_id' => $bid,
                    'sort_order' => $sort,
                ]);
            }
        }

        header('Location: admin_ecosystem.php?parent=' . $pid . '&saved=1');
        exit;
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

$editingParent = null;
$editingBundleProducts = [];
if ($parentId > 0 && $bundleTableExists) {
    $pStmt = $pdo->prepare(
        'SELECT id, name, sku, slug, status FROM products WHERE id = :id LIMIT 1'
    );
    $pStmt->execute(['id' => $parentId]);
    $editingParent = $pStmt->fetch() ?: null;
    if (!$editingParent) {
        $message = 'Mehsul tapilmadi (ID ' . $parentId . ').';
        $messageType = 'error';
        $parentId = 0;
    } else {
        $bundleListStmt = $pdo->prepare(
            'SELECT p.id, p.name, p.sku, p.base_price, p.status
             FROM product_bundle_items pbi
             INNER JOIN products p ON p.id = pbi.bundle_product_id
             WHERE pbi.parent_product_id = :pid
             ORDER BY pbi.sort_order ASC, pbi.id ASC'
        );
        $bundleListStmt->execute(['pid' => $parentId]);
        foreach ($bundleListStmt->fetchAll() ?: [] as $br) {
            $editingBundleProducts[] = [
                'id' => (int) $br['id'],
                'name' => (string) $br['name'],
                'sku' => (string) $br['sku'],
                'base_price' => (float) $br['base_price'],
                'status' => (string) $br['status'],
            ];
        }
    }
}

$listRows = [];
if ($parentId <= 0) {
    $sql = "SELECT p.id, p.name, p.sku, p.status,
            (SELECT COUNT(*) FROM product_bundle_items pbi WHERE pbi.parent_product_id = p.id) AS bundle_count
            FROM products p";
    $params = [];
    if ($listQ !== '') {
        $sql .= ' WHERE p.name LIKE :q OR p.sku LIKE :q OR CAST(p.id AS CHAR) = :exact';
        $params['q'] = '%' . $listQ . '%';
        $params['exact'] = $listQ;
    }
    $sql .= ' ORDER BY p.id DESC LIMIT 120';
    $listStmt = $pdo->prepare($sql);
    $listStmt->execute($params);
    $listRows = $listStmt->fetchAll() ?: [];
}

$storeEcosystemUrl = '';
if ($editingParent) {
    $slug = trim((string) ($editingParent['slug'] ?? ''));
    $storeEcosystemUrl = $slug !== ''
        ? '../product_ecosystem.php?slug=' . rawurlencode($slug)
        : '../product_ecosystem.php?id=' . (int) $editingParent['id'];
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Ekosistem paketleri</title>
    <link rel="stylesheet" href="../assets/css/foundation.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <?php adminSidebar('ecosystem'); ?>
    <main class="admin-main">
        <header class="admin-top">
            <div>
                <h1 class="admin-title">Ekosistem paketleri</h1>
                <p class="admin-subtitle">Her ana mehsul ucun magazada «+» ile gosterilen tamamlayici mehsullar. Mehsul formundan da redakte etmek olar.</p>
            </div>
            <?php if ($parentId > 0): ?>
                <a class="btn btn-muted" href="admin_ecosystem.php<?php echo $listQ !== '' ? '?q=' . urlencode($listQ) : ''; ?>">Siyahiya qayıt</a>
            <?php endif; ?>
        </header>

        <?php if ($message !== '' && !isset($_GET['saved'])): ?>
            <div class="flash <?php echo $messageType === 'error' ? 'flash-error' : 'flash-success'; ?>">
                <?php echo e($message); ?>
            </div>
        <?php elseif (isset($_GET['saved']) && $_GET['saved'] === '1'): ?>
            <div class="flash flash-success">
                <?php echo e($message !== '' ? $message : 'Ekosistem siyahisi yadda saxlanildi.'); ?>
            </div>
        <?php endif; ?>

        <?php if (!$bundleTableExists): ?>
            <section class="card">
                <h3 class="section-title">Verilenler bazasi</h3>
                <p class="spec-help"><code>product_bundle_items</code> cedveli tapilmadi. <code>database/migration_product_bundle_items.sql</code> faylini ishledin.</p>
            </section>
        <?php elseif ($editingParent): ?>
            <section class="card" style="margin-bottom: 14px;">
                <h3 class="section-title">Ana məhsul: <?php echo e((string) $editingParent['name']); ?> <span class="spec-help">(DB ID <?php echo (int) $editingParent['id']; ?>)</span></h3>
                <p class="spec-help" style="margin-bottom: 12px;">
                    <a href="admin_products.php?edit=<?php echo (int) $editingParent['id']; ?>">Tam məhsul redaktəsi (qiymət, şəkil, xüsusiyyətlər)</a>
                    <?php if ($storeEcosystemUrl !== ''): ?>
                        · <a href="<?php echo e($storeEcosystemUrl); ?>" target="_blank" rel="noopener">Mağaza: ekosistem səhifəsi</a>
                        · <a href="../product_details.php<?php echo !empty($editingParent['slug']) ? '?slug=' . urlencode((string) $editingParent['slug']) : '?id=' . (int) $editingParent['id']; ?>" target="_blank" rel="noopener">Məhsul detalı</a>
                    <?php endif; ?>
                </p>
                <form method="post">
                    <input type="hidden" name="action" value="save_ecosystem">
                    <input type="hidden" name="parent_id" value="<?php echo (int) $editingParent['id']; ?>">
                    <div class="bundle-picker" id="bundle-picker" data-exclude-id="<?php echo (int) $editingParent['id']; ?>">
                        <label class="bundle-picker__label">Tamamlayıcı məhsullar (ekosistem)</label>
                        <p class="spec-help bundle-picker__hint">Axtarış və ya ID ilə əlavə edin. Sıra mağazada «tam siyahı» səhifəsində saxlanılır; məhsul detalında çoxlu olduqda sıra təsadüfi göstərilə bilər.</p>
                        <div class="bundle-picker__search-wrap">
                            <input type="search" id="bundle-search-q" class="bundle-picker__search" placeholder="Ad və ya SKU (min. 2 simvol)..." autocomplete="off" />
                            <div id="bundle-search-results" class="bundle-picker__results" hidden></div>
                        </div>
                        <ul id="bundle-selected-list" class="bundle-selected-list" aria-label="Seçilmiş ekosistem məhsulları"></ul>
                        <div id="bundle-hidden-inputs"></div>
                        <details class="bundle-picker__advanced">
                            <summary>ID siyahısı ilə sürətli əlavə</summary>
                            <p class="spec-help">Nümunə: <code>12, 15, 18</code> və ya hər sətirdə bir ID. Yalnız <strong>active</strong> məhsullar saxlanılır.</p>
                            <textarea id="bundle-bulk-ids" class="bundle-picker__bulk" rows="3" placeholder="12, 15, 18"></textarea>
                            <button type="button" class="btn btn-muted bundle-picker__bulk-btn" id="bundle-bulk-apply">Siyahıya əlavə et</button>
                            <span id="bundle-bulk-msg" class="bundle-picker__bulk-msg" role="status"></span>
                        </details>
                    </div>
                    <div class="stack-sm" style="margin-top:14px;">
                        <button class="btn btn-primary" type="submit">Yadda saxla</button>
                        <a class="btn btn-muted" href="admin_ecosystem.php<?php echo $listQ !== '' ? '?q=' . urlencode($listQ) : ''; ?>">Ləğv et</a>
                    </div>
                </form>
                <script type="application/json" id="bundle-picker-initial"><?php
                    echo json_encode(
                        $editingBundleProducts,
                        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                    );
                ?></script>
            </section>
        <?php else: ?>
            <section class="card">
                <h3 class="section-title">Ana məhsul seçin</h3>
                <form method="get" style="display:flex; gap:10px; margin-bottom: 12px; flex-wrap: wrap;">
                    <input name="q" value="<?php echo e($listQ); ?>" placeholder="Ad, SKU və ya ID ilə axtar..." style="min-width: 220px; flex: 1;">
                    <button class="btn btn-muted" type="submit">Axtar</button>
                    <a class="btn btn-muted" href="admin_ecosystem.php">Təmizlə</a>
                </form>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>№</th>
                            <th>ID</th>
                            <th>Ad</th>
                            <th>SKU</th>
                            <th>Status</th>
                            <th>Ekosistem sayı</th>
                            <th>Əməliyyat</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($listRows)): ?>
                            <tr><td colspan="7">Məhsul tapılmadı.</td></tr>
                        <?php else: ?>
                            <?php foreach ($listRows as $idx => $row): ?>
                                <tr>
                                    <td><?php echo $idx + 1; ?></td>
                                    <td><?php echo (int) $row['id']; ?></td>
                                    <td><?php echo e((string) $row['name']); ?></td>
                                    <td><code><?php echo e((string) $row['sku']); ?></code></td>
                                    <td><span class="status-badge status-<?php echo e((string) $row['status']); ?>"><?php echo e((string) $row['status']); ?></span></td>
                                    <td><?php echo (int) $row['bundle_count']; ?></td>
                                    <td>
                                        <a class="btn btn-primary" style="padding:6px 12px;font-size:13px;" href="admin_ecosystem.php?parent=<?php echo (int) $row['id']; ?><?php echo $listQ !== '' ? '&q=' . urlencode($listQ) : ''; ?>">Ekosistemi idarə et</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </main>
</div>
<?php if ($bundleTableExists && $editingParent): ?>
<script>
(() => {
    const root = document.getElementById('bundle-picker');
    if (!root) return;

    const excludeId = parseInt(root.dataset.excludeId || '0', 10) || 0;
    const initialEl = document.getElementById('bundle-picker-initial');
    let selected = [];
    if (initialEl && initialEl.textContent.trim()) {
        try {
            selected = JSON.parse(initialEl.textContent);
        } catch (e) {
            selected = [];
        }
    }
    if (!Array.isArray(selected)) {
        selected = [];
    }

    const searchInput = document.getElementById('bundle-search-q');
    const resultsEl = document.getElementById('bundle-search-results');
    const listEl = document.getElementById('bundle-selected-list');
    const hiddenWrap = document.getElementById('bundle-hidden-inputs');
    const bulkTa = document.getElementById('bundle-bulk-ids');
    const bulkBtn = document.getElementById('bundle-bulk-apply');
    const bulkMsg = document.getElementById('bundle-bulk-msg');
    let searchTimer = null;
    const MAX = 20;

    function syncHidden() {
        hiddenWrap.innerHTML = '';
        selected.forEach((p) => {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'bundle_product_ids[]';
            inp.value = String(p.id);
            hiddenWrap.appendChild(inp);
        });
    }

    function fmtMoney(n) {
        return new Intl.NumberFormat('az-AZ', { style: 'currency', currency: 'AZN', minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n) || 0);
    }

    function renderList() {
        listEl.innerHTML = '';
        if (selected.length === 0) {
            const li = document.createElement('li');
            li.className = 'bundle-picker-empty';
            li.textContent = 'Hələ əlavə məhsul seçilməyib. Yuxarıdan ad və ya SKU ilə axtarın.';
            listEl.appendChild(li);
            syncHidden();
            return;
        }

        selected.forEach((p, idx) => {
            const li = document.createElement('li');
            li.className = 'bundle-selected-row';

            const info = document.createElement('div');
            info.className = 'bundle-selected-row__info';
            const title = document.createElement('strong');
            title.textContent = p.name;
            const meta = document.createElement('div');
            meta.className = 'bundle-selected-row__meta';
            meta.textContent = 'ID ' + p.id + ' · ' + p.sku + ' · ' + fmtMoney(p.base_price);
            info.appendChild(title);
            info.appendChild(meta);
            li.appendChild(info);

            const actions = document.createElement('div');
            actions.className = 'bundle-selected-row__actions';

            const up = document.createElement('button');
            up.type = 'button';
            up.className = 'btn btn-muted bundle-selected-row__btn';
            up.textContent = 'Yuxarı';
            up.disabled = idx === 0;
            up.addEventListener('click', () => {
                if (idx <= 0) return;
                const t = selected[idx - 1];
                selected[idx - 1] = selected[idx];
                selected[idx] = t;
                renderList();
            });

            const down = document.createElement('button');
            down.type = 'button';
            down.className = 'btn btn-muted bundle-selected-row__btn';
            down.textContent = 'Aşağı';
            down.disabled = idx === selected.length - 1;
            down.addEventListener('click', () => {
                if (idx >= selected.length - 1) return;
                const t = selected[idx + 1];
                selected[idx + 1] = selected[idx];
                selected[idx] = t;
                renderList();
            });

            const rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'btn btn-danger bundle-selected-row__btn';
            rm.textContent = 'Çıxar';
            rm.addEventListener('click', () => {
                selected.splice(idx, 1);
                renderList();
            });

            actions.appendChild(up);
            actions.appendChild(down);
            actions.appendChild(rm);
            li.appendChild(actions);
            listEl.appendChild(li);
        });
        syncHidden();
    }

    function addProduct(p) {
        if (!p || p.id === excludeId) return false;
        if (selected.some((x) => x.id === p.id)) return false;
        if (selected.length >= MAX) {
            window.alert('Ekosistem siyahısında ən çox ' + MAX + ' məhsul ola bilər.');
            return false;
        }
        selected.push({
            id: p.id,
            name: p.name,
            sku: p.sku,
            base_price: p.base_price,
            status: p.status,
        });
        renderList();
        return true;
    }

    async function runSearch(q) {
        const url = 'ajax/product_search.php?q=' + encodeURIComponent(q) + '&exclude_id=' + excludeId;
        const r = await fetch(url, { credentials: 'same-origin' });
        const data = await r.json().catch(() => null);
        if (!r.ok) {
            throw new Error((data && data.message) ? data.message : ('HTTP ' + r.status));
        }
        if (!data || typeof data !== 'object') {
            throw new Error('Server cavabi JSON deyil');
        }
        if (!data.ok) throw new Error(data.message || 'Xəta');
        return data.products || [];
    }

    async function runIds(idsStr) {
        const r = await fetch('ajax/product_search.php?ids=' + encodeURIComponent(idsStr) + '&exclude_id=' + excludeId, { credentials: 'same-origin' });
        const data = await r.json().catch(() => null);
        if (!r.ok) {
            throw new Error((data && data.message) ? data.message : ('HTTP ' + r.status));
        }
        if (!data || typeof data !== 'object') {
            throw new Error('Server cavabi JSON deyil');
        }
        if (!data.ok) throw new Error(data.message || 'Xəta');
        return data;
    }

    function showResults(products) {
        resultsEl.innerHTML = '';
        if (!products.length) {
            const empty = document.createElement('div');
            empty.className = 'bundle-picker__result-empty';
            empty.textContent = 'Nəticə tapılmadı';
            resultsEl.appendChild(empty);
            resultsEl.hidden = false;
            return;
        }
        products.forEach((p) => {
            const row = document.createElement('button');
            row.type = 'button';
            row.className = 'bundle-picker__result-row';
            const already = selected.some((x) => x.id === p.id);
            if (already) {
                row.classList.add('is-disabled');
            }
            const nameSpan = document.createElement('span');
            nameSpan.className = 'bundle-picker__result-name';
            nameSpan.textContent = p.name;
            const metaSpan = document.createElement('span');
            metaSpan.className = 'bundle-picker__result-meta';
            metaSpan.textContent = p.sku + ' · ' + fmtMoney(p.base_price);
            row.appendChild(nameSpan);
            row.appendChild(metaSpan);
            row.addEventListener('click', () => {
                if (already) return;
                if (addProduct(p)) {
                    resultsEl.hidden = true;
                    searchInput.value = '';
                }
            });
            resultsEl.appendChild(row);
        });
        resultsEl.hidden = false;
    }

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            const q = searchInput.value.trim();
            if (q.length < 2) {
                resultsEl.hidden = true;
                resultsEl.innerHTML = '';
                return;
            }
            searchTimer = setTimeout(async () => {
                try {
                    const products = await runSearch(q);
                    showResults(products);
                } catch (err) {
                    resultsEl.innerHTML = '';
                    const errDiv = document.createElement('div');
                    errDiv.className = 'bundle-picker__result-empty';
                    errDiv.textContent = 'Axtarış yüklənmədi: ' + ((err && err.message) ? err.message : 'bilinmeyen xeta');
                    resultsEl.appendChild(errDiv);
                    resultsEl.hidden = false;
                }
            }, 300);
        });

        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                resultsEl.hidden = true;
            }
        });
    }

    document.addEventListener('click', (e) => {
        if (!root.contains(e.target)) {
            resultsEl.hidden = true;
        }
    });

    if (bulkBtn && bulkTa && bulkMsg) {
        bulkBtn.addEventListener('click', async () => {
            bulkMsg.textContent = '';
            const raw = bulkTa.value.trim();
            if (!raw) return;
            try {
                const data = await runIds(raw);
                let added = 0;
                for (const p of data.products || []) {
                    if (p.id === excludeId) continue;
                    if (selected.some((x) => x.id === p.id)) continue;
                    if (selected.length >= MAX) {
                        bulkMsg.textContent = 'Limit: ən çox ' + MAX + ' məhsul. ';
                        break;
                    }
                    selected.push({
                        id: p.id,
                        name: p.name,
                        sku: p.sku,
                        base_price: p.base_price,
                        status: p.status,
                    });
                    added += 1;
                }
                renderList();
                const parts = [];
                if (data.missing && data.missing.length) {
                    parts.push('Tapılmadı və ya aktiv deyil: ' + data.missing.join(', '));
                }
                if (added) {
                    parts.push(added + ' məhsul əlavə edildi');
                }
                if (!added && !(data.missing && data.missing.length)) {
                    parts.push('Yeni əlavə ediləcək məhsul yoxdur (təkrar və ya boş).');
                }
                bulkMsg.textContent = parts.join(' · ');
                bulkTa.value = '';
            } catch (err) {
                bulkMsg.textContent = 'Yükləmə xətası';
            }
        });
    }

    renderList();
})();
</script>
<?php endif; ?>
</body>
</html>
