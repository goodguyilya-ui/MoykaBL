<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_method('POST');

$data = read_json_body();
$login = field($data, 'login');
$password = (string)($data['password'] ?? '');
$fullName = field($data, 'full_name');
$phone = field($data, 'phone');
$email = mb_strtolower(field($data, 'email'));

if (!preg_match('/^[A-Za-z0-9]{6,}$/', $login)) {
    json_response(['error' => 'Логин: латиница/цифры, минимум 6 символов.'], 400);
}
if (mb_strlen($password) < 8) {
    json_response(['error' => 'Пароль должен быть не менее 8 символов.'], 400);
}
if (!preg_match('/^[А-Яа-яЁё\s]+$/u', $fullName)) {
    json_response(['error' => 'ФИО: только кириллица и пробелы.'], 400);
}
if (!preg_match('/^8\(\d{3}\)\d{3}-\d{2}-\d{2}$/', $phone)) {
    json_response(['error' => 'Телефон в формате 8(XXX)XXX-XX-XX.'], 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['error' => 'Некорректный email.'], 400);
}

$pdo = get_pdo();

$check = $pdo->prepare('SELECT id FROM users WHERE login = :login OR email = :email LIMIT 1');
$check->execute(['login' => $login, 'email' => $email]);
if ($check->fetch()) {
    json_response(['error' => 'Логин или email уже используются.'], 409);
}

$roleIdStmt = $pdo->query("SELECT id FROM roles WHERE name = 'client' LIMIT 1");
$roleId = (int)($roleIdStmt->fetch()['id'] ?? 0);
if ($roleId <= 0) {
    json_response(['error' => 'В БД отсутствует роль client.'], 500);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$insert = $pdo->prepare(
    'INSERT INTO users (login, password_hash, full_name, phone, email, role_id)
     VALUES (:login, :password_hash, :full_name, :phone, :email, :role_id)'
);
$insert->execute([
    'login' => $login,
    'password_hash' => $passwordHash,
    'full_name' => $fullName,
    'phone' => $phone,
    'email' => $email,
    'role_id' => $roleId,
]);

json_response(['ok' => true, 'message' => 'Пользователь создан.']);
