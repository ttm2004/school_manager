<?php
require_once __DIR__ . '/../config.php';
adm_require_auth();
$pageTitle = 'Quản lý điểm chuẩn';

$year = intval($_GET['year'] ?? date('Y'));

// Available years
$yrs = [];
$yr = $conn->query("SELECT DISTINCT YEAR(created_at) as y FROM adm_registrations ORDER BY y DESC");
while ($r = $yr->fetch_assoc()) $yrs[] = $r['y'];
if (!in_array($year, $yrs)) $yrs[] = $year;
sort($yrs); $yrs = array_reverse($yrs);

// Stats by major
$majorStats = $conn->query("
    SELECT m.id, m.major_name, m.major_code,
        COUNT(r.id) as total,
        COUNT(s.id) as has_score,
        AVG(s.total_score) as avg_score,
        MAX(s.total_score) as max_score,
        MIN(s.total_score) as min_score,
        aq.quota as quota
    FROM majors m
    LEFT JOIN adm_registrations r ON r.major_id = m.id AND YEAR(r.created_at) = $year
    LEFT JOIN adm_scores s ON s.registration_id = r.id
    LEFT JOIN adm_quota aq ON aq.major_id = m.id AND aq.year = $year
    WHERE m.status = 'open'
    GROUP BY m.id ORDER BY m.major_name
");

// Existing cutoff scores
$cutoffs = $conn->query("
    SELECT cs.*, m.major_name, m.major_code, am.method_name, sc.code as combo_code
    FROM adm_cutoff_scores cs
    LEFT JOIN majors m ON cs.major_id = m.id
    LEFT JOIN adm_methods am ON cs.method_code = am.code
    LEFT JOIN adm_subject_combinations sc ON cs.combination_id = sc.id
    WHERE cs.year = $year ORDER BY m.major_name, cs.method_code
");

$methods = $conn->query("SELECT code, method_name FROM adm_methods WHERE status='open' ORDER BY priority");
$combos  = $conn->query("SELECT id, code, name FROM adm_subject_combinations ORDER BY code");
$majors  = $conn->query("SELECT id, major_name, major_code FROM majors WHERE status='open' ORDER BY major_name");

include __DIR__ . '/../includes/header.php';
?>

<?php if (isset($_SESSION['adm_success'])): ?>
<div class="alert alert-success auto-dismiss"><?php echo htmlspecialchars($_SESSION['adm_success']); unset($_SESSION['adm_success']); ?></div>
<?php endif; ?>

<!-- Year filter -->
<div class="card mb-3">
    <div class="card-body py-3 d-flex align-items-center gap-3 flex-wrap">
        <label class="fw-semibold mb-0">Năm xét tuyển:</label>
        <div class="d-flex gap-2 flex-wrap">
            <?php foreach ($yrs as $y): ?>
            <a href="?year=<?php echo $y; ?>" class="btn btn-sm <?php echo $y==$year?'btn-navy':'btn-outline-secondary'; ?>"><?php echo $y; ?></a>
            <?php endforeach; ?>
        </div>
        <button class="btn btn-sm btn-gold ms-auto" data-bs-toggle="modal" data-bs-target="#addCutoffModal">
            <i class="fas fa-plus me-1"></i>Thêm điểm chuẩn
        </button>
    </div>
</div>

<!-- Major stats grid -->
<div class="row g-3 mb-4">
<?php if ($majorStats && $majorStats->num_rows > 0): while ($m = $majorStats->fetch_assoc()): ?>
<div class="col-md-6 col-xl-4">
    <div class="card h-100">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <span class="badge bg-navy text-white small"><?php echo htmlspecialchars($m['major_code']); ?></span>
                    <div class="fw-bold mt-1"><?php echo htmlspecialchars($m['major_name']); ?></div>
                </div>
                <button class="btn btn-sm btn-outline-primary" onclick="openSuggest(<?php echo $m['id']; ?>,'<?php echo addslashes($m['major_name']); ?>',$year)">
                    <i class="fas fa-magic"></i>
                </button>
            </div>
            <div class="row g-2 text-center mt-1">
                <?php $items = [
                    ['Chỉ tiêu', $m['quota'] ?? '—'],
                    ['Hồ sơ', $m['total']],
                    ['Có điểm', $m['has_score']],
                    ['TB', number_format($m['avg_score']??0,2)],
                    ['Cao nhất', number_format($m['max_score']??0,2)],
                    ['Thấp nhất', number_format($m['min_score']??0,2)],
                ];
                foreach ($items as [$lbl,$val]): ?>
                <div class="col-4">
                    <div class="small text-muted"><?php echo $lbl; ?></div>
                    <div class="fw-bold"><?php echo $val; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endwhile; else: ?>
<div class="col-12"><div class="alert alert-info">Chưa có dữ liệu ngành nào.</div></div>
<?php endif; ?>
</div>

<!-- Cutoff scores table -->
<div class="card">
    <div class="card-header"><i class="fas fa-bullseye me-2"></i>Điểm chuẩn năm <?php echo $year; ?></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr>
                    <th>Ngành</th><th>Phương thức</th><th>Tổ hợp</th>
                    <th>Điểm chuẩn</th><th>Chỉ tiêu</th><th>Trúng tuyển</th><th></th>
                </tr></thead>
                <tbody>
                <?php if ($cutoffs && $cutoffs->num_rows > 0): while ($c = $cutoffs->fetch_assoc()):
                    $admitted = $conn->query("
                        SELECT COUNT(*) as cnt FROM adm_registrations r
                        JOIN adm_scores s ON r.id=s.registration_id
                        WHERE r.major_id={$c['major_id']} AND r.method_code='{$c['method_code']}'
                        AND s.total_score >= {$c['score']} AND YEAR(r.created_at)=$year
                    ")->fetch_assoc()['cnt'] ?? 0;
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($c['major_code']); ?></strong><br>
                        <small class="text-muted"><?php echo htmlspecialchars($c['major_name']); ?></small></td>
                    <td><?php echo htmlspecialchars($c['method_name']); ?></td>
                    <td><?php echo htmlspecialchars($c['combo_code'] ?? 'Tất cả'); ?></td>
                    <td><span class="badge bg-primary fs-6"><?php echo number_format($c['score'],2); ?></span></td>
                    <td><?php echo $c['quota'] ?? '—'; ?></td>
                    <td><span class="badge bg-success"><?php echo $admitted; ?></span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteCutoff(<?php echo $c['id']; ?>)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Chưa có điểm chuẩn nào cho năm <?php echo $year; ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Apply benchmark button -->
<div class="mt-3 d-flex gap-2">
    <button class="btn btn-gold" data-bs-toggle="modal" data-bs-target="#applyModal">
        <i class="fas fa-play me-2"></i>Chạy xét tuyển tự động
    </button>
</div>

<!-- Add Cutoff Modal -->
<div class="modal fade" id="addCutoffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-plus me-2"></i>Thêm điểm chuẩn</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" action="../api/save_cutoff.php">
                <input type="hidden" name="year" value="<?php echo $year; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Ngành <span class="text-danger">*</span></label>
                        <select name="major_id" class="form-select" required>
                            <option value="">-- Chọn ngành --</option>
                            <?php $majors->data_seek(0); while ($m = $majors->fetch_assoc()): ?>
                            <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['major_code'].' - '.$m['major_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Phương thức <span class="text-danger">*</span></label>
                        <select name="method_code" class="form-select" required>
                            <option value="">-- Chọn phương thức --</option>
                            <?php $methods->data_seek(0); while ($m = $methods->fetch_assoc()): ?>
                            <option value="<?php echo $m['code']; ?>"><?php echo htmlspecialchars($m['method_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tổ hợp môn</label>
                        <select name="combination_id" class="form-select">
                            <option value="">-- Tất cả tổ hợp --</option>
                            <?php $combos->data_seek(0); while ($c = $combos->fetch_assoc()): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['code'].' - '.$c['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Điểm chuẩn <span class="text-danger">*</span></label>
                            <input type="number" name="score" class="form-control" step="0.01" min="0" max="30" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Chỉ tiêu</label>
                            <input type="number" name="quota" class="form-control" min="0" placeholder="Để trống = dùng chỉ tiêu ngành">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-gold"><i class="fas fa-save me-1"></i>Lưu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Apply Modal -->
<div class="modal fade" id="applyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-play me-2"></i>Xét tuyển tự động</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>
                    Hệ thống sẽ tự động xét duyệt tất cả hồ sơ dựa trên điểm chuẩn đã thiết lập cho năm <strong><?php echo $year; ?></strong>.
                    Hành động này sẽ cập nhật trạng thái tất cả hồ sơ có điểm.
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Chọn ngành</label>
                    <select id="applyMajorId" class="form-select">
                        <option value="0">-- Tất cả ngành --</option>
                        <?php $majors->data_seek(0); while ($m = $majors->fetch_assoc()): ?>
                        <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['major_code'].' - '.$m['major_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div id="applyResult" class="d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-gold" onclick="runAutoReview()"><i class="fas fa-play me-1"></i>Chạy xét tuyển</button>
            </div>
        </div>
    </div>
</div>

<script>
function deleteCutoff(id) {
    if (!confirm('Xóa điểm chuẩn này?')) return;
    fetch('../api/delete_cutoff.php', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({id})
    }).then(r=>r.json()).then(d => { if (d.success) location.reload(); else alert(d.message); });
}

function openSuggest(majorId, majorName, year) {
    fetch(`../api/suggest_cutoff.php?major_id=${majorId}&year=${year}`)
        .then(r=>r.json()).then(d => {
            alert(`Gợi ý điểm chuẩn cho ${majorName}:\n` +
                `Tổng hồ sơ: ${d.total}\nChỉ tiêu: ${d.quota}\nĐiểm gợi ý: ${d.suggested ?? 'Chưa đủ dữ liệu'}`);
        });
}

function runAutoReview() {
    const majorId = document.getElementById('applyMajorId').value;
    const year = <?php echo $year; ?>;
    fetch('../api/apply_benchmark.php', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({major_id: parseInt(majorId), year})
    }).then(r=>r.json()).then(d => {
        const el = document.getElementById('applyResult');
        el.classList.remove('d-none');
        if (d.success) {
            el.className = 'alert alert-success';
            el.innerHTML = `✓ ${d.message}<br>Trúng tuyển: <strong>${d.data?.stats?.passed??0}</strong> | Không trúng: <strong>${d.data?.stats?.failed??0}</strong>`;
            setTimeout(() => location.reload(), 2000);
        } else {
            el.className = 'alert alert-danger';
            el.textContent = d.message;
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
