<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Lịch thi cuối kỳ';

$success = $error = '';

// ===== XỬ LÝ POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $section_id = intval($_POST['course_section_id'] ?? 0);
        $exam_date  = trim($_POST['exam_date'] ?? '');
        $start_time = trim($_POST['start_time'] ?? '');
        $end_time   = trim($_POST['end_time'] ?? '');
        $room       = trim($_POST['room'] ?? '');
        $exam_form  = trim($_POST['exam_form'] ?? 'Tự luận');
        $note       = trim($_POST['note'] ?? '');
        $status     = trim($_POST['status'] ?? 'scheduled');

        if ($section_id && $exam_date && $start_time && $end_time) {
            // Kiểm tra ngày thi phải sau ngày kết thúc học kỳ
            $semEnd = $conn->prepare("SELECT sm.end_date, sm.semester_name, sm.school_year FROM course_sections cs JOIN semesters sm ON cs.semester_id=sm.id WHERE cs.id=?");
            $semEnd->bind_param('i', $section_id);
            $semEnd->execute();
            $semRow = $semEnd->get_result()->fetch_assoc();
            $semEnd->close();

            if (!$semRow) {
                $error = 'Không tìm thấy thông tin học kỳ của lớp học phần này.';
            } elseif (empty($semRow['end_date'])) {
                $error = 'Học kỳ <strong>' . htmlspecialchars($semRow['semester_name'] . ' ' . $semRow['school_year']) . '</strong> chưa có ngày kết thúc. Vui lòng <a href="semesters.php" class="alert-link">cập nhật học kỳ</a> trước khi xếp lịch thi.';
            } elseif ($exam_date <= $semRow['end_date']) {
                $error = 'Ngày thi <strong>' . date('d/m/Y', strtotime($exam_date)) . '</strong> phải sau ngày kết thúc học kỳ <strong>' . date('d/m/Y', strtotime($semRow['end_date'])) . '</strong>.';
            } else {
                $stmt = $conn->prepare("INSERT INTO final_exam_schedules
                    (course_section_id, exam_date, start_time, end_time, room, exam_form, note, status)
                    VALUES (?,?,?,?,?,?,?,?)");
                $stmt->bind_param('isssssss', $section_id, $exam_date, $start_time, $end_time, $room, $exam_form, $note, $status);
                $stmt->execute() ? $success = 'Thêm lịch thi thành công!' : $error = 'Lỗi: ' . $conn->error;
                $stmt->close();
            }
        } else {
            $error = 'Vui lòng điền đầy đủ thông tin bắt buộc.';
        }
    }

    if ($action === 'edit') {
        $id         = intval($_POST['id'] ?? 0);
        $section_id = intval($_POST['course_section_id'] ?? 0);
        $exam_date  = trim($_POST['exam_date'] ?? '');
        $start_time = trim($_POST['start_time'] ?? '');
        $end_time   = trim($_POST['end_time'] ?? '');
        $room       = trim($_POST['room'] ?? '');
        $exam_form  = trim($_POST['exam_form'] ?? 'Tự luận');
        $note       = trim($_POST['note'] ?? '');
        $status     = trim($_POST['status'] ?? 'scheduled');

        if ($id && $section_id && $exam_date && $start_time && $end_time) {
            // Kiểm tra ngày thi phải sau ngày kết thúc học kỳ
            $semEnd = $conn->prepare("SELECT sm.end_date, sm.semester_name, sm.school_year FROM course_sections cs JOIN semesters sm ON cs.semester_id=sm.id WHERE cs.id=?");
            $semEnd->bind_param('i', $section_id);
            $semEnd->execute();
            $semRow = $semEnd->get_result()->fetch_assoc();
            $semEnd->close();

            if (!$semRow) {
                $error = 'Không tìm thấy thông tin học kỳ của lớp học phần này.';
            } elseif (empty($semRow['end_date'])) {
                $error = 'Học kỳ <strong>' . htmlspecialchars($semRow['semester_name'] . ' ' . $semRow['school_year']) . '</strong> chưa có ngày kết thúc. Vui lòng <a href="semesters.php" class="alert-link">cập nhật học kỳ</a> trước khi xếp lịch thi.';
            } elseif ($exam_date <= $semRow['end_date']) {
                $error = 'Ngày thi <strong>' . date('d/m/Y', strtotime($exam_date)) . '</strong> phải sau ngày kết thúc học kỳ <strong>' . date('d/m/Y', strtotime($semRow['end_date'])) . '</strong>.';
            } else {
                $stmt = $conn->prepare("UPDATE final_exam_schedules
                    SET course_section_id=?, exam_date=?, start_time=?, end_time=?, room=?, exam_form=?, note=?, status=?
                    WHERE id=?");
                $stmt->bind_param('isssssssi', $section_id, $exam_date, $start_time, $end_time, $room, $exam_form, $note, $status, $id);
                $stmt->execute() ? $success = 'Cập nhật lịch thi thành công!' : $error = 'Lỗi: ' . $conn->error;
                $stmt->close();
            }
        }
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM final_exam_schedules WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute() ? $success = 'Xóa lịch thi thành công!' : $error = 'Lỗi: ' . $conn->error;
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

// ===== AJAX: Kiểm tra phòng thi trống theo ngày + giờ =====
if (isset($_GET['check_room']) && isset($_GET['exam_date']) && isset($_GET['start_time']) && isset($_GET['end_time'])) {
    header('Content-Type: application/json');
    $exam_date  = trim($_GET['exam_date']);
    $start_time = trim($_GET['start_time']);
    $end_time   = trim($_GET['end_time']);
    $exclude_id = intval($_GET['exclude_id'] ?? 0); // khi edit, bỏ qua lịch thi hiện tại

    // Lấy tất cả phòng đang bị dùng trong khung giờ đó
    $sql = "SELECT room FROM final_exam_schedules
            WHERE exam_date = ?
              AND status != 'cancelled'
              AND room != ''
              AND start_time < ?
              AND end_time > ?";
    $params = [$exam_date, $end_time, $start_time];
    $types  = 'sss';
    if ($exclude_id) { $sql .= " AND id != ?"; $params[] = $exclude_id; $types .= 'i'; }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $busyRooms = [];
    while ($row = $result->fetch_assoc()) $busyRooms[$row['room']] = true;
    $stmt->close();

    echo json_encode(['busy' => array_keys($busyRooms)]);
    exit;
}


$filter_sem  = intval($_GET['semester_id'] ?? 0);
$filter_date = trim($_GET['exam_date'] ?? '');
$filter_status = trim($_GET['status'] ?? '');

$whereArr = [];
if ($filter_sem)    $whereArr[] = "cs.semester_id = " . intval($filter_sem);
if ($filter_date)   $whereArr[] = "f.exam_date = '" . $conn->real_escape_string($filter_date) . "'";
if ($filter_status) $whereArr[] = "f.status = '" . $conn->real_escape_string($filter_status) . "'";
$whereSQL = $whereArr ? 'WHERE ' . implode(' AND ', $whereArr) : '';

// ===== LẤY DỮ LIỆU =====
$exams = $conn->query("
    SELECT f.*,
           cs.section_code, cs.room as cs_room,
           s.subject_name, s.credits,
           sm.semester_name, sm.school_year, sm.end_date as sem_end_date,
           u.full_name as teacher_name,
           COUNT(DISTINCT ss.student_id) as eligible_students
    FROM final_exam_schedules f
    JOIN course_sections cs ON f.course_section_id = cs.id
    JOIN subjects s ON cs.subject_id = s.id
    JOIN semesters sm ON cs.semester_id = sm.id
    JOIN teachers t ON cs.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    LEFT JOIN student_subjects ss ON ss.course_section_id = cs.id AND ss.status = 'registered'
    $whereSQL
    GROUP BY f.id
    ORDER BY f.exam_date ASC, f.start_time ASC
");

$semesters    = $conn->query("SELECT * FROM semesters ORDER BY school_year DESC, id DESC");
$allSections  = $conn->query("
    SELECT cs.id, cs.section_code, s.subject_name, sm.semester_name, sm.school_year,
           sm.end_date as sem_end_date
    FROM course_sections cs
    JOIN subjects s ON cs.subject_id = s.id
    JOIN semesters sm ON cs.semester_id = sm.id
    ORDER BY sm.school_year DESC, s.subject_name
");

// Thống kê
$stats = $conn->query("
    SELECT
        COUNT(*) as total,
        SUM(status='scheduled') as scheduled,
        SUM(status='completed') as completed,
        SUM(status='cancelled') as cancelled
    FROM final_exam_schedules
")->fetch_assoc();

// Danh sách phòng thi (từ bảng rooms nếu có, fallback về danh sách cứng)
$roomsList = [];
$roomsCheck = $conn->query("SHOW TABLES LIKE 'rooms'");
if ($roomsCheck && $roomsCheck->num_rows > 0) {
    $roomsResult = $conn->query("SELECT room_code, room_name, building, capacity, room_type FROM rooms WHERE status='active' ORDER BY building, room_code");
    if ($roomsResult) {
        while ($r = $roomsResult->fetch_assoc()) $roomsList[] = $r;
    }
}
// Fallback nếu bảng rooms chưa có data
if (empty($roomsList)) {
    $buildings = ['Dãy A1','Dãy A2','Dãy A3','Dãy A4','Dãy B1','Dãy B2','Dãy B3','Dãy B4'];
    foreach ($buildings as $b) {
        for ($i = 1; $i <= 6; $i++) {
            $code = str_replace('Dãy ', '', $b) . '.' . str_pad($i, 2, '0', STR_PAD_LEFT);
            $roomsList[] = ['room_code'=>$code,'room_name'=>"Phòng $code",'building'=>$b,'capacity'=>45,'room_type'=>'lecture'];
        }
    }
    foreach (['C1','C2','C3','C4'] as $b) {
        for ($i = 1; $i <= 3; $i++) {
            $code = "$b." . str_pad($i, 2, '0', STR_PAD_LEFT);
            $roomsList[] = ['room_code'=>$code,'room_name'=>"Phòng máy $code",'building'=>"Dãy $b",'capacity'=>40,'room_type'=>'lab'];
        }
    }
    foreach (['HT.A'=>['Hội trường A',300],'HT.B'=>['Hội trường B',300],'GD.01'=>['Giảng đường 01',120],'GD.02'=>['Giảng đường 02',120]] as $code=>$info) {
        $roomsList[] = ['room_code'=>$code,'room_name'=>$info[0],'building'=>'Hội trường','capacity'=>$info[1],'room_type'=>'exam_hall'];
    }
}

// Nhóm phòng theo tòa/dãy để hiển thị optgroup
$roomsByBuilding = [];
foreach ($roomsList as $r) {
    $roomsByBuilding[$r['building']][] = $r;
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
            <span class="admin-topbar-title"><i class="bi bi-calendar-event-fill me-2"></i>Lịch thi cuối kỳ</span>
        </div>
    </div>
    <div class="admin-content">

        <?php $flash = getFlash(); if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type']; ?> auto-dismiss alert-dismissible fade show">
            <i class="bi bi-<?php echo $flash['type']==='success'?'check-circle-fill':'exclamation-circle-fill'; ?> me-2"></i><?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Thống kê -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="fs-3 fw-bold text-navy"><?php echo $stats['total'] ?? 0; ?></div>
                        <div class="small text-muted">Tổng lịch thi</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="fs-3 fw-bold text-primary"><?php echo $stats['scheduled'] ?? 0; ?></div>
                        <div class="small text-muted">Đã lên lịch</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="fs-3 fw-bold text-success"><?php echo $stats['completed'] ?? 0; ?></div>
                        <div class="small text-muted">Đã thi xong</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card text-center border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="fs-3 fw-bold text-danger"><?php echo $stats['cancelled'] ?? 0; ?></div>
                        <div class="small text-muted">Đã hủy</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bộ lọc -->
        <div class="card mb-4">
            <div class="card-body py-2">
                <form method="GET" class="d-flex gap-2 align-items-end flex-wrap">
                    <div>
                        <label class="form-label small mb-1">Học kỳ</label>
                        <select name="semester_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Tất cả học kỳ</option>
                            <?php if ($semesters): while ($sem = $semesters->fetch_assoc()): ?>
                            <option value="<?php echo $sem['id']; ?>" <?php echo $filter_sem==$sem['id']?'selected':''; ?>>
                                <?php echo htmlspecialchars($sem['semester_name'] . ' ' . $sem['school_year']); ?>
                            </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label small mb-1">Ngày thi</label>
                        <input type="date" name="exam_date" class="form-control form-control-sm"
                               value="<?php echo htmlspecialchars($filter_date); ?>" onchange="this.form.submit()">
                    </div>
                    <div>
                        <label class="form-label small mb-1">Trạng thái</label>
                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Tất cả</option>
                            <option value="scheduled"  <?php echo $filter_status=='scheduled' ?'selected':''; ?>>Đã lên lịch</option>
                            <option value="completed"  <?php echo $filter_status=='completed' ?'selected':''; ?>>Đã thi xong</option>
                            <option value="cancelled"  <?php echo $filter_status=='cancelled' ?'selected':''; ?>>Đã hủy</option>
                        </select>
                    </div>
                    <?php if ($filter_sem || $filter_date || $filter_status): ?>
                    <a href="final_exam_schedules.php" class="btn btn-outline-secondary btn-sm align-self-end">
                        <i class="bi bi-x-lg me-1"></i>Xóa lọc
                    </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Bảng danh sách -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-calendar-event-fill me-2"></i>Danh sách lịch thi cuối kỳ</span>
                <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-lg me-1"></i>Thêm lịch thi
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Môn học / Mã lớp HP</th>
                                <th>Học kỳ</th>
                                <th>Giảng viên</th>
                                <th>Ngày thi</th>
                                <th>Giờ thi</th>
                                <th>Phòng thi</th>
                                <th>Hình thức</th>
                                <th>SV dự thi</th>
                                <th>Trạng thái</th>
                                <th>Ghi chú</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $statusMap = [
                                'scheduled' => ['Đã lên lịch', 'primary'],
                                'completed' => ['Đã thi xong', 'success'],
                                'cancelled' => ['Đã hủy',      'danger'],
                            ];
                            $formMap = [
                                'Tự luận'    => ['secondary', 'bi-pencil-fill'],
                                'Trắc nghiệm'=> ['info',      'bi-ui-checks'],
                                'Tiểu luận'  => ['warning',   'bi-file-earmark-text-fill'],
                            ];
                            if ($exams && $exams->num_rows > 0):
                                $idx = 1;
                                while ($e = $exams->fetch_assoc()):
                                    $st = $statusMap[$e['status']] ?? [$e['status'], 'secondary'];
                                    $fm = $formMap[$e['exam_form']] ?? ['secondary', 'bi-question'];
                                    $examTs = strtotime($e['exam_date']);
                                    $isToday   = date('Y-m-d') === $e['exam_date'];
                                    $isPast    = $examTs < strtotime('today');
                                    $isUpcoming = $examTs <= strtotime('+7 days') && $examTs >= strtotime('today');
                            ?>
                            <tr class="<?php echo $isToday ? 'table-warning' : ($isPast && $e['status']==='scheduled' ? 'table-light' : ''); ?>">
                                <td class="text-muted small"><?php echo $idx++; ?></td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($e['subject_name']); ?></div>
                                    <span class="badge bg-navy" style="font-size:0.7rem"><?php echo htmlspecialchars($e['section_code']); ?></span>
                                    <span class="badge bg-secondary ms-1" style="font-size:0.7rem"><?php echo $e['credits']; ?> TC</span>
                                </td>
                                <td class="small text-muted"><?php echo htmlspecialchars($e['semester_name'] . ' ' . $e['school_year']); ?></td>
                                <td class="small"><?php echo htmlspecialchars($e['teacher_name']); ?></td>
                                <td>
                                    <div class="fw-bold <?php echo $isToday ? 'text-warning' : ''; ?>">
                                        <?php echo date('d/m/Y', $examTs); ?>
                                    </div>
                                    <div class="small text-muted"><?php echo date('l', $examTs); ?></div>
                                    <?php if ($isToday): ?>
                                    <span class="badge bg-warning text-dark" style="font-size:0.65rem">Hôm nay</span>
                                    <?php elseif ($isUpcoming && !$isPast): ?>
                                    <span class="badge bg-info text-dark" style="font-size:0.65rem">Sắp thi</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small fw-bold">
                                    <?php echo substr($e['start_time'],0,5); ?> – <?php echo substr($e['end_time'],0,5); ?>
                                </td>
                                <td class="small fw-bold text-navy"><?php echo htmlspecialchars($e['room'] ?: '--'); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $fm[0]; ?>">
                                        <i class="bi <?php echo $fm[1]; ?> me-1"></i><?php echo htmlspecialchars($e['exam_form']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-navy">
                                        <i class="bi bi-people-fill me-1"></i><?php echo $e['eligible_students']; ?> SV
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $st[1]; ?>"><?php echo $st[0]; ?></span>
                                </td>
                                <td class="small text-muted" style="max-width:150px">
                                    <?php echo $e['note'] ? htmlspecialchars($e['note']) : '<span class="fst-italic">--</span>'; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1"
                                        data-bs-toggle="modal" data-bs-target="#editModal"
                                        data-id="<?php echo $e['id']; ?>"
                                        data-section="<?php echo $e['course_section_id']; ?>"
                                        data-date="<?php echo $e['exam_date']; ?>"
                                        data-start="<?php echo $e['start_time']; ?>"
                                        data-end="<?php echo $e['end_time']; ?>"
                                        data-room="<?php echo htmlspecialchars($e['room']); ?>"
                                        data-form="<?php echo htmlspecialchars($e['exam_form']); ?>"
                                        data-note="<?php echo htmlspecialchars($e['note']); ?>"
                                        data-status="<?php echo $e['status']; ?>"
                                        data-sem-end="<?php echo $e['sem_end_date'] ?? ''; ?>">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Xóa lịch thi này?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $e['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr>
                                <td colspan="11" class="text-center text-muted py-5">
                                    <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
                                    Chưa có lịch thi nào<?php echo ($filter_sem||$filter_date||$filter_status) ? ' phù hợp với bộ lọc' : ''; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div><!-- /.admin-content -->
    <div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một</div>
</div>

<!-- ===== MODAL THÊM ===== -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Thêm lịch thi cuối kỳ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Lớp học phần <span class="text-danger">*</span></label>
                            <select name="course_section_id" class="form-select" required>
                                <option value="">-- Chọn lớp học phần --</option>
                                <?php if ($allSections): while ($sec = $allSections->fetch_assoc()): ?>
                                <option value="<?php echo $sec['id']; ?>"
                                        data-sem-end="<?php echo $sec['sem_end_date'] ?? ''; ?>">
                                    <?php echo htmlspecialchars($sec['section_code'] . ' — ' . $sec['subject_name'] . ' (' . $sec['semester_name'] . ' ' . $sec['school_year'] . ')'); ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ngày thi <span class="text-danger">*</span></label>
                            <input type="date" name="exam_date" class="form-control" required
                                   min="<?php echo date('Y-m-d'); ?>"
                                   value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Giờ bắt đầu <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" id="addStart" class="form-control" required value="07:00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Giờ kết thúc <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" id="addEnd" class="form-control" required value="09:00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Phòng thi</label>
                            <select name="room" id="addRoomSelect" class="form-select">
                                <option value="">-- Chọn phòng thi --</option>
                                <?php foreach ($roomsByBuilding as $building => $rooms): ?>
                                <optgroup label="<?php echo htmlspecialchars($building); ?>">
                                    <?php foreach ($rooms as $r):
                                        $typeLabel = ['lecture'=>'LT','lab'=>'Máy','exam_hall'=>'HT','seminar'=>'HT'][$r['room_type']] ?? '';
                                    ?>
                                    <option value="<?php echo htmlspecialchars($r['room_code']); ?>"
                                            data-base="<?php echo htmlspecialchars($r['room_code'] . ' — ' . $r['room_name'] . ' (' . $r['capacity'] . ' chỗ)'); ?>">
                                        <?php echo htmlspecialchars($r['room_code'] . ' — ' . $r['room_name'] . ' (' . $r['capacity'] . ' chỗ)'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Hình thức thi</label>
                            <select name="exam_form" class="form-select">
                                <option value="Tự luận">Tự luận</option>
                                <option value="Trắc nghiệm">Trắc nghiệm</option>
                                <option value="Tiểu luận">Tiểu luận</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Trạng thái</label>
                            <select name="status" class="form-select">
                                <option value="scheduled">Đã lên lịch</option>
                                <option value="completed">Đã thi xong</option>
                                <option value="cancelled">Đã hủy</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Ghi chú</label>
                            <input type="text" name="note" class="form-control" placeholder="VD: Mang theo CMND, cấm tài liệu...">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-gold"><i class="bi bi-save me-1"></i>Lưu lịch thi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== MODAL SỬA ===== -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Chỉnh sửa lịch thi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Lớp học phần <span class="text-danger">*</span></label>
                            <select name="course_section_id" id="editSection" class="form-select" required>
                                <option value="">-- Chọn lớp học phần --</option>
                                <?php
                                $allSections2 = $conn->query("
                                    SELECT cs.id, cs.section_code, s.subject_name, sm.semester_name, sm.school_year,
                                           sm.end_date as sem_end_date
                                    FROM course_sections cs
                                    JOIN subjects s ON cs.subject_id = s.id
                                    JOIN semesters sm ON cs.semester_id = sm.id
                                    ORDER BY sm.school_year DESC, s.subject_name
                                ");
                                if ($allSections2): while ($sec = $allSections2->fetch_assoc()): ?>
                                <option value="<?php echo $sec['id']; ?>"
                                        data-sem-end="<?php echo $sec['sem_end_date'] ?? ''; ?>">
                                    <?php echo htmlspecialchars($sec['section_code'] . ' — ' . $sec['subject_name'] . ' (' . $sec['semester_name'] . ' ' . $sec['school_year'] . ')'); ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ngày thi <span class="text-danger">*</span></label>
                            <input type="date" name="exam_date" id="editDate" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Giờ bắt đầu <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" id="editStart" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Giờ kết thúc <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" id="editEnd" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Phòng thi</label>
                            <select name="room" id="editRoomSelect" class="form-select">
                                <option value="">-- Chọn phòng thi --</option>
                                <?php foreach ($roomsByBuilding as $building => $rooms): ?>
                                <optgroup label="<?php echo htmlspecialchars($building); ?>">
                                    <?php foreach ($rooms as $r): ?>
                                    <option value="<?php echo htmlspecialchars($r['room_code']); ?>"
                                            data-base="<?php echo htmlspecialchars($r['room_code'] . ' — ' . $r['room_name'] . ' (' . $r['capacity'] . ' chỗ)'); ?>">
                                        <?php echo htmlspecialchars($r['room_code'] . ' — ' . $r['room_name'] . ' (' . $r['capacity'] . ' chỗ)'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Hình thức thi</label>
                            <select name="exam_form" id="editForm" class="form-select">
                                <option value="Tự luận">Tự luận</option>
                                <option value="Trắc nghiệm">Trắc nghiệm</option>
                                <option value="Tiểu luận">Tiểu luận</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Trạng thái</label>
                            <select name="status" id="editStatus" class="form-select">
                                <option value="scheduled">Đã lên lịch</option>
                                <option value="completed">Đã thi xong</option>
                                <option value="cancelled">Đã hủy</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Ghi chú</label>
                            <input type="text" name="note" id="editNote" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-gold"><i class="bi bi-save me-1"></i>Cập nhật</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<script>
document.getElementById('editModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('editId').value      = btn.dataset.id;
    document.getElementById('editSection').value = btn.dataset.section;
    document.getElementById('editDate').value    = btn.dataset.date;
    document.getElementById('editStart').value   = btn.dataset.start;
    document.getElementById('editEnd').value     = btn.dataset.end;
    document.getElementById('editRoomSelect').value = btn.dataset.room;
    document.getElementById('editForm').value    = btn.dataset.form;
    document.getElementById('editNote').value    = btn.dataset.note;
    document.getElementById('editStatus').value  = btn.dataset.status;
    // Cập nhật min cho giờ kết thúc
    document.getElementById('editEnd').min = btn.dataset.start;
    // Cập nhật min ngày thi = ngày sau ngày kết thúc học kỳ
    if (btn.dataset.semEnd) {
        const minDate = getNextDay(btn.dataset.semEnd);
        document.getElementById('editDate').min = minDate;
        document.getElementById('editDate').title = 'Phải sau ngày kết thúc học kỳ: ' + formatDate(btn.dataset.semEnd);
    }
    // Kiểm tra phòng ngay khi mở modal
    setTimeout(() => checkRoomAvailability('edit'), 200);
});

// Hàm lấy ngày hôm sau
function getNextDay(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    d.setDate(d.getDate() + 1);
    return d.toISOString().split('T')[0];
}

// Hàm format ngày dd/mm/yyyy
function formatDate(dateStr) {
    if (!dateStr) return '';
    const [y, m, d] = dateStr.split('-');
    return d + '/' + m + '/' + y;
}

// Khi chọn lớp HP trong modal Thêm → cập nhật min ngày thi
document.querySelector('#addModal select[name="course_section_id"]').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const semEnd = opt.dataset.semEnd || '';
    const dateInput = document.querySelector('#addModal input[name="exam_date"]');
    if (semEnd) {
        const minDate = getNextDay(semEnd);
        dateInput.min = minDate;
        dateInput.title = 'Phải sau ngày kết thúc học kỳ: ' + formatDate(semEnd);
        // Nếu ngày đang chọn <= ngày kết thúc HK thì reset
        if (dateInput.value && dateInput.value <= semEnd) {
            dateInput.value = minDate;
        }
    } else {
        dateInput.min = new Date().toISOString().split('T')[0];
        dateInput.title = '';
    }
});

// Khi chọn lớp HP trong modal Sửa → cập nhật min ngày thi
document.getElementById('editSection').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const semEnd = opt.dataset.semEnd || '';
    if (semEnd) {
        const minDate = getNextDay(semEnd);
        document.getElementById('editDate').min = minDate;
        document.getElementById('editDate').title = 'Phải sau ngày kết thúc học kỳ: ' + formatDate(semEnd);
        if (document.getElementById('editDate').value && document.getElementById('editDate').value <= semEnd) {
            document.getElementById('editDate').value = minDate;
        }
    }
});

// Validation giờ kết thúc > giờ bắt đầu cho modal Thêm
document.querySelector('#addModal form').addEventListener('submit', function(e) {
    const start = document.getElementById('addStart').value;
    const end   = document.getElementById('addEnd').value;
    if (start && end && end <= start) {
        e.preventDefault();
        alert('Giờ kết thúc phải lớn hơn giờ bắt đầu!');
        document.getElementById('addEnd').focus();
    }
});

// Validation giờ kết thúc > giờ bắt đầu cho modal Sửa
document.querySelector('#editModal form').addEventListener('submit', function(e) {
    const start = document.getElementById('editStart').value;
    const end   = document.getElementById('editEnd').value;
    if (start && end && end <= start) {
        e.preventDefault();
        alert('Giờ kết thúc phải lớn hơn giờ bắt đầu!');
        document.getElementById('editEnd').focus();
    }
});

// Tự động cập nhật min giờ kết thúc khi đổi giờ bắt đầu (modal Thêm)
document.getElementById('addStart').addEventListener('change', function() {
    document.getElementById('addEnd').min = this.value;
    if (document.getElementById('addEnd').value && document.getElementById('addEnd').value <= this.value) {
        document.getElementById('addEnd').value = '';
    }
    checkRoomAvailability('add');
});

// Tự động cập nhật min giờ kết thúc khi đổi giờ bắt đầu (modal Sửa)
document.getElementById('editStart').addEventListener('change', function() {
    document.getElementById('editEnd').min = this.value;
    if (document.getElementById('editEnd').value && document.getElementById('editEnd').value <= this.value) {
        document.getElementById('editEnd').value = '';
    }
    checkRoomAvailability('edit');
});

// Trigger check khi đổi ngày hoặc giờ kết thúc
document.querySelector('#addModal input[name="exam_date"]').addEventListener('change', () => checkRoomAvailability('add'));
document.getElementById('addEnd').addEventListener('change', () => checkRoomAvailability('add'));
document.getElementById('editDate').addEventListener('change', () => checkRoomAvailability('edit'));
document.getElementById('editEnd').addEventListener('change', () => checkRoomAvailability('edit'));

// ===== Kiểm tra phòng thi trống/bận theo ngày + giờ =====
let checkTimer = null;
function checkRoomAvailability(form) {
    clearTimeout(checkTimer);
    checkTimer = setTimeout(() => _doCheckRoom(form), 300);
}

function _doCheckRoom(form) {
    const isAdd  = form === 'add';
    const date   = isAdd
        ? document.querySelector('#addModal input[name="exam_date"]').value
        : document.getElementById('editDate').value;
    const start  = isAdd ? document.getElementById('addStart').value : document.getElementById('editStart').value;
    const end    = isAdd ? document.getElementById('addEnd').value   : document.getElementById('editEnd').value;
    const excId  = isAdd ? 0 : (document.getElementById('editId')?.value || 0);

    if (!date || !start || !end || end <= start) return;

    const url = `?check_room=1&exam_date=${date}&start_time=${start}&end_time=${end}&exclude_id=${excId}`;
    fetch(url)
        .then(r => r.json())
        .then(data => {
            const busySet = new Set(data.busy || []);
            const selId   = isAdd ? 'addRoomSelect' : 'editRoomSelect';
            const sel     = document.getElementById(selId);
            if (!sel) return;

            // Cập nhật từng option
            [...sel.options].forEach(opt => {
                if (!opt.value) return; // bỏ qua option trống
                const isBusy = busySet.has(opt.value);
                // Cập nhật text hiển thị
                const base = opt.dataset.base || opt.text.replace(/ 🔴.*| 🟢.*/g, '');
                opt.dataset.base = base;
                opt.text = base + (isBusy ? ' 🔴 Bận' : ' 🟢 Trống');
                opt.style.color = isBusy ? '#dc3545' : '#198754';
                opt.disabled = false; // không disable để vẫn có thể chọn nếu cần
            });

            // Hiển thị badge cảnh báo nếu phòng đang chọn bị bận
            const selectedRoom = sel.value;
            const warnId = isAdd ? 'addRoomWarn' : 'editRoomWarn';
            let warnEl = document.getElementById(warnId);
            if (!warnEl) {
                warnEl = document.createElement('div');
                warnEl.id = warnId;
                warnEl.className = 'mt-1';
                sel.parentNode.appendChild(warnEl);
            }
            if (selectedRoom && busySet.has(selectedRoom)) {
                warnEl.innerHTML = '<span class="badge bg-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>Phòng này đã có lịch thi trong khung giờ đó!</span>';
            } else if (selectedRoom) {
                warnEl.innerHTML = '<span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i>Phòng trống trong khung giờ này</span>';
            } else {
                warnEl.innerHTML = '';
            }
        })
        .catch(() => {});
}

// Trigger check khi đổi phòng
document.addEventListener('change', function(e) {
    if (e.target.id === 'addRoomSelect') checkRoomAvailability('add');
    if (e.target.id === 'editRoomSelect') checkRoomAvailability('edit');
});
</script>
</body>
</html>
