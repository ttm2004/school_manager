<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/AcademicPolicy.php';
require_once '../includes/teacher_assignment_rules.php';
require_once 'includes/faculty_helpers.php';
requireAnyRole(['faculty_manager', 'faculty_staff', 'dept_head']);

$pageTitle = 'Đề xuất Mở lớp & Phân công GV';
$userId    = (int)$_SESSION['user_id'];
$facultyId = getFacultyId($conn, $userId);
$ip        = $_SERVER['REMOTE_ADDR'] ?? '';

if ($facultyId <= 0 && ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Tài khoản chưa được gán vào khoa nào.'];
    header('Location: /university/login.php');
    exit();
}

function facultyProposalWindowOrRedirect(mysqli $conn, int $semesterId, string $redirect = 'proposals.php'): void
{
    $window = academicPolicyCheckFacultyProposalWindow($conn, $semesterId);
    if (!$window['ok']) {
        $_SESSION['_flash'] = ['type' => 'warning', 'message' => $window['message']];
        header('Location: ' . $redirect);
        exit();
    }
}

function facultyProposalSectionSemester(mysqli $conn, int $sectionId): int
{
    $stmt = $conn->prepare("SELECT semester_id FROM course_sections WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $sectionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int)($row['semester_id'] ?? 0);
}

function facultyProposalEnsureNullableTeacherId(mysqli $conn): void
{
    $stmt = $conn->prepare(
        "SELECT IS_NULLABLE
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'course_sections'
           AND COLUMN_NAME = 'teacher_id'
         LIMIT 1"
    );
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (($row['IS_NULLABLE'] ?? 'YES') === 'NO') {
        if (!$conn->query("ALTER TABLE course_sections MODIFY teacher_id INT(11) NULL DEFAULT NULL")) {
            throw new Exception('Không cập nhật được cấu trúc teacher_id để cho phép lớp đề xuất chưa có giảng viên chính thức: ' . $conn->error);
        }
    }
}

function facultyProposalStudentEstimate(mysqli $conn, ?int $cohortId, int $majorId, int $enrollmentYear): int
{
    if ($cohortId) {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS c
             FROM students s
             LEFT JOIN classes cl ON s.class_id = cl.id
             WHERE COALESCE(s.cohort_id, cl.cohort_id) = ?"
        );
        $stmt->bind_param('i', $cohortId);
    } else {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS c
             FROM students s
             JOIN classes cl ON s.class_id = cl.id
             WHERE cl.major_id = ? AND COALESCE(s.enrollment_year, cl.enrollment_year) = ?"
        );
        $stmt->bind_param('ii', $majorId, $enrollmentYear);
    }
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();

    return max($count, 30);
}

function facultyProposalNextSectionCode(mysqli $conn, string $subjectCode, int $semesterId): string
{
    $prefix = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $subjectCode));
    $prefix = $prefix !== '' ? $prefix : 'LHP';
    $stmt = $conn->prepare(
        "SELECT section_code FROM course_sections
         WHERE semester_id = ? AND section_code LIKE ?
         ORDER BY section_code DESC LIMIT 1"
    );
    $like = $prefix . '.%';
    $stmt->bind_param('is', $semesterId, $like);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $next = 1;
    if (!empty($row['section_code']) && preg_match('/\.(\d+)$/', $row['section_code'], $matches)) {
        $next = (int)$matches[1] + 1;
    }

    return $prefix . '.' . str_pad((string)$next, 2, '0', STR_PAD_LEFT);
}

function facultyProposalRoomScheduleConflict(mysqli $conn, string $roomCode, int $semesterId, ?string $daySessions): bool
{
    if ($roomCode === '') {
        return false;
    }

    $tokens = academicPolicyScheduleTokens($daySessions);
    if (empty($tokens)) {
        return false;
    }

    $stmt = $conn->prepare(
        "SELECT day_sessions
         FROM course_sections
         WHERE semester_id = ?
           AND room = ?
           AND status IN ('draft','proposed','open','full','closed')
           AND day_sessions IS NOT NULL
           AND day_sessions <> ''"
    );
    $stmt->bind_param('is', $semesterId, $roomCode);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $row) {
        if (array_intersect($tokens, academicPolicyScheduleTokens($row['day_sessions'] ?? ''))) {
            return true;
        }
    }

    return false;
}

function facultyProposalFindAvailableClassrooms(mysqli $conn, int $semesterId, int $subjectId, int $maxStudents, string $daySessions): array
{
    return academicPolicyFindAvailableClassrooms($conn, 0, $semesterId, $subjectId, $maxStudents, 'offline', $daySessions);
}

function facultyProposalSubjectLoad(mysqli $conn, int $subjectId, int $fallbackCredits): array
{
    $selects = ['credits'];
    foreach (['theory_periods', 'practice_periods', 'total_periods'] as $column) {
        if (academicPolicyColumnExists($conn, 'subjects', $column)) {
            $selects[] = $column;
        }
    }

    $stmt = $conn->prepare('SELECT ' . implode(', ', $selects) . ' FROM subjects WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $subjectId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $credits = max(1, (int)($row['credits'] ?? $fallbackCredits));
    $theory = (int)($row['theory_periods'] ?? 0);
    $practice = (int)($row['practice_periods'] ?? 0);
    $total = (int)($row['total_periods'] ?? 0);
    if ($total <= 0) {
        $total = $theory + $practice;
    }
    if ($total <= 0) {
        $total = $credits * 15;
    }

    return [
        'credits' => $credits,
        'theory_periods' => $theory,
        'practice_periods' => $practice,
        'total_periods' => $total,
    ];
}

function facultyProposalSemesterWeeks(array $semester): int
{
    $window = null;
    if (!empty($semester['start_date']) && !empty($semester['end_date'])) {
        $window = ['start_date' => $semester['start_date'], 'end_date' => $semester['end_date']];
    } else {
        $window = academicPolicySemesterWindowFromRow($semester);
    }

    if (!$window || empty($window['start_date']) || empty($window['end_date'])) {
        return academicPolicyIsTestSemester($semester) ? 4 : 15;
    }

    $start = strtotime((string)$window['start_date']);
    $end = strtotime((string)$window['end_date']);
    if (!$start || !$end || $end <= $start) {
        return academicPolicyIsTestSemester($semester) ? 4 : 15;
    }

    return max(1, (int)ceil(($end - $start + 86400) / (7 * 86400)));
}

function facultyProposalSessionsPerWeek(array $semester, array $subjectLoad): int
{
    if (!academicPolicyIsTestSemester($semester)) {
        return 1;
    }

    $periodsPerSession = 3;
    $weeks = facultyProposalSemesterWeeks($semester);
    $totalPeriods = max(1, (int)($subjectLoad['total_periods'] ?? 0));

    return max(1, min(6, (int)ceil($totalPeriods / ($weeks * $periodsPerSession))));
}

function facultyProposalScheduleCandidates(array $semester, array $subjectLoad): array
{
    $sessionsPerWeek = facultyProposalSessionsPerWeek($semester, $subjectLoad);
    $slots = [];
    $dayLabels = [
        2 => 'Thứ 2',
        3 => 'Thứ 3',
        4 => 'Thứ 4',
        5 => 'Thứ 5',
        6 => 'Thứ 6',
        7 => 'Thứ 7',
        8 => 'Chủ nhật',
    ];
    $sessionLabels = [
        'sang' => 'sáng',
        'chieu' => 'chiều',
        'toi' => 'tối',
    ];
    foreach ($dayLabels as $day => $dayLabel) {
        foreach ($sessionLabels as $session => $sessionLabel) {
            $slots[] = [
                'token' => $day . ':' . $session,
                'label' => $dayLabel . ' ' . $sessionLabel,
                'day' => $day,
                'session' => $session,
            ];
        }
    }

    $candidates = [];
    $buildCandidate = static function (array $selectedSlots) use (&$candidates, $sessionsPerWeek, $semester, $subjectLoad): void {
        $tokens = array_map(static fn(array $slot): string => $slot['token'], $selectedSlots);
        $labels = array_map(static fn(array $slot): string => $slot['label'], $selectedSlots);
        $value = implode(',', $tokens);
        if (isset($candidates[$value])) {
            return;
        }
        $candidates[$value] = [
            'value' => $value,
            'label' => implode(', ', $labels),
            'sessions_per_week' => $sessionsPerWeek,
            'weeks' => facultyProposalSemesterWeeks($semester),
            'total_periods' => (int)($subjectLoad['total_periods'] ?? 0),
        ];
    };

    foreach (array_keys($dayLabels) as $startDay) {
        foreach (array_keys($sessionLabels) as $session) {
            $selected = [];
            foreach (array_keys($dayLabels) as $day) {
                if ($day < $startDay) {
                    continue;
                }
                $selected[] = [
                    'token' => $day . ':' . $session,
                    'label' => $dayLabels[$day] . ' ' . $sessionLabels[$session],
                    'day' => $day,
                    'session' => $session,
                ];
                if (count($selected) === $sessionsPerWeek) {
                    break;
                }
            }
            if (count($selected) < $sessionsPerWeek) {
                foreach (array_keys($dayLabels) as $day) {
                    if ($day >= $startDay) {
                        continue;
                    }
                    $selected[] = [
                        'token' => $day . ':' . $session,
                        'label' => $dayLabels[$day] . ' ' . $sessionLabels[$session],
                        'day' => $day,
                        'session' => $session,
                    ];
                    if (count($selected) === $sessionsPerWeek) {
                        break;
                    }
                }
            }
            $buildCandidate($selected);
        }
    }

    return array_values($candidates);
}

function facultyProposalRecommendedScheduleAndRoom(mysqli $conn, int $semesterId, int $subjectId, int $maxStudents, array $semester, array $subjectLoad): array
{
    $candidates = facultyProposalScheduleCandidates($semester, $subjectLoad);

    foreach ($candidates as $candidate) {
        $schedule = (string)$candidate['value'];
        $rooms = facultyProposalFindAvailableClassrooms($conn, $semesterId, $subjectId, $maxStudents, $schedule);
        if (!empty($rooms)) {
            $room = $rooms[0];
            return [
                'day_sessions' => $schedule,
                'room' => (string)$room['room_code'],
                'classroom_id' => (int)$room['id'],
                'room_label' => $room['room_code'] . ' (' . (int)$room['capacity'] . ' SV)',
            ];
        }
    }

    return [
        'day_sessions' => (string)($candidates[0]['value'] ?? '2:sang'),
        'room' => '',
        'classroom_id' => 0,
        'room_label' => 'Chưa có phòng trống phù hợp',
    ];
}

function facultyProposalRoomOptions(mysqli $conn, int $semesterId, int $subjectId, int $maxStudents, string $daySessions): array
{
    if ($conn->query("SHOW TABLES LIKE 'classrooms'")->num_rows === 0) {
        return [];
    }

    $needsSpecialRoom = academicPolicySubjectNeedsSpecialRoom($conn, $subjectId);
    $stmt = $conn->prepare(
        "SELECT id, room_code, room_name, building, room_type, capacity, status, note
         FROM classrooms
         WHERE status = 'active'
         ORDER BY building ASC, capacity ASC, room_code ASC"
    );
    $stmt->execute();
    $rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $options = [];
    foreach ($rooms as $room) {
        $reason = '';
        if ((int)$room['capacity'] < $maxStudents) {
            $reason = 'Không đủ sức chứa';
        } elseif (!academicPolicyClassroomIsSuitable($room, $maxStudents, $needsSpecialRoom)) {
            $reason = $needsSpecialRoom ? 'Không phù hợp phòng thực hành' : 'Không phù hợp';
        } elseif (facultyProposalRoomScheduleConflict($conn, (string)$room['room_code'], $semesterId, $daySessions)) {
            $reason = 'Trùng lịch';
        }

        $options[] = [
            'room_code' => (string)$room['room_code'],
            'room_name' => (string)($room['room_name'] ?? ''),
            'building' => (string)($room['building'] ?? ''),
            'room_type' => (string)($room['room_type'] ?? ''),
            'capacity' => (int)$room['capacity'],
            'available' => $reason === '',
            'reason' => $reason,
            'label' => trim((string)$room['room_code'] . ' - ' . ((string)($room['room_name'] ?? '') ?: (string)($room['building'] ?? '')) . ' (' . (int)$room['capacity'] . ' SV)'),
        ];
    }

    usort($options, static function (array $a, array $b): int {
        return [(int)!$a['available'], (int)$a['capacity'], $a['room_code']]
            <=> [(int)!$b['available'], (int)$b['capacity'], $b['room_code']];
    });

    return $options;
}

function facultyProposalEligibleTeachers(mysqli $conn, int $facultyId, int $semesterId, int $subjectId): array
{
    $wishTable = $conn->query("SHOW TABLES LIKE 'teaching_wishes'");
    if ($wishTable && $wishTable->num_rows > 0) {
        $stmt = $conn->prepare(
            "SELECT DISTINCT t.id, t.teacher_code, u.full_name,
                    COALESCE(SUM(s2.credits), 0) AS current_load,
                    'approved_wish' AS source
             FROM teaching_wishes tw
             JOIN teachers t ON tw.teacher_id = t.id
             JOIN users u ON t.user_id = u.id
             LEFT JOIN course_sections cs2 ON cs2.teacher_id = t.id AND cs2.semester_id = ? AND cs2.status IN ('open','closed')
             LEFT JOIN subjects s2 ON cs2.subject_id = s2.id
             WHERE tw.subject_id = ?
               AND tw.semester_id = ?
               AND tw.faculty_id = ?
               AND t.faculty_id = ?
               AND u.status = 1
               AND tw.status IN ('faculty_approved','confirmed')
             GROUP BY t.id, t.teacher_code, u.full_name
             ORDER BY current_load ASC, u.full_name ASC"
        );
        $stmt->bind_param('iiiii', $semesterId, $subjectId, $semesterId, $facultyId, $facultyId);
        $stmt->execute();
        $teachers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        if (!empty($teachers)) {
            return $teachers;
        }
    }

    $subject = null;
    $stmtSubject = $conn->prepare(
        "SELECT s.subject_name, m.major_name
         FROM subjects s
         JOIN majors m ON s.major_id = m.id
         WHERE s.id = ?
         LIMIT 1"
    );
    $stmtSubject->bind_param('i', $subjectId);
    $stmtSubject->execute();
    $subject = $stmtSubject->get_result()->fetch_assoc() ?: ['subject_name' => '', 'major_name' => ''];
    $stmtSubject->close();

    $stmt = $conn->prepare(
        "SELECT DISTINCT t.id, t.teacher_code, u.full_name,
                COALESCE(SUM(s2.credits), 0) AS current_load,
                'system' AS source,
                CASE
                    WHEN LOWER(COALESCE(t.specialization, '')) LIKE LOWER(?) THEN 1
                    WHEN LOWER(COALESCE(t.specialization, '')) LIKE LOWER(?) THEN 1
                    ELSE 0
                END AS specialization_match
         FROM teachers t
         JOIN users u ON t.user_id = u.id
         LEFT JOIN course_sections cs2 ON cs2.teacher_id = t.id AND cs2.semester_id = ? AND cs2.status IN ('open','closed')
         LEFT JOIN subjects s2 ON cs2.subject_id = s2.id
         WHERE t.faculty_id = ?
           AND u.status = 1
           AND NOT EXISTS (
                SELECT 1 FROM teaching_wishes tw_bad
                WHERE tw_bad.teacher_id = t.id
                  AND tw_bad.subject_id = ?
                  AND tw_bad.semester_id = ?
                  AND tw_bad.status IN ('faculty_rejected','dept_rejected','cancelled')
           )
         GROUP BY t.id, t.teacher_code, u.full_name
         ORDER BY specialization_match DESC, current_load ASC, u.full_name ASC
         LIMIT 12"
    );
    $subjectLike = '%' . (string)$subject['subject_name'] . '%';
    $majorLike = '%' . (string)$subject['major_name'] . '%';
    $stmt->bind_param('ssiiii', $subjectLike, $majorLike, $semesterId, $facultyId, $subjectId, $semesterId);
    $stmt->execute();
    $teachers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $teachers;
}

function facultyProposalExistingOpeningSummary(mysqli $conn, int $subjectId, int $semesterId, int $cohortId): array
{
    $stmt = $conn->prepare(
        "SELECT section_code, status
         FROM course_sections
         WHERE subject_id = ? AND semester_id = ? AND target_cohort_id = ?
           AND status IN ('draft','proposed','open','full','closed')
         ORDER BY id ASC"
    );
    $stmt->bind_param('iii', $subjectId, $semesterId, $cohortId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return [
        'count' => count($rows),
        'codes' => array_map(static fn(array $row): string => (string)$row['section_code'], $rows),
    ];
}

// POST Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isFacultyManager()) {
        $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Bạn không có quyền thực hiện thao tác này.'];
        header('Location: proposals.php');
        exit();
    }

    $action = trim($_POST['action'] ?? '');

    // CREATE DRAFT (mo lop)
    if ($action === 'create_draft') {
        $subjectId   = (int)($_POST['subject_id'] ?? 0);
        $semesterId  = (int)($_POST['semester_id'] ?? 0);
        $cohortId    = (int)($_POST['cohort_id'] ?? 0);
        $expStudents = (int)($_POST['expected_students'] ?? 0);
        $sectionName = trim($_POST['section_name'] ?? '');
        $daySessions = trim($_POST['day_sessions'] ?? '');
        $note        = trim($_POST['open_proposal_note'] ?? '');

        if ($subjectId <= 0 || $semesterId <= 0 || $cohortId <= 0 || $expStudents <= 0 || $sectionName === '' || $daySessions === '') {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Vui lòng điền đầy đủ thông tin.'];
            header('Location: proposals.php?tab=open');
            exit();
        }
        facultyProposalWindowOrRedirect($conn, $semesterId, 'proposals.php?tab=open');
        try {
            facultyProposalEnsureNullableTeacherId($conn);
        } catch (Throwable $e) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Lỗi: ' . $e->getMessage()];
            header('Location: proposals.php?tab=open');
            exit();
        }

        // Kiem tra semester hop le
        $stmtSemChk = $conn->prepare("SELECT id FROM semesters WHERE id = ? AND status IN ('active','upcoming','open') LIMIT 1");
        $stmtSemChk->bind_param('i', $semesterId);
        $stmtSemChk->execute();
        if ($stmtSemChk->get_result()->num_rows === 0) {
            $stmtSemChk->close();
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Học kỳ không hợp lệ.'];
            header('Location: proposals.php?tab=open');
            exit();
        }
        $stmtSemChk->close();

        $proposalWindow = academicPolicyCheckProposalWindow($conn, $semesterId);
        if (!$proposalWindow['ok']) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => $proposalWindow['message']];
            header('Location: proposals.php?tab=open');
            exit();
        }

        $policyCheck = academicPolicyValidateSubjectOpening($conn, $facultyId, $subjectId, $semesterId, $cohortId);
        if (!$policyCheck['ok']) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => $policyCheck['message']];
            header('Location: proposals.php?tab=open');
            exit();
        }

        // Kiem tra duplicate
        $demoContext = academicPolicySemesterDemoContext($conn, $semesterId);
        $stmtDup = $conn->prepare(
            "SELECT id FROM course_sections WHERE subject_id = ? AND semester_id = ? AND data_mode = ? AND status IN ('proposed','open','draft') LIMIT 1"
        );
        $stmtDup->bind_param('iis', $subjectId, $semesterId, $demoContext['data_mode']);
        $stmtDup->execute();
        if ($stmtDup->get_result()->num_rows > 0) {
            $stmtDup->close();
            $_SESSION['_flash'] = ['type' => 'warning', 'message' => 'Đã có đề xuất hoặc lớp học phần cho môn này trong học kỳ được chọn.'];
            header('Location: proposals.php?tab=open');
            exit();
        }
        $stmtDup->close();

        $stmtIns = $conn->prepare(
            "INSERT INTO course_sections (subject_id, semester_id, target_cohort_id, section_code, status, data_mode, demo_batch_id, expected_students, max_students, day_sessions, open_proposal_note, open_proposed_by)
             VALUES (?, ?, ?, ?, 'draft', ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmtIns->bind_param('iiisssiissi', $subjectId, $semesterId, $cohortId, $sectionName, $demoContext['data_mode'], $demoContext['demo_batch_id'], $expStudents, $expStudents, $daySessions, $note, $userId);
        $stmtIns->execute();
        $newId = (int)$conn->insert_id;
        $stmtIns->close();

        logAudit($conn, $userId, 'create', 'faculty', 'course_sections', $newId, null,
            json_encode(['subject_id' => $subjectId, 'semester_id' => $semesterId, 'status' => 'draft']), $ip);

        $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Đã tạo đề xuất nháp. Vui lòng gửi đề xuất khi sẵn sàng.'];
        header('Location: proposals.php?tab=open');
        exit();
    }

    if ($action === 'batch_create_drafts') {
        $semesterId = (int)($_POST['semester_id'] ?? 0);
        $selected = $_POST['selected'] ?? [];
        $classCounts = $_POST['class_count'] ?? [];
        $maxStudents = $_POST['max_students'] ?? [];
        $daySessions = $_POST['day_sessions'] ?? [];
        $roomCodes = $_POST['room'] ?? [];
        $teacherIds = $_POST['proposed_teacher_id'] ?? [];
        $notes = $_POST['open_proposal_note'] ?? [];

        if ($semesterId <= 0 || empty($selected) || !is_array($selected)) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Vui lòng chọn học kỳ và ít nhất một môn cần mở.'];
            header('Location: proposals.php?tab=open&semester_id=' . $semesterId);
            exit();
        }
        facultyProposalWindowOrRedirect($conn, $semesterId, 'proposals.php?tab=open&semester_id=' . $semesterId);
        try {
            facultyProposalEnsureNullableTeacherId($conn);
        } catch (Throwable $e) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Lỗi: ' . $e->getMessage()];
            header('Location: proposals.php?tab=open&semester_id=' . $semesterId);
            exit();
        }

        $created = 0;
        $skippedDuplicates = [];
        $conn->begin_transaction();
        try {
            foreach ($selected as $key => $_checked) {
                [$subjectIdRaw, $cohortIdRaw] = array_pad(explode(':', (string)$key, 2), 2, '0');
                $subjectId = (int)$subjectIdRaw;
                $cohortId = (int)$cohortIdRaw;
                if ($subjectId <= 0 || $cohortId <= 0) {
                    continue;
                }

                $policyCheck = academicPolicyValidateSubjectOpening($conn, $facultyId, $subjectId, $semesterId, $cohortId);
                if (!$policyCheck['ok'] || empty($policyCheck['eligible'][0])) {
                    throw new Exception($policyCheck['message'] ?: 'Môn học không phù hợp kế hoạch đào tạo.');
                }
                $eligible = $policyCheck['eligible'][0];

                $count = max(1, min(10, (int)($classCounts[$key] ?? 1)));
                $capacity = max(1, (int)($maxStudents[$key] ?? 60));
                $schedule = trim((string)($daySessions[$key] ?? ''));
                $roomCode = trim((string)($roomCodes[$key] ?? ''));
                $teacherId = (int)($teacherIds[$key] ?? 0);
                $note = trim((string)($notes[$key] ?? ''));
                if ($schedule === '') {
                    $semesterRow = academicPolicyGetSemester($conn, $semesterId) ?: [];
                    $subjectLoad = facultyProposalSubjectLoad($conn, $subjectId, (int)($eligible['credits'] ?? 3));
                    $suggestion = facultyProposalRecommendedScheduleAndRoom($conn, $semesterId, $subjectId, $capacity, $semesterRow, $subjectLoad);
                    $schedule = (string)$suggestion['day_sessions'];
                    $roomCode = (string)$suggestion['room'];
                }

                $demoContext = academicPolicySemesterDemoContext($conn, $semesterId);
                $stmtExisting = $conn->prepare(
                    "SELECT COUNT(*) AS c FROM course_sections
                     WHERE subject_id = ? AND semester_id = ? AND target_cohort_id = ?
                       AND data_mode = ?
                       AND status IN ('draft','proposed','open','full','closed')"
                );
                $stmtExisting->bind_param('iiis', $subjectId, $semesterId, $cohortId, $demoContext['data_mode']);
                $stmtExisting->execute();
                $existingCount = (int)($stmtExisting->get_result()->fetch_assoc()['c'] ?? 0);
                $stmtExisting->close();
                if ($existingCount >= $count) {
                    $skippedDuplicates[] = $eligible['subject_code'] . ' (' . $existingCount . ' lớp đã có)';
                    continue;
                }

                for ($i = $existingCount; $i < $count; $i++) {
                    $availableRooms = facultyProposalFindAvailableClassrooms($conn, $semesterId, $subjectId, $capacity, $schedule);
                    if (empty($availableRooms)) {
                        throw new Exception('Không còn phòng trống phù hợp cho môn ' . $eligible['subject_code'] . ' ở lịch ' . $schedule . '.');
                    }
                    $selectedRoom = $availableRooms[0];
                    if ($roomCode !== '') {
                        foreach ($availableRooms as $availableRoom) {
                            if ((string)$availableRoom['room_code'] === $roomCode) {
                                $selectedRoom = $availableRoom;
                                break;
                            }
                        }
                        if ($i === $existingCount && (string)$selectedRoom['room_code'] !== $roomCode) {
                            throw new Exception('Phòng ' . $roomCode . ' không trống hoặc không phù hợp cho môn ' . $eligible['subject_code'] . ' ở lịch ' . $schedule . '.');
                        }
                    }

                    $sectionCode = facultyProposalNextSectionCode($conn, (string)$eligible['subject_code'], $semesterId);
                    $stmtIns = $conn->prepare(
                        "INSERT INTO course_sections
                         (subject_id, semester_id, target_cohort_id, section_code, status, expected_students,
                          max_students, data_mode, demo_batch_id, day_sessions, room, classroom_id, teaching_mode,
                          open_proposal_note, open_proposed_by, open_proposed_at)
                         VALUES (?, ?, ?, ?, 'proposed', ?, ?, ?, ?, ?, ?, ?, 'offline', ?, ?, NOW())"
                    );
                    $room = (string)$selectedRoom['room_code'];
                    $classroomId = (int)$selectedRoom['id'];
                    $stmtIns->bind_param('iiisiissssisi', $subjectId, $semesterId, $cohortId, $sectionCode, $capacity, $capacity, $demoContext['data_mode'], $demoContext['demo_batch_id'], $schedule, $room, $classroomId, $note, $userId);
                    if (!$stmtIns->execute()) {
                        throw new Exception('Không tạo được đề xuất ' . $sectionCode . ': ' . $stmtIns->error);
                    }
                    $newId = (int)$conn->insert_id;
                    $stmtIns->close();

                    if ($teacherId > 0) {
                        $eligibleTeacherIds = array_map(
                            static fn(array $teacher): int => (int)$teacher['id'],
                            facultyProposalEligibleTeachers($conn, $facultyId, $semesterId, $subjectId)
                        );
                        if (!in_array($teacherId, $eligibleTeacherIds, true)) {
                            throw new Exception($sectionCode . ': Giảng viên được chọn chưa có nguyện vọng/chuyên môn được duyệt cho môn ' . $eligible['subject_code'] . '.');
                        }
                        $assignmentCheck = validateTeacherAssignmentForSection($conn, $teacherId, $newId);
                        if (!$assignmentCheck['ok']) {
                            throw new Exception($sectionCode . ': ' . $assignmentCheck['message']);
                        }
                        $proposalNote = $note !== '' ? $note : 'Khoa/Viện đề xuất cùng kế hoạch mở lớp.';
                        $stmtTeacher = $conn->prepare(
                            "UPDATE course_sections
                             SET proposed_teacher_id = ?, proposal_status = 'pending',
                                 proposal_note = ?, proposed_by = ?, proposed_at = NOW()
                             WHERE id = ?"
                        );
                        $stmtTeacher->bind_param('isii', $teacherId, $proposalNote, $userId, $newId);
                        $stmtTeacher->execute();
                        $stmtTeacher->close();
                    }

                    logAudit($conn, $userId, 'submit', 'faculty', 'course_sections', $newId, null,
                        json_encode(['subject_id' => $subjectId, 'semester_id' => $semesterId, 'status' => 'proposed']), $ip);
                    $created++;
                }
            }

            if ($created === 0) {
                $duplicateMessage = $skippedDuplicates
                    ? ' Các môn bị trùng hoặc đã đủ số lớp: ' . implode(', ', array_slice($skippedDuplicates, 0, 6)) . (count($skippedDuplicates) > 6 ? ', ...' : '') . '.'
                    : '';
                throw new Exception('Chưa có đề xuất nào được tạo.' . $duplicateMessage);
            }
            $conn->commit();
            $skipMessage = $skippedDuplicates
                ? ' Bỏ qua ' . count($skippedDuplicates) . ' dòng đã đủ số lớp/không tạo trùng.'
                : '';
            $_SESSION['_flash'] = ['type' => 'success', 'message' => "Đã gửi {$created} đề xuất mở lớp lên Phòng Đào tạo." . $skipMessage];
        } catch (Throwable $e) {
            $conn->rollback();
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Lỗi: ' . $e->getMessage()];
        }
        header('Location: proposals.php?tab=open&semester_id=' . $semesterId);
        exit();
    }

    // SUBMIT (draft -> pending)
    if ($action === 'submit') {
        $sectionId = (int)($_POST['section_id'] ?? 0);
        if ($sectionId <= 0) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Dữ liệu không hợp lệ.'];
            header('Location: proposals.php?tab=open');
            exit();
        }
        facultyProposalWindowOrRedirect($conn, facultyProposalSectionSemester($conn, $sectionId), 'proposals.php?tab=open');

        // Kiem tra ownership va status
        $stmtChk = $conn->prepare(
            "SELECT cs.id, cs.status FROM course_sections cs
             JOIN subjects s ON cs.subject_id = s.id
             JOIN curriculum cur ON s.id = cur.subject_id AND cur.deleted_at IS NULL
             JOIN majors m ON cur.major_id = m.id
             WHERE cs.id = ? AND m.faculty_id = ? AND cs.status = 'draft' LIMIT 1"
        );
        $stmtChk->bind_param('ii', $sectionId, $facultyId);
        $stmtChk->execute();
        if ($stmtChk->get_result()->num_rows === 0) {
            $stmtChk->close();
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Đề xuất không hợp lệ hoặc đã được gửi.'];
            header('Location: proposals.php?tab=open');
            exit();
        }
        $stmtChk->close();

        $stmtSec = $conn->prepare("SELECT semester_id FROM course_sections WHERE id = ? LIMIT 1");
        $stmtSec->bind_param('i', $sectionId);
        $stmtSec->execute();
        $secSem = $stmtSec->get_result()->fetch_assoc();
        $stmtSec->close();
        $proposalWindow = academicPolicyCheckProposalWindow($conn, (int)($secSem['semester_id'] ?? 0));
        if (!$proposalWindow['ok']) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => $proposalWindow['message']];
            header('Location: proposals.php?tab=open');
            exit();
        }

        $stmtUpd = $conn->prepare(
            "UPDATE course_sections SET status = 'proposed', open_proposed_at = NOW(), open_proposed_by = ? WHERE id = ?"
        );
        $stmtUpd->bind_param('ii', $userId, $sectionId);
        $stmtUpd->execute();
        $stmtUpd->close();

        logAudit($conn, $userId, 'submit', 'faculty', 'course_sections', $sectionId, null,
            json_encode(['status' => 'proposed']), $ip);

        $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Đã gửi đề xuất mở lớp. Chờ Phòng Đào tạo duyệt.'];
        header('Location: proposals.php?tab=open');
        exit();
    }

    // CANCEL (pending -> cancelled)
    if ($action === 'cancel') {
        $sectionId = (int)($_POST['section_id'] ?? 0);
        if ($sectionId <= 0) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Dữ liệu không hợp lệ.'];
            header('Location: proposals.php?tab=open');
            exit();
        }
        facultyProposalWindowOrRedirect($conn, facultyProposalSectionSemester($conn, $sectionId), 'proposals.php?tab=open');

        $stmtChk = $conn->prepare(
            "SELECT cs.id FROM course_sections cs
             JOIN subjects s ON cs.subject_id = s.id
             JOIN curriculum cur ON s.id = cur.subject_id AND cur.deleted_at IS NULL
             JOIN majors m ON cur.major_id = m.id
             WHERE cs.id = ? AND m.faculty_id = ? AND cs.status = 'proposed' LIMIT 1"
        );
        $stmtChk->bind_param('ii', $sectionId, $facultyId);
        $stmtChk->execute();
        if ($stmtChk->get_result()->num_rows === 0) {
            $stmtChk->close();
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Chỉ có thể hủy đề xuất đang chờ duyệt.'];
            header('Location: proposals.php?tab=open');
            exit();
        }
        $stmtChk->close();

        $stmtUpd = $conn->prepare("UPDATE course_sections SET status = 'cancelled' WHERE id = ?");
        $stmtUpd->bind_param('i', $sectionId);
        $stmtUpd->execute();
        $stmtUpd->close();

        logAudit($conn, $userId, 'update', 'faculty', 'course_sections', $sectionId, null,
            json_encode(['status' => 'cancelled']), $ip);

        $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Đã hủy đề xuất.'];
        header('Location: proposals.php?tab=open');
        exit();
    }

    // PROPOSE TEACHER
    if ($action === 'propose_teacher') {
        $sectionId  = (int)($_POST['section_id'] ?? 0);
        $teacherId  = (int)($_POST['proposed_teacher_id'] ?? 0);
        $propNote   = trim($_POST['proposal_note'] ?? '');

        if ($sectionId <= 0 || $teacherId <= 0) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Dữ liệu không hợp lệ.'];
            header('Location: proposals.php?tab=assign');
            exit();
        }
        facultyProposalWindowOrRedirect($conn, facultyProposalSectionSemester($conn, $sectionId), 'proposals.php?tab=assign');

        // Kiem tra section chua duoc duyet
        $stmtSChk = $conn->prepare(
            "SELECT cs.id, cs.proposal_status FROM course_sections cs
             JOIN subjects s ON cs.subject_id = s.id
             JOIN curriculum cur ON s.id = cur.subject_id AND cur.deleted_at IS NULL
             JOIN majors m ON cur.major_id = m.id
             WHERE cs.id = ? AND m.faculty_id = ? LIMIT 1"
        );
        $stmtSChk->bind_param('ii', $sectionId, $facultyId);
        $stmtSChk->execute();
        $secRow = $stmtSChk->get_result()->fetch_assoc();
        $stmtSChk->close();

        if (!$secRow) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Lớp học phần không hợp lệ.'];
            header('Location: proposals.php?tab=assign');
            exit();
        }
        if ($secRow['proposal_status'] === 'approved') {
            $_SESSION['_flash'] = ['type' => 'warning', 'message' => 'Phân công đã được Phòng Đào tạo phê duyệt.'];
            header('Location: proposals.php?tab=assign');
            exit();
        }

        $assignmentCheck = validateTeacherAssignmentForSection($conn, $teacherId, $sectionId);
        if (!$assignmentCheck['ok']) {
            // Giữ thông điệp cũ cho luồng khoa: chỉ được đề xuất giảng viên thuộc khoa mình.
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => $assignmentCheck['message']];
            header('Location: proposals.php?tab=assign');
            exit();
        }

        $stmtUpd = $conn->prepare(
            "UPDATE course_sections SET proposed_teacher_id = ?, proposal_status = 'pending',
             proposal_note = ?, proposed_by = ?, proposed_at = NOW() WHERE id = ?"
        );
        $stmtUpd->bind_param('isii', $teacherId, $propNote, $userId, $sectionId);
        $stmtUpd->execute();
        $stmtUpd->close();

        logAudit($conn, $userId, 'submit', 'faculty', 'course_sections', $sectionId, null,
            json_encode(['proposed_teacher_id' => $teacherId, 'proposal_status' => 'pending']), $ip);

        $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Đã gửi đề xuất phân công giảng viên.'];
        header('Location: proposals.php?tab=assign');
        exit();
    }

    // CANCEL TEACHER PROPOSAL
    if ($action === 'cancel_teacher_proposal') {
        $sectionId = (int)($_POST['section_id'] ?? 0);
        if ($sectionId <= 0) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Dữ liệu không hợp lệ.'];
            header('Location: proposals.php?tab=assign');
            exit();
        }
        facultyProposalWindowOrRedirect($conn, facultyProposalSectionSemester($conn, $sectionId), 'proposals.php?tab=assign');

        $stmtChk = $conn->prepare(
            "SELECT cs.id FROM course_sections cs
             JOIN subjects s ON cs.subject_id = s.id
             JOIN curriculum cur ON s.id = cur.subject_id AND cur.deleted_at IS NULL
             JOIN majors m ON cur.major_id = m.id
             WHERE cs.id = ? AND m.faculty_id = ? AND cs.proposal_status = 'pending' LIMIT 1"
        );
        $stmtChk->bind_param('ii', $sectionId, $facultyId);
        $stmtChk->execute();
        if ($stmtChk->get_result()->num_rows === 0) {
            $stmtChk->close();
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Chỉ có thể hủy đề xuất đang chờ duyệt.'];
            header('Location: proposals.php?tab=assign');
            exit();
        }
        $stmtChk->close();

        $stmtUpd = $conn->prepare(
            "UPDATE course_sections SET proposed_teacher_id = NULL, proposal_status = NULL, proposal_note = NULL WHERE id = ?"
        );
        $stmtUpd->bind_param('i', $sectionId);
        $stmtUpd->execute();
        $stmtUpd->close();

        logAudit($conn, $userId, 'update', 'faculty', 'course_sections', $sectionId, null,
            json_encode(['proposal_status' => null]), $ip);

        $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Đã hủy đề xuất phân công giảng viên.'];
        header('Location: proposals.php?tab=assign');
        exit();
    }

    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Hành động không hợp lệ.'];
    header('Location: proposals.php');
    exit();
}

// GET Handler
$flash      = getFlash();
$tab        = trim($_GET['tab'] ?? 'open'); // open | assign
$statusFilter = trim($_GET['status'] ?? '');

$semesters = [];
$stmtSem = $conn->prepare(
    "SELECT id, semester_name, school_year, status, start_date, end_date,
            proposal_start, proposal_end
     FROM semesters
     ORDER BY COALESCE(start_date, created_at) DESC, id DESC"
);
$stmtSem->execute();
$semesters = $stmtSem->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtSem->close();

$activeSemester = getActiveSemester($conn);
$selectedSemId  = (int)($_GET['semester_id'] ?? 0);
if ($selectedSemId <= 0 && !empty($semesters)) {
    foreach ($semesters as $sem) {
        $window = academicPolicyCheckFacultyProposalWindow($conn, (int)$sem['id']);
        if ($window['ok']) {
            $selectedSemId = (int)$sem['id'];
            break;
        }
    }
}
if ($selectedSemId <= 0 && !empty($activeSemester['id'])) {
    $selectedSemId = (int)$activeSemester['id'];
}
if ($selectedSemId <= 0 && !empty($semesters)) {
    foreach ($semesters as $sem) {
        if (in_array($sem['status'], ['active', 'upcoming', 'open'], true)) {
            $selectedSemId = (int)$sem['id'];
            break;
        }
    }
    if ($selectedSemId <= 0) {
        $selectedSemId = (int)$semesters[0]['id'];
    }
}
$facultyProposalWindow = $selectedSemId > 0
    ? academicPolicyCheckFacultyProposalWindow($conn, $selectedSemId)
    : ['ok' => false, 'message' => 'Vui lòng chọn học kỳ để kiểm tra thời gian Khoa/Viện được phép thao tác.'];
$selectedSemester = null;
foreach ($semesters as $sem) {
    if ((int)$sem['id'] === $selectedSemId) {
        $selectedSemester = $sem;
        break;
    }
}

// Lay de xuat mo lop
$openProposals = [];
$stmtOpen = $conn->prepare(
    "SELECT DISTINCT cs.id, cs.section_code, cs.status, cs.max_students,
            cs.open_proposal_note, cs.open_proposed_at, cs.day_sessions,
            s.subject_name, s.subject_code,
            sem.semester_name, sem.school_year,
            tc.cohort_code, tc.cohort_name,
            u.full_name AS proposed_by_name
     FROM course_sections cs
     JOIN subjects s ON cs.subject_id = s.id
     JOIN curriculum cur ON s.id = cur.subject_id AND cur.deleted_at IS NULL
     JOIN majors m ON cur.major_id = m.id
     JOIN semesters sem ON cs.semester_id = sem.id
     LEFT JOIN training_cohorts tc ON cs.target_cohort_id = tc.id
     LEFT JOIN users u ON cs.open_proposed_by = u.id
     WHERE m.faculty_id = ?
       AND cs.semester_id = ?
       AND cs.status IN ('draft','proposed','open','cancelled','closed')
     ORDER BY cs.open_proposed_at DESC, cs.id DESC"
);
$stmtOpen->bind_param('ii', $facultyId, $selectedSemId);
$stmtOpen->execute();
$openProposals = $stmtOpen->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtOpen->close();

// Lay de xuat phan cong GV
$assignProposals = [];
$stmtAssign = $conn->prepare(
    "SELECT cs.id, cs.section_code, cs.status, cs.proposal_status,
            cs.proposal_note, cs.proposed_at, cs.proposal_reject_reason,
            s.subject_name, s.subject_code,
            sem.semester_name, sem.school_year,
            pt.teacher_code AS proposed_teacher_code,
            pu.full_name AS proposed_teacher_name,
            at2.teacher_code AS assigned_teacher_code,
            au.full_name AS assigned_teacher_name,
            pb.full_name AS proposed_by_name
     FROM course_sections cs
     JOIN subjects s ON cs.subject_id = s.id
     JOIN curriculum cur ON s.id = cur.subject_id AND cur.deleted_at IS NULL
     JOIN majors m ON cur.major_id = m.id
     JOIN semesters sem ON cs.semester_id = sem.id
     LEFT JOIN teachers pt ON cs.proposed_teacher_id = pt.id
     LEFT JOIN users pu ON pt.user_id = pu.id
     LEFT JOIN teachers at2 ON cs.teacher_id = at2.id
     LEFT JOIN users au ON at2.user_id = au.id
     LEFT JOIN users pb ON cs.proposed_by = pb.id
     WHERE m.faculty_id = ? AND cs.semester_id = ?
     ORDER BY cs.proposed_at DESC, cs.id DESC"
);
$stmtAssign->bind_param('ii', $facultyId, $selectedSemId);
$stmtAssign->execute();
$assignProposals = $stmtAssign->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtAssign->close();

// Lay danh sach GV trong khoa
$facultyTeachers = [];
$stmtFT = $conn->prepare(
    "SELECT t.id, t.teacher_code, u.full_name,
            COALESCE(SUM(s2.credits), 0) AS current_load
     FROM teachers t
     JOIN users u ON t.user_id = u.id
     LEFT JOIN course_sections cs2 ON cs2.teacher_id = t.id AND cs2.semester_id = ? AND cs2.status IN ('open','closed')
     LEFT JOIN subjects s2 ON cs2.subject_id = s2.id
     WHERE t.faculty_id = ?
     GROUP BY t.id, t.teacher_code, u.full_name
     ORDER BY u.full_name ASC"
);
$stmtFT->bind_param('ii', $selectedSemId, $facultyId);
$stmtFT->execute();
$facultyTeachers = $stmtFT->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtFT->close();

$activeRoomOptions = [];
$roomTable = $conn->query("SHOW TABLES LIKE 'classrooms'");
if ($roomTable && $roomTable->num_rows > 0) {
    $stmtRooms = $conn->prepare(
        "SELECT id, room_code, room_name, building, room_type, capacity
         FROM classrooms
         WHERE status = 'active'
         ORDER BY capacity ASC, building ASC, room_code ASC
         LIMIT 160"
    );
    $stmtRooms->execute();
    $activeRoomOptions = $stmtRooms->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtRooms->close();
}

// Lay danh sach mon hoc dung CTDT/hoc ky/khoa tuyen sinh cua khoa.
// Trang nay co the co rat nhieu mon test; chi load nhanh danh sach dau,
// con kiem tra phong trung lich van thuc hien o server khi bam gui.
$facultySubjectLimit = 80;
$facultySubjectsAll = $selectedSemId > 0
    ? academicPolicyFindEligibleOpenings($conn, $facultyId, $selectedSemId, null, null, $facultySubjectLimit + 1)
    : [];
$facultySubjectTotal = count($facultySubjectsAll);
$facultySubjects = array_slice($facultySubjectsAll, 0, $facultySubjectLimit);
$facultySubjectsTruncated = $facultySubjectTotal > $facultySubjectLimit;
unset($facultySubjectsAll);
foreach ($facultySubjects as &$subjectPlan) {
    $subjectPlan['estimated_students'] = facultyProposalStudentEstimate(
        $conn,
        !empty($subjectPlan['cohort_id']) ? (int)$subjectPlan['cohort_id'] : null,
        (int)$subjectPlan['major_id'],
        (int)$subjectPlan['enrollment_year']
    );
    $defaultClassCount = max(1, (int)ceil((int)$subjectPlan['estimated_students'] / 60));
    $defaultCapacity = max(1, (int)ceil((int)$subjectPlan['estimated_students'] / $defaultClassCount));
    $subjectPlan['default_class_count'] = $defaultClassCount;
    $subjectPlan['default_capacity'] = $defaultCapacity;
    $subjectPlan['subject_load'] = facultyProposalSubjectLoad(
        $conn,
        (int)$subjectPlan['subject_id'],
        (int)$subjectPlan['credits']
    );
    $subjectPlan['schedule_options'] = facultyProposalScheduleCandidates(
        $selectedSemester ?: [],
        $subjectPlan['subject_load']
    );
    $firstSchedule = (string)($subjectPlan['schedule_options'][0]['value'] ?? '2:sang');
    $roomOptions = [];
    foreach ($activeRoomOptions as $room) {
        if ((int)$room['capacity'] < $defaultCapacity) {
            continue;
        }
        $roomOptions[] = [
            'room_code' => (string)$room['room_code'],
            'room_name' => (string)($room['room_name'] ?? ''),
            'building' => (string)($room['building'] ?? ''),
            'room_type' => (string)($room['room_type'] ?? ''),
            'capacity' => (int)$room['capacity'],
            'available' => true,
            'reason' => '',
            'label' => trim((string)$room['room_code'] . ' - ' . ((string)($room['room_name'] ?? '') ?: (string)($room['building'] ?? '')) . ' (' . (int)$room['capacity'] . ' SV)'),
        ];
        if (count($roomOptions) >= 20) {
            break;
        }
    }
    $recommendedRoom = $roomOptions[0] ?? null;
    $subjectPlan['recommendation'] = [
        'day_sessions' => $firstSchedule,
        'room' => $recommendedRoom ? (string)$recommendedRoom['room_code'] : '',
        'classroom_id' => 0,
        'room_label' => $recommendedRoom
            ? ((string)$recommendedRoom['room_code'] . ' (' . (int)$recommendedRoom['capacity'] . ' SV)')
            : 'Chua co phong phu hop voi si so',
    ];
    $subjectPlan['room_options_by_schedule'] = [];
    $subjectPlan['room_options'] = $roomOptions;
    $subjectPlan['eligible_teachers'] = $facultyTeachers;
    $subjectPlan['existing_openings'] = facultyProposalExistingOpeningSummary(
        $conn,
        (int)$subjectPlan['subject_id'],
        $selectedSemId,
        (int)$subjectPlan['cohort_id']
    );
}
unset($subjectPlan);

$facultyCohorts = [];
$stmtCohorts = $conn->prepare(
    "SELECT tc.id, tc.cohort_code, tc.cohort_name, tc.enrollment_year, m.major_name
     FROM training_cohorts tc
     JOIN majors m ON tc.major_id = m.id
     WHERE m.faculty_id = ? AND tc.status IN ('planned','active')
     ORDER BY tc.enrollment_year DESC, m.major_name ASC"
);
$stmtCohorts->bind_param('i', $facultyId);
$stmtCohorts->execute();
$facultyCohorts = $stmtCohorts->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtCohorts->close();

$statusBadgeMap = [
    'draft'     => ['secondary', 'Nháp'],
    'proposed'  => ['warning', 'Chờ duyệt'],
    'open'      => ['success', 'Đã duyệt'],
    'cancelled' => ['dark', 'Đã hủy'],
    'closed'    => ['info', 'Đã đóng'],
];
$propStatusBadgeMap = [
    'pending'  => ['warning', 'Chờ duyệt'],
    'approved' => ['success', 'Đã duyệt'],
    'rejected' => ['danger', 'Từ chối'],
];

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle" aria-label="Mo/dong menu">
                <i class="bi bi-list fs-5" aria-hidden="true"></i>
            </button>
            <span class="admin-topbar-title">
                <i class="bi bi-send-fill me-2 text-navy" aria-hidden="true"></i>Đề xuất Mở lớp & Phân công GV
            </span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <?php if (isFacultyManager() && $facultyProposalWindow['ok']): ?>
            <?php if ($tab === 'open'): ?>
            <button class="btn btn-sm btn-navy" data-bs-toggle="modal" data-bs-target="#createDraftModal"
                    aria-label="Tạo đề xuất mở lớp mới">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tạo đề xuất
            </button>
            <?php endif; ?>
            <?php endif; ?>
            <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Dang xuat
            </a>
        </div>
    </div>

    <div class="admin-content">

        <?php if ($flash): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show auto-dismiss mb-4" role="alert">
            <?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
        </div>
        <?php endif; ?>

        <div class="card mb-3">
            <div class="card-body">
                <form method="get" action="proposals.php" class="row g-3 align-items-end">
                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
                    <div class="col-12 col-md-5">
                        <label for="semester_id" class="form-label fw-semibold">Học kỳ</label>
                        <select id="semester_id" name="semester_id" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($semesters as $sem): ?>
                            <?php
                                $semWindow = academicPolicyCheckFacultyProposalWindow($conn, (int)$sem['id']);
                                $suffix = $semWindow['ok'] ? ' - Đang mở tác vụ' : '';
                                if (!$semWindow['ok'] && !empty($sem['end_date']) && strtotime($sem['end_date']) < time()) {
                                    $suffix = ' - Đã qua';
                                }
                            ?>
                            <option value="<?php echo (int)$sem['id']; ?>"
                                <?php echo $selectedSemId === (int)$sem['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sem['semester_name'] . ' ' . ($sem['school_year'] ?? '') . $suffix); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-navy">
                            <i class="bi bi-search me-1" aria-hidden="true"></i>Xem lịch sử
                        </button>
                    </div>
                    <?php if ($selectedSemester): ?>
                    <div class="col-12 col-md">
                        <div class="text-muted small">
                            Thời gian đề xuất:
                            <strong>
                                <?php echo !empty($selectedSemester['proposal_start']) ? date('d/m/Y H:i', strtotime($selectedSemester['proposal_start'])) : 'Chưa cấu hình'; ?>
                                -
                                <?php echo !empty($selectedSemester['proposal_end']) ? date('d/m/Y H:i', strtotime($selectedSemester['proposal_end'])) : 'Chưa cấu hình'; ?>
                            </strong>
                        </div>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if (!$facultyProposalWindow['ok']): ?>
        <div class="alert alert-warning mb-4" role="alert">
            <i class="bi bi-lock-fill me-2" aria-hidden="true"></i>
            <?php echo htmlspecialchars($facultyProposalWindow['message']); ?>
        </div>
        <?php else: ?>
        <div class="alert alert-success mb-4" role="alert">
            <i class="bi bi-unlock-fill me-2" aria-hidden="true"></i>
            Phòng Đào tạo đang mở tác vụ cho học kỳ này. Khoa/Viện có thể tạo, gửi đề xuất mở lớp và đề xuất phân công giảng viên.
        </div>
        <?php endif; ?>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $tab === 'open' ? 'active' : ''; ?>"
                   href="proposals.php?tab=open&semester_id=<?php echo $selectedSemId; ?>" role="tab" aria-selected="<?php echo $tab === 'open' ? 'true' : 'false'; ?>">
                    <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Đề xuất Mở lớp
                    <span class="badge bg-warning ms-1"><?php echo count(array_filter($openProposals, fn($p) => $p['status'] === 'proposed')); ?></span>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $tab === 'assign' ? 'active' : ''; ?>"
                   href="proposals.php?tab=assign&semester_id=<?php echo $selectedSemId; ?>" role="tab"
                   aria-selected="<?php echo $tab === 'assign' ? 'true' : 'false'; ?>">
                    <i class="bi bi-person-check me-1" aria-hidden="true"></i>Đề xuất Phân công GV
                    <span class="badge bg-warning ms-1"><?php echo count(array_filter($assignProposals, fn($p) => $p['proposal_status'] === 'pending')); ?></span>
                </a>
            </li>
        </ul>

        <?php if ($tab === 'open'): ?>
        <!-- Open proposals tab -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-list-check me-2" aria-hidden="true"></i>Danh sách Đề xuất Mở lớp
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Mã lớp</th>
                            <th>Môn học</th>
                            <th>Học kỳ</th>
                            <th>Khoa</th>
                            <th class="text-center">Dự kiến SV</th>
                            <th>Lịch học</th>
                            <th>Trạng thái</th>
                            <th>Ngày gửi</th>
                            <th>Ghi chú</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($openProposals)): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3 d-block mb-2" aria-hidden="true"></i>
                                Chưa có đề xuất nào.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($openProposals as $p): ?>
                        <?php $sb = $statusBadgeMap[$p['status']] ?? ['secondary', $p['status']]; ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($p['section_code']); ?></code></td>
                            <td><?php echo htmlspecialchars($p['subject_name']); ?></td>
                            <td><?php echo htmlspecialchars($p['semester_name'] . ' - ' . ($p['school_year'] ?? '')); ?></td>
                            <td class="text-muted small"><?php echo htmlspecialchars($p['cohort_code'] ?? '—'); ?></td>
                            <td class="text-center"><?php echo (int)$p['max_students']; ?></td>
                            <td class="text-muted small"><?php echo htmlspecialchars($p['day_sessions'] ?? '—'); ?></td>
                            <td><span class="badge bg-<?php echo $sb[0]; ?>"><?php echo $sb[1]; ?></span></td>
                            <td class="text-muted small"><?php echo htmlspecialchars($p['open_proposed_at'] ?? '—'); ?></td>
                            <td class="text-muted small"><?php echo htmlspecialchars(mb_substr($p['open_proposal_note'] ?? '', 0, 50)); ?></td>
                            <td>
                                <?php if (isFacultyManager() && $facultyProposalWindow['ok']): ?>
                                <?php if ($p['status'] === 'draft'): ?>
                                <form method="post" action="proposals.php" class="d-inline">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="submit">
                                    <input type="hidden" name="section_id" value="<?php echo (int)$p['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-warning"
                                            aria-label="Gửi đề xuất <?php echo htmlspecialchars($p['section_code']); ?>">
                                        <i class="bi bi-send" aria-hidden="true"></i> Gửi
                                    </button>
                                </form>
                                <?php elseif ($p['status'] === 'proposed'): ?>
                                <form method="post" action="proposals.php" class="d-inline"
                                      onsubmit="return confirm('Hủy đề xuất này?')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="section_id" value="<?php echo (int)$p['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                            aria-label="Hủy đề xuất <?php echo htmlspecialchars($p['section_code']); ?>">
                                        <i class="bi bi-x-circle" aria-hidden="true"></i> Hủy
                                    </button>
                                </form>
                                <?php elseif ($p['status'] === 'open'): ?>
                                <span class="badge bg-success">Đã được duyệt</span>
                                <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php else: ?>
        <!-- Assign proposals tab -->
        <div class="card mb-3">
            <div class="card-body">
                <form method="get" action="proposals.php" class="row g-3 align-items-end">
                    <input type="hidden" name="tab" value="assign">
                    <div class="col-12 col-md-4">
                        <label for="assign_semester_id" class="form-label">Học kỳ</label>
                        <select id="assign_semester_id" name="semester_id" class="form-select">
                            <?php foreach ($semesters as $sem): ?>
                            <option value="<?php echo (int)$sem['id']; ?>"
                                <?php echo $selectedSemId === (int)$sem['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sem['semester_name'] . ' - ' . ($sem['school_year'] ?? '')); ?>
                                <?php if ($sem['status'] === 'active'): ?>(Hiện tại)<?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-navy" aria-label="Xem đề xuất phân công">
                            <i class="bi bi-search me-1" aria-hidden="true"></i>Xem
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <i class="bi bi-person-check me-2" aria-hidden="true"></i>Đề xuất Phân công Giảng viên
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Mã lớp</th>
                            <th>Môn học</th>
                            <th>GV đề xuất</th>
                            <th>GV chính thức</th>
                            <th>Trạng thái</th>
                            <th>Lý do từ chối</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($assignProposals)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3 d-block mb-2" aria-hidden="true"></i>
                                Không có lớp học phần nào trong học kỳ này.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($assignProposals as $p): ?>
                        <?php $psb = $propStatusBadgeMap[$p['proposal_status']] ?? ['light', 'Chưa đề xuất']; ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($p['section_code']); ?></code></td>
                            <td><?php echo htmlspecialchars($p['subject_name']); ?></td>
                            <td>
                                <?php if ($p['proposed_teacher_name']): ?>
                                <?php echo htmlspecialchars($p['proposed_teacher_name']); ?>
                                <small class="text-muted">(<?php echo htmlspecialchars($p['proposed_teacher_code']); ?>)</small>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($p['assigned_teacher_name']): ?>
                                <?php echo htmlspecialchars($p['assigned_teacher_name']); ?>
                                <?php else: ?>
                                <span class="text-muted">Chưa phân công</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $psb[0]; ?>"><?php echo $psb[1]; ?></span>
                            </td>
                            <td class="text-muted small">
                                <?php echo htmlspecialchars($p['proposal_reject_reason'] ?? '—'); ?>
                            </td>
                            <td>
                                <?php if (isFacultyManager() && $facultyProposalWindow['ok']): ?>
                                <?php if ($p['proposal_status'] === 'approved'): ?>
                                <span class="text-muted small">Đã duyệt</span>
                                <?php elseif ($p['proposal_status'] === 'pending'): ?>
                                <form method="post" action="proposals.php" class="d-inline"
                                      onsubmit="return confirm('Hủy đề xuất phân công này?')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="cancel_teacher_proposal">
                                    <input type="hidden" name="section_id" value="<?php echo (int)$p['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                            aria-label="Hủy đề xuất phân công lớp <?php echo htmlspecialchars($p['section_code']); ?>">
                                        <i class="bi bi-x-circle" aria-hidden="true"></i>
                                    </button>
                                </form>
                                <?php else: ?>
                                <button class="btn btn-sm btn-outline-navy"
                                        data-bs-toggle="modal" data-bs-target="#propTeacherModal"
                                        data-section-id="<?php echo (int)$p['id']; ?>"
                                        data-section-code="<?php echo htmlspecialchars($p['section_code']); ?>"
                                        aria-label="Đề xuất giảng viên cho lớp <?php echo htmlspecialchars($p['section_code']); ?>">
                                    <i class="bi bi-person-plus" aria-hidden="true"></i>
                                </button>
                                <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <div class="admin-footer">
        &copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một
    </div>
</div>

<?php if (isFacultyManager()): ?>
<!-- Create Draft Modal -->
<div class="modal fade" id="createDraftModal" tabindex="-1" aria-labelledby="createDraftModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="post" action="proposals.php">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="batch_create_drafts">
                <input type="hidden" name="semester_id" value="<?php echo (int)$selectedSemId; ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="createDraftModalLabel">Lập đề xuất mở lớp theo kế hoạch đào tạo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small">
                        Hệ thống tự chọn các môn còn cần mở theo CTĐT, học kỳ và khóa/ngành. Khoa/Viện có thể sửa số lớp, sĩ số, lịch, phòng và giảng viên trước khi gửi; hệ thống sẽ cảnh báo nếu môn đã có lớp/đề xuất trong học kỳ này.
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-5">
                            <label for="plan_cohort_filter" class="form-label">Khóa/ngành/lớp</label>
                            <select id="plan_cohort_filter" class="form-select">
                                <option value="">Tất cả khóa/ngành</option>
                                <?php foreach ($facultyCohorts as $cohort): ?>
                                <option value="<?php echo (int)$cohort['id']; ?>">
                                    <?php echo htmlspecialchars($cohort['cohort_code'] . ' - ' . $cohort['major_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Học kỳ</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars(($selectedSemester['semester_name'] ?? '') . ' ' . ($selectedSemester['school_year'] ?? '')); ?>" readonly>
                        </div>
                        <div class="col-md-3">
                            <label for="plan_search" class="form-label">Tìm kiếm</label>
                            <input type="search" id="plan_search" class="form-control" placeholder="Môn, mã môn, ngành...">
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                        <button type="button" class="btn btn-sm btn-outline-navy" id="plan_select_all">
                            <i class="bi bi-check2-square me-1" aria-hidden="true"></i>Chọn tất cả
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="plan_clear_all">
                            <i class="bi bi-square me-1" aria-hidden="true"></i>Bỏ chọn tất cả
                        </button>
                        <span class="text-muted small" id="plan_filter_count"></span>
                    </div>
                        <?php if (empty($facultySubjects)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        Học kỳ đang chọn chưa có môn phù hợp với CTĐT và khóa tuyển sinh.
                    </div>
                        <?php else: ?>
                    <?php if (!empty($facultySubjectsTruncated)): ?>
                    <div class="alert alert-info py-2 small">
                        Đang hiển thị <?php echo count($facultySubjects); ?> dòng đầu tiên để trang tải nhanh; học kỳ này còn thêm dữ liệu phù hợp khác. Dùng bộ lọc khóa/ngành hoặc ô tìm kiếm trong danh sách hiện tại.
                    </div>
                    <?php endif; ?>
                    <div class="table-responsive" style="max-height:55vh;">
                        <table class="table table-sm align-middle" style="min-width:1380px;">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="width:36px;"></th>
                                    <th>Môn cần mở</th>
                                    <th>Khóa/ngành</th>
                                    <th>Tín chỉ</th>
                                    <th>Loại</th>
                                    <th>Tiên quyết</th>
                                    <th>Dự kiến SV</th>
                                    <th style="width:110px;">Số lớp</th>
                                    <th style="width:120px;">Sĩ số/lớp</th>
                                    <th style="min-width:150px;">Lịch đề xuất</th>
                                    <th style="min-width:150px;">Phòng đề xuất</th>
                                    <th style="min-width:170px;">GV đề xuất</th>
                                    <th style="min-width:150px;">Kiểm tra</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($facultySubjects as $subj): ?>
                                <?php
                                    $key = (int)$subj['subject_id'] . ':' . (int)$subj['cohort_id'];
                                    $estimated = (int)($subj['estimated_students'] ?? 30);
                                    $defaultClassCount = (int)($subj['default_class_count'] ?? max(1, (int)ceil($estimated / 60)));
                                    $defaultCapacity = (int)($subj['default_capacity'] ?? max(1, (int)ceil($estimated / $defaultClassCount)));
                                    $recommendation = $subj['recommendation'] ?? [];
                                    $scheduleOptions = $subj['schedule_options'] ?? [];
                                    $roomOptions = $subj['room_options'] ?? [];
                                    $roomOptionsBySchedule = $subj['room_options_by_schedule'] ?? [];
                                    $subjectLoad = $subj['subject_load'] ?? ['total_periods' => ((int)$subj['credits'] * 15)];
                                    $eligibleTeachers = $subj['eligible_teachers'] ?? [];
                                    $existingOpening = $subj['existing_openings'] ?? ['count' => 0, 'codes' => []];
                                    $existingCount = (int)($existingOpening['count'] ?? 0);
                                    $existingCodes = $existingOpening['codes'] ?? [];
                                    $hasRoomSuggestion = !empty($recommendation['room']);
                                    $autoChecked = $existingCount < $defaultClassCount && $hasRoomSuggestion;
                                ?>
                                <tr class="plan-row"
                                    data-cohort-id="<?php echo (int)$subj['cohort_id']; ?>"
                                    data-existing-count="<?php echo $existingCount; ?>"
                                    data-default-class-count="<?php echo $defaultClassCount; ?>"
                                    data-estimated-students="<?php echo $estimated; ?>"
                                    data-room-options="<?php echo htmlspecialchars(json_encode($roomOptionsBySchedule, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)); ?>"
                                    data-search-text="<?php echo htmlspecialchars(mb_strtolower($subj['subject_code'] . ' ' . $subj['subject_name'] . ' ' . $subj['cohort_code'] . ' ' . $subj['major_name'] . ' ' . $subj['enrollment_year'] . '-' . $subj['graduation_year'], 'UTF-8')); ?>"
                                    data-subject-label="<?php echo htmlspecialchars($subj['subject_code'] . ' - ' . $subj['subject_name']); ?>">
                                    <td>
                                        <input class="form-check-input plan-check" type="checkbox"
                                               name="selected[<?php echo htmlspecialchars($key); ?>]" value="1"
                                               <?php echo $autoChecked ? 'checked' : ''; ?>>
                                    </td>
                                    <td>
                                        <div class="fw-semibold small"><?php echo htmlspecialchars($subj['subject_code'] . ' - ' . $subj['subject_name']); ?></div>
                                        <small class="text-muted">Học kỳ CTĐT: <?php echo (int)$subj['suggested_semester']; ?></small>
                                    </td>
                                    <td class="small">
                                        <?php echo htmlspecialchars($subj['cohort_code'] . ' - ' . $subj['major_name']); ?><br>
                                        <span class="text-muted">Khóa <?php echo (int)$subj['enrollment_year']; ?>-<?php echo (int)$subj['graduation_year']; ?></span>
                                    </td>
                                    <td><span class="badge bg-light text-dark"><?php echo (int)$subj['credits']; ?></span></td>
                                    <td class="small"><?php echo htmlspecialchars($subj['subject_type'] ?? 'Bắt buộc'); ?></td>
                                    <td class="small text-muted"><?php echo htmlspecialchars($subj['prerequisite_ids'] ?: 'Không'); ?></td>
                                    <td class="text-center"><?php echo $estimated; ?></td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm plan-class-count"
                                               name="class_count[<?php echo htmlspecialchars($key); ?>]" min="1" max="10"
                                               value="<?php echo max($defaultClassCount, $existingCount); ?>"
                                               style="min-width:74px;">
                                        <?php if ($existingCount > 0): ?>
                                        <small class="text-muted d-block mt-1">Đã có <?php echo $existingCount; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm plan-capacity"
                                               name="max_students[<?php echo htmlspecialchars($key); ?>]" min="1"
                                               value="<?php echo $defaultCapacity; ?>"
                                               style="min-width:82px;">
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm plan-schedule"
                                                name="day_sessions[<?php echo htmlspecialchars($key); ?>]"
                                                style="min-width:210px;">
                                            <?php foreach ($scheduleOptions as $scheduleOption): ?>
                                            <?php $isSelectedSchedule = (string)($recommendation['day_sessions'] ?? '') === (string)$scheduleOption['value']; ?>
                                            <option value="<?php echo htmlspecialchars($scheduleOption['value']); ?>"
                                                    <?php echo $isSelectedSchedule ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($scheduleOption['label']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted d-block mt-1">
                                            <?php echo (int)($subjectLoad['total_periods'] ?? 0); ?> tiết ·
                                            <?php echo (int)($scheduleOptions[0]['sessions_per_week'] ?? 1); ?> buổi/tuần
                                        </small>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm plan-room"
                                                name="room[<?php echo htmlspecialchars($key); ?>]"
                                                style="min-width:180px;">
                                            <option value="">Chọn phòng từ CSDL</option>
                                            <?php foreach ($roomOptions as $roomOption): ?>
                                            <?php
                                                $isSelectedRoom = (string)($recommendation['room'] ?? '') === (string)$roomOption['room_code'];
                                                $isAvailableRoom = !empty($roomOption['available']);
                                            ?>
                                            <option value="<?php echo htmlspecialchars($roomOption['room_code']); ?>"
                                                    data-capacity="<?php echo (int)$roomOption['capacity']; ?>"
                                                    data-available="<?php echo $isAvailableRoom ? '1' : '0'; ?>"
                                                    data-reason="<?php echo htmlspecialchars($roomOption['reason'] ?? ''); ?>"
                                                    data-label-base="<?php echo htmlspecialchars($roomOption['label']); ?>"
                                                    <?php echo $isSelectedRoom && $isAvailableRoom ? 'selected' : ''; ?>
                                                    <?php echo !$isAvailableRoom ? 'disabled' : ''; ?>>
                                                <?php
                                                    $statusLabel = $isAvailableRoom ? 'Trống' : (string)$roomOption['reason'];
                                                    echo htmlspecialchars($roomOption['label'] . ' - ' . $statusLabel);
                                                ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted d-block mt-1">
                                            <?php echo htmlspecialchars($recommendation['room_label'] ?? 'Chưa có phòng trống phù hợp'); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" name="proposed_teacher_id[<?php echo htmlspecialchars($key); ?>]">
                                            <option value="0">Chưa đề xuất</option>
                                            <?php foreach ($eligibleTeachers as $teacherIndex => $t): ?>
                                            <option value="<?php echo (int)$t['id']; ?>" <?php echo $teacherIndex === 0 ? 'selected' : ''; ?>>
                                                <?php
                                                    $sourceLabel = ($t['source'] ?? '') === 'approved_wish'
                                                        ? 'Ưu tiên: nguyện vọng đã duyệt'
                                                        : 'Hệ thống đề xuất';
                                                    echo htmlspecialchars($t['full_name'] . ' (' . $t['teacher_code'] . ') - ' . $sourceLabel);
                                                ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (empty($eligibleTeachers)): ?>
                                        <small class="text-danger d-block mt-1">Chưa có giảng viên phù hợp để hệ thống đề xuất.</small>
                                        <?php endif; ?>
                                        <input type="hidden" name="open_proposal_note[<?php echo htmlspecialchars($key); ?>]" value="Đề xuất theo kế hoạch đào tạo">
                                    </td>
                                    <td class="small plan-duplicate-cell">
                                        <?php if ($existingCount > 0): ?>
                                            <span class="badge bg-warning text-dark">Có dữ liệu trùng</span>
                                            <div class="text-muted mt-1">
                                                <?php echo htmlspecialchars(implode(', ', array_slice($existingCodes, 0, 3))); ?>
                                                <?php echo count($existingCodes) > 3 ? '...' : ''; ?>
                                            </div>
                                        <?php elseif (!$hasRoomSuggestion): ?>
                                            <span class="badge bg-danger">Chưa có phòng</span>
                                            <div class="text-muted mt-1">Hãy sửa lịch/phòng hoặc bỏ chọn.</div>
                                        <?php else: ?>
                                            <span class="badge bg-success">Có thể gửi</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                        <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-navy" <?php echo empty($facultySubjects) ? 'disabled' : ''; ?>>
                        <i class="bi bi-send me-1" aria-hidden="true"></i>Gửi đề xuất hàng loạt
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Propose Teacher Modal -->
<div class="modal fade" id="propTeacherModal" tabindex="-1" aria-labelledby="propTeacherModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="proposals.php">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="propose_teacher">
                <input type="hidden" name="section_id" id="pt_section_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="propTeacherModalLabel">Đề xuất Phân công Giảng viên</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted" id="pt_section_code"></p>
                    <div class="mb-3">
                        <label for="pt_teacher" class="form-label">Giảng viên <span class="text-danger">*</span></label>
                        <select id="pt_teacher" name="proposed_teacher_id" class="form-select" required>
                            <option value="">-- Chọn giảng viên --</option>
                            <?php foreach ($facultyTeachers as $t): ?>
                            <option value="<?php echo (int)$t['id']; ?>">
                                <?php echo htmlspecialchars($t['full_name'] . ' (' . $t['teacher_code'] . ') — ' . $t['current_load'] . ' TC hiện tại'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="pt_note" class="form-label">Ghi chú</label>
                        <textarea id="pt_note" name="proposal_note" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-navy">
                        <i class="bi bi-send me-1" aria-hidden="true"></i>Gửi đề xuất
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function normalizePlanText(value) {
    return (value || '').toString().toLocaleLowerCase('vi-VN').trim();
}

function refreshPlanFilters() {
    const cohortId = document.getElementById('plan_cohort_filter')?.value || '';
    const query = normalizePlanText(document.getElementById('plan_search')?.value || '');
    let visible = 0;
    let selected = 0;

    document.querySelectorAll('.plan-row').forEach(function(row) {
        const cohortMatch = !cohortId || row.dataset.cohortId === cohortId;
        const searchMatch = !query || normalizePlanText(row.dataset.searchText || '').includes(query);
        const isVisible = cohortMatch && searchMatch;
        row.style.display = isVisible ? '' : 'none';
        if (isVisible) {
            visible++;
            if (row.querySelector('.plan-check')?.checked) {
                selected++;
            }
        }
    });

    const counter = document.getElementById('plan_filter_count');
    if (counter) {
        counter.textContent = visible + ' dòng đang hiển thị' + (visible ? ' · ' + selected + ' đã chọn' : '');
    }
}

document.getElementById('plan_cohort_filter')?.addEventListener('change', refreshPlanFilters);
document.getElementById('plan_search')?.addEventListener('input', refreshPlanFilters);

document.getElementById('plan_select_all')?.addEventListener('click', function() {
    document.querySelectorAll('.plan-row').forEach(function(row) {
        if (row.style.display !== 'none') {
            const checkbox = row.querySelector('.plan-check');
            if (checkbox) checkbox.checked = true;
        }
    });
    refreshPlanFilters();
});

document.getElementById('plan_clear_all')?.addEventListener('click', function() {
    document.querySelectorAll('.plan-row').forEach(function(row) {
        if (row.style.display !== 'none') {
            const checkbox = row.querySelector('.plan-check');
            if (checkbox) checkbox.checked = false;
        }
    });
    refreshPlanFilters();
});

document.querySelectorAll('.plan-check').forEach(function(checkbox) {
    checkbox.addEventListener('change', refreshPlanFilters);
});

document.querySelector('#createDraftModal form')?.addEventListener('submit', function(e) {
    const checkedRows = Array.from(this.querySelectorAll('.plan-check:checked'))
        .map(function(input) { return input.closest('.plan-row'); })
        .filter(Boolean);
    const checked = checkedRows.length;
    if (checked === 0) {
        e.preventDefault();
        alert('Vui lòng chọn ít nhất một môn cần mở.');
        return;
    }

    const duplicates = [];
    const missingSchedules = [];
    const missingRooms = [];
    const invalidRooms = [];
    checkedRows.forEach(function(row) {
        const existingCount = Number(row.dataset.existingCount || 0);
        const classCount = Number(row.querySelector('.plan-class-count')?.value || 0);
        const capacity = Number(row.querySelector('.plan-capacity')?.value || 0);
        const schedule = row.querySelector('[name^="day_sessions"]')?.value.trim() || '';
        const roomSelect = row.querySelector('.plan-room');
        const selectedRoom = roomSelect?.selectedOptions?.[0] || null;
        if (existingCount > 0 && classCount <= existingCount) {
            duplicates.push(row.dataset.subjectLabel + ' đã có ' + existingCount + ' lớp/đề xuất');
        }
        if (!schedule) {
            missingSchedules.push(row.dataset.subjectLabel);
        }
        if (!roomSelect?.value) {
            missingRooms.push(row.dataset.subjectLabel);
        } else if (!selectedRoom || selectedRoom.disabled || Number(selectedRoom.dataset.capacity || 0) < capacity) {
            invalidRooms.push(row.dataset.subjectLabel + ' - phòng không còn phù hợp');
        }
    });

    if (duplicates.length > 0) {
        e.preventDefault();
        alert('Một số môn đang bị trùng hoặc đã đủ số lớp:\n- ' + duplicates.slice(0, 8).join('\n- ') + '\n\nHãy tăng "Số lớp" nếu muốn mở thêm lớp, hoặc bỏ chọn dòng đó.');
        return;
    }

    if (missingSchedules.length > 0) {
        e.preventDefault();
        alert('Vui lòng nhập lịch đề xuất cho:\n- ' + missingSchedules.slice(0, 8).join('\n- '));
        return;
    }

    if (missingRooms.length > 0) {
        e.preventDefault();
        alert('Vui lòng chọn phòng học từ danh mục CSDL cho:\n- ' + missingRooms.slice(0, 8).join('\n- '));
        return;
    }

    if (invalidRooms.length > 0) {
        e.preventDefault();
        alert('Một số phòng đang trùng lịch hoặc không đủ sức chứa:\n- ' + invalidRooms.slice(0, 8).join('\n- '));
    }
});

function refreshPlanRowState(row) {
    const existingCount = Number(row.dataset.existingCount || 0);
    const classCount = Number(row.querySelector('.plan-class-count')?.value || 0);
    const cell = row.querySelector('.plan-duplicate-cell');
    if (!cell || existingCount <= 0) {
        return;
    }
    if (classCount <= existingCount) {
        cell.querySelector('.badge')?.classList.remove('bg-success');
        cell.querySelector('.badge')?.classList.add('bg-warning', 'text-dark');
        cell.querySelector('.badge').textContent = 'Có dữ liệu trùng';
    } else {
        cell.querySelector('.badge')?.classList.remove('bg-warning', 'text-dark');
        cell.querySelector('.badge')?.classList.add('bg-success');
        cell.querySelector('.badge').textContent = 'Mở thêm ' + (classCount - existingCount) + ' lớp';
    }
}

function syncPlanCapacityFromClassCount(row) {
    const estimated = Number(row.dataset.estimatedStudents || 0);
    const classInput = row.querySelector('.plan-class-count');
    const capacityInput = row.querySelector('.plan-capacity');
    const classCount = Math.max(1, Number(classInput?.value || 1));
    if (!capacityInput || estimated <= 0) {
        return;
    }
    capacityInput.value = Math.max(1, Math.ceil(estimated / classCount));
}

function syncPlanClassCountFromCapacity(row) {
    const estimated = Number(row.dataset.estimatedStudents || 0);
    const existingCount = Number(row.dataset.existingCount || 0);
    const classInput = row.querySelector('.plan-class-count');
    const capacityInput = row.querySelector('.plan-capacity');
    const capacity = Math.max(1, Number(capacityInput?.value || 1));
    if (!classInput || estimated <= 0) {
        return;
    }
    const suggestedClassCount = Math.max(1, Math.ceil(estimated / capacity), existingCount);
    classInput.value = Math.min(10, suggestedClassCount);
}

function refreshPlanRoomOptions(row) {
    const capacity = Number(row.querySelector('.plan-capacity')?.value || 0);
    const roomSelect = row.querySelector('.plan-room');
    if (!roomSelect) {
        return;
    }

    const currentValue = roomSelect.value;
    const schedule = row.querySelector('.plan-schedule')?.value || '';
    let optionsBySchedule = {};
    try {
        optionsBySchedule = JSON.parse(row.dataset.roomOptions || '{}');
    } catch (_err) {
        optionsBySchedule = {};
    }

    if (optionsBySchedule[schedule]) {
        roomSelect.innerHTML = '<option value="">Chọn phòng từ CSDL</option>';
        optionsBySchedule[schedule].forEach(function(room) {
            const option = document.createElement('option');
            option.value = room.room_code || '';
            option.dataset.capacity = String(room.capacity || 0);
            option.dataset.available = room.available ? '1' : '0';
            option.dataset.reason = room.reason || '';
            option.dataset.labelBase = room.label || option.value;
            roomSelect.appendChild(option);
        });
    }

    let firstAvailable = '';
    Array.from(roomSelect.options).forEach(function(option) {
        if (!option.value) return;
        const roomCapacity = Number(option.dataset.capacity || 0);
        const reason = option.dataset.reason || '';
        const fixedUnavailable = reason && reason !== 'Không đủ sức chứa';
        const tooSmall = roomCapacity < capacity;
        option.disabled = fixedUnavailable || tooSmall;

        const labelBase = option.dataset.labelBase || option.textContent;
        const status = fixedUnavailable ? reason : (tooSmall ? 'Không đủ sức chứa' : 'Trống');
        option.textContent = labelBase + ' - ' + status;

        if (!option.disabled && !firstAvailable) {
            firstAvailable = option.value;
        }
    });

    if (currentValue && Array.from(roomSelect.options).some(function(option) {
        return option.value === currentValue && !option.disabled;
    })) {
        roomSelect.value = currentValue;
    } else if (roomSelect.value && roomSelect.selectedOptions[0]?.disabled) {
        roomSelect.value = firstAvailable;
    } else if (!roomSelect.value && firstAvailable) {
        roomSelect.value = firstAvailable;
    }
}

document.querySelectorAll('.plan-row').forEach(function(row) {
    refreshPlanRoomOptions(row);
    refreshPlanRowState(row);
    row.querySelector('.plan-class-count')?.addEventListener('input', function() {
        syncPlanCapacityFromClassCount(row);
        refreshPlanRoomOptions(row);
        refreshPlanRowState(row);
    });
    row.querySelector('.plan-capacity')?.addEventListener('input', function() {
        syncPlanClassCountFromCapacity(row);
        refreshPlanRoomOptions(row);
        refreshPlanRowState(row);
    });
    row.querySelector('.plan-schedule')?.addEventListener('change', function() {
        refreshPlanRoomOptions(row);
    });
});
refreshPlanFilters();

document.getElementById('propTeacherModal').addEventListener('show.bs.modal', function(e) {
    var btn = e.relatedTarget;
    document.getElementById('pt_section_id').value = btn.dataset.sectionId;
    document.getElementById('pt_section_code').textContent = 'Lớp: ' + btn.dataset.sectionCode;
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
