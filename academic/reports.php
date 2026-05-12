<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/academic_helpers.php';
requireAnyRole(['academic_manager','academic_staff']);

$pageTitle = 'Bao cao Thong ke';
$filterSem = (int)($_GET['semester_id'] ?? 0);
if ($filterSem === 0) {
    $activeSem = getActiveSemesterAcademic($conn);
    if ($activeSem) $filterSem = (int)$activeSem['id'];
}

// Tong quan toan truong
$totalStudents  = (int)$conn->query("SELECT COUNT(*) AS c FROM students WHERE academic_status='Dang hoc'")->fetch_assoc()['c'];
$totalTeachers  = (int)$conn->query("SELECT COUNT(*) AS c FROM teachers")->fetch_assoc()['c'];
$totalFaculties = (int)$conn->query("SELECT COUNT(*) AS c FROM faculties")->fetch_assoc()['c'];
$totalMajors    = (int)$conn->query("SELECT COUNT(*) AS c FROM majors")->fetch_assoc()['c'];

// Thong ke theo hoc ky
$semStats = [];
if ($filterSem > 0) {
    // Lop HP theo trang thai
    $r = $conn->query("SELECT status, COUNT(*) AS c FROM course_sections WHERE semester_id=$filterSem GROUP BY status");
    $sectionsByStatus = [];
    while ($row = $r->fetch_assoc()) $sectionsByStatus[$row['status']] = $row['c'];

    // Tong luot dang ky
    $totalReg = (int)$conn->query("SELECT COUNT(*) AS c FROM student_subjects ss JOIN course_sections cs ON ss.course_section_id=cs.id WHERE cs.semester_id=$filterSem AND ss.status='registered'")->fetch_assoc()['c'];

    // Ti le dat trung binh
    $passRate = $conn->query(
        "SELECT AVG(pass_rate) AS avg_rate FROM (
             SELECT cs.id,
                    CASE WHEN COUNT(g.id)>0 THEN SUM(CASE WHEN g.final_score>=5 THEN 1 ELSE 0 END)/COUNT(g.id)*100 ELSE NULL END AS pass_rate
             FROM course_sections cs
             JOIN student_subjects ss ON ss.course_section_id=cs.id AND ss.status='registered'
             LEFT JOIN grades g ON g.student_subject_id=ss.id
             WHERE cs.semester_id=$filterSem
             GROUP BY cs.id
         ) x WHERE pass_rate IS NOT NULL"
    )->fetch_assoc()['avg_rate'];

    // SV theo khoa
    $svByFaculty = $conn->query(
        "SELECT f.faculty_name, COUNT(DISTINCT ss.student_id) AS c
         FROM student_subjects ss
         JOIN course_sections cs ON ss.course_section_id=cs.id
         JOIN subjects s ON cs.subject_id=s.id
         JOIN majors m ON s.major_id=m.id
         JOIN faculties f ON m.faculty_id=f.id
         WHERE cs.semester_id=$filterSem AND ss.status='registered'
         GROUP BY f.id, f.faculty_name ORDER BY c DESC"
    )->fetch_all(MYSQLI_ASSOC);

    // GV theo hoc vi
    $tvByDegree = $conn->query(
        "SELECT COALESCE(t.degree,'Chua cap nhat') AS degree, COUNT(*) AS c
         FROM teachers t GROUP BY t.degree ORDER BY c DESC"
    )->fetch_all(MYSQLI_ASSOC);

    // Top 10 mon hoc nhieu SV nhat
    $topSubjects = $conn->query(
        "SELECT s.subject_name, s.credits, COUNT(ss.id) AS reg_count,
                AVG(g.total_score) AS avg_score
         FROM course_sections cs
         JOIN subjects s ON cs.subject_id=s.id
         JOIN student_subjects ss ON ss.course_section_id=cs.id AND ss.status='registered'
         LEFT JOIN grades g ON g.student_subject_id=ss.id
         WHERE cs.semester_id=$filterSem
         GROUP BY s.id, s.subject_name, s.credits
         ORDER BY reg_count DESC LIMIT 10"
    )->fetch_all(MYSQLI_ASSOC);

    $semStats = compact('sectionsByStatus','totalReg','passRate','svByFaculty','tvByDegree','topSubjects');
}

$semesters = $conn->query("SELECT id, semester_name, school_year FROM semesters ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
<div class="admin-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
        <span class="admin-topbar-title"><i class="bi bi-clipboard-data-fill me-2 text-navy"></i>Bao cao Thong ke</span>
    </div>
    <span class="text-muted small"><?php echo htmlspecialchars($_SESSION['full_name']??''); ?></span>
</div>
<div class="admin-content">

<!-- Chon hoc ky -->
<div class="card mb-4">
    <div class="card-body py-2">
        <form method="get" class="d-flex gap-2 align-items-end">
            <div>
                <label class="form-label small">Hoc ky</label>
                <select name="semester_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($semesters as $sm): ?>
                    <option value="<?php echo $sm['id']; ?>" <?php echo $filterSem==$sm['id']?'selected':''; ?>>
                        <?php echo htmlspecialchars($sm['semester_name'].' '.$sm['school_year']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<!-- Tong quan toan truong -->
<h5 class="fw-bold mb-3"><i class="bi bi-building me-2 text-navy"></i>Tong quan Toan truong</h5>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card-admin stat-bg-1">
            <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
            <div class="stat-value mt-2"><?php echo number_format($totalStudents); ?></div>
            <div class="stat-label">Sinh vien dang hoc</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card-admin stat-bg-2">
            <div class="stat-icon"><i class="bi bi-person-badge-fill"></i></div>
            <div class="stat-value mt-2"><?php echo number_format($totalTeachers); ?></div>
            <div class="stat-label">Giang vien</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card-admin stat-bg-3">
            <div class="stat-icon"><i class="bi bi-building"></i></div>
            <div class="stat-value mt-2"><?php echo $totalFaculties; ?></div>
            <div class="stat-label">Khoa/Vien</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card-admin stat-bg-4">
            <div class="stat-icon"><i class="bi bi-diagram-3-fill"></i></div>
            <div class="stat-value mt-2"><?php echo $totalMajors; ?></div>
            <div class="stat-label">Nganh dao tao</div>
        </div>
    </div>
</div>

<?php if (!empty($semStats)): ?>
<!-- Thong ke hoc ky -->
<h5 class="fw-bold mb-3"><i class="bi bi-calendar3 me-2 text-navy"></i>Thong ke Hoc ky</h5>
<div class="row g-3 mb-4">
    <?php
    $statusLabels = ['open'=>['success','Dang mo'],'proposed'=>['warning','De xuat'],'closed'=>['dark','Da dong'],'cancelled'=>['danger','Huy'],'draft'=>['secondary','Nhap'],'full'=>['info','Day']];
    foreach ($semStats['sectionsByStatus'] as $st => $cnt):
        [$c,$l] = $statusLabels[$st] ?? ['secondary',$st];
    ?>
    <div class="col-6 col-lg-2">
        <div class="card text-center p-3">
            <div class="fw-bold fs-3 text-<?php echo $c; ?>"><?php echo $cnt; ?></div>
            <div class="small text-muted">Lop <?php echo $l; ?></div>
        </div>
    </div>
    <?php endforeach; ?>
    <div class="col-6 col-lg-2">
        <div class="card text-center p-3">
            <div class="fw-bold fs-3 text-primary"><?php echo number_format($semStats['totalReg']); ?></div>
            <div class="small text-muted">Luot dang ky</div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card text-center p-3">
            <div class="fw-bold fs-3 text-<?php echo ($semStats['passRate']??0)>=70?'success':'danger'; ?>">
                <?php echo $semStats['passRate'] !== null ? round($semStats['passRate'],1).'%' : '—'; ?>
            </div>
            <div class="small text-muted">Ti le dat TB</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- SV theo khoa -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-building me-2"></i>Sinh vien dang ky theo Khoa</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Khoa</th><th class="text-center">So SV</th></tr></thead>
                    <tbody>
                    <?php foreach ($semStats['svByFaculty'] as $row): ?>
                    <tr>
                        <td class="small"><?php echo htmlspecialchars($row['faculty_name']); ?></td>
                        <td class="text-center"><span class="badge bg-navy"><?php echo $row['c']; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- GV theo hoc vi -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-mortarboard me-2"></i>Giang vien theo Hoc vi</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Hoc vi</th><th class="text-center">So GV</th></tr></thead>
                    <tbody>
                    <?php foreach ($semStats['tvByDegree'] as $row): ?>
                    <tr>
                        <td class="small"><?php echo htmlspecialchars($row['degree']); ?></td>
                        <td class="text-center"><span class="badge bg-light text-dark"><?php echo $row['c']; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Top mon hoc -->
<div class="card">
    <div class="card-header"><i class="bi bi-trophy me-2"></i>Top 10 Mon hoc nhieu SV dang ky nhat</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>#</th><th>Mon hoc</th><th class="text-center">TC</th><th class="text-center">So SV</th><th class="text-center">Diem TB</th></tr></thead>
            <tbody>
            <?php foreach ($semStats['topSubjects'] as $i => $row): ?>
            <tr>
                <td class="text-muted small"><?php echo $i+1; ?></td>
                <td class="small"><?php echo htmlspecialchars($row['subject_name']); ?></td>
                <td class="text-center"><span class="badge bg-light text-dark"><?php echo $row['credits']; ?></span></td>
                <td class="text-center"><strong><?php echo $row['reg_count']; ?></strong></td>
                <td class="text-center">
                    <?php if ($row['avg_score'] !== null): ?>
                    <span class="badge bg-<?php echo $row['avg_score']>=5?'success':'danger'; ?>">
                        <?php echo number_format($row['avg_score'],2); ?>
                    </span>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

</div>
<div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU</div>
</div>
<?php include 'includes/footer.php'; ?>
