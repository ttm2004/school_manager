<?php
require_once 'config/db.php';
include 'includes/header.php';

// Kiểm tra nếu chưa đăng nhập thì đẩy về trang login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success_msg = $error_msg = "";

// XỬ LÝ CẬP NHẬT KHI SUBMIT FORM
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $new_pass = $_POST['new_password'];

    try {
        // 1. Cập nhật thông tin cơ bản
        $sql = "UPDATE users SET full_name = ?, email = ?, phone = ?, address = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$full_name, $email, $phone, $address, $user_id]);
        $_SESSION['full_name'] = $full_name; // Cập nhật lại tên hiển thị trên header

        // 2. Xử lý đổi mật khẩu nếu có nhập (Lưu ý: Bạn nên dùng password_hash nếu muốn bảo mật cao hơn)
        if (!empty($new_pass)) {
            $update_pass = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_pass->execute([$new_pass, $user_id]);
        }

        // 3. Xử lý Upload ảnh đại diện
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
            $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $file_name = time() . "_" . $user_id . "." . $ext;
            $target = "uploads/avatars/" . $file_name;

            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $target)) {
                $update_avt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $update_avt->execute([$file_name, $user_id]);
            }
        }

        $success_msg = "Cập nhật hồ sơ thành công!";
    } catch (PDOException $e) {
        $error_msg = "Có lỗi xảy ra: " . $e->getMessage();
    }
}

// LẤY DỮ LIỆU HIỆN TẠI CỦA NGƯỜI DÙNG
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Đường dẫn ảnh đại diện
$avatar_url = !empty($user['avatar']) ? "uploads/avatars/" . $user['avatar'] : "https://ui-avatars.com/api/?name=" . urlencode($user['full_name']);
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-user-edit me-2"></i>Chỉnh sửa hồ sơ cá nhân</h5>
                </div>
                <div class="card-body p-4 p-md-5">
                    <?php if($success_msg) echo "<div class='alert alert-success'>$success_msg</div>"; ?>
                    <?php if($error_msg) echo "<div class='alert alert-danger'>$error_msg</div>"; ?>

                    <form action="" method="POST" enctype="multipart/form-data">
                        <div class="row g-4">
                            <div class="col-md-4 text-center border-end">
                                <div class="mb-3">
                                    <img src="<?= $avatar_url ?>" id="imgPreview" class="rounded-circle img-thumbnail shadow-sm" style="width: 150px; height: 150px; object-fit: cover;">
                                </div>
                                <div class="mb-3">
                                    <label for="avatar" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                        <i class="fas fa-camera me-1"></i> Thay ảnh
                                    </label>
                                    <input type="file" name="avatar" id="avatar" class="d-none" onchange="previewImage(this)">
                                </div>
                                <div class="badge bg-secondary rounded-pill px-3 py-2 mt-2">
                                     <?= strtoupper($user['role']) ?>
                                </div>
                            </div>

                            <div class="col-md-8">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Tên đăng nhập</label>
                                        <input type="text" class="form-control bg-light" value="<?= $user['username'] ?>" readonly disabled>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Họ và tên</label>
                                        <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Email</label>
                                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Số điện thoại</label>
                                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold">Địa chỉ</label>
                                        <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($user['address']) ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold text-danger">Đổi mật khẩu (Bỏ trống nếu không muốn đổi)</label>
                                        <input type="password" name="new_password" class="form-control" placeholder="Nhập mật khẩu mới">
                                    </div>
                                    <div class="col-12 mt-4">
                                        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow">
                                            Lưu thay đổi
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Hàm xem trước ảnh ngay lập tức khi chọn file
    function previewImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('imgPreview').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>

<?php include 'includes/footer.php'; ?>