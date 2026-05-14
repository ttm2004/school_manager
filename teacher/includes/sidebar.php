<?php
/**
 * Teacher Sidebar — dùng chung cho tất cả trang trong university/teacher/
 * Yêu cầu: $teacher array đã được load trước khi include file này
 */
require_once __DIR__ . '/../../includes/AcademicPolicy.php';
$_cur = basename($_SERVER['PHP_SELF']);
$teachingWishSemesterId = 0;
if (isset($conn) && $conn instanceof mysqli) {
    $semRes = $conn->query(
        "SELECT id FROM semesters
         WHERE proposal_start IS NOT NULL AND proposal_end IS NOT NULL
         ORDER BY COALESCE(start_date, created_at) DESC, id DESC"
    );
    if ($semRes) {
        while ($sem = $semRes->fetch_assoc()) {
            $window = academicPolicyCheckFacultyProposalWindow($conn, (int)$sem['id']);
            if ($window['ok']) {
                $teachingWishSemesterId = (int)$sem['id'];
                break;
            }
        }
    }
}
?>
<div class="student-sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon"><i class="bi bi-person-badge-fill"></i></div>
        <div class="sidebar-brand-text">
            <div>Cổng Giảng viên</div>
            <small><?php echo htmlspecialchars($teacher['teacher_code'] ?? ''); ?></small>
        </div>
    </div>
    <nav class="sidebar-nav">
        <a href="/university/teacher/index.php"
           class="sidebar-link <?php echo $_cur === 'index.php' ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2"></i> Tổng quan
        </a>
        <a href="/university/teacher/profile.php"
           class="sidebar-link <?php echo $_cur === 'profile.php' ? 'active' : ''; ?>">
            <i class="bi bi-person-fill"></i> Hồ sơ cá nhân
        </a>
        <a href="/university/teacher/my_courses.php"
           class="sidebar-link <?php echo $_cur === 'my_courses.php' ? 'active' : ''; ?>">
            <i class="bi bi-journal-text"></i> Lớp học phần
        </a>
        <a href="/university/teacher/timetable.php"
           class="sidebar-link <?php echo $_cur === 'timetable.php' ? 'active' : ''; ?>">
            <i class="bi bi-calendar3-week"></i> Thời khóa biểu
        </a>
        <a href="/university/teacher/exam_schedule.php"
           class="sidebar-link <?php echo $_cur === 'exam_schedule.php' ? 'active' : ''; ?>">
            <i class="bi bi-calendar-event-fill"></i> Lịch thi cuối kỳ
        </a>
        <a href="/university/teacher/grades.php"
           class="sidebar-link <?php echo $_cur === 'grades.php' ? 'active' : ''; ?>">
            <i class="bi bi-bar-chart-fill"></i> Nhập điểm
        </a>
        <a href="/university/teacher/evaluation.php"
           class="sidebar-link <?php echo $_cur === 'evaluation.php' ? 'active' : ''; ?>">
            <i class="bi bi-star-fill"></i> Kết quả đánh giá
        </a>
        <?php if ($teachingWishSemesterId > 0): ?>
        <a href="/university/faculty/teaching_wishes.php?semester_id=<?php echo $teachingWishSemesterId; ?>"
           class="sidebar-link">
            <i class="bi bi-hand-index-fill"></i> Đăng ký nguyện vọng dạy
        </a>
        <?php endif; ?>
        <hr class="my-2">
        <?php if (canSwitchRole()): ?>
        <a href="/university/switch_role.php" class="sidebar-link text-warning">
            <i class="bi bi-arrow-left-right"></i> Chuyển vai trò
        </a>
        <?php endif; ?>
        <a href="/university/index.php" class="sidebar-link">
            <i class="bi bi-globe"></i> Trang chủ
        </a>
        <a href="/university/login.php?logout=1" class="sidebar-link text-danger">
            <i class="bi bi-box-arrow-right"></i> Đăng xuất
        </a>
    </nav>
</div>
