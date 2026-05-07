<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
$pageTitle = 'Giới thiệu';
include 'includes/header.php';

// Fetch faculties
$faculties = $conn->query("SELECT * FROM faculties ORDER BY faculty_name ASC");
?>

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="/university/index.php">Trang chủ</a></li>
                <li class="breadcrumb-item active">Giới thiệu</li>
            </ol>
        </nav>
        <h1><i class="bi bi-building me-2"></i>Giới thiệu trường</h1>
        <p class="text-white-50 mb-0">Trường Đại học Thủ Dầu Một - Hơn 15 năm xây dựng và phát triển</p>
    </div>
</div>

<!-- Introduction -->
<section class="py-5">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6">
                <h2 class="section-title">Về Trường Đại học Thủ Dầu Một</h2>
                <p class="text-muted mt-3">
                    Trường Đại học Thủ Dầu Một (Thu Dau Mot University - TDMU) được thành lập theo Quyết định số
                    900/QĐ-TTg ngày 24/6/2009 của Thủ tướng Chính phủ. Trường là cơ sở giáo dục đại học công lập
                    trực thuộc UBND tỉnh Bình Dương.
                </p>
                <p class="text-muted">
                    Với hơn 15 năm xây dựng và phát triển, Trường Đại học Thủ Dầu Một đã đào tạo hàng chục nghìn 
                    cử nhân, kỹ sư, thạc sĩ phục vụ cho sự nghiệp phát triển kinh tế - xã hội của tỉnh Bình Dương 
                    và cả nước.
                </p>
                <p class="text-muted">
                    Trường hiện có cơ sở vật chất khang trang, hiện đại với diện tích hơn 10 hecta, bao gồm các 
                    giảng đường, phòng thí nghiệm, thư viện, ký túc xá và các công trình phục vụ sinh viên.
                </p>
                <div class="row g-3 mt-2">
                    <div class="col-6">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-check-circle-fill text-gold fs-5"></i>
                            <span class="small fw-500">Thành lập năm 1997</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-check-circle-fill text-gold fs-5"></i>
                            <span class="small fw-500">Kiểm định chất lượng</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-check-circle-fill text-gold fs-5"></i>
                            <span class="small fw-500">Hợp tác quốc tế</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-check-circle-fill text-gold fs-5"></i>
                            <span class="small fw-500">Học bổng đa dạng</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="bg-light-navy rounded-lg p-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="card text-center p-3">
                                <div class="text-gold fw-bold" style="font-size:2rem;">1997</div>
                                <div class="text-muted small">Năm thành lập</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card text-center p-3">
                                <div class="text-gold fw-bold" style="font-size:2rem;">10+</div>
                                <div class="text-muted small">Hecta khuôn viên</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card text-center p-3">
                                <div class="text-gold fw-bold" style="font-size:2rem;">50+</div>
                                <div class="text-muted small">Chương trình đào tạo</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card text-center p-3">
                                <div class="text-gold fw-bold" style="font-size:2rem;">95%</div>
                                <div class="text-muted small">Sinh viên có việc làm</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Mission & Vision -->
<section class="py-5 bg-light">
    <div class="container">
        <h2 class="section-title text-center mb-2">Sứ mệnh & Tầm nhìn</h2>
        <p class="text-center text-muted mb-5">Định hướng phát triển của Trường Đại học Thủ Dầu Một</p>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 text-center p-4">
                    <div class="faculty-icon mx-auto mb-3">
                        <i class="bi bi-bullseye"></i>
                    </div>
                    <h5 class="fw-bold text-navy">Sứ mệnh</h5>
                    <p class="text-muted small">
                        Đào tạo nguồn nhân lực chất lượng cao, nghiên cứu khoa học và chuyển giao công nghệ, 
                        phục vụ sự nghiệp công nghiệp hóa, hiện đại hóa đất nước và hội nhập quốc tế.
                    </p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 text-center p-4">
                    <div class="faculty-icon mx-auto mb-3">
                        <i class="bi bi-eye-fill"></i>
                    </div>
                    <h5 class="fw-bold text-navy">Tầm nhìn</h5>
                    <p class="text-muted small">
                        Đến năm 2030, Trường Đại học Thủ Dầu Một trở thành trường đại học đa ngành, đa lĩnh vực, 
                        có uy tín trong khu vực Đông Nam Á, đạt chuẩn kiểm định quốc tế.
                    </p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 text-center p-4">
                    <div class="faculty-icon mx-auto mb-3">
                        <i class="bi bi-gem"></i>
                    </div>
                    <h5 class="fw-bold text-navy">Giá trị cốt lõi</h5>
                    <p class="text-muted small">
                        Chất lượng - Sáng tạo - Trách nhiệm - Hội nhập. Cam kết mang lại môi trường học tập 
                        tốt nhất cho sinh viên và cộng đồng.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Faculties -->
<section class="py-5">
    <div class="container">
        <h2 class="section-title text-center mb-2">Các Khoa đào tạo</h2>
        <p class="text-center text-muted mb-5">Hệ thống các khoa chuyên ngành tại Trường Đại học Thủ Dầu Một</p>
        <div class="row g-4">
            <?php
            $facultyIcons = ['bi-laptop', 'bi-calculator', 'bi-heart-pulse', 'bi-building', 'bi-palette', 'bi-translate', 'bi-briefcase', 'bi-gear'];
            $fi = 0;
            if ($faculties && $faculties->num_rows > 0):
                while ($faculty = $faculties->fetch_assoc()):
                    $ficon = $facultyIcons[$fi % count($facultyIcons)];
                    $fi++;
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="card faculty-card h-100">
                    <div class="card-body p-4">
                        <div class="faculty-icon mb-3">
                            <i class="bi <?php echo $ficon; ?>"></i>
                        </div>
                        <h5 class="fw-bold text-navy"><?php echo htmlspecialchars($faculty['faculty_name']); ?></h5>
                        <?php if (!empty($faculty['description'])): ?>
                        <p class="text-muted small text-truncate-3"><?php echo htmlspecialchars($faculty['description']); ?></p>
                        <?php else: ?>
                        <p class="text-muted small">Khoa đào tạo chuyên ngành chất lượng cao tại Trường Đại học Thủ Dầu Một.</p>
                        <?php endif; ?>
                        <?php if (!empty($faculty['email'])): ?>
                        <div class="small text-muted mt-2">
                            <i class="bi bi-envelope me-1 text-gold"></i><?php echo htmlspecialchars($faculty['email']); ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($faculty['phone'])): ?>
                        <div class="small text-muted">
                            <i class="bi bi-telephone me-1 text-gold"></i><?php echo htmlspecialchars($faculty['phone']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; else: ?>
            <div class="col-12">
                <div class="alert alert-info text-center">
                    <i class="bi bi-info-circle me-2"></i>Chưa có thông tin khoa đào tạo.
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Achievements -->
<section class="py-5 bg-navy">
    <div class="container">
        <h2 class="section-title text-center text-white mb-2">Thành tích & Khen thưởng</h2>
        <p class="text-center text-white-50 mb-5">Những thành tựu nổi bật trong hành trình phát triển</p>
        <div class="row g-4">
            <div class="col-md-3 text-center">
                <i class="bi bi-trophy-fill text-gold" style="font-size:3rem;"></i>
                <h5 class="text-white mt-3">Huân chương Lao động</h5>
                <p class="text-white-50 small">Hạng Nhất do Nhà nước trao tặng</p>
            </div>
            <div class="col-md-3 text-center">
                <i class="bi bi-award-fill text-gold" style="font-size:3rem;"></i>
                <h5 class="text-white mt-3">Kiểm định chất lượng</h5>
                <p class="text-white-50 small">Đạt chuẩn kiểm định quốc gia</p>
            </div>
            <div class="col-md-3 text-center">
                <i class="bi bi-globe text-gold" style="font-size:3rem;"></i>
                <h5 class="text-white mt-3">Hợp tác quốc tế</h5>
                <p class="text-white-50 small">Liên kết với 20+ trường đại học quốc tế</p>
            </div>
            <div class="col-md-3 text-center">
                <i class="bi bi-star-fill text-gold" style="font-size:3rem;"></i>
                <h5 class="text-white mt-3">Top trường tư thục</h5>
                <p class="text-white-50 small">Trong bảng xếp hạng đại học Việt Nam</p>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
