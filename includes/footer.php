<footer class="footer bg-navy text-white mt-5 pt-5 pb-3">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="d-flex align-items-center mb-3">
                    <i class="bi bi-mortarboard-fill fs-2 text-gold me-2"></i>
                    <div>
                        <div class="fw-bold fs-5">Trường Đại học Thủ Dầu Một</div>
                        <small class="text-gold">Thu Dau Mot University - TDMU</small>
                    </div>
                </div>
                <p class="text-white-50 small">TDMU - Trường Đại học Thủ Dầu Một, nơi đào tạo nguồn nhân lực chất lượng cao, phục vụ sự nghiệp phát triển kinh tế - xã hội của tỉnh Bình Dương và cả nước.</p>
                <div class="d-flex gap-3 mt-3">
                    <a href="#" class="text-gold fs-5"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="text-gold fs-5"><i class="bi bi-youtube"></i></a>
                    <a href="#" class="text-gold fs-5"><i class="bi bi-envelope-fill"></i></a>
                </div>
            </div>
            <div class="col-lg-2 col-md-4">
                <h6 class="text-gold fw-bold mb-3">Liên kết nhanh</h6>
                <ul class="list-unstyled small">
                    <li class="mb-1"><a href="/university/index.php" class="text-white-50 text-decoration-none hover-gold">Trang chủ</a></li>
                    <li class="mb-1"><a href="/university/about.php" class="text-white-50 text-decoration-none hover-gold">Giới thiệu</a></li>
                    <li class="mb-1"><a href="/university/programs.php" class="text-white-50 text-decoration-none hover-gold">Chương trình đào tạo</a></li>
                    <li class="mb-1"><a href="/university/admission.php" class="text-white-50 text-decoration-none hover-gold">Tuyển sinh</a></li>
                    <li class="mb-1"><a href="/university/news.php" class="text-white-50 text-decoration-none hover-gold">Tin tức</a></li>
                    <li class="mb-1"><a href="/university/contact.php" class="text-white-50 text-decoration-none hover-gold">Liên hệ</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-4">
                <h6 class="text-gold fw-bold mb-3">Đào tạo</h6>
                <ul class="list-unstyled small">
                    <li class="mb-1"><a href="#" class="text-white-50 text-decoration-none">Đại học chính quy</a></li>
                    <li class="mb-1"><a href="#" class="text-white-50 text-decoration-none">Đại học vừa làm vừa học</a></li>
                    <li class="mb-1"><a href="#" class="text-white-50 text-decoration-none">Sau đại học</a></li>
                    <li class="mb-1"><a href="/university/admission.php" class="text-white-50 text-decoration-none">Thông tin tuyển sinh</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-4">
                <h6 class="text-gold fw-bold mb-3">Liên hệ</h6>
                <ul class="list-unstyled small text-white-50">
                    <li class="mb-2"><i class="bi bi-geo-alt-fill text-gold me-2"></i>6 Trần Văn Ơn, Phú Hòa, Thủ Dầu Một, Bình Dương</li>
                    <li class="mb-2"><i class="bi bi-telephone-fill text-gold me-2"></i>(0274) 3 822 518</li>
                    <li class="mb-2"><i class="bi bi-envelope-fill text-gold me-2"></i>info@tdmu.edu.vn</li>
                    <li class="mb-2"><i class="bi bi-globe text-gold me-2"></i>www.tdmu.edu.vn</li>
                </ul>
            </div>
        </div>
        <hr class="border-secondary mt-4">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <small class="text-white-50">&copy; <?php echo date('Y'); ?> Trường Đại học Thủ Dầu Một (TDMU). Bảo lưu mọi quyền.</small>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <small class="text-white-50">Thiết kế bởi Phòng Công nghệ Thông tin</small>
            </div>
        </div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
<?php include_once __DIR__ . '/analytics_widget.php'; ?>
<?php endif; ?>

</body>
</html>
