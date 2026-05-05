<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_method('POST');

$data = read_json_body();
$login = field($data, 'login');
$password = (string)($data['password'] ?? '');

if ($login === '' || $password === '') {
    json_response(['error' => 'Введите логин и пароль.'], 400);
}

$pdo = get_pdo();
$stmt = $pdo->prepare(
    'SELECT u.id, u.login, u.password_hash, r.name AS role
     FROM users u
     INNER JOIN roles r ON r.id = u.role_id
     WHERE u.login = :login
     LIMIT 1'
);
$stmt->execute(['login' => $login]);
$user = $stmt->fetch();

if (!$user) {
    json_response(['error' => 'Неверный логин или пароль.'], 401);
}

$hash = (string)$user['password_hash'];
$ok = password_verify($password, $hash) || hash_equals($hash, $password);
if (!$ok) {
    json_response(['error' => 'Неверный логин или пароль.'], 401);
}

json_response([
    'ok' => true,
    'user_id' => (int)$user['id'],
    'role' => (string)$user['role'],
]);
