<?php
$pageTitle = 'Danh sách Hóa đơn';
include __DIR__ . '/includes/header.php';
if (!function_exists('fmtVND')) { function fmtVND($n) { return number_format(floatval($n),0,',','.') . ' ₫'; } }

$aid = (int)($_SESSION['user_id'] ?? 0);

// AJAX
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    if ($_GET['ajax'] === 'payments') {
        $iid = intval($_GET['id']??0);
        $pays = [];
        if ($iid) {
            $r = $conn->prepare("SELECT tp.*, u.full_name pb FROM tuition_payments tp LEFT JOIN users u ON tp.paid_by=u.id WHERE tp.invoice_id=? ORDER BY tp.paid_at DESC");
            $r->bind_param('i',$iid); $r->execute();
            $res = $r->get_result(); while ($row=$res->fetch_assoc()) $pays[]=$row; $r->close();
        }
        echo json_encode(['payments'=>$pays]); exit();
    }
    exit();
}

// POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['_csrf_token'] ?? '')) {
        $_SESSION['_flash'] = ['type'=>'danger','message'=>'Yeu cau khong hop le. Vui long tai lai trang va thu lai.'];
        header('Location: invoices.php'); exit();
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'record_payment') {
        $iid=intval($_POST['invoice_id']??0); $amount=floatval($_POST['amount']??0);
        $method=trim($_POST['method']??'cash'); $ref=trim($_POST['reference']??''); $note=trim($_POST['note']??'');
        // Whitelist payment method
        $allowedMethods = ['cash','bank_transfer','online','other'];
        if (!in_array($method, $allowedMethods, true)) $method = 'cash';
        if ($iid && $amount > 0) {
            $invStmt=$conn->prepare("SELECT * FROM tuition_invoices WHERE id=?");
            $invStmt->bind_param('i',$iid); $invStmt->execute();
            $inv=$invStmt->get_result()->fetch_assoc(); $invStmt->close();
            if ($inv) {
                $dataMode=(($inv['data_mode'] ?? 'system') === 'test') ? 'test' : 'system';
                $demoBatchId=(string)($inv['demo_batch_id'] ?? '');
                $pay=$conn->prepare("INSERT INTO tuition_payments (invoice_id,amount,method,reference,note,data_mode,demo_batch_id,paid_by) VALUES (?,?,?,?,?,?,?,?)");
                $pay->bind_param('idsssssi',$iid,$amount,$method,$ref,$note,$dataMode,$demoBatchId,$aid); $pay->execute(); $pay->close();
                $newPaid=$inv['paid_amount']+$amount; $net=$inv['net_amount'];
                $st=$inv['status']==='waived'?'waived':($newPaid>=$net?'paid':($newPaid>0?'partial':'unpaid'));
                $updStmt=$conn->prepare("UPDATE tuition_invoices SET paid_amount=?,status=?,updated_at=NOW() WHERE id=?");
                $updStmt->bind_param('dsi',$newPaid,$st,$iid); $updStmt->execute(); $updStmt->close();
                $_SESSION['_flash']=['type'=>'success','message'=>'Ghi nhận thanh toán thành công!'];
            }
        }
        $qs=http_build_query(array_filter(['period_id'=>$_GET['period_id']??'','status'=>$_GET['status']??'','q'=>$_GET['q']??'']));
        header('Location: invoices.php'.($qs?"?$qs":'')); exit();
    }

    if ($action === 'update_discount') {
        $iid=intval($_POST['invoice_id']??0); $disc=floatval($_POST['discount']??0); $note=trim($_POST['note']??'');
        if ($iid) {
            $invStmt=$conn->prepare("SELECT gross_amount,paid_amount,status FROM tuition_invoices WHERE id=?");
            $invStmt->bind_param('i',$iid); $invStmt->execute();
            $inv=$invStmt->get_result()->fetch_assoc(); $invStmt->close();
            if ($inv) {
                $net=max(0,$inv['gross_amount']-$disc);
                $st=$inv['status']==='waived'?'waived':($inv['paid_amount']>=$net&&$net>0?'paid':($inv['paid_amount']>0?'partial':($inv['status']==='draft'?'draft':'unpaid')));
                $upd=$conn->prepare("UPDATE tuition_invoices SET discount=?,net_amount=?,note=?,status=?,updated_at=NOW() WHERE id=?");
                $upd->bind_param('ddssi',$disc,$net,$note,$st,$iid); $upd->execute(); $upd->close();
                $_SESSION['_flash']=['type'=>'success','message'=>'Cập nhật miễn giảm thành công!'];
            }
        }
        $qs=http_build_query(array_filter(['period_id'=>$_GET['period_id']??'','status'=>$_GET['status']??'','q'=>$_GET['q']??'']));
        header('Location: invoices.php'.($qs?"?$qs":'')); exit();
    }
}

// Filters
$fPeriod = intval($_GET['period_id']??0);
$fStatus = trim($_GET['status']??'');
$fSearch = trim($_GET['q']??'');
$perPage = 25; $page = max(1,intval($_GET['page']??1)); $offset=($page-1)*$perPage;

$periods = $conn->query("SELECT tp.id, tp.title, sm.semester_name, sm.school_year FROM tuition_periods tp JOIN semesters sm ON tp.semester_id=sm.id ORDER BY tp.created_at DESC");

$conds=["ti.status!='draft'"]; $params=[]; $types='';
if ($fPeriod) { $conds[]="ti.period_id=$fPeriod"; }
if ($fStatus) { $conds[]="ti.status='".addslashes($fStatus)."'"; }
if ($fSearch) { $like=addslashes($fSearch); $conds[]="(u.full_name LIKE '%$like%' OR st.student_code LIKE '%$like%')"; }
$where='WHERE '.implode(' AND ',$conds);

$total=(int)($conn->query("SELECT COUNT(*) c FROM tuition_invoices ti JOIN students st ON ti.student_id=st.id JOIN users u ON st.user_id=u.id $where")->fetch_assoc()['c']??0);
$totalPages=max(1,(int)ceil($total/$perPage));

$invoices=$conn->query("SELECT ti.*,u.full_name,st.student_code,cl.class_name,m.major_name,tp.title period_title,sm.semester_name,sm.school_year
    FROM tuition_invoices ti JOIN students st ON ti.student_id=st.id JOIN users u ON st.user_id=u.id
    LEFT JOIN classes cl ON st.class_id=cl.id LEFT JOIN majors m ON cl.major_id=m.id
    JOIN tuition_periods tp ON ti.period_id=tp.id JOIN semesters sm ON tp.semester_id=sm.id
    $where ORDER BY u.full_name LIMIT $perPage OFFSET $offset");

$sMap=['unpaid'=>['warning','Chưa đóng'],'partial'=>['info','Một phần'],'paid'=>['success','Đã đóng'],'overdue'=>['danger','Quá hạn'],'waived'=>['secondary','Miễn']];
$flash=getFlash();
?>

<?php if ($flash): ?>
<div class="alert alert-<?php echo $flash['type']; ?> auto-dismiss alert-dismissible fade show">
    <i class="bi bi-<?php echo $flash['type']==='success'?'check-circle-fill':'exclamation-circle-fill'; ?> me-2"></i>
    <?php echo $flash['message']; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <select name="period_id" class="form-select form-select-sm">
                    <option value="">-- Tất cả đợt thu --</option>
                    <?php if ($periods) while ($p=$periods->fetch_assoc()): ?>
                    <option value="<?php echo $p['id']; ?>" <?php echo $fPeriod==$p['id']?'selected':''; ?>><?php echo htmlspecialchars($p['title']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- Tất cả --</option>
                    <?php foreach (['unpaid'=>'Chưa đóng','partial'=>'Một phần','paid'=>'Đã đóng','overdue'=>'Quá hạn','waived'=>'Miễn'] as $v=>$l): ?>
                    <option value="<?php echo $v; ?>" <?php echo $fStatus===$v?'selected':''; ?>><?php echo $l; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Tên, mã SV..." value="<?php echo htmlspecialchars($fSearch); ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-navy btn-sm flex-fill"><i class="bi bi-search me-1"></i>Lọc</button>
                <?php if ($fPeriod||$fStatus||$fSearch): ?><a href="invoices.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a><?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="card-header"><i class="bi bi-receipt me-2"></i>Hóa đơn học phí <span class="badge bg-gold text-dark ms-2"><?php echo number_format($total); ?></span></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle" style="font-size:.83rem;">
                <thead><tr><th>#</th><th>Sinh viên</th><th>Đợt thu</th><th class="text-center">TC</th><th class="text-end">Phải đóng</th><th class="text-end">Đã đóng</th><th class="text-end text-danger">Còn nợ</th><th class="text-center">Trạng thái</th><th class="text-center">Thao tác</th></tr></thead>
                <tbody>
                <?php if ($invoices && $invoices->num_rows > 0): $idx=$offset+1; while ($inv=$invoices->fetch_assoc()):
                    $rem=max(0,$inv['net_amount']-$inv['paid_amount']); $s=$sMap[$inv['status']]??['secondary',$inv['status']];
                ?>
                <tr class="<?php echo $inv['status']==='overdue'?'table-danger':''; ?>">
                    <td class="text-muted"><?php echo $idx++; ?></td>
                    <td><div class="fw-semibold"><?php echo htmlspecialchars($inv['full_name']); ?></div><div class="text-muted" style="font-size:.72rem;"><?php echo htmlspecialchars($inv['student_code']); ?> &bull; <?php echo htmlspecialchars($inv['class_name']??''); ?></div></td>
                    <td class="small text-muted"><?php echo htmlspecialchars($inv['period_title']); ?><br><span style="font-size:.7rem;"><?php echo htmlspecialchars($inv['semester_name'].' '.$inv['school_year']); ?></span></td>
                    <td class="text-center fw-bold"><?php echo $inv['total_credits']; ?></td>
                    <td class="text-end"><?php echo fmtVND($inv['net_amount']); ?></td>
                    <td class="text-end text-success"><?php echo fmtVND($inv['paid_amount']); ?></td>
                    <td class="text-end fw-bold <?php echo $rem>0?'text-danger':'text-success'; ?>"><?php echo $rem>0?fmtVND($rem):'—'; ?></td>
                    <td class="text-center"><span class="badge bg-<?php echo $s[0]; ?>"><?php echo $s[1]; ?></span></td>
                    <td class="text-center">
                        <div class="d-flex gap-1 justify-content-center">
                            <?php if (!in_array($inv['status'],['paid','waived'])): ?>
                            <button class="btn btn-sm btn-gold" title="Thanh toán"
                                data-bs-toggle="modal" data-bs-target="#payModal"
                                data-id="<?php echo $inv['id']; ?>"
                                data-name="<?php echo htmlspecialchars($inv['full_name'],ENT_QUOTES); ?>"
                                data-code="<?php echo htmlspecialchars($inv['student_code'],ENT_QUOTES); ?>"
                                data-net="<?php echo $inv['net_amount']; ?>"
                                data-paid="<?php echo $inv['paid_amount']; ?>"
                                data-rem="<?php echo $rem; ?>">
                                <i class="bi bi-cash-coin"></i>
                            </button>
                            <?php endif; ?>
                            <?php if ($_isManager): ?>
                            <button class="btn btn-sm btn-outline-primary" title="Miễn giảm"
                                data-bs-toggle="modal" data-bs-target="#discModal"
                                data-id="<?php echo $inv['id']; ?>"
                                data-name="<?php echo htmlspecialchars($inv['full_name'],ENT_QUOTES); ?>"
                                data-gross="<?php echo $inv['gross_amount']; ?>"
                                data-disc="<?php echo $inv['discount']; ?>"
                                data-note="<?php echo htmlspecialchars($inv['note']??'',ENT_QUOTES); ?>">
                                <i class="bi bi-percent"></i>
                            </button>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-outline-secondary" title="Lịch sử" onclick="viewHist(<?php echo $inv['id']; ?>,'<?php echo htmlspecialchars($inv['full_name'],ENT_QUOTES); ?>')">
                                <i class="bi bi-clock-history"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="9" class="text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Không có hóa đơn nào</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages>1): ?>
        <div class="px-3 py-2 border-top"><nav><ul class="pagination pagination-sm justify-content-center mb-0">
            <?php for ($p=max(1,$page-2);$p<=min($totalPages,$page+2);$p++): ?>
            <li class="page-item <?php echo $p===$page?'active':''; ?>"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET,['page'=>$p])); ?>"><?php echo $p; ?></a></li>
            <?php endfor; ?>
        </ul></nav></div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal thanh toán -->
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-cash-coin me-2"></i>Ghi nhận Thanh toán</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="record_payment">
            <input type="hidden" name="invoice_id" id="payId">
            <div class="modal-body">
                <div class="alert alert-light border py-2 px-3 mb-3"><div class="fw-bold" id="payName"></div><div class="small text-muted" id="payCode"></div></div>
                <div class="row g-2 mb-3">
                    <div class="col-4"><div class="text-muted small">Phải đóng</div><div class="fw-bold text-navy" id="payNet"></div></div>
                    <div class="col-4"><div class="text-muted small">Đã đóng</div><div class="fw-bold text-success" id="payPaid"></div></div>
                    <div class="col-4"><div class="text-muted small">Còn lại</div><div class="fw-bold text-danger" id="payRem"></div></div>
                </div>
                <div class="mb-3"><label class="form-label fw-bold">Số tiền <span class="text-danger">*</span></label><input type="number" name="amount" id="payAmt" class="form-control" min="1000" step="1000" required></div>
                <div class="mb-3"><label class="form-label fw-bold">Hình thức</label><select name="method" class="form-select"><option value="cash">Tiền mặt</option><option value="bank_transfer">Chuyển khoản</option><option value="online">Online</option><option value="other">Khác</option></select></div>
                <div class="mb-3"><label class="form-label fw-bold">Mã giao dịch / Biên lai</label><input type="text" name="reference" class="form-control"></div>
                <div class="mb-3"><label class="form-label fw-bold">Ghi chú</label><textarea name="note" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-gold"><i class="bi bi-save me-1"></i>Lưu</button></div>
        </form>
    </div></div>
</div>

<!-- Modal miễn giảm -->
<div class="modal fade" id="discModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-percent me-2"></i>Cập nhật Miễn giảm</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="update_discount">
            <input type="hidden" name="invoice_id" id="discId">
            <div class="modal-body">
                <div class="fw-bold mb-1" id="discName"></div>
                <div class="text-muted small mb-3">Học phí gốc: <span class="fw-bold text-navy" id="discGross"></span></div>
                <div class="mb-3"><label class="form-label fw-bold">Số tiền miễn giảm (₫)</label><input type="number" name="discount" id="discDisc" class="form-control" min="0" step="1000" required></div>
                <div class="mb-3"><label class="form-label fw-bold">Lý do</label><textarea name="note" id="discNote" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-navy"><i class="bi bi-save me-1"></i>Cập nhật</button></div>
        </form>
    </div></div>
</div>

<!-- Modal lịch sử -->
<div class="modal fade" id="histModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-clock-history me-2"></i>Lịch sử — <span id="histName"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" id="histBody"><div class="text-center py-4"><div class="spinner-border text-navy"></div></div></div>
    </div></div>
</div>

<script>
function fmtVND(n){return new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(n);}
document.getElementById('payModal')?.addEventListener('show.bs.modal',function(e){
    const b=e.relatedTarget;
    document.getElementById('payId').value=b.dataset.id;
    document.getElementById('payName').textContent=b.dataset.name;
    document.getElementById('payCode').textContent=b.dataset.code||'';
    const net=parseFloat(b.dataset.net)||0,paid=parseFloat(b.dataset.paid)||0,rem=parseFloat(b.dataset.rem)||0;
    document.getElementById('payNet').textContent=fmtVND(net);
    document.getElementById('payPaid').textContent=fmtVND(paid);
    document.getElementById('payRem').textContent=fmtVND(rem);
    document.getElementById('payAmt').value=rem>0?rem:'';
    document.getElementById('payAmt').max=rem>0?rem:'';
});
document.getElementById('discModal')?.addEventListener('show.bs.modal',function(e){
    const b=e.relatedTarget;
    document.getElementById('discId').value=b.dataset.id;
    document.getElementById('discName').textContent=b.dataset.name;
    document.getElementById('discGross').textContent=fmtVND(parseFloat(b.dataset.gross)||0);
    document.getElementById('discDisc').value=b.dataset.disc||0;
    document.getElementById('discNote').value=b.dataset.note||'';
});
function viewHist(id,name){
    document.getElementById('histName').textContent=name;
    document.getElementById('histBody').innerHTML='<div class="text-center py-4"><div class="spinner-border text-navy"></div></div>';
    new bootstrap.Modal(document.getElementById('histModal')).show();
    fetch('invoices.php?ajax=payments&id='+id,{credentials:'same-origin'}).then(r=>r.json()).then(data=>{
        const pays=data.payments||[];
        if(!pays.length){document.getElementById('histBody').innerHTML='<div class="text-center text-muted py-4"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Chưa có lịch sử</div>';return;}
        const mMap={cash:'Tiền mặt',bank_transfer:'Chuyển khoản',online:'Online',other:'Khác'};
        let html='<div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead><tr><th>Thời gian</th><th class="text-end">Số tiền</th><th>Hình thức</th><th>Mã GD</th><th>Người ghi</th><th>Ghi chú</th></tr></thead><tbody>';
        pays.forEach(p=>{html+=`<tr><td class="small">${new Date(p.paid_at).toLocaleString('vi-VN')}</td><td class="text-end fw-bold text-success">${fmtVND(parseFloat(p.amount))}</td><td class="small">${mMap[p.method]||p.method}</td><td class="small text-muted">${p.reference||'—'}</td><td class="small">${p.pb||'—'}</td><td class="small text-muted">${p.note||'—'}</td></tr>`;});
        html+='</tbody></table></div>';
        document.getElementById('histBody').innerHTML=html;
    }).catch(()=>{document.getElementById('histBody').innerHTML='<div class="alert alert-danger">Lỗi tải dữ liệu.</div>';});
}
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
