<?php
/**
 * Academic Module — Helper Library
 * university/academic/includes/academic_helpers.php
 *
 * Phòng Đào tạo: quản lý học vụ toàn trường
 * Roles: academic_manager, academic_staff
 */

// ── Kiểm tra quyền Phòng Đào tạo ─────────────────────────────

function isAcademicManager(): bool
{
    if (!isset($_SESSION['user_id'])) return false;
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') return true;
    if (function_exists('hasRole')) return hasRole('academic_manager');
    return false;
}

function isAcademicStaff(): bool
{
    if (!isset($_SESSION['user_id'])) return false;
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') return true;
    if (function_exists('hasRole')) {
        return hasRole('academic_manager') || hasRole('academic_staff');
    }
    return false;
}

// ── Tính điểm & xếp loại ─────────────────────────────────────

function calcTotalScore(?float $process, ?float $midterm, ?float $final): ?float
{
    if ($process === null || $midterm === null || $final === null) return null;
    return round($process * 0.2 + $midterm * 0.3 + $final * 0.5, 2);
}

function calcLetterGrade(?float $total): ?string
{
    if ($total === null) return null;
    if ($total >= 8.5) return 'A';
    if ($total >= 8.0) return 'B+';
    if ($total >= 7.0) return 'B';
    if ($total >= 6.0) return 'C+';
    if ($total >= 5.0) return 'C';
    if ($total >= 4.0) return 'D+';
    if ($total >= 3.5) return 'D';
    return 'F';
}

// --- TKB: tinh ngay hoc thuc te tu tong tiet va lich goc ---
function academicScheduleNormalizeSession(string $session): string
{
    $value = mb_strtolower(trim($session), 'UTF-8');
    $value = strtr($value, [
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

    return match ($value) {
        'sang', 'morning', 'am' => 'sang',
        'chieu', 'afternoon', 'pm' => 'chieu',
        'toi', 'evening', 'night' => 'toi',
        default => $value,
    };
}

function academicScheduleParseDaySessions(?string $daySessions): array
{
    $items = [];
    foreach (preg_split('/\s*,\s*/', trim((string)$daySessions), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
        [$day, $session] = array_pad(explode(':', trim($token), 2), 2, '');
        $day = (int)preg_replace('/\D+/', '', $day);
        $session = academicScheduleNormalizeSession($session);
        if ($day >= 2 && $day <= 8 && in_array($session, ['sang', 'chieu', 'toi'], true)) {
            $items[] = ['day' => $day, 'session' => $session];
        }
    }
    return $items;
}

function academicScheduleSectionDates(?string $startDate, ?string $daySessions, int $totalPeriods, int $periodsPerMeeting = 5, ?string $limitEndDate = null): array
{
    $startTs = $startDate ? strtotime($startDate . ' 00:00:00') : false;
    if (!$startTs) return [];

    $meetings = academicScheduleParseDaySessions($daySessions);
    if (!$meetings) return [];

    $needed = max(1, (int)ceil(max(1, $totalPeriods) / max(1, $periodsPerMeeting)));
    $limitTs = $limitEndDate ? strtotime($limitEndDate . ' 23:59:59') : strtotime('+1 year', $startTs);
    $weekMonday = strtotime('monday this week', $startTs);
    if ((int)date('N', $startTs) === 1) {
        $weekMonday = $startTs;
    }

    $dates = [];
    for ($week = 0; $week < 80 && count($dates) < $needed; $week++) {
        foreach ($meetings as $meeting) {
            $dayOffset = ((int)$meeting['day'] === 8) ? 6 : (int)$meeting['day'] - 2;
            $dateTs = strtotime('+' . ($week * 7 + $dayOffset) . ' days', $weekMonday);
            $sameStartWeek = date('o-W', $dateTs) === date('o-W', $startTs);
            if (($dateTs < $startTs && !$sameStartWeek) || $dateTs > $limitTs) continue;
            $dates[] = date('Y-m-d', $dateTs);
            if (count($dates) >= $needed) break;
        }
    }

    return $dates;
}

function academicScheduleSectionEndDate(?string $startDate, ?string $daySessions, int $totalPeriods, ?string $limitEndDate = null): ?string
{
    $dates = academicScheduleSectionDates($startDate, $daySessions, $totalPeriods, 5, $limitEndDate);
    return $dates ? end($dates) : ($startDate ?: null);
}

function academicTimetableIsTestSemester(array $semester): bool
{
    if (($semester['data_mode'] ?? 'system') === 'test') {
        return true;
    }

    $label = mb_strtolower((string)($semester['info'] ?? $semester['semester_name'] ?? ''), 'UTF-8');
    return str_contains($label, 'test');
}

function academicTimetableResolveEndTs(array $semester, array $sections): ?int
{
    $semEnd = $semester['sem_end'] ?? $semester['end_date'] ?? null;
    $endTs = $semEnd ? strtotime($semEnd) : null;

    if (!academicTimetableIsTestSemester($semester)) {
        return $endTs;
    }

    foreach ($sections as $section) {
        $date = $section['_effective_end'] ?? $section['end_date'] ?? null;
        if (!$date) continue;
        $sectionEndTs = strtotime($date);
        if ($sectionEndTs && (!$endTs || $sectionEndTs > $endTs)) {
            $endTs = $sectionEndTs;
        }
    }

    return $endTs;
}

function academicEnsureScheduleChangesTable(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS course_section_schedule_changes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_section_id INT NOT NULL,
            original_date DATE NOT NULL,
            new_date DATE NOT NULL,
            new_day_session VARCHAR(30) NOT NULL,
            room VARCHAR(50) NULL,
            reason TEXT NULL,
            approved_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_section_original (course_section_id, original_date),
            INDEX idx_section_new (course_section_id, new_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function academicScheduleChangesBySection(mysqli $conn, array $sectionIds): array
{
    $sectionIds = array_values(array_unique(array_filter(array_map('intval', $sectionIds))));
    if (!$sectionIds) return [];

    academicEnsureScheduleChangesTable($conn);
    $in = implode(',', $sectionIds);
    $rows = $conn->query("SELECT * FROM course_section_schedule_changes WHERE course_section_id IN ($in) ORDER BY original_date, id")
        ->fetch_all(MYSQLI_ASSOC);

    $bySection = [];
    foreach ($rows as $row) {
        $bySection[(int)$row['course_section_id']][] = $row;
    }
    return $bySection;
}

// ── Lấy học kỳ active ────────────────────────────────────────

function getActiveSemesterAcademic(mysqli $conn): ?array
{
    $res = $conn->query(
        "SELECT * FROM semesters
         WHERE status IN ('active','open')
         ORDER BY id DESC LIMIT 1"
    );
    if (!$res || $res->num_rows === 0) return null;
    return $res->fetch_assoc();
}

// ── Thống kê dashboard ────────────────────────────────────────

function getAcademicDashboardStats(mysqli $conn): array
{
    $stats = [];

    // Tổng lớp HP đang mở
    $r = $conn->query("SELECT COUNT(*) AS c FROM course_sections WHERE status='open'");
    $stats['open_sections'] = (int)($r ? $r->fetch_assoc()['c'] : 0);

    // Lớp HP đề xuất chờ duyệt
    $r = $conn->query("SELECT COUNT(*) AS c FROM course_sections WHERE status='proposed'");
    $stats['pending_proposals'] = (int)($r ? $r->fetch_assoc()['c'] : 0);

    // Đề xuất phân công GV chờ duyệt
    $r = $conn->query("SELECT COUNT(*) AS c FROM course_sections WHERE proposal_status='pending'");
    $stats['pending_assignments'] = (int)($r ? $r->fetch_assoc()['c'] : 0);

    // Lớp HP chưa có GV (đang mở)
    $r = $conn->query("SELECT COUNT(*) AS c FROM course_sections WHERE status='open' AND (teacher_id IS NULL OR teacher_id=0)");
    $stats['no_teacher'] = (int)($r ? $r->fetch_assoc()['c'] : 0);

    $r = $conn->query("SELECT COUNT(*) AS c FROM course_sections WHERE status IN ('open','full') AND teaching_mode <> 'online' AND (day_sessions IS NULL OR day_sessions = '')");
    $stats['no_schedule'] = (int)($r ? $r->fetch_assoc()['c'] : 0);

    $r = $conn->query("SELECT COUNT(*) AS c FROM course_sections WHERE status IN ('open','full') AND teaching_mode <> 'online' AND (room IS NULL OR room = '')");
    $stats['no_room'] = (int)($r ? $r->fetch_assoc()['c'] : 0);

    // Lớp HP chưa có lịch thi
    $r = $conn->query(
        "SELECT COUNT(*) AS c FROM course_sections cs
         LEFT JOIN final_exam_schedules fes ON cs.id = fes.course_section_id
         WHERE cs.status='open' AND fes.id IS NULL"
    );
    $stats['no_exam'] = (int)($r ? $r->fetch_assoc()['c'] : 0);

    // Lớp HP chưa có điểm đầy đủ (GV chưa nhập hết)
    $r = $conn->query(
        "SELECT COUNT(DISTINCT cs.id) AS c
         FROM course_sections cs
         JOIN student_subjects ss ON ss.course_section_id = cs.id
         LEFT JOIN grades g ON g.student_subject_id = ss.id
         WHERE cs.status IN ('open','closed')
           AND (g.id IS NULL OR g.final_score IS NULL)
           AND ss.status = 'registered'"
    );
    $stats['missing_grades'] = (int)($r ? $r->fetch_assoc()['c'] : 0);

    // Tổng SV đang học
    $r = $conn->query("SELECT COUNT(*) AS c FROM students WHERE academic_status='Đang học'");
    $stats['total_students'] = (int)($r ? $r->fetch_assoc()['c'] : 0);

    // Tổng GV
    $r = $conn->query("SELECT COUNT(*) AS c FROM teachers");
    $stats['total_teachers'] = (int)($r ? $r->fetch_assoc()['c'] : 0);

    return $stats;
}

// ── Gửi thông báo nội bộ Phòng ĐT ────────────────────────────

function sendAcademicNotification(
    mysqli $conn,
    int    $sentBy,
    string $type,
    string $title,
    string $content,
    ?int   $facultyId  = null,
    ?int   $teacherId  = null,
    ?int   $refId      = null,
    ?string $refType   = null
): bool {
    $chk = $conn->query("SHOW TABLES LIKE 'academic_notifications'");
    if (!$chk || $chk->num_rows === 0) return false;

    $stmt = $conn->prepare(
        "INSERT INTO academic_notifications
         (type, faculty_id, teacher_id, ref_id, ref_type, title, content, sent_by)
         VALUES (?,?,?,?,?,?,?,?)"
    );
    if (!$stmt) return false;
    $stmt->bind_param('siiiissi',
        $type, $facultyId, $teacherId, $refId, $refType,
        $title, $content, $sentBy
    );
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

// ── Kiểm tra điểm đã bị khóa chưa ───────────────────────────

function isGradeLocked(mysqli $conn, int $sectionId): bool
{
    $chk = $conn->query("SHOW TABLES LIKE 'grade_locks'");
    if (!$chk || $chk->num_rows === 0) return false;
    $stmt = $conn->prepare("SELECT id FROM grade_locks WHERE course_section_id = ? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param('i', $sectionId);
    $stmt->execute();
    $found = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $found;
}

// ── Pagination ────────────────────────────────────────────────

function paginateAcademic(int $total, int $page, int $perPage = 20): array
{
    $perPage    = max(1, min(100, $perPage));
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page       = max(1, min($totalPages, $page));
    return [
        'total'        => $total,
        'per_page'     => $perPage,
        'current_page' => $page,
        'total_pages'  => $totalPages,
        'offset'       => ($page - 1) * $perPage,
    ];
}

function renderAcademicPagination(array $pag, string $qs = ''): string
{
    if ($pag['total_pages'] <= 1) return '';
    $cur   = $pag['current_page'];
    $total = $pag['total_pages'];
    $sep   = $qs !== '' ? '&' . ltrim($qs, '&') : '';
    $html  = '<nav><ul class="pagination pagination-sm mb-0">';
    $html .= $cur > 1
        ? "<li class='page-item'><a class='page-link' href='?page=" . ($cur-1) . $sep . "'>&laquo;</a></li>"
        : "<li class='page-item disabled'><span class='page-link'>&laquo;</span></li>";
    for ($i = max(1,$cur-2); $i <= min($total,$cur+2); $i++) {
        $html .= $i === $cur
            ? "<li class='page-item active'><span class='page-link'>$i</span></li>"
            : "<li class='page-item'><a class='page-link' href='?page=$i$sep'>$i</a></li>";
    }
    $html .= $cur < $total
        ? "<li class='page-item'><a class='page-link' href='?page=" . ($cur+1) . $sep . "'>&raquo;</a></li>"
        : "<li class='page-item disabled'><span class='page-link'>&raquo;</span></li>";
    return $html . '</ul></nav>';
}
