<?php
include 'includes/header.php';
?>

<div class="container py-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Trang chủ</a></li>
            <li class="breadcrumb-item active">Liên hệ</li>
        </ol>
    </nav>

    <div class="text-center mb-5">
        <h2 class="fw-bold text-uppercase display-6">Kết nối với chúng tôi</h2>
        <div class="mx-auto bg-warning" style="height: 3px; width: 60px;"></div>
        <p class="text-muted mt-3">Mọi thắc mắc của bạn sẽ được đội ngũ hỗ trợ phản hồi trong vòng 24 giờ.</p>
    </div>

    <div class="row g-5">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-primary text-white">
                <h4 class="fw-bold mb-4">Thông tin chi tiết</h4>
                
                <div class="d-flex mb-4">
                    <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px; flex-shrink:0;">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div>
                        <h6 class="mb-1">Địa chỉ</h6>
                        <p class="small mb-0 opacity-75">123 Đường Công Nghệ, Quận Cầu Giấy, TP. Hà Nội</p>
                    </div>
                </div>

                <div class="d-flex mb-4">
                    <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px; flex-shrink:0;">
                        <i class="fas fa-phone-alt"></i>
                    </div>
                    <div>
                        <h6 class="mb-1">Điện thoại</h6>
                        <p class="small mb-0 opacity-75">090.123.4567 - 024.333.888</p>
                    </div>
                </div>

                <div class="d-flex mb-4">
                    <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px; flex-shrink:0;">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div>
                        <h6 class="mb-1">Email</h6>
                        <p class="small mb-0 opacity-75">contact@edutech2026.edu.vn</p>
                    </div>
                </div>

                <div class="mt-auto">
                    <h6 class="mb-3">Theo dõi chúng tôi</h6>
                    <div class="d-flex gap-2">
                        <a href="#" class="btn btn-sm btn-light rounded-circle"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="btn btn-sm btn-light rounded-circle"><i class="fab fa-youtube"></i></a>
                        <a href="#" class="btn btn-sm btn-light rounded-circle"><i class="fab fa-tiktok"></i></a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 p-4">
                <form action="#" method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Họ và tên</label>
                            <input type="text" class="form-control" placeholder="Nguyễn Văn A" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Email</label>
                            <input type="email" class="form-control" placeholder="email@example.com" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Chủ đề</label>
                            <input type="text" class="form-control" placeholder="Hỗ trợ đăng nhập, tư vấn học tập...">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Nội dung tin nhắn</label>
                            <textarea class="form-control" rows="5" placeholder="Nhập tin nhắn của bạn tại đây..." required></textarea>
                        </div>
                        <div class="col-12 mt-4 text-end">
                            <button type="submit" class="btn btn-primary rounded-pill px-5 py-2 fw-bold shadow-sm">
                                <i class="fas fa-paper-plane me-2"></i> Gửi tin nhắn
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="mt-5 rounded-4 overflow-hidden shadow-sm border" style="height: 400px;">
        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3723.863981044385!2d105.7801038758652!3d21.038127787457782!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3135ab354920c233%3A0x5d06077519391a4!2zRGhxbmdoLCBD4bqndSBHaeG6pXksIEjDoCBO4buZaSwgVmnhu4d0IE5hbQ!5e0!3m2!1svi!2s!4v1700000000000!5m2!1svi!2s" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
    </div>
</div>

<?php include 'includes/footer.php'; ?>