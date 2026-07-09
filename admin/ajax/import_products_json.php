<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin_auth.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/product_json_import.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'message' => 'Admin sessiyasi bitib. Yeniden login olun.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$step = trim((string) ($_POST['step'] ?? $_GET['step'] ?? ''));

try {
    $pdo = db();

    if ($step === 'start') {
        if (!isset($_FILES['products_json']) || !is_array($_FILES['products_json'])) {
            throw new RuntimeException('JSON fayli servere catmadi. Fayli yeniden secib cehd edin.');
        }

        $uploadError = (int) ($_FILES['products_json']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('JSON fayli secin.');
        }
        if ($uploadError !== UPLOAD_ERR_OK) {
            throw new RuntimeException('JSON fayli yuklenmedi (xeta kodu: ' . $uploadError . ').');
        }

        $settings = [
            'default_category_id' => (int) ($_POST['import_category_id'] ?? 0),
            'import_limit' => max(0, (int) ($_POST['import_limit'] ?? 0)),
            'batch_size' => max(1, min(50, (int) ($_POST['batch_size'] ?? PRODUCT_JSON_IMPORT_BATCH_SIZE))),
            'category_create' => productJsonImportCategoryCreateSettingsFromPost($_POST),
        ];

        $job = productJsonImportCreateJob($_FILES['products_json'], $settings);
        echo json_encode([
            'ok' => true,
            'step' => 'start',
            'job_id' => $job['job_id'],
            'total' => $job['total'],
            'batch_count' => $job['batch_count'],
            'batch_size' => $job['batch_size'],
            'source' => $job['source'] ?? 'json',
            'message' => 'Fayl parse edildi. ' . $job['total'] . ' mehsul, ' . $job['batch_count'] . ' addim.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($step === 'start_api') {
        $settings = [
            'default_category_id' => (int) ($_POST['import_category_id'] ?? 0),
            'import_limit' => max(0, (int) ($_POST['import_limit'] ?? 0)),
            'batch_size' => max(1, min(50, (int) ($_POST['batch_size'] ?? PRODUCT_JSON_IMPORT_BATCH_SIZE))),
            'category_create' => productJsonImportCategoryCreateSettingsFromPost($_POST),
            'api_url' => trim((string) ($_POST['api_url'] ?? '')),
            'api_token' => trim((string) ($_POST['api_token'] ?? '')),
        ];

        $job = productJsonImportCreateJobFromApi($pdo, $settings);
        echo json_encode([
            'ok' => true,
            'step' => 'start_api',
            'job_id' => $job['job_id'],
            'total' => $job['total'],
            'batch_count' => $job['batch_count'],
            'batch_size' => $job['batch_size'],
            'source' => $job['source'] ?? 'api',
            'message' => 'API-dan ' . $job['total'] . ' mehsul cekildi. ' . $job['batch_count'] . ' addim.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($step === 'batch') {
        $jobId = trim((string) ($_POST['job_id'] ?? ''));
        if ($jobId === '') {
            throw new RuntimeException('Job ID tapilmadi.');
        }

        $result = productJsonImportProcessNextBatch($pdo, $jobId);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($step === 'cancel') {
        $jobId = trim((string) ($_POST['job_id'] ?? ''));
        if ($jobId !== '') {
            productJsonImportCancelJob($jobId);
        }
        echo json_encode([
            'ok' => true,
            'message' => 'Import dayandirildi.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    throw new RuntimeException('Naməlum addim.');
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
