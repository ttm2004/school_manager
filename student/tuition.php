<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('student');
$pageTitle = 'Học phí của tôi';

// Lấy student_id
$uid = (int)$_SESSION['user_id'];
$stRes = $conn->query("SELECT s.id, s.student_code, c.class_name, m.major_name, m.tuition_per_credit
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN majors m ON c.major_id = m.id
    WHERE s.user_id = $uid LIMIT 1");
$student = $stRes ? $stRes->fetch_assoc() : null;
if (!$student) { header('Location: /university/student/index.php'); exit(); }

$sid = (int)$student['id'];

// Lấy tất cả hóa đơn
$invoices = $conn->query("
    SELECT ti.*, sm.semester_name, sm.school_year
    FROM tuition_invoices ti
    JOIN semesters sm ON ti.semester_id = sm.id
    WHERE ti.student_id = $sid
    ORDER BY sm.school_year DESC, sm.semester_name DESC
");

// Tổng hợp
$totalNet  = 0; $totalPaid = 0;
$rows = [];
if ($invoices) while ($r = $invoices->fetch_assoc()) {
    $rows[] = $r;
    $totalNet  += $r['net_amount'];
    $totalPaid += $r['paid_amount'];
}
$totalRemaining = $totalNet - $totalPaid;

$statusMap = [
    'unpaid'  => ['warning', 'Chưa đóng'],
    'partial' => ['info',    'Đóng một phần'],
    'paid'    => ['success', 'Đã đóng'],
    'overdue' => ['danger',  'Quá hạn'],
    'waived'  => ['secondary','Miễn học phí'],
];

function fmtVND($n) { return number_format(floatval($n),0,',','.') . ' ₫'; }
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Học phí — TDMU</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/university/assets/css/style.css">
    <style>
        body { background:#f0f2f7; }
        .student-topbar { background:#0d2d6b; color:#fff; padding:12px 24px; display:flex; align-items:center; justify-content:space-between; }
        .student-topbar .brand { font-weight:700; font-size:1.1rem; display:flex; align-items:center; gap:8px; }
        .student-topbar .brand i { color:#f5a623; }
        .page-content { max-width:960px; margin:0 auto; padding:28px 16px; }
        .summary-card { background:#fff; border-radius:14px; padding:20px 24px; box-shadow:0 2px 12px rgba(13,45,107,.08); margin-bottom:24px; }
        .stat-pill { background:#f0f2f7; border-radius:10px; padding:12px 18px; text-align:center; }
        .stat-pill .val { font-size:1.3rem; font-weight:800; }
        .stat-pill .lbl { font-size:.75rem; color:#6b7a99; margin-top:2px; }
        .invoice-card { background:#fff; border-radius:12px; box-shadow:0 2px 10px rgba(13,45,107,.07); margin-bottom:16px; overflow:hidden; }
        .invoice-card .inv-header { background:#0d2d6b; color:#fff; padding:12px 18px; display:flex; align-items:center; justify-content:space-between; }
        .invoice-card .inv-body { padding:16px 18px; }
        .inv-row { display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px solid #f0f2f7; font-size:.88rem; }
        .inv-row:last-child { border-bottom:none; }
        .inv-row .lbl { color:#6b7a99; }
        .inv-row .val { font-weight:600; }
        .progress-bar-custom { height:8px; border-radius:4px; background:#e2e8f0; overflow:hidden; margin-top:10px; }
        .progress-bar-custom .fill { height:100%; border-radius:4px; background:#0d2d6b; transition:width .4s; }
    </style>
</head>
<body>
<div class="student-topbar">
    <div class="brand"><i class="bi bi-mortarboard-fill"></i> TDMU — Học phí</div>
    <div class="d-flex align-items-center gap-3">
        <span class="small opacity-75"><?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?></span>
        <a href="/university/student/index.php" class="btn btn-sm btn-outline-light">
            <i class="bi bi-arrow-left me-1"></i>Trang chủ
        </a>
    </div>
</div>

<div class="page-content">
    <!-- Thông tin sinh viên -->
    <div class="summary-card mb-4">
        <div class="d-flex align-items-center gap-3 mb-3">
            <div style="width:48px;height:48px;border-radius:50%;background:#0d2d6b;display:flex;align-items:center;justify-content:center;color:#f5a623;font-size:1.4rem;">
                <i class="bi bi-person-fill"></i>
            </div>
            <div>
                <div class="fw-bold fs-5"><?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?></div>
                <div class="text-muted small">
                    <?php echo htmlspecialchars($student['student_code']); ?> &bull;
                    <?php echo htmlspecialchars($student['class_name'] ?? ''); ?> &bull;
                    <?php echo htmlspecialchars($student['major_name'] ?? ''); ?>
                </div>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-4">
                <div class="stat-pill">
                    <div class="val text-navy"><?php echo fmtVND($totalNet); ?></div>
                    <div class="lbl">Tổng phải đóng</div>
                </div>
            </div>
            <div class="col-4">
                <div class="stat-pill">
                    <div class="val text-success"><?php echo fmtVND($totalPaid); ?></div>
                    <div class="lbl">Đã đóng</div>
                </div>
            </div>
            <div class="col-4">
                <div class="stat-pill">
                    <div class="val <?php echo $totalRemaining > 0 ? 'text-danger' : 'text-success'; ?>">
                        <?php echo fmtVND($totalRemaining); ?>
                    </div>
                    <div class="lbl">Còn lại</div>
                </div>
            </div>
        </div>
        <?php if ($totalNet > 0): ?>
        <div class="progress-bar-custom mt-3">
            <div class="fill" style="width:<?php echo min(100, round($totalPaid / $totalNet * 100)); ?>%;"></div>
        </div>
        <div class="text-end text-muted" style="font-size:.72rem;margin-top:4px;">
            Đã đóng <?php echo round($totalPaid / $totalNet * 100); ?>%
        </div>
        <?php endif; ?>
    </div>

    <!-- Danh sách hóa đơn -->
    <h6 class="fw-bold text-navy mb-3"><i class="bi bi-receipt me-2"></i>Lịch sử học phí theo học kỳ</h6>

    <?php if (empty($rows)): ?>
    <div class="text-center text-muted py-5">
        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
        Chưa có hóa đơn học phí nào.
    </div>
    <?php else: ?>
    <?php foreach ($rows as $inv):
        $s = $statusMap[$inv['status']] ?? ['secondary', $inv['status']];
        $pct = $inv['net_amount'] > 0 ? min(100, round($inv['paid_amount'] / $inv['net_amount'] * 100)) : 0;
        $remaining = max(0, $inv['net_amount'] - $inv['paid_amount']);
    ?>
    <div class="invoice-card">
        <div class="inv-header">
            <div>
                <div class="fw-bold"><?php echo htmlspecialchars($inv['semester_name'] . ' ' . $inv['school_year']); ?></div>
                <div class="small opacity-75"><?php echo $inv['total_credits']; ?> tín chỉ &times; <?php echo fmtVND($inv['unit_price']); ?>/TC</div>
            </div>
            <span class="badge bg-<?php echo $s[0]; ?> fs-6"><?php echo $s[1]; ?></span>
        </div>
        <div class="inv-body">
            <div class="inv-row">
                <span class="lbl">Học phí gốc</span>
                <span class="val"><?php echo fmtVND($inv['gross_amount']); ?></span>
            </div>
            <?php if ($inv['discount'] > 0): ?>
            <div class="inv-row">
                <span class="lbl">Miễn giảm / Học bổng</span>
                <span class="val text-success">- <?php echo fmtVND($inv['discount']); ?></span>
            </div>
            <?php endif; ?>
            <div class="inv-row">
                <span class="lbl fw-bold text-navy">Phải đóng</span>
                <span class="val fw-bold text-navy"><?php echo fmtVND($inv['net_amount']); ?></span>
            </div>
            <div class="inv-row">
                <span class="lbl">Đã đóng</span>
                <span class="val text-success"><?php echo fmtVND($inv['paid_amount']); ?></span>
            </div>
            <?php if ($remaining > 0): ?>
            <div class="inv-row">
                <span class="lbl">Còn lại</span>
                <span class="val text-danger"><?php echo fmtVND($remaining); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($inv['due_date']): ?>
            <div class="inv-row">
                <span class="lbl">Hạn đóng</span>
                <span class="val <?php echo ($inv['status'] === 'overdue') ? 'text-danger' : ''; ?>">
                    <?php echo date('d/m/Y', strtotime($inv['due_date'])); ?>
                    <?php if ($inv['status'] === 'overdue'): ?><i class="bi bi-exclamation-triangle-fill ms-1 text-danger"></i><?php endif; ?>
                </span>
            </div>
            <?php endif; ?>
            <?php if ($inv['note']): ?>
            <div class="inv-row">
                <span class="lbl">Ghi chú</span>
                <span class="val text-muted"><?php echo htmlspecialchars($inv['note']); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($inv['net_amount'] > 0): ?>
            <div class="progress-bar-custom">
                <div class="fill" style="width:<?php echo $pct; ?>%;background:<?php echo $pct >= 100 ? '#10b981' : '#0d2d6b'; ?>;"></div>
            </div>
            <div class="text-end text-muted" style="font-size:.7rem;margin-top:3px;">Đã đóng <?php echo $pct; ?>%</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<?php include_once __DIR__ . '/../includes/analytics_widget.php'; ?>
</body>
</html>
