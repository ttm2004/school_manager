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

function facultyProposalAdminClassesForCohort(mysqli $conn, int $cohortId, string $dataMode): array
{
    $stmt = $conn->prepare(
        "SELECT c.id, c.class_code, c.class_name, COUNT(st.id) AS student_count
         FROM training_cohorts tc
         JOIN classes c
           ON c.major_id = tc.major_id
          AND c.enrollment_year = tc.enrollment_year
          AND (c.cohort_id IS NULL OR c.cohort_id = tc.id)
          AND COALESCE(c.data_mode, 'system') = ?
         LEFT JOIN students st ON st.class_id = c.id
         WHERE tc.id = ?
         GROUP BY c.id, c.class_code, c.class_name
         ORDER BY c.class_code, c.id"
    );
    $stmt->bind_param('si', $dataMode, $cohortId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $classesWithStudents = array_values(array_filter(
        $rows,
        static fn(array $row): bool => (int)($row['student_count'] ?? 0) > 0
    ));

    return !empty($classesWithStudents) ? $classesWithStudents : $rows;
}

function facultyProposalNextSectionCode(mysqli $conn, string $subjectCode, int $semesterId): string
{
    $prefix = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $subjectCode));
    $prefix = $prefix !== '' ? $prefix : 'LHP';
    $stmt = $conn->prepare(
        "SELECT section_code FROM course_sections
         WHERE section_code LIKE ?
         ORDER BY LENGTH(section_code) DESC, section_code DESC"
    );
    $like = $prefix . '.%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $next = 1;
    foreach ($rows as $row) {
        if (!empty($row['section_code']) && preg_match('/^' . preg_quote($prefix, '/') . '\.(\d+)$/', (string)$row['section_code'], $matches)) {
            $next = max($next, (int)$matches[1] + 1);
        }
    }

    return $prefix . '.' . str_pad((string)$next, 2, '0', STR_PAD_LEFT);
}

function facultyProposalDateRangesOverlap(?string $startA, ?string $endA, ?string $startB, ?string $endB): bool
{
    if (!$startA || !$endA || !$startB || !$endB) {
        return true;
    }
    $aStart = strtotime($startA . ' 00:00:00');
    $aEnd = strtotime($endA . ' 23:59:59');
    $bStart = strtotime($startB . ' 00:00:00');
    $bEnd = strtotime($endB . ' 23:59:59');
    if (!$aStart || !$aEnd || !$bStart || !$bEnd) {
        return true;
    }
    return $aStart <= $bEnd && $bStart <= $aEnd;
}

function facultyProposalRoomScheduleConflict(mysqli $conn, string $roomCode, int $semesterId, ?string $daySessions, int $excludeSectionId = 0, ?string $startDate = null, ?string $endDate = null): bool
{
    if ($roomCode === '') {
        return false;
    }

    $tokens = academicPolicyScheduleTokens($daySessions);
    if (empty($tokens)) {
        return false;
    }
    $demoContext = academicPolicySemesterDemoContext($conn, $semesterId);
    if (($demoContext['data_mode'] ?? 'system') === 'test') {
        return false;
    }
    $modeSql = academicPolicyColumnExists($conn, 'course_sections', 'data_mode')
        ? " AND COALESCE(data_mode, 'system') = ?"
        : "";

    $sql =
        "SELECT day_sessions, start_date, end_date
         FROM course_sections
         WHERE semester_id = ?
           AND room = ?
           AND status IN ('draft','proposed','open','full','closed')
           AND day_sessions IS NOT NULL
           AND day_sessions <> ''
           $modeSql";
    if ($excludeSectionId > 0) {
        $sql .= " AND id <> ?";
    }
    $stmt = $conn->prepare($sql);
    if ($modeSql && $excludeSectionId > 0) {
        $stmt->bind_param('issi', $semesterId, $roomCode, $demoContext['data_mode'], $excludeSectionId);
    } elseif ($modeSql) {
        $stmt->bind_param('iss', $semesterId, $roomCode, $demoContext['data_mode']);
    } elseif ($excludeSectionId > 0) {
        $stmt->bind_param('isi', $semesterId, $roomCode, $excludeSectionId);
    } else {
        $stmt->bind_param('is', $semesterId, $roomCode);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $row) {
        if (academicPolicyScheduleWindowsOverlap($daySessions, $startDate, $endDate, $row['day_sessions'] ?? '', $row['start_date'] ?? null, $row['end_date'] ?? null)) {
            return true;
        }
    }

    return false;
}

function facultyProposalTeacherScheduleConflict(mysqli $conn, int $teacherId, int $semesterId, ?string $daySessions, int $excludeSectionId = 0, ?string $startDate = null, ?string $endDate = null): bool
{
    if ($teacherId <= 0) {
        return false;
    }
    $tokens = academicPolicyScheduleTokens($daySessions);
    if (empty($tokens)) {
        return false;
    }
    $demoContext = academicPolicySemesterDemoContext($conn, $semesterId);
    if (($demoContext['data_mode'] ?? 'system') === 'test') {
        return false;
    }
    $modeSql = academicPolicyColumnExists($conn, 'course_sections', 'data_mode')
        ? " AND COALESCE(data_mode, 'system') = ?"
        : "";
    $stmt = $conn->prepare(
        "SELECT id, day_sessions, start_date, end_date
         FROM course_sections
         WHERE id <> ?
           AND semester_id = ?
           AND status IN ('draft','proposed','open','full','closed')
           AND (teacher_id = ? OR proposed_teacher_id = ?)
           AND day_sessions IS NOT NULL
           AND day_sessions <> ''
           $modeSql"
    );
    if ($modeSql) {
        $stmt->bind_param('iiiis', $excludeSectionId, $semesterId, $teacherId, $teacherId, $demoContext['data_mode']);
    } else {
        $stmt->bind_param('iiii', $excludeSectionId, $semesterId, $teacherId, $teacherId);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $row) {
        if (academicPolicyScheduleWindowsOverlap($daySessions, $startDate, $endDate, $row['day_sessions'] ?? '', $row['start_date'] ?? null, $row['end_date'] ?? null)) {
            return true;
        }
    }

    return false;
}

function facultyProposalPlannedScheduleConflict(array $plannedItems, string $daySessions, ?string $startDate, ?string $endDate): bool
{
    foreach ($plannedItems as $item) {
        if (academicPolicyScheduleWindowsOverlap(
            $daySessions,
            $startDate,
            $endDate,
            (string)($item['day_sessions'] ?? ''),
            $item['start_date'] ?? null,
            $item['end_date'] ?? null
        )) {
            return true;
        }
    }

    return false;
}

function facultyProposalPlannedGroupConflict(array $plannedGroups, ?int $classId, int $cohortId, string $daySessions, ?string $startDate, ?string $endDate): bool
{
    foreach ($plannedGroups as $item) {
        $sameGroup = $classId
            ? ((int)($item['class_id'] ?? 0) === $classId)
            : (!$item['class_id'] && (int)($item['cohort_id'] ?? 0) === $cohortId);
        if ($sameGroup && academicPolicyScheduleWindowsOverlap(
            $daySessions,
            $startDate,
            $endDate,
            (string)($item['day_sessions'] ?? ''),
            $item['start_date'] ?? null,
            $item['end_date'] ?? null
        )) {
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

function facultyProposalSemesterWindow(array $semester): ?array
{
    if (!empty($semester['start_date']) && !empty($semester['end_date'])) {
        return ['start_date' => $semester['start_date'], 'end_date' => $semester['end_date']];
    }
    return academicPolicySemesterWindowFromRow($semester);
}

function facultyProposalSessionsPerWeek(array $semester, array $subjectLoad): int
{
    $periodsPerSession = 5;
    $weeks = facultyProposalSemesterWeeks($semester);
    $totalPeriods = max(1, (int)($subjectLoad['total_periods'] ?? 0));
    $meetings = (int)ceil($totalPeriods / $periodsPerSession);

    return max(1, min(6, (int)ceil($meetings / $weeks)));
}

function facultyProposalCourseWeeks(array $semester, array $subjectLoad): int
{
    $meetings = (int)ceil(max(1, (int)($subjectLoad['total_periods'] ?? 0)) / 5);
    $sessionsPerWeek = facultyProposalSessionsPerWeek($semester, $subjectLoad);
    return max(1, min(facultyProposalSemesterWeeks($semester), (int)ceil($meetings / $sessionsPerWeek)));
}

function facultyProposalMondayOfWeek(string $date): ?DateTimeImmutable
{
    try {
        $dt = new DateTimeImmutable($date);
        return $dt->modify('monday this week');
    } catch (Throwable) {
        return null;
    }
}

function facultyProposalScheduleWindow(array $semester, array $subjectLoad, string $daySessions, int $seed): array
{
    $window = facultyProposalSemesterWindow($semester);
    if (!$window) {
        return ['start_date' => null, 'end_date' => null, 'week_offset' => 0, 'course_weeks' => 1];
    }
    $semesterWeeks = facultyProposalSemesterWeeks($semester);
    $courseWeeks = facultyProposalCourseWeeks($semester, $subjectLoad);
    $maxOffset = max(0, $semesterWeeks - $courseWeeks);
    $offset = $maxOffset > 0 ? abs($seed) % ($maxOffset + 1) : 0;
    $semesterMonday = facultyProposalMondayOfWeek($window['start_date']);
    if (!$semesterMonday) {
        return ['start_date' => $window['start_date'], 'end_date' => $window['end_date'], 'week_offset' => $offset, 'course_weeks' => $courseWeeks];
    }

    $tokens = academicPolicyScheduleTokens($daySessions);
    $firstDay = 8;
    $lastDay = 2;
    foreach ($tokens as $token) {
        [$day] = array_pad(explode(':', $token, 2), 2, '2');
        $dayInt = (int)$day;
        if ($dayInt === 8) $dayInt = 7;
        $firstDay = min($firstDay, max(2, $dayInt));
        $lastDay = max($lastDay, max(2, $dayInt));
    }

    $start = $semesterMonday->modify('+' . ($offset * 7 + ($firstDay - 2)) . ' days');
    $end = $semesterMonday->modify('+' . (($offset + $courseWeeks - 1) * 7 + ($lastDay - 2)) . ' days');
    $semesterStart = new DateTimeImmutable($window['start_date']);
    $semesterEnd = new DateTimeImmutable($window['end_date']);
    if ($start < $semesterStart) $start = $semesterStart;
    if ($end > $semesterEnd) $end = $semesterEnd;

    return [
        'start_date' => $start->format('Y-m-d'),
        'end_date' => $end->format('Y-m-d'),
        'week_offset' => $offset,
        'course_weeks' => $courseWeeks,
    ];
}

function facultyProposalScheduleCandidates(array $semester, array $subjectLoad, int $seed = 0): array
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
    $buildCandidate = static function (array $selectedSlots) use (&$candidates, $sessionsPerWeek, $semester, $subjectLoad, $seed): void {
        $tokens = array_map(static fn(array $slot): string => $slot['token'], $selectedSlots);
        $labels = array_map(static fn(array $slot): string => $slot['label'], $selectedSlots);
        $value = implode(',', $tokens);
        if (isset($candidates[$value])) {
            return;
        }
        $window = facultyProposalScheduleWindow($semester, $subjectLoad, $value, $seed + (int)sprintf('%u', crc32($value)));
        $candidates[$value] = [
            'value' => $value,
            'label' => implode(', ', $labels),
            'sessions_per_week' => $sessionsPerWeek,
            'weeks' => facultyProposalSemesterWeeks($semester),
            'course_weeks' => facultyProposalCourseWeeks($semester, $subjectLoad),
            'total_periods' => (int)($subjectLoad['total_periods'] ?? 0),
            'start_date' => $window['start_date'],
            'end_date' => $window['end_date'],
            'week_offset' => $window['week_offset'],
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
        $rooms = facultyProposalRoomOptions($conn, $semesterId, $subjectId, $maxStudents, $schedule, 0, $candidate['start_date'] ?? null, $candidate['end_date'] ?? null);
        foreach ($rooms as $room) {
            if (empty($room['available'])) {
                continue;
            }
            return [
                'day_sessions' => $schedule,
                'room' => (string)$room['room_code'],
                'classroom_id' => 0,
                'room_label' => $room['room_code'] . ' (' . (int)$room['capacity'] . ' SV)',
                'start_date' => $candidate['start_date'] ?? null,
                'end_date' => $candidate['end_date'] ?? null,
            ];
        }
    }

    return [
        'day_sessions' => (string)($candidates[0]['value'] ?? '2:sang'),
        'room' => '',
        'classroom_id' => 0,
        'room_label' => 'Chưa có phòng trống phù hợp',
        'start_date' => $candidates[0]['start_date'] ?? null,
        'end_date' => $candidates[0]['end_date'] ?? null,
    ];
}

function facultyProposalRoomOptions(mysqli $conn, int $semesterId, int $subjectId, int $maxStudents, string $daySessions, int $excludeSectionId = 0, ?string $startDate = null, ?string $endDate = null): array
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
        } elseif (facultyProposalRoomScheduleConflict($conn, (string)$room['room_code'], $semesterId, $daySessions, $excludeSectionId, $startDate, $endDate)) {
            $reason = 'Trùng lịch';
        }

        $options[] = [
            'id' => (int)$room['id'],
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
    $teachers = [];
    $seen = [];
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
        $wishTeachers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($wishTeachers as $teacher) {
            $tid = (int)$teacher['id'];
            $teachers[] = $teacher;
            $seen[$tid] = true;
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
         LIMIT 20"
    );
    $subjectLike = '%' . (string)$subject['subject_name'] . '%';
    $majorLike = '%' . (string)$subject['major_name'] . '%';
    $stmt->bind_param('ssiiii', $subjectLike, $majorLike, $semesterId, $facultyId, $subjectId, $semesterId);
    $stmt->execute();
    $fallbackTeachers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($fallbackTeachers as $teacher) {
        $tid = (int)$teacher['id'];
        if (isset($seen[$tid])) {
            continue;
        }
        $teachers[] = $teacher;
        $seen[$tid] = true;
    }

    return $teachers;
}

function facultyProposalValidateTeacherFromFaculty(mysqli $conn, int $teacherId, int $facultyId, int $subjectId, int $semesterId): array
{
    if ($teacherId <= 0) {
        return ['ok' => true, 'message' => ''];
    }

    $stmt = $conn->prepare(
        "SELECT t.id, u.status
         FROM teachers t
         JOIN users u ON u.id = t.user_id
         WHERE t.id = ? AND t.faculty_id = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Không kiểm tra được thông tin giảng viên.'];
    }
    $stmt->bind_param('ii', $teacherId, $facultyId);
    $stmt->execute();
    $teacher = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$teacher || (int)$teacher['status'] !== 1) {
        return ['ok' => false, 'message' => 'Giảng viên không thuộc khoa đang đề xuất hoặc tài khoản đã bị khóa.'];
    }

    $wishTable = $conn->query("SHOW TABLES LIKE 'teaching_wishes'");
    if ($wishTable && $wishTable->num_rows > 0) {
        $stmtWish = $conn->prepare(
            "SELECT status
             FROM teaching_wishes
             WHERE teacher_id = ?
               AND subject_id = ?
               AND semester_id = ?
               AND faculty_id = ?
               AND status IN ('faculty_rejected','dept_rejected','cancelled')
             LIMIT 1"
        );
        if (!$stmtWish) {
            return ['ok' => false, 'message' => 'Không kiểm tra được nguyện vọng giảng dạy của giảng viên.'];
        }
        $stmtWish->bind_param('iiii', $teacherId, $subjectId, $semesterId, $facultyId);
        $stmtWish->execute();
        $badWish = $stmtWish->get_result()->fetch_assoc();
        $stmtWish->close();
        if ($badWish) {
            return ['ok' => false, 'message' => 'Nguyện vọng giảng dạy của giảng viên cho môn này đã bị từ chối hoặc đã hủy.'];
        }
    }

    return ['ok' => true, 'message' => ''];
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

        if ($subjectId <= 0 || $semesterId <= 0 || $cohortId <= 0 || $expStudents <= 0 || $sectionName === '') {
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
        $plan = academicPolicyPlanSectionOpening($conn, [
            'id' => 0,
            'subject_id' => $subjectId,
            'semester_id' => $semesterId,
            'target_cohort_id' => $cohortId,
            'section_code' => $sectionName,
            'data_mode' => $demoContext['data_mode'],
        ], $expStudents, 'offline', $daySessions, '');
        if (!$plan['ok']) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => $plan['message']];
            header('Location: proposals.php?tab=open');
            exit();
        }
        $daySessions = (string)$plan['day_sessions'];
        $startDate = (string)$plan['start_date'];
        $endDate = (string)$plan['end_date'];
        $room = (string)$plan['room'];
        $classroomId = $plan['classroom_id'];
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
            "INSERT INTO course_sections (subject_id, semester_id, target_cohort_id, section_code, status, data_mode, demo_batch_id, expected_students, max_students, day_sessions, start_date, end_date, room, classroom_id, teaching_mode, open_proposal_note, open_proposed_by)
             VALUES (?, ?, ?, ?, 'draft', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'offline', ?, ?)"
        );
        $stmtIns->bind_param('iiisssiissssisi', $subjectId, $semesterId, $cohortId, $sectionName, $demoContext['data_mode'], $demoContext['demo_batch_id'], $expStudents, $expStudents, $daySessions, $startDate, $endDate, $room, $classroomId, $note, $userId);
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
        $cohortFilterId = (int)($_POST['cohort_filter'] ?? 0);
        $selected = $_POST['selected'] ?? [];
        $classCounts = $_POST['class_count'] ?? [];
        $adminClassIdsByKey = $_POST['admin_class_ids'] ?? [];
        $maxStudents = $_POST['max_students'] ?? [];
        $daySessions = $_POST['day_sessions'] ?? [];
        $startDates = $_POST['start_date'] ?? [];
        $endDates = $_POST['end_date'] ?? [];
        $roomCodes = $_POST['room'] ?? [];
        $teacherIds = $_POST['proposed_teacher_id'] ?? [];
        $notes = $_POST['open_proposal_note'] ?? [];

        if ($semesterId <= 0 || empty($selected) || !is_array($selected)) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Vui lòng chọn học kỳ và ít nhất một môn cần mở.'];
            header('Location: proposals.php?tab=open&semester_id=' . $semesterId . '&cohort_id=' . $cohortFilterId);
            exit();
        }
        facultyProposalWindowOrRedirect($conn, $semesterId, 'proposals.php?tab=open&semester_id=' . $semesterId);
        try {
            facultyProposalEnsureNullableTeacherId($conn);
        } catch (Throwable $e) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Lỗi: ' . $e->getMessage()];
            header('Location: proposals.php?tab=open&semester_id=' . $semesterId . '&cohort_id=' . $cohortFilterId);
            exit();
        }

        $created = 0;
        $skippedDuplicates = [];
        $plannedGroups = [];
        $plannedTeachers = [];
        $plannedRooms = [];
        $hasCourseSectionClassId = academicPolicyColumnExists($conn, 'course_sections', 'class_id');
        $conn->begin_transaction();
        try {
            foreach ($selected as $key => $_checked) {
                [$subjectIdRaw, $cohortIdRaw] = array_pad(explode(':', (string)$key, 2), 2, '0');
                $subjectId = (int)$subjectIdRaw;
                $cohortId = (int)$cohortIdRaw;
                if ($subjectId <= 0 || $cohortId <= 0) {
                    continue;
                }
                if ($cohortFilterId > 0 && $cohortId !== $cohortFilterId) {
                    continue;
                }

                $policyCheck = academicPolicyValidateSubjectOpening($conn, $facultyId, $subjectId, $semesterId, $cohortId);
                if (!$policyCheck['ok'] || empty($policyCheck['eligible'][0])) {
                    throw new Exception($policyCheck['message'] ?: 'Môn học không phù hợp kế hoạch đào tạo.');
                }
                $eligible = $policyCheck['eligible'][0];
                $demoContext = academicPolicySemesterDemoContext($conn, $semesterId);
                $semesterOrder = (int)($eligible['semester_order'] ?? $eligible['suggested_semester'] ?? 0);
                $isFirstSemester = $semesterOrder === 1;
                $adminClasses = $isFirstSemester && $hasCourseSectionClassId
                    ? facultyProposalAdminClassesForCohort($conn, $cohortId, $demoContext['data_mode'])
                    : [];

                $count = max(1, min(10, (int)($classCounts[$key] ?? 1)));
                $capacity = max(1, (int)($maxStudents[$key] ?? 70));
                $schedule = trim((string)($daySessions[$key] ?? ''));
                $sectionStartDate = trim((string)($startDates[$key] ?? ''));
                $sectionEndDate = trim((string)($endDates[$key] ?? ''));
                $roomCode = trim((string)($roomCodes[$key] ?? ''));
                $teacherId = (int)($teacherIds[$key] ?? 0);
                $note = trim((string)($notes[$key] ?? ''));
                if ($schedule === '') {
                    $semesterRow = academicPolicyGetSemester($conn, $semesterId) ?: [];
                    $subjectLoad = facultyProposalSubjectLoad($conn, $subjectId, (int)($eligible['credits'] ?? 3));
                    $suggestion = facultyProposalRecommendedScheduleAndRoom($conn, $semesterId, $subjectId, $capacity, $semesterRow, $subjectLoad);
                    $schedule = (string)$suggestion['day_sessions'];
                    $roomCode = (string)$suggestion['room'];
                    $sectionStartDate = (string)($suggestion['start_date'] ?? '');
                    $sectionEndDate = (string)($suggestion['end_date'] ?? '');
                }
                if ($sectionStartDate === '' || $sectionEndDate === '') {
                    $semesterRow = academicPolicyGetSemester($conn, $semesterId) ?: [];
                    $subjectLoad = facultyProposalSubjectLoad($conn, $subjectId, (int)($eligible['credits'] ?? 3));
                    $range = facultyProposalScheduleWindow($semesterRow, $subjectLoad, $schedule, (int)sprintf('%u', crc32($key . ':' . $schedule)));
                    $sectionStartDate = (string)($range['start_date'] ?? '');
                    $sectionEndDate = (string)($range['end_date'] ?? '');
                }
                if ($sectionStartDate !== '') {
                    $subjectLoad = $subjectLoad ?? facultyProposalSubjectLoad($conn, $subjectId, (int)($eligible['credits'] ?? 3));
                    $calculatedEndDate = academicPolicyCourseEndDateFromStart($sectionStartDate, $schedule, $subjectLoad);
                    if ($calculatedEndDate) {
                        $sectionEndDate = $calculatedEndDate;
                    }
                }
                $semesterLimit = academicPolicyGetSemester($conn, $semesterId) ?: [];
                if (!academicPolicyIsTestSemester($semesterLimit)) {
                    if (!empty($semesterLimit['start_date']) && $sectionStartDate !== '' && strtotime($sectionStartDate) < strtotime((string)$semesterLimit['start_date'])) {
                        throw new Exception('Ngày bắt đầu của môn ' . $eligible['subject_code'] . ' phải nằm trong thời gian học kỳ.');
                    }
                    if (!empty($semesterLimit['end_date']) && $sectionEndDate !== '' && strtotime($sectionEndDate) > strtotime((string)$semesterLimit['end_date'])) {
                        throw new Exception('Ngày bắt đầu của môn ' . $eligible['subject_code'] . ' quá muộn, số buổi học vượt quá thời gian học kỳ.');
                    }
                }

                if ($isFirstSemester && !empty($adminClasses)) {
                    $selectedAdminClassIds = array_values(array_filter(array_map('intval', (array)($adminClassIdsByKey[$key] ?? []))));
                    $adminClassMap = [];
                    foreach ($adminClasses as $adminClass) {
                        $adminClassMap[(int)$adminClass['id']] = $adminClass;
                    }
                    $chosenAdminClasses = [];
                    foreach ($selectedAdminClassIds as $selectedClassId) {
                        if (isset($adminClassMap[$selectedClassId])) {
                            $chosenAdminClasses[] = $adminClassMap[$selectedClassId];
                        }
                    }
                    if (empty($chosenAdminClasses)) {
                        $chosenAdminClasses = array_slice($adminClasses, 0, $count);
                    }
                    $count = count($chosenAdminClasses);
                    $openingTargets = array_map(static fn(array $class): array => [
                        'class_id' => (int)$class['id'],
                        'label' => (string)$class['class_code'],
                    ], $chosenAdminClasses);
                    if ($count > count($openingTargets)) {
                        $openingTargets = array_merge(
                            $openingTargets,
                            array_fill(0, $count - count($openingTargets), ['class_id' => null, 'label' => 'lớp bổ sung'])
                        );
                    }
                } else {
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
                    $openingTargets = array_fill(0, $count - $existingCount, ['class_id' => null, 'label' => '']);
                }

                $subjectLoad = $subjectLoad ?? facultyProposalSubjectLoad($conn, $subjectId, (int)($eligible['credits'] ?? 3));
                $scheduleCandidates = facultyProposalScheduleCandidates(
                    $semesterLimit ?: (academicPolicyGetSemester($conn, $semesterId) ?: []),
                    $subjectLoad,
                    (int)sprintf('%u', crc32($key . ':sections'))
                );
                foreach ($openingTargets as $targetIndex => $openingTarget) {
                    $targetSchedule = $schedule;
                    $targetStartDate = $sectionStartDate;
                    $targetEndDate = $sectionEndDate;
                    $targetClassId = !empty($openingTarget['class_id']) ? (int)$openingTarget['class_id'] : null;
                    $candidatePlans = [[
                        'value' => $schedule,
                        'start_date' => $sectionStartDate,
                        'end_date' => $sectionEndDate,
                    ]];
                    foreach ($scheduleCandidates as $candidate) {
                        if ((string)($candidate['value'] ?? '') !== $schedule) {
                            $candidatePlans[] = $candidate;
                        }
                    }
                    if ($targetIndex > 0 && count($candidatePlans) > 2) {
                        $candidatePlans = array_merge(
                            array_slice($candidatePlans, $targetIndex),
                            array_slice($candidatePlans, 0, $targetIndex)
                        );
                    }
                    $plannedSelection = null;
                    foreach ($candidatePlans as $candidate) {
                        $candidateSchedule = (string)($candidate['value'] ?? '');
                        if ($candidateSchedule === '') {
                            continue;
                        }
                        $candidateStartDate = (string)($candidate['start_date'] ?? $sectionStartDate);
                        $candidateEndDate = (string)($candidate['end_date'] ?? $sectionEndDate);
                        $calculatedCandidateEnd = academicPolicyCourseEndDateFromStart($candidateStartDate, $candidateSchedule, $subjectLoad);
                        if ($calculatedCandidateEnd) {
                            $candidateEndDate = $calculatedCandidateEnd;
                        }
                        if (facultyProposalPlannedGroupConflict($plannedGroups, $targetClassId, $cohortId, $candidateSchedule, $candidateStartDate, $candidateEndDate)) {
                            continue;
                        }
                        if ($teacherId > 0 && facultyProposalPlannedScheduleConflict($plannedTeachers[$teacherId] ?? [], $candidateSchedule, $candidateStartDate, $candidateEndDate)) {
                            continue;
                        }
                        if (!academicPolicyIsTestSemester($semesterLimit) && academicPolicyHasStudentGroupScheduleConflict($conn, [
                            'id' => 0,
                            'semester_id' => $semesterId,
                            'target_cohort_id' => $cohortId,
                            'class_id' => $targetClassId,
                            'data_mode' => $demoContext['data_mode'],
                        ], $candidateSchedule, $candidateStartDate, $candidateEndDate)) {
                            continue;
                        }
                        if ($teacherId > 0 && facultyProposalTeacherScheduleConflict($conn, $teacherId, $semesterId, $candidateSchedule, 0, $candidateStartDate, $candidateEndDate)) {
                            continue;
                        }
                        $candidateRooms = facultyProposalRoomOptions($conn, $semesterId, $subjectId, $capacity, $candidateSchedule, 0, $candidateStartDate, $candidateEndDate);
                        $candidateRoom = null;
                        foreach ($candidateRooms as $availableRoom) {
                            if (empty($availableRoom['available'])) {
                                continue;
                            }
                            if ($roomCode !== '' && $targetIndex === 0 && (string)$availableRoom['room_code'] !== $roomCode) {
                                continue;
                            }
                            if (facultyProposalPlannedScheduleConflict($plannedRooms[(string)$availableRoom['room_code']] ?? [], $candidateSchedule, $candidateStartDate, $candidateEndDate)) {
                                continue;
                            }
                            $candidateRoom = $availableRoom;
                            break;
                        }
                        if ($candidateRoom) {
                            $plannedSelection = [
                                'day_sessions' => $candidateSchedule,
                                'start_date' => $candidateStartDate,
                                'end_date' => $candidateEndDate,
                                'room' => $candidateRoom,
                            ];
                            break;
                        }
                    }
                    if ($plannedSelection) {
                        $targetSchedule = (string)$plannedSelection['day_sessions'];
                        $targetStartDate = (string)$plannedSelection['start_date'];
                        $targetEndDate = (string)$plannedSelection['end_date'];
                    } elseif ($targetIndex > 0 && !empty($scheduleCandidates)) {
                        for ($candidateOffset = 0; $candidateOffset < count($scheduleCandidates); $candidateOffset++) {
                            $candidate = $scheduleCandidates[($targetIndex + $candidateOffset) % count($scheduleCandidates)];
                            $candidateSchedule = (string)($candidate['value'] ?? '');
                            if ($candidateSchedule === '' || $candidateSchedule === $schedule) {
                                continue;
                            }
                            $targetSchedule = $candidateSchedule;
                            $targetStartDate = (string)($candidate['start_date'] ?? $sectionStartDate);
                            $targetEndDate = (string)($candidate['end_date'] ?? $sectionEndDate);
                            $calculatedCandidateEnd = academicPolicyCourseEndDateFromStart($targetStartDate, $targetSchedule, $subjectLoad);
                            if ($calculatedCandidateEnd) {
                                $targetEndDate = $calculatedCandidateEnd;
                            }
                            break;
                        }
                    }
                    if ($targetClassId !== null) {
                        $stmtExistingClass = $conn->prepare(
                            "SELECT id FROM course_sections
                             WHERE subject_id = ? AND semester_id = ? AND target_cohort_id = ?
                               AND class_id = ?
                               AND data_mode = ?
                               AND status IN ('draft','proposed','open','full','closed')
                             LIMIT 1"
                        );
                        $stmtExistingClass->bind_param('iiiis', $subjectId, $semesterId, $cohortId, $targetClassId, $demoContext['data_mode']);
                        $stmtExistingClass->execute();
                        $existsForClass = $stmtExistingClass->get_result()->fetch_assoc();
                        $stmtExistingClass->close();
                        if ($existsForClass) {
                            $skippedDuplicates[] = $eligible['subject_code'] . ' - ' . $openingTarget['label'] . ' đã có';
                            continue;
                        }
                    }
                    if (!academicPolicyIsTestSemester($semesterLimit) && academicPolicyHasStudentGroupScheduleConflict($conn, [
                        'id' => 0,
                        'semester_id' => $semesterId,
                        'target_cohort_id' => $cohortId,
                        'class_id' => $targetClassId,
                        'data_mode' => $demoContext['data_mode'],
                    ], $targetSchedule, $targetStartDate, $targetEndDate)) {
                        throw new Exception('Lớp/khóa ' . ($openingTarget['label'] ?: $eligible['cohort_code']) . ' bị trùng lịch ở môn ' . $eligible['subject_code'] . '.');
                    }
                    $availableRooms = facultyProposalRoomOptions($conn, $semesterId, $subjectId, $capacity, $targetSchedule, 0, $targetStartDate, $targetEndDate);
                    if (empty($availableRooms)) {
                        throw new Exception('Không còn phòng trống phù hợp cho môn ' . $eligible['subject_code'] . ' ở lịch ' . $targetSchedule . '.');
                    }
                    $selectedRoom = null;
                    if (!empty($plannedSelection['room'])) {
                        foreach ($availableRooms as $availableRoom) {
                            if ((string)$availableRoom['room_code'] === (string)$plannedSelection['room']['room_code'] && !empty($availableRoom['available'])) {
                                $selectedRoom = $availableRoom;
                                break;
                            }
                        }
                    }
                    if (!$selectedRoom) {
                        foreach ($availableRooms as $availableRoom) {
                            if (empty($availableRoom['available'])) {
                                continue;
                            }
                            if (facultyProposalPlannedScheduleConflict($plannedRooms[(string)$availableRoom['room_code']] ?? [], $targetSchedule, $targetStartDate, $targetEndDate)) {
                                continue;
                            }
                            $selectedRoom = $availableRoom;
                            break;
                        }
                    }
                    if (!$selectedRoom) {
                        throw new Exception('Không còn phòng trống phù hợp cho môn ' . $eligible['subject_code'] . ' trong khoảng ' . $sectionStartDate . ' - ' . $sectionEndDate . '.');
                    }
                    if ($roomCode !== '' && $targetIndex === 0) {
                        foreach ($availableRooms as $availableRoom) {
                            if ((string)$availableRoom['room_code'] === $roomCode && !empty($availableRoom['available'])) {
                                $selectedRoom = $availableRoom;
                                break;
                            }
                        }
                        if ($targetIndex === 0 && (string)$selectedRoom['room_code'] !== $roomCode) {
                            throw new Exception('Phòng ' . $roomCode . ' không trống hoặc không phù hợp cho môn ' . $eligible['subject_code'] . ' ở lịch ' . $targetSchedule . '.');
                        }
                    }

                    $sectionCode = facultyProposalNextSectionCode($conn, (string)$eligible['subject_code'], $semesterId);
                    $room = (string)$selectedRoom['room_code'];
                    $classroomId = (int)($selectedRoom['id'] ?? $selectedRoom['classroom_id'] ?? 0);
                    if ($hasCourseSectionClassId) {
                        $stmtIns = $conn->prepare(
                            "INSERT INTO course_sections
                             (subject_id, semester_id, target_cohort_id, class_id, section_code, status, expected_students,
                              max_students, data_mode, demo_batch_id, day_sessions, start_date, end_date, room, classroom_id, teaching_mode,
                              open_proposal_note, open_proposed_by, open_proposed_at)
                             VALUES (?, ?, ?, ?, ?, 'proposed', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'offline', ?, ?, NOW())"
                        );
                        $stmtIns->bind_param('iiiisiissssssisi', $subjectId, $semesterId, $cohortId, $targetClassId, $sectionCode, $capacity, $capacity, $demoContext['data_mode'], $demoContext['demo_batch_id'], $targetSchedule, $targetStartDate, $targetEndDate, $room, $classroomId, $note, $userId);
                    } else {
                        $stmtIns = $conn->prepare(
                            "INSERT INTO course_sections
                             (subject_id, semester_id, target_cohort_id, section_code, status, expected_students,
                              max_students, data_mode, demo_batch_id, day_sessions, start_date, end_date, room, classroom_id, teaching_mode,
                              open_proposal_note, open_proposed_by, open_proposed_at)
                             VALUES (?, ?, ?, ?, 'proposed', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'offline', ?, ?, NOW())"
                        );
                        $stmtIns->bind_param('iiisiissssssisi', $subjectId, $semesterId, $cohortId, $sectionCode, $capacity, $capacity, $demoContext['data_mode'], $demoContext['demo_batch_id'], $targetSchedule, $targetStartDate, $targetEndDate, $room, $classroomId, $note, $userId);
                    }
                    if (!$stmtIns->execute()) {
                        throw new Exception('Không tạo được đề xuất ' . $sectionCode . ': ' . $stmtIns->error);
                    }
                    $newId = (int)$conn->insert_id;
                    $stmtIns->close();
                    $plannedGroups[] = [
                        'class_id' => $targetClassId,
                        'cohort_id' => $cohortId,
                        'day_sessions' => $targetSchedule,
                        'start_date' => $targetStartDate,
                        'end_date' => $targetEndDate,
                    ];
                    $plannedRooms[$room][] = [
                        'day_sessions' => $targetSchedule,
                        'start_date' => $targetStartDate,
                        'end_date' => $targetEndDate,
                    ];

                    if ($teacherId > 0) {
                        $eligibleTeacherIds = array_map(
                            static fn(array $teacher): int => (int)$teacher['id'],
                            facultyProposalEligibleTeachers($conn, $facultyId, $semesterId, $subjectId)
                        );
                        if (!in_array($teacherId, $eligibleTeacherIds, true)) {
                            throw new Exception($sectionCode . ': Giảng viên được chọn không thuộc danh sách giảng viên của khoa đang đề xuất.');
                        }
                        $assignmentCheck = facultyProposalValidateTeacherFromFaculty($conn, $teacherId, $facultyId, $subjectId, $semesterId);
                        if (!$assignmentCheck['ok']) {
                            throw new Exception($sectionCode . ': ' . $assignmentCheck['message']);
                        }
                        if (facultyProposalTeacherScheduleConflict($conn, $teacherId, $semesterId, $targetSchedule, $newId, $targetStartDate, $targetEndDate)) {
                            throw new Exception($sectionCode . ': Giảng viên đang trùng lịch với lớp học phần khác trong cùng thời điểm.');
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
                        $plannedTeachers[$teacherId][] = [
                            'day_sessions' => $targetSchedule,
                            'start_date' => $targetStartDate,
                            'end_date' => $targetEndDate,
                        ];
                    }

                    logAudit($conn, $userId, 'submit', 'faculty', 'course_sections', $newId, null,
                        json_encode(['subject_id' => $subjectId, 'semester_id' => $semesterId, 'status' => 'proposed']), $ip);
                    $created++;
                }
            }

            if ($created === 0) {
                $duplicateMessage = $skippedDuplicates
                    ? ' Các môn đã có đủ số lớp/đề xuất: ' . implode(', ', array_slice($skippedDuplicates, 0, 6)) . (count($skippedDuplicates) > 6 ? ', ...' : '') . '.'
                    : '';
                $conn->commit();
                $_SESSION['_flash'] = ['type' => 'warning', 'message' => 'Chưa tạo đề xuất mới.' . $duplicateMessage . ' Tăng "Số lớp" nếu muốn mở thêm.'];
                header('Location: proposals.php?tab=open&semester_id=' . $semesterId . '&cohort_id=' . $cohortFilterId);
                exit();
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

    // BULK DELETE TEST OPEN PROPOSALS
    if ($action === 'delete_test_open_proposals') {
        $semesterId = (int)($_POST['semester_id'] ?? 0);
        $redirectCohortId = (int)($_POST['cohort_id'] ?? 0);
        $sectionIds = array_values(array_unique(array_filter(
            array_map('intval', $_POST['section_ids'] ?? []),
            static fn(int $id): bool => $id > 0
        )));

        if ($semesterId <= 0 || empty($sectionIds)) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Vui lòng chọn đề xuất test cần xóa.'];
            header('Location: proposals.php?tab=open&semester_id=' . $semesterId);
            exit();
        }

        $semesterContext = academicPolicySemesterDemoContext($conn, $semesterId);
        if (($semesterContext['data_mode'] ?? 'system') !== 'test') {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Chỉ được xóa hàng loạt trong học kỳ test.'];
            header('Location: proposals.php?tab=open&semester_id=' . $semesterId);
            exit();
        }

        $placeholders = implode(',', array_fill(0, count($sectionIds), '?'));
        $types = 'ii' . str_repeat('i', count($sectionIds));
        $params = array_merge([$facultyId, $semesterId], $sectionIds);

        $stmtOwned = $conn->prepare(
            "SELECT DISTINCT cs.id
             FROM course_sections cs
             JOIN subjects s ON cs.subject_id = s.id
             JOIN curriculum cur ON s.id = cur.subject_id AND cur.deleted_at IS NULL
             JOIN majors m ON cur.major_id = m.id
             WHERE m.faculty_id = ?
               AND cs.semester_id = ?
               AND cs.status IN ('draft','proposed','open','cancelled','closed')
               AND cs.id IN ($placeholders)"
        );
        $stmtOwned->bind_param($types, ...$params);
        $stmtOwned->execute();
        $ownedIds = array_map('intval', array_column($stmtOwned->get_result()->fetch_all(MYSQLI_ASSOC), 'id'));
        $stmtOwned->close();

        if (empty($ownedIds)) {
            $_SESSION['_flash'] = ['type' => 'warning', 'message' => 'Không có đề xuất test hợp lệ để xóa.'];
            header('Location: proposals.php?tab=open&semester_id=' . $semesterId);
            exit();
        }

        $idList = implode(',', $ownedIds);
        $conn->begin_transaction();
        try {
            $conn->query("DROP TEMPORARY TABLE IF EXISTS tmp_faculty_delete_sections");
            $conn->query("CREATE TEMPORARY TABLE tmp_faculty_delete_sections AS SELECT id FROM course_sections WHERE id IN ($idList) AND semester_id = $semesterId");
            $conn->query("DROP TEMPORARY TABLE IF EXISTS tmp_faculty_delete_student_subjects");
            $conn->query("CREATE TEMPORARY TABLE tmp_faculty_delete_student_subjects AS SELECT ss.id, ss.student_id FROM student_subjects ss JOIN tmp_faculty_delete_sections ds ON ds.id = ss.course_section_id");

            $conn->query("DELETE se FROM student_evaluations se JOIN tmp_faculty_delete_sections ds ON ds.id = se.course_section_id");
            $conn->query("DELETE sec FROM student_extra_comments sec JOIN tmp_faculty_delete_sections ds ON ds.id = sec.course_section_id");
            $conn->query("DELETE gl FROM grade_locks gl JOIN tmp_faculty_delete_sections ds ON ds.id = gl.course_section_id");
            $conn->query("DELETE g FROM grades g JOIN tmp_faculty_delete_student_subjects tss ON tss.id = g.student_subject_id");
            $conn->query("DELETE pe FROM pending_enrollments pe JOIN tmp_faculty_delete_student_subjects tss ON tss.student_id = pe.student_id AND pe.data_mode = 'test'");
            $conn->query("DELETE ss FROM student_subjects ss JOIN tmp_faculty_delete_student_subjects tss ON tss.id = ss.id");
            $conn->query("DELETE fes FROM final_exam_schedules fes JOIN tmp_faculty_delete_sections ds ON ds.id = fes.course_section_id");
            $conn->query("DELETE csc FROM course_section_schedule_changes csc JOIN tmp_faculty_delete_sections ds ON ds.id = csc.course_section_id");
            $conn->query("DELETE cs FROM course_sections cs JOIN tmp_faculty_delete_sections ds ON ds.id = cs.id");

            foreach ($ownedIds as $deletedId) {
                logAudit($conn, $userId, 'delete', 'faculty', 'course_sections', $deletedId, null,
                    json_encode(['semester_id' => $semesterId, 'data_mode' => 'test']), $ip);
            }

            $conn->commit();
            $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Đã xóa ' . count($ownedIds) . ' đề xuất/lớp học phần test đã chọn.'];
        } catch (Throwable $e) {
            $conn->rollback();
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Lỗi xóa dữ liệu test: ' . $e->getMessage()];
        }
        header('Location: proposals.php?tab=open&semester_id=' . $semesterId . '&cohort_id=' . $redirectCohortId);
        exit();
    }

    if ($action === 'resubmit_open_revision') {
        $sectionId = (int)($_POST['section_id'] ?? 0);
        $maxStudents = max(1, (int)($_POST['max_students'] ?? 70));
        $daySessions = trim($_POST['day_sessions'] ?? '');
        $note = trim($_POST['open_proposal_note'] ?? '');

        if ($sectionId <= 0) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Vui lòng nhập đủ sĩ số để gửi lại đề xuất.'];
            header('Location: proposals.php?tab=open');
            exit();
        }
        facultyProposalWindowOrRedirect($conn, facultyProposalSectionSemester($conn, $sectionId), 'proposals.php?tab=open');

        $stmtChk = $conn->prepare(
            "SELECT cs.id, cs.subject_id, cs.semester_id, cs.status, cs.target_cohort_id, cs.class_id, cs.data_mode, cs.section_code, cs.room_requirement FROM course_sections cs
             JOIN subjects s ON cs.subject_id = s.id
             JOIN curriculum cur ON s.id = cur.subject_id AND cur.deleted_at IS NULL
             JOIN majors m ON cur.major_id = m.id
             WHERE cs.id = ? AND m.faculty_id = ? AND cs.status IN ('open','cancelled','closed') LIMIT 1"
        );
        $stmtChk->bind_param('ii', $sectionId, $facultyId);
        $stmtChk->execute();
        $secRow = $stmtChk->get_result()->fetch_assoc();
        $stmtChk->close();

        if (!$secRow) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Chỉ có thể sửa và gửi lại đề xuất đã duyệt hoặc đã bị từ chối.'];
            header('Location: proposals.php?tab=open');
            exit();
        }
        $plan = academicPolicyPlanSectionOpening($conn, $secRow, $maxStudents, 'offline', $daySessions, '');
        if (!$plan['ok']) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => $plan['message']];
            header('Location: proposals.php?tab=open');
            exit();
        }
        $daySessions = (string)$plan['day_sessions'];
        $sectionStartDate = $plan['start_date'] ?? null;
        $sectionEndDate = $plan['end_date'] ?? null;
        $room = (string)($plan['room'] ?? '');
        if (academicPolicyHasStudentGroupScheduleConflict($conn, $secRow, $daySessions, $sectionStartDate, $sectionEndDate)) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Lớp/khóa tuyển sinh đang bị trùng lịch với lớp học phần khác.'];
            header('Location: proposals.php?tab=open');
            exit();
        }

        $stmtUpd = $conn->prepare(
            "UPDATE course_sections
             SET status = 'proposed',
                 expected_students = ?,
                 max_students = ?,
                 day_sessions = ?,
                 start_date = ?,
                 end_date = ?,
                 room = ?,
                 open_proposal_note = ?,
                 open_proposed_by = ?,
                 open_proposed_at = NOW(),
                 open_reviewed_by = NULL,
                 open_reviewed_at = NULL,
                 open_reject_reason = NULL
             WHERE id = ?"
        );
        $stmtUpd->bind_param('iisssssii', $maxStudents, $maxStudents, $daySessions, $sectionStartDate, $sectionEndDate, $room, $note, $userId, $sectionId);
        $stmtUpd->execute();
        $stmtUpd->close();

        logAudit($conn, $userId, 'resubmit', 'faculty', 'course_sections', $sectionId, null,
            json_encode(['status' => 'proposed', 'max_students' => $maxStudents, 'day_sessions' => $daySessions]), $ip);

        $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Đã chỉnh sửa và gửi lại đề xuất. Chờ Phòng Đào tạo duyệt lại.'];
        header('Location: proposals.php?tab=open');
        exit();
    }

    // PROPOSE TEACHER
    if ($action === 'propose_teacher') {
        $sectionId  = (int)($_POST['section_id'] ?? 0);
        $teacherId  = (int)($_POST['proposed_teacher_id'] ?? 0);
        $maxStudents = max(1, (int)($_POST['max_students'] ?? 40));
        $daySessions = trim($_POST['day_sessions'] ?? '');
        $sectionStartDate = trim($_POST['start_date'] ?? '') ?: null;
        $sectionEndDate = trim($_POST['end_date'] ?? '') ?: null;
        $room = trim($_POST['room'] ?? '');
        $propNote   = trim($_POST['proposal_note'] ?? '');

        if ($sectionId <= 0 || $teacherId <= 0) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Dữ liệu không hợp lệ.'];
            header('Location: proposals.php?tab=assign');
            exit();
        }
        facultyProposalWindowOrRedirect($conn, facultyProposalSectionSemester($conn, $sectionId), 'proposals.php?tab=assign');

        // Kiem tra section chua duoc duyet
        $stmtSChk = $conn->prepare(
            "SELECT cs.id, cs.subject_id, cs.proposal_status, cs.semester_id, cs.target_cohort_id, cs.class_id, cs.data_mode, cs.section_code, cs.room_requirement FROM course_sections cs
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

        $assignmentCheck = facultyProposalValidateTeacherFromFaculty($conn, $teacherId, $facultyId, (int)$secRow['subject_id'], (int)$secRow['semester_id']);
        if (!$assignmentCheck['ok']) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => $assignmentCheck['message']];
            header('Location: proposals.php?tab=assign');
            exit();
        }
        $planSection = $secRow;
        $planSection['teacher_id'] = $teacherId;
        $plan = academicPolicyPlanSectionOpening($conn, $planSection, $maxStudents, 'offline', $daySessions, $room);
        if (!$plan['ok']) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => $plan['message']];
            header('Location: proposals.php?tab=assign');
            exit();
        }
        $daySessions = (string)$plan['day_sessions'];
        $sectionStartDate = $plan['start_date'] ?? null;
        $sectionEndDate = $plan['end_date'] ?? null;
        $room = (string)($plan['room'] ?? '');
        if (academicPolicyHasStudentGroupScheduleConflict($conn, $secRow, $daySessions, $sectionStartDate, $sectionEndDate)) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Lớp/khóa tuyển sinh đang bị trùng lịch với lớp học phần khác.'];
            header('Location: proposals.php?tab=assign');
            exit();
        }
        if (facultyProposalTeacherScheduleConflict($conn, $teacherId, (int)$secRow['semester_id'], $daySessions, $sectionId, $sectionStartDate, $sectionEndDate)) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Giảng viên đang trùng lịch với lớp học phần khác trong cùng thời điểm.'];
            header('Location: proposals.php?tab=assign');
            exit();
        }
        if ($room !== '' && facultyProposalRoomScheduleConflict($conn, $room, (int)$secRow['semester_id'], $daySessions, $sectionId, $sectionStartDate, $sectionEndDate)) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Phòng học đã được chọn cho lớp học phần khác cùng thời điểm.'];
            header('Location: proposals.php?tab=assign');
            exit();
        }
        if ($room !== '') {
            $roomOptions = facultyProposalRoomOptions($conn, (int)$secRow['semester_id'], (int)$secRow['subject_id'], $maxStudents, $daySessions, $sectionId, $sectionStartDate, $sectionEndDate);
            $roomOk = false;
            foreach ($roomOptions as $roomOption) {
                if ((string)$roomOption['room_code'] === $room && !empty($roomOption['available'])) {
                    $roomOk = true;
                    break;
                }
            }
            if (!$roomOk) {
                $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Phòng học không phù hợp sĩ số/loại môn hoặc không còn trống theo lịch đã chọn.'];
                header('Location: proposals.php?tab=assign');
                exit();
            }
        }

        $stmtUpd = $conn->prepare(
            "UPDATE course_sections SET proposed_teacher_id = ?, proposal_status = 'pending',
             proposal_note = ?, proposed_by = ?, proposed_at = NOW(),
             expected_students = ?, max_students = ?, day_sessions = ?, start_date = ?, end_date = ?, room = ?
             WHERE id = ?"
        );
        $stmtUpd->bind_param('isiiissssi', $teacherId, $propNote, $userId, $maxStudents, $maxStudents, $daySessions, $sectionStartDate, $sectionEndDate, $room, $sectionId);
        $stmtUpd->execute();
        $stmtUpd->close();

        logAudit($conn, $userId, 'submit', 'faculty', 'course_sections', $sectionId, null,
            json_encode(['proposed_teacher_id' => $teacherId, 'proposal_status' => 'pending', 'max_students' => $maxStudents, 'day_sessions' => $daySessions, 'room' => $room]), $ip);

        $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Đã gửi đề xuất giảng viên và dữ liệu lớp học phần.'];
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
$assignStatusFilter = trim($_GET['assign_status'] ?? '');
$assignMajorFilter = (int)($_GET['assign_major_id'] ?? 0);
$assignTeacherFilter = (int)($_GET['assign_teacher_id'] ?? 0);
$assignSearch = trim($_GET['assign_q'] ?? '');
$proposalCohortFilter = (int)($_GET['cohort_id'] ?? 0);

$semesters = [];
$stmtSem = $conn->prepare(
    "SELECT id, semester_name, school_year, status, start_date, end_date,
            proposal_start, proposal_end,
            " . (academicPolicyColumnExists($conn, 'semesters', 'data_mode') ? 'data_mode' : "'system' AS data_mode") . ",
            " . (academicPolicyColumnExists($conn, 'semesters', 'demo_batch_id') ? 'demo_batch_id' : "'' AS demo_batch_id") . "
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
$selectedDemoContext = $selectedSemId > 0 ? academicPolicySemesterDemoContext($conn, $selectedSemId) : ['data_mode' => 'system'];
$selectedIsTestSemester = (($selectedDemoContext['data_mode'] ?? 'system') === 'test');

// Lay de xuat mo lop
$openProposals = [];
$openWhere = [
    'm.faculty_id = ?',
    'cs.semester_id = ?',
    "cs.status IN ('draft','proposed','open','cancelled','closed')",
];
$openTypes = 'ii';
$openParams = [$facultyId, $selectedSemId];
if ($proposalCohortFilter > 0) {
    $openWhere[] = 'cs.target_cohort_id = ?';
    $openTypes .= 'i';
    $openParams[] = $proposalCohortFilter;
}
$openWhereSql = implode(' AND ', $openWhere);
$stmtOpen = $conn->prepare(
    "SELECT DISTINCT cs.id, cs.section_code, cs.status, cs.max_students, cs.data_mode,
            cs.open_proposal_note, cs.open_proposed_at, cs.day_sessions, cs.start_date, cs.end_date,
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
     WHERE $openWhereSql
     ORDER BY cs.open_proposed_at DESC, cs.id DESC"
);
$stmtOpen->bind_param($openTypes, ...$openParams);
$stmtOpen->execute();
$openProposals = $stmtOpen->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtOpen->close();

// Lay de xuat phan cong GV
$assignProposals = [];
$assignWhere = ['m.faculty_id = ?', 'cs.semester_id = ?'];
$assignTypes = 'ii';
$assignParams = [$facultyId, $selectedSemId];
if ($proposalCohortFilter > 0) {
    $assignWhere[] = 'cs.target_cohort_id = ?';
    $assignTypes .= 'i';
    $assignParams[] = $proposalCohortFilter;
}
if ($assignMajorFilter > 0) {
    $assignWhere[] = 'm.id = ?';
    $assignTypes .= 'i';
    $assignParams[] = $assignMajorFilter;
}
if ($assignTeacherFilter > 0) {
    $assignWhere[] = '(cs.teacher_id = ? OR cs.proposed_teacher_id = ?)';
    $assignTypes .= 'ii';
    $assignParams[] = $assignTeacherFilter;
}
if ($assignStatusFilter !== '') {
    if ($assignStatusFilter === 'none') {
        $assignWhere[] = "(cs.proposal_status IS NULL OR cs.proposal_status = '')";
    } elseif (in_array($assignStatusFilter, ['pending', 'approved', 'rejected'], true)) {
        $assignWhere[] = 'cs.proposal_status = ?';
        $assignTypes .= 's';
        $assignParams[] = $assignStatusFilter;
    }
}
if ($assignSearch !== '') {
    $assignWhere[] = '(cs.section_code LIKE ? OR s.subject_code LIKE ? OR s.subject_name LIKE ?)';
    $assignTypes .= 'sss';
    $assignLike = '%' . $assignSearch . '%';
    $assignParams[] = $assignLike;
    $assignParams[] = $assignLike;
    $assignParams[] = $assignLike;
}
$assignWhereSql = implode(' AND ', $assignWhere);
$stmtAssign = $conn->prepare(
    "SELECT DISTINCT cs.id, cs.subject_id, cs.section_code, cs.status, cs.proposal_status,
            cs.proposal_note, cs.proposed_at, cs.proposal_reject_reason,
            cs.expected_students, cs.max_students, cs.day_sessions, cs.room,
            s.subject_name, s.subject_code, s.credits,
            m.id AS major_id, m.major_name,
            sem.id AS semester_id, sem.semester_name, sem.school_year, sem.start_date, sem.end_date,
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
     WHERE $assignWhereSql
     ORDER BY cs.proposed_at DESC, cs.id DESC"
);
$stmtAssign->bind_param($assignTypes, ...$assignParams);
$stmtAssign->execute();
$assignProposals = $stmtAssign->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtAssign->close();

// Lay danh sach GV trong khoa
$facultyTeachers = [];
$stmtFT = $conn->prepare(
    "SELECT t.id, t.teacher_code, u.full_name, COALESCE(t.specialization, '') AS specialization,
            COALESCE(SUM(s2.credits), 0) AS current_load
     FROM teachers t
     JOIN users u ON t.user_id = u.id
     LEFT JOIN course_sections cs2 ON cs2.teacher_id = t.id AND cs2.semester_id = ? AND cs2.status IN ('open','closed')
     LEFT JOIN subjects s2 ON cs2.subject_id = s2.id
     WHERE t.faculty_id = ?
     GROUP BY t.id, t.teacher_code, u.full_name, t.specialization
     ORDER BY u.full_name ASC"
);
$stmtFT->bind_param('ii', $selectedSemId, $facultyId);
$stmtFT->execute();
$facultyTeachers = $stmtFT->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtFT->close();

$facultyTeacherBusy = [];
if ($selectedSemId > 0 && !empty($facultyTeachers)) {
    $facultyTeacherIds = array_map(static fn(array $teacher): int => (int)$teacher['id'], $facultyTeachers);
    $teacherPlaceholders = implode(',', array_fill(0, count($facultyTeacherIds), '?'));
    $busyDemoContext = academicPolicySemesterDemoContext($conn, $selectedSemId);
    $busyModeSql = academicPolicyColumnExists($conn, 'course_sections', 'data_mode')
        ? " AND COALESCE(cs.data_mode, 'system') = ?"
        : "";
    $busyTypes = 'i' . ($busyModeSql ? 's' : '') . str_repeat('i', count($facultyTeacherIds)) . str_repeat('i', count($facultyTeacherIds));
    $busyParams = array_merge([$selectedSemId], $busyModeSql ? [$busyDemoContext['data_mode']] : [], $facultyTeacherIds, $facultyTeacherIds);
    $stmtBusy = $conn->prepare(
        "SELECT COALESCE(NULLIF(cs.teacher_id, 0), cs.proposed_teacher_id) AS teacher_ref,
                cs.section_code, cs.day_sessions, cs.start_date, cs.end_date
         FROM course_sections cs
         WHERE cs.semester_id = ?
           AND cs.status IN ('draft','proposed','open','full','closed')
           AND cs.day_sessions IS NOT NULL
           AND cs.day_sessions <> ''
           $busyModeSql
           AND (
                cs.teacher_id IN ($teacherPlaceholders)
                OR cs.proposed_teacher_id IN ($teacherPlaceholders)
           )"
    );
    if ($stmtBusy) {
        $stmtBusy->bind_param($busyTypes, ...$busyParams);
        $stmtBusy->execute();
        $busyRows = $stmtBusy->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtBusy->close();
        foreach ($busyRows as $busyRow) {
            $teacherRef = (int)($busyRow['teacher_ref'] ?? 0);
            if ($teacherRef <= 0) {
                continue;
            }
            $facultyTeacherBusy[$teacherRef][] = [
                'section_code' => (string)($busyRow['section_code'] ?? ''),
                'day_sessions' => (string)($busyRow['day_sessions'] ?? ''),
                'start_date' => (string)($busyRow['start_date'] ?? ''),
                'end_date' => (string)($busyRow['end_date'] ?? ''),
            ];
        }
    }
}

$facultyMajors = [];
$stmtMajors = $conn->prepare("SELECT id, major_name FROM majors WHERE faculty_id = ? ORDER BY major_name");
$stmtMajors->bind_param('i', $facultyId);
$stmtMajors->execute();
$facultyMajors = $stmtMajors->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtMajors->close();

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

$assignScheduleOptionsBySection = [];
$assignRoomOptionsBySection = [];
foreach ($assignProposals as $assignRow) {
    $subjectLoad = facultyProposalSubjectLoad($conn, (int)$assignRow['subject_id'], (int)($assignRow['credits'] ?? 3));
    $semesterRow = [
        'start_date' => $assignRow['start_date'] ?? null,
        'end_date' => $assignRow['end_date'] ?? null,
        'semester_name' => $assignRow['semester_name'] ?? '',
        'school_year' => $assignRow['school_year'] ?? '',
    ];
    $scheduleOptions = facultyProposalScheduleCandidates($semesterRow, $subjectLoad, (int)sprintf('%u', crc32((string)$assignRow['id'])));
    $assignScheduleOptionsBySection[(int)$assignRow['id']] = $scheduleOptions;
    $capacity = max(1, (int)($assignRow['expected_students'] ?: $assignRow['max_students'] ?: 40));
    $roomMap = [];
    foreach ($scheduleOptions as $scheduleOption) {
        $roomMap[(string)$scheduleOption['value']] = facultyProposalRoomOptions(
            $conn,
            (int)$assignRow['semester_id'],
            (int)$assignRow['subject_id'],
            $capacity,
            (string)$scheduleOption['value'],
            (int)$assignRow['id'],
            $scheduleOption['start_date'] ?? null,
            $scheduleOption['end_date'] ?? null
        );
    }
    $assignRoomOptionsBySection[(int)$assignRow['id']] = $roomMap;
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
    $semesterOrder = (int)($subjectPlan['semester_order'] ?? $subjectPlan['suggested_semester'] ?? 0);
    $adminClasses = !empty($subjectPlan['cohort_id'])
        ? facultyProposalAdminClassesForCohort($conn, (int)$subjectPlan['cohort_id'], (string)$selectedDemoContext['data_mode'])
        : [];
    $defaultClassCount = max(1, (int)ceil((int)$subjectPlan['estimated_students'] / 70));
    $defaultCapacity = 70;
    $subjectPlan['admin_classes'] = $adminClasses;
    $subjectPlan['default_class_count'] = $defaultClassCount;
    $subjectPlan['default_capacity'] = $defaultCapacity;
    $subjectPlan['subject_load'] = facultyProposalSubjectLoad(
        $conn,
        (int)$subjectPlan['subject_id'],
        (int)$subjectPlan['credits']
    );
    $subjectPlan['schedule_options'] = facultyProposalScheduleCandidates(
        $selectedSemester ?: [],
        $subjectPlan['subject_load'],
        (int)sprintf('%u', crc32((string)$subjectPlan['subject_id'] . ':' . (string)$subjectPlan['cohort_id'] . ':' . (string)$selectedSemId))
    );
    $firstSchedule = (string)($subjectPlan['schedule_options'][0]['value'] ?? '2:sang');
    $roomOptionsBySchedule = [];
    foreach ($subjectPlan['schedule_options'] as $scheduleOption) {
        $roomOptionsBySchedule[(string)$scheduleOption['value']] = facultyProposalRoomOptions(
            $conn,
            $selectedSemId,
            (int)$subjectPlan['subject_id'],
            $defaultCapacity,
            (string)$scheduleOption['value'],
            0,
            $scheduleOption['start_date'] ?? null,
            $scheduleOption['end_date'] ?? null
        );
    }
    $recommendedSchedule = $subjectPlan['schedule_options'][0] ?? [];
    $roomOptions = $roomOptionsBySchedule[$firstSchedule] ?? [];
    $recommendedRoom = $roomOptions[0] ?? null;
    foreach ($subjectPlan['schedule_options'] as $scheduleOption) {
        foreach (($roomOptionsBySchedule[(string)$scheduleOption['value']] ?? []) as $roomOption) {
            if (!empty($roomOption['available'])) {
                $firstSchedule = (string)$scheduleOption['value'];
                $recommendedSchedule = $scheduleOption;
                $roomOptions = $roomOptionsBySchedule[$firstSchedule] ?? [];
                $recommendedRoom = $roomOption;
                break 2;
            }
        }
    }
    foreach ($roomOptions as $roomOption) {
        if (!empty($roomOption['available'])) {
            $recommendedRoom = $roomOption;
            break;
        }
    }
    $subjectPlan['recommendation'] = [
        'day_sessions' => $firstSchedule,
        'room' => ($recommendedRoom && !empty($recommendedRoom['available'])) ? (string)$recommendedRoom['room_code'] : '',
        'classroom_id' => 0,
        'room_label' => ($recommendedRoom && !empty($recommendedRoom['available']))
            ? ((string)$recommendedRoom['room_code'] . ' (' . (int)$recommendedRoom['capacity'] . ' SV)')
            : 'Chua co phong phu hop voi si so',
        'start_date' => $recommendedSchedule['start_date'] ?? null,
        'end_date' => $recommendedSchedule['end_date'] ?? null,
    ];
    $subjectPlan['room_options_by_schedule'] = $roomOptionsBySchedule;
    $subjectPlan['room_options'] = $roomOptions;
    $subjectPlan['eligible_teachers'] = facultyProposalEligibleTeachers(
        $conn,
        $facultyId,
        $selectedSemId,
        (int)$subjectPlan['subject_id']
    );
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
                    <div class="col-12 col-md-4">
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
                    <div class="col-12 col-md-4">
                        <label for="cohort_id" class="form-label fw-semibold">Khóa/ngành</label>
                        <select id="cohort_id" name="cohort_id" class="form-select" onchange="this.form.submit()">
                            <option value="0">Tất cả khóa/ngành</option>
                            <?php foreach ($facultyCohorts as $cohort): ?>
                            <option value="<?php echo (int)$cohort['id']; ?>" <?php echo $proposalCohortFilter === (int)$cohort['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cohort['cohort_code'] . ' - ' . $cohort['major_name']); ?>
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
                   href="proposals.php?tab=open&semester_id=<?php echo $selectedSemId; ?>&cohort_id=<?php echo (int)$proposalCohortFilter; ?>" role="tab" aria-selected="<?php echo $tab === 'open' ? 'true' : 'false'; ?>">
                    <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Đề xuất Mở lớp
                    <span class="badge bg-warning ms-1"><?php echo count(array_filter($openProposals, fn($p) => $p['status'] === 'proposed')); ?></span>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $tab === 'assign' ? 'active' : ''; ?>"
                   href="proposals.php?tab=assign&semester_id=<?php echo $selectedSemId; ?>&cohort_id=<?php echo (int)$proposalCohortFilter; ?>" role="tab"
                   aria-selected="<?php echo $tab === 'assign' ? 'true' : 'false'; ?>">
                    <i class="bi bi-person-check me-1" aria-hidden="true"></i>Đề xuất Phân công GV
                    <span class="badge bg-warning ms-1"><?php echo count(array_filter($assignProposals, fn($p) => $p['proposal_status'] === 'pending')); ?></span>
                </a>
            </li>
        </ul>

        <?php if ($tab === 'open'): ?>
        <!-- Open proposals tab -->
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span><i class="bi bi-list-check me-2" aria-hidden="true"></i>Danh sách Đề xuất Mở lớp</span>
                <?php if (isFacultyManager() && $selectedIsTestSemester && !empty($openProposals)): ?>
                <form id="bulkDeleteTestOpenForm" method="post" action="proposals.php?tab=open&semester_id=<?php echo (int)$selectedSemId; ?>"
                      onsubmit="return confirm('Xóa các đề xuất/lớp học phần test đã chọn? Dữ liệu đăng ký, điểm, lịch thi liên quan cũng sẽ bị xóa.');">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="delete_test_open_proposals">
                    <input type="hidden" name="semester_id" value="<?php echo (int)$selectedSemId; ?>">
                    <input type="hidden" name="cohort_id" value="<?php echo (int)$proposalCohortFilter; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" id="bulkDeleteOpenBtn" disabled>
                        <i class="bi bi-trash3 me-1" aria-hidden="true"></i>Xóa đã chọn
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <?php if (isFacultyManager() && $selectedIsTestSemester): ?>
                            <th style="width:42px;">
                                <input type="checkbox" class="form-check-input" id="selectAllOpenProposals"
                                       aria-label="Chọn tất cả đề xuất mở lớp test">
                            </th>
                            <?php endif; ?>
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
                            <td colspan="<?php echo (isFacultyManager() && $selectedIsTestSemester) ? 11 : 10; ?>" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3 d-block mb-2" aria-hidden="true"></i>
                                Chưa có đề xuất nào.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($openProposals as $p): ?>
                        <?php $sb = $statusBadgeMap[$p['status']] ?? ['secondary', $p['status']]; ?>
                        <tr>
                            <?php if (isFacultyManager() && $selectedIsTestSemester): ?>
                            <td>
                                <input type="checkbox" class="form-check-input open-proposal-check"
                                       form="bulkDeleteTestOpenForm"
                                       name="section_ids[]"
                                       value="<?php echo (int)$p['id']; ?>"
                                       aria-label="Chọn đề xuất <?php echo htmlspecialchars($p['section_code']); ?>">
                            </td>
                            <?php endif; ?>
                            <td><code><?php echo htmlspecialchars($p['section_code']); ?></code></td>
                            <td><?php echo htmlspecialchars($p['subject_name']); ?></td>
                            <td><?php echo htmlspecialchars($p['semester_name'] . ' - ' . ($p['school_year'] ?? '')); ?></td>
                            <td class="text-muted small"><?php echo htmlspecialchars($p['cohort_code'] ?? '—'); ?></td>
                            <td class="text-center"><?php echo (int)$p['max_students']; ?></td>
                            <td class="text-muted small">
                                <?php echo htmlspecialchars($p['day_sessions'] ?? '—'); ?><br>
                                <span><?php echo htmlspecialchars(trim(($p['start_date'] ?? '') . ' - ' . ($p['end_date'] ?? ''), ' -') ?: 'Chưa có khoảng học'); ?></span>
                            </td>
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
                                <?php elseif (in_array($p['status'], ['open','cancelled','closed'], true)): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="modal" data-bs-target="#resubmitOpenModal"
                                        data-section-id="<?php echo (int)$p['id']; ?>"
                                        data-section-code="<?php echo htmlspecialchars($p['section_code']); ?>"
                                        data-subject-name="<?php echo htmlspecialchars($p['subject_name']); ?>"
                                        data-max-students="<?php echo (int)$p['max_students']; ?>"
                                        data-day-sessions="<?php echo htmlspecialchars($p['day_sessions'] ?? ''); ?>"
                                        data-start-date="<?php echo htmlspecialchars($p['start_date'] ?? ''); ?>"
                                        data-end-date="<?php echo htmlspecialchars($p['end_date'] ?? ''); ?>"
                                        data-note="<?php echo htmlspecialchars($p['open_proposal_note'] ?? ''); ?>">
                                    <i class="bi bi-pencil-square" aria-hidden="true"></i> Sửa & gửi lại
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

        <?php else: ?>
        <!-- Assign proposals tab -->
        <div class="card mb-3">
            <div class="card-body">
                <form method="get" action="proposals.php" class="row g-2 align-items-end">
                    <input type="hidden" name="tab" value="assign">
                    <input type="hidden" name="cohort_id" value="<?php echo (int)$proposalCohortFilter; ?>">
                    <div class="col-12 col-md-3">
                        <label for="assign_semester_id" class="form-label">Học kỳ</label>
                        <select id="assign_semester_id" name="semester_id" class="form-select form-select-sm">
                            <?php foreach ($semesters as $sem): ?>
                            <option value="<?php echo (int)$sem['id']; ?>"
                                <?php echo $selectedSemId === (int)$sem['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sem['semester_name'] . ' - ' . ($sem['school_year'] ?? '')); ?>
                                <?php if ($sem['status'] === 'active'): ?>(Hiện tại)<?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="assign_major_id" class="form-label">Ngành</label>
                        <select id="assign_major_id" name="assign_major_id" class="form-select form-select-sm">
                            <option value="0">-- Tất cả --</option>
                            <?php foreach ($facultyMajors as $major): ?>
                            <option value="<?php echo (int)$major['id']; ?>" <?php echo $assignMajorFilter === (int)$major['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($major['major_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="assign_teacher_id" class="form-label">Giảng viên</label>
                        <select id="assign_teacher_id" name="assign_teacher_id" class="form-select form-select-sm">
                            <option value="0">-- Tất cả --</option>
                            <?php foreach ($facultyTeachers as $teacher): ?>
                            <option value="<?php echo (int)$teacher['id']; ?>" <?php echo $assignTeacherFilter === (int)$teacher['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($teacher['full_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="assign_status" class="form-label">Trạng thái</label>
                        <select id="assign_status" name="assign_status" class="form-select form-select-sm">
                            <option value="">-- Tất cả --</option>
                            <option value="none" <?php echo $assignStatusFilter === 'none' ? 'selected' : ''; ?>>Chưa đề xuất</option>
                            <option value="pending" <?php echo $assignStatusFilter === 'pending' ? 'selected' : ''; ?>>Chờ duyệt</option>
                            <option value="approved" <?php echo $assignStatusFilter === 'approved' ? 'selected' : ''; ?>>Đã duyệt</option>
                            <option value="rejected" <?php echo $assignStatusFilter === 'rejected' ? 'selected' : ''; ?>>Từ chối</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="assign_q" class="form-label">Tìm kiếm</label>
                        <input id="assign_q" name="assign_q" class="form-control form-control-sm" value="<?php echo htmlspecialchars($assignSearch); ?>" placeholder="Môn, mã lớp...">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-navy" aria-label="Xem đề xuất phân công">
                            <i class="bi bi-search me-1" aria-hidden="true"></i>Xem
                        </button>
                        <a href="proposals.php?tab=assign&semester_id=<?php echo (int)$selectedSemId; ?>&cohort_id=<?php echo (int)$proposalCohortFilter; ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                        </a>
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
                            <th>Sĩ số</th>
                            <th>Lịch/phòng đề xuất</th>
                            <th>Trạng thái</th>
                            <th>Lý do từ chối</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($assignProposals)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
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
                            <td><span class="badge bg-light text-dark"><?php echo (int)($p['expected_students'] ?: $p['max_students'] ?: 0); ?>/<?php echo (int)($p['max_students'] ?: 0); ?></span></td>
                            <td class="small">
                                <?php echo htmlspecialchars($p['day_sessions'] ?: '—'); ?><br>
                                <span class="text-muted"><?php echo htmlspecialchars(trim(($p['start_date'] ?? '') . ' - ' . ($p['end_date'] ?? ''), ' -') ?: 'Chưa có khoảng học'); ?></span><br>
                                <span class="text-muted"><?php echo htmlspecialchars($p['room'] ?: 'Chưa đề xuất phòng'); ?></span>
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
                                        data-subject-name="<?php echo htmlspecialchars($p['subject_name']); ?>"
                                        data-major-id="<?php echo (int)$p['major_id']; ?>"
                                        data-major-name="<?php echo htmlspecialchars($p['major_name']); ?>"
                                        data-max-students="<?php echo (int)($p['expected_students'] ?: $p['max_students'] ?: 40); ?>"
                                        data-day-sessions="<?php echo htmlspecialchars($p['day_sessions'] ?? ''); ?>"
                                        data-room="<?php echo htmlspecialchars($p['room'] ?? ''); ?>"
                                        data-schedule-options="<?php echo htmlspecialchars(json_encode($assignScheduleOptionsBySection[(int)$p['id']] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)); ?>"
                                        data-room-options="<?php echo htmlspecialchars(json_encode($assignRoomOptionsBySection[(int)$p['id']] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)); ?>"
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
<!-- Resubmit Open Proposal Modal -->
<div class="modal fade" id="resubmitOpenModal" tabindex="-1" aria-labelledby="resubmitOpenModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="proposals.php?tab=open&semester_id=<?php echo (int)$selectedSemId; ?>">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="resubmit_open_revision">
                <input type="hidden" name="section_id" id="resubmit_section_id">
                <div class="modal-header bg-navy text-white">
                    <h5 class="modal-title" id="resubmitOpenModalLabel">Sửa đề xuất và gửi lại</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="fw-semibold" id="resubmit_subject_name"></div>
                        <small class="text-muted" id="resubmit_section_code"></small>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Sĩ số đề xuất</label>
                            <input type="number" name="max_students" id="resubmit_max_students" class="form-control" min="1" value="70" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Lịch học đề xuất</label>
                            <input type="text" name="day_sessions" id="resubmit_day_sessions" class="form-control" placeholder="Để trống để hệ thống tự xếp, VD: 2:sang,4:chieu">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Ghi chú gửi Phòng Đào tạo</label>
                            <textarea name="open_proposal_note" id="resubmit_note" class="form-control" rows="3" placeholder="Nêu phần cần chỉnh so với lần duyệt trước..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-navy">
                        <i class="bi bi-send me-1" aria-hidden="true"></i>Gửi lại
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

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
                            <select id="plan_cohort_filter" name="cohort_filter" class="form-select">
                                <option value="">Tất cả khóa/ngành</option>
                                <?php foreach ($facultyCohorts as $cohort): ?>
                                <option value="<?php echo (int)$cohort['id']; ?>" <?php echo $proposalCohortFilter === (int)$cohort['id'] ? 'selected' : ''; ?>>
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
                        <table class="table table-sm align-middle" style="min-width:1530px;">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="width:36px;"></th>
                                    <th>Môn cần mở</th>
                                    <th>Khóa/ngành</th>
                                    <th style="min-width:150px;">Lớp HC</th>
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
                                    $adminClasses = $subj['admin_classes'] ?? [];
                                    $isFirstSemesterPlan = (int)($subj['semester_order'] ?? $subj['suggested_semester'] ?? 0) === 1;
                                    $existingOpening = $subj['existing_openings'] ?? ['count' => 0, 'codes' => []];
                                    $existingCount = (int)($existingOpening['count'] ?? 0);
                                    $existingCodes = $existingOpening['codes'] ?? [];
                                    $hasRoomSuggestion = !empty($recommendation['room']);
                                    $autoChecked = $existingCount < $defaultClassCount && $hasRoomSuggestion;
                                ?>
                                <tr class="plan-row"
                                    data-semester-id="<?php echo (int)$selectedSemId; ?>"
                                    data-cohort-id="<?php echo (int)$subj['cohort_id']; ?>"
                                    data-existing-count="<?php echo $existingCount; ?>"
                                    data-default-class-count="<?php echo $defaultClassCount; ?>"
                                    data-estimated-students="<?php echo $estimated; ?>"
                                    data-room-options="<?php echo htmlspecialchars(json_encode($roomOptionsBySchedule, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)); ?>"
                                    data-start-date="<?php echo htmlspecialchars($recommendation['start_date'] ?? ''); ?>"
                                    data-end-date="<?php echo htmlspecialchars($recommendation['end_date'] ?? ''); ?>"
                                    data-total-periods="<?php echo (int)($subjectLoad['total_periods'] ?? 0); ?>"
                                    data-semester-start="<?php echo htmlspecialchars($selectedSemester['start_date'] ?? ''); ?>"
                                    data-semester-end="<?php echo htmlspecialchars($selectedSemester['end_date'] ?? ''); ?>"
                                    data-semester-test="<?php echo academicPolicyIsTestSemester($selectedSemester ?: []) ? '1' : '0'; ?>"
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
                                        <?php if ($isFirstSemesterPlan && !empty($adminClasses)): ?>
                                        <br><small class="text-info">HK1: theo <?php echo count($adminClasses); ?> lớp hành chính</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($adminClasses)): ?>
                                        <select class="form-select form-select-sm plan-admin-classes"
                                                name="admin_class_ids[<?php echo htmlspecialchars($key); ?>][]"
                                                multiple
                                                size="<?php echo min(3, max(1, count($adminClasses))); ?>"
                                                style="min-width:150px;">
                                            <?php foreach ($adminClasses as $adminIndex => $adminClass): ?>
                                            <option value="<?php echo (int)$adminClass['id']; ?>" <?php echo $adminIndex < $defaultClassCount ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars((string)$adminClass['class_code']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted d-block mt-1">Giữ Ctrl để chọn nhiều lớp.</small>
                                        <?php else: ?>
                                        <span class="text-muted small">Theo khóa</span>
                                        <?php endif; ?>
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
                                                    data-start-date="<?php echo htmlspecialchars($scheduleOption['start_date'] ?? ''); ?>"
                                                    data-end-date="<?php echo htmlspecialchars($scheduleOption['end_date'] ?? ''); ?>"
                                                    <?php echo $isSelectedSchedule ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($scheduleOption['label']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="date" class="form-control form-control-sm plan-start-date mt-1"
                                               name="start_date[<?php echo htmlspecialchars($key); ?>]"
                                               value="<?php echo htmlspecialchars($recommendation['start_date'] ?? ''); ?>"
                                               <?php if (!academicPolicyIsTestSemester($selectedSemester ?: [])): ?>
                                               min="<?php echo htmlspecialchars($selectedSemester['start_date'] ?? ''); ?>"
                                               max="<?php echo htmlspecialchars($selectedSemester['end_date'] ?? ''); ?>"
                                               <?php endif; ?>
                                               style="min-width:150px;">
                                        <input type="hidden" class="plan-end-date" name="end_date[<?php echo htmlspecialchars($key); ?>]" value="<?php echo htmlspecialchars($recommendation['end_date'] ?? ''); ?>">
                                        <small class="text-muted d-block mt-1">
                                            <?php echo (int)($subjectLoad['total_periods'] ?? 0); ?> tiết ·
                                            <?php echo (int)($scheduleOptions[0]['sessions_per_week'] ?? 1); ?> buổi/tuần ·
                                            <span class="plan-date-label"><?php echo htmlspecialchars(($recommendation['start_date'] ?? '') . ' - ' . ($recommendation['end_date'] ?? '')); ?></span>
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
                                        <select class="form-select form-select-sm plan-teacher" name="proposed_teacher_id[<?php echo htmlspecialchars($key); ?>]">
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
                                            <span class="badge bg-<?php echo $existingCount >= $defaultClassCount ? 'warning text-dark' : 'info'; ?>">
                                                <?php echo $existingCount >= $defaultClassCount ? 'Đã có đủ lớp' : 'Đã có lớp'; ?>
                                            </span>
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
    <div class="modal-dialog modal-lg">
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
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="pt_teacher_major_filter" class="form-label">Lọc GV theo ngành/chuyên môn</label>
                            <select id="pt_teacher_major_filter" class="form-select">
                                <option value="0">-- Tất cả giảng viên trong khoa --</option>
                                <?php foreach ($facultyMajors as $major): ?>
                                <option value="<?php echo (int)$major['id']; ?>" data-major-name="<?php echo htmlspecialchars(mb_strtolower($major['major_name'], 'UTF-8')); ?>">
                                    <?php echo htmlspecialchars($major['major_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                        <label for="pt_teacher" class="form-label">Giảng viên <span class="text-danger">*</span></label>
                        <select id="pt_teacher" name="proposed_teacher_id" class="form-select" required>
                            <option value="">-- Chọn giảng viên --</option>
                            <?php foreach ($facultyTeachers as $t): ?>
                            <option value="<?php echo (int)$t['id']; ?>"
                                    data-specialization="<?php echo htmlspecialchars(mb_strtolower(($t['specialization'] ?? '') . ' ' . $t['full_name'], 'UTF-8')); ?>">
                                <?php echo htmlspecialchars($t['full_name'] . ' (' . $t['teacher_code'] . ') — ' . $t['current_load'] . ' TC hiện tại'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="pt_max_students" class="form-label">Sĩ số đề xuất</label>
                            <input type="number" id="pt_max_students" name="max_students" class="form-control" min="1" value="40">
                        </div>
                        <div class="col-md-4">
                            <label for="pt_day_sessions" class="form-label">Lịch đề xuất</label>
                            <select id="pt_day_sessions" name="day_sessions" class="form-select"></select>
                            <input type="hidden" id="pt_start_date" name="start_date">
                            <input type="hidden" id="pt_end_date" name="end_date">
                            <div class="form-text" id="pt_schedule_hint"></div>
                        </div>
                        <div class="col-md-4">
                            <label for="pt_room" class="form-label">Phòng đề xuất</label>
                            <select id="pt_room" name="room" class="form-select">
                                <option value="">-- Chọn phòng --</option>
                            </select>
                            <div class="form-text">Hệ thống sẽ kiểm tra trùng phòng khi gửi.</div>
                        </div>
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
window.__facultyTeacherBusy = <?php echo json_encode($facultyTeacherBusy, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

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
        const checkbox = row.querySelector('.plan-check');
        if (checkbox) {
            checkbox.disabled = !isVisible;
        }
        if (isVisible) {
            visible++;
            if (checkbox?.checked) {
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
    const checkedRows = Array.from(this.querySelectorAll('.plan-check:checked:not(:disabled)'))
        .map(function(input) { return input.closest('.plan-row'); })
        .filter(Boolean);
    const checked = checkedRows.length;
    if (checked === 0) {
        e.preventDefault();
        alert('Vui lòng chọn ít nhất một môn cần mở.');
        return;
    }

    const invalidRooms = [];
    const invalidRows = [];
    const scheduleRows = [];
    checkedRows.forEach(function(row) {
        refreshPlanRoomOptions(row);
        refreshPlanRowState(row);
        if (row.dataset.planError === '1') {
            invalidRows.push(row.dataset.subjectLabel || 'Một dòng kế hoạch');
            return;
        }
        const existingCount = Number(row.dataset.existingCount || 0);
        const classCount = Number(row.querySelector('.plan-class-count')?.value || 0);
        if (existingCount > 0 && classCount <= existingCount) {
            return;
        }
        const capacity = Number(row.querySelector('.plan-capacity')?.value || 0);
        const roomSelect = row.querySelector('.plan-room');
        const selectedRoom = roomSelect?.selectedOptions?.[0] || null;
        if (roomSelect?.value && (!selectedRoom || selectedRoom.disabled || Number(selectedRoom.dataset.capacity || 0) < capacity)) {
            invalidRooms.push(row.dataset.subjectLabel + ' - phòng không còn phù hợp');
        }
        scheduleRows.push(row);
    });

    const rowConflicts = [];
    const realScheduleRows = scheduleRows.filter((row) => row.dataset.semesterTest !== '1');
    realScheduleRows.forEach(function(row, index) {
        const schedule = row.querySelector('.plan-schedule')?.value || '';
        const startDate = row.querySelector('.plan-start-date')?.value || '';
        const endDate = row.querySelector('.plan-end-date')?.value || '';
        const tokens = planScheduleTokens(schedule);
        realScheduleRows.slice(index + 1).forEach(function(otherRow) {
            if ((row.dataset.semesterId || '') !== (otherRow.dataset.semesterId || '')) {
                return;
            }
            if ((row.dataset.cohortId || '') !== (otherRow.dataset.cohortId || '')) {
                return;
            }
            const otherTokens = planScheduleTokens(otherRow.querySelector('.plan-schedule')?.value || '');
            const hasSameSlot = tokens.some((token) => otherTokens.includes(token));
            const hasSameDates = planDatesOverlap(
                startDate,
                endDate,
                otherRow.querySelector('.plan-start-date')?.value || '',
                otherRow.querySelector('.plan-end-date')?.value || ''
            );
            if (hasSameSlot && hasSameDates) {
                const otherStart = otherRow.querySelector('.plan-start-date')?.value || '';
                const otherEnd = otherRow.querySelector('.plan-end-date')?.value || '';
                rowConflicts.push(
                    (row.dataset.subjectLabel || 'Một dòng') + ' (' + startDate + ' - ' + endDate + ') trùng '
                    + (otherRow.dataset.subjectLabel || 'một dòng khác') + ' (' + otherStart + ' - ' + otherEnd + ')'
                );
            }
        });
    });

    if (invalidRows.length > 0) {
        e.preventDefault();
        alert('Một số dòng chưa hợp lệ, vui lòng sửa trước khi gửi:\n- ' + invalidRows.slice(0, 8).join('\n- '));
        return;
    }

    if (rowConflicts.length > 0) {
        e.preventDefault();
        alert('Một số môn cùng khóa/lớp đang trùng ca và khoảng ngày học, không phải lỗi trùng phòng:\n- ' + rowConflicts.slice(0, 8).join('\n- '));
        return;
    }

    if (invalidRooms.length > 0) {
        e.preventDefault();
        alert('Một số phòng đang trùng lịch hoặc không đủ sức chứa:\n- ' + invalidRooms.slice(0, 8).join('\n- '));
    }
});

function planScheduleTokens(value) {
    return String(value || '').split(',').map((item) => item.trim()).filter(Boolean);
}

function planDatesOverlap(startA, endA, startB, endB) {
    if (!startA || !endA || !startB || !endB) return true;
    const aStart = new Date(startA + 'T00:00:00').getTime();
    const aEnd = new Date(endA + 'T23:59:59').getTime();
    const bStart = new Date(startB + 'T00:00:00').getTime();
    const bEnd = new Date(endB + 'T23:59:59').getTime();
    if (!aStart || !aEnd || !bStart || !bEnd) return true;
    return aStart <= bEnd && bStart <= aEnd;
}

function planScheduleWindowsOverlap(scheduleA, startA, endA, scheduleB, startB, endB) {
    const tokensA = planScheduleTokens(scheduleA);
    const tokensB = planScheduleTokens(scheduleB);
    if (!tokensA.length || !tokensB.length || !tokensA.some((token) => tokensB.includes(token))) {
        return false;
    }
    return planDatesOverlap(startA, endA, startB, endB);
}

function planAddDays(date, days) {
    const next = new Date(date.getTime());
    next.setDate(next.getDate() + days);
    return next;
}

function planFormatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return year + '-' + month + '-' + day;
}

function planCalculateEndDate(startDate, schedule, totalPeriods) {
    if (!startDate || !schedule) return '';
    const studyDays = Array.from(new Set(planScheduleTokens(schedule)
        .map((token) => Number((token.split(':')[0] || '').replace(/\D+/g, '')))
        .filter(Boolean)
        .map((day) => day === 8 ? 0 : Math.max(0, Math.min(6, day - 1)))
    ));
    if (!studyDays.length) return '';
    const meetingsNeeded = Math.max(1, Math.ceil(Math.max(1, Number(totalPeriods || 0)) / 5));
    let cursor = new Date(startDate + 'T00:00:00');
    if (!cursor.getTime()) return '';
    let meetings = 0;
    for (let i = 0; i < 370; i++) {
        if (studyDays.includes(cursor.getDay())) {
            meetings++;
            if (meetings >= meetingsNeeded) {
                return planFormatDate(cursor);
            }
        }
        cursor = planAddDays(cursor, 1);
    }
    return '';
}

function planTeacherConflict(row) {
    const teacherId = row.querySelector('.plan-teacher')?.value || '0';
    if (!teacherId || teacherId === '0') return null;
    const scheduleSelect = row.querySelector('.plan-schedule');
    const schedule = scheduleSelect?.value || '';
    const startDate = row.querySelector('.plan-start-date')?.value || row.dataset.startDate || '';
    const endDate = row.querySelector('.plan-end-date')?.value || row.dataset.endDate || '';
    const busyItems = window.__facultyTeacherBusy?.[teacherId] || [];
    return busyItems.find((item) => planScheduleWindowsOverlap(
        schedule,
        startDate,
        endDate,
        item.day_sessions || '',
        item.start_date || '',
        item.end_date || ''
    )) || null;
}

function refreshPlanRowState(row) {
    const existingCount = Number(row.dataset.existingCount || 0);
    const classCount = Number(row.querySelector('.plan-class-count')?.value || 0);
    const cell = row.querySelector('.plan-duplicate-cell');
    if (!cell) {
        return;
    }
    const roomSelect = row.querySelector('.plan-room');
    const selectedRoom = roomSelect?.selectedOptions?.[0] || null;
    const capacity = Number(row.querySelector('.plan-capacity')?.value || 0);
    const roomInvalid = !!(roomSelect?.value && (!selectedRoom || selectedRoom.disabled || Number(selectedRoom.dataset.capacity || 0) < capacity));
    const roomUnavailable = row.dataset.roomUnavailable === '1';
    const teacherConflict = planTeacherConflict(row);
    const startDate = row.querySelector('.plan-start-date')?.value || '';
    const endDate = row.querySelector('.plan-end-date')?.value || '';
    const semesterEnd = row.dataset.semesterEnd || '';
    const isTestSemester = row.dataset.semesterTest === '1';
    const dateInvalid = !startDate || !endDate || (!isTestSemester && semesterEnd && endDate > semesterEnd);

    row.dataset.planError = roomInvalid || roomUnavailable || teacherConflict || dateInvalid ? '1' : '0';
    if (dateInvalid) {
        cell.innerHTML = '<span class="badge bg-danger">Ngày học không hợp lệ</span><div class="text-muted mt-1">Chọn ngày bắt đầu sớm hơn để đủ số buổi trong học kỳ.</div>';
        return;
    }
    if (roomUnavailable) {
        cell.innerHTML = '<span class="badge bg-danger">Chưa có phòng</span><div class="text-muted mt-1">Đổi lịch hoặc kiểm tra phòng phù hợp.</div>';
        return;
    }
    if (roomInvalid) {
        cell.innerHTML = '<span class="badge bg-danger">Phòng không hợp lệ</span><div class="text-muted mt-1">Đổi lịch/phòng hoặc giảm sĩ số.</div>';
        return;
    }
    if (teacherConflict) {
        cell.innerHTML = `<span class="badge bg-danger">GV trùng lịch</span><div class="text-muted mt-1">Trùng ${teacherConflict.section_code || 'lớp khác'}.</div>`;
        return;
    }
    if (existingCount <= 0) {
        cell.innerHTML = '<span class="badge bg-success">Có thể gửi</span>';
        return;
    }
    if (classCount <= existingCount) {
        cell.innerHTML = '<span class="badge bg-warning text-dark">Đã có đủ lớp</span>';
    } else {
        cell.innerHTML = '<span class="badge bg-success">Mở thêm ' + (classCount - existingCount) + ' lớp</span>';
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

function syncPlanClassCountFromAdminClasses(row) {
    const adminSelect = row.querySelector('.plan-admin-classes');
    const classInput = row.querySelector('.plan-class-count');
    if (!adminSelect || !classInput) {
        return;
    }
    const selectedCount = Array.from(adminSelect.selectedOptions || []).length;
    classInput.value = Math.max(1, selectedCount);
}

function refreshPlanRoomOptions(row) {
    const capacity = Number(row.querySelector('.plan-capacity')?.value || 0);
    const roomSelect = row.querySelector('.plan-room');
    if (!roomSelect) {
        return;
    }

    const currentValue = roomSelect.value;
    const scheduleSelect = row.querySelector('.plan-schedule');
    const schedule = scheduleSelect?.value || '';
    const selectedSchedule = scheduleSelect?.selectedOptions?.[0] || null;
    const startInput = row.querySelector('.plan-start-date');
    const endInput = row.querySelector('.plan-end-date');
    if (startInput && !startInput.value) {
        startInput.value = selectedSchedule?.dataset.startDate || row.dataset.startDate || '';
    }
    const startDate = startInput?.value || selectedSchedule?.dataset.startDate || row.dataset.startDate || '';
    const calculatedEndDate = planCalculateEndDate(startDate, schedule, row.dataset.totalPeriods || 0);
    const endDate = calculatedEndDate || selectedSchedule?.dataset.endDate || row.dataset.endDate || '';
    if (endInput) endInput.value = endDate;
    const dateLabel = row.querySelector('.plan-date-label');
    if (dateLabel) dateLabel.textContent = startDate && endDate ? (startDate + ' - ' + endDate) : '';
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
        const fixedUnavailable = reason && reason !== 'Không đủ sức chứa' && reason !== 'Trùng lịch';
        const tooSmall = roomCapacity < capacity;
        option.disabled = fixedUnavailable || tooSmall;

        const labelBase = option.dataset.labelBase || option.textContent;
        const status = fixedUnavailable ? reason : (tooSmall ? 'Không đủ sức chứa' : (reason === 'Trùng lịch' ? 'Kiểm tra lại khi gửi' : 'Trống'));
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
    row.dataset.roomUnavailable = firstAvailable ? '0' : '1';
}

document.querySelectorAll('.plan-row').forEach(function(row) {
    syncPlanClassCountFromAdminClasses(row);
    refreshPlanRoomOptions(row);
    refreshPlanRowState(row);
    row.querySelector('.plan-class-count')?.addEventListener('input', function() {
        refreshPlanRoomOptions(row);
        refreshPlanRowState(row);
    });
    row.querySelector('.plan-capacity')?.addEventListener('input', function() {
        refreshPlanRoomOptions(row);
        refreshPlanRowState(row);
    });
    row.querySelector('.plan-admin-classes')?.addEventListener('change', function() {
        syncPlanClassCountFromAdminClasses(row);
        refreshPlanRoomOptions(row);
        refreshPlanRowState(row);
    });
    row.querySelector('.plan-schedule')?.addEventListener('change', function() {
        const selected = this.selectedOptions?.[0] || null;
        const startInput = row.querySelector('.plan-start-date');
        if (startInput) startInput.value = selected?.dataset.startDate || row.dataset.startDate || '';
        refreshPlanRoomOptions(row);
        refreshPlanRowState(row);
    });
    row.querySelector('.plan-start-date')?.addEventListener('change', function() {
        refreshPlanRoomOptions(row);
        refreshPlanRowState(row);
    });
    row.querySelector('.plan-room')?.addEventListener('change', function() {
        refreshPlanRowState(row);
    });
    row.querySelector('.plan-teacher')?.addEventListener('change', function() {
        refreshPlanRowState(row);
    });
});
refreshPlanFilters();

const selectAllOpenProposals = document.getElementById('selectAllOpenProposals');
const bulkDeleteOpenBtn = document.getElementById('bulkDeleteOpenBtn');
function refreshBulkOpenDeleteState() {
    const checks = Array.from(document.querySelectorAll('.open-proposal-check'));
    const checked = checks.filter(function(check) { return check.checked; }).length;
    if (bulkDeleteOpenBtn) {
        bulkDeleteOpenBtn.disabled = checked === 0;
    }
    if (selectAllOpenProposals) {
        selectAllOpenProposals.checked = checks.length > 0 && checked === checks.length;
        selectAllOpenProposals.indeterminate = checked > 0 && checked < checks.length;
    }
}
selectAllOpenProposals?.addEventListener('change', function() {
    document.querySelectorAll('.open-proposal-check').forEach(function(check) {
        check.checked = selectAllOpenProposals.checked;
    });
    refreshBulkOpenDeleteState();
});
document.querySelectorAll('.open-proposal-check').forEach(function(check) {
    check.addEventListener('change', refreshBulkOpenDeleteState);
});
refreshBulkOpenDeleteState();

document.getElementById('resubmitOpenModal')?.addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('resubmit_section_id').value = btn.dataset.sectionId || '';
    document.getElementById('resubmit_subject_name').textContent = btn.dataset.subjectName || '';
    document.getElementById('resubmit_section_code').textContent = btn.dataset.sectionCode ? 'Mã lớp: ' + btn.dataset.sectionCode : '';
    document.getElementById('resubmit_max_students').value = btn.dataset.maxStudents || 70;
    document.getElementById('resubmit_day_sessions').value = btn.dataset.daySessions || '';
    document.getElementById('resubmit_note').value = btn.dataset.note || '';
});

function refreshTeacherOptions() {
    const majorSelect = document.getElementById('pt_teacher_major_filter');
    const teacherSelect = document.getElementById('pt_teacher');
    const selected = majorSelect?.selectedOptions?.[0];
    const majorName = selected?.dataset.majorName || '';
    let visible = 0;
    Array.from(teacherSelect?.options || []).forEach(function(option) {
        if (!option.value) return;
        option.hidden = !!majorName && !(option.dataset.specialization || '').includes(majorName);
        if (!option.hidden) visible++;
    });
    if (majorName && visible === 0 && majorSelect) {
        majorSelect.value = '0';
        refreshTeacherOptions();
        return;
    }
    if (teacherSelect?.selectedOptions[0]?.hidden) {
        teacherSelect.value = '';
    }
}

function refreshAssignRoomOptions(preferredRoom) {
    const scheduleSelect = document.getElementById('pt_day_sessions');
    const roomSelect = document.getElementById('pt_room');
    const capacity = Number(document.getElementById('pt_max_students')?.value || 0);
    const roomMap = window.__assignRoomOptions || {};
    const rooms = roomMap[scheduleSelect?.value || ''] || [];
    roomSelect.innerHTML = '<option value="">-- Chọn phòng --</option>';
    rooms.forEach(function(room) {
        const option = document.createElement('option');
        option.value = room.room_code || '';
        option.dataset.capacity = String(room.capacity || 0);
        option.dataset.reason = room.reason || '';
        option.textContent = (room.label || room.room_code || '') + ' - ' + (room.reason || 'Trống');
        option.disabled = !!room.reason || Number(room.capacity || 0) < capacity;
        roomSelect.appendChild(option);
    });
    const available = Array.from(roomSelect.options).find(function(option) {
        return option.value && !option.disabled && (!preferredRoom || option.value === preferredRoom);
    }) || Array.from(roomSelect.options).find(function(option) {
        return option.value && !option.disabled;
    });
    roomSelect.value = available?.value || '';
}

document.getElementById('pt_teacher_major_filter')?.addEventListener('change', refreshTeacherOptions);
document.getElementById('pt_day_sessions')?.addEventListener('change', function() {
    refreshAssignRoomOptions('');
});
document.getElementById('pt_max_students')?.addEventListener('input', function() {
    refreshAssignRoomOptions(document.getElementById('pt_room')?.value || '');
});

document.getElementById('propTeacherModal')?.addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('pt_section_id').value = btn.dataset.sectionId;
    document.getElementById('pt_section_code').textContent = 'Lớp: ' + btn.dataset.sectionCode + ' · ' + (btn.dataset.subjectName || '');
    document.getElementById('pt_max_students').value = btn.dataset.maxStudents || 40;

    const majorFilter = document.getElementById('pt_teacher_major_filter');
    if (majorFilter) {
        majorFilter.value = btn.dataset.majorId || '0';
        refreshTeacherOptions();
    }

    let scheduleOptions = [];
    try {
        scheduleOptions = JSON.parse(btn.dataset.scheduleOptions || '[]');
    } catch (_err) {
        scheduleOptions = [];
    }
    window.__assignRoomOptions = {};
    try {
        window.__assignRoomOptions = JSON.parse(btn.dataset.roomOptions || '{}');
    } catch (_err) {
        window.__assignRoomOptions = {};
    }

    const scheduleSelect = document.getElementById('pt_day_sessions');
    scheduleSelect.innerHTML = '';
    const autoScheduleOption = document.createElement('option');
    autoScheduleOption.value = '';
    autoScheduleOption.textContent = '-- Hệ thống tự xếp lịch/phòng --';
    autoScheduleOption.dataset.hint = 'Phòng Đào tạo sẽ chốt lịch theo số tiết và thời gian học kỳ.';
    scheduleSelect.appendChild(autoScheduleOption);
    scheduleOptions.forEach(function(schedule) {
        const option = document.createElement('option');
        option.value = schedule.value || '';
        option.textContent = schedule.label || schedule.value || '';
        option.dataset.startDate = schedule.start_date || '';
        option.dataset.endDate = schedule.end_date || '';
        option.dataset.hint = (schedule.total_periods || 0) + ' tiết · ' + (schedule.sessions_per_week || 1) + ' buổi/tuần · 5 tiết/buổi · ' + (schedule.start_date || '') + ' - ' + (schedule.end_date || '');
        scheduleSelect.appendChild(option);
    });
    if (btn.dataset.daySessions && Array.from(scheduleSelect.options).some(function(option) { return option.value === btn.dataset.daySessions; })) {
        scheduleSelect.value = btn.dataset.daySessions;
    }
    function syncAssignScheduleDates() {
        const option = scheduleSelect.selectedOptions[0];
        document.getElementById('pt_schedule_hint').textContent = option?.dataset.hint || '';
        document.getElementById('pt_start_date').value = option?.dataset.startDate || '';
        document.getElementById('pt_end_date').value = option?.dataset.endDate || '';
    }
    syncAssignScheduleDates();
    scheduleSelect.onchange = function() {
        syncAssignScheduleDates();
        refreshAssignRoomOptions('');
    };
    refreshAssignRoomOptions(btn.dataset.room || '');
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
