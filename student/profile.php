<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('student');

$stmt = $conn->prepare("
    SELECT s.*, u.full_name, u.email, u.phone, u.address as u_address, u.username, u.avatar,
           c.class_name, c.school_year, m.major_name, f.faculty_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN majors m ON c.major_id = m.id
    LEFT JOIN faculties f ON m.faculty_id = f.id
    WHERE s.user_id = ?
");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $old_pw  = trim($_POST['old_password'] ?? '');
    $new_pw  = trim($_POST['new_password'] ?? '');
    $avatar  = $student['avatar'] ?? '';

    // Xử lý upload ảnh đại diện
    if (!empty($_FILES['avatar']['name'])) {
        $file     = $_FILES['avatar'];
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed  = ['jpg','jpeg','png','gif','webp'];
        $maxSize  = 2 * 1024 * 1024; // 2MB

        if (!in_array($ext, $allowed)) {
            $error = 'Chỉ chấp nhận file ảnh: JPG, PNG, GIF, WEBP.';
        } elseif ($file['size'] > $maxSize) {
            $error = 'Ảnh không được vượt quá 2MB.';
        } else {
            $uploadDir = '../assets/uploads/avatars/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            // Xóa ảnh cũ nếu có
            if (!empty($student['avatar']) && file_exists('../' . $student['avatar'])) {
                unlink('../' . $student['avatar']);
            }

            $newName  = 'sv_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
            $destPath = $uploadDir . $newName;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $avatar = 'assets/uploads/avatars/' . $newName;
            } else {
                $error = 'Lỗi upload ảnh. Vui lòng thử lại.';
            }
        }
    }

    if (!$error) {
        // Cập nhật thông tin + avatar
        $stmt = $conn->prepare("UPDATE users SET phone=?, address=?, avatar=? WHERE id=?");
        $stmt->bind_param('sssi', $phone, $address, $avatar, $_SESSION['user_id']);
        if ($stmt->execute()) {
            $success = 'Cập nhật thông tin thành công!';
            $student['phone']     = $phone;
            $student['u_address'] = $address;
            $student['avatar']    = $avatar;
        } else {
            $error = 'Lỗi cập nhật: ' . $conn->error;
        }
        $stmt->close();

        // Đổi mật khẩu
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
            } else {
                $error .= ' Mật khẩu cũ không đúng.';
            }
        }
    }
}

$avatarUrl = !empty($student['avatar'])
    ? '/university/' . $student['avatar']
    : null;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ sơ cá nhân - Sinh viên</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/university/assets/css/style.css">
    <style>
        .avatar-wrap {
            position: relative;
            width: 110px;
            height: 110px;
            margin: 0 auto 16px;
        }
        .avatar-wrap img {
            width: 110px;
            height: 110px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid var(--navy);
        }
        .avatar-wrap .avatar-circle-lg {
            width: 110px;
            height: 110px;
            font-size: 2.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .avatar-edit-btn {
            position: absolute;
            bottom: 4px;
            right: 4px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--gold);
            border: 2px solid #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.8rem;
            color: #333;
        }
        #avatarPreview { display: none; }
    </style>
</head>
<body>
<div class="student-wrapper">
    <div class="student-sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon"><i class="bi bi-mortarboard-fill"></i></div>
            <div class="sidebar-brand-text">
                <div>Cổng Sinh viên</div>
                <small><?php echo htmlspecialchars($student['student_code']); ?></small>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="/university/student/index.php" class="sidebar-link"><i class="bi bi-speedometer2"></i> Tổng quan</a>
            <a href="/university/student/profile.php" class="sidebar-link active"><i class="bi bi-person-fill"></i> Hồ sơ cá nhân</a>
            <a href="/university/student/curriculum.php" class="sidebar-link"><i class="bi bi-journal-bookmark-fill"></i> Chương trình đào tạo</a>
            <a href="/university/student/register_subject.php" class="sidebar-link"><i class="bi bi-journal-plus"></i> Đăng ký học phần</a>
            <a href="/university/student/my_subjects.php" class="sidebar-link"><i class="bi bi-journal-check"></i> Học phần của tôi</a>
            <a href="/university/student/timetable.php" class="sidebar-link"><i class="bi bi-calendar3-week"></i> Thời khóa biểu</a>
            <a href="/university/student/exam_schedule.php" class="sidebar-link"><i class="bi bi-calendar-event-fill"></i> Lịch thi cuối kỳ</a>
            <a href="/university/student/grades.php" class="sidebar-link"><i class="bi bi-bar-chart-fill"></i> Kết quả học tập</a>
            <a href="/university/student/evaluation.php" class="sidebar-link"><i class="bi bi-star-fill"></i> Đánh giá giảng viên</a>
            <hr class="my-2">
            <a href="/university/index.php" class="sidebar-link"><i class="bi bi-globe"></i> Trang chủ</a>
            <a href="/university/login.php?logout=1" class="sidebar-link text-danger"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a>
        </nav>
    </div>

    <div class="student-main">
        <div class="student-topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary d-lg-none"
                        onclick="document.querySelector('.student-sidebar').classList.toggle('show')">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <span class="fw-bold text-navy"><i class="bi bi-person-fill me-2"></i>Hồ sơ cá nhân</span>
            </div>
            <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>

        <div class="student-content">
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

            <div class="row g-4">
                <!-- Card thông tin -->
                <div class="col-lg-4">
                    <div class="card text-center">
                        <div class="card-body py-4">
                            <!-- Avatar -->
                            <div class="avatar-wrap">
                                <?php if ($avatarUrl): ?>
                                <img src="<?php echo htmlspecialchars($avatarUrl); ?>" id="avatarImg" alt="Avatar">
                                <?php else: ?>
                                <div class="avatar-circle-lg bg-navy text-white" id="avatarCircle">
                                    <?php echo mb_strtoupper(mb_substr($student['full_name'], 0, 1)); ?>
                                </div>
                                <img src="" id="avatarImg" style="display:none;width:110px;height:110px;object-fit:cover;border-radius:50%;border:3px solid var(--navy);">
                                <?php endif; ?>
                                <label class="avatar-edit-btn" for="avatarInput" title="Đổi ảnh đại diện">
                                    <i class="bi bi-camera-fill"></i>
                                </label>
                            </div>

                            <h5 class="fw-bold"><?php echo htmlspecialchars($student['full_name']); ?></h5>
                            <div class="text-muted small mb-2"><?php echo htmlspecialchars($student['student_code']); ?></div>
                            <span class="badge bg-success"><?php echo htmlspecialchars($student['academic_status'] ?? 'Đang học'); ?></span>
                            <hr>
                            <div class="text-start">
                                <div class="mb-2">
                                    <small class="text-muted">Lớp:</small>
                                    <div class="fw-bold small"><?php echo htmlspecialchars($student['class_name'] ?? '--'); ?></div>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">Ngành:</small>
                                    <div class="fw-bold small"><?php echo htmlspecialchars($student['major_name'] ?? '--'); ?></div>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">Khoa:</small>
                                    <div class="fw-bold small"><?php echo htmlspecialchars($student['faculty_name'] ?? '--'); ?></div>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">Khóa:</small>
                                    <div class="fw-bold small"><?php echo htmlspecialchars($student['school_year'] ?? '--'); ?></div>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">Giới tính:</small>
                                    <div class="fw-bold small"><?php echo htmlspecialchars($student['gender'] ?? '--'); ?></div>
                                </div>
                                <?php if (!empty($student['birthday'])): ?>
                                <div>
                                    <small class="text-muted">Ngày sinh:</small>
                                    <div class="fw-bold small"><?php echo date('d/m/Y', strtotime($student['birthday'])); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form cập nhật -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <i class="bi bi-pencil-square me-2"></i>Cập nhật thông tin
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <!-- Input file ẩn -->
                                <input type="file" name="avatar" id="avatarInput" accept="image/*" style="display:none">

                                <div class="row g-3">
                                    <!-- Ảnh đại diện preview -->
                                    <div class="col-12" id="avatarPreviewWrap" style="display:none">
                                        <div class="alert alert-info d-flex align-items-center gap-3 py-2">
                                            <img id="avatarPreviewThumb" src="" style="width:50px;height:50px;object-fit:cover;border-radius:50%;">
                                            <div>
                                                <div class="fw-bold small" id="avatarFileName"></div>
                                                <div class="text-muted small">Ảnh sẽ được cập nhật khi bấm Lưu</div>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger ms-auto" onclick="clearAvatar()">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Họ và tên</label>
                                        <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($student['full_name']); ?>" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Tên đăng nhập</label>
                                        <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($student['username']); ?>" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control bg-light" value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Số điện thoại</label>
                                        <input type="text" name="phone" class="form-control"
                                               value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>"
                                               placeholder="Nhập số điện thoại...">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Địa chỉ</label>
                                        <input type="text" name="address" class="form-control"
                                               value="<?php echo htmlspecialchars($student['u_address'] ?? ''); ?>"
                                               placeholder="Nhập địa chỉ...">
                                    </div>

                                    <div class="col-12"><hr><h6 class="text-navy"><i class="bi bi-lock-fill me-2"></i>Đổi mật khẩu</h6></div>
                                    <div class="col-md-6">
                                        <label class="form-label">Mật khẩu cũ</label>
                                        <input type="password" name="old_password" class="form-control" placeholder="Nhập mật khẩu cũ...">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Mật khẩu mới</label>
                                        <input type="password" name="new_password" class="form-control" placeholder="Nhập mật khẩu mới...">
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-navy px-4">
                                            <i class="bi bi-save me-1"></i>Lưu thay đổi
                                        </button>
                                    </div>
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
<script>
const avatarInput = document.getElementById('avatarInput');
const avatarImg   = document.getElementById('avatarImg');
const avatarCircle = document.getElementById('avatarCircle');
const previewWrap = document.getElementById('avatarPreviewWrap');
const previewThumb = document.getElementById('avatarPreviewThumb');
const fileNameEl  = document.getElementById('avatarFileName');

avatarInput.addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;

    // Kiểm tra kích thước
    if (file.size > 2 * 1024 * 1024) {
        alert('Ảnh không được vượt quá 2MB!');
        this.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        const src = e.target.result;
        // Cập nhật avatar preview trong card
        if (avatarCircle) avatarCircle.style.display = 'none';
        avatarImg.src = src;
        avatarImg.style.display = 'block';
        // Hiện preview bar
        previewThumb.src = src;
        fileNameEl.textContent = file.name + ' (' + (file.size / 1024).toFixed(0) + ' KB)';
        previewWrap.style.display = 'block';
    };
    reader.readAsDataURL(file);
});

function clearAvatar() {
    avatarInput.value = '';
    previewWrap.style.display = 'none';
    // Khôi phục avatar cũ
    <?php if ($avatarUrl): ?>
    avatarImg.src = '<?php echo htmlspecialchars($avatarUrl); ?>';
    <?php else: ?>
    avatarImg.style.display = 'none';
    if (avatarCircle) avatarCircle.style.display = 'flex';
    <?php endif; ?>
}
</script>
</body>
</html>
