<?php
require_once 'php/config.php';

$registration_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Lấy thông tin xác nhận
$sql = "SELECT ac.*, r.birthday, r.identification
        FROM admission_confirmation ac
        JOIN registrations r ON ac.registration_id = r.id
        WHERE ac.registration_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $registration_id);
$stmt->execute();
$result = $stmt->get_result();
$confirmation = $result->fetch_assoc();

if (!$confirmation) {
    header('Location: tra-cuu.php');
    exit();
}

$page_title = "Xác nhận nhập học thành công";
require_once '../includes/header.php';
?>

<style>
    .success-page {
        min-height: 100vh;
        background: linear-gradient(135deg, #667eea10, #764ba210);
        padding: 60px 0;
    }
    
    .success-card {
        background: white;
        border-radius: 30px;
        padding: 50px;
        max-width: 800px;
        margin: 0 auto;
        box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        text-align: center;
    }
    
    .success-icon {
        width: 120px;
        height: 120px;
        background: linear-gradient(135deg, #4cc9f0, #4895ef);
        border-radius: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 30px;
        font-size: 60px;
        color: white;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    
    .success-card h1 {
        font-size: 32px;
        font-weight: 700;
        color: #1a1a2e;
        margin-bottom: 15px;
    }
    
    .success-message {
        color: #6c757d;
        font-size: 16px;
        margin-bottom: 30px;
        line-height: 1.6;
    }
    
    .info-panel {
        background: #f8f9fa;
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 30px;
        text-align: left;
    }
    
    .info-panel h3 {
        font-size: 20px;
        font-weight: 600;
        color: #1a1a2e;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .info-panel h3 i {
        color: #667eea;
    }
    
    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid #e9ecef;
    }
    
    .info-row:last-child {
        border-bottom: none;
    }
    
    .info-label {
        color: #6c757d;
        font-size: 14px;
    }
    
    .info-value {
        font-weight: 600;
        color: #1a1a2e;
    }
    
    .guidelines {
        background: #e8f4ff;
        border-radius: 16px;
        padding: 20px;
        margin-bottom: 30px;
        text-align: left;
    }
    
    .guidelines h4 {
        color: #1a1a2e;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .guidelines ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .guidelines li {
        padding: 8px 0;
        padding-left: 25px;
        position: relative;
        color: #2c3e50;
    }
    
    .guidelines li:before {
        content: '✓';
        position: absolute;
        left: 0;
        color: #4cc9f0;
        font-weight: 600;
    }
    
    .btn-actions {
        display: flex;
        gap: 15px;
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .btn-primary, .btn-secondary {
        padding: 12px 30px;
        border: none;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
        transition: all 0.3s ease;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
    }
    
    .btn-secondary {
        background: white;
        color: #1a1a2e;
        border: 2px solid #e9ecef;
    }
    
    .btn-primary:hover, .btn-secondary:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    
    .contact-info {
        margin-top: 30px;
        padding-top: 30px;
        border-top: 1px solid #e9ecef;
        display: flex;
        justify-content: center;
        gap: 40px;
        flex-wrap: wrap;
    }
    
    .contact-item {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #6c757d;
    }
    
    .contact-item i {
        color: #667eea;
    }
    
    @media (max-width: 768px) {
        .success-card {
            padding: 30px 20px;
        }
        
        .success-card h1 {
            font-size: 28px;
        }
        
        .btn-actions {
            flex-direction: column;
        }
        
        .contact-info {
            flex-direction: column;
            gap: 15px;
            align-items: center;
        }
    }
</style>

<div class="success-page">
    <div class="container">
        <div class="success-card">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            
            <h1>Xác nhận nhập học thành công!</h1>
            <p class="success-message">
                Chúc mừng bạn đã hoàn tất thủ tục xác nhận nhập học tại trường. 
                Dưới đây là thông tin chi tiết về đợt nhập học của bạn.
            </p>
            
            <div class="info-panel">
                <h3>
                    <i class="fas fa-user-graduate"></i>
                    Thông tin thí sinh
                </h3>
                
                <div class="info-row">
                    <span class="info-label">Mã hồ sơ</span>
                    <span class="info-value">#<?php echo str_pad($registration_id, 6, '0', STR_PAD_LEFT); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Họ và tên</span>
                    <span class="info-value"><?php echo $confirmation['fullname']; ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Số CCCD/CMND</span>
                    <span class="info-value"><?php echo $confirmation['identification']; ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Ngày sinh</span>
                    <span class="info-value"><?php echo date('d/m/Y', strtotime($confirmation['birthday'])); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Số điện thoại</span>
                    <span class="info-value"><?php echo $confirmation['phone']; ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?php echo $confirmation['email']; ?></span>
                </div>
            </div>
            
            <div class="info-panel">
                <h3>
                    <i class="fas fa-graduation-cap"></i>
                    Thông tin trúng tuyển
                </h3>
                
                <div class="info-row">
                    <span class="info-label">Ngành trúng tuyển</span>
                    <span class="info-value"><?php echo $confirmation['major_name']; ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Tổng điểm</span>
                    <span class="info-value"><?php echo number_format($confirmation['total_score'], 2); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Phương thức xét tuyển</span>
                    <span class="info-value"><?php echo $confirmation['method_name']; ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Thời gian xác nhận</span>
                    <span class="info-value"><?php echo date('H:i d/m/Y', strtotime($confirmation['confirmed_at'])); ?></span>
                </div>
            </div>
            
            <div class="guidelines">
                <h4>
                    <i class="fas fa-clipboard-list"></i>
                    Hướng dẫn tiếp theo
                </h4>
                <ul>
                    <li>Vui lòng chuẩn bị hồ sơ nhập học theo thông báo</li>
                    <li>Thời gian nhập học dự kiến: Từ 15/09/2024 đến 30/09/2024</li>
                    <li>Mang theo giấy tờ tùy thân và giấy báo trúng tuyển (bản gốc)</li>
                    <li>Đóng học phí theo quy định của nhà trường</li>
                    <li>Tham gia tuần sinh hoạt công dân đầu khóa</li>
                </ul>
            </div>
            
            <div class="btn-actions">
                <a href="tra-cuu.php" class="btn-primary">
                    <i class="fas fa-search"></i>
                    Tra cứu lại
                </a>
                <a href="in-giay-bao.php?id=<?php echo $registration_id; ?>" class="btn-secondary" target="_blank">
                    <i class="fas fa-print"></i>
                    In giấy báo
                </a>
            </div>
            
            <div class="contact-info">
                <div class="contact-item">
                    <i class="fas fa-phone-alt"></i>
                    1900 8198
                </div>
                <div class="contact-item">
                    <i class="fas fa-envelope"></i>
                    tuyensinh@hus.edu.vn
                </div>
                <div class="contact-item">
                    <i class="fas fa-map-marker-alt"></i>
                    334 Nguyễn Trãi, Thanh Xuân, Hà Nội
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>