<?php
// session_start();
require_once '../php/config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Lấy thông tin hồ sơ
$sql = "SELECT r.*, 
        m.name as major_name, m.code as major_code,
        am.name as method_name,
        sc.code as combination_code, sc.name as combination_name,
        p.name as province_name, d.name as district_name
        FROM registrations r
        LEFT JOIN majors m ON r.major = m.id
        LEFT JOIN admission_methods am ON r.method = am.code
        LEFT JOIN subject_combinations sc ON r.combination_id = sc.id
        LEFT JOIN provinces p ON r.province_id = p.id
        LEFT JOIN districts d ON r.district_id = d.id
        WHERE r.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$registration = $result->fetch_assoc();

if (!$registration) {
    header('Location: registrations.php');
    exit();
}

// Lấy điểm xét tuyển
$score_sql = "SELECT * FROM diemtuyensinh WHERE registration_id = ?";
$score_stmt = $conn->prepare($score_sql);
$score_stmt->bind_param("i", $id);
$score_stmt->execute();
$score_data = $score_stmt->get_result()->fetch_assoc();

// Lấy lịch sử xử lý
$log_sql = "SELECT l.*, a.username 
            FROM activity_logs l
            LEFT JOIN admin_users a ON l.admin_id = a.id
            WHERE l.registration_id = ?
            ORDER BY l.created_at DESC";
$log_stmt = $conn->prepare($log_sql);

if (!$log_stmt) {
    die("SQL LOG ERROR: " . $conn->error);  
}

$log_stmt->bind_param("i", $id);
$log_stmt->execute();
$logs = $log_stmt->get_result();

$page_title = "Chi tiết hồ sơ";
require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mt-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="registrations.php">Quản lý hồ sơ</a></li>
                    <li class="breadcrumb-item active">Chi tiết hồ sơ #<?php echo str_pad($id, 8, '0', STR_PAD_LEFT); ?></li>
                </ol>
            </nav>

            <!-- Action Buttons -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h4">Chi tiết hồ sơ</h2>
                <div class="btn-group">
                    <button class="btn btn-outline-secondary" onclick="window.print()">
                        <i class="fas fa-print"></i> In
                    </button>
                    <button class="btn btn-outline-primary" onclick="exportPDF(<?php echo $id; ?>)">
                        <i class="fas fa-file-pdf"></i> Xuất PDF
                    </button>
                    <?php if ($registration['status'] == 'pending'): ?>
                        <button class="btn btn-success" onclick="processRegistration(<?php echo $id; ?>, 'approve')">
                            <i class="fas fa-check"></i> Duyệt
                        </button>
                        <button class="btn btn-danger" onclick="processRegistration(<?php echo $id; ?>, 'reject')">
                            <i class="fas fa-times"></i> Từ chối
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Status Banner -->
            <?php
            $status_colors = [
                'pending' => 'warning',
                'approved' => 'success',
                'rejected' => 'danger'
            ];
            $status_text = [
                'pending' => 'CHỜ XỬ LÝ',
                'approved' => 'ĐÃ TRÚNG TUYỂN',
                'rejected' => 'KHÔNG TRÚNG TUYỂN'
            ];
            ?>
            <div class="alert alert-<?php echo $status_colors[$registration['status']]; ?> mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>Trạng thái:</strong> <?php echo $status_text[$registration['status']]; ?>
                        <?php if ($registration['status'] == 'pending'): ?>
                            <span class="ms-3"><i class="fas fa-clock"></i> Chờ xử lý</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong>Ngày đăng ký:</strong> <?php echo date('d/m/Y H:i', strtotime($registration['created_at'])); ?>
                    </div>
                </div>
            </div>

            <!-- Profile Card -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>Thông tin cá nhân</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 text-center mb-3">
                            <?php if ($registration['avatar_file']): ?>
                                <img src="../uploads/registrations/<?php echo $id; ?>/<?php echo $registration['avatar_file']; ?>" 
                                     class="rounded-circle img-thumbnail" style="width: 150px; height: 150px; object-fit: cover;">
                            <?php else: ?>
                                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto" 
                                     style="width: 150px; height: 150px;">
                                    <i class="fas fa-user fa-4x text-secondary"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-8">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="text-muted">Họ và tên</label>
                                    <p class="fw-bold"><?php echo $registration['fullname']; ?></p>
                                </div>
                                <div class="col-sm-6">
                                    <label class="text-muted">Ngày sinh</label>
                                    <p class="fw-bold"><?php echo date('d/m/Y', strtotime($registration['birthday'])); ?></p>
                                </div>
                                <div class="col-sm-6">
                                    <label class="text-muted">Giới tính</label>
                                    <p class="fw-bold"><?php echo $registration['gender'] == 'male' ? 'Nam' : ($registration['gender'] == 'female' ? 'Nữ' : 'Khác'); ?></p>
                                </div>
                                <div class="col-sm-6">
                                    <label class="text-muted">Số CMND/CCCD</label>
                                    <p class="fw-bold"><?php echo $registration['identification']; ?></p>
                                </div>
                                <div class="col-sm-6">
                                    <label class="text-muted">Số điện thoại</label>
                                    <p class="fw-bold"><?php echo $registration['phone']; ?></p>
                                </div>
                                <div class="col-sm-6">
                                    <label class="text-muted">Email</label>
                                    <p class="fw-bold"><?php echo $registration['email']; ?></p>
                                </div>
                                <div class="col-12">
                                    <label class="text-muted">Địa chỉ</label>
                                    <p class="fw-bold">
                                        <?php 
                                        echo $registration['address'];
                                        if ($registration['district_name']) echo ', ' . $registration['district_name'];
                                        if ($registration['province_name']) echo ', ' . $registration['province_name'];
                                        ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Education Info -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>Thông tin học tập</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="text-muted">Trường THPT</label>
                            <p class="fw-bold"><?php echo $registration['school']; ?></p>
                        </div>
                        <div class="col-sm-6">
                            <label class="text-muted">Năm tốt nghiệp</label>
                            <p class="fw-bold"><?php echo $registration['graduation_year']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Admission Info -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-file-signature me-2"></i>Thông tin tuyển sinh</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="text-muted">Ngành đăng ký</label>
                            <p class="fw-bold"><?php echo $registration['major_name'] . ' (' . $registration['major_code'] . ')'; ?></p>
                        </div>
                        <div class="col-sm-6">
                            <label class="text-muted">Phương thức xét tuyển</label>
                            <p class="fw-bold"><?php echo $registration['method_name']; ?></p>
                        </div>
                        <div class="col-sm-6">
                            <label class="text-muted">Tổ hợp môn</label>
                            <p class="fw-bold"><?php echo $registration['combination_code'] . ' - ' . $registration['combination_name']; ?></p>
                        </div>
                        <div class="col-sm-6">
                            <label class="text-muted">Ghi chú</label>
                            <p class="fw-bold"><?php echo $registration['notes'] ?: 'Không có'; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Scores -->
            <?php if ($score_data): ?>
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Điểm xét tuyển</h5>
                </div>
                <div class="card-body">
                    <?php
                    $scores = json_decode($score_data['score_data'], true);
                    ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Môn</th>
                                    <th>Điểm</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($scores as $key => $value): ?>
                                <tr>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $key)); ?></td>
                                    <td><?php echo $value; ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-primary">
                                    <th>Tổng điểm</th>
                                    <th><?php echo $score_data['total_score']; ?></th>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Documents -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-file-upload me-2"></i>Hồ sơ đính kèm</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if ($registration['transcript_file']): ?>
                        <div class="col-md-4 mb-3">
                            <div class="border rounded p-3 text-center">
                                <i class="fas fa-file-pdf fa-3x text-danger mb-2"></i>
                                <p class="mb-2">Học bạ THPT</p>
                                <a href="../uploads/registrations/<?php echo $id; ?>/<?php echo $registration['transcript_file']; ?>" 
                                   class="btn btn-sm btn-primary" target="_blank">
                                    <i class="fas fa-eye"></i> Xem
                                </a>
                                <a href="../php/download.php?file=transcript&id=<?php echo $id; ?>" 
                                   class="btn btn-sm btn-success">
                                    <i class="fas fa-download"></i> Tải
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($registration['certificate_file']): ?>
                        <div class="col-md-4 mb-3">
                            <div class="border rounded p-3 text-center">
                                <i class="fas fa-file-pdf fa-3x text-warning mb-2"></i>
                                <p class="mb-2">Chứng chỉ ưu tiên</p>
                                <a href="../uploads/registrations/<?php echo $id; ?>/<?php echo $registration['certificate_file']; ?>" 
                                   class="btn btn-sm btn-primary" target="_blank">
                                    <i class="fas fa-eye"></i> Xem
                                </a>
                                <a href="../php/download.php?file=certificate&id=<?php echo $id; ?>" 
                                   class="btn btn-sm btn-success">
                                    <i class="fas fa-download"></i> Tải
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Activity Logs -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Lịch sử xử lý</h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php if ($logs->num_rows > 0): ?>
                            <?php while ($log = $logs->fetch_assoc()): ?>
                            <div class="timeline-item">
                                <div class="timeline-date">
                                    <?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?>
                                </div>
                                <div class="timeline-content">
                                    <strong><?php echo $log['username']; ?></strong> 
                                    đã <?php echo $log['action'] == 'approve' ? 'duyệt' : 'từ chối'; ?> hồ sơ
                                    <?php if ($log['note']): ?>
                                        <p class="text-muted mt-2">Ghi chú: <?php echo $log['note']; ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-muted">Chưa có hoạt động xử lý</p>
                        <?php endif; ?>
                        
                        <div class="timeline-item">
                            <div class="timeline-date">
                                <?php echo date('d/m/Y H:i', strtotime($registration['created_at'])); ?>
                            </div>
                            <div class="timeline-content">
                                <strong>Hệ thống</strong> đã tiếp nhận hồ sơ
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Process Form (for pending) -->
            <?php if ($registration['status'] == 'pending'): ?>
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Xử lý hồ sơ</h5>
                </div>
                <div class="card-body">
                    <form id="processForm">
                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Ghi chú xử lý</label>
                            <textarea class="form-control" name="note" rows="3" 
                                      placeholder="Nhập ghi chú (nếu cần)..."></textarea>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Sau khi xử lý, hệ thống sẽ tự động gửi email thông báo cho thí sinh.
                        </div>

                        <div class="btn-group">
                            <button type="button" class="btn btn-success" onclick="submitProcess('approve')">
                                <i class="fas fa-check"></i> Duyệt hồ sơ
                            </button>
                            <button type="button" class="btn btn-danger" onclick="submitProcess('reject')">
                                <i class="fas fa-times"></i> Từ chối
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Email Preview -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-envelope me-2"></i>Email thông báo</h5>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs" id="emailTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="approve-tab" data-bs-toggle="tab" data-bs-target="#approve" type="button" role="tab">
                                Email duyệt
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="reject-tab" data-bs-toggle="tab" data-bs-target="#reject" type="button" role="tab">
                                Email từ chối
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content mt-3" id="emailTabContent">
                        <div class="tab-pane fade show active" id="approve" role="tabpanel">
                            <div class="card">
                                <div class="card-body bg-light">
                                    <h5>Chủ đề: Kết quả xét tuyển - Đã trúng tuyển</h5>
                                    <hr>
                                    <p>Kính gửi <strong><?php echo $registration['fullname']; ?></strong>,</p>
                                    <p>Chúng tôi xin thông báo: Hồ sơ đăng ký xét tuyển của bạn đã được <span class="text-success">DUYỆT</span>.</p>
                                    <p>Chúc mừng bạn đã trúng tuyển vào ngành <strong><?php echo $registration['major_name']; ?></strong> của trường Đại học Khoa học và Công nghệ.</p>
                                    <p>Vui lòng làm theo hướng dẫn để hoàn tất thủ tục nhập học.</p>
                                    <hr>
                                    <p class="text-muted">Trân trọng,<br>Phòng Tuyển sinh</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="tab-pane fade" id="reject" role="tabpanel">
                            <div class="card">
                                <div class="card-body bg-light">
                                    <h5>Chủ đề: Kết quả xét tuyển - Không trúng tuyển</h5>
                                    <hr>
                                    <p>Kính gửi <strong><?php echo $registration['fullname']; ?></strong>,</p>
                                    <p>Chúng tôi rất tiếc phải thông báo: Hồ sơ đăng ký xét tuyển của bạn <span class="text-danger">KHÔNG ĐỦ ĐIỀU KIỆN</span> trúng tuyển.</p>
                                    <p>Cảm ơn bạn đã quan tâm và đăng ký vào trường. Chúc bạn thành công trong các cơ hội tiếp theo.</p>
                                    <hr>
                                    <p class="text-muted">Trân trọng,<br>Phòng Tuyển sinh</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #667eea;
}

.timeline-item {
    position: relative;
    padding-bottom: 25px;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -34px;
    top: 0;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: white;
    border: 2px solid #667eea;
}

.timeline-date {
    font-size: 13px;
    color: #999;
    margin-bottom: 5px;
}

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
}
</style>

<script>
function processRegistration(id, action) {
    if (confirm('Bạn có chắc chắn muốn ' + (action == 'approve' ? 'duyệt' : 'từ chối') + ' hồ sơ này?')) {
        fetch('process-registration.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: id,
                action: action
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Có lỗi xảy ra: ' + data.message);
            }
        });
    }
}

function submitProcess(action) {
    const form = document.getElementById('processForm');
    const formData = new FormData(form);
    
    if (confirm('Bạn có chắc chắn muốn ' + (action == 'approve' ? 'duyệt' : 'từ chối') + ' hồ sơ này?')) {
        fetch('process-registration.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: formData.get('id'),
                action: action,
                note: formData.get('note')
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Có lỗi xảy ra: ' + data.message);
            }
        });
    }
}

function exportPDF(id) {
    window.open('export-pdf.php?id=' + id, '_blank');
}
</script>

<?php require_once 'includes/footer.php'; ?>