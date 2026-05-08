<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Quản lý Lớp học phần';

// Tự động thêm các cột mới nếu chưa có
foreach ([
    'start_time TIME NULL',
    'end_time TIME NULL',
    'start_date DATE NULL',
    'end_date DATE NULL',
    'sessions_per_week TINYINT DEFAULT 2',
    "study_days VARCHAR(20) NULL COMMENT '2,3,4,5,6,7,8'",
    "session_type VARCHAR(10) NULL COMMENT 'sang/chieu/toi'",
    "day_sessions VARCHAR(50) NULL COMMENT '2:sang,4:chieu'",
    "class_id INT NULL COMMENT 'Lớp học được gán'"
] as $colDef) {
    $colName = explode(' ', $colDef)[0];
    $chk = $conn->query("SHOW COLUMNS FROM course_sections LIKE '$colName'");
    if ($chk && $chk->num_rows == 0) {
        $conn->query("ALTER TABLE course_sections ADD COLUMN $colDef");
    }
}

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $subject_id    = intval($_POST['subject_id'] ?? 0);
        $teacher_id    = intval($_POST['teacher_id'] ?? 0);
        $semester_id   = intval($_POST['semester_id'] ?? 0);
        $section_code  = trim($_POST['section_code'] ?? '');
        $schedule_text = trim($_POST['schedule_text'] ?? '');
        $room          = trim($_POST['room'] ?? '');
        $max_students  = intval($_POST['max_students'] ?? 40);
        $tuition_fee   = floatval($_POST['tuition_fee'] ?? 1350000);
        $status        = trim($_POST['status'] ?? 'open');
        $start_time    = trim($_POST['start_time'] ?? '') ?: null;
        $end_time      = trim($_POST['end_time'] ?? '') ?: null;
        $start_date    = trim($_POST['start_date'] ?? '') ?: null;
        $end_date      = trim($_POST['end_date'] ?? '') ?: null;
        $sessions_pw   = intval($_POST['sessions_per_week'] ?? 2);
        $study_days    = trim($_POST['study_days'] ?? '') ?: null;
        $session_type  = trim($_POST['session_type'] ?? '') ?: null;
        $day_sessions  = trim($_POST['day_sessions'] ?? '') ?: null;
        $class_id      = intval($_POST['class_id'] ?? 0) ?: null;
        if ($subject_id && $semester_id && $section_code) {
            $stmt = $conn->prepare("INSERT INTO course_sections (subject_id,teacher_id,semester_id,section_code,schedule_text,room,max_students,current_students,tuition_fee,status,start_time,end_time,start_date,end_date,sessions_per_week,study_days,session_type,day_sessions,class_id) VALUES (?,?,?,?,?,?,?,0,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('iiisssidsssssisssi', $subject_id,$teacher_id,$semester_id,$section_code,$schedule_text,$room,$max_students,$tuition_fee,$status,$start_time,$end_time,$start_date,$end_date,$sessions_pw,$study_days,$session_type,$day_sessions,$class_id);
            $stmt->execute() ? $success = 'Thêm lớp học phần thành công!' : $error = 'Lỗi: '.$conn->error;
            $stmt->close();
        } else { $error = 'Vui lòng điền đầy đủ thông tin.'; }
    }
    if ($action === 'edit') {
        $id            = intval($_POST['id'] ?? 0);
        $subject_id    = intval($_POST['subject_id'] ?? 0);
        $teacher_id    = intval($_POST['teacher_id'] ?? 0);
        $semester_id   = intval($_POST['semester_id'] ?? 0);
        $section_code  = trim($_POST['section_code'] ?? '');
        $schedule_text = trim($_POST['schedule_text'] ?? '');
        $room          = trim($_POST['room'] ?? '');
        $max_students  = intval($_POST['max_students'] ?? 40);
        $tuition_fee   = floatval($_POST['tuition_fee'] ?? 1350000);
        $status        = trim($_POST['status'] ?? 'open');
        $start_time    = trim($_POST['start_time'] ?? '') ?: null;
        $end_time      = trim($_POST['end_time'] ?? '') ?: null;
        $start_date    = trim($_POST['start_date'] ?? '') ?: null;
        $end_date      = trim($_POST['end_date'] ?? '') ?: null;
        $sessions_pw   = intval($_POST['sessions_per_week'] ?? 2);
        $study_days    = trim($_POST['study_days'] ?? '') ?: null;
        $session_type  = trim($_POST['edit_session_type'] ?? '') ?: null;
        $day_sessions  = trim($_POST['day_sessions'] ?? '') ?: null;
        $class_id      = intval($_POST['class_id'] ?? 0) ?: null;
        if ($id) {
            $stmt = $conn->prepare("UPDATE course_sections SET subject_id=?,teacher_id=?,semester_id=?,section_code=?,schedule_text=?,room=?,max_students=?,tuition_fee=?,status=?,start_time=?,end_time=?,start_date=?,end_date=?,sessions_per_week=?,study_days=?,session_type=?,day_sessions=?,class_id=? WHERE id=?");
            $stmt->bind_param('iiisssidsssssisssii', $subject_id,$teacher_id,$semester_id,$section_code,$schedule_text,$room,$max_students,$tuition_fee,$status,$start_time,$end_time,$start_date,$end_date,$sessions_pw,$study_days,$session_type,$day_sessions,$class_id,$id);
            $stmt->execute() ? $success = 'Cập nhật thành công!' : $error = 'Lỗi: '.$conn->error;
            $stmt->close();
        }
    }
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM course_sections WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute() ? $success = 'Xóa thành công!' : $error = 'Lỗi: '.$conn->error;
            $stmt->close();
        }
    }

    // PRG: redirect sau POST de tranh F5 gui lai form
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

$perPage = 10;
$page    = max(1, intval($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;
$total   = $conn->query("SELECT COUNT(*) as c FROM course_sections")->fetch_assoc()['c'];
$totalPages = ceil($total / $perPage);

$stmt = $conn->prepare("
    SELECT cs.*, sub.subject_name, sub.subject_code,
           sm.semester_name, sm.school_year,
           u.full_name as teacher_name
    FROM course_sections cs
    LEFT JOIN subjects sub ON cs.subject_id = sub.id
    LEFT JOIN semesters sm ON cs.semester_id = sm.id
    LEFT JOIN teachers t ON cs.teacher_id = t.id
    LEFT JOIN users u ON t.user_id = u.id
    ORDER BY cs.id DESC LIMIT ? OFFSET ?
");
$stmt->bind_param('ii', $perPage, $offset);
$stmt->execute();
$sections  = $stmt->get_result();
$stmt->close();

$subjects  = $conn->query("SELECT * FROM subjects ORDER BY subject_name");
$teachers  = $conn->query("SELECT t.*, u.full_name FROM teachers t LEFT JOIN users u ON t.user_id=u.id ORDER BY u.full_name");
$semesters = $conn->query("SELECT * FROM semesters ORDER BY created_at DESC");
$classes   = $conn->query("SELECT c.*, m.major_name FROM classes c LEFT JOIN majors m ON c.major_id=m.id ORDER BY c.class_name");

// Danh sách phòng học — nhóm theo tòa, kèm trạng thái đang dùng
$roomsCheck = $conn->query("SHOW TABLES LIKE 'rooms'");
$roomsByBuilding = [];
if ($roomsCheck && $roomsCheck->num_rows > 0) {
    // Lấy phòng đang được dùng trong học kỳ hiện tại
    $usedRooms = [];
    $usedResult = $conn->query("SELECT DISTINCT room FROM course_sections WHERE room != '' AND room IS NOT NULL");
    if ($usedResult) while ($r = $usedResult->fetch_assoc()) $usedRooms[$r['room']] = true;

    $roomsResult = $conn->query("SELECT room_code, room_name, building, capacity, room_type FROM rooms WHERE status='active' ORDER BY building, room_code");
    if ($roomsResult) {
        while ($r = $roomsResult->fetch_assoc()) {
            $r['in_use'] = isset($usedRooms[$r['room_code']]);
            $roomsByBuilding[$r['building']][] = $r;
        }
    }
}
// Fallback nếu bảng rooms chưa có
if (empty($roomsByBuilding)) {
    $usedRooms = [];
    $usedResult = $conn->query("SELECT DISTINCT room FROM course_sections WHERE room != '' AND room IS NOT NULL");
    if ($usedResult) while ($r = $usedResult->fetch_assoc()) $usedRooms[$r['room']] = true;
    $fallback = [];
    foreach (['A1','A2','A3','A4','B1','B2','B3','B4'] as $b) {
        for ($i=1;$i<=6;$i++) {
            $code = "$b.0$i";
            $fallback["Dãy $b"][] = ['room_code'=>$code,'room_name'=>"Phòng $code",'capacity'=>45,'room_type'=>'lecture','in_use'=>isset($usedRooms[$code])];
        }
    }
    foreach (['C1','C2','C3','C4'] as $b) {
        for ($i=1;$i<=3;$i++) {
            $code = "$b.0$i";
            $fallback["Dãy $b (Máy tính)"][] = ['room_code'=>$code,'room_name'=>"Phòng máy $code",'capacity'=>40,'room_type'=>'lab','in_use'=>isset($usedRooms[$code])];
        }
    }
    foreach (['HT.A'=>['Hội trường A',300],'HT.B'=>['Hội trường B',300],'GD.01'=>['Giảng đường 01',120],'GD.02'=>['Giảng đường 02',120]] as $code=>$info) {
        $fallback['Hội trường / Giảng đường'][] = ['room_code'=>$code,'room_name'=>$info[0],'capacity'=>$info[1],'room_type'=>'exam_hall','in_use'=>isset($usedRooms[$code])];
    }
    $roomsByBuilding = $fallback;
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
            <span class="admin-topbar-title">Quản lý Lớp học phần</span>
        </div>
        <span class="text-muted small"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
    </div>
    <div class="admin-content">
        <?php $flash = getFlash(); if ($flash): ?><div class="alert alert-<?php echo $flash['type']; ?> auto-dismiss alert-dismissible fade show"><i class="bi bi-<?php echo $flash['type']=='success'?'check-circle-fill':'exclamation-circle-fill'; ?> me-2"></i><?php echo htmlspecialchars($flash['message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-grid-3x3-gap-fill me-2"></i>Danh sách Lớp học phần (<?php echo $total; ?>)</span>
                <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i>Thêm mới</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr>
                            <th>#</th><th>Mã lớp</th><th>Môn học</th><th>Giảng viên</th>
                            <th>Học kỳ</th><th>Lịch học</th>
                            <th>Giờ học</th>
                            <th>Ngày học</th>
                            <th>Phòng</th><th>Sĩ số</th><th>Học phí</th><th>TT</th><th>Thao tác</th>
                        </tr></thead>
                        <tbody>
                            <?php if ($sections && $sections->num_rows > 0): $idx=$offset+1; while ($s = $sections->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $idx++; ?></td>
                                <td class="fw-bold text-navy small"><?php echo htmlspecialchars($s['section_code']); ?></td>
                                <td>
                                    <div class="fw-bold small"><?php echo htmlspecialchars($s['subject_name']); ?></div>
                                    <div class="text-muted" style="font-size:11px"><?php echo htmlspecialchars($s['subject_code']); ?></div>
                                </td>
                                <td class="small"><?php echo htmlspecialchars($s['teacher_name'] ?? '--'); ?></td>
                                <td class="small text-muted"><?php echo htmlspecialchars($s['semester_name'].' '.$s['school_year']); ?></td>
                                <?php
                                // Parse day_sessions: "2:sang,4:chieu"
                                $SESSION_LABEL = ['sang'=>'Sáng','chieu'=>'Chiều','toi'=>'Tối'];
                                $SESSION_COLOR = ['sang'=>'#f0a500','chieu'=>'#1976d2','toi'=>'#6f42c1'];
                                $SESSION_TIME  = ['sang'=>'7:00–11:30','chieu'=>'12:30–17:00','toi'=>'17:30–22:00'];
                                $DAY_LABEL     = [2=>'T2',3=>'T3',4=>'T4',5=>'T5',6=>'T6',7=>'T7',8=>'CN'];
                                $dsRaw = $s['day_sessions'] ?? '';
                                $dsParsed = [];
                                if ($dsRaw) {
                                    foreach (explode(',', $dsRaw) as $part) {
                                        [$d, $sess] = array_pad(explode(':', $part), 2, '');
                                        if ($d && $sess) $dsParsed[(int)$d] = $sess;
                                    }
                                }
                                ?>
                                <!-- Cột Lịch học -->
                                <td class="small">
                                    <?php if (!empty($dsParsed)): ?>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php foreach ($dsParsed as $d => $sess): ?>
                                        <span class="badge" style="background:<?php echo $SESSION_COLOR[$sess] ?? '#666'; ?>; font-size:0.72rem;">
                                            <?php echo $DAY_LABEL[$d] ?? 'N'.$d; ?>
                                        </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php else: ?><span class="text-muted">--</span><?php endif; ?>
                                </td>
                                <!-- Cột Giờ học -->
                                <td class="small">
                                    <?php if (!empty($dsParsed)):
                                        // Nhóm các thứ cùng buổi
                                        $grouped = [];
                                        foreach ($dsParsed as $d => $sess) $grouped[$sess][] = $DAY_LABEL[$d] ?? 'N'.$d;
                                        foreach ($grouped as $sess => $dayList):
                                    ?>
                                    <div class="d-flex align-items-center gap-1 mb-1">
                                        <span class="badge" style="background:<?php echo $SESSION_COLOR[$sess] ?? '#666'; ?>; font-size:0.7rem;">
                                            <?php echo $SESSION_LABEL[$sess] ?? $sess; ?>
                                        </span>
                                        <span class="text-muted" style="font-size:0.7rem;"><?php echo $SESSION_TIME[$sess] ?? ''; ?></span>
                                    </div>
                                    <?php endforeach; else: ?><span class="text-muted">--</span><?php endif; ?>
                                </td>                                <td class="small">
                                    <?php if (!empty($s['start_date'])): ?>
                                    <div><i class="bi bi-play-fill text-success" style="font-size:0.7rem;"></i> <?php echo date('d/m/Y', strtotime($s['start_date'])); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($s['end_date'])): ?>
                                    <div><i class="bi bi-stop-fill text-danger" style="font-size:0.7rem;"></i> <?php echo date('d/m/Y', strtotime($s['end_date'])); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($s['start_date']) && !empty($s['end_date'])): ?>
                                    <?php
                                        $weeks = ceil((strtotime($s['end_date']) - strtotime($s['start_date'])) / (7 * 86400));
                                        $totalSessions = $weeks * ($s['sessions_per_week'] ?? 2);
                                    ?>
                                    <div class="text-muted" style="font-size:0.7rem;"><?php echo $weeks; ?> tuần · <?php echo $totalSessions; ?> buổi</div>
                                    <?php endif; ?>
                                    <?php if (empty($s['start_date']) && empty($s['end_date'])): ?><span class="text-muted">--</span><?php endif; ?>
                                </td>
                                <td class="small"><?php echo htmlspecialchars($s['room']); ?></td>
                                <td><span class="badge bg-<?php echo $s['current_students']>=$s['max_students']?'danger':'success'; ?>"><?php echo $s['current_students'].'/'.$s['max_students']; ?></span></td>
                                <td class="small text-success"><?php echo number_format($s['tuition_fee'],0,',','.'); ?>đ</td>
                                <td><span class="badge bg-<?php echo $s['status']=='open'?'success':($s['status']=='full'?'warning':'secondary'); ?>"><?php echo $s['status']=='open'?'Mở':($s['status']=='full'?'Đầy':'Đóng'); ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#editModal"
                                        data-id="<?php echo $s['id']; ?>"
                                        data-subject="<?php echo $s['subject_id']; ?>"
                                        data-teacher="<?php echo $s['teacher_id']; ?>"
                                        data-semester="<?php echo $s['semester_id']; ?>"
                                        data-code="<?php echo htmlspecialchars($s['section_code']); ?>"
                                        data-schedule="<?php echo htmlspecialchars($s['schedule_text']); ?>"
                                        data-room="<?php echo htmlspecialchars($s['room']); ?>"
                                        data-max="<?php echo $s['max_students']; ?>"
                                        data-tuition="<?php echo $s['tuition_fee']; ?>"
                                        data-status="<?php echo $s['status']; ?>"
                                        data-start="<?php echo !empty($s['start_time']) ? substr($s['start_time'],0,5) : ''; ?>"
                                        data-end="<?php echo !empty($s['end_time']) ? substr($s['end_time'],0,5) : ''; ?>"
                                        data-startdate="<?php echo $s['start_date'] ?? ''; ?>"
                                        data-enddate="<?php echo $s['end_date'] ?? ''; ?>"
                                        data-spw="<?php echo $s['sessions_per_week'] ?? 2; ?>"
                                        data-studydays="<?php echo htmlspecialchars($s['study_days'] ?? ''); ?>"
                                        data-sessiontype="<?php echo htmlspecialchars($s['session_type'] ?? ''); ?>"
                                        data-daysessions="<?php echo htmlspecialchars($s['day_sessions'] ?? ''); ?>"
                                        data-classid="<?php echo $s['class_id'] ?? ''; ?>">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Xóa lớp học phần này?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash-fill"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="12" class="text-center text-muted py-4">Chưa có dữ liệu</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($totalPages > 1): ?>
                <nav class="p-3"><ul class="pagination justify-content-center mb-0">
                    <?php if ($page > 1): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?>"><i class="bi bi-chevron-left"></i></a></li><?php endif; ?>
                    <?php for ($p=max(1,$page-2);$p<=min($totalPages,$page+2);$p++): ?>
                    <li class="page-item <?php echo $p==$page?'active':''; ?>"><a class="page-link" href="?page=<?php echo $p; ?>"><?php echo $p; ?></a></li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?>"><i class="bi bi-chevron-right"></i></a></li><?php endif; ?>
                </ul></nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một</div>
</div>

<!-- ===== MODAL THÊM ===== -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-grid-3x3-gap me-2"></i>Thêm Lớp học phần</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Mã lớp HP <span class="text-danger">*</span></label>
                            <input type="text" name="section_code" class="form-control" required placeholder="VD: CNTT101_01">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Môn học <span class="text-danger">*</span></label>
                            <select name="subject_id" class="form-select" required>
                                <option value="">-- Chọn môn học --</option>
                                <?php if ($subjects): while ($sub = $subjects->fetch_assoc()): ?>
                                <option value="<?php echo $sub['id']; ?>"><?php echo htmlspecialchars($sub['subject_name']); ?> (<?php echo htmlspecialchars($sub['subject_code']); ?>)</option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Giảng viên</label>
                            <select name="teacher_id" class="form-select">
                                <option value="">-- Chọn giảng viên --</option>
                                <?php if ($teachers): while ($t = $teachers->fetch_assoc()): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['full_name']); ?> (<?php echo htmlspecialchars($t['teacher_code']); ?>)</option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Học kỳ <span class="text-danger">*</span></label>
                            <select name="semester_id" class="form-select" required>
                                <option value="">-- Chọn học kỳ --</option>
                                <?php if ($semesters): while ($sem = $semesters->fetch_assoc()): ?>
                                <option value="<?php echo $sem['id']; ?>"><?php echo htmlspecialchars($sem['semester_name'].' '.$sem['school_year']); ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Phòng học</label>
                            <select name="room" id="addRoom" class="form-select">
                                <option value="">-- Chọn phòng --</option>
                                <?php foreach ($roomsByBuilding as $building => $rooms): ?>
                                <optgroup label="<?php echo htmlspecialchars($building); ?>">
                                    <?php foreach ($rooms as $r):
                                        $typeIcon = ['lecture'=>'📚','lab'=>'💻','exam_hall'=>'🏛️','seminar'=>'🗣️'][$r['room_type']] ?? '🚪';
                                        $inUseLabel = $r['in_use'] ? ' 🔴 Đang dùng' : ' 🟢 Trống';
                                    ?>
                                    <option value="<?php echo htmlspecialchars($r['room_code']); ?>">
                                        <?php echo htmlspecialchars($typeIcon . ' ' . $r['room_code'] . ' (' . $r['capacity'] . ' chỗ)' . $inUseLabel); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sĩ số tối đa</label>
                            <input type="number" name="max_students" class="form-control" value="60" min="1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Lớp học <span class="text-muted small">(tùy chọn)</span></label>
                            <select name="class_id" class="form-select">
                                <option value="">-- Không gán lớp cụ thể --</option>
                                <?php if ($classes): while ($cl = $classes->fetch_assoc()): ?>
                                <option value="<?php echo $cl['id']; ?>">
                                    <?php echo htmlspecialchars($cl['class_name'] . ($cl['major_name'] ? ' — ' . $cl['major_name'] : '')); ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>

                        <!-- Thời gian học -->
                        <div class="col-12"><hr class="my-1">
                            <div class="fw-semibold text-navy small mb-2">
                                <i class="bi bi-clock-fill me-1"></i>Thời gian học
                                <span class="text-muted fw-normal">(dùng để sắp xếp thời khóa biểu)</span>
                            </div>
                        </div>

                        <!-- BƯỚC 1+2: Chọn thứ + buổi cho từng thứ -->
                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                <span class="badge bg-navy me-1">1</span>
                                Học vào các thứ — chọn thứ rồi chọn buổi cho từng thứ
                            </label>
                            <div class="d-flex flex-wrap gap-3" id="addDaySessionWrap">
                                <?php foreach ([2=>'Thứ 2',3=>'Thứ 3',4=>'Thứ 4',5=>'Thứ 5',6=>'Thứ 6',7=>'Thứ 7',8=>'CN'] as $d=>$dn): ?>
                                <div class="day-session-item" data-day="<?php echo $d; ?>" data-form="add">
                                    <!-- Nút chọn thứ -->
                                    <div class="day-btn mb-1"
                                         data-day="<?php echo $d; ?>" data-form="add"
                                         onclick="toggleDay(this)"
                                         style="cursor:pointer;padding:8px 16px;border-radius:8px;border:2px solid #dee2e6;font-size:0.85rem;font-weight:600;color:#666;background:#f8f9fa;user-select:none;transition:all .15s;text-align:center;">
                                        <?php echo $dn; ?>
                                    </div>
                                    <!-- Dropdown buổi — ẩn khi chưa chọn thứ -->
                                    <select class="form-select form-select-sm session-select"
                                            data-day="<?php echo $d; ?>" data-form="add"
                                            onchange="onDaySessionChange('add')"
                                            style="display:none;font-size:0.78rem;min-width:100px;">
                                        <option value="">-- Buổi --</option>
                                        <option value="sang">☀️ Sáng (7:00–11:30)</option>
                                        <option value="chieu">🌤️ Chiều (12:30–17:00)</option>
                                        <option value="toi">🌙 Tối (17:30–22:00)</option>
                                    </select>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="study_days" id="addStudyDays" value="">
                            <input type="hidden" name="day_sessions" id="addDaySessions" value="">
                            <div class="text-muted small mt-2" id="addDaysSummary"></div>
                        </div>

                        <!-- BƯỚC 3: Ngày bắt đầu + số buổi -->
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">
                                <span class="badge bg-navy me-1">3</span>
                                Ngày bắt đầu học
                            </label>
                            <input type="date" name="start_date" id="addStartDate" class="form-control" onchange="calcEndDate('add')">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Tổng số buổi cần học</label>
                            <input type="number" id="addTotalNeeded" class="form-control" min="1" value="30"
                                   placeholder="VD: 30" oninput="calcEndDate('add')">
                            <div class="text-muted small mt-1">Mỗi buổi 5 tiết</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Ngày kết thúc (tự tính)</label>
                            <input type="date" name="end_date" id="addEndDate" class="form-control" style="background:#f0f4f8;">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small">Kết quả</label>
                            <div class="p-2 rounded bg-light border" id="addCalcResult" style="font-size:0.8rem;min-height:38px;color:#555;">--</div>
                        </div>

                        <!-- Ngày lễ bù -->
                        <div class="col-12">
                            <label class="form-label text-muted small">
                                <i class="bi bi-calendar-x me-1 text-danger"></i>
                                Ngày lễ / nghỉ bù (nhập để hệ thống tự bỏ qua và bù sang tuần sau)
                            </label>
                            <div class="d-flex gap-2 flex-wrap align-items-center" id="addHolidayList">
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="addHoliday('add')">
                                    <i class="bi bi-plus me-1"></i>Thêm ngày lễ
                                </button>
                            </div>
                            <input type="hidden" name="holiday_dates" id="addHolidayDates" value="">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Học phí (đ)</label>
                            <input type="number" name="tuition_fee" class="form-control" value="1350000" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Trạng thái</label>
                            <select name="status" class="form-select">
                                <option value="open">Mở đăng ký</option>
                                <option value="closed">Đóng</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-gold"><i class="bi bi-save me-1"></i>Lưu</button>
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
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Chỉnh sửa Lớp học phần</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Mã lớp HP</label>
                            <input type="text" name="section_code" id="editCode" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Môn học</label>
                            <select name="subject_id" id="editSubject" class="form-select">
                                <option value="">-- Chọn môn học --</option>
                                <?php $subjects2 = $conn->query("SELECT * FROM subjects ORDER BY subject_name"); while ($sub = $subjects2->fetch_assoc()): ?>
                                <option value="<?php echo $sub['id']; ?>"><?php echo htmlspecialchars($sub['subject_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Giảng viên</label>
                            <select name="teacher_id" id="editTeacher" class="form-select">
                                <option value="">-- Chọn giảng viên --</option>
                                <?php $teachers2 = $conn->query("SELECT t.*, u.full_name FROM teachers t LEFT JOIN users u ON t.user_id=u.id ORDER BY u.full_name"); while ($t = $teachers2->fetch_assoc()): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['full_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Học kỳ</label>
                            <select name="semester_id" id="editSemester" class="form-select">
                                <option value="">-- Chọn học kỳ --</option>
                                <?php $semesters2 = $conn->query("SELECT * FROM semesters ORDER BY created_at DESC"); while ($sem = $semesters2->fetch_assoc()): ?>
                                <option value="<?php echo $sem['id']; ?>"><?php echo htmlspecialchars($sem['semester_name'].' '.$sem['school_year']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Phòng học</label>
                            <select name="room" id="editRoom" class="form-select">
                                <option value="">-- Chọn phòng --</option>
                                <?php foreach ($roomsByBuilding as $building => $rooms): ?>
                                <optgroup label="<?php echo htmlspecialchars($building); ?>">
                                    <?php foreach ($rooms as $r):
                                        $typeIcon = ['lecture'=>'📚','lab'=>'💻','exam_hall'=>'🏛️','seminar'=>'🗣️'][$r['room_type']] ?? '🚪';
                                        $inUseLabel = $r['in_use'] ? ' 🔴 Đang dùng' : ' 🟢 Trống';
                                    ?>
                                    <option value="<?php echo htmlspecialchars($r['room_code']); ?>">
                                        <?php echo htmlspecialchars($typeIcon . ' ' . $r['room_code'] . ' (' . $r['capacity'] . ' chỗ)' . $inUseLabel); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sĩ số tối đa</label>
                            <input type="number" name="max_students" id="editMax" class="form-control" min="1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Lớp học <span class="text-muted small">(tùy chọn)</span></label>
                            <select name="class_id" id="editClassId" class="form-select">
                                <option value="">-- Không gán lớp cụ thể --</option>
                                <?php
                                $classes2 = $conn->query("SELECT c.*, m.major_name FROM classes c LEFT JOIN majors m ON c.major_id=m.id ORDER BY c.class_name");
                                if ($classes2): while ($cl = $classes2->fetch_assoc()): ?>
                                <option value="<?php echo $cl['id']; ?>">
                                    <?php echo htmlspecialchars($cl['class_name'] . ($cl['major_name'] ? ' — ' . $cl['major_name'] : '')); ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>

                        <!-- Thời gian học -->
                        <div class="col-12"><hr class="my-1">
                            <div class="fw-semibold text-navy small mb-2">
                                <i class="bi bi-clock-fill me-1"></i>Thời gian học
                            </div>
                        </div>
                        <!-- Ngày bắt đầu + số buổi -->
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">
                                <span class="badge bg-navy me-1">2</span>
                                Ngày bắt đầu học
                            </label>
                            <input type="date" name="start_date" id="editStartDate" class="form-control" onchange="calcEndDate('edit')">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                <span class="badge bg-navy me-1">1</span>
                                Học vào các thứ — chọn thứ rồi chọn buổi cho từng thứ
                            </label>
                            <div class="d-flex flex-wrap gap-3" id="editDaySessionWrap">
                                <?php foreach ([2=>'Thứ 2',3=>'Thứ 3',4=>'Thứ 4',5=>'Thứ 5',6=>'Thứ 6',7=>'Thứ 7',8=>'CN'] as $d=>$dn): ?>
                                <div class="day-session-item" data-day="<?php echo $d; ?>" data-form="edit">
                                    <div class="day-btn mb-1"
                                         data-day="<?php echo $d; ?>" data-form="edit"
                                         onclick="toggleDay(this)"
                                         style="cursor:pointer;padding:8px 16px;border-radius:8px;border:2px solid #dee2e6;font-size:0.85rem;font-weight:600;color:#666;background:#f8f9fa;user-select:none;transition:all .15s;text-align:center;">
                                        <?php echo $dn; ?>
                                    </div>
                                    <select class="form-select form-select-sm session-select"
                                            data-day="<?php echo $d; ?>" data-form="edit"
                                            onchange="onDaySessionChange('edit')"
                                            style="display:none;font-size:0.78rem;min-width:100px;">
                                        <option value="">-- Buổi --</option>
                                        <option value="sang">☀️ Sáng (7:00–11:30)</option>
                                        <option value="chieu">🌤️ Chiều (12:30–17:00)</option>
                                        <option value="toi">🌙 Tối (17:30–22:00)</option>
                                    </select>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="study_days" id="editStudyDays" value="">
                            <input type="hidden" name="day_sessions" id="editDaySessions" value="">
                            <div class="text-muted small mt-2" id="editDaysSummary"></div>
                        </div>

                        <!-- Ngày bắt đầu + số buổi -->
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">
                                <span class="badge bg-navy me-1">3</span>
                                Ngày bắt đầu học
                            </label>
                            <input type="date" name="start_date" id="editStartDate" class="form-control" onchange="calcEndDate('edit')">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Tổng số buổi cần học</label>
                            <input type="number" id="editTotalNeeded" class="form-control" min="1" value="30"
                                   placeholder="VD: 30" oninput="calcEndDate('edit')">
                            <div class="text-muted small mt-1">Mỗi buổi 5 tiết</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Ngày kết thúc (tự tính)</label>
                            <input type="date" name="end_date" id="editEndDate" class="form-control" style="background:#f0f4f8;">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small">Kết quả</label>
                            <div class="p-2 rounded bg-light border" id="editCalcResult" style="font-size:0.8rem;min-height:38px;color:#555;">--</div>
                        </div>

                        <!-- Ngày lễ bù -->
                        <div class="col-12">
                            <label class="form-label text-muted small">
                                <i class="bi bi-calendar-x me-1 text-danger"></i>
                                Ngày lễ / nghỉ bù (hệ thống tự bỏ qua và bù sang tuần sau)
                            </label>
                            <div class="d-flex gap-2 flex-wrap align-items-center" id="editHolidayList">
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="addHoliday('edit')">
                                    <i class="bi bi-plus me-1"></i>Thêm ngày lễ
                                </button>
                            </div>
                            <input type="hidden" name="holiday_dates" id="editHolidayDates" value="">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Học phí</label>
                            <input type="number" name="tuition_fee" id="editTuition" class="form-control" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Trạng thái</label>
                            <select name="status" id="editStatus" class="form-select">
                                <option value="open">Mở đăng ký</option>
                                <option value="full">Đầy</option>
                                <option value="closed">Đóng</option>
                            </select>
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
// ===== Điền dữ liệu vào Edit Modal =====
document.getElementById('editModal').addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('editId').value         = b.dataset.id;
    document.getElementById('editSubject').value    = b.dataset.subject;
    document.getElementById('editTeacher').value    = b.dataset.teacher;
    document.getElementById('editSemester').value   = b.dataset.semester;
    document.getElementById('editCode').value       = b.dataset.code;
    document.getElementById('editRoom').value       = b.dataset.room;
    document.getElementById('editMax').value        = b.dataset.max;
    document.getElementById('editTuition').value    = b.dataset.tuition;
    document.getElementById('editStatus').value     = b.dataset.status;
    document.getElementById('editStartDate').value  = b.dataset.startdate || '';
    document.getElementById('editEndDate').value    = b.dataset.enddate   || '';
    document.getElementById('editClassId').value    = b.dataset.classid   || '';

    // Khôi phục thứ đã chọn
    const days = (b.dataset.studydays || '').split(',').map(Number).filter(Boolean);
    // day_sessions: "2:sang,4:chieu"
    const dsMap = {};
    (b.dataset.daysessions || '').split(',').forEach(x => {
        const [d, s] = x.split(':');
        if (d && s) dsMap[d] = s;
    });
    document.querySelectorAll('#editModal .day-btn').forEach(btn => {
        const d = parseInt(btn.dataset.day);
        if (days.includes(d)) {
            activateDay(btn);
            const sel = btn.closest('.day-session-item')?.querySelector('.session-select');
            if (sel && dsMap[d]) sel.value = dsMap[d];
        } else {
            deactivateDay(btn);
        }
    });
    document.getElementById('editStudyDays').value   = b.dataset.studydays   || '';
    document.getElementById('editDaySessions').value = b.dataset.daysessions || '';
    updateDaySessionData('edit');

    // Khôi phục ngày lễ
    const holidays = (b.dataset.holidays || '').split(',').filter(Boolean);
    document.getElementById('editHolidayDates').value = b.dataset.holidays || '';
    renderHolidays('edit', holidays);
});

// ===== Toggle chọn thứ =====
function activateDay(btn) {
    btn.style.background  = 'var(--navy)';
    btn.style.color       = '#fff';
    btn.style.borderColor = 'var(--navy)';
    btn.dataset.selected  = '1';
    // Hiện dropdown buổi
    const sel = btn.closest('.day-session-item')?.querySelector('.session-select');
    if (sel) sel.style.display = '';
}
function deactivateDay(btn) {
    btn.style.background  = '#f8f9fa';
    btn.style.color       = '#666';
    btn.style.borderColor = '#dee2e6';
    btn.dataset.selected  = '0';
    // Ẩn và reset dropdown buổi
    const sel = btn.closest('.day-session-item')?.querySelector('.session-select');
    if (sel) { sel.style.display = 'none'; sel.value = ''; }
}
function toggleDay(btn) {
    if (btn.dataset.selected === '1') deactivateDay(btn);
    else activateDay(btn);
    updateDaySessionData(btn.dataset.form);
    calcEndDate(btn.dataset.form);
}

// Khi thay đổi buổi của 1 thứ
function onDaySessionChange(form) {
    updateDaySessionData(form);
    calcEndDate(form);
}

// Cập nhật hidden inputs + summary
function updateDaySessionData(form) {
    const modal = form === 'add' ? document.getElementById('addModal') : document.getElementById('editModal');
    const items = modal.querySelectorAll('.day-btn[data-selected="1"]');
    const SESSION_LABEL = { sang: '☀️ Sáng', chieu: '🌤️ Chiều', toi: '🌙 Tối' };
    const DAY_LABEL = {2:'Thứ 2',3:'Thứ 3',4:'Thứ 4',5:'Thứ 5',6:'Thứ 6',7:'Thứ 7',8:'CN'};

    const days = [], daySessions = [], summaryParts = [];
    items.forEach(btn => {
        const d   = btn.dataset.day;
        const sel = btn.closest('.day-session-item')?.querySelector('.session-select');
        const s   = sel?.value || '';
        days.push(d);
        daySessions.push(d + ':' + s); // VD: "2:sang"
        summaryParts.push(`<strong>${DAY_LABEL[d]}</strong> ${s ? SESSION_LABEL[s] : '<span class="text-danger">chưa chọn buổi</span>'}`);
    });

    document.getElementById(form + 'StudyDays').value   = days.join(',');
    document.getElementById(form + 'DaySessions').value = daySessions.join(',');

    const sumEl = document.getElementById(form + 'DaysSummary');
    if (sumEl) sumEl.innerHTML = summaryParts.length
        ? '<i class="bi bi-check2-circle text-success me-1"></i>' + summaryParts.join(' &nbsp;|&nbsp; ')
        : '';
}

// ===== Chọn buổi (legacy — không dùng nữa) =====
function onSessionChange(form) {}

// ===== Ngày lễ =====
function getHolidays(form) {
    return (document.getElementById(form + 'HolidayDates').value || '')
        .split(',').filter(Boolean);
}
function saveHolidays(form, arr) {
    document.getElementById(form + 'HolidayDates').value = arr.join(',');
}
function renderHolidays(form, arr) {
    const list = document.getElementById(form + 'HolidayList');
    // Xóa các tag cũ (giữ nút Thêm)
    [...list.querySelectorAll('.holiday-tag')].forEach(t => t.remove());
    arr.forEach(d => {
        if (!d) return;
        const tag = document.createElement('span');
        tag.className = 'holiday-tag badge bg-danger d-flex align-items-center gap-1';
        tag.style.cssText = 'font-size:0.8rem;padding:6px 10px;border-radius:6px;';
        tag.innerHTML = `<i class="bi bi-calendar-x"></i> ${d}
            <button type="button" onclick="removeHoliday('${form}','${d}')"
                style="background:none;border:none;color:#fff;padding:0;margin-left:4px;cursor:pointer;font-size:0.9rem;">×</button>`;
        list.insertBefore(tag, list.querySelector('button'));
    });
}
function addHoliday(form) {
    const d = prompt('Nhập ngày lễ (định dạng YYYY-MM-DD, VD: 2025-09-02):');
    if (!d || !/^\d{4}-\d{2}-\d{2}$/.test(d)) {
        if (d) alert('Định dạng không đúng. Vui lòng nhập YYYY-MM-DD');
        return;
    }
    const arr = getHolidays(form);
    if (!arr.includes(d)) { arr.push(d); saveHolidays(form, arr); renderHolidays(form, arr); }
    calcEndDate(form);
}
function removeHoliday(form, d) {
    const arr = getHolidays(form).filter(x => x !== d);
    saveHolidays(form, arr);
    renderHolidays(form, arr);
    calcEndDate(form);
}

// ===== Tính ngày kết thúc =====
// Mapping: thứ trong tuần (2=T2...7=T7, 8=CN) → JS getDay() (0=CN,1=T2...6=T7)
const DAY_MAP = {2:1, 3:2, 4:3, 5:4, 6:5, 7:6, 8:0};

function calcEndDate(form) {
    const startVal   = document.getElementById(form + 'StartDate').value;
    const totalNeed  = parseInt(document.getElementById(form + 'TotalNeeded')?.value || 0);
    const daysStr    = document.getElementById(form + 'StudyDays').value;
    const holidays   = getHolidays(form);
    const resultEl   = document.getElementById(form + 'CalcResult');
    const endInput   = document.getElementById(form + 'EndDate');

    if (!startVal || !totalNeed || !daysStr) {
        if (resultEl) resultEl.innerHTML = '<span class="text-muted">Chọn thứ, nhập ngày bắt đầu và số buổi</span>';
        return;
    }

    const studyDays = daysStr.split(',').map(Number).filter(Boolean)
                             .map(d => DAY_MAP[d]).filter(d => d !== undefined);
    if (!studyDays.length) {
        if (resultEl) resultEl.innerHTML = '<span class="text-muted">Chọn ít nhất 1 thứ học</span>';
        return;
    }

    const holidaySet = new Set(holidays);
    let cur = new Date(startVal + 'T00:00:00');
    let count = 0;
    let skipped = 0;
    let lastDate = null;
    const MAX_ITER = 1000; // tránh vòng lặp vô hạn
    let iter = 0;

    while (count < totalNeed && iter < MAX_ITER) {
        iter++;
        const dow = cur.getDay(); // 0=CN,1=T2...6=T7
        if (studyDays.includes(dow)) {
            const ymd = cur.toISOString().slice(0, 10);
            if (holidaySet.has(ymd)) {
                skipped++; // bỏ qua ngày lễ, buổi này bù tuần sau (tự nhiên vì vòng lặp tiếp tục)
            } else {
                count++;
                lastDate = new Date(cur);
            }
        }
        cur.setDate(cur.getDate() + 1);
    }

    if (lastDate) {
        const endStr = lastDate.toISOString().slice(0, 10);
        endInput.value = endStr;

        const spw    = studyDays.length;
        const weeks  = Math.ceil(totalNeed / spw);
        const tiets  = totalNeed * 5;
        let html = `<strong style="color:var(--navy)">${totalNeed} buổi</strong>
            <span class="text-muted">(${tiets} tiết)</span><br>
            <span style="color:#28a745">${spw} buổi/tuần ≈ ${weeks} tuần</span>`;
        if (skipped > 0) {
            html += `<br><span style="color:#dc3545"><i class="bi bi-calendar-x"></i> Bỏ qua ${skipped} ngày lễ (đã bù)</span>`;
        }
        if (resultEl) resultEl.innerHTML = html;
    } else {
        if (resultEl) resultEl.innerHTML = '<span style="color:#dc3545">Không tính được</span>';
    }
}
</script>
</body>
</html>
