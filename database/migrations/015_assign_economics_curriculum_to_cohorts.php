<?php
/**
 * Gan chuong trinh dao tao cho tung khoa cua Khoa Kinh te.
 *
 * Chay:
 *   php database/migrations/015_assign_economics_curriculum_to_cohorts.php
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

function columnExists(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS c
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    return (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0) > 0;
}

function tableColumns(mysqli $conn, string $table): array
{
    $columns = [];
    $stmt = $conn->prepare(
        "SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
         ORDER BY ORDINAL_POSITION"
    );
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $rs = $stmt->get_result();
    while ($row = $rs->fetch_assoc()) {
        $columns[] = $row['COLUMN_NAME'];
    }
    return $columns;
}

function indexExists(mysqli $conn, string $table, string $index): bool
{
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS c
         FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?"
    );
    $stmt->bind_param('ss', $table, $index);
    $stmt->execute();
    return (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0) > 0;
}

function parseEnrollmentYear(?string $schoolYear, string $classCode): ?int
{
    if ($schoolYear && preg_match('/(20\d{2})/', $schoolYear, $m)) {
        return (int)$m[1];
    }
    if (preg_match('/D(\d{2})/i', $classCode, $m)) {
        return 2000 + (int)$m[1];
    }
    return null;
}

function ensureColumn(mysqli $conn, string $table, string $column, string $definition): void
{
    if (!columnExists($conn, $table, $column)) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

ensureColumn($conn, 'training_programs', 'version_code', "VARCHAR(50) NULL AFTER `program_name`");
ensureColumn($conn, 'training_programs', 'effective_year', "INT NULL AFTER `version_code`");
ensureColumn($conn, 'training_programs', 'duration_years', "DECIMAL(3,1) NOT NULL DEFAULT 4.0 AFTER `total_credits`");
ensureColumn($conn, 'training_programs', 'status', "ENUM('draft','active','archived') NOT NULL DEFAULT 'active' AFTER `duration_years`");
ensureColumn($conn, 'classes', 'enrollment_year', "INT NULL AFTER `school_year`");
ensureColumn($conn, 'classes', 'cohort_id', "INT NULL AFTER `enrollment_year`");
ensureColumn($conn, 'students', 'enrollment_year', "INT NULL AFTER `class_id`");
ensureColumn($conn, 'students', 'cohort_id', "INT NULL AFTER `enrollment_year`");
ensureColumn($conn, 'students', 'training_program_id', "INT NULL AFTER `cohort_id`");
ensureColumn($conn, 'students', 'expected_grad_year', "INT NULL AFTER `training_program_id`");
ensureColumn($conn, 'curriculum', 'program_id', "INT NULL AFTER `major_id`");
ensureColumn($conn, 'curriculum', 'allow_off_semester', "TINYINT(1) NOT NULL DEFAULT 0 AFTER `prerequisite_ids`");

if (indexExists($conn, 'curriculum', 'uq_curriculum_major_subject')) {
    $conn->query("ALTER TABLE `curriculum` DROP INDEX `uq_curriculum_major_subject`");
}
if (!indexExists($conn, 'curriculum', 'idx_curriculum_major_program_subject')) {
    $conn->query("CREATE INDEX `idx_curriculum_major_program_subject` ON `curriculum` (`major_id`, `program_id`, `subject_id`)");
}

$conn->query(
    "CREATE TABLE IF NOT EXISTS `training_cohorts` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `major_id` INT NOT NULL,
        `enrollment_year` INT NOT NULL,
        `program_id` INT NOT NULL,
        `cohort_code` VARCHAR(50) NOT NULL,
        `cohort_name` VARCHAR(150) NOT NULL,
        `duration_years` DECIMAL(3,1) NOT NULL DEFAULT 4.0,
        `start_date` DATE NULL,
        `expected_end_date` DATE NULL,
        `status` ENUM('planned','active','completed','closed') NOT NULL DEFAULT 'active',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_training_cohort_major_year` (`major_id`, `enrollment_year`),
        KEY `idx_training_cohort_program` (`program_id`),
        KEY `idx_training_cohort_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$stats = [
    'programs_created' => 0,
    'programs_updated' => 0,
    'cohorts_created' => 0,
    'cohorts_updated' => 0,
    'classes_updated' => 0,
    'students_updated' => 0,
    'curriculum_copied' => 0,
    'curriculum_seeded_from_subjects' => 0,
    'majors_seeded_from_subjects' => [],
];

$conn->begin_transaction();

try {
    $stmtMajors = $conn->prepare(
        "SELECT m.id, m.major_code, m.major_name, COALESCE(m.total_credits, 120) AS total_credits
         FROM majors m
         JOIN faculties f ON f.id = m.faculty_id
         WHERE f.faculty_code = 'KT' OR f.faculty_name LIKE '%Kinh%'
         ORDER BY m.id"
    );
    $stmtMajors->execute();
    $majors = $stmtMajors->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmtClasses = $conn->prepare(
        "SELECT id, major_id, class_code, school_year, enrollment_year
         FROM classes
         WHERE major_id = ?
         ORDER BY id"
    );
    $stmtClassYear = $conn->prepare("UPDATE classes SET enrollment_year = ? WHERE id = ? AND (enrollment_year IS NULL OR enrollment_year = 0)");

    $programFind = $conn->prepare(
        "SELECT id FROM training_programs
         WHERE major_id = ? AND effective_year = ?
         ORDER BY id DESC
         LIMIT 1"
    );
    $programInsert = $conn->prepare(
        "INSERT INTO training_programs
            (major_id, program_name, version_code, effective_year, school_year, total_credits, duration_years, status, note)
         VALUES (?, ?, ?, ?, ?, ?, 4.0, 'active', ?)"
    );
    $programUpdate = $conn->prepare(
        "UPDATE training_programs
         SET program_name = ?, version_code = ?, school_year = ?, total_credits = ?, duration_years = 4.0,
             status = 'active', note = ?
         WHERE id = ?"
    );

    $cohortFind = $conn->prepare(
        "SELECT id FROM training_cohorts
         WHERE major_id = ? AND enrollment_year = ?
         LIMIT 1"
    );
    $cohortInsert = $conn->prepare(
        "INSERT INTO training_cohorts
            (major_id, enrollment_year, program_id, cohort_code, cohort_name, duration_years, start_date, expected_end_date, status)
         VALUES (?, ?, ?, ?, ?, 4.0, ?, ?, 'active')"
    );
    $cohortUpdate = $conn->prepare(
        "UPDATE training_cohorts
         SET program_id = ?, cohort_code = ?, cohort_name = ?, duration_years = 4.0,
             start_date = ?, expected_end_date = ?, status = 'active'
         WHERE id = ?"
    );
    $classCohortUpdate = $conn->prepare(
        "UPDATE classes
         SET cohort_id = ?
         WHERE major_id = ? AND enrollment_year = ? AND (cohort_id IS NULL OR cohort_id <> ?)"
    );
    $studentUpdate = $conn->prepare(
        "UPDATE students s
         JOIN classes cl ON cl.id = s.class_id
         SET s.enrollment_year = COALESCE(s.enrollment_year, cl.enrollment_year),
             s.cohort_id = ?,
             s.training_program_id = ?,
             s.expected_grad_year = COALESCE(s.expected_grad_year, ?)
         WHERE cl.major_id = ? AND cl.enrollment_year = ?"
    );

    foreach ($majors as $major) {
        $majorId = (int)$major['id'];
        $stmtClasses->bind_param('i', $majorId);
        $stmtClasses->execute();
        $classes = $stmtClasses->get_result()->fetch_all(MYSQLI_ASSOC);

        $years = [];
        foreach ($classes as $class) {
            $year = (int)($class['enrollment_year'] ?? 0);
            if ($year <= 0) {
                $year = parseEnrollmentYear($class['school_year'] ?? null, (string)$class['class_code']) ?? 0;
                if ($year > 0) {
                    $classId = (int)$class['id'];
                    $stmtClassYear->bind_param('ii', $year, $classId);
                    $stmtClassYear->execute();
                    $stats['classes_updated'] += $stmtClassYear->affected_rows;
                }
            }
            if ($year > 0) {
                $years[$year] = true;
            }
        }

        $stmtStudentYears = $conn->prepare(
            "SELECT DISTINCT s.enrollment_year
             FROM students s
             JOIN classes cl ON cl.id = s.class_id
             WHERE cl.major_id = ? AND s.enrollment_year IS NOT NULL AND s.enrollment_year > 0"
        );
        $stmtStudentYears->bind_param('i', $majorId);
        $stmtStudentYears->execute();
        $rsYears = $stmtStudentYears->get_result();
        while ($row = $rsYears->fetch_assoc()) {
            $years[(int)$row['enrollment_year']] = true;
        }

        ksort($years);

        $stmtCurrCount = $conn->prepare(
            "SELECT COUNT(*) AS c, COALESCE(SUM(credits), 0) AS credits
             FROM curriculum
             WHERE major_id = ? AND deleted_at IS NULL AND program_id IS NULL"
        );
        $stmtCurrCount->bind_param('i', $majorId);
        $stmtCurrCount->execute();
        $currInfo = $stmtCurrCount->get_result()->fetch_assoc();
        $currCount = (int)($currInfo['c'] ?? 0);
        if ($currCount <= 0) {
            $seeded = seedBaseCurriculumFromSubjects($conn, $majorId);
            if ($seeded > 0) {
                $stats['curriculum_seeded_from_subjects'] += $seeded;
                $stats['majors_seeded_from_subjects'][] = $major['major_name'];
                $stmtCurrCount->execute();
                $currInfo = $stmtCurrCount->get_result()->fetch_assoc();
                $currCount = (int)($currInfo['c'] ?? 0);
            }
        }
        $totalCredits = (int)($currInfo['credits'] ?? 0);
        if ($totalCredits <= 0) {
            $totalCredits = (int)$major['total_credits'];
        }

        foreach (array_keys($years) as $year) {
            $programName = 'CTĐT ' . $major['major_name'] . ' khóa ' . $year;
            $versionCode = 'CTDT-' . $major['major_code'] . '-' . $year;
            $schoolYear = $year . '-' . ($year + 1);
            $note = 'Gán tự động theo khóa nhập học ' . $year . ' của Khoa Kinh tế';

            $programFind->bind_param('ii', $majorId, $year);
            $programFind->execute();
            $programRow = $programFind->get_result()->fetch_assoc();

            if ($programRow) {
                $programId = (int)$programRow['id'];
                $programUpdate->bind_param('sssisi', $programName, $versionCode, $schoolYear, $totalCredits, $note, $programId);
                $programUpdate->execute();
                $stats['programs_updated'] += $programUpdate->affected_rows > 0 ? 1 : 0;
            } else {
                $programInsert->bind_param('issisis', $majorId, $programName, $versionCode, $year, $schoolYear, $totalCredits, $note);
                $programInsert->execute();
                $programId = $conn->insert_id;
                $stats['programs_created']++;
            }

            $cohortCode = $major['major_code'] . '-K' . substr((string)$year, -2);
            $cohortName = $major['major_name'] . ' khóa ' . $year . '-' . ($year + 4);
            $startDate = $year . '-08-15';
            $endDate = ($year + 4) . '-08-14';

            $cohortFind->bind_param('ii', $majorId, $year);
            $cohortFind->execute();
            $cohortRow = $cohortFind->get_result()->fetch_assoc();

            if ($cohortRow) {
                $cohortId = (int)$cohortRow['id'];
                $cohortUpdate->bind_param('issssi', $programId, $cohortCode, $cohortName, $startDate, $endDate, $cohortId);
                $cohortUpdate->execute();
                $stats['cohorts_updated'] += $cohortUpdate->affected_rows > 0 ? 1 : 0;
            } else {
                $cohortInsert->bind_param('iiissss', $majorId, $year, $programId, $cohortCode, $cohortName, $startDate, $endDate);
                $cohortInsert->execute();
                $cohortId = $conn->insert_id;
                $stats['cohorts_created']++;
            }

            $classCohortUpdate->bind_param('iiii', $cohortId, $majorId, $year, $cohortId);
            $classCohortUpdate->execute();
            $stats['classes_updated'] += $classCohortUpdate->affected_rows;

            $expectedGradYear = $year + 4;
            $studentUpdate->bind_param('iiiii', $cohortId, $programId, $expectedGradYear, $majorId, $year);
            $studentUpdate->execute();
            $stats['students_updated'] += $studentUpdate->affected_rows;

            if ($currCount > 0) {
                $inserted = copyCurriculumForProgram($conn, $majorId, $programId);
                $stats['curriculum_copied'] += $inserted;
            }
        }
    }

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    throw $e;
}

function copyCurriculumForProgram(mysqli $conn, int $majorId, int $programId): int
{
    $columns = tableColumns($conn, 'curriculum');
    $copyColumns = array_values(array_filter($columns, static function (string $column): bool {
        return !in_array($column, ['id', 'created_at', 'updated_at'], true);
    }));

    if (!in_array('program_id', $copyColumns, true)) {
        return 0;
    }

    $stmtBase = $conn->prepare(
        "SELECT *
         FROM curriculum
         WHERE major_id = ? AND program_id IS NULL AND deleted_at IS NULL
         ORDER BY suggested_semester, id"
    );
    $stmtBase->bind_param('i', $majorId);
    $stmtBase->execute();
    $rows = $stmtBase->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmtExists = $conn->prepare(
        "SELECT id
         FROM curriculum
         WHERE major_id = ? AND program_id = ? AND subject_id = ? AND deleted_at IS NULL
         LIMIT 1"
    );

    $inserted = 0;
    foreach ($rows as $row) {
        $subjectId = (int)$row['subject_id'];
        $stmtExists->bind_param('iii', $majorId, $programId, $subjectId);
        $stmtExists->execute();
        if ($stmtExists->get_result()->fetch_assoc()) {
            continue;
        }

        $names = '`' . implode('`, `', $copyColumns) . '`';
        $placeholders = implode(', ', array_fill(0, count($copyColumns), '?'));
        $sql = "INSERT INTO curriculum ($names) VALUES ($placeholders)";
        $stmtInsert = $conn->prepare($sql);

        $values = [];
        $types = '';
        foreach ($copyColumns as $column) {
            $value = $row[$column] ?? null;
            if ($column === 'program_id') {
                $value = $programId;
            }
            if (in_array($column, ['major_id', 'program_id', 'subject_id', 'credits', 'suggested_semester', 'deleted_by', 'allow_off_semester'], true)) {
                $types .= 'i';
                $values[] = $value === null ? null : (int)$value;
            } else {
                $types .= 's';
                $values[] = $value === null ? null : (string)$value;
            }
        }

        $stmtInsert->bind_param($types, ...$values);
        $stmtInsert->execute();
        $inserted += $stmtInsert->affected_rows;
    }

    return $inserted;
}

function seedBaseCurriculumFromSubjects(mysqli $conn, int $majorId): int
{
    $stmt = $conn->prepare(
        "INSERT INTO curriculum (major_id, subject_id, credits, suggested_semester, subject_type)
         SELECT s.major_id,
                s.id,
                COALESCE(s.credits, 3),
                COALESCE(NULLIF(s.semester_order, 0), 1),
                CASE
                    WHEN s.subject_type_new IN ('required','elective','general') THEN s.subject_type_new
                    WHEN COALESCE(s.is_mandatory, 1) = 0 THEN 'elective'
                    ELSE 'required'
                END
         FROM subjects s
         WHERE s.major_id = ?
           AND NOT EXISTS (
                SELECT 1
                FROM curriculum c
                WHERE c.major_id = s.major_id
                  AND c.subject_id = s.id
                  AND c.program_id IS NULL
                  AND c.deleted_at IS NULL
           )"
    );
    $stmt->bind_param('i', $majorId);
    $stmt->execute();
    return max(0, $stmt->affected_rows);
}

echo "Hoan tat gan CTDT Khoa Kinh te theo khoa.\n";
foreach ($stats as $key => $value) {
    if (is_array($value)) {
        $value = empty($value) ? 'khong co' : implode(', ', array_unique($value));
    }
    echo "- {$key}: {$value}\n";
}
