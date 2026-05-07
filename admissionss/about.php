<?php
require_once 'php/config.php';
$page_title = "Giới thiệu về trường";
require_once 'includes/header.php';
?>

<!-- Page Header -->
<section class="page-header bg-primary text-white">
    <div class="container">
        <div class="row">
            <div class="col-12 text-center">
                <h1 class="display-4">Giới thiệu về trường</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb justify-content-center">
                        <li class="breadcrumb-item"><a href="index.php" class="text-white">Trang chủ</a></li>
                        <li class="breadcrumb-item active text-white" aria-current="page">Giới thiệu</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</section>

<!-- Giới thiệu chung -->
<section class="about-intro section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="about-image">
                    <img src="assets/images/about-main.jpg" alt="Đại học Khoa học và Công nghệ" class="img-fluid rounded shadow">
                </div>
            </div>
            <div class="col-lg-6">
                <div class="about-content">
                    <h2 class="section-title">Đại học Khoa học và Công nghệ</h2>
                    <p class="lead">Nơi ươm mầm tài năng, kiến tạo tương lai</p>
                    <p>Được thành lập năm 2000, Đại học Khoa học và Công nghệ tự hào là một trong những trường đại học hàng đầu về đào tạo khoa học cơ bản và công nghệ cao tại Việt Nam. Với sứ mệnh đào tạo nguồn nhân lực chất lượng cao, nghiên cứu khoa học và chuyển giao công nghệ phục vụ sự nghiệp công nghiệp hóa, hiện đại hóa đất nước.</p>
                    <p>Nhà trường luôn chú trọng đầu tư cơ sở vật chất, trang thiết bị hiện đại, xây dựng đội ngũ giảng viên giàu kinh nghiệm và tâm huyết với nghề, không ngừng đổi mới phương pháp giảng dạy để nâng cao chất lượng đào tạo.</p>
                    
                    <div class="row mt-4">
                        <div class="col-6">
                            <div class="stat-box">
                                <span class="stat-number">20+</span>
                                <span class="stat-label">Năm thành lập</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-box">
                                <span class="stat-number">30+</span>
                                <span class="stat-label">Ngành đào tạo</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-box">
                                <span class="stat-number">15.000+</span>
                                <span class="stat-label">Sinh viên</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-box">
                                <span class="stat-number">500+</span>
                                <span class="stat-label">Đối tác doanh nghiệp</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Sứ mạng và tầm nhìn -->
<section class="mission-vision bg-light section">
    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <div class="mission-box">
                    <div class="icon-box">
                        <i class="fas fa-bullseye"></i>
                    </div>
                    <h3>Sứ mạng</h3>
                    <p>Đào tạo nguồn nhân lực chất lượng cao, nghiên cứu khoa học và chuyển giao công nghệ tiên tiến, đáp ứng nhu cầu phát triển kinh tế - xã hội của đất nước và hội nhập quốc tế.</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="vision-box">
                    <div class="icon-box">
                        <i class="fas fa-eye"></i>
                    </div>
                    <h3>Tầm nhìn</h3>
                    <p>Trở thành trường đại học nghiên cứu hàng đầu trong khu vực vào năm 2030, nơi hội tụ của tri thức và sáng tạo, đào tạo thế hệ lãnh đạo tương lai trong lĩnh vực khoa học và công nghệ.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Giá trị cốt lõi -->
<section class="core-values section">
    <div class="container">
        <h2 class="text-center mb-5">Giá trị cốt lõi</h2>
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-flask"></i>
                    </div>
                    <h4>Sáng tạo</h4>
                    <p>Khuyến khích tư duy sáng tạo và đổi mới trong học tập và nghiên cứu</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-hand-holding-heart"></i>
                    </div>
                    <h4>Chất lượng</h4>
                    <p>Cam kết đào tạo chất lượng cao, đáp ứng nhu cầu xã hội</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <h4>Hợp tác</h4>
                    <p>Xây dựng mối quan hệ hợp tác bền vững với doanh nghiệp và đối tác</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-leaf"></i>
                    </div>
                    <h4>Trách nhiệm</h4>
                    <p>Đào tạo công dân có trách nhiệm với cộng đồng và xã hội</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Cơ sở vật chất -->
<section class="facilities bg-light section">
    <div class="container">
        <h2 class="text-center mb-5">Cơ sở vật chất</h2>
        <div class="row">
            <div class="col-md-4">
                <div class="facility-card">
                    <img src="assets/images/facility1.jpg" alt="Thư viện" class="img-fluid">
                    <div class="facility-info">
                        <h4>Thư viện hiện đại</h4>
                        <p>Hơn 50.000 đầu sách và tài liệu tham khảo</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="facility-card">
                    <img src="assets/images/facility2.jpg" alt="Phòng thí nghiệm" class="img-fluid">
                    <div class="facility-info">
                        <h4>Phòng thí nghiệm</h4>
                        <p>Trang bị thiết bị hiện đại đạt chuẩn quốc tế</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="facility-card">
                    <img src="assets/images/facility3.jpg" alt="Ký túc xá" class="img-fluid">
                    <div class="facility-info">
                        <h4>Ký túc xá</h4>
                        <p>Chỗ ở tiện nghi cho 3.000 sinh viên</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Đội ngũ giảng viên -->
<section class="faculty section">
    <div class="container">
        <h2 class="text-center mb-5">Đội ngũ giảng viên</h2>
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="faculty-card">
                    <img src="assets/images/teacher1.jpg" alt="GS.TS Nguyễn Văn A" class="img-fluid">
                    <h4>GS.TS Nguyễn Văn A</h4>
                    <p>Hiệu trưởng</p>
                    <p class="small">Chuyên ngành: Công nghệ thông tin</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="faculty-card">
                    <img src="assets/images/teacher2.jpg" alt="PGS.TS Trần Thị B" class="img-fluid">
                    <h4>PGS.TS Trần Thị B</h4>
                    <p>Trưởng khoa CNTT</p>
                    <p class="small">Chuyên ngành: Khoa học máy tính</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="faculty-card">
                    <img src="assets/images/teacher3.jpg" alt="GS.TS Lê Văn C" class="img-fluid">
                    <h4>GS.TS Lê Văn C</h4>
                    <p>Trưởng khoa Vật lý</p>
                    <p class="small">Chuyên ngành: Vật lý bán dẫn</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="faculty-card">
                    <img src="assets/images/teacher4.jpg" alt="PGS.TS Phạm Thị D" class="img-fluid">
                    <h4>PGS.TS Phạm Thị D</h4>
                    <p>Trưởng khoa Toán</p>
                    <p class="small">Chuyên ngành: Toán ứng dụng</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Thành tích -->
<section class="achievements bg-light section">
    <div class="container">
        <h2 class="text-center mb-5">Thành tích nổi bật</h2>
        <div class="row">
            <div class="col-md-6">
                <ul class="achievement-list">
                    <li><i class="fas fa-trophy text-warning"></i> Top 10 trường đại học hàng đầu Việt Nam (2023)</li>
                    <li><i class="fas fa-trophy text-warning"></i> Giải thưởng Sao Khuê về đào tạo CNTT (2022, 2023)</li>
                    <li><i class="fas fa-trophy text-warning"></i> Chứng nhận kiểm định chất lượng giáo dục quốc tế AUN-QA</li>
                    <li><i class="fas fa-trophy text-warning"></i> 95% sinh viên có việc làm sau 1 năm tốt nghiệp</li>
                </ul>
            </div>
            <div class="col-md-6">
                <ul class="achievement-list">
                    <li><i class="fas fa-trophy text-warning"></i> Hơn 100 đề tài nghiên cứu khoa học cấp quốc gia</li>
                    <li><i class="fas fa-trophy text-warning"></i> 50 bằng sáng chế và giải pháp hữu ích</li>
                    <li><i class="fas fa-trophy text-warning"></i> Đối tác chiến lược với 20 tập đoàn công nghệ hàng đầu</li>
                    <li><i class="fas fa-trophy text-warning"></i> 3 giải thưởng Nhân tài Đất Việt</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>