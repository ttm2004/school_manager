<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/academic_helpers.php';
requireAnyRole(['academic_manager','academic_staff']);

$pageTitle = 'Quản lý phòng học';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['_csrf_token'] ?? '')) {
        $_SESSION['_flash'] = ['type'=>'danger','message'=>'Yêu cầu không hợp lệ.'];
        header('Location: rooms.php'); exit();
    }
    if (!isAcademicManager()) {
        $_SESSION['_flash'] = ['type'=>'danger','message'=>'Chỉ Trưởng phòng Đào tạo mới có quyền cập nhật phòng học.'];
        header('Location: rooms.php'); exit();
    }

    $action = trim($_POST['action'] ?? '');
    $code = strtoupper(trim($_POST['room_code'] ?? ''));
    $name = trim($_POST['room_name'] ?? '');
    $building = trim($_POST['building'] ?? '');
    $type = trim($_POST['room_type'] ?? 'theory');
    $capacity = max(1, (int)($_POST['capacity'] ?? 40));
    $status = trim($_POST['status'] ?? 'active');
    $note = trim($_POST['note'] ?? '');
    $allowedTypes = ['theory','lab','computer_lab','online','other'];
    $allowedStatuses = ['active','maintenance','inactive'];
    if (!in_array($type, $allowedTypes, true)) $type = 'theory';
    if (!in_array($status, $allowedStatuses, true)) $status = 'active';

    if ($action === 'add') {
        if ($code === '') {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Vui lòng nhập mã phòng.'];
            header('Location: rooms.php'); exit();
        }
        $stmt = $conn->prepare(
            "INSERT INTO classrooms (room_code, room_name, building, room_type, capacity, status, note)
             VALUES (?,?,?,?,?,?,?)"
        );
        $stmt->bind_param('ssssiss', $code, $name, $building, $type, $capacity, $status, $note);
        $_SESSION['_flash'] = $stmt->execute()
            ? ['type'=>'success','message'=>'Đã thêm phòng học.']
            : ['type'=>'danger','message'=>'Lỗi: '.$conn->error];
        $stmt->close();
        header('Location: rooms.php'); exit();
    }

    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || $code === '') {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Dữ liệu phòng không hợp lệ.'];
            header('Location: rooms.php'); exit();
        }
        $stmt = $conn->prepare(
            "UPDATE classrooms
             SET room_code=?, room_name=?, building=?, room_type=?, capacity=?, status=?, note=?
             WHERE id=?"
        );
        $stmt->bind_param('ssssissi', $code, $name, $building, $type, $capacity, $status, $note, $id);
        $_SESSION['_flash'] = $stmt->execute()
            ? ['type'=>'success','message'=>'Đã cập nhật phòng học.']
            : ['type'=>'danger','message'=>'Lỗi: '.$conn->error];
        $stmt->close();
        header('Location: rooms.php'); exit();
    }
}

$flash = getFlash();
$filterType = trim($_GET['type'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$search = trim($_GET['q'] ?? '');

$where = ['1=1']; $types = ''; $params = [];
if ($filterType !== '') { $where[] = 'room_type=?'; $types .= 's'; $params[] = $filterType; }
if ($filterStatus !== '') { $where[] = 'status=?'; $types .= 's'; $params[] = $filterStatus; }
if ($search !== '') {
    $where[] = '(room_code LIKE ? OR room_name LIKE ? OR building LIKE ?)';
    $like = "%$search%"; $types .= 'sss'; $params[] = $like; $params[] = $like; $params[] = $like;
}
$whereSQL = implode(' AND ', $where);
$stmt = $conn->prepare("SELECT * FROM classrooms WHERE $whereSQL ORDER BY building, room_code");
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$typeLabels = ['theory'=>'Lý thuyết','lab'=>'Thực hành','computer_lab'=>'Phòng máy','online'=>'Online','other'=>'Khác'];
$statusLabels = ['active'=>['success','Khả dụng'],'maintenance'=>['warning','Bảo trì'],'inactive'=>['secondary','Ngưng dùng']];

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
<div class="admin-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
        <span class="admin-topbar-title"><i class="bi bi-door-open-fill me-2 text-navy"></i>Quản lý phòng học</span>
    </div>
    <?php if (isAcademicManager()): ?>
    <button class="btn btn-sm btn-navy" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i>Thêm phòng</button>
    <?php endif; ?>
</div>
<div class="admin-content">
<?php if ($flash): ?>
<div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show auto-dismiss mb-3">
    <?php echo htmlspecialchars($flash['message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card mb-3"><div class="card-body py-2">
<form method="get" class="row g-2 align-items-end">
    <div class="col-6 col-md-2"><label class="form-label small">Loại phòng</label>
        <select name="type" class="form-select form-select-sm">
            <option value="">Tất cả</option>
            <?php foreach ($typeLabels as $k=>$v): ?><option value="<?php echo $k; ?>" <?php echo $filterType===$k?'selected':''; ?>><?php echo $v; ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-2"><label class="form-label small">Trạng thái</label>
        <select name="status" class="form-select form-select-sm">
            <option value="">Tất cả</option>
            <?php foreach ($statusLabels as $k=>$v): ?><option value="<?php echo $k; ?>" <?php echo $filterStatus===$k?'selected':''; ?>><?php echo $v[1]; ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-3"><label class="form-label small">Tìm kiếm</label><input name="q" class="form-control form-control-sm" value="<?php echo htmlspecialchars($search); ?>" placeholder="Mã, tên, tòa nhà..."></div>
    <div class="col-auto"><button class="btn btn-sm btn-navy"><i class="bi bi-search"></i></button><a href="rooms.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a></div>
</form>
</div></div>

<div class="card">
    <div class="card-header"><i class="bi bi-door-open-fill me-2"></i>Danh mục phòng <span class="badge bg-light text-dark ms-1"><?php echo count($rooms); ?></span></div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>Mã phòng</th><th>Tên phòng</th><th>Tòa</th><th>Loại</th><th class="text-center">Sức chứa</th><th>Trạng thái</th><th class="text-center">Thao tác</th></tr></thead>
            <tbody>
            <?php if (empty($rooms)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Chưa có phòng học.</td></tr>
            <?php else: foreach ($rooms as $room): [$color,$label] = $statusLabels[$room['status']] ?? ['secondary',$room['status']]; ?>
            <tr>
                <td><code><?php echo htmlspecialchars($room['room_code']); ?></code></td>
                <td class="fw-semibold small"><?php echo htmlspecialchars($room['room_name'] ?: '—'); ?></td>
                <td class="small text-muted"><?php echo htmlspecialchars($room['building'] ?: '—'); ?></td>
                <td class="small"><?php echo $typeLabels[$room['room_type']] ?? $room['room_type']; ?></td>
                <td class="text-center"><span class="badge bg-light text-dark"><?php echo (int)$room['capacity']; ?></span></td>
                <td><span class="badge bg-<?php echo $color; ?>"><?php echo $label; ?></span></td>
                <td class="text-center">
                    <?php if (isAcademicManager()): ?>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal"
                        data-id="<?php echo (int)$room['id']; ?>"
                        data-code="<?php echo htmlspecialchars($room['room_code']); ?>"
                        data-name="<?php echo htmlspecialchars($room['room_name'] ?? ''); ?>"
                        data-building="<?php echo htmlspecialchars($room['building'] ?? ''); ?>"
                        data-type="<?php echo htmlspecialchars($room['room_type']); ?>"
                        data-capacity="<?php echo (int)$room['capacity']; ?>"
                        data-status="<?php echo htmlspecialchars($room['status']); ?>"
                        data-note="<?php echo htmlspecialchars($room['note'] ?? ''); ?>"><i class="bi bi-pencil"></i></button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
<div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU</div>
</div>

<?php if (isAcademicManager()): ?>
<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form method="post"><?php echo csrfField(); ?><input type="hidden" name="action" value="add">
<div class="modal-header"><h5 class="modal-title">Thêm phòng học</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body row g-3">
    <div class="col-md-6"><label class="form-label">Mã phòng *</label><input name="room_code" class="form-control" required placeholder="VD: A101"></div>
    <div class="col-md-6"><label class="form-label">Tên phòng</label><input name="room_name" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">Tòa nhà</label><input name="building" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">Sức chứa</label><input type="number" name="capacity" min="1" class="form-control" value="40"></div>
    <div class="col-md-6"><label class="form-label">Loại phòng</label><select name="room_type" class="form-select"><?php foreach ($typeLabels as $k=>$v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label">Trạng thái</label><select name="status" class="form-select"><?php foreach ($statusLabels as $k=>$v): ?><option value="<?php echo $k; ?>"><?php echo $v[1]; ?></option><?php endforeach; ?></select></div>
    <div class="col-12"><label class="form-label">Ghi chú</label><textarea name="note" class="form-control" rows="2"></textarea></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button><button class="btn btn-navy">Lưu</button></div>
</form></div></div></div>

<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form method="post"><?php echo csrfField(); ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="editId">
<div class="modal-header"><h5 class="modal-title">Sửa phòng học</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body row g-3">
    <div class="col-md-6"><label class="form-label">Mã phòng *</label><input name="room_code" id="editCode" class="form-control" required></div>
    <div class="col-md-6"><label class="form-label">Tên phòng</label><input name="room_name" id="editName" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">Tòa nhà</label><input name="building" id="editBuilding" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">Sức chứa</label><input type="number" name="capacity" id="editCapacity" min="1" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">Loại phòng</label><select name="room_type" id="editType" class="form-select"><?php foreach ($typeLabels as $k=>$v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label">Trạng thái</label><select name="status" id="editStatus" class="form-select"><?php foreach ($statusLabels as $k=>$v): ?><option value="<?php echo $k; ?>"><?php echo $v[1]; ?></option><?php endforeach; ?></select></div>
    <div class="col-12"><label class="form-label">Ghi chú</label><textarea name="note" id="editNote" class="form-control" rows="2"></textarea></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button><button class="btn btn-navy">Lưu</button></div>
</form></div></div></div>
<script>
document.getElementById('editModal').addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('editId').value = b.dataset.id;
    document.getElementById('editCode').value = b.dataset.code;
    document.getElementById('editName').value = b.dataset.name;
    document.getElementById('editBuilding').value = b.dataset.building;
    document.getElementById('editType').value = b.dataset.type;
    document.getElementById('editCapacity').value = b.dataset.capacity;
    document.getElementById('editStatus').value = b.dataset.status;
    document.getElementById('editNote').value = b.dataset.note;
});
</script>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
