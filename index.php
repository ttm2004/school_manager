 <?php
require_once 'config/database.php';
require_once 'includes/auth.php';
$pageTitle = 'Trang chủ';
include 'includes/header.php';

// Fetch featured majors
$majors = $conn->query("SELECT m.*, f.faculty_name FROM majors m LEFT JOIN faculties f ON m.faculty_id = f.id WHERE m.status='open' LIMIT 6");

// Fetch latest notifications
$notifications = $conn->query("SELECT * FROM notifications WHERE status='show' ORDER BY created_at DESC LIMIT 4");

// Fetch latest admission news
$admNews = $conn->query("SELECT * FROM admission_news WHERE status='show' ORDER BY created_at DESC LIMIT 3");

// Stats
$totalStudents = $conn->query("SELECT COUNT(*) as c FROM students")->fetch_assoc()['c'] ?? 0;
$totalMajors = $conn->query("SELECT COUNT(*) as c FROM majors WHERE status='open'")->fetch_assoc()['c'] ?? 0;
$totalTeachers = $conn->query("SELECT COUNT(*) as c FROM teachers")->fetch_assoc()['c'] ?? 0;
$totalFaculties = $conn->query("SELECT COUNT(*) as c FROM faculties")->fetch_assoc()['c'] ?? 0;
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="hero-dots"></div>
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-7 hero-content">
                <div class="hero-badge">
                    <i class="bi bi-star-fill"></i> Chào mừng đến với TDMU
                </div>
                <h1 class="hero-title">
                    Trường Đại học<br>
                    <span>Thủ Dầu Một</span>
                </h1>
                <p class="hero-subtitle">
                    Nơi đào tạo nguồn nhân lực chất lượng cao, kết hợp lý thuyết và thực tiễn,
                    phục vụ sự nghiệp phát triển kinh tế - xã hội của tỉnh Bình Dương và cả nước.
                </p>
                <div class="hero-actions">
                    <a href="/university/admission.php" class="btn btn-gold btn-lg px-4 py-2">
                        <i class="bi bi-pencil-square me-2"></i>Đăng ký tuyển sinh
                    </a>
                    <a href="/university/about.php" class="btn btn-outline-light btn-lg px-4 py-2">
                        <i class="bi bi-info-circle me-2"></i>Tìm hiểu thêm
                    </a>
                </div>
                <div class="hero-trust">
                    <div class="hero-trust-item">
                        <i class="bi bi-patch-check-fill"></i>
                        <span>Kiểm định chất lượng quốc gia</span>
                    </div>
                    <div class="hero-trust-item">
                        <i class="bi bi-award-fill"></i>
                        <span>Hơn 25 năm kinh nghiệm</span>
                    </div>
                    <div class="hero-trust-item">
                        <i class="bi bi-building-fill"></i>
                        <span>Cơ sở vật chất hiện đại</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 d-none d-lg-block hero-visual">
                <div class="hero-card-main">
                    <div class="hero-icon-wrap">
                        <i class="bi bi-mortarboard-fill"></i>
                    </div>
                    <div class="text-white fw-bold fs-5 mb-1">Trường Đại học Thủ Dầu Một</div>
                    <div style="color:var(--gold);font-size:0.85rem;font-weight:600;letter-spacing:0.05em;">Thu Dau Mot University · TDMU</div>
                    <div class="hero-mini-stats">
                        <div class="hero-mini-stat">
                            <div class="val"><?php echo number_format($totalStudents); ?>+</div>
                            <div class="lbl">Sinh viên</div>
                        </div>
                        <div class="hero-mini-stat">
                            <div class="val"><?php echo $totalMajors; ?>+</div>
                            <div class="lbl">Ngành đào tạo</div>
                        </div>
                        <div class="hero-mini-stat">
                            <div class="val"><?php echo $totalTeachers; ?>+</div>
                            <div class="lbl">Giảng viên</div>
                        </div>
                        <div class="hero-mini-stat">
                            <div class="val"><?php echo $totalFaculties; ?></div>
                            <div class="lbl">Khoa đào tạo</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="stats-section">
    <div class="container">
        <div class="row g-0">
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($totalStudents); ?>+</div>
                    <div class="stat-label"><i class="bi bi-people-fill me-1"></i>Sinh viên</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalMajors; ?>+</div>
                    <div class="stat-label"><i class="bi bi-book-fill me-1"></i>Ngành đào tạo</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalTeachers; ?>+</div>
                    <div class="stat-label"><i class="bi bi-person-badge-fill me-1"></i>Giảng viên</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalFaculties; ?></div>
                    <div class="stat-label"><i class="bi bi-building-fill me-1"></i>Khoa đào tạo</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured Majors -->
<section class="py-5" style="background: var(--light-bg);">
    <div class="container">
        <div class="row align-items-end mb-5">
            <div class="col-lg-7">
                <h2 class="section-title">Ngành đào tạo nổi bật</h2>
                <p class="section-subtitle mb-0">Khám phá các chương trình đào tạo chất lượng cao tại Trường Đại học Thủ Dầu Một</p>
            </div>
            <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
                <a href="/university/admission.php" class="btn btn-outline-navy">
                    Xem tất cả ngành <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
        <div class="row g-4">
            <?php
            $icons = ['bi-laptop', 'bi-calculator', 'bi-heart-pulse', 'bi-building', 'bi-palette', 'bi-translate'];
            $i = 0;
            if ($majors && $majors->num_rows > 0):
                while ($major = $majors->fetch_assoc()):
                    $icon = $icons[$i % count($icons)];
                    $i++;
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="card major-card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-start gap-3">
                            <div class="major-icon">
                                <i class="bi <?php echo $icon; ?>"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <h5 class="fw-bold text-navy mb-1 fs-6"><?php echo htmlspecialchars($major['major_name']); ?></h5>
                                <div class="text-muted small mb-2 d-flex align-items-center gap-1">
                                    <i class="bi bi-building"></i>
                                    <span><?php echo htmlspecialchars($major['faculty_name'] ?? 'N/A'); ?></span>
                                </div>
                                <?php if (!empty($major['description'])): ?>
                                <p class="text-muted small text-truncate-2 mb-2" style="line-height:1.5;"><?php echo htmlspecialchars($major['description']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($major['tuition_per_credit'])): ?>
                                <div class="d-inline-flex align-items-center gap-1 px-2 py-1 rounded-2" style="background:rgba(245,166,35,0.12);">
                                    <i class="bi bi-cash text-gold" style="font-size:0.8rem;"></i>
                                    <span class="fw-bold small" style="color:var(--gold-dark);"><?php echo number_format($major['tuition_per_credit']); ?> VNĐ/tín chỉ</span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-top pt-0 pb-3 px-4" style="border-color:var(--border-color)!important;">
                        <a href="/university/admission.php" class="btn btn-sm btn-outline-navy w-100">
                            Tìm hiểu thêm <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endwhile; else: ?>
            <div class="col-12">
                <div class="alert alert-info">Chưa có ngành đào tạo nào được hiển thị.</div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Latest Notifications -->
<section class="py-5 bg-white">
    <div class="container">
        <div class="row align-items-end mb-5">
            <div class="col-lg-7">
                <h2 class="section-title">Thông báo mới nhất</h2>
                <p class="section-subtitle mb-0">Cập nhật các thông tin quan trọng từ nhà trường</p>
            </div>
            <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
                <a href="/university/news.php" class="btn btn-outline-navy">Xem tất cả <i class="bi bi-arrow-right ms-1"></i></a>
            </div>
        </div>
        <div class="row g-4">
            <?php
            if ($notifications && $notifications->num_rows > 0):
                while ($notif = $notifications->fetch_assoc()):
            ?>
            <div class="col-md-6">
                <div class="card h-100" style="border-left:4px solid var(--navy);">
                    <div class="card-body p-4">
                        <div class="d-flex gap-3">
                            <div class="flex-shrink-0">
                                <div style="width:46px;height:46px;background:linear-gradient(135deg,var(--navy),var(--navy-light));border-radius:12px;display:flex;align-items:center;justify-content:center;">
                                    <i class="bi bi-bell-fill text-gold fs-6"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <h6 class="fw-bold text-navy mb-1 text-truncate-2"><?php echo htmlspecialchars($notif['title']); ?></h6>
                                <p class="text-muted small text-truncate-2 mb-2"><?php echo htmlspecialchars($notif['content']); ?></p>
                                <div class="d-flex align-items-center gap-1 text-muted" style="font-size:0.78rem;">
                                    <i class="bi bi-clock"></i>
                                    <span><?php echo date('d/m/Y', strtotime($notif['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; else: ?>
            <div class="col-12"><div class="alert alert-info">Chưa có thông báo nào.</div></div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Admission News -->
<section class="py-5" style="background:var(--light-bg);">
    <div class="container">
        <div class="row align-items-end mb-5">
            <div class="col-lg-7">
                <h2 class="section-title">Tin tức tuyển sinh</h2>
                <p class="section-subtitle mb-0">Thông tin tuyển sinh mới nhất năm <?php echo date('Y'); ?></p>
            </div>
            <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
                <a href="/university/news.php" class="btn btn-outline-navy">Xem tất cả <i class="bi bi-arrow-right ms-1"></i></a>
            </div>
        </div>
        <div class="row g-4">
            <?php
            if ($admNews && $admNews->num_rows > 0):
                while ($news = $admNews->fetch_assoc()):
            ?>
            <div class="col-md-4">
                <div class="card news-card h-100">
                    <?php if (!empty($news['image_url'])): ?>
                    <img src="<?php echo htmlspecialchars($news['image_url']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($news['title']); ?>">
                    <?php else: ?>
                    <div class="news-img-placeholder">
                        <i class="bi bi-newspaper"></i>
                    </div>
                    <?php endif; ?>
                    <div class="card-body p-4">
                        <div class="news-date mb-2 d-flex align-items-center gap-1">
                            <i class="bi bi-calendar3"></i>
                            <span><?php echo date('d/m/Y', strtotime($news['created_at'])); ?></span>
                        </div>
                        <h6 class="fw-bold text-navy text-truncate-2 mb-2" style="line-height:1.5;"><?php echo htmlspecialchars($news['title']); ?></h6>
                        <?php if (!empty($news['content'])): ?>
                        <p class="text-muted small text-truncate-3 mb-0" style="line-height:1.6;"><?php echo htmlspecialchars($news['content']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-transparent pt-0 pb-3 px-4" style="border-color:var(--border-color)!important;">
                        <a href="/university/news.php" class="btn btn-sm btn-outline-navy">Đọc thêm <i class="bi bi-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
            <?php endwhile; else: ?>
            <div class="col-12"><div class="alert alert-info">Chưa có tin tức tuyển sinh nào.</div></div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section">
    <div class="container text-center position-relative" style="z-index:2;">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div style="width:72px;height:72px;background:rgba(245,166,35,0.2);border:2px solid rgba(245,166,35,0.4);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;">
                    <i class="bi bi-mortarboard-fill text-gold" style="font-size:2rem;"></i>
                </div>
                <h2 class="text-white fw-bold mb-3" style="font-size:2.1rem;letter-spacing:-0.02em;">Bắt đầu hành trình của bạn tại TDMU</h2>
                <p class="mb-4" style="color:rgba(255,255,255,0.65);font-size:1.05rem;line-height:1.75;">Đăng ký xét tuyển ngay hôm nay để không bỏ lỡ cơ hội trở thành sinh viên Trường Đại học Thủ Dầu Một.</p>
                <div class="d-flex flex-wrap gap-3 justify-content-center">
                    <a href="/university/admission.php" class="btn btn-gold btn-lg px-5">
                        <i class="bi bi-pencil-square me-2"></i>Đăng ký ngay
                    </a>
                    <a href="/university/contact.php" class="btn btn-outline-light btn-lg px-5">
                        <i class="bi bi-telephone me-2"></i>Liên hệ tư vấn
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Back to top -->
<button id="backToTop" class="btn btn-gold rounded-circle shadow" style="position:fixed;bottom:2rem;right:2rem;width:44px;height:44px;display:none;align-items:center;justify-content:center;z-index:999;">
    <i class="bi bi-arrow-up"></i>
</button>

<?php include 'includes/footer.php'; ?>
