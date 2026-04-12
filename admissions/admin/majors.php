    <?php
// session_start();
require_once '../php/config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Xử lý thêm/sửa/xóa
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $code = sanitize($_POST['code']);
            $name = sanitize($_POST['name']);
            $category = sanitize($_POST['category']);
            $description = sanitize($_POST['description']);
            $duration = intval($_POST['duration']);
            $tuition = floatval($_POST['tuition']);
            $quota = intval($_POST['quota']);
            
            $sql = "INSERT INTO majors (code, name, category, description, duration, tuition, quota) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssidi", $code, $name, $category, $description, $duration, $tuition, $quota);
            $stmt->execute();
        } elseif ($_POST['action'] == 'edit') {
            $id = intval($_POST['id']);
            $code = sanitize($_POST['code']);
            $name = sanitize($_POST['name']);
            $category = sanitize($_POST['category']);
            $description = sanitize($_POST['description']);
            $duration = intval($_POST['duration']);
            $tuition = floatval($_POST['tuition']);
            $quota = intval($_POST['quota']);
            $status = sanitize($_POST['status']);
            
            $sql = "UPDATE majors SET code=?, name=?, category=?, description=?, duration=?, tuition=?, quota=?, status=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssidisi", $code, $name, $category, $description, $duration, $tuition, $quota, $status, $id);
            $stmt->execute();
        } elseif ($_POST['action'] == 'delete') {
            $id = intval($_POST['id']);
            $conn->query("DELETE FROM majors WHERE id = $id");
        }
    }
    header('Location: majors.php');
    exit();
}

// Lấy danh sách ngành
$majors = $conn->query("SELECT * FROM majors ORDER BY id DESC");

include 'includes/stats.php';
$page_title = "Quản lý ngành học";
require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Quản lý ngành học</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMajorModal">
                    <i class="fas fa-plus"></i> Thêm ngành mới
                </button>
            </div>

            <!-- Majors Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Mã ngành</th>
                                    <th>Tên ngành</th>
                                    <th>Danh mục</th>
                                    <th>Thời gian</th>
                                    <th>Học phí</th>
                                    <th>Chỉ tiêu</th>
                                    <th>Trạng thái</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $majors->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo $row['code']; ?></strong></td>
                                    <td><?php echo $row['name']; ?></td>
                                    <td>
                                        <?php
                                        $category_labels = [
                                            'tech' => 'Công nghệ',
                                            'science' => 'Khoa học',
                                            'engineer' => 'Kỹ thuật',
                                            'economic' => 'Kinh tế'
                                        ];
                                        echo $category_labels[$row['category']] ?? $row['category'];
                                        ?>
                                    </td>
                                    <td><?php echo $row['duration']; ?> năm</td>
                                    <td><?php echo number_format($row['tuition'], 0, ',', '.'); ?>đ</td>
                                    <td><?php echo $row['quota']; ?></td>
                                    <td>
                                        <?php if ($row['status'] == 'active'): ?>
                                            <span class="badge bg-success">Hoạt động</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Tạm dừng</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="editMajor(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteMajor(<?php echo $row['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add Major Modal -->
<div class="modal fade" id="addMajorModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-header">
                    <h5 class="modal-title">Thêm ngành học mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Mã ngành</label>
                            <input type="text" class="form-control" name="code" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tên ngành</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Danh mục</label>
                            <select class="form-select" name="category" required>
                                <option value="tech">Công nghệ</option>
                                <option value="science">Khoa học</option>
                                <option value="engineer">Kỹ thuật</option>
                                <option value="economic">Kinh tế</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Thời gian đào tạo (năm)</label>
                            <input type="number" class="form-control" name="duration" value="4" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Học phí/năm</label>
                            <input type="number" class="form-control" name="tuition" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Chỉ tiêu</label>
                            <input type="number" class="form-control" name="quota" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Mô tả</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Thêm ngành</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Major Modal -->
<div class="modal fade" id="editMajorModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Sửa ngành học</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Mã ngành</label>
                            <input type="text" class="form-control" name="code" id="edit_code" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tên ngành</label>
                            <input type="text" class="form-control" name="name" id="edit_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Danh mục</label>
                            <select class="form-select" name="category" id="edit_category" required>
                                <option value="tech">Công nghệ</option>
                                <option value="science">Khoa học</option>
                                <option value="engineer">Kỹ thuật</option>
                                <option value="economic">Kinh tế</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Thời gian đào tạo (năm)</label>
                            <input type="number" class="form-control" name="duration" id="edit_duration" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Học phí/năm</label>
                            <input type="number" class="form-control" name="tuition" id="edit_tuition" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Chỉ tiêu</label>
                            <input type="number" class="form-control" name="quota" id="edit_quota" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Trạng thái</label>
                            <select class="form-select" name="status" id="edit_status" required>
                                <option value="active">Hoạt động</option>
                                <option value="inactive">Tạm dừng</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Mô tả</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Cập nhật</button>
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
    document.getElementById('edit_tuition').value = major.tuition;
    document.getElementById('edit_quota').value = major.quota;
    document.getElementById('edit_status').value = major.status;
    document.getElementById('edit_description').value = major.description;
    
    new bootstrap.Modal(document.getElementById('editMajorModal')).show();
}

function deleteMajor(id) {
    if (confirm('Bạn có chắc chắn muốn xóa ngành học này?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>