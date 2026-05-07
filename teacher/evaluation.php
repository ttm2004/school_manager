<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('teacher');

$stmt = $conn->prepare("SELECT t.*, u.full_name FROM teachers t JOIN users u ON t.user_id=u.id WHERE t.user_id=?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$teacher) { header('Location: /university/login.php?logout=1'); exit(); }

// Lấy danh sách đợt đánh giá
$periods = $conn->query("
    SELECT ep.*, sm.semester_name, sm.school_year
    FROM evaluation_periods ep
    LEFT JOIN semesters sm ON ep.semester_id = sm.id
    ORDER BY ep.created_at DESC
");
$periodList = [];
if ($periods) {
    while ($p = $periods->fetch_assoc()) $periodList[] = $p;
}

// Lấy danh sách học kỳ
$semesters = $conn->query("SELECT id, semester_name, school_year FROM semesters ORDER BY school_year DESC, id DESC");
$semList = [];
if ($semesters) {
    while ($s = $semesters->fetch_assoc()) $semList[] = $s;
}

$filter_period = intval($_GET['period_id'] ?? 0);
$filter_sem    = intval($_GET['semester_id'] ?? 0);

// Kết quả tổng hợp theo lớp học phần của giảng viên này
$results = [];
if ($filter_period) {
    $whereExtra = $filter_sem ? "AND cs.semester_id = " . intval($filter_sem) : "";
    $stmt = $conn->prepare("
        SELECT cs.id as section_id, cs.section_code,
               s.subject_name, s.credits,
               sm.semester_name, sm.school_year,
               COUNT(DISTINCT se.student_id) as response_count,
               AVG(se.rating) as avg_rating
        FROM student_evaluations se
        JOIN course_sections cs ON se.course_section_id = cs.id
        JOIN subjects s ON cs.subject_id = s.id
        JOIN semesters sm ON cs.semester_id = sm.id
        WHERE se.period_id = ? AND se.teacher_id = ? $whereExtra
        GROUP BY se.course_section_id
        ORDER BY cs.section_code
    ");
    $stmt->bind_param('ii', $filter_period, $teacher['id']);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $results[] = $row;
    $stmt->close();
}

// Chi tiết lớp học phần (GET param)
$detailData    = null;
$detailSection = intval($_GET['detail_section'] ?? 0);
$detailPeriod  = intval($_GET['detail_period'] ?? 0);
if ($detailSection && $detailPeriod) {
    // Kiểm tra lớp này có thuộc giảng viên không
    $chk = $conn->prepare("SELECT id FROM course_sections WHERE id=? AND teacher_id=?");
    $chk->bind_param('ii', $detailSection, $teacher['id']);
    $chk->execute();
    $validSection = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ($validSection) {
        // Thông tin lớp
        $dStmt = $conn->prepare("
            SELECT cs.section_code, s.subject_name, sm.semester_name, sm.school_year
            FROM course_sections cs
            JOIN subjects s ON cs.subject_id = s.id
            JOIN semesters sm ON cs.semester_id = sm.id
            WHERE cs.id = ?
        ");
        $dStmt->bind_param('i', $detailSection);
        $dStmt->execute();
        $detailInfo = $dStmt->get_result()->fetch_assoc();
        $dStmt->close();

        // Kết quả theo từng câu hỏi (ẩn danh - không lộ thông tin sinh viên)
        $dStmt2 = $conn->prepare("
            SELECT eq.id as q_id, eq.question_text, eq.question_type,
                   AVG(se.rating) as avg_rating,
                   COUNT(se.id) as answer_count,
                   GROUP_CONCAT(se.comment SEPARATOR '|||') as comments,
                   SUM(CASE WHEN se.rating = 5 THEN 1 ELSE 0 END) as cnt5,
                   SUM(CASE WHEN se.rating = 4 THEN 1 ELSE 0 END) as cnt4,
                   SUM(CASE WHEN se.rating = 3 THEN 1 ELSE 0 END) as cnt3,
                   SUM(CASE WHEN se.rating = 2 THEN 1 ELSE 0 END) as cnt2,
                   SUM(CASE WHEN se.rating = 1 THEN 1 ELSE 0 END) as cnt1
            FROM evaluation_questions eq
            LEFT JOIN student_evaluations se ON se.question_id = eq.id
                AND se.course_section_id = ? AND se.period_id = ?
                AND se.teacher_id = ?
            WHERE eq.status = 'show'
            GROUP BY eq.id
            ORDER BY eq.id ASC
        ");
        $dStmt2->bind_param('iii', $detailSection, $detailPeriod, $teacher['id']);
        $dStmt2->execute();
        $detailQuestions = $dStmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        $dStmt2->close();

        // Danh sách SV đã đánh giá (chỉ hiển thị mã SV + lớp, ẩn tên đầy đủ với giảng viên)
        $qListStmt = $conn->query("SELECT id, question_text, question_type FROM evaluation_questions WHERE status='show' ORDER BY id ASC");
        $qListForSV = $qListStmt ? $qListStmt->fetch_all(MYSQLI_ASSOC) : [];

        // Lấy SV đã đánh giá — giảng viên chỉ thấy mã SV + lớp (ẩn tên)
        $svStmt = $conn->prepare("
            SELECT DISTINCT st.id as student_id, st.student_code,
                   cl.class_name, se.created_at
            FROM student_evaluations se
            JOIN students st ON se.student_id = st.id
            LEFT JOIN classes cl ON st.class_id = cl.id
            WHERE se.course_section_id = ? AND se.period_id = ? AND se.teacher_id = ?
            ORDER BY st.student_code
        ");
        $svStmt->bind_param('iii', $detailSection, $detailPeriod, $teacher['id']);
        $svStmt->execute();
        $svList = $svStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $svStmt->close();

        // Lấy tất cả câu trả lời
        $ansStmt = $conn->prepare("
            SELECT se.student_id, se.question_id, se.rating, se.comment
            FROM student_evaluations se
            WHERE se.course_section_id = ? AND se.period_id = ? AND se.teacher_id = ?
        ");
        $ansStmt->bind_param('iii', $detailSection, $detailPeriod, $teacher['id']);
        $ansStmt->execute();
        $ansRows = $ansStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $ansStmt->close();

        $answerMap = [];
        foreach ($ansRows as $ans) {
            $answerMap[$ans['student_id']][$ans['question_id']] = $ans;
        }

        // Lấy góp ý thêm từ bảng student_extra_comments
        $conn->query("
            CREATE TABLE IF NOT EXISTS student_extra_comments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                course_section_id INT NOT NULL,
                teacher_id INT NOT NULL,
                period_id INT NOT NULL,
                comment TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_extra (student_id, course_section_id, period_id)
            )
        ");
        $extraStmt = $conn->prepare("
            SELECT sec.comment, sec.created_at
            FROM student_extra_comments sec
            WHERE sec.course_section_id = ? AND sec.period_id = ?
              AND sec.teacher_id = ?
            ORDER BY sec.created_at ASC
        ");
        $extraStmt->bind_param('iii', $detailSection, $detailPeriod, $teacher['id']);
        $extraStmt->execute();
        $extraComments = $extraStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $extraStmt->close();

        $detailData = [
            'info'           => $detailInfo,
            'questions'      => $detailQuestions,
            'sv_list'        => $svList,
            'q_list'         => $qListForSV,
            'answer_map'     => $answerMap,
            'extra_comments' => $extraComments,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kết quả đánh giá - Giảng viên</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/university/assets/css/style.css">
    <style>
        .rating-bar-wrap { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
        .rating-bar-label { width: 90px; font-size: 0.78rem; color: #555; flex-shrink: 0; }
        .rating-bar-track { flex: 1; background: #e9ecef; border-radius: 4px; height: 14px; overflow: hidden; }
        .rating-bar-fill { height: 100%; border-radius: 4px; transition: width 0.4s; }
        .rating-bar-count { width: 30px; text-align: right; font-size: 0.78rem; color: #888; flex-shrink: 0; }
        .avg-score-circle {
            width: 90px; height: 90px; border-radius: 50%;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            font-size: 1.8rem; font-weight: 700; border: 4px solid;
        }
        .question-card { border-left: 4px solid var(--navy, #1a3a5c); }
        .question-card.text-type { border-left-color: #28a745; }
    </style>
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
            <a href="/university/teacher/my_courses.php" class="sidebar-link"><i class="bi bi-journal-text"></i> Lớp học phần</a>
            <a href="/university/teacher/timetable.php" class="sidebar-link"><i class="bi bi-calendar3-week"></i> Thời khóa biểu</a>
            <a href="/university/teacher/exam_schedule.php" class="sidebar-link"><i class="bi bi-calendar-event-fill"></i> Lịch thi cuối kỳ</a>
            <a href="/university/teacher/grades.php" class="sidebar-link"><i class="bi bi-bar-chart-fill"></i> Nhập điểm</a>
            <a href="/university/teacher/evaluation.php" class="sidebar-link active"><i class="bi bi-star-fill"></i> Kết quả đánh giá</a>
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
                <span class="fw-bold text-navy">Kết quả đánh giá của tôi</span>
            </div>
            <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
        <div class="student-content">

            <!-- Bộ lọc -->
            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-funnel me-2"></i>Bộ lọc</div>
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label">Đợt đánh giá</label>
                            <select name="period_id" class="form-select">
                                <option value="">-- Chọn đợt đánh giá --</option>
                                <?php foreach ($periodList as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo $filter_period == $p['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['title'] . ' (' . $p['semester_name'] . ' ' . $p['school_year'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Học kỳ</label>
                            <select name="semester_id" class="form-select">
                                <option value="">Tất cả học kỳ</option>
                                <?php foreach ($semList as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo $filter_sem == $s['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['semester_name'] . ' ' . $s['school_year']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-navy w-100">
                                <i class="bi bi-search me-1"></i>Xem kết quả
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($filter_period): ?>

            <!-- Bảng tổng hợp -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-graph-up-arrow me-2"></i>Tổng hợp kết quả đánh giá</span>
                    <span class="badge bg-navy"><?php echo count($results); ?> lớp học phần</span>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($results)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Lớp học phần</th>
                                    <th>Môn học</th>
                                    <th>Học kỳ</th>
                                    <th class="text-center">Số lượt ĐG</th>
                                    <th class="text-center">Điểm TB</th>
                                    <th class="text-center">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $idx = 1; foreach ($results as $r):
                                    $avg = $r['avg_rating'] !== null ? round((float)$r['avg_rating'], 2) : null;
                                    $avgColor = $avg === null ? 'secondary' : ($avg >= 4 ? 'success' : ($avg >= 3 ? 'warning' : 'danger'));
                                ?>
                                <tr>
                                    <td><?php echo $idx++; ?></td>
                                    <td class="fw-bold text-navy small"><?php echo htmlspecialchars($r['section_code']); ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($r['subject_name']); ?></div>
                                        <span class="badge bg-navy"><?php echo $r['credits']; ?> TC</span>
                                    </td>
                                    <td class="small text-muted">
                                        <?php echo htmlspecialchars($r['semester_name'] . ' ' . $r['school_year']); ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info"><?php echo $r['response_count']; ?> SV</span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($avg !== null): ?>
                                        <div class="d-flex align-items-center justify-content-center gap-1">
                                            <span class="text-warning">
                                                <?php
                                                $filled = round($avg);
                                                for ($i = 1; $i <= 5; $i++) {
                                                    echo $i <= $filled
                                                        ? '<i class="bi bi-star-fill"></i>'
                                                        : '<i class="bi bi-star"></i>';
                                                }
                                                ?>
                                            </span>
                                            <span class="badge bg-<?php echo $avgColor; ?> ms-1">
                                                <?php echo number_format($avg, 2); ?>
                                            </span>
                                        </div>
                                        <?php else: ?>
                                        <span class="text-muted">--</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="?period_id=<?php echo $filter_period; ?>&semester_id=<?php echo $filter_sem; ?>&detail_section=<?php echo $r['section_id']; ?>&detail_period=<?php echo $filter_period; ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye-fill me-1"></i>Chi tiết
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                        Chưa có sinh viên nào đánh giá trong đợt này
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Chi tiết lớp học phần -->
            <?php if ($detailData && $detailData['info']): ?>
            <div class="card" id="detailCard">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <i class="bi bi-bar-chart-fill me-2"></i>
                        Chi tiết đánh giá —
                        <strong><?php echo htmlspecialchars($detailData['info']['section_code']); ?></strong>
                        <span class="text-muted small ms-1"><?php echo htmlspecialchars($detailData['info']['subject_name']); ?></span>
                        <span class="text-muted small ms-1">
                            | <?php echo htmlspecialchars($detailData['info']['semester_name'] . ' ' . $detailData['info']['school_year']); ?>
                        </span>
                    </div>
                    <a href="?period_id=<?php echo $filter_period; ?>&semester_id=<?php echo $filter_sem; ?>"
                       class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>

                <!-- Nav tabs -->
                <div class="card-header border-top-0 pt-0 pb-0 bg-white">
                    <ul class="nav nav-tabs card-header-tabs" id="detailTabs">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabQuestions">
                                <i class="bi bi-bar-chart-fill me-1"></i>Thống kê câu hỏi
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabStudents">
                                <i class="bi bi-people-fill me-1"></i>
                                SV đã đánh giá
                                <span class="badge bg-navy ms-1"><?php echo count($detailData['sv_list']); ?></span>
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabExtraComments">
                                <i class="bi bi-chat-quote-fill me-1" style="color:#f0a500;"></i>
                                Góp ý thêm
                                <?php $extraCount = count($detailData['extra_comments']); ?>
                                <span class="badge ms-1" style="background:#f0a500;color:#1a3a5c;"><?php echo $extraCount; ?></span>
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="tab-content">
                    <!-- TAB 1: Thống kê câu hỏi -->
                    <div class="tab-pane fade show active p-4" id="tabQuestions">
                        <div class="alert alert-info small mb-4">
                            <i class="bi bi-shield-lock-fill me-2"></i>
                            Kết quả đánh giá được <strong>ẩn danh hoàn toàn</strong>. Bạn chỉ thấy thống kê tổng hợp.
                        </div>
                        <?php foreach ($detailData['questions'] as $dq): ?>
                        <div class="card question-card <?php echo $dq['question_type'] === 'text' ? 'text-type' : ''; ?> mb-4">
                            <div class="card-body">
                                <div class="d-flex align-items-start gap-2 mb-3">
                                    <?php if ($dq['question_type'] === 'rating'): ?>
                                    <span class="badge bg-primary"><i class="bi bi-star-fill"></i></span>
                                    <?php else: ?>
                                    <span class="badge bg-success"><i class="bi bi-chat-left-text-fill"></i></span>
                                    <?php endif; ?>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($dq['question_text']); ?></div>
                                </div>

                                <?php if ($dq['question_type'] === 'rating'): ?>
                                    <?php if ($dq['avg_rating'] !== null && $dq['answer_count'] > 0): ?>
                                    <div class="row align-items-center g-3">
                                        <div class="col-auto">
                                            <?php
                                            $avg = (float)$dq['avg_rating'];
                                            $avgColor = $avg >= 4 ? '#28a745' : ($avg >= 3 ? '#ffc107' : '#dc3545');
                                            ?>
                                            <div class="avg-score-circle" style="color:<?php echo $avgColor; ?>;border-color:<?php echo $avgColor; ?>;">
                                                <span><?php echo number_format($avg, 1); ?></span>
                                                <small style="font-size:0.65rem;font-weight:400;">/ 5</small>
                                            </div>
                                            <div class="text-center mt-1 text-warning" style="font-size:1.1rem;">
                                                <?php $filled = round($avg); for ($i=1;$i<=5;$i++) echo $i<=$filled?'<i class="bi bi-star-fill"></i>':'<i class="bi bi-star"></i>'; ?>
                                            </div>
                                            <div class="text-center text-muted" style="font-size:0.75rem;"><?php echo $dq['answer_count']; ?> phản hồi</div>
                                        </div>
                                        <div class="col">
                                            <?php
                                            $total = $dq['answer_count'] ?: 1;
                                            $bars = [
                                                ['label'=>'5 - Rất hài lòng','count'=>(int)$dq['cnt5'],'color'=>'#28a745'],
                                                ['label'=>'4 - Hài lòng','count'=>(int)$dq['cnt4'],'color'=>'#20c997'],
                                                ['label'=>'3 - Bình thường','count'=>(int)$dq['cnt3'],'color'=>'#ffc107'],
                                                ['label'=>'2 - Không hài lòng','count'=>(int)$dq['cnt2'],'color'=>'#fd7e14'],
                                                ['label'=>'1 - Rất không hài lòng','count'=>(int)$dq['cnt1'],'color'=>'#dc3545'],
                                            ];
                                            foreach ($bars as $bar): $pct = $total>0?round($bar['count']/$total*100):0; ?>
                                            <div class="rating-bar-wrap">
                                                <div class="rating-bar-label"><?php echo $bar['label']; ?></div>
                                                <div class="rating-bar-track"><div class="rating-bar-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $bar['color']; ?>;"></div></div>
                                                <div class="rating-bar-count"><?php echo $bar['count']; ?></div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-muted small"><i class="bi bi-dash-circle me-1"></i>Chưa có phản hồi</div>
                                    <?php endif; ?>

                                <?php elseif ($dq['question_type'] === 'text'): ?>
                                    <?php
                                    $comments = [];
                                    if ($dq['comments']) {
                                        $comments = array_filter(array_map('trim', explode('|||', $dq['comments'])), fn($c) => $c !== '');
                                    }
                                    ?>
                                    <?php if (!empty($comments)): ?>
                                    <div class="text-muted small mb-2"><i class="bi bi-chat-square-quote me-1"></i><?php echo count($comments); ?> nhận xét:</div>
                                    <div class="d-flex flex-column gap-2">
                                        <?php foreach ($comments as $cm): ?>
                                        <div class="bg-light rounded px-3 py-2 small border-start border-3 border-success">
                                            <i class="bi bi-quote text-muted me-1"></i><?php echo htmlspecialchars($cm); ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-muted small"><i class="bi bi-dash-circle me-1"></i>Không có nhận xét</div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- TAB 2: Danh sách SV đã đánh giá (ẩn danh — chỉ mã SV + lớp) -->
                    <div class="tab-pane fade" id="tabStudents">
                        <div class="alert alert-warning small m-3">
                            <i class="bi bi-eye-slash-fill me-2"></i>
                            Danh sách hiển thị <strong>mã sinh viên và lớp</strong> để bạn biết tỷ lệ phản hồi. Tên sinh viên được ẩn để đảm bảo tính ẩn danh.
                        </div>
                        <?php if (empty($detailData['sv_list'])): ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            Chưa có sinh viên nào đánh giá
                        </div>
                        <?php else: ?>
                        <?php
                        $qList    = $detailData['q_list'];
                        $svList   = $detailData['sv_list'];
                        $ansMap   = $detailData['answer_map'];
                        $ratingQs = array_filter($qList, fn($q) => $q['question_type'] === 'rating');
                        $textQs   = array_filter($qList, fn($q) => $q['question_type'] === 'text');
                        ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered mb-0 align-middle" style="font-size:0.83rem">
                                <thead class="table-light">
                                    <tr>
                                        <th rowspan="2" class="align-middle text-center">#</th>
                                        <th rowspan="2" class="align-middle">Mã SV</th>
                                        <th rowspan="2" class="align-middle">Lớp</th>
                                        <?php if (!empty($ratingQs)): ?>
                                        <th colspan="<?php echo count($ratingQs); ?>" class="text-center bg-primary bg-opacity-10 text-primary">
                                            <i class="bi bi-star-fill me-1"></i>Điểm đánh giá
                                        </th>
                                        <th rowspan="2" class="align-middle text-center">TB</th>
                                        <?php endif; ?>
                                        <?php if (!empty($textQs)): ?>
                                        <th colspan="<?php echo count($textQs); ?>" class="text-center bg-success bg-opacity-10 text-success">
                                            <i class="bi bi-chat-left-text-fill me-1"></i>Nhận xét
                                        </th>
                                        <?php endif; ?>
                                        <th rowspan="2" class="align-middle text-center">Thời gian</th>
                                    </tr>
                                    <tr>
                                        <?php foreach ($ratingQs as $q): ?>
                                        <th class="text-center bg-primary bg-opacity-10" style="min-width:70px;font-size:0.75rem;">
                                            <span title="<?php echo htmlspecialchars($q['question_text']); ?>" data-bs-toggle="tooltip">
                                                CH<?php echo $q['id']; ?>
                                            </span>
                                        </th>
                                        <?php endforeach; ?>
                                        <?php foreach ($textQs as $q): ?>
                                        <th class="bg-success bg-opacity-10" style="min-width:160px;font-size:0.75rem;">
                                            <span title="<?php echo htmlspecialchars($q['question_text']); ?>" data-bs-toggle="tooltip">
                                                <?php echo mb_strimwidth($q['question_text'], 0, 28, '…'); ?>
                                            </span>
                                        </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($svList as $idx => $sv):
                                        $svAnswers = $ansMap[$sv['student_id']] ?? [];
                                        $svRatings = [];
                                        foreach ($ratingQs as $q) {
                                            if (isset($svAnswers[$q['id']]['rating']) && $svAnswers[$q['id']]['rating'] !== null) {
                                                $svRatings[] = (int)$svAnswers[$q['id']]['rating'];
                                            }
                                        }
                                        $svAvg = !empty($svRatings) ? array_sum($svRatings) / count($svRatings) : null;
                                        $svAvgColor = $svAvg === null ? 'secondary' : ($svAvg >= 4 ? 'success' : ($svAvg >= 3 ? 'warning' : 'danger'));
                                    ?>
                                    <tr>
                                        <td class="text-center text-muted"><?php echo $idx + 1; ?></td>
                                        <td class="fw-bold text-navy"><?php echo htmlspecialchars($sv['student_code']); ?></td>
                                        <td class="text-muted small"><?php echo htmlspecialchars($sv['class_name'] ?? '--'); ?></td>

                                        <?php foreach ($ratingQs as $q):
                                            $rating = $svAnswers[$q['id']]['rating'] ?? null;
                                            $starColor = $rating === null ? '#ccc' : ($rating >= 4 ? '#28a745' : ($rating >= 3 ? '#ffc107' : '#dc3545'));
                                        ?>
                                        <td class="text-center">
                                            <?php if ($rating !== null): ?>
                                            <span style="color:<?php echo $starColor; ?>;font-weight:700;">
                                                <?php for ($s=1;$s<=5;$s++) echo '<i class="bi bi-star'.($s<=$rating?'-fill':'').'" style="font-size:0.65rem;"></i>'; ?>
                                                <br><small><?php echo $rating; ?>/5</small>
                                            </span>
                                            <?php else: ?><span class="text-muted">--</span><?php endif; ?>
                                        </td>
                                        <?php endforeach; ?>

                                        <?php if (!empty($ratingQs)): ?>
                                        <td class="text-center">
                                            <?php if ($svAvg !== null): ?>
                                            <span class="badge bg-<?php echo $svAvgColor; ?>"><?php echo number_format($svAvg, 2); ?></span>
                                            <?php else: ?><span class="text-muted">--</span><?php endif; ?>
                                        </td>
                                        <?php endif; ?>

                                        <?php foreach ($textQs as $q):
                                            $comment = trim($svAnswers[$q['id']]['comment'] ?? '');
                                        ?>
                                        <td>
                                            <?php if ($comment !== ''): ?>
                                            <span class="d-inline-block text-truncate" style="max-width:180px;"
                                                  title="<?php echo htmlspecialchars($comment); ?>" data-bs-toggle="tooltip">
                                                <?php echo htmlspecialchars($comment); ?>
                                            </span>
                                            <?php else: ?><span class="text-muted small fst-italic">Không có</span><?php endif; ?>
                                        </td>
                                        <?php endforeach; ?>

                                        <td class="text-muted small text-center">
                                            <?php echo $sv['created_at'] ? date('d/m/Y H:i', strtotime($sv['created_at'])) : '--'; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (!empty($ratingQs)): ?>
                        <div class="p-3 border-top bg-light">
                            <div class="small text-muted fw-bold mb-1"><i class="bi bi-info-circle me-1"></i>Chú thích:</div>
                            <div class="row g-1">
                                <?php foreach ($ratingQs as $q): ?>
                                <div class="col-md-6 small text-muted">
                                    <span class="badge bg-primary me-1">CH<?php echo $q['id']; ?></span>
                                    <?php echo htmlspecialchars($q['question_text']); ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- TAB 3: Góp ý thêm -->
                    <div class="tab-pane fade" id="tabExtraComments">
                        <?php $extraComments = $detailData['extra_comments']; ?>
                        <?php if (empty($extraComments)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-chat-square fs-2 d-block mb-2" style="color:#f0a500;opacity:.4;"></i>
                            <div class="fw-semibold">Chưa có góp ý thêm nào</div>
                            <div class="small mt-1">Sinh viên chưa gửi góp ý bổ sung cho lớp học phần này</div>
                        </div>
                        <?php else: ?>
                        <div class="p-4">
                            <div class="alert d-flex align-items-center gap-2 mb-4"
                                 style="background:#fff8e1;border:1px solid #ffe082;border-radius:8px;">
                                <i class="bi bi-shield-lock-fill" style="color:#f0a500;font-size:1.2rem;flex-shrink:0;"></i>
                                <div class="small">
                                    Các góp ý dưới đây được sinh viên gửi <strong>ẩn danh</strong>.
                                    Đây là ý kiến bổ sung ngoài phần đánh giá sao — giúp bạn cải thiện chất lượng giảng dạy.
                                </div>
                            </div>

                            <div class="d-flex align-items-center gap-2 mb-3">
                                <i class="bi bi-chat-quote-fill fs-5" style="color:#f0a500;"></i>
                                <span class="fw-bold text-navy">
                                    <?php echo count($extraComments); ?> góp ý từ sinh viên
                                </span>
                            </div>

                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($extraComments as $i => $ec): ?>
                                <div class="d-flex gap-3 align-items-start">
                                    <!-- Số thứ tự -->
                                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                         style="width:32px;height:32px;background:#fff3cd;color:#856404;font-weight:700;font-size:0.85rem;">
                                        <?php echo $i + 1; ?>
                                    </div>
                                    <!-- Nội dung -->
                                    <div class="flex-grow-1 rounded p-3"
                                         style="background:#fffdf0;border:1px solid #ffe082;border-left:4px solid #f0a500;border-radius:8px;">
                                        <div style="white-space:pre-wrap;line-height:1.6;">
                                            <?php echo htmlspecialchars($ec['comment']); ?>
                                        </div>
                                        <?php if ($ec['created_at']): ?>
                                        <div class="text-muted mt-2" style="font-size:0.72rem;">
                                            <i class="bi bi-clock me-1"></i>
                                            <?php echo date('d/m/Y H:i', strtotime($ec['created_at'])); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                </div><!-- /.tab-content -->
            </div>
            <?php endif; ?>

            <?php else: ?>
            <!-- Chưa chọn đợt -->
            <div class="card">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-star fs-2 d-block mb-2 text-warning"></i>
                    <div class="fw-semibold mb-1">Chọn đợt đánh giá để xem kết quả</div>
                    <div class="small">Kết quả hiển thị chỉ bao gồm các lớp học phần bạn đang phụ trách</div>
                </div>
            </div>
            <?php endif; ?>

        </div>
        <div class="student-footer">&copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một</div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<script>
<?php if ($detailData): ?>
document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('detailCard');
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
        new bootstrap.Tooltip(el, { trigger: 'hover' });
    });
});
<?php endif; ?>
</script>
</body>
</html>
