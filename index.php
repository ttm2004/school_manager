<?php
require_once 'config/db.php';
include 'includes/header.php';

// Cấu hình đường dẫn thư mục chứa ảnh
$image_path = "uploads/news/";

// Lấy Slide
$stmt = $conn->query("SELECT * FROM news WHERE type='slide' ORDER BY id DESC LIMIT 3");
$slides = $stmt->fetchAll();

// Lấy Tin tức
$stmt = $conn->query("SELECT * FROM news WHERE type='news' ORDER BY id DESC LIMIT 6");
$news_list = $stmt->fetchAll();
?>

<div id="homeCarousel" class="carousel slide carousel-fade shadow-lg" data-bs-ride="carousel">
    <div class="carousel-indicators">
        <?php foreach ($slides as $index => $slide): ?>
            <button type="button" data-bs-target="#homeCarousel" data-bs-slide-to="<?= $index ?>" class="<?= $index == 0 ? 'active' : '' ?>"></button>
        <?php endforeach; ?>
    </div>
    <div class="carousel-inner" style="border-radius: 0 0 50px 50px; overflow: hidden;">
        <?php foreach ($slides as $index => $slide): ?>
            <div class="carousel-item <?= $index == 0 ? 'active' : '' ?>">
                <div style="position: absolute; top:0; left:0; width:100%; height:100%; background: linear-gradient(to bottom, rgba(0,0,0,0.3), rgba(78, 84, 200, 0.6)); z-index: 1;"></div>

                <img src="<?= $image_path . $slide['image_url'] ?>" class="d-block w-100" style="height: 600px; object-fit: cover;" alt="<?= htmlspecialchars($slide['title']) ?>">

                <div class="carousel-caption d-none d-md-block text-start" style="z-index: 2; bottom: 20%; left: 10%;">
                    <h5 class="display-4 fw-bold text-white mb-3" style="text-shadow: 2px 2px 4px rgba(0,0,0,0.5);"><?= htmlspecialchars($slide['title']) ?></h5>
                    <p class="lead text-light mb-4 w-75"><?= mb_strimwidth(strip_tags($slide['content']), 0, 150, "...") ?></p>
                    <a href="news_detail.php?id=<?= $slide['id'] ?>" class="btn btn-gradient rounded-pill px-5 py-2 fw-bold">Xem Chi Tiết</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#homeCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon"></span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#homeCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon"></span>
    </button>
</div>

<div class="container" style="margin-top: -50px; position: relative; z-index: 10;">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card border-0 shadow-lg rounded-4 p-4 bg-white">
                <div class="row text-center">

                    <div class="col-md-3 mb-3 mb-md-0">
                        <a href="thuvien.html" class="text-decoration-none text-dark">
                            <div class="p-3">
                                <i class="fas fa-book-reader fa-3x text-primary mb-3"></i>
                                <h5 class="fw-bold">Thư viện số</h5>
                                <p class="text-muted small">Tra cứu tài liệu học tập miễn phí</p>
                            </div>
                        </a>
                    </div>

                    <div class="col-md-3 mb-3 mb-md-0 border-start border-light">
                        <a href="lichhoc.html" class="text-decoration-none text-dark">
                            <div class="p-3">
                                <i class="fas fa-calendar-alt fa-3x text-warning mb-3"></i>
                                <h5 class="fw-bold">Lịch học</h5>
                                <p class="text-muted small">Cập nhật thời khóa biểu mới nhất</p>
                            </div>
                        </a>
                    </div>

                    <div class="col-md-3 mb-3 mb-md-0 border-start border-light">
                        <a href="../admissions/tra-cuu-tuyen-sinh.php" class="text-decoration-none text-dark">
                            <div class="p-3">
                                <i class="fas fa-chart-line fa-3x text-success mb-3"></i>
                                <h5 class="fw-bold">Kết quả thi</h5>
                                <p class="text-muted small">Tra cứu điểm thi nhanh chóng</p>
                            </div>
                        </a>
                    </div>

                    <div class="col-md-3 border-start border-light">
                        <a href="/admissions/index.php" class="text-decoration-none text-dark">
                            <div class="p-3">
                                <i class="fas fa-user-graduate fa-3x text-danger mb-3"></i>
                                <h5 class="fw-bold">Tuyển sinh</h5>
                                <p class="text-muted small">Thông tin tuyển sinh năm 2026</p>
                            </div>
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<div class="container py-5" id="news">
    <div class="text-center mb-5">
        <h2 class="section-title text-uppercase display-6 fw-bold">Tin Tức & Sự Kiện</h2>
        <div class="mx-auto bg-primary" style="height: 3px; width: 60px;"></div>
    </div>

    <div class="row g-4">
        <?php foreach ($news_list as $news): ?>
            <div class="col-md-4">
                <div class="card card-hover h-100 border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-img-wrapper" style="height: 200px; overflow: hidden;">
                        <img src="<?= $image_path . $news['image_url'] ?>" class="card-img-top w-100 h-100" style="object-fit: cover;" alt="<?= htmlspecialchars($news['title']) ?>">
                        <div class="position-absolute top-0 end-0 bg-warning text-dark fw-bold px-3 py-1 rounded-start mt-3">MỚI</div>
                    </div>
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3 text-muted small">
                            <i class="far fa-calendar-alt me-2"></i>
                            <?= date('d/m/Y', strtotime($news['created_at'])) ?>
                        </div>
                        <h5 class="card-title fw-bold mb-3"><?= htmlspecialchars($news['title']) ?></h5>
                        <p class="card-text text-muted small"><?= mb_strimwidth(strip_tags($news['content']), 0, 100, "...") ?></p>
                    </div>
                    <div class="card-footer bg-white border-0 pb-4 pt-0 px-4">
                        <a href="news_detail.php?id=<?= $news['id'] ?>" class="text-primary text-decoration-none fw-bold">Xem thêm <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="text-center mt-5">
        <a href="all_news.php" class="btn btn-outline-primary rounded-pill px-5 py-2 fw-bold">Xem tất cả tin tức</a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>