<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_method('POST');

$data = read_json_body();
$userId = (int)($data['user_id'] ?? 0);
$text = field($data, 'text');

if ($userId <= 0 || $text === '') {
    json_response(['error' => 'Некорректные данные отзыва.'], 400);
}

$pdo = get_pdo();
$permissionStmt = $pdo->prepare(
    "SELECT COUNT(*) AS completed_count
     FROM applications a
     INNER JOIN application_statuses st ON st.id = a.status_id
     WHERE a.user_id = :user_id
       AND st.name = 'Обучение завершено'"
);
$permissionStmt->execute(['user_id' => $userId]);
$completedCount = (int)($permissionStmt->fetch()['completed_count'] ?? 0);
if ($completedCount <= 0) {
    json_response(['error' => 'Отзыв доступен только после статуса \"Обучение завершено\".'], 403);
}

$stmt = $pdo->prepare('INSERT INTO reviews (user_id, text) VALUES (:user_id, :text)');
$stmt->execute([
    'user_id' => $userId,
    'text' => $text,
]);

json_response(['ok' => true, 'message' => 'Отзыв отправлен.']);
