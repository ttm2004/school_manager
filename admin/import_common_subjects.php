<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('admin');

header('Content-Type: application/json; charset=utf-8');

function commonImportResponse(bool $success, string $message, array $extra = []): never
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

function commonImportText(?string $value): string
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

$chk = $conn->query("SHOW COLUMNS FROM subjects LIKE 'is_common'");
if ($chk && $chk->num_rows === 0) {
    $conn->query("ALTER TABLE subjects ADD COLUMN is_common TINYINT(1) NOT NULL DEFAULT 0");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    commonImportResponse(false, 'Phuong thuc khong hop le.');
}

$fallbackMajorId = (int)($_POST['major_id'] ?? 0);
if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    commonImportResponse(false, 'Loi upload file.');
}

$ext = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    commonImportResponse(false, 'Danh sach mon chung chi ho tro CSV.');
}

$handle = fopen($_FILES['excel_file']['tmp_name'], 'r');
if (!$handle) {
    commonImportResponse(false, 'Khong the doc file CSV.');
}

$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF") rewind($handle);

$rows = [];
$header = fgetcsv($handle, 0, ',');
while (($line = fgetcsv($handle, 0, ',')) !== false) {
    if (count(array_filter($line, fn($v) => trim((string)$v) !== '')) === 0) continue;
    $rows[] = $line;
}
fclose($handle);

if (empty($rows)) {
    commonImportResponse(false, 'File khong co du lieu.');
}

$marked = 0;
$inserted = 0;
$errors = [];

foreach ($rows as $idx => $row) {
    // Ho tro ca CSV CTDT: STT, Ma MH, Ten mon, ..., So TC
    // va CSV gon: Ma MH, Ten mon, So TC.
    $subjectCode = commonImportText($row[1] ?? '');
    $subjectName = commonImportText($row[2] ?? '');
    $credits = (int)($row[4] ?? 0);

    if ($subjectCode === '' || $subjectName === '') {
        $subjectCode = commonImportText($row[0] ?? '');
        $subjectName = commonImportText($row[1] ?? '');
        $credits = (int)($row[2] ?? 0);
    }

    if ($subjectCode === '' || $subjectName === '') {
        $errors[] = 'Dong ' . ($idx + 2) . ': thieu ma mon hoac ten mon.';
        continue;
    }
    if ($credits <= 0) $credits = 1;

    $stmtFind = $conn->prepare("SELECT id FROM subjects WHERE subject_code = ? LIMIT 1");
    $stmtFind->bind_param('s', $subjectCode);
    $stmtFind->execute();
    $existing = $stmtFind->get_result()->fetch_assoc();
    $stmtFind->close();

    if ($existing) {
        $subjectId = (int)$existing['id'];
        $stmtUpd = $conn->prepare("UPDATE subjects SET subject_name = ?, credits = CASE WHEN credits > 0 THEN credits ELSE ? END, is_common = 1 WHERE id = ?");
        $stmtUpd->bind_param('sii', $subjectName, $credits, $subjectId);
        if ($stmtUpd->execute()) $marked++;
        else $errors[] = 'Dong ' . ($idx + 2) . ': khong the cap nhat ' . $subjectCode;
        $stmtUpd->close();
        continue;
    }

    if ($fallbackMajorId <= 0) {
        $errors[] = 'Dong ' . ($idx + 2) . ': mon moi can chon nganh hien tai de tao ban ghi.';
        continue;
    }

    $stmtIns = $conn->prepare(
        "INSERT INTO subjects (major_id, subject_code, subject_name, credits, subject_type, subject_type_new, semester_order, is_mandatory, is_common)
         VALUES (?, ?, ?, ?, 'Bắt buộc', 'general', 1, 1, 1)"
    );
    $stmtIns->bind_param('issi', $fallbackMajorId, $subjectCode, $subjectName, $credits);
    if ($stmtIns->execute()) $inserted++;
    else $errors[] = 'Dong ' . ($idx + 2) . ': khong the tao mon ' . $subjectCode;
    $stmtIns->close();
}

$message = "Da danh dau $marked mon chung";
if ($inserted > 0) $message .= ", tao moi $inserted mon";
if (!empty($errors)) $message .= '. Loi: ' . implode('; ', array_slice($errors, 0, 5));

commonImportResponse(empty($errors) || ($marked + $inserted) > 0, $message, [
    'marked' => $marked,
    'inserted' => $inserted,
    'errors' => $errors,
]);
