<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireAnyRole(['admissions_manager','admissions_staff']);

$pageTitle = 'Lớp đã phân';
$filterMode = (($_GET['mode'] ?? 'system') === 'test') ? 'test' : 'system';
$filterFaculty = (int)($_GET['faculty_id'] ?? 0);
$filterMajor = (int)($_GET['major_id'] ?? 0);
$filterYear = (int)($_GET['year'] ?? 0);
$search = trim($_GET['q'] ?? '');

$where = ['s.data_mode = ?'];
$types = 's';
$params = [$filterMode];
if ($filterFaculty > 0) { $where[] = 'f.id = ?'; $types .= 'i'; $params[] = $filterFaculty; }
if ($filterMajor > 0) { $where[] = 'm.id = ?'; $types .= 'i'; $params[] = $filterMajor; }
if ($filterYear > 0) { $where[] = 'COALESCE(s.enrollment_year, c.enrollment_year) = ?'; $types .= 'i'; $params[] = $filterYear; }
if ($search !== '') {
    $where[] = '(c.class_code LIKE ? OR c.class_name LIKE ? OR m.major_name LIKE ? OR u.full_name LIKE ? OR s.student_code LIKE ?)';
    $like = "%$search%";
    $types .= 'sssss';
    array_push($params, $like, $like, $like, $like, $like);
}
$whereSQL = implode(' AND ', $where);

$stmt = $conn->prepare(
    "SELECT c.id, c.class_code, c.class_name, c.school_year, COALESCE(s.enrollment_year, c.enrollment_year) AS enrollment_year,
            m.major_name, f.faculty_name, COUNT(DISTINCT s.id) AS student_count,
            COUNT(DISTINCT ss.id) AS registered_count, MAX(u.created_at) AS last_created_at
     FROM students s
     JOIN users u ON u.id = s.user_id
     JOIN classes c ON c.id = s.class_id
     LEFT JOIN majors m ON m.id = c.major_id
     LEFT JOIN faculties f ON f.id = m.faculty_id
     LEFT JOIN student_subjects ss ON ss.student_id = s.id
     WHERE $whereSQL
     GROUP BY c.id, enrollment_year
     ORDER BY enrollment_year DESC, f.faculty_name, m.major_name, c.class_code"
);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$classes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$faculties = $conn->query("SELECT id, faculty_name FROM faculties ORDER BY faculty_name")->fetch_all(MYSQLI_ASSOC);
$majors = $conn->query("SELECT m.id, m.major_name, m.faculty_id, f.faculty_name FROM majors m LEFT JOIN faculties f ON f.id=m.faculty_id ORDER BY f.faculty_name, m.major_name")->fetch_all(MYSQLI_ASSOC);
$yearsStmt = $conn->prepare("SELECT DISTINCT COALESCE(s.enrollment_year, c.enrollment_year) AS y FROM students s JOIN classes c ON c.id=s.class_id WHERE s.data_mode=? ORDER BY y DESC");
$yearsStmt->bind_param('s', $filterMode);
$yearsStmt->execute();
$years = $yearsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$yearsStmt->close();

include __DIR__ . '/includes/header.php';
?>
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label small">Chế độ</label>
                <select name="mode" class="form-select form-select-sm">
                    <option value="system" <?php echo $filterMode==='system'?'selected':''; ?>>Dữ liệu thật</option>
                    <option value="test" <?php echo $filterMode==='test'?'selected':''; ?>>Test / Demo</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small">Khoa/Viện</label>
                <select name="faculty_id" id="filterFaculty" class="form-select form-select-sm">
                    <option value="0">Tất cả</option>
                    <?php foreach ($faculties as $f): ?><option value="<?php echo (int)$f['id']; ?>" <?php echo $filterFaculty===(int)$f['id']?'selected':''; ?>><?php echo htmlspecialchars($f['faculty_name']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small">Ngành</label>
                <select name="major_id" id="filterMajor" class="form-select form-select-sm">
                    <option value="0">Tất cả</option>
                    <?php foreach ($majors as $m): ?><option value="<?php echo (int)$m['id']; ?>" data-faculty="<?php echo (int)$m['faculty_id']; ?>" <?php echo $filterMajor===(int)$m['id']?'selected':''; ?>><?php echo htmlspecialchars($m['major_name']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small">Năm tuyển sinh</label>
                <select name="year" class="form-select form-select-sm">
                    <option value="0">Tất cả</option>
                    <?php foreach ($years as $y): $yy=(int)$y['y']; ?><option value="<?php echo $yy; ?>" <?php echo $filterYear===$yy?'selected':''; ?>><?php echo $yy; ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small">Tìm kiếm</label>
                <input name="q" class="form-control form-control-sm" value="<?php echo htmlspecialchars($search); ?>" placeholder="Mã lớp, tên lớp, sinh viên...">
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-navy"><i class="bi bi-search"></i></button>
                <a href="assigned_classes.php?mode=<?php echo urlencode($filterMode); ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-diagram-3-fill me-2"></i>Các lớp đã phân <span class="badge bg-light text-dark ms-1"><?php echo count($classes); ?></span></span>
        <span class="badge <?php echo $filterMode==='test'?'bg-warning text-dark':'bg-success'; ?>"><?php echo $filterMode==='test'?'Test / Demo':'Dữ liệu thật'; ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Mã lớp</th><th>Tên lớp</th><th>Khoa/Viện</th><th>Ngành</th><th>Năm tuyển sinh</th><th>Đã phân</th><th>Đăng ký môn</th><th>Cập nhật</th></tr></thead>
            <tbody>
            <?php if (empty($classes)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">Chưa có lớp nào đã phân trong chế độ này.</td></tr>
            <?php else: foreach ($classes as $c): ?>
            <tr>
                <td><code><?php echo htmlspecialchars($c['class_code']); ?></code></td>
                <td><div class="fw-semibold"><?php echo htmlspecialchars($c['class_name']); ?></div><div class="small text-muted"><?php echo htmlspecialchars($c['school_year'] ?? ''); ?></div></td>
                <td class="small"><?php echo htmlspecialchars($c['faculty_name'] ?? ''); ?></td>
                <td class="small"><?php echo htmlspecialchars($c['major_name'] ?? ''); ?></td>
                <td><span class="badge bg-light text-dark"><?php echo (int)$c['enrollment_year']; ?></span></td>
                <td><span class="badge bg-primary"><?php echo (int)$c['student_count']; ?> SV</span></td>
                <td><span class="badge <?php echo (int)$c['registered_count']>0?'bg-success':'bg-secondary'; ?>"><?php echo (int)$c['registered_count']; ?></span></td>
                <td class="small text-muted"><?php echo $c['last_created_at'] ? date('d/m/Y H:i', strtotime($c['last_created_at'])) : ''; ?></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
document.getElementById('filterFaculty')?.addEventListener('change', function() {
    const faculty = this.value;
    const major = document.getElementById('filterMajor');
    Array.from(major.options).forEach(opt => {
        opt.hidden = opt.value !== '0' && faculty !== '0' && opt.dataset.faculty !== faculty;
    });
    if (major.selectedOptions[0]?.hidden) major.value = '0';
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
