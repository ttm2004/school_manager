<?php
require_once 'config/db.php';
include 'includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$image_path = "uploads/news/";

// Lấy chi tiết bài viết
$stmt = $conn->prepare("SELECT * FROM news WHERE id = ?");
$stmt->execute([$id]);
$news = $stmt->fetch();

if (!$news) {
    echo "<div class='container py-5 text-center'><h3>Bài viết không tồn tại.</h3><a href='index.php'>Quay lại trang chủ</a></div>";
    include 'includes/footer.php';
    exit;
}

// Lấy tin tức liên quan (các tin khác)
$related_stmt = $conn->prepare("SELECT * FROM news WHERE id != ? AND type='news' ORDER BY id DESC LIMIT 4");
$related_stmt->execute([$id]);
$related_news = $related_stmt->fetchAll();
?>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb small">
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Trang chủ</a></li>
                    <li class="breadcrumb-item"><a href="all_news.php" class="text-decoration-none">Tin tức</a></li>
                    <li class="breadcrumb-item active text-truncate" style="max-width: 200px;"><?= htmlspecialchars($news['title']) ?></li>
                </ol>
            </nav>

            <h1 class="fw-bold mb-3"><?= htmlspecialchars($news['title']) ?></h1>
            <div class="text-muted small mb-4">
                <i class="far fa-calendar-alt me-2"></i> Đăng ngày <?= date('d/m/Y H:i', strtotime($news['created_at'])) ?>
            </div>

            <div class="rounded-4 overflow-hidden mb-4 shadow-sm">
                <img src="<?= $image_path . $news['image_url'] ?>" class="img-fluid w-100" alt="...">
            </div>

            <div class="news-content fs-5 lh-lg text-dark">
                <?= nl2br($news['content']) ?>
            </div>

            <hr class="my-5">
            <div class="d-flex justify-content-between">
                <a href="all_news.php" class="btn btn-light rounded-pill px-4"><i class="fas fa-chevron-left me-2"></i>Quay lại</a>
                <div class="share-buttons">
                    <span class="small text-muted me-2">Chia sẻ:</span>
                    <a href="#" class="text-primary me-2"><i class="fab fa-facebook fa-lg"></i></a>
                    <a href="#" class="text-info"><i class="fab fa-twitter fa-lg"></i></a>
                </div>
            </div>
        </div>

        <div class="col-lg-4 mt-5 mt-lg-0">
            <div class="sticky-top" style="top: 100px; z-index: 1;">
                <h5 class="fw-bold mb-4 border-start border-warning border-4 ps-3">Tin liên quan</h5>
                <?php foreach($related_news as $r): ?>
                <a href="news_detail.php?id=<?= $r['id'] ?>" class="text-decoration-none group mb-4 d-block">
                    <div class="row g-2 align-items-center">
                        <div class="col-4">
                            <img src="<?= $image_path . $r['image_url'] ?>" class="img-fluid rounded-3 shadow-sm" style="height: 70px; width: 100%; object-fit: cover;">
                        </div>
                        <div class="col-8">
                            <h6 class="text-dark fw-bold mb-1 small lh-base"><?= htmlspecialchars($r['title']) ?></h6>
                            <small class="text-muted"><?= date('d/m/Y', strtotime($r['created_at'])) ?></small>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>

                <div class="card border-0 bg-primary text-white p-4 rounded-4 mt-5">
                    <h6 class="fw-bold"><i class="fas fa-paper-plane me-2"></i>Đăng ký nhận tin</h6>
                    <p class="small opacity-75">Nhận thông báo mới nhất từ Edutech qua email.</p>
                    <div class="input-group">
                        <input type="email" class="form-control form-control-sm border-0" placeholder="Email của bạn">
                        <button class="btn btn-warning btn-sm">Gửi</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>