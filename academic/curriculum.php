<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/academic_helpers.php';
requireAnyRole(['academic_manager','academic_staff']);

$pageTitle = 'Chương trình đào tạo';

function ctdtEnsureSubjectColumns(mysqli $conn): void
{
    $columns = [
        'semester_order' => 'semester_order TINYINT NOT NULL DEFAULT 1',
        'theory_periods' => 'theory_periods INT DEFAULT 30',
        'practice_periods' => 'practice_periods INT DEFAULT 0',
        'total_periods' => 'total_periods INT DEFAULT 30',
        'is_mandatory' => 'is_mandatory TINYINT(1) DEFAULT 1',
        'is_common' => 'is_common TINYINT(1) NOT NULL DEFAULT 0',
    ];
    foreach ($columns as $name => $definition) {
        $safe = $conn->real_escape_string($name);
        $chk = $conn->query("SHOW COLUMNS FROM subjects LIKE '$safe'");
        if ($chk && $chk->num_rows === 0) {
            $conn->query("ALTER TABLE subjects ADD COLUMN $definition");
        }
    }
}

function ctdtCsvText(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') return '';
    $supported = array_map('strtoupper', mb_list_encodings());
    $candidates = array_values(array_filter(['UTF-8', 'Windows-1258', 'CP1258', 'ISO-8859-1'], fn($enc) => in_array(strtoupper($enc), $supported, true)));
    $encoding = mb_detect_encoding($value, $candidates ?: ['UTF-8', 'ISO-8859-1'], true);
    if ($encoding && $encoding !== 'UTF-8') {
        $converted = @mb_convert_encoding($value, 'UTF-8', $encoding);
        if ($converted !== false) $value = $converted;
    }
    return preg_replace('/^\xEF\xBB\xBF/', '', $value);
}

function ctdtCsvYes(?string $value): bool
{
    $value = mb_strtolower(ctdtCsvText($value), 'UTF-8');
    return in_array($value, ['có', 'co', 'yes', '1', 'true'], true);
}

function ctdtSemesterNumber(string $label): int
{
    if (preg_match('/([0-9]+)/u', $label, $m)) {
        return max(1, (int)$m[1]);
    }
    return 1;
}

function ctdtRawSemesterNumber(string $label): int
{
    if (preg_match('/([0-9]+)/u', $label, $m)) {
        return max(1, (int)$m[1]);
    }
    return 1;
}

function ctdtSemesterLabelFromOrder(int $semesterOrder): string
{
    return 'Học kỳ ' . (($semesterOrder % 2) === 0 ? 2 : 1);
}

function ctdtDisplaySemesterLabelFromOrder(int $semesterOrder): string
{
    $slot = (($semesterOrder - 1) % 3) + 1;
    return 'Học kỳ ' . ($slot === 2 ? 2 : 1);
}

function ctdtYearStart(string $label): int
{
    if (preg_match('/(20[0-9]{2})/', $label, $m)) {
        return (int)$m[1];
    }
    return 0;
}

function ctdtCurriculumType(string $subjectCode, bool $isMandatory): string
{
    if (str_starts_with(strtoupper($subjectCode), 'KTCH')) return 'general';
    return $isMandatory ? 'required' : 'elective';
}

function ctdtCohortYearLabel(?array $cohort, int $semesterOrder, ?string $storedYearLabel): string
{
    if (!$cohort || empty($cohort['enrollment_year']) || $semesterOrder <= 0) {
        return (string)($storedYearLabel ?? '');
    }
    $start = (int)$cohort['enrollment_year'] + intdiv(max(0, $semesterOrder - 1), 3);
    return $start . '-' . ($start + 1);
}

ctdtEnsureSubjectColumns($conn);

$filterMajor = (int)($_GET['major_id'] ?? $_POST['major_id'] ?? 0);
$filterCohort = (int)($_GET['cohort_id'] ?? $_POST['cohort_id'] ?? 0);
$scope = trim($_GET['scope'] ?? $_POST['scope'] ?? 'all');
if (!in_array($scope, ['all','common','major'], true)) $scope = 'all';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['_csrf_token'] ?? '')) {
        $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Yêu cầu không hợp lệ. Vui lòng tải lại trang.'];
        header('Location: curriculum.php'); exit();
    }
    if (!isAcademicManager()) {
        $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Chỉ Trưởng phòng mới có quyền thực hiện.'];
        header('Location: curriculum.php'); exit();
    }

    $redirect = 'curriculum.php?' . http_build_query([
        'major_id' => $filterMajor,
        'cohort_id' => $filterCohort,
        'scope' => 'common',
    ]);

    if (($_POST['action'] ?? '') === 'import_common') {
        if ($filterMajor <= 0) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Vui lòng chọn ngành trước khi import môn chung.'];
            header('Location: ' . $redirect); exit();
        }
        if (!isset($_FILES['common_file']) || $_FILES['common_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Lỗi tải file lên.'];
            header('Location: ' . $redirect); exit();
        }
        if (strtolower(pathinfo($_FILES['common_file']['name'], PATHINFO_EXTENSION)) !== 'csv') {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Chỉ hỗ trợ file CSV.'];
            header('Location: ' . $redirect); exit();
        }

        $programId = 0;
        if ($filterCohort > 0) {
            $stmtCohort = $conn->prepare("SELECT program_id FROM training_cohorts WHERE id=? AND major_id=? LIMIT 1");
            $stmtCohort->bind_param('ii', $filterCohort, $filterMajor);
            $stmtCohort->execute();
            $programId = (int)($stmtCohort->get_result()->fetch_assoc()['program_id'] ?? 0);
            $stmtCohort->close();
        }

        $matched = 0;
        $updated = 0;
        $missing = [];
        $handle = fopen($_FILES['common_file']['tmp_name'], 'r');
        if ($handle) {
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") rewind($handle);
            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                $code = ctdtCsvText($row[0] ?? '');
                if ($code === '' || mb_strtolower($code, 'UTF-8') === 'mã mh' || mb_strtolower($code, 'UTF-8') === 'ma mh') continue;

                if ($programId > 0) {
                    $stmt = $conn->prepare(
                        "SELECT DISTINCT s.id
                         FROM subjects s
                         JOIN curriculum c ON c.subject_id=s.id AND c.deleted_at IS NULL
                         WHERE c.major_id=? AND (c.program_id IS NULL OR c.program_id=?) AND s.subject_code=?"
                    );
                    $stmt->bind_param('iis', $filterMajor, $programId, $code);
                } else {
                    $stmt = $conn->prepare(
                        "SELECT DISTINCT s.id
                         FROM subjects s
                         JOIN curriculum c ON c.subject_id=s.id AND c.deleted_at IS NULL
                         WHERE c.major_id=? AND s.subject_code=?"
                    );
                    $stmt->bind_param('is', $filterMajor, $code);
                }
                $stmt->execute();
                $ids = array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id'));
                $stmt->close();

                if (empty($ids)) {
                    $missing[] = $code;
                    continue;
                }
                foreach ($ids as $subjectId) {
                    $stmtUpdate = $conn->prepare("UPDATE subjects SET is_common=1 WHERE id=?");
                    $stmtUpdate->bind_param('i', $subjectId);
                    $stmtUpdate->execute();
                    if ($stmtUpdate->affected_rows > 0) $updated++;
                    $stmtUpdate->close();
                    $matched++;
                }
            }
            fclose($handle);
        }

        $message = "Đã import môn chung: $matched mã môn trùng CTĐT, $updated môn được cập nhật.";
        if (!empty($missing)) {
            $message .= ' Không có trong ngành/khóa đang lọc: ' . implode(', ', array_slice($missing, 0, 8)) . '.';
        }
        $_SESSION['_flash'] = ['type' => 'success', 'message' => $message];
        header('Location: ' . $redirect); exit();
    }

    if (($_POST['action'] ?? '') === 'import_curriculum') {
        $curriculumRedirect = 'curriculum.php?' . http_build_query([
            'major_id' => $filterMajor,
            'cohort_id' => $filterCohort,
            'scope' => $scope === 'common' ? 'all' : $scope,
        ]);
        if ($filterMajor <= 0 || $filterCohort <= 0) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Vui lòng chọn ngành và khóa trước khi import CTĐT.'];
            header('Location: ' . $curriculumRedirect); exit();
        }

        $stmtCohort = $conn->prepare("SELECT id, enrollment_year, program_id FROM training_cohorts WHERE id=? AND major_id=? LIMIT 1");
        $stmtCohort->bind_param('ii', $filterCohort, $filterMajor);
        $stmtCohort->execute();
        $targetCohort = $stmtCohort->get_result()->fetch_assoc();
        $stmtCohort->close();
        $targetProgramId = (int)($targetCohort['program_id'] ?? 0);
        $targetEnrollmentYear = (int)($targetCohort['enrollment_year'] ?? 0);
        if (!$targetCohort || $targetProgramId <= 0 || $targetEnrollmentYear <= 0) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Khóa được chọn chưa có phiên bản CTĐT hợp lệ.'];
            header('Location: ' . $curriculumRedirect); exit();
        }
        if (!isset($_FILES['curriculum_file']) || $_FILES['curriculum_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Lỗi tải file CTĐT lên.'];
            header('Location: ' . $curriculumRedirect); exit();
        }
        if (strtolower(pathinfo($_FILES['curriculum_file']['name'], PATHINFO_EXTENSION)) !== 'csv') {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Import CTĐT chỉ hỗ trợ file CSV.'];
            header('Location: ' . $curriculumRedirect); exit();
        }

        $rows = [];
        $sourceBaseYear = 0;
        $handle = fopen($_FILES['curriculum_file']['tmp_name'], 'r');
        if ($handle) {
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") rewind($handle);
            fgetcsv($handle, 0, ',');
            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                if (count($row) < 10) continue;
                $yearStart = ctdtYearStart(ctdtCsvText($row[11] ?? ''));
                if ($yearStart > 0 && ($sourceBaseYear === 0 || $yearStart < $sourceBaseYear)) {
                    $sourceBaseYear = $yearStart;
                }
                $rows[] = $row;
            }
            fclose($handle);
        }
        if (empty($rows)) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'File CTĐT không có dữ liệu hoặc sai định dạng.'];
            header('Location: ' . $curriculumRedirect); exit();
        }
        if ($sourceBaseYear <= 0) $sourceBaseYear = $targetEnrollmentYear;

        $mode = trim($_POST['import_mode'] ?? 'replace');
        if ($mode === 'replace') {
            $stmtDel = $conn->prepare("DELETE FROM curriculum WHERE major_id=? AND program_id=?");
            $stmtDel->bind_param('ii', $filterMajor, $targetProgramId);
            $stmtDel->execute();
            $stmtDel->close();
        }

        $inserted = 0;
        $updated = 0;
        $errors = [];
        foreach ($rows as $i => $row) {
            $subjectCode = ctdtCsvText($row[1] ?? '');
            $subjectName = ctdtCsvText($row[2] ?? '');
            $credits = (int)($row[4] ?? 0);
            $isMandatory = ctdtCsvYes($row[5] ?? '') ? 1 : 0;
            $totalPeriods = (int)($row[7] ?? 0);
            $theoryPeriods = (int)($row[8] ?? 0);
            $practicePeriods = (int)($row[9] ?? 0);
            $semesterLabel = ctdtCsvText($row[10] ?? '');
            $sourceYearStart = ctdtYearStart(ctdtCsvText($row[11] ?? ''));
            $semesterNumber = ctdtRawSemesterNumber($semesterLabel);
            $yearOffset = max(0, ($sourceYearStart ?: $sourceBaseYear) - $sourceBaseYear);
            if ($semesterNumber === 3) {
                continue;
            }
            $semesterOrder = ($yearOffset * 3) + $semesterNumber;
            $semesterLabel = ctdtDisplaySemesterLabelFromOrder($semesterOrder);
            $displayYearStart = $targetEnrollmentYear + intdiv(max(0, $semesterOrder - 1), 3);
            $yearLabel = $displayYearStart . '-' . ($displayYearStart + 1);

            if ($subjectCode === '' || $subjectName === '' || $credits <= 0) {
                $errors[] = 'Dòng ' . ($i + 2) . ': thiếu mã môn, tên môn hoặc tín chỉ.';
                continue;
            }
            if ($totalPeriods <= 0) $totalPeriods = $theoryPeriods + $practicePeriods;
            $subjectTypeNew = ctdtCurriculumType($subjectCode, (bool)$isMandatory);
            $subjectTypeVi = $isMandatory ? 'Bắt buộc' : 'Tự chọn';

            $stmtChk = $conn->prepare("SELECT id, is_common FROM subjects WHERE subject_code=? LIMIT 1");
            $stmtChk->bind_param('s', $subjectCode);
            $stmtChk->execute();
            $existing = $stmtChk->get_result()->fetch_assoc();
            $stmtChk->close();

            if ($existing) {
                $subjectId = (int)$existing['id'];
                $isCommon = (int)($existing['is_common'] ?? 0);
                $stmtUpd = $conn->prepare(
                    "UPDATE subjects SET subject_name=?, credits=?, theory_periods=?, practice_periods=?,
                        total_periods=?, subject_type_new=?, is_mandatory=?, semester_order=?, major_id=?, subject_type=?, is_common=?
                     WHERE id=?"
                );
                $stmtUpd->bind_param('siiiisiiisii', $subjectName, $credits, $theoryPeriods, $practicePeriods, $totalPeriods, $subjectTypeNew, $isMandatory, $semesterOrder, $filterMajor, $subjectTypeVi, $isCommon, $subjectId);
                $stmtUpd->execute();
                $stmtUpd->close();
                $updated++;
            } else {
                $stmtIns = $conn->prepare(
                    "INSERT INTO subjects
                        (major_id, subject_code, subject_name, credits, theory_periods, practice_periods,
                         total_periods, subject_type_new, is_mandatory, semester_order, subject_type, is_common)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,0)"
                );
                $stmtIns->bind_param('issiiiisiis', $filterMajor, $subjectCode, $subjectName, $credits, $theoryPeriods, $practicePeriods, $totalPeriods, $subjectTypeNew, $isMandatory, $semesterOrder, $subjectTypeVi);
                if (!$stmtIns->execute()) {
                    $errors[] = 'Dòng ' . ($i + 2) . ': lỗi thêm môn ' . $subjectCode . '.';
                    $stmtIns->close();
                    continue;
                }
                $subjectId = (int)$conn->insert_id;
                $stmtIns->close();
                $inserted++;
            }

            $stmtCurrChk = $conn->prepare("SELECT id FROM curriculum WHERE major_id=? AND program_id=? AND subject_id=? AND deleted_at IS NULL LIMIT 1");
            $stmtCurrChk->bind_param('iii', $filterMajor, $targetProgramId, $subjectId);
            $stmtCurrChk->execute();
            $existingCurr = $stmtCurrChk->get_result()->fetch_assoc();
            $stmtCurrChk->close();
            if ($existingCurr) {
                $currId = (int)$existingCurr['id'];
                $stmtCurrUpd = $conn->prepare(
                    "UPDATE curriculum SET credits=?, suggested_semester=?, semester_label=?, year_label=?, subject_type=?
                     WHERE id=?"
                );
                $stmtCurrUpd->bind_param('iisssi', $credits, $semesterOrder, $semesterLabel, $yearLabel, $subjectTypeNew, $currId);
                $stmtCurrUpd->execute();
                $stmtCurrUpd->close();
            } else {
                $stmtCurrIns = $conn->prepare(
                    "INSERT INTO curriculum (major_id, program_id, subject_id, credits, suggested_semester, semester_label, year_label, subject_type)
                     VALUES (?,?,?,?,?,?,?,?)"
                );
                $stmtCurrIns->bind_param('iiiiisss', $filterMajor, $targetProgramId, $subjectId, $credits, $semesterOrder, $semesterLabel, $yearLabel, $subjectTypeNew);
                $stmtCurrIns->execute();
                $stmtCurrIns->close();
            }
        }

        $totalCreditsStmt = $conn->prepare(
            "SELECT COALESCE(SUM(credits),0) AS total_credits
             FROM curriculum
             WHERE major_id=? AND program_id=? AND deleted_at IS NULL"
        );
        $totalCreditsStmt->bind_param('ii', $filterMajor, $targetProgramId);
        $totalCreditsStmt->execute();
        $programCredits = (int)($totalCreditsStmt->get_result()->fetch_assoc()['total_credits'] ?? 0);
        $totalCreditsStmt->close();
        if ($programCredits > 0) {
            $stmtProgram = $conn->prepare("UPDATE training_programs SET total_credits=? WHERE id=?");
            $stmtProgram->bind_param('ii', $programCredits, $targetProgramId);
            $stmtProgram->execute();
            $stmtProgram->close();
        }

        $message = "Đã import CTĐT khóa $targetEnrollmentYear: $inserted môn mới, $updated môn cập nhật.";
        if (!empty($errors)) {
            $message .= ' Một số lỗi: ' . implode('; ', array_slice($errors, 0, 5));
        }
        $_SESSION['_flash'] = ['type' => empty($errors) ? 'success' : 'warning', 'message' => $message];
        header('Location: ' . $curriculumRedirect); exit();
    }
}

$flash = getFlash();
$majors = $conn->query(
    "SELECT m.id, m.major_code, m.major_name, COALESCE(m.total_credits,0) AS total_credits, f.faculty_name
     FROM majors m
     LEFT JOIN faculties f ON f.id=m.faculty_id
     ORDER BY f.faculty_name, m.major_name"
)->fetch_all(MYSQLI_ASSOC);

$currentMajor = null;
foreach ($majors as $major) {
    if ((int)$major['id'] === $filterMajor) $currentMajor = $major;
}

$cohorts = [];
if ($filterMajor > 0) {
    $stmtCohorts = $conn->prepare(
        "SELECT id, cohort_code, cohort_name, enrollment_year, program_id
         FROM training_cohorts
         WHERE major_id=?
         ORDER BY enrollment_year DESC, id DESC"
    );
    $stmtCohorts->bind_param('i', $filterMajor);
    $stmtCohorts->execute();
    $cohorts = $stmtCohorts->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtCohorts->close();
}

$currentCohort = null;
foreach ($cohorts as $cohort) {
    if ((int)$cohort['id'] === $filterCohort) $currentCohort = $cohort;
}
$programId = (int)($currentCohort['program_id'] ?? 0);

$subjects = [];
if ($filterMajor > 0) {
    if ($programId > 0) {
        $stmt = $conn->prepare(
            "SELECT s.id, s.subject_code, s.subject_name, s.credits,
                    s.theory_periods, s.practice_periods, s.total_periods,
                    s.subject_type, s.subject_type_new, s.is_mandatory, s.is_common,
                    COALESCE(c.suggested_semester, s.semester_order, 1) AS semester_order,
                    c.semester_label, c.year_label, c.subject_type AS curriculum_type
             FROM curriculum c
             JOIN subjects s ON s.id=c.subject_id
             WHERE c.major_id=? AND c.deleted_at IS NULL
               AND (
                    c.program_id=?
                    OR (
                        c.program_id IS NULL
                        AND NOT EXISTS (
                            SELECT 1
                            FROM curriculum c2
                            WHERE c2.major_id=c.major_id
                              AND c2.subject_id=c.subject_id
                              AND c2.program_id=?
                              AND c2.deleted_at IS NULL
                            LIMIT 1
                        )
                    )
               )
             ORDER BY c.year_label, COALESCE(c.suggested_semester, s.semester_order, 1), s.is_mandatory DESC, s.subject_name"
        );
        $stmt->bind_param('iii', $filterMajor, $programId, $programId);
    } else {
        $stmt = $conn->prepare(
            "SELECT s.id, s.subject_code, s.subject_name, s.credits,
                    s.theory_periods, s.practice_periods, s.total_periods,
                    s.subject_type, s.subject_type_new, s.is_mandatory, s.is_common,
                    COALESCE(c.suggested_semester, s.semester_order, 1) AS semester_order,
                    c.semester_label, c.year_label, c.subject_type AS curriculum_type
             FROM curriculum c
             JOIN subjects s ON s.id=c.subject_id
             WHERE c.major_id=? AND c.deleted_at IS NULL AND c.program_id IS NULL
             ORDER BY c.year_label, COALESCE(c.suggested_semester, s.semester_order, 1), s.is_mandatory DESC, s.subject_name"
        );
        $stmt->bind_param('i', $filterMajor);
    }
    $stmt->execute();
    $subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($subjects)) {
        $stmt = $conn->prepare(
            "SELECT s.id, s.subject_code, s.subject_name, s.credits,
                    s.theory_periods, s.practice_periods, s.total_periods,
                    s.subject_type, s.subject_type_new, s.is_mandatory, s.is_common,
                    COALESCE(s.semester_order,1) AS semester_order,
                    NULL AS semester_label, NULL AS year_label, s.subject_type_new AS curriculum_type
             FROM subjects s
             WHERE s.major_id=?
             ORDER BY COALESCE(s.semester_order,1), s.is_mandatory DESC, s.subject_name"
        );
        $stmt->bind_param('i', $filterMajor);
        $stmt->execute();
        $subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

if ($scope === 'common') {
    $subjects = array_values(array_filter($subjects, fn($s) => !empty($s['is_common'])));
} elseif ($scope === 'major') {
    $subjects = array_values(array_filter($subjects, fn($s) => empty($s['is_common'])));
}

$grouped = [];
foreach ($subjects as $subject) {
    $subject['display_year_label'] = ctdtCohortYearLabel($currentCohort, (int)($subject['semester_order'] ?? 1), $subject['year_label'] ?? '');
    $subject['display_semester_label'] = ctdtDisplaySemesterLabelFromOrder((int)($subject['semester_order'] ?? 1));
    $year = (string)($subject['display_year_label'] ?? '');
    $sem = (int)($subject['semester_order'] ?? 1);
    $label = (string)($subject['display_semester_label'] ?? '');
    $key = ($year ?: '0000') . '|' . sprintf('%02d', $sem) . '|' . $label;
    $grouped[$key][] = $subject;
}
ksort($grouped);

$typeMap = [
    'required' => ['Bắt buộc', 'danger'],
    'elective' => ['Tự chọn', 'warning'],
    'general' => ['Đại cương', 'info'],
    'Bắt buộc' => ['Bắt buộc', 'danger'],
    'Tự chọn' => ['Tự chọn', 'warning'],
    'Bắt buộc' => ['Bắt buộc', 'danger'],
    'Tự chọn' => ['Tự chọn', 'warning'],
];

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
<div class="admin-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
        <span class="admin-topbar-title"><i class="bi bi-journal-bookmark-fill me-2 text-navy"></i>Chương trình đào tạo</span>
    </div>
    <span class="text-muted small"><?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?></span>
</div>

<div class="admin-content">
<?php if ($flash): ?><div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show auto-dismiss mb-3"><?php echo htmlspecialchars($flash['message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="card mb-3"><div class="card-body">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-4"><label class="form-label small fw-bold">Ngành đào tạo</label>
            <select name="major_id" class="form-select" onchange="this.form.submit()">
                <option value="0">-- Chọn ngành --</option>
                <?php foreach ($majors as $major): ?><option value="<?php echo (int)$major['id']; ?>" <?php echo $filterMajor===(int)$major['id']?'selected':''; ?>><?php echo htmlspecialchars($major['major_code'].' - '.$major['major_name'].' ('.$major['faculty_name'].')'); ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3"><label class="form-label small fw-bold">Khóa</label>
            <select name="cohort_id" class="form-select" onchange="this.form.submit()" <?php echo $filterMajor > 0 ? '' : 'disabled'; ?>>
                <option value="0">-- Tất cả khóa --</option>
                <?php foreach ($cohorts as $cohort): ?><option value="<?php echo (int)$cohort['id']; ?>" <?php echo $filterCohort===(int)$cohort['id']?'selected':''; ?>><?php echo htmlspecialchars($cohort['cohort_code'].' - Khóa '.$cohort['enrollment_year']); ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3"><label class="form-label small fw-bold">Lọc môn học</label>
            <select name="scope" class="form-select" onchange="this.form.submit()">
                <option value="all" <?php echo $scope==='all'?'selected':''; ?>>Tất cả trong CTĐT</option>
                <option value="common" <?php echo $scope==='common'?'selected':''; ?>>Môn chung</option>
                <option value="major" <?php echo $scope==='major'?'selected':''; ?>>Môn chuyên ngành</option>
            </select>
        </div>
        <div class="col-auto"><button class="btn btn-navy"><i class="bi bi-search"></i></button></div>
        <?php if (isAcademicManager() && $filterMajor > 0): ?>
        <?php if (false): ?>
        <div class="col-auto ms-auto">
            <?php if ($scope === 'common'): ?>
            <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#importCommonModal"><i class="bi bi-upload me-1"></i>Import môn chung</button>
            <a class="btn btn-outline-primary" href="/university/database/imports/mon_chung.csv"><i class="bi bi-download me-1"></i>Tải CSV môn chung</a>
            <a class="btn btn-gold" href="common_course_sections.php?major_id=<?php echo $filterMajor; ?>"><i class="bi bi-collection-fill me-1"></i>Mở lớp HP chung</a>
            <?php elseif ($currentCohort): ?>
            <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#importCurriculumModal"><i class="bi bi-upload me-1"></i>Import CTDT khoa <?php echo (int)$currentCohort['enrollment_year']; ?></button>
            <a class="btn btn-outline-primary" href="/university/database/imports/chuong_trinh_dao_tao.csv"><i class="bi bi-download me-1"></i>Tai CSV CTDT</a>
            <?php if (false): ?>
            <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#importCurriculumModal"><i class="bi bi-upload me-1"></i>Import CTĐT khóa <?php echo (int)$currentCohort['enrollment_year']; ?></button>
            <a class="btn btn-outline-primary" href="/university/database/imports/chuong_trinh_dao_tao.csv"><i class="bi bi-download me-1"></i>Tải CSV CTĐT</a>
            <?php else: ?>
            <button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-upload me-1"></i>Chọn khóa để import CTĐT</button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        <div class="col-auto ms-auto">
            <?php if ($scope === 'common'): ?>
            <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#importCommonModal"><i class="bi bi-upload me-1"></i>Import môn chung</button>
            <a class="btn btn-outline-primary" href="/university/database/imports/mon_chung.csv"><i class="bi bi-download me-1"></i>Tải CSV môn chung</a>
            <a class="btn btn-gold" href="common_course_sections.php?major_id=<?php echo $filterMajor; ?>"><i class="bi bi-collection-fill me-1"></i>Mở lớp HP chung</a>
            <?php elseif ($currentCohort): ?>
            <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#importCurriculumModal"><i class="bi bi-upload me-1"></i>Import CTĐT khóa <?php echo (int)$currentCohort['enrollment_year']; ?></button>
            <a class="btn btn-outline-primary" href="/university/database/imports/chuong_trinh_dao_tao.csv"><i class="bi bi-download me-1"></i>Tải CSV CTĐT</a>
            <?php else: ?>
            <button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-upload me-1"></i>Chọn khóa để import CTĐT</button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </form>
</div></div>

<?php if (!$filterMajor): ?>
<div class="card"><div class="card-body text-center text-muted py-5"><i class="bi bi-journal-bookmark fs-2 d-block mb-2"></i>Chọn ngành để xem chương trình đào tạo.</div></div>
<?php else: ?>

<?php if ($currentMajor): ?>
<div class="card mb-3 border-0" style="background:linear-gradient(135deg,var(--navy),#245b87);color:white">
    <div class="card-body py-3 d-flex flex-wrap justify-content-between gap-3">
        <div>
            <div class="fw-bold fs-5"><?php echo htmlspecialchars($currentMajor['major_name']); ?></div>
            <div class="small" style="opacity:.82"><?php echo htmlspecialchars($currentMajor['major_code']); ?> | <?php echo htmlspecialchars($currentMajor['faculty_name']); ?></div>
            <?php if ($currentCohort): ?><div class="small" style="opacity:.82">Đang xem: <?php echo htmlspecialchars($currentCohort['cohort_code'].' - Khóa '.$currentCohort['enrollment_year']); ?></div><?php endif; ?>
        </div>
        <div class="d-flex gap-4 text-center">
            <div><div class="fw-bold fs-5"><?php echo (int)$currentMajor['total_credits']; ?></div><div class="small" style="opacity:.78">TC yêu cầu</div></div>
            <div><div class="fw-bold fs-5"><?php echo array_sum(array_map(fn($s) => (int)$s['credits'], $subjects)); ?></div><div class="small" style="opacity:.78">TC hiển thị</div></div>
            <div><div class="fw-bold fs-5"><?php echo count($subjects); ?></div><div class="small" style="opacity:.78">Môn học</div></div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (empty($subjects)): ?>
<div class="card"><div class="card-body text-center text-muted py-5">Không có môn học phù hợp bộ lọc.</div></div>
<?php else: ?>
<?php foreach ($grouped as $key => $semesterSubjects):
    [$yearLabel, $semesterOrderPad, $semesterLabel] = explode('|', $key);
    $semesterOrder = (int)$semesterOrderPad;
    $semesterCredits = array_sum(array_map(fn($s) => (int)$s['credits'], $semesterSubjects));
?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-calendar3 me-2"></i><strong><?php echo htmlspecialchars($semesterLabel ?: 'Học kỳ '.$semesterOrder); ?></strong><?php if ($yearLabel !== '0000'): ?><span class="text-muted ms-2 small"><?php echo htmlspecialchars($yearLabel); ?></span><?php endif; ?></span>
        <span class="badge bg-navy"><?php echo $semesterCredits; ?> TC | <?php echo count($semesterSubjects); ?> môn</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr><th style="width:42px">#</th><th>Mã môn</th><th>Tên môn học</th><th class="text-center">TC</th><th class="text-center">LT</th><th class="text-center">TH</th><th>Loại</th><th>Phạm vi</th></tr></thead>
            <tbody>
            <?php $idx = 1; foreach ($semesterSubjects as $subject):
                $typeKey = $subject['curriculum_type'] ?: ($subject['subject_type_new'] ?: $subject['subject_type']);
                $type = $typeMap[$typeKey] ?? ['Khác', 'secondary'];
                $isCommon = !empty($subject['is_common']);
            ?>
            <tr>
                <td class="text-muted"><?php echo $idx++; ?></td>
                <td><span class="badge bg-navy"><?php echo htmlspecialchars($subject['subject_code']); ?></span></td>
                <td class="fw-semibold"><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                <td class="text-center"><span class="badge bg-light text-dark border"><?php echo (int)$subject['credits']; ?></span></td>
                <td class="text-center text-muted"><?php echo (int)($subject['theory_periods'] ?? 0) ?: '-'; ?></td>
                <td class="text-center text-muted"><?php echo (int)($subject['practice_periods'] ?? 0) ?: '-'; ?></td>
                <td><span class="badge bg-<?php echo $type[1]; ?>"><?php echo htmlspecialchars($type[0]); ?></span></td>
                <td><span class="badge bg-<?php echo $isCommon ? 'primary' : 'secondary'; ?>"><?php echo $isCommon ? 'Môn chung' : 'Chuyên ngành'; ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>
</div>
<div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU</div>
</div>

<?php if (isAcademicManager() && $filterMajor > 0 && $scope === 'common'): ?>
<div class="modal fade" id="importCommonModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Import môn chung</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post" enctype="multipart/form-data">
        <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="import_common">
        <input type="hidden" name="major_id" value="<?php echo $filterMajor; ?>">
        <input type="hidden" name="cohort_id" value="<?php echo $filterCohort; ?>">
        <input type="hidden" name="scope" value="common">
        <div class="modal-body">
            <div class="alert alert-info small">Dùng file CSV danh sách môn chung gồm mã môn và tên môn. Hệ thống chỉ lấy mã môn để đối chiếu với CTĐT của ngành/khóa đang lọc; học kỳ và năm học giữ nguyên theo CTĐT đã import.</div>
            <input type="file" name="common_file" class="form-control" accept=".csv" required>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button><button class="btn btn-navy" type="submit"><i class="bi bi-upload me-1"></i>Import</button></div>
    </form>
</div></div></div>
<?php endif; ?>

<?php if (isAcademicManager() && $filterMajor > 0 && $currentCohort && $scope !== 'common'): ?>
<div class="modal fade" id="importCurriculumModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Import CTĐT khóa <?php echo (int)$currentCohort['enrollment_year']; ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post" enctype="multipart/form-data">
        <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="import_curriculum">
        <input type="hidden" name="major_id" value="<?php echo $filterMajor; ?>">
        <input type="hidden" name="cohort_id" value="<?php echo $filterCohort; ?>">
        <input type="hidden" name="scope" value="<?php echo htmlspecialchars($scope); ?>">
        <div class="modal-body">
            <div class="alert alert-info small">CSV dùng mẫu CTĐT hiện tại. Hệ thống gắn dữ liệu vào phiên bản CTĐT của khóa đang chọn và tự quy đổi năm học theo năm tuyển sinh của khóa.</div>
            <input type="file" name="curriculum_file" class="form-control mb-3" accept=".csv" required>
            <label class="form-label small fw-bold">Cách import</label>
            <select name="import_mode" class="form-select">
                <option value="replace">Thay thế CTĐT riêng của khóa này</option>
                <option value="append">Thêm/cập nhật, giữ dữ liệu đang có</option>
            </select>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button><button class="btn btn-navy" type="submit"><i class="bi bi-upload me-1"></i>Import CTĐT</button></div>
    </form>
</div></div></div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
</body></html>
