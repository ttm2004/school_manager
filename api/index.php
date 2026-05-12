<?php
/**
 * API Router — university/api/index.php
 *
 * URL pattern: /university/api/{module}/{action}
 * Method: GET | POST | PUT | DELETE
 * Auth: session-based (same session as web)
 * Response: JSON
 *
 * Usage:
 *   GET  /api/auth/me
 *   POST /api/auth/login
 *   GET  /api/semesters
 *   GET  /api/course-sections?semester_id=1
 *   POST /api/grades
 *   ...
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

// ── CORS (cho dev, restrict trên production) ──────────────────
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [
    'http://localhost:3000',   // React dev
    'http://localhost:8080',   // Vue dev
    'http://localhost',
    'http://127.0.0.1',
];
if (in_array($origin, $allowedOrigins) || str_contains($origin, 'localhost')) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// ── Parse route ───────────────────────────────────────────────
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base   = '/university/api/';
$path   = ltrim(str_replace($base, '', $uri), '/');
$parts  = explode('/', $path);
$module = $parts[0] ?? '';
$action = $parts[1] ?? '';
$id     = isset($parts[2]) ? (int)$parts[2] : null;
$method = $_SERVER['REQUEST_METHOD'];

// ── Parse JSON body ───────────────────────────────────────────
$body = [];
if (in_array($method, ['POST','PUT','PATCH'])) {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $body = json_decode($raw, true) ?? [];
    }
    // Fallback to $_POST
    if (empty($body)) $body = $_POST;
}

// ── Response helpers ──────────────────────────────────────────
function apiOk(mixed $data = null, string $message = 'OK', int $code = 200): never
{
    http_response_code($code);
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

function apiError(string $message, int $code = 400, mixed $errors = null): never
{
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'errors'  => $errors,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

function requireApiAuth(): void
{
    if (!isLoggedIn()) {
        apiError('Chưa đăng nhập', 401);
    }
}

function requireApiRole(array $roles): void
{
    requireApiAuth();
    $userRole = $_SESSION['role'] ?? '';
    if ($userRole === 'admin') return;
    if (!in_array($userRole, $roles)) {
        // Kiểm tra RBAC roles
        foreach ($roles as $role) {
            if (function_exists('hasRole') && hasRole($role)) return;
        }
        apiError('Không có quyền truy cập', 403);
    }
}

// ── Route to handler ──────────────────────────────────────────
$handlerFile = __DIR__ . "/handlers/{$module}.php";

if (!$module) {
    apiOk(['version' => '1.0', 'modules' => [
        'auth', 'semesters', 'subjects', 'course-sections',
        'grades', 'students', 'teachers', 'exam-schedules',
        'proposals', 'notifications', 'reports',
    ]], 'TDMU API v1.0');
}

if (!file_exists($handlerFile)) {
    apiError("Module '$module' không tồn tại", 404);
}

// Pass context to handler
$ctx = compact('conn', 'method', 'action', 'id', 'body');
require $handlerFile;

apiError('Endpoint không tồn tại', 404);
