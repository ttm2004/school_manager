<?php
/**
 * App Config — load từ .env
 * Dùng: config('db.host'), config('app.debug')
 */

// ── Load .env ─────────────────────────────────────────────────
function loadEnv(string $path): void
{
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        // Strip quotes
        if (preg_match('/^"(.*)"$/', $value, $m) || preg_match("/^'(.*)'$/", $value, $m)) {
            $value = $m[1];
        }
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// Load .env từ root project
loadEnv(dirname(__DIR__) . '/.env');
date_default_timezone_set((string)($_ENV['APP_TIMEZONE'] ?? getenv('APP_TIMEZONE') ?: 'Asia/Ho_Chi_Minh'));

// ── Helper: lấy config ────────────────────────────────────────
function env(string $key, mixed $default = null): mixed
{
    $val = $_ENV[$key] ?? getenv($key);
    if ($val === false || $val === null) return $default;
    return match(strtolower((string)$val)) {
        'true','(true)'   => true,
        'false','(false)' => false,
        'null','(null)'   => null,
        'empty',''        => $default,
        default           => $val,
    };
}

// ── Config registry ───────────────────────────────────────────
$_CONFIG = [
    'app' => [
        'env'   => env('APP_ENV', 'production'),
        'debug' => env('APP_DEBUG', false),
        'url'   => env('APP_URL', 'http://localhost/university'),
        'key'   => env('APP_KEY', ''),
        'name'  => 'TDMU University Management',
    ],
    'db' => [
        'host'    => env('DB_HOST', 'localhost'),
        'port'    => (int)env('DB_PORT', 3306),
        'name'    => env('DB_NAME', 'edu_management'),
        'user'    => env('DB_USER', 'root'),
        'pass'    => env('DB_PASS', ''),
        'charset' => 'utf8mb4',
    ],
    'session' => [
        'lifetime' => (int)env('SESSION_LIFETIME', 7200),
        'secure'   => env('SESSION_SECURE', false),
    ],
    'mail' => [
        'driver'   => env('MAIL_DRIVER', 'smtp'),
        'host'     => env('MAIL_HOST', ''),
        'port'     => (int)env('MAIL_PORT', 587),
        'username' => env('MAIL_USERNAME', ''),
        'password' => env('MAIL_PASSWORD', ''),
        'from'     => env('MAIL_FROM_ADDRESS', ''),
        'name'     => env('MAIL_FROM_NAME', 'TDMU'),
    ],
    'jwt' => [
        'secret' => env('JWT_SECRET', ''),
        'expire' => (int)env('JWT_EXPIRE', 3600),
    ],
];

function config(string $key, mixed $default = null): mixed
{
    global $_CONFIG;
    $parts = explode('.', $key);
    $val   = $_CONFIG;
    foreach ($parts as $part) {
        if (!is_array($val) || !array_key_exists($part, $val)) return $default;
        $val = $val[$part];
    }
    return $val;
}
