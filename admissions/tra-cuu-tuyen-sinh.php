<?php
require_once 'php/config.php';

$page_title = "Tra cứu hồ sơ tuyển sinh";
require_once '../includes/header.php';
?>

<style>
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --primary-gradient-hover: linear-gradient(135deg, #5a67d8 0%, #6b46a0 100%);
    --success-gradient: linear-gradient(135deg, #11998e, #38ef7d);
    --warning-gradient: linear-gradient(135deg, #f2994a, #f2c94c);
    --danger-gradient: linear-gradient(135deg, #eb3349, #f45c43);
    --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
    --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
    --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1);
}

/* Page Header */
.page-header-modern {
    position: relative;
    background: var(--primary-gradient);
    padding: 80px 0 120px;
    overflow: hidden;
}

.page-header-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(255,255,255,0.1)" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,154.7C960,171,1056,181,1152,170.7C1248,160,1344,128,1392,112L1440,96L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') repeat-x bottom;
    opacity: 0.3;
}

.page-title {
    font-size: 3rem;
    font-weight: 800;
    color: white;
    margin-bottom: 1rem;
    line-height: 1.2;
}

.text-gradient {
    background: linear-gradient(135deg, #fff, #e0d4ff);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.page-description {
    font-size: 1.1rem;
    color: rgba(255,255,255,0.9);
    margin-bottom: 0;
}

/* Search Card */
.search-section {
    margin-top: -60px;
    position: relative;
    z-index: 10;
    padding-bottom: 60px;
}

.search-card {
    background: white;
    border-radius: 24px;
    box-shadow: var(--shadow-xl);
    overflow: hidden;
    transition: transform 0.3s ease;
}

.search-card:hover {
    transform: translateY(-5px);
}

.search-card-header {
    background: linear-gradient(135deg, #f8f9fa, #fff);
    padding: 30px;
    text-align: center;
}

.icon-circle {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    background: var(--primary-gradient);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.icon-circle i {
    font-size: 40px;
    color: white;
}

.search-card-header h2 {
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 10px;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.search-card-body {
    padding: 30px;
}

.form-group {
    margin-bottom: 25px;
}

.form-group label {
    font-weight: 600;
    margin-bottom: 8px;
    display: block;
    color: #2d3748;
}

.form-group label i {
    margin-right: 8px;
    color: #667eea;
}

.input-group {
    position: relative;
}

.input-group i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #a0aec0;
    z-index: 1;
}

.input-group input {
    width: 100%;
    padding: 12px 15px 12px 45px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s;
}

.input-group input:focus {
    border-color: #667eea;
    outline: none;
    box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
}

.input-hint {
    font-size: 0.8rem;
    color: #718096;
    margin-top: 5px;
    display: block;
}

.btn-search {
    width: 100%;
    padding: 14px;
    background: var(--primary-gradient);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.btn-search:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.search-loading {
    display: none;
    text-align: center;
    padding: 20px;
}

.search-loading.active {
    display: block;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 3px solid #e2e8f0;
    border-top-color: #667eea;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 10px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Result Section */
.result-section {
    padding: 60px 0;
    background: #f8f9fa;
}

.result-header {
    background: white;
    border-radius: 16px;
    padding: 20px 30px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    box-shadow: var(--shadow-sm);
}

.result-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.result-title i {
    font-size: 28px;
    color: #11998e;
}

.result-title h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
}

.registration-badge {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 8px 20px;
    border-radius: 50px;
    font-weight: 600;
}

/* Profile Card */
.profile-card-modern {
    background: white;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: var(--shadow-lg);
}

.profile-cover {
    height: 120px;
    background: var(--primary-gradient);
}

.profile-info {
    padding: 0 30px 30px;
    position: relative;
}

.profile-avatar-large {
    width: 100px;
    height: 100px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: -50px;
    margin-bottom: 20px;
    box-shadow: var(--shadow-lg);
    border: 4px solid white;
}

.profile-avatar-large i {
    font-size: 50px;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.profile-name-section {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
}

.profile-name-section h3 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
}

.status-badge-modern {
    padding: 6px 16px;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.status-badge-modern.status-admitted {
    background: linear-gradient(135deg, #11998e, #38ef7d);
    color: white;
}

.status-badge-modern.status-pending {
    background: linear-gradient(135deg, #f2994a, #f2c94c);
    color: white;
}

.status-badge-modern.status-rejected {
    background: linear-gradient(135deg, #eb3349, #f45c43);
    color: white;
}

/* Info Grid */
.info-grid-modern {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.info-card-modern {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 16px;
    transition: all 0.3s;
}

.info-card-modern:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
}

.info-icon {
    width: 40px;
    height: 40px;
    background: var(--primary-gradient);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 15px;
}

.info-icon i {
    font-size: 20px;
    color: white;
}

.info-label {
    font-size: 0.8rem;
    color: #718096;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 5px;
}

.info-value {
    font-size: 1rem;
    font-weight: 600;
    color: #2d3748;
}

/* Score Card */
.score-card-modern {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 30px;
    color: white;
}

.score-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.score-header i {
    font-size: 28px;
}

.score-header h3 {
    margin: 0;
    font-size: 1.3rem;
}

.score-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
}

.score-item {
    background: rgba(255,255,255,0.2);
    padding: 12px;
    border-radius: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.score-subject {
    font-weight: 500;
}

.score-value {
    font-weight: 700;
    font-size: 1.2rem;
}

.score-total {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid rgba(255,255,255,0.3);
    display: flex;
    justify-content: space-between;
    font-weight: 700;
    font-size: 1.1rem;
}

/* Confirmation Box */
.confirmation-box {
    background: linear-gradient(135deg, #ebf8ff, #e0f0ff);
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 30px;
    text-align: center;
}

.confirmation-box h4 {
    color: #2b6cb0;
    margin-bottom: 15px;
}

.confirmed-box {
    background: linear-gradient(135deg, #11998e, #38ef7d);
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 30px;
    text-align: center;
    color: white;
}

.confirmed-box i {
    font-size: 48px;
    margin-bottom: 15px;
}

.expired-box {
    background: linear-gradient(135deg, #eb3349, #f45c43);
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 30px;
    text-align: center;
    color: white;
}

.expired-box i {
    font-size: 48px;
    margin-bottom: 15px;
}

.warning-box {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 15px;
    border-radius: 10px;
    margin: 20px 0;
    display: flex;
    align-items: center;
    gap: 15px;
}

.warning-box i {
    font-size: 24px;
    color: #ffc107;
}

.countdown-timer {
    font-size: 2rem;
    font-weight: 700;
    font-family: monospace;
    background: white;
    border-radius: 12px;
    padding: 10px;
    margin: 15px 0;
    color: #2d3748;
}

.btn-confirm-admission {
    background: linear-gradient(135deg, #11998e, #38ef7d);
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-confirm-admission:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Documents Grid */
.documents-grid-modern {
    margin-bottom: 30px;
}

.documents-grid-modern h3 {
    margin-bottom: 15px;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.documents-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
}

.document-card {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s;
}

.document-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
}

.document-icon {
    width: 45px;
    height: 45px;
    background: var(--primary-gradient);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.document-icon i {
    font-size: 24px;
    color: white;
}

.document-info {
    flex: 1;
}

.document-name {
    font-weight: 600;
    margin-bottom: 4px;
}

.document-size {
    font-size: 0.75rem;
    color: #718096;
}

.btn-download {
    background: none;
    border: none;
    color: #667eea;
    cursor: pointer;
    font-size: 1.2rem;
    transition: all 0.3s;
}

.btn-download:hover {
    transform: scale(1.1);
}

/* Timeline */
.timeline-modern {
    margin-bottom: 30px;
}

.timeline-modern h3 {
    margin-bottom: 20px;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.timeline-container {
    position: relative;
    padding-left: 30px;
}

.timeline-container::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(180deg, #667eea, #764ba2);
}

.timeline-item-modern {
    position: relative;
    padding-bottom: 25px;
}

.timeline-item-modern::before {
    content: '';
    position: absolute;
    left: -26px;
    top: 0;
    width: 12px;
    height: 12px;
    background: #667eea;
    border-radius: 50%;
    border: 2px solid white;
    box-shadow: var(--shadow-sm);
}

.timeline-date {
    font-size: 0.8rem;
    color: #718096;
    margin-bottom: 5px;
}

.timeline-content h4 {
    margin: 0 0 5px;
    font-size: 1rem;
}

.timeline-content p {
    margin: 0;
    color: #718096;
    font-size: 0.9rem;
}

/* Action Buttons */
.action-buttons-modern {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    margin-top: 30px;
}

.btn-action-modern {
    flex: 1;
    padding: 12px;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.btn-primary-modern {
    background: var(--primary-gradient);
    color: white;
}

.btn-outline-modern {
    background: white;
    color: #667eea;
    border: 2px solid #667eea;
}

.btn-secondary-modern {
    background: #f8f9fa;
    color: #2d3748;
}

.btn-action-modern:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal.show {
    display: flex;
    animation: fadeIn 0.3s;
}

.modal-content {
    background: white;
    border-radius: 24px;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    animation: slideUp 0.3s;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 10px;
    position: sticky;
    top: 0;
    background: white;
}

.modal-header i {
    font-size: 24px;
    color: #11998e;
}

.modal-header h3 {
    flex: 1;
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    gap: 15px;
}

.btn-cancel, .btn-confirm {
    flex: 1;
    padding: 12px;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-cancel {
    background: #e2e8f0;
    color: #2d3748;
}

.btn-confirm {
    background: linear-gradient(135deg, #11998e, #38ef7d);
    color: white;
}

/* Error Message */
.error-message-modern {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 24px;
    box-shadow: var(--shadow-lg);
}

.error-message-modern i {
    font-size: 64px;
    color: #f45c43;
    margin-bottom: 20px;
}

.error-message-modern h3 {
    font-size: 1.5rem;
    margin-bottom: 10px;
}

.btn-retry {
    margin-top: 20px;
    padding: 10px 30px;
    background: var(--primary-gradient);
    color: white;
    border: none;
    border-radius: 10px;
    cursor: pointer;
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from { transform: translateY(50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

/* Responsive */
@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
    
    .profile-name-section {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .action-buttons-modern {
        flex-direction: column;
    }
    
    .info-grid-modern {
        grid-template-columns: 1fr;
    }
    
    .score-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Page Header -->
<section class="page-header-modern">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="page-title">Tra cứu hồ sơ<br><span class="text-gradient">tuyển sinh 2024</span></h1>
                <p class="page-description">Nhập thông tin để kiểm tra tình trạng hồ sơ và kết quả xét tuyển của bạn</p>
            </div>
            <div class="col-lg-6 text-center">
                <div class="page-illustration">
                    <i class="fas fa-search fa-6x text-white opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Search Section -->
<section class="search-section">
    <div class="container">
        <div class="search-card">
            <div class="search-card-header">
                <div class="icon-circle">
                    <i class="fas fa-id-card"></i>
                </div>
                <h2>Tra cứu hồ sơ</h2>
                <p>Nhập CCCD/CMND và ngày sinh để kiểm tra</p>
            </div>
            <div class="search-card-body">
                <form class="search-form" id="searchForm">
                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> Số CCCD/CMND <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <i class="fas fa-id-card"></i>
                            <input type="text" id="identification" name="identification" placeholder="Nhập số CCCD/CMND (9 hoặc 12 số)"
                                pattern="[0-9]{9,12}" maxlength="12" required autocomplete="off">
                        </div>
                        <span class="input-hint">Nhập đúng số CCCD/CMND đã đăng ký</span>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-calendar-alt"></i> Ngày sinh <span class="text-danger">*</span></label>
                        <div class="input-group date">
                            <i class="fas fa-calendar-alt"></i>
                            <input type="date" id="birthday" name="birthday" required>
                        </div>
                        <span class="input-hint">Chọn ngày/tháng/năm sinh</span>
                    </div>

                    <div class="search-loading" id="searchLoading">
                        <div class="spinner"></div>
                        <p>Đang tra cứu...</p>
                    </div>

                    <button type="submit" class="btn-search" id="searchBtn">
                        <i class="fas fa-search"></i>
                        <span>Tra cứu ngay</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- Result Section -->
<section class="result-section" id="resultSection" style="display: none;">
    <div class="container">
        <div class="result-header">
            <div class="result-title">
                <i class="fas fa-check-circle"></i>
                <h2>Kết quả tra cứu</h2>
            </div>
            <div class="registration-badge">
                <i class="fas fa-hashtag"></i> Mã hồ sơ: <span id="regId"></span>
            </div>
        </div>

        <div class="profile-card-modern">
            <div class="profile-cover"></div>
            <div class="profile-info">
                <div class="profile-avatar-large" id="profileAvatar">
                    <i class="fas fa-user-graduate"></i>
                </div>

                <div class="profile-name-section">
                    <h3 id="studentName"></h3>
                    <span class="status-badge-modern" id="statusBadge"></span>
                </div>

                <div class="info-grid-modern" id="basicInfo"></div>
                <div class="info-grid-modern" id="admissionInfo"></div>

                <div class="score-card-modern" id="scoreSection" style="display: none;">
                    <div class="score-header">
                        <i class="fas fa-chart-line"></i>
                        <h3>Điểm xét tuyển</h3>
                    </div>
                    <div class="score-grid" id="scoreGrid"></div>
                </div>

                <div id="admissionResult"></div>

                <div class="documents-grid-modern" id="documentsList" style="display: none;">
                    <h3><i class="fas fa-paperclip"></i> Hồ sơ đính kèm</h3>
                    <div class="documents-container" id="documentsContainer"></div>
                </div>

                <div class="timeline-modern" id="timeline" style="display: none;">
                    <h3><i class="fas fa-history"></i> Lịch sử xử lý</h3>
                    <div class="timeline-container" id="timelineContainer"></div>
                </div>

                <div class="action-buttons-modern">
                    <button class="btn-action-modern btn-primary-modern" onclick="window.print()">
                        <i class="fas fa-print"></i> In hồ sơ
                    </button>
                    <button class="btn-action-modern btn-outline-modern" onclick="downloadProfile()">
                        <i class="fas fa-file-pdf"></i> Tải PDF
                    </button>
                    <button class="btn-action-modern btn-secondary-modern" onclick="contactAdmission()">
                        <i class="fas fa-headset"></i> Liên hệ tư vấn
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
// [Giữ nguyên toàn bộ JavaScript từ code cũ, chỉ cập nhật CSS classes nếu cần]

let currentStudent = null;
let countdownInterval = null;

// Search form submission
document.getElementById('searchForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const cccd = document.getElementById('identification').value.trim();
    const birthday = document.getElementById('birthday').value;

    if (!cccd || !birthday) {
        alert('Vui lòng nhập đầy đủ thông tin');
        return;
    }

    if (!/^[0-9]{9,12}$/.test(cccd)) {
        alert('Số CCCD/CMND không hợp lệ (phải là 9 hoặc 12 số)');
        return;
    }

    document.getElementById('searchLoading').classList.add('active');
    document.getElementById('searchBtn').disabled = true;

    try {
        const response = await fetch(`api/tra-cuu.php?identification=${cccd}&birthday=${birthday}`);
        const data = await response.json();

        if (data.success) {
            currentStudent = data.data;
            displayResult(data.data);
        } else {
            showError(data.message || 'Không tìm thấy hồ sơ');
        }
    } catch (error) {
        console.error('Error:', error);
        showError('Có lỗi xảy ra, vui lòng thử lại sau');
    } finally {
        document.getElementById('searchLoading').classList.remove('active');
        document.getElementById('searchBtn').disabled = false;
    }
});

function displayResult(data) {
    const resultSection = document.getElementById('resultSection');
    resultSection.style.display = 'block';

    setTimeout(() => {
        resultSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 300);

    document.getElementById('regId').textContent = '#' + String(data.id).padStart(6, '0');
    document.getElementById('studentName').textContent = data.fullname;

    const statusBadge = document.getElementById('statusBadge');
    statusBadge.className = 'status-badge-modern ' + data.status_class;
    statusBadge.innerHTML = `<i class="fas ${data.status_icon}"></i> ${data.status_text}`;

    const basicInfo = document.getElementById('basicInfo');
    basicInfo.innerHTML = `
        <div class="info-card-modern">
            <div class="info-icon"><i class="fas fa-id-card"></i></div>
            <div class="info-label">CCCD/CMND</div>
            <div class="info-value">${data.identification}</div>
        </div>
        <div class="info-card-modern">
            <div class="info-icon"><i class="fas fa-calendar-alt"></i></div>
            <div class="info-label">Ngày sinh</div>
            <div class="info-value">${formatDate(data.birthday)}</div>
        </div>
        <div class="info-card-modern">
            <div class="info-icon"><i class="fas fa-phone"></i></div>
            <div class="info-label">Số điện thoại</div>
            <div class="info-value">${data.phone}</div>
        </div>
        <div class="info-card-modern">
            <div class="info-icon"><i class="fas fa-envelope"></i></div>
            <div class="info-label">Email</div>
            <div class="info-value">${data.email}</div>
        </div>
        <div class="info-card-modern">
            <div class="info-icon"><i class="fas fa-map-marker-alt"></i></div>
            <div class="info-label">Địa chỉ</div>
            <div class="info-value">${data.address || 'Chưa cập nhật'}</div>
        </div>
        <div class="info-card-modern">
            <div class="info-icon"><i class="fas fa-graduation-cap"></i></div>
            <div class="info-label">Trường THPT</div>
            <div class="info-value">${data.school || 'Chưa cập nhật'}</div>
        </div>
    `;

    const admissionInfo = document.getElementById('admissionInfo');
    admissionInfo.innerHTML = `
        <div class="info-card-modern">
            <div class="info-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="info-label">Năm tốt nghiệp</div>
            <div class="info-value">${data.graduation_year}</div>
        </div>
        <div class="info-card-modern">
            <div class="info-icon"><i class="fas fa-book-open"></i></div>
            <div class="info-label">Ngành đăng ký</div>
            <div class="info-value">${data.major_name} (${data.major_code})</div>
        </div>
        <div class="info-card-modern">
            <div class="info-icon"><i class="fas fa-layer-group"></i></div>
            <div class="info-label">Phương thức</div>
            <div class="info-value">${data.method_name}</div>
        </div>
        <div class="info-card-modern">
            <div class="info-icon"><i class="fas fa-puzzle-piece"></i></div>
            <div class="info-label">Tổ hợp môn</div>
            <div class="info-value">${data.combination_name || 'Không áp dụng'}</div>
        </div>
        <div class="info-card-modern">
            <div class="info-icon"><i class="fas fa-star"></i></div>
            <div class="info-label">Điểm ưu tiên</div>
            <div class="info-value">${data.priority_score || '0'}</div>
        </div>
        <div class="info-card-modern">
            <div class="info-icon"><i class="fas fa-clock"></i></div>
            <div class="info-label">Ngày đăng ký</div>
            <div class="info-value">${formatDate(data.created_at)}</div>
        </div>
    `;

    if (data.scores && data.scores.length > 0) {
        displayScores(data.scores);
    }

    displayAdmissionResult(data);

    if (data.documents && data.documents.length > 0) {
        displayDocuments(data.documents);
    }

    if (data.timeline && data.timeline.length > 0) {
        displayTimeline(data.timeline);
    }
}

function displayScores(scores) {
    const scoreSection = document.getElementById('scoreSection');
    const scoreGrid = document.getElementById('scoreGrid');
    scoreSection.style.display = 'block';

    let html = '';
    let total = 0;
    scores.forEach(score => {
        html += `
            <div class="score-item">
                <span class="score-subject">${score.subject}</span>
                <span class="score-value">${score.score}</span>
            </div>
        `;
        total += parseFloat(score.score);
    });
    html += `
        <div class="score-total">
            <span>Tổng điểm</span>
            <span>${total.toFixed(2)}</span>
        </div>
    `;
    scoreGrid.innerHTML = html;
}

function displayAdmissionResult(data) {
    const admissionResult = document.getElementById('admissionResult');
    if (!data.admission_result) {
        admissionResult.innerHTML = '';
        return;
    }

    const result = data.admission_result;
    console.log('Admission result:', result);

    if (result.status === 'admitted') {
        if (result.confirmation) {
            if (result.confirmation.status === 'confirmed') {
                admissionResult.innerHTML = `
                    <div class="confirmed-box">
                        <i class="fas fa-check-circle"></i>
                        <h4>Đã xác nhận nhập học</h4>
                        <p>Bạn đã hoàn tất xác nhận nhập học lúc ${formatDateTime(result.confirmation.confirmed_at)}</p>
                        <p style="margin-top: 10px;">Vui lòng chờ hướng dẫn nhập học từ nhà trường.</p>
                    </div>
                `;
            } else if (result.confirmation.status === 'expired') {
                admissionResult.innerHTML = `
                    <div class="expired-box">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h4>Hết hạn xác nhận</h4>
                        <p>Thời hạn xác nhận nhập học đã kết thúc.</p>
                        <p style="margin-top: 10px;">Vui lòng liên hệ phòng tuyển sinh để được hỗ trợ.</p>
                    </div>
                `;
            }
        } else {
            const expiryDate = new Date();
            expiryDate.setDate(expiryDate.getDate() + 7);
            admissionResult.innerHTML = `
                <div class="confirmation-box">
                    <h4>Xác nhận nhập học</h4>
                    <p>Bạn cần xác nhận nhập học trước thời hạn:</p>
                    <div class="countdown-timer" id="countdownDisplay"></div>
                    <div class="warning-box">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>Sau thời gian trên, nếu không xác nhận, kết quả trúng tuyển sẽ bị hủy bỏ</div>
                    </div>
                    <button class="btn-confirm-admission" onclick="showConfirmModal()">
                        <i class="fas fa-check-circle"></i> Xác nhận nhập học ngay
                    </button>
                </div>
            `;
            startCountdown(expiryDate);
        }
    } else if (result.status === 'rejected') {
        admissionResult.innerHTML = `
            <div class="expired-box">
                <i class="fas fa-frown"></i>
                <h4>Rất tiếc</h4>
                <p>Điểm của bạn không đủ để trúng tuyển vào ngành đã chọn.</p>
                <p style="margin-top: 10px;">Vui lòng tham khảo các ngành đào tạo khác hoặc đợt xét tuyển tiếp theo.</p>
            </div>
        `;
    } else {
        admissionResult.innerHTML = `
            <div class="confirmation-box">
                <i class="fas fa-hourglass-half"></i>
                <h4>Đang chờ kết quả</h4>
                <p>Kết quả xét tuyển của bạn đang được xử lý.</p>
                <p style="margin-top: 10px;">Vui lòng quay lại sau để kiểm tra kết quả.</p>
            </div>
        `;
    }
}

function startCountdown(expiryDate) {
    if (countdownInterval) clearInterval(countdownInterval);
    const countdownDisplay = document.getElementById('countdownDisplay');
    countdownInterval = setInterval(function() {
        const now = new Date().getTime();
        const distance = expiryDate - now;
        if (distance < 0) {
            clearInterval(countdownInterval);
            countdownDisplay.innerHTML = 'ĐÃ HẾT HẠN';
            location.reload();
            return;
        }
        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
        countdownDisplay.innerHTML = `${String(days).padStart(2,'0')}:${String(hours).padStart(2,'0')}:${String(minutes).padStart(2,'0')}:${String(seconds).padStart(2,'0')}`;
    }, 1000);
}

function showConfirmModal() {
    if (!currentStudent) return;
    document.getElementById('confirmStudentName').textContent = currentStudent.fullname;
    document.getElementById('confirmStudentMajor').textContent = 'Ngành: ' + currentStudent.major_name + ' (' + currentStudent.major_code + ')';
    document.getElementById('confirmStudentScore').textContent = 'Tổng điểm: ' + (currentStudent.scores ? currentStudent.scores.reduce((sum, s) => sum + parseFloat(s.score), 0).toFixed(2) : 'Chưa có');
    document.getElementById('confirmModal').classList.add('show');
}

function hideModal() {
    document.getElementById('confirmModal').classList.remove('show');
}

async function processConfirmation() {
    if (!currentStudent) return;
    hideModal();
    const loading = document.createElement('div');
    loading.className = 'search-loading active';
    loading.innerHTML = '<div class="spinner"></div><p>Đang xử lý xác nhận...</p>';
    document.body.appendChild(loading);
    try {
        const response = await fetch('api/xac-nhan-nhap-hoc.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ registration_id: currentStudent.id })
        });
        const data = await response.json();
        loading.remove();
        if (data.success) {
            alert('Xác nhận nhập học thành công!');
            window.location.href = 'xac-nhan-thanh-cong.php?id=' + currentStudent.id;
        } else {
            alert('Có lỗi xảy ra: ' + data.message);
        }
    } catch (error) {
        loading.remove();
        alert('Có lỗi xảy ra, vui lòng thử lại sau');
    }
}

function displayDocuments(documents) {
    const documentsList = document.getElementById('documentsList');
    const documentsContainer = document.getElementById('documentsContainer');
    documentsList.style.display = 'block';
    let html = '';
    documents.forEach(doc => {
        const icon = doc.type === 'pdf' ? 'fa-file-pdf' : 'fa-file-image';
        html += `
            <div class="document-card">
                <div class="document-icon"><i class="fas ${icon}"></i></div>
                <div class="document-info">
                    <div class="document-name">${doc.name}</div>
                    <div class="document-size">${doc.size}</div>
                </div>
                <button class="btn-download" onclick="downloadFile('${doc.file}')"><i class="fas fa-download"></i></button>
            </div>
        `;
    });
    documentsContainer.innerHTML = html;
}

function displayTimeline(timeline) {
    const timelineEl = document.getElementById('timeline');
    const timelineContainer = document.getElementById('timelineContainer');
    timelineEl.style.display = 'block';
    let html = '';
    timeline.forEach(item => {
        html += `
            <div class="timeline-item-modern">
                <div class="timeline-date"><i class="far fa-clock"></i> ${item.date}</div>
                <div class="timeline-content"><h4>${item.title}</h4><p>${item.description}</p></div>
            </div>
        `;
    });
    timelineContainer.innerHTML = html;
}

function showError(message) {
    const resultSection = document.getElementById('resultSection');
    resultSection.style.display = 'block';
    resultSection.innerHTML = `
        <div class="container">
            <div class="error-message-modern">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Không tìm thấy hồ sơ</h3>
                <p>${message || 'Vui lòng kiểm tra lại CCCD/CMND và ngày sinh'}</p>
                <button class="btn-retry" onclick="location.reload()"><i class="fas fa-redo"></i> Thử lại</button>
            </div>
        </div>
    `;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return `${date.getDate().toString().padStart(2,'0')}/${(date.getMonth()+1).toString().padStart(2,'0')}/${date.getFullYear()}`;
}

function formatDateTime(dateString) {
    const date = new Date(dateString);
    return `${date.getDate().toString().padStart(2,'0')}/${(date.getMonth()+1).toString().padStart(2,'0')}/${date.getFullYear()} ${date.getHours().toString().padStart(2,'0')}:${date.getMinutes().toString().padStart(2,'0')}`;
}

function downloadFile(file) {
    const regId = document.getElementById('regId').textContent;
    window.location.href = `php/download.php?file=${file}&id=${regId}`;
}

function downloadProfile() {
    const regId = document.getElementById('regId').textContent.replace('#', '');
    window.open(`php/export-pdf.php?id=${regId}`, '_blank');
}

function contactAdmission() {
    const regId = document.getElementById('regId').textContent;
    window.location.href = 'lien-he.php?subject=Tra cứu hồ sơ - ' + regId;
}
</script>

<!-- Modal Xác nhận -->
<div class="modal" id="confirmModal">
    <div class="modal-content">
        <div class="modal-header">
            <i class="fas fa-check-circle"></i>
            <h3>Xác nhận nhập học</h3>
            <button class="modal-close" onclick="hideModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="info-box" style="background:#f8f9fa; border-radius:12px; padding:15px; margin-bottom:20px;">
                <strong>Thông tin thí sinh:</strong>
                <p id="confirmStudentName" style="margin:5px 0"></p>
                <p id="confirmStudentMajor" style="margin:5px 0"></p>
                <p id="confirmStudentScore" style="margin:5px 0"></p>
            </div>
            <div class="countdown-timer" id="countdownTimer" style="text-align:center; font-size:1.2rem;"></div>
            <div class="warning-box" style="margin-top:20px;">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Lưu ý quan trọng:</strong>
                    <ul style="margin-top:5px; padding-left:20px;">
                        <li>Thời hạn xác nhận: <strong>7 ngày</strong> kể từ khi nhận được kết quả</li>
                        <li>Sau thời gian trên, nếu không xác nhận, kết quả trúng tuyển sẽ bị <strong>hủy bỏ</strong></li>
                        <li>Mỗi thí sinh chỉ được xác nhận nhập học <strong>một lần duy nhất</strong></li>
                        <li>Việc xác nhận nhập học đồng nghĩa với cam kết sẽ theo học tại trường</li>
                    </ul>
                </div>
            </div>
            <p style="text-align:center; margin:20px 0;">Bạn có chắc chắn muốn xác nhận nhập học?</p>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="hideModal()">Hủy bỏ</button>
            <button class="btn-confirm" onclick="processConfirmation()">Xác nhận nhập học</button>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>