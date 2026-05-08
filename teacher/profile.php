<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('teacher');

$stmt = $conn->prepare("SELECT t.*, u.full_name, u.email, u.phone, u.address, u.username, f.faculty_name FROM teachers t JOIN users u ON t.user_id=u.id JOIN faculties f ON t.faculty_id=f.id WHERE t.user_id=?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $old_pw  = trim($_POST['old_password'] ?? '');
    $new_pw  = trim($_POST['new_password'] ?? '');
    $stmt = $conn->prepare("UPDATE users SET phone=?, address=? WHERE id=?");
    $stmt->bind_param('ssi', $phone, $address, $_SESSION['user_id']);
    $stmt->execute() ? $success = 'Cập nhật thành công!' : $error = 'Lỗi: ' . $conn->error;
    $stmt->close();
    if ($old_pw && $new_pw) {
        $chk = $conn->prepare("SELECT password FROM users WHERE id=?");
        $chk->bind_param('i', $_SESSION['user_id']);
        $chk->execute();
        $curPw = $chk->get_result()->fetch_assoc()['password'];
        $chk->close();
        if ($curPw === $old_pw) {
            $upd = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $upd->bind_param('si', $new_pw, $_SESSION['user_id']);
            $upd->execute() ? $success .= ' Đổi mật khẩu thành công!' : $error .= ' Lỗi đổi mật khẩu.';
            $upd->close();
        } else { $error .= ' Mật khẩu cũ không đúng.'; }
    }
    $teacher['phone'] = $phone;
    $teacher['address'] = $address;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ho so - Giang vien</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/university/assets/css/style.css">
</head>
<body>
<div class="student-wrapper">
    <div class="student-sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon"><i class="bi bi-person-badge-fill"></i></div>
            <div class="sidebar-brand-text"><div>Cong Giang vien</div><small><?php echo htmlspecialchars($teacher['teacher_code']); ?></small></div>
        </div>
        <nav class="sidebar-nav">
            <a href="/university/teacher/index.php" class="sidebar-link"><i class="bi bi-speedometer2"></i> Tong quan</a>
            <a href="/university/teacher/profile.php" class="sidebar-link active"><i class="bi bi-person-fill"></i> Ho so ca nhan</a>
            <a href="/university/teacher/my_courses.php" class="sidebar-link"><i class="bi bi-journal-text"></i> Lop hoc phan</a>
            <a href="/university/teacher/grades.php" class="sidebar-link"><i class="bi bi-bar-chart-fill"></i> Nhap diem</a>
            <a href="/university/teacher/evaluation.php" class="sidebar-link"><i class="bi bi-star-fill"></i> Ket qua danh gia</a>
            <hr class="my-2">
            <a href="/university/index.php" class="sidebar-link"><i class="bi bi-globe"></i> Trang chu</a>
            <a href="/university/login.php?logout=1" class="sidebar-link text-danger"><i class="bi bi-box-arrow-right"></i> Dang xuat</a>
        </nav>
    </div>
    <div class="student-main">
        <div class="student-topbar">
            <span class="fw-bold text-navy">Ho so ca nhan</span>
            <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i></a>
        </div>
        <div class="student-content">
            <?php if ($success): ?><div class="alert alert-success auto-dismiss alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger auto-dismiss alert-dismissible fade show"><i class="bi bi-exclamation-circle-fill me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card text-center">
                        <div class="card-body py-4">
                            <div class="avatar-circle-lg bg-success text-white mx-auto mb-3"><?php echo mb_substr($teacher['full_name'],0,1); ?></div>
                            <h5 class="fw-bold"><?php echo htmlspecialchars($teacher['full_name']); ?></h5>
                            <div class="text-muted small mb-2"><?php echo htmlspecialchars($teacher['teacher_code']); ?></div>
                            <span class="badge bg-success"><?php echo htmlspecialchars($teacher['degree']); ?></span>
                            <hr>
                            <div class="text-start">
                                <div class="mb-2"><small class="text-muted">Khoa:</small><div class="fw-bold small"><?php echo htmlspecialchars($teacher['faculty_name']); ?></div></div>
                                <div class="mb-2"><small class="text-muted">Chuyen nganh:</small><div class="fw-bold small"><?php echo htmlspecialchars($teacher['specialization']); ?></div></div>
                                <div><small class="text-muted">Email:</small><div class="fw-bold small"><?php echo htmlspecialchars($teacher['email']); ?></div></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header"><i class="bi bi-pencil-square me-2"></i>Cap nhat thong tin</div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row g-3">
                                    <div class="col-md-6"><label class="form-label">Ho va ten</label><input type="text" class="form-control" value="<?php echo htmlspecialchars($teacher['full_name']); ?>" readonly></div>
                                    <div class="col-md-6"><label class="form-label">Ten dang nhap</label><input type="text" class="form-control" value="<?php echo htmlspecialchars($teacher['username']); ?>" readonly></div>
                                    <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" value="<?php echo htmlspecialchars($teacher['email']); ?>" readonly></div>
                                    <div class="col-md-6"><label class="form-label">So dien thoai</label><input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($teacher['phone'] ?? ''); ?>"></div>
                                    <div class="col-12"><label class="form-label">Dia chi</label><input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($teacher['address'] ?? ''); ?>"></div>
                                    <div class="col-12"><hr><h6 class="text-navy">Doi mat khau</h6></div>
                                    <div class="col-md-6"><label class="form-label">Mat khau cu</label><input type="password" name="old_password" class="form-control"></div>
                                    <div class="col-md-6"><label class="form-label">Mat khau moi</label><input type="password" name="new_password" class="form-control"></div>
                                    <div class="col-12"><button type="submit" class="btn btn-navy"><i class="bi bi-save me-1"></i>Luu thay doi</button></div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="student-footer">&copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một</div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<?php include_once __DIR__ . "/../includes/analytics_widget.php"; ?>
</body>
</html>
