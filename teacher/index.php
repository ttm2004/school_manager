<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('teacher');

$stmt = $conn->prepare("
    SELECT t.*, u.full_name, u.email, u.phone, f.faculty_name
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    JOIN faculties f ON t.faculty_id = f.id
    WHERE t.user_id = ?
");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$teacher) { header('Location: /university/login.php?logout=1'); exit(); }

// Số lớp học phần
$courseCount = $conn->prepare("SELECT COUNT(*) as c FROM course_sections WHERE teacher_id=?");
$courseCount->bind_param('i', $teacher['id']);
$courseCount->execute();
$courseCount = $courseCount->get_result()->fetch_assoc()['c'];

// Số sinh viên đang dạy
$stuCount = $conn->prepare("SELECT COUNT(DISTINCT ss.student_id) as c FROM student_subjects ss JOIN course_sections cs ON ss.course_section_id=cs.id WHERE cs.teacher_id=? AND ss.status='registered'");
$stuCount->bind_param('i', $teacher['id']);
$stuCount->execute();
$stuCount = $stuCount->get_result()->fetch_assoc()['c'];

// Lớp học phần học kỳ hiện tại
$currentCourses = $conn->prepare("
    SELECT cs.*, s.subject_name, s.credits, sm.semester_name, sm.school_year
    FROM course_sections cs
    JOIN subjects s ON cs.subject_id = s.id
    JOIN semesters sm ON cs.semester_id = sm.id
    WHERE cs.teacher_id = ? AND sm.status = 'open'
    ORDER BY s.subject_name
");
$currentCourses->bind_param('i', $teacher['id']);
$currentCourses->execute();
$currentCourses = $currentCourses->get_result();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cổng Giảng viên - TDMU</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/university/assets/css/style.css">
</head>
<body>
<div class="student-wrapper">
    <div class="student-sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon"><i class="bi bi-person-badge-fill"></i></div>
            <div class="sidebar-brand-text"><div>Cổng Giảng viên</div><small><?php echo htmlspecialchars($teacher['teacher_code']); ?></small></div>
        </div>
        <nav class="sidebar-nav">
            <a href="/university/teacher/index.php" class="sidebar-link active"><i class="bi bi-speedometer2"></i> Tổng quan</a>
            <a href="/university/teacher/profile.php" class="sidebar-link"><i class="bi bi-person-fill"></i> Hồ sơ cá nhân</a>
            <a href="/university/teacher/my_courses.php" class="sidebar-link"><i class="bi bi-journal-text"></i> Lớp học phần</a>
            <a href="/university/teacher/grades.php" class="sidebar-link"><i class="bi bi-bar-chart-fill"></i> Nhập điểm</a>
            <a href="/university/teacher/evaluation.php" class="sidebar-link"><i class="bi bi-star-fill"></i> Kết quả đánh giá</a>
            <hr class="my-2">
            <a href="/university/index.php" class="sidebar-link"><i class="bi bi-globe"></i> Trang chủ</a>
            <a href="/university/login.php?logout=1" class="sidebar-link text-danger"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a>
        </nav>
    </div>
    <div class="student-main">
        <div class="student-topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary d-lg-none" onclick="document.querySelector('.student-sidebar').classList.toggle('show')"><i class="bi bi-list fs-5"></i></button>
                <span class="fw-bold text-navy">Xin chào, <?php echo htmlspecialchars($teacher['full_name']); ?>!</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-success"><?php echo htmlspecialchars($teacher['teacher_code']); ?></span>
                <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i></a>
            </div>
        </div>
        <div class="student-content">
            <!-- Thông tin giảng viên -->
            <div class="row g-4 mb-4">
                <div class="col-lg-8">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="avatar-circle bg-success text-white">
                                    <?php echo mb_substr($teacher['full_name'], 0, 1); ?>
                                </div>
                                <div>
                                    <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($teacher['full_name']); ?></h5>
                                    <div class="text-muted small"><?php echo htmlspecialchars($teacher['teacher_code']); ?> &bull; <?php echo htmlspecialchars($teacher['faculty_name']); ?></div>
                                </div>
                            </div>
                            <div class="row g-2">
                                <div class="col-sm-6"><small class="text-muted">Học vị:</small><div class="fw-bold small"><?php echo htmlspecialchars($teacher['degree']); ?></div></div>
                                <div class="col-sm-6"><small class="text-muted">Chuyên ngành:</small><div class="fw-bold small"><?php echo htmlspecialchars($teacher['specialization']); ?></div></div>
                                <div class="col-sm-6"><small class="text-muted">Email:</small><div class="fw-bold small"><?php echo htmlspecialchars($teacher['email']); ?></div></div>
                                <div class="col-sm-6"><small class="text-muted">Điện thoại:</small><div class="fw-bold small"><?php echo htmlspecialchars($teacher['phone'] ?? '--'); ?></div></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="stat-card-student text-center">
                                <i class="bi bi-grid-3x3-gap-fill text-navy fs-2 mb-1"></i>
                                <div class="stat-value"><?php echo $courseCount; ?></div>
                                <div class="stat-label">Lớp HP</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-card-student text-center">
                                <i class="bi bi-people-fill text-success fs-2 mb-1"></i>
                                <div class="stat-value"><?php echo $stuCount; ?></div>
                                <div class="stat-label">Sinh viên</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lớp học phần hiện tại -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-journal-text me-2"></i>Lớp học phần học kỳ hiện tại</span>
                    <a href="/university/teacher/my_courses.php" class="btn btn-sm btn-outline-light">Xem tất cả</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr><th>Mã HP</th><th>Môn học</th><th>Học kỳ</th><th>Lịch học</th><th>Phòng</th><th>Sĩ số</th><th>Thao tác</th></tr>
                            </thead>
                            <tbody>
                                <?php if ($currentCourses && $currentCourses->num_rows > 0): while ($c = $currentCourses->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold text-navy small"><?php echo htmlspecialchars($c['section_code']); ?></td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($c['subject_name']); ?></div>
                                        <span class="badge bg-navy"><?php echo $c['credits']; ?> TC</span>
                                    </td>
                                    <td class="small text-muted"><?php echo htmlspecialchars($c['semester_name'] . ' ' . $c['school_year']); ?></td>
                                    <td class="small text-muted"><?php echo htmlspecialchars($c['schedule_text']); ?></td>
                                    <td class="small"><?php echo htmlspecialchars($c['room']); ?></td>
                                    <td><span class="badge bg-<?php echo $c['current_students']>=$c['max_students']?'danger':'success'; ?>"><?php echo $c['current_students']; ?>/<?php echo $c['max_students']; ?></span></td>
                                    <td>
                                        <a href="/university/teacher/grades.php?section_id=<?php echo $c['id']; ?>" class="btn btn-sm btn-gold">
                                            <i class="bi bi-pencil-square me-1"></i>Nhập điểm
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">Không có lớp học phần nào trong học kỳ hiện tại</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="student-footer">&copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một</div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
</body>
</html>
