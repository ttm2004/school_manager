<?php
/** API: /api/auth/{action} */

// GET /api/auth/me
if ($method === 'GET' && $action === 'me') {
    requireApiAuth();
    $uid = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare(
        "SELECT u.id, u.username, u.full_name, u.email, u.phone, u.role, u.avatar, u.status,
                u.created_at
         FROM users u WHERE u.id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user) apiError('Không tìm thấy user', 404);

    // Lấy roles phòng ban
    $roles = getUserRoles();
    $user['department_roles'] = $roles;
    unset($user['password']);
    apiOk($user);
}

// POST /api/auth/login
if ($method === 'POST' && $action === 'login') {
    $username = trim($body['username'] ?? '');
    $password = trim($body['password'] ?? '');

    if (!$username || !$password) {
        apiError('Vui lòng nhập tên đăng nhập và mật khẩu');
    }

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND status = 1 LIMIT 1");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($password, $user['password'])) {
        apiError('Tên đăng nhập hoặc mật khẩu không đúng', 401);
    }

    session_regenerate_id(true);
    $_SESSION['user_id']        = $user['id'];
    $_SESSION['role']           = $user['role'];
    $_SESSION['full_name']      = $user['full_name'];
    $_SESSION['username']       = $user['username'];
    $_SESSION['_last_activity'] = time();

    unset($user['password']);
    apiOk([
        'user'       => $user,
        'session_id' => session_id(),
    ], 'Đăng nhập thành công');
}

// POST /api/auth/logout
if ($method === 'POST' && $action === 'logout') {
    session_destroy();
    apiOk(null, 'Đã đăng xuất');
}

// POST /api/auth/change-password
if ($method === 'POST' && $action === 'change-password') {
    requireApiAuth();
    $oldPw  = trim($body['old_password'] ?? '');
    $newPw  = trim($body['new_password'] ?? '');
    if (strlen($newPw) < 6) apiError('Mật khẩu mới phải ít nhất 6 ký tự');

    $uid  = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || !password_verify($oldPw, $row['password'])) {
        apiError('Mật khẩu cũ không đúng', 401);
    }

    $hashed = password_hash($newPw, PASSWORD_DEFAULT);
    $stmt2  = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt2->bind_param('si', $hashed, $uid);
    $stmt2->execute();
    $stmt2->close();
    apiOk(null, 'Đổi mật khẩu thành công');
}
