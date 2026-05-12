<?php
/**
 * Faculty Module — Core Helper Library
 * university/faculty/includes/faculty_helpers.php
 *
 * Tất cả functions dùng chung trong module Quản lý Khoa/Viện.
 * Yêu cầu: PHP 8.x, MySQLi, session đã khởi động.
 */

/**
 * Lấy faculty_id của user hiện tại, cache vào session.
 * Admin (role='admin') trả về 0 — bypass isolation.
 *
 * @param mysqli $conn   Kết nối DB
 * @param int    $userId user_id từ session
 * @return int   faculty_id > 0, hoặc 0 nếu không tìm thấy / admin
 */
function getFacultyId(mysqli $conn, int $userId): int
{
    // Admin bypass: không cần faculty isolation
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        return 0;
    }

    // Trả về cache nếu đã có — nhưng validate lại mỗi 10 phút
    $cacheTs = $_SESSION['_faculty_id_ts'] ?? 0;
    if (!empty($_SESSION['_faculty_id']) && (time() - $cacheTs) < 600) {
        return (int)$_SESSION['_faculty_id'];
    }

    // Cách 1: Lấy từ bảng teachers (giảng viên kiêm trưởng khoa/thư ký)
    $stmt = $conn->prepare(
        "SELECT faculty_id FROM teachers WHERE user_id = ? LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $fid = (int)($row['faculty_id'] ?? 0);
        if ($fid > 0) {
            $_SESSION['_faculty_id']    = $fid;
            $_SESSION['_faculty_id_ts'] = time();
            return $fid;
        }
    }

    // Cách 2: Lấy từ role code dạng faculty_manager_X, faculty_staff_X, dept_head_X
    // Dành cho nhân viên hành chính không có record trong bảng teachers
    $stmt2 = $conn->prepare(
        "SELECT r.code FROM user_roles ur
         JOIN roles r ON ur.role_id = r.id
         WHERE ur.user_id = ? AND r.is_active = 1
           AND (r.code LIKE 'faculty_manager_%'
                OR r.code LIKE 'faculty_staff_%'
                OR r.code LIKE 'dept_head_%')
           AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
         ORDER BY
             CASE WHEN r.code LIKE 'faculty_manager_%' THEN 0
                  WHEN r.code LIKE 'dept_head_%' THEN 1
                  ELSE 2 END
         LIMIT 1"
    );
    if ($stmt2) {
        $stmt2->bind_param('i', $userId);
        $stmt2->execute();
        $row2 = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
        if ($row2 && preg_match('/_(manager|staff|head)_(\d+)$/', $row2['code'], $m)) {
            $fid = (int)$m[2];
            if ($fid > 0) {
                $_SESSION['_faculty_id']    = $fid;
                $_SESSION['_faculty_id_ts'] = time();
                return $fid;
            }
        }
    }

    // Xóa cache cũ nếu không tìm thấy
    unset($_SESSION['_faculty_id'], $_SESSION['_faculty_id_ts']);
    return 0;
}

/**
 * Kiểm tra record thuộc faculty hiện tại.
 *
 * Hỗ trợ:
 *   - teachers       : direct faculty_id column
 *   - departments    : direct faculty_id column
 *   - curriculum     : via JOIN majors
 *   - course_sections: via JOIN subjects → curriculum → majors
 *
 * Admin bypass: trả về true nếu $_SESSION['role'] === 'admin'
 *
 * @param mysqli $conn      Kết nối DB
 * @param string $table     Tên bảng cần kiểm tra
 * @param int    $recordId  ID của record
 * @param int    $facultyId faculty_id hiện tại
 * @return bool
 */
function assertFacultyOwnership(
    mysqli $conn,
    string $table,
    int $recordId,
    int $facultyId
): bool {
    // Admin bypass
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        return true;
    }

    // Bảng có cột faculty_id trực tiếp
    $directTables = ['teachers', 'departments'];
    if (in_array($table, $directTables, true)) {
        $stmt = $conn->prepare(
            "SELECT id FROM `{$table}` WHERE id = ? AND faculty_id = ? LIMIT 1"
        );
        if (!$stmt) return false;
        $stmt->bind_param('ii', $recordId, $facultyId);
        $stmt->execute();
        $found = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $found;
    }

    // curriculum: kiểm tra qua majors
    if ($table === 'curriculum') {
        $stmt = $conn->prepare(
            "SELECT c.id FROM curriculum c
             JOIN majors m ON c.major_id = m.id
             WHERE c.id = ? AND m.faculty_id = ?
             LIMIT 1"
        );
        if (!$stmt) return false;
        $stmt->bind_param('ii', $recordId, $facultyId);
        $stmt->execute();
        $found = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $found;
    }

    // course_sections: kiểm tra qua subjects → curriculum → majors
    if ($table === 'course_sections') {
        $stmt = $conn->prepare(
            "SELECT cs.id FROM course_sections cs
             JOIN subjects s ON cs.subject_id = s.id
             JOIN curriculum cur ON s.id = cur.subject_id
             JOIN majors m ON cur.major_id = m.id
             WHERE cs.id = ? AND m.faculty_id = ?
             LIMIT 1"
        );
        if (!$stmt) return false;
        $stmt->bind_param('ii', $recordId, $facultyId);
        $stmt->execute();
        $found = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $found;
    }

    return false;
}

/**
 * Ghi audit log. Dùng prepared statement.
 *
 * action_type: create|update|delete|submit|approve|reject|restore|export|login_denied
 *
 * @param mysqli      $conn       Kết nối DB
 * @param int         $userId     user_id người thực hiện
 * @param string      $actionType Loại hành động
 * @param string      $module     Module (thường là 'faculty')
 * @param string      $tableName  Tên bảng bị tác động
 * @param int         $recordId   ID record bị tác động
 * @param string|null $oldData    Dữ liệu cũ (JSON hoặc null)
 * @param string|null $newData    Dữ liệu mới (JSON hoặc null)
 * @param string      $ip         IP address
 */
function logAudit(
    mysqli $conn,
    int $userId,
    string $actionType,
    string $module,
    string $tableName,
    int $recordId,
    ?string $oldData,
    ?string $newData,
    string $ip
): void {
    // Lấy role hiện tại của actor tại thời điểm thao tác
    $actorRole = $_SESSION['role'] ?? '';
    if (!empty($_SESSION['_user_role_codes'])) {
        // Ưu tiên role faculty cụ thể nhất
        foreach ($_SESSION['_user_role_codes'] as $code) {
            if (str_starts_with($code, 'faculty_manager') || str_starts_with($code, 'dept_head')) {
                $actorRole = $code;
                break;
            }
            if (str_starts_with($code, 'faculty_staff')) {
                $actorRole = $code;
            }
        }
    }

    $stmt = $conn->prepare(
        "INSERT INTO faculty_audit_logs
         (user_id, actor_role, action_type, module, table_name, record_id, old_data, new_data, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        error_log('[Faculty Module] logAudit prepare failed: ' . $conn->error);
        return;
    }
    $stmt->bind_param(
        'issssisss',
        $userId,
        $actorRole,
        $actionType,
        $module,
        $tableName,
        $recordId,
        $oldData,
        $newData,
        $ip
    );
    $stmt->execute();
    $stmt->close();
}

/**
 * Server-side pagination.
 *
 * @param int $totalRecords Tổng số records
 * @param int $currentPage  Trang hiện tại (1-based)
 * @param int $perPage      Số records mỗi trang (1–100)
 * @return array ['offset'=>int, 'per_page'=>int, 'total_pages'=>int, 'current_page'=>int, 'total'=>int]
 */
function paginate(int $totalRecords, int $currentPage = 1, int $perPage = 20): array
{
    $perPage     = max(1, min(100, $perPage));
    $totalPages  = max(1, (int)ceil($totalRecords / $perPage));
    $currentPage = max(1, min($totalPages, $currentPage));
    $offset      = ($currentPage - 1) * $perPage;

    return [
        'offset'       => $offset,
        'per_page'     => $perPage,
        'total_pages'  => $totalPages,
        'current_page' => $currentPage,
        'total'        => $totalRecords,
    ];
}

/**
 * Tính GPA tích lũy sinh viên (thang 10).
 *
 * @param mysqli $conn      Kết nối DB
 * @param int    $studentId students.id
 * @return float|null       GPA hoặc null nếu chưa có điểm
 */
function calculateStudentGPA(mysqli $conn, int $studentId): ?float
{
    $stmt = $conn->prepare(
        "SELECT AVG(g.total_score) AS avg_gpa
         FROM grades g
         JOIN student_subjects ss ON g.student_subject_id = ss.id
         WHERE ss.student_id = ? AND g.total_score IS NOT NULL"
    );
    if (!$stmt) return null;
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row === null || $row['avg_gpa'] === null) {
        return null;
    }
    return round((float)$row['avg_gpa'], 2);
}

/**
 * Kiểm tra cảnh báo học vụ sinh viên.
 *
 * Trả về array chứa các loại cảnh báo active:
 *   - 'gpa'     : AVG(gpa_score) < 4.0
 *   - 'credits' : failed_credits / total_credits > 0.3 (học kỳ active)
 *   - 'retake'  : có môn học lại > 2 lần
 *
 * @param mysqli $conn      Kết nối DB
 * @param int    $studentId students.id
 * @return array
 */
function getAcademicWarnings(mysqli $conn, int $studentId): array
{
    $warnings = [];

    // Kiểm tra GPA tích lũy
    $gpa = calculateStudentGPA($conn, $studentId);
    if ($gpa !== null && $gpa < 4.0) {
        $warnings[] = 'gpa';
    }

    // Kiểm tra tỷ lệ tín chỉ trượt trong học kỳ active
    $stmt = $conn->prepare(
        "SELECT
           COUNT(*) AS total,
           SUM(CASE WHEN g.final_score < 5.0 THEN 1 ELSE 0 END) AS failed
         FROM grades g
         JOIN student_subjects ss ON g.student_subject_id = ss.id
         JOIN course_sections cs ON ss.course_section_id = cs.id
         WHERE ss.student_id = ?
           AND cs.semester_id = (
               SELECT id FROM semesters WHERE status = 'active' LIMIT 1
           )"
    );
    if ($stmt) {
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && (int)$row['total'] > 0) {
            $ratio = (int)$row['failed'] / (int)$row['total'];
            if ($ratio > 0.3) {
                $warnings[] = 'credits';
            }
        }
    }

    // Kiểm tra môn học lại > 2 lần
    $stmt = $conn->prepare(
        "SELECT cs.subject_id, COUNT(*) AS cnt
         FROM grades g
         JOIN student_subjects ss ON g.student_subject_id = ss.id
         JOIN course_sections cs ON ss.course_section_id = cs.id
         WHERE ss.student_id = ?
         GROUP BY cs.subject_id
         HAVING COUNT(*) > 2"
    );
    if ($stmt) {
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $warnings[] = 'retake';
        }
        $stmt->close();
    }

    return $warnings;
}

/**
 * Lấy dashboard warning counts, cache 5 phút trong session.
 *
 * Trả về:
 *   - no_teacher          : lớp HP status='open' không có teacher_id
 *   - no_exam             : lớp HP status='open' không có final_exam_schedule
 *   - overloaded          : GV có tổng tín chỉ > 20 trong học kỳ active
 *   - academic_warning    : SV có GPA < 4.0 trong khoa
 *   - curriculum_incomplete: ngành có tổng tín chỉ CTĐT < 120
 *
 * Invalidate cache: xóa $_SESSION['_dashboard_warnings_'.$facultyId]
 *
 * @param mysqli $conn      Kết nối DB
 * @param int    $facultyId faculty_id hiện tại
 * @return array
 */
function getDashboardWarnings(mysqli $conn, int $facultyId): array
{
    $cacheKey = '_dashboard_warnings_' . $facultyId;
    $cacheTs  = '_dashboard_warnings_ts_' . $facultyId;

    // Trả về cache nếu còn hiệu lực (5 phút = 300 giây)
    if (
        isset($_SESSION[$cacheKey]) &&
        isset($_SESSION[$cacheTs]) &&
        (time() - (int)$_SESSION[$cacheTs]) < 300
    ) {
        return $_SESSION[$cacheKey];
    }

    // 1. Lớp HP chưa có GV
    $noTeacher = 0;
    $r1 = $conn->prepare(
        "SELECT COUNT(*) AS c
         FROM course_sections cs
         JOIN subjects s ON cs.subject_id = s.id
         JOIN curriculum cur ON s.id = cur.subject_id
         JOIN majors m ON cur.major_id = m.id
         WHERE m.faculty_id = ?
           AND cs.teacher_id IS NULL
           AND cs.status = 'open'"
    );
    if ($r1) {
        $r1->bind_param('i', $facultyId);
        $r1->execute();
        $noTeacher = (int)($r1->get_result()->fetch_assoc()['c'] ?? 0);
        $r1->close();
    }

    // 2. Lớp HP chưa có lịch thi
    $noExam = 0;
    $r2 = $conn->prepare(
        "SELECT COUNT(*) AS c
         FROM course_sections cs
         JOIN subjects s ON cs.subject_id = s.id
         JOIN curriculum cur ON s.id = cur.subject_id
         JOIN majors m ON cur.major_id = m.id
         LEFT JOIN final_exam_schedules fes ON cs.id = fes.course_section_id
         WHERE m.faculty_id = ?
           AND cs.status = 'open'
           AND fes.id IS NULL"
    );
    if ($r2) {
        $r2->bind_param('i', $facultyId);
        $r2->execute();
        $noExam = (int)($r2->get_result()->fetch_assoc()['c'] ?? 0);
        $r2->close();
    }

    // 3. GV quá tải (> 20 tín chỉ học kỳ active)
    $overloaded = 0;
    $r3 = $conn->prepare(
        "SELECT COUNT(*) AS c FROM (
             SELECT t.id, SUM(s.credits) AS total
             FROM teachers t
             JOIN course_sections cs ON cs.teacher_id = t.id
             JOIN subjects s ON cs.subject_id = s.id
             WHERE t.faculty_id = ?
               AND cs.semester_id = (
                   SELECT id FROM semesters WHERE status = 'active' LIMIT 1
               )
               AND cs.status IN ('open', 'closed')
             GROUP BY t.id
             HAVING SUM(s.credits) > 20
         ) x"
    );
    if ($r3) {
        $r3->bind_param('i', $facultyId);
        $r3->execute();
        $overloaded = (int)($r3->get_result()->fetch_assoc()['c'] ?? 0);
        $r3->close();
    }

    // 4. SV cảnh báo học vụ (điểm tổng kết trung bình < 4.0)
    $academicWarning = 0;
    $r4 = $conn->prepare(
        "SELECT COUNT(*) AS c FROM (
             SELECT ss.student_id, AVG(g.total_score) AS avg_score
             FROM grades g
             JOIN student_subjects ss ON g.student_subject_id = ss.id
             JOIN students st ON ss.student_id = st.id
             JOIN classes cl ON st.class_id = cl.id
             JOIN majors m ON cl.major_id = m.id
             WHERE m.faculty_id = ? AND g.total_score IS NOT NULL
             GROUP BY ss.student_id
             HAVING avg_score < 4.0
         ) x"
    );
    if ($r4) {
        $r4->bind_param('i', $facultyId);
        $r4->execute();
        $academicWarning = (int)($r4->get_result()->fetch_assoc()['c'] ?? 0);
        $r4->close();
    }

    // 5. Ngành có tổng tín chỉ CTĐT < 120
    $curriculumIncomplete = 0;
    $r5 = $conn->prepare(
        "SELECT COUNT(*) AS c FROM (
             SELECT m.id, COALESCE(SUM(cur.credits), 0) AS total_credits
             FROM majors m
             LEFT JOIN curriculum cur ON cur.major_id = m.id
                 AND cur.deleted_at IS NULL
             WHERE m.faculty_id = ?
             GROUP BY m.id
             HAVING total_credits < 120
         ) x"
    );
    if ($r5) {
        $r5->bind_param('i', $facultyId);
        $r5->execute();
        $curriculumIncomplete = (int)($r5->get_result()->fetch_assoc()['c'] ?? 0);
        $r5->close();
    }

    $warnings = [
        'no_teacher'            => $noTeacher,
        'no_exam'               => $noExam,
        'overloaded'            => $overloaded,
        'academic_warning'      => $academicWarning,
        'curriculum_incomplete' => $curriculumIncomplete,
    ];

    // Lưu cache
    $_SESSION[$cacheKey] = $warnings;
    $_SESSION[$cacheTs]  = time();

    return $warnings;
}

/**
 * Render pagination HTML (Bootstrap 5).
 *
 * @param array  $pag         Kết quả từ paginate()
 * @param string $queryString Chuỗi GET params hiện tại (không có page=)
 * @return string HTML
 */
function renderPagination(array $pag, string $queryString = ''): string
{
    if ($pag['total_pages'] <= 1) {
        return '';
    }

    $current = $pag['current_page'];
    $total   = $pag['total_pages'];
    $qs      = $queryString !== '' ? '&' . ltrim($queryString, '&') : '';

    $html  = '<nav aria-label="Phân trang">';
    $html .= '<ul class="pagination pagination-sm mb-0">';

    // Nút Previous
    if ($current > 1) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="?page=' . ($current - 1) . $qs . '" aria-label="Trang trước">';
        $html .= '<span aria-hidden="true">&laquo;</span></a></li>';
    } else {
        $html .= '<li class="page-item disabled">';
        $html .= '<span class="page-link" aria-hidden="true">&laquo;</span></li>';
    }

    // Các trang
    $start = max(1, $current - 2);
    $end   = min($total, $current + 2);

    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="?page=1' . $qs . '">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        if ($i === $current) {
            $html .= '<li class="page-item active" aria-current="page">';
            $html .= '<span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item">';
            $html .= '<a class="page-link" href="?page=' . $i . $qs . '">' . $i . '</a></li>';
        }
    }

    if ($end < $total) {
        if ($end < $total - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="?page=' . $total . $qs . '">' . $total . '</a></li>';
    }

    // Nút Next
    if ($current < $total) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="?page=' . ($current + 1) . $qs . '" aria-label="Trang sau">';
        $html .= '<span aria-hidden="true">&raquo;</span></a></li>';
    } else {
        $html .= '<li class="page-item disabled">';
        $html .= '<span class="page-link" aria-hidden="true">&raquo;</span></li>';
    }

    $html .= '</ul></nav>';
    return $html;
}

/**
 * Lấy học kỳ đang active (status='active').
 *
 * @param mysqli $conn Kết nối DB
 * @return array|null  Row từ bảng semesters hoặc null
 */
function getActiveSemester(mysqli $conn): ?array
{
    $stmt = $conn->prepare(
        "SELECT * FROM semesters WHERE status = 'active' ORDER BY id DESC LIMIT 1"
    );
    if (!$stmt) return null;
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Kiểm tra user hiện tại có phải faculty_manager không.
 * Nhận diện cả role chung (faculty_manager) lẫn role khoa cụ thể (faculty_manager_1, faculty_manager_2...).
 */
function isFacultyManager(): bool
{
    if (!isset($_SESSION['user_id'])) return false;
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') return true;

    // Kiểm tra _active_role trước
    $activeRole = $_SESSION['_active_role'] ?? '';
    if ($activeRole) {
        if ($activeRole === 'faculty_manager') return true;
        if (preg_match('/^faculty_manager(_\d+)?$/', $activeRole)) return true;
    }

    if (function_exists('hasRole')) {
        if (hasRole('faculty_manager')) return true;
        if (!empty($_SESSION['_user_role_codes'])) {
            foreach ($_SESSION['_user_role_codes'] as $code) {
                if (preg_match('/^faculty_manager(_\d+)?$/', $code)) return true;
            }
        } else {
            global $conn;
            $uid = (int)$_SESSION['user_id'];
            $res = $conn->prepare(
                "SELECT r.code FROM user_roles ur
                 JOIN roles r ON ur.role_id = r.id
                 WHERE ur.user_id = ? AND r.is_active = 1
                   AND (r.code = 'faculty_manager' OR r.code LIKE 'faculty_manager_%')
                   AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
                 LIMIT 1"
            );
            if ($res) {
                $res->bind_param('i', $uid);
                $res->execute();
                $found = $res->get_result()->num_rows > 0;
                $res->close();
                if ($found) return true;
            }
        }
    }
    return false;
}

/**
 * Kiểm tra user hiện tại có phải Trưởng Bộ môn không.
 * Nhận diện: dept_head, dept_head_1, dept_head_2...
 */
function isDeptHead(): bool
{
    if (!isset($_SESSION['user_id'])) return false;
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') return true;
    if (isFacultyManager()) return true; // Trưởng khoa có quyền cao hơn

    if (function_exists('hasRole')) {
        if (hasRole('dept_head')) return true;
        if (!empty($_SESSION['_user_role_codes'])) {
            foreach ($_SESSION['_user_role_codes'] as $code) {
                if (preg_match('/^dept_head_\d+$/', $code)) return true;
            }
        } else {
            global $conn;
            $uid = (int)$_SESSION['user_id'];
            $res = $conn->prepare(
                "SELECT r.code FROM user_roles ur
                 JOIN roles r ON ur.role_id = r.id
                 WHERE ur.user_id = ? AND r.is_active = 1
                   AND (r.code = 'dept_head' OR r.code LIKE 'dept_head_%')
                   AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
                 LIMIT 1"
            );
            if ($res) {
                $res->bind_param('i', $uid);
                $res->execute();
                $found = $res->get_result()->num_rows > 0;
                $res->close();
                if ($found) return true;
            }
        }
    }
    return false;
}

/**
 * Kiểm tra user hiện tại có phải Giảng viên thường không.
 */
function isFacultyLecturer(): bool
{
    if (!isset($_SESSION['user_id'])) return false;
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') return true;
    // Trưởng khoa, Trưởng BM, Thư ký đều có quyền cao hơn
    if (isFacultyManager() || isDeptHead()) return true;
    if (function_exists('hasRole')) {
        return hasRole('faculty_lecturer') || hasRole('faculty_staff');
    }
    return false;
}

/**
 * Lấy department_id của user hiện tại (nếu là Trưởng BM hoặc GV thuộc BM).
 * Trả về 0 nếu không xác định được.
 */
function getDepartmentId(mysqli $conn, int $userId): int
{
    if (isset($_SESSION['_dept_id'])) {
        return (int)$_SESSION['_dept_id'];
    }

    // Lấy từ teachers.department_id
    $chkCol = $conn->query("SHOW COLUMNS FROM `teachers` LIKE 'department_id'");
    if (!$chkCol || $chkCol->num_rows === 0) return 0;

    $stmt = $conn->prepare(
        "SELECT department_id FROM teachers WHERE user_id = ? LIMIT 1"
    );
    if (!$stmt) return 0;
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $did = (int)($row['department_id'] ?? 0);
    if ($did > 0) $_SESSION['_dept_id'] = $did;
    return $did;
}

/**
 * Invalidate dashboard warning cache cho faculty.
 * Gọi sau mỗi write operation ảnh hưởng đến warning counts.
 * Cũng xóa faculty_id cache để force re-resolve lần sau.
 *
 * @param int $facultyId faculty_id cần xóa cache
 */
function invalidateDashboardCache(int $facultyId): void
{
    unset(
        $_SESSION['_dashboard_warnings_' . $facultyId],
        $_SESSION['_dashboard_warnings_ts_' . $facultyId]
    );
}

/**
 * Xóa toàn bộ faculty session cache.
 * Gọi khi admin thay đổi faculty_id của teacher, hoặc thay đổi user_roles.
 */
function clearFacultySessionCache(): void
{
    unset(
        $_SESSION['_faculty_id'],
        $_SESSION['_faculty_id_ts'],
        $_SESSION['_dept_id'],
        $_SESSION['_user_role_codes'],
        $_SESSION['_roles_cached']
    );
    // Xóa tất cả dashboard warning cache
    foreach (array_keys($_SESSION) as $key) {
        if (str_starts_with($key, '_dashboard_warnings_')) {
            unset($_SESSION[$key]);
        }
    }
}

/**
 * Kiểm tra user hiện tại có quyền truy cập vào faculty_id cụ thể không.
 * Dùng để bảo vệ các endpoint nhận faculty_id từ URL/POST.
 *
 * Admin: luôn true.
 * Staff: chỉ true nếu faculty_id khớp với khoa của họ.
 *
 * @param mysqli $conn
 * @param int    $targetFacultyId  faculty_id cần kiểm tra
 * @return bool
 */
function canAccessFaculty(mysqli $conn, int $targetFacultyId): bool
{
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') return true;
    $myFacultyId = getFacultyId($conn, (int)($_SESSION['user_id'] ?? 0));
    // myFacultyId = 0 nghĩa là không xác định được khoa → từ chối
    if ($myFacultyId <= 0) return false;
    return $myFacultyId === $targetFacultyId;
}
