<?php
$pageTitle = 'Báo cáo kế toán';
include __DIR__ . '/includes/header.php';
if (!$_isManager) { header('Location: index.php'); exit(); }

if (!function_exists('fmtVND')) {
    function fmtVND($n) { return number_format((float)$n, 0, ',', '.') . ' ₫'; }
}

$filterSemester = (int)($_GET['semester_id'] ?? 0);
$filterPeriod   = (int)($_GET['period_id'] ?? 0);

$semesters = $conn->query("SELECT id, semester_name, school_year FROM semesters ORDER BY id DESC");
$periods = $conn->query(
    "SELECT tp.id, tp.title, tp.semester_id, sm.semester_name, sm.school_year
     FROM tuition_periods tp
     JOIN semesters sm ON sm.id = tp.semester_id
     ORDER BY tp.created_at DESC"
);

$where = "ti.status != 'draft'";
if ($filterPeriod > 0) {
    $where .= " AND ti.period_id = " . $filterPeriod;
}
if ($filterSemester > 0) {
    $where .= " AND ti.semester_id = " . $filterSemester;
}

$summary = $conn->query(
    "SELECT COUNT(*) AS total_invoice,
            COALESCE(SUM(ti.total_credits),0) AS total_credits,
            COALESCE(SUM(ti.gross_amount),0) AS gross_amount,
            COALESCE(SUM(ti.discount),0) AS discount,
            COALESCE(SUM(ti.net_amount),0) AS net_amount,
            COALESCE(SUM(ti.paid_amount),0) AS paid_amount,
            COALESCE(SUM(GREATEST(ti.net_amount - ti.paid_amount, 0)),0) AS remaining_amount,
            COALESCE(SUM(ti.status = 'paid'),0) AS paid_count,
            COALESCE(SUM(ti.status = 'partial'),0) AS partial_count,
            COALESCE(SUM(ti.status = 'unpaid'),0) AS unpaid_count,
            COALESCE(SUM(ti.status = 'overdue'),0) AS overdue_count,
            COALESCE(SUM(ti.status = 'waived'),0) AS waived_count
     FROM tuition_invoices ti
     WHERE $where"
)->fetch_assoc() ?: [];

$periodRows = $conn->query(
    "SELECT tp.id, tp.title, tp.status AS period_status, tp.due_date,
            sm.semester_name, sm.school_year,
            COUNT(ti.id) AS invoice_count,
            COALESCE(SUM(ti.net_amount),0) AS net_amount,
            COALESCE(SUM(ti.paid_amount),0) AS paid_amount,
            COALESCE(SUM(GREATEST(ti.net_amount - ti.paid_amount, 0)),0) AS remaining_amount,
            COALESCE(SUM(ti.status = 'paid'),0) AS paid_count,
            COALESCE(SUM(ti.status IN ('unpaid','partial','overdue')),0) AS debt_count
     FROM tuition_periods tp
     JOIN semesters sm ON sm.id = tp.semester_id
     LEFT JOIN tuition_invoices ti ON ti.period_id = tp.id AND ti.status != 'draft'
     WHERE " . ($filterSemester > 0 ? "tp.semester_id = $filterSemester" : "1=1") . "
       " . ($filterPeriod > 0 ? "AND tp.id = $filterPeriod" : "") . "
     GROUP BY tp.id, tp.title, tp.status, tp.due_date, sm.semester_name, sm.school_year
     ORDER BY tp.created_at DESC"
);

$majorRows = $conn->query(
    "SELECT COALESCE(f.faculty_name, 'Chưa xác định') AS faculty_name,
            COALESCE(m.major_name, 'Chưa xác định') AS major_name,
            COUNT(ti.id) AS invoice_count,
            COALESCE(SUM(ti.net_amount),0) AS net_amount,
            COALESCE(SUM(ti.paid_amount),0) AS paid_amount,
            COALESCE(SUM(GREATEST(ti.net_amount - ti.paid_amount, 0)),0) AS remaining_amount,
            COALESCE(SUM(ti.status IN ('unpaid','partial','overdue')),0) AS debt_count
     FROM tuition_invoices ti
     JOIN students st ON st.id = ti.student_id
     LEFT JOIN classes cl ON cl.id = st.class_id
     LEFT JOIN majors m ON m.id = cl.major_id
     LEFT JOIN faculties f ON f.id = m.faculty_id
     WHERE $where
     GROUP BY f.faculty_name, m.major_name
     ORDER BY remaining_amount DESC, net_amount DESC
     LIMIT 20"
);

$debtRows = $conn->query(
    "SELECT ti.id, ti.status, ti.net_amount, ti.paid_amount,
            GREATEST(ti.net_amount - ti.paid_amount, 0) AS remaining_amount,
            u.full_name, st.student_code, cl.class_name, tp.title AS period_title,
            sm.semester_name, sm.school_year, tp.due_date
     FROM tuition_invoices ti
     JOIN students st ON st.id = ti.student_id
     JOIN users u ON u.id = st.user_id
     LEFT JOIN classes cl ON cl.id = st.class_id
     JOIN tuition_periods tp ON tp.id = ti.period_id
     JOIN semesters sm ON sm.id = ti.semester_id
     WHERE $where
       AND ti.status IN ('unpaid','partial','overdue')
       AND ti.net_amount > ti.paid_amount
     ORDER BY remaining_amount DESC, tp.due_date ASC
     LIMIT 30"
);

$methodRows = $conn->query(
    "SELECT tp.method, COUNT(*) AS payment_count, COALESCE(SUM(tp.amount),0) AS amount
     FROM tuition_payments tp
     JOIN tuition_invoices ti ON ti.id = tp.invoice_id
     WHERE " . str_replace('ti.status != \'draft\'', '1=1', $where) . "
     GROUP BY tp.method
     ORDER BY amount DESC"
);

$dailyRows = $conn->query(
    "SELECT DATE(tp.paid_at) AS paid_date, COALESCE(SUM(tp.amount),0) AS amount, COUNT(*) AS payment_count
     FROM tuition_payments tp
     JOIN tuition_invoices ti ON ti.id = tp.invoice_id
     WHERE " . str_replace('ti.status != \'draft\'', '1=1', $where) . "
       AND tp.paid_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
     GROUP BY DATE(tp.paid_at)
     ORDER BY paid_date DESC"
);

$statusMap = [
    'unpaid' => ['warning', 'Chưa đóng'],
    'partial' => ['info', 'Một phần'],
    'paid' => ['success', 'Đã đóng'],
    'overdue' => ['danger', 'Quá hạn'],
    'waived' => ['secondary', 'Miễn'],
];
$periodStatusMap = ['draft'=>['secondary','Nháp'], 'published'=>['success','Đã công bố'], 'closed'=>['dark','Đã đóng']];
$methodMap = ['cash'=>'Tiền mặt', 'bank_transfer'=>'Chuyển khoản', 'online'=>'Online', 'other'=>'Khác'];
?>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Học kỳ</label>
                <select name="semester_id" class="form-select">
                    <option value="0">-- Tất cả học kỳ --</option>
                    <?php if ($semesters) while ($sm = $semesters->fetch_assoc()): ?>
                    <option value="<?php echo (int)$sm['id']; ?>" <?php echo $filterSemester === (int)$sm['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($sm['semester_name'] . ' ' . $sm['school_year']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Đợt thu</label>
                <select name="period_id" class="form-select">
                    <option value="0">-- Tất cả đợt thu --</option>
                    <?php if ($periods) while ($p = $periods->fetch_assoc()): ?>
                    <option value="<?php echo (int)$p['id']; ?>" data-semester="<?php echo (int)$p['semester_id']; ?>" <?php echo $filterPeriod === (int)$p['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($p['title'] . ' - ' . $p['semester_name'] . ' ' . $p['school_year']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-navy flex-fill"><i class="bi bi-search me-1"></i>Lọc báo cáo</button>
                <?php if ($filterSemester || $filterPeriod): ?>
                <a href="reports.php" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['bi-receipt', '#0d2d6b', 'Tổng hóa đơn', number_format((int)($summary['total_invoice'] ?? 0))],
        ['bi-wallet2', '#d4891a', 'Phải thu', fmtVND($summary['net_amount'] ?? 0)],
        ['bi-cash-stack', '#059669', 'Đã thu', fmtVND($summary['paid_amount'] ?? 0)],
        ['bi-exclamation-circle-fill', '#dc2626', 'Còn nợ', fmtVND($summary['remaining_amount'] ?? 0)],
        ['bi-percent', '#7c3aed', 'Miễn giảm', fmtVND($summary['discount'] ?? 0)],
        ['bi-mortarboard-fill', '#0f766e', 'Tổng tín chỉ', number_format((int)($summary['total_credits'] ?? 0))],
    ];
    foreach ($cards as [$icon, $color, $label, $value]): ?>
    <div class="col-6 col-lg-2">
        <div class="stat-card">
            <div class="ic" style="background:<?php echo $color; ?>18;color:<?php echo $color; ?>"><i class="bi <?php echo $icon; ?>"></i></div>
            <div class="vl" style="font-size:1.15rem;color:<?php echo $color; ?>"><?php echo $value; ?></div>
            <div class="lb"><?php echo $label; ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-calendar-range-fill me-2"></i>Tổng hợp theo đợt thu</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle" style="font-size:.85rem;">
                        <thead><tr><th>Đợt thu</th><th class="text-center">HĐ</th><th class="text-end">Phải thu</th><th class="text-end">Đã thu</th><th class="text-end">Còn nợ</th><th class="text-center">Trạng thái</th></tr></thead>
                        <tbody>
                        <?php if ($periodRows && $periodRows->num_rows > 0): while ($row = $periodRows->fetch_assoc()):
                            $ps = $periodStatusMap[$row['period_status']] ?? ['secondary', $row['period_status']];
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($row['title']); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($row['semester_name'] . ' ' . $row['school_year']); ?> · Hạn <?php echo $row['due_date'] ? date('d/m/Y', strtotime($row['due_date'])) : '--'; ?></div>
                            </td>
                            <td class="text-center fw-bold"><?php echo (int)$row['invoice_count']; ?></td>
                            <td class="text-end"><?php echo fmtVND($row['net_amount']); ?></td>
                            <td class="text-end text-success"><?php echo fmtVND($row['paid_amount']); ?></td>
                            <td class="text-end fw-bold <?php echo (float)$row['remaining_amount'] > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo fmtVND($row['remaining_amount']); ?></td>
                            <td class="text-center"><span class="badge bg-<?php echo $ps[0]; ?>"><?php echo $ps[1]; ?></span></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Chưa có dữ liệu đợt thu.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-pie-chart-fill me-2"></i>Trạng thái hóa đơn</div>
            <div class="card-body">
                <?php
                $statusCards = [
                    ['success', 'Đã đóng', (int)($summary['paid_count'] ?? 0)],
                    ['info', 'Một phần', (int)($summary['partial_count'] ?? 0)],
                    ['warning', 'Chưa đóng', (int)($summary['unpaid_count'] ?? 0)],
                    ['danger', 'Quá hạn', (int)($summary['overdue_count'] ?? 0)],
                    ['secondary', 'Miễn', (int)($summary['waived_count'] ?? 0)],
                ];
                foreach ($statusCards as [$color, $label, $count]): ?>
                <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                    <span><span class="badge bg-<?php echo $color; ?> me-2">&nbsp;</span><?php echo $label; ?></span>
                    <strong><?php echo number_format($count); ?></strong>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-building me-2"></i>Công nợ theo khoa/ngành</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="font-size:.85rem;">
                        <thead><tr><th>Khoa / ngành</th><th class="text-center">HĐ</th><th class="text-end">Phải thu</th><th class="text-end">Đã thu</th><th class="text-end">Còn nợ</th></tr></thead>
                        <tbody>
                        <?php if ($majorRows && $majorRows->num_rows > 0): while ($row = $majorRows->fetch_assoc()): ?>
                        <tr>
                            <td><div class="fw-semibold"><?php echo htmlspecialchars($row['major_name']); ?></div><div class="small text-muted"><?php echo htmlspecialchars($row['faculty_name']); ?></div></td>
                            <td class="text-center"><?php echo (int)$row['invoice_count']; ?></td>
                            <td class="text-end"><?php echo fmtVND($row['net_amount']); ?></td>
                            <td class="text-end text-success"><?php echo fmtVND($row['paid_amount']); ?></td>
                            <td class="text-end fw-bold <?php echo (float)$row['remaining_amount'] > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo fmtVND($row['remaining_amount']); ?></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Chưa có dữ liệu theo ngành.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-credit-card-2-front-fill me-2"></i>Thanh toán theo hình thức</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0" style="font-size:.85rem;">
                    <thead><tr><th>Hình thức</th><th class="text-center">Lượt</th><th class="text-end">Số tiền</th></tr></thead>
                    <tbody>
                    <?php if ($methodRows && $methodRows->num_rows > 0): while ($row = $methodRows->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($methodMap[$row['method']] ?? $row['method']); ?></td>
                        <td class="text-center"><?php echo (int)$row['payment_count']; ?></td>
                        <td class="text-end fw-bold text-success"><?php echo fmtVND($row['amount']); ?></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="3" class="text-center text-muted py-4">Chưa có thanh toán.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-exclamation-triangle-fill me-2"></i>Sinh viên còn nợ nhiều nhất</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle" style="font-size:.85rem;">
                        <thead><tr><th>Sinh viên</th><th>Đợt thu</th><th class="text-end">Còn nợ</th><th class="text-center">Trạng thái</th></tr></thead>
                        <tbody>
                        <?php if ($debtRows && $debtRows->num_rows > 0): while ($row = $debtRows->fetch_assoc()):
                            $st = $statusMap[$row['status']] ?? ['secondary', $row['status']];
                        ?>
                        <tr>
                            <td><div class="fw-semibold"><?php echo htmlspecialchars($row['full_name']); ?></div><div class="small text-muted"><?php echo htmlspecialchars($row['student_code'] . ' · ' . ($row['class_name'] ?? '')); ?></div></td>
                            <td><div class="small"><?php echo htmlspecialchars($row['period_title']); ?></div><div class="small text-muted"><?php echo htmlspecialchars($row['semester_name'] . ' ' . $row['school_year']); ?></div></td>
                            <td class="text-end fw-bold text-danger"><?php echo fmtVND($row['remaining_amount']); ?></td>
                            <td class="text-center"><span class="badge bg-<?php echo $st[0]; ?>"><?php echo $st[1]; ?></span></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">Không có sinh viên còn nợ trong phạm vi lọc.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-calendar-week me-2"></i>Thu 14 ngày gần đây</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0" style="font-size:.85rem;">
                    <thead><tr><th>Ngày</th><th class="text-center">Lượt</th><th class="text-end">Đã thu</th></tr></thead>
                    <tbody>
                    <?php if ($dailyRows && $dailyRows->num_rows > 0): while ($row = $dailyRows->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($row['paid_date'])); ?></td>
                        <td class="text-center"><?php echo (int)$row['payment_count']; ?></td>
                        <td class="text-end fw-bold text-success"><?php echo fmtVND($row['amount']); ?></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="3" class="text-center text-muted py-4">Chưa có thanh toán gần đây.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
