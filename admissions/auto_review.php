<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireAnyRole(['admissions_manager', 'admissions_staff']);
$pageTitle = 'Xét tuyển tự động';

// ── Download file mẫu CSV ────────────────────────────────────
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="mau_chi_tieu_diem_chuan.csv"');
    echo "\xEF\xBB\xBF"; // BOM UTF-8
    echo "major_code,major_name,quota,threshold,year\n";
    echo "7480201,Công nghệ thông tin,120,18.50,2026\n";
    echo "7480103,Kỹ thuật phần mềm,80,17.75,2026\n";
    echo "7340101,Quản trị kinh doanh,100,16.00,2026\n";
    exit();
}

include __DIR__ . '/includes/header.php';

// ── Trạng thái đợt tuyển sinh ────────────────────────────────
$roundPhase    = getRoundPhase();
$activeRound   = getActiveRound();
$reviewAllowed = in_array($roundPhase, ['reviewing', 'supp_reviewing']);
$reviewLocked  = !$reviewAllowed;
$isManager     = hasRole('admissions_manager');

// ── Biến kết quả ─────────────────────────────────────────────
$importErrors  = [];   // lỗi khi parse CSV
$importRows    = [];   // dữ liệu đã parse từ CSV (session)
$reviewResults = [];   // kết quả sau khi chạy xét tuyển
$success = $error = '';

// ── Khôi phục import từ session ──────────────────────────────
if (!empty($_SESSION['import_rows'])) {
    $importRows = $_SESSION['import_rows'];
}

// ════════════════════════════════════════════════════════════
// XỬ LÝ POST
// ════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── 1. IMPORT CSV ────────────────────────────────────────
    if ($action === 'import_csv') {
        if (!$isManager) {
            $error = 'Chỉ Trưởng phòng mới có quyền import.';
        } elseif (empty($_FILES['csv_file']['tmp_name'])) {
            $error = 'Vui lòng chọn file CSV.';
        } else {
            $file = $_FILES['csv_file']['tmp_name'];
            $ext  = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['csv', 'txt'])) {
                $error = 'Chỉ hỗ trợ file CSV (.csv).';
            } else {
                $handle = fopen($file, 'r');
                $lineNo = 0;
                $parsed = [];
                // Đọc BOM nếu có
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") rewind($handle);

                while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                    $lineNo++;
                    if ($lineNo === 1) continue; // bỏ header

                    // Cột: major_code, major_name, quota, threshold, year
                    if (count($row) < 4) {
                        $importErrors[] = "Dòng $lineNo: Thiếu cột (cần ít nhất 4 cột).";
                        continue;
                    }

                    $majorCode = trim($row[0]);
                    $majorName = trim($row[1]);
                    $quota     = intval($row[2]);
                    $threshold = floatval(str_replace(',', '.', $row[3]));
                    $year      = isset($row[4]) ? intval($row[4]) : ($activeRound['year'] ?? date('Y'));

                    if (!$majorCode) {
                        $importErrors[] = "Dòng $lineNo: Mã ngành trống.";
                        continue;
                    }
                    if ($threshold <= 0 || $threshold > 30) {
                        $importErrors[] = "Dòng $lineNo [$majorCode]: Điểm chuẩn không hợp lệ ($threshold).";
                        continue;
                    }
                    if ($quota < 0) {
                        $importErrors[] = "Dòng $lineNo [$majorCode]: Chỉ tiêu không hợp lệ.";
                        continue;
                    }

                    // Tìm major_id trong DB
                    $mc  = $conn->real_escape_string($majorCode);
                    $res = $conn->query("SELECT id, major_name FROM majors WHERE major_code='$mc' LIMIT 1");
                    if (!$res || $res->num_rows === 0) {
                        $importErrors[] = "Dòng $lineNo: Mã ngành <strong>$majorCode</strong> không tồn tại trong hệ thống.";
                        continue;
                    }
                    $maj = $res->fetch_assoc();

                    $parsed[] = [
                        'major_id'   => $maj['id'],
                        'major_code' => $majorCode,
                        'major_name' => $majorName ?: $maj['major_name'],
                        'quota'      => $quota,
                        'threshold'  => $threshold,
                        'year'       => $year,
                    ];
                }
                fclose($handle);

                if ($parsed) {
                    $_SESSION['import_rows'] = $parsed;
                    $importRows = $parsed;
                    $success = 'Import thành công <strong>' . count($parsed) . '</strong> ngành.' .
                               ($importErrors ? ' Có ' . count($importErrors) . ' dòng lỗi.' : '');
                } else {
                    $error = 'Không có dữ liệu hợp lệ nào được import.';
                }
            }
        }
    }

    // ── 2. XÓA IMPORT ───────────────────────────────────────
    if ($action === 'clear_import') {
        unset($_SESSION['import_rows']);
        $importRows = [];
        $success = 'Đã xóa dữ liệu import.';
    }

    // ── 3. CHẠY XÉT TUYỂN TỰ ĐỘNG (từ import hoặc nhập tay) ─
    if ($action === 'run_auto_review') {
        if (!$isManager) {
            $error = 'Chỉ Trưởng phòng mới có quyền chạy xét tuyển tự động.';
        } elseif ($reviewLocked) {
            $error = 'Chức năng chỉ khả dụng trong giai đoạn xét tuyển.';
        } else {
            $source = $_POST['source'] ?? 'manual'; // 'import' hoặc 'manual'

            $jobs = [];
            if ($source === 'import' && !empty($importRows)) {
                $jobs = $importRows;
            } else {
                // Nhập tay
                $major_id  = intval($_POST['major_id'] ?? 0);
                $threshold = floatval($_POST['threshold'] ?? 0);
                $quota     = intval($_POST['quota'] ?? 0);
                if (!$major_id || $threshold <= 0) {
                    $error = 'Vui lòng chọn ngành và nhập điểm chuẩn hợp lệ.';
                } else {
                    $mRes = $conn->query("SELECT major_code, major_name FROM majors WHERE id=$major_id LIMIT 1");
                    $mRow = $mRes ? $mRes->fetch_assoc() : [];
                    $jobs[] = [
                        'major_id'   => $major_id,
                        'major_code' => $mRow['major_code'] ?? '',
                        'major_name' => $mRow['major_name'] ?? '',
                        'quota'      => $quota,
                        'threshold'  => $threshold,
                        'year'       => $activeRound['year'] ?? date('Y'),
                    ];
                }
            }

            if ($jobs && !$error) {
                foreach ($jobs as $job) {
                    $mid  = intval($job['major_id']);
                    $thr  = floatval($job['threshold']);
                    $qta  = intval($job['quota']);

                    // Lấy hồ sơ chờ xét của ngành này
                    $stmt = $conn->prepare("
                        SELECT id, full_name, email,
                               (math_score + literature_score + english_score) AS total_score
                        FROM admission_applications
                        WHERE major_id = ? AND status IN ('new','checking')
                        ORDER BY total_score DESC
                    ");
                    $stmt->bind_param('i', $mid);
                    $stmt->execute();
                    $candidates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();

                    $approved = $rejected = $rank = 0;
                    $approvedList = $rejectedList = [];

                    foreach ($candidates as $c) {
                        $rank++;
                        $pass = ($c['total_score'] >= $thr) && ($qta === 0 || $rank <= $qta);
                        $newStatus = $pass ? 'approved' : 'rejected';

                        $upd = $conn->prepare("UPDATE admission_applications SET status=? WHERE id=?");
                        $upd->bind_param('si', $newStatus, $c['id']);
                        $upd->execute();
                        $upd->close();

                        if ($pass) {
                            $approved++;
                            $approvedList[] = ['name' => $c['full_name'], 'score' => $c['total_score']];
                        } else {
                            $rejected++;
                            $rejectedList[] = ['name' => $c['full_name'], 'score' => $c['total_score']];
                        }
                    }

                    $reviewResults[] = [
                        'major_code'   => $job['major_code'],
                        'major_name'   => $job['major_name'],
                        'threshold'    => $thr,
                        'quota'        => $qta,
                        'total'        => count($candidates),
                        'approved'     => $approved,
                        'rejected'     => $rejected,
                        'approved_list'=> $approvedList,
                        'rejected_list'=> $rejectedList,
                    ];
                }
                $totalApproved = array_sum(array_column($reviewResults, 'approved'));
                $totalRejected = array_sum(array_column($reviewResults, 'rejected'));
                $success = "Xét tuyển hoàn tất: <strong>$totalApproved</strong> đậu, <strong>$totalRejected</strong> rớt trên " . count($jobs) . " ngành.";
            }
        }
    }

    // ── 4. DUYỆT / TỪ CHỐI THỦ CÔNG ────────────────────────
    if ($action === 'bulk_approve' || $action === 'bulk_reject') {
        if (!hasPermission('admissions', 'approve_application')) {
            $error = 'Bạn không có quyền duyệt hồ sơ.';
        } elseif ($reviewLocked) {
            $error = 'Chức năng chỉ khả dụng trong giai đoạn xét tuyển.';
        } else {
            $ids = $_POST['ids'] ?? [];
            $newSt = $action === 'bulk_approve' ? 'approved' : 'rejected';
            $cnt = 0;
            foreach ($ids as $id) {
                $id = intval($id);
                if ($id) {
                    $upd = $conn->prepare("UPDATE admission_applications SET status=? WHERE id=?");
                    $upd->bind_param('si', $newSt, $id);
                    $upd->execute(); $upd->close(); $cnt++;
                }
            }
            $label = $action === 'bulk_approve' ? 'duyệt' : 'từ chối';
            $success = "Đã $label <strong>$cnt</strong> hồ sơ.";
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

// ── Load dữ liệu hiển thị ────────────────────────────────────
$majors = $conn->query("SELECT id, major_name, major_code FROM majors WHERE status='open' ORDER BY major_name");

$filter_major = intval($_GET['major_id'] ?? 0);
$whereSQL = "WHERE aa.status IN ('new','checking')" . ($filter_major ? " AND aa.major_id=$filter_major" : '');
$pending = $conn->query("
    SELECT aa.*, m.major_name, am.method_name,
           (aa.math_score + aa.literature_score + aa.english_score) AS total_score
    FROM admission_applications aa
    LEFT JOIN majors m ON aa.major_id = m.id
    LEFT JOIN admission_methods am ON aa.method_id = am.id
    $whereSQL ORDER BY total_score DESC
");
$pendingCount = $pending ? $pending->num_rows : 0;
$majorsFilter = $conn->query("SELECT id, major_name FROM majors WHERE status='open' ORDER BY major_name");

// Thống kê nhanh theo ngành
$statsByMajor = [];
$statRes = $conn->query("
    SELECT m.major_name, m.major_code,
           SUM(aa.status IN ('new','checking')) AS pending,
           SUM(aa.status='approved') AS approved,
           SUM(aa.status='rejected') AS rejected,
           COUNT(*) AS total
    FROM admission_applications aa
    LEFT JOIN majors m ON aa.major_id=m.id
    GROUP BY aa.major_id
    ORDER BY m.major_name
");
if ($statRes) while ($r = $statRes->fetch_assoc()) $statsByMajor[] = $r;
?>

<!-- ── Modal trạng thái ──────────────────────────────────── -->
<?php
$arColor = $reviewLocked ? '#6b7280' : '#059669';
$arIcon  = $reviewLocked ? 'bi-lock-fill' : 'bi-robot';
$arTitle = $reviewLocked ? 'Chức năng bị khóa' : 'Đang trong giai đoạn xét tuyển';
$lockMap = [
    'no_round'       => 'Không có đợt tuyển sinh nào đang hoạt động.',
    'before_reg'     => 'Chưa đến thời gian nhận hồ sơ.',
    'reg_open'       => 'Đang nhận hồ sơ — chưa đến giai đoạn xét tuyển.',
    'enrolling'      => 'Đã qua giai đoạn xét tuyển — đang trong thời gian nhập học.',
    'supp_enrolling' => 'Đang trong giai đoạn nhập học bổ sung.',
    'after_enroll'   => 'Đã hết hạn nhập học.',
    'completed'      => 'Đợt tuyển sinh đã hoàn tất.',
];
$arBody = $reviewLocked
    ? ($lockMap[$roundPhase] ?? 'Không trong giai đoạn xét tuyển.') . ' Chức năng xét tuyển chỉ khả dụng khi đợt tuyển sinh ở trạng thái <strong>Đang xét tuyển</strong>.'
    : 'Hệ thống đang trong giai đoạn xét tuyển. Tất cả chức năng đã được mở.';
$arDuration = $reviewLocked ? 8000 : 4000;
?>
<div class="modal fade" id="arStatusModal" tabindex="-1" <?php echo $reviewLocked ? 'data-bs-backdrop="static"' : ''; ?>>
    <div class="modal-dialog modal-dialog-centered" style="max-width:460px;">
        <div class="modal-content border-0 shadow-lg overflow-hidden">
            <div class="modal-header border-0 pb-0" style="background:<?php echo $reviewLocked ? 'rgba(107,114,128,.08)' : 'rgba(16,185,129,.08)'; ?>">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:42px;height:42px;border-radius:50%;background:<?php echo $reviewLocked ? 'rgba(107,114,128,.15)' : 'rgba(16,185,129,.15)'; ?>;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">
                        <i class="bi <?php echo $arIcon; ?>" style="color:<?php echo $arColor; ?>;"></i>
                    </div>
                    <h5 class="modal-title fw-bold mb-0" style="color:<?php echo $arColor; ?>;"><?php echo $arTitle; ?></h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" id="arModalClose"></button>
            </div>
            <div class="modal-body pt-3">
                <p class="mb-0" style="font-size:.9rem;line-height:1.6;"><?php echo $arBody; ?></p>
                <div class="mt-3 d-flex align-items-center gap-2">
                    <div class="progress flex-grow-1" style="height:4px;border-radius:4px;">
                        <div id="arModalProgress" class="progress-bar" style="width:100%;background:<?php echo $arColor; ?>;transition:width linear;"></div>
                    </div>
                    <small class="text-muted" id="arModalCountdown" style="font-size:.72rem;white-space:nowrap;"></small>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('arStatusModal'));
    modal.show();
    const dur = <?php echo $arDuration; ?>;
    const bar = document.getElementById('arModalProgress');
    const cd  = document.getElementById('arModalCountdown');
    let rem = Math.ceil(dur/1000);
    cd.textContent = rem + 's';
    const t = setInterval(function(){ rem--; cd.textContent=rem+'s'; if(rem<=0){clearInterval(t);modal.hide();} }, 1000);
    requestAnimationFrame(()=>requestAnimationFrame(()=>{ bar.style.transitionDuration=dur+'ms'; bar.style.width='0%'; }));
    document.getElementById('arModalClose').addEventListener('click',()=>clearInterval(t));
});
</script>

<!-- ── Alerts ──────────────────────────────────────────────── -->
<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show auto-dismiss">
    <i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show auto-dismiss">
    <i class="bi bi-exclamation-circle-fill me-2"></i><?php echo $error; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ── Kết quả xét tuyển ────────────────────────────────────── -->
<?php if ($reviewResults): ?>
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-header d-flex align-items-center gap-2" style="background:linear-gradient(135deg,#0d2d6b,#1a4fa0);">
        <i class="bi bi-clipboard2-data-fill fs-5"></i>
        <span class="fw-bold">Kết quả xét tuyển tự động</span>
        <span class="badge bg-warning text-dark ms-auto"><?php echo array_sum(array_column($reviewResults,'total')); ?> hồ sơ đã xử lý</span>
    </div>
    <div class="card-body p-0">
        <?php foreach ($reviewResults as $res): ?>
        <div class="border-bottom p-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                <div>
                    <span class="fw-bold text-navy"><?php echo htmlspecialchars($res['major_name']); ?></span>
                    <span class="text-muted small ms-2">(<?php echo htmlspecialchars($res['major_code']); ?>)</span>
                    <span class="badge bg-secondary ms-2">Điểm chuẩn: <?php echo number_format($res['threshold'],2); ?></span>
                    <?php if ($res['quota']): ?>
                    <span class="badge bg-info ms-1">Chỉ tiêu: <?php echo $res['quota']; ?></span>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-success fs-6 px-3"><i class="bi bi-check-circle me-1"></i><?php echo $res['approved']; ?> Đậu</span>
                    <span class="badge bg-danger fs-6 px-3"><i class="bi bi-x-circle me-1"></i><?php echo $res['rejected']; ?> Rớt</span>
                    <span class="badge bg-secondary fs-6 px-3"><i class="bi bi-people me-1"></i><?php echo $res['total']; ?> Tổng</span>
                </div>
            </div>
            <?php if ($res['approved_list']): ?>
            <details class="mt-2">
                <summary class="text-success fw-semibold small" style="cursor:pointer;">
                    <i class="bi bi-chevron-right me-1"></i>Danh sách đậu (<?php echo count($res['approved_list']); ?>)
                </summary>
                <div class="table-responsive mt-2">
                    <table class="table table-sm table-bordered mb-0" style="font-size:.8rem;">
                        <thead><tr><th>#</th><th>Họ tên</th><th class="text-center">Tổng điểm</th></tr></thead>
                        <tbody>
                        <?php foreach ($res['approved_list'] as $i => $a): ?>
                        <tr>
                            <td class="text-muted"><?php echo $i+1; ?></td>
                            <td><?php echo htmlspecialchars($a['name']); ?></td>
                            <td class="text-center fw-bold text-success"><?php echo number_format($a['score'],2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
            <?php endif; ?>
            <?php if ($res['rejected_list']): ?>
            <details class="mt-1">
                <summary class="text-danger fw-semibold small" style="cursor:pointer;">
                    <i class="bi bi-chevron-right me-1"></i>Danh sách rớt (<?php echo count($res['rejected_list']); ?>)
                </summary>
                <div class="table-responsive mt-2">
                    <table class="table table-sm table-bordered mb-0" style="font-size:.8rem;">
                        <thead><tr><th>#</th><th>Họ tên</th><th class="text-center">Tổng điểm</th></tr></thead>
                        <tbody>
                        <?php foreach ($res['rejected_list'] as $i => $a): ?>
                        <tr>
                            <td class="text-muted"><?php echo $i+1; ?></td>
                            <td><?php echo htmlspecialchars($a['name']); ?></td>
                            <td class="text-center fw-bold text-danger"><?php echo number_format($a['score'],2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ── Import errors ────────────────────────────────────────── -->
<?php if ($importErrors): ?>
<div class="alert alert-warning alert-dismissible fade show">
    <strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Lỗi khi import (<?php echo count($importErrors); ?> dòng):</strong>
    <ul class="mb-0 mt-1 small">
        <?php foreach ($importErrors as $e): ?><li><?php echo $e; ?></li><?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ── Thống kê nhanh theo ngành ─────────────────────────────── -->
<?php if ($statsByMajor): ?>
<div class="row g-3 mb-4">
    <?php foreach ($statsByMajor as $s): ?>
    <div class="col-md-4 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-2 px-3">
                <div class="fw-semibold small text-navy mb-1" title="<?php echo htmlspecialchars($s['major_name']); ?>">
                    <?php echo mb_substr(htmlspecialchars($s['major_name']),0,28); ?>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="badge bg-warning text-dark"><?php echo $s['pending']; ?> chờ</span>
                    <span class="badge bg-success"><?php echo $s['approved']; ?> đậu</span>
                    <span class="badge bg-danger"><?php echo $s['rejected']; ?> rớt</span>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── 2 cột chính ──────────────────────────────────────────── -->
<div class="row g-4 mb-4">

    <!-- CỘT TRÁI: Import + Xét tuyển tự động -->
    <div class="col-lg-5">

        <!-- Import CSV -->
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-file-earmark-arrow-up-fill"></i>
                <span>Import danh sách chỉ tiêu & điểm chuẩn</span>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-2">
                    File CSV gồm các cột: <code>major_code, major_name, quota, threshold, year</code><br>
                    <span class="text-muted" style="font-size:.75rem;">VD: <code>7480201,Công nghệ thông tin,120,18.5,2026</code></span>
                </p>
                <a href="?download_template=1" class="btn btn-sm btn-outline-secondary mb-3">
                    <i class="bi bi-download me-1"></i>Tải file mẫu
                </a>
                <?php if ($isManager): ?>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="import_csv">
                    <div class="input-group">
                        <input type="file" name="csv_file" class="form-control form-control-sm" accept=".csv,.txt" required>
                        <button type="submit" class="btn btn-sm btn-navy">
                            <i class="bi bi-upload me-1"></i>Import
                        </button>
                    </div>
                </form>
                <?php else: ?>
                <div class="text-muted small"><i class="bi bi-lock me-1"></i>Chỉ Trưởng phòng mới có quyền import.</div>
                <?php endif; ?>

                <!-- Hiển thị dữ liệu đã import -->
                <?php if ($importRows): ?>
                <div class="mt-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="fw-semibold small text-success"><i class="bi bi-check-circle me-1"></i><?php echo count($importRows); ?> ngành đã import</span>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="clear_import">
                            <button type="submit" class="btn btn-xs btn-outline-danger" style="font-size:.72rem;padding:2px 8px;">
                                <i class="bi bi-trash me-1"></i>Xóa
                            </button>
                        </form>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0" style="font-size:.78rem;">
                            <thead><tr><th>Mã ngành</th><th>Tên ngành</th><th class="text-center">Chỉ tiêu</th><th class="text-center">Điểm chuẩn</th><th class="text-center">Năm</th></tr></thead>
                            <tbody>
                            <?php foreach ($importRows as $ir): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($ir['major_code']); ?></code></td>
                                <td><?php echo htmlspecialchars($ir['major_name']); ?></td>
                                <td class="text-center"><?php echo $ir['quota'] ?: '∞'; ?></td>
                                <td class="text-center fw-bold text-navy"><?php echo number_format($ir['threshold'],2); ?></td>
                                <td class="text-center"><?php echo $ir['year']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Xét tuyển tự động -->
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-robot"></i>
                <span>Chạy xét tuyển tự động</span>
            </div>
            <div class="card-body">
                <?php if ($importRows && $isManager && !$reviewLocked): ?>
                <!-- Nút chạy từ import -->
                <div class="alert alert-info py-2 small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Đã có <strong><?php echo count($importRows); ?></strong> ngành từ file import. Nhấn bên dưới để chạy tất cả.
                </div>
                <form method="POST" onsubmit="return confirm('Xác nhận chạy xét tuyển tự động cho <?php echo count($importRows); ?> ngành?')">
                    <input type="hidden" name="action" value="run_auto_review">
                    <input type="hidden" name="source" value="import">
                    <button type="submit" class="btn btn-navy w-100 mb-3">
                        <i class="bi bi-play-circle-fill me-2"></i>Chạy xét tuyển từ file import (<?php echo count($importRows); ?> ngành)
                    </button>
                </form>
                <hr class="my-2"><p class="text-muted small text-center mb-2">— hoặc nhập tay từng ngành —</p>
                <?php endif; ?>

                <!-- Nhập tay -->
                <form method="POST" <?php echo (!$isManager || $reviewLocked) ? '' : ''; ?>>
                    <input type="hidden" name="action" value="run_auto_review">
                    <input type="hidden" name="source" value="manual">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Ngành xét tuyển <span class="text-danger">*</span></label>
                        <select name="major_id" class="form-select form-select-sm" <?php echo (!$isManager || $reviewLocked) ? 'disabled' : 'required'; ?>>
                            <option value="">-- Chọn ngành --</option>
                            <?php if ($majors): while ($m = $majors->fetch_assoc()): ?>
                            <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['major_name']); ?> (<?php echo $m['major_code']; ?>)</option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small">Điểm chuẩn <span class="text-danger">*</span></label>
                            <input type="number" name="threshold" class="form-control form-control-sm" step="0.25" min="0" max="30" placeholder="VD: 18.5"
                                <?php echo (!$isManager || $reviewLocked) ? 'disabled' : 'required'; ?>>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small">Chỉ tiêu</label>
                            <input type="number" name="quota" class="form-control form-control-sm" min="0" placeholder="0 = không giới hạn"
                                <?php echo (!$isManager || $reviewLocked) ? 'disabled' : ''; ?>>
                        </div>
                    </div>
                    <div class="alert alert-warning py-2 small mb-3">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        Thao tác này cập nhật trạng thái tất cả hồ sơ đang chờ. Không thể hoàn tác.
                    </div>
                    <button type="submit" class="btn btn-navy w-100"
                        <?php echo (!$isManager || $reviewLocked) ? 'disabled' : ''; ?>
                        onclick="return confirm('Xác nhận chạy xét tuyển tự động?')">
                        <i class="bi bi-play-circle-fill me-2"></i>Chạy xét tuyển (nhập tay)
                    </button>
                    <?php if (!$isManager): ?>
                    <div class="text-muted small mt-2"><i class="bi bi-lock me-1"></i>Chỉ Trưởng phòng mới có quyền.</div>
                    <?php elseif ($reviewLocked): ?>
                    <div class="text-muted small mt-2"><i class="bi bi-lock me-1"></i>Chỉ khả dụng khi đợt tuyển sinh ở trạng thái <strong>Đang xét tuyển</strong>.</div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- CỘT PHẢI: Danh sách hồ sơ chờ xét -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-hourglass-split me-2"></i>Hồ sơ chờ xét
                    <span class="badge bg-warning text-dark ms-1"><?php echo $pendingCount; ?></span>
                </span>
                <form method="GET" class="d-flex gap-2">
                    <select name="major_id" class="form-select form-select-sm" style="width:180px" onchange="this.form.submit()">
                        <option value="">Tất cả ngành</option>
                        <?php if ($majorsFilter): while ($m = $majorsFilter->fetch_assoc()): ?>
                        <option value="<?php echo $m['id']; ?>" <?php echo $filter_major==$m['id']?'selected':''; ?>>
                            <?php echo htmlspecialchars($m['major_name']); ?>
                        </option>
                        <?php endwhile; endif; ?>
                    </select>
                </form>
            </div>
            <div class="card-body p-0">
                <form method="POST" id="bulkForm">
                    <div class="p-2 border-bottom d-flex gap-2 flex-wrap align-items-center">
                        <?php if (hasPermission('admissions','approve_application') && !$reviewLocked): ?>
                        <button type="submit" name="action" value="bulk_approve" class="btn btn-sm btn-success" onclick="return confirmBulk('duyệt')">
                            <i class="bi bi-check-all me-1"></i>Duyệt đã chọn
                        </button>
                        <button type="submit" name="action" value="bulk_reject" class="btn btn-sm btn-danger" onclick="return confirmBulk('từ chối')">
                            <i class="bi bi-x-lg me-1"></i>Từ chối đã chọn
                        </button>
                        <?php elseif ($reviewLocked): ?>
                        <span class="text-muted small"><i class="bi bi-lock me-1"></i>Chỉ khả dụng trong giai đoạn xét tuyển</span>
                        <?php else: ?>
                        <span class="text-muted small"><i class="bi bi-lock me-1"></i>Không có quyền duyệt</span>
                        <?php endif; ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" onclick="toggleAll()">
                            <i class="bi bi-check2-square me-1"></i>Chọn tất cả
                        </button>
                    </div>
                    <div class="table-responsive" style="max-height:420px;overflow-y:auto;">
                        <table class="table table-hover table-sm mb-0">
                            <thead style="position:sticky;top:0;z-index:1;">
                                <tr>
                                    <th width="30"><input type="checkbox" id="checkAll"></th>
                                    <th>Họ tên</th><th>Ngành</th>
                                    <th class="text-center">Toán</th>
                                    <th class="text-center">Văn</th>
                                    <th class="text-center">Anh</th>
                                    <th class="text-center text-success">Tổng</th>
                                    <th>Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($pending && $pending->num_rows > 0):
                                while ($app = $pending->fetch_assoc()): ?>
                            <tr>
                                <td><input type="checkbox" name="ids[]" value="<?php echo $app['id']; ?>" class="app-check"></td>
                                <td>
                                    <div class="fw-semibold" style="font-size:.82rem;"><?php echo htmlspecialchars($app['full_name']); ?></div>
                                    <div class="text-muted" style="font-size:.7rem;"><?php echo htmlspecialchars($app['email']); ?></div>
                                </td>
                                <td class="text-muted" style="font-size:.78rem;"><?php echo mb_substr($app['major_name']??'--',0,18); ?></td>
                                <td class="text-center small"><?php echo number_format($app['math_score']??0,1); ?></td>
                                <td class="text-center small"><?php echo number_format($app['literature_score']??0,1); ?></td>
                                <td class="text-center small"><?php echo number_format($app['english_score']??0,1); ?></td>
                                <td class="text-center fw-bold text-success"><?php echo number_format($app['total_score']??0,2); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $app['status']==='new'?'warning':'info'; ?> small">
                                        <?php echo $app['status']==='new'?'Mới':'Đang xét'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="8" class="text-center text-muted py-5">
                                <i class="bi bi-check2-all fs-2 d-block mb-2 text-success"></i>
                                Không có hồ sơ nào đang chờ xét
                            </td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toggleAll() {
    const checks = document.querySelectorAll('.app-check');
    const allChecked = [...checks].every(c => c.checked);
    checks.forEach(c => c.checked = !allChecked);
}
document.getElementById('checkAll')?.addEventListener('change', function() {
    document.querySelectorAll('.app-check').forEach(c => c.checked = this.checked);
});
function confirmBulk(action) {
    const checked = document.querySelectorAll('.app-check:checked').length;
    if (checked === 0) { alert('Vui lòng chọn ít nhất một hồ sơ.'); return false; }
    return confirm(`Xác nhận ${action} ${checked} hồ sơ đã chọn?`);
}
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
