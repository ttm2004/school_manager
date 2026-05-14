<?php
/**
 * Them khoa 2025 va 2026 cho cac nganh Khoa Kinh te.
 *
 * Chay:
 *   php database/seeds/010_economics_cohorts_2025_2026.php
 */

require_once __DIR__ . '/../../config/app.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli(
    (string)config('db.host', 'localhost'),
    (string)config('db.user', 'root'),
    (string)config('db.pass', ''),
    (string)config('db.name', 'edu_management'),
    (int)config('db.port', 3306)
);
$conn->set_charset('utf8mb4');
$conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

$stats = [
    'programs' => 0,
    'cohorts' => 0,
    'curriculum_rows' => 0,
    'classes' => 0,
    'users' => 0,
    'students' => 0,
];

function fetchOne(mysqli $conn, string $sql, string $types = '', mixed ...$params): ?array
{
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function scalar(mysqli $conn, string $sql, string $types = '', mixed ...$params): mixed
{
    $row = fetchOne($conn, $sql, $types, ...$params);
    return $row ? reset($row) : null;
}

function execSql(mysqli $conn, string $sql, string $types = '', mixed ...$params): int
{
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return max(0, $stmt->affected_rows);
}

function ensureProgram(mysqli $conn, array $major, int $year, array &$stats): int
{
    $existing = scalar(
        $conn,
        "SELECT id FROM training_programs WHERE major_id = ? AND effective_year = ? LIMIT 1",
        'ii',
        (int)$major['id'],
        $year
    );
    if ($existing) {
        return (int)$existing;
    }

    $baseCredits = (int)(scalar(
        $conn,
        "SELECT COALESCE(SUM(credits), 0) FROM curriculum WHERE major_id = ? AND program_id IS NULL AND deleted_at IS NULL",
        'i',
        (int)$major['id']
    ) ?: $major['total_credits']);
    if ($baseCredits <= 0) {
        $baseCredits = (int)$major['total_credits'];
    }

    execSql(
        $conn,
        "INSERT INTO training_programs
            (major_id, program_name, version_code, effective_year, school_year, total_credits, duration_years, status, note)
         VALUES (?, ?, ?, ?, ?, ?, 4.0, 'active', ?)",
        'issisis',
        (int)$major['id'],
        'CTĐT ' . $major['major_name'] . ' khóa ' . $year,
        'CTDT-' . $major['major_code'] . '-' . $year,
        $year,
        $year . '-' . ($year + 1),
        $baseCredits,
        'Tạo dữ liệu test khóa ' . $year . ' cho Khoa Kinh tế'
    );
    $stats['programs']++;
    return (int)$conn->insert_id;
}

function ensureCohort(mysqli $conn, array $major, int $year, int $programId, array &$stats): int
{
    $existing = scalar(
        $conn,
        "SELECT id FROM training_cohorts WHERE major_id = ? AND enrollment_year = ? LIMIT 1",
        'ii',
        (int)$major['id'],
        $year
    );
    if ($existing) {
        execSql($conn, "UPDATE training_cohorts SET program_id = ? WHERE id = ?", 'ii', $programId, (int)$existing);
        return (int)$existing;
    }

    execSql(
        $conn,
        "INSERT INTO training_cohorts
            (major_id, enrollment_year, program_id, cohort_code, cohort_name, duration_years, start_date, expected_end_date, status)
         VALUES (?, ?, ?, ?, ?, 4.0, ?, ?, 'active')",
        'iiissss',
        (int)$major['id'],
        $year,
        $programId,
        $major['major_code'] . '-K' . substr((string)$year, -2),
        $major['major_name'] . ' khóa ' . $year . '-' . ($year + 4),
        $year . '-08-15',
        ($year + 4) . '-08-14'
    );
    $stats['cohorts']++;
    return (int)$conn->insert_id;
}

function copyCurriculum(mysqli $conn, int $majorId, int $programId, array &$stats): void
{
    $rs = $conn->prepare(
        "SELECT *
         FROM curriculum
         WHERE major_id = ? AND program_id IS NULL AND deleted_at IS NULL
         ORDER BY suggested_semester, id"
    );
    $rs->bind_param('i', $majorId);
    $rs->execute();
    $rows = $rs->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as $row) {
        $exists = scalar(
            $conn,
            "SELECT id FROM curriculum WHERE major_id = ? AND program_id = ? AND subject_id = ? AND deleted_at IS NULL LIMIT 1",
            'iii',
            $majorId,
            $programId,
            (int)$row['subject_id']
        );
        if ($exists) {
            continue;
        }
        execSql(
            $conn,
            "INSERT INTO curriculum
                (major_id, program_id, subject_id, credits, suggested_semester, semester_label, year_label, subject_type, prerequisite_ids, allow_off_semester)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            'iiiiissssi',
            $majorId,
            $programId,
            (int)$row['subject_id'],
            (int)$row['credits'],
            (int)$row['suggested_semester'],
            (string)($row['semester_label'] ?? ''),
            (string)($row['year_label'] ?? ''),
            (string)($row['subject_type'] ?? 'required'),
            (string)($row['prerequisite_ids'] ?? ''),
            (int)($row['allow_off_semester'] ?? 0)
        );
        $stats['curriculum_rows']++;
    }
}

function ensureClass(mysqli $conn, array $major, int $year, int $cohortId, int $index, array &$stats): int
{
    $prefix = $major['major_code'] === '7340301' ? 'KT' : 'QTKD';
    $code = 'D' . substr((string)$year, -2) . $prefix . sprintf('%02d', $index);
    $existing = scalar($conn, "SELECT id FROM classes WHERE class_code = ? LIMIT 1", 's', $code);
    if ($existing) {
        execSql($conn, "UPDATE classes SET enrollment_year = ?, cohort_id = ? WHERE id = ?", 'iii', $year, $cohortId, (int)$existing);
        return (int)$existing;
    }

    execSql(
        $conn,
        "INSERT INTO classes (major_id, class_code, class_name, school_year, enrollment_year, cohort_id)
         VALUES (?, ?, ?, ?, ?, ?)",
        'isssii',
        (int)$major['id'],
        $code,
        $major['major_name'] . ' K' . substr((string)$year, -2) . ' - Lớp ' . $index,
        $year . '-' . ($year + 4),
        $year,
        $cohortId
    );
    $stats['classes']++;
    return (int)$conn->insert_id;
}

function ensureStudents(mysqli $conn, array $major, int $year, int $programId, int $cohortId, int $classId, int $count, array &$stats): void
{
    $prefix = $major['major_code'] === '7340301' ? 'KT' : 'QTKD';
    for ($i = 1; $i <= $count; $i++) {
        $studentCode = $year . $major['major_code'] . sprintf('%03d', (($classId % 100) * 10) + $i);
        if (scalar($conn, "SELECT id FROM students WHERE student_code = ? LIMIT 1", 's', $studentCode)) {
            continue;
        }

        $email = strtolower($studentCode) . '@student.tdmu.edu.vn';
        $userId = (int)(scalar($conn, "SELECT id FROM users WHERE email = ? LIMIT 1", 's', $email) ?: 0);
        if ($userId <= 0) {
            execSql(
                $conn,
                "INSERT INTO users (username, password, full_name, email, role, status)
                 VALUES (?, ?, ?, ?, 'student', 1)",
                'ssss',
                $studentCode,
                password_hash($studentCode, PASSWORD_DEFAULT),
                'Sinh viên ' . $prefix . ' K' . substr((string)$year, -2) . ' ' . sprintf('%03d', $i),
                $email
            );
            $userId = (int)$conn->insert_id;
            $stats['users']++;
        }

        execSql(
            $conn,
            "INSERT INTO students
                (user_id, class_id, student_code, gender, birthday, address, academic_status,
                 enrollment_year, cohort_id, training_program_id, expected_grad_year)
             VALUES (?, ?, ?, ?, ?, 'Bình Dương', 'Đang học', ?, ?, ?, ?)",
            'iisssiiii',
            $userId,
            $classId,
            $studentCode,
            $i % 2 === 0 ? 'Nữ' : 'Nam',
            ($year - 18) . '-09-' . sprintf('%02d', ($i % 25) + 1),
            $year,
            $cohortId,
            $programId,
            $year + 4
        );
        $stats['students']++;
    }
}

$conn->begin_transaction();

try {
    $rs = $conn->query(
        "SELECT m.*
         FROM majors m
         JOIN faculties f ON f.id = m.faculty_id
         WHERE f.faculty_code = 'KT'
         ORDER BY m.major_code"
    );
    $majors = $rs->fetch_all(MYSQLI_ASSOC);
    foreach ($majors as $major) {
        foreach ([2025, 2026] as $year) {
            $programId = ensureProgram($conn, $major, $year, $stats);
            copyCurriculum($conn, (int)$major['id'], $programId, $stats);
            $cohortId = ensureCohort($conn, $major, $year, $programId, $stats);
            for ($classIndex = 1; $classIndex <= 2; $classIndex++) {
                $classId = ensureClass($conn, $major, $year, $cohortId, $classIndex, $stats);
                ensureStudents($conn, $major, $year, $programId, $cohortId, $classId, 30, $stats);
            }
        }
    }

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    throw $e;
}

echo "Hoàn tất thêm khóa 2025 và 2026 cho Khoa Kinh tế.\n";
foreach ($stats as $key => $value) {
    echo "- {$key}: {$value}\n";
}
