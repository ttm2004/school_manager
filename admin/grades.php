<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Quản lý Điểm số';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_grade') {
        $ss_id = intval($_POST['student_subject_id'] ?? 0);
        $process = $_POST['process_score'] !== '' ? floatval($_POST['process_score']) : null;
        $midterm = $_POST['midterm_score'] !== '' ? floatval($_POST['midterm_score']) : null;
        $final   = $_POST['final_score'] !== '' ? floatval($_POST['final_score']) : null;
        $note    = trim($_POST['note'] ?? '');

        // Tính tổng điểm
        $total = null;
        $letter = null;
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

        // Check existing
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
        $stmt->execute() ? $success = 'Lưu điểm thành công!' : $error = 'Lỗi: ' . $conn->error;
        $stmt->close();
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

// Lấy danh sách lớp học phần để lọc
$sections = $conn->query("SELECT cs.id, cs.section_code, s.subject_name, sm.semester_name, sm.school_year
    FROM course_sections cs
    JOIN subjects s ON cs.subject_id = s.id
    JOIN semesters sm ON cs.semester_id = sm.id
    ORDER BY sm.school_year DESC, sm.semester_name, cs.section_code");

$filter_section = intval($_GET['section_id'] ?? 0);

// Lấy danh sách điểm
$grades = [];
if ($filter_section > 0) {
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
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
            <span class="admin-topbar-title">Quản lý Điểm số</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
        </div>
    </div>
    <div class="admin-content">
        <?php $flash = getFlash(); if ($flash): ?><div class="alert alert-<?php echo $flash['type']; ?> auto-dismiss alert-dismissible fade show"><i class="bi bi-<?php echo $flash['type']=='success'?'check-circle-fill':'exclamation-circle-fill'; ?> me-2"></i><?php echo htmlspecialchars($flash['message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-funnel me-2"></i>Chọn lớp học phần</div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label">Lớp học phần</label>
                        <select name="section_id" class="form-select">
                            <option value="">-- Chọn lớp học phần --</option>
                            <?php if ($sections && $sections->num_rows > 0): while ($sec = $sections->fetch_assoc()): ?>
                            <option value="<?php echo $sec['id']; ?>" <?php echo $filter_section==$sec['id']?'selected':''; ?>>
                                <?php echo htmlspecialchars($sec['section_code'] . ' - ' . $sec['subject_name'] . ' (' . $sec['semester_name'] . ' ' . $sec['school_year'] . ')'); ?>
                            </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-navy w-100"><i class="bi bi-search me-1"></i>Xem danh sách</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($filter_section > 0): ?>
        <div class="card">
            <div class="card-header"><i class="bi bi-bar-chart-fill me-2"></i>Nhập điểm sinh viên</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Mã SV</th>
                                <th>Họ tên</th>
                                <th>Điểm QT (20%)</th>
                                <th>Điểm GK (30%)</th>
                                <th>Điểm CK (50%)</th>
                                <th>Tổng kết</th>
                                <th>Xếp loại</th>
                                <th>Ghi chú</th>
                                <th>Lưu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($grades && $grades->num_rows > 0): $idx=1; while ($g = $grades->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $idx++; ?></td>
                                <td class="fw-bold text-navy"><?php echo htmlspecialchars($g['student_code']); ?></td>
                                <td><?php echo htmlspecialchars($g['full_name']); ?></td>
                                <form method="POST">
                                    <input type="hidden" name="action" value="save_grade">
                                    <input type="hidden" name="student_subject_id" value="<?php echo $g['ss_id']; ?>">
                                    <input type="hidden" name="section_id" value="<?php echo $filter_section; ?>">
                                    <td><input type="number" name="process_score" class="form-control form-control-sm grade-input" min="0" max="10" step="0.1" value="<?php echo $g['process_score'] ?? ''; ?>" style="width:80px"></td>
                                    <td><input type="number" name="midterm_score" class="form-control form-control-sm grade-input" min="0" max="10" step="0.1" value="<?php echo $g['midterm_score'] ?? ''; ?>" style="width:80px"></td>
                                    <td><input type="number" name="final_score" class="form-control form-control-sm grade-input" min="0" max="10" step="0.1" value="<?php echo $g['final_score'] ?? ''; ?>" style="width:80px"></td>
                                    <td>
                                        <?php if ($g['total_score'] !== null): ?>
                                        <span class="fw-bold <?php echo $g['total_score']>=5?'text-success':'text-danger'; ?>">
                                            <?php echo number_format($g['total_score'],2); ?>
                                        </span>
                                        <?php else: ?><span class="text-muted">--</span><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($g['letter_grade']): ?>
                                        <?php
                                        $lc = ['A'=>'success','B+'=>'primary','B'=>'info','C+'=>'warning','C'=>'warning','D+'=>'secondary','D'=>'secondary','F'=>'danger'];
                                        $lcolor = $lc[$g['letter_grade']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $lcolor; ?>"><?php echo $g['letter_grade']; ?></span>
                                        <?php else: ?><span class="text-muted">--</span><?php endif; ?>
                                    </td>
                                    <td><input type="text" name="note" class="form-control form-control-sm" value="<?php echo htmlspecialchars($g['note'] ?? ''); ?>" style="width:120px"></td>
                                    <td><button type="submit" class="btn btn-sm btn-gold"><i class="bi bi-save"></i></button></td>
                                </form>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="10" class="text-center text-muted py-4">Chưa có sinh viên đăng ký lớp học phần này</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
</body></html>
