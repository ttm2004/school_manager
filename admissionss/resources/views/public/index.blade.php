    <!DOCTYPE html>
    <html lang="vi">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Đại học Khoa học và Công nghệ - Tuyển sinh 2024</title>
        <link rel="stylesheet" href="{{ asset('css/style.css') }}">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    </head>

    <body>

        <div id="loading" class="loading">
            <div class="spinner"></div>
        </div>
        <!-- Header -->
        <header>
            <div class="top-header">
                <div class="container">
                    <div class="contact-info">
                        <span><i class="fas fa-phone"></i> Hotline: 1900 1234</span>
                        <span><i class="fas fa-envelope"></i> tuyensinh@hus.edu.vn</span>
                    </div>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                        <a href="#"><i class="fab fa-tiktok"></i></a>
                    </div>
                </div>
            </div>

            <nav class="navbar">
                <div class="container">
                    <div class="logo">
                        <img src="images/logo.png" alt="Logo Đại học" onerror="this.src='images/partner1.png'">
                        <div class="logo-text">
                            <h2>ĐẠI HỌC KHOA HỌC VÀ CÔNG NGHỆ</h2>
                            <p>University of Science and Technology</p>
                        </div>
                    </div>
                    <ul class="nav-menu">
                        <li><a href="#home" class="active">Trang chủ</a></li>
                        <li><a href="#about">Giới thiệu</a></li>
                        <li><a href="#admission">Tuyển sinh</a></li>
                        <li><a href="#majors">Ngành học</a></li>
                        <li><a href="#tuition">Học phí</a></li>
                        <li><a href="#scholarship">Học bổng</a></li>
                        <li><a href="#contact">Liên hệ</a></li>

                    </ul>
                    <div class="hamburger">
                        <span class="bar"></span>
                        <span class="bar"></span>
                        <span class="bar"></span>
                    </div>
                </div>
            </nav>
        </header>

        <!-- Hero Slider -->
        <section id="home" class="hero">
            <div class="slider-container">
                <div class="slider">
                    <div class="slide active">
                        <img src="images/slide1.jpg" alt="Slide 1" onerror="this.src='images/partner1.png'">
                        <div class="slide-content">
                            <h2>Chào mừng đến với Đại học Khoa học và Công nghệ</h2>
                            <p>Môi trường học tập hiện đại - Cơ hội phát triển toàn diện</p>
                            <a href="#admission" class="btn btn-primary">Đăng ký xét tuyển</a>
                        </div>
                    </div>
                    <div class="slide">
                        <img src="images/slide2.jpg" alt="Slide 2" onerror="this.src='images/partner1.png'">
                        <div class="slide-content">
                            <h2>Tuyển sinh đại học năm 2024</h2>
                            <p>Nhận hồ sơ từ ngày 01/03/2024</p>
                            <a href="#admission" class="btn btn-primary">Xem chi tiết</a>
                        </div>
                    </div>
                    <div class="slide">
                        <img src="images/slide3.jpg" alt="Slide 3" onerror="this.src='images/partner1.png'">
                        <div class="slide-content">
                            <h2>Học bổng lên đến 100% học phí</h2>
                            <p>Dành cho thí sinh có thành tích xuất sắc</p>
                            <a href="#scholarship" class="btn btn-primary">Xem học bổng</a>
                        </div>
                    </div>
                </div>
                <button class="slider-btn prev"><i class="fas fa-chevron-left"></i></button>
                <button class="slider-btn next"><i class="fas fa-chevron-right"></i></button>
                <div class="slider-dots"></div>
            </div>
        </section>

        <!-- Thông báo tuyển sinh -->
        <section class="announcement">
            <div class="container">
                <div class="announcement-box">
                    <div class="announcement-icon">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <div class="announcement-content">
                        <h3>Thông báo tuyển sinh năm 2024</h3>
                        <p>Đại học Khoa học và Công nghệ thông báo tuyển sinh 2.000 chỉ tiêu đại học chính quy năm 2024</p>
                    </div>
                    <a href="#" class="btn btn-outline">Xem chi tiết</a>
                </div>
            </div>
        </section>

        <!-- Giới thiệu -->
        <section id="about" class="about section">
            <div class="container">
                <div class="section-header">
                    <h2>Giới thiệu về trường</h2>
                    <p>Đại học Khoa học và Công nghệ - 20 năm xây dựng và phát triển</p>
                </div>
                <div class="about-content">
                    <div class="about-text">
                        <h3>Đại học hàng đầu về Khoa học và Công nghệ</h3>
                        <p>Đại học Khoa học và Công nghệ là trường đại học công lập trọng điểm quốc gia, đào tạo đa ngành, đa lĩnh vực với thế mạnh về khoa học cơ bản và công nghệ cao.</p>
                        <ul class="about-list">
                            <li><i class="fas fa-check-circle"></i> Đội ngũ giảng viên: 80% có trình độ tiến sĩ trở lên</li>
                            <li><i class="fas fa-check-circle"></i> Cơ sở vật chất hiện đại, phòng thí nghiệm đạt chuẩn quốc tế</li>
                            <li><i class="fas fa-check-circle"></i> 100% sinh viên có cơ hội thực tập tại doanh nghiệp</li>
                            <li><i class="fas fa-check-circle"></i> Tỷ lệ sinh viên có việc làm sau 1 năm tốt nghiệp: 95%</li>
                        </ul>
                        <div class="stats">
                            <div class="stat-item">
                                <span class="stat-number">20+</span>
                                <span class="stat-label">Năm thành lập</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number">30+</span>
                                <span class="stat-label">Ngành đào tạo</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number">15.000+</span>
                                <span class="stat-label">Sinh viên</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number">500+</span>
                                <span class="stat-label">Đối tác doanh nghiệp</span>
                            </div>
                        </div>
                    </div>
                    <div class="about-video">
                        <img src="images/about.jpg" alt="Giới thiệu trường" onerror="this.src='images/partner1.png'">
                        <a href="#" class="play-btn"><i class="fas fa-play"></i></a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Thông tin tuyển sinh -->
        <section id="admission" class="admission section bg-light">
            <div class="container">
                <div class="section-header">
                    <h2>Thông tin tuyển sinh 2024</h2>
                    <p>Cập nhật thông tin mới nhất về kỳ tuyển sinh đại học</p>
                </div>

                <div class="admission-info">
                    <div class="info-card">
                        <div class="info-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>Chỉ tiêu tuyển sinh</h3>
                        <p class="info-number">2.000</p>
                        <p>Chỉ tiêu đại học chính quy</p>
                    </div>

                    <div class="info-card">
                        <div class="info-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h3>Thời gian nhận hồ sơ</h3>
                        <p class="info-number">01/03 - 30/07</p>
                        <p>Đợt 1: Xét tuyển sớm</p>
                    </div>

                    <div class="info-card">
                        <div class="info-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h3>Phương thức xét tuyển</h3>
                        <p class="info-number">03</p>
                        <p>phương thức xét tuyển</p>
                    </div>

                    <div class="info-card">
                        <div class="info-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <h3>Học phí dự kiến</h3>
                        <p class="info-number">12-18 triệu</p>
                        <p>VNĐ/năm học</p>
                    </div>
                </div>

                <!-- Phương thức xét tuyển -->
                <div class="admission-methods">
                    <h3>Phương thức xét tuyển</h3>
                    <div class="methods-grid">
                        <div class="method-item">
                            <div class="method-number">01</div>
                            <h4>Xét tuyển thẳng</h4>
                            <p>Theo quy định của Bộ GD&ĐT và quy chế tuyển sinh của trường</p>
                        </div>
                        <div class="method-item">
                            <div class="method-number">02</div>
                            <h4>Xét điểm thi THPT</h4>
                            <p>Dựa vào kết quả kỳ thi tốt nghiệp THPT năm 2024</p>
                        </div>
                        <div class="method-item">
                            <div class="method-number">03</div>
                            <h4>Xét học bạ</h4>
                            <p>Xét kết quả học tập 3 năm THPT (lớp 10, 11, 12)</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Ngành đào tạo -->
        <section id="majors" class="majors section">
            <div class="container">
                <div class="section-header">
                    <h2>Các ngành đào tạo</h2>
                    <p>Đa dạng ngành học đáp ứng nhu cầu của thị trường lao động</p>
                </div>

                <div class="majors-filter">
                    <button class="filter-btn active" data-filter="all">Tất cả</button>
                    <button class="filter-btn" data-filter="tech">Công nghệ</button>
                    <button class="filter-btn" data-filter="science">Khoa học</button>
                    <button class="filter-btn" data-filter="engineer">Kỹ thuật</button>
                    <button class="filter-btn" data-filter="economic">Kinh tế</button>
                </div>

                <div class="majors-grid">
                    <!-- CNTT -->
                    <div class="major-card" data-category="tech">
                        <div class="major-icon">
                            <i class="fas fa-laptop-code"></i>
                        </div>
                        <h4>Công nghệ thông tin</h4>
                        <p>Mã ngành: 7480201</p>
                        <div class="major-details">
                            <span><i class="fas fa-users"></i> 350 chỉ tiêu</span>
                            <span><i class="fas fa-star"></i> Điểm chuẩn 2023: 26.5</span>
                        </div>
                    </div>

                    <!-- Khoa học máy tính -->
                    <div class="major-card" data-category="tech">
                        <div class="major-icon">
                            <i class="fas fa-microchip"></i>
                        </div>
                        <h4>Khoa học máy tính</h4>
                        <p>Mã ngành: 7480101</p>
                        <div class="major-details">
                            <span><i class="fas fa-users"></i> 200 chỉ tiêu</span>
                            <span><i class="fas fa-star"></i> Điểm chuẩn 2023: 27.0</span>
                        </div>
                    </div>

                    <!-- Điện tử viễn thông -->
                    <div class="major-card" data-category="engineer">
                        <div class="major-icon">
                            <i class="fas fa-satellite-dish"></i>
                        </div>
                        <h4>Điện tử viễn thông</h4>
                        <p>Mã ngành: 7520207</p>
                        <div class="major-details">
                            <span><i class="fas fa-users"></i> 150 chỉ tiêu</span>
                            <span><i class="fas fa-star"></i> Điểm chuẩn 2023: 24.5</span>
                        </div>
                    </div>

                    <!-- Toán học -->
                    <div class="major-card" data-category="science">
                        <div class="major-icon">
                            <i class="fas fa-square-root-alt"></i>
                        </div>
                        <h4>Toán học</h4>
                        <p>Mã ngành: 7460112</p>
                        <div class="major-details">
                            <span><i class="fas fa-users"></i> 100 chỉ tiêu</span>
                            <span><i class="fas fa-star"></i> Điểm chuẩn 2023: 23.0</span>
                        </div>
                    </div>

                    <!-- Vật lý -->
                    <div class="major-card" data-category="science">
                        <div class="major-icon">
                            <i class="fas fa-atom"></i>
                        </div>
                        <h4>Vật lý học</h4>
                        <p>Mã ngành: 7440122</p>
                        <div class="major-details">
                            <span><i class="fas fa-users"></i> 80 chỉ tiêu</span>
                            <span><i class="fas fa-star"></i> Điểm chuẩn 2023: 22.0</span>
                        </div>
                    </div>

                    <!-- Kỹ thuật phần mềm -->
                    <div class="major-card" data-category="tech">
                        <div class="major-icon">
                            <i class="fas fa-code"></i>
                        </div>
                        <h4>Kỹ thuật phần mềm</h4>
                        <p>Mã ngành: 7480103</p>
                        <div class="major-details">
                            <span><i class="fas fa-users"></i> 180 chỉ tiêu</span>
                            <span><i class="fas fa-star"></i> Điểm chuẩn 2023: 26.0</span>
                        </div>
                    </div>

                    <!-- Quản trị kinh doanh -->
                    <div class="major-card" data-category="economic">
                        <div class="major-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h4>Quản trị kinh doanh</h4>
                        <p>Mã ngành: 7340101</p>
                        <div class="major-details">
                            <span><i class="fas fa-users"></i> 200 chỉ tiêu</span>
                            <span><i class="fas fa-star"></i> Điểm chuẩn 2023: 25.0</span>
                        </div>
                    </div>

                    <!-- Kỹ thuật cơ điện tử -->
                    <div class="major-card" data-category="engineer">
                        <div class="major-icon">
                            <i class="fas fa-robot"></i>
                        </div>
                        <h4>Kỹ thuật cơ điện tử</h4>
                        <p>Mã ngành: 7520114</p>
                        <div class="major-details">
                            <span><i class="fas fa-users"></i> 120 chỉ tiêu</span>
                            <span><i class="fas fa-star"></i> Điểm chuẩn 2023: 24.0</span>
                        </div>
                    </div>
                </div>

                <div class="text-center">
                    <a href="#" class="btn btn-secondary">Xem tất cả ngành học</a>
                </div>
            </div>
        </section>

        <!-- Học bổng -->
        <section id="scholarship" class="scholarship section bg-light">
            <div class="container">
                <div class="section-header">
                    <h2>Học bổng tuyển sinh 2024</h2>
                    <p>Cơ hội nhận học bổng giá trị dành cho tân sinh viên</p>
                </div>

                <div class="scholarship-grid">
                    <div class="scholarship-card">
                        <div class="scholarship-badge">ĐẶC BIỆT</div>
                        <h3>Học bổng Toàn phần</h3>
                        <div class="scholarship-value">100% học phí</div>
                        <ul class="scholarship-list">
                            <li><i class="fas fa-check"></i> Miễn 100% học phí 4 năm</li>
                            <li><i class="fas fa-check"></i> Hỗ trợ ký túc xá</li>
                            <li><i class="fas fa-check"></i> Cơ hội trao đổi quốc tế</li>
                            <li><i class="fas fa-check"></i> Điều kiện: Thủ khoa, giải Quốc gia</li>
                        </ul>
                    </div>

                    <div class="scholarship-card featured">
                        <div class="scholarship-badge">PHỔ BIẾN</div>
                        <h3>Học bổng Khuyến khích</h3>
                        <div class="scholarship-value">50% học phí</div>
                        <ul class="scholarship-list">
                            <li><i class="fas fa-check"></i> Miễn 50% học phí năm đầu</li>
                            <li><i class="fas fa-check"></i> Hỗ trợ tài liệu học tập</li>
                            <li><i class="fas fa-check"></i> Tham gia câu lạc bộ tài năng</li>
                            <li><i class="fas fa-check"></i> Điều kiện: HSG 3 năm, điểm thi ≥ 26</li>
                        </ul>
                    </div>

                    <div class="scholarship-card">
                        <div class="scholarship-badge">CƠ BẢN</div>
                        <h3>Học bổng Tài năng</h3>
                        <div class="scholarship-value">30% học phí</div>
                        <ul class="scholarship-list">
                            <li><i class="fas fa-check"></i> Giảm 30% học phí năm nhất</li>
                            <li><i class="fas fa-check"></i> Tham gia dự án nghiên cứu</li>
                            <li><i class="fas fa-check"></i> Điều kiện: Điểm thi ≥ 24</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- Form đăng ký xét tuyển -->
        <section id="register" class="register section">
            <div class="container">
                <div class="section-header">
                    <h2>Đăng ký xét tuyển trực tuyến</h2>
                    <p>Điền thông tin để được tư vấn và hỗ trợ xét tuyển</p>
                </div>

                <div class="register-form-container">
                    <form id="registerForm" class="register-form" method="POST" enctype="multipart/form-data">
                        @csrf
                        <!-- Thông tin cá nhân -->
                        <div class="form-section">
                            <h3><i class="fas fa-user-circle"></i> Thông tin cá nhân</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="fullname">Họ và tên <span>*</span></label>
                                    <input type="text" id="fullname" name="fullname" placeholder="Nhập họ tên đầy đủ" required>
                                </div>
                                <div class="form-group">
                                    <label for="birthday">Ngày sinh <span>*</span></label>
                                    <input type="date" id="birthday" name="birthday" required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="gender">Giới tính</label>
                                    <select id="gender" name="gender">
                                        <option value="">Chọn giới tính</option>
                                        <option value="male">Nam</option>
                                        <option value="female">Nữ</option>
                                        <option value="other">Khác</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="identification">Số CMND/CCCD <span>*</span></label>
                                    <input type="text" id="identification" name="identification" placeholder="Nhập số CMND/CCCD" required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="phone">Số điện thoại <span>*</span></label>
                                    <input type="tel" id="phone" name="phone" placeholder="VD: 0912345678" required>
                                </div>
                                <div class="form-group">
                                    <label for="email">Email <span>*</span></label>
                                    <input type="email" id="email" name="email" placeholder="example@email.com" required>
                                </div>
                            </div>
                        </div>

                        <!-- Địa chỉ -->
                        <div class="form-section">
                            <h3><i class="fas fa-map-marker-alt"></i> Địa chỉ liên hệ</h3>

                            <div class="form-row">

                                <div class="form-group">
                                    <label for="province">Tỉnh/Thành phố <span>*</span></label>
                                    <select id="province" name="province" required>
                                        <option value="">Chọn tỉnh/thành phố</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="district">Quận/Huyện <span>*</span></label>
                                    <select id="district" name="district" required disabled>
                                        <option value="">Chọn quận/huyện</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="ward">Xã/Phường <span>*</span></label>
                                    <select id="ward" name="ward" required disabled>
                                        <option value="">Chọn xã/phường</option>
                                    </select>
                                </div>

                            </div>

                            <div class="form-group">
                                <label for="address">Địa chỉ chi tiết <span>*</span></label>
                                <input type="text" id="address" name="address" placeholder="Số nhà, đường, thôn/xóm" required>
                            </div>

                        </div>

                        <!-- Thông tin học tập -->
                        <div class="form-section">
                            <h3><i class="fas fa-graduation-cap"></i> Thông tin học tập</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="graduation_year">Năm tốt nghiệp THPT <span>*</span></label>
                                    <select id="graduation_year" name="graduation_year" required>
                                        <option value="">Chọn năm</option>
                                        <?php
                                        $current_year = date('Y');
                                        for ($year = $current_year; $year >= $current_year - 5; $year--) {
                                            echo "<option value='$year'>$year</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="school">Trường THPT <span>*</span></label>
                                    <input type="text" id="school" name="school" placeholder="Nhập tên trường THPT" required>
                                </div>
                            </div>
                        </div>

                        <!-- Chọn ngành và phương thức -->
                        <div class="form-section">
                            <h3><i class="fas fa-book-open"></i> Đăng ký xét tuyển</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="major">Ngành đăng ký <span>*</span></label>
                                    <select id="major" name="major" required>
                                        <option value="">Chọn ngành học</option>

                                        <!--  Lấy danh sách ngành từ database -->
                                        @foreach ($majors as $major)
                                        <option value="{{ $major->id }}" data-code="{{ $major->code }}">
                                            {{ $major->name }} ({{ $major->code }})
                                        </option>
                                        @endforeach

                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="method">Phương thức xét tuyển <span>*</span></label>
                                    <select id="method" name="method" required>
                                        <option value="">Chọn phương thức</option>
                                        <!-- Lấy danh sách phương thức xét tuyển từ database -->
                                        @foreach ($admissionMethods as $method)
                                        <option value="{{ $method->code }}">
                                            {{ $method->name }}
                                        </option>
                                        @endforeach

                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="combination">Tổ hợp môn <span>*</span></label>
                                <select id="combination" name="combination" required>
                                    <option value="">Chọn tổ hợp môn</option>
                                    <!-- Lấy danh sách tổ hợp môn từ database -->
                                    @foreach ($subjectCombinations as $combination)
                                    <option value="{{ $combination->id }}">
                                        {{ $combination->code }} - {{ $combination->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Khu vực nhập điểm theo phương thức -->
                        <div class="form-section" id="scoreSection">
                            <h3><i class="fas fa-chart-line"></i> <span id="scoreTitle">Nhập điểm xét tuyển</span></h3>

                            <!-- Container for dynamic score fields -->
                            <div id="scoreFields"></div>
                        </div>

                        <!-- Ghi chú và cam kết -->
                        <div class="form-section">
                            <div class="form-group">
                                <label for="notes">Ghi chú thêm</label>
                                <textarea id="notes" name="notes" rows="3" placeholder="Nhập thông tin bổ sung nếu có..."></textarea>
                            </div>

                            <div class="form-group form-check">
                                <input type="checkbox" id="agree" name="agree" required>
                                <label for="agree">Tôi cam kết các thông tin trên là đúng sự thật và chịu trách nhiệm về tính chính xác của thông tin đã cung cấp.</label>
                            </div>

                            <div class="form-group form-check">
                                <input type="checkbox" id="newsletter" name="newsletter">
                                <label for="newsletter">Đăng ký nhận thông tin tuyển sinh qua email</label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-paper-plane"></i> Đăng ký xét tuyển
                        </button>
                    </form>
                    <div id="formMessage" class="form-message"></div>
                </div>
            </div>
        </section>

        <!-- Template cho các loại điểm -->
        <script type="text/template" id="template_thpt">
            <div class="score-group">
                <h4>Điểm thi tốt nghiệp THPT</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label for="score_math">Toán <span>*</span></label>
                        <input type="number" id="score_math" name="scores[math]" step="0.01" min="0" max="10" placeholder="0.00 - 10.00" required>
                    </div>
                    <div class="form-group">
                        <label for="score_physic">Vật lý <span>*</span></label>
                        <input type="number" id="score_physic" name="scores[physic]" step="0.01" min="0" max="10" placeholder="0.00 - 10.00" required>
                    </div>
                    <div class="form-group">
                        <label for="score_chemistry">Hóa học <span>*</span></label>
                        <input type="number" id="score_chemistry" name="scores[chemistry]" step="0.01" min="0" max="10" placeholder="0.00 - 10.00" required>
                    </div>
                </div>
                <div class="total-score">
                    <strong>Tổng điểm: <span id="total_thpt">0.00</span></strong>
                    <small>(chưa tính điểm ưu tiên)</small>
                </div>
            </div>
        </script>

        <script type="text/template" id="template_hocba">
            <div class="score-group">
                <h4>Điểm học bạ THPT</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Học kỳ 1 lớp 10</label>
                        <input type="number" name="scores[grade10_sem1]" step="0.01" min="0" max="10" placeholder="0.00 - 10.00">
                    </div>
                    <div class="form-group">
                        <label>Học kỳ 2 lớp 10</label>
                        <input type="number" name="scores[grade10_sem2]" step="0.01" min="0" max="10" placeholder="0.00 - 10.00">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Học kỳ 1 lớp 11</label>
                        <input type="number" name="scores[grade11_sem1]" step="0.01" min="0" max="10" placeholder="0.00 - 10.00">
                    </div>
                    <div class="form-group">
                        <label>Học kỳ 2 lớp 11</label>
                        <input type="number" name="scores[grade11_sem2]" step="0.01" min="0" max="10" placeholder="0.00 - 10.00">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Học kỳ 1 lớp 12</label>
                        <input type="number" name="scores[grade12_sem1]" step="0.01" min="0" max="10" placeholder="0.00 - 10.00">
                    </div>
                    <div class="form-group">
                        <label>Điểm trung bình cả năm lớp 12</label>
                        <input type="number" name="scores[grade12_avg]" step="0.01" min="0" max="10" placeholder="0.00 - 10.00">
                    </div>
                </div>
                <div class="total-score">
                    <strong>Điểm TB 3 năm: <span id="total_hocba">0.00</span></strong>
                </div>
            </div>
        </script>

        <script type="text/template" id="template_dgnl">
            <div class="score-group">
                <h4>Điểm đánh giá năng lực</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label for="dgnl_score">Điểm thi ĐGNL <span>*</span></label>
                        <input type="number" id="dgnl_score" name="scores[dgnl]" step="1" min="0" max="1200" placeholder="0 - 1200" required>
                    </div>
                    <div class="form-group">
                        <label for="dgnl_year">Năm thi <span>*</span></label>
                        <select id="dgnl_year" name="scores[dgnl_year]" required>
                            <option value="">Chọn năm</option>
                            <option value="2024">2024</option>
                            <option value="2023">2023</option>
                        </select>
                    </div>
                </div>
                <div class="total-score">
                    <strong>Điểm ĐGNL: <span id="total_dgnl">0</span></strong>
                </div>
            </div>
        </script>

        <script type="text/template" id="template_direct">
            <div class="score-group">
            <h4>Xét tuyển thẳng</h4>
            <div class="form-group">
                <label for="direct_type">Loại ưu tiên <span>*</span></label>
                <select id="direct_type" name="scores[direct_type]" required>
                    <option value="">Chọn loại ưu tiên</option>
                    <option value="1">Đạt giải Quốc gia, Quốc tế</option>
                    <option value="2">Học sinh trường chuyên</option>
                    <option value="3">Đối tượng chính sách</option>
                </select>
            </div>
            <div class="form-group">
                <label for="direct_detail">Chi tiết thành tích</label>
                <textarea id="direct_detail" name="scores[direct_detail]" rows="2" placeholder="Mô tả chi tiết thành tích..."></textarea>
            </div>
            <div class="form-group file-upload">
                <label for="direct_file">Tải lên minh chứng</label>
                <input type="file" id="direct_file" name="direct_file" accept=".pdf,.jpg,.jpeg,.png">
            </div>
        </div>
    </script>

        <!-- Tin tức tuyển sinh -->
        <section id="news" class="news section bg-light">
            <div class="container">
                <div class="section-header">
                    <h2>Tin tức tuyển sinh</h2>
                    <p>Cập nhật thông tin mới nhất về kỳ tuyển sinh</p>
                </div>

                <div class="news-grid">
                    <article class="news-card">
                        <div class="news-image">
                            <img src="images/news1.jpg" alt="News 1" onerror="this.src='images/partner1.png'">
                        </div>
                        <div class="news-content">
                            <div class="news-date">
                                <i class="far fa-calendar-alt"></i> 15/01/2024
                            </div>
                            <h3>Thông báo tuyển sinh năm 2024</h3>
                            <p>Đại học Khoa học và Công nghệ chính thức công bố đề án tuyển sinh năm 2024 với nhiều điểm mới...</p>
                            <a href="#" class="read-more">Đọc tiếp <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </article>

                    <article class="news-card">
                        <div class="news-image">
                            <img src="images/news2.jpg" alt="News 2" onerror="this.src='images/partner1.png'">
                        </div>
                        <div class="news-content">
                            <div class="news-date">
                                <i class="far fa-calendar-alt"></i> 10/01/2024
                            </div>
                            <h3>Hướng dẫn đăng ký xét tuyển trực tuyến</h3>
                            <p>Chi tiết các bước đăng ký xét tuyển trực tuyến tại trường Đại học Khoa học và Công nghệ...</p>
                            <a href="#" class="read-more">Đọc tiếp <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </article>

                    <article class="news-card">
                        <div class="news-image">
                            <img src="images/news3.jpg" alt="News 3" onerror="this.src='images/partner1.png'">
                        </div>
                        <div class="news-content">
                            <div class="news-date">
                                <i class="far fa-calendar-alt"></i> 05/01/2024
                            </div>
                            <h3>Chương trình học bổng dành cho tân sinh viên</h3>
                            <p>Trường công bố các gói học bổng hấp dẫn dành cho tân sinh viên có thành tích cao...</p>
                            <a href="#" class="read-more">Đọc tiếp <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </article>
                </div>

            </div>
        </section>

        <!-- Câu hỏi thường gặp -->
        <section id="faq" class="faq section">
            <div class="container">
                <div class="section-header">
                    <h2>Câu hỏi thường gặp</h2>
                    <p>Giải đáp thắc mắc về tuyển sinh</p>
                </div>

                <div class="faq-container">
                    <div class="faq-item">
                        <div class="faq-question">
                            <h3>Hồ sơ đăng ký xét tuyển gồm những gì?</h3>
                            <span class="faq-toggle"><i class="fas fa-plus"></i></span>
                        </div>
                        <div class="faq-answer">
                            <p>Hồ sơ đăng ký xét tuyển bao gồm: Đơn đăng ký xét tuyển, Bản sao công chứng học bạ THPT, Bản sao công chứng bằng tốt nghiệp THPT hoặc giấy chứng nhận tốt nghiệp tạm thời, Các giấy tờ ưu tiên (nếu có), 02 ảnh 3x4.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <h3>Điểm chuẩn các ngành năm 2023 là bao nhiêu?</h3>
                            <span class="faq-toggle"><i class="fas fa-plus"></i></span>
                        </div>
                        <div class="faq-answer">
                            <p>Điểm chuẩn năm 2023 dao động từ 22-27 điểm tùy theo ngành. Ngành có điểm cao nhất là Khoa học máy tính 27 điểm, ngành Công nghệ thông tin 26.5 điểm.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <h3>Trường có ký túc xá cho sinh viên không?</h3>
                            <span class="faq-toggle"><i class="fas fa-plus"></i></span>
                        </div>
                        <div class="faq-answer">
                            <p>Trường có ký túc xá với sức chứa 3.000 sinh viên. Ký túc xá được trang bị đầy đủ tiện nghi, có wifi, phòng tập thể thao, căn tin phục vụ 24/7.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <h3>Học phí trung bình một năm là bao nhiêu?</h3>
                            <span class="faq-toggle"><i class="fas fa-plus"></i></span>
                        </div>
                        <div class="faq-answer">
                            <p>Học phí dự kiến năm 2024 từ 12-18 triệu đồng/năm học tùy theo ngành. Học phí có thể điều chỉnh theo quy định của Nhà nước nhưng không tăng quá 10%/năm.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Đối tác -->
        <section class="partners section bg-light">
            <div class="container">
                <div class="section-header">
                    <h2>Đối tác doanh nghiệp</h2>
                    <p>Các đối tác tuyển dụng và hợp tác đào tạo</p>
                </div>

                <div class="partners-slider">
                    <div class="partner-item">
                        <img src="images/partner.png" alt="Partner 1" onerror="this.src='images/partner1.png'">
                    </div>
                    <div class="partner-item">
                        <img src="images/partner.png" alt="Partner 2" onerror="this.src='images/partner1.png'">
                    </div>
                    <div class="partner-item">
                        <img src="images/partner1.png" alt="Partner 3" onerror="this.src='images/partner1.png'">
                    </div>
                    <div class="partner-item">
                        <img src="images/partner1.png" alt="Partner 4" onerror="this.src='images/partner1.png'">
                    </div>
                    <div class="partner-item">
                        <img src="images/partner1.png" alt="Partner 5" onerror="this.src='images/partner1.png'">
                    </div>
                </div>
            </div>
        </section>

        <!-- Liên hệ -->
        <section id="contact" class="contact section">
            <div class="container">
                <div class="section-header">
                    <h2>Liên hệ tư vấn tuyển sinh</h2>
                    <p>Mọi thắc mắc về tuyển sinh xin vui lòng liên hệ</p>
                </div>

                <div class="contact-grid">
                    <div class="contact-info">
                        <div class="contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <div>
                                <h4>Địa chỉ</h4>
                                <p>Phòng 101, Nhà A1, Đại học Khoa học và Công nghệ</p>
                                <p>Số 1, Đại Cồ Việt, Hai Bà Trưng, Hà Nội</p>
                            </div>
                        </div>

                        <div class="contact-item">
                            <i class="fas fa-phone-alt"></i>
                            <div>
                                <h4>Điện thoại</h4>
                                <p>Hotline: 1900 1234 (nhánh số 1 - Tuyển sinh)</p>
                                <p>Văn phòng: (024) 3869 1234</p>
                            </div>
                        </div>

                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <h4>Email</h4>
                                <p>tuyensinh@hus.edu.vn</p>
                                <p>info@hus.edu.vn</p>
                            </div>
                        </div>

                        <div class="contact-item">
                            <i class="fas fa-clock"></i>
                            <div>
                                <h4>Giờ làm việc</h4>
                                <p>Thứ 2 - Thứ 6: 8:00 - 17:00</p>
                                <p>Thứ 7: 8:00 - 11:30</p>
                            </div>
                        </div>
                    </div>

                    <div class="contact-form">
                        <form id="contactForm" action="php/contact.php" method="POST">
                            <div class="form-group">
                                <input type="text" name="name" placeholder="Họ và tên *" required>
                            </div>
                            <div class="form-group">
                                <input type="email" name="email" placeholder="Email *" required>
                            </div>
                            <div class="form-group">
                                <input type="tel" name="phone" placeholder="Số điện thoại *" required>
                            </div>
                            <div class="form-group">
                                <input type="text" name="subject" placeholder="Tiêu đề *" required>
                            </div>
                            <div class="form-group">
                                <textarea name="message" rows="5" placeholder="Nội dung *" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Gửi tin nhắn</button>
                        </form>
                    </div>
                </div>
            </div>
        </section>

        <!-- Map -->
        <section class="map">
            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3724.485516688149!2d105.843444314763!3d21.006574986012!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3135ac76f6c2e3b3%3A0x687df3b5c3f3f3f3!2zVHLGsOG7nW5nIMSQ4bqhaSBo4buNYyBCw6FjaCBraG9h!5e0!3m2!1svi!2s!4v1620000000000!5m2!1svi!2s" allowfullscreen="" loading="lazy"></iframe>
        </section>

        <!-- Footer -->
        <footer>
            <div class="container">
                <div class="footer-grid">
                    <div class="footer-col">
                        <h3>Đại học Khoa học và Công nghệ</h3>
                        <p>Địa chỉ: Số 1, Đại Cồ Việt, Hai Bà Trưng, Hà Nội</p>
                        <p>Điện thoại: (024) 3869 1234</p>
                        <p>Email: info@hus.edu.vn</p>
                        <p>Website: www.hus.edu.vn</p>
                    </div>

                    <div class="footer-col">
                        <h3>Liên kết nhanh</h3>
                        <ul>
                            <li><a href="#home">Trang chủ</a></li>
                            <li><a href="#about">Giới thiệu</a></li>
                            <li><a href="#admission">Tuyển sinh</a></li>
                            <li><a href="#majors">Ngành học</a></li>
                            <li><a href="#contact">Liên hệ</a></li>
                        </ul>
                    </div>

                    <div class="footer-col">
                        <h3>Hỗ trợ trực tuyến</h3>
                        <ul>
                            <li><i class="fas fa-phone"></i> 1900 1234 (nhánh 1)</li>
                            <li><i class="fas fa-envelope"></i> tuyensinh@hus.edu.vn</li>
                            <li><i class="fas fa-comment"></i> Tư vấn qua Facebook</li>
                            <li><i class="fas fa-comments"></i> Tư vấn qua Zalo: 0987654321</li>
                        </ul>
                    </div>

                    <div class="footer-col">
                        <h3>Kết nối với chúng tôi</h3>
                        <div class="footer-social">
                            <a href="#"><i class="fab fa-facebook-f"></i></a>
                            <a href="#"><i class="fab fa-youtube"></i></a>
                            <a href="#"><i class="fab fa-tiktok"></i></a>
                            <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        </div>
                        <div class="newsletter">
                            <h4>Đăng ký nhận tin</h4>
                            <form id="newsletterForm">
                                <input type="email" placeholder="Email của bạn">
                                <button type="submit"><i class="fas fa-paper-plane"></i></button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="footer-bottom">
                    <p>&copy; 2024 Đại học Khoa học và Công nghệ. All rights reserved.</p>
                </div>
            </div>
        </footer>

        <!-- Scroll to top -->
        <button id="scrollTop" class="scroll-top">
            <i class="fas fa-arrow-up"></i>
        </button>

        <script src="{{ asset('js/main.js') }}"></script>
        <script>
            // Loading spinner
            window.addEventListener('load', function() {
                const loading = document.getElementById('loading');
                if (loading) {
                    setTimeout(() => {
                        loading.classList.add('hidden');
                    }, 500);
                }
            });

            // Navbar scroll effect
            window.addEventListener('scroll', function() {
                const navbar = document.querySelector('.navbar');
                if (window.scrollY > 100) {
                    navbar.classList.add('scrolled');
                } else {
                    navbar.classList.remove('scrolled');
                }
            });

            // Add AOS animations if you want to use
            // You can add AOS library from: https://michalsnik.github.io/aos/
        </script>
        <script src="{{ asset('js/home.js') }}"></script>
        <script >
            const province = document.getElementById("province");
            const district = document.getElementById("district");
            const ward = document.getElementById("ward");


            // LOAD TỈNH
            fetch("https://provinces.open-api.vn/api/p/")
                .then(res => res.json())
                .then(data => {

                    let html = '<option value="">Chọn tỉnh/thành phố</option>';

                    data.forEach(p => {
                        html += `<option value="${p.code}">${p.name}</option>`;
                    });

                    province.innerHTML = html;

                })
                .catch(err => console.error("Lỗi load tỉnh:", err));



            // CHỌN TỈNH → LOAD HUYỆN
            province.addEventListener("change", function() {

                let provinceCode = this.value;

                district.innerHTML = '<option value="">Đang tải...</option>';
                ward.innerHTML = '<option value="">Chọn xã/phường</option>';

                ward.disabled = true;

                if (!provinceCode) {
                    district.disabled = true;
                    return;
                }

                fetch(`https://provinces.open-api.vn/api/p/${provinceCode}?depth=2`)
                    .then(res => res.json())
                    .then(data => {

                        let html = '<option value="">Chọn quận/huyện</option>';

                        data.districts.forEach(d => {
                            html += `<option value="${d.code}">${d.name}</option>`;
                        });

                        district.innerHTML = html;

                        district.disabled = false;

                    })
                    .catch(err => {
                        console.error("Lỗi load huyện:", err);
                    });

            });



            // CHỌN HUYỆN → LOAD XÃ
            district.addEventListener("change", function() {

                let districtCode = this.value;

                ward.innerHTML = '<option value="">Đang tải...</option>';

                if (!districtCode) {
                    ward.disabled = true;
                    return;
                }

                fetch(`https://provinces.open-api.vn/api/d/${districtCode}?depth=2`)
                    .then(res => res.json())
                    .then(data => {

                        let html = '<option value="">Chọn xã/phường</option>';

                        data.wards.forEach(w => {
                            html += `<option value="${w.code}">${w.name}</option>`;
                        });

                        ward.innerHTML = html;

                        ward.disabled = false;

                    })
                    .catch(err => {
                        console.error("Lỗi load xã:", err);
                    });

            });
        </script>
        
    </body>

    </html>