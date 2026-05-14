<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Nhan vien & Phan quyen';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $username  = trim($_POST['username'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $password  = trim($_POST['password'] ?? '123456');
        if ($username && $full_name) {
            $chk = $conn->prepare("SELECT id FROM users WHERE username=?");
            $chk->bind_param('s',$username); $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $error = 'Ten dang nhap da ton tai.';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username,password,full_name,email,phone,role,status) VALUES (?,?,?,?,?,'staff',1)");
                $stmt->bind_param('sssss',$username,$hashed,$full_name,$email,$phone);
                $stmt->execute() ? $success = 'Thêm nhân viên thành công!' : $error = $conn->error;
                $stmt->close();
            }
            $chk->close();
        } else { $error = 'Vui lòng điền đủ thông tin.'; }
    }

    if ($action === 'edit') {
        $id=$_POST['id']??0; $full_name=trim($_POST['full_name']??'');
        $email=trim($_POST['email']??''); $phone=trim($_POST['phone']??'');
        $pw=trim($_POST['new_password']??'');
        if ($id && $full_name) {
            if ($pw) {
                $hashed = password_hash($pw, PASSWORD_DEFAULT);
                $stmt=$conn->prepare("UPDATE users SET full_name=?,email=?,phone=?,password=? WHERE id=?");
                $stmt->bind_param('ssssi',$full_name,$email,$phone,$hashed,$id);
            } else {
                $stmt=$conn->prepare("UPDATE users SET full_name=?,email=?,phone=? WHERE id=?");
                $stmt->bind_param('sssi',$full_name,$email,$phone,$id);
            }
            $stmt->execute() ? $success='Cập nhật thành công!' : $error=$conn->error;
            $stmt->close();
        }
    }

    if ($action === 'toggle') {
        $id=intval($_POST['id']??0);
        if ($id && $id!=(int)$_SESSION['user_id']) {
            $conn->query("UPDATE users SET status=IF(status=1,0,1) WHERE id=$id");
            $success='Da cap nhat trang thai.';
        } else { $error='Khong the khoa chinh minh.'; }
    }

    if ($action === 'grant_role') {
        $uid=intval($_POST['uid']??0); $rid=intval($_POST['rid']??0);
        $note=trim($_POST['note']??''); $exp=trim($_POST['exp']??'')?:null;
        $by=(int)$_SESSION['user_id'];
        if ($uid && $rid) {
            if ($exp) {
                $stmt=$conn->prepare("INSERT INTO user_roles(user_id,role_id,granted_by,note,expires_at) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE granted_by=VALUES(granted_by),note=VALUES(note),expires_at=VALUES(expires_at),granted_at=NOW()");
                $stmt->bind_param('iiiss',$uid,$rid,$by,$note,$exp);
            } else {
                $stmt=$conn->prepare("INSERT INTO user_roles(user_id,role_id,granted_by,note) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE granted_by=VALUES(granted_by),note=VALUES(note),expires_at=NULL,granted_at=NOW()");
                $stmt->bind_param('iiis',$uid,$rid,$by,$note);
            }
            $stmt->execute() ? $success='Cap quyen thanh cong!' : $error=$conn->error;
            $stmt->close();
        }
    }

    if ($action === 'revoke') {
        $urid=intval($_POST['urid']??0);
        if ($urid) {
            $stmt=$conn->prepare("DELETE FROM user_roles WHERE id=?");
            $stmt->bind_param('i',$urid); $stmt->execute();
            $success='Da thu hoi quyen.'; $stmt->close();
        }
    }

    // ── PRG: redirect sau POST để tránh F5 gửi lại form ──
    if (!empty($success) || !empty($error)) {
        $_SESSION['_flash'] = [
            'type'    => !empty($success) ? 'success' : 'danger',
            'message' => !empty($success) ? $success : $error,
        ];
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . ($qs ? '?' . $qs : ''));
        exit();
    }
}

$search=trim($_GET['q']??'');
$filterType = trim($_GET['type'] ?? 'all');
$validTypes = ['all', 'admin', 'staff', 'teacher', 'leader', 'inactive'];
if (!in_array($filterType, $validTypes, true)) {
    $filterType = 'all';
}
$perPage=15; $page=max(1,intval($_GET['page']??1)); $offset=($page-1)*$perPage;

$baseFrom="FROM users u
    LEFT JOIN teachers t ON t.user_id=u.id
    LEFT JOIN faculties f ON f.id=t.faculty_id
    LEFT JOIN (SELECT user_id,COUNT(*) cnt FROM user_roles GROUP BY user_id) rc ON rc.user_id=u.id
    LEFT JOIN (
        SELECT ur.user_id, COUNT(*) leader_cnt
        FROM user_roles ur
        JOIN roles r ON r.id=ur.role_id
        WHERE r.is_active=1
          AND (r.code LIKE '%_manager' OR r.name LIKE 'Trưởng%' OR r.name LIKE '%lãnh đạo%')
          AND (ur.expires_at IS NULL OR ur.expires_at>NOW())
        GROUP BY ur.user_id
    ) lr ON lr.user_id=u.id";
$baseWhere="WHERE (u.role IN ('admin','staff','teacher') OR rc.cnt>0)";
$typeWhere = '';
if ($filterType === 'admin') {
    $typeWhere = " AND u.role='admin'";
} elseif ($filterType === 'staff') {
    $typeWhere = " AND u.role='staff'";
} elseif ($filterType === 'teacher') {
    $typeWhere = " AND (u.role='teacher' OR t.id IS NOT NULL)";
} elseif ($filterType === 'leader') {
    $typeWhere = " AND COALESCE(lr.leader_cnt,0)>0";
} elseif ($filterType === 'inactive') {
    $typeWhere = " AND u.status=0";
}
$baseWhere .= $typeWhere;
$selectUserFields = "u.id,u.username,u.full_name,u.email,u.phone,u.role,u.status,u.created_at,
    t.teacher_code, t.degree, f.faculty_name, COALESCE(lr.leader_cnt,0) leader_cnt";

if ($search) {
    $like="%$search%";
    $cs=$conn->prepare("SELECT COUNT(*) c $baseFrom $baseWhere AND (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)");
    $cs->bind_param('sss',$like,$like,$like); $cs->execute();
    $total=$cs->get_result()->fetch_assoc()['c']; $cs->close();
    $stmt=$conn->prepare("SELECT $selectUserFields $baseFrom $baseWhere AND (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?) ORDER BY u.created_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param('sssii',$like,$like,$like,$perPage,$offset);
} else {
    $total=$conn->query("SELECT COUNT(*) c $baseFrom $baseWhere")->fetch_assoc()['c'];
    $stmt=$conn->prepare("SELECT $selectUserFields $baseFrom $baseWhere ORDER BY u.created_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param('ii',$perPage,$offset);
}
$stmt->execute(); $users=$stmt->get_result(); $stmt->close();
$totalPages=ceil($total/$perPage);

$allRoles=[]; $rr=$conn->query("SELECT id,name,department,color FROM roles WHERE is_active=1 ORDER BY department,name");
if($rr) while($r=$rr->fetch_assoc()) $allRoles[$r['department']][]=$r;

$viewId=intval($_GET['roles']??0); $viewUser=null; $viewRoles=null;
if ($viewId) {
    $vs=$conn->prepare("SELECT * FROM users WHERE id=?"); $vs->bind_param('i',$viewId); $vs->execute();
    $viewUser=$vs->get_result()->fetch_assoc(); $vs->close();
    if ($viewUser) {
        $viewRoles=$conn->query("SELECT ur.id ur_id,r.name,r.department,r.color,ur.expires_at,ur.note,u2.full_name gby FROM user_roles ur JOIN roles r ON ur.role_id=r.id LEFT JOIN users u2 ON ur.granted_by=u2.id WHERE ur.user_id=$viewId ORDER BY r.department");
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
<div class="admin-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
        <span class="admin-topbar-title">Nhan vien &amp; Phan quyen</span>
    </div>
    <span class="text-muted small"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
</div>
<div class="admin-content">

<?php if($success):?><div class="alert alert-success auto-dismiss alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success;?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif;?>
<?php if($error):?><div class="alert alert-danger auto-dismiss alert-dismissible fade show"><i class="bi bi-exclamation-circle-fill me-2"></i><?php echo $error;?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif;?>

<div class="alert alert-info py-2 small mb-3"><i class="bi bi-info-circle-fill me-2"></i>Trang này quản lý tài khoản hệ thống của nhà trường. Giảng viên vẫn có thể là nhân sự/lãnh đạo nếu được cấp quyền phòng ban hoặc quyền Khoa/Viện.</div>

<?php if($viewUser):?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-shield-lock me-2"></i>Phan quyen cho: <strong><?php echo htmlspecialchars($viewUser['full_name']);?></strong></span>
        <a href="users.php" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-left me-1"></i>Quay lai</a>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-lg-7">
                <h6 class="fw-bold mb-3"><i class="bi bi-key-fill me-2"></i>Quyen dang co</h6>
                <?php if($viewRoles && $viewRoles->num_rows>0):?>
                <table class="table table-sm"><thead><tr><th>Phong ban</th><th>Chuc vu</th><th>Cap boi</th><th>Het han</th><th>Ghi chu</th><th></th></tr></thead><tbody>
                <?php while($ur=$viewRoles->fetch_assoc()):?>
                <tr>
                    <td><small class="text-muted"><?php echo htmlspecialchars($ur['department']);?></small></td>
                    <td><span class="badge" style="background:<?php echo $ur['color'];?>"><?php echo htmlspecialchars($ur['name']);?></span></td>
                    <td><small><?php echo htmlspecialchars($ur['gby']??'System');?></small></td>
                    <td><small class="<?php echo $ur['expires_at']&&strtotime($ur['expires_at'])<time()?'text-danger':'text-muted';?>"><?php echo $ur['expires_at']?date('d/m/Y',strtotime($ur['expires_at'])):'Vinh vien';?></small></td>
                    <td><small><?php echo htmlspecialchars($ur['note']??'');?></small></td>
                    <td><form method="POST" class="d-inline" onsubmit="return confirm('Thu hoi?')"><input type="hidden" name="action" value="revoke"><input type="hidden" name="urid" value="<?php echo $ur['ur_id'];?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></button></form></td>
                </tr>
                <?php endwhile;?>
                </tbody></table>
                <?php else:?><div class="alert alert-info small">Chua co quyen nao.</div><?php endif;?>
            </div>
            <div class="col-lg-5">
                <h6 class="fw-bold mb-3"><i class="bi bi-plus-circle-fill me-2 text-success"></i>Cap quyen moi</h6>
                <form method="POST">
                    <input type="hidden" name="action" value="grant_role">
                    <input type="hidden" name="uid" value="<?php echo $viewId;?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Chon quyen <span class="text-danger">*</span></label>
                        <select name="rid" class="form-select" required>
                            <option value="">-- Chon quyen --</option>
                            <?php foreach($allRoles as $dept=>$roles):?>
                            <optgroup label="<?php echo htmlspecialchars($dept);?>">
                                <?php foreach($roles as $r):?><option value="<?php echo $r['id'];?>"><?php echo htmlspecialchars($r['name']);?></option><?php endforeach;?>
                            </optgroup>
                            <?php endforeach;?>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label fw-semibold">Het han <small class="text-muted">(de trong = vinh vien)</small></label><input type="date" name="exp" class="form-control" min="<?php echo date('Y-m-d');?>"></div>
                    <div class="mb-3"><label class="form-label fw-semibold">Ghi chu</label><input type="text" name="note" class="form-control" placeholder="VD: Phu trach tuyen sinh 2025"></div>
                    <button type="submit" class="btn btn-gold w-100"><i class="bi bi-shield-check me-2"></i>Cap quyen</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif;?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-people-fill me-2"></i>Danh sach Nhan vien (<?php echo $total;?>)</span>
        <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-person-plus-fill me-1"></i>Them nhan vien</button>
    </div>
    <div class="card-body">
        <form method="GET" class="mb-3">
            <div class="d-flex gap-2 flex-wrap align-items-end">
                <div>
                    <label class="form-label small fw-semibold mb-1">Loại tài khoản</label>
                    <select name="type" class="form-select" style="width:190px" onchange="this.form.submit()">
                        <option value="all" <?php echo $filterType==='all'?'selected':''; ?>>Tất cả nhân sự</option>
                        <option value="admin" <?php echo $filterType==='admin'?'selected':''; ?>>Quản trị hệ thống</option>
                        <option value="staff" <?php echo $filterType==='staff'?'selected':''; ?>>Nhân viên phòng ban</option>
                        <option value="teacher" <?php echo $filterType==='teacher'?'selected':''; ?>>Giảng viên</option>
                        <option value="leader" <?php echo $filterType==='leader'?'selected':''; ?>>Lãnh đạo/Quản lý</option>
                        <option value="inactive" <?php echo $filterType==='inactive'?'selected':''; ?>>Tài khoản đã khóa</option>
                    </select>
                </div>
                <div class="flex-grow-1" style="max-width:420px">
                    <label class="form-label small fw-semibold mb-1">Tìm kiếm</label>
                    <div class="input-group">
                        <input type="text" name="q" class="form-control" placeholder="Tìm tên, email, tài khoản..." value="<?php echo htmlspecialchars($search);?>">
                        <button class="btn btn-navy" type="submit"><i class="bi bi-search"></i></button>
                    </div>
                </div>
                <?php if($search || $filterType !== 'all'):?><a href="users.php" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>Xóa lọc</a><?php endif;?>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>#</th><th>Tên đăng nhập</th><th>Họ tên</th><th>Loại</th><th>Email / SĐT</th><th>Quyền phòng ban</th><th>Trạng thái</th><th>Ngày tạo</th><th>Thao tác</th></tr></thead>
                <tbody>
                <?php if($users&&$users->num_rows>0): $idx=$offset+1; while($u=$users->fetch_assoc()):
                    $ur2=$conn->query("SELECT r.name,r.color FROM user_roles ur JOIN roles r ON ur.role_id=r.id WHERE ur.user_id={$u['id']} AND r.is_active=1 AND (ur.expires_at IS NULL OR ur.expires_at>NOW())");
                ?>
                <tr>
                    <td class="text-muted small"><?php echo $idx++;?></td>
                    <td class="fw-bold text-navy"><?php echo htmlspecialchars($u['username']);?></td>
                    <td><?php echo htmlspecialchars($u['full_name']);?></td>
                    <td class="small">
                        <?php if ($u['role'] === 'admin'): ?>
                            <span class="badge bg-dark">Quản trị</span>
                        <?php elseif ($u['role'] === 'teacher' || !empty($u['teacher_code'])): ?>
                            <span class="badge bg-primary">Giảng viên</span>
                            <?php if (!empty($u['degree'])): ?><div class="text-muted mt-1"><?php echo htmlspecialchars($u['degree']); ?></div><?php endif; ?>
                            <?php if (!empty($u['faculty_name'])): ?><div class="text-muted"><?php echo htmlspecialchars($u['faculty_name']); ?></div><?php endif; ?>
                        <?php else: ?>
                            <span class="badge bg-info text-dark">Nhân viên</span>
                        <?php endif; ?>
                        <?php if ((int)$u['leader_cnt'] > 0): ?>
                            <div class="mt-1"><span class="badge bg-warning text-dark">Lãnh đạo/Quản lý</span></div>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted"><?php echo htmlspecialchars($u['email']??'');?><?php if($u['phone']):?><br><?php echo htmlspecialchars($u['phone']);?><?php endif;?></td>
                    <td>
                        <?php if($ur2&&$ur2->num_rows>0): while($r=$ur2->fetch_assoc()):?>
                        <span class="badge me-1" style="background:<?php echo $r['color'];?>;font-size:.7rem"><?php echo htmlspecialchars($r['name']);?></span>
                        <?php endwhile; else:?><span class="text-muted small fst-italic">Chua co quyen</span><?php endif;?>
                    </td>
                    <td><span class="badge bg-<?php echo $u['status']?'success':'secondary';?>"><?php echo $u['status']?'Hoat dong':'Khoa';?></span></td>
                    <td class="text-muted small"><?php echo date('d/m/Y',strtotime($u['created_at']));?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="?roles=<?php echo $u['id'];?>" class="btn btn-sm btn-outline-warning" title="Phan quyen"><i class="bi bi-shield-fill-check"></i></a>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal"
                                data-id="<?php echo $u['id'];?>" data-username="<?php echo htmlspecialchars($u['username']);?>"
                                data-fullname="<?php echo htmlspecialchars($u['full_name']);?>" data-email="<?php echo htmlspecialchars($u['email']??'');?>"
                                data-phone="<?php echo htmlspecialchars($u['phone']??'');?>" title="Sua"><i class="bi bi-pencil-fill"></i></button>
                            <?php if($u['id']!=$_SESSION['user_id']):?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('<?php echo $u['status']?'Khoa tai khoan?':'Mo khoa?';?>')">
                                <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo $u['id'];?>">
                                <button class="btn btn-sm <?php echo $u['status']?'btn-outline-danger':'btn-outline-success';?>" title="<?php echo $u['status']?'Khoa':'Mo khoa';?>"><i class="bi <?php echo $u['status']?'bi-lock-fill':'bi-unlock-fill';?>"></i></button>
                            </form>
                            <?php endif;?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else:?>
                <tr><td colspan="9" class="text-center text-muted py-5"><i class="bi bi-people fs-2 d-block mb-2"></i>Không có tài khoản nào phù hợp</td></tr>
                <?php endif;?>
                </tbody>
            </table>
        </div>
        <?php if($totalPages>1):?>
        <nav><ul class="pagination justify-content-center mt-3">
            <?php if($page>1):?><li class="page-item"><a class="page-link" href="?type=<?php echo urlencode($filterType);?>&q=<?php echo urlencode($search);?>&page=<?php echo $page-1;?>"><i class="bi bi-chevron-left"></i></a></li><?php endif;?>
            <?php for($p=max(1,$page-2);$p<=min($totalPages,$page+2);$p++):?>
            <li class="page-item <?php echo $p==$page?'active':'';?>"><a class="page-link" href="?type=<?php echo urlencode($filterType);?>&q=<?php echo urlencode($search);?>&page=<?php echo $p;?>"><?php echo $p;?></a></li>
            <?php endfor;?>
            <?php if($page<$totalPages):?><li class="page-item"><a class="page-link" href="?type=<?php echo urlencode($filterType);?>&q=<?php echo urlencode($search);?>&page=<?php echo $page+1;?>"><i class="bi bi-chevron-right"></i></a></li><?php endif;?>
        </ul></nav>
        <?php endif;?>
    </div>
</div>

</div>
<div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU</div>
</div>

<!-- Modal Them -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-person-plus-fill me-2"></i>Them Nhan vien moi</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST"><input type="hidden" name="action" value="add">
        <div class="modal-body"><div class="row g-3">
            <div class="col-md-6"><label class="form-label fw-semibold">Ten dang nhap <span class="text-danger">*</span></label><input type="text" name="username" class="form-control" required placeholder="VD: nv.tuyensinh"></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Mat khau <span class="text-danger">*</span></label><input type="text" name="password" class="form-control" value="123456" required></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Ho va ten <span class="text-danger">*</span></label><input type="text" name="full_name" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Email</label><input type="email" name="email" class="form-control"></div>
            <div class="col-md-6"><label class="form-label fw-semibold">So dien thoai</label><input type="text" name="phone" class="form-control"></div>
            <div class="col-12"><div class="alert alert-info py-2 mb-0 small"><i class="bi bi-info-circle me-1"></i>Sau khi tao, dung nut <i class="bi bi-shield-fill-check"></i> de cap quyen phong ban.</div></div>
        </div></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huy</button><button type="submit" class="btn btn-gold"><i class="bi bi-save me-1"></i>Tao tai khoan</button></div>
        </form>
    </div></div>
</div>

<!-- Modal Sua -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil-fill me-2"></i>Sua thong tin Nhan vien</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST"><input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="eId">
        <div class="modal-body"><div class="row g-3">
            <div class="col-12"><label class="form-label text-muted">Ten dang nhap</label><input type="text" id="eUser" class="form-control bg-light" readonly></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Ho va ten <span class="text-danger">*</span></label><input type="text" name="full_name" id="eFull" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Email</label><input type="email" name="email" id="eEmail" class="form-control"></div>
            <div class="col-md-6"><label class="form-label fw-semibold">So dien thoai</label><input type="text" name="phone" id="ePhone" class="form-control"></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Mat khau moi <small class="text-muted">(de trong neu khong doi)</small></label><input type="text" name="new_password" class="form-control" placeholder="Nhap mat khau moi..."></div>
        </div></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huy</button><button type="submit" class="btn btn-gold"><i class="bi bi-save me-1"></i>Luu thay doi</button></div>
        </form>
    </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<script>
document.getElementById('editModal').addEventListener('show.bs.modal',function(e){
    const b=e.relatedTarget;
    document.getElementById('eId').value=b.dataset.id;
    document.getElementById('eUser').value=b.dataset.username;
    document.getElementById('eFull').value=b.dataset.fullname;
    document.getElementById('eEmail').value=b.dataset.email;
    document.getElementById('ePhone').value=b.dataset.phone;
});
</script>
</body></html>

