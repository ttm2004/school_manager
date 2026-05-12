<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/academic_helpers.php';
requireAnyRole(['academic_manager','academic_staff']);
$pageTitle = 'Giang vien Toan truong';
$search = trim($_GET['q'] ?? '');
$filterFaculty = (int)($_GET['faculty_id'] ?? 0);
$filterDegree  = trim($_GET['degree'] ?? '');
$page = max(1,(int)($_GET['page'] ?? 1)); $perPage = 25;
$flash = getFlash();

$where=['1=1']; $types=''; $params=[];
if ($filterFaculty>0) { $where[]='t.faculty_id=?'; $types.='i'; $params[]=$filterFaculty; }
if ($filterDegree!=='') { $where[]='t.degree=?'; $types.='s'; $params[]=$filterDegree; }
if ($search!=='') { $where[]='(u.full_name LIKE ? OR t.teacher_code LIKE ? OR u.email LIKE ?)'; $like="%$search%"; $types.='sss'; $params[]=$like; $params[]=$like; $params[]=$like; }
$whereSQL=implode(' AND ',$where);

$stmtCnt=$conn->prepare("SELECT COUNT(*) AS c FROM teachers t JOIN users u ON t.user_id=u.id WHERE $whereSQL");
if ($types) $stmtCnt->bind_param($types,...$params); $stmtCnt->execute();
$total=(int)($stmtCnt->get_result()->fetch_assoc()['c']??0); $stmtCnt->close();
$pag=paginateAcademic($total,$page,$perPage);

$stmtData=$conn->prepare("SELECT t.id, t.teacher_code, t.degree, t.specialization, u.full_name, u.email, u.phone, f.faculty_name,
       (SELECT COUNT(*) FROM course_sections cs2 WHERE cs2.teacher_id=t.id AND cs2.semester_id=(SELECT id FROM semesters WHERE status IN ('active','open') ORDER BY id DESC LIMIT 1)) AS current_load
FROM teachers t JOIN users u ON t.user_id=u.id LEFT JOIN faculties f ON t.faculty_id=f.id
WHERE $whereSQL ORDER BY f.faculty_name, u.full_name LIMIT ? OFFSET ?");
$allTypes=$types.'ii'; $allParams=array_merge($params,[$pag['per_page'],$pag['offset']]);
$stmtData->bind_param($allTypes,...$allParams); $stmtData->execute();
$teachers=$stmtData->get_result()->fetch_all(MYSQLI_ASSOC); $stmtData->close();

$faculties=$conn->query("SELECT id, faculty_name FROM faculties ORDER BY faculty_name")->fetch_all(MYSQLI_ASSOC);
$degrees=$conn->query("SELECT DISTINCT degree FROM teachers WHERE degree IS NOT NULL ORDER BY degree")->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php'; include 'includes/sidebar.php';
?>
<div class="admin-main">
<div class="admin-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
        <span class="admin-topbar-title"><i class="bi bi-person-badge-fill me-2 text-navy"></i>Giang vien Toan truong</span>
    </div>
    <span class="text-muted small"><?php echo htmlspecialchars($_SESSION['full_name']??''); ?></span>
</div>
<div class="admin-content">
<?php if ($flash): ?><div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show auto-dismiss mb-3"><?php echo htmlspecialchars($flash['message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="card mb-3"><div class="card-body py-2">
<form method="get" class="row g-2 align-items-end">
    <div class="col-6 col-md-3"><label class="form-label small">Khoa</label>
        <select name="faculty_id" class="form-select form-select-sm"><option value="0">-- Tat ca --</option>
        <?php foreach ($faculties as $f): ?><option value="<?php echo $f['id']; ?>" <?php echo $filterFaculty==$f['id']?'selected':''; ?>><?php echo htmlspecialchars($f['faculty_name']); ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-2"><label class="form-label small">Hoc vi</label>
        <select name="degree" class="form-select form-select-sm"><option value="">-- Tat ca --</option>
        <?php foreach ($degrees as $d): ?><option value="<?php echo htmlspecialchars($d['degree']); ?>" <?php echo $filterDegree===$d['degree']?'selected':''; ?>><?php echo htmlspecialchars($d['degree']); ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-6 col-md-4"><label class="form-label small">Tim kiem</label>
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Ten, ma GV, email..." value="<?php echo htmlspecialchars($search); ?>"></div>
    <div class="col-auto"><button type="submit" class="btn btn-sm btn-navy"><i class="bi bi-search"></i></button>
        <a href="teachers.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a></div>
</form></div></div>

<div class="card">
    <div class="card-header"><i class="bi bi-person-badge-fill me-2"></i>Giang vien <span class="badge bg-light text-dark ms-1"><?php echo number_format($total); ?></span></div>
    <?php if (empty($teachers)): ?>
    <div class="card-body text-center text-muted py-4"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Khong co du lieu.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>Ma GV</th><th>Ho ten</th><th>Khoa</th><th>Hoc vi</th><th>Chuyen nganh</th><th>Email</th><th class="text-center">Lop HP HK nay</th></tr></thead>
            <tbody>
            <?php foreach ($teachers as $t): $loadColor=$t['current_load']>20?'danger':($t['current_load']>0?'success':'secondary'); ?>
            <tr>
                <td><code class="small"><?php echo htmlspecialchars($t['teacher_code']); ?></code></td>
                <td class="small fw-semibold"><?php echo htmlspecialchars($t['full_name']); ?></td>
                <td class="small"><?php echo htmlspecialchars($t['faculty_name']??'—'); ?></td>
                <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($t['degree']??'—'); ?></span></td>
                <td class="small text-muted"><?php echo htmlspecialchars($t['specialization']??'—'); ?></td>
                <td class="small text-muted"><?php echo htmlspecialchars($t['email']??'—'); ?></td>
                <td class="text-center"><span class="badge bg-<?php echo $loadColor; ?>"><?php echo $t['current_load']; ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pag['total_pages']>1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted"><?php echo $pag['offset']+1; ?>–<?php echo min($pag['offset']+$pag['per_page'],$pag['total']); ?> / <?php echo number_format($pag['total']); ?></small>
        <?php echo renderAcademicPagination($pag,http_build_query(['faculty_id'=>$filterFaculty,'degree'=>$filterDegree,'q'=>$search])); ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
</div>
<div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU</div>
</div>
<?php include 'includes/footer.php'; ?>
