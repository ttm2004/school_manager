<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('teacher');

$stmt = $conn->prepare("SELECT t.*, u.full_name FROM teachers t JOIN users u ON t.user_id=u.id WHERE t.user_id=?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_grade') {
        $ss_id   = intval($_POST['student_subject_id'] ?? 0);
        $process = $_POST['process_score'] !== '' ? floatval($_POST['process_score']) : null;
        $midterm = $_POST['midterm_score'] !== '' ? floatval($_POST['midterm_score']) : null;
        $final   = $_POST['final_score'] !== '' ? floatval($_POST['final_score']) : null;
        $note    = trim($_POST['note'] ?? '');

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

        if ($exists) {
            $stmt = $conn->prepare("UPDATE grades SET process_score=?, midterm_score=?, final_score=?, total_score=?, letter_grade=?, note=? WHERE student_subject_id=?");
            $stmt->bind_param('ddddssi', $process, $midterm, $final, $total, $letter, $note, $ss_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO grades (student_subject_id, process_score, midterm_score, final_score, total_score, letter_grade, note) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param('iddddss', $ss_id, $process, $midterm, $final, $total, $letter, $note);
        }
        $stmt->execute() ? $success = 'Luu diem thanh cong!' : $error = 'Loi: ' . $conn->error;
        $stmt->close();
    }
}

$filter_section = intval($_GET['section_id'] ?? 0);

// Lay danh sach lop hoc phan cua giang vien
$mySections = $conn->prepare("
    SELECT cs.id, cs.section_code, s.subject_name, sm.semester_name, sm.school_year
    FROM course_sections cs
    JOIN subjects s ON cs.subject_id = s.id
    JOIN semesters sm ON cs.semester_id = sm.id
    WHERE cs.teacher_id = ?
    ORDER BY sm.school_year DESC, sm.semester_name, cs.section_code
");
$mySections->bind_param('i', $teacher['id']);
$mySections->execute();
$mySections = $mySections->get_result();

$grades = null;
if ($filter_section > 0) {
    // Kiem tra section thuoc giang vien nay
    $chkSec = $conn->prepare("SELECT id FROM course_sections WHERE id=? AND teacher_id=?");
    $chkSec->bind_param('ii', $filter_section, $teacher['id']);
    $chkSec->execute();
    $validSec = $chkSec->get_result()->fetch_assoc();
    $chkSec->close();

    if ($validSec) {
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
        $error = 'Ban khong co quyen truy cap lop hoc phan nay.';
        $filter_section = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nhap diem - Giang vien</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/university/assets/css/style.css">
</head>
<body>
<div class="student-wrapper">
    <div class="student-sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon"><i class="bi bi-person-badge-fill"></i></div>
            <div class="sidebar-brand-text"><div>Cong Giang vien</div><small><?php echo htmlspecialchars($teacher['teacher_code']); ?></small></div>
        </div>
        <nav class="sidebar-nav">
            <a href="/university/teacher/index.php" class="sidebar-link"><i class="bi bi-speedometer2"></i> Tong quan</a>
            <a href="/university/teacher/profile.php" class="sidebar-link"><i class="bi bi-person-fill"></i> Ho so ca nhan</a>
            <a href="/university/teacher/my_courses.php" class="sidebar-link"><i class="bi bi-journal-text"></i> Lop hoc phan</a>
            <a href="/university/teacher/grades.php" class="sidebar-link active"><i class="bi bi-bar-chart-fill"></i> Nhap diem</a>
            <a href="/university/teacher/evaluation.php" class="sidebar-link"><i class="bi bi-star-fill"></i> Ket qua danh gia</a>
            <hr class="my-2">
            <a href="/university/index.php" class="sidebar-link"><i class="bi bi-globe"></i> Trang chu</a>
            <a href="/university/login.php?logout=1" class="sidebar-link text-danger"><i class="bi bi-box-arrow-right"></i> Dang xuat</a>
        </nav>
    </div>
    <div class="student-main">
        <div class="student-topbar">
            <span class="fw-bold text-navy">Nhap diem sinh vien</span>
            <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i></a>
        </div>
        <div class="student-content">
            <?php if ($success): ?><div class="alert alert-success auto-dismiss alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger auto-dismiss alert-dismissible fade show"><i class="bi bi-exclamation-circle-fill me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-funnel me-2"></i>Chon lop hoc phan</div>
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-8">
                            <label class="form-label">Lop hoc phan</label>
                            <select name="section_id" class="form-select">
                                <option value="">-- Chon lop hoc phan --</option>
                                <?php if ($mySections && $mySections->num_rows > 0): while ($sec = $mySections->fetch_assoc()): ?>
                                <option value="<?php echo $sec['id']; ?>" <?php echo $filter_section==$sec['id']?'selected':''; ?>>
                                    <?php echo htmlspecialchars($sec['section_code'] . ' - ' . $sec['subject_name'] . ' (' . $sec['semester_name'] . ' ' . $sec['school_year'] . ')'); ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-navy w-100"><i class="bi bi-search me-1"></i>Xem danh sach</button>
                        </div>
                    </form>
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
</body>
</html>
