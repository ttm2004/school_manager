<?php

function academicPolicySemesterNumber(array $semester): int
{
    $name = (string)($semester['semester_name'] ?? '');
    $lowerName = mb_strtolower($name, 'UTF-8');
    if (str_contains($lowerName, 'hè') || str_contains($lowerName, 'he') || str_contains($lowerName, 'summer')) {
        return 3;
    }

    if (preg_match('/([123])/', $name, $matches)) {
        return (int)$matches[1];
    }

    return 1;
}

function academicPolicyIsTestSemester(array $semester): bool
{
    if (($semester['data_mode'] ?? 'system') === 'test') {
        return true;
    }
    $name = mb_strtolower((string)($semester['semester_name'] ?? ''), 'UTF-8');
    return str_contains($name, 'test');
}

function academicPolicySemesterDemoContext(mysqli $conn, int $semesterId): array
{
    static $cache = [];

    if (isset($cache[$semesterId])) {
        return $cache[$semesterId];
    }

    if (!academicPolicyColumnExists($conn, 'semesters', 'data_mode')) {
        return $cache[$semesterId] = ['data_mode' => 'system', 'demo_batch_id' => ''];
    }
    $batchSelect = academicPolicyColumnExists($conn, 'semesters', 'demo_batch_id') ? 'demo_batch_id' : "'' AS demo_batch_id";
    $stmt = $conn->prepare("SELECT data_mode, $batchSelect FROM semesters WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $semesterId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    return $cache[$semesterId] = [
        'data_mode' => (($row['data_mode'] ?? 'system') === 'test') ? 'test' : 'system',
        'demo_batch_id' => (string)($row['demo_batch_id'] ?? ''),
    ];
}

function academicPolicySectionDemoContext(mysqli $conn, int $sectionId): array
{
    static $cache = [];

    if (isset($cache[$sectionId])) {
        return $cache[$sectionId];
    }

    if (!academicPolicyColumnExists($conn, 'course_sections', 'data_mode')) {
        $stmt = $conn->prepare("SELECT semester_id FROM course_sections WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $sectionId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        return $cache[$sectionId] = academicPolicySemesterDemoContext($conn, (int)($row['semester_id'] ?? 0));
    }
    $batchSelect = academicPolicyColumnExists($conn, 'course_sections', 'demo_batch_id') ? 'demo_batch_id' : "'' AS demo_batch_id";
    $stmt = $conn->prepare("SELECT data_mode, $batchSelect FROM course_sections WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $sectionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    return $cache[$sectionId] = [
        'data_mode' => (($row['data_mode'] ?? 'system') === 'test') ? 'test' : 'system',
        'demo_batch_id' => (string)($row['demo_batch_id'] ?? ''),
    ];
}

function academicPolicySchoolYearStart(array $semester): ?int
{
    $schoolYear = (string)($semester['school_year'] ?? '');
    if (preg_match('/(20\d{2})/', $schoolYear, $matches)) {
        return (int)$matches[1];
    }

    return null;
}

function academicPolicySemesterWindow(int $schoolYearStart, int $semesterNumber): array
{
    if ($semesterNumber === 2) {
        return [
            'start_date' => sprintf('%04d-01-01', $schoolYearStart + 1),
            'end_date' => sprintf('%04d-05-31', $schoolYearStart + 1),
        ];
    }

    if ($semesterNumber === 3) {
        return [
            'start_date' => sprintf('%04d-06-01', $schoolYearStart + 1),
            'end_date' => sprintf('%04d-08-14', $schoolYearStart + 1),
        ];
    }

    return [
        'start_date' => sprintf('%04d-08-15', $schoolYearStart),
        'end_date' => sprintf('%04d-12-31', $schoolYearStart),
    ];
}

function academicPolicySemesterWindowFromRow(array $semester): ?array
{
    $yearStart = academicPolicySchoolYearStart($semester);
    if ($yearStart === null) {
        return null;
    }

    return academicPolicySemesterWindow($yearStart, academicPolicySemesterNumber($semester));
}

function academicPolicyClassEnrollmentYear(array $class): ?int
{
    if (!empty($class['enrollment_year']) && (int)$class['enrollment_year'] >= 2000) {
        return (int)$class['enrollment_year'];
    }

    $schoolYear = (string)($class['school_year'] ?? '');
    if (preg_match('/(20\d{2})/', $schoolYear, $matches)) {
        return (int)$matches[1];
    }

    $classCode = strtoupper((string)($class['class_code'] ?? ''));
    if (preg_match('/D(\d{2})/', $classCode, $matches)) {
        return 2000 + (int)$matches[1];
    }

    return null;
}

function academicPolicyCurriculumSemesterOrder(int $enrollmentYear, array $semester): ?int
{
    $yearStart = academicPolicySchoolYearStart($semester);
    if ($yearStart === null || $yearStart < $enrollmentYear) {
        return null;
    }

    return (($yearStart - $enrollmentYear) * 3) + academicPolicySemesterNumber($semester);
}

function academicPolicyGetSemester(mysqli $conn, int $semesterId): ?array
{
    $modeSelect = academicPolicyColumnExists($conn, 'semesters', 'data_mode') ? ', data_mode' : ", 'system' AS data_mode";
    $batchSelect = academicPolicyColumnExists($conn, 'semesters', 'demo_batch_id') ? ', demo_batch_id' : ", '' AS demo_batch_id";
    $stmt = $conn->prepare(
        "SELECT id, semester_name, school_year, start_date, end_date, status,
                proposal_start, proposal_end, approval_start, approval_end, proposal_deadline
                $modeSelect
                $batchSelect
         FROM semesters WHERE id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $semesterId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    return $row;
}

function academicPolicyWindowCheck(?string $start, ?string $end, string $label): array
{
    $now = time();
    if (!$start || !$end) {
        return ['ok' => false, 'message' => "Học kỳ chưa cấu hình thời gian {$label}."];
    }
    $startTs = strtotime($start);
    $endTs = strtotime($end);
    if (!$startTs || !$endTs) {
        return ['ok' => false, 'message' => "Thời gian {$label} không hợp lệ."];
    }
    if ($now < $startTs) {
        return ['ok' => false, 'message' => "Chưa đến thời gian {$label}. Bắt đầu: " . date('d/m/Y H:i', $startTs) . '.'];
    }
    if ($now > $endTs) {
        return ['ok' => false, 'message' => "Đã hết thời gian {$label}. Hạn cuối: " . date('d/m/Y H:i', $endTs) . '.'];
    }

    return ['ok' => true, 'message' => ''];
}

function academicPolicyCheckProposalWindow(mysqli $conn, int $semesterId): array
{
    $semester = academicPolicyGetSemester($conn, $semesterId);
    if (!$semester) {
        return ['ok' => false, 'message' => 'Học kỳ không hợp lệ.'];
    }

    return academicPolicyWindowCheck($semester['proposal_start'] ?? null, $semester['proposal_end'] ?? null, 'Khoa/Viện đề xuất mở lớp');
}

function academicPolicyCheckApprovalWindow(mysqli $conn, int $semesterId): array
{
    $semester = academicPolicyGetSemester($conn, $semesterId);
    if (!$semester) {
        return ['ok' => false, 'message' => 'Học kỳ không hợp lệ.'];
    }

    return academicPolicyWindowCheck($semester['approval_start'] ?? null, $semester['approval_end'] ?? null, 'Phòng đào tạo duyệt mở lớp');
}

function academicPolicyCheckFacultyProposalWindow(mysqli $conn, int $semesterId): array
{
    $semester = academicPolicyGetSemester($conn, $semesterId);
    if (!$semester) {
        return ['ok' => false, 'message' => 'Học kỳ không hợp lệ.'];
    }

    $start = $semester['proposal_start'] ?? null;
    $end = $semester['proposal_end'] ?? null;
    if (!$start || !$end) {
        return [
            'ok' => false,
            'message' => 'Phòng Đào tạo chưa cấu hình thời gian Khoa/Viện được phép đề xuất cho học kỳ này.',
        ];
    }

    $startTs = strtotime($start);
    $endTs = strtotime($end);
    if (!$startTs || !$endTs) {
        return ['ok' => false, 'message' => 'Thời gian Khoa/Viện được phép đề xuất không hợp lệ.'];
    }

    $now = time();
    if ($now < $startTs) {
        return [
            'ok' => false,
            'message' => 'Phòng Đào tạo chưa mở thời gian để Khoa/Viện thực hiện tác vụ cho học kỳ này. Bắt đầu: ' . date('d/m/Y H:i', $startTs) . '.',
        ];
    }
    if ($now > $endTs) {
        return [
            'ok' => false,
            'message' => 'Đã hết thời gian Khoa/Viện được phép thực hiện tác vụ cho học kỳ này. Hạn cuối: ' . date('d/m/Y H:i', $endTs) . '.',
        ];
    }

    return ['ok' => true, 'message' => ''];
}

function academicPolicyColumnExists(mysqli $conn, string $table, string $column): bool
{
    static $cache = [];

    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$safeTable` LIKE '$safeColumn'");
    $cache[$key] = $res && $res->num_rows > 0;

    return $cache[$key];
}

function academicPolicyNormalizeScheduleSession(string $session): string
{
    $value = mb_strtolower(trim($session), 'UTF-8');
    $ascii = strtr($value, [
        'á' => 'a', 'à' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a',
        'ă' => 'a', 'ắ' => 'a', 'ằ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a',
        'â' => 'a', 'ấ' => 'a', 'ầ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
        'é' => 'e', 'è' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e',
        'ê' => 'e', 'ế' => 'e', 'ề' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
        'í' => 'i', 'ì' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o',
        'ô' => 'o', 'ố' => 'o', 'ồ' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ộ' => 'o',
        'ơ' => 'o', 'ớ' => 'o', 'ờ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u',
        'ư' => 'u', 'ứ' => 'u', 'ừ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
        'ý' => 'y', 'ỳ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
        'đ' => 'd',
    ]);

    return match ($ascii) {
        'sang', 'morning', 'am' => 'sang',
        'chieu', 'afternoon', 'pm' => 'chieu',
        'toi', 'tối', 'evening', 'night' => 'toi',
        default => $ascii,
    };
}

function academicPolicyScheduleTokens(?string $daySessions): array
{
    $value = trim((string)$daySessions);
    if ($value === '') {
        return [];
    }

    $tokens = [];
    foreach (preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
        [$day, $session] = array_pad(explode(':', trim($token), 2), 2, '');
        $day = preg_replace('/\D+/', '', $day);
        $session = academicPolicyNormalizeScheduleSession($session);
        if ($day !== '' && $session !== '') {
            $tokens[] = $day . ':' . $session;
        }
    }

    return array_values(array_unique($tokens));
}

function academicPolicyHasScheduleConflict(mysqli $conn, int $sectionId, string $field, mixed $fieldValue, int $semesterId, ?string $daySessions): bool
{
    if ($fieldValue === null || $fieldValue === '' || !in_array($field, ['teacher_id', 'room'], true)) {
        return false;
    }

    $tokens = academicPolicyScheduleTokens($daySessions);
    if (empty($tokens)) {
        return false;
    }

    $demoContext = $sectionId > 0
        ? academicPolicySectionDemoContext($conn, $sectionId)
        : academicPolicySemesterDemoContext($conn, $semesterId);
    $modeSql = academicPolicyColumnExists($conn, 'course_sections', 'data_mode') ? " AND data_mode = ?" : "";

    $stmt = $conn->prepare(
        "SELECT id, day_sessions
         FROM course_sections
         WHERE id <> ?
           AND semester_id = ?
           AND status IN ('open','full','closed')
           AND $field = ?
           AND day_sessions IS NOT NULL
           AND day_sessions <> ''
           $modeSql"
    );

    if ($field === 'teacher_id') {
        $intValue = (int)$fieldValue;
        if ($modeSql) {
            $stmt->bind_param('iiis', $sectionId, $semesterId, $intValue, $demoContext['data_mode']);
        } else {
            $stmt->bind_param('iii', $sectionId, $semesterId, $intValue);
        }
    } else {
        $stringValue = (string)$fieldValue;
        if ($modeSql) {
            $stmt->bind_param('iiss', $sectionId, $semesterId, $stringValue, $demoContext['data_mode']);
        } else {
            $stmt->bind_param('iis', $sectionId, $semesterId, $stringValue);
        }
    }

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

function academicPolicyGetClassroom(mysqli $conn, string $roomCode): ?array
{
    if ($roomCode === '' || $conn->query("SHOW TABLES LIKE 'classrooms'")->num_rows === 0) {
        return null;
    }

    $stmt = $conn->prepare("SELECT * FROM classrooms WHERE room_code = ? LIMIT 1");
    $stmt->bind_param('s', $roomCode);
    $stmt->execute();
    $room = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    return $room;
}

function academicPolicySubjectNeedsSpecialRoom(mysqli $conn, int $subjectId, string $roomRequirement = ''): bool
{
    $stmtSubject = $conn->prepare("SELECT theory_periods, practice_periods FROM subjects WHERE id = ? LIMIT 1");
    $stmtSubject->bind_param('i', $subjectId);
    $stmtSubject->execute();
    $subject = $stmtSubject->get_result()->fetch_assoc() ?: [];
    $stmtSubject->close();

    $requirement = mb_strtolower($roomRequirement, 'UTF-8');

    return str_contains($requirement, 'lab')
        || str_contains($requirement, 'máy')
        || str_contains($requirement, 'may')
        || str_contains($requirement, 'thực hành')
        || str_contains($requirement, 'thuc hanh')
        || ((int)($subject['practice_periods'] ?? 0) > (int)($subject['theory_periods'] ?? 0));
}

function academicPolicyRoomTypePriority(string $roomType, bool $needsSpecialRoom): int
{
    if ($needsSpecialRoom) {
        return match ($roomType) {
            'computer_lab' => 1,
            'lab' => 2,
            'other' => 3,
            default => 99,
        };
    }

    return match ($roomType) {
        'theory' => 1,
        'other' => 2,
        'lab', 'computer_lab' => 3,
        default => 99,
    };
}

function academicPolicyClassroomIsSuitable(array $room, int $maxStudents, bool $needsSpecialRoom): bool
{
    if (($room['status'] ?? '') !== 'active') {
        return false;
    }
    if ((int)($room['capacity'] ?? 0) < $maxStudents) {
        return false;
    }
    if (($room['room_type'] ?? '') === 'online') {
        return false;
    }
    if ($needsSpecialRoom && !in_array($room['room_type'] ?? '', ['lab', 'computer_lab', 'other'], true)) {
        return false;
    }

    return true;
}

function academicPolicyFindAvailableClassrooms(
    mysqli $conn,
    int $sectionId,
    int $semesterId,
    int $subjectId,
    int $maxStudents,
    string $teachingMode,
    ?string $daySessions,
    string $roomRequirement = ''
): array {
    if ($teachingMode === 'online' || $conn->query("SHOW TABLES LIKE 'classrooms'")->num_rows === 0) {
        return [];
    }

    $needsSpecialRoom = academicPolicySubjectNeedsSpecialRoom($conn, $subjectId, $roomRequirement);
    $stmt = $conn->prepare(
        "SELECT id, room_code, room_name, building, room_type, capacity, status, note
         FROM classrooms
         WHERE status = 'active' AND capacity >= ?
         ORDER BY capacity ASC, room_code ASC"
    );
    $stmt->bind_param('i', $maxStudents);
    $stmt->execute();
    $rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $available = [];
    foreach ($rooms as $room) {
        if (!academicPolicyClassroomIsSuitable($room, $maxStudents, $needsSpecialRoom)) {
            continue;
        }
        if (academicPolicyHasScheduleConflict($conn, $sectionId, 'room', $room['room_code'], $semesterId, $daySessions)) {
            continue;
        }

        $room['priority'] = academicPolicyRoomTypePriority((string)$room['room_type'], $needsSpecialRoom);
        $available[] = $room;
    }

    usort($available, static function (array $a, array $b): int {
        return [$a['priority'], (int)$a['capacity'], $a['room_code']]
            <=> [$b['priority'], (int)$b['capacity'], $b['room_code']];
    });

    return $available;
}

function academicPolicyValidateTeachingSchedule(string $teachingMode, ?string $daySessions): array
{
    if ($teachingMode === 'online') {
        return ['ok' => true, 'message' => ''];
    }

    if (empty(academicPolicyScheduleTokens($daySessions))) {
        return ['ok' => false, 'message' => 'Lớp offline/hybrid phải có lịch học theo ca sáng/chiều/tối trước khi xếp phòng.'];
    }

    return ['ok' => true, 'message' => ''];
}

function academicPolicyValidateRoom(mysqli $conn, string $roomCode, int $maxStudents, int $subjectId, string $teachingMode, string $roomRequirement = ''): array
{
    if ($teachingMode === 'online') {
        return ['ok' => true, 'message' => '', 'classroom_id' => null];
    }
    if ($roomCode === '') {
        return ['ok' => false, 'message' => 'Vui lòng chọn phòng học cho lớp offline/hybrid.', 'classroom_id' => null];
    }

    $room = academicPolicyGetClassroom($conn, $roomCode);
    if (!$room) {
        return ['ok' => false, 'message' => 'Phòng học không tồn tại trong danh mục phòng.', 'classroom_id' => null];
    }
    if (($room['status'] ?? '') !== 'active') {
        return ['ok' => false, 'message' => 'Phòng học đang không khả dụng.', 'classroom_id' => null];
    }
    if ((int)$room['capacity'] < $maxStudents) {
        return ['ok' => false, 'message' => "Sức chứa phòng {$room['room_code']} chỉ {$room['capacity']} sinh viên, nhỏ hơn sĩ số tối đa {$maxStudents}.", 'classroom_id' => null];
    }

    $subject = null;
    $stmtSubject = $conn->prepare("SELECT theory_periods, practice_periods FROM subjects WHERE id = ? LIMIT 1");
    $stmtSubject->bind_param('i', $subjectId);
    $stmtSubject->execute();
    $subject = $stmtSubject->get_result()->fetch_assoc();
    $stmtSubject->close();

    $requirement = mb_strtolower($roomRequirement, 'UTF-8');
    $needsLab = str_contains($requirement, 'lab')
        || str_contains($requirement, 'máy')
        || str_contains($requirement, 'may')
        || str_contains($requirement, 'thực hành')
        || str_contains($requirement, 'thuc hanh')
        || ((int)($subject['practice_periods'] ?? 0) > (int)($subject['theory_periods'] ?? 0));

    if ($needsLab && !in_array($room['room_type'], ['lab', 'computer_lab', 'other'], true)) {
        return ['ok' => false, 'message' => 'Môn/lớp có yêu cầu thực hành nhưng phòng được chọn không phải phòng lab/phòng máy.', 'classroom_id' => null];
    }

    return ['ok' => true, 'message' => '', 'classroom_id' => (int)$room['id']];
}

function academicPolicyFindEligibleOpenings(mysqli $conn, int $facultyId, int $semesterId, ?int $subjectId = null, ?int $cohortId = null, ?int $maxRows = null): array
{
    $semester = academicPolicyGetSemester($conn, $semesterId);
    if (!$semester) {
        return [];
    }

    $hasCohorts = $conn->query("SHOW TABLES LIKE 'training_cohorts'")->num_rows > 0;
    $hasCurriculumProgram = academicPolicyColumnExists($conn, 'curriculum', 'program_id');
    if ($hasCohorts) {
        $sqlCohorts = "SELECT tc.id AS cohort_id, tc.major_id, tc.enrollment_year, tc.program_id,
                              tc.cohort_code, tc.cohort_name, tc.duration_years,
                              m.major_code, m.major_name
                       FROM training_cohorts tc
                       JOIN majors m ON tc.major_id = m.id
                       WHERE m.faculty_id = ? AND tc.status IN ('planned','active')";
        if ($cohortId !== null) {
            $sqlCohorts .= " AND tc.id = ?";
        }
        $sqlCohorts .= " ORDER BY tc.enrollment_year DESC, m.major_name ASC";
        $stmtCohorts = $conn->prepare($sqlCohorts);
        if ($cohortId !== null) {
            $stmtCohorts->bind_param('ii', $facultyId, $cohortId);
        } else {
            $stmtCohorts->bind_param('i', $facultyId);
        }
        $stmtCohorts->execute();
        $cohorts = $stmtCohorts->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtCohorts->close();
    } else {
        $stmtClasses = $conn->prepare(
            "SELECT cl.id, cl.class_code, cl.class_name, cl.school_year, cl.major_id,
                    m.major_code, m.major_name
             FROM classes cl
             JOIN majors m ON cl.major_id = m.id
             WHERE m.faculty_id = ?
             ORDER BY cl.school_year DESC, cl.class_code ASC"
        );
        $stmtClasses->bind_param('i', $facultyId);
        $stmtClasses->execute();
        $classes = $stmtClasses->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtClasses->close();

        $cohorts = [];
        foreach ($classes as $class) {
            $enrollmentYear = academicPolicyClassEnrollmentYear($class);
            if ($enrollmentYear === null) {
                continue;
            }
            $cohorts[] = [
                'cohort_id' => null,
                'major_id' => (int)$class['major_id'],
                'enrollment_year' => $enrollmentYear,
                'program_id' => null,
                'cohort_code' => $class['class_code'],
                'cohort_name' => $class['class_name'],
                'duration_years' => 4,
                'major_code' => $class['major_code'],
                'major_name' => $class['major_name'],
            ];
        }
    }

    $rows = [];
    $seen = [];

    $isTestSemester = academicPolicyIsTestSemester($semester);
    if ($isTestSemester && $subjectId === null && $cohortId === null) {
        $testMajorCodes = ['CNTT' => true, 'KETOAN' => true, '7480201' => true, '7340301' => true];
        $allowedMajorIds = [];
        $filteredCohorts = [];
        foreach ($cohorts as $cohort) {
            $majorId = (int)($cohort['major_id'] ?? 0);
            $majorCode = strtoupper((string)($cohort['major_code'] ?? ''));
            if ($majorId <= 0 || !isset($testMajorCodes[$majorCode])) {
                continue;
            }
            $allowedMajorIds[$majorId] = true;
            $filteredCohorts[] = $cohort;
        }
        if (empty($filteredCohorts)) {
            foreach ($cohorts as $cohort) {
                $majorId = (int)($cohort['major_id'] ?? 0);
                if ($majorId <= 0) {
                    continue;
                }
                if (!isset($allowedMajorIds[$majorId]) && count($allowedMajorIds) >= 2) {
                    continue;
                }
                $allowedMajorIds[$majorId] = true;
                $filteredCohorts[] = $cohort;
            }
        }
        $cohorts = $filteredCohorts;
    }

    foreach ($cohorts as $cohort) {
        $enrollmentYear = (int)$cohort['enrollment_year'];
        $semesterOrder = academicPolicyCurriculumSemesterOrder($enrollmentYear, $semester);
        if (!$isTestSemester && ($semesterOrder === null || $semesterOrder < 1)) {
            continue;
        }

        $majorId = (int)$cohort['major_id'];
        $programId = (int)($cohort['program_id'] ?? 0);
        $sql = "SELECT DISTINCT s.id AS subject_id, s.subject_code, s.subject_name, s.credits,
                       c.suggested_semester, c.subject_type, c.prerequisite_ids,
                       m.id AS major_id, m.major_code, m.major_name
                FROM curriculum c
                JOIN subjects s ON c.subject_id = s.id
                JOIN majors m ON c.major_id = m.id
                WHERE c.major_id = ? AND c.deleted_at IS NULL";
        if (!$isTestSemester) {
            $sql .= " AND c.suggested_semester = ?";
        }
        if ($hasCurriculumProgram) {
            $sql .= " AND (c.program_id IS NULL OR c.program_id = ?)";
        }
        if ($subjectId !== null) {
            $sql .= " AND s.id = ?";
        }
        $sql .= " ORDER BY c.suggested_semester ASC, s.subject_name ASC";

        $stmtCur = $conn->prepare($sql);
        if ($isTestSemester) {
            if ($hasCurriculumProgram && $subjectId !== null) {
                $stmtCur->bind_param('iii', $majorId, $programId, $subjectId);
            } elseif ($hasCurriculumProgram) {
                $stmtCur->bind_param('ii', $majorId, $programId);
            } elseif ($subjectId !== null) {
                $stmtCur->bind_param('ii', $majorId, $subjectId);
            } else {
                $stmtCur->bind_param('i', $majorId);
            }
        } else {
            if ($hasCurriculumProgram && $subjectId !== null) {
                $stmtCur->bind_param('iiii', $majorId, $semesterOrder, $programId, $subjectId);
            } elseif ($hasCurriculumProgram) {
                $stmtCur->bind_param('iii', $majorId, $semesterOrder, $programId);
            } elseif ($subjectId !== null) {
                $stmtCur->bind_param('iii', $majorId, $semesterOrder, $subjectId);
            } else {
                $stmtCur->bind_param('ii', $majorId, $semesterOrder);
            }
        }
        $stmtCur->execute();
        $subjects = $stmtCur->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtCur->close();

        foreach ($subjects as $subject) {
            $effectiveSemesterOrder = $isTestSemester ? (int)$subject['suggested_semester'] : (int)$semesterOrder;
            $key = $subject['subject_id'] . ':' . $majorId . ':' . $effectiveSemesterOrder . ':' . ($cohort['cohort_id'] ?? 'legacy');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $subject['cohort_id'] = $cohort['cohort_id'] !== null ? (int)$cohort['cohort_id'] : null;
            $subject['program_id'] = $programId ?: null;
            $subject['cohort_code'] = $cohort['cohort_code'];
            $subject['cohort_name'] = $cohort['cohort_name'];
            $subject['enrollment_year'] = $enrollmentYear;
            $subject['graduation_year'] = $enrollmentYear + (int)ceil((float)($cohort['duration_years'] ?? 4));
            $subject['semester_window'] = academicPolicySemesterWindowFromRow($semester);
            $rows[] = $subject;
            if ($maxRows !== null && count($rows) >= $maxRows) {
                return $rows;
            }
        }
    }

    return $rows;
}

function academicPolicyValidateSubjectOpening(mysqli $conn, int $facultyId, int $subjectId, int $semesterId, ?int $cohortId = null): array
{
    $eligible = academicPolicyFindEligibleOpenings($conn, $facultyId, $semesterId, $subjectId, $cohortId);
    if ($eligible) {
        return ['ok' => true, 'message' => '', 'eligible' => $eligible];
    }

    return [
        'ok' => false,
        'message' => 'Môn học này không thuộc học kỳ CTĐT tương ứng với khóa tuyển sinh của khoa trong học kỳ đã chọn.',
        'eligible' => [],
    ];
}

function academicPolicyValidateSectionOpening(mysqli $conn, int $sectionId): array
{
    $subjectActiveSelect = academicPolicyColumnExists($conn, 'subjects', 'is_active')
        ? 's.is_active AS subject_is_active,'
        : '1 AS subject_is_active,';
    $minStudentsSelect = academicPolicyColumnExists($conn, 'course_sections', 'min_students')
        ? 'cs.min_students,'
        : '20 AS min_students,';

    $stmt = $conn->prepare(
        "SELECT cs.id, cs.subject_id, cs.semester_id, cs.target_cohort_id, cs.open_proposed_by,
                cs.expected_students, cs.max_students, cs.teacher_id, cs.proposed_teacher_id,
                cs.room, cs.room_requirement, cs.day_sessions, cs.teaching_mode,
                $minStudentsSelect
                s.subject_name, s.subject_code, $subjectActiveSelect
                sm.status AS semester_status, sm.proposal_deadline,
                f.id AS faculty_id,
                tf.id AS proposer_faculty_id
         FROM course_sections cs
         JOIN subjects s ON cs.subject_id = s.id
         JOIN semesters sm ON cs.semester_id = sm.id
         JOIN curriculum cur ON cs.subject_id = cur.subject_id AND cur.deleted_at IS NULL
         JOIN majors m ON cur.major_id = m.id
         JOIN faculties f ON m.faculty_id = f.id
         LEFT JOIN teachers pt ON pt.user_id = cs.open_proposed_by
         LEFT JOIN faculties tf ON pt.faculty_id = tf.id
         WHERE cs.id = ?
         LIMIT 1"
    );
    $stmt->bind_param('i', $sectionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['ok' => false, 'message' => 'Không tìm thấy thông tin khoa/ngành của lớp học phần.', 'eligible' => []];
    }

    if ((int)$row['subject_is_active'] !== 1) {
        return ['ok' => false, 'message' => 'Môn học đang ngừng hoạt động, không thể duyệt mở lớp.', 'eligible' => []];
    }

    if (!empty($row['proposer_faculty_id']) && (int)$row['proposer_faculty_id'] !== (int)$row['faculty_id']) {
        return ['ok' => false, 'message' => 'Khoa đề xuất không có quyền mở môn học này.', 'eligible' => []];
    }

    if (($row['semester_status'] ?? '') === 'closed') {
        return ['ok' => false, 'message' => 'Học kỳ đã kết thúc, không thể duyệt đề xuất mở lớp.', 'eligible' => []];
    }

    $expectedStudents = (int)($row['expected_students'] ?: $row['max_students'] ?: 0);
    $minStudents = max(1, (int)($row['min_students'] ?? 20));
    if ($expectedStudents < $minStudents) {
        return [
            'ok' => false,
            'message' => "Sĩ số dự kiến phải từ {$minStudents} sinh viên trở lên.",
            'eligible' => [],
        ];
    }

    $teacherId = (int)($row['teacher_id'] ?: $row['proposed_teacher_id'] ?: 0);
    if ($teacherId > 0 && academicPolicyHasScheduleConflict($conn, $sectionId, 'teacher_id', $teacherId, (int)$row['semester_id'], $row['day_sessions'] ?? '')) {
        return ['ok' => false, 'message' => 'Giảng viên đang bị trùng lịch trong học kỳ này.', 'eligible' => []];
    }

    if (academicPolicyHasScheduleConflict($conn, $sectionId, 'room', $row['room'] ?? '', (int)$row['semester_id'], $row['day_sessions'] ?? '')) {
        return ['ok' => false, 'message' => 'Phòng học đang bị trùng lịch trong học kỳ này.', 'eligible' => []];
    }

    $policyCheck = academicPolicyValidateSubjectOpening(
        $conn,
        (int)$row['faculty_id'],
        (int)$row['subject_id'],
        (int)$row['semester_id'],
        !empty($row['target_cohort_id']) ? (int)$row['target_cohort_id'] : null
    );
    if (!$policyCheck['ok']) {
        return $policyCheck;
    }

    $policyCheck['section'] = $row;
    return $policyCheck;
}

function academicPolicyGetStudentContext(mysqli $conn, int $studentId): ?array
{
    $stmt = $conn->prepare(
        "SELECT s.id, s.class_id, s.student_code, s.academic_status, s.enrollment_year,
                s.cohort_id, s.training_program_id, s.data_mode,
                cl.major_id, cl.cohort_id AS class_cohort_id
         FROM students s
         JOIN classes cl ON s.class_id = cl.id
         WHERE s.id = ?
         LIMIT 1"
    );
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    return $row;
}

function academicPolicySubjectPassed(mysqli $conn, int $studentId, int $subjectId): bool
{
    $stmt = $conn->prepare(
        "SELECT g.id
         FROM student_subjects ss
         JOIN course_sections cs ON ss.course_section_id = cs.id
         JOIN grades g ON g.student_subject_id = ss.id
         WHERE ss.student_id = ?
           AND cs.subject_id = ?
           AND g.final_score >= 5.0
         LIMIT 1"
    );
    $stmt->bind_param('ii', $studentId, $subjectId);
    $stmt->execute();
    $passed = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $passed;
}

function academicPolicyRegisteredCredits(mysqli $conn, int $studentId, int $semesterId): int
{
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(subj.credits), 0) AS credits
         FROM student_subjects ss
         JOIN course_sections cs ON ss.course_section_id = cs.id
         JOIN subjects subj ON cs.subject_id = subj.id
         WHERE ss.student_id = ?
           AND ss.status IN ('registered','auto_enrolled')
           AND cs.semester_id = ?"
    );
    $stmt->bind_param('ii', $studentId, $semesterId);
    $stmt->execute();
    $credits = (int)($stmt->get_result()->fetch_assoc()['credits'] ?? 0);
    $stmt->close();

    return $credits;
}

function academicPolicyStudentScheduleConflict(mysqli $conn, int $studentId, int $sectionId): array
{
    $stmtSection = $conn->prepare(
        "SELECT cs.id, cs.semester_id, cs.day_sessions, s.subject_name, cs.section_code
         FROM course_sections cs
         JOIN subjects s ON cs.subject_id = s.id
         WHERE cs.id = ?
         LIMIT 1"
    );
    $stmtSection->bind_param('i', $sectionId);
    $stmtSection->execute();
    $section = $stmtSection->get_result()->fetch_assoc();
    $stmtSection->close();

    if (!$section) {
        return ['ok' => false, 'message' => 'Không tìm thấy lớp học phần cần đăng ký.', 'conflicts' => []];
    }

    $newTokens = academicPolicyScheduleTokens($section['day_sessions'] ?? '');
    if (empty($newTokens)) {
        return ['ok' => true, 'message' => '', 'conflicts' => []];
    }

    $stmtRegistered = $conn->prepare(
        "SELECT cs.id, cs.day_sessions, cs.section_code, s.subject_name
         FROM student_subjects ss
         JOIN course_sections cs ON ss.course_section_id = cs.id
         JOIN subjects s ON cs.subject_id = s.id
         WHERE ss.student_id = ?
           AND ss.status IN ('registered','auto_enrolled')
           AND cs.semester_id = ?
           AND cs.id <> ?"
    );
    $stmtRegistered->bind_param('iii', $studentId, $section['semester_id'], $sectionId);
    $stmtRegistered->execute();
    $registered = $stmtRegistered->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtRegistered->close();

    $dayNames = [2 => 'Thứ 2', 3 => 'Thứ 3', 4 => 'Thứ 4', 5 => 'Thứ 5', 6 => 'Thứ 6', 7 => 'Thứ 7', 8 => 'Chủ nhật'];
    $sessionNames = ['sang' => 'Sáng', 'chieu' => 'Chiều', 'toi' => 'Tối'];
    $conflicts = [];

    foreach ($registered as $row) {
        foreach (array_intersect($newTokens, academicPolicyScheduleTokens($row['day_sessions'] ?? '')) as $token) {
            [$day, $session] = array_pad(explode(':', $token, 2), 2, '');
            $conflicts[] = [
                'section_code' => $row['section_code'],
                'subject_name' => $row['subject_name'],
                'slot' => ($dayNames[(int)$day] ?? ('Ngày ' . $day)) . ' ' . ($sessionNames[$session] ?? $session),
            ];
        }
    }

    if (!empty($conflicts)) {
        $first = $conflicts[0];
        return [
            'ok' => false,
            'message' => "Trùng lịch {$first['slot']} với môn {$first['subject_name']} ({$first['section_code']}).",
            'conflicts' => $conflicts,
        ];
    }

    return ['ok' => true, 'message' => '', 'conflicts' => []];
}

function academicPolicyValidateExamSchedule(
    mysqli $conn,
    int $courseSectionId,
    string $examDate,
    string $startTime,
    string $endTime,
    string $room,
    int $ignoreExamId = 0
): array {
    if ($courseSectionId <= 0 || $examDate === '' || $startTime === '' || $endTime === '') {
        return ['ok' => false, 'message' => 'Vui lòng nhập đầy đủ lớp học phần, ngày thi và giờ thi.'];
    }
    if ($startTime >= $endTime) {
        return ['ok' => false, 'message' => 'Giờ kết thúc thi phải sau giờ bắt đầu.'];
    }

    $stmtSection = $conn->prepare(
        "SELECT cs.id, cs.semester_id, cs.section_code, s.subject_name, sm.end_date
         FROM course_sections cs
         JOIN subjects s ON cs.subject_id = s.id
         JOIN semesters sm ON cs.semester_id = sm.id
         WHERE cs.id = ?
         LIMIT 1"
    );
    $stmtSection->bind_param('i', $courseSectionId);
    $stmtSection->execute();
    $section = $stmtSection->get_result()->fetch_assoc();
    $stmtSection->close();
    if (!$section) {
        return ['ok' => false, 'message' => 'Không tìm thấy lớp học phần cần xếp lịch thi.'];
    }
    if (!empty($section['end_date']) && $examDate <= $section['end_date']) {
        return ['ok' => false, 'message' => 'Ngày thi phải sau ngày kết thúc học kỳ (' . date('d/m/Y', strtotime($section['end_date'])) . ').'];
    }

    $ignoreSql = $ignoreExamId > 0 ? ' AND fes.id <> ?' : '';
    if ($room !== '') {
        $sqlRoom = "SELECT cs.section_code
                    FROM final_exam_schedules fes
                    JOIN course_sections cs ON fes.course_section_id = cs.id
                    WHERE fes.exam_date = ?
                      AND fes.room = ?
                      AND fes.status IN ('scheduled','postponed')
                      AND NOT (fes.end_time <= ? OR fes.start_time >= ?)
                      $ignoreSql
                    LIMIT 1";
        $stmtRoom = $conn->prepare($sqlRoom);
        if ($ignoreExamId > 0) {
            $stmtRoom->bind_param('ssssi', $examDate, $room, $startTime, $endTime, $ignoreExamId);
        } else {
            $stmtRoom->bind_param('ssss', $examDate, $room, $startTime, $endTime);
        }
        $stmtRoom->execute();
        $roomConflict = $stmtRoom->get_result()->fetch_assoc();
        $stmtRoom->close();
        if ($roomConflict) {
            return ['ok' => false, 'message' => 'Phòng thi đã trùng lịch với lớp ' . $roomConflict['section_code'] . '.'];
        }
    }

    $sqlStudent = "SELECT DISTINCT cs.section_code, s.subject_name
                   FROM student_subjects ss_new
                   JOIN student_subjects ss_old ON ss_old.student_id = ss_new.student_id
                   JOIN final_exam_schedules fes ON fes.course_section_id = ss_old.course_section_id
                   JOIN course_sections cs ON cs.id = fes.course_section_id
                   JOIN subjects s ON s.id = cs.subject_id
                   WHERE ss_new.course_section_id = ?
                     AND ss_new.status IN ('registered','auto_enrolled')
                     AND ss_old.status IN ('registered','auto_enrolled')
                     AND ss_old.course_section_id <> ss_new.course_section_id
                     AND fes.exam_date = ?
                     AND fes.status IN ('scheduled','postponed')
                     AND NOT (fes.end_time <= ? OR fes.start_time >= ?)
                     $ignoreSql
                   LIMIT 1";
    $stmtStudent = $conn->prepare($sqlStudent);
    if ($ignoreExamId > 0) {
        $stmtStudent->bind_param('isssi', $courseSectionId, $examDate, $startTime, $endTime, $ignoreExamId);
    } else {
        $stmtStudent->bind_param('isss', $courseSectionId, $examDate, $startTime, $endTime);
    }
    $stmtStudent->execute();
    $studentConflict = $stmtStudent->get_result()->fetch_assoc();
    $stmtStudent->close();
    if ($studentConflict) {
        return ['ok' => false, 'message' => 'Có sinh viên bị trùng lịch thi với môn ' . $studentConflict['subject_name'] . ' (' . $studentConflict['section_code'] . ').'];
    }

    return ['ok' => true, 'message' => ''];
}

function academicPolicyValidateStudentRegistration(mysqli $conn, int $studentId, int $sectionId, int $maxCredits = 25): array
{
    $student = academicPolicyGetStudentContext($conn, $studentId);
    if (!$student) {
        return ['ok' => false, 'message' => 'Không tìm thấy hồ sơ sinh viên.'];
    }

    if (($student['academic_status'] ?? '') !== 'Đang học') {
        return ['ok' => false, 'message' => 'Sinh viên không ở trạng thái đang học nên không được đăng ký học phần.'];
    }

    $stmt = $conn->prepare(
        "SELECT cs.id, cs.subject_id, cs.semester_id, cs.target_cohort_id, cs.current_students, cs.max_students,
                cs.status, subj.credits,
                sm.semester_name, sm.school_year, sm.data_mode AS semester_data_mode
         FROM course_sections cs
         JOIN subjects subj ON cs.subject_id = subj.id
         JOIN semesters sm ON cs.semester_id = sm.id
         WHERE cs.id = ?
         LIMIT 1"
    );
    $stmt->bind_param('i', $sectionId);
    $stmt->execute();
    $section = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$section || !in_array($section['status'], ['open','full'], true)) {
        return ['ok' => false, 'message' => 'Lớp học phần chưa mở đăng ký.'];
    }
    $studentDataMode = ($student['data_mode'] ?? 'system') === 'test' ? 'test' : 'system';
    $isTestSemester = (($section['semester_data_mode'] ?? 'system') === 'test') || str_contains(strtolower((string)$section['semester_name']), 'test');
    if ($studentDataMode === 'test' && !$isTestSemester) {
        return ['ok' => false, 'message' => 'Tai khoan test chi duoc dang ky hoc phan trong hoc ky Test.'];
    }
    if ($studentDataMode !== 'test' && $isTestSemester) {
        return ['ok' => false, 'message' => 'Hoc ky Test chi danh cho du lieu demo, khong ap dung cho sinh vien he thong that.'];
    }
    if ((int)$section['current_students'] >= (int)$section['max_students']) {
        return ['ok' => false, 'message' => 'Lớp học phần đã đủ sĩ số.'];
    }

    $stmtRegistrationWindow = $conn->prepare(
        "SELECT id
         FROM semesters
         WHERE id = ?
           AND status = 'open'
           AND register_start <= NOW()
           AND register_end >= NOW()
         LIMIT 1"
    );
    $stmtRegistrationWindow->bind_param('i', $section['semester_id']);
    $stmtRegistrationWindow->execute();
    $registrationOpen = $stmtRegistrationWindow->get_result()->num_rows > 0;
    $stmtRegistrationWindow->close();
    if (!$registrationOpen) {
        return ['ok' => false, 'message' => 'Hiện tại không trong thời gian đăng ký học phần của học kỳ này.'];
    }

    $enrollmentYear = (int)($student['enrollment_year'] ?? 0);
    $semesterOrder = $enrollmentYear > 0 ? academicPolicyCurriculumSemesterOrder($enrollmentYear, $section) : null;
    if ($semesterOrder === 1) {
        return ['ok' => false, 'message' => 'Hoc ky 1 nam nhat duoc he thong dang ky tu dong. Ban chi co the xem danh sach hoc phan da duoc xep.'];
    }

    $studentCohortId = (int)($student['cohort_id'] ?: $student['class_cohort_id'] ?: 0);
    if (!empty($section['target_cohort_id']) && $studentCohortId > 0 && (int)$section['target_cohort_id'] !== $studentCohortId) {
        return ['ok' => false, 'message' => 'Lớp học phần này không mở cho khóa tuyển sinh của bạn.'];
    }

    $programCondition = '';
    $programId = (int)($student['training_program_id'] ?? 0);
    if (academicPolicyColumnExists($conn, 'curriculum', 'program_id') && $programId > 0) {
        $programCondition = ' AND (program_id IS NULL OR program_id = ?)';
    }

    $sqlCur = "SELECT prerequisite_ids, suggested_semester, allow_off_semester
               FROM curriculum
               WHERE major_id = ? AND subject_id = ? AND deleted_at IS NULL $programCondition
               LIMIT 1";
    $stmtCur = $conn->prepare($sqlCur);
    $majorId = (int)$student['major_id'];
    $subjectId = (int)$section['subject_id'];
    if ($programCondition) {
        $stmtCur->bind_param('iii', $majorId, $subjectId, $programId);
    } else {
        $stmtCur->bind_param('ii', $majorId, $subjectId);
    }
    $stmtCur->execute();
    $curriculum = $stmtCur->get_result()->fetch_assoc();
    $stmtCur->close();

    if (!$curriculum) {
        return ['ok' => false, 'message' => 'Môn học không thuộc chương trình đào tạo của bạn.'];
    }

    $stmtDuplicate = $conn->prepare(
        "SELECT cs.section_code
         FROM student_subjects ss
         JOIN course_sections cs ON ss.course_section_id = cs.id
         WHERE ss.student_id = ?
           AND ss.status <> 'cancelled'
           AND cs.semester_id = ?
           AND cs.subject_id = ?
         LIMIT 1"
    );
    $stmtDuplicate->bind_param('iii', $studentId, $section['semester_id'], $subjectId);
    $stmtDuplicate->execute();
    $duplicate = $stmtDuplicate->get_result()->fetch_assoc();
    $stmtDuplicate->close();
    if ($duplicate) {
        return ['ok' => false, 'message' => 'Bạn đã đăng ký môn học này ở lớp ' . $duplicate['section_code'] . ' trong cùng học kỳ.'];
    }

    if (academicPolicySubjectPassed($conn, $studentId, $subjectId)) {
        return ['ok' => false, 'message' => 'Bạn đã đạt môn này. Đăng ký học cải thiện cần quy trình riêng của Phòng đào tạo.'];
    }

    $prereqIds = array_filter(array_map('intval', preg_split('/\s*,\s*/', (string)($curriculum['prerequisite_ids'] ?? ''), -1, PREG_SPLIT_NO_EMPTY)));
    foreach ($prereqIds as $prereqId) {
        if (!academicPolicySubjectPassed($conn, $studentId, $prereqId)) {
            return ['ok' => false, 'message' => 'Bạn chưa đạt môn tiên quyết nên không thể đăng ký học phần này.'];
        }
    }

    $currentOrder = $enrollmentYear > 0 ? academicPolicyCurriculumSemesterOrder($enrollmentYear, $section) : null;
    if ($currentOrder !== null
        && (int)$curriculum['suggested_semester'] > $currentOrder
        && (int)($curriculum['allow_off_semester'] ?? 0) !== 1
    ) {
        return ['ok' => false, 'message' => 'Môn học thuộc học kỳ sau. Học vượt cần Phòng đào tạo cho phép.'];
    }

    $scheduleCheck = academicPolicyStudentScheduleConflict($conn, $studentId, $sectionId);
    if (!$scheduleCheck['ok']) {
        return ['ok' => false, 'message' => $scheduleCheck['message']];
    }

    $totalCredits = academicPolicyRegisteredCredits($conn, $studentId, (int)$section['semester_id']) + (int)$section['credits'];
    if ($totalCredits > $maxCredits) {
        return ['ok' => false, 'message' => "Tổng số tín chỉ đăng ký vượt giới hạn {$maxCredits} tín chỉ."];
    }

    return ['ok' => true, 'message' => ''];
}
