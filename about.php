<?php
include 'includes/header.php';
?>

<div class="container py-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Trang chủ</a></li>
            <li class="breadcrumb-item active">Giới thiệu</li>
        </ol>
    </nav>

    <div class="row align-items-center g-5 py-4">
        <div class="col-lg-6">
            <h1 class="display-5 fw-bold lh-1 mb-3 text-primary">Về EDUTECH 2026</h1>
            <p class="lead text-muted">Chào mừng bạn đến với hệ thống quản lý giáo dục thông minh hàng đầu. Chúng tôi cung cấp giải pháp tối ưu để kết nối nhà trường, giáo viên và học sinh trong thời đại số.</p>
            <div class="d-grid gap-2 d-md-flex justify-content-md-start mt-4">
                <button type="button" class="btn btn-primary btn-lg px-4 me-md-2 rounded-pill shadow">Bắt đầu ngay</button>
                <button type="button" class="btn btn-outline-secondary btn-lg px-4 rounded-pill">Liên hệ tư vấn</button>
            </div>
        </div>
        <div class="col-lg-6">
            <img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" class="d-block mx-lg-auto img-fluid rounded-5 shadow-lg" alt="Education" width="700" height="500" loading="lazy">
        </div>
    </div>

    <div class="row row-cols-1 row-cols-md-3 g-4 py-5 text-center">
        <div class="col">
            <div class="card h-100 border-0 bg-light rounded-4 p-4">
                <i class="fas fa-user-graduate fa-3x text-warning mb-3"></i>
                <h3 class="fw-bold">10,000+</h3>
                <p class="text-muted">Sinh viên đang sử dụng</p>
            </div>
        </div>
        <div class="col">
            <div class="card h-100 border-0 bg-light rounded-4 p-4">
                <i class="fas fa-chalkboard-teacher fa-3x text-primary mb-3"></i>
                <h3 class="fw-bold">500+</h3>
                <p class="text-muted">Giảng viên tâm huyết</p>
            </div>
        </div>
        <div class="col">
            <div class="card h-100 border-0 bg-light rounded-4 p-4">
                <i class="fas fa-school fa-3x text-success mb-3"></i>
                <h3 class="fw-bold">50+</h3>
                <p class="text-muted">Khoa và Ngành đào tạo</p>
            </div>
        </div>
    </div>

    <div class="row g-4 py-5">
        <div class="col-md-6">
            <h2 class="fw-bold mb-3 border-start border-primary border-4 ps-3">Tầm nhìn</h2>
            <p class="text-secondary">Trở thành nền tảng giáo dục số 1 tại Việt Nam, mang đến trải nghiệm học tập hạnh phúc và hiệu quả cho mọi cá nhân thông qua công nghệ hiện đại.</p>
        </div>
        <div class="col-md-6">
            <h2 class="fw-bold mb-3 border-start border-warning border-4 ps-3">Sứ mệnh</h2>
            <p class="text-secondary">Xây dựng môi trường giáo dục minh bạch, dễ dàng tiếp cận và không ngừng đổi mới sáng tạo để ươm mầm các tài năng tương lai cho đất nước.</p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>