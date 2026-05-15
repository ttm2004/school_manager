<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('teacher');

$sectionId = (int)($_GET['section_id'] ?? 0);

$stmt = $conn->prepare("SELECT t.id FROM teachers t WHERE t.user_id = ? LIMIT 1");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$teacher || $sectionId <= 0) {
    http_response_code(404);
    exit('Không tìm thấy lớp học phần.');
}

$stmt = $conn->prepare("
    SELECT cs.id, cs.section_code, s.subject_name, s.subject_code, sm.semester_name, sm.school_year
    FROM course_sections cs
    JOIN subjects s ON cs.subject_id = s.id
    JOIN semesters sm ON cs.semester_id = sm.id
    WHERE cs.id = ? AND cs.teacher_id = ?
    LIMIT 1
");
$stmt->bind_param('ii', $sectionId, $teacher['id']);
$stmt->execute();
$section = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$section) {
    http_response_code(403);
    exit('Bạn không có quyền xuất danh sách lớp học phần này.');
}

$stmt = $conn->prepare("
    SELECT st.student_code, u.full_name, st.gender, u.email, cl.class_name,
           ss.status, ss.register_date
    FROM student_subjects ss
    JOIN students st ON ss.student_id = st.id
    JOIN users u ON st.user_id = u.id
    LEFT JOIN classes cl ON st.class_id = cl.id
    WHERE ss.course_section_id = ? AND ss.status IN ('registered','auto_enrolled')
    ORDER BY u.full_name
");
$stmt->bind_param('i', $sectionId);
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$safeCode = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$section['section_code']);
$filename = 'danh_sach_sinh_vien_' . $safeCode . '_' . date('Ymd_His') . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF";
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #999; padding: 6px; }
        th { background: #d9eaf7; font-weight: bold; }
        .title { font-weight: bold; font-size: 16px; }
    </style>
</head>
<body>
    <table>
        <tr><td colspan="8" class="title">Danh sách sinh viên lớp học phần</td></tr>
        <tr><td colspan="8">Môn học: <?php echo htmlspecialchars($section['subject_name']); ?></td></tr>
        <tr><td colspan="8">Mã lớp HP: <?php echo htmlspecialchars($section['section_code']); ?></td></tr>
        <tr><td colspan="8">Học kỳ: <?php echo htmlspecialchars($section['semester_name'] . ' ' . $section['school_year']); ?></td></tr>
        <tr><td colspan="8">Số sinh viên: <?php echo count($students); ?></td></tr>
        <tr>
            <th>STT</th>
            <th>Mã sinh viên</th>
            <th>Họ tên</th>
            <th>Giới tính</th>
            <th>Email</th>
            <th>Lớp</th>
            <th>Trạng thái</th>
            <th>Ngày đăng ký</th>
        </tr>
        <?php foreach ($students as $idx => $student): ?>
        <tr>
            <td><?php echo $idx + 1; ?></td>
            <td><?php echo htmlspecialchars($student['student_code'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($student['full_name'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($student['gender'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($student['email'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($student['class_name'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($student['status'] ?? ''); ?></td>
            <td><?php echo !empty($student['register_date']) ? date('d/m/Y H:i', strtotime($student['register_date'])) : ''; ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
