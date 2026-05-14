<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/grade_windows.php';
requireRole('teacher');

$stmt = $conn->prepare("SELECT t.*, u.full_name FROM teachers t JOIN users u ON t.user_id=u.id WHERE t.user_id=?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

$success = $error = '';

function teacherGradeDemoContext(mysqli $conn, int $studentSubjectId): array {
    $stmt = $conn->prepare("SELECT data_mode, demo_batch_id FROM student_subjects WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $studentSubjectId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    return [
        'data_mode' => (($row['data_mode'] ?? 'system') === 'test') ? 'test' : 'system',
        'demo_batch_id' => (string)($row['demo_batch_id'] ?? ''),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_grade') {
        $ss_id   = intval($_POST['student_subject_id'] ?? 0);
        $process = $_POST['process_score'] !== '' ? floatval($_POST['process_score']) : null;
        $midterm = $_POST['midterm_score'] !== '' ? floatval($_POST['midterm_score']) : null;
        $final   = $_POST['final_score'] !== '' ? floatval($_POST['final_score']) : null;
        $note    = trim($_POST['note'] ?? '');

        $window = getGradeInputWindowForStudentSubject($conn, $ss_id);
        if (!$window || (int)$window['teacher_id'] !== (int)$teacher['id']) {
            $error = 'Ban khong co quyen nhap diem lop hoc phan nay.';
        } elseif (!$window['is_grade_window_open']) {
            $error = $window['grade_window_message'];
        } else {
            $lockChk = $conn->query("SHOW TABLES LIKE 'grade_locks'");
            if ($lockChk && $lockChk->num_rows > 0) {
                $sectionId = (int)$window['id'];
                $locked = $conn->query("SELECT id FROM grade_locks WHERE course_section_id=$sectionId LIMIT 1");
                if ($locked && $locked->num_rows > 0) {
                    $error = 'Diem lop hoc phan nay da bi khoa. Vui long lien he Phong Dao tao neu can mo khoa.';
                }
            }
        }

        if (empty($error)) {
            $total = null; $letter = null;
            if ($process !== null && $midterm !== null && $final !== null) {
                $total = round($process * 0.2 + $midterm * 0.3 + $final * 0.5, 2);
                if ($total >= 8.5) $letter = 'A';
                elseif ($total >= 8.0) $letter = 'B+';
                elseif ($total >= 7.0) $letter = 'B';
                elseif ($total >= 6.0) $letter = 'C+';
                elseif ($total >= 5.0) $letter = 'C';
                elseif ($total >= 4.0) $letter = 'D+';
                elseif ($total >= 3.5) $letter = 'D';
                else $letter = 'F';
            }

            $chk = $conn->prepare("SELECT id FROM grades WHERE student_subject_id=?");
            $chk->bind_param('i', $ss_id);
            $chk->execute();
            $exists = $chk->get_result()->fetch_assoc();
            $chk->close();
            $demoContext = teacherGradeDemoContext($conn, $ss_id);

            if ($exists) {
                $stmt = $conn->prepare("UPDATE grades SET process_score=?, midterm_score=?, final_score=?, total_score=?, letter_grade=?, note=?, data_mode=?, demo_batch_id=? WHERE student_subject_id=?");
                $stmt->bind_param('ddddssssi', $process, $midterm, $final, $total, $letter, $note, $demoContext['data_mode'], $demoContext['demo_batch_id'], $ss_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO grades (student_subject_id, process_score, midterm_score, final_score, total_score, letter_grade, note, data_mode, demo_batch_id) VALUES (?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param('iddddssss', $ss_id, $process, $midterm, $final, $total, $letter, $note, $demoContext['data_mode'], $demoContext['demo_batch_id']);
            }
            $stmt->execute() ? $success = 'Luu diem thanh cong!' : $error = 'Loi: ' . $conn->error;
            $stmt->close();
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

$filter_section = intval($_GET['section_id'] ?? 0);

// Chi hien cac lop da ket thuc va dang trong han nhap diem do Phong Dao tao mo.
$mySections = getTeacherOpenGradeSections($conn, (int)$teacher['id']);
$openSectionIds = array_map('intval', array_column($mySections, 'id'));

$grades = null;
$selectedWindow = null;
if ($filter_section > 0) {
    // Kiem tra section thuoc giang vien nay
    $selectedWindow = getGradeInputWindowForSection($conn, $filter_section, (int)$teacher['id']);

    if ($selectedWindow && $selectedWindow['is_grade_window_open']) {
        $stmt = $conn->prepare("
            SELECT ss.id as ss_id, u.full_name, st.student_code,
                   g.id as grade_id, g.process_score, g.midterm_score, g.final_score, g.total_score, g.letter_grade, g.note
            FROM student_subjects ss
            JOIN students st ON ss.student_id = st.id
            JOIN users u ON st.user_id = u.id
            LEFT JOIN grades g ON g.student_subject_id = ss.id
            WHERE ss.course_section_id = ? AND ss.status != 'cancelled'
            ORDER BY u.full_name
        ");
        $stmt->bind_param('i', $filter_section);
        $stmt->execute();
        $grades = $stmt->get_result();
        $stmt->close();
    } else {
        $error = $selectedWindow ? $selectedWindow['grade_window_message'] : 'Ban khong co quyen truy cap lop hoc phan nay.';
        $filter_section = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
    <title>Nhap diem - Giang vien</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/university/assets/css/style.css">
</head>
<body>
<div class="student-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="student-main">
        <div class="student-topbar">
            <span class="fw-bold text-navy">Nhap diem sinh vien</span>
            <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i></a>
        </div>
        <div class="student-content">
            <?php $flash = getFlash(); if ($flash): ?><div class="alert alert-<?php echo $flash['type']; ?> auto-dismiss alert-dismissible fade show"><i class="bi bi-<?php echo $flash['type']=='success'?'check-circle-fill':'exclamation-circle-fill'; ?> me-2"></i><?php echo htmlspecialchars($flash['message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
            <?php if (!empty($error)): ?>
            <div class="alert alert-warning alert-dismissible fade show">
                <i class="bi bi-info-circle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            <?php if (!empty($mySections)): ?>
            <div class="alert alert-success">
                <i class="bi bi-unlock-fill me-2"></i>
                Phong Dao tao dang mo nhap diem cho <?php echo count($mySections); ?> lop hoc phan da ket thuc.
                Vui long hoan tat truoc han nop diem cua tung hoc ky.
            </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-funnel me-2"></i>Chon lop hoc phan</div>
                <div class="card-body">
                    <?php if (empty($mySections)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-eye-slash fs-3 d-block mb-2"></i>
                        Chua co lop hoc phan nao trong thoi gian nhap diem. Bang nhap diem chi hien sau khi mon hoc ket thuc va Phong Dao tao mo han nop diem.
                    </div>
                    <?php else: ?>
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-8">
                            <label class="form-label">Lop hoc phan</label>
                            <select name="section_id" class="form-select">
                                <option value="">-- Chon lop hoc phan --</option>
                                <?php foreach ($mySections as $sec): ?>
                                <option value="<?php echo $sec['id']; ?>" <?php echo $filter_section==$sec['id']?'selected':''; ?>>
                                    <?php echo htmlspecialchars($sec['section_code'] . ' - ' . $sec['subject_name'] . ' (' . $sec['semester_name'] . ' ' . $sec['school_year'] . ') - han ' . date('d/m/Y', strtotime($sec['grade_submit_deadline']))); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-navy w-100"><i class="bi bi-search me-1"></i>Xem danh sach</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($filter_section > 0 && $grades): ?>
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-bar-chart-fill me-2"></i>Nhap diem sinh vien
                    <small class="text-muted ms-2">(QT: 20% | GK: 30% | CK: 50%)</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th><th>Ma SV</th><th>Ho ten</th>
                                    <th>Diem QT</th><th>Diem GK</th><th>Diem CK</th>
                                    <th>Tong ket</th><th>Xep loai</th><th>Ghi chu</th><th>Luu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($grades->num_rows > 0): $idx=1; while ($g = $grades->fetch_assoc()):
                                $lc = ['A'=>'success','B+'=>'primary','B'=>'info','C+'=>'warning','C'=>'warning','D+'=>'secondary','D'=>'secondary','F'=>'danger'];
                                $lcolor = $lc[$g['letter_grade'] ?? ''] ?? 'secondary';
                                ?>
                                <tr>
                                    <td><?php echo $idx++; ?></td>
                                    <td class="fw-bold text-navy"><?php echo htmlspecialchars($g['student_code']); ?></td>
                                    <td><?php echo htmlspecialchars($g['full_name']); ?></td>
                                    <form method="POST">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="save_grade">
                                        <input type="hidden" name="student_subject_id" value="<?php echo $g['ss_id']; ?>">
                                        <input type="hidden" name="section_id" value="<?php echo $filter_section; ?>">
                                        <td><input type="number" name="process_score" class="form-control form-control-sm" min="0" max="10" step="0.1" value="<?php echo $g['process_score'] ?? ''; ?>" style="width:75px"></td>
                                        <td><input type="number" name="midterm_score" class="form-control form-control-sm" min="0" max="10" step="0.1" value="<?php echo $g['midterm_score'] ?? ''; ?>" style="width:75px"></td>
                                        <td><input type="number" name="final_score" class="form-control form-control-sm" min="0" max="10" step="0.1" value="<?php echo $g['final_score'] ?? ''; ?>" style="width:75px"></td>
                                        <td class="fw-bold <?php echo $g['total_score']!==null?($g['total_score']>=5?'text-success':'text-danger'):''; ?>">
                                            <?php echo $g['total_score'] !== null ? number_format($g['total_score'],2) : '--'; ?>
                                        </td>
                                        <td>
                                            <?php if ($g['letter_grade']): ?>
                                            <span class="badge bg-<?php echo $lcolor; ?>"><?php echo $g['letter_grade']; ?></span>
                                            <?php else: ?>--<?php endif; ?>
                                        </td>
                                        <td><input type="text" name="note" class="form-control form-control-sm" value="<?php echo htmlspecialchars($g['note'] ?? ''); ?>" style="width:110px"></td>
                                        <td><button type="submit" class="btn btn-sm btn-gold"><i class="bi bi-save"></i></button></td>
                                    </form>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr><td colspan="10" class="text-center text-muted py-4">Chua co sinh vien dang ky lop hoc phan nay</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <div class="student-footer">&copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một</div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<?php include_once __DIR__ . "/../includes/analytics_widget.php"; ?>
</body>
</html>

