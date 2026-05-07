<?php
// session_start();
require_once '../php/config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Xử lý thêm/sửa/xóa
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $code = sanitize($_POST['code']);
            $name = sanitize($_POST['name']);
            $category = sanitize($_POST['category']);
            $description = sanitize($_POST['description']);
            $duration = intval($_POST['duration']);
            $tuition = floatval(str_replace(',', '', $_POST['tuition']));
            $quota = intval($_POST['quota']);
            
            $check = $conn->query("SELECT id FROM majors WHERE code = '$code'");
            if ($check->num_rows > 0) {
                $error = '<div class="alert alert-danger">Mã ngành đã tồn tại!</div>';
            } else {
                $sql = "INSERT INTO majors (code, name, category, description, duration, tuition, quota) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssidi", $code, $name, $category, $description, $duration, $tuition, $quota);
                if ($stmt->execute()) {
                    $message = '<div class="alert alert-success">Thêm ngành học thành công!</div>';
                } else {
                    $error = '<div class="alert alert-danger">Có lỗi xảy ra, vui lòng thử lại!</div>';
                }
            }
        } elseif ($_POST['action'] == 'edit') {
            $id = intval($_POST['id']);
            $code = sanitize($_POST['code']);
            $name = sanitize($_POST['name']);
            $category = sanitize($_POST['category']);
            $description = sanitize($_POST['description']);
            $duration = intval($_POST['duration']);
            $tuition = floatval(str_replace(',', '', $_POST['tuition']));
            $quota = intval($_POST['quota']);
            $status = sanitize($_POST['status']);
            
            $sql = "UPDATE majors SET code=?, name=?, category=?, description=?, duration=?, tuition=?, quota=?, status=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssidisi", $code, $name, $category, $description, $duration, $tuition, $quota, $status, $id);
            if ($stmt->execute()) {
                $message = '<div class="alert alert-success">Cập nhật ngành học thành công!</div>';
            } else {
                $error = '<div class="alert alert-danger">Có lỗi xảy ra, vui lòng thử lại!</div>';
            }
        } elseif ($_POST['action'] == 'delete') {
            $id = intval($_POST['id']);
            $check = $conn->query("SELECT id FROM registrations WHERE major = $id LIMIT 1");
            if ($check->num_rows > 0) {
                $error = '<div class="alert alert-danger">Không thể xóa ngành này vì đang có hồ sơ đăng ký!</div>';
            } else {
                if ($conn->query("DELETE FROM majors WHERE id = $id")) {
                    $message = '<div class="alert alert-success">Xóa ngành học thành công!</div>';
                } else {
                    $error = '<div class="alert alert-danger">Có lỗi xảy ra, vui lòng thử lại!</div>';
                }
            }
        }
    }
}

// Lấy danh sách ngành
$majors = $conn->query("SELECT * FROM majors ORDER BY created_at DESC");

$page_title = "Quản lý ngành học";
require_once 'includes/header.php';
?>

<style>
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    --warning-gradient: linear-gradient(135deg, #f2994a 0%, #f2c94c 100%);
    --danger-gradient: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
    --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
    --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
    --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1);
}

/* Header Section */
.page-header-custom {
    background: var(--primary-gradient);
    border-radius: 20px;
    padding: 20px 25px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.page-header-custom h1 {
    color: white;
    margin: 0;
    font-size: 1.5rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.page-header-custom p {
    color: rgba(255,255,255,0.9);
    margin: 5px 0 0;
    font-size: 0.85rem;
}

.btn-gradient-primary {
    background: white;
    color: #667eea;
    border: none;
    padding: 10px 24px;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-gradient-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    background: white;
    color: #5a67d8;
}

/* Stats Row */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: var(--shadow-md);
    transition: all 0.3s;
    border: 1px solid #e2e8f0;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
}

.stat-card .stat-icon {
    width: 50px;
    height: 50px;
    background: var(--primary-gradient);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 15px;
}

.stat-card .stat-icon i {
    font-size: 24px;
    color: white;
}

.stat-card .stat-label {
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #718096;
    margin-bottom: 5px;
}

.stat-card .stat-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: #2d3748;
}

/* Table Card */
.table-card {
    background: white;
    border-radius: 20px;
    box-shadow: var(--shadow-md);
    overflow-x: auto;
}

.table-card .card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
    border-bottom: 1px solid #e2e8f0;
    padding: 15px 20px;
}

.table-card .card-header h5 {
    margin: 0;
    font-weight: 600;
    color: #2d3748;
    display: flex;
    align-items: center;
    gap: 10px;
}

.table-custom {
    margin: 0;
    min-width: 800px;
}

.table-custom thead th {
    background: #f8f9fa;
    border-bottom: 2px solid #e2e8f0;
    padding: 15px 20px;
    font-weight: 600;
    color: #2d3748;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table-custom tbody td {
    padding: 15px 20px;
    vertical-align: middle;
    border-bottom: 1px solid #e2e8f0;
}

.table-custom tbody tr:hover {
    background: #f8f9fa;
}

/* Badge Styles */
.badge-custom {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.badge-active {
    background: var(--success-gradient);
    color: white;
}

.badge-inactive {
    background: var(--danger-gradient);
    color: white;
}

.badge-tech {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.badge-science {
    background: linear-gradient(135deg, #11998e, #38ef7d);
    color: white;
}

.badge-engineer {
    background: linear-gradient(135deg, #f2994a, #f2c94c);
    color: white;
}

.badge-economic {
    background: linear-gradient(135deg, #4facfe, #00f2fe);
    color: white;
}

/* Action Buttons */
.btn-action {
    width: 32px;
    height: 32px;
    padding: 0;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin: 0 3px;
    transition: all 0.3s;
}

.btn-action:hover {
    transform: scale(1.1);
}

/* Modal Styling */
.modal-modern .modal-content {
    border-radius: 20px;
    border: none;
    box-shadow: var(--shadow-xl);
    overflow: hidden;
}

.modal-modern .modal-header {
    background: var(--primary-gradient);
    color: white;
    padding: 20px 25px;
    border: none;
}

.modal-modern .modal-header .btn-close {
    filter: brightness(0) invert(1);
}

.modal-modern .modal-body {
    padding: 25px;
}

.modal-modern .modal-footer {
    padding: 20px 25px;
    border-top: 1px solid #e2e8f0;
}

/* Form Styling */
.form-control-modern,
.form-select-modern {
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 10px 15px;
    transition: all 0.3s;
    width: 100%;
}

.form-control-modern:focus,
.form-select-modern:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
    outline: none;
}

.form-label-modern {
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 8px;
    display: block;
}

/* Tuition Format */
.tuition-value {
    font-weight: 600;
    color: #11998e;
}

/* Responsive */
@media (max-width: 992px) {
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .page-header-custom {
        flex-direction: column;
        text-align: center;
    }
    
    .stats-row {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .stat-card .stat-value {
        font-size: 1.5rem;
    }
    
    .modal-modern .modal-body {
        padding: 20px;
    }
    
    .modal-modern .modal-header,
    .modal-modern .modal-footer {
        padding: 15px 20px;
    }
}

@media (max-width: 576px) {
    .page-header-custom {
        padding: 15px 20px;
    }
    
    .page-header-custom h1 {
        font-size: 1.2rem;
    }
    
    .btn-gradient-primary {
        padding: 8px 16px;
        font-size: 0.85rem;
    }
    
    .stat-card {
        padding: 15px;
    }
    
    .stat-card .stat-icon {
        width: 40px;
        height: 40px;
    }
    
    .stat-card .stat-icon i {
        font-size: 18px;
    }
    
    .stat-card .stat-value {
        font-size: 1.3rem;
    }
    
    .card-header h5 {
        font-size: 1rem;
    }
}
</style>

<div class="container-fluid px-4">
    <div class="row">
        <?php require_once 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <!-- Header -->
            <div class="page-header-custom">
                <div>
                    <h1><i class="fas fa-graduation-cap me-2"></i>Quản lý ngành học</h1>
                    <p>Quản lý danh sách các ngành đào tạo của trường</p>
                </div>
                <button class="btn-gradient-primary" data-bs-toggle="modal" data-bs-target="#addMajorModal">
                    <i class="fas fa-plus me-2"></i>Thêm ngành mới
                </button>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Stats Row -->
            <?php
            $total_majors = $conn->query("SELECT COUNT(*) as total FROM majors")->fetch_assoc()['total'] ?? 0;
            $active_majors = $conn->query("SELECT COUNT(*) as total FROM majors WHERE status = 'active'")->fetch_assoc()['total'] ?? 0;
            $total_quota = $conn->query("SELECT SUM(quota) as total FROM majors")->fetch_assoc()['total'] ?? 0;
            ?>
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-book-open"></i></div>
                    <div class="stat-label">Tổng số ngành</div>
                    <div class="stat-value"><?php echo number_format($total_majors); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-label">Đang hoạt động</div>
                    <div class="stat-value"><?php echo number_format($active_majors); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-label">Tổng chỉ tiêu</div>
                    <div class="stat-value"><?php echo number_format($total_quota); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-label">Đào tạo bình quân</div>
                    <div class="stat-value">4 năm</div>
                </div>
            </div>

            <!-- Majors Table -->
            <div class="table-card">
                <div class="card-header">
                    <h5><i class="fas fa-list me-2"></i>Danh sách ngành đào tạo</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>Mã ngành</th>
                                <th>Tên ngành</th>
                                <th>Danh mục</th>
                                <th class="text-center">Thời gian</th>
                                <th class="text-end">Học phí</th>
                                <th class="text-center">Chỉ tiêu</th>
                                <th class="text-center">Đã đăng ký</th>
                                <th class="text-center">Trạng thái</th>
                                <th class="text-center">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($majors && $majors->num_rows > 0): ?>
                                <?php while ($row = $majors->fetch_assoc()): 
                                    $registered = $conn->query("SELECT COUNT(*) as total FROM registrations WHERE major = " . $row['id'])->fetch_assoc()['total'] ?? 0;
                                    $percent = $row['quota'] > 0 ? round($registered / $row['quota'] * 100, 1) : 0;
                                    $color = $percent >= 80 ? 'text-danger' : ($percent >= 50 ? 'text-warning' : 'text-success');
                                ?>
                                <tr>
                                    <td>
                                        <strong class="text-primary"><?php echo htmlspecialchars($row['code']); ?></strong>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['name']); ?></strong>
                                        <?php if ($row['description']): ?>
                                            <br><small class="text-muted"><?php echo substr(htmlspecialchars($row['description']), 0, 50); ?>...</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $category_badges = [
                                            'tech' => '<span class="badge-custom badge-tech"><i class="fas fa-laptop-code"></i> Công nghệ</span>',
                                            'science' => '<span class="badge-custom badge-science"><i class="fas fa-flask"></i> Khoa học</span>',
                                            'engineer' => '<span class="badge-custom badge-engineer"><i class="fas fa-cogs"></i> Kỹ thuật</span>',
                                            'economic' => '<span class="badge-custom badge-economic"><i class="fas fa-chart-line"></i> Kinh tế</span>'
                                        ];
                                        echo $category_badges[$row['category']] ?? '<span class="badge-custom">' . htmlspecialchars($row['category']) . '</span>';
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <i class="fas fa-calendar-alt me-1 text-muted"></i>
                                        <?php echo $row['duration']; ?> năm
                                    </td>
                                    <td class="text-end">
                                        <span class="tuition-value">
                                            <i class="fas fa-dollar-sign me-1"></i>
                                            <?php echo number_format($row['tuition'], 0, ',', '.'); ?>đ
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold"><?php echo number_format($row['quota']); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold <?php echo $color; ?>"><?php echo number_format($registered); ?></span>
                                        <br><small class="text-muted">(<?php echo $percent; ?>%)</small>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($row['status'] == 'active'): ?>
                                            <span class="badge-custom badge-active"><i class="fas fa-circle" style="font-size: 8px;"></i> Hoạt động</span>
                                        <?php else: ?>
                                            <span class="badge-custom badge-inactive"><i class="fas fa-circle" style="font-size: 8px;"></i> Tạm dừng</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-primary btn-action" onclick="editMajor(<?php echo htmlspecialchars(json_encode($row)); ?>)" title="Sửa">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger btn-action" onclick="deleteMajor(<?php echo $row['id']; ?>)" title="Xóa">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-5">
                                        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                        Chưa có ngành học nào
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add Major Modal -->
<div class="modal fade modal-modern" id="addMajorModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Thêm ngành học mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label-modern">Mã ngành <span class="text-danger">*</span></label>
                            <input type="text" class="form-control-modern" name="code" required placeholder="VD: CNTT001">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-modern">Tên ngành <span class="text-danger">*</span></label>
                            <input type="text" class="form-control-modern" name="name" required placeholder="Nhập tên ngành">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-modern">Danh mục <span class="text-danger">*</span></label>
                            <select class="form-select-modern" name="category" required>
                                <option value="tech">💻 Công nghệ</option>
                                <option value="science">🔬 Khoa học</option>
                                <option value="engineer">⚙️ Kỹ thuật</option>
                                <option value="economic">📊 Kinh tế</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-modern">Thời gian đào tạo (năm) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control-modern" name="duration" value="4" min="1" max="7" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-modern">Học phí/năm (VNĐ) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control-modern tuition-input" name="tuition" required placeholder="VD: 15,000,000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-modern">Chỉ tiêu <span class="text-danger">*</span></label>
                            <input type="number" class="form-control-modern" name="quota" required placeholder="VD: 500" min="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label-modern">Mô tả</label>
                            <textarea class="form-control-modern" name="description" rows="3" placeholder="Mô tả chi tiết về ngành học..."></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn" style="background: var(--primary-gradient); color: white; border: none; padding: 8px 24px; border-radius: 10px;">
                        <i class="fas fa-save me-2"></i>Thêm ngành
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Major Modal -->
<div class="modal fade modal-modern" id="editMajorModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Sửa ngành học</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label-modern">Mã ngành <span class="text-danger">*</span></label>
                            <input type="text" class="form-control-modern" name="code" id="edit_code" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-modern">Tên ngành <span class="text-danger">*</span></label>
                            <input type="text" class="form-control-modern" name="name" id="edit_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-modern">Danh mục <span class="text-danger">*</span></label>
                            <select class="form-select-modern" name="category" id="edit_category" required>
                                <option value="tech">💻 Công nghệ</option>
                                <option value="science">🔬 Khoa học</option>
                                <option value="engineer">⚙️ Kỹ thuật</option>
                                <option value="economic">📊 Kinh tế</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-modern">Thời gian đào tạo (năm) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control-modern" name="duration" id="edit_duration" min="1" max="7" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-modern">Học phí/năm (VNĐ) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control-modern tuition-input" name="tuition" id="edit_tuition" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-modern">Chỉ tiêu <span class="text-danger">*</span></label>
                            <input type="number" class="form-control-modern" name="quota" id="edit_quota" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-modern">Trạng thái <span class="text-danger">*</span></label>
                            <select class="form-select-modern" name="status" id="edit_status" required>
                                <option value="active">Hoạt động</option>
                                <option value="inactive">Tạm dừng</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label-modern">Mô tả</label>
                            <textarea class="form-control-modern" name="description" id="edit_description" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn" style="background: var(--primary-gradient); color: white; border: none; padding: 8px 24px; border-radius: 10px;">
                        <i class="fas fa-save me-2"></i>Cập nhật
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editMajor(major) {
    document.getElementById('edit_id').value = major.id;
    document.getElementById('edit_code').value = major.code;
    document.getElementById('edit_name').value = major.name;
    document.getElementById('edit_category').value = major.category;
    document.getElementById('edit_duration').value = major.duration;
    document.getElementById('edit_tuition').value = formatNumberInput(major.tuition);
    document.getElementById('edit_quota').value = major.quota;
    document.getElementById('edit_status').value = major.status;
    document.getElementById('edit_description').value = major.description || '';
    
    new bootstrap.Modal(document.getElementById('editMajorModal')).show();
}

function deleteMajor(id) {
    Swal.fire({
        title: 'Xác nhận xóa',
        text: 'Bạn có chắc chắn muốn xóa ngành học này? Hành động này không thể hoàn tác!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Xóa ngay',
        cancelButtonText: 'Hủy'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// Format tiền tệ khi nhập
function formatNumberInput(value) {
    if (!value) return '';
    let num = value.toString().replace(/[^\d]/g, '');
    if (num) {
        return parseInt(num).toLocaleString('vi-VN');
    }
    return '';
}

function handleTuitionInput(e) {
    let value = this.value.replace(/[^\d]/g, '');
    if (value) {
        this.value = parseInt(value).toLocaleString('vi-VN');
    }
}

document.querySelectorAll('.tuition-input').forEach(input => {
    input.addEventListener('input', handleTuitionInput);
});
</script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>