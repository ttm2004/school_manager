<?php
session_start();
require_once '../../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    die("Bạn không có quyền truy cập trang này.");
}

$class_id = (int)($_GET['class_id'] ?? 0);
$subject_id = (int)($_GET['subject_id'] ?? 0);
$teacher_name = $_SESSION['full_name'];

$stmtInfo = $conn->prepare("
    SELECT c.name as class_name, s.name as subject_name 
    FROM classes c, subjects s 
    WHERE c.id = ? AND s.id = ?
");
$stmtInfo->execute([$class_id, $subject_id]);
$info = $stmtInfo->fetch(PDO::FETCH_ASSOC);

if (!$info) die("Không tìm thấy thông tin lớp học.");

$stmt = $conn->prepare("
    SELECT 
        u.id,
        u.full_name, 
        u.username as student_code, 
        AVG(sub.grade) as avg_assignment,
        (SELECT AVG(er.score) 
         FROM exam_results er 
         INNER JOIN exams e ON er.exam_id = e.id 
         WHERE er.student_id = u.id 
         AND e.class_id = ? 
         AND e.subject_id = ?) as avg_exam
    FROM users u
    INNER JOIN student_subjects ss ON u.id = ss.student_id
    LEFT JOIN submissions sub ON u.id = sub.student_id AND sub.assignment_id IN 
        (SELECT id FROM assignments WHERE class_id = ? AND subject_id = ?)
    WHERE ss.class_id = ? AND ss.subject_id = ?
    GROUP BY u.id 
    ORDER BY u.full_name ASC
");

$stmt->execute([
    $class_id, $subject_id,
    $class_id, $subject_id,
    $class_id, $subject_id
]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

function calculateRank($score) {
    if ($score === null) return '-';
    if ($score >= 9.0) return 'A+';
    if ($score >= 8.0) return 'A';
    if ($score >= 7.0) return 'B';
    if ($score >= 5.0) return 'C';
    return 'F';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Bảng điểm - <?= htmlspecialchars($info['class_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Times New Roman', Times, serif; }
        .header-title { text-transform: uppercase; font-weight: bold; text-align: center; }
        .sub-title { text-align: center; font-style: italic; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid black; padding: 8px; text-align: center; font-size: 14px; }
        th { background-color: #f0f0f0 !important; font-weight: bold; }
        .text-start { text-align: left !important; }
        .signature-section { margin-top: 50px; display: flex; justify-content: space-between; }
        .signature-box { text-align: center; width: 40%; }
        @media print {
            @page { size: A4; margin: 2cm; }
            .no-print { display: none !important; }
            body { -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body class="bg-white p-4">
    <div class="fixed-top p-3 no-print">
        <button onclick="window.print()" class="btn btn-primary shadow"><i class="fas fa-print"></i> In ngay / Lưu PDF</button>
    </div>
    <div class="container">
        <div class="row mb-4">
            <div class="col-6 text-center">
                <p class="mb-0 fw-bold">TRƯỜNG ĐẠI HỌC EDUTECH</p>
                <p class="small text-decoration-underline">Phòng Đào tạo</p>
            </div>
            <div class="col-6 text-center">
                <p class="mb-0 fw-bold">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</p>
                <p class="small text-decoration-underline">Độc lập - Tự do - Hạnh phúc</p>
            </div>
        </div>
        <h3 class="header-title mt-4">BẢNG TỔNG HỢP KẾT QUẢ HỌC TẬP</h3>
        <p class="sub-title">
            Lớp: <b><?= htmlspecialchars($info['class_name']) ?></b> - 
            Môn: <b><?= htmlspecialchars($info['subject_name']) ?></b>
        </p>
        <table>
            <thead>
                <tr>
                    <th width="5%">STT</th>
                    <th width="15%">Mã SV</th>
                    <th width="25%" class="text-start ps-3">Họ và tên</th>
                    <th width="10%">BT (20%)</th>
                    <th width="10%">Thi (80%)</th>
                    <th width="10%">Tổng kết</th>
                    <th width="10%">Xếp loại</th>
                    <th width="15%">Ghi chú</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr><td colspan="8">Chưa có dữ liệu sinh viên</td></tr>
                <?php else: ?>
                    <?php 
                    $stt = 1;
                    foreach ($students as $s): 
                        $bt = $s['avg_assignment'] !== null ? round((float)$s['avg_assignment'], 1) : 0;
                        $thi = $s['avg_exam'] !== null ? round((float)$s['avg_exam'], 1) : 0;
                        $total = ($bt * 0.2) + ($thi * 0.8);
                        $total = round($total, 1);
                        $rank = calculateRank($total);
                        $show_bt = $s['avg_assignment'] !== null ? $bt : '-';
                        $show_thi = $s['avg_exam'] !== null ? $thi : '-';
                    ?>
                    <tr>
                        <td><?= $stt++ ?></td>
                        <td><?= htmlspecialchars($s['student_code']) ?></td>
                        <td class="text-start ps-3 fw-bold"><?= htmlspecialchars($s['full_name']) ?></td>
                        <td><?= $show_bt ?></td>
                        <td><?= $show_thi ?></td>
                        <td class="fw-bold"><?= $total ?></td>
                        <td class="fw-bold <?= $rank == 'F' ? 'text-danger' : '' ?> <?= ($rank == 'A' || $rank == 'A+') ? 'text-success' : '' ?>">
                            <?= $rank ?>
                        </td>
                        <td></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="signature-section">
            <div class="signature-box">
                <p class="mb-5"><b>Giáo vụ khoa</b><br><i class="small">(Ký và ghi rõ họ tên)</i></p>
                <br><br>
                <p>.......................................</p>
            </div>
            <div class="signature-box">
                <p class="mb-0"><i>Ngày <?= date('d') ?> tháng <?= date('m') ?> năm <?= date('Y') ?></i></p>
                <p class="mb-5"><b>Giảng viên môn học</b><br><i class="small">(Ký và ghi rõ họ tên)</i></p>
                <br><br>
                <p class="fw-bold"><?= htmlspecialchars($teacher_name) ?></p>
            </div>
        </div>
    </div>
</body>
</html>