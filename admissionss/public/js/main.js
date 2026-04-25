// DOM Elements
document.addEventListener('DOMContentLoaded', function () {
    // Mobile Menu Toggle
    const hamburger = document.querySelector('.hamburger');
    const navMenu = document.querySelector('.nav-menu');

    if (hamburger) {
        hamburger.addEventListener('click', function () {
            hamburger.classList.toggle('active');
            navMenu.classList.toggle('active');
        });
    }

    // Close menu when clicking on a link
    document.querySelectorAll('.nav-menu a').forEach(link => {
        link.addEventListener('click', () => {
            hamburger.classList.remove('active');
            navMenu.classList.remove('active');
        });
    });

    // Header Scroll Effect
    window.addEventListener('scroll', function () {
        const navbar = document.querySelector('.navbar');
        const scrollTop = document.getElementById('scrollTop');

        if (window.scrollY > 100) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }

        // Scroll to Top Button
        if (window.scrollY > 300) {
            scrollTop.classList.add('show');
        } else {
            scrollTop.classList.remove('show');
        }
    });

    // Hero Slider
    class HeroSlider {
        constructor() {
            this.slides = document.querySelectorAll('.slide');
            this.prevBtn = document.querySelector('.slider-btn.prev');
            this.nextBtn = document.querySelector('.slider-btn.next');
            this.dotsContainer = document.querySelector('.slider-dots');
            this.currentSlide = 0;
            this.slideInterval = null;
            this.intervalTime = 5000;

            if (this.slides.length > 0) {
                this.init();
            }
        }

        init() {
            // Create dots
            this.slides.forEach((_, index) => {
                const dot = document.createElement('span');
                dot.classList.add('dot');
                if (index === 0) dot.classList.add('active');
                dot.addEventListener('click', () => this.goToSlide(index));
                this.dotsContainer.appendChild(dot);
            });

            this.dots = document.querySelectorAll('.dot');

            // Event listeners
            if (this.prevBtn) {
                this.prevBtn.addEventListener('click', () => this.prevSlide());
            }
            if (this.nextBtn) {
                this.nextBtn.addEventListener('click', () => this.nextSlide());
            }

            // Start auto slide
            this.startAutoSlide();

            // Pause on hover
            const slider = document.querySelector('.slider-container');
            slider.addEventListener('mouseenter', () => this.stopAutoSlide());
            slider.addEventListener('mouseleave', () => this.startAutoSlide());
        }

        goToSlide(index) {
            if (index < 0) index = this.slides.length - 1;
            if (index >= this.slides.length) index = 0;

            this.slides.forEach(slide => slide.classList.remove('active'));
            this.dots.forEach(dot => dot.classList.remove('active'));

            this.slides[index].classList.add('active');
            this.dots[index].classList.add('active');
            this.currentSlide = index;
        }

        nextSlide() {
            this.goToSlide(this.currentSlide + 1);
        }

        prevSlide() {
            this.goToSlide(this.currentSlide - 1);
        }

        startAutoSlide() {
            this.slideInterval = setInterval(() => this.nextSlide(), this.intervalTime);
        }

        stopAutoSlide() {
            clearInterval(this.slideInterval);
        }
    }

    // Initialize slider
    if (document.querySelector('.slider-container')) {
        new HeroSlider();
    }

    // Smooth Scrolling for Anchor Links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Majors Filter
    const filterBtns = document.querySelectorAll('.filter-btn');
    const majorCards = document.querySelectorAll('.major-card');

    if (filterBtns.length > 0) {
        filterBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                // Remove active class from all buttons
                filterBtns.forEach(btn => btn.classList.remove('active'));

                // Add active class to clicked button
                this.classList.add('active');

                const filterValue = this.getAttribute('data-filter');

                majorCards.forEach(card => {
                    if (filterValue === 'all' || card.getAttribute('data-category') === filterValue) {
                        card.style.display = 'block';
                        setTimeout(() => {
                            card.style.opacity = '1';
                            card.style.transform = 'scale(1)';
                        }, 50);
                    } else {
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.8)';
                        setTimeout(() => {
                            card.style.display = 'none';
                        }, 300);
                    }
                });
            });
        });
    }

    // FAQ Accordion
    const faqItems = document.querySelectorAll('.faq-item');

    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question');

        if (question) {
            question.addEventListener('click', () => {
                const isActive = item.classList.contains('active');

                // Close all other items
                faqItems.forEach(otherItem => {
                    if (otherItem !== item) {
                        otherItem.classList.remove('active');
                    }
                });

                // Toggle current item
                item.classList.toggle('active', !isActive);
            });
        }
    });

    // Province-District Cascade
    const provinceSelect = document.getElementById('province');
    const districtSelect = document.getElementById('district');

    if (provinceSelect && districtSelect) {
        provinceSelect.addEventListener('change', function () {
            const provinceId = this.value;
            if (provinceId) {
                // Show loading
                districtSelect.innerHTML = '<option value="">Đang tải...</option>';
                districtSelect.disabled = true;

                // Fetch districts via AJAX
                fetch(`api/get-districts.php?province_id=${provinceId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        districtSelect.innerHTML = '<option value="">Chọn quận/huyện</option>';
                        if (data && data.length > 0) {
                            data.forEach(district => {
                                districtSelect.innerHTML += `<option value="${district.id}">${district.name}</option>`;
                            });
                            districtSelect.disabled = false;
                        } else {
                            districtSelect.innerHTML = '<option value="">Không có dữ liệu</option>';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        districtSelect.innerHTML = '<option value="">Lỗi tải dữ liệu</option>';
                    });
            } else {
                districtSelect.innerHTML = '<option value="">Chọn quận/huyện</option>';
                districtSelect.disabled = true;
            }
        });
    }

    // Dynamic Score Fields based on Admission Method
    const methodSelect = document.getElementById('method');
    const scoreFields = document.getElementById('scoreFields');
    const scoreTitle = document.getElementById('scoreTitle');

    // Templates for different methods
    const templates = {
        'thpt': `
            <div class="score-group">
                <h4><i class="fas fa-calculator"></i> Điểm thi tốt nghiệp THPT</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label for="score_math">Toán <span>*</span></label>
                        <input type="number" id="score_math" name="scores[math]" step="0.01" min="0" max="10" placeholder="0.00 - 10.00" required>
                        <small class="input-hint">Nhập điểm thi Toán</small>
                    </div>
                    <div class="form-group">
                        <label for="score_physic">Vật lý <span>*</span></label>
                        <input type="number" id="score_physic" name="scores[physic]" step="0.01" min="0" max="10" placeholder="0.00 - 10.00" required>
                        <small class="input-hint">Nhập điểm thi Vật lý</small>
                    </div>
                    <div class="form-group">
                        <label for="score_chemistry">Hóa học <span>*</span></label>
                        <input type="number" id="score_chemistry" name="scores[chemistry]" step="0.01" min="0" max="10" placeholder="0.00 - 10.00" required>
                        <small class="input-hint">Nhập điểm thi Hóa học</small>
                    </div>
                </div>
                <div class="total-score">
                    <div class="total-label">
                        <i class="fas fa-star"></i>
                        <strong>Tổng điểm 3 môn:</strong>
                    </div>
                    <span id="total_thpt">0.00</span>
                </div>
                <div class="score-note">
                    <i class="fas fa-info-circle"></i>
                    <small>Điểm chuẩn năm 2023: 22-27 điểm (tùy ngành). Điểm ưu tiên sẽ được cộng sau.</small>
                </div>
            </div>
        `,
        'hocba': `
            <div class="score-group">
                <h4><i class="fas fa-book"></i> Điểm học bạ THPT</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Điểm TB Học kỳ 1 lớp 10</label>
                        <input type="number" name="scores[grade10_sem1]" step="0.01" min="0" max="10" placeholder="0.00 - 10.00">
                    </div>
                    <div class="form-group">
                        <label>Điểm TB Học kỳ 2 lớp 10</label>
                        <input type="number" name="scores[grade10_sem2]" step="0.01" min="0" max="10" placeholder="0.00 - 10.00">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Điểm TB Học kỳ 1 lớp 11</label>
                        <input type="number" name="scores[grade11_sem1]" step="0.01" min="0" max="10" placeholder="0.00 - 10.00">
                    </div>
                    <div class="form-group">
                        <label>Điểm TB Học kỳ 2 lớp 11</label>
                        <input type="number" name="scores[grade11_sem2]" step="0.01" min="0" max="10" placeholder="0.00 - 10.00">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Điểm TB Học kỳ 1 lớp 12 <span>*</span></label>
                        <input type="number" name="scores[grade12_sem1]" step="0.01" min="0" max="10" placeholder="0.00 - 10.00" required>
                    </div>
                    <div class="form-group">
                        <label>Điểm TB cả năm lớp 12 <span>*</span></label>
                        <input type="number" name="scores[grade12_avg]" step="0.01" min="0" max="10" placeholder="0.00 - 10.00" required>
                    </div>
                </div>
                <div class="total-score">
                    <div class="total-label">
                        <i class="fas fa-star"></i>
                        <strong>Điểm TB 3 năm:</strong>
                    </div>
                    <span id="total_hocba">0.00</span>
                </div>
                <div class="score-note">
                    <i class="fas fa-info-circle"></i>
                    <small>Điểm xét tuyển = (Điểm TB lớp 10 + lớp 11 + lớp 12) / 3</small>
                </div>
            </div>
        `,
        'dgnl': `
            <div class="score-group">
                <h4><i class="fas fa-brain"></i> Điểm đánh giá năng lực</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label for="dgnl_score">Điểm thi ĐGNL <span>*</span></label>
                        <input type="number" id="dgnl_score" name="scores[dgnl]" step="1" min="0" max="1200" placeholder="0 - 1200" required>
                        <small class="input-hint">Thang điểm 1200 (ĐHQG-HCM)</small>
                    </div>
                    <div class="form-group">
                        <label for="dgnl_year">Năm thi <span>*</span></label>
                        <select id="dgnl_year" name="scores[dgnl_year]" required>
                            <option value="">Chọn năm</option>
                            <option value="2024">2024</option>
                            <option value="2023">2023</option>
                            <option value="2022">2022</option>
                        </select>
                    </div>
                </div>
                <div class="total-score">
                    <div class="total-label">
                        <i class="fas fa-star"></i>
                        <strong>Điểm ĐGNL:</strong>
                    </div>
                    <span id="total_dgnl">0</span>
                </div>
                <div class="score-note">
                    <i class="fas fa-info-circle"></i>
                    <small>Điểm chuẩn năm 2023: 800-950 điểm</small>
                </div>
            </div>
        `,
        'direct': `
            <div class="score-group">
                <h4><i class="fas fa-trophy"></i> Xét tuyển thẳng</h4>
                <div class="form-group">
                    <label for="direct_type">Loại ưu tiên <span>*</span></label>
                    <select id="direct_type" name="scores[direct_type]" required>
                        <option value="">Chọn loại ưu tiên</option>
                        <option value="1">Đạt giải Nhất/Nhì/Ba kỳ thi HSG Quốc gia</option>
                        <option value="2">Đạt giải Khuyến khích kỳ thi HSG Quốc gia</option>
                        <option value="3">Đạt giải Cuộc thi KHKT Quốc gia</option>
                        <option value="4">Học sinh trường chuyên</option>
                        <option value="5">Đối tượng chính sách (theo quy định)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="direct_detail">Chi tiết thành tích</label>
                    <textarea id="direct_detail" name="scores[direct_detail]" rows="3" placeholder="Mô tả chi tiết thành tích (tên giải, năm đạt giải, ...)"></textarea>
                </div>
                <div class="form-group file-upload">
                    <label for="direct_file">Tải lên minh chứng (Giấy chứng nhận, bằng khen)</label>
                    <input type="file" id="direct_file" name="direct_file" accept=".pdf,.jpg,.jpeg,.png">
                    <small>Chấp nhận file PDF, JPG, PNG (tối đa 5MB)</small>
                </div>
                <div class="total-score">
                    <div class="total-label">
                        <i class="fas fa-star"></i>
                        <strong>Xét tuyển thẳng:</strong>
                    </div>
                    <span class="badge badge-success">Đủ điều kiện</span>
                </div>
            </div>
        `,
        'diem_thi': `
            <div class="score-group">
                <h4><i class="fas fa-chart-line"></i> Điểm thi các môn</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label for="subject1">Môn 1 <span>*</span></label>
                        <select id="subject1" name="scores[subject1_name]" required>
                            <option value="">Chọn môn</option>
                            <option value="toan">Toán</option>
                            <option value="van">Ngữ văn</option>
                            <option value="anh">Tiếng Anh</option>
                            <option value="ly">Vật lý</option>
                            <option value="hoa">Hóa học</option>
                            <option value="sinh">Sinh học</option>
                            <option value="su">Lịch sử</option>
                            <option value="dia">Địa lý</option>
                            <option value="gdcd">GDCD</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="score1">Điểm <span>*</span></label>
                        <input type="number" id="score1" name="scores[subject1_score]" step="0.01" min="0" max="10" placeholder="0.00 - 10.00" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="subject2">Môn 2 <span>*</span></label>
                        <select id="subject2" name="scores[subject2_name]" required>
                            <option value="">Chọn môn</option>
                            <option value="toan">Toán</option>
                            <option value="van">Ngữ văn</option>
                            <option value="anh">Tiếng Anh</option>
                            <option value="ly">Vật lý</option>
                            <option value="hoa">Hóa học</option>
                            <option value="sinh">Sinh học</option>
                            <option value="su">Lịch sử</option>
                            <option value="dia">Địa lý</option>
                            <option value="gdcd">GDCD</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="score2">Điểm <span>*</span></label>
                        <input type="number" id="score2" name="scores[subject2_score]" step="0.01" min="0" max="10" placeholder="0.00 - 10.00" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="subject3">Môn 3 <span>*</span></label>
                        <select id="subject3" name="scores[subject3_name]" required>
                            <option value="">Chọn môn</option>
                            <option value="toan">Toán</option>
                            <option value="van">Ngữ văn</option>
                            <option value="anh">Tiếng Anh</option>
                            <option value="ly">Vật lý</option>
                            <option value="hoa">Hóa học</option>
                            <option value="sinh">Sinh học</option>
                            <option value="su">Lịch sử</option>
                            <option value="dia">Địa lý</option>
                            <option value="gdcd">GDCD</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="score3">Điểm <span>*</span></label>
                        <input type="number" id="score3" name="scores[subject3_score]" step="0.01" min="0" max="10" placeholder="0.00 - 10.00" required>
                    </div>
                </div>
                <div class="total-score">
                    <div class="total-label">
                        <i class="fas fa-star"></i>
                        <strong>Tổng điểm 3 môn:</strong>
                    </div>
                    <span id="total_diemthi">0.00</span>
                </div>
            </div>
        `
    };

    if (methodSelect && scoreFields) {
        methodSelect.addEventListener('change', function () {
            const method = this.value;
            if (method && templates[method]) {
                // Animate transition
                scoreFields.style.opacity = '0';
                setTimeout(() => {
                    scoreFields.innerHTML = templates[method];

                    // Update title
                    const methodText = this.options[this.selectedIndex]?.text || 'Nhập điểm xét tuyển';
                    if (scoreTitle) {
                        scoreTitle.textContent = methodText;
                    }

                    // Show score section
                    scoreFields.style.opacity = '1';

                    // Initialize score calculations
                    initScoreCalculations(method);
                }, 300);
            } else {
                scoreFields.style.opacity = '0';
                setTimeout(() => {
                    scoreFields.innerHTML = '';
                    if (scoreTitle) {
                        scoreTitle.textContent = 'Nhập điểm xét tuyển';
                    }
                    scoreFields.style.opacity = '1';
                }, 300);
            }
        });
    }

    // Initialize score calculations
    function initScoreCalculations(method) {
        if (method === 'thpt') {
            const mathInput = document.getElementById('score_math');
            const physicInput = document.getElementById('score_physic');
            const chemistryInput = document.getElementById('score_chemistry');
            const totalSpan = document.getElementById('total_thpt');

            if (mathInput && physicInput && chemistryInput && totalSpan) {
                [mathInput, physicInput, chemistryInput].forEach(input => {
                    input.addEventListener('input', function () {
                        const math = parseFloat(mathInput.value) || 0;
                        const physic = parseFloat(physicInput.value) || 0;
                        const chemistry = parseFloat(chemistryInput.value) || 0;
                        const total = math + physic + chemistry;
                        totalSpan.textContent = total.toFixed(2);

                        // Color coding based on total score
                        if (total >= 24) {
                            totalSpan.style.color = '#4cc9f0';
                        } else if (total >= 18) {
                            totalSpan.style.color = '#f8961e';
                        } else {
                            totalSpan.style.color = '#f94144';
                        }
                    });
                });
            }
        }

        if (method === 'hocba') {
            const inputs = document.querySelectorAll('[name^="scores[grade"]');
            const totalSpan = document.getElementById('total_hocba');

            if (inputs.length > 0 && totalSpan) {
                inputs.forEach(input => {
                    input.addEventListener('input', function () {
                        let sum = 0;
                        let count = 0;
                        inputs.forEach(inp => {
                            const val = parseFloat(inp.value);
                            if (!isNaN(val)) {
                                sum += val;
                                count++;
                            }
                        });
                        const avg = count > 0 ? sum / count : 0;
                        totalSpan.textContent = avg.toFixed(2);

                        // Highlight based on average
                        if (avg >= 8.0) {
                            totalSpan.style.color = '#4cc9f0';
                        } else if (avg >= 6.5) {
                            totalSpan.style.color = '#f8961e';
                        } else {
                            totalSpan.style.color = '#f94144';
                        }
                    });
                });
            }
        }

        if (method === 'dgnl') {
            const dgnlInput = document.getElementById('dgnl_score');
            const totalSpan = document.getElementById('total_dgnl');

            if (dgnlInput && totalSpan) {
                dgnlInput.addEventListener('input', function () {
                    const score = this.value || '0';
                    totalSpan.textContent = score;

                    // Highlight based on score
                    const numScore = parseFloat(score);
                    if (numScore >= 900) {
                        totalSpan.style.color = '#4cc9f0';
                    } else if (numScore >= 700) {
                        totalSpan.style.color = '#f8961e';
                    } else {
                        totalSpan.style.color = '#f94144';
                    }
                });
            }
        }

        if (method === 'diem_thi') {
            const subject1Score = document.getElementById('score1');
            const subject2Score = document.getElementById('score2');
            const subject3Score = document.getElementById('score3');
            const totalSpan = document.getElementById('total_diemthi');

            if (subject1Score && subject2Score && subject3Score && totalSpan) {
                [subject1Score, subject2Score, subject3Score].forEach(input => {
                    input.addEventListener('input', function () {
                        const s1 = parseFloat(subject1Score.value) || 0;
                        const s2 = parseFloat(subject2Score.value) || 0;
                        const s3 = parseFloat(subject3Score.value) || 0;
                        const total = s1 + s2 + s3;
                        totalSpan.textContent = total.toFixed(2);

                        if (total >= 24) {
                            totalSpan.style.color = '#4cc9f0';
                        } else if (total >= 18) {
                            totalSpan.style.color = '#f8961e';
                        } else {
                            totalSpan.style.color = '#f94144';
                        }
                    });
                });
            }
        }
    }

    // Registration Form Submission
    const registerForm = document.getElementById('registerForm');
    const formMessage = document.getElementById('formMessage');

    if (registerForm) {
        registerForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            // Validate form
            let isValid = true;
            const requiredFields = this.querySelectorAll('[required]');

            // Clear previous error styles
            requiredFields.forEach(field => {
                field.style.borderColor = '';
                field.classList.remove('error');
            });

            // Check required fields
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = 'red';
                    field.classList.add('error');
                    isValid = false;

                    // Scroll to first error
                    if (isValid === false) {
                        field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            });

            // Validate email
            const email = document.getElementById('email');
            if (email && email.value && !isValidEmail(email.value)) {
                email.style.borderColor = 'red';
                email.classList.add('error');
                isValid = false;
            }

            // Validate phone
            const phone = document.getElementById('phone');
            if (phone && phone.value && !isValidPhone(phone.value)) {
                phone.style.borderColor = 'red';
                phone.classList.add('error');
                isValid = false;
            }

            // Validate identification
            const identification = document.getElementById('identification');
            if (identification && identification.value && !isValidIdentification(identification.value)) {
                identification.style.borderColor = 'red';
                identification.classList.add('error');
                isValid = false;
            }

            // Validate scores based on method
            const method = document.getElementById('method')?.value;
            if (method && !validateScores(method)) {
                isValid = false;
            }

            if (!isValid) {
                showFormMessage('Vui lòng điền đầy đủ thông tin hợp lệ', 'error');
                return;
            }

            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';
            submitBtn.disabled = true;
            submitBtn.classList.add('btn-loading');

            // Prepare form data
            const formData = new FormData(this);

            try {
                // Submit form via AJAX
                const response = await fetch('php/register.php', {
                    method: 'POST',
                    body: formData
                });

                const text = await response.text();
                console.log("SERVER RESPONSE:", text);

                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error("Server trả về không phải JSON:", text);
                    showFormMessage('Lỗi server: response không hợp lệ', 'error');
                    return;
                }

                if (data.success) {

                    const regId = data.registration_id || data.data?.registration_id || '';

                    showRegisterPopup(regId, data.message);

                    registerForm.reset();

                    // Reset dynamic fields
                    if (scoreFields) {
                        scoreFields.innerHTML = '';
                    }
                    if (scoreTitle) {
                        scoreTitle.textContent = 'Nhập điểm xét tuyển';
                    }

                    // Scroll to top
                    window.scrollTo({ top: 0, behavior: 'smooth' });

                    // Track conversion (if using analytics)
                    if (typeof gtag !== 'undefined') {
                        gtag('event', 'conversion', {
                            'send_to': 'AW-XXXXXX/YYYYYY',
                            'value': 1.0,
                            'currency': 'VND'
                        });
                    }
                } else {
                    showFormMessage(data.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showFormMessage('Có lỗi xảy ra, vui lòng thử lại sau', 'error');
            } finally {
                // Restore button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                submitBtn.classList.remove('btn-loading');
            }
        });
    }

    // Validate scores based on method
    function validateScores(method) {
        if (method === 'thpt') {
            const math = parseFloat(document.getElementById('score_math')?.value);
            const physic = parseFloat(document.getElementById('score_physic')?.value);
            const chemistry = parseFloat(document.getElementById('score_chemistry')?.value);

            if (isNaN(math) || math < 0 || math > 10) return false;
            if (isNaN(physic) || physic < 0 || physic > 10) return false;
            if (isNaN(chemistry) || chemistry < 0 || chemistry > 10) return false;
        }

        if (method === 'dgnl') {
            const dgnl = parseFloat(document.getElementById('dgnl_score')?.value);
            const year = document.getElementById('dgnl_year')?.value;

            if (isNaN(dgnl) || dgnl < 0 || dgnl > 1200) return false;
            if (!year) return false;
        }

        if (method === 'direct') {
            const directType = document.getElementById('direct_type')?.value;
            if (!directType) return false;
        }

        if (method === 'diem_thi') {
            const score1 = parseFloat(document.getElementById('score1')?.value);
            const score2 = parseFloat(document.getElementById('score2')?.value);
            const score3 = parseFloat(document.getElementById('score3')?.value);

            if (isNaN(score1) || score1 < 0 || score1 > 10) return false;
            if (isNaN(score2) || score2 < 0 || score2 > 10) return false;
            if (isNaN(score3) || score3 < 0 || score3 > 10) return false;
        }

        return true;
    }

    // Contact Form
    const contactForm = document.getElementById('contactForm');

    if (contactForm) {
        contactForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;

            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang gửi...';
            submitBtn.disabled = true;

            try {
                const response = await fetch('php/contact.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    alert('✅ Tin nhắn của bạn đã được gửi thành công! Chúng tôi sẽ phản hồi trong thời gian sớm nhất.');
                    contactForm.reset();
                } else {
                    alert('❌ ' + (data.message || 'Có lỗi xảy ra, vui lòng thử lại sau'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('❌ Có lỗi xảy ra, vui lòng thử lại sau');
            } finally {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });
    }

    // Newsletter Form
    const newsletterForm = document.getElementById('newsletterForm');

    if (newsletterForm) {
        newsletterForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const email = this.querySelector('input[type="email"]').value;
            const submitBtn = this.querySelector('button[type="submit"]');

            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            submitBtn.disabled = true;

            try {
                const response = await fetch('php/newsletter.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ email: email })
                });

                const data = await response.json();

                if (data.success) {
                    alert('✅ Đăng ký nhận tin thành công!');
                    this.reset();
                } else {
                    alert('❌ ' + (data.message || 'Có lỗi xảy ra'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('❌ Có lỗi xảy ra, vui lòng thử lại sau');
            } finally {
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
                submitBtn.disabled = false;
            }
        });
    }

    // Helper Functions
    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    function isValidPhone(phone) {
        const re = /^(0[3|5|7|8|9])[0-9]{8}$/;
        return re.test(phone);
    }

    function isValidIdentification(id) {
        // CMND 9 số, CCCD 12 số
        const re = /^[0-9]{9}$|^[0-9]{12}$/;
        return re.test(id);
    }

    function showFormMessage(message, type) {
        if (!formMessage) return;

        formMessage.textContent = message;
        formMessage.className = 'form-message ' + type;
        formMessage.style.display = 'block';

        // Auto hide after 5 seconds
        setTimeout(() => {
            formMessage.style.animation = 'fadeOut 0.5s ease';
            setTimeout(() => {
                formMessage.style.display = 'none';
                formMessage.style.animation = '';
            }, 500);
        }, 5000);
    }

    // Scroll to Top
    const scrollTopBtn = document.getElementById('scrollTop');

    if (scrollTopBtn) {
        scrollTopBtn.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }

    // Active Navigation based on scroll position
    const sections = document.querySelectorAll('section[id]');

    function highlightNavigation() {
        const scrollY = window.pageYOffset;
        const navbarHeight = document.querySelector('.navbar')?.offsetHeight || 0;

        sections.forEach(section => {
            const sectionHeight = section.offsetHeight;
            const sectionTop = section.offsetTop - navbarHeight - 20;
            const sectionId = section.getAttribute('id');
            const navLink = document.querySelector(`.nav-menu a[href="#${sectionId}"]`);

            if (navLink) {
                if (scrollY > sectionTop && scrollY <= sectionTop + sectionHeight) {
                    navLink.classList.add('active');
                } else {
                    navLink.classList.remove('active');
                }
            }
        });
    }

    window.addEventListener('scroll', highlightNavigation);

    // Counter Animation
    const statNumbers = document.querySelectorAll('.stat-number');

    function animateStats() {
        statNumbers.forEach(stat => {
            const targetText = stat.innerText.replace('+', '');
            const target = parseInt(targetText);
            if (isNaN(target)) return;

            let current = 0;
            const increment = Math.ceil(target / 50);

            const updateCounter = () => {
                if (current < target) {
                    current += increment;
                    if (current > target) current = target;
                    stat.innerText = current + '+';
                    requestAnimationFrame(() => {
                        setTimeout(updateCounter, 20);
                    });
                }
            };

            updateCounter();
        });
    }

    // Trigger animation when stats come into view
    const statsObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateStats();
                statsObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });

    document.querySelectorAll('.stats').forEach(stats => {
        statsObserver.observe(stats);
    });

    // File Upload Preview and Validation
    const fileInputs = document.querySelectorAll('input[type="file"]');

    fileInputs.forEach(input => {
        input.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;

            const fileSize = file.size / 1024 / 1024; // in MB
            const fileExt = file.name.split('.').pop().toLowerCase();
            const allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];

            // Check file size
            if (fileSize > 5) {
                alert('❌ File không được vượt quá 5MB');
                this.value = '';
                return;
            }

            // Check file type
            if (!allowedExts.includes(fileExt)) {
                alert('❌ Chỉ chấp nhận file PDF, JPG, JPEG, PNG');
                this.value = '';
                return;
            }

            // Show file name
            const parent = this.closest('.file-upload');
            if (parent) {
                let fileNameDisplay = parent.querySelector('.file-name');
                if (!fileNameDisplay) {
                    fileNameDisplay = document.createElement('div');
                    fileNameDisplay.className = 'file-name';
                    parent.appendChild(fileNameDisplay);
                }
                fileNameDisplay.innerHTML = `<i class="fas fa-check-circle text-success"></i> ${file.name} (${fileSize.toFixed(2)} MB)`;
            }

            // Preview image if it's an image
            if (fileExt !== 'pdf') {
                const reader = new FileReader();
                reader.onload = function (e) {
                    let preview = parent.querySelector('.file-preview');
                    if (!preview) {
                        preview = document.createElement('div');
                        preview.className = 'file-preview';
                        parent.appendChild(preview);
                    }
                    preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                };
                reader.readAsDataURL(file);
            }
        });
    });

    // Search functionality for tuition table
    const searchInput = document.getElementById('searchTuition');
    if (searchInput) {
        searchInput.addEventListener('keyup', function () {
            const searchTerm = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('#tuitionTable tbody tr');

            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // Filter for tuition table
    const filterYear = document.getElementById('filterYear');
    if (filterYear) {
        filterYear.addEventListener('change', function () {
            // Handle year filter
            console.log('Filter by year:', this.value);
        });
    }

    // Loading spinner
    const loading = document.getElementById('loading');
    if (loading) {
        setTimeout(() => {
            loading.classList.add('hidden');
        }, 500);
    }

    // Hiển thị thông báo đăng ký xét tuyển thành công
    function showRegisterPopup(regId, message) {

    const popup = document.createElement("div");
    popup.className = "register-popup";

    popup.innerHTML = `
        <div class="popup-content">

            <h3>🎉 Đăng ký thành công</h3>

            <p>${message}</p>

            <div class="popup-id">${regId}</div>

            <p>Vui lòng lưu lại mã hồ sơ để tra cứu</p>

            <small>Thông báo sẽ tự đóng sau 10 giây</small>

        </div>
    `;

    document.body.appendChild(popup);

    setTimeout(() => {
        popup.classList.add("hide");

        setTimeout(() => {
            popup.remove();
        }, 500);

    }, 10000);
}
});