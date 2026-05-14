<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../app/Services/AdmissionsEnrollmentService.php';
requireAnyRole(['admissions_manager', 'admissions_staff']);
$pageTitle = 'Thu tuc Nhap hoc';
include __DIR__ . '/../includes/header.php';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Buoc 1: Xac nhan nhap hoc (approved -> enrolled)
    if ($action === 'enroll') {
        if (!hasPermission('admissions', 'manage_enrollment')) {
            $error = 'Ban khong co quyen xac nhan nhap hoc.';
        } else {
            $id = intval($_POST['id'] ?? 0);
            if ($id) {
                $stmt = $conn->prepare("UPDATE admission_applications SET status='enrolled' WHERE id=? AND status='approved'");
                $stmt->bind_param('i', $id);
                $stmt->execute() ? $success = 'Da xac nhan nhap hoc! Vui long cap tai khoan cho sinh vien.' : $error = 'Loi: ' . $conn->error;
                $stmt->close();
            }
        }
    }

    // Bulk enroll
    if ($action === 'bulk_enroll') {
        if (!hasPermission('admissions', 'manage_enrollment')) {
            $error = 'Ban khong co quyen xac nhan nhap hoc.';
        } else {
            $ids = $_POST['ids'] ?? []; $count = 0;
            foreach ($ids as $id) {
                $id = intval($id);
                if ($id) {
                    $upd = $conn->prepare("UPDATE admission_applications SET status='enrolled' WHERE id=? AND status='approved'");
                    $upd->bind_param('i', $id); $upd->execute(); $upd->close(); $count++;
                }
            }
            $success = "Da xac nhan nhap hoc cho <strong>$count</strong> thi sinh. Vui long cap tai khoan cho tung sinh vien.";
        }
    }

    // Huy nhap hoc
    if ($action === 'cancel_enroll') {
        if (!hasPermission('admissions', 'manage_enrollment')) {
            $error = 'Ban khong co quyen huy nhap hoc.';
        } else {
            $id = intval($_POST['id'] ?? 0);
            if ($id) {
                // Xoa user va student neu da tao
                $appRow = $conn->query("SELECT * FROM admission_applications WHERE id=$id")->fetch_assoc();
                if ($appRow) {
                    $stmt = $conn->prepare("SELECT id FROM users WHERE email=? AND role='student'");
                    $stmt->bind_param('s', $appRow['email']);
                    $stmt->execute();
                    $uCheck = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($uCheck) {
                        $uid = $uCheck['id'];
                        $conn->query("DELETE FROM students WHERE user_id=$uid");
                        $conn->query("DELETE FROM users WHERE id=$uid");
                    }
                }
                $stmt = $conn->prepare("UPDATE admission_applications SET status='approved' WHERE id=? AND status='enrolled'");
                $stmt->bind_param('i', $id);
                $stmt->execute() ? $success = 'Da huy nhap hoc.' : $error = 'Loi: ' . $conn->error;
                $stmt->close();
            }
        }
    }

    // Buoc 2: Cap tai khoan sinh vien
    if ($action === 'create_account') {
        if (!hasPermission('admissions', 'manage_enrollment')) {
            $error = 'Ban khong co quyen cap tai khoan.';
        } else {
            $app_id      = intval($_POST['app_id'] ?? 0);
            $class_id    = intval($_POST['class_id'] ?? 0);
            $student_code = trim($_POST['student_code'] ?? '');
            $username    = trim($_POST['username'] ?? '');
            $password    = trim($_POST['password'] ?? '');
            $autoEnrollMode = ($_POST['auto_enroll_mode'] ?? 'system') === 'test' ? 'test' : 'system';

            if (!$app_id || !$class_id || !$student_code) {
                $error = 'Vui long dien day du thong tin.';
            } else {
                // Lay thong tin ho so
                $appStmt = $conn->prepare("SELECT * FROM admission_applications WHERE id=? AND status='enrolled'");
                $appStmt->bind_param('i', $app_id); $appStmt->execute();
                $app = $appStmt->get_result()->fetch_assoc(); $appStmt->close();

                if (!$app) {
                    $error = 'Khong tim thay ho so hoac ho so chua duoc xac nhan nhap hoc.';
                } else {
                    $classContext = AdmissionsEnrollmentService::getClassAcademicContext($conn, $class_id);
                    if (!$classContext) {
                        $error = 'Lop hanh chinh khong hop le.';
                    } else {
                    $enrollmentYear = (int)($classContext['enrollment_year'] ?: date('Y', strtotime($app['created_at'] ?? 'now')));
                    if ($enrollmentYear >= 2000 && preg_match('/^\d{4}/', $student_code)) {
                        $student_code = $enrollmentYear . substr($student_code, 4);
                    }
                    $username = strtolower($student_code);
                    $password = $student_code;

                    // Kiem tra username trung
                    $chk = $conn->prepare("SELECT id FROM users WHERE username=?");
                    $chk->bind_param('s', $username); $chk->execute();
                    if ($chk->get_result()->num_rows > 0) {
                        $error = 'Ten dang nhap da ton tai, vui long chon ten khac.';
                    } else {
                        $conn->begin_transaction();
                        try {
                            $hashed = password_hash($password, PASSWORD_DEFAULT);
                            // Tao user
                            $uStmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, phone, role, status) VALUES (?,?,?,?,?,'student',1)");
                            $uStmt->bind_param('sssss', $username, $hashed, $app['full_name'], $app['email'], $app['phone']);
                            if (!$uStmt->execute()) throw new Exception('Loi tao tai khoan: ' . $conn->error);
                            $userId = $conn->insert_id;
                            $uStmt->close();

                            $studentId = AdmissionsEnrollmentService::createStudentProfile($conn, $userId, $student_code, $class_id, $app, $classContext, $autoEnrollMode);
                            AdmissionsEnrollmentService::notifyFinanceNewEnrollment($conn, $studentId, $student_code, $app['full_name']);

                            $conn->commit();
                            $success = "Cap tai khoan thanh cong! MSSV: <strong>$student_code</strong> | TK: <strong>$username</strong> | MK: <strong>$password</strong>";
                        } catch (Exception $e) {
                            $conn->rollback();
                            $error = $e->getMessage();
                        }
                    }
                    $chk->close();
                    }
                }
            }
        }
    }
}

// Stats
$filter_mode = trim($_GET['mode'] ?? 'system');
if (!in_array($filter_mode, ['system','test'], true)) $filter_mode = 'system';
$modeSql = $filter_mode === 'all' ? '1=1' : "data_mode='" . $conn->real_escape_string($filter_mode) . "'";
$approvedCount = $conn->query("SELECT COUNT(*) as c FROM admission_applications WHERE status='approved' AND $modeSql")->fetch_assoc()['c'] ?? 0;
$enrolledCount = $conn->query("SELECT COUNT(*) as c FROM admission_applications WHERE status='enrolled' AND $modeSql")->fetch_assoc()['c'] ?? 0;
// Dem so sinh vien da co tai khoan (da cap)
$accountedCount = $conn->query("
    SELECT COUNT(*) as c FROM admission_applications aa
    JOIN users u ON aa.email = u.email AND u.role='student'
    WHERE aa.status='enrolled' AND $modeSql
")->fetch_assoc()['c'] ?? 0;

$tab          = $_GET['tab'] ?? 'approved';
$filter_major = intval($_GET['major_id'] ?? 0);
$filter_search = trim($_GET['q'] ?? '');
$perPage = 15; $page = max(1, intval($_GET['page'] ?? 1)); $offset = ($page-1)*$perPage;

$statusFilter = $tab === 'enrolled' ? 'enrolled' : 'approved';
$where = ["aa.status = '$statusFilter'"];
$params = []; $types = '';
if ($filter_mode !== 'all') { $where[] = 'aa.data_mode=?'; $params[] = $filter_mode; $types .= 's'; }
if ($filter_major)  { $where[] = 'aa.major_id=?'; $params[] = $filter_major; $types .= 'i'; }
if ($filter_search) { $where[] = '(aa.full_name LIKE ? OR aa.email LIKE ? OR aa.citizen_id LIKE ?)'; $like = "%$filter_search%"; $params = array_merge($params, [$like,$like,$like]); $types .= 'sss'; }
$whereSQL = 'WHERE ' . implode(' AND ', $where);

$cSQL = "SELECT COUNT(*) as c FROM admission_applications aa $whereSQL";
if ($params) { $cs=$conn->prepare($cSQL); $cs->bind_param($types,...$params); $cs->execute(); $total=$cs->get_result()->fetch_assoc()['c']; $cs->close(); }
else { $total=$conn->query($cSQL)->fetch_assoc()['c']; }
$totalPages = ceil($total/$perPage);

$dSQL = "SELECT aa.*, m.major_name, am.method_name,
    (aa.math_score + aa.literature_score + aa.english_score) as total_score
    FROM admission_applications aa
    LEFT JOIN majors m ON aa.major_id = m.id
    LEFT JOIN admission_methods am ON aa.method_id = am.id
    $whereSQL ORDER BY aa.created_at DESC LIMIT ? OFFSET ?";
$allP = array_merge($params, [$perPage, $offset]);
$allT = $types . 'ii';
$stmt = $conn->prepare($dSQL); $stmt->bind_param($allT, ...$allP); $stmt->execute();
$applications = $stmt->get_result(); $stmt->close();

$majors  = $conn->query("SELECT id, major_name FROM majors ORDER BY major_name");
$classes = $conn->query("SELECT c.id, c.class_name, c.class_code, c.school_year, COALESCE(c.enrollment_year, tc.enrollment_year) AS enrollment_year, m.major_name FROM classes c LEFT JOIN majors m ON c.major_id=m.id LEFT JOIN training_cohorts tc ON c.cohort_id=tc.id ORDER BY c.school_year DESC, c.class_name");
?>

<?php if ($success): ?><div class="alert alert-success auto-dismiss alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger auto-dismiss alert-dismissible fade show"><i class="bi bi-exclamation-circle-fill me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card text-center p-3" style="border-left:4px solid #28a745;">
            <div class="fs-2 fw-bold text-success"><?php echo $approvedCount; ?></div>
            <div class="text-muted small"><i class="bi bi-check-circle-fill text-success me-1"></i>Cho nhap hoc</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center p-3" style="border-left:4px solid var(--navy);">
            <div class="fs-2 fw-bold text-navy"><?php echo $enrolledCount; ?></div>
            <div class="text-muted small"><i class="bi bi-person-check-fill me-1"></i>Da nhap hoc</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center p-3" style="border-left:4px solid var(--gold);">
            <div class="fs-2 fw-bold" style="color:var(--gold-dark);"><?php echo $accountedCount; ?></div>
            <div class="text-muted small"><i class="bi bi-person-badge-fill me-1"></i>Da cap tai khoan</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center p-3" style="border-left:4px solid #dc3545;">
            <div class="fs-2 fw-bold text-danger"><?php echo $enrolledCount - $accountedCount; ?></div>
            <div class="text-muted small"><i class="bi bi-person-x-fill me-1"></i>Chua cap tai khoan</div>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="card">
    <div class="card-header p-0">
        <ul class="nav nav-tabs border-0">
            <li class="nav-item">
                <a class="nav-link <?php echo $tab!='enrolled'?'active':''; ?> rounded-0 border-0 px-4 py-3" href="?tab=approved&mode=<?php echo urlencode($filter_mode); ?>">
                    <i class="bi bi-clock-history me-1"></i>Cho nhap hoc
                    <span class="badge bg-success ms-1"><?php echo $approvedCount; ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $tab=='enrolled'?'active':''; ?> rounded-0 border-0 px-4 py-3" href="?tab=enrolled&mode=<?php echo urlencode($filter_mode); ?>">
                    <i class="bi bi-person-check me-1"></i>Da nhap hoc — Cap tai khoan
                    <span class="badge bg-navy ms-1"><?php echo $enrolledCount; ?></span>
                    <?php if ($enrolledCount - $accountedCount > 0): ?>
                    <span class="badge bg-danger ms-1"><?php echo $enrolledCount - $accountedCount; ?> chua cap</span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>
    </div>

    <!-- Filter -->
    <div class="card-body border-bottom py-2">
        <form method="GET" class="d-flex gap-2 flex-wrap align-items-end">
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
            <select name="mode" class="form-select form-select-sm" style="width:150px">
                <option value="system" <?php echo $filter_mode==='system'?'selected':''; ?>>Dữ liệu thật</option>
                <option value="test" <?php echo $filter_mode==='test'?'selected':''; ?>>Test / Demo</option>
                            </select>
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Tim ten, email, CCCD..." value="<?php echo htmlspecialchars($filter_search); ?>" style="width:220px">
            <select name="major_id" class="form-select form-select-sm" style="width:200px">
                <option value="">Tất cả nganh</option>
                <?php if ($majors): while ($mj=$majors->fetch_assoc()): ?>
                <option value="<?php echo $mj['id']; ?>" <?php echo $filter_major==$mj['id']?'selected':''; ?>><?php echo htmlspecialchars($mj['major_name']); ?></option>
                <?php endwhile; endif; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-navy"><i class="bi bi-search me-1"></i>Loc</button>
            <?php if ($filter_major || $filter_search || $filter_mode !== 'system'): ?>
            <a href="?tab=<?php echo $tab; ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x me-1"></i>Xoa loc</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card-body p-0">
        <?php if ($tab !== 'enrolled'): ?>
        <form method="POST" id="enrollForm">
            <div class="p-2 border-bottom d-flex gap-2">
                <?php if (hasPermission('admissions','manage_enrollment')): ?>
                <button type="submit" name="action" value="bulk_enroll" class="btn btn-sm btn-success" onclick="return confirmEnroll()">
                    <i class="bi bi-person-check-fill me-1"></i>Xac nhan nhap hoc (da chon)
                </button>
                <?php endif; ?>
                <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" onclick="toggleAll()">Chon Tất cả</button>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <?php if ($tab !== 'enrolled'): ?><th width="30"><input type="checkbox" id="checkAll"></th><?php endif; ?>
                        <th>#</th><th>Ho ten</th><th>Nganh</th><th>Tong diem</th>
                        <?php if ($tab === 'enrolled'): ?><th>Tai khoan SV</th><?php endif; ?>
                        <th>Thao tac</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($applications && $applications->num_rows > 0): $idx=$offset+1; while ($app=$applications->fetch_assoc()):
                    // Kiem tra da co tai khoan chua
                    $hasAccount = false; $studentInfo = null;
                    if ($tab === 'enrolled') {
                        $uStmt = $conn->prepare("SELECT u.username, s.student_code FROM users u JOIN students s ON u.id=s.user_id WHERE u.email=? AND u.role='student' LIMIT 1");
                        $uStmt->bind_param('s', $app['email']);
                        $uStmt->execute();
                        $uCheck = $uStmt->get_result()->fetch_assoc();
                        $uStmt->close();
                        $hasAccount = !empty($uCheck);
                        $studentInfo = $uCheck;
                    }
                ?>
                <tr class="<?php echo ($tab==='enrolled' && !$hasAccount) ? 'table-warning' : ''; ?>">
                    <?php if ($tab !== 'enrolled'): ?>
                    <td><input type="checkbox" name="ids[]" value="<?php echo $app['id']; ?>" class="app-check"></td>
                    <?php endif; ?>
                    <td class="text-muted small"><?php echo $idx++; ?></td>
                    <td>
                        <div class="fw-bold small"><?php echo htmlspecialchars($app['full_name']); ?></div>
                        <div class="text-muted" style="font-size:.75rem"><?php echo htmlspecialchars($app['email']); ?></div>
                        <?php if ($app['phone']): ?><div class="text-muted" style="font-size:.75rem"><i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($app['phone']); ?></div><?php endif; ?>
                    </td>
                    <td class="small text-muted"><?php echo htmlspecialchars($app['major_name']??'--'); ?></td>
                    <td class="fw-bold text-success"><?php echo number_format($app['total_score']??0,2); ?></td>
                    <?php if ($tab === 'enrolled'): ?>
                    <td>
                        <?php if ($hasAccount): ?>
                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i><?php echo htmlspecialchars($studentInfo['student_code']); ?></span><br>
                        <small class="text-muted">@<?php echo htmlspecialchars($studentInfo['username']); ?></small>
                        <?php else: ?>
                        <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i>Chua cap</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td>
                        <?php if ($tab !== 'enrolled'): ?>
                        <?php if (hasPermission('admissions','manage_enrollment')): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Xac nhan nhap hoc cho <?php echo htmlspecialchars(addslashes($app['full_name'])); ?>?')">
                            <input type="hidden" name="action" value="enroll">
                            <input type="hidden" name="id" value="<?php echo $app['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-person-check-fill me-1"></i>Nhap hoc</button>
                        </form>
                        <?php endif; ?>
                        <?php else: ?>
                        <div class="d-flex gap-1 flex-wrap">
                            <?php if (!$hasAccount && hasPermission('admissions','manage_enrollment')): ?>
                            <button class="btn btn-sm btn-navy" onclick="openAccountModal(<?php echo htmlspecialchars(json_encode([
                                'id'       => $app['id'],
                                'name'     => $app['full_name'],
                                'email'    => $app['email'],
                                'major'    => $app['major_name'] ?? '',
                                'major_id' => $app['major_id'],
                            ])); ?>)">
                                <i class="bi bi-person-badge me-1"></i>Cap tai khoan
                            </button>
                            <?php endif; ?>
                            <?php if (hasPermission('admissions','manage_enrollment')): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Huy nhap hoc? Tai khoan sinh vien se bi xoa!')">
                                <input type="hidden" name="action" value="cancel_enroll">
                                <input type="hidden" name="id" value="<?php echo $app['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="<?php echo $tab!='enrolled'?6:7; ?>" class="text-center text-muted py-5">
                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>Khong co ho so nao
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($tab !== 'enrolled'): ?></form><?php endif; ?>

        <?php if ($totalPages > 1): ?>
        <nav class="p-3"><ul class="pagination justify-content-center mb-0">
            <?php if ($page>1): ?><li class="page-item"><a class="page-link" href="?tab=<?php echo $tab; ?>&major_id=<?php echo $filter_major; ?>&q=<?php echo urlencode($filter_search); ?>&page=<?php echo $page-1; ?>"><i class="bi bi-chevron-left"></i></a></li><?php endif; ?>
            <?php for ($p=max(1,$page-2);$p<=min($totalPages,$page+2);$p++): ?>
            <li class="page-item <?php echo $p==$page?'active':''; ?>"><a class="page-link" href="?tab=<?php echo $tab; ?>&major_id=<?php echo $filter_major; ?>&q=<?php echo urlencode($filter_search); ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a></li>
            <?php endfor; ?>
            <?php if ($page<$totalPages): ?><li class="page-item"><a class="page-link" href="?tab=<?php echo $tab; ?>&major_id=<?php echo $filter_major; ?>&q=<?php echo urlencode($filter_search); ?>&page=<?php echo $page+1; ?>"><i class="bi bi-chevron-right"></i></a></li><?php endif; ?>
        </ul></nav>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Cap tai khoan sinh vien -->
<div class="modal fade" id="accountModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-badge me-2"></i>Cap tai khoan Sinh vien</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_account">
                <input type="hidden" name="app_id" id="accAppId">
                <div class="modal-body">
                    <!-- Thong tin thi sinh -->
                    <div class="alert alert-info py-2 mb-3" id="accStudentInfo"></div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Ma so sinh vien (MSSV) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" name="student_code" id="accStudentCode" class="form-control" required placeholder="VD: 2024001234">
                                <button type="button" class="btn btn-outline-secondary" onclick="genStudentCode()">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Lop hoc <span class="text-danger">*</span></label>
                            <select name="class_id" id="accClassId" class="form-select" required>
                                <option value="">-- Chon lop --</option>
                                <?php
                                if ($classes) { $classes->data_seek(0); while ($c=$classes->fetch_assoc()): ?>
                                <option value="<?php echo $c['id']; ?>" data-major="<?php echo $c['major_name']; ?>" data-year="<?php echo (int)($c['enrollment_year'] ?: date('Y')); ?>" data-code="<?php echo htmlspecialchars($c['class_code'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($c['class_name'].' ('.$c['school_year'].')'); ?> — <?php echo htmlspecialchars($c['major_name']); ?>
                                </option>
                                <?php endwhile; } ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Ten dang nhap <span class="text-danger">*</span></label>
                            <input type="text" name="username" id="accUsername" class="form-control" required readonly placeholder="MSSV">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Mat khau <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" name="password" id="accPassword" class="form-control" required readonly>
                                <button type="button" class="btn btn-outline-secondary" onclick="genPassword()" title="Mat khau mac dinh bang MSSV">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                            </div>
                            <div class="form-text">Mat khau mac dinh bang MSSV.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Che do dang ky HK1 tu dong</label>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-check border rounded p-2 h-100">
                                        <input class="form-check-input" type="radio" name="auto_enroll_mode" value="system" checked>
                                        <span class="form-check-label fw-semibold">Theo Dữ liệu thật</span>
                                        <div class="small text-muted">Tim HK1 dung khoa/nam hoc.</div>
                                    </label>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-check border rounded p-2 h-100">
                                        <input class="form-check-input" type="radio" name="auto_enroll_mode" value="test">
                                        <span class="form-check-label fw-semibold">Theo hoc ky Test</span>
                                        <div class="small text-muted">Dung hoc ky Test de demo nhanh.</div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning py-2 mt-3 small">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Sau khi cap tai khoan, sinh vien co the dang nhap vao cong thong tin sinh vien.
                        Vui long thong bao ten dang nhap va mat khau cho sinh vien.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huy</button>
                    <button type="submit" class="btn btn-navy"><i class="bi bi-person-badge me-1"></i>Cap tai khoan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<script>
function toggleAll() {
    const checks = document.querySelectorAll('.app-check');
    const allChecked = [...checks].every(c => c.checked);
    checks.forEach(c => c.checked = !allChecked);
}
document.getElementById('checkAll')?.addEventListener('change', function() {
    document.querySelectorAll('.app-check').forEach(c => c.checked = this.checked);
});
function confirmEnroll() {
    const checked = document.querySelectorAll('.app-check:checked').length;
    if (checked === 0) { alert('Vui long chon it nhat mot thi sinh.'); return false; }
    return confirm(`Xac nhan nhap hoc cho ${checked} thi sinh da chon?`);
}

function openAccountModal(data) {
    document.getElementById('accAppId').value = data.id;
    document.getElementById('accStudentInfo').innerHTML =
        `<i class="bi bi-person-fill me-2"></i><strong>${data.name}</strong> &mdash; ${data.email} &mdash; Nganh: ${data.major}`;

    // Auto-gen MSSV, username va mat khau mac dinh theo MSSV.
    genStudentCode();
    genPassword();

    new bootstrap.Modal(document.getElementById('accountModal')).show();
}

function genStudentCode() {
    const classSelect = document.getElementById('accClassId');
    const selected = classSelect?.selectedOptions?.[0];
    const year = selected?.dataset?.year || new Date().getFullYear();
    const classCode = selected?.dataset?.code || '';
    const classDigits = classCode.replace(/\D/g, '').slice(-2);
    const rand = String(Math.floor(Math.random() * 9000) + 1000);
    const code = year + classDigits + rand;
    document.getElementById('accStudentCode').value = code;
    document.getElementById('accUsername').value = code.toLowerCase();
    document.getElementById('accPassword').value = code;
}

function genPassword() {
    const code = document.getElementById('accStudentCode').value.trim();
    document.getElementById('accPassword').value = code;
    document.getElementById('accUsername').value = code.toLowerCase();
}
document.getElementById('accClassId')?.addEventListener('change', genStudentCode);
document.getElementById('accStudentCode')?.addEventListener('input', genPassword);
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
