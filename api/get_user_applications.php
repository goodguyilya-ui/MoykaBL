<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_method('GET');

$userId = (int)($_GET['user_id'] ?? 0);
if ($userId <= 0) {
    json_response(['error' => 'Не указан user_id.'], 400);
}

$pdo = get_pdo();
$stmt = $pdo->prepare(
    'SELECT
       a.id,
       c.name AS course_name,
       a.car_model,
       a.visit_date,
       a.visit_time,
       pm.name AS payment_method,
       st.name AS status
     FROM applications a
     INNER JOIN courses c ON c.id = a.course_id
     INNER JOIN payment_methods pm ON pm.id = a.payment_method_id
     INNER JOIN application_statuses st ON st.id = a.status_id
     WHERE a.user_id = :user_id
     ORDER BY a.id DESC'
);
$stmt->execute(['user_id' => $userId]);

json_response([
    'ok' => true,
    'items' => $stmt->fetchAll(),
]);
