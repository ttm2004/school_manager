<?php
require_once 'config/db.php';
include 'includes/header.php';

$image_path = "uploads/news/";
$limit = 9; // Số tin mỗi trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Tính tổng số tin để phân trang
$total_stmt = $conn->query("SELECT COUNT(*) FROM news WHERE type='news'");
$total_news = $total_stmt->fetchColumn();
$total_pages = ceil($total_news / $limit);

// Lấy dữ liệu tin tức theo trang
$stmt = $conn->prepare("SELECT * FROM news WHERE type='news' ORDER BY created_at DESC LIMIT :start, :limit");
$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$news_list = $stmt->fetchAll();
?>

<div class="container py-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Trang chủ</a></li>
            <li class="breadcrumb-item active">Tất cả tin tức</li>
        </ol>
    </nav>

    <div class="text-center mb-5">
        <h2 class="fw-bold text-uppercase">Bản tin Edutech</h2>
        <div class="mx-auto bg-warning" style="height: 3px; width: 60px;"></div>
    </div>

    <div class="row g-4">
        <?php foreach($news_list as $news): ?>
            <div class="col-md-4">
                <div class="card card-hover h-100 border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-img-wrapper" style="height: 200px; overflow: hidden;">
                        <img src="<?= $image_path . $news['image_url'] ?>" class="card-img-top w-100 h-100" style="object-fit: cover;" alt="...">
                    </div>
                    <div class="card-body p-4">
                        <div class="small text-muted mb-2"><?= date('d/m/Y', strtotime($news['created_at'])) ?></div>
                        <h5 class="card-title fw-bold mb-3"><?= htmlspecialchars($news['title']) ?></h5>
                        <p class="card-text text-muted small"><?= mb_strimwidth(strip_tags($news['content']), 0, 100, "...") ?></p>
                    </div>
                    <div class="card-footer bg-white border-0 pb-4 px-4">
                        <a href="news_detail.php?id=<?= $news['id'] ?>" class="text-primary text-decoration-none fw-bold small">Đọc tiếp <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <nav class="mt-5">
        <ul class="pagination justify-content-center">
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link shadow-none" href="all_news.php?page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>