<?php
/** API: /api/notifications */
requireApiAuth();

// GET /api/notifications — lấy thông báo broadcast
if ($method === 'GET' && !$action) {
    $type   = trim($_GET['type'] ?? '');
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(50, max(1, (int)($_GET['per_page'] ?? 10)));

    $where = ["status='show'"]; $types = ''; $params = [];
    if ($type) { $where[] = 'type=?'; $types .= 's'; $params[] = $type; }
    $whereSQL = implode(' AND ', $where);

    $total = (int)$conn->query("SELECT COUNT(*) AS c FROM notifications WHERE $whereSQL")->fetch_assoc()['c'];
    $offset = ($page - 1) * $perPage;

    $stmtData = $conn->prepare("SELECT id, title, content, type, image, created_at FROM notifications WHERE $whereSQL ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $allTypes = $types . 'ii'; $allParams = array_merge($params, [$perPage, $offset]);
    $stmtData->bind_param($allTypes, ...$allParams);
    $stmtData->execute();
    $rows = $stmtData->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtData->close();

    apiOk(['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
}

// POST /api/notifications — tạo thông báo broadcast
if ($method === 'POST' && !$action) {
    requireApiRole(['academic_manager', 'admin']);
    $title   = trim($body['title'] ?? '');
    $content = trim($body['content'] ?? '');
    $type    = trim($body['type'] ?? 'general');
    if (!$title || !$content) apiError('title và content là bắt buộc');

    $stmt = $conn->prepare("INSERT INTO notifications (title, content, type, status) VALUES (?,?,?,'show')");
    $stmt->bind_param('sss', $title, $content, $type);
    if ($stmt->execute()) {
        $newId = $conn->insert_id;
        $stmt->close();
        apiOk(['id' => $newId], 'Đã tạo thông báo', 201);
    }
    apiError('Lỗi: ' . $conn->error);
}
