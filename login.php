<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

// ── Mapping: role code prefix → module URL ──────────────────
// Thêm vào đây khi có module mới
define('ROLE_MODULE_MAP', [
    'admissions_'  => '/university/admissions/',
    'academic_'    => '/university/academic/',
    'finance_'     => '/university/finance/',
    'hr_'          => '/university/hr/',
    'student_affairs_' => '/university/student_affairs/',
    'exam_'        => '/university/exam/',
    'it_'          => '/university/admin/',
]);

/**
 * Lấy URL redirect cho nhân viên dựa trên role phòng ban đầu tiên
 * Ưu tiên role có priority cao nhất (manager > staff)
 */
function getStaffRedirectUrl(int $userId, $conn): string {
    $result = $conn->query("
        SELECT r.code FROM user_roles ur
        JOIN roles r ON ur.role_id = r.id
        WHERE ur.user_id = $userId
          AND r.is_active = 1
          AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
        ORDER BY
            CASE WHEN r.code LIKE '%_manager' THEN 0 ELSE 1 END,
            r.department
        LIMIT 1
    ");
    if ($result && $row = $result->fetch_assoc()) {
        $code = $row['code'];
        foreach (ROLE_MODULE_MAP as $prefix => $url) {
            if (str_starts_with($code, $prefix)) {
                return $url;
            }
        }
    }
    // Không có role nào → về trang thông báo
    return '/university/no_access.php';
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /university/login.php?msg=logout');
    exit();
}

if (isLoggedIn()) {
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: /university/admin/');
            break;
        case 'student':
            header('Location: /university/student/');
            break;
        case 'teacher':
            header('Location: /university/teacher/');
            break;
        case 'staff':
            // Redirect theo role phòng ban
            header('Location: ' . getStaffRedirectUrl((int)$_SESSION['user_id'], $conn));
            break;
        default:
            header('Location: /university/index.php');
    }
    exit();
}

$error = $success = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'logout') {
    $success = 'Bạn đã đăng xuất thành công.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Vui lòng nhập tên đăng nhập và mật khẩu.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND status = 1 LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['username']  = $user['username'];

            switch ($user['role']) {
                case 'admin':
                    header('Location: /university/admin/');
                    break;
                case 'student':
                    header('Location: /university/student/');
                    break;
                case 'teacher':
                    header('Location: /university/teacher/');
                    break;
                case 'staff':
                    // Redirect theo role phòng ban được cấp
                    header('Location: ' . getStaffRedirectUrl((int)$user['id'], $conn));
                    break;
                default:
                    header('Location: /university/index.php');
            }
            exit();
        } else {
            $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - TDMU</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/university/assets/css/style.css">
    <style>
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            background: #eef2f7;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', sans-serif;
        }

        .login-container {
            display: flex;
            width: 1060px;
            max-width: 96vw;
            min-height: 580px;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,48,135,0.13);
            overflow: hidden;
        }

        /* ===== LEFT — Illustration ===== */
        .login-left {
            flex: 1;
            background: #eef2f7;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 30px;
            position: relative;
        }
        .login-left .brand {
            position: absolute;
            top: 24px;
            left: 28px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .login-left .brand-icon {
            width: 38px; height: 38px;
            background: #003087;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: #FFB81C; font-size: 1.2rem;
        }
        .login-left .brand-text {
            line-height: 1.2;
        }
        .login-left .brand-text strong {
            display: block; font-size: 0.85rem; color: #003087;
        }
        .login-left .brand-text span {
            font-size: 0.72rem; color: #888;
        }

        /* SVG Illustration */
        .illustration { width: 100%; max-width: 340px; }

        /* ===== RIGHT — Form ===== */
        .login-right {
            width: 480px;
            flex-shrink: 0;
            padding: 56px 56px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .login-right h2 {
            font-size: 2.1rem;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 8px;
        }
        .login-right .subtitle {
            font-size: 0.92rem;
            color: #999;
            margin-bottom: 32px;
            line-height: 1.6;
        }

        /* Input group */
        .login-field {
            background: #f0f4f8;
            border-radius: 10px;
            padding: 14px 20px;
            margin-bottom: 2px;
            border: 1.5px solid transparent;
            transition: border-color 0.2s;
        }
        .login-field:focus-within {
            border-color: #003087;
            background: #fff;
        }
        .login-field label {
            display: block;
            font-size: 0.73rem;
            color: #aaa;
            margin-bottom: 4px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .login-field input {
            width: 100%;
            border: none;
            background: transparent;
            outline: none;
            font-size: 1rem;
            color: #1a1a2e;
            padding: 0;
        }
        .login-field input::placeholder { color: #bbb; }

        .login-fields-wrap {
            background: #f0f4f8;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 18px;
        }
        .login-fields-wrap .login-field {
            border-radius: 0;
            margin-bottom: 0;
            background: transparent;
        }
        .login-fields-wrap .login-field:first-child {
            border-bottom: 1px solid #e0e6ef;
        }
        .login-fields-wrap .login-field:focus-within {
            background: #fff;
            border-color: transparent;
            box-shadow: inset 0 0 0 2px #003087;
        }

        /* Password toggle */
        .pw-wrap { position: relative; }
        .pw-wrap input { padding-right: 32px; }
        .pw-toggle {
            position: absolute; right: 0; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            color: #aaa; font-size: 1rem; padding: 0;
        }
        .pw-toggle:hover { color: #003087; }

        /* Options row */
        .login-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
            font-size: 0.85rem;
        }
        .login-options label {
            display: flex; align-items: center; gap: 6px;
            color: #555; cursor: pointer;
        }
        .login-options input[type="checkbox"] {
            accent-color: #003087;
            width: 15px; height: 15px;
        }
        .login-options a {
            color: #888; text-decoration: none;
        }
        .login-options a:hover { color: #003087; }

        /* Submit button */
        .btn-login {
            width: 100%;
            padding: 15px;
            background: #003087;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            letter-spacing: 0.5px;
        }
        .btn-login:hover {
            background: #002060;
            transform: translateY(-1px);
        }
        .btn-login:active { transform: translateY(0); }



        /* Back link */
        .back-link {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 0.8rem; color: #aaa; text-decoration: none;
            margin-top: 16px;
            transition: color 0.2s;
        }
        .back-link:hover { color: #003087; }

        /* Alert */
        .login-alert {
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.85rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .login-alert.error { background: #fde8e8; color: #c0392b; }
        .login-alert.success { background: #e8f5e9; color: #27ae60; }

        /* Responsive */
        @media (max-width: 700px) {
            .login-left { display: none; }
            .login-right { width: 100%; padding: 36px 28px; }
            .login-container { border-radius: 16px; }
        }
    </style>
</head>
<body>

<div class="login-container">

    <!-- LEFT: Illustration -->
    <div class="login-left">
        <!-- Brand -->
        <div class="brand">
            <div class="brand-icon"><i class="bi bi-mortarboard-fill"></i></div>
            <div class="brand-text">
                <strong>TDMU</strong>
                <span>Thủ Dầu Một</span>
            </div>
        </div>

        <!-- SVG Illustration — người ngồi làm việc -->
        <svg class="illustration" viewBox="0 0 500 420" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- Background shapes -->
            <ellipse cx="250" cy="390" rx="200" ry="18" fill="#d8e4f0" opacity="0.5"/>

            <!-- Bookshelf -->
            <rect x="60" y="40" width="180" height="12" rx="4" fill="#c5d5e8"/>
            <rect x="60" y="38" width="4" height="120" rx="2" fill="#b0c4de"/>
            <rect x="236" y="38" width="4" height="120" rx="2" fill="#b0c4de"/>
            <!-- Books -->
            <rect x="68" y="52" width="18" height="50" rx="2" fill="#003087"/>
            <rect x="88" y="58" width="14" height="44" rx="2" fill="#FFB81C"/>
            <rect x="104" y="54" width="16" height="48" rx="2" fill="#5b8dd9"/>
            <rect x="122" y="60" width="12" height="42" rx="2" fill="#e74c3c"/>
            <rect x="136" y="56" width="18" height="46" rx="2" fill="#003087" opacity="0.6"/>
            <rect x="156" y="62" width="14" height="40" rx="2" fill="#27ae60"/>
            <rect x="172" y="55" width="16" height="47" rx="2" fill="#FFB81C" opacity="0.7"/>
            <rect x="190" y="59" width="12" height="43" rx="2" fill="#9b59b6"/>
            <!-- Shelf 2 -->
            <rect x="60" y="108" width="180" height="10" rx="3" fill="#c5d5e8"/>
            <rect x="68" y="118" width="20" height="42" rx="2" fill="#FFB81C"/>
            <rect x="90" y="122" width="15" height="38" rx="2" fill="#003087" opacity="0.5"/>
            <rect x="107" y="120" width="18" height="40" rx="2" fill="#e74c3c" opacity="0.7"/>
            <rect x="127" y="124" width="14" height="36" rx="2" fill="#5b8dd9"/>
            <!-- Clock on shelf -->
            <circle cx="210" cy="130" r="14" fill="#fff" stroke="#c5d5e8" stroke-width="2"/>
            <circle cx="210" cy="130" r="2" fill="#003087"/>
            <line x1="210" y1="130" x2="210" y2="120" stroke="#003087" stroke-width="1.5" stroke-linecap="round"/>
            <line x1="210" y1="130" x2="217" y2="133" stroke="#003087" stroke-width="1.5" stroke-linecap="round"/>

            <!-- Desk -->
            <rect x="80" y="270" width="300" height="14" rx="5" fill="#b0c4de"/>
            <rect x="100" y="284" width="12" height="80" rx="4" fill="#9ab0cc"/>
            <rect x="348" y="284" width="12" height="80" rx="4" fill="#9ab0cc"/>

            <!-- Monitor -->
            <rect x="155" y="180" width="130" height="88" rx="8" fill="#1a2a4a"/>
            <rect x="161" y="186" width="118" height="72" rx="5" fill="#2d4a7a"/>
            <!-- Screen content -->
            <rect x="168" y="194" width="60" height="6" rx="2" fill="#5b8dd9" opacity="0.8"/>
            <rect x="168" y="204" width="45" height="4" rx="2" fill="#fff" opacity="0.4"/>
            <rect x="168" y="212" width="50" height="4" rx="2" fill="#fff" opacity="0.3"/>
            <rect x="168" y="220" width="40" height="4" rx="2" fill="#FFB81C" opacity="0.6"/>
            <rect x="168" y="230" width="55" height="4" rx="2" fill="#fff" opacity="0.25"/>
            <!-- Blue card on screen -->
            <rect x="220" y="194" width="52" height="36" rx="4" fill="#003087" opacity="0.9"/>
            <rect x="225" y="200" width="30" height="3" rx="1" fill="#fff" opacity="0.7"/>
            <rect x="225" y="207" width="22" height="3" rx="1" fill="#FFB81C" opacity="0.8"/>
            <rect x="225" y="214" width="26" height="3" rx="1" fill="#fff" opacity="0.5"/>
            <!-- Monitor stand -->
            <rect x="210" y="268" width="20" height="8" rx="2" fill="#9ab0cc"/>
            <rect x="200" y="274" width="40" height="6" rx="3" fill="#b0c4de"/>

            <!-- Keyboard -->
            <rect x="165" y="258" width="110" height="14" rx="4" fill="#c5d5e8"/>
            <rect x="170" y="261" width="8" height="5" rx="1" fill="#9ab0cc"/>
            <rect x="181" y="261" width="8" height="5" rx="1" fill="#9ab0cc"/>
            <rect x="192" y="261" width="8" height="5" rx="1" fill="#9ab0cc"/>
            <rect x="203" y="261" width="8" height="5" rx="1" fill="#9ab0cc"/>
            <rect x="214" y="261" width="8" height="5" rx="1" fill="#9ab0cc"/>
            <rect x="225" y="261" width="8" height="5" rx="1" fill="#9ab0cc"/>
            <rect x="236" y="261" width="8" height="5" rx="1" fill="#9ab0cc"/>
            <rect x="247" y="261" width="8" height="5" rx="1" fill="#9ab0cc"/>
            <rect x="258" y="261" width="8" height="5" rx="1" fill="#9ab0cc"/>

            <!-- Clipboard / notepad -->
            <rect x="310" y="200" width="50" height="68" rx="4" fill="#f0f4f8" stroke="#c5d5e8" stroke-width="1.5"/>
            <rect x="328" y="194" width="14" height="10" rx="3" fill="#9ab0cc"/>
            <rect x="318" y="214" width="34" height="3" rx="1" fill="#c5d5e8"/>
            <rect x="318" y="222" width="28" height="3" rx="1" fill="#c5d5e8"/>
            <rect x="318" y="230" width="32" height="3" rx="1" fill="#c5d5e8"/>
            <rect x="318" y="238" width="20" height="3" rx="1" fill="#c5d5e8"/>
            <rect x="318" y="246" width="26" height="3" rx="1" fill="#c5d5e8"/>

            <!-- Chair -->
            <rect x="130" y="300" width="80" height="10" rx="5" fill="#6c8ebf"/>
            <rect x="165" y="310" width="10" height="50" rx="3" fill="#5a7aad"/>
            <rect x="148" y="355" width="44" height="8" rx="4" fill="#5a7aad"/>
            <!-- Chair back -->
            <rect x="128" y="240" width="84" height="62" rx="8" fill="#7ba3d4"/>
            <rect x="136" y="248" width="68" height="46" rx="5" fill="#6c8ebf"/>

            <!-- Person -->
            <!-- Body -->
            <ellipse cx="185" cy="290" rx="28" ry="32" fill="#5a6a8a"/>
            <!-- Head -->
            <circle cx="185" cy="240" r="22" fill="#f4c5a0"/>
            <!-- Hair bun -->
            <ellipse cx="185" cy="220" rx="18" ry="12" fill="#2c2c3e"/>
            <circle cx="185" cy="212" r="8" fill="#2c2c3e"/>
            <!-- Neck -->
            <rect x="179" y="260" width="12" height="14" rx="4" fill="#f4c5a0"/>
            <!-- Arms -->
            <path d="M158 285 Q140 295 148 310" stroke="#5a6a8a" stroke-width="14" stroke-linecap="round" fill="none"/>
            <path d="M212 285 Q228 295 220 308" stroke="#5a6a8a" stroke-width="14" stroke-linecap="round" fill="none"/>
            <!-- Hands -->
            <ellipse cx="149" cy="313" rx="8" ry="6" fill="#f4c5a0"/>
            <ellipse cx="219" cy="311" rx="8" ry="6" fill="#f4c5a0"/>
            <!-- Legs -->
            <path d="M168 320 Q162 350 158 370" stroke="#2c2c3e" stroke-width="12" stroke-linecap="round" fill="none"/>
            <path d="M202 320 Q208 350 212 370" stroke="#2c2c3e" stroke-width="12" stroke-linecap="round" fill="none"/>
            <!-- Shoes -->
            <ellipse cx="155" cy="373" rx="12" ry="6" fill="#1a1a2e"/>
            <ellipse cx="215" cy="373" rx="12" ry="6" fill="#1a1a2e"/>

            <!-- Plant left -->
            <rect x="88" y="248" width="14" height="24" rx="3" fill="#8fa8c8"/>
            <ellipse cx="95" cy="248" rx="10" ry="6" fill="#8fa8c8"/>
            <path d="M95 248 Q80 230 75 215" stroke="#27ae60" stroke-width="3" fill="none"/>
            <ellipse cx="73" cy="212" rx="10" ry="7" fill="#27ae60" transform="rotate(-20 73 212)"/>
            <path d="M95 248 Q88 228 90 215" stroke="#27ae60" stroke-width="3" fill="none"/>
            <ellipse cx="89" cy="212" rx="9" ry="6" fill="#2ecc71" transform="rotate(10 89 212)"/>
            <path d="M95 248 Q105 230 108 218" stroke="#27ae60" stroke-width="3" fill="none"/>
            <ellipse cx="110" cy="215" rx="9" ry="6" fill="#27ae60" transform="rotate(30 110 215)"/>

            <!-- Plant right -->
            <rect x="368" y="240" width="16" height="32" rx="4" fill="#003087" opacity="0.7"/>
            <ellipse cx="376" cy="240" rx="12" ry="7" fill="#003087" opacity="0.6"/>
            <path d="M376 240 Q358 215 352 195" stroke="#003087" stroke-width="3" fill="none" opacity="0.8"/>
            <ellipse cx="349" cy="191" rx="12" ry="8" fill="#003087" opacity="0.7" transform="rotate(-15 349 191)"/>
            <path d="M376 240 Q370 215 372 198" stroke="#003087" stroke-width="3" fill="none" opacity="0.8"/>
            <ellipse cx="371" cy="194" rx="11" ry="7" fill="#1a5276" opacity="0.8" transform="rotate(5 371 194)"/>
            <path d="M376 240 Q390 218 395 200" stroke="#003087" stroke-width="3" fill="none" opacity="0.8"/>
            <ellipse cx="397" cy="197" rx="11" ry="7" fill="#003087" opacity="0.7" transform="rotate(25 397 197)"/>

            <!-- Floating dots decoration -->
            <circle cx="430" cy="80" r="6" fill="#FFB81C" opacity="0.5"/>
            <circle cx="450" cy="110" r="4" fill="#003087" opacity="0.3"/>
            <circle cx="420" cy="140" r="5" fill="#5b8dd9" opacity="0.4"/>
            <circle cx="70" cy="320" r="5" fill="#FFB81C" opacity="0.4"/>
            <circle cx="50" cy="290" r="4" fill="#003087" opacity="0.3"/>
        </svg>
    </div>

    <!-- RIGHT: Form -->
    <div class="login-right">
        <h2>Đăng nhập</h2>
        <p class="subtitle">Chào mừng bạn đến với cổng thông tin<br>Trường Đại học Thủ Dầu Một (TDMU)</p>

        <?php if ($error): ?>
        <div class="login-alert error">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="login-alert success">
            <i class="bi bi-check-circle-fill"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <div class="login-fields-wrap">
                <div class="login-field">
                    <label>Tên đăng nhập</label>
                    <input type="text" name="username" placeholder="Nhập tên đăng nhập"
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                           autofocus required>
                </div>
                <div class="login-field">
                    <label>Mật khẩu</label>
                    <div class="pw-wrap">
                        <input type="password" name="password" id="pwInput"
                               placeholder="Nhập mật khẩu" required>
                        <button type="button" class="pw-toggle" onclick="togglePw()">
                            <i class="bi bi-eye" id="pwEye"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="login-options">
                <label>
                    <input type="checkbox" name="remember"> Ghi nhớ đăng nhập
                </label>
                <a href="/university/index.php">Quay về trang chủ</a>
            </div>

            <button type="submit" class="btn-login">
                Đăng nhập
            </button>
        </form>



        <a href="/university/index.php" class="back-link">
            <i class="bi bi-arrow-left"></i> Quay về trang chủ
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<script>
function togglePw() {
    const inp = document.getElementById('pwInput');
    const ico = document.getElementById('pwEye');
    if (inp.type === 'password') {
        inp.type = 'text';
        ico.className = 'bi bi-eye-slash';
    } else {
        inp.type = 'password';
        ico.className = 'bi bi-eye';
    }
}
</script>
</body>
</html>
