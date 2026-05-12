<?php
$currentFile = basename($_SERVER['PHP_SELF']);

// Lấy tên khoa từ DB nếu có faculty_id trong session
$facultyName = 'Khoa/Viện';
$facultySubtitle = 'Quản lý nội bộ';
if (!empty($_SESSION['_faculty_id'])) {
    global $conn;
    if (isset($conn) && $conn instanceof mysqli) {
        $fid  = (int)$_SESSION['_faculty_id'];
        $fStmt = $conn->prepare("SELECT faculty_name FROM faculties WHERE id = ? LIMIT 1");
        if ($fStmt) {
            $fStmt->bind_param('i', $fid);
            $fStmt->execute();
            $fRow = $fStmt->get_result()->fetch_assoc();
            $fStmt->close();
            if ($fRow) {
                $facultyName     = htmlspecialchars($fRow['faculty_name']);
                $facultySubtitle = 'Quản lý Khoa/Viện';
            }
        }
    }
}
?>
<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon">
            <i class="bi bi-building-fill"></i>
        </div>
        <div class="sidebar-brand-text">
            <div><?php echo $facultyName; ?></div>
            <small><?php echo htmlspecialchars($facultySubtitle); ?></small>
        </div>
    </div>

    <nav class="sidebar-nav">

        <!-- ── Tổng quan ── -->
        <div class="sidebar-section-title">Tổng quan</div>
        <a href="/university/faculty/index.php"
           class="sidebar-link <?php echo $currentFile === 'index.php' ? 'active' : ''; ?>"
           aria-current="<?php echo $currentFile === 'index.php' ? 'page' : 'false'; ?>">
            <i class="bi bi-speedometer2" aria-hidden="true"></i> Dashboard
        </a>

        <!-- ── Giảng viên ── -->
        <div class="sidebar-section-title">Giảng viên</div>
        <a href="/university/faculty/teachers.php"
           class="sidebar-link <?php echo $currentFile === 'teachers.php' ? 'active' : ''; ?>">
            <i class="bi bi-person-badge-fill" aria-hidden="true"></i> Danh sách GV
        </a>
        <a href="/university/faculty/teaching_load.php"
           class="sidebar-link <?php echo $currentFile === 'teaching_load.php' ? 'active' : ''; ?>">
            <i class="bi bi-bar-chart-fill" aria-hidden="true"></i> Khối lượng GD
        </a>
        <a href="/university/faculty/teaching_wishes.php"
           class="sidebar-link <?php echo $currentFile === 'teaching_wishes.php' ? 'active' : ''; ?>">
            <i class="bi bi-hand-index-fill" aria-hidden="true"></i> Nguyện vọng GD
        </a>
        <a href="/university/faculty/teacher_kpi.php"
           class="sidebar-link <?php echo $currentFile === 'teacher_kpi.php' ? 'active' : ''; ?>">
            <i class="bi bi-graph-up-arrow" aria-hidden="true"></i> KPI Giảng viên
        </a>
        <a href="/university/faculty/departments.php"
           class="sidebar-link <?php echo $currentFile === 'departments.php' ? 'active' : ''; ?>">
            <i class="bi bi-diagram-3-fill" aria-hidden="true"></i> Bộ môn
        </a>

        <!-- ── Sinh viên ── -->
        <div class="sidebar-section-title">Sinh viên</div>
        <a href="/university/faculty/students.php"
           class="sidebar-link <?php echo $currentFile === 'students.php' ? 'active' : ''; ?>"
           aria-current="<?php echo $currentFile === 'students.php' ? 'page' : 'false'; ?>">
            <i class="bi bi-people-fill" aria-hidden="true"></i> Danh sách SV
        </a>
        <a href="/university/faculty/academic_warnings.php"
           class="sidebar-link <?php echo $currentFile === 'academic_warnings.php' ? 'active' : ''; ?>"
           aria-current="<?php echo $currentFile === 'academic_warnings.php' ? 'page' : 'false'; ?>">
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> Cảnh báo học vụ
        </a>
        <a href="/university/faculty/grades.php"
           class="sidebar-link <?php echo $currentFile === 'grades.php' ? 'active' : ''; ?>"
           aria-current="<?php echo $currentFile === 'grades.php' ? 'page' : 'false'; ?>">
            <i class="bi bi-graph-up" aria-hidden="true"></i> Kết quả học tập
        </a>

        <!-- ── Đào tạo ── -->
        <div class="sidebar-section-title">Đào tạo</div>
        <a href="/university/faculty/curriculum.php"
           class="sidebar-link <?php echo $currentFile === 'curriculum.php' ? 'active' : ''; ?>"
           aria-current="<?php echo $currentFile === 'curriculum.php' ? 'page' : 'false'; ?>">
            <i class="bi bi-journal-bookmark-fill" aria-hidden="true"></i> Chương trình ĐT
        </a>
        <a href="/university/faculty/proposals.php"
           class="sidebar-link <?php echo $currentFile === 'proposals.php' ? 'active' : ''; ?>"
           aria-current="<?php echo $currentFile === 'proposals.php' ? 'page' : 'false'; ?>">
            <i class="bi bi-send-fill" aria-hidden="true"></i> Đề xuất mở lớp/GV
        </a>
        <a href="/university/faculty/exam_schedules.php"
           class="sidebar-link <?php echo $currentFile === 'exam_schedules.php' ? 'active' : ''; ?>"
           aria-current="<?php echo $currentFile === 'exam_schedules.php' ? 'page' : 'false'; ?>">
            <i class="bi bi-calendar-event-fill" aria-hidden="true"></i> Lịch thi cuối kỳ
        </a>

        <!-- ── Chất lượng ── -->
        <div class="sidebar-section-title">Chất lượng</div>
        <a href="/university/faculty/evaluation.php"
           class="sidebar-link <?php echo $currentFile === 'evaluation.php' ? 'active' : ''; ?>"
           aria-current="<?php echo $currentFile === 'evaluation.php' ? 'page' : 'false'; ?>">
            <i class="bi bi-star-fill" aria-hidden="true"></i> Đánh giá giảng viên
        </a>

        <!-- ── Tiện ích ── -->
        <div class="sidebar-section-title">Tiện ích</div>
        <a href="/university/faculty/reports.php"
           class="sidebar-link <?php echo $currentFile === 'reports.php' ? 'active' : ''; ?>"
           aria-current="<?php echo $currentFile === 'reports.php' ? 'page' : 'false'; ?>">
            <i class="bi bi-clipboard-data-fill" aria-hidden="true"></i> Báo cáo thống kê
        </a>
        <a href="/university/faculty/notifications.php"
           class="sidebar-link <?php echo $currentFile === 'notifications.php' ? 'active' : ''; ?>"
           aria-current="<?php echo $currentFile === 'notifications.php' ? 'page' : 'false'; ?>">
            <i class="bi bi-bell-fill" aria-hidden="true"></i> Thông báo nội bộ
        </a>
        <a href="/university/faculty/audit_log.php"
           class="sidebar-link <?php echo $currentFile === 'audit_log.php' ? 'active' : ''; ?>"
           aria-current="<?php echo $currentFile === 'audit_log.php' ? 'page' : 'false'; ?>">
            <i class="bi bi-shield-check" aria-hidden="true"></i> Nhật ký thao tác
        </a>
        <a href="/university/faculty/export.php"
           class="sidebar-link <?php echo $currentFile === 'export.php' ? 'active' : ''; ?>"
           aria-current="<?php echo $currentFile === 'export.php' ? 'page' : 'false'; ?>">
            <i class="bi bi-download" aria-hidden="true"></i> Export dữ liệu
        </a>

        <!-- ── Hệ thống ── -->
        <div class="sidebar-section-title">Hệ thống</div>
        <?php if (isFacultyManager()): ?>
        <a href="/university/faculty/staff_roles.php"
           class="sidebar-link <?php echo $currentFile === 'staff_roles.php' ? 'active' : ''; ?>">
            <i class="bi bi-shield-lock-fill" aria-hidden="true"></i> Phân quyền NV
        </a>
        <?php endif; ?>
        <?php if (canSwitchRole()): ?>
        <a href="/university/switch_role.php" class="sidebar-link text-warning">
            <i class="bi bi-arrow-left-right" aria-hidden="true"></i> Chuyển vai trò
        </a>
        <?php endif; ?>
        <a href="/university/index.php" class="sidebar-link" target="_blank" rel="noopener noreferrer">
            <i class="bi bi-globe" aria-hidden="true"></i> Xem trang web
        </a>
        <a href="/university/login.php?logout=1"
           class="sidebar-link text-danger">
            <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Đăng xuất
        </a>

    </nav>
</aside>
