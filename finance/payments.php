<?php
$pageTitle = 'Lịch sử Thanh toán';
include __DIR__ . '/includes/header.php';
if (!function_exists('fmtVND')) { function fmtVND($n) { return number_format(floatval($n),0,',','.') . ' ₫'; } }

$fSearch = trim($_GET['q']??'');
$fMethod = trim($_GET['method']??'');
$perPage = 30; $page = max(1,intval($_GET['page']??1)); $offset=($page-1)*$perPage;

$conds=[]; $where='';
if ($fSearch) { $like=addslashes($fSearch); $conds[]="(u.full_name LIKE '%$like%' OR st.student_code LIKE '%$like%' OR tp.reference LIKE '%$like%')"; }
if ($fMethod) { $conds[]="tp.method='".addslashes($fMethod)."'"; }
if ($conds) $where='WHERE '.implode(' AND ',$conds);

$total=(int)($conn->query("SELECT COUNT(*) c FROM tuition_payments tp JOIN tuition_invoices ti ON tp.invoice_id=ti.id JOIN students st ON ti.student_id=st.id JOIN users u ON st.user_id=u.id $where")->fetch_assoc()['c']??0);
$totalPages=max(1,(int)ceil($total/$perPage));

$payments=$conn->query("SELECT tp.*, ti.net_amount, ti.paid_amount, ti.total_credits,
    u.full_name, st.student_code, sm.semester_name, sm.school_year, tper.title period_title,
    pu.full_name paid_by_name
    FROM tuition_payments tp
    JOIN tuition_invoices ti ON tp.invoice_id=ti.id
    JOIN students st ON ti.student_id=st.id JOIN users u ON st.user_id=u.id
    JOIN tuition_periods tper ON ti.period_id=tper.id JOIN semesters sm ON tper.semester_id=sm.id
    LEFT JOIN users pu ON tp.paid_by=pu.id
    $where ORDER BY tp.paid_at DESC LIMIT $perPage OFFSET $offset");

$sumToday=(float)($conn->query("SELECT COALESCE(SUM(amount),0) s FROM tuition_payments WHERE DATE(paid_at)=CURDATE()")->fetch_assoc()['s']??0);
$sumWeek=(float)($conn->query("SELECT COALESCE(SUM(amount),0) s FROM tuition_payments WHERE paid_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetch_assoc()['s']??0);
$sumTotal=(float)($conn->query("SELECT COALESCE(SUM(amount),0) s FROM tuition_payments")->fetch_assoc()['s']??0);
$mMap=['cash'=>'Tiền mặt','bank_transfer'=>'Chuyển khoản','online'=>'Online','other'=>'Khác'];
?>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="stat-card"><div class="ic" style="background:rgba(16,185,129,.12);color:#059669"><i class="bi bi-calendar-day"></i></div><div class="vl" style="font-size:1.3rem;color:#059669"><?php echo fmtVND($sumToday); ?></div><div class="lb">Thu hôm nay</div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="ic" style="background:rgba(13,45,107,.1);color:#0d2d6b"><i class="bi bi-calendar-week"></i></div><div class="vl" style="font-size:1.3rem;"><?php echo fmtVND($sumWeek); ?></div><div class="lb">7 ngày qua</div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="ic" style="background:rgba(245,166,35,.15);color:#d4891a"><i class="bi bi-cash-stack"></i></div><div class="vl" style="font-size:1.3rem;color:#d4891a"><?php echo fmtVND($sumTotal); ?></div><div class="lb">Tổng đã thu</div></div></div>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex gap-2 flex-wrap align-items-end">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Tên, mã SV, mã GD..." value="<?php echo htmlspecialchars($fSearch); ?>" style="width:220px">
            <select name="method" class="form-select form-select-sm" style="width:160px">
                <option value="">-- Hình thức --</option>
                <?php foreach ($mMap as $v=>$l): ?><option value="<?php echo $v; ?>" <?php echo $fMethod===$v?'selected':''; ?>><?php echo $l; ?></option><?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-navy btn-sm"><i class="bi bi-search me-1"></i>Lọc</button>
            <?php if ($fSearch||$fMethod): ?><a href="payments.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a><?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-clock-history me-2"></i>Lịch sử thanh toán <span class="badge bg-gold text-dark ms-2"><?php echo number_format($total); ?></span></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size:.83rem;">
                <thead><tr><th>Thời gian</th><th>Sinh viên</th><th>Đợt thu</th><th class="text-end">Số tiền</th><th>Hình thức</th><th>Mã GD</th><th>Người ghi</th><th>Ghi chú</th></tr></thead>
                <tbody>
                <?php if ($payments && $payments->num_rows > 0): while ($p=$payments->fetch_assoc()): ?>
                <tr>
                    <td class="small"><?php echo date('d/m/Y H:i',strtotime($p['paid_at'])); ?></td>
                    <td><div class="fw-semibold"><?php echo htmlspecialchars($p['full_name']); ?></div><div class="text-muted" style="font-size:.72rem;"><?php echo htmlspecialchars($p['student_code']); ?></div></td>
                    <td class="small text-muted"><?php echo htmlspecialchars($p['period_title']); ?><br><span style="font-size:.7rem;"><?php echo htmlspecialchars($p['semester_name'].' '.$p['school_year']); ?></span></td>
                    <td class="text-end fw-bold text-success"><?php echo fmtVND($p['amount']); ?></td>
                    <td class="small"><?php echo $mMap[$p['method']]??$p['method']; ?></td>
                    <td class="small text-muted"><?php echo htmlspecialchars($p['reference']??'—'); ?></td>
                    <td class="small"><?php echo htmlspecialchars($p['paid_by_name']??'—'); ?></td>
                    <td class="small text-muted"><?php echo htmlspecialchars($p['note']??'—'); ?></td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Chưa có lịch sử thanh toán</td></tr>
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

<?php include __DIR__ . '/includes/footer.php'; ?>
