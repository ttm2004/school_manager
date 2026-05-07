<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Kết quả đánh giá';

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

$filter_period  = intval($_GET['period_id'] ?? 0);
$filter_sem     = intval($_GET['semester_id'] ?? 0);

// Lấy danh sách câu hỏi rating để hiển thị cột
$questions = $conn->query("SELECT * FROM evaluation_questions WHERE status='show' ORDER BY id ASC");
$questionList = [];
if ($questions) {
    while ($q = $questions->fetch_assoc()) $questionList[] = $q;
}

// Kết quả tổng hợp theo lớp học phần
$results = [];
if ($filter_period) {
    $whereExtra = $filter_sem ? "AND cs.semester_id = " . intval($filter_sem) : "";
    $stmt = $conn->prepare("
        SELECT cs.id as section_id, cs.section_code,
               s.subject_name,
               u.full_name as teacher_name,
               COUNT(DISTINCT se.student_id) as response_count,
               AVG(se.rating) as avg_rating
        FROM student_evaluations se
        JOIN course_sections cs ON se.course_section_id = cs.id
        JOIN subjects s ON cs.subject_id = s.id
        JOIN teachers t ON se.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE se.period_id = ? $whereExtra
        GROUP BY se.course_section_id
        ORDER BY cs.section_code
    ");
    $stmt->bind_param('i', $filter_period);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $results[] = $row;
    $stmt->close();
}

// Lấy chi tiết cho modal (AJAX-like via GET)
$detailData = null;
$detailSection = intval($_GET['detail_section'] ?? 0);
$detailPeriod  = intval($_GET['detail_period'] ?? 0);
if ($detailSection && $detailPeriod) {
    // Thông tin lớp
    $dStmt = $conn->prepare("
        SELECT cs.section_code, s.subject_name, u.full_name as teacher_name
        FROM course_sections cs
        JOIN subjects s ON cs.subject_id = s.id
        JOIN teachers t ON cs.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE cs.id = ?
    ");
    $dStmt->bind_param('i', $detailSection);
    $dStmt->execute();
    $detailInfo = $dStmt->get_result()->fetch_assoc();
    $dStmt->close();

    // Kết quả theo từng câu hỏi
    $dStmt2 = $conn->prepare("
        SELECT eq.id as q_id, eq.question_text, eq.question_type,
               AVG(se.rating) as avg_rating,
               COUNT(se.id) as answer_count,
               GROUP_CONCAT(se.comment SEPARATOR '|||') as comments
        FROM evaluation_questions eq
        LEFT JOIN student_evaluations se ON se.question_id = eq.id
            AND se.course_section_id = ? AND se.period_id = ?
        WHERE eq.status = 'show'
        GROUP BY eq.id
        ORDER BY eq.id ASC
    ");
    $dStmt2->bind_param('ii', $detailSection, $detailPeriod);
    $dStmt2->execute();
    $detailQuestions = $dStmt2->get_result()->fetch_all(MYSQLI_ASSOC);
    $dStmt2->close();

    // -------------------------------------------------------
    // Danh sách sinh viên đã đánh giá + điểm từng câu hỏi
    // -------------------------------------------------------
    // Lấy danh sách câu hỏi đang hiển thị (để build cột)
    $qListStmt = $conn->query("SELECT id, question_text, question_type FROM evaluation_questions WHERE status='show' ORDER BY id ASC");
    $qListForSV = $qListStmt ? $qListStmt->fetch_all(MYSQLI_ASSOC) : [];

    // Lấy danh sách SV đã đánh giá (distinct)
    $svStmt = $conn->prepare("
        SELECT DISTINCT st.id as student_id, st.student_code, u.full_name,
               cl.class_name, se.created_at
        FROM student_evaluations se
        JOIN students st ON se.student_id = st.id
        JOIN users u ON st.user_id = u.id
        LEFT JOIN classes cl ON st.class_id = cl.id
        WHERE se.course_section_id = ? AND se.period_id = ?
        ORDER BY u.full_name
    ");
    $svStmt->bind_param('ii', $detailSection, $detailPeriod);
    $svStmt->execute();
    $svList = $svStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $svStmt->close();

    // Lấy tất cả câu trả lời của lớp này trong đợt này
    $ansStmt = $conn->prepare("
        SELECT se.student_id, se.question_id, se.rating, se.comment
        FROM student_evaluations se
        WHERE se.course_section_id = ? AND se.period_id = ?
    ");
    $ansStmt->bind_param('ii', $detailSection, $detailPeriod);
    $ansStmt->execute();
    $ansRows = $ansStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $ansStmt->close();

    // Index: [student_id][question_id] => answer
    $answerMap = [];
    foreach ($ansRows as $ans) {
        $answerMap[$ans['student_id']][$ans['question_id']] = $ans;
    }

    // Lấy góp ý thêm từ bảng student_extra_comments
    $conn->query("CREATE TABLE IF NOT EXISTS student_extra_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL, course_section_id INT NOT NULL,
        teacher_id INT NOT NULL, period_id INT NOT NULL,
        comment TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_extra (student_id, course_section_id, period_id)
    )");
    $extraStmt = $conn->prepare("
        SELECT sec.comment, sec.created_at
        FROM student_extra_comments sec
        WHERE sec.course_section_id = ? AND sec.period_id = ?
        ORDER BY sec.created_at ASC
    ");
    $extraStmt->bind_param('ii', $detailSection, $detailPeriod);
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

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
            <span class="admin-topbar-title">Kết quả đánh giá giảng viên</span>
        </div>
        <span class="text-muted small"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
    </div>
    <div class="admin-content">

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
                            <option value="<?php echo $p['id']; ?>" <?php echo $filter_period==$p['id']?'selected':''; ?>>
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
                            <option value="<?php echo $s['id']; ?>" <?php echo $filter_sem==$s['id']?'selected':''; ?>>
                                <?php echo htmlspecialchars($s['semester_name'] . ' ' . $s['school_year']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-navy w-100"><i class="bi bi-search me-1"></i>Xem kết quả</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($filter_period): ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-graph-up-arrow me-2"></i>Kết quả đánh giá</span>
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
                                <th>Giảng viên</th>
                                <th class="text-center">Số lượt ĐG</th>
                                <th class="text-center">Điểm TB</th>
                                <th class="text-center">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $idx = 1; foreach ($results as $r):
                                $avg = $r['avg_rating'] !== null ? round($r['avg_rating'], 2) : null;
                                $avgColor = $avg === null ? 'secondary' : ($avg >= 4 ? 'success' : ($avg >= 3 ? 'warning' : 'danger'));
                            ?>
                            <tr>
                                <td><?php echo $idx++; ?></td>
                                <td class="fw-bold text-navy small"><?php echo htmlspecialchars($r['section_code']); ?></td>
                                <td><?php echo htmlspecialchars($r['subject_name']); ?></td>
                                <td><?php echo htmlspecialchars($r['teacher_name']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-info"><?php echo $r['response_count']; ?> SV</span>
                                </td>
                                <td class="text-center">
                                    <?php if ($avg !== null): ?>
                                    <span class="badge bg-<?php echo $avgColor; ?> fs-6">
                                        <?php
                                        $filled = round($avg);
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo $i <= $filled
                                                ? '<i class="bi bi-star-fill"></i>'
                                                : '<i class="bi bi-star"></i>';
                                        }
                                        echo ' ' . number_format($avg, 2);
                                        ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted">--</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <a href="?period_id=<?php echo $filter_period; ?>&semester_id=<?php echo $filter_sem; ?>&detail_section=<?php echo $r['section_id']; ?>&detail_period=<?php echo $filter_period; ?>"
                                       class="btn btn-sm btn-outline-primary" title="Xem chi tiết">
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
                    Chưa có dữ liệu đánh giá cho đợt này
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($detailData && $detailData['info']): ?>
        <!-- Chi tiết lớp học phần — 2 tab -->
        <div class="card mt-4" id="detailCard">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <i class="bi bi-bar-chart-fill me-2"></i>
                    Chi tiết đánh giá —
                    <strong><?php echo htmlspecialchars($detailData['info']['section_code']); ?></strong>
                    <span class="text-muted small ms-1"><?php echo htmlspecialchars($detailData['info']['subject_name']); ?></span>
                    <span class="text-muted small ms-1">| GV: <?php echo htmlspecialchars($detailData['info']['teacher_name']); ?></span>
                </div>
                <a href="?period_id=<?php echo $filter_period; ?>&semester_id=<?php echo $filter_sem; ?>"
                   class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
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
                            Danh sách SV đã đánh giá
                            <span class="badge bg-navy ms-1"><?php echo count($detailData['sv_list']); ?></span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabExtraAdmin">
                            <i class="bi bi-chat-quote-fill me-1" style="color:#f0a500;"></i>
                            Góp ý thêm
                            <span class="badge ms-1" style="background:#f0a500;color:#1a3a5c;">
                                <?php echo count($detailData['extra_comments']); ?>
                            </span>
                        </button>
                    </li>
                </ul>
            </div>

            <div class="tab-content">
                <!-- TAB 1: Thống kê câu hỏi -->
                <div class="tab-pane fade show active p-4" id="tabQuestions">
                    <?php foreach ($detailData['questions'] as $dq): ?>
                    <div class="mb-4 pb-3 border-bottom">
                        <div class="d-flex align-items-start gap-2 mb-2">
                            <?php if ($dq['question_type'] === 'rating'): ?>
                            <span class="badge bg-primary"><i class="bi bi-star-fill"></i></span>
                            <?php else: ?>
                            <span class="badge bg-success"><i class="bi bi-chat-left-text-fill"></i></span>
                            <?php endif; ?>
                            <div class="fw-semibold"><?php echo htmlspecialchars($dq['question_text']); ?></div>
                        </div>

                        <?php if ($dq['question_type'] === 'rating' && $dq['avg_rating'] !== null): ?>
                        <div class="d-flex align-items-center gap-3 ms-4">
                            <div class="text-warning fs-5">
                                <?php
                                $avg = round($dq['avg_rating']);
                                for ($i = 1; $i <= 5; $i++) {
                                    echo $i <= $avg ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star"></i>';
                                }
                                ?>
                            </div>
                            <span class="fw-bold text-navy fs-5"><?php echo number_format($dq['avg_rating'], 2); ?> / 5</span>
                            <span class="text-muted small">(<?php echo $dq['answer_count']; ?> phản hồi)</span>
                        </div>
                        <?php elseif ($dq['question_type'] === 'rating'): ?>
                        <div class="ms-4 text-muted small">Chưa có phản hồi</div>
                        <?php endif; ?>

                        <?php if ($dq['question_type'] === 'text' && $dq['comments']): ?>
                        <?php
                        $comments = array_filter(array_map('trim', explode('|||', $dq['comments'])));
                        $comments = array_filter($comments, fn($c) => $c !== '');
                        ?>
                        <?php if (!empty($comments)): ?>
                        <div class="ms-4 mt-2">
                            <div class="text-muted small mb-1"><i class="bi bi-chat-square-quote me-1"></i>Nhận xét (<?php echo count($comments); ?>):</div>
                            <div class="d-flex flex-column gap-1">
                                <?php foreach ($comments as $cm): ?>
                                <div class="bg-light rounded px-3 py-2 small border-start border-3 border-success">
                                    <?php echo htmlspecialchars($cm); ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="ms-4 text-muted small">Không có nhận xét</div>
                        <?php endif; ?>
                        <?php elseif ($dq['question_type'] === 'text'): ?>
                        <div class="ms-4 text-muted small">Không có nhận xét</div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- TAB 2: Danh sách sinh viên đã đánh giá -->
                <div class="tab-pane fade" id="tabStudents">
                    <?php if (empty($detailData['sv_list'])): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                        Chưa có sinh viên nào đánh giá lớp học phần này
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
                                    <th rowspan="2" class="align-middle text-center" style="min-width:40px">#</th>
                                    <th rowspan="2" class="align-middle" style="min-width:90px">Mã SV</th>
                                    <th rowspan="2" class="align-middle" style="min-width:150px">Họ và tên</th>
                                    <th rowspan="2" class="align-middle" style="min-width:90px">Lớp</th>
                                    <?php if (!empty($ratingQs)): ?>
                                    <th colspan="<?php echo count($ratingQs); ?>" class="text-center bg-primary bg-opacity-10 text-primary">
                                        <i class="bi bi-star-fill me-1"></i>Câu hỏi đánh giá sao
                                    </th>
                                    <th rowspan="2" class="align-middle text-center" style="min-width:80px">TB chung</th>
                                    <?php endif; ?>
                                    <?php if (!empty($textQs)): ?>
                                    <th colspan="<?php echo count($textQs); ?>" class="text-center bg-success bg-opacity-10 text-success">
                                        <i class="bi bi-chat-left-text-fill me-1"></i>Nhận xét
                                    </th>
                                    <?php endif; ?>
                                    <th rowspan="2" class="align-middle text-center" style="min-width:100px">Thời gian</th>
                                </tr>
                                <tr>
                                    <?php foreach ($ratingQs as $q): ?>
                                    <th class="text-center bg-primary bg-opacity-10" style="min-width:80px; font-weight:500; font-size:0.75rem;">
                                        <span title="<?php echo htmlspecialchars($q['question_text']); ?>"
                                              data-bs-toggle="tooltip">
                                            CH<?php echo $q['id']; ?>
                                        </span>
                                    </th>
                                    <?php endforeach; ?>
                                    <?php foreach ($textQs as $q): ?>
                                    <th class="bg-success bg-opacity-10" style="min-width:180px; font-weight:500; font-size:0.75rem;">
                                        <span title="<?php echo htmlspecialchars($q['question_text']); ?>"
                                              data-bs-toggle="tooltip">
                                            <?php echo mb_strimwidth($q['question_text'], 0, 30, '…'); ?>
                                        </span>
                                    </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($svList as $idx => $sv):
                                    $svAnswers = $ansMap[$sv['student_id']] ?? [];
                                    // Tính TB chung của SV này (chỉ rating)
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
                                    <td><?php echo htmlspecialchars($sv['full_name']); ?></td>
                                    <td class="text-muted small"><?php echo htmlspecialchars($sv['class_name'] ?? '--'); ?></td>

                                    <?php foreach ($ratingQs as $q):
                                        $rating = $svAnswers[$q['id']]['rating'] ?? null;
                                        $starColor = $rating === null ? '#ccc' : ($rating >= 4 ? '#28a745' : ($rating >= 3 ? '#ffc107' : '#dc3545'));
                                    ?>
                                    <td class="text-center">
                                        <?php if ($rating !== null): ?>
                                        <span style="color:<?php echo $starColor; ?>; font-weight:700;">
                                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                            <i class="bi bi-star<?php echo $s <= $rating ? '-fill' : ''; ?>" style="font-size:0.7rem;"></i>
                                            <?php endfor; ?>
                                            <br><small><?php echo $rating; ?>/5</small>
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted">--</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php endforeach; ?>

                                    <?php if (!empty($ratingQs)): ?>
                                    <td class="text-center">
                                        <?php if ($svAvg !== null): ?>
                                        <span class="badge bg-<?php echo $svAvgColor; ?>">
                                            <?php echo number_format($svAvg, 2); ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted">--</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>

                                    <?php foreach ($textQs as $q):
                                        $comment = trim($svAnswers[$q['id']]['comment'] ?? '');
                                    ?>
                                    <td>
                                        <?php if ($comment !== ''): ?>
                                        <span class="d-inline-block text-truncate" style="max-width:200px;"
                                              title="<?php echo htmlspecialchars($comment); ?>"
                                              data-bs-toggle="tooltip" data-bs-placement="top">
                                            <?php echo htmlspecialchars($comment); ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted small fst-italic">Không có</span>
                                        <?php endif; ?>
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
                    <!-- Chú thích câu hỏi -->
                    <?php if (!empty($ratingQs)): ?>
                    <div class="p-3 border-top bg-light">
                        <div class="small text-muted fw-bold mb-1"><i class="bi bi-info-circle me-1"></i>Chú thích câu hỏi:</div>
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
                <div class="tab-pane fade" id="tabExtraAdmin">
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
                            <i class="bi bi-chat-quote-fill fs-5" style="color:#f0a500;flex-shrink:0;"></i>
                            <div class="small">
                                <strong><?php echo count($extraComments); ?> góp ý</strong> từ sinh viên —
                                hoàn toàn ẩn danh, ghi bằng chữ tự do.
                            </div>
                        </div>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($extraComments as $i => $ec): ?>
                            <div class="d-flex gap-3 align-items-start">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                     style="width:32px;height:32px;background:#fff3cd;color:#856404;font-weight:700;font-size:0.85rem;">
                                    <?php echo $i + 1; ?>
                                </div>
                                <div class="flex-grow-1 p-3"
                                     style="background:#fffdf0;border:1px solid #ffe082;border-left:4px solid #f0a500;border-radius:8px;">
                                    <div style="white-space:pre-wrap;line-height:1.6;">
                                        <?php echo htmlspecialchars($ec['comment']); ?>
                                    </div>
                                    <div class="text-muted mt-2" style="font-size:0.72rem;">
                                        <i class="bi bi-clock me-1"></i>
                                        <?php echo $ec['created_at'] ? date('d/m/Y H:i', strtotime($ec['created_at'])) : '--'; ?>
                                    </div>
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
        <div class="card">
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-graph-up-arrow fs-2 d-block mb-2"></i>
                Chọn đợt đánh giá để xem kết quả
            </div>
        </div>
        <?php endif; ?>

    </div>
    <div class="admin-footer">&copy; <?php echo date('Y'); ?> Trường Đại học Thủ Dầu Một</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<script>
<?php if ($detailData): ?>
document.addEventListener('DOMContentLoaded', function() {
    const el = document.getElementById('detailCard');
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });

    // Khởi tạo tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
        new bootstrap.Tooltip(el, { trigger: 'hover' });
    });

    // Nếu URL có tab=students thì mở tab SV
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('tab') === 'students') {
        const tabBtn = document.querySelector('[data-bs-target="#tabStudents"]');
        if (tabBtn) bootstrap.Tab.getOrCreateInstance(tabBtn).show();
    }
});
<?php endif; ?>
</script>
</body>
</html>
