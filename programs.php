<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
$pageTitle = 'Chương trình đào tạo';

// Tự động thêm cột nếu chưa có
$colChecks = [
    "semester_order TINYINT NOT NULL DEFAULT 1",
    "theory_periods INT DEFAULT 30",
    "practice_periods INT DEFAULT 0",
    "is_mandatory TINYINT(1) DEFAULT 1"
];
foreach ($colChecks as $colDef) {
    $colName = explode(' ', $colDef)[0];
    $chk = $conn->query("SHOW COLUMNS FROM subjects LIKE '$colName'");
    if ($chk && $chk->num_rows == 0) {
        $conn->query("ALTER TABLE subjects ADD COLUMN $colDef");
    }
}

$filter_major = intval($_GET['major_id'] ?? 0);

// Lấy tất cả ngành
$majors = $conn->query("
    SELECT m.*, f.faculty_name,
           COUNT(s.id) as subject_count,
           SUM(s.credits) as total_credits_actual
    FROM majors m
    LEFT JOIN faculties f ON m.faculty_id = f.id
    LEFT JOIN subjects s ON s.major_id = m.id
    WHERE m.status = 'open'
    GROUP BY m.id
    ORDER BY f.faculty_name, m.major_name
");
$majorsArr = [];
while ($m = $majors->fetch_assoc()) $majorsArr[] = $m;

// Nhóm theo khoa
$byFaculty = [];
foreach ($majorsArr as $m) {
    $byFaculty[$m['faculty_name']][] = $m;
}

// Tự chọn ngành đầu tiên có môn học
if (!$filter_major) {
    foreach ($majorsArr as $m) {
        if ($m['subject_count'] > 0) { $filter_major = $m['id']; break; }
    }
}

// Lấy CTĐT của ngành
$subjects = [];
$currentMajor = null;
if ($filter_major) {
    foreach ($majorsArr as $m) {
        if ($m['id'] == $filter_major) { $currentMajor = $m; break; }
    }
    $stmt = $conn->prepare("SELECT * FROM subjects WHERE major_id = ? ORDER BY semester_order ASC, is_mandatory DESC, subject_name ASC");
    $stmt->bind_param('i', $filter_major);
    $stmt->execute();
    $subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Nhóm theo học kỳ
$bySemester = [];
foreach ($subjects as $s) {
    $sem = $s['semester_order'] ?? 1;
    $bySemester[$sem][] = $s;
}
ksort($bySemester);

// Thống kê — xử lý cả 2 schema cũ/mới
$totalCredits = array_sum(array_column($subjects, 'credits'));
$requiredCount = $electiveCount = $generalCount = 0;
foreach ($subjects as $s) {
    $t = $s['subject_type_new'] ?? $s['subject_type'] ?? '';
    if (in_array($t, ['required','Bắt buộc'])) $requiredCount++;
    elseif (in_array($t, ['elective','Tự chọn'])) $electiveCount++;
    elseif ($t === 'general') $generalCount++;
    else $requiredCount++;
}

// Helper: lấy badge loại môn
function getTypeBadge($s) {
    $t = $s['subject_type_new'] ?? $s['subject_type'] ?? '';
    if (in_array($t, ['required','Bắt buộc'])) return ['Bắt buộc','danger'];
    if (in_array($t, ['elective','Tự chọn']))  return ['Tự chọn','warning'];
    if ($t === 'general')                       return ['Đại cương','info'];
    return ['Bắt buộc','danger'];
}

include 'includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="/university/index.php">Trang chủ</a></li>
                <li class="breadcrumb-item active">Chương trình đào tạo</li>
            </ol>
        </nav>
        <h1><i class="bi bi-journal-bookmark-fill me-2"></i>Chương trình đào tạo</h1>
        <p class="text-white-50 mb-0">Khung chương trình đào tạo đại học chính quy - Trường ĐH Thủ Dầu Một</p>
    </div>
</div>

<section class="py-5">
    <div class="container">
        <div class="row g-4">

            <!-- Sidebar ngành -->
            <div class="col-lg-3">
                <div class="card shadow-custom sticky-top" style="top:80px;max-height:85vh;overflow-y:auto;">
                    <div class="card-header py-2">
                        <i class="bi bi-list-ul me-2"></i><strong>Danh sách ngành</strong>
                    </div>
                    <div class="p-0">
                        <?php foreach ($byFaculty as $facultyName => $fMajors): ?>
                        <div class="px-3 pt-3 pb-1">
                            <div class="text-muted fw-bold text-uppercase" style="font-size:0.7rem;letter-spacing:0.5px;">
                                <i class="bi bi-building me-1"></i><?php echo htmlspecialchars($facultyName); ?>
                            </div>
                        </div>
                        <?php foreach ($fMajors as $m): $active = $filter_major == $m['id']; ?>
                        <a href="?major_id=<?php echo $m['id']; ?>"
                           class="d-flex align-items-start gap-2 px-3 py-2 text-decoration-none border-bottom <?php echo $active ? 'bg-navy text-white' : 'text-dark'; ?>"
                           style="transition:background .15s;">
                            <div class="mt-1 flex-shrink-0">
                                <span class="badge <?php echo $active ? 'bg-gold text-dark' : 'bg-navy'; ?>" style="font-size:0.62rem;">
                                    <?php echo htmlspecialchars($m['major_code']); ?>
                                </span>
                            </div>
                            <div>
                                <div class="fw-bold" style="font-size:0.83rem;"><?php echo htmlspecialchars($m['major_name']); ?></div>
                                <div class="<?php echo $active ? 'text-white-50' : 'text-muted'; ?>" style="font-size:0.73rem;">
                                    <?php echo $m['total_credits']; ?> TC &nbsp;·&nbsp; <?php echo $m['subject_count']; ?> môn
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Nội dung CTĐT -->
            <div class="col-lg-9">

                <?php if (!$currentMajor || empty($subjects)): ?>
                <div class="card">
                    <div class="card-body text-center text-muted py-5">
                        <i class="bi bi-journal-bookmark fs-2 d-block mb-3 text-navy"></i>
                        <?php if ($currentMajor): ?>
                        <h5>Chương trình đào tạo chưa được cập nhật</h5>
                        <p class="mb-0">Ngành <strong><?php echo htmlspecialchars($currentMajor['major_name']); ?></strong> chưa có dữ liệu môn học.</p>
                        <?php else: ?>
                        <h5>Chọn ngành để xem chương trình đào tạo</h5>
                        <?php endif; ?>
                    </div>
                </div>

                <?php else: ?>

                <!-- Header ngành -->
                <div class="card mb-4 border-0" style="background:linear-gradient(135deg,var(--navy) 0%,#1a5276 100%);color:#fff;">
                    <div class="card-body py-4">
                        <div class="row align-items-center">
                            <div class="col-md-7">
                                <div class="d-flex align-items-center gap-3">
                                    <div style="width:56px;height:56px;background:rgba(255,255,255,0.15);border-radius:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                        <i class="bi bi-mortarboard-fill fs-3"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold fs-5"><?php echo htmlspecialchars($currentMajor['major_name']); ?></div>
                                        <div style="opacity:.8;font-size:.85rem;">
                                            Mã ngành: <strong><?php echo htmlspecialchars($currentMajor['major_code']); ?></strong>
                                            &nbsp;|&nbsp; Khoa: <?php echo htmlspecialchars($currentMajor['faculty_name']); ?>
                                        </div>
                                        <?php if (!empty($currentMajor['description'])): ?>
                                        <div style="opacity:.7;font-size:.78rem;margin-top:4px;"><?php echo htmlspecialchars(mb_substr($currentMajor['description'],0,100)); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-5 mt-3 mt-md-0">
                                <div class="row g-2 text-center">
                                    <div class="col-3">
                                        <div class="fw-bold fs-4"><?php echo $currentMajor['total_credits']; ?></div>
                                        <div style="opacity:.7;font-size:.7rem;">TC yêu cầu</div>
                                    </div>
                                    <div class="col-3">
                                        <div class="fw-bold fs-4"><?php echo $totalCredits; ?></div>
                                        <div style="opacity:.7;font-size:.7rem;">TC trong CTĐT</div>
                                    </div>
                                    <div class="col-3">
                                        <div class="fw-bold fs-4"><?php echo count($subjects); ?></div>
                                        <div style="opacity:.7;font-size:.7rem;">Môn học</div>
                                    </div>
                                    <div class="col-3">
                                        <div class="fw-bold fs-4"><?php echo count($bySemester); ?></div>
                                        <div style="opacity:.7;font-size:.7rem;">Học kỳ</div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="d-flex justify-content-between mb-1" style="font-size:.73rem;opacity:.85;">
                                        <span>Tín chỉ CTĐT</span>
                                        <span><?php echo $totalCredits; ?> / <?php echo $currentMajor['total_credits']; ?> TC</span>
                                    </div>
                                    <div class="progress" style="height:7px;background:rgba(255,255,255,.2);">
                                        <?php $pct = $currentMajor['total_credits'] > 0 ? min(100, round($totalCredits/$currentMajor['total_credits']*100)) : 0; ?>
                                        <div class="progress-bar bg-warning" style="width:<?php echo $pct; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chú thích + nút đăng ký -->
                <div class="d-flex gap-2 mb-3 flex-wrap align-items-center">
                    <span class="badge bg-danger px-3 py-2"><i class="bi bi-lock-fill me-1"></i>Bắt buộc (<?php echo $requiredCount; ?>)</span>
                    <span class="badge bg-warning text-dark px-3 py-2"><i class="bi bi-unlock-fill me-1"></i>Tự chọn (<?php echo $electiveCount; ?>)</span>
                    <?php if ($generalCount): ?>
                    <span class="badge bg-info text-dark px-3 py-2"><i class="bi bi-book-fill me-1"></i>Đại cương (<?php echo $generalCount; ?>)</span>
                    <?php endif; ?>
                    <a href="/university/admission.php#apply" class="btn btn-gold btn-sm ms-auto">
                        <i class="bi bi-pencil-square me-1"></i>Đăng ký xét tuyển
                    </a>
                </div>

                <!-- CTĐT theo học kỳ -->
                <?php
                $semLabels = [
                    1=>'Học kỳ 1', 2=>'Học kỳ 2', 3=>'Học kỳ 3', 4=>'Học kỳ 4',
                    5=>'Học kỳ 5', 6=>'Học kỳ 6', 7=>'Học kỳ 7', 8=>'Học kỳ 8',
                    9=>'Học kỳ 9', 10=>'Học kỳ 10 (Thực tập)', 11=>'Học kỳ 11 (Tốt nghiệp)'
                ];
                foreach ($bySemester as $semOrder => $semSubjects):
                    $semCredits = array_sum(array_column($semSubjects, 'credits'));
                    $semLabel   = $semLabels[$semOrder] ?? "Học kỳ $semOrder";
                    $semId      = "sem$semOrder";
                ?>
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center"
                         style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#<?php echo $semId; ?>">
                        <span>
                            <i class="bi bi-calendar3 me-2 text-gold"></i>
                            <strong><?php echo $semLabel; ?></strong>
                            <span class="text-muted ms-2" style="font-size:.83rem;">(<?php echo count($semSubjects); ?> môn)</span>
                        </span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-navy"><?php echo $semCredits; ?> TC</span>
                            <i class="bi bi-chevron-down text-muted" style="font-size:.8rem;"></i>
                        </div>
                    </div>
                    <div class="collapse show" id="<?php echo $semId; ?>">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle" style="font-size:.88rem;">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:36px">#</th>
                                            <th style="width:100px">Mã môn</th>
                                            <th>Tên môn học</th>
                                            <th class="text-center" style="width:50px">TC</th>
                                            <th class="text-center" style="width:55px">LT</th>
                                            <th class="text-center" style="width:55px">TH</th>
                                            <th style="width:95px">Loại</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $idx=1; foreach ($semSubjects as $sub):
                                            [$typeLabel, $typeColor] = getTypeBadge($sub);
                                            $lt = $sub['theory_periods'] ?? 0;
                                            $th = $sub['practice_periods'] ?? 0;
                                        ?>
                                        <tr>
                                            <td class="text-muted"><?php echo $idx++; ?></td>
                                            <td><span class="badge bg-navy" style="font-size:.72rem;"><?php echo htmlspecialchars($sub['subject_code']); ?></span></td>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($sub['subject_name']); ?></div>
                                                <?php if (!empty($sub['description'])): ?>
                                                <div class="text-muted" style="font-size:.78rem;"><?php echo htmlspecialchars(mb_substr($sub['description'],0,70)); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-gold text-dark fw-bold"><?php echo $sub['credits']; ?></span>
                                            </td>
                                            <td class="text-center text-muted"><?php echo $lt ?: '--'; ?></td>
                                            <td class="text-center text-muted"><?php echo $th ?: '--'; ?></td>
                                            <td><span class="badge bg-<?php echo $typeColor; ?>"><?php echo $typeLabel; ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <td colspan="3" class="text-end fw-bold text-muted" style="font-size:.82rem;">Tổng <?php echo $semLabel; ?>:</td>
                                            <td class="text-center"><span class="badge bg-navy fw-bold"><?php echo $semCredits; ?></span></td>
                                            <td colspan="3"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Tổng kết -->
                <div class="card border-0 bg-light mt-2">
                    <div class="card-body py-3">
                        <div class="row text-center g-3">
                            <div class="col-6 col-md-3">
                                <div class="fw-bold fs-3 text-navy"><?php echo $totalCredits; ?></div>
                                <div class="text-muted small">Tổng TC trong CTĐT</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="fw-bold fs-3 text-danger"><?php echo $requiredCount; ?></div>
                                <div class="text-muted small">Môn bắt buộc</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="fw-bold fs-3 text-warning"><?php echo $electiveCount; ?></div>
                                <div class="text-muted small">Môn tự chọn</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="fw-bold fs-3 text-info"><?php echo count($bySemester); ?></div>
                                <div class="text-muted small">Số học kỳ</div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
