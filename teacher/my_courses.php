<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('teacher');

$stmt = $conn->prepare("SELECT t.*, u.full_name FROM teachers t JOIN users u ON t.user_id=u.id WHERE t.user_id=?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

$filter_sem = intval($_GET['semester_id'] ?? 0);
$view_section = intval($_GET['view_students'] ?? 0); // xem danh sách SV của lớp nào
$semesters = $conn->query("SELECT * FROM semesters ORDER BY school_year DESC, id DESC");

$whereSQL = $filter_sem ? "AND cs.semester_id = " . intval($filter_sem) : "";
$stmt = $conn->prepare("
    SELECT cs.*, s.subject_name, s.credits, s.subject_type, s.subject_code,
           sm.semester_name, sm.school_year, sm.status as sem_status,
           cs.day_sessions, cs.schedule_data, cs.start_date, cs.end_date
    FROM course_sections cs
    JOIN subjects s ON cs.subject_id = s.id
    JOIN semesters sm ON cs.semester_id = sm.id
    WHERE cs.teacher_id = ? $whereSQL
    ORDER BY sm.school_year DESC, sm.semester_name, s.subject_name
");
$stmt->bind_param('i', $teacher['id']);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Nếu xem danh sách sinh viên của một lớp cụ thể
$sectionStudents = [];
$viewSectionInfo = null;
if ($view_section) {
    // Kiểm tra lớp này có thuộc giảng viên không
    $chk = $conn->prepare("SELECT cs.*, s.subject_name, s.subject_code, sm.semester_name, sm.school_year
        FROM course_sections cs
        JOIN subjects s ON cs.subject_id = s.id
        JOIN semesters sm ON cs.semester_id = sm.id
        WHERE cs.id = ? AND cs.teacher_id = ?");
    $chk->bind_param('ii', $view_section, $teacher['id']);
    $chk->execute();
    $viewSectionInfo = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ($viewSectionInfo) {
        $svStmt = $conn->prepare("
            SELECT ss.id as ss_id, ss.status as reg_status, ss.register_date,
                   st.student_code, st.gender,
                   u.full_name, u.email,
                   cl.class_name,
                   g.process_score, g.midterm_score, g.final_score, g.total_score, g.letter_grade
            FROM student_subjects ss
            JOIN students st ON ss.student_id = st.id
            JOIN users u ON st.user_id = u.id
            LEFT JOIN classes cl ON st.class_id = cl.id
            LEFT JOIN grades g ON g.student_subject_id = ss.id
            WHERE ss.course_section_id = ? AND ss.status = 'registered'
            ORDER BY u.full_name
        ");
        $svStmt->bind_param('i', $view_section);
        $svStmt->execute();
        $sectionStudents = $svStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $svStmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lớp học phần - Giảng viên</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/university/assets/css/style.css">
</head>
<body>
<div class="student-wrapper">
    <div class="student-sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon"><i class="bi bi-person-badge-fill"></i></div>
            <div class="sidebar-brand-text">
                <div>Cổng Giảng viên</div>
                <small><?php echo htmlspecialchars($teacher['teacher_code']); ?></small>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="/university/teacher/index.php" class="sidebar-link"><i class="bi bi-speedometer2"></i> Tổng quan</a>
            <a href="/university/teacher/profile.php" class="sidebar-link"><i class="bi bi-person-fill"></i> Hồ sơ cá nhân</a>
            <a href="/university/teacher/my_courses.php" class="sidebar-link active"><i class="bi bi-journal-text"></i> Lớp học phần</a>
            <a href="/university/teacher/timetable.php" class="sidebar-link"><i class="bi bi-calendar3-week"></i> Thời khóa biểu</a>
            <a href="/university/teacher/exam_schedule.php" class="sidebar-link"><i class="bi bi-calendar-event-fill"></i> Lịch thi cuối kỳ</a>
            <a href="/university/teacher/grades.php" class="sidebar-link"><i class="bi bi-bar-chart-fill"></i> Nhập điểm</a>
            <a href="/university/teacher/evaluation.php" class="sidebar-link"><i class="bi bi-star-fill"></i> Kết quả đánh giá</a>
            <hr class="my-2">
            <a href="/university/index.php" class="sidebar-link"><i class="bi bi-globe"></i> Trang chủ</a>
            <a href="/university/login.php?logout=1" class="sidebar-link text-danger"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a>
        </nav>
    </div>
    <div class="student-main">
        <div class="student-topbar">
            <span class="fw-bold text-navy">Lớp học phần của tôi</span>
            <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i></a>
        </div>
        <div class="student-content">

            <!-- Bộ lọc học kỳ -->
            <div class="card mb-4">
                <div class="card-body py-2">
                    <form method="GET" class="d-flex gap-2 align-items-end flex-wrap">
                        <div>
                            <label class="form-label small mb-1">Lọc theo học kỳ</label>
                            <select name="semester_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Tất cả học kỳ</option>
                                <?php if ($semesters): while ($sem = $semesters->fetch_assoc()): ?>
                                <option value="<?php echo $sem['id']; ?>" <?php echo $filter_sem==$sem['id']?'selected':''; ?>>
                                    <?php echo htmlspecialchars($sem['semester_name'] . ' ' . $sem['school_year']); ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <?php if ($filter_sem): ?>
                        <a href="my_courses.php" class="btn btn-outline-secondary btn-sm align-self-end">
                            <i class="bi bi-x-lg me-1"></i>Xóa lọc
                        </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Danh sách lớp học phần -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-journal-text me-2"></i>Danh sách lớp học phần
                    <span class="badge bg-navy ms-2"><?php echo count($courses); ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Mã HP</th>
                                    <th>Môn học</th>
                                    <th>Học kỳ</th>
                                    <th>Lịch học</th>
                                    <th>Phòng</th>
                                    <th>Sĩ số</th>
                                    <th>Trạng thái</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($courses)): foreach ($courses as $c): ?>
                                <tr class="<?php echo $view_section==$c['id']?'table-primary':''; ?>">
                                    <td class="fw-bold text-navy small"><?php echo htmlspecialchars($c['section_code']); ?></td>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($c['subject_name']); ?></div>
                                        <div class="d-flex gap-1 mt-1">
                                            <span class="badge bg-navy"><?php echo $c['credits']; ?> TC</span>
                                            <?php if (!empty($c['subject_type'])): ?>
                                            <span class="badge bg-<?php echo $c['subject_type']=='Bắt buộc'?'danger':'info'; ?> small">
                                                <?php echo htmlspecialchars($c['subject_type']); ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="small text-muted"><?php echo htmlspecialchars($c['semester_name'] . ' ' . $c['school_year']); ?></td>
                                    <td class="small">
                                        <?php
                                        $SESSION_LABEL = ['sang'=>'Sáng','chieu'=>'Chiều','toi'=>'Tối'];
                                        $SESSION_COLOR = ['sang'=>'#f57c00','chieu'=>'#1976d2','toi'=>'#7b1fa2'];
                                        $SESSION_TIME  = ['sang'=>'7:00–11:30','chieu'=>'12:30–17:00','toi'=>'17:30–22:00'];
                                        $DAY_LABEL     = [2=>'T2',3=>'T3',4=>'T4',5=>'T5',6=>'T6',7=>'T7',8=>'CN'];
                                        $dayMap = [];
                                        if (!empty($c['day_sessions'])) {
                                            foreach (explode(',', $c['day_sessions']) as $p) {
                                                $a = explode(':', trim($p));
                                                if (count($a)==2) $dayMap[(int)$a[0]] = $a[1];
                                            }
                                        } elseif (!empty($c['schedule_data'])) {
                                            $slots = json_decode($c['schedule_data'], true) ?: [];
                                            foreach ($slots as $sl) $dayMap[(int)$sl['day']] = $sl['session'];
                                        }
                                        if (!empty($dayMap)):
                                        ?>
                                        <div class="d-flex flex-wrap gap-1 mb-1">
                                            <?php foreach ($dayMap as $d => $s): ?>
                                            <span class="badge"
                                                  style="background:<?php echo $SESSION_COLOR[$s]??'#666'; ?>; font-size:0.75rem;"
                                                  title="<?php echo $SESSION_TIME[$s]??''; ?>">
                                                <?php echo $DAY_LABEL[$d]??'N'.$d; ?>
                                                <?php echo $SESSION_LABEL[$s]??$s; ?>
                                            </span>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="text-muted" style="font-size:0.7rem">
                                            <?php echo count($dayMap); ?> buổi/tuần × 5 tiết
                                        </div>
                                        <?php
                                        $sdStart = !empty($c['start_date']) ? date('d/m/Y', strtotime($c['start_date'])) : null;
                                        $sdEnd   = !empty($c['end_date'])   ? date('d/m/Y', strtotime($c['end_date']))   : null;
                                        if ($sdStart || $sdEnd): ?>
                                        <div class="text-muted" style="font-size:0.7rem; margin-top:2px;">
                                            <i class="bi bi-calendar-range"></i>
                                            <?php echo $sdStart ?? '--'; ?> → <?php echo $sdEnd ?? '--'; ?>
                                        </div>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <span class="text-muted fst-italic small">Chưa có lịch</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><?php echo htmlspecialchars($c['room'] ?: '--'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $c['current_students']>=$c['max_students']?'danger':'success'; ?>">
                                            <?php echo $c['current_students']; ?>/<?php echo $c['max_students']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $c['status']=='open'?'success':($c['status']=='full'?'warning':'secondary'); ?>">
                                            <?php echo $c['status']=='open'?'Mở':($c['status']=='full'?'Đầy':'Đóng'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <a href="?<?php echo $filter_sem?'semester_id='.$filter_sem.'&':''; ?>view_students=<?php echo $c['id']; ?>"
                                               class="btn btn-sm btn-outline-navy <?php echo $view_section==$c['id']?'active':''; ?>"
                                               title="Xem danh sách sinh viên">
                                                <i class="bi bi-people-fill"></i>
                                                <span class="d-none d-md-inline ms-1">SV</span>
                                            </a>
                                            <a href="/university/teacher/grades.php?section_id=<?php echo $c['id']; ?>"
                                               class="btn btn-sm btn-gold" title="Nhập điểm">
                                                <i class="bi bi-pencil-square"></i>
                                                <span class="d-none d-md-inline ms-1">Điểm</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                    Chưa được phân công lớp học phần nào
                                </td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Danh sách sinh viên của lớp được chọn -->
            <?php if ($view_section && $viewSectionInfo): ?>
            <div class="card" id="studentList">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-people-fill me-2"></i>
                        Danh sách sinh viên đăng ký —
                        <strong><?php echo htmlspecialchars($viewSectionInfo['section_code']); ?></strong>
                        <span class="text-muted small ms-1"><?php echo htmlspecialchars($viewSectionInfo['subject_name']); ?></span>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="badge bg-navy"><?php echo count($sectionStudents); ?> sinh viên</span>
                        <a href="my_courses.php<?php echo $filter_sem?'?semester_id='.$filter_sem:''; ?>"
                           class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($sectionStudents)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Mã SV</th>
                                    <th>Họ và tên</th>
                                    <th>Lớp</th>
                                    <th>Ngày đăng ký</th>
                                    <th>QT</th>
                                    <th>GK</th>
                                    <th>CK</th>
                                    <th>Tổng kết</th>
                                    <th>Xếp loại</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sectionStudents as $i => $sv): ?>
                                <tr>
                                    <td class="text-muted small"><?php echo $i + 1; ?></td>
                                    <td class="fw-bold text-navy small"><?php echo htmlspecialchars($sv['student_code']); ?></td>
                                    <td>
                                        <div class="fw-semibold small"><?php echo htmlspecialchars($sv['full_name']); ?></div>
                                        <div class="text-muted" style="font-size:11px"><?php echo htmlspecialchars($sv['email'] ?? ''); ?></div>
                                    </td>
                                    <td class="small text-muted"><?php echo htmlspecialchars($sv['class_name'] ?? '--'); ?></td>
                                    <td class="small text-muted">
                                        <?php echo $sv['register_date'] ? date('d/m/Y', strtotime($sv['register_date'])) : '--'; ?>
                                    </td>
                                    <td class="small text-center">
                                        <?php echo $sv['process_score'] !== null ? number_format($sv['process_score'], 1) : '<span class="text-muted">--</span>'; ?>
                                    </td>
                                    <td class="small text-center">
                                        <?php echo $sv['midterm_score'] !== null ? number_format($sv['midterm_score'], 1) : '<span class="text-muted">--</span>'; ?>
                                    </td>
                                    <td class="small text-center">
                                        <?php echo $sv['final_score'] !== null ? number_format($sv['final_score'], 1) : '<span class="text-muted">--</span>'; ?>
                                    </td>
                                    <td class="small text-center fw-bold">
                                        <?php if ($sv['total_score'] !== null): ?>
                                            <span class="text-<?php echo $sv['total_score'] >= 5 ? 'success' : 'danger'; ?>">
                                                <?php echo number_format($sv['total_score'], 2); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">--</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($sv['letter_grade']): ?>
                                            <?php
                                            $gradeColor = match($sv['letter_grade']) {
                                                'A+', 'A' => 'success',
                                                'B+', 'B' => 'primary',
                                                'C+', 'C' => 'info',
                                                'D+', 'D' => 'warning',
                                                default   => 'danger'
                                            };
                                            ?>
                                            <span class="badge bg-<?php echo $gradeColor; ?>"><?php echo htmlspecialchars($sv['letter_grade']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">Chưa có</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 border-top d-flex gap-3 flex-wrap">
                        <a href="/university/teacher/grades.php?section_id=<?php echo $view_section; ?>"
                           class="btn btn-gold btn-sm">
                            <i class="bi bi-pencil-square me-1"></i>Nhập điểm cho lớp này
                        </a>
                        <span class="text-muted small align-self-center">
                            Tổng: <?php echo count($sectionStudents); ?> sinh viên đã đăng ký
                        </span>
                    </div>
                    <?php else: ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-person-x fs-2 d-block mb-2"></i>
                        Chưa có sinh viên nào đăng ký lớp học phần này
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif ($view_section && !$viewSectionInfo): ?>
            <div class="alert alert-danger">Lớp học phần không tồn tại hoặc không thuộc quyền quản lý của bạn.</div>
            <?php endif; ?>

        </div><!-- /.student-content -->
        <div class="student-footer">&copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một</div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<script>
// Tự động scroll xuống danh sách SV nếu đang xem
<?php if ($view_section && $viewSectionInfo): ?>
document.addEventListener('DOMContentLoaded', function() {
    const el = document.getElementById('studentList');
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
});
<?php endif; ?>
</script>
<?php include_once __DIR__ . "/../includes/analytics_widget.php"; ?>
</body>
</html>
