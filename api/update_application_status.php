<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_method('POST');

$data = read_json_body();
$applicationId = (int)($data['application_id'] ?? 0);
$statusName = field($data, 'status');

if ($applicationId <= 0 || $statusName === '') {
    json_response(['error' => 'Некорректные данные обновления статуса.'], 400);
}

$pdo = get_pdo();
$statusStmt = $pdo->prepare('SELECT id FROM application_statuses WHERE name = :name LIMIT 1');
$statusStmt->execute(['name' => $statusName]);
$statusId = (int)($statusStmt->fetch()['id'] ?? 0);
if ($statusId <= 0) {
    json_response(['error' => 'Неизвестный статус.'], 400);
}

$update = $pdo->prepare('UPDATE applications SET status_id = :status_id WHERE id = :id');
$update->execute([
    'status_id' => $statusId,
    'id' => $applicationId,
]);

json_response(['ok' => true]);
