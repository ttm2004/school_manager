<?php
/**
 * Import chương trình đào tạo từ CSV.
 * CSV: STT,Mã MH,Tên môn học,Chuyên ngành,Số tín chỉ,Môn bắt buộc,Đã học,
 *      Tổng tiết,Lý thuyết,Thực hành,Học kỳ,Năm học.
 */
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('admin');

header('Content-Type: application/json; charset=utf-8');

$commonCol = $conn->query("SHOW COLUMNS FROM subjects LIKE 'is_common'");
if ($commonCol && $commonCol->num_rows === 0) {
    $conn->query("ALTER TABLE subjects ADD COLUMN is_common TINYINT(1) NOT NULL DEFAULT 0");
}

function importResponse(bool $success, string $message, array $extra = []): never
{
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

function normalizeCsvText(?string $value): string
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

function getSemesterOrder(string $semLabel, string $yearLabel): int
{
    $semNum = (int)preg_replace('/\D/', '', $semLabel);
    $yearStart = (int)substr($yearLabel, 0, 4);
    if ($semNum <= 0 || $yearStart <= 0) return 1;

    $baseYear = 2022;
    return (($yearStart - $baseYear) * 3) + $semNum;
}

function normalizedSemesterLabelFromOrder(int $semesterOrder): string
{
    $slot = (($semesterOrder - 1) % 3) + 1;
    return 'Học kỳ ' . ($slot === 2 ? 2 : 1);
}

function normalizeCurriculumImportType(string $subjectCode, bool $isMandatory): string
{
    if (str_starts_with(strtoupper($subjectCode), 'KTCH')) return 'general';
    return $isMandatory ? 'required' : 'elective';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    importResponse(false, 'Phương thức không hợp lệ.');
}

$majorId = (int)($_POST['major_id'] ?? 0);
$mode    = trim($_POST['mode'] ?? 'append');

if ($majorId <= 0) {
    importResponse(false, 'Vui lòng chọn ngành.');
}

if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    importResponse(false, 'Lỗi upload file.');
}

$fileName = $_FILES['excel_file']['name'];
$ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
if (!in_array($ext, ['csv', 'xlsx', 'xls'], true)) {
    importResponse(false, 'Chỉ hỗ trợ file .csv, .xlsx, .xls.');
}

if ($ext !== 'csv') {
    importResponse(false, 'File Excel (.xlsx/.xls) chưa được hỗ trợ trực tiếp. Vui lòng chuyển sang CSV UTF-8.');
}

$rows = [];
$handle = fopen($_FILES['excel_file']['tmp_name'], 'r');
if (!$handle) {
    importResponse(false, 'Không thể đọc file CSV.');
}

$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF") rewind($handle);

fgetcsv($handle, 0, ',');
while (($line = fgetcsv($handle, 0, ',')) !== false) {
    if (count($line) >= 10) $rows[] = $line;
}
fclose($handle);

if (empty($rows)) {
    importResponse(false, 'File không có dữ liệu hoặc sai định dạng.');
}

if ($mode === 'replace') {
    $stmtDel = $conn->prepare("DELETE FROM curriculum WHERE major_id = ?");
    $stmtDel->bind_param('i', $majorId);
    $stmtDel->execute();
    $stmtDel->close();

    $stmtDelS = $conn->prepare("DELETE FROM subjects WHERE major_id = ? AND COALESCE(is_common,0)=0");
    $stmtDelS->bind_param('i', $majorId);
    $stmtDelS->execute();
    $stmtDelS->close();
}

$inserted = 0;
$updated  = 0;
$errors   = [];

foreach ($rows as $i => $row) {
    $subjectCode = normalizeCsvText($row[1] ?? '');
    $subjectName = normalizeCsvText($row[2] ?? '');
    $credits     = (int)($row[4] ?? 0);

    $isMandatoryRaw = mb_strtolower(normalizeCsvText($row[5] ?? ''));
    $isMandatory = in_array($isMandatoryRaw, ['có', 'co', 'yes', '1', 'true'], true) ? 1 : 0;

    $totalPeriods    = (int)($row[7] ?? 0);
    $theoryPeriods   = (int)($row[8] ?? 0);
    $practicePeriods = (int)($row[9] ?? 0);
    $semesterLabel   = normalizeCsvText($row[10] ?? '');
    $yearLabel       = normalizeCsvText($row[11] ?? '');

    if (!$subjectCode || !$subjectName || $credits <= 0) {
        $errors[] = 'Dòng ' . ($i + 2) . ': Thiếu dữ liệu bắt buộc.';
        continue;
    }

    if ($totalPeriods <= 0) $totalPeriods = $theoryPeriods + $practicePeriods;

    $subjectTypeNew = normalizeCurriculumImportType($subjectCode, (bool)$isMandatory);
    $subjectTypeVi  = $isMandatory ? 'Bắt buộc' : 'Tự chọn';
    if ((int)preg_replace('/\D/', '', $semesterLabel) === 3) {
        continue;
    }
    $semesterOrder  = getSemesterOrder($semesterLabel, $yearLabel);
    $semesterLabel  = normalizedSemesterLabelFromOrder($semesterOrder);

    $stmtChk = $conn->prepare("SELECT id, is_common FROM subjects WHERE subject_code = ? LIMIT 1");
    $stmtChk->bind_param('s', $subjectCode);
    $stmtChk->execute();
    $existing = $stmtChk->get_result()->fetch_assoc();
    $stmtChk->close();

    if ($existing) {
        $stmtUpd = $conn->prepare(
            "UPDATE subjects SET
                subject_name=?, credits=?, theory_periods=?, practice_periods=?,
                total_periods=?, subject_type_new=?, is_mandatory=?,
                semester_order=?, major_id=?, subject_type=?, is_common=?
             WHERE id=?"
        );
        $existingId = (int)$existing['id'];
        $isCommon = (int)($existing['is_common'] ?? 0);
        $stmtUpd->bind_param(
            'siiiisiiisii',
            $subjectName,
            $credits,
            $theoryPeriods,
            $practicePeriods,
            $totalPeriods,
            $subjectTypeNew,
            $isMandatory,
            $semesterOrder,
            $majorId,
            $subjectTypeVi,
            $isCommon,
            $existingId
        );
        $stmtUpd->execute();
        $stmtUpd->close();
        $subjectId = $existingId;
        $updated++;
    } else {
        $stmtIns = $conn->prepare(
            "INSERT INTO subjects
                (major_id, subject_code, subject_name, credits, theory_periods, practice_periods,
                 total_periods, subject_type_new, is_mandatory, semester_order, subject_type, is_common)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,0)"
        );
        $stmtIns->bind_param(
            'issiiiisiis',
            $majorId,
            $subjectCode,
            $subjectName,
            $credits,
            $theoryPeriods,
            $practicePeriods,
            $totalPeriods,
            $subjectTypeNew,
            $isMandatory,
            $semesterOrder,
            $subjectTypeVi
        );
        if (!$stmtIns->execute()) {
            $errors[] = 'Dòng ' . ($i + 2) . ": Lỗi insert môn $subjectCode - " . $conn->error;
            $stmtIns->close();
            continue;
        }
        $subjectId = (int)$conn->insert_id;
        $stmtIns->close();
        $inserted++;
    }

    $stmtCurrChk = $conn->prepare("SELECT id FROM curriculum WHERE major_id=? AND subject_id=? AND deleted_at IS NULL LIMIT 1");
    $stmtCurrChk->bind_param('ii', $majorId, $subjectId);
    $stmtCurrChk->execute();
    $existingCurr = $stmtCurrChk->get_result()->fetch_assoc();
    $stmtCurrChk->close();

    if ($existingCurr) {
        $stmtCurrUpd = $conn->prepare(
            "UPDATE curriculum SET
                credits=?, suggested_semester=?, semester_label=?, year_label=?, subject_type=?
             WHERE id=?"
        );
        $currId = (int)$existingCurr['id'];
        $stmtCurrUpd->bind_param('iisssi', $credits, $semesterOrder, $semesterLabel, $yearLabel, $subjectTypeNew, $currId);
        $stmtCurrUpd->execute();
        $stmtCurrUpd->close();
    } else {
        $stmtCurrIns = $conn->prepare(
            "INSERT INTO curriculum (major_id, subject_id, credits, suggested_semester, semester_label, year_label, subject_type)
             VALUES (?,?,?,?,?,?,?)"
        );
        $stmtCurrIns->bind_param('iiiisss', $majorId, $subjectId, $credits, $semesterOrder, $semesterLabel, $yearLabel, $subjectTypeNew);
        $stmtCurrIns->execute();
        $stmtCurrIns->close();
    }
}

$message = "Import thành công: $inserted môn mới, $updated môn cập nhật.";
if (!empty($errors)) {
    $message .= ' Lỗi: ' . implode('; ', array_slice($errors, 0, 5));
}

importResponse(true, $message, [
    'inserted' => $inserted,
    'updated' => $updated,
    'errors' => $errors,
]);
