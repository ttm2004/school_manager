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
