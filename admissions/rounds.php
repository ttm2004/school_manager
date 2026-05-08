<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireAnyRole(['admissions_manager']);
$pageTitle = 'Quản lý Đợt Tuyển sinh';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id          = intval($_POST['id'] ?? 0);
        $year        = intval($_POST['year'] ?? date('Y'));
        $name        = trim($_POST['name'] ?? '');
        $reg_start   = trim($_POST['reg_start'] ?? '');
        $reg_end     = trim($_POST['reg_end'] ?? '');
        $rev_start   = trim($_POST['review_start'] ?? '');
        $rev_end     = trim($_POST['review_end'] ?? '');
        $enroll_dl   = trim($_POST['enroll_deadline'] ?? '');
        $status      = trim($_POST['status'] ?? 'draft');
        $notes       = trim($_POST['notes'] ?? '');

        // Bổ sung (optional)
        $supp_reg_start = trim($_POST['supp_reg_start'] ?? '') ?: null;
        $supp_reg_end   = trim($_POST['supp_reg_end'] ?? '') ?: null;
        $supp_rev_end   = trim($_POST['supp_review_end'] ?? '') ?: null;
        $supp_enroll_dl = trim($_POST['supp_enroll_deadline'] ?? '') ?: null;
        $supp_bonus     = floatval($_POST['supp_score_bonus'] ?? 0);

        if (!$name || !$reg_start || !$reg_end || !$rev_start || !$rev_end || !$enroll_dl) {
            $error = 'Vui lòng điền đầy đủ các mốc thời gian bắt buộc.';
        } elseif (strtotime($reg_end) <= strtotime($reg_start)) {
            $error = 'Ngày kết thúc nhận hồ sơ phải sau ngày bắt đầu.';
        } elseif (strtotime($rev_end) <= strtotime($rev_start)) {
            $error = 'Ngày kết thúc xét tuyển phải sau ngày bắt đầu.';
        } else {
            $by = $_SESSION['user_id'];
            if ($action === 'add') {
                $stmt = $conn->prepare("INSERT INTO admission_rounds
                    (year,name,reg_start,reg_end,review_start,review_end,enroll_deadline,
                     supp_reg_start,supp_reg_end,supp_review_end,supp_enroll_deadline,supp_score_bonus,
                     status,notes,created_by)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param('issssssssssdsssi',
                    $year,$name,$reg_start,$reg_end,$rev_start,$rev_end,$enroll_dl,
                    $supp_reg_start,$supp_reg_end,$supp_rev_end,$supp_enroll_dl,$supp_bonus,
                    $status,$notes,$by);
            } else {
                $stmt = $conn->prepare("UPDATE admission_rounds SET
                    year=?,name=?,reg_start=?,reg_end=?,review_start=?,review_end=?,enroll_deadline=?,
                    supp_reg_start=?,supp_reg_end=?,supp_review_end=?,supp_enroll_deadline=?,supp_score_bonus=?,
                    status=?,notes=?
                    WHERE id=?");
                $stmt->bind_param('issssssssssdsssi',
                    $year,$name,$reg_start,$reg_end,$rev_start,$rev_end,$enroll_dl,
                    $supp_reg_start,$supp_reg_end,$supp_rev_end,$supp_enroll_dl,$supp_bonus,
                    $status,$notes,$id);
            }
            if ($stmt->execute()) {
                $success = $action === 'add' ? 'Tạo đợt tuyển sinh thành công!' : 'Cập nhật thành công!';
            } else {
                $error = 'Lỗi: ' . $conn->error;
            }
            $stmt->close();
        }
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM admission_rounds WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute() ? $success = 'Đã xóa đợt tuyển sinh.' : $error = $conn->error;
            $stmt->close();
        }
    }

    if ($action === 'change_status') {
        $id     = intval($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $valid  = ['draft','open','reviewing','enrolling','supplementary','completed'];
        if ($id && in_array($status, $valid)) {
            $stmt = $conn->prepare("UPDATE admission_rounds SET status=? WHERE id=?");
            $stmt->bind_param('si', $status, $id);
            $stmt->execute() ? $success = 'Đã cập nhật trạng thái!' : $error = $conn->error;
            $stmt->close();
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

$rounds = $conn->query("SELECT * FROM admission_rounds ORDER BY year DESC");

// Edit view
$editRound = null;
if (isset($_GET['edit'])) {
    $eid = intval($_GET['edit']);
    $es  = $conn->prepare("SELECT * FROM admission_rounds WHERE id=?");
    $es->bind_param('i', $eid); $es->execute();
    $editRound = $es->get_result()->fetch_assoc(); $es->close();
}

include __DIR__ . '/includes/header.php';

$statusLabels = [
    'draft'         => ['secondary', 'Nháp'],
    'open'          => ['success',   'Đang nhận hồ sơ'],
    'reviewing'     => ['danger',    'Đang xét tuyển'],
    'enrolling'     => ['primary',   'Đang nhập học'],
    'supplementary' => ['warning',   'Đợt bổ sung'],
    'completed'     => ['dark',      'Hoàn tất'],
];
?>

<?php $flash = getFlash(); if ($flash): ?><div class="alert alert-<?php echo $flash['type']; ?> auto-dismiss alert-dismissible fade show"><i class="bi bi-<?php echo $flash['type']==='success'?'check-circle-fill':'exclamation-circle-fill'; ?> me-2"></i><?php echo htmlspecialchars($flash['message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<!-- Form thêm/sửa -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-calendar-plus me-2"></i><?php echo $editRound ? 'Chỉnh sửa đợt tuyển sinh' : 'Tạo đợt tuyển sinh mới'; ?></span>
        <?php if ($editRound): ?>
        <a href="rounds.php" class="btn btn-sm btn-outline-light"><i class="bi bi-plus me-1"></i>Tạo mới</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="<?php echo $editRound ? 'edit' : 'add'; ?>">
            <?php if ($editRound): ?><input type="hidden" name="id" value="<?php echo $editRound['id']; ?>"><?php endif; ?>

            <div class="row g-3 mb-3">
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Năm <span class="text-danger">*</span></label>
                    <input type="number" name="year" class="form-control" value="<?php echo $editRound['year'] ?? date('Y'); ?>" min="2020" max="2099" required>
                </div>
                <div class="col-md-7">
                    <label class="form-label fw-semibold">Tên đợt <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($editRound['name'] ?? 'Tuyển sinh đại học ' . date('Y')); ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Trạng thái</label>
                    <select name="status" class="form-select">
                        <?php foreach ($statusLabels as $val => [$cls, $lbl]): ?>
                        <option value="<?php echo $val; ?>" <?php echo ($editRound['status'] ?? 'draft') === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Đợt chính -->
            <div class="p-3 mb-3 rounded" style="background:#f8f9ff;border:1px solid #e2e8f0;">
                <h6 class="fw-bold mb-3 text-navy"><i class="bi bi-1-circle-fill me-2"></i>Đợt xét tuyển chính thức</h6>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Bắt đầu nhận hồ sơ <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="reg_start" class="form-control" value="<?php echo $editRound ? date('Y-m-d\TH:i', strtotime($editRound['reg_start'])) : ''; ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Kết thúc nhận hồ sơ <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="reg_end" class="form-control" value="<?php echo $editRound ? date('Y-m-d\TH:i', strtotime($editRound['reg_end'])) : ''; ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Bắt đầu xét tuyển <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="review_start" class="form-control" value="<?php echo $editRound ? date('Y-m-d\TH:i', strtotime($editRound['review_start'])) : ''; ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Kết thúc xét tuyển <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="review_end" class="form-control" value="<?php echo $editRound ? date('Y-m-d\TH:i', strtotime($editRound['review_end'])) : ''; ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Hạn nhập học <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="enroll_deadline" class="form-control" value="<?php echo $editRound ? date('Y-m-d\TH:i', strtotime($editRound['enroll_deadline'])) : ''; ?>" required>
                    </div>
                </div>
            </div>

            <!-- Đợt bổ sung -->
            <div class="p-3 mb-3 rounded" style="background:#fffbf0;border:1px solid #fde68a;">
                <h6 class="fw-bold mb-1 text-warning"><i class="bi bi-2-circle-fill me-2"></i>Đợt bổ sung <small class="text-muted fw-normal">(để trống nếu không có)</small></h6>
                <p class="text-muted small mb-3">Mở khi thiếu chỉ tiêu sau đợt chính. Điểm chuẩn bổ sung thường cao hơn đợt chính.</p>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Bắt đầu nhận hồ sơ bổ sung</label>
                        <input type="datetime-local" name="supp_reg_start" class="form-control" value="<?php echo ($editRound && $editRound['supp_reg_start']) ? date('Y-m-d\TH:i', strtotime($editRound['supp_reg_start'])) : ''; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Kết thúc nhận hồ sơ bổ sung</label>
                        <input type="datetime-local" name="supp_reg_end" class="form-control" value="<?php echo ($editRound && $editRound['supp_reg_end']) ? date('Y-m-d\TH:i', strtotime($editRound['supp_reg_end'])) : ''; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Kết thúc xét bổ sung</label>
                        <input type="datetime-local" name="supp_review_end" class="form-control" value="<?php echo ($editRound && $editRound['supp_review_end']) ? date('Y-m-d\TH:i', strtotime($editRound['supp_review_end'])) : ''; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Hạn nhập học bổ sung</label>
                        <input type="datetime-local" name="supp_enroll_deadline" class="form-control" value="<?php echo ($editRound && $editRound['supp_enroll_deadline']) ? date('Y-m-d\TH:i', strtotime($editRound['supp_enroll_deadline'])) : ''; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Điểm chuẩn bổ sung cao hơn (điểm)</label>
                        <input type="number" name="supp_score_bonus" class="form-control" step="0.25" min="0" max="5" value="<?php echo $editRound['supp_score_bonus'] ?? 0; ?>" placeholder="VD: 0.5">
                        <div class="form-text">Điểm chuẩn bổ sung = Điểm chuẩn chính + giá trị này</div>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold small">Ghi chú</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Ghi chú thêm về đợt tuyển sinh..."><?php echo htmlspecialchars($editRound['notes'] ?? ''); ?></textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-gold"><i class="bi bi-save me-1"></i><?php echo $editRound ? 'Cập nhật' : 'Tạo đợt tuyển sinh'; ?></button>
                <?php if ($editRound): ?>
                <a href="rounds.php" class="btn btn-outline-secondary">Hủy</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Danh sách đợt tuyển sinh -->
<div class="card">
    <div class="card-header"><i class="bi bi-list-ul me-2"></i>Danh sách đợt tuyển sinh</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr>
                    <th>Năm</th><th>Tên đợt</th><th>Nhận hồ sơ</th><th>Xét tuyển</th><th>Hạn nhập học</th><th>Bổ sung</th><th>Trạng thái</th><th>Thao tác</th>
                </tr></thead>
                <tbody>
                <?php if ($rounds && $rounds->num_rows > 0): while ($r = $rounds->fetch_assoc()):
                    [$cls, $lbl] = $statusLabels[$r['status']] ?? ['secondary', $r['status']];
                    $phase = '';
                    $now = time();
                    if ($now >= strtotime($r['review_start']) && $now <= strtotime($r['review_end'])) {
                        $phase = '<span class="badge bg-danger ms-1 small">🔒 Đang xét</span>';
                    }
                ?>
                <tr>
                    <td class="fw-bold text-navy"><?php echo $r['year']; ?></td>
                    <td>
                        <div class="fw-semibold"><?php echo htmlspecialchars($r['name']); ?></div>
                        <?php if ($r['notes']): ?><div class="text-muted small"><?php echo mb_substr($r['notes'],0,50); ?></div><?php endif; ?>
                    </td>
                    <td class="small text-muted">
                        <?php echo date('d/m/Y', strtotime($r['reg_start'])); ?><br>
                        → <?php echo date('d/m/Y', strtotime($r['reg_end'])); ?>
                    </td>
                    <td class="small text-muted">
                        <?php echo date('d/m/Y', strtotime($r['review_start'])); ?><br>
                        → <?php echo date('d/m/Y', strtotime($r['review_end'])); ?>
                        <?php echo $phase; ?>
                    </td>
                    <td class="small text-muted"><?php echo date('d/m/Y', strtotime($r['enroll_deadline'])); ?></td>
                    <td class="small text-muted">
                        <?php if ($r['supp_reg_start']): ?>
                        <?php echo date('d/m/Y', strtotime($r['supp_reg_start'])); ?> →<br>
                        <?php echo date('d/m/Y', strtotime($r['supp_enroll_deadline'])); ?>
                        <?php if ($r['supp_score_bonus'] > 0): ?>
                        <span class="badge bg-warning text-dark">+<?php echo $r['supp_score_bonus']; ?> điểm</span>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-<?php echo $cls; ?>"><?php echo $lbl; ?></span>
                        <!-- Quick status change -->
                        <form method="POST" class="d-inline ms-1">
                            <input type="hidden" name="action" value="change_status">
                            <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                            <select name="status" class="form-select form-select-sm d-inline-block mt-1" style="width:auto;font-size:.72rem;" onchange="this.form.submit()">
                                <?php foreach ($statusLabels as $val => [$c, $l]): ?>
                                <option value="<?php echo $val; ?>" <?php echo $r['status']===$val?'selected':''; ?>><?php echo $l; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="?edit=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-primary" title="Sửa"><i class="bi bi-pencil-fill"></i></a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Xóa đợt tuyển sinh này?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa"><i class="bi bi-trash-fill"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Chưa có đợt tuyển sinh nào</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
