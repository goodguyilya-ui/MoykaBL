<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_method('GET');

$pdo = get_pdo();
$stmt = $pdo->query(
    'SELECT
       a.id,
       a.user_id,
       s.name AS service_name,
       a.car_model,
       a.visit_date,
       a.visit_time,
       st.name AS status
     FROM applications a
     INNER JOIN services s ON s.id = a.service_id
     INNER JOIN application_statuses st ON st.id = a.status_id
     ORDER BY a.id DESC'
);

json_response([
    'ok' => true,
    'items' => $stmt->fetchAll(),
]);
