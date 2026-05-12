<?php
/**
 * Student Sidebar — dùng chung cho tất cả trang trong university/student/
 * Yêu cầu: $student array đã được load trước khi include file này
 */
$_cur = basename($_SERVER['PHP_SELF']);
?>
<div class="student-sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon"><i class="bi bi-mortarboard-fill"></i></div>
        <div class="sidebar-brand-text">
            <div>Cổng Sinh viên</div>
            <small><?php echo htmlspecialchars($student['student_code'] ?? ''); ?></small>
        </div>
    </div>
    <nav class="sidebar-nav">
        <a href="/university/student/index.php"
           class="sidebar-link <?php echo $_cur === 'index.php' ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2"></i> Tổng quan
        </a>
        <a href="/university/student/profile.php"
           class="sidebar-link <?php echo $_cur === 'profile.php' ? 'active' : ''; ?>">
            <i class="bi bi-person-fill"></i> Hồ sơ cá nhân
        </a>
        <a href="/university/student/register_subject.php"
           class="sidebar-link <?php echo $_cur === 'register_subject.php' ? 'active' : ''; ?>">
            <i class="bi bi-journal-plus"></i> Đăng ký học phần
        </a>
        <a href="/university/student/my_subjects.php"
           class="sidebar-link <?php echo $_cur === 'my_subjects.php' ? 'active' : ''; ?>">
            <i class="bi bi-journal-check"></i> Học phần của tôi
        </a>
        <a href="/university/student/timetable.php"
           class="sidebar-link <?php echo $_cur === 'timetable.php' ? 'active' : ''; ?>">
            <i class="bi bi-calendar3-week"></i> Thời khóa biểu
        </a>
        <a href="/university/student/exam_schedule.php"
           class="sidebar-link <?php echo $_cur === 'exam_schedule.php' ? 'active' : ''; ?>">
            <i class="bi bi-calendar-event-fill"></i> Lịch thi cuối kỳ
        </a>
        <a href="/university/student/grades.php"
           class="sidebar-link <?php echo $_cur === 'grades.php' ? 'active' : ''; ?>">
            <i class="bi bi-bar-chart-fill"></i> Kết quả học tập
        </a>
        <a href="/university/student/tuition.php"
           class="sidebar-link <?php echo $_cur === 'tuition.php' ? 'active' : ''; ?>">
            <i class="bi bi-cash-coin"></i> Học phí
        </a>
        <a href="/university/student/evaluation.php"
           class="sidebar-link <?php echo $_cur === 'evaluation.php' ? 'active' : ''; ?>">
            <i class="bi bi-star-fill"></i> Đánh giá giảng viên
        </a>
        <hr class="my-2">
        <a href="/university/index.php" class="sidebar-link">
            <i class="bi bi-globe"></i> Trang chủ
        </a>
        <a href="/university/login.php?logout=1" class="sidebar-link text-danger">
            <i class="bi bi-box-arrow-right"></i> Đăng xuất
        </a>
    </nav>
</div>
