<?php
$currentFile = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
?>
<aside class="admin-sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon">
            <i class="bi bi-mortarboard-fill"></i>
        </div>
        <div class="sidebar-brand-text">
            <div>Trường ĐH</div>
            <small>Thủ Dầu Một</small>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-section-title">Tổng quan</div>
        <a href="/university/admin/index.php" class="sidebar-link <?php echo $currentFile=='index.php'&&$currentDir=='admin'?'active':''; ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="sidebar-section-title">Quản lý Nhân viên</div>
        <a href="/university/admin/users.php" class="sidebar-link <?php echo $currentFile=='users.php'?'active':''; ?>">
            <i class="bi bi-people-fill"></i> Nhân viên
        </a>
        <a href="/university/admin/students.php" class="sidebar-link <?php echo $currentFile=='students.php'?'active':''; ?>">
            <i class="bi bi-person-fill"></i> Sinh viên
        </a>
        <a href="/university/admin/teachers.php" class="sidebar-link <?php echo $currentFile=='teachers.php'?'active':''; ?>">
            <i class="bi bi-person-badge-fill"></i> Giảng viên
        </a>

        <div class="sidebar-section-title">Đào tạo</div>
        <a href="/university/admin/faculties.php" class="sidebar-link <?php echo $currentFile=='faculties.php'?'active':''; ?>">
            <i class="bi bi-building-fill"></i> Khoa
        </a>
        <a href="/university/admin/majors.php" class="sidebar-link <?php echo $currentFile=='majors.php'?'active':''; ?>">
            <i class="bi bi-book-fill"></i> Ngành học
        </a>
        <a href="/university/admin/classes.php" class="sidebar-link <?php echo $currentFile=='classes.php'?'active':''; ?>">
            <i class="bi bi-collection-fill"></i> Lớp học
        </a>
        <a href="/university/admin/semesters.php" class="sidebar-link <?php echo $currentFile=='semesters.php'?'active':''; ?>">
            <i class="bi bi-calendar3"></i> Học kỳ
        </a>
        <a href="/university/admin/subjects.php" class="sidebar-link <?php echo $currentFile=='subjects.php'?'active':''; ?>">
            <i class="bi bi-journal-text"></i> Môn học
        </a>
        <a href="/university/admin/course_sections.php" class="sidebar-link <?php echo $currentFile=='course_sections.php'?'active':''; ?>">
            <i class="bi bi-grid-3x3-gap-fill"></i> Lớp học phần
        </a>
        <a href="/university/admin/teacher_assignments.php" class="sidebar-link <?php echo $currentFile=='teacher_assignments.php'?'active':''; ?>">
            <i class="bi bi-person-lines-fill"></i> Phân công GV
        </a>
        <a href="/university/admin/grades.php" class="sidebar-link <?php echo $currentFile=='grades.php'?'active':''; ?>">
            <i class="bi bi-bar-chart-fill"></i> Điểm số
        </a>
        <a href="/university/admin/curriculum.php" class="sidebar-link <?php echo $currentFile=='curriculum.php'?'active':''; ?>">
            <i class="bi bi-journal-bookmark-fill"></i> Chương trình ĐT
        </a>
        <a href="/university/admin/final_exam_schedules.php" class="sidebar-link <?php echo $currentFile=='final_exam_schedules.php'?'active':''; ?>">
            <i class="bi bi-calendar-event-fill"></i> Lịch thi cuối kỳ
        </a>
        <a href="/university/admin/tuition.php" class="sidebar-link <?php echo $currentFile=='tuition.php'?'active':''; ?>">
            <i class="bi bi-cash-coin"></i> Học phí
        </a>
        <a href="/university/admin/tuition.php" class="sidebar-link <?php echo $currentFile=='tuition.php'?'active':''; ?>">
            <i class="bi bi-cash-coin"></i> Học phí
        </a>

        <div class="sidebar-section-title">Tuyển sinh</div>
        <a href="/university/admissions/" class="sidebar-link <?php echo $currentDir==='admissions'?'active':''; ?>" style="background:rgba(245,166,35,.08);border-left:3px solid var(--gold);">
            <i class="bi bi-mortarboard-fill text-gold"></i> <strong>Module Tuyển sinh</strong>
        </a>

        <div class="sidebar-section-title">Đánh giá giảng viên</div>
        <a href="/university/admin/evaluation_periods.php" class="sidebar-link <?php echo $currentFile=='evaluation_periods.php'?'active':''; ?>">
            <i class="bi bi-clipboard-check-fill"></i> Đợt đánh giá
        </a>
        <a href="/university/admin/evaluation_questions.php" class="sidebar-link <?php echo $currentFile=='evaluation_questions.php'?'active':''; ?>">
            <i class="bi bi-question-circle-fill"></i> Câu hỏi đánh giá
        </a>
        <a href="/university/admin/evaluation_results.php" class="sidebar-link <?php echo $currentFile=='evaluation_results.php'?'active':''; ?>">
            <i class="bi bi-graph-up-arrow"></i> Kết quả đánh giá
        </a>

        <div class="sidebar-section-title">Truyền thông</div>
        <a href="/university/admin/notifications.php" class="sidebar-link <?php echo $currentFile=='notifications.php'?'active':''; ?>">
            <i class="bi bi-bell-fill"></i> Thông báo
        </a>
        <a href="/university/admin/contacts.php" class="sidebar-link <?php echo $currentFile=='contacts.php'?'active':''; ?>">
            <i class="bi bi-chat-dots-fill"></i> Liên hệ
        </a>

        <div class="sidebar-section-title">Hệ thống</div>
        <a href="/university/index.php" class="sidebar-link" target="_blank">
            <i class="bi bi-globe"></i> Xem trang web
        </a>
        <a href="/university/login.php?logout=1" class="sidebar-link text-danger">
            <i class="bi bi-box-arrow-right"></i> Đăng xuất
        </a>
    </nav>
</aside>
<?php include_once __DIR__ . '/../../includes/analytics_widget.php'; ?>
