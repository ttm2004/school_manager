<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../academic/includes/academic_helpers.php';
requireRole('student');

$stmt = $conn->prepare("SELECT s.*, u.full_name FROM students s JOIN users u ON s.user_id=u.id WHERE s.user_id=?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    header('Location: /university/login.php?logout=1');
    exit();
}
requireNoTuitionLock((int)$student['id']);

$stmt = $conn->prepare(
    "SELECT DISTINCT sm.id, sm.semester_name, sm.school_year, sm.start_date, sm.end_date
     FROM student_subjects ss
     JOIN course_sections cs ON ss.course_section_id = cs.id
     JOIN semesters sm ON cs.semester_id = sm.id
     WHERE ss.student_id = ? AND ss.status IN ('registered','auto_enrolled')
     ORDER BY sm.school_year DESC, sm.id DESC"
);
$stmt->bind_param('i', $student['id']);
$stmt->execute();
$semesters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$selectedSem = (int)($_GET['semester_id'] ?? ($semesters[0]['id'] ?? 0));
$sections = [];
$selectedSemester = null;
foreach ($semesters as $sem) {
    if ((int)$sem['id'] === $selectedSem) {
        $selectedSemester = $sem;
        break;
    }
}

if ($selectedSem > 0) {
    $stmt = $conn->prepare(
        "SELECT ss.status AS reg_status,
                cs.id AS section_id, cs.section_code, cs.room, cs.day_sessions, cs.start_date, cs.end_date,
                s.subject_code, s.subject_name, s.credits,
                COALESCE(NULLIF(s.total_periods,0), s.theory_periods + s.practice_periods, s.credits * 15, 45) AS total_periods,
                COALESCE(u.full_name, 'Chưa phân công') AS teacher_name,
                COALESCE(t.degree, '') AS degree,
                cl.class_code, cl.class_name
         FROM student_subjects ss
         JOIN course_sections cs ON ss.course_section_id = cs.id
         JOIN subjects s ON cs.subject_id = s.id
         LEFT JOIN teachers t ON cs.teacher_id = t.id
         LEFT JOIN users u ON t.user_id = u.id
         LEFT JOIN classes cl ON cs.class_id = cl.id
         WHERE ss.student_id = ?
           AND ss.status IN ('registered','auto_enrolled')
           AND cs.semester_id = ?
         ORDER BY cs.start_date IS NULL, cs.start_date, s.subject_name, cs.section_code"
    );
    $stmt->bind_param('ii', $student['id'], $selectedSem);
    $stmt->execute();
    $sections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$dayNames = [2=>'Thứ 2',3=>'Thứ 3',4=>'Thứ 4',5=>'Thứ 5',6=>'Thứ 6',7=>'Thứ 7',8=>'Chủ nhật'];
$sessionNames = ['sang'=>'Sáng','chieu'=>'Chiều','toi'=>'Tối'];
function semesterTkbFormatSessions(?string $value, array $dayNames, array $sessionNames): string
{
    $items = academicScheduleParseDaySessions($value);
    if (!$items) return 'Chưa xếp';
    $labels = [];
    foreach ($items as $item) {
        $labels[] = ($dayNames[(int)$item['day']] ?? ('Ngày ' . $item['day'])) . ' ' . ($sessionNames[$item['session']] ?? $item['session']);
    }
    return implode(', ', $labels);
}

$totalCredits = array_sum(array_map(static fn($row) => (int)$row['credits'], $sections));
$pageTitle = 'Thời khóa biểu học kỳ';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thời khóa biểu học kỳ - Sinh viên</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/university/assets/css/style.css">
    <style>
        body{background:#eef3f8}
        .semester-shell{max-width:1280px;margin:0 auto;padding:18px}
        .summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:14px}
        .summary-card{background:#fff;border:1px solid #dde3ee;border-radius:8px;padding:14px;box-shadow:0 6px 18px rgba(13,45,107,.06)}
        .summary-card .label{font-size:.72rem;text-transform:uppercase;color:#667085;font-weight:700}
        .summary-card .value{font-size:1.15rem;color:#0d2d6b;font-weight:800}
        .course-card{background:#fff;border:1px solid #dde3ee;border-radius:8px;padding:14px 16px;margin-bottom:10px;box-shadow:0 5px 16px rgba(13,45,107,.05)}
        .course-card .code{font-weight:800;color:#0d2d6b}
        .meta{font-size:.86rem;color:#475467}
        .pill{display:inline-flex;align-items:center;gap:5px;border:1px solid #d7dfec;background:#f8fafc;border-radius:999px;padding:4px 9px;font-size:.78rem;margin:3px 4px 3px 0}
        @media(max-width:768px){.summary-grid{grid-template-columns:1fr}.semester-shell{padding:10px}}
    </style>
</head>
<body>
<div class="student-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="student-main">
        <div class="student-topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary d-lg-none" onclick="document.querySelector('.student-sidebar').classList.toggle('show')"><i class="bi bi-list fs-5"></i></button>
                <span class="fw-bold text-navy"><i class="bi bi-calendar-range me-2"></i>Thời khóa biểu học kỳ</span>
            </div>
            <div class="d-flex gap-2">
                <a href="/university/student/timetable.php<?php echo $selectedSem ? '?semester_id=' . (int)$selectedSem : ''; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-calendar3-week me-1"></i>Dạng tuần</a>
                <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>In</button>
            </div>
        </div>
        <div class="student-content">
            <div class="semester-shell">
                <div class="card mb-3">
                    <div class="card-body">
                        <form class="row g-2 align-items-end" method="get">
                            <div class="col-md-5">
                                <label class="form-label fw-semibold">Học kỳ</label>
                                <select name="semester_id" class="form-select" onchange="this.form.submit()">
                                    <?php foreach ($semesters as $sem): ?>
                                    <option value="<?php echo (int)$sem['id']; ?>" <?php echo (int)$sem['id'] === $selectedSem ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sem['semester_name'] . ' - ' . $sem['school_year']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-7 text-md-end">
                                <span class="text-muted small">Trang này hiển thị toàn bộ học phần đã đăng ký trong học kỳ, gồm ngày bắt đầu/kết thúc từng môn.</span>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="summary-grid">
                    <div class="summary-card"><div class="label">Tổng môn</div><div class="value"><?php echo count($sections); ?></div></div>
                    <div class="summary-card"><div class="label">Tổng tín chỉ</div><div class="value"><?php echo (int)$totalCredits; ?></div></div>
                    <div class="summary-card"><div class="label">Bắt đầu học kỳ</div><div class="value"><?php echo $selectedSemester && $selectedSemester['start_date'] ? date('d/m/Y', strtotime($selectedSemester['start_date'])) : '--'; ?></div></div>
                    <div class="summary-card"><div class="label">Kết thúc học kỳ</div><div class="value"><?php echo $selectedSemester && $selectedSemester['end_date'] ? date('d/m/Y', strtotime($selectedSemester['end_date'])) : '--'; ?></div></div>
                </div>

                <?php if (empty($sections)): ?>
                <div class="card"><div class="card-body text-center text-muted py-5">
                    <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
                    Chưa có học phần nào trong học kỳ này.
                </div></div>
                <?php else: foreach ($sections as $row):
                    $sessions = semesterTkbFormatSessions($row['day_sessions'] ?? '', $dayNames, $sessionNames);
                    $meetings = academicScheduleSectionDates(
                        $row['start_date'] ?? null,
                        $row['day_sessions'] ?? '',
                        (int)($row['total_periods'] ?? 45),
                        5,
                        $row['end_date'] ?? null
                    );
                    $displayStart = $meetings[0] ?? ($row['start_date'] ?? null);
                    $displayEnd = $meetings ? end($meetings) : ($row['end_date'] ?? null);
                    $teacher = trim(($row['degree'] ? $row['degree'] . '. ' : '') . $row['teacher_name']);
                ?>
                <div class="course-card">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <div>
                            <div class="code"><?php echo htmlspecialchars($row['subject_code']); ?> · <?php echo htmlspecialchars($row['section_code']); ?></div>
                            <h6 class="mb-2"><?php echo htmlspecialchars($row['subject_name']); ?></h6>
                        </div>
                        <div class="text-md-end">
                            <span class="badge bg-navy"><?php echo (int)$row['credits']; ?> tín chỉ</span>
                            <span class="badge bg-<?php echo $row['reg_status'] === 'auto_enrolled' ? 'success' : 'primary'; ?>"><?php echo $row['reg_status'] === 'auto_enrolled' ? 'Tự động HK1' : 'Đã đăng ký'; ?></span>
                        </div>
                    </div>
                    <div class="meta">
                        <span class="pill"><i class="bi bi-person-video3"></i><?php echo htmlspecialchars($teacher); ?></span>
                        <span class="pill"><i class="bi bi-door-open"></i><?php echo htmlspecialchars($row['room'] ?: 'Chưa xếp phòng'); ?></span>
                        <span class="pill"><i class="bi bi-clock"></i><?php echo htmlspecialchars($sessions); ?></span>
                        <span class="pill"><i class="bi bi-people"></i><?php echo htmlspecialchars(($row['class_code'] ?? '') ?: 'Lớp chung khóa'); ?></span>
                    </div>
                    <div class="row g-2 mt-2 small">
                        <div class="col-md-3"><span class="text-muted">Buổi đầu:</span><br><strong><?php echo $displayStart ? date('d/m/Y', strtotime($displayStart)) : '--'; ?></strong></div>
                        <div class="col-md-3"><span class="text-muted">Buổi cuối:</span><br><strong><?php echo $displayEnd ? date('d/m/Y', strtotime($displayEnd)) : '--'; ?></strong></div>
                        <div class="col-md-3"><span class="text-muted">Số buổi dự kiến:</span><br><strong><?php echo count($meetings); ?> buổi</strong></div>
                        <div class="col-md-3"><span class="text-muted">Tổng số tiết:</span><br><strong><?php echo (int)$row['total_periods']; ?> tiết</strong></div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <div class="student-footer">&copy; <?php echo date('Y'); ?> Trường Đại học Thủ Dầu Một</div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
</body>
</html>
