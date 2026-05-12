<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/academic_helpers.php';
requireAnyRole(['academic_manager','academic_staff']);
$pageTitle = 'Quan ly Mon hoc';
$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['_csrf_token'] ?? '')) { $_SESSION['_flash']=['type'=>'danger','message'=>'CSRF invalid.']; header('Location: subjects.php'); exit(); }
    if (!isAcademicManager()) { $_SESSION['_flash']=['type'=>'danger','message'=>'Chi Truong phong moi co quyen.']; header('Location: subjects.php'); exit(); }
    $action = trim($_POST['action'] ?? '');
    if ($action === 'add') {
        $code    = trim($_POST['subject_code'] ?? '');
        $name    = trim($_POST['subject_name'] ?? '');
        $majorId = (int)($_POST['major_id'] ?? 0);
        $credits = max(1,(int)($_POST['credits'] ?? 3));
        $type    = trim($_POST['subject_type'] ?? 'Bat buoc');
        $desc    = trim($_POST['description'] ?? '');
        if ($code && $name && $majorId) {
            $stmt = $conn->prepare("INSERT INTO subjects (subject_code,subject_name,major_id,credits,subject_type,description) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param('ssiiss',$code,$name,$majorId,$credits,$type,$desc);
            $stmt->execute() ? $_SESSION['_flash']=['type'=>'success','message'=>'Them mon hoc thanh cong.']
                             : $_SESSION['_flash']=['type'=>'danger','message'=>'Loi: '.$conn->error];
            $stmt->close();
        } else { $_SESSION['_flash']=['type'=>'danger','message'=>'Vui long dien day du thong tin.']; }
        header('Location: subjects.php'); exit();
    }
    if ($action === 'edit') {
        $id=(int)($_POST['id']??0); $name=trim($_POST['subject_name']??''); $credits=max(1,(int)($_POST['credits']??3)); $type=trim($_POST['subject_type']??''); $desc=trim($_POST['description']??'');
        if ($id && $name) {
            $stmt=$conn->prepare("UPDATE subjects SET subject_name=?,credits=?,subject_type=?,description=? WHERE id=?");
            $stmt->bind_param('sissi',$name,$credits,$type,$desc,$id);
            $stmt->execute() ? $_SESSION['_flash']=['type'=>'success','message'=>'Cap nhat thanh cong.']
                             : $_SESSION['_flash']=['type'=>'danger','message'=>'Loi: '.$conn->error];
            $stmt->close();
        }
        header('Location: subjects.php'); exit();
    }
}

$flash = getFlash();
$filterFaculty = (int)($_GET['faculty_id'] ?? 0);
$filterMajor   = (int)($_GET['major_id'] ?? 0);
$search        = trim($_GET['q'] ?? '');
$page          = max(1,(int)($_GET['page'] ?? 1));
$perPage       = 25;

$where=['1=1']; $types=''; $params=[];
if ($filterFaculty) { $where[]='f.id=?'; $types.='i'; $params[]=$filterFaculty; }
if ($filterMajor)   { $where[]='s.major_id=?'; $types.='i'; $params[]=$filterMajor; }
if ($search)        { $where[]='(s.subject_name LIKE ? OR s.subject_code LIKE ?)'; $like="%$search%"; $types.='ss'; $params[]=$like; $params[]=$like; }
$whereSQL=implode(' AND ',$where);

$stmtCnt=$conn->prepare("SELECT COUNT(*) AS c FROM subjects s JOIN majors m ON s.major_id=m.id JOIN faculties f ON m.faculty_id=f.id WHERE $whereSQL");
if ($types) $stmtCnt->bind_param($types,...$params); $stmtCnt->execute();
$total=(int)($stmtCnt->get_result()->fetch_assoc()['c']??0); $stmtCnt->close();
$pag=paginateAcademic($total,$page,$perPage);

$stmtData=$conn->prepare("SELECT s.id, s.subject_code, s.subject_name, s.credits, s.subject_type, s.description, m.major_name, f.faculty_name FROM subjects s JOIN majors m ON s.major_id=m.id JOIN faculties f ON m.faculty_id=f.id WHERE $whereSQL ORDER BY f.faculty_name, m.major_name, s.subject_name LIMIT ? OFFSET ?");
$allTypes=$types.'ii'; $allParams=array_merge($params,[$pag['per_page'],$pag['offset']]);
$stmtData->bind_param($allTypes,...$allParams); $stmtData->execute();
$subjects=$stmtData->get_result()->fetch_all(MYSQLI_ASSOC); $stmtData->close();

$faculties=$conn->query("SELECT id, faculty_name FROM faculties ORDER BY faculty_name")->fetch_all(MYSQLI_ASSOC);
$majors=$conn->query("SELECT m.id, m.major_name, f.faculty_name FROM majors m JOIN faculties f ON m.faculty_id=f.id ORDER BY f.faculty_name, m.major_name")->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php'; include 'includes/sidebar.php';
?>
<div class="admin-main">
<div class="admin-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
        <span class="admin-topbar-title"><i class="bi bi-book-fill me-2 text-navy"></i>Quan ly Mon hoc</span>
    </div>
    <div class="d-flex gap-2">
        <?php if (isAcademicManager()): ?>
        <button class="btn btn-sm btn-navy" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i>Them mon hoc</button>
        <?php endif; ?>
        <span class="text-muted small align-self-center"><?php echo htmlspecialchars($_SESSION['full_name']??'')?></span>
    </div>
</div>
<div class="admin-content">
<?php if ($flash): ?><div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show auto-dismiss mb-3"><?php echo htmlspecialchars($flash['message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="card mb-3"><div class="card-body py-2">
<form method="get" class="row g-2 align-items-end">
    <div class="col-6 col-md-3"><label class="form-label small">Khoa</label>
        <select name="faculty_id" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="0">-- Tat ca --</option>
            <?php foreach ($faculties as $f): ?><option value="<?php echo $f['id']; ?>" <?php echo $filterFaculty==$f['id']?'selected':''; ?>><?php echo htmlspecialchars($f['faculty_name']); ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-3"><label class="form-label small">Nganh</label>
        <select name="major_id" class="form-select form-select-sm">
            <option value="0">-- Tat ca --</option>
            <?php foreach ($majors as $m): ?><option value="<?php echo $m['id']; ?>" <?php echo $filterMajor==$m['id']?'selected':''; ?>><?php echo htmlspecialchars($m['major_name']); ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-3"><label class="form-label small">Tim kiem</label>
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Ten, ma mon..." value="<?php echo htmlspecialchars($search); ?>"></div>
    <div class="col-auto"><button type="submit" class="btn btn-sm btn-navy"><i class="bi bi-search"></i></button>
        <a href="subjects.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a></div>
</form></div></div>

<div class="card">
    <div class="card-header"><i class="bi bi-book-fill me-2"></i>Mon hoc <span class="badge bg-light text-dark ms-1"><?php echo number_format($total); ?></span></div>
    <?php if (empty($subjects)): ?>
    <div class="card-body text-center text-muted py-4"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Khong co du lieu.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>Ma mon</th><th>Ten mon hoc</th><th>Khoa / Nganh</th><th class="text-center">TC</th><th>Loai</th><th class="text-center">Thao tac</th></tr></thead>
            <tbody>
            <?php foreach ($subjects as $s): ?>
            <tr>
                <td><code class="small"><?php echo htmlspecialchars($s['subject_code']); ?></code></td>
                <td class="small fw-semibold"><?php echo htmlspecialchars($s['subject_name']); ?></td>
                <td class="small text-muted"><?php echo htmlspecialchars($s['faculty_name']); ?> / <?php echo htmlspecialchars($s['major_name']); ?></td>
                <td class="text-center"><span class="badge bg-light text-dark"><?php echo $s['credits']; ?></span></td>
                <td class="small"><?php echo htmlspecialchars($s['subject_type']); ?></td>
                <td class="text-center">
                    <?php if (isAcademicManager()): ?>
                    <button class="btn btn-xs btn-outline-primary" style="font-size:.7rem;padding:2px 6px"
                            data-bs-toggle="modal" data-bs-target="#editModal"
                            data-id="<?php echo $s['id']; ?>"
                            data-name="<?php echo htmlspecialchars($s['subject_name']); ?>"
                            data-credits="<?php echo $s['credits']; ?>"
                            data-type="<?php echo htmlspecialchars($s['subject_type']); ?>"
                            data-desc="<?php echo htmlspecialchars($s['description']??''); ?>">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pag['total_pages']>1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted"><?php echo $pag['offset']+1; ?>–<?php echo min($pag['offset']+$pag['per_page'],$pag['total']); ?> / <?php echo number_format($pag['total']); ?></small>
        <?php echo renderAcademicPagination($pag,http_build_query(['faculty_id'=>$filterFaculty,'major_id'=>$filterMajor,'q'=>$search])); ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
</div>
<div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU</div>
</div>

<!-- Modal Them -->
<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Them Mon hoc</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post" action="subjects.php"><?php echo csrfField(); ?><input type="hidden" name="action" value="add">
    <div class="modal-body"><div class="row g-3">
        <div class="col-md-6"><label class="form-label fw-semibold">Ma mon <span class="text-danger">*</span></label><input type="text" name="subject_code" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label fw-semibold">Ten mon hoc <span class="text-danger">*</span></label><input type="text" name="subject_name" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label fw-semibold">Nganh <span class="text-danger">*</span></label>
            <select name="major_id" class="form-select" required><option value="">-- Chon nganh --</option>
            <?php $lastF=''; foreach ($majors as $m): if ($m['faculty_name']!==$lastF) { if ($lastF!=='') echo '</optgroup>'; echo '<optgroup label="'.htmlspecialchars($m['faculty_name']).'">'; $lastF=$m['faculty_name']; } ?>
            <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['major_name']); ?></option>
            <?php endforeach; if ($lastF!=='') echo '</optgroup>'; ?></select></div>
        <div class="col-md-3"><label class="form-label fw-semibold">So tin chi</label><input type="number" name="credits" class="form-control" value="3" min="1" max="10"></div>
        <div class="col-md-3"><label class="form-label fw-semibold">Loai</label>
            <select name="subject_type" class="form-select"><option value="Bat buoc">Bat buoc</option><option value="Tu chon">Tu chon</option></select></div>
        <div class="col-12"><label class="form-label fw-semibold">Mo ta</label><textarea name="description" class="form-control" rows="2"></textarea></div>
    </div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huy</button><button type="submit" class="btn btn-navy"><i class="bi bi-save me-1"></i>Luu</button></div>
    </form>
</div></div></div>

<!-- Modal Sua -->
<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Chinh sua Mon hoc</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post" action="subjects.php"><?php echo csrfField(); ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="editId">
    <div class="modal-body"><div class="row g-3">
        <div class="col-12"><label class="form-label fw-semibold">Ten mon hoc</label><input type="text" name="subject_name" id="editName" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label fw-semibold">So tin chi</label><input type="number" name="credits" id="editCredits" class="form-control" min="1" max="10"></div>
        <div class="col-md-6"><label class="form-label fw-semibold">Loai</label>
            <select name="subject_type" id="editType" class="form-select"><option value="Bat buoc">Bat buoc</option><option value="Tu chon">Tu chon</option></select></div>
        <div class="col-12"><label class="form-label fw-semibold">Mo ta</label><textarea name="description" id="editDesc" class="form-control" rows="2"></textarea></div>
    </div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huy</button><button type="submit" class="btn btn-navy"><i class="bi bi-save me-1"></i>Luu</button></div>
    </form>
</div></div></div>
<script>
document.getElementById('editModal').addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('editId').value = b.dataset.id;
    document.getElementById('editName').value = b.dataset.name;
    document.getElementById('editCredits').value = b.dataset.credits;
    document.getElementById('editType').value = b.dataset.type;
    document.getElementById('editDesc').value = b.dataset.desc;
});
</script>
<?php include 'includes/footer.php'; ?>
