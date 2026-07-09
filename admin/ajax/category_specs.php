<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin_auth.php';
require_once __DIR__ . '/../../db.php';

requireAdminAuth();

header('Content-Type: application/json; charset=utf-8');

$categoryId = (int) ($_GET['category_id'] ?? 0);
if ($categoryId <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Yanlis category_id'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = db();
    $excludedSpecKeys = [
        'screen_size_inch',
        'storage_gb',
        'sp_touch_sampling_hz',
        'sp_gorilla_glass',
    ];
    $excludedPlaceholders = implode(',', array_fill(0, count($excludedSpecKeys), '?'));
    $categoryIdsToTry = [];
    $seenCategoryIds = [];
    $currentCategoryId = $categoryId;
    $parentStmt = $pdo->prepare(
        "SELECT parent_id
         FROM categories
         WHERE id = ?
         LIMIT 1"
    );
    while ($currentCategoryId > 0 && !isset($seenCategoryIds[$currentCategoryId])) {
        $categoryIdsToTry[] = $currentCategoryId;
        $seenCategoryIds[$currentCategoryId] = true;

        $parentStmt->execute([$currentCategoryId]);
        $parentId = (int) ($parentStmt->fetchColumn() ?: 0);
        $currentCategoryId = $parentId;
    }

    $stmt = $pdo->prepare(
        "SELECT
            sd.spec_key,
            sd.label,
            sd.input_type,
            sd.unit,
            sd.options_json,
            csm.is_required,
            csm.sort_order
         FROM category_spec_map csm
         INNER JOIN spec_definitions sd ON sd.id = csm.spec_definition_id
         WHERE csm.category_id = ?
           AND sd.is_active = 1
           AND sd.spec_key NOT IN (" . $excludedPlaceholders . ")
         ORDER BY csm.sort_order ASC, sd.sort_order ASC, sd.label ASC"
    );
    $rows = [];
    foreach ($categoryIdsToTry as $categoryIdCandidate) {
        $stmt->execute(array_merge([$categoryIdCandidate], $excludedSpecKeys));
        $rows = $stmt->fetchAll() ?: [];
        if (!empty($rows)) {
            break;
        }
    }

    $specs = array_map(static function (array $row): array {
        $options = [];
        if (!empty($row['options_json'])) {
            $decoded = json_decode((string) $row['options_json'], true);
            if (is_array($decoded)) {
                $options = array_values(array_filter($decoded, static fn ($item) => is_scalar($item)));
            }
        }

        return [
            'spec_key' => (string) $row['spec_key'],
            'label' => (string) $row['label'],
            'input_type' => (string) $row['input_type'],
            'unit' => $row['unit'] !== null ? (string) $row['unit'] : null,
            'options' => $options,
            'is_required' => (int) $row['is_required'] === 1,
            'sort_order' => (int) $row['sort_order'],
        ];
    }, $rows);

    $extraTemplates = array_map(
        static fn (string $label): array => ['label' => $label],
        maxhomeFetchCategoryExtraSpecTemplateLabels($pdo, $categoryId)
    );

    echo json_encode(
        ['ok' => true, 'specs' => $specs, 'extra_templates' => $extraTemplates],
        JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Spec siyahisi yuklenmedi'], JSON_UNESCAPED_UNICODE);
}
