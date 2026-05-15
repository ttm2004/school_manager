<?php
$cur = basename($_SERVER['PHP_SELF']);
$classModeQuery = (($_GET['mode'] ?? '') === 'test') ? '?mode=test' : '';
?>
<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon"><i class="bi bi-mortarboard-fill"></i></div>
        <div class="sidebar-brand-text">
            <div>Phòng Đào tạo</div>
            <small>Quản lý học vụ</small>
        </div>
    </div>
    <nav class="sidebar-nav">

        <div class="sidebar-section-title">Tổng quan</div>
        <a href="/university/academic/index.php"
           class="sidebar-link <?php echo $cur === 'index.php' ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="sidebar-section-title">Kế hoạch học kỳ</div>
        <a href="/university/academic/semesters.php"
           class="sidebar-link <?php echo $cur === 'semesters.php' ? 'active' : ''; ?>">
            <i class="bi bi-calendar3"></i> Học kỳ
        </a>
        <a href="/university/academic/subjects.php"
           class="sidebar-link <?php echo $cur === 'subjects.php' ? 'active' : ''; ?>">
            <i class="bi bi-book-fill"></i> Môn học
        </a>
        <a href="/university/academic/curriculum.php"
           class="sidebar-link <?php echo $cur === 'curriculum.php' ? 'active' : ''; ?>">
            <i class="bi bi-journal-bookmark-fill"></i> Chương trình ĐT
        </a>

        <div class="sidebar-section-title">Lớp học phần</div>
        <a href="/university/academic/rooms.php"
           class="sidebar-link <?php echo $cur === 'rooms.php' ? 'active' : ''; ?>">
            <i class="bi bi-door-open-fill"></i> Phòng học
        </a>
        <a href="/university/academic/classes.php<?php echo $classModeQuery; ?>"
           class="sidebar-link <?php echo $cur === 'classes.php' ? 'active' : ''; ?>">
            <i class="bi bi-diagram-3-fill"></i> Lớp hành chính
        </a>
        <a href="/university/academic/course_sections.php"
           class="sidebar-link <?php echo $cur === 'course_sections.php' ? 'active' : ''; ?>">
            <i class="bi bi-grid-3x3-gap-fill"></i> Quản lý lớp HP
        </a>
        <a href="/university/academic/common_course_sections.php"
           class="sidebar-link <?php echo $cur === 'common_course_sections.php' ? 'active' : ''; ?>">
            <i class="bi bi-collection-fill"></i> Lớp HP chung
        </a>
        <a href="/university/academic/proposals.php"
           class="sidebar-link <?php echo $cur === 'proposals.php' ? 'active' : ''; ?>">
            <i class="bi bi-send-fill"></i> Đề xuất từ Khoa
            <?php
            global $conn;
            $cnt = $conn->query("SELECT COUNT(*) AS c FROM course_sections WHERE status='proposed'")->fetch_assoc()['c'] ?? 0;
            if ($cnt > 0): ?>
            <span class="badge bg-warning text-dark ms-auto"><?php echo $cnt; ?></span>
            <?php endif; ?>
        </a>
        <a href="/university/academic/teacher_assignments.php"
           class="sidebar-link <?php echo $cur === 'teacher_assignments.php' ? 'active' : ''; ?>">
            <i class="bi bi-person-check-fill"></i> Phân công GV
            <?php
            $cnt2 = $conn->query("SELECT COUNT(*) AS c FROM course_sections WHERE proposal_status='pending'")->fetch_assoc()['c'] ?? 0;
            if ($cnt2 > 0): ?>
            <span class="badge bg-warning text-dark ms-auto"><?php echo $cnt2; ?></span>
            <?php endif; ?>
        </a>
        <a href="/university/academic/pending_enrollments.php"
           class="sidebar-link <?php echo $cur === 'pending_enrollments.php' ? 'active' : ''; ?>">
            <i class="bi bi-hourglass-split"></i> Đăng ký tự động chờ xử lý
            <?php
            $pendingCnt = $conn->query("SELECT COUNT(*) AS c FROM pending_enrollments WHERE status='pending'")->fetch_assoc()['c'] ?? 0;
            if ($pendingCnt > 0): ?>
            <span class="badge bg-warning text-dark ms-auto"><?php echo $pendingCnt; ?></span>
            <?php endif; ?>
        </a>
        <a href="/university/academic/timetable.php"
           class="sidebar-link <?php echo $cur === 'timetable.php' ? 'active' : ''; ?>">
            <i class="bi bi-table"></i> Thời khóa biểu
        </a>

        <div class="sidebar-section-title">Thi & Điểm</div>
        <a href="/university/academic/exam_schedules.php"
           class="sidebar-link <?php echo $cur === 'exam_schedules.php' ? 'active' : ''; ?>">
            <i class="bi bi-calendar-event-fill"></i> Lịch thi
        </a>
        <a href="/university/academic/grades.php"
           class="sidebar-link <?php echo $cur === 'grades.php' ? 'active' : ''; ?>">
            <i class="bi bi-graph-up"></i> Quản lý điểm
        </a>
        <a href="/university/academic/grade_locks.php"
           class="sidebar-link <?php echo $cur === 'grade_locks.php' ? 'active' : ''; ?>">
            <i class="bi bi-lock-fill"></i> Khóa điểm
        </a>

        <div class="sidebar-section-title">Sinh viên & GV</div>
        <a href="/university/academic/students.php"
           class="sidebar-link <?php echo $cur === 'students.php' ? 'active' : ''; ?>">
            <i class="bi bi-people-fill"></i> Sinh viên
        </a>
        <a href="/university/academic/teachers.php"
           class="sidebar-link <?php echo $cur === 'teachers.php' ? 'active' : ''; ?>">
            <i class="bi bi-person-badge-fill"></i> Giảng viên
        </a>
        <a href="/university/academic/grade_reminder.php"
           class="sidebar-link <?php echo $cur === 'grade_reminder.php' ? 'active' : ''; ?>">
            <i class="bi bi-bell-fill"></i> Nhắc nhập điểm
        </a>

        <div class="sidebar-section-title">Báo cáo</div>
        <a href="/university/academic/reports.php"
           class="sidebar-link <?php echo $cur === 'reports.php' ? 'active' : ''; ?>">
            <i class="bi bi-clipboard-data-fill"></i> Báo cáo thống kê
        </a>
        <a href="/university/academic/notifications.php"
           class="sidebar-link <?php echo $cur === 'notifications.php' ? 'active' : ''; ?>">
            <i class="bi bi-megaphone-fill"></i> Thông báo
        </a>

        <div class="sidebar-section-title">Hệ thống</div>
        <?php if (canSwitchRole()): ?>
        <a href="/university/switch_role.php" class="sidebar-link text-warning">
            <i class="bi bi-arrow-left-right"></i> Chuyển vai trò
        </a>
        <?php endif; ?>
        <a href="/university/login.php?logout=1" class="sidebar-link text-danger">
            <i class="bi bi-box-arrow-right"></i> Đăng xuất
        </a>
    </nav>
</aside>
