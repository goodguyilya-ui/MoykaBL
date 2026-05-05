<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_method('POST');

$data = read_json_body();
$userId = (int)($data['user_id'] ?? 0);
$serviceName = field($data, 'service_name');
$carModel = field($data, 'car_model');
$visitDate = field($data, 'visit_date');
$visitTime = field($data, 'visit_time');
$paymentMethod = field($data, 'payment_method');

if ($userId <= 0 || $serviceName === '' || $carModel === '' || $visitDate === '' || $visitTime === '' || $paymentMethod === '') {
    json_response(['error' => 'Заполните все поля заявки.'], 400);
}

$pdo = get_pdo();

$serviceStmt = $pdo->prepare('SELECT id FROM services WHERE name = :name LIMIT 1');
$serviceStmt->execute(['name' => $serviceName]);
$serviceId = (int)($serviceStmt->fetch()['id'] ?? 0);

$paymentStmt = $pdo->prepare('SELECT id FROM payment_methods WHERE name = :name LIMIT 1');
$paymentStmt->execute(['name' => $paymentMethod]);
$paymentId = (int)($paymentStmt->fetch()['id'] ?? 0);

$statusStmt = $pdo->query("SELECT id FROM application_statuses WHERE name = 'Новая' LIMIT 1");
$statusId = (int)($statusStmt->fetch()['id'] ?? 0);

if ($serviceId <= 0 || $paymentId <= 0 || $statusId <= 0) {
    json_response(['error' => 'Ошибка справочников БД (услуги/оплата/статусы).'], 500);
}

$insert = $pdo->prepare(
    'INSERT INTO applications (user_id, service_id, car_model, visit_date, visit_time, payment_method_id, status_id)
     VALUES (:user_id, :service_id, :car_model, :visit_date, :visit_time, :payment_method_id, :status_id)'
);
$insert->execute([
    'user_id' => $userId,
    'service_id' => $serviceId,
    'car_model' => $carModel,
    'visit_date' => $visitDate,
    'visit_time' => $visitTime,
    'payment_method_id' => $paymentId,
    'status_id' => $statusId,
]);

json_response(['ok' => true, 'message' => 'Заявка создана.']);
