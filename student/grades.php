<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('student');

$stmt = $conn->prepare("SELECT s.*, u.full_name FROM students s JOIN users u ON s.user_id=u.id WHERE s.user_id=?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Lấy điểm theo học kỳ
$stmt = $conn->prepare("
    SELECT g.*, ss.status as reg_status,
           s.subject_name, s.credits,
           cs.section_code,
           sm.semester_name, sm.school_year,
           f.exam_date, f.status as exam_status
    FROM student_subjects ss
    JOIN course_sections cs ON ss.course_section_id = cs.id
    JOIN subjects s ON cs.subject_id = s.id
    JOIN semesters sm ON cs.semester_id = sm.id
    LEFT JOIN grades g ON g.student_subject_id = ss.id
    LEFT JOIN final_exam_schedules f ON f.course_section_id = cs.id AND f.status != 'cancelled'
    WHERE ss.student_id = ? AND ss.status != 'cancelled'
    ORDER BY sm.school_year DESC, sm.semester_name, s.subject_name
");
$stmt->bind_param('i', $student['id']);
$stmt->execute();
$allGrades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Nhóm theo học kỳ
$bySemester = [];
foreach ($allGrades as $g) {
    $key = $g['school_year'] . ' - ' . $g['semester_name'];
    $bySemester[$key][] = $g;
}

// Tính GPA tổng
$totalCredits = 0;
$totalWeighted = 0;
foreach ($allGrades as $g) {
    if ($g['total_score'] !== null) {
        $totalCredits += $g['credits'];
        $totalWeighted += $g['total_score'] * $g['credits'];
    }
}
$gpa = $totalCredits > 0 ? round($totalWeighted / $totalCredits, 2) : null;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kết quả học tập - Sinh viên</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/university/assets/css/style.css">
</head>
<body>
<div class="student-wrapper">
    <div class="student-sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon"><i class="bi bi-mortarboard-fill"></i></div>
            <div class="sidebar-brand-text"><div>Cổng Sinh viên</div><small><?php echo htmlspecialchars($student['student_code']); ?></small></div>
        </div>
        <nav class="sidebar-nav">
            <a href="/university/student/index.php" class="sidebar-link"><i class="bi bi-speedometer2"></i> Tổng quan</a>
            <a href="/university/student/profile.php" class="sidebar-link"><i class="bi bi-person-fill"></i> Hồ sơ cá nhân</a>
            <a href="/university/student/register_subject.php" class="sidebar-link"><i class="bi bi-journal-plus"></i> Đăng ký học phần</a>
            <a href="/university/student/my_subjects.php" class="sidebar-link"><i class="bi bi-journal-check"></i> Học phần của tôi</a>
            <a href="/university/student/timetable.php" class="sidebar-link"><i class="bi bi-calendar3-week"></i> Thời khóa biểu</a>
            <a href="/university/student/exam_schedule.php" class="sidebar-link"><i class="bi bi-calendar-event-fill"></i> Lịch thi cuối kỳ</a>
            <a href="/university/student/grades.php" class="sidebar-link active"><i class="bi bi-bar-chart-fill"></i> Kết quả học tập</a>
            <a href="/university/student/evaluation.php" class="sidebar-link"><i class="bi bi-star-fill"></i> Đánh giá giảng viên</a>
            <hr class="my-2">
            <a href="/university/index.php" class="sidebar-link"><i class="bi bi-globe"></i> Trang chủ</a>
            <a href="/university/login.php?logout=1" class="sidebar-link text-danger"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a>
        </nav>
    </div>
    <div class="student-main">
        <div class="student-topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary d-lg-none" onclick="document.querySelector('.student-sidebar').classList.toggle('show')"><i class="bi bi-list fs-5"></i></button>
                <span class="fw-bold text-navy">Kết quả học tập</span>
            </div>
            <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i></a>
        </div>
        <div class="student-content">
            <!-- GPA Summary -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="stat-card-student text-center">
                        <i class="bi bi-trophy-fill text-gold fs-2 mb-2"></i>
                        <div class="stat-value"><?php echo $gpa ?? '--'; ?></div>
                        <div class="stat-label">Điểm TB tích lũy (GPA)</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card-student text-center">
                        <i class="bi bi-book-fill text-navy fs-2 mb-2"></i>
                        <div class="stat-value"><?php echo $totalCredits; ?></div>
                        <div class="stat-label">Tín chỉ tích lũy</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card-student text-center">
                        <i class="bi bi-journal-text text-success fs-2 mb-2"></i>
                        <div class="stat-value"><?php echo count($allGrades); ?></div>
                        <div class="stat-label">Tổng số môn</div>
                    </div>
                </div>
            </div>

            <?php if (empty($bySemester)): ?>
            <div class="card"><div class="card-body text-center text-muted py-5">
                <i class="bi bi-inbox fs-2 d-block mb-2"></i>Chưa có kết quả học tập
            </div></div>
            <?php else: ?>
            <?php foreach ($bySemester as $semKey => $grades): ?>
            <?php
            $semCredits = 0; $semWeighted = 0;
            foreach ($grades as $g) {
                if ($g['total_score'] !== null) {
                    $semCredits += $g['credits'];
                    $semWeighted += $g['total_score'] * $g['credits'];
                }
            }
            $semGpa = $semCredits > 0 ? round($semWeighted / $semCredits, 2) : null;
            ?>
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-calendar3 me-2"></i><?php echo htmlspecialchars($semKey); ?></span>
                    <?php if ($semGpa): ?>
                    <span class="badge bg-<?php echo $semGpa>=7?'success':($semGpa>=5?'warning':'danger'); ?> fs-6">
                        GPA: <?php echo $semGpa; ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Môn học</th>
                                    <th>TC</th>
                                    <th>Điểm QT</th>
                                    <th>Điểm GK</th>
                                    <th>Điểm CK</th>
                                    <th>Tổng kết</th>
                                    <th>Xếp loại</th>
                                    <th>Trạng thái</th>
                                    <th>Ghi chú</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $idx=1; foreach ($grades as $g):
                                $lc = ['A'=>'success','B+'=>'primary','B'=>'info','C+'=>'warning','C'=>'warning','D+'=>'secondary','D'=>'secondary','F'=>'danger'];
                                $lcolor = $lc[$g['letter_grade'] ?? ''] ?? 'secondary';
                                ?>
                                <tr>
                                    <td><?php echo $idx++; ?></td>
                                    <td class="fw-bold"><?php echo htmlspecialchars($g['subject_name']); ?></td>
                                    <td class="text-center"><span class="badge bg-navy"><?php echo $g['credits']; ?></span></td>
                                    <td class="text-center"><?php echo $g['process_score'] ?? '<span class="text-muted">--</span>'; ?></td>
                                    <td class="text-center"><?php echo $g['midterm_score'] ?? '<span class="text-muted">--</span>'; ?></td>
                                    <td class="text-center"><?php echo $g['final_score'] ?? '<span class="text-muted">--</span>'; ?></td>
                                    <td class="text-center fw-bold <?php echo $g['total_score']!==null?($g['total_score']>=5?'text-success':'text-danger'):''; ?>">
                                        <?php echo $g['total_score'] !== null ? number_format($g['total_score'],2) : '<span class="text-muted">--</span>'; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($g['letter_grade']): ?>
                                        <span class="badge bg-<?php echo $lcolor; ?> fs-6"><?php echo $g['letter_grade']; ?></span>
                                        <?php else: ?><span class="text-muted">--</span><?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        if ($g['total_score'] !== null):
                                        ?>
                                        <span class="badge bg-success"><i class="bi bi-check2-circle me-1"></i>Có kết quả</span>
                                        <?php elseif (!empty($g['exam_date']) && strtotime($g['exam_date']) < strtotime('today')): ?>
                                        <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Chờ điểm</span>
                                        <?php elseif (!empty($g['exam_date'])): ?>
                                        <span class="badge bg-info text-dark"><i class="bi bi-calendar-event me-1"></i>Chưa thi</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary"><i class="bi bi-clock me-1"></i>Chưa có lịch</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small"><?php echo htmlspecialchars($g['note'] ?? ''); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="student-footer">&copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một</div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
</body>
</html>
