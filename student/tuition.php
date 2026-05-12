<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('student');

function fmtVNDStudent($amount): string {
    return number_format((float)$amount, 0, ',', '.') . ' VND';
}

$stmt = $conn->prepare(
    "SELECT s.*, u.full_name
     FROM students s
     JOIN users u ON s.user_id = u.id
     WHERE s.user_id = ? LIMIT 1"
);
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    header('Location: /university/login.php?logout=1');
    exit();
}

$invoices = [];
$summary = [
    'total_net' => 0,
    'total_paid' => 0,
    'total_remaining' => 0,
    'debt_count' => 0,
];

$tableExists = $conn->query("SHOW TABLES LIKE 'tuition_invoices'")->num_rows > 0
    && $conn->query("SHOW TABLES LIKE 'tuition_periods'")->num_rows > 0;

if ($tableExists) {
    $stmtInv = $conn->prepare(
        "SELECT ti.*, tp.title AS period_title, tp.open_date, tp.due_date, tp.status AS period_status,
                sm.semester_name, sm.school_year
         FROM tuition_invoices ti
         JOIN tuition_periods tp ON ti.period_id = tp.id
         JOIN semesters sm ON ti.semester_id = sm.id
         WHERE ti.student_id = ?
           AND ti.status != 'draft'
           AND tp.status IN ('published','closed')
         ORDER BY sm.id DESC, tp.due_date DESC, ti.id DESC"
    );
    $stmtInv->bind_param('i', $student['id']);
    $stmtInv->execute();
    $invoices = $stmtInv->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtInv->close();

    foreach ($invoices as &$invoice) {
        $invoice['remaining_amount'] = max(0, (float)$invoice['net_amount'] - (float)$invoice['paid_amount']);
        $summary['total_net'] += (float)$invoice['net_amount'];
        $summary['total_paid'] += (float)$invoice['paid_amount'];
        $summary['total_remaining'] += (float)$invoice['remaining_amount'];
        if (in_array($invoice['status'], ['unpaid', 'partial', 'overdue'], true)) {
            $summary['debt_count']++;
        }

        $stmtPay = $conn->prepare(
            "SELECT amount, method, reference, note, paid_at
             FROM tuition_payments
             WHERE invoice_id = ?
             ORDER BY paid_at DESC"
        );
        $stmtPay->bind_param('i', $invoice['id']);
        $stmtPay->execute();
        $invoice['payments'] = $stmtPay->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtPay->close();
    }
    unset($invoice);
}

$statusMap = [
    'unpaid' => ['warning', 'Chua dong'],
    'partial' => ['info', 'Dong mot phan'],
    'paid' => ['success', 'Da dong'],
    'overdue' => ['danger', 'Qua han'],
    'waived' => ['secondary', 'Mien giam'],
];

$methodMap = [
    'cash' => 'Tien mat',
    'bank_transfer' => 'Chuyen khoan',
    'online' => 'Online',
    'other' => 'Khac',
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
    <title>Hoc phi - Sinh vien</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/university/assets/css/style.css">
</head>
<body>
<div class="student-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="student-main">
        <div class="student-topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary d-lg-none" onclick="document.querySelector('.student-sidebar').classList.toggle('show')">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <span class="fw-bold text-navy">Hoc phi cua toi</span>
            </div>
            <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i></a>
        </div>

        <div class="student-content">
            <?php if (!$tableExists): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                He thong hoc phi chua duoc khoi tao. Vui long lien he phong Tai chinh.
            </div>
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="stat-card-student">
                        <i class="bi bi-receipt text-navy fs-3"></i>
                        <div class="stat-value"><?php echo fmtVNDStudent($summary['total_net']); ?></div>
                        <div class="stat-label">Tong phai dong</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card-student">
                        <i class="bi bi-check-circle-fill text-success fs-3"></i>
                        <div class="stat-value"><?php echo fmtVNDStudent($summary['total_paid']); ?></div>
                        <div class="stat-label">Da dong</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card-student">
                        <i class="bi bi-exclamation-circle-fill text-danger fs-3"></i>
                        <div class="stat-value"><?php echo fmtVNDStudent($summary['total_remaining']); ?></div>
                        <div class="stat-label">Con no</div>
                    </div>
                </div>
            </div>

            <?php if ($summary['debt_count'] > 0): ?>
            <div class="alert alert-danger">
                <i class="bi bi-lock-fill me-2"></i>
                Ban con <?php echo (int)$summary['debt_count']; ?> hoa don chua hoan tat. Viec dang ky hoc phan co the bi khoa cho den khi hoan thanh hoc phi.
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <i class="bi bi-list-ul me-2"></i>Danh sach hoa don hoc phi
                </div>
                <?php if (empty($invoices)): ?>
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                    Chua co hoa don hoc phi nao duoc cong bo.
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Dot thu</th>
                                <th>Hoc ky</th>
                                <th class="text-center">Tin chi</th>
                                <th class="text-end">Phai dong</th>
                                <th class="text-end">Da dong</th>
                                <th class="text-end">Con lai</th>
                                <th class="text-center">Trang thai</th>
                                <th class="text-center">Chi tiet</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($invoices as $invoice):
                            $status = $statusMap[$invoice['status']] ?? ['secondary', $invoice['status']];
                        ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($invoice['period_title']); ?></div>
                                    <div class="text-muted small">
                                        Han: <?php echo $invoice['due_date'] ? date('d/m/Y', strtotime($invoice['due_date'])) : '--'; ?>
                                    </div>
                                </td>
                                <td class="small text-muted"><?php echo htmlspecialchars($invoice['semester_name'] . ' ' . $invoice['school_year']); ?></td>
                                <td class="text-center"><span class="badge bg-navy"><?php echo (int)$invoice['total_credits']; ?></span></td>
                                <td class="text-end"><?php echo fmtVNDStudent($invoice['net_amount']); ?></td>
                                <td class="text-end text-success"><?php echo fmtVNDStudent($invoice['paid_amount']); ?></td>
                                <td class="text-end fw-bold <?php echo $invoice['remaining_amount'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo $invoice['remaining_amount'] > 0 ? fmtVNDStudent($invoice['remaining_amount']) : '--'; ?>
                                </td>
                                <td class="text-center"><span class="badge bg-<?php echo $status[0]; ?>"><?php echo $status[1]; ?></span></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-navy" type="button" data-bs-toggle="collapse" data-bs-target="#invoice-<?php echo (int)$invoice['id']; ?>">
                                        <i class="bi bi-chevron-down"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr class="collapse" id="invoice-<?php echo (int)$invoice['id']; ?>">
                                <td colspan="8" class="bg-light">
                                    <div class="p-3">
                                        <div class="row g-3 mb-3">
                                            <div class="col-md-3"><span class="text-muted small">Don gia/TC</span><div class="fw-semibold"><?php echo fmtVNDStudent($invoice['unit_price']); ?></div></div>
                                            <div class="col-md-3"><span class="text-muted small">Hoc phi goc</span><div class="fw-semibold"><?php echo fmtVNDStudent($invoice['gross_amount']); ?></div></div>
                                            <div class="col-md-3"><span class="text-muted small">Mien giam</span><div class="fw-semibold"><?php echo fmtVNDStudent($invoice['discount']); ?></div></div>
                                            <div class="col-md-3"><span class="text-muted small">Ngay cong bo</span><div class="fw-semibold"><?php echo $invoice['open_date'] ? date('d/m/Y', strtotime($invoice['open_date'])) : '--'; ?></div></div>
                                        </div>
                                        <div class="fw-semibold mb-2"><i class="bi bi-clock-history me-1"></i>Lich su thanh toan</div>
                                        <?php if (empty($invoice['payments'])): ?>
                                        <div class="text-muted small">Chua ghi nhan thanh toan.</div>
                                        <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered mb-0">
                                                <thead><tr><th>Thoi gian</th><th class="text-end">So tien</th><th>Hinh thuc</th><th>Ma giao dich</th><th>Ghi chu</th></tr></thead>
                                                <tbody>
                                                <?php foreach ($invoice['payments'] as $payment): ?>
                                                <tr>
                                                    <td class="small"><?php echo date('d/m/Y H:i', strtotime($payment['paid_at'])); ?></td>
                                                    <td class="text-end text-success fw-semibold"><?php echo fmtVNDStudent($payment['amount']); ?></td>
                                                    <td class="small"><?php echo htmlspecialchars($methodMap[$payment['method']] ?? $payment['method']); ?></td>
                                                    <td class="small text-muted"><?php echo htmlspecialchars($payment['reference'] ?: '--'); ?></td>
                                                    <td class="small text-muted"><?php echo htmlspecialchars($payment['note'] ?: '--'); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="student-footer">&copy; <?php echo date('Y'); ?> TDMU - Truong Dai hoc Thu Dau Mot</div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<?php include_once __DIR__ . "/../includes/analytics_widget.php"; ?>
</body>
</html>
