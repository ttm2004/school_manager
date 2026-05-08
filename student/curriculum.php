<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('student');

$stmt = $conn->prepare("
    SELECT s.*, u.full_name,
           COALESCE(c.class_name, '') as class_name,
           COALESCE(m.id, 0) as major_id,
           COALESCE(m.major_name, 'Chưa xác định') as major_name,
           COALESCE(m.major_code, '') as major_code,
           COALESCE(m.total_credits, 120) as total_credits,
           m.description as major_desc,
           COALESCE(f.faculty_name, '') as faculty_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN majors m ON c.major_id = m.id
    LEFT JOIN faculties f ON m.faculty_id = f.id
    WHERE s.user_id = ?
");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) { header('Location: /university/login.php?logout=1'); exit(); }

// Nếu SV chưa được gán lớp/ngành
if (empty($student['major_id'])) {
    include '../includes/header.php' ;
    // dùng layout sinh viên đơn giản
    echo '<div style="max-width:600px;margin:80px auto;text-align:center;">';
    echo '<i class="bi bi-exclamation-triangle-fill text-warning" style="font-size:3rem;"></i>';
    echo '<h4 class="mt-3">Chưa được gán ngành đào tạo</h4>';
    echo '<p class="text-muted">Tài khoản của bạn chưa được gán vào lớp học hoặc ngành đào tạo.<br>Vui lòng liên hệ phòng đào tạo để được hỗ trợ.</p>';
    echo '<a href="/university/student/index.php" class="btn btn-navy mt-2"><i class="bi bi-arrow-left me-1"></i>Quay lại trang chủ</a>';
    echo '</div>';
    include '../includes/footer.php';
    exit();
}

// Lấy chương trình đào tạo của ngành sinh viên
// Tự động thêm cột nếu chưa có
foreach (["subject_type ENUM('required','elective','general') NOT NULL DEFAULT 'required'", "semester_order TINYINT NOT NULL DEFAULT 1"] as $colDef) {
    $colName = explode(' ', $colDef)[0];
    $chk = $conn->query("SHOW COLUMNS FROM subjects LIKE '$colName'");
    if ($chk && $chk->num_rows == 0) {
        $conn->query("ALTER TABLE subjects ADD COLUMN $colDef");
    }
}

$stmt = $conn->prepare("
    SELECT * FROM subjects
    WHERE major_id = ?
    ORDER BY semester_order ASC, subject_type ASC, subject_name ASC
");
$stmt->bind_param('i', $student['major_id']);
$stmt->execute();
$allSubjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Lấy các môn sinh viên đã học (có điểm hoặc đang học)
$stmt = $conn->prepare("
    SELECT sub.id as subject_id, g.total_score, g.letter_grade, ss.status as reg_status
    FROM student_subjects ss
    JOIN course_sections cs ON ss.course_section_id = cs.id
    JOIN subjects sub ON cs.subject_id = sub.id
    LEFT JOIN grades g ON g.student_subject_id = ss.id
    WHERE ss.student_id = ?
");
$stmt->bind_param('i', $student['id']);
$stmt->execute();
$mySubjectsRaw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Map subject_id → trạng thái
$mySubjectMap = [];
foreach ($mySubjectsRaw as $ms) {
    $mySubjectMap[$ms['subject_id']] = $ms;
}

// Nhóm theo học kỳ
$bySemester = [];
foreach ($allSubjects as $s) {
    $bySemester[$s['semester_order']][] = $s;
}
ksort($bySemester);

// Thống kê
$totalRequired  = array_sum(array_column(array_filter($allSubjects, fn($s) => $s['subject_type']==='required'), 'credits'));
$completedCredits = 0;
foreach ($allSubjects as $s) {
    if (isset($mySubjectMap[$s['id']]) && ($mySubjectMap[$s['id']]['total_score'] ?? null) !== null) {
        if ($mySubjectMap[$s['id']]['total_score'] >= 4) $completedCredits += $s['credits'];
    }
}
$progress = $student['total_credits'] > 0 ? round($completedCredits / $student['total_credits'] * 100) : 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chương trình đào tạo - Sinh viên</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/university/assets/css/style.css">
    <style>
        .subject-row.done    { background: #f0fff4; }
        .subject-row.current { background: #fffbf0; }
        .subject-row.locked  { opacity: 0.6; }
        .progress-ring { width: 80px; height: 80px; }
    </style>
</head>
<body>
<div class="student-wrapper">
    <div class="student-sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon"><i class="bi bi-mortarboard-fill"></i></div>
            <div class="sidebar-brand-text">
                <div>Cổng Sinh viên</div>
                <small><?php echo htmlspecialchars($student['student_code']); ?></small>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="/university/student/index.php" class="sidebar-link"><i class="bi bi-speedometer2"></i> Tổng quan</a>
            <a href="/university/student/profile.php" class="sidebar-link"><i class="bi bi-person-fill"></i> Hồ sơ cá nhân</a>
            <a href="/university/student/curriculum.php" class="sidebar-link active"><i class="bi bi-journal-bookmark-fill"></i> Chương trình đào tạo</a>
            <a href="/university/student/register_subject.php" class="sidebar-link"><i class="bi bi-journal-plus"></i> Đăng ký học phần</a>
            <a href="/university/student/my_subjects.php" class="sidebar-link"><i class="bi bi-journal-check"></i> Học phần của tôi</a>
            <a href="/university/student/timetable.php" class="sidebar-link"><i class="bi bi-calendar3-week"></i> Thời khóa biểu</a>
            <a href="/university/student/exam_schedule.php" class="sidebar-link"><i class="bi bi-calendar-event-fill"></i> Lịch thi cuối kỳ</a>
            <a href="/university/student/grades.php" class="sidebar-link"><i class="bi bi-bar-chart-fill"></i> Kết quả học tập</a>
            <a href="/university/student/evaluation.php" class="sidebar-link"><i class="bi bi-star-fill"></i> Đánh giá giảng viên</a>
            <hr class="my-2">
            <a href="/university/index.php" class="sidebar-link"><i class="bi bi-globe"></i> Trang chủ</a>
            <a href="/university/login.php?logout=1" class="sidebar-link text-danger"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a>
        </nav>
    </div>

    <div class="student-main">
        <div class="student-topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary d-lg-none"
                        onclick="document.querySelector('.student-sidebar').classList.toggle('show')">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <span class="fw-bold text-navy"><i class="bi bi-journal-bookmark-fill me-2"></i>Chương trình đào tạo</span>
            </div>
            <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>

        <div class="student-content">

            <!-- Thông tin ngành -->
            <div class="card mb-4 border-0" style="background: linear-gradient(135deg, var(--navy) 0%, #1a5276 100%); color:#fff;">
                <div class="card-body py-3">
                    <div class="row align-items-center">
                        <div class="col-md-7">
                            <div class="d-flex align-items-center gap-3">
                                <div style="width:50px;height:50px;background:rgba(255,255,255,0.15);border-radius:12px;display:flex;align-items:center;justify-content:center;">
                                    <i class="bi bi-mortarboard-fill fs-4"></i>
                                </div>
                                <div>
                                    <div class="fw-bold fs-5"><?php echo htmlspecialchars($student['major_name']); ?></div>
                                    <div style="opacity:0.8;font-size:0.85rem;">
                                        Mã ngành: <?php echo htmlspecialchars($student['major_code']); ?> &nbsp;|&nbsp;
                                        Khoa: <?php echo htmlspecialchars($student['faculty_name']); ?>
                                    </div>
                                    <div style="opacity:0.7;font-size:0.8rem;">
                                        Lớp: <?php echo htmlspecialchars($student['class_name']); ?> &nbsp;|&nbsp;
                                        MSSV: <?php echo htmlspecialchars($student['student_code']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5 mt-3 mt-md-0">
                            <div class="d-flex align-items-center gap-3">
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between mb-1" style="font-size:0.8rem;opacity:0.9;">
                                        <span>Tiến độ tích lũy</span>
                                        <span><?php echo $completedCredits; ?> / <?php echo $student['total_credits']; ?> TC</span>
                                    </div>
                                    <div class="progress" style="height:10px;background:rgba(255,255,255,0.2);">
                                        <div class="progress-bar bg-warning" style="width:<?php echo $progress; ?>%"></div>
                                    </div>
                                    <div style="font-size:0.75rem;opacity:0.7;margin-top:4px;"><?php echo $progress; ?>% hoàn thành</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chú thích -->
            <div class="d-flex gap-3 mb-3 flex-wrap">
                <span class="badge bg-success px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i>Đã hoàn thành</span>
                <span class="badge bg-warning text-dark px-3 py-2"><i class="bi bi-clock-fill me-1"></i>Đang học</span>
                <span class="badge bg-secondary px-3 py-2"><i class="bi bi-lock-fill me-1"></i>Chưa học</span>
                <span class="badge bg-danger px-3 py-2"><i class="bi bi-x-circle-fill me-1"></i>Không đạt</span>
            </div>

            <?php if (empty($allSubjects)): ?>
            <div class="card">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-inbox fs-2 d-block mb-2 text-warning"></i>
                    <h5>Chương trình đào tạo chưa được cập nhật</h5>
                    <p class="mb-0">Ngành <strong><?php echo htmlspecialchars($student['major_name']); ?></strong> chưa có dữ liệu môn học.</p>
                    <p class="text-muted small mt-1">Vui lòng liên hệ phòng đào tạo hoặc chờ admin cập nhật chương trình.</p>
                </div>
            </div>
            <?php else: ?>

            <?php
            $typeMap = ['required'=>['Bắt buộc','danger'],'elective'=>['Tự chọn','warning'],'general'=>['Đại cương','info']];
            $gradeColors = ['A'=>'success','B+'=>'primary','B'=>'info','C+'=>'warning','C'=>'warning','D+'=>'secondary','D'=>'secondary','F'=>'danger'];
            foreach ($bySemester as $semOrder => $semSubjects):
                $semCredits = array_sum(array_column($semSubjects, 'credits'));
                $semDone = 0;
                foreach ($semSubjects as $s) {
                    if (isset($mySubjectMap[$s['id']]) && ($mySubjectMap[$s['id']]['total_score'] ?? null) !== null && $mySubjectMap[$s['id']]['total_score'] >= 4) $semDone++;
                }
            ?>
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>
                        <i class="bi bi-calendar3 me-2"></i>
                        <strong>Học kỳ <?php echo $semOrder; ?></strong>
                    </span>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="badge bg-navy"><?php echo $semCredits; ?> TC &nbsp;|&nbsp; <?php echo count($semSubjects); ?> môn</span>
                        <?php if ($semDone == count($semSubjects) && count($semSubjects) > 0): ?>
                        <span class="badge bg-success"><i class="bi bi-check-all me-1"></i>Hoàn thành</span>
                        <?php elseif ($semDone > 0): ?>
                        <span class="badge bg-warning text-dark"><?php echo $semDone; ?>/<?php echo count($semSubjects); ?> môn</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Mã môn</th>
                                    <th>Tên môn học</th>
                                    <th class="text-center">TC</th>
                                    <th>Loại</th>
                                    <th class="text-center">Điểm</th>
                                    <th class="text-center">Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $idx=1; foreach ($semSubjects as $sub):
                                    $myData   = $mySubjectMap[$sub['id']] ?? null;
                                    $score    = $myData['total_score'] ?? null;
                                    $letter   = $myData['letter_grade'] ?? null;
                                    $regStat  = $myData['reg_status'] ?? null;
                                    $type     = $typeMap[$sub['subject_type']] ?? ['Khác','secondary'];
                                    $lc       = $gradeColors[$letter ?? ''] ?? 'secondary';

                                    if ($score !== null && $score >= 4)      $rowClass = 'subject-row done';
                                    elseif ($score !== null && $score < 4)   $rowClass = 'subject-row';
                                    elseif ($regStat === 'registered')       $rowClass = 'subject-row current';
                                    else                                     $rowClass = 'subject-row';
                                ?>
                                <tr class="<?php echo $rowClass; ?>">
                                    <td class="text-muted small"><?php echo $idx++; ?></td>
                                    <td><span class="badge bg-navy"><?php echo htmlspecialchars($sub['subject_code']); ?></span></td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($sub['subject_name']); ?></div>
                                        <?php if (!empty($sub['description'])): ?>
                                        <div class="text-muted small"><?php echo htmlspecialchars(mb_substr($sub['description'], 0, 60)); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-gold text-dark fw-bold"><?php echo $sub['credits']; ?></span>
                                    </td>
                                    <td><span class="badge bg-<?php echo $type[1]; ?>"><?php echo $type[0]; ?></span></td>
                                    <td class="text-center">
                                        <?php if ($score !== null): ?>
                                        <div class="fw-bold <?php echo $score>=5?'text-success':'text-danger'; ?>"><?php echo number_format($score,2); ?></div>
                                        <?php if ($letter): ?>
                                        <span class="badge bg-<?php echo $lc; ?>"><?php echo $letter; ?></span>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <span class="text-muted">--</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($score !== null && $score >= 4): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i>Đạt</span>
                                        <?php elseif ($score !== null && $score < 4): ?>
                                        <span class="badge bg-danger"><i class="bi bi-x-circle-fill me-1"></i>Không đạt</span>
                                        <?php elseif ($regStat === 'registered'): ?>
                                        <span class="badge bg-warning text-dark"><i class="bi bi-clock-fill me-1"></i>Đang học</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary"><i class="bi bi-lock-fill me-1"></i>Chưa học</span>
                                        <?php endif; ?>
                                    </td>
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
<?php include_once __DIR__ . "/../includes/analytics_widget.php"; ?>
</body>
</html>
