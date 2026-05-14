<?php
require_once __DIR__ . '/../../config/database.php';

$rows = [];
$sql = "
    SELECT s.id AS student_id,
           s.user_id,
           s.student_code AS old_code,
           COALESCE(s.enrollment_year, tc.enrollment_year, c.enrollment_year) AS enrollment_year
    FROM students s
    LEFT JOIN classes c ON c.id = s.class_id
    LEFT JOIN training_cohorts tc ON tc.id = COALESCE(s.cohort_id, c.cohort_id)
    WHERE COALESCE(s.enrollment_year, tc.enrollment_year, c.enrollment_year) IS NOT NULL
";
$rs = $conn->query($sql);
while ($row = $rs->fetch_assoc()) {
    $year = (int)$row['enrollment_year'];
    $oldCode = (string)$row['old_code'];
    if ($year < 2000 || strlen($oldCode) < 5) {
        continue;
    }

    $row['new_code'] = $year . substr($oldCode, 4);
    $rows[] = $row;
}

$seen = [];
foreach ($rows as $row) {
    $code = $row['new_code'];
    if (isset($seen[$code])) {
        throw new RuntimeException("Duplicate normalized student_code: {$code}");
    }
    $seen[$code] = true;
}

$conn->begin_transaction();
try {
    $tmpStudent = $conn->prepare("UPDATE students SET student_code = ? WHERE id = ?");
    $tmpUser = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
    foreach ($rows as $row) {
        $sid = (int)$row['student_id'];
        $uid = (int)$row['user_id'];
        $tmpCode = 'TMP' . $sid . '_' . substr((string)$row['old_code'], 0, 20);
        $tmpUsername = 'tmp_student_' . $uid;
        $tmpStudent->bind_param('si', $tmpCode, $sid);
        $tmpStudent->execute();
        $tmpUser->bind_param('si', $tmpUsername, $uid);
        $tmpUser->execute();
    }
    $tmpStudent->close();
    $tmpUser->close();

    $updStudent = $conn->prepare("UPDATE students SET student_code = ?, enrollment_year = ? WHERE id = ?");
    $updUser = $conn->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
    $updEnrollment = null;
    if ($conn->query("SHOW TABLES LIKE 'adm_enrollments'")->num_rows > 0) {
        $updEnrollment = $conn->prepare("UPDATE adm_enrollments SET student_code = ? WHERE student_code = ?");
    }

    $changed = 0;
    foreach ($rows as $row) {
        $sid = (int)$row['student_id'];
        $uid = (int)$row['user_id'];
        $year = (int)$row['enrollment_year'];
        $oldCode = (string)$row['old_code'];
        $newCode = (string)$row['new_code'];
        $hash = password_hash($newCode, PASSWORD_DEFAULT);
        $username = strtolower($newCode);

        $updStudent->bind_param('sii', $newCode, $year, $sid);
        $updStudent->execute();
        $updUser->bind_param('ssi', $username, $hash, $uid);
        $updUser->execute();
        if ($updEnrollment && $oldCode !== $newCode) {
            $updEnrollment->bind_param('ss', $newCode, $oldCode);
            $updEnrollment->execute();
        }
        if ($oldCode !== $newCode) {
            $changed++;
        }
    }

    $updStudent->close();
    $updUser->close();
    if ($updEnrollment) {
        $updEnrollment->close();
    }

    $conn->commit();
    echo "Normalized " . count($rows) . " student accounts; changed {$changed} student codes.\n";
} catch (Throwable $e) {
    $conn->rollback();
    throw $e;
}
