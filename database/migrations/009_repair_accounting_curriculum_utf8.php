<?php
/**
 * Repair Accounting curriculum text from the UTF-8 CSV source.
 *
 * Run:
 *   php database/migrations/009_repair_accounting_curriculum_utf8.php
 */
require_once __DIR__ . '/../../config/app.php';

$conn = new mysqli(
    config('db.host'),
    config('db.user'),
    config('db.pass'),
    config('db.name'),
    config('db.port')
);
$conn->set_charset('utf8mb4');
$conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

if ($conn->connect_error) {
    fwrite(STDERR, "DB connection failed: {$conn->connect_error}\n");
    exit(1);
}

$csvPath = __DIR__ . '/../../chuong_trinh_dao_tao.csv';
if (!is_file($csvPath)) {
    fwrite(STDERR, "CSV not found: $csvPath\n");
    exit(1);
}

$major = $conn->query("SELECT id FROM majors WHERE major_code='7340301' LIMIT 1")->fetch_assoc();
if (!$major) {
    fwrite(STDERR, "Accounting major 7340301 not found.\n");
    exit(1);
}
$majorId = (int)$major['id'];

$conn->query("UPDATE majors SET major_name='Kế toán' WHERE id=$majorId");

$handle = fopen($csvPath, 'r');
if (!$handle) {
    fwrite(STDERR, "Cannot open CSV.\n");
    exit(1);
}

$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF") rewind($handle);
fgetcsv($handle, 0, ',');

$stmtSubject = $conn->prepare(
    "UPDATE subjects
     SET subject_name=?, credits=?, theory_periods=?, practice_periods=?,
         total_periods=?, subject_type=?, subject_type_new=?, is_mandatory=?,
         semester_order=?, major_id=?
     WHERE subject_code=?"
);
$stmtCurrLookup = $conn->prepare("SELECT id FROM subjects WHERE subject_code=? LIMIT 1");
$stmtCurr = $conn->prepare(
    "UPDATE curriculum
     SET credits=?, suggested_semester=?, semester_label=?, year_label=?, subject_type=?
     WHERE major_id=? AND subject_id=? AND deleted_at IS NULL"
);

$updated = 0;
$missing = [];

while (($row = fgetcsv($handle, 0, ',')) !== false) {
    if (count($row) < 12) continue;

    $code = trim($row[1]);
    $name = trim(preg_replace('/\s*\(\d+\+\d+\)\s*$/u', '', $row[2]));
    $credits = (int)$row[4];
    $mandatory = trim($row[5]) === 'Có' ? 1 : 0;
    $totalPeriods = (int)$row[7];
    $theory = (int)$row[8];
    $practice = (int)$row[9];
    $semesterLabel = trim($row[10]);
    $yearLabel = trim($row[11]);
    $yearLabel = match ($yearLabel) {
        '2022-203' => '2022-2023',
        '2023-20242' => '2023-2024',
        default => $yearLabel,
    };

    $semNum = (int)preg_replace('/\D/', '', $semesterLabel);
    $yearStart = (int)substr($yearLabel, 0, 4);
    $semesterOrder = (($yearStart - 2022) * 3) + max(1, $semNum);
    $typeNew = str_starts_with($code, 'KTCH') ? 'general' : ($mandatory ? 'required' : 'elective');
    $typeVi = $mandatory ? 'Bắt buộc' : 'Tự chọn';

    $stmtSubject->bind_param(
        'siiiissiiis',
        $name,
        $credits,
        $theory,
        $practice,
        $totalPeriods,
        $typeVi,
        $typeNew,
        $mandatory,
        $semesterOrder,
        $majorId,
        $code
    );
    $stmtSubject->execute();
    if ($stmtSubject->affected_rows >= 0) $updated++;

    $stmtCurrLookup->bind_param('s', $code);
    $stmtCurrLookup->execute();
    $subject = $stmtCurrLookup->get_result()->fetch_assoc();
    if (!$subject) {
        $missing[] = $code;
        continue;
    }
    $subjectId = (int)$subject['id'];

    $stmtCurr->bind_param(
        'iisssii',
        $credits,
        $semesterOrder,
        $semesterLabel,
        $yearLabel,
        $typeNew,
        $majorId,
        $subjectId
    );
    $stmtCurr->execute();
}
fclose($handle);

$stmtSubject->close();
$stmtCurrLookup->close();
$stmtCurr->close();
$conn->close();

echo "Updated $updated Accounting subjects from UTF-8 CSV.\n";
if ($missing) {
    echo "Missing subject codes: " . implode(', ', $missing) . "\n";
}
