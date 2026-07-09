<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim((string) ($_GET['q'] ?? ''));

$qLen = function_exists('mb_strlen') ? mb_strlen($q, 'UTF-8') : strlen($q);
if ($qLen < 2) {
    echo json_encode([
        'ok' => true,
        'faqs' => [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = db();
    $like = '%' . $q . '%';
    $sql = "SELECT id, question, answer
            FROM support_faqs
            WHERE is_active = 1
              AND (question LIKE :q_question OR answer LIKE :q_answer)
            ORDER BY
              CASE WHEN question LIKE :q_start THEN 0 ELSE 1 END,
              sort_order ASC,
              id ASC
            LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'q_question' => $like,
        'q_answer' => $like,
        'q_start' => $q . '%',
    ]);
    $rows = $stmt->fetchAll() ?: [];

    $faqs = array_map(static function (array $row): array {
        $answer = trim((string) $row['answer']);
        $excerpt = $answer;
        if (function_exists('mb_strimwidth')) {
            $excerpt = mb_strimwidth($answer, 0, 120, '...');
        } elseif (strlen($answer) > 120) {
            $excerpt = substr($answer, 0, 117) . '...';
        }

        return [
            'id' => (int) $row['id'],
            'question' => (string) $row['question'],
            'excerpt' => $excerpt,
        ];
    }, $rows);

    echo json_encode([
        'ok' => true,
        'faqs' => $faqs,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $errorCode = (string) $e->getCode();
    if ($errorCode === '42S02') {
        // support_faqs table has not been migrated yet.
        echo json_encode([
            'ok' => true,
            'faqs' => [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Server xetasi',
        'faqs' => [],
    ], JSON_UNESCAPED_UNICODE);
}
