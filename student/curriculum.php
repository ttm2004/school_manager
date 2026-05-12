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
// Ưu tiên lấy từ bảng curriculum (có year_label, semester_label) nếu có dữ liệu
$hasCurr = 0;
$chkCurr = $conn->prepare("SELECT COUNT(*) AS c FROM curriculum WHERE major_id = ? AND deleted_at IS NULL");
$chkCurr->bind_param('i', $student['major_id']);
$chkCurr->execute();
$hasCurr = (int)($chkCurr->get_result()->fetch_assoc()['c'] ?? 0);
$chkCurr->close();

if ($hasCurr > 0) {
    // Lấy từ curriculum JOIN subjects — có đầy đủ year_label, semester_label, total_periods, is_mandatory
    $stmt = $conn->prepare("
        SELECT s.id, s.subject_code, s.subject_name, s.credits,
               s.theory_periods, s.practice_periods,
               COALESCE(s.total_periods, s.theory_periods + s.practice_periods) AS total_periods,
               s.description,
               c.suggested_semester AS semester_order,
               c.semester_label, c.year_label,
               c.subject_type AS subject_type,
               CASE c.subject_type
                   WHEN 'required' THEN 'required'
                   WHEN 'elective' THEN 'elective'
                   WHEN 'general'  THEN 'general'
                   ELSE 'required'
               END AS subject_type_new,
               CASE WHEN c.subject_type IN ('required','general') THEN 1 ELSE 0 END AS is_mandatory
        FROM curriculum c
        JOIN subjects s ON c.subject_id = s.id
        WHERE c.major_id = ? AND c.deleted_at IS NULL
        ORDER BY c.year_label ASC, c.suggested_semester ASC, s.subject_name ASC
    ");
    $stmt->bind_param('i', $student['major_id']);
    $stmt->execute();
    $allSubjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    // Fallback: lấy từ subjects trực tiếp (dữ liệu cũ chưa có curriculum)
    // Tự động thêm cột nếu chưa có
    foreach (["subject_type ENUM('required','elective','general') NOT NULL DEFAULT 'required'", "semester_order TINYINT NOT NULL DEFAULT 1"] as $colDef) {
        $colName = explode(' ', $colDef)[0];
        $chk = $conn->query("SHOW COLUMNS FROM subjects LIKE '$colName'");
        if ($chk && $chk->num_rows == 0) {
            $conn->query("ALTER TABLE subjects ADD COLUMN $colDef");
        }
    }
    $stmt = $conn->prepare("
        SELECT *, semester_order, NULL AS semester_label, NULL AS year_label,
               subject_type AS subject_type_new,
               COALESCE(total_periods, theory_periods + practice_periods) AS total_periods
        FROM subjects
        WHERE major_id = ?
        ORDER BY semester_order ASC, subject_type ASC, subject_name ASC
    ");
    $stmt->bind_param('i', $student['major_id']);
    $stmt->execute();
    $allSubjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

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

// Nhóm theo năm học + học kỳ (key: year_label|semOrder|semLabel)
$bySemester = [];
foreach ($allSubjects as $s) {
    $yearLabel = $s['year_label'] ?? '';
    $semLabel  = $s['semester_label'] ?? '';
    $semOrder  = (int)($s['semester_order'] ?? 1);
    $key = ($yearLabel ?: '0000') . '|' . sprintf('%02d', $semOrder) . '|' . $semLabel;
    $bySemester[$key][] = $s;
}
ksort($bySemester);

// Thống kê
$totalRequired  = array_sum(array_column(array_filter($allSubjects, fn($s) => in_array($s['subject_type_new'] ?? $s['subject_type'], ['required','general'])), 'credits'));
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
    <meta name="csrf-token" content="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
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
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

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
            $prevYear = null;
            foreach ($bySemester as $key => $semSubjects):
                [$yearLabel, $semOrderPad, $semLabel] = explode('|', $key);
                $semOrder = (int)$semOrderPad;
                $semCredits = array_sum(array_column($semSubjects, 'credits'));
                $semDone = 0;
                foreach ($semSubjects as $s) {
                    if (isset($mySubjectMap[$s['id']]) && ($mySubjectMap[$s['id']]['total_score'] ?? null) !== null && $mySubjectMap[$s['id']]['total_score'] >= 4) $semDone++;
                }
                // Header năm học
                if ($yearLabel && $yearLabel !== $prevYear):
                    $prevYear = $yearLabel;
            ?>
            <div class="d-flex align-items-center gap-2 mb-2 mt-3">
                <i class="bi bi-calendar-range-fill text-navy"></i>
                <strong class="text-navy">Năm học <?php echo htmlspecialchars($yearLabel); ?></strong>
                <hr class="flex-grow-1 my-0">
            </div>
            <?php endif; ?>
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>
                        <i class="bi bi-calendar3 me-2"></i>
                        <strong><?php echo htmlspecialchars($semLabel ?: 'Học kỳ '.$semOrder); ?></strong>
                        <?php if($yearLabel): ?><span class="text-muted ms-2 small"><?php echo htmlspecialchars($yearLabel); ?></span><?php endif; ?>
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
                                    <th class="text-center">LT</th>
                                    <th class="text-center">TH</th>
                                    <th class="text-center">Tổng tiết</th>
                                    <th>Loại</th>
                                    <th class="text-center">Bắt buộc</th>
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
                                    $typeKey  = $sub['subject_type_new'] ?? $sub['subject_type'] ?? 'required';
                                    $type     = $typeMap[$typeKey] ?? ['Khác','secondary'];
                                    $lc       = $gradeColors[$letter ?? ''] ?? 'secondary';
                                    $lt       = (int)($sub['theory_periods'] ?? 0);
                                    $th       = (int)($sub['practice_periods'] ?? 0);
                                    $tot      = (int)($sub['total_periods'] ?? ($lt + $th));
                                    $isMandatory = (bool)($sub['is_mandatory'] ?? ($typeKey !== 'elective'));

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
                                    <td class="text-center text-muted small"><?php echo $lt ?: '-'; ?></td>
                                    <td class="text-center text-muted small"><?php echo $th ?: '-'; ?></td>
                                    <td class="text-center text-muted small"><?php echo $tot ?: '-'; ?></td>
                                    <td><span class="badge bg-<?php echo $type[1]; ?>"><?php echo $type[0]; ?></span></td>
                                    <td class="text-center">
                                        <?php if ($isMandatory): ?>
                                        <span class="badge bg-success">Có</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Không</span>
                                        <?php endif; ?>
                                    </td>
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
