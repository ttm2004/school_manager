<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/faculty_helpers.php';
requireAnyRole(['faculty_manager', 'faculty_staff', 'dept_head']);

$pageTitle = 'Thong bao Noi bo';
$userId    = (int)$_SESSION['user_id'];
$facultyId = getFacultyId($conn, $userId);
$ip        = $_SERVER['REMOTE_ADDR'] ?? '';

if ($facultyId <= 0 && ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Tai khoan chua duoc gan vao khoa nao.'];
    header('Location: /university/login.php');
    exit();
}

// POST Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isFacultyManager()) {
        $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Ban khong co quyen thuc hien thao tac nay.'];
        header('Location: notifications.php');
        exit();
    }

    $action = trim($_POST['action'] ?? '');

    if ($action === 'send') {
        $title         = trim($_POST['title'] ?? '');
        $content       = trim($_POST['content'] ?? '');
        $recipientType = trim($_POST['recipient_type'] ?? '');
        $majorId2      = (int)($_POST['major_id'] ?? 0);
        $classId       = (int)($_POST['class_id'] ?? 0);
        $specificTeachers = array_map('intval', (array)($_POST['specific_teachers'] ?? []));
        $specificStudents = array_map('intval', (array)($_POST['specific_students'] ?? []));

        if ($title === '' || $content === '') {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Tieu de va noi dung khong duoc de trong.'];
            header('Location: notifications.php');
            exit();
        }

        // Lay danh sach nguoi nhan
        $recipientUserIds = [];

        if ($recipientType === 'all_faculty') {
            // Tat ca GV + SV trong khoa
            $stmtGV = $conn->prepare("SELECT u.id FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.faculty_id = ?");
            $stmtGV->bind_param('i', $facultyId);
            $stmtGV->execute();
            $gvRows = $stmtGV->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtGV->close();
            foreach ($gvRows as $r) $recipientUserIds[] = (int)$r['id'];

            $stmtSV = $conn->prepare("SELECT u.id FROM students s JOIN users u ON s.user_id = u.id JOIN classes cl ON s.class_id = cl.id JOIN majors m ON cl.major_id = m.id WHERE m.faculty_id = ?");
            $stmtSV->bind_param('i', $facultyId);
            $stmtSV->execute();
            $svRows = $stmtSV->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtSV->close();
            foreach ($svRows as $r) $recipientUserIds[] = (int)$r['id'];

        } elseif ($recipientType === 'all_teachers') {
            $stmtGV = $conn->prepare("SELECT u.id FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.faculty_id = ?");
            $stmtGV->bind_param('i', $facultyId);
            $stmtGV->execute();
            $rows = $stmtGV->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtGV->close();
            foreach ($rows as $r) $recipientUserIds[] = (int)$r['id'];

        } elseif ($recipientType === 'all_students') {
            $stmtSV = $conn->prepare("SELECT u.id FROM students s JOIN users u ON s.user_id = u.id JOIN classes cl ON s.class_id = cl.id JOIN majors m ON cl.major_id = m.id WHERE m.faculty_id = ?");
            $stmtSV->bind_param('i', $facultyId);
            $stmtSV->execute();
            $rows = $stmtSV->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtSV->close();
            foreach ($rows as $r) $recipientUserIds[] = (int)$r['id'];

        } elseif ($recipientType === 'by_major' && $majorId2 > 0) {
            // Kiem tra major thuoc faculty
            $stmtMChk = $conn->prepare("SELECT id FROM majors WHERE id = ? AND faculty_id = ? LIMIT 1");
            $stmtMChk->bind_param('ii', $majorId2, $facultyId);
            $stmtMChk->execute();
            if ($stmtMChk->get_result()->num_rows === 0) {
                $stmtMChk->close();
                $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Chi duoc gui thong bao cho sinh vien thuoc khoa minh.'];
                header('Location: notifications.php');
                exit();
            }
            $stmtMChk->close();

            $stmtSV = $conn->prepare("SELECT u.id FROM students s JOIN users u ON s.user_id = u.id JOIN classes cl ON s.class_id = cl.id WHERE cl.major_id = ?");
            $stmtSV->bind_param('i', $majorId2);
            $stmtSV->execute();
            $rows = $stmtSV->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtSV->close();
            foreach ($rows as $r) $recipientUserIds[] = (int)$r['id'];

        } elseif ($recipientType === 'by_class' && $classId > 0) {
            // Kiem tra class thuoc faculty
            $stmtClsChk = $conn->prepare(
                "SELECT c.id FROM classes c JOIN majors m ON c.major_id = m.id WHERE c.id = ? AND m.faculty_id = ? LIMIT 1"
            );
            $stmtClsChk->bind_param('ii', $classId, $facultyId);
            $stmtClsChk->execute();
            if ($stmtClsChk->get_result()->num_rows === 0) {
                $stmtClsChk->close();
                $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Chi duoc gui thong bao cho sinh vien thuoc khoa minh.'];
                header('Location: notifications.php');
                exit();
            }
            $stmtClsChk->close();

            $stmtSV = $conn->prepare("SELECT u.id FROM students s JOIN users u ON s.user_id = u.id WHERE s.class_id = ?");
            $stmtSV->bind_param('i', $classId);
            $stmtSV->execute();
            $rows = $stmtSV->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtSV->close();
            foreach ($rows as $r) $recipientUserIds[] = (int)$r['id'];

        } elseif ($recipientType === 'specific_teacher' && !empty($specificTeachers)) {
            // Validate tat ca teacher thuoc faculty
            foreach ($specificTeachers as $tid) {
                $stmtTChk = $conn->prepare("SELECT u.id FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.id = ? AND t.faculty_id = ? LIMIT 1");
                $stmtTChk->bind_param('ii', $tid, $facultyId);
                $stmtTChk->execute();
                $row = $stmtTChk->get_result()->fetch_assoc();
                $stmtTChk->close();
                if ($row) $recipientUserIds[] = (int)$row['id'];
            }

        } elseif ($recipientType === 'specific_student' && !empty($specificStudents)) {
            foreach ($specificStudents as $sid) {
                $stmtSChk = $conn->prepare(
                    "SELECT u.id FROM students s JOIN users u ON s.user_id = u.id JOIN classes cl ON s.class_id = cl.id JOIN majors m ON cl.major_id = m.id WHERE s.id = ? AND m.faculty_id = ? LIMIT 1"
                );
                $stmtSChk->bind_param('ii', $sid, $facultyId);
                $stmtSChk->execute();
                $row = $stmtSChk->get_result()->fetch_assoc();
                $stmtSChk->close();
                if ($row) $recipientUserIds[] = (int)$row['id'];
            }
        }

        $recipientUserIds = array_unique($recipientUserIds);

        if (empty($recipientUserIds)) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Khong co nguoi nhan hop le.'];
            header('Location: notifications.php');
            exit();
        }

        // Insert notifications
        $stmtIns = $conn->prepare("INSERT INTO system_notifications (user_id, title, content, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
        $insertCount = 0;
        foreach ($recipientUserIds as $uid) {
            $stmtIns->bind_param('iss', $uid, $title, $content);
            if ($stmtIns->execute()) $insertCount++;
        }
        $stmtIns->close();

        logAudit($conn, $userId, 'create', 'faculty', 'notifications', 0, null,
            json_encode(['recipient_type' => $recipientType, 'count' => $insertCount, 'title' => $title]), $ip);

        $_SESSION['_flash'] = ['type' => 'success', 'message' => "Da gui thong bao thanh cong den {$insertCount} nguoi nhan."];
        header('Location: notifications.php');
        exit();
    }

    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Hanh dong khong hop le.'];
    header('Location: notifications.php');
    exit();
}

// GET Handler
$flash = getFlash();
$page  = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// Lay lich su thong bao (gui boi user trong khoa)
$stmtCount = $conn->prepare(
    "SELECT COUNT(*) AS c FROM system_notifications n
     JOIN users u ON n.user_id = u.id
     WHERE n.user_id IN (
         SELECT u2.id FROM teachers t JOIN users u2 ON t.user_id = u2.id WHERE t.faculty_id = ?
         UNION
         SELECT u3.id FROM students s JOIN users u3 ON s.user_id = u3.id JOIN classes cl ON s.class_id = cl.id JOIN majors m ON cl.major_id = m.id WHERE m.faculty_id = ?
     )"
);
$stmtCount->bind_param('ii', $facultyId, $facultyId);
$stmtCount->execute();
$totalNotifs = (int)($stmtCount->get_result()->fetch_assoc()['c'] ?? 0);
$stmtCount->close();

$pag = paginate($totalNotifs, $page, $perPage);

$stmtNotifs = $conn->prepare(
    "SELECT n.id, n.title, n.content, n.is_read, n.created_at,
            u.full_name AS recipient_name
     FROM system_notifications n
     JOIN users u ON n.user_id = u.id
     WHERE n.user_id IN (
         SELECT u2.id FROM teachers t JOIN users u2 ON t.user_id = u2.id WHERE t.faculty_id = ?
         UNION
         SELECT u3.id FROM students s JOIN users u3 ON s.user_id = u3.id JOIN classes cl ON s.class_id = cl.id JOIN majors m ON cl.major_id = m.id WHERE m.faculty_id = ?
     )
     ORDER BY n.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmtNotifs->bind_param('iiii', $facultyId, $facultyId, $pag['per_page'], $pag['offset']);
$stmtNotifs->execute();
$notifications = $stmtNotifs->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtNotifs->close();

// Lay danh sach nganh va lop cho form
$majors = [];
$stmtMajors = $conn->prepare("SELECT id, major_name FROM majors WHERE faculty_id = ? ORDER BY major_name ASC");
$stmtMajors->bind_param('i', $facultyId);
$stmtMajors->execute();
$majors = $stmtMajors->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtMajors->close();

$classes = [];
$stmtClasses = $conn->prepare(
    "SELECT c.id, c.class_name FROM classes c JOIN majors m ON c.major_id = m.id WHERE m.faculty_id = ? ORDER BY c.class_name ASC"
);
$stmtClasses->bind_param('i', $facultyId);
$stmtClasses->execute();
$classes = $stmtClasses->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtClasses->close();

$teachers = [];
$stmtT = $conn->prepare("SELECT t.id, t.teacher_code, u.full_name FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.faculty_id = ? ORDER BY u.full_name ASC");
$stmtT->bind_param('i', $facultyId);
$stmtT->execute();
$teachers = $stmtT->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtT->close();

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle" aria-label="Mo/dong menu">
                <i class="bi bi-list fs-5" aria-hidden="true"></i>
            </button>
            <span class="admin-topbar-title">
                <i class="bi bi-bell-fill me-2 text-navy" aria-hidden="true"></i>Thong bao Noi bo
            </span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <?php if (isFacultyManager()): ?>
            <button class="btn btn-sm btn-navy" data-bs-toggle="modal" data-bs-target="#sendNotifModal"
                    aria-label="Gui thong bao moi">
                <i class="bi bi-send me-1" aria-hidden="true"></i>Gui thong bao
            </button>
            <?php endif; ?>
            <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Dang xuat
            </a>
        </div>
    </div>

    <div class="admin-content">

        <?php if ($flash): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show auto-dismiss mb-4" role="alert">
            <?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dong"></button>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <i class="bi bi-bell-fill me-2" aria-hidden="true"></i>
                Lich su Thong bao
                <span class="badge bg-light text-dark ms-2"><?php echo number_format($totalNotifs); ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Tieu de</th>
                            <th>Nguoi nhan</th>
                            <th>Ngay gui</th>
                            <th>Trang thai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($notifications)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                <i class="bi bi-bell-slash fs-3 d-block mb-2" aria-hidden="true"></i>
                                Chua co thong bao nao.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($notifications as $n): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($n['title']); ?></td>
                            <td><?php echo htmlspecialchars($n['recipient_name']); ?></td>
                            <td class="text-muted small"><?php echo htmlspecialchars($n['created_at']); ?></td>
                            <td>
                                <?php if ($n['is_read']): ?>
                                <span class="badge bg-success">Da doc</span>
                                <?php else: ?>
                                <span class="badge bg-warning text-dark">Chua doc</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pag['total_pages'] > 1): ?>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    Hien thi <?php echo $pag['offset'] + 1; ?>-<?php echo min($pag['offset'] + $pag['per_page'], $pag['total']); ?>
                    / <?php echo number_format($pag['total']); ?>
                </small>
                <?php echo renderPagination($pag); ?>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <div class="admin-footer">
        &copy; <?php echo date('Y'); ?> TDMU - Truong Dai hoc Thu Dau Mot
    </div>
</div>

<?php if (isFacultyManager()): ?>
<!-- Send Notification Modal -->
<div class="modal fade" id="sendNotifModal" tabindex="-1" aria-labelledby="sendNotifModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="notifications.php">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="send">
                <div class="modal-header">
                    <h5 class="modal-title" id="sendNotifModalLabel">Gui Thong bao Noi bo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Dong"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="notif_title" class="form-label">Tieu de <span class="text-danger">*</span></label>
                        <input type="text" id="notif_title" name="title" class="form-control" required
                               placeholder="Tieu de thong bao...">
                    </div>
                    <div class="mb-3">
                        <label for="notif_content" class="form-label">Noi dung <span class="text-danger">*</span></label>
                        <textarea id="notif_content" name="content" class="form-control" rows="5" required
                                  placeholder="Noi dung thong bao..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="recipient_type" class="form-label">Nguoi nhan <span class="text-danger">*</span></label>
                        <select id="recipient_type" name="recipient_type" class="form-select" required
                                onchange="toggleRecipientOptions(this.value)">
                            <option value="all_faculty">Tat ca (GV + SV trong khoa)</option>
                            <option value="all_teachers">Tat ca Giang vien</option>
                            <option value="all_students">Tat ca Sinh vien</option>
                            <option value="by_major">Theo Nganh</option>
                            <option value="by_class">Theo Lop</option>
                            <option value="specific_teacher">Giang vien cu the</option>
                        </select>
                    </div>
                    <div id="opt_by_major" class="mb-3 d-none">
                        <label for="notif_major_id" class="form-label">Chon Nganh</label>
                        <select id="notif_major_id" name="major_id" class="form-select">
                            <option value="0">-- Chon nganh --</option>
                            <?php foreach ($majors as $m): ?>
                            <option value="<?php echo (int)$m['id']; ?>"><?php echo htmlspecialchars($m['major_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="opt_by_class" class="mb-3 d-none">
                        <label for="notif_class_id" class="form-label">Chon Lop</label>
                        <select id="notif_class_id" name="class_id" class="form-select">
                            <option value="0">-- Chon lop --</option>
                            <?php foreach ($classes as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['class_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="opt_specific_teacher" class="mb-3 d-none">
                        <label class="form-label">Chon Giang vien</label>
                        <div style="max-height:200px;overflow-y:auto;border:1px solid #dee2e6;border-radius:4px;padding:8px;">
                            <?php foreach ($teachers as $t): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"
                                       name="specific_teachers[]"
                                       value="<?php echo (int)$t['id']; ?>"
                                       id="t_<?php echo (int)$t['id']; ?>">
                                <label class="form-check-label" for="t_<?php echo (int)$t['id']; ?>">
                                    <?php echo htmlspecialchars($t['full_name'] . ' (' . $t['teacher_code'] . ')'); ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huy</button>
                    <button type="submit" class="btn btn-navy">
                        <i class="bi bi-send me-1" aria-hidden="true"></i>Gui thong bao
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleRecipientOptions(type) {
    var opts = ['by_major','by_class','specific_teacher'];
    opts.forEach(function(o) {
        document.getElementById('opt_' + o).classList.add('d-none');
    });
    if (type === 'by_major') document.getElementById('opt_by_major').classList.remove('d-none');
    else if (type === 'by_class') document.getElementById('opt_by_class').classList.remove('d-none');
    else if (type === 'specific_teacher') document.getElementById('opt_specific_teacher').classList.remove('d-none');
}
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
