<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('student');

$stmt = $conn->prepare("SELECT s.*, u.full_name FROM students s JOIN users u ON s.user_id=u.id WHERE s.user_id=?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

$error = '';

// ============================================================
// XỬ LÝ SUBMIT ĐÁNH GIÁ
// ============================================================
// Tự tạo bảng student_extra_comments nếu chưa có
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_evaluation') {
    $period_id  = intval($_POST['period_id'] ?? 0);
    $section_id = intval($_POST['course_section_id'] ?? 0);
    $teacher_id = intval($_POST['teacher_id'] ?? 0);

    $chkPeriod = $conn->prepare("SELECT id FROM evaluation_periods WHERE id=? AND status='open'");
    $chkPeriod->bind_param('i', $period_id);
    $chkPeriod->execute();
    $validPeriod = $chkPeriod->get_result()->fetch_assoc();
    $chkPeriod->close();

    if (!$validPeriod) {
        $error = 'Đợt đánh giá đã đóng hoặc không hợp lệ.';
    } else {
        $chkSec = $conn->prepare("SELECT ss.id FROM student_subjects ss
            WHERE ss.student_id=? AND ss.course_section_id=? AND ss.status='registered'");
        $chkSec->bind_param('ii', $student['id'], $section_id);
        $chkSec->execute();
        $validSec = $chkSec->get_result()->fetch_assoc();
        $chkSec->close();

        if (!$validSec) {
            $error = 'Bạn không có quyền đánh giá lớp học phần này.';
        } else {
            $qRes = $conn->query("SELECT * FROM evaluation_questions WHERE status='show' ORDER BY id ASC");
            $allQuestions = $qRes->fetch_all(MYSQLI_ASSOC);
            $conn->begin_transaction();
            try {
                foreach ($allQuestions as $q) {
                    $qid = $q['id'];
                    if ($q['question_type'] === 'rating') {
                        $rating  = isset($_POST['rating_' . $qid]) ? intval($_POST['rating_' . $qid]) : null;
                        $comment = null;
                        if ($rating === null || $rating < 1 || $rating > 5) {
                            throw new Exception('Vui lòng đánh giá tất cả các câu hỏi sao.');
                        }
                    } else {
                        $rating  = null;
                        $comment = trim($_POST['comment_' . $qid] ?? '') ?: null;
                    }
                    $ins = $conn->prepare("INSERT INTO student_evaluations
                        (student_id, course_section_id, teacher_id, period_id, question_id, rating, comment)
                        VALUES (?,?,?,?,?,?,?)");
                    $ins->bind_param('iiiiids', $student['id'], $section_id, $teacher_id, $period_id, $qid, $rating, $comment);
                    if (!$ins->execute()) {
                        if ($conn->errno == 1062) throw new Exception('Bạn đã đánh giá lớp học phần này rồi.');
                        throw new Exception('Lỗi lưu dữ liệu: ' . $conn->error);
                    }
                    $ins->close();
                }
                $conn->commit();
                // Lưu góp ý thêm vào bảng riêng student_extra_comments
                $extraComment = trim($_POST['extra_comment'] ?? '');
                if ($extraComment !== '') {
                    $insExtra = $conn->prepare("
                        INSERT INTO student_extra_comments
                            (student_id, course_section_id, teacher_id, period_id, comment)
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            comment = VALUES(comment),
                            teacher_id = VALUES(teacher_id),
                            updated_at = CURRENT_TIMESTAMP
                    ");
                    if ($insExtra) {
                        $insExtra->bind_param('iiiis',
                            $student['id'], $section_id, $teacher_id, $period_id, $extraComment);
                        $insExtra->execute();
                        $insExtra->close();
                    }
                }
                header("Location: /university/student/evaluation.php?done=1&period_id={$period_id}");
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}

// ============================================================
// DỮ LIỆU CHUNG
// ============================================================
$qRes = $conn->query("SELECT * FROM evaluation_questions WHERE status='show' ORDER BY id ASC");
$questionList = $qRes ? $qRes->fetch_all(MYSQLI_ASSOC) : [];
$totalQ = count($questionList);

$levelRes = $conn->query("SELECT * FROM evaluation_levels ORDER BY level_value ASC");
$levels = $levelRes ? $levelRes->fetch_all(MYSQLI_ASSOC) : [];

$openPeriods = $conn->query("
    SELECT ep.*, sm.semester_name, sm.school_year, sm.id as semester_id
    FROM evaluation_periods ep
    LEFT JOIN semesters sm ON ep.semester_id = sm.id
    WHERE ep.status = 'open'
    ORDER BY ep.created_at DESC
");
$openPeriodList = [];
if ($openPeriods) while ($p = $openPeriods->fetch_assoc()) $openPeriodList[] = $p;

$sectionsByPeriod = [];
foreach ($openPeriodList as $period) {
    $stmt = $conn->prepare("
        SELECT cs.id as section_id, cs.section_code, cs.teacher_id,
               s.subject_name, s.credits,
               t.degree, u.full_name as teacher_name,
               f.faculty_name,
               sm.semester_name, sm.school_year
        FROM student_subjects ss
        JOIN course_sections cs ON ss.course_section_id = cs.id
        JOIN subjects s ON cs.subject_id = s.id
        JOIN semesters sm ON cs.semester_id = sm.id
        JOIN teachers t ON cs.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        LEFT JOIN faculties f ON t.faculty_id = f.id
        WHERE ss.student_id = ? AND ss.status = 'registered'
          AND cs.semester_id = ?
        ORDER BY s.subject_name
    ");
    $stmt->bind_param('ii', $student['id'], $period['semester_id']);
    $stmt->execute();
    $sections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($sections as &$sec) {
        $chk = $conn->prepare("SELECT COUNT(DISTINCT question_id) as cnt FROM student_evaluations
            WHERE student_id=? AND course_section_id=? AND period_id=?");
        $chk->bind_param('iii', $student['id'], $sec['section_id'], $period['id']);
        $chk->execute();
        $cnt = $chk->get_result()->fetch_assoc()['cnt'];
        $chk->close();
        $sec['evaluated'] = ($totalQ > 0 && $cnt >= $totalQ);
    }
    unset($sec);
    $sectionsByPeriod[$period['id']] = $sections;
}

// Chế độ hiển thị
$activeSection = intval($_GET['section_id'] ?? 0);
$activePeriod  = intval($_GET['period_id'] ?? 0);
$showDone      = isset($_GET['done']);
$showForm = false;
$formSection = $formPeriod = null;

if ($activeSection && $activePeriod) {
    foreach ($openPeriodList as $p) {
        if ((int)$p['id'] === $activePeriod) { $formPeriod = $p; break; }
    }
    if ($formPeriod && isset($sectionsByPeriod[$activePeriod])) {
        foreach ($sectionsByPeriod[$activePeriod] as $sec) {
            if ((int)$sec['section_id'] === $activeSection && !$sec['evaluated']) {
                $formSection = $sec;
                $showForm    = true;
                break;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đánh giá giảng viên - Sinh viên</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/university/assets/css/style.css">
    <style>
        /* Star rating — dùng JS để highlight, không dùng CSS-only trick */
        .star-rating { display:flex; flex-direction:row; gap:4px; }
        .star-rating input[type="radio"] { display:none; }
        .star-rating label {
            font-size:2.2rem; color:#ddd; cursor:pointer;
            transition:color .15s, transform .1s; line-height:1;
        }
        .star-rating label:hover { transform:scale(1.15); }
        .star-rating label.active { color:#f0a500; }
        .star-rating label.hover  { color:#f0a500; }
        .question-card { border-left:4px solid var(--navy,#1a3a5c); border-radius:8px; }
        .question-card.text-type { border-left-color:#28a745; }
        .teacher-card {
            background:linear-gradient(135deg,#1a3a5c 0%,#2d5a8e 100%);
            border-radius:12px; color:#fff; padding:20px 24px;
        }
        .teacher-avatar {
            width:56px; height:56px; border-radius:50%;
            background:rgba(255,255,255,0.2);
            display:flex; align-items:center; justify-content:center;
            font-size:1.6rem; font-weight:700; color:#f0a500; flex-shrink:0;
        }
        .section-row { cursor:pointer; transition:background .15s; }
        .section-row:hover td { background:#f0f4ff !important; }
        .evaluated-row td { background:#f0fff4 !important; }
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
            <a href="/university/student/register_subject.php" class="sidebar-link"><i class="bi bi-journal-plus"></i> Đăng ký học phần</a>
            <a href="/university/student/my_subjects.php" class="sidebar-link"><i class="bi bi-journal-check"></i> Học phần của tôi</a>
            <a href="/university/student/timetable.php" class="sidebar-link"><i class="bi bi-calendar3-week"></i> Thời khóa biểu</a>
            <a href="/university/student/exam_schedule.php" class="sidebar-link"><i class="bi bi-calendar-event-fill"></i> Lịch thi cuối kỳ</a>
            <a href="/university/student/grades.php" class="sidebar-link"><i class="bi bi-bar-chart-fill"></i> Kết quả học tập</a>
            <a href="/university/student/evaluation.php" class="sidebar-link active"><i class="bi bi-star-fill"></i> Đánh giá giảng viên</a>
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
                <span class="fw-bold text-navy">
                    <i class="bi bi-star-fill text-warning me-2"></i>Đánh giá giảng viên
                </span>
            </div>
            <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>

        <div class="student-content">

            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-circle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($showDone): ?>
            <div class="alert alert-success d-flex align-items-center gap-3 mb-4">
                <i class="bi bi-check-circle-fill fs-3 text-success flex-shrink-0"></i>
                <div>
                    <div class="fw-bold">Gửi đánh giá thành công!</div>
                    <div class="small">Cảm ơn bạn đã dành thời gian phản hồi. Ý kiến của bạn giúp nâng cao chất lượng giảng dạy.</div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($showForm && $formSection && $formPeriod): ?>
            <!-- ===== FORM ĐÁNH GIÁ GIẢNG VIÊN ===== -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/university/student/evaluation.php">Đánh giá giảng viên</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($formSection['subject_name']); ?></li>
                </ol>
            </nav>

            <!-- Thông tin giảng viên được đánh giá -->
            <div class="teacher-card mb-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="teacher-avatar"><?php echo mb_substr($formSection['teacher_name'], 0, 1); ?></div>
                    <div class="flex-grow-1">
                        <div class="small opacity-75 mb-1">
                            <i class="bi bi-star-fill text-warning me-1"></i>Bạn đang đánh giá giảng viên
                        </div>
                        <div class="fw-bold fs-5"><?php echo htmlspecialchars($formSection['teacher_name']); ?></div>
                        <div class="small opacity-75">
                            <?php if (!empty($formSection['degree'])): ?>
                            <span class="me-2"><i class="bi bi-award-fill me-1"></i><?php echo htmlspecialchars($formSection['degree']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($formSection['faculty_name'])): ?>
                            <span><i class="bi bi-building me-1"></i><?php echo htmlspecialchars($formSection['faculty_name']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-end d-none d-md-block">
                        <div class="small opacity-75">Môn học</div>
                        <div class="fw-bold"><?php echo htmlspecialchars($formSection['subject_name']); ?></div>
                        <div class="small opacity-75"><?php echo htmlspecialchars($formSection['section_code']); ?></div>
                    </div>
                </div>
            </div>

            <!-- Thông báo ẩn danh -->
            <div class="alert alert-info d-flex align-items-start gap-2 mb-4">
                <i class="bi bi-shield-lock-fill fs-5 mt-1 flex-shrink-0"></i>
                <div>
                    <div class="fw-semibold">Đánh giá hoàn toàn ẩn danh</div>
                    <div class="small">Giảng viên và nhà trường <strong>không biết</strong> bạn là ai. Hãy đánh giá trung thực để góp phần nâng cao chất lượng giảng dạy.</div>
                    <div class="small mt-1 text-muted">
                        <i class="bi bi-clipboard-check me-1"></i>Đợt: <strong><?php echo htmlspecialchars($formPeriod['title']); ?></strong>
                        <?php if ($formPeriod['end_date']): ?>
                        &nbsp;|&nbsp;<i class="bi bi-clock me-1"></i>Hạn: <strong><?php echo date('d/m/Y H:i', strtotime($formPeriod['end_date'])); ?></strong>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Form -->
            <form method="POST" id="evalForm">
                <input type="hidden" name="action" value="submit_evaluation">
                <input type="hidden" name="period_id" value="<?php echo $formPeriod['id']; ?>">
                <input type="hidden" name="course_section_id" value="<?php echo $formSection['section_id']; ?>">
                <input type="hidden" name="teacher_id" value="<?php echo $formSection['teacher_id']; ?>">

                <?php
                $ratingQs = array_values(array_filter($questionList, fn($q) => $q['question_type'] === 'rating'));
                $textQs   = array_values(array_filter($questionList, fn($q) => $q['question_type'] === 'text'));
                ?>

                <?php if (!empty($ratingQs)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-star-fill text-warning me-2"></i>
                        <strong>Đánh giá chất lượng giảng dạy</strong>
                        <span class="text-muted small ms-2">— Bắt buộc chọn tất cả</span>
                    </div>
                    <div class="card-body d-flex flex-column gap-3">
                        <?php foreach ($ratingQs as $i => $q): ?>
                        <div class="question-card card" id="qcard_<?php echo $q['id']; ?>">
                            <div class="card-body">
                                <div class="fw-semibold mb-1 text-navy">
                                    <?php echo ($i+1); ?>. <?php echo htmlspecialchars($q['question_text']); ?>
                                    <span class="text-danger">*</span>
                                </div>
                                <div class="text-muted small mb-3">1 = Rất không hài lòng &nbsp;→&nbsp; 5 = Rất hài lòng</div>
                                <div class="star-rating mb-1" id="stars_<?php echo $q['id']; ?>">
                                    <?php for ($v = 1; $v <= 5; $v++): ?>
                                    <input type="radio" name="rating_<?php echo $q['id']; ?>"
                                           id="star_<?php echo $q['id']; ?>_<?php echo $v; ?>"
                                           value="<?php echo $v; ?>">
                                    <label for="star_<?php echo $q['id']; ?>_<?php echo $v; ?>"
                                           data-val="<?php echo $v; ?>"
                                           title="<?php echo isset($levels[$v-1]) ? htmlspecialchars($levels[$v-1]['level_name']) : $v; ?>">
                                        <i class="bi bi-star-fill"></i>
                                    </label>
                                    <?php endfor; ?>
                                </div>
                                <?php if (!empty($levels)): ?>
                                <div class="d-flex mt-2" style="max-width:290px;">
                                    <?php foreach ($levels as $lv): ?>
                                    <div class="text-center flex-fill">
                                        <div class="small fw-bold text-muted"><?php echo $lv['level_value']; ?></div>
                                        <div style="font-size:0.6rem;color:#aaa;line-height:1.3;"><?php echo htmlspecialchars($lv['level_name']); ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                <div class="mt-2 small fst-italic selected-label-<?php echo $q['id']; ?> text-muted"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($textQs)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-chat-left-text-fill text-success me-2"></i>
                        <strong>Góp ý &amp; Nhận xét</strong>
                        <span class="text-muted small ms-2">— Ghi bằng chữ, không bắt buộc</span>
                    </div>
                    <div class="card-body d-flex flex-column gap-3">
                        <?php foreach ($textQs as $i => $q): ?>
                        <div class="question-card text-type card">
                            <div class="card-body">
                                <div class="fw-semibold mb-1 text-navy">
                                    <?php echo ($i+1); ?>. <?php echo htmlspecialchars($q['question_text']); ?>
                                </div>
                                <div class="text-muted small mb-2">
                                    <i class="bi bi-pencil-fill me-1"></i>Viết góp ý của bạn bằng chữ vào ô bên dưới
                                </div>
                                <textarea name="comment_<?php echo $q['id']; ?>" class="form-control" rows="4"
                                    placeholder="Ví dụ: Giảng viên giảng dễ hiểu, cần thêm bài tập thực hành..."></textarea>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (empty($questionList)): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>Chưa có câu hỏi đánh giá nào. Vui lòng liên hệ phòng đào tạo.
                </div>
                <?php else: ?>

                <!-- ===== Ô GÓP Ý THÊM CỐ ĐỊNH ===== -->
                <div class="card mb-4" style="border-left:4px solid #f0a500; border-radius:8px;">
                    <div class="card-header" style="background:linear-gradient(135deg,#fff8e1,#fff3cd); border-bottom:1px solid #ffe082;">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-chat-quote-fill fs-5" style="color:#f0a500;"></i>
                            <div>
                                <div class="fw-bold text-navy">Góp ý thêm cho giảng viên</div>
                                <div class="text-muted small">Bạn có thể ghi thêm bất kỳ ý kiến nào muốn gửi đến giảng viên — không bắt buộc</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <label class="form-label fw-semibold text-navy mb-2">
                            <i class="bi bi-pencil-square me-1"></i>
                            Ý kiến của bạn <span class="text-muted fw-normal">(ghi bằng chữ)</span>
                        </label>
                        <textarea name="extra_comment" id="extraComment" class="form-control" rows="4"
                            style="border:2px solid #ffe082; border-radius:8px; resize:vertical;"
                            placeholder="Ví dụ:&#10;- Thầy/Cô giảng rất dễ hiểu, có nhiều ví dụ thực tế&#10;- Mong thầy/cô tăng thêm bài tập thực hành&#10;- Tài liệu học tập cần cập nhật hơn..."></textarea>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div class="text-muted small">
                                <i class="bi bi-shield-lock me-1"></i>Góp ý hoàn toàn ẩn danh
                            </div>
                            <div class="text-muted small" id="charCount">0 ký tự</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-3 align-items-center flex-wrap">
                    <button type="submit" class="btn btn-navy btn-lg px-5" id="submitBtn">
                        <i class="bi bi-send-fill me-2"></i>Gửi đánh giá
                    </button>
                    <a href="/university/student/evaluation.php" class="btn btn-outline-secondary btn-lg">
                        <i class="bi bi-arrow-left me-1"></i>Hủy
                    </a>
                    <span class="text-muted small"><i class="bi bi-lock-fill me-1"></i>Ẩn danh hoàn toàn</span>
                </div>
                <?php endif; ?>
            </form>

            <?php else: ?>
            <!-- ===== DANH SÁCH LỚP HỌC PHẦN CẦN ĐÁNH GIÁ ===== -->
            <?php if (empty($openPeriodList)): ?>
            <div class="card">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-calendar-x fs-2 d-block mb-3"></i>
                    <div class="fw-semibold mb-1">Hiện không có đợt đánh giá nào đang mở</div>
                    <div class="small">Nhà trường sẽ thông báo khi mở đợt đánh giá mới.</div>
                </div>
            </div>
            <?php else: ?>
            <?php foreach ($openPeriodList as $period):
                $sections   = $sectionsByPeriod[$period['id']] ?? [];
                $doneCount  = count(array_filter($sections, fn($s) => $s['evaluated']));
                $totalCount = count($sections);
                $pct        = $totalCount > 0 ? round($doneCount / $totalCount * 100) : 0;
            ?>
            <div class="card mb-4">
                <!-- Header đợt đánh giá -->
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="bi bi-clipboard-check-fill text-success fs-5"></i>
                                <span class="fw-bold text-white"><?php echo htmlspecialchars($period['title']); ?></span>
                                <span class="badge bg-success">Đang mở</span>
                            </div>
                            <div class="small" style="color:rgba(255,255,255,0.75)">
                                <i class="bi bi-calendar3 me-1"></i>
                                <?php echo htmlspecialchars($period['semester_name'] . ' ' . $period['school_year']); ?>
                                <?php if ($period['end_date']): ?>
                                &nbsp;|&nbsp;<i class="bi bi-clock me-1"></i>Hạn:
                                <strong style="color:#ffc107"><?php echo date('d/m/Y H:i', strtotime($period['end_date'])); ?></strong>
                                <?php endif; ?>
                            </div>
                            <?php if ($period['description']): ?>
                            <div class="small mt-1" style="color:rgba(255,255,255,0.65)"><?php echo htmlspecialchars($period['description']); ?></div>
                            <?php endif; ?>
                        </div>
                        <!-- Thanh tiến độ -->
                        <div class="text-end" style="min-width:140px;">
                            <div class="small mb-1" style="color:rgba(255,255,255,0.75)">Tiến độ: <strong class="text-white"><?php echo $doneCount; ?>/<?php echo $totalCount; ?></strong> môn</div>
                            <div class="progress" style="height:8px;width:140px;background:rgba(255,255,255,0.2)">
                                <div class="progress-bar bg-success" style="width:<?php echo $pct; ?>%"></div>
                            </div>
                            <div class="small mt-1" style="color:rgba(255,255,255,0.65)"><?php echo $pct; ?>% hoàn thành</div>
                        </div>
                    </div>
                </div>

                <!-- Bảng lớp học phần -->
                <div class="card-body p-0">
                    <?php if (empty($sections)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        Không có lớp học phần nào trong học kỳ này
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:40px">#</th>
                                    <th>Môn học</th>
                                    <th>Mã lớp HP</th>
                                    <th><i class="bi bi-person-badge-fill me-1 text-navy"></i>Giảng viên phụ trách</th>
                                    <th class="text-center" style="width:130px">Trạng thái</th>
                                    <th class="text-center" style="width:140px">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sections as $idx => $sec): ?>
                                <tr class="<?php echo $sec['evaluated'] ? 'evaluated-row' : 'section-row'; ?>"
                                    <?php if (!$sec['evaluated']): ?>
                                    onclick="window.location='/university/student/evaluation.php?period_id=<?php echo $period['id']; ?>&section_id=<?php echo $sec['section_id']; ?>'"
                                    <?php endif; ?>>
                                    <td class="text-muted"><?php echo $idx + 1; ?></td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($sec['subject_name']); ?></div>
                                        <span class="badge bg-navy"><?php echo $sec['credits']; ?> TC</span>
                                    </td>
                                    <td class="small text-muted fw-semibold"><?php echo htmlspecialchars($sec['section_code']); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle bg-navy text-white d-flex align-items-center justify-content-center flex-shrink-0"
                                                 style="width:34px;height:34px;font-size:0.9rem;font-weight:700;">
                                                <?php echo mb_substr($sec['teacher_name'], 0, 1); ?>
                                            </div>
                                            <div>
                                                <div class="fw-semibold small"><?php echo htmlspecialchars($sec['teacher_name']); ?></div>
                                                <?php if (!empty($sec['degree'])): ?>
                                                <div class="text-muted" style="font-size:0.72rem;"><?php echo htmlspecialchars($sec['degree']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($sec['faculty_name'])): ?>
                                                <div class="text-muted" style="font-size:0.7rem;"><?php echo htmlspecialchars($sec['faculty_name']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($sec['evaluated']): ?>
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle-fill me-1"></i>Đã đánh giá
                                        </span>
                                        <?php else: ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="bi bi-hourglass-split me-1"></i>Chưa đánh giá
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if (!$sec['evaluated']): ?>
                                        <a href="/university/student/evaluation.php?period_id=<?php echo $period['id']; ?>&section_id=<?php echo $sec['section_id']; ?>"
                                           class="btn btn-sm btn-navy" onclick="event.stopPropagation()">
                                            <i class="bi bi-star-fill me-1"></i>Đánh giá ngay
                                        </a>
                                        <?php else: ?>
                                        <span class="text-success small fw-semibold">
                                            <i class="bi bi-check2-circle me-1"></i>Hoàn thành
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-3 py-2 border-top bg-light d-flex justify-content-between align-items-center">
                        <span class="small text-muted">
                            <i class="bi bi-hand-index me-1"></i>Click vào hàng để bắt đầu đánh giá
                        </span>
                        <span class="small">
                            <?php if ($doneCount === $totalCount && $totalCount > 0): ?>
                            <span class="text-success fw-semibold">
                                <i class="bi bi-trophy-fill me-1"></i>Đã hoàn thành tất cả!
                            </span>
                            <?php else: ?>
                            Còn <strong class="text-danger"><?php echo $totalCount - $doneCount; ?></strong> môn chưa đánh giá
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
            <?php endif; ?>

        </div><!-- /.student-content -->
        <div class="student-footer">&copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một</div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<script>
// Hiển thị nhãn mức độ khi chọn sao
const levelLabels = <?php echo json_encode(array_column($levels, 'level_name', 'level_value')); ?>;

// ===== STAR RATING — JS thuần =====
function initStarRating() {
    document.querySelectorAll('.star-rating').forEach(function(group) {
        const qid    = group.id.replace('stars_', '');
        const labels = group.querySelectorAll('label');
        const inputs = group.querySelectorAll('input[type="radio"]');

        // Hàm highlight sao từ 1 đến val
        function highlightStars(val) {
            labels.forEach(function(lbl) {
                const v = parseInt(lbl.dataset.val);
                lbl.classList.toggle('active', v <= val);
            });
        }

        // Hover: sáng từ 1 đến sao đang hover
        labels.forEach(function(lbl) {
            lbl.addEventListener('mouseenter', function() {
                const val = parseInt(this.dataset.val);
                labels.forEach(function(l) {
                    l.classList.toggle('hover', parseInt(l.dataset.val) <= val);
                });
            });
            lbl.addEventListener('mouseleave', function() {
                labels.forEach(function(l) { l.classList.remove('hover'); });
            });
        });

        // Click: chọn sao, highlight cố định
        inputs.forEach(function(input) {
            input.addEventListener('change', function() {
                const val  = parseInt(this.value);
                const el   = document.querySelector('.selected-label-' + qid);
                const card = document.getElementById('qcard_' + qid);

                highlightStars(val);

                if (el) {
                    el.innerHTML = '<i class="bi bi-check-circle-fill text-success me-1"></i>'
                        + (levelLabels[val] || val + ' sao');
                    el.className = 'mt-2 small fw-semibold text-success selected-label-' + qid;
                }
                if (card) card.style.borderLeftColor = '#28a745';
            });
        });
    });
}
initStarRating();

// Đếm ký tự góp ý thêm
const extraTa = document.getElementById('extraComment');
const charCount = document.getElementById('charCount');
if (extraTa && charCount) {
    extraTa.addEventListener('input', function() {
        const len = this.value.length;
        charCount.textContent = len + ' ký tự';
        charCount.style.color = len > 500 ? '#dc3545' : '#888';
    });
}

// Validate trước khi submit
document.getElementById('evalForm')?.addEventListener('submit', function(e) {
    let firstError = null;
    this.querySelectorAll('.star-rating').forEach(function(group) {
        const qid  = group.id.replace('stars_', '');
        const card = document.getElementById('qcard_' + qid);
        if (!group.querySelector('input[type="radio"]:checked')) {
            if (card) card.style.borderLeftColor = '#dc3545';
            if (!firstError) firstError = group;
        }
    });
    if (firstError) {
        e.preventDefault();
        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        const btn = document.getElementById('submitBtn');
        if (btn) {
            btn.classList.replace('btn-navy', 'btn-danger');
            btn.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>Vui lòng đánh giá tất cả câu hỏi sao';
            setTimeout(() => {
                btn.classList.replace('btn-danger', 'btn-navy');
                btn.innerHTML = '<i class="bi bi-send-fill me-2"></i>Gửi đánh giá';
            }, 3000);
        }
        return;
    }
    const btn = document.getElementById('submitBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang gửi...';
    }
});
</script>
<?php include_once __DIR__ . "/../includes/analytics_widget.php"; ?>
</body>
</html>
