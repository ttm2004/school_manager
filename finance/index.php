<?php
$pageTitle = 'Dashboard Kế toán';
include __DIR__ . '/includes/header.php';

if (!function_exists('fmtVND')) { function fmtVND($n) { return number_format(floatval($n),0,',','.') . ' ₫'; } }

// Stats tổng quan
$totalInvoices = (int)($conn->query("SELECT COUNT(*) c FROM tuition_invoices WHERE status!='draft'")->fetch_assoc()['c'] ?? 0);
$totalPaid     = (int)($conn->query("SELECT COUNT(*) c FROM tuition_invoices WHERE status='paid'")->fetch_assoc()['c'] ?? 0);
$totalUnpaid   = (int)($conn->query("SELECT COUNT(*) c FROM tuition_invoices WHERE status IN ('unpaid','partial')")->fetch_assoc()['c'] ?? 0);
$totalOverdue  = (int)($conn->query("SELECT COUNT(*) c FROM tuition_invoices WHERE status='overdue'")->fetch_assoc()['c'] ?? 0);
$sumCollected  = (float)($conn->query("SELECT COALESCE(SUM(paid_amount),0) s FROM tuition_invoices")->fetch_assoc()['s'] ?? 0);
$sumRemaining  = (float)($conn->query("SELECT COALESCE(SUM(net_amount-paid_amount),0) s FROM tuition_invoices WHERE status NOT IN ('draft','paid','waived')")->fetch_assoc()['s'] ?? 0);

// Đợt thu gần nhất
$latestPeriod = $conn->query("SELECT tp.*, sm.semester_name, sm.school_year,
    (SELECT COUNT(*) FROM tuition_invoices WHERE period_id=tp.id AND status!='draft') AS inv_count,
    (SELECT COUNT(*) FROM tuition_invoices WHERE period_id=tp.id AND status='paid') AS paid_count
    FROM tuition_periods tp JOIN semesters sm ON tp.semester_id=sm.id
    ORDER BY tp.created_at DESC LIMIT 3");

// Thanh toán gần đây
$recentPayments = $conn->query("SELECT tp.*, ti.net_amount, ti.paid_amount,
    u.full_name, st.student_code, sm.semester_name, sm.school_year,
    pu.full_name AS paid_by_name
    FROM tuition_payments tp
    JOIN tuition_invoices ti ON tp.invoice_id=ti.id
    JOIN students st ON ti.student_id=st.id
    JOIN users u ON st.user_id=u.id
    JOIN tuition_periods tper ON ti.period_id=tper.id
    JOIN semesters sm ON tper.semester_id=sm.id
    LEFT JOIN users pu ON tp.paid_by=pu.id
    ORDER BY tp.paid_at DESC LIMIT 8");
?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['bi-receipt','rgba(13,45,107,.1)','#0d2d6b','Tổng hóa đơn', number_format($totalInvoices)],
        ['bi-check-circle-fill','rgba(16,185,129,.12)','#059669','Đã đóng', number_format($totalPaid)],
        ['bi-hourglass-split','rgba(245,158,11,.12)','#d97706','Chưa đóng', number_format($totalUnpaid)],
        ['bi-exclamation-triangle-fill','rgba(239,68,68,.12)','#dc2626','Quá hạn', number_format($totalOverdue)],
        ['bi-cash-stack','rgba(245,166,35,.15)','#d4891a','Đã thu', fmtVND($sumCollected)],
        ['bi-wallet2','rgba(139,92,246,.12)','#7c3aed','Còn nợ', fmtVND($sumRemaining)],
    ];
    foreach ($cards as [$icon,$bg,$color,$lbl,$val]): ?>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div class="ic" style="background:<?php echo $bg; ?>;color:<?php echo $color; ?>"><i class="bi <?php echo $icon; ?>"></i></div>
            <div class="vl" style="color:<?php echo $color; ?>;font-size:1.3rem;"><?php echo $val; ?></div>
            <div class="lb"><?php echo $lbl; ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <!-- Đợt thu gần nhất -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-calendar-range-fill me-2"></i>Đợt thu học phí</span>
                <?php if ($_isManager): ?>
                <a href="periods.php" class="btn btn-gold btn-sm"><i class="bi bi-plus-lg me-1"></i>Quản lý</a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if ($latestPeriod && $latestPeriod->num_rows > 0):
                    $pStatusMap = ['draft'=>['secondary','Nháp'],'published'=>['success','Đã công bố'],'closed'=>['dark','Đã đóng']];
                    while ($p = $latestPeriod->fetch_assoc()):
                        $ps = $pStatusMap[$p['status']] ?? ['secondary',$p['status']];
                        $pct = $p['inv_count'] > 0 ? round($p['paid_count']/$p['inv_count']*100) : 0;
                ?>
                <div class="p-3 border-bottom">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <div>
                            <div class="fw-bold small"><?php echo htmlspecialchars($p['title']); ?></div>
                            <div class="text-muted" style="font-size:.75rem;"><?php echo htmlspecialchars($p['semester_name'].' '.$p['school_year']); ?></div>
                        </div>
                        <span class="badge bg-<?php echo $ps[0]; ?>"><?php echo $ps[1]; ?></span>
                    </div>
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>Hạn: <strong><?php echo date('d/m/Y',strtotime($p['due_date'])); ?></strong></span>
                        <span><?php echo $p['paid_count']; ?>/<?php echo $p['inv_count']; ?> đã đóng</span>
                    </div>
                    <div class="progress" style="height:5px;border-radius:3px;">
                        <div class="progress-bar bg-success" style="width:<?php echo $pct; ?>%"></div>
                    </div>
                </div>
                <?php endwhile; else: ?>
                <div class="text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Chưa có đợt thu nào</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Thanh toán gần đây -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history me-2"></i>Thanh toán gần đây</span>
                <a href="payments.php" class="btn btn-sm btn-outline-light">Xem tất cả</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="font-size:.83rem;">
                        <thead><tr><th>Sinh viên</th><th>Học kỳ</th><th class="text-end">Số tiền</th><th>Hình thức</th><th>Thời gian</th></tr></thead>
                        <tbody>
                        <?php if ($recentPayments && $recentPayments->num_rows > 0):
                            $mMap = ['cash'=>'Tiền mặt','bank_transfer'=>'Chuyển khoản','online'=>'Online','other'=>'Khác'];
                            while ($pay = $recentPayments->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($pay['full_name']); ?></div>
                                <div class="text-muted" style="font-size:.72rem;"><?php echo htmlspecialchars($pay['student_code']); ?></div>
                            </td>
                            <td class="text-muted small"><?php echo htmlspecialchars($pay['semester_name'].' '.$pay['school_year']); ?></td>
                            <td class="text-end fw-bold text-success"><?php echo fmtVND($pay['amount']); ?></td>
                            <td class="small"><?php echo $mMap[$pay['method']]??$pay['method']; ?></td>
                            <td class="text-muted small"><?php echo date('d/m H:i',strtotime($pay['paid_at'])); ?></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="5" class="text-center text-muted py-4"><i class="bi bi-inbox d-block mb-1"></i>Chưa có thanh toán</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
