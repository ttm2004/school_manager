<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/academic_helpers.php';
requireAnyRole(['academic_manager','academic_staff']);
$pageTitle = 'Sinh vien Toan truong';
$search = trim($_GET['q'] ?? '');
$filterFaculty = (int)($_GET['faculty_id'] ?? 0);
$filterStatus  = trim($_GET['status'] ?? '');
$filterMode = (($_GET['mode'] ?? 'system') === 'test') ? 'test' : 'system';
$page = max(1,(int)($_GET['page'] ?? 1)); $perPage = 25;
$flash = getFlash();

$where=['s.data_mode=?']; $types='s'; $params=[$filterMode];
if ($filterFaculty>0) { $where[]='f.id=?'; $types.='i'; $params[]=$filterFaculty; }
if ($filterStatus!=='') { $where[]='s.academic_status=?'; $types.='s'; $params[]=$filterStatus; }
if ($search!=='') { $where[]='(u.full_name LIKE ? OR s.student_code LIKE ? OR u.email LIKE ?)'; $like="%$search%"; $types.='sss'; $params[]=$like; $params[]=$like; $params[]=$like; }
$whereSQL=implode(' AND ',$where);

$stmtCnt=$conn->prepare("SELECT COUNT(*) AS c FROM students s JOIN users u ON s.user_id=u.id JOIN classes cl ON s.class_id=cl.id JOIN majors m ON cl.major_id=m.id JOIN faculties f ON m.faculty_id=f.id WHERE $whereSQL");
if ($types) $stmtCnt->bind_param($types,...$params); $stmtCnt->execute();
$total=(int)($stmtCnt->get_result()->fetch_assoc()['c']??0); $stmtCnt->close();
$pag=paginateAcademic($total,$page,$perPage);

$stmtData=$conn->prepare("SELECT s.id, s.student_code, s.academic_status, s.enrollment_year, s.data_mode, u.full_name, u.email, m.major_name, f.faculty_name, cl.class_name FROM students s JOIN users u ON s.user_id=u.id JOIN classes cl ON s.class_id=cl.id JOIN majors m ON cl.major_id=m.id JOIN faculties f ON m.faculty_id=f.id WHERE $whereSQL ORDER BY f.faculty_name, u.full_name LIMIT ? OFFSET ?");
$allTypes=$types.'ii'; $allParams=array_merge($params,[$pag['per_page'],$pag['offset']]);
$stmtData->bind_param($allTypes,...$allParams); $stmtData->execute();
$students=$stmtData->get_result()->fetch_all(MYSQLI_ASSOC); $stmtData->close();

$faculties=$conn->query("SELECT id, faculty_name FROM faculties ORDER BY faculty_name")->fetch_all(MYSQLI_ASSOC);
$statusBadge=['Dang hoc'=>'success','Bao luu'=>'warning','Thoi hoc'=>'danger','Da tot nghiep'=>'info'];

include 'includes/header.php'; include 'includes/sidebar.php';
?>
<div class="admin-main">
<div class="admin-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
        <span class="admin-topbar-title"><i class="bi bi-people-fill me-2 text-navy"></i>Sinh vien Toan truong</span>
    </div>
    <span class="text-muted small"><?php echo htmlspecialchars($_SESSION['full_name']??''); ?></span>
</div>
<div class="admin-content">
<?php if ($flash): ?><div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show auto-dismiss mb-3"><?php echo htmlspecialchars($flash['message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="card mb-3"><div class="card-body py-2">
<form method="get" class="row g-2 align-items-end">
    <div class="col-6 col-md-2"><label class="form-label small">Chế độ</label>
        <select name="mode" class="form-select form-select-sm">
            <option value="system" <?php echo $filterMode==='system'?'selected':''; ?>>Dữ liệu thật</option>
            <option value="test" <?php echo $filterMode==='test'?'selected':''; ?>>Test / Demo</option>
        </select></div>
    <div class="col-6 col-md-3"><label class="form-label small">Khoa</label>
        <select name="faculty_id" class="form-select form-select-sm"><option value="0">-- Tat ca --</option>
        <?php foreach ($faculties as $f): ?><option value="<?php echo $f['id']; ?>" <?php echo $filterFaculty==$f['id']?'selected':''; ?>><?php echo htmlspecialchars($f['faculty_name']); ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-2"><label class="form-label small">Trang thai</label>
        <select name="status" class="form-select form-select-sm"><option value="">-- Tat ca --</option>
        <?php foreach (array_keys($statusBadge) as $st): ?><option value="<?php echo $st; ?>" <?php echo $filterStatus===$st?'selected':''; ?>><?php echo $st; ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-4"><label class="form-label small">Tim kiem</label>
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Ten, ma SV, email..." value="<?php echo htmlspecialchars($search); ?>"></div>
    <div class="col-auto"><button type="submit" class="btn btn-sm btn-navy"><i class="bi bi-search"></i></button>
        <a href="students.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a></div>
</form></div></div>

<div class="card">
    <div class="card-header"><i class="bi bi-people-fill me-2"></i>Sinh vien <span class="badge bg-light text-dark ms-1"><?php echo number_format($total); ?></span></div>
    <?php if (empty($students)): ?>
    <div class="card-body text-center text-muted py-4"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Không có dữ liệu.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>Mã SV</th><th>Họ tên</th><th>Khoa</th><th>Ngành</th><th>Lớp</th><th>Chế độ</th><th>Trạng thái</th><th>Năm nhập học</th></tr></thead>
            <tbody>
            <?php foreach ($students as $sv): $badge=$statusBadge[$sv['academic_status']]??'secondary'; ?>
            <tr>
                <td><code class="small"><?php echo htmlspecialchars($sv['student_code']); ?></code></td>
                <td class="small fw-semibold"><?php echo htmlspecialchars($sv['full_name']); ?></td>
                <td class="small"><?php echo htmlspecialchars($sv['faculty_name']); ?></td>
                <td class="small"><?php echo htmlspecialchars($sv['major_name']); ?></td>
                <td class="small"><?php echo htmlspecialchars($sv['class_name']); ?></td>
                <td><span class="badge <?php echo ($sv['data_mode'] ?? 'system') === 'test' ? 'bg-info text-dark' : 'bg-secondary'; ?>"><?php echo ($sv['data_mode'] ?? 'system') === 'test' ? 'Test / Demo' : 'Dữ liệu thật'; ?></span></td>
                <td><span class="badge bg-<?php echo $badge; ?>"><?php echo htmlspecialchars($sv['academic_status']); ?></span></td>
                <td class="small text-muted"><?php echo $sv['enrollment_year']??'—'; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pag['total_pages']>1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted"><?php echo $pag['offset']+1; ?>–<?php echo min($pag['offset']+$pag['per_page'],$pag['total']); ?> / <?php echo number_format($pag['total']); ?></small>
        <?php echo renderAcademicPagination($pag,http_build_query(['mode'=>$filterMode,'faculty_id'=>$filterFaculty,'status'=>$filterStatus,'q'=>$search])); ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
</div>
<div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU</div>
</div>
<?php include 'includes/footer.php'; ?>
