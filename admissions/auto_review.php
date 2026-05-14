<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireAnyRole(['admissions_manager', 'admissions_staff']);
$pageTitle = 'Xét tuyển tự động';

// ── Download file mẫu CSV ────────────────────────────────────
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="mau_chi_tieu_xet_tuyen.csv"');
    echo "\xEF\xBB\xBF"; // BOM UTF-8
    echo "major_code,major_name,quota,year\n";
    echo "7480201,Cong nghe thong tin,120,2026\n";
    echo "7340301,Ke toan,80,2026\n";
    exit();
}

// ── Trạng thái đợt tuyển sinh ────────────────────────────────
$filter_mode   = (($_GET['mode'] ?? $_POST['mode'] ?? 'system') === 'test') ? 'test' : 'system';
$roundPhase    = getRoundPhase($filter_mode);
$activeRound   = getActiveRound($filter_mode);
$reviewAllowed = in_array($roundPhase, ['reviewing', 'supp_reviewing']);
$reviewLocked  = !$reviewAllowed;
$isManager     = hasRole('admissions_manager');
$modeImportKey = 'import_rows_' . $filter_mode;
$modeLabel     = $filter_mode === 'test' ? 'Demo/Test' : 'Dữ liệu thật';
$modeBadgeClass = $filter_mode === 'test' ? 'bg-warning text-dark' : 'bg-success';

// ── Biến kết quả ─────────────────────────────────────────────
$importErrors  = [];   // lỗi khi parse CSV
$importRows    = [];   // dữ liệu đã parse từ CSV (session)
$reviewResults = [];   // kết quả sau khi chạy xét tuyển
$success = $error = '';
$autoPreviewImport = false;

// ── Khôi phục import từ session ──────────────────────────────
if (!empty($_SESSION[$modeImportKey])) {
    $importRows = $_SESSION[$modeImportKey];
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

                    // Cot: major_code, major_name, quota, year. Diem chuan se duoc tinh tu dong theo chi tieu.
                    if (count($row) < 4) {
                        $importErrors[] = "Dòng $lineNo: Thiếu cột (cần ít nhất 4 cột).";
                        continue;
                    }

                    $majorCode = trim($row[0]);
                    $majorName = trim($row[1]);
                    $quota     = intval($row[2]);
                    $year      = isset($row[3]) ? intval($row[3]) : ($activeRound['year'] ?? date('Y'));

                    if (!$majorCode) {
                        $importErrors[] = "Dòng $lineNo: Mã ngành trống.";
                        continue;
                    }
                    if ($quota <= 0) {
                        $importErrors[] = "Dòng $lineNo [$majorCode]: Chỉ tiêu không hợp lệ.";
                        continue;
                    }

                    // Tìm major_id trong DB
                    $mcStmt = $conn->prepare("SELECT id, major_name FROM majors WHERE major_code=? LIMIT 1");
                    $mcStmt->bind_param('s', $majorCode);
                    $mcStmt->execute();
                    $res = $mcStmt->get_result();
                    $mcStmt->close();
                    if (!$res || $res->num_rows === 0) {
                        $importErrors[] = "Dòng $lineNo: Mã ngành <strong>" . htmlspecialchars($majorCode) . "</strong> không tồn tại trong hệ thống.";
                        continue;
                    }
                    $maj = $res->fetch_assoc();

                    $parsed[] = [
                        'major_id'   => $maj['id'],
                        'major_code' => $majorCode,
                        'major_name' => $majorName ?: $maj['major_name'],
                        'quota'      => $quota,
                        'year'       => $year,
                    ];
                }
                fclose($handle);

                if ($parsed) {
                    $_SESSION[$modeImportKey] = $parsed;
                    $importRows = $parsed;
                    $autoPreviewImport = true;
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
        unset($_SESSION[$modeImportKey]);
        $importRows = [];
        $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Đã xóa dữ liệu import.'];
        header('Location: auto_review.php?mode=' . urlencode($filter_mode));
        exit();
    }

    // ── 3. CHẠY XÉT TUYỂN TỰ ĐỘNG — xử lý qua api/actions.php (fetch) ──
    // run_auto_review được gọi từ JS fetch, không xử lý ở đây nữa

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
                    $upd = $conn->prepare("UPDATE admission_applications SET status=? WHERE id=? AND data_mode=?");
                    $upd->bind_param('sis', $newSt, $id, $filter_mode);
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
$whereSQL = "WHERE aa.status IN ('new','checking') AND aa.data_mode='" . $conn->real_escape_string($filter_mode) . "'" . ($filter_major ? " AND aa.major_id=$filter_major" : '');
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
    WHERE aa.data_mode='" . $conn->real_escape_string($filter_mode) . "'
    GROUP BY aa.major_id
    ORDER BY m.major_name
");
if ($statRes) while ($r = $statRes->fetch_assoc()) $statsByMajor[] = $r;

include __DIR__ . '/includes/header.php';
?>
<?php $flash = getFlash(); if ($flash): ?><div class="alert alert-<?php echo $flash['type']; ?> auto-dismiss alert-dismissible fade show"><i class="bi bi-<?php echo $flash['type']==='success'?'check-circle-fill':'exclamation-circle-fill'; ?> me-2"></i><?php echo htmlspecialchars($flash['message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3 py-3">
        <div>
            <div class="text-muted small mb-1">Chế độ dữ liệu đang thao tác</div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge <?php echo $modeBadgeClass; ?> fs-6 px-3 py-2"><?php echo htmlspecialchars($modeLabel); ?></span>
                <span class="text-muted small">Import chỉ tiêu, xét tuyển, duyệt/từ chối và danh sách chờ xét đều theo chế độ này.</span>
            </div>
        </div>
        <div class="btn-group" role="group" aria-label="Chuyển chế độ dữ liệu">
            <a class="btn btn-sm <?php echo $filter_mode === 'system' ? 'btn-navy' : 'btn-outline-navy'; ?>"
               href="auto_review.php?mode=system<?php echo $filter_major ? '&major_id=' . $filter_major : ''; ?>">
                Dữ liệu thật
            </a>
            <a class="btn btn-sm <?php echo $filter_mode === 'test' ? 'btn-warning' : 'btn-outline-warning'; ?>"
               href="auto_review.php?mode=test<?php echo $filter_major ? '&major_id=' . $filter_major : ''; ?>">
                Demo/Test
            </a>
        </div>
    </div>
</div>

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
                <span>Import danh sách chỉ tiêu</span>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-2">
                    File CSV gồm các cột: <code>major_code, major_name, quota, year</code><br>
                    <span class="text-muted" style="font-size:.75rem;">VD: <code>7480201,Cong nghe thong tin,120,2026</code>. Hệ thống tự tính điểm chuẩn theo chỉ tiêu.</span>
                </p>
                <a href="?download_template=1" class="btn btn-sm btn-outline-secondary mb-3">
                    <i class="bi bi-download me-1"></i>Tải file mẫu
                </a>
                <?php if ($isManager): ?>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="import_csv">
                    <input type="hidden" name="mode" value="<?php echo htmlspecialchars($filter_mode); ?>">
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
                        <button type="button" class="btn btn-xs btn-outline-danger" style="font-size:.72rem;padding:2px 8px;"
                            onclick="clearImport(this)">
                            <i class="bi bi-trash me-1"></i>Xóa
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0" style="font-size:.78rem;">
                            <thead><tr><th>Mã ngành</th><th>Tên ngành</th><th class="text-center">Chỉ tiêu</th><th class="text-center">Năm</th></tr></thead>
                            <tbody>
                            <?php foreach ($importRows as $ir): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($ir['major_code']); ?></code></td>
                                <td><?php echo htmlspecialchars($ir['major_name']); ?></td>
                                <td class="text-center"><?php echo $ir['quota'] ?: '∞'; ?></td>
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
                    Đã có <strong><?php echo count($importRows); ?></strong> ngành từ file import. Hệ thống sẽ tạo bản xem trước để kiểm tra trước khi công bố.
                </div>
                <button type="button" class="btn btn-navy w-100 mb-3" id="btnRunImport">
                    <i class="bi bi-eye-fill me-2"></i>Xem trước xét tuyển từ file import (<?php echo count($importRows); ?> ngành)
                </button>
                <hr class="my-2"><p class="text-muted small text-center mb-2">— hoặc nhập tay từng ngành —</p>
                <?php endif; ?>

                <!-- Nhập tay -->
                <div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Ngành xét tuyển <span class="text-danger">*</span></label>
                        <select id="manual_major_id" class="form-select form-select-sm" <?php echo (!$isManager || $reviewLocked) ? 'disabled' : ''; ?>>
                            <option value="">-- Chọn ngành --</option>
                            <?php if ($majors): while ($m = $majors->fetch_assoc()): ?>
                            <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['major_name']); ?> (<?php echo $m['major_code']; ?>)</option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Chỉ tiêu <span class="text-danger">*</span></label>
                        <input type="number" id="manual_quota" class="form-control form-control-sm" min="1" placeholder="VD: 120"
                            <?php echo (!$isManager || $reviewLocked) ? 'disabled' : ''; ?>>
                        <div class="form-text">Hệ thống sẽ lấy điểm của thí sinh cuối cùng trong chỉ tiêu làm điểm chuẩn.</div>
                    </div>
                    <div class="alert alert-warning py-2 small mb-3">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        Bước này chỉ tạo bản xem trước. Trạng thái hồ sơ chỉ thay đổi sau khi bấm <strong>Công bố kết quả</strong>.
                    </div>
                    <button type="button" class="btn btn-navy w-100" id="btnRunManual"
                        <?php echo (!$isManager || $reviewLocked) ? 'disabled' : ''; ?>>
                        <i class="bi bi-eye-fill me-2"></i>Xem trước xét tuyển (nhập tay)
                    </button>
                    <?php if (!$isManager): ?>
                    <div class="text-muted small mt-2"><i class="bi bi-lock me-1"></i>Chỉ Trưởng phòng mới có quyền.</div>
                    <?php elseif ($reviewLocked): ?>
                    <div class="text-muted small mt-2"><i class="bi bi-lock me-1"></i>Chỉ khả dụng khi đợt tuyển sinh ở trạng thái <strong>Đang xét tuyển</strong>.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- CỘT PHẢI: Danh sách hồ sơ chờ xét -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-hourglass-split me-2"></i>Hồ sơ chờ xét
                    <span class="badge bg-warning text-dark ms-1"><?php echo $pendingCount; ?></span>
                    <span class="badge <?php echo $modeBadgeClass; ?> ms-1"><?php echo htmlspecialchars($modeLabel); ?></span>
                </span>
                <form method="GET" class="d-flex gap-2">
                    <input type="hidden" name="mode" value="<?php echo htmlspecialchars($filter_mode); ?>">
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
                <div id="bulkWrapper">
                    <div class="p-2 border-bottom d-flex gap-2 flex-wrap align-items-center">
                        <?php if (hasPermission('admissions','approve_application') && !$reviewLocked): ?>
                        <button type="button" class="btn btn-sm btn-success" onclick="bulkAction('bulk_approve')">
                            <i class="bi bi-check-all me-1"></i>Duyệt đã chọn
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" onclick="bulkAction('bulk_reject')">
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
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

function admFetch(data) {
    const fd = new FormData();
    fd.append('_csrf_token', CSRF);
    fd.append('module', 'auto_review');
    Object.entries(data).forEach(([k, v]) => fd.append(k, v));
    return fetch('/university/admissions/api/actions.php', {
        method: 'POST', body: fd, credentials: 'same-origin'
    }).then(r => r.json());
}

function showToast(type, msg) {
    const el = document.createElement('div');
    el.className = `alert alert-${type==='success'?'success':'danger'} alert-dismissible fade show position-fixed shadow`;
    el.style.cssText = 'top:1rem;right:1rem;z-index:9999;min-width:320px;max-width:480px;';
    el.innerHTML = `<i class="bi bi-${type==='success'?'check-circle-fill':'exclamation-circle-fill'} me-2"></i>${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 6000);
}

function setLoading(btn, loading) {
    if (loading) {
        btn.disabled = true;
        btn._orig = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang xử lý...';
    } else {
        btn.disabled = false;
        btn.innerHTML = btn._orig || btn.innerHTML;
    }
}

let currentPreviewToken = '';

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
}

function renderCandidateRows(list, emptyText) {
    if (!list || !list.length) {
        return `<tr><td colspan="8" class="text-center text-muted py-3">${emptyText}</td></tr>`;
    }
    return list.map(c => `
        <tr>
            <td class="text-center fw-bold">${c.rank}</td>
            <td>
                <div class="fw-semibold">${escapeHtml(c.full_name)}</div>
                <div class="text-muted small">${escapeHtml(c.email)}</div>
            </td>
            <td class="text-center">${Number(c.math_score).toFixed(1)}</td>
            <td class="text-center">${Number(c.literature_score).toFixed(1)}</td>
            <td class="text-center">${Number(c.english_score).toFixed(1)}</td>
            <td class="text-center fw-bold text-success">${Number(c.total_score).toFixed(2)}</td>
            <td class="text-center"><span class="badge ${c.result === 'approved' ? 'bg-success' : 'bg-danger'}">${c.result === 'approved' ? '\u0110\u1eadu' : 'R\u1edbt'}</span></td>
            <td class="text-center"><code>#${c.id}</code></td>
        </tr>
    `).join('');
}

function showResults(results, previewToken = '') {
    currentPreviewToken = previewToken;
    document.getElementById('autoReviewPreviewCard')?.remove();

    let totalPublish = results.reduce((sum, r) => sum + Number(r.total || 0), 0);
    let html = `<div class="card mb-4 border-0 shadow-sm" id="autoReviewPreviewCard">
        <div class="card-header d-flex align-items-center justify-content-between gap-2" style="background:linear-gradient(135deg,#0d2d6b,#1a4fa0);">
            <div><i class="bi bi-clipboard2-data-fill fs-5 me-2"></i><span class="fw-bold">B&#7843;n xem tr&#432;&#7899;c k&#7871;t qu&#7843; x&#233;t tuy&#7875;n</span></div>
            <span class="badge bg-warning text-dark">Ch&#432;a c&#244;ng b&#7889;</span>
        </div>
        <div class="card-body">
            <div class="alert alert-warning d-flex align-items-start gap-2 py-2">
                <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                <div class="small">&#272;&#226;y l&#224; k&#7871;t qu&#7843; xem tr&#432;&#7899;c. H&#227;y ki&#7875;m tra ch&#7881; ti&#234;u, &#273;i&#7875;m chu&#7849;n v&#224; danh s&#225;ch th&#237; sinh. H&#7891; s&#417; ch&#432;a &#273;&#7893;i tr&#7841;ng th&#225;i cho t&#7899;i khi b&#7845;m <strong>C&#244;ng b&#7889; k&#7871;t qu&#7843;</strong>.</div>
            </div>`;

    results.forEach((r, idx) => {
        const approvedId = `previewApproved${idx}`;
        const rejectedId = `previewRejected${idx}`;
        html += `<div class="border rounded mb-3 overflow-hidden">
            <div class="p-3 bg-light d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <span class="fw-bold text-navy">${escapeHtml(r.major_name)}</span>
                    <span class="badge bg-secondary ms-2">&#272;i&#7875;m chu&#7849;n: ${Number(r.threshold || 0).toFixed(2)}</span>
                    <span class="badge bg-info ms-1">Ch&#7881; ti&#234;u: ${r.quota || 0}</span>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-success fs-6 px-3"><i class="bi bi-check-circle me-1"></i>${r.approved} &#272;&#7853;u</span>
                    <span class="badge bg-danger fs-6 px-3"><i class="bi bi-x-circle me-1"></i>${r.rejected} R&#7899;t</span>
                    <span class="badge bg-secondary fs-6 px-3"><i class="bi bi-people me-1"></i>${r.total} T&#7893;ng</span>
                </div>
            </div>
            <div class="p-3">
                <div class="d-flex gap-2 mb-2">
                    <button class="btn btn-sm btn-outline-success" type="button" data-bs-toggle="collapse" data-bs-target="#${approvedId}">
                        <i class="bi bi-chevron-down me-1"></i>Danh s&#225;ch &#273;&#7853;u (${r.approved})
                    </button>
                    <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="collapse" data-bs-target="#${rejectedId}">
                        <i class="bi bi-chevron-down me-1"></i>Danh s&#225;ch r&#7899;t (${r.rejected})
                    </button>
                </div>
                <div class="collapse show" id="${approvedId}">
                    <div class="table-responsive mb-3" style="max-height:280px;overflow:auto;">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead><tr><th class="text-center">H&#7841;ng</th><th>Th&#237; sinh</th><th class="text-center">To&#225;n</th><th class="text-center">V&#259;n</th><th class="text-center">Anh</th><th class="text-center">T&#7893;ng</th><th class="text-center">KQ</th><th class="text-center">ID</th></tr></thead>
                            <tbody>${renderCandidateRows(r.approved_list, 'Kh&#244;ng c&#243; th&#237; sinh &#273;&#7853;u')}</tbody>
                        </table>
                    </div>
                </div>
                <div class="collapse" id="${rejectedId}">
                    <div class="table-responsive" style="max-height:280px;overflow:auto;">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead><tr><th class="text-center">H&#7841;ng</th><th>Th&#237; sinh</th><th class="text-center">To&#225;n</th><th class="text-center">V&#259;n</th><th class="text-center">Anh</th><th class="text-center">T&#7893;ng</th><th class="text-center">KQ</th><th class="text-center">ID</th></tr></thead>
                            <tbody>${renderCandidateRows(r.rejected_list, 'Kh&#244;ng c&#243; th&#237; sinh r&#7899;t')}</tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>`;
    });

    html += `<div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('autoReviewPreviewCard')?.remove(); currentPreviewToken='';">
                    <i class="bi bi-x-lg me-1"></i>H&#7911;y b&#7843;n xem tr&#432;&#7899;c
                </button>
                <button type="button" class="btn btn-success" id="btnPublishPreview" ${totalPublish ? '' : 'disabled'}>
                    <i class="bi bi-megaphone-fill me-1"></i>C&#244;ng b&#7889; k&#7871;t qu&#7843; (${totalPublish} h&#7891; s&#417;)
                </button>
            </div>
        </div>
    </div>`;

    const content = document.querySelector('.adm-content');
    if (content) content.insertAdjacentHTML('afterbegin', html);
    document.getElementById('autoReviewPreviewCard')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    document.getElementById('btnPublishPreview')?.addEventListener('click', publishPreview);
}

function publishPreview() {
    if (!currentPreviewToken) { showToast('error', 'Kh\u00f4ng t\u00ecm th\u1ea5y b\u1ea3n xem tr\u01b0\u1edbc. Vui l\u00f2ng ch\u1ea1y l\u1ea1i x\u00e9t tuy\u1ec3n.'); return; }
    if (!confirm('C\u00f4ng b\u1ed1 k\u1ebft qu\u1ea3 n\u00e0y? Sau khi c\u00f4ng b\u1ed1, tr\u1ea1ng th\u00e1i h\u1ed3 s\u01a1 s\u1ebd \u0111\u01b0\u1ee3c c\u1eadp nh\u1eadt \u0110\u1eadu/R\u1edbt.')) return;
    const btn = document.getElementById('btnPublishPreview');
    setLoading(btn, true);
    admFetch({ action: 'publish_auto_review', preview_token: currentPreviewToken, data_mode: <?php echo json_encode($filter_mode); ?> })
        .then(res => {
            setLoading(btn, false);
            if (res.success) {
                showToast('success', res.message);
                setTimeout(() => window.location.href = 'auto_review.php?mode=<?php echo urlencode($filter_mode); ?>', 900);
            } else {
                showToast('error', res.message);
            }
        })
        .catch(() => { setLoading(btn, false); showToast('error', 'L\u1ed7i k\u1ebft n\u1ed1i.'); });
}

const btnRunImport = document.getElementById('btnRunImport');
if (btnRunImport) {
    btnRunImport.addEventListener('click', function() {
        setLoading(this, true);
        const btn = this;
        admFetch({ action: 'run_auto_review', source: 'import', data_mode: <?php echo json_encode($filter_mode); ?> })
            .then(res => {
                setLoading(btn, false);
                if (res.success) {
                    showToast('success', res.message);
                    if (res.data?.results) showResults(res.data.results, res.data.preview_token || '');
                } else { showToast('error', res.message); }
            })
            .catch(() => { setLoading(btn, false); showToast('error', 'Lỗi kết nối.'); });
    });
    <?php if ($autoPreviewImport && $isManager && !$reviewLocked): ?>
    window.addEventListener('load', () => setTimeout(() => btnRunImport.click(), 250));
    <?php endif; ?>
}

// Chạy nhập tay
const btnRunManual = document.getElementById('btnRunManual');
if (btnRunManual) {
    btnRunManual.addEventListener('click', function() {
        const major_id  = document.getElementById('manual_major_id')?.value;
        const quota     = document.getElementById('manual_quota')?.value || '0';
        if (!major_id || Number(quota) <= 0) { showToast('error', 'Vui lòng chọn ngành và nhập chỉ tiêu.'); return; }
        setLoading(this, true);
        const btn = this;
        admFetch({ action: 'run_auto_review', source: 'manual', major_id, quota, data_mode: <?php echo json_encode($filter_mode); ?> })
            .then(res => {
                setLoading(btn, false);
                if (res.success) {
                    showToast('success', res.message);
                    if (res.data?.results) showResults(res.data.results, res.data.preview_token || '');
                } else { showToast('error', res.message); }
            })
            .catch(() => { setLoading(btn, false); showToast('error', 'Lỗi kết nối.'); });
    });
}

// Xóa import
function clearImport(btn) {
    if (!confirm('Xóa dữ liệu import?')) return;
    btn.disabled = true;
    const fd = new FormData();
    fd.append('action', 'clear_import');
    fd.append('mode', <?php echo json_encode($filter_mode); ?>);
    fetch('auto_review.php?mode=<?php echo urlencode($filter_mode); ?>', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(() => location.reload())
        .catch(() => { btn.disabled = false; showToast('error', 'Lỗi kết nối.'); });
}

// Bulk approve/reject
function bulkAction(action) {
    const checked = [...document.querySelectorAll('.app-check:checked')].map(c => c.value);
    if (!checked.length) { showToast('error', 'Vui lòng chọn ít nhất một hồ sơ.'); return; }
    const label = action === 'bulk_approve' ? 'duyệt' : 'từ chối';
    if (!confirm(`Xác nhận ${label} ${checked.length} hồ sơ đã chọn?`)) return;

    const fd = new FormData();
    fd.append('_csrf_token', CSRF);
    fd.append('module', 'applications');
    fd.append('action', action === 'bulk_approve' ? 'bulk_approve' : 'bulk_reject');
    fd.append('data_mode', <?php echo json_encode($filter_mode); ?>);
    checked.forEach(id => fd.append('ids[]', id));

    fetch('/university/admissions/api/actions.php', {
        method: 'POST', body: fd, credentials: 'same-origin'
    }).then(r => r.json()).then(res => {
        if (res.success) {
            showToast('success', res.message);
            // Xóa các dòng đã xử lý khỏi bảng
            checked.forEach(id => {
                const cb = document.querySelector(`.app-check[value="${id}"]`);
                if (cb) cb.closest('tr')?.remove();
            });
        } else { showToast('error', res.message); }
    }).catch(() => showToast('error', 'Lỗi kết nối.'));
}

function toggleAll() {
    const checks = document.querySelectorAll('.app-check');
    const allChecked = [...checks].every(c => c.checked);
    checks.forEach(c => c.checked = !allChecked);
}
document.getElementById('checkAll')?.addEventListener('change', function() {
    document.querySelectorAll('.app-check').forEach(c => c.checked = this.checked);
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
