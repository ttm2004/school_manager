<?php
$pageTitle = 'Đợt thu học phí';
include __DIR__ . '/includes/header.php';
if (!$_isManager) { header('Location: index.php'); exit(); }
if (!function_exists('fmtVND')) { function fmtVND($n) { return number_format(floatval($n),0,',','.') . ' ₫'; } }

$aid = (int)($_SESSION['user_id'] ?? 0);

// POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_period') {
        $semId = intval($_POST['semester_id']??0); $title = trim($_POST['title']??'');
        $open  = trim($_POST['open_date']??'');    $due   = trim($_POST['due_date']??'');
        $note  = trim($_POST['note']??'');
        if (!$semId||!$title||!$open||!$due) {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Vui lòng điền đầy đủ thông tin.'];
        } else {
            $chk = $conn->query("SELECT id FROM tuition_periods WHERE semester_id=$semId");
            if ($chk && $chk->num_rows > 0) {
                $_SESSION['_flash'] = ['type'=>'danger','message'=>'Học kỳ này đã có đợt thu học phí.'];
            } else {
                $ins = $conn->prepare("INSERT INTO tuition_periods (semester_id,title,open_date,due_date,note,created_by) VALUES (?,?,?,?,?,?)");
                $ins->bind_param('issssi',$semId,$title,$open,$due,$note,$aid);
                if ($ins->execute()) {
                    $pid = $conn->insert_id;
                    // Tự động tạo hóa đơn draft
                    $res = $conn->query("SELECT ss.student_id, SUM(sub.credits) tc, m.tuition_per_credit up
                        FROM student_subjects ss JOIN course_sections cs ON ss.course_section_id=cs.id
                        JOIN subjects sub ON cs.subject_id=sub.id
                        JOIN students st ON ss.student_id=st.id
                        JOIN classes cl ON st.class_id=cl.id JOIN majors m ON cl.major_id=m.id
                        WHERE cs.semester_id=$semId AND ss.status!='cancelled'
                        GROUP BY ss.student_id, m.tuition_per_credit");
                    $created = 0;
                    if ($res) while ($row = $res->fetch_assoc()) {
                        $sid=(int)$row['student_id']; $tc=(int)$row['tc']; $up=(float)$row['up']; $gross=$tc*$up;
                        $i2=$conn->prepare("INSERT IGNORE INTO tuition_invoices (period_id,student_id,semester_id,total_credits,unit_price,gross_amount,discount,net_amount,paid_amount,status,created_by) VALUES (?,?,?,?,?,?,0,?,0,'draft',?)");
                        $i2->bind_param('iiiidddi',$pid,$sid,$semId,$tc,$up,$gross,$gross,$aid);
                        if ($i2->execute()) $created++; $i2->close();
                    }
                    $_SESSION['_flash'] = ['type'=>'success','message'=>"Tạo đợt thu thành công! Đã tạo $created hóa đơn nháp. Xem xét rồi xác nhận công bố."];
                } else { $_SESSION['_flash'] = ['type'=>'danger','message'=>'Lỗi: '.$conn->error]; }
                $ins->close();
            }
        }
        header('Location: periods.php'); exit();
    }

    if ($action === 'update_period') {
        $pid=$_POST['period_id']??0; $title=trim($_POST['title']??''); $open=trim($_POST['open_date']??''); $due=trim($_POST['due_date']??''); $note=trim($_POST['note']??'');
        if ($pid&&$title&&$open&&$due) {
            $upd=$conn->prepare("UPDATE tuition_periods SET title=?,open_date=?,due_date=?,note=?,updated_at=NOW() WHERE id=? AND status='draft'");
            $upd->bind_param('ssssi',$title,$open,$due,$note,$pid); $upd->execute(); $upd->close();
            $_SESSION['_flash']=['type'=>'success','message'=>'Cập nhật thành công!'];
        }
        header('Location: periods.php?id='.$pid); exit();
    }

    if ($action === 'regenerate') {
        $pid=intval($_POST['period_id']??0);
        $period=$conn->query("SELECT * FROM tuition_periods WHERE id=$pid AND status='draft'")->fetch_assoc();
        if ($period) {
            $semId=(int)$period['semester_id'];
            $res=$conn->query("SELECT ss.student_id, SUM(sub.credits) tc, m.tuition_per_credit up
                FROM student_subjects ss JOIN course_sections cs ON ss.course_section_id=cs.id
                JOIN subjects sub ON cs.subject_id=sub.id JOIN students st ON ss.student_id=st.id
                JOIN classes cl ON st.class_id=cl.id JOIN majors m ON cl.major_id=m.id
                WHERE cs.semester_id=$semId AND ss.status!='cancelled'
                GROUP BY ss.student_id, m.tuition_per_credit");
            $upd=$new=0;
            if ($res) while ($row=$res->fetch_assoc()) {
                $sid=(int)$row['student_id']; $tc=(int)$row['tc']; $up=(float)$row['up']; $gross=$tc*$up;
                $chk=$conn->query("SELECT id FROM tuition_invoices WHERE period_id=$pid AND student_id=$sid");
                if ($chk&&$chk->num_rows>0) { $conn->query("UPDATE tuition_invoices SET total_credits=$tc,unit_price=$up,gross_amount=$gross,net_amount=$gross,updated_at=NOW() WHERE period_id=$pid AND student_id=$sid AND status='draft'"); $upd++; }
                else { $i2=$conn->prepare("INSERT INTO tuition_invoices (period_id,student_id,semester_id,total_credits,unit_price,gross_amount,discount,net_amount,paid_amount,status,created_by) VALUES (?,?,?,?,?,?,0,?,0,'draft',?)"); $i2->bind_param('iiiidddi',$pid,$sid,$semId,$tc,$up,$gross,$gross,$aid); if ($i2->execute()) $new++; $i2->close(); }
            }
            $_SESSION['_flash']=['type'=>'success','message'=>"Đã cập nhật $upd, tạo mới $new hóa đơn nháp."];
        }
        header('Location: periods.php?id='.$pid); exit();
    }

    if ($action === 'publish') {
        $pid=intval($_POST['period_id']??0);
        $conn->query("UPDATE tuition_periods SET status='published',published_at=NOW(),updated_at=NOW() WHERE id=$pid AND status='draft'");
        $conn->query("UPDATE tuition_invoices SET status='unpaid',updated_at=NOW() WHERE period_id=$pid AND status='draft'");
        $_SESSION['_flash']=['type'=>'success','message'=>'Đã công bố! Sinh viên có thể xem hóa đơn.'];
        header('Location: periods.php?id='.$pid); exit();
    }

    if ($action === 'close') {
        $pid=intval($_POST['period_id']??0);
        $conn->query("UPDATE tuition_periods SET status='closed',updated_at=NOW() WHERE id=$pid");
        $conn->query("UPDATE tuition_invoices SET status='overdue',updated_at=NOW() WHERE period_id=$pid AND status IN ('unpaid','partial')");
        $_SESSION['_flash']=['type'=>'success','message'=>'Đã đóng đợt thu. Hóa đơn chưa đóng được đánh dấu quá hạn.'];
        header('Location: periods.php?id='.$pid); exit();
    }
}

$semesters = $conn->query("SELECT * FROM semesters ORDER BY school_year DESC, semester_name DESC");
$periods   = $conn->query("SELECT tp.*, sm.semester_name, sm.school_year,
    (SELECT COUNT(*) FROM tuition_invoices WHERE period_id=tp.id) inv_count,
    (SELECT COUNT(*) FROM tuition_invoices WHERE period_id=tp.id AND status='paid') paid_count,
    (SELECT COALESCE(SUM(net_amount),0) FROM tuition_invoices WHERE period_id=tp.id AND status!='draft') sum_net,
    (SELECT COALESCE(SUM(paid_amount),0) FROM tuition_invoices WHERE period_id=tp.id) sum_paid
    FROM tuition_periods tp JOIN semesters sm ON tp.semester_id=sm.id
    ORDER BY tp.created_at DESC");

$viewId = intval($_GET['id'] ?? 0);
$viewPeriod = $viewId ? $conn->query("SELECT tp.*,sm.semester_name,sm.school_year FROM tuition_periods tp JOIN semesters sm ON tp.semester_id=sm.id WHERE tp.id=$viewId")->fetch_assoc() : null;

$pStatusMap = ['draft'=>['secondary','Nháp'],'published'=>['success','Đã công bố'],'closed'=>['dark','Đã đóng']];
$flash = getFlash();
?>

<?php if ($flash): ?>
<div class="alert alert-<?php echo $flash['type']; ?> auto-dismiss alert-dismissible fade show">
    <i class="bi bi-<?php echo $flash['type']==='success'?'check-circle-fill':'exclamation-circle-fill'; ?> me-2"></i>
    <?php echo $flash['message']; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Danh sách đợt thu -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-ul me-2"></i>Đợt thu học phí</span>
                <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#createModal">
                    <i class="bi bi-plus-lg me-1"></i>Tạo mới
                </button>
            </div>
            <div class="card-body p-0">
                <?php if ($periods && $periods->num_rows > 0): while ($p = $periods->fetch_assoc()):
                    $ps = $pStatusMap[$p['status']] ?? ['secondary',$p['status']];
                    $pct = $p['inv_count'] > 0 ? round($p['paid_count']/$p['inv_count']*100) : 0;
                ?>
                <a href="periods.php?id=<?php echo $p['id']; ?>" class="d-block p-3 border-bottom text-decoration-none <?php echo $viewId==$p['id']?'bg-light':''; ?>" style="color:inherit;">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <div class="fw-semibold small"><?php echo htmlspecialchars($p['title']); ?></div>
                        <span class="badge bg-<?php echo $ps[0]; ?> ms-2"><?php echo $ps[1]; ?></span>
                    </div>
                    <div class="text-muted" style="font-size:.75rem;"><?php echo htmlspecialchars($p['semester_name'].' '.$p['school_year']); ?> &bull; Hạn: <?php echo date('d/m/Y',strtotime($p['due_date'])); ?></div>
                    <div class="d-flex justify-content-between small text-muted mt-1">
                        <span><?php echo $p['paid_count']; ?>/<?php echo $p['inv_count']; ?> đã đóng</span>
                        <span class="text-success"><?php echo fmtVND($p['sum_paid']); ?></span>
                    </div>
                    <div class="progress mt-1" style="height:3px;border-radius:2px;"><div class="progress-bar bg-success" style="width:<?php echo $pct; ?>%"></div></div>
                </a>
                <?php endwhile; else: ?>
                <div class="text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Chưa có đợt thu nào</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Chi tiết đợt thu -->
    <div class="col-lg-8">
        <?php if ($viewPeriod):
            $ps = $pStatusMap[$viewPeriod['status']] ?? ['secondary',$viewPeriod['status']];
            $sr = $conn->query("SELECT COUNT(*) total, COALESCE(SUM(status='draft'),0) draft, COALESCE(SUM(status='unpaid'),0) unpaid, COALESCE(SUM(status='partial'),0) partial, COALESCE(SUM(status='paid'),0) paid, COALESCE(SUM(status='overdue'),0) overdue, COALESCE(SUM(net_amount),0) sum_net, COALESCE(SUM(paid_amount),0) sum_paid FROM tuition_invoices WHERE period_id=$viewId");
            $st = $sr ? ($sr->fetch_assoc() ?? []) : [];
        ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="bi bi-calendar-range-fill me-2"></i><?php echo htmlspecialchars($viewPeriod['title']); ?></span>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="badge bg-<?php echo $ps[0]; ?> fs-6"><?php echo $ps[1]; ?></span>
                    <?php if ($viewPeriod['status']==='draft'): ?>
                    <button class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#editModal"><i class="bi bi-pencil me-1"></i>Sửa</button>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Tái tạo hóa đơn từ dữ liệu đăng ký hiện tại?')">
                        <input type="hidden" name="action" value="regenerate">
                        <input type="hidden" name="period_id" value="<?php echo $viewId; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-warning"><i class="bi bi-arrow-clockwise me-1"></i>Tái tạo HĐ</button>
                    </form>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Xác nhận công bố? Sinh viên sẽ thấy hóa đơn ngay.')">
                        <input type="hidden" name="action" value="publish">
                        <input type="hidden" name="period_id" value="<?php echo $viewId; ?>">
                        <button type="submit" class="btn btn-sm btn-gold"><i class="bi bi-megaphone-fill me-1"></i>Công bố</button>
                    </form>
                    <?php elseif ($viewPeriod['status']==='published'): ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Đóng đợt thu? Hóa đơn chưa đóng sẽ bị đánh dấu quá hạn.')">
                        <input type="hidden" name="action" value="close">
                        <input type="hidden" name="period_id" value="<?php echo $viewId; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-lock-fill me-1"></i>Đóng đợt thu</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><div class="text-muted small">Học kỳ</div><div class="fw-bold"><?php echo htmlspecialchars($viewPeriod['semester_name'].' '.$viewPeriod['school_year']); ?></div></div>
                    <div class="col-md-3"><div class="text-muted small">Ngày công bố</div><div class="fw-bold"><?php echo date('d/m/Y',strtotime($viewPeriod['open_date'])); ?></div></div>
                    <div class="col-md-3"><div class="text-muted small">Hạn đóng</div><div class="fw-bold text-danger"><?php echo date('d/m/Y',strtotime($viewPeriod['due_date'])); ?></div></div>
                    <div class="col-md-3"><div class="text-muted small">Tổng thu được</div><div class="fw-bold text-success"><?php echo fmtVND($st['sum_paid']??0); ?></div></div>
                </div>
                <div class="row g-2">
                    <?php foreach ([['secondary','Nháp',$st['draft']??0],['warning','Chưa đóng',$st['unpaid']??0],['info','Một phần',$st['partial']??0],['success','Đã đóng',$st['paid']??0],['danger','Quá hạn',$st['overdue']??0]] as [$c,$l,$v]): ?>
                    <div class="col"><div class="text-center p-2 rounded" style="background:rgba(0,0,0,.04);">
                        <div class="fw-bold fs-5"><?php echo $v; ?></div>
                        <div><span class="badge bg-<?php echo $c; ?>"><?php echo $l; ?></span></div>
                    </div></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Danh sách hóa đơn của đợt này -->
        <div class="card">
            <div class="card-header"><i class="bi bi-table me-2"></i>Hóa đơn trong đợt này</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="font-size:.83rem;">
                        <thead><tr><th>Sinh viên</th><th class="text-center">TC</th><th class="text-end">Phải đóng</th><th class="text-end">Đã đóng</th><th class="text-end text-danger">Còn nợ</th><th class="text-center">Trạng thái</th></tr></thead>
                        <tbody>
                        <?php
                        $invRes = $conn->query("SELECT ti.*,u.full_name,st.student_code,cl.class_name FROM tuition_invoices ti JOIN students st ON ti.student_id=st.id JOIN users u ON st.user_id=u.id LEFT JOIN classes cl ON st.class_id=cl.id WHERE ti.period_id=$viewId ORDER BY u.full_name LIMIT 50");
                        $sMap = ['draft'=>['secondary','Nháp'],'unpaid'=>['warning','Chưa đóng'],'partial'=>['info','Một phần'],'paid'=>['success','Đã đóng'],'overdue'=>['danger','Quá hạn'],'waived'=>['secondary','Miễn']];
                        if ($invRes && $invRes->num_rows > 0): while ($inv = $invRes->fetch_assoc()):
                            $rem = max(0,$inv['net_amount']-$inv['paid_amount']);
                            $s = $sMap[$inv['status']] ?? ['secondary',$inv['status']];
                        ?>
                        <tr>
                            <td><div class="fw-semibold"><?php echo htmlspecialchars($inv['full_name']); ?></div><div class="text-muted" style="font-size:.72rem;"><?php echo htmlspecialchars($inv['student_code']); ?> &bull; <?php echo htmlspecialchars($inv['class_name']??''); ?></div></td>
                            <td class="text-center fw-bold"><?php echo $inv['total_credits']; ?></td>
                            <td class="text-end"><?php echo fmtVND($inv['net_amount']); ?></td>
                            <td class="text-end text-success"><?php echo fmtVND($inv['paid_amount']); ?></td>
                            <td class="text-end fw-bold <?php echo $rem>0?'text-danger':'text-success'; ?>"><?php echo $rem>0?fmtVND($rem):'—'; ?></td>
                            <td class="text-center"><span class="badge bg-<?php echo $s[0]; ?>"><?php echo $s[1]; ?></span></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox d-block mb-1"></i>Chưa có hóa đơn</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="card"><div class="card-body text-center text-muted py-5"><i class="bi bi-arrow-left-circle fs-2 d-block mb-2"></i>Chọn một đợt thu để xem chi tiết</div></div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal tạo đợt thu -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-plus-lg me-2"></i>Tạo đợt thu học phí</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST">
            <input type="hidden" name="action" value="create_period">
            <div class="modal-body">
                <div class="alert alert-info small mb-3"><i class="bi bi-info-circle me-1"></i>Hệ thống tự tính học phí = tín chỉ đã đăng ký × đơn giá/TC. Hóa đơn ở trạng thái <strong>Nháp</strong> cho đến khi bạn xác nhận công bố.</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Học kỳ <span class="text-danger">*</span></label>
                        <select name="semester_id" class="form-select" required>
                            <option value="">-- Chọn học kỳ --</option>
                            <?php if ($semesters) { $semesters->data_seek(0); while ($sem=$semesters->fetch_assoc()): ?>
                            <option value="<?php echo $sem['id']; ?>"><?php echo htmlspecialchars($sem['semester_name'].' '.$sem['school_year']); ?></option>
                            <?php endwhile; } ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Tiêu đề <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required placeholder="VD: Thu học phí HK1 2025-2026">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Ngày bắt đầu thu <span class="text-danger">*</span></label>
                        <input type="date" name="open_date" class="form-control" required>
                        <div class="form-text">Ngày sinh viên bắt đầu thấy hóa đơn.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Hạn đóng học phí <span class="text-danger">*</span></label>
                        <input type="date" name="due_date" class="form-control" required>
                        <div class="form-text">Quá hạn → khóa chức năng sinh viên.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Ghi chú</label>
                        <textarea name="note" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn btn-gold"><i class="bi bi-lightning-fill me-1"></i>Tạo & tính hóa đơn nháp</button>
            </div>
        </form>
    </div></div>
</div>

<!-- Modal sửa -->
<?php if ($viewPeriod && $viewPeriod['status']==='draft'): ?>
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Sửa đợt thu</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST">
            <input type="hidden" name="action" value="update_period">
            <input type="hidden" name="period_id" value="<?php echo $viewId; ?>">
            <div class="modal-body">
                <div class="mb-3"><label class="form-label fw-bold">Tiêu đề</label><input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($viewPeriod['title']); ?>" required></div>
                <div class="row g-3">
                    <div class="col-6"><label class="form-label fw-bold">Ngày bắt đầu</label><input type="date" name="open_date" class="form-control" value="<?php echo $viewPeriod['open_date']; ?>"></div>
                    <div class="col-6"><label class="form-label fw-bold">Hạn đóng</label><input type="date" name="due_date" class="form-control" value="<?php echo $viewPeriod['due_date']; ?>"></div>
                </div>
                <div class="mt-3"><label class="form-label fw-bold">Ghi chú</label><textarea name="note" class="form-control" rows="2"><?php echo htmlspecialchars($viewPeriod['note']??''); ?></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn btn-navy"><i class="bi bi-save me-1"></i>Lưu</button>
            </div>
        </form>
    </div></div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
