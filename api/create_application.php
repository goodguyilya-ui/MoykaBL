<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_method('POST');

$data = read_json_body();
$userId = (int)($data['user_id'] ?? 0);
$courseName = field($data, 'course_name');
$carModel = field($data, 'car_model');
$visitDate = field($data, 'visit_date');
$visitTime = field($data, 'visit_time');
$paymentMethod = field($data, 'payment_method');

if ($userId <= 0 || $courseName === '' || $carModel === '' || $visitDate === '' || $visitTime === '' || $paymentMethod === '') {
    json_response(['error' => 'Заполните все поля заявки.'], 400);
}

$pdo = get_pdo();

$courseStmt = $pdo->prepare('SELECT id FROM courses WHERE name = :name LIMIT 1');
$courseStmt->execute(['name' => $courseName]);
$courseId = (int)($courseStmt->fetch()['id'] ?? 0);

$paymentStmt = $pdo->prepare('SELECT id FROM payment_methods WHERE name = :name LIMIT 1');
$paymentStmt->execute(['name' => $paymentMethod]);
$paymentId = (int)($paymentStmt->fetch()['id'] ?? 0);

$statusStmt = $pdo->query("SELECT id FROM application_statuses WHERE name = 'Новая' LIMIT 1");
$statusId = (int)($statusStmt->fetch()['id'] ?? 0);

if ($courseId <= 0 || $paymentId <= 0 || $statusId <= 0) {
    json_response(['error' => 'Ошибка справочников БД (курсы/оплата/статусы).'], 500);
}

$insert = $pdo->prepare(
    'INSERT INTO applications (user_id, course_id, car_model, visit_date, visit_time, payment_method_id, status_id)
     VALUES (:user_id, :course_id, :car_model, :visit_date, :visit_time, :payment_method_id, :status_id)'
);
$insert->execute([
    'user_id' => $userId,
    'course_id' => $courseId,
    'car_model' => $carModel,
    'visit_date' => $visitDate,
    'visit_time' => $visitTime,
    'payment_method_id' => $paymentId,
    'status_id' => $statusId,
]);

json_response(['ok' => true, 'message' => 'Заявка создана.']);
