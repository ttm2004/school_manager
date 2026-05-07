<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
$pageTitle = 'Liên hệ';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_contact'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($full_name) || empty($email) || empty($message)) {
        $error = 'Vui lòng điền đầy đủ các trường bắt buộc.';
    } else {
        $stmt = $conn->prepare("INSERT INTO contacts (full_name, email, phone, subject, message, status, created_at) VALUES (?,?,?,?,?,'new',NOW())");
        $stmt->bind_param('sssss', $full_name, $email, $phone, $subject, $message);
        if ($stmt->execute()) {
            $success = 'Cảm ơn bạn đã liên hệ! Chúng tôi sẽ phản hồi trong thời gian sớm nhất.';
        } else {
            $error = 'Có lỗi xảy ra. Vui lòng thử lại sau.';
        }
        $stmt->close();
    }
}

include 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="/university/index.php">Trang chủ</a></li>
                <li class="breadcrumb-item active">Liên hệ</li>
            </ol>
        </nav>
        <h1><i class="bi bi-envelope me-2"></i>Liên hệ với chúng tôi</h1>
        <p class="text-white-50 mb-0">Chúng tôi luôn sẵn sàng hỗ trợ bạn</p>
    </div>
</div>

<section class="py-5">
    <div class="container">
        <div class="row g-5">
            <!-- Contact Info -->
            <div class="col-lg-4">
                <div class="contact-info-card mb-4">
                    <h5 class="text-gold fw-bold mb-4">
                        <i class="bi bi-info-circle me-2"></i>Thông tin liên hệ
                    </h5>
                    <div class="contact-info-item">
                        <div class="contact-info-icon">
                            <i class="bi bi-geo-alt-fill"></i>
                        </div>
                        <div>
                            <div class="fw-bold small mb-1">Địa chỉ</div>
                            <div class="text-white-50 small">6 Trần Văn Ơn, Phú Hòa, Thủ Dầu Một, Bình Dương</div>
                        </div>
                    </div>
                    <div class="contact-info-item">
                        <div class="contact-info-icon">
                            <i class="bi bi-telephone-fill"></i>
                        </div>
                        <div>
                            <div class="fw-bold small mb-1">Điện thoại</div>
                            <div class="text-white-50 small">(0274) 3 822 518</div>
                            <div class="text-white-50 small">(0274) 3 822 519</div>
                        </div>
                    </div>
                    <div class="contact-info-item">
                        <div class="contact-info-icon">
                            <i class="bi bi-envelope-fill"></i>
                        </div>
                        <div>
                            <div class="fw-bold small mb-1">Email</div>
                            <div class="text-white-50 small">info@tdmu.edu.vn</div>
                            <div class="text-white-50 small">tuyensinh@tdmu.edu.vn</div>
                        </div>
                    </div>
                    <div class="contact-info-item">
                        <div class="contact-info-icon">
                            <i class="bi bi-clock-fill"></i>
                        </div>
                        <div>
                            <div class="fw-bold small mb-1">Giờ làm việc</div>
                            <div class="text-white-50 small">Thứ 2 - Thứ 6: 7:30 - 17:00</div>
                            <div class="text-white-50 small">Thứ 7: 7:30 - 11:30</div>
                        </div>
                    </div>
                </div>

                <!-- Social Links -->
                <div class="card p-4">
                    <h6 class="fw-bold text-navy mb-3">Mạng xã hội</h6>
                    <div class="d-flex gap-3">
                        <a href="#" class="btn btn-navy btn-sm">
                            <i class="bi bi-facebook me-1"></i>Facebook
                        </a>
                        <a href="#" class="btn btn-outline-navy btn-sm">
                            <i class="bi bi-youtube me-1"></i>YouTube
                        </a>
                    </div>
                </div>
            </div>

            <!-- Contact Form -->
            <div class="col-lg-8">
                <div class="card shadow-custom">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-chat-dots me-2"></i>Gửi tin nhắn cho chúng tôi</h5>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($success): ?>
                        <div class="alert alert-success auto-dismiss alert-dismissible fade show">
                            <i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                        <div class="alert alert-danger auto-dismiss alert-dismissible fade show">
                            <i class="bi bi-exclamation-circle-fill me-2"></i><?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <input type="hidden" name="send_contact" value="1">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Họ và tên <span class="text-danger">*</span></label>
                                    <input type="text" name="full_name" class="form-control" required value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" placeholder="Nguyễn Văn A">
                                    <div class="invalid-feedback">Vui lòng nhập họ và tên.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="email@example.com">
                                    <div class="invalid-feedback">Vui lòng nhập email hợp lệ.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Số điện thoại</label>
                                    <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" placeholder="0901234567">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Chủ đề</label>
                                    <input type="text" name="subject" class="form-control" value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" placeholder="Tư vấn tuyển sinh, hỗ trợ...">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Nội dung <span class="text-danger">*</span></label>
                                    <textarea name="message" class="form-control" rows="6" required placeholder="Nhập nội dung tin nhắn của bạn..."><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                                    <div class="invalid-feedback">Vui lòng nhập nội dung tin nhắn.</div>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-gold btn-lg px-5">
                                        <i class="bi bi-send-fill me-2"></i>Gửi tin nhắn
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Map placeholder -->
                <div class="card mt-4">
                    <div class="card-body p-0">
                        <div style="background:linear-gradient(135deg,#e8f0fe,#f0f4ff);height:250px;display:flex;align-items:center;justify-content:center;border-radius:var(--radius);">
                            <div class="text-center text-muted">
                                <i class="bi bi-map-fill text-navy" style="font-size:3rem;"></i>
                                <div class="mt-2 fw-bold text-navy">Bản đồ vị trí</div>
                                <div class="small">6 Trần Văn Ơn, Phú Hòa, Thủ Dầu Một, Bình Dương</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
