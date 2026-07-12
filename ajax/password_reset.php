<?php
declare(strict_types=1);

require_once __DIR__ . '/../user_auth.php';
require_once __DIR__ . '/../includes/password_reset.php';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Yalniz POST sorğusu qebul olunur.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = trim((string) ($_POST['action'] ?? ''));
$pdo = db();

try {
    if ($action === 'send_code') {
        $result = maxhomePasswordResetSendCode($pdo, (string) ($_POST['email'] ?? ''));
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'verify_code') {
        $result = maxhomePasswordResetVerifyCode(
            $pdo,
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['code'] ?? '')
        );
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'reset_password') {
        $result = maxhomePasswordResetUpdatePassword(
            $pdo,
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['password_confirm'] ?? '')
        );
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Naməlum əməliyyat.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Sistem xətası baş verdi.'], JSON_UNESCAPED_UNICODE);
}
