<?php
require_once 'config/db.php';
include 'includes/header.php';

/**
 * LẤY DANH SÁCH GIÁO VIÊN ĐỘNG
 */
try {
    // Truy vấn tất cả người dùng có vai trò là giáo viên
    $stmt = $conn->prepare("SELECT id, full_name, username, avatar, email FROM users WHERE role = 'teacher' ORDER BY full_name ASC");
    $stmt->execute();
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $teachers = [];
}
?>

<div class="container py-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Trang chủ</a></li>
            <li class="breadcrumb-item active">Đội ngũ giảng viên</li>
        </ol>
    </nav>

    <div class="text-center mb-5">
        <h2 class="fw-bold text-uppercase display-6">Đội Ngũ Giảng Viên</h2>
        <div class="mx-auto bg-warning mb-4" style="height: 3px; width: 80px;"></div>
        <p class="text-muted w-75 mx-auto fs-5">
            Tại <strong>EDUTECH 2026</strong>, chúng tôi tự hào sở hữu đội ngũ giảng viên giàu kinh nghiệm, 
            không chỉ giỏi về chuyên môn mà còn tận tâm trong việc truyền cảm hứng và dẫn dắt sinh viên 
            chinh phục những đỉnh cao kiến thức mới.
        </p>
    </div>

    <div class="row g-4">
        <?php if (empty($teachers)): ?>
            <div class="col-12 text-center py-5">
                <p class="text-muted">Hiện chưa có dữ liệu giảng viên.</p>
            </div>
        <?php else: ?>
            <?php foreach($teachers as $t): ?>
                <?php 
                    // Kiểm tra ảnh đại diện, nếu không có dùng ảnh mặc định dựa trên tên
                    $avatar_path = !empty($t['avatar']) ? "uploads/avatars/" . $t['avatar'] : "https://ui-avatars.com/api/?name=" . urlencode($t['full_name']) . "&background=random&color=fff";
                ?>
                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 text-center p-3 card-hover">
                        <div class="pt-3">
                            <img src="<?= $avatar_path ?>" 
                                 class="rounded-circle border border-3 border-light shadow-sm" 
                                 style="width: 120px; height: 120px; object-fit: cover;" 
                                 alt="<?= htmlspecialchars($t['full_name']) ?>">
                        </div>
                        <div class="card-body">
                            <h5 class="fw-bold mb-1 text-primary"><?= htmlspecialchars($t['full_name']) ?></h5>
                            <p class="text-muted small mb-2">Giảng viên chuyên ngành</p>
                            <div class="d-flex justify-content-center gap-2 mb-3">
                                <a href="mailto:<?= $t['email'] ?>" class="btn btn-sm btn-light rounded-circle text-danger shadow-sm"><i class="fas fa-envelope"></i></a>
                                <a href="#" class="btn btn-sm btn-light rounded-circle text-primary shadow-sm"><i class="fab fa-facebook-f"></i></a>
                                <a href="#" class="btn btn-sm btn-light rounded-circle text-info shadow-sm"><i class="fab fa-linkedin-in"></i></a>
                            </div>
                            <button class="btn btn-outline-primary btn-sm rounded-pill px-4">Xem hồ sơ</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="mt-5 p-5 bg-primary text-white rounded-5 shadow-lg text-center">
        <h3 class="fw-bold">Bạn muốn trở thành một phần của EDUTECH?</h3>
        <p class="opacity-75 mb-4">Chúng tôi luôn chào đón những giảng viên tài năng và tâm huyết cùng xây dựng tương lai giáo dục Việt Nam.</p>
        <a href="contact.php" class="btn btn-warning rounded-pill px-5 fw-bold py-2 shadow">Gửi hồ sơ ứng tuyển</a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>