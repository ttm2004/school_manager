<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireAnyRole(['admissions_manager', 'admissions_staff']);
$pageTitle = 'Thủ tục Nhập học';

$isManager = hasRole('admissions_manager');
$canEnroll = hasPermission('admissions', 'manage_enrollment');
$success = $error = '';
$newAccount = null;

// ── Kiểm tra trạng thái đợt tuyển sinh ──────────────────────
$roundPhase  = getRoundPhase();
$activeRound = getActiveRound();
$roundMsg    = getRoundStatusMessage();

// CHỈ cho phép thao tác nhập học khi đang trong giai đoạn enrolling hoặc supp_enrolling
// Tất cả giai đoạn khác (reviewing, completed, no_round, reg_open...) đều khóa
$enrollAllowed = in_array($roundPhase, ['enrolling', 'supp_enrolling']);
$enrollLocked  = !$enrollAllowed;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'enroll') {
        if (!$canEnroll) {
            $error = 'Bạn không có quyền xác nhận nhập học.';
        } elseif ($enrollLocked) {
            $error = '⚠️ Không thể làm thủ tục nhập học trong giai đoạn này (' . $roundMsg[1] . ')';
        } else {
            $id = intval($_POST['id'] ?? 0);
            if ($id) {
                $stmt = $conn->prepare("UPDATE admission_applications SET status='enrolled' WHERE id=? AND status='approved'");
                $stmt->bind_param('i', $id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    // Redirect sang tab enrolled sau khi nhap hoc thanh cong
                    header('Location: enrollment.php?tab=enrolled&msg=enrolled');
                    exit();
                } else {
                    $error = 'Lỗi cập nhật: ' . $conn->error;
                }
                $stmt->close();
            }
        }
    }

    if ($action === 'cancel_enroll') {
        if (!$isManager) {
            $error = 'Chỉ Trưởng phòng mới có quyền hủy nhập học.';
        } elseif ($enrollLocked) {
            $error = '⚠️ Không thể hủy nhập học trong giai đoạn này.';
        } else {
            $id = intval($_POST['id'] ?? 0);
            if ($id) {
                $stmt = $conn->prepare("UPDATE admission_applications SET status='approved' WHERE id=? AND status='enrolled'");
                $stmt->bind_param('i', $id);
                $stmt->execute() ? $success = 'Đã hủy nhập học.' : $error = $conn->error;
                $stmt->close();
            }
        }
    }

    if ($action === 'create_account') {
        if (!$canEnroll) {
            $error = 'Bạn không có quyền cấp tài khoản.';
        } elseif ($enrollLocked) {
            $error = '⚠️ Không thể cấp tài khoản trong giai đoạn này.';
        } else {
            $app_id = intval($_POST['app_id'] ?? 0);
            $class_id = intval($_POST['class_id'] ?? 0);
            if (!$app_id || !$class_id) {
                $error = 'Vui lòng chọn lớp học.';
            } else {
                $stmt = $conn->prepare("SELECT aa.*, m.major_code FROM admission_applications aa LEFT JOIN majors m ON aa.major_id=m.id WHERE aa.id=? AND aa.status='enrolled'");
                $stmt->bind_param('i', $app_id);
                $stmt->execute();
                $app = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$app) {
                    $error = 'Không tìm thấy hồ sơ.';
                } else {
                    $chk = $conn->prepare('SELECT u.id FROM users u JOIN students s ON u.id=s.user_id WHERE u.email=?');
                    $chk->bind_param('s', $app['email']);
                    $chk->execute();
                    if ($chk->get_result()->num_rows > 0) {
                        $error = 'Email này đã có tài khoản sinh viên.';
                    } else {
                        $year = date('Y', strtotime($app['created_at']));
                        $majorCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $app['major_code'] ?? 'SV'));
                        $attempts = 0;
                        do {
                            $rand = rand(10, 999);
                            $studentCode = $year . $majorCode . $rand;
                            $dup = $conn->query("SELECT id FROM students WHERE student_code='$studentCode'");
                            $attempts++;
                        } while ($dup && $dup->num_rows > 0 && $attempts < 50);

                        $username = strtolower($studentCode);
                        $password = $studentCode;
                        $hashed = password_hash($password, PASSWORD_DEFAULT);
                        $conn->begin_transaction();
                        try {
                            $us = $conn->prepare("INSERT INTO users (username,password,full_name,email,phone,role,status) VALUES (?,?,?,?,?,'student',1)");
                            $us->bind_param('sssss', $username, $hashed, $app['full_name'], $app['email'], $app['phone']);
                            if (!$us->execute())
                                throw new Exception($conn->error);
                            $userId = $conn->insert_id;
                            $us->close();
                            $ss = $conn->prepare('INSERT INTO students (user_id,student_code,class_id,address,birthday,gender) VALUES (?,?,?,?,?,?)');
                            $ss->bind_param('isisss', $userId, $studentCode, $class_id, $app['address'], $app['birthday'], $app['gender']);
                            if (!$ss->execute())
                                throw new Exception($conn->error);
                            $ss->close();
                            $conn->commit();
                            $success = 'Cấp tài khoản thành công!';
                            $newAccount = ['username' => $username, 'password' => $password, 'student_code' => $studentCode, 'full_name' => $app['full_name']];
                        } catch (Exception $e) {
                            $conn->rollback();
                            $error = 'Lỗi: ' . $e->getMessage();
                        }
                    }
                    $chk->close();
                }
            }
        }
    }
}

// Hien thi thong bao redirect
if (isset($_GET['msg']) && $_GET['msg'] === 'enrolled') {
    $success = 'Đã xác nhận nhập học thành công! Nhấn nút <strong>Cấp TK</strong> để tạo tài khoản sinh viên.';
}

// Query AFTER POST processing
$approvedCount = $conn->query("SELECT COUNT(*) as c FROM admission_applications WHERE status='approved'")->fetch_assoc()['c'] ?? 0;
$enrolledCount = $conn->query("SELECT COUNT(*) as c FROM admission_applications WHERE status='enrolled'")->fetch_assoc()['c'] ?? 0;

$tab = $_GET['tab'] ?? 'approved';
$filter_major = intval($_GET['major_id'] ?? 0);
$filter_search = trim($_GET['q'] ?? '');
$statusFilter = $tab === 'enrolled' ? 'enrolled' : 'approved';
$where = ["aa.status='$statusFilter'"];
$params = [];
$types = '';
if ($filter_major) {
    $where[] = 'aa.major_id=?';
    $params[] = $filter_major;
    $types .= 'i';
}
if ($filter_search) {
    $like = "%$filter_search%";
    $where[] = '(aa.full_name LIKE ? OR aa.email LIKE ? OR aa.citizen_id LIKE ?)';
    $params = array_merge($params, [$like, $like, $like]);
    $types .= 'sss';
}
$wSQL = 'WHERE ' . implode(' AND ', $where);
$perPage = 15;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;
$cSQL = "SELECT COUNT(*) as c FROM admission_applications aa $wSQL";
if ($params) {
    $cs = $conn->prepare($cSQL);
    $cs->bind_param($types, ...$params);
    $cs->execute();
    $total = $cs->get_result()->fetch_assoc()['c'];
    $cs->close();
} else {
    $total = $conn->query($cSQL)->fetch_assoc()['c'];
}
$totalPages = ceil($total / $perPage);
$dSQL = "SELECT aa.*, m.major_name, m.major_code, am.method_name,
    (aa.math_score+aa.literature_score+aa.english_score) as total_score,
    (SELECT u.id FROM users u JOIN students s ON u.id=s.user_id WHERE u.email=aa.email LIMIT 1) as has_account
    FROM admission_applications aa
    LEFT JOIN majors m ON aa.major_id=m.id
    LEFT JOIN admission_methods am ON aa.method_id=am.id
    $wSQL ORDER BY aa.created_at DESC LIMIT ? OFFSET ?";
$allP = array_merge($params, [$perPage, $offset]);
$allT = $types . 'ii';
$stmt = $conn->prepare($dSQL);
$stmt->bind_param($allT, ...$allP);
$stmt->execute();
$applications = $stmt->get_result();
$stmt->close();
$majors = $conn->query('SELECT id, major_name FROM majors ORDER BY major_name');
$classes = $conn->query('SELECT c.id, c.class_name, c.class_code, c.school_year, m.major_name, m.id as major_id FROM classes c LEFT JOIN majors m ON c.major_id=m.id ORDER BY c.class_name');
$classArr = [];
if ($classes)
    while ($cl = $classes->fetch_assoc())
        $classArr[] = $cl;

include __DIR__ . '/includes/header.php';
?>

<?php
// Thông báo sau khi redirect
if (isset($_GET['msg']) && $_GET['msg'] === 'enrolled') {
    echo '<div class="alert alert-success auto-dismiss alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i>Đã xác nhận nhập học thành công! Nhấn <strong>"Cấp TK"</strong> để tạo tài khoản sinh viên.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}
?>

<?php if ($newAccount): ?>
<div class="alert alert-success border-0 mb-3" style="background:linear-gradient(135deg,#d1fae5,#a7f3d0);">
    <h6 class="fw-bold mb-2"><i class="bi bi-person-check-fill me-2"></i>Cấp tài khoản thành công!</h6>
    <div class="row g-2">
        <div class="col-md-3"><div class="text-muted small">Họ tên</div><div class="fw-bold"><?php echo htmlspecialchars($newAccount['full_name']); ?></div></div>
        <div class="col-md-3"><div class="text-muted small">Mã sinh viên</div><div class="fw-bold text-navy"><?php echo htmlspecialchars($newAccount['student_code']); ?></div></div>
        <div class="col-md-3"><div class="text-muted small">Tên đăng nhập</div><div class="fw-bold"><?php echo htmlspecialchars($newAccount['username']); ?></div></div>
        <div class="col-md-3"><div class="text-muted small">Mật khẩu</div><div class="fw-bold text-danger"><?php echo htmlspecialchars($newAccount['password']); ?></div></div>
    </div>
    <div class="mt-2 small text-muted"><i class="bi bi-info-circle me-1"></i>Thông báo cho sinh viên. Mật khẩu = Mã sinh viên, có thể đổi sau khi đăng nhập.</div>
</div>
<?php endif; ?>
<?php if ($success && !$newAccount): ?><div class="alert alert-success auto-dismiss alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger auto-dismiss alert-dismissible fade show"><i class="bi bi-exclamation-circle-fill me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<!-- Banner trạng thái đợt tuyển sinh — hiển thị qua modal nổi khi load trang -->
<?php
$_modalType = $enrollLocked ? 'danger' : 'success';
$_modalIcon = $enrollLocked ? 'bi-lock-fill' : 'bi-unlock-fill';
$_modalTitle = $enrollLocked ? 'Trang chỉ xem' : 'Giai đoạn nhập học';
if ($enrollLocked) {
    $lockReasons = [
        'no_round'       => 'Không có đợt tuyển sinh nào đang hoạt động. Vui lòng tạo và kích hoạt đợt tuyển sinh.',
        'before_reg'     => 'Chưa đến thời gian nhận hồ sơ.',
        'reg_open'       => 'Đang trong thời gian nhận hồ sơ — chưa đến giai đoạn nhập học.',
        'reviewing'      => 'Đang trong giai đoạn xét tuyển — chưa có kết quả chính thức.',
        'supp_reviewing' => 'Đang trong giai đoạn xét tuyển bổ sung.',
        'after_enroll'   => 'Đã hết hạn nhập học chính thức.',
        'completed'      => 'Đợt tuyển sinh đã hoàn tất.',
    ];
    $_modalBody = ($lockReasons[$roundPhase] ?? 'Không trong giai đoạn nhập học.') .
        ' Các tác vụ nhập học chỉ khả dụng khi đợt tuyển sinh ở trạng thái <strong>Đang nhập học</strong> hoặc <strong>Đợt bổ sung</strong>.';
    $_modalAction = '<a href="rounds.php" class="btn btn-sm btn-outline-danger mt-2"><i class="bi bi-calendar-range me-1"></i>Quản lý đợt tuyển sinh</a>';
} else {
    $_modalBody = ($roundPhase === 'supp_enrolling'
        ? 'Đang trong giai đoạn nhập học <strong>bổ sung</strong>.'
        : 'Đang trong giai đoạn nhập học chính thức.') .
        ($activeRound ? ' Hạn: <strong>' . date('d/m/Y H:i', strtotime($roundPhase === 'supp_enrolling' ? $activeRound['supp_enroll_deadline'] : $activeRound['enroll_deadline'])) . '</strong>' : '');
    $_modalAction = '';
}
if (!$isManager) {
    $_modalBody .= '<hr class="my-2"><small class="text-muted"><i class="bi bi-info-circle me-1"></i><strong>Quyền của bạn (Nhân viên):</strong> Xác nhận nhập học, cấp tài khoản sinh viên. Các chức năng quản lý khác chỉ dành cho Trưởng phòng.</small>';
}
?>
<!-- Modal thông báo trạng thái -->
<div class="modal fade" id="statusModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
        <div class="modal-content border-0 shadow-lg overflow-hidden">
            <div class="modal-header border-0 pb-0" style="background:<?php echo $enrollLocked ? 'rgba(239,68,68,.08)' : 'rgba(16,185,129,.08)'; ?>">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:42px;height:42px;border-radius:50%;background:<?php echo $enrollLocked ? 'rgba(239,68,68,.15)' : 'rgba(16,185,129,.15)'; ?>;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">
                        <i class="bi <?php echo $_modalIcon; ?>" style="color:<?php echo $enrollLocked ? '#dc2626' : '#059669'; ?>;"></i>
                    </div>
                    <h5 class="modal-title fw-bold mb-0" style="color:<?php echo $enrollLocked ? '#dc2626' : '#059669'; ?>;"><?php echo $_modalTitle; ?></h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" id="statusModalClose"></button>
            </div>
            <div class="modal-body pt-3">
                <p class="mb-0" style="font-size:.9rem;line-height:1.6;"><?php echo $_modalBody; ?></p>
                <?php echo $_modalAction; ?>
                <div class="mt-3 d-flex align-items-center gap-2">
                    <div class="progress flex-grow-1" style="height:4px;border-radius:4px;">
                        <div id="statusModalProgress" class="progress-bar" style="width:100%;background:<?php echo $enrollLocked ? '#dc2626' : '#059669'; ?>;transition:width linear;"></div>
                    </div>
                    <small class="text-muted" id="statusModalCountdown" style="font-size:.72rem;white-space:nowrap;"></small>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('statusModal'));
    modal.show();
    const duration = <?php echo $enrollLocked ? 8000 : 5000; ?>;
    const bar = document.getElementById('statusModalProgress');
    const countdown = document.getElementById('statusModalCountdown');
    let remaining = duration / 1000;
    countdown.textContent = remaining + 's';
    const tick = setInterval(function() {
        remaining--;
        countdown.textContent = remaining + 's';
        if (remaining <= 0) { clearInterval(tick); modal.hide(); }
    }, 1000);
    requestAnimationFrame(function() {
        requestAnimationFrame(function() {
            bar.style.transitionDuration = duration + 'ms';
            bar.style.width = '0%';
        });
    });
    document.getElementById('statusModalClose').addEventListener('click', function() {
        clearInterval(tick);
    });
});
</script>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card" style="border-left:4px solid #059669">
            <div class="ic" style="background:rgba(16,185,129,.12);color:#059669"><i class="bi bi-check-circle-fill"></i></div>
            <div class="vl"><?php echo $approvedCount; ?></div>
            <div class="lb">Đã duyệt — chờ nhập học</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card" style="border-left:4px solid #0d2d6b">
            <div class="ic" style="background:rgba(13,45,107,.1);color:#0d2d6b"><i class="bi bi-person-check-fill"></i></div>
            <div class="vl"><?php echo $enrolledCount; ?></div>
            <div class="lb">Đã nhập học</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card" style="border-left:4px solid #f5a623">
            <div class="ic" style="background:rgba(245,166,35,.15);color:#d4891a"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="vl"><?php echo ($approvedCount + $enrolledCount) > 0 ? round($enrolledCount / ($approvedCount + $enrolledCount) * 100) : 0; ?>%</div>
            <div class="lb">Tỷ lệ nhập học</div>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="card">
    <div class="card-header p-0">
        <ul class="nav nav-tabs border-0">
            <li class="nav-item">
                <a class="nav-link <?php echo $tab != 'enrolled' ? 'active' : ''; ?> rounded-0 border-0 px-4 py-3" href="?tab=approved">
                    <i class="bi bi-clock-history me-1"></i>Chờ nhập học
                    <span class="badge bg-success ms-1"><?php echo $approvedCount; ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $tab == 'enrolled' ? 'active' : ''; ?> rounded-0 border-0 px-4 py-3" href="?tab=enrolled">
                    <i class="bi bi-person-check me-1"></i>Đã nhập học
                    <span class="badge bg-navy ms-1"><?php echo $enrolledCount; ?></span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Filter -->
    <div class="card-body border-bottom py-2">
        <form method="GET" class="d-flex gap-2 flex-wrap align-items-end">
            <input type="hidden" name="tab" value="<?php echo $tab; ?>">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Tìm tên, email, CCCD..." value="<?php echo htmlspecialchars($filter_search); ?>" style="width:220px">
            <select name="major_id" class="form-select form-select-sm" style="width:200px">
                <option value="">Tất cả ngành</option>
                <?php if ($majors):
                    while ($mj = $majors->fetch_assoc()): ?>
                <option value="<?php echo $mj['id']; ?>" <?php echo $filter_major == $mj['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($mj['major_name']); ?></option>
                <?php endwhile;
                endif; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-navy"><i class="bi bi-search me-1"></i>Lọc</button>
            <?php if ($filter_major || $filter_search): ?><a href="?tab=<?php echo $tab; ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x me-1"></i>Xóa lọc</a><?php endif; ?>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr>
                    <th>#</th><th>Họ tên</th><th>Ngành</th><th>Phương thức</th><th>Tổng điểm</th><th>Ngày nộp</th>
                    <?php if ($tab === 'enrolled'): ?><th>Tài khoản SV</th><?php endif; ?>
                    <th>Thao tác</th>
                </tr></thead>
                <tbody>
                <?php if ($applications && $applications->num_rows > 0):
                    $idx = $offset + 1;
                    while ($app = $applications->fetch_assoc()): ?>
                <tr>
                    <td class="text-muted small"><?php echo $idx++; ?></td>
                    <td>
                        <div class="fw-semibold small"><?php echo htmlspecialchars($app['full_name']); ?></div>
                        <div class="text-muted" style="font-size:.72rem"><?php echo htmlspecialchars($app['email']); ?></div>
                        <?php if ($app['phone']): ?><div class="text-muted" style="font-size:.72rem"><i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($app['phone']); ?></div><?php endif; ?>
                    </td>
                    <td class="small text-muted"><?php echo htmlspecialchars($app['major_name'] ?? '--'); ?></td>
                    <td class="small text-muted"><?php echo mb_substr($app['method_name'] ?? '--', 0, 20); ?></td>
                    <td class="fw-bold text-success"><?php echo number_format($app['total_score'] ?? 0, 2); ?></td>
                    <td class="text-muted small"><?php echo date('d/m/Y', strtotime($app['created_at'])); ?></td>
                    <?php if ($tab === 'enrolled'): ?>
                    <td>
                        <?php if ($app['has_account']): ?>
                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Đã cấp</span>
                        <?php else: ?>
                        <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Chưa cấp</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td>
                        <?php if ($tab !== 'enrolled'): ?>
                            <?php if ($canEnroll && !$enrollLocked): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Xác nhận nhập học cho <?php echo htmlspecialchars(addslashes($app['full_name'])); ?>?')">
                                <input type="hidden" name="action" value="enroll">
                                <input type="hidden" name="id" value="<?php echo $app['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-person-check-fill me-1"></i>Nhập học</button>
                            </form>
                            <?php elseif ($enrollLocked): ?>
                            <span class="text-muted small"><i class="bi bi-lock me-1"></i>Bị khóa</span>
                            <?php else: ?>
                            <span class="text-muted small"><i class="bi bi-lock me-1"></i>Không có quyền</span>
                            <?php endif; ?>
                        <?php else: ?>
                        <div class="d-flex gap-1 flex-wrap">
                            <?php if (!$app['has_account'] && $canEnroll && !$enrollLocked): ?>
                            <button class="btn btn-sm btn-gold" onclick="openAccountModal(<?php echo $app['id']; ?>,'<?php echo htmlspecialchars(addslashes($app['full_name'])); ?>',<?php echo intval($app['major_id']); ?>)">
                                <i class="bi bi-person-plus-fill me-1"></i>Cấp TK
                            </button>
                            <?php elseif ($app['has_account']): ?>
                            <span class="btn btn-sm btn-outline-success disabled"><i class="bi bi-check2 me-1"></i>Đã cấp TK</span>
                            <?php elseif ($enrollLocked): ?>
                            <span class="text-muted small"><i class="bi bi-lock me-1"></i>Bị khóa</span>
                            <?php endif; ?>
                            <?php if ($isManager && !$enrollLocked): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Hủy nhập học? Tài khoản sinh viên sẽ không bị xóa.')">
                                <input type="hidden" name="action" value="cancel_enroll">
                                <input type="hidden" name="id" value="<?php echo $app['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Hủy nhập học (chỉ Trưởng phòng)"><i class="bi bi-x-circle"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile;
                else: ?>
                <tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Không có hồ sơ nào</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-body border-top d-flex justify-content-between align-items-center">
        <small class="text-muted">Hiển thị <?php echo $offset + 1; ?>–<?php echo min($offset + $perPage, $total); ?> / <?php echo $total; ?></small>
        <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
            <li class="page-item <?php echo $p == $page ? 'active' : ''; ?>"><a class="page-link" href="?tab=<?php echo $tab; ?>&major_id=<?php echo $filter_major; ?>&q=<?php echo urlencode($filter_search); ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a></li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Cấp Tài Khoản -->
<div class="modal fade" id="accountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-person-plus-fill me-2"></i>Cấp tài khoản Sinh viên</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <input type="hidden" name="action" value="create_account">
                <input type="hidden" name="app_id" id="modalAppId">
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3"><i class="bi bi-person me-1"></i>Sinh viên: <strong id="modalStudentName"></strong></div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Chọn lớp <span class="text-danger">*</span></label>
                        <select name="class_id" id="modalClassSelect" class="form-select" required>
                            <option value="">-- Chọn lớp --</option>
                        </select>
                        <div class="form-text">Chỉ hiển thị lớp cùng ngành với sinh viên.</div>
                    </div>
                    <div class="alert alert-warning py-2 small">
                        <i class="bi bi-key me-1"></i><strong>Mã SV tự động:</strong> Năm + Mã ngành + 3 số ngẫu nhiên (VD: 2024CNTT456)<br>
                        <i class="bi bi-lock me-1"></i><strong>Mật khẩu mặc định</strong> = Mã sinh viên.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-gold"><i class="bi bi-save me-1"></i>Cấp tài khoản</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<script>
const allClasses = <?php echo json_encode($classArr, JSON_UNESCAPED_UNICODE); ?>;
function openAccountModal(appId, name, majorId) {
    document.getElementById('modalAppId').value = appId;
    document.getElementById('modalStudentName').textContent = name;
    const sel = document.getElementById('modalClassSelect');
    sel.innerHTML = '<option value="">-- Chọn lớp --</option>';
    allClasses.forEach(function(cl) {
        if (!majorId || parseInt(cl.major_id) === parseInt(majorId)) {
            const opt = document.createElement('option');
            opt.value = cl.id;
            opt.textContent = cl.class_name + (cl.school_year ? ' (' + cl.school_year + ')' : '');
            sel.appendChild(opt);
        }
    });
    if (sel.options.length <= 1) {
        // Neu khong co lop cung nganh, hien tat ca
        allClasses.forEach(function(cl) {
            const opt = document.createElement('option');
            opt.value = cl.id;
            opt.textContent = cl.class_name + ' — ' + (cl.major_name||'') + (cl.school_year ? ' (' + cl.school_year + ')' : '');
            sel.appendChild(opt);
        });
    }
    new bootstrap.Modal(document.getElementById('accountModal')).show();
}
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>