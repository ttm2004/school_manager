<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
$pageTitle = 'Tuyển sinh';

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply'])) {
    $major_id = intval($_POST['major_id'] ?? 0);
    $method_id = intval($_POST['method_id'] ?? 0);
    $full_name = trim($_POST['full_name'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $birthday = trim($_POST['birthday'] ?? '');
    $citizen_id = trim($_POST['citizen_id'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $high_school = trim($_POST['high_school'] ?? '');
    $graduation_year = intval($_POST['graduation_year'] ?? 0);
    $math_score = floatval($_POST['math_score'] ?? 0);
    $literature_score = floatval($_POST['literature_score'] ?? 0);
    $english_score = floatval($_POST['english_score'] ?? 0);
    $note = trim($_POST['note'] ?? '');

    if (empty($full_name) || empty($email) || empty($phone) || $major_id == 0 || $method_id == 0) {
        $error = 'Vui lòng điền đầy đủ các trường bắt buộc.';
    } else {
        $stmt = $conn->prepare("INSERT INTO admission_applications (major_id, method_id, full_name, gender, birthday, citizen_id, email, phone, address, high_school, graduation_year, math_score, literature_score, english_score, note, status, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'new',NOW())");
        $stmt->bind_param('iissssssssiddds', $major_id, $method_id, $full_name, $gender, $birthday, $citizen_id, $email, $phone, $address, $high_school, $graduation_year, $math_score, $literature_score, $english_score, $note);
        if ($stmt->execute()) {
            $success = 'Đăng ký xét tuyển thành công! Chúng tôi sẽ liên hệ với bạn sớm nhất.';
        } else {
            $error = 'Có lỗi xảy ra. Vui lòng thử lại sau.';
        }
        $stmt->close();
    }
}

// Fetch admission methods
$methods = $conn->query("SELECT * FROM admission_methods WHERE status='open' ORDER BY method_name ASC");

// Fetch majors with faculty
$majors = $conn->query("SELECT m.*, f.faculty_name FROM majors m LEFT JOIN faculties f ON m.faculty_id = f.id WHERE m.status='open' ORDER BY f.faculty_name, m.major_name");

include 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="/university/index.php">Trang chủ</a></li>
                <li class="breadcrumb-item active">Tuyển sinh</li>
            </ol>
        </nav>
        <h1><i class="bi bi-pencil-square me-2"></i>Thông tin tuyển sinh</h1>
        <p class="text-white-50 mb-0">Đăng ký xét tuyển vào Trường Đại học Thủ Dầu Một năm <?php echo date('Y'); ?></p>
    </div>
</div>

<!-- Admission Methods -->
<section class="py-5 bg-light">
    <div class="container">
        <h2 class="section-title text-center mb-2">Phương thức xét tuyển</h2>
        <p class="text-center text-muted mb-5">Các phương thức xét tuyển đang mở tại Trường Đại học Thủ Dầu Một</p>
        <div class="row g-4 justify-content-center">
            <?php
            $methodIcons = ['bi-file-text-fill', 'bi-graph-up', 'bi-award-fill', 'bi-clipboard-check-fill'];
            $mi = 0;
            if ($methods && $methods->num_rows > 0):
                $methodsArr = [];
                while ($m = $methods->fetch_assoc()) $methodsArr[] = $m;
                foreach ($methodsArr as $method):
                    $micon = $methodIcons[$mi % count($methodIcons)];
                    $mi++;
            ?>
            <div class="col-md-6 col-lg-3">
                <div class="card admission-method-card h-100">
                    <div class="admission-method-icon">
                        <i class="bi <?php echo $micon; ?>"></i>
                    </div>
                    <h5 class="fw-bold text-navy"><?php echo htmlspecialchars($method['method_name']); ?></h5>
                    <?php if (!empty($method['description'])): ?>
                    <p class="text-muted small"><?php echo htmlspecialchars($method['description']); ?></p>
                    <?php endif; ?>
                    <span class="badge bg-success mt-auto">Đang mở</span>
                </div>
            </div>
            <?php endforeach; else: ?>
            <div class="col-12"><div class="alert alert-info text-center">Chưa có phương thức xét tuyển nào đang mở.</div></div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Majors Available -->
<section class="py-5">
    <div class="container">
        <h2 class="section-title mb-2">Ngành đào tạo tuyển sinh</h2>
        <p class="text-muted mb-4">Danh sách các ngành đang tuyển sinh năm <?php echo date('Y'); ?></p>
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Tên ngành</th>
                                <th>Khoa</th>
                                <th>Học phí/năm</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($majors && $majors->num_rows > 0):
                                $idx = 1;
                                while ($major = $majors->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?php echo $idx++; ?></td>
                                <td class="fw-bold text-navy"><?php echo htmlspecialchars($major['major_name']); ?></td>
                                <td><?php echo htmlspecialchars($major['faculty_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if (!empty($major['tuition_per_credit'])): ?>
                                    <span class="text-gold fw-bold"><?php echo number_format($major['tuition_per_credit']); ?> VNĐ/TC</span>
                                    <?php else: ?>
                                    <span class="text-muted">Liên hệ</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-success">Đang tuyển</span></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">Chưa có ngành đào tạo nào.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Application Form -->
<section class="py-5 bg-light" id="apply">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="card shadow-custom">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Đăng ký xét tuyển trực tuyến</h5>
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
                            <input type="hidden" name="apply" value="1">
                            <h6 class="fw-bold text-navy mb-3 border-bottom pb-2">
                                <i class="bi bi-1-circle-fill me-2 text-gold"></i>Thông tin xét tuyển
                            </h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Ngành đăng ký <span class="text-danger">*</span></label>
                                    <select name="major_id" class="form-select" required>
                                        <option value="">-- Chọn ngành --</option>
                                        <?php
                                        // Re-query majors for form
                                        $majorsForm = $conn->query("SELECT m.id, m.major_name, f.faculty_name FROM majors m LEFT JOIN faculties f ON m.faculty_id = f.id WHERE m.status='open' ORDER BY f.faculty_name, m.major_name");
                                        if ($majorsForm):
                                            while ($mf = $majorsForm->fetch_assoc()):
                                        ?>
                                        <option value="<?php echo $mf['id']; ?>" <?php echo (isset($_POST['major_id']) && $_POST['major_id'] == $mf['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($mf['major_name']); ?> (<?php echo htmlspecialchars($mf['faculty_name'] ?? ''); ?>)
                                        </option>
                                        <?php endwhile; endif; ?>
                                    </select>
                                    <div class="invalid-feedback">Vui lòng chọn ngành đăng ký.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phương thức xét tuyển <span class="text-danger">*</span></label>
                                    <select name="method_id" class="form-select" required>
                                        <option value="">-- Chọn phương thức --</option>
                                        <?php
                                        $methodsForm = $conn->query("SELECT * FROM admission_methods WHERE status='open' ORDER BY method_name");
                                        if ($methodsForm):
                                            while ($meth = $methodsForm->fetch_assoc()):
                                        ?>
                                        <option value="<?php echo $meth['id']; ?>" <?php echo (isset($_POST['method_id']) && $_POST['method_id'] == $meth['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($meth['method_name']); ?>
                                        </option>
                                        <?php endwhile; endif; ?>
                                    </select>
                                    <div class="invalid-feedback">Vui lòng chọn phương thức xét tuyển.</div>
                                </div>
                            </div>

                            <h6 class="fw-bold text-navy mb-3 border-bottom pb-2">
                                <i class="bi bi-2-circle-fill me-2 text-gold"></i>Thông tin cá nhân
                            </h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Họ và tên <span class="text-danger">*</span></label>
                                    <input type="text" name="full_name" class="form-control" required value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" placeholder="Nguyễn Văn A">
                                    <div class="invalid-feedback">Vui lòng nhập họ và tên.</div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Giới tính</label>
                                    <select name="gender" class="form-select">
                                        <option value="">-- Chọn --</option>
                                        <option value="male" <?php echo (isset($_POST['gender']) && $_POST['gender']=='male') ? 'selected' : ''; ?>>Nam</option>
                                        <option value="female" <?php echo (isset($_POST['gender']) && $_POST['gender']=='female') ? 'selected' : ''; ?>>Nữ</option>
                                        <option value="other" <?php echo (isset($_POST['gender']) && $_POST['gender']=='other') ? 'selected' : ''; ?>>Khác</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Ngày sinh</label>
                                    <input type="date" name="birthday" class="form-control" value="<?php echo htmlspecialchars($_POST['birthday'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">CCCD/CMND</label>
                                    <input type="text" name="citizen_id" class="form-control" value="<?php echo htmlspecialchars($_POST['citizen_id'] ?? ''); ?>" placeholder="012345678901">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="email@example.com">
                                    <div class="invalid-feedback">Vui lòng nhập email hợp lệ.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                                    <input type="tel" name="phone" class="form-control" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" placeholder="0901234567">
                                    <div class="invalid-feedback">Vui lòng nhập số điện thoại.</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Địa chỉ</label>
                                    <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>" placeholder="Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành phố">
                                </div>
                            </div>

                            <h6 class="fw-bold text-navy mb-3 border-bottom pb-2">
                                <i class="bi bi-3-circle-fill me-2 text-gold"></i>Thông tin học vấn
                            </h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-8">
                                    <label class="form-label">Trường THPT</label>
                                    <input type="text" name="high_school" class="form-control" value="<?php echo htmlspecialchars($_POST['high_school'] ?? ''); ?>" placeholder="Tên trường THPT">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Năm tốt nghiệp</label>
                                    <input type="number" name="graduation_year" class="form-control" min="2000" max="<?php echo date('Y'); ?>" value="<?php echo htmlspecialchars($_POST['graduation_year'] ?? date('Y')); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Điểm Toán</label>
                                    <input type="number" name="math_score" class="form-control" min="0" max="10" step="0.25" value="<?php echo htmlspecialchars($_POST['math_score'] ?? ''); ?>" placeholder="0.00 - 10.00">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Điểm Văn</label>
                                    <input type="number" name="literature_score" class="form-control" min="0" max="10" step="0.25" value="<?php echo htmlspecialchars($_POST['literature_score'] ?? ''); ?>" placeholder="0.00 - 10.00">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Điểm Anh</label>
                                    <input type="number" name="english_score" class="form-control" min="0" max="10" step="0.25" value="<?php echo htmlspecialchars($_POST['english_score'] ?? ''); ?>" placeholder="0.00 - 10.00">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Ghi chú</label>
                                    <textarea name="note" class="form-control" rows="3" placeholder="Thông tin bổ sung..."><?php echo htmlspecialchars($_POST['note'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <div class="d-flex gap-3">
                                <button type="submit" class="btn btn-gold btn-lg px-5">
                                    <i class="bi bi-send-fill me-2"></i>Gửi đăng ký
                                </button>
                                <button type="reset" class="btn btn-outline-secondary btn-lg">
                                    <i class="bi bi-arrow-counterclockwise me-2"></i>Nhập lại
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
