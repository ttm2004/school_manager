<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('student');

// Lấy thông tin sinh viên
$stmt = $conn->prepare("
    SELECT s.*, u.full_name, u.email, u.phone, u.address,
           c.class_name, c.school_year, m.major_name, f.faculty_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    JOIN classes c ON s.class_id = c.id
    JOIN majors m ON c.major_id = m.id
    JOIN faculties f ON m.faculty_id = f.id
    WHERE s.user_id = ?
");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    header('Location: /university/login.php?logout=1');
    exit();
}

// Học kỳ hiện tại
$semester = $conn->query("SELECT * FROM semesters WHERE status='open' ORDER BY id DESC LIMIT 1")->fetch_assoc();

// Số môn đã đăng ký
$regCount = $conn->prepare("SELECT COUNT(*) as c FROM student_subjects WHERE student_id=? AND status IN ('registered','auto_enrolled')");
$regCount->bind_param('i', $student['id']);
$regCount->execute();
$regCount = $regCount->get_result()->fetch_assoc()['c'];

// Số môn hoàn thành
$doneCount = $conn->prepare("SELECT COUNT(*) as c FROM student_subjects WHERE student_id=? AND status='completed'");
$doneCount->bind_param('i', $student['id']);
$doneCount->execute();
$doneCount = $doneCount->get_result()->fetch_assoc()['c'];

// Điểm trung bình
$avgStmt = $conn->prepare("SELECT AVG(g.total_score) as avg FROM grades g JOIN student_subjects ss ON g.student_subject_id=ss.id WHERE ss.student_id=? AND g.total_score IS NOT NULL");
$avgStmt->bind_param('i', $student['id']);
$avgStmt->execute();
$avgScore = $avgStmt->get_result()->fetch_assoc()['avg'];
$avgStmt->close();

// Thông báo mới nhất
$notifications = $conn->query("SELECT * FROM notifications WHERE status='show' ORDER BY created_at DESC LIMIT 4");

$pageTitle = 'Dashboard Sinh viên';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
    <title>Cổng Sinh viên - TDMU</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/university/assets/css/style.css">
</head>
<body>

<div class="student-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="student-main">
        <div class="student-topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary d-lg-none" onclick="document.querySelector('.student-sidebar').classList.toggle('show')">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <span class="fw-bold text-navy">Xin chào, <?php echo htmlspecialchars($student['full_name']); ?>!</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-navy"><?php echo htmlspecialchars($student['student_code']); ?></span>
                <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i></a>
            </div>
        </div>

        <div class="student-content">
            <!-- Thông tin sinh viên -->
            <div class="row g-4 mb-4">
                <div class="col-lg-8">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="avatar-circle bg-navy text-white">
                                    <?php echo mb_substr($student['full_name'], 0, 1); ?>
                                </div>
                                <div>
                                    <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($student['full_name']); ?></h5>
                                    <div class="text-muted small"><?php echo htmlspecialchars($student['student_code']); ?> &bull; <?php echo htmlspecialchars($student['class_name']); ?></div>
                                </div>
                            </div>
                            <div class="row g-2">
                                <div class="col-sm-6"><small class="text-muted">Ngành:</small><div class="fw-bold small"><?php echo htmlspecialchars($student['major_name']); ?></div></div>
                                <div class="col-sm-6"><small class="text-muted">Khoa:</small><div class="fw-bold small"><?php echo htmlspecialchars($student['faculty_name']); ?></div></div>
                                <div class="col-sm-6"><small class="text-muted">Khóa học:</small><div class="fw-bold small"><?php echo htmlspecialchars($student['school_year']); ?></div></div>
                                <div class="col-sm-6"><small class="text-muted">Trạng thái:</small><div><span class="badge bg-success"><?php echo $student['academic_status']; ?></span></div></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <?php if ($semester): ?>
                    <div class="card h-100 border-gold">
                        <div class="card-body text-center">
                            <i class="bi bi-calendar3 fs-2 text-gold mb-2"></i>
                            <h6 class="fw-bold"><?php echo htmlspecialchars($semester['semester_name']); ?></h6>
                            <div class="text-muted small"><?php echo htmlspecialchars($semester['school_year']); ?></div>
                            <hr>
                            <?php
                            $now = time();
                            $regStart = strtotime($semester['register_start']);
                            $regEnd = strtotime($semester['register_end']);
                            if ($now >= $regStart && $now <= $regEnd):
                            ?>
                            <span class="badge bg-success mb-2">Đang mở đăng ký</span>
                            <div class="text-muted small">Hạn: <?php echo date('d/m/Y', $regEnd); ?></div>
                            <a href="/university/student/register_subject.php" class="btn btn-gold btn-sm mt-2 w-100">
                                <i class="bi bi-journal-plus me-1"></i>Đăng ký ngay
                            </a>
                            <?php else: ?>
                            <span class="badge bg-secondary">Chưa mở đăng ký</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stats -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="stat-card-student">
                        <i class="bi bi-journal-check text-primary fs-3"></i>
                        <div class="stat-value"><?php echo $regCount; ?></div>
                        <div class="stat-label">Môn đang học</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card-student">
                        <i class="bi bi-patch-check-fill text-success fs-3"></i>
                        <div class="stat-value"><?php echo $doneCount; ?></div>
                        <div class="stat-label">Môn hoàn thành</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card-student">
                        <i class="bi bi-bar-chart-fill text-warning fs-3"></i>
                        <div class="stat-value"><?php echo $avgScore ? number_format($avgScore, 2) : '--'; ?></div>
                        <div class="stat-label">Điểm TB</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card-student">
                        <i class="bi bi-book-fill text-info fs-3"></i>
                        <div class="stat-value"><?php echo $student['total_credits'] ?? 120; ?></div>
                        <div class="stat-label">Tổng tín chỉ</div>
                    </div>
                </div>
            </div>

            <!-- Thông báo -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-bell-fill me-2"></i>Thông báo mới nhất</span>
                    <a href="/university/news.php" class="btn btn-sm btn-outline-light">Xem tất cả</a>
                </div>
                <div class="card-body p-0">
                    <?php if ($notifications && $notifications->num_rows > 0): while ($n = $notifications->fetch_assoc()): ?>
                    <div class="d-flex gap-3 p-3 border-bottom">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width:40px;height:40px">
                                <i class="bi bi-bell text-navy"></i>
                            </div>
                        </div>
                        <div>
                            <div class="fw-bold small"><?php echo htmlspecialchars($n['title']); ?></div>
                            <div class="text-muted small"><?php echo mb_substr(strip_tags($n['content']),0,100); ?>...</div>
                            <div class="text-muted" style="font-size:11px"><?php echo date('d/m/Y', strtotime($n['created_at'])); ?></div>
                        </div>
                    </div>
                    <?php endwhile; else: ?>
                    <div class="text-center text-muted py-4">Không có thông báo mới</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="student-footer">&copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một</div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<?php include_once __DIR__ . "/../includes/analytics_widget.php"; ?>
</body>
</html>

