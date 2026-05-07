<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
$pageTitle = 'Tin tức & Thông báo';

$perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$type = $_GET['type'] ?? 'all';
$offset = ($page - 1) * $perPage;

// Count notifications
$countQuery = "SELECT COUNT(*) as c FROM notifications WHERE status='show'";
$totalNotifs = $conn->query($countQuery)->fetch_assoc()['c'] ?? 0;
$totalPages = ceil($totalNotifs / $perPage);

// Fetch notifications with pagination
$stmt = $conn->prepare("SELECT * FROM notifications WHERE status='show' ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param('ii', $perPage, $offset);
$stmt->execute();
$notifications = $stmt->get_result();
$stmt->close();

// Fetch admission news
$admNews = $conn->query("SELECT * FROM admission_news WHERE status='show' ORDER BY created_at DESC LIMIT 10");

include 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="/university/index.php">Trang chủ</a></li>
                <li class="breadcrumb-item active">Tin tức & Thông báo</li>
            </ol>
        </nav>
        <h1><i class="bi bi-newspaper me-2"></i>Tin tức & Thông báo</h1>
        <p class="text-white-50 mb-0">Cập nhật thông tin mới nhất từ Trường Đại học Thủ Dầu Một</p>
    </div>
</div>

<section class="py-5">
    <div class="container">
        <!-- Filter Tabs -->
        <ul class="nav nav-pills mb-4">
            <li class="nav-item">
                <a class="nav-link <?php echo $type=='all'?'active bg-navy':''; ?>" href="?type=all">
                    <i class="bi bi-grid me-1"></i>Tất cả
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $type=='notifications'?'active bg-navy':''; ?>" href="?type=notifications">
                    <i class="bi bi-bell me-1"></i>Thông báo
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $type=='admission'?'active bg-navy':''; ?>" href="?type=admission">
                    <i class="bi bi-newspaper me-1"></i>Tin tuyển sinh
                </a>
            </li>
        </ul>

        <div class="row g-4">
            <!-- Notifications Column -->
            <?php if ($type == 'all' || $type == 'notifications'): ?>
            <div class="col-lg-<?php echo ($type=='all') ? '7' : '12'; ?>">
                <h4 class="fw-bold text-navy mb-4">
                    <i class="bi bi-bell-fill text-gold me-2"></i>Thông báo nhà trường
                </h4>
                <?php if ($notifications && $notifications->num_rows > 0): ?>
                    <?php while ($notif = $notifications->fetch_assoc()): ?>
                    <div class="card mb-3">
                        <div class="card-body p-4">
                            <div class="d-flex gap-3">
                                <div class="flex-shrink-0">
                                    <div style="width:50px;height:50px;background:linear-gradient(135deg,var(--navy),var(--navy-light));border-radius:12px;display:flex;align-items:center;justify-content:center;">
                                        <i class="bi bi-bell-fill text-gold fs-5"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="fw-bold text-navy mb-1"><?php echo htmlspecialchars($notif['title']); ?></h6>
                                    <p class="text-muted small mb-2"><?php echo nl2br(htmlspecialchars($notif['content'])); ?></p>
                                    <div class="d-flex align-items-center gap-3">
                                        <small class="text-muted">
                                            <i class="bi bi-clock me-1"></i>
                                            <?php echo date('d/m/Y H:i', strtotime($notif['created_at'])); ?>
                                        </small>
                                        <?php if (!empty($notif['type'])): ?>
                                        <span class="badge bg-navy"><?php echo htmlspecialchars($notif['type']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?type=<?php echo $type; ?>&page=<?php echo $page-1; ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php for ($p = max(1, $page-2); $p <= min($totalPages, $page+2); $p++): ?>
                            <li class="page-item <?php echo $p==$page?'active':''; ?>">
                                <a class="page-link" href="?type=<?php echo $type; ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
                            </li>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?type=<?php echo $type; ?>&page=<?php echo $page+1; ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>Chưa có thông báo nào.
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Admission News Column -->
            <?php if ($type == 'all' || $type == 'admission'): ?>
            <div class="col-lg-<?php echo ($type=='all') ? '5' : '12'; ?>">
                <h4 class="fw-bold text-navy mb-4">
                    <i class="bi bi-newspaper text-gold me-2"></i>Tin tức tuyển sinh
                </h4>
                <?php if ($admNews && $admNews->num_rows > 0): ?>
                    <?php if ($type == 'all'): ?>
                        <?php while ($news = $admNews->fetch_assoc()): ?>
                        <div class="card mb-3">
                            <div class="card-body p-3">
                                <div class="d-flex gap-3">
                                    <?php if (!empty($news['image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($news['image_url']); ?>" alt="" style="width:80px;height:60px;object-fit:cover;border-radius:8px;flex-shrink:0;">
                                    <?php else: ?>
                                    <div style="width:80px;height:60px;background:linear-gradient(135deg,var(--navy),var(--navy-light));border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                        <i class="bi bi-newspaper text-gold"></i>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <h6 class="fw-bold text-navy small mb-1 text-truncate-2"><?php echo htmlspecialchars($news['title']); ?></h6>
                                        <small class="text-muted">
                                            <i class="bi bi-calendar3 me-1"></i>
                                            <?php echo date('d/m/Y', strtotime($news['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="row g-4">
                        <?php while ($news = $admNews->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card news-card h-100">
                                <?php if (!empty($news['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($news['image_url']); ?>" class="card-img-top" alt="" style="height:180px;object-fit:cover;">
                                <?php else: ?>
                                <div class="news-img-placeholder" style="height:180px;">
                                    <i class="bi bi-newspaper"></i>
                                </div>
                                <?php endif; ?>
                                <div class="card-body p-3">
                                    <div class="news-date mb-1">
                                        <i class="bi bi-calendar3 me-1"></i>
                                        <?php echo date('d/m/Y', strtotime($news['created_at'])); ?>
                                    </div>
                                    <h6 class="fw-bold text-navy text-truncate-2"><?php echo htmlspecialchars($news['title']); ?></h6>
                                    <?php if (!empty($news['content'])): ?>
                                    <p class="text-muted small text-truncate-3"><?php echo htmlspecialchars($news['content']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>Chưa có tin tức tuyển sinh nào.
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
