<?php
declare(strict_types=1);

$dbCandidates = [
    __DIR__ . '/../db.php',
    __DIR__ . '/../../db.php',
    __DIR__ . '/../../backend/db.php',
];

$dbLoaded = false;
foreach ($dbCandidates as $candidate) {
    if (is_file($candidate)) {
        require_once $candidate;
        $dbLoaded = true;
        break;
    }
}

if (!$dbLoaded) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(
        ['error' => 'Не найден файл подключения db.php. Проверьте структуру проекта.'],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

header('Content-Type: application/json; charset=utf-8');

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function require_method(string $method): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== strtoupper($method)) {
        json_response(['error' => 'Метод не поддерживается.'], 405);
    }
}

function field(array $data, string $key): string
{
    return trim((string)($data[$key] ?? ''));
}

set_exception_handler(static function (Throwable $e): void {
    $message = $e->getMessage();
    if (str_contains(mb_strtolower($message), 'access denied')) {
        $message = 'Ошибка подключения к MySQL: проверьте логин/пароль в db.php.';
    }
    json_response(['error' => $message], 500);
});
