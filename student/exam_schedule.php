<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('student');

$stmt = $conn->prepare("SELECT s.*, u.full_name FROM students s JOIN users u ON s.user_id=u.id WHERE s.user_id=?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();
requireNoTuitionLock((int)($student['id'] ?? 0));

// Lấy lịch thi của sinh viên (qua các môn đã đăng ký)
// Bước 4 trong luồng: Hệ thống kiểm tra sinh viên đủ điều kiện thi
// Điều kiện: student_subjects.status = 'registered' (đã đăng ký và chưa hủy)
$stmt = $conn->prepare("
    SELECT f.*,
           cs.section_code, cs.room as cs_room,
           s.subject_name, s.credits,
           sm.semester_name, sm.school_year, sm.id as semester_id,
           u.full_name as teacher_name,
           ss.status as enrollment_status,
           CASE
               WHEN ss.status IN ('registered','auto_enrolled') THEN 1
               ELSE 0
           END as eligible_to_exam
    FROM final_exam_schedules f
    JOIN course_sections cs ON f.course_section_id = cs.id
    JOIN subjects s ON cs.subject_id = s.id
    JOIN semesters sm ON cs.semester_id = sm.id
    JOIN teachers t ON cs.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    JOIN student_subjects ss ON ss.course_section_id = cs.id
    WHERE ss.student_id = ? AND ss.status IN ('registered','auto_enrolled') AND f.status != 'cancelled'
    ORDER BY f.exam_date ASC, f.start_time ASC
");
$stmt->bind_param('i', $student['id']);
$stmt->execute();
$allExams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Đếm số môn không đủ điều kiện thi (đã đăng ký nhưng lịch thi bị hủy)
$ineligibleCount = 0;
$stmtCheck = $conn->prepare("
    SELECT COUNT(*) as cnt
    FROM student_subjects ss
    JOIN course_sections cs ON ss.course_section_id = cs.id
    JOIN final_exam_schedules f ON f.course_section_id = cs.id
    WHERE ss.student_id = ? AND ss.status IN ('registered','auto_enrolled') AND f.status = 'cancelled'
");
$stmtCheck->bind_param('i', $student['id']);
$stmtCheck->execute();
$ineligibleCount = $stmtCheck->get_result()->fetch_assoc()['cnt'] ?? 0;
$stmtCheck->close();

// Nhóm theo học kỳ
$bySemester = [];
foreach ($allExams as $e) {
    $key = $e['semester_id'];
    $bySemester[$key]['info']   = $e['semester_name'] . ' ' . $e['school_year'];
    $bySemester[$key]['exams'][] = $e;
}

$selectedSem = intval($_GET['semester_id'] ?? (array_key_first($bySemester) ?? 0));

// Thống kê cho học kỳ đang chọn
$upcoming = 0; $today = 0; $done = 0;
if (isset($bySemester[$selectedSem])) {
    foreach ($bySemester[$selectedSem]['exams'] as $e) {
        $ts = strtotime($e['exam_date']);
        if (date('Y-m-d') === $e['exam_date']) $today++;
        elseif ($ts > time()) $upcoming++;
        else $done++;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
    <title>Lịch thi cuối kỳ - Sinh viên</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/university/assets/css/style.css">
    <style>
        .exam-card {
            border-left: 4px solid;
            border-radius: 8px;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .exam-card:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,0.10); }
        .exam-card.today  { border-left-color: #f0a500; background: #fffbf0; }
        .exam-card.upcoming { border-left-color: #1976d2; background: #f0f7ff; }
        .exam-card.past   { border-left-color: #adb5bd; background: #f8f9fa; }
        .countdown-badge { font-size: 0.72rem; font-weight: 600; }
    </style>
</head>
<body>
<div class="student-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="student-main">
        <div class="student-topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary d-lg-none"
                        onclick="document.querySelector('.student-sidebar').classList.toggle('show')">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <span class="fw-bold text-navy"><i class="bi bi-calendar-event-fill me-2"></i>Lịch thi cuối kỳ</span>
            </div>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-printer me-1"></i>In lịch thi
                </button>
                <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>

        <div class="student-content">

            <!-- Chọn học kỳ -->
            <?php if (!empty($bySemester)): ?>
            <div class="d-flex gap-2 mb-4 flex-wrap">
                <?php foreach ($bySemester as $semId => $semData): ?>
                <a href="?semester_id=<?php echo $semId; ?>"
                   class="btn btn-<?php echo $semId==$selectedSem?'navy':'outline-secondary'; ?> btn-sm">
                    <i class="bi bi-calendar3 me-1"></i><?php echo htmlspecialchars($semData['info']); ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (empty($bySemester) || !isset($bySemester[$selectedSem])): ?>
            <div class="card">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
                    Chưa có lịch thi nào được xếp cho bạn.
                    <div class="small mt-1">Lịch thi sẽ hiển thị khi nhà trường công bố.</div>
                </div>
            </div>
            <?php else: ?>

            <!-- Thống kê nhanh -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body py-3">
                            <div class="fs-3 fw-bold text-warning"><?php echo $today; ?></div>
                            <div class="small text-muted">Thi hôm nay</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body py-3">
                            <div class="fs-3 fw-bold text-primary"><?php echo $upcoming; ?></div>
                            <div class="small text-muted">Sắp thi</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body py-3">
                            <div class="fs-3 fw-bold text-success"><?php echo $done; ?></div>
                            <div class="small text-muted">Đã thi</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body py-3">
                            <div class="fs-3 fw-bold <?php echo $ineligibleCount > 0 ? 'text-danger' : 'text-success'; ?>">
                                <?php echo $ineligibleCount > 0 ? $ineligibleCount : '✓'; ?>
                            </div>
                            <div class="small text-muted"><?php echo $ineligibleCount > 0 ? 'Lịch thi bị hủy' : 'Đủ điều kiện thi'; ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bảng lịch thi -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-table me-2"></i>Bảng lịch thi —
                    <strong><?php echo htmlspecialchars($bySemester[$selectedSem]['info']); ?></strong>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Môn học</th>
                                    <th>Ngày thi</th>
                                    <th>Giờ thi</th>
                                    <th>Phòng thi</th>
                                    <th>Hình thức</th>
                                    <th>Điều kiện thi</th>
                                    <th>Giảng viên</th>
                                    <th>Ghi chú</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $formMap = [
                                    'Tự luận'    => ['secondary', 'bi-pencil-fill'],
                                    'Trắc nghiệm'=> ['info',      'bi-ui-checks'],
                                    'Tiểu luận'  => ['warning',   'bi-file-earmark-text-fill'],
                                ];
                                $idx = 1;
                                foreach ($bySemester[$selectedSem]['exams'] as $e):
                                    $examTs   = strtotime($e['exam_date']);
                                    $isToday  = date('Y-m-d') === $e['exam_date'];
                                    $isPast   = $examTs < strtotime('today');
                                    $daysLeft = (int)ceil(($examTs - strtotime('today')) / 86400);
                                    $fm = $formMap[$e['exam_form']] ?? ['secondary', 'bi-question'];
                                ?>
                                <tr class="<?php echo $isToday ? 'table-warning' : ($isPast ? 'table-light text-muted' : ''); ?>">
                                    <td class="text-muted small"><?php echo $idx++; ?></td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($e['subject_name']); ?></div>
                                        <span class="badge bg-navy" style="font-size:0.7rem"><?php echo htmlspecialchars($e['section_code']); ?></span>
                                        <span class="badge bg-secondary ms-1" style="font-size:0.7rem"><?php echo $e['credits']; ?> TC</span>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo date('d/m/Y', $examTs); ?></div>
                                        <div class="small text-muted"><?php
                                            $days = ['Sunday'=>'Chủ nhật','Monday'=>'Thứ 2','Tuesday'=>'Thứ 3',
                                                     'Wednesday'=>'Thứ 4','Thursday'=>'Thứ 5','Friday'=>'Thứ 6','Saturday'=>'Thứ 7'];
                                            echo $days[date('l', $examTs)] ?? date('l', $examTs);
                                        ?></div>
                                        <?php if ($isToday): ?>
                                        <span class="badge bg-warning text-dark countdown-badge">Hôm nay!</span>
                                        <?php elseif (!$isPast && $daysLeft <= 7): ?>
                                        <span class="badge bg-danger countdown-badge">Còn <?php echo $daysLeft; ?> ngày</span>
                                        <?php elseif (!$isPast): ?>
                                        <span class="badge bg-info text-dark countdown-badge">Còn <?php echo $daysLeft; ?> ngày</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary countdown-badge">Đã thi</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold"><?php echo substr($e['start_time'],0,5); ?> – <?php echo substr($e['end_time'],0,5); ?></td>
                                    <td class="fw-bold text-navy"><?php echo htmlspecialchars($e['room'] ?: '--'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $fm[0]; ?>">
                                            <i class="bi <?php echo $fm[1]; ?> me-1"></i><?php echo htmlspecialchars($e['exam_form']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($e['eligible_to_exam']): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i>Đủ điều kiện</span>
                                        <?php else: ?>
                                        <span class="badge bg-danger"><i class="bi bi-x-circle-fill me-1"></i>Không đủ ĐK</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><?php echo htmlspecialchars($e['teacher_name']); ?></td>
                                    <td class="small text-muted"><?php echo $e['note'] ? htmlspecialchars($e['note']) : '<span class="fst-italic">--</span>'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Cards lịch thi (view thân thiện) -->
            <h6 class="fw-bold text-navy mb-3"><i class="bi bi-grid me-2"></i>Xem theo dạng thẻ</h6>
            <div class="row g-3">
                <?php foreach ($bySemester[$selectedSem]['exams'] as $e):
                    $examTs  = strtotime($e['exam_date']);
                    $isToday = date('Y-m-d') === $e['exam_date'];
                    $isPast  = $examTs < strtotime('today');
                    $daysLeft = (int)ceil(($examTs - strtotime('today')) / 86400);
                    $cardClass = $isToday ? 'today' : ($isPast ? 'past' : 'upcoming');
                    $fm = $formMap[$e['exam_form']] ?? ['secondary', 'bi-question'];
                    $days = ['Sunday'=>'CN','Monday'=>'T2','Tuesday'=>'T3',
                             'Wednesday'=>'T4','Thursday'=>'T5','Friday'=>'T6','Saturday'=>'T7'];
                    $dayLabel = $days[date('l', $examTs)] ?? '';
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card exam-card <?php echo $cardClass; ?> h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <div class="fw-bold" style="font-size:0.95rem"><?php echo htmlspecialchars($e['subject_name']); ?></div>
                                    <span class="badge bg-navy" style="font-size:0.7rem"><?php echo htmlspecialchars($e['section_code']); ?></span>
                                    <span class="badge bg-secondary ms-1" style="font-size:0.7rem"><?php echo $e['credits']; ?> TC</span>
                                </div>
                                <div class="text-center ms-2" style="min-width:52px">
                                    <div class="fw-bold fs-4 lh-1" style="color:<?php echo $isToday?'#f0a500':($isPast?'#adb5bd':'#1a3a6b'); ?>">
                                        <?php echo date('d', $examTs); ?>
                                    </div>
                                    <div class="small text-muted"><?php echo date('M', $examTs); ?></div>
                                    <div class="small fw-bold text-muted"><?php echo $dayLabel; ?></div>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-clock me-1"></i><?php echo substr($e['start_time'],0,5); ?> – <?php echo substr($e['end_time'],0,5); ?>
                                </span>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-door-open me-1"></i><?php echo htmlspecialchars($e['room'] ?: '--'); ?>
                                </span>
                                <span class="badge bg-<?php echo $fm[0]; ?>">
                                    <i class="bi <?php echo $fm[1]; ?> me-1"></i><?php echo htmlspecialchars($e['exam_form']); ?>
                                </span>
                            </div>
                            <div class="small text-muted mb-1">
                                <i class="bi bi-person-fill me-1"></i><?php echo htmlspecialchars($e['teacher_name']); ?>
                            </div>
                            <?php if ($e['note']): ?>
                            <div class="small text-muted">
                                <i class="bi bi-info-circle me-1"></i><?php echo htmlspecialchars($e['note']); ?>
                            </div>
                            <?php endif; ?>
                            <div class="mt-2">
                                <?php if ($isToday): ?>
                                <span class="badge bg-warning text-dark">🔔 Thi hôm nay!</span>
                                <?php elseif (!$isPast && $daysLeft <= 3): ?>
                                <span class="badge bg-danger">⚠ Còn <?php echo $daysLeft; ?> ngày</span>
                                <?php elseif (!$isPast && $daysLeft <= 7): ?>
                                <span class="badge bg-warning text-dark">Còn <?php echo $daysLeft; ?> ngày</span>
                                <?php elseif (!$isPast): ?>
                                <span class="badge bg-info text-dark">Còn <?php echo $daysLeft; ?> ngày</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">✓ Đã thi</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

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

