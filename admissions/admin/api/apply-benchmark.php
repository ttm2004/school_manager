<?php
require_once '../../php/config.php';

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$major_id = (int)$data['major_id'];
$year = (int)$data['year'];
$suggested_score = (float)$data['suggested_score'];

$conn->begin_transaction();

try {

    // ================== 1. LẤY QUOTA ==================
    $quota_result = $conn->query("
        SELECT quota 
        FROM admission_quota 
        WHERE major_id = $major_id AND year = $year
    ");

    if ($quota_result && $quota_result->num_rows > 0) {
        $row_quota = $quota_result->fetch_assoc();
        $quota = (int)$row_quota['quota'];
    } else {
        $quota = 100; // fallback
    }

    // ================== 2. LƯU ĐIỂM CHUẨN ==================
    $method_code = 'thpt';
    $combination_id = null;

    $check = $conn->prepare("
        SELECT id FROM cutoff_scores 
        WHERE major_id = ? AND year = ? AND method_code = ?
    ");
    $check->bind_param("iis", $major_id, $year, $method_code);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $stmt = $conn->prepare("
            UPDATE cutoff_scores 
            SET score = ?, quota = ?
            WHERE major_id = ? AND year = ? AND method_code = ?
        ");
        $stmt->bind_param("diiis", $suggested_score, $quota, $major_id, $year, $method_code);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO cutoff_scores 
            (major_id, year, method_code, combination_id, score, quota) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iisidi", $major_id, $year, $method_code, $combination_id, $suggested_score, $quota);
    }

    $stmt->execute();

    // ================== 3. XÓA KẾT QUẢ CŨ ==================
    $conn->query("
        DELETE FROM admission_results 
        WHERE major_id = $major_id AND year = $year
    ");

    // ================== 4. LẤY DANH SÁCH THÍ SINH ==================
    $result = $conn->query("
        SELECT r.id, r.method, r.combination_id, d.total_score
        FROM registrations r
        JOIN diemtuyensinh d ON r.id = d.registration_id
        WHERE r.major = $major_id 
        AND YEAR(r.created_at) = $year
        AND d.total_score IS NOT NULL
        ORDER BY d.total_score DESC
    ");

    $results = [];
    $stats = ['passed' => 0, 'failed' => 0, 'pending' => 0];

    $index = 0;

    while ($row = $result->fetch_assoc()) {

        if ($index < $quota) {
            $status = 'passed';
            $note = 'Trúng tuyển theo chỉ tiêu';
            $db_status = 'approved';
            $stats['passed']++;
        } else {
            $status = 'failed';
            $note = 'Không trúng tuyển';
            $db_status = 'rejected';
            $stats['failed']++;
        }

        // UPDATE registrations
        $update = $conn->prepare("
            UPDATE registrations 
            SET status = ?
            WHERE id = ?
        ");
        $update->bind_param("si", $db_status, $row['id']);
        $update->execute();

        // INSERT admission_results
        $insert = $conn->prepare("
            INSERT INTO admission_results 
            (registration_id, major_id, year, method_code, combination_id, total_score, cutoff_score, status, note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $insert->bind_param(
            "iiisiddss",
            $row['id'],
            $major_id,
            $year,
            $row['method'],
            $row['combination_id'],
            $row['total_score'],
            $suggested_score,
            $status,
            $note
        );

        $insert->execute();

        $results[] = [
            'id' => $row['id'],
            'status' => $status,
            'note' => $note
        ];

        $index++;
    }

    // ================== 5. NGƯỜI CHƯA CÓ ĐIỂM ==================
    $conn->query("
        UPDATE registrations r
        LEFT JOIN diemtuyensinh d ON r.id = d.registration_id
        SET r.status = 'pending'
        WHERE r.major = $major_id 
        AND YEAR(r.created_at) = $year
        AND d.total_score IS NULL
    ");

    $stats['pending'] = $conn->query("
        SELECT COUNT(*) as total
        FROM registrations r
        LEFT JOIN diemtuyensinh d ON r.id = d.registration_id
        WHERE r.major = $major_id 
        AND YEAR(r.created_at) = $year
        AND d.total_score IS NULL
    ")->fetch_assoc()['total'];

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Xét tuyển thành công!',
        'data' => [
            'stats' => $stats,
            'results' => $results
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: ' . $e->getMessage()
    ]);
}