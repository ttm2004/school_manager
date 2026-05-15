<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/AcademicPolicy.php';
require_once 'includes/academic_helpers.php';
requireAnyRole(['academic_manager','academic_staff']);

$pageTitle = 'Mở lớp học phần chung';

function commonSectionsEnsureColumn(mysqli $conn, string $table, string $column, string $definition): void
{
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $chk = $conn->query("SHOW COLUMNS FROM `$safeTable` LIKE '$safeColumn'");
    if ($chk && $chk->num_rows === 0) {
        $conn->query("ALTER TABLE `$safeTable` ADD COLUMN $definition");
    }
}

function commonSectionsSemesterTerm(?string $name): int
{
    $text = mb_strtolower((string)$name, 'UTF-8');
    if (str_contains($text, 'he') || str_contains($text, 'hè')) return 3;
    if (preg_match('/([123])/', $text, $m)) return (int)$m[1];
    return 1;
}

function commonSectionsYearStart(?string $schoolYear): int
{
    if (preg_match('/(20[0-9]{2})/', (string)$schoolYear, $m)) return (int)$m[1];
    return (int)date('Y');
}

function commonSectionsBuildCode(mysqli $conn, string $subjectCode): string
{
    $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $subjectCode));
    if ($prefix === '') $prefix = 'CHUNG';
    $like = $prefix . '_%';
    $stmt = $conn->prepare("SELECT section_code FROM course_sections WHERE section_code LIKE ? ORDER BY section_code");
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $used = array_flip(array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'section_code'));
    $stmt->close();
    for ($i = 1; $i <= 99; $i++) {
        $code = $prefix . '_' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
        if (!isset($used[$code])) return $code;
    }
    return $prefix . '_' . date('His');
}

function commonSectionsIsTestSemester(array $semester): bool
{
    return (($semester['data_mode'] ?? 'system') === 'test')
        || str_contains(mb_strtolower((string)($semester['semester_name'] ?? ''), 'UTF-8'), 'test');
}

function commonSectionsCanOperate(array $semester): array
{
    if (commonSectionsIsTestSemester($semester)) {
        return [
            'allowed' => true,
            'message' => 'Chế độ demo: có thể mở full môn chung để kiểm thử, không phụ thuộc thời gian học kỳ thật.',
            'badge' => 'Demo',
        ];
    }

    if (($semester['status'] ?? '') !== 'open') {
        return [
            'allowed' => false,
            'message' => 'Học kỳ này chưa mở hoặc đã đóng. Bạn chỉ có thể xem các lớp học phần đã mở.',
            'badge' => 'Không mở',
        ];
    }

    $now = time();
    if (!empty($semester['end_date']) && strtotime((string)$semester['end_date'] . ' 23:59:59') < $now) {
        return [
            'allowed' => false,
            'message' => 'Học kỳ này đã qua ngày kết thúc. Bạn chỉ có thể xem các lớp học phần đã mở.',
            'badge' => 'Đã qua',
        ];
    }

    $windows = [
        [$semester['approval_start'] ?? null, $semester['approval_end'] ?? null],
        [$semester['register_start'] ?? null, $semester['register_end'] ?? null],
    ];
    foreach ($windows as [$start, $end]) {
        $startTs = $start ? strtotime((string)$start) : 0;
        $endTs = $end ? strtotime((string)$end) : 0;
        if ($startTs && $endTs && $startTs <= $now && $now <= $endTs) {
            return [
                'allowed' => true,
                'message' => 'Học kỳ thật đang trong thời gian cho phép mở/điều chỉnh lớp học phần.',
                'badge' => 'Đang mở',
            ];
        }
    }

    return [
        'allowed' => false,
        'message' => 'Học kỳ này không còn trong thời gian mở lớp. Bạn chỉ có thể xem các lớp học phần đã mở.',
        'badge' => 'Ngoài thời gian',
    ];
}

commonSectionsEnsureColumn($conn, 'subjects', 'is_common', 'is_common TINYINT(1) NOT NULL DEFAULT 0');
$hasClassId = academicPolicyColumnExists($conn, 'course_sections', 'class_id');
$hasSemesterDataMode = academicPolicyColumnExists($conn, 'semesters', 'data_mode');
$semesterModeSelect = $hasSemesterDataMode ? 'data_mode' : "'system' AS data_mode";
$semesterModeExpr = $hasSemesterDataMode ? 'data_mode' : "'system'";
$semesterBatchSelect = academicPolicyColumnExists($conn, 'semesters', 'demo_batch_id') ? 'demo_batch_id' : "'' AS demo_batch_id";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['_csrf_token'] ?? '')) {
        $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'CSRF invalid.'];
        header('Location: common_course_sections.php'); exit();
    }
    if (!isAcademicManager()) {
        $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Chi Truong phong moi co quyen.'];
        header('Location: common_course_sections.php'); exit();
    }

    $semesterId = (int)($_POST['semester_id'] ?? 0);
    $status = in_array(($_POST['status'] ?? 'open'), ['open','proposed'], true) ? $_POST['status'] : 'open';
    $subjects = array_values(array_unique(array_map('intval', $_POST['selected_subject_ids'] ?? [])));
    $counts = $_POST['section_count'] ?? [];
    $targets = $_POST['target_sections'] ?? [];
    $maxes = $_POST['max_students'] ?? [];

    $stmtSem = $conn->prepare("SELECT id, semester_name, start_date, end_date, status, approval_start, approval_end, register_start, register_end, $semesterModeSelect, $semesterBatchSelect FROM semesters WHERE id=? LIMIT 1");
    $stmtSem->bind_param('i', $semesterId);
    $stmtSem->execute();
    $semester = $stmtSem->get_result()->fetch_assoc();
    $stmtSem->close();

    if (!$semester) {
        $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Học kỳ không hợp lệ.'];
        header('Location: common_course_sections.php'); exit();
    }
    $actionState = commonSectionsCanOperate($semester);
    if (!$actionState['allowed']) {
        $_SESSION['_flash'] = ['type' => 'warning', 'message' => $actionState['message']];
        header('Location: common_course_sections.php?' . http_build_query(['semester_id' => $semesterId, 'major_id' => (int)($_POST['major_id'] ?? 0)])); exit();
    }

    $created = 0;
    $skipped = 0;
    $dataMode = (($semester['data_mode'] ?? 'system') === 'test') ? 'test' : 'system';
    foreach ($subjects as $subjectId) {
        $requestedCount = max(0, min(20, (int)($counts[$subjectId] ?? 0)));
        $targetTotal = max(0, min(20, (int)($targets[$subjectId] ?? 0)));
        $maxStudents = max(1, (int)($maxes[$subjectId] ?? 70));
        if ($requestedCount <= 0) continue;

        $stmtSubject = $conn->prepare("SELECT id, subject_code FROM subjects WHERE id=? AND is_common=1 LIMIT 1");
        $stmtSubject->bind_param('i', $subjectId);
        $stmtSubject->execute();
        $subject = $stmtSubject->get_result()->fetch_assoc();
        $stmtSubject->close();
        if (!$subject) continue;

        if ($hasClassId) {
            $stmtExisting = $conn->prepare(
                "SELECT COUNT(*) AS opened_sections
                 FROM course_sections
                 WHERE subject_id=? AND semester_id=? AND target_cohort_id IS NULL AND class_id IS NULL AND data_mode=?"
            );
            $stmtExisting->bind_param('iis', $subjectId, $semesterId, $dataMode);
        } else {
            $stmtExisting = $conn->prepare(
                "SELECT COUNT(*) AS opened_sections
                 FROM course_sections
                 WHERE subject_id=? AND semester_id=? AND target_cohort_id IS NULL AND data_mode=?"
            );
            $stmtExisting->bind_param('iis', $subjectId, $semesterId, $dataMode);
        }
        $stmtExisting->execute();
        $openedSections = (int)($stmtExisting->get_result()->fetch_assoc()['opened_sections'] ?? 0);
        $stmtExisting->close();

        $remainingToTarget = $targetTotal > 0 ? max(0, $targetTotal - $openedSections) : $requestedCount;
        $sectionCount = min($requestedCount, $remainingToTarget);
        if ($sectionCount <= 0) {
            $skipped++;
            continue;
        }

        for ($i = 0; $i < $sectionCount; $i++) {
            $code = commonSectionsBuildCode($conn, (string)$subject['subject_code']);
            $room = '';
            $demoBatchId = (string)($semester['demo_batch_id'] ?? '');
            $daySessions = '';
            $teachingMode = 'offline';
            $startDate = $semester['start_date'] ?: null;
            $endDate = $semester['end_date'] ?: null;
            if ($hasClassId) {
                $stmtIns = $conn->prepare(
                    "INSERT INTO course_sections
                     (subject_id, teacher_id, semester_id, target_cohort_id, section_code, room, classroom_id, max_students, current_students, status, data_mode, demo_batch_id, day_sessions, start_date, end_date, teaching_mode, class_id)
                     VALUES (?,NULL,?,NULL,?,?,NULL,?,0,?,?,?,?,?,?,?,NULL)"
                );
                $stmtIns->bind_param('iississsssss', $subjectId, $semesterId, $code, $room, $maxStudents, $status, $dataMode, $demoBatchId, $daySessions, $startDate, $endDate, $teachingMode);
            } else {
                $stmtIns = $conn->prepare(
                    "INSERT INTO course_sections
                     (subject_id, teacher_id, semester_id, target_cohort_id, section_code, room, classroom_id, max_students, current_students, status, data_mode, demo_batch_id, day_sessions, start_date, end_date, teaching_mode)
                     VALUES (?,NULL,?,NULL,?,?,NULL,?,0,?,?,?,?,?,?,?)"
                );
                $stmtIns->bind_param('iississsssss', $subjectId, $semesterId, $code, $room, $maxStudents, $status, $dataMode, $demoBatchId, $daySessions, $startDate, $endDate, $teachingMode);
            }
            if ($stmtIns->execute()) $created++;
            $stmtIns->close();
        }
    }

    $message = "Đã tạo $created lớp học phần chung.";
    if ($skipped > 0) {
        $message .= " Bỏ qua $skipped môn đã đủ số lớp cần mở.";
    }
    $_SESSION['_flash'] = ['type' => 'success', 'message' => $message];
    header('Location: common_course_sections.php?' . http_build_query(['semester_id' => $semesterId, 'major_id' => (int)($_POST['major_id'] ?? 0)])); exit();
}

$flash = getFlash();
$filterSemester = (int)($_GET['semester_id'] ?? 0);
$filterMajor = (int)($_GET['major_id'] ?? 0);

$semesters = $conn->query(
    "SELECT id, semester_name, school_year, start_date, end_date, status, approval_start, approval_end, register_start, register_end, $semesterModeSelect
     FROM semesters
     ORDER BY CASE WHEN $semesterModeExpr='test' OR LOWER(semester_name) LIKE '%test%' THEN 0 ELSE 1 END,
              school_year DESC, id DESC"
)->fetch_all(MYSQLI_ASSOC);
if (!$filterSemester && !empty($semesters)) $filterSemester = (int)$semesters[0]['id'];

$majors = $conn->query("SELECT id, major_code, major_name FROM majors ORDER BY major_name")->fetch_all(MYSQLI_ASSOC);

$semester = null;
foreach ($semesters as $sem) {
    if ((int)$sem['id'] === $filterSemester) { $semester = $sem; break; }
}

$commonSubjects = [];
$isTestSemester = false;
$actionState = ['allowed' => false, 'message' => 'Chưa chọn học kỳ.', 'badge' => 'Chưa chọn'];
$emptyMessage = 'Chưa có môn chung phù hợp. Hãy đánh dấu/import môn chung trong Chương trình đào tạo trước.';
if ($semester) {
    $actionState = commonSectionsCanOperate($semester);
    $mode = commonSectionsIsTestSemester($semester) ? 'test' : 'system';
    $isTestSemester = $mode === 'test';
    $term = commonSectionsSemesterTerm($semester['semester_name'] ?? '');
    $yearStart = commonSectionsYearStart($semester['school_year'] ?? '');
    $majorWhere = $filterMajor > 0 ? 'AND cur.major_id = ?' : '';
    if ($isTestSemester) {
        $sql = "
            SELECT s.id, s.subject_code, s.subject_name, s.credits,
                   COUNT(DISTINCT st.id) AS eligible_students,
                   COUNT(DISTINCT cur.major_id) AS major_count,
                   COALESCE(opened.opened_sections, 0) AS opened_sections
            FROM subjects s
            JOIN curriculum cur ON cur.subject_id = s.id AND cur.deleted_at IS NULL
            LEFT JOIN classes cl ON cl.major_id = cur.major_id AND COALESCE(cl.data_mode, 'system') = 'test'
            LEFT JOIN students st ON st.class_id = cl.id AND COALESCE(st.data_mode, 'system') = 'test'
            LEFT JOIN (
                SELECT subject_id, COUNT(*) AS opened_sections
                FROM course_sections
                WHERE semester_id = ? AND target_cohort_id IS NULL " . ($hasClassId ? "AND class_id IS NULL " : "") . "
                GROUP BY subject_id
            ) opened ON opened.subject_id = s.id
            WHERE s.is_common = 1
              $majorWhere
            GROUP BY s.id, s.subject_code, s.subject_name, s.credits, opened.opened_sections
            ORDER BY s.subject_name, s.subject_code
        ";
        $stmt = $conn->prepare($sql);
        if ($filterMajor > 0) {
            $stmt->bind_param('ii', $filterSemester, $filterMajor);
        } else {
            $stmt->bind_param('i', $filterSemester);
        }
    } else {
        $sql = "
            SELECT s.id, s.subject_code, s.subject_name, s.credits,
                   COUNT(DISTINCT st.id) AS eligible_students,
                   COUNT(DISTINCT cur.major_id) AS major_count,
                   COALESCE(opened.opened_sections, 0) AS opened_sections
            FROM subjects s
            JOIN curriculum cur ON cur.subject_id = s.id AND cur.deleted_at IS NULL
            JOIN classes cl ON cl.major_id = cur.major_id
            LEFT JOIN training_cohorts tc ON tc.id = cl.cohort_id
            JOIN students st ON st.class_id = cl.id
            LEFT JOIN (
                SELECT subject_id, COUNT(*) AS opened_sections
                FROM course_sections
                WHERE semester_id = ? AND target_cohort_id IS NULL " . ($hasClassId ? "AND class_id IS NULL " : "") . "
                GROUP BY subject_id
            ) opened ON opened.subject_id = s.id
            WHERE s.is_common = 1
              AND COALESCE(st.data_mode, 'system') = ?
              AND (($yearStart - COALESCE(st.enrollment_year, tc.enrollment_year, 0)) * 3 + " . min(2, max(1, $term)) . ") = cur.suggested_semester
              AND NOT EXISTS (
                  SELECT 1
                  FROM student_subjects ss_done
                  JOIN course_sections cs_done ON cs_done.id = ss_done.course_section_id
                  JOIN grades g_done ON g_done.student_subject_id = ss_done.id
                  WHERE ss_done.student_id = st.id
                    AND cs_done.subject_id = s.id
                    AND g_done.final_score >= 5
                  LIMIT 1
              )
              $majorWhere
            GROUP BY s.id, s.subject_code, s.subject_name, s.credits, opened.opened_sections
            ORDER BY s.subject_name, s.subject_code
        ";
        $stmt = $conn->prepare($sql);
        if ($filterMajor > 0) {
            $stmt->bind_param('isi', $filterSemester, $mode, $filterMajor);
        } else {
            $stmt->bind_param('is', $filterSemester, $mode);
        }
    }
    $stmt->execute();
    $commonSubjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($commonSubjects) && !$isTestSemester) {
        $stmtDiag = $conn->prepare(
            "SELECT COUNT(DISTINCT s.id) AS common_count
             FROM subjects s
             JOIN curriculum cur ON cur.subject_id = s.id AND cur.deleted_at IS NULL
             WHERE s.is_common = 1 $majorWhere"
        );
        if ($filterMajor > 0) $stmtDiag->bind_param('i', $filterMajor);
        $stmtDiag->execute();
        $commonCount = (int)($stmtDiag->get_result()->fetch_assoc()['common_count'] ?? 0);
        $stmtDiag->close();
        $emptyMessage = $commonCount > 0
            ? 'Hệ thống thật chưa có sinh viên đúng học kỳ CTĐT để mở môn chung trong học kỳ này. Kiểm tra khóa tuyển sinh, học kỳ CTĐT hoặc dữ liệu sinh viên.'
            : 'Chưa có môn chung phù hợp. Hãy đánh dấu/import môn chung trong Chương trình đào tạo trước.';
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
<div class="admin-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
        <span class="admin-topbar-title"><i class="bi bi-collection-fill me-2 text-navy"></i>Mở lớp học phần chung</span>
    </div>
    <span class="text-muted small"><?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?></span>
</div>
<div class="admin-content">
<?php if ($flash): ?><div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show auto-dismiss mb-3"><?php echo htmlspecialchars($flash['message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="card mb-3"><div class="card-body">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-4"><label class="form-label small fw-bold">Học kỳ mở</label>
            <select name="semester_id" class="form-select" onchange="this.form.submit()">
                <?php foreach ($semesters as $sem): ?><option value="<?php echo (int)$sem['id']; ?>" <?php echo $filterSemester===(int)$sem['id']?'selected':''; ?>><?php echo htmlspecialchars($sem['semester_name'].' - '.$sem['school_year']); ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4"><label class="form-label small fw-bold">Ngành</label>
            <select name="major_id" class="form-select" onchange="this.form.submit()">
                <option value="0">-- Tất cả ngành có môn chung --</option>
                <?php foreach ($majors as $major): ?><option value="<?php echo (int)$major['id']; ?>" <?php echo $filterMajor===(int)$major['id']?'selected':''; ?>><?php echo htmlspecialchars($major['major_code'].' - '.$major['major_name']); ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto"><button class="btn btn-navy"><i class="bi bi-search"></i></button></div>
    </form>
</div></div>

<form method="post">
<?php echo csrfField(); ?>
<input type="hidden" name="semester_id" value="<?php echo $filterSemester; ?>">
<input type="hidden" name="major_id" value="<?php echo $filterMajor; ?>">
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-list-check me-2"></i>Môn chung đủ điều kiện mở lớp
            <span class="badge bg-<?php echo $actionState['allowed'] ? ($isTestSemester ? 'info' : 'success') : 'secondary'; ?> ms-2"><?php echo htmlspecialchars($actionState['badge']); ?></span>
        </span>
        <?php if (isAcademicManager()): ?>
        <div class="d-flex gap-2 align-items-center">
            <button type="button" class="btn btn-sm btn-outline-light" id="selectMissingCommonSubjects" <?php echo $actionState['allowed'] ? '' : 'disabled'; ?>><i class="bi bi-check2-square me-1"></i>Chọn môn còn thiếu</button>
            <button type="button" class="btn btn-sm btn-outline-light" id="clearCommonSubjects" <?php echo $actionState['allowed'] ? '' : 'disabled'; ?>><i class="bi bi-square me-1"></i>Bỏ chọn</button>
            <select name="status" class="form-select form-select-sm" style="width:150px" <?php echo $actionState['allowed'] ? '' : 'disabled'; ?>>
                <option value="open">Mở đăng ký</option>
                <option value="proposed">Chờ duyệt</option>
            </select>
            <a class="btn btn-sm btn-outline-light" href="course_sections.php?semester_id=<?php echo (int)$filterSemester; ?>&class_filter=common"><i class="bi bi-grid-3x3-gap me-1"></i>Quản lý lớp đã mở</a>
            <button type="submit" class="btn btn-sm btn-gold" <?php echo $actionState['allowed'] ? '' : 'disabled'; ?>><i class="bi bi-plus-square me-1"></i>Mở lớp còn thiếu</button>
        </div>
        <?php endif; ?>
    </div>
    <div class="px-3 py-2 small text-<?php echo $actionState['allowed'] ? 'muted' : 'danger'; ?> border-bottom">
        <?php echo htmlspecialchars($actionState['message']); ?>
    </div>
    <?php if (empty($commonSubjects)): ?>
    <div class="card-body text-center text-muted py-4"><?php echo htmlspecialchars($emptyMessage); ?></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr><th style="width:44px" class="text-center"><input type="checkbox" class="form-check-input" id="selectAllCommonSubjects" title="Chọn tất cả môn còn thiếu lớp" <?php echo $actionState['allowed'] ? '' : 'disabled'; ?>></th><th>Môn học</th><th class="text-center">TC</th><th class="text-center">Ngành áp dụng</th><th class="text-center">SV đủ điều kiện</th><th class="text-center">Đã mở</th><th class="text-center">Cần có</th><th style="width:135px">Mở thêm</th><th style="width:130px">Sĩ số/lớp</th><th class="text-center">Trạng thái</th></tr></thead>
            <tbody>
            <?php foreach ($commonSubjects as $subject):
                $eligible = (int)$subject['eligible_students'];
                $opened = (int)$subject['opened_sections'];
                $targetSections = $eligible > 0 ? max(1, (int)ceil($eligible / 70)) : ($isTestSemester ? 1 : 0);
                $suggestedSections = max(0, $targetSections - $opened);
                $canSelectSubject = $actionState['allowed'] && $suggestedSections > 0;
                $rowStatus = !$actionState['allowed']
                    ? ['Không thao tác', 'secondary']
                    : ($suggestedSections > 0 ? ['Còn thiếu', 'warning'] : ['Đã đủ lớp', 'success']);
            ?>
            <tr>
                <td class="text-center">
                    <input type="checkbox"
                           class="form-check-input common-subject-check"
                           name="selected_subject_ids[]"
                           value="<?php echo (int)$subject['id']; ?>"
                           data-missing="<?php echo $suggestedSections > 0 ? '1' : '0'; ?>"
                           <?php echo $canSelectSubject ? 'checked' : ''; ?>
                           <?php echo $canSelectSubject ? '' : 'disabled'; ?>>
                </td>
                <td><div class="fw-semibold"><?php echo htmlspecialchars($subject['subject_name']); ?></div><code class="small"><?php echo htmlspecialchars($subject['subject_code']); ?></code></td>
                <td class="text-center"><span class="badge bg-navy"><?php echo (int)$subject['credits']; ?></span></td>
                <td class="text-center"><?php echo (int)$subject['major_count']; ?></td>
                <td class="text-center"><span class="badge bg-light text-dark border"><?php echo $eligible; ?></span></td>
                <td class="text-center"><?php echo $opened; ?></td>
                <td class="text-center"><span class="badge bg-light text-dark border"><?php echo $targetSections; ?></span></td>
                <td>
                    <input type="hidden" name="subject_ids[]" value="<?php echo (int)$subject['id']; ?>">
                    <input type="hidden" name="target_sections[<?php echo (int)$subject['id']; ?>]" value="<?php echo $targetSections; ?>">
                    <input type="number" name="section_count[<?php echo (int)$subject['id']; ?>]" class="form-control form-control-sm" min="0" max="20" value="<?php echo $suggestedSections; ?>" <?php echo $canSelectSubject ? '' : 'readonly'; ?>>
                </td>
                <td><input type="number" name="max_students[<?php echo (int)$subject['id']; ?>]" class="form-control form-control-sm" min="1" value="70" <?php echo $actionState['allowed'] ? '' : 'readonly'; ?>></td>
                <td class="text-center"><span class="badge bg-<?php echo $rowStatus[1]; ?>"><?php echo htmlspecialchars($rowStatus[0]); ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
</form>
</div>
<div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU</div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.getElementById('selectAllCommonSubjects');
    const checks = Array.from(document.querySelectorAll('.common-subject-check'));
    const selectMissing = document.getElementById('selectMissingCommonSubjects');
    const clearSelected = document.getElementById('clearCommonSubjects');
    if (!selectAll || checks.length === 0) return;

    const syncSelectAll = () => {
        const enabled = checks.filter(check => !check.disabled);
        const checked = enabled.filter(check => check.checked);
        selectAll.checked = enabled.length > 0 && checked.length === enabled.length;
        selectAll.indeterminate = checked.length > 0 && checked.length < enabled.length;
        selectAll.disabled = enabled.length === 0;
    };

    selectAll.addEventListener('change', () => {
        checks.forEach(check => {
            if (!check.disabled) check.checked = selectAll.checked;
        });
        syncSelectAll();
    });
    selectMissing?.addEventListener('click', () => {
        checks.forEach(check => {
            if (!check.disabled && check.dataset.missing === '1') check.checked = true;
        });
        syncSelectAll();
    });
    clearSelected?.addEventListener('click', () => {
        checks.forEach(check => {
            if (!check.disabled) check.checked = false;
        });
        syncSelectAll();
    });
    checks.forEach(check => check.addEventListener('change', syncSelectAll));
    syncSelectAll();
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
</body></html>
