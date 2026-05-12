<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/AcademicPolicy.php';
requireRole('student');

$stmt = $conn->prepare("SELECT s.*, u.full_name FROM students s JOIN users u ON s.user_id=u.id WHERE s.user_id=?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    if (!verifyCSRFToken($_POST['_csrf_token'] ?? '')) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.';
    }
    $ss_id = intval($_POST['ss_id'] ?? 0);

    if (empty($error)) {
        $conn->begin_transaction();
        try {
            $getStmt = $conn->prepare(
                "SELECT ss.course_section_id, cs.semester_id
                 FROM student_subjects ss
                 JOIN course_sections cs ON ss.course_section_id = cs.id
                 WHERE ss.id = ? AND ss.student_id = ? AND ss.status = 'registered'
                 LIMIT 1"
            );
            $getStmt->bind_param('ii', $ss_id, $student['id']);
            $getStmt->execute();
            $ssRow = $getStmt->get_result()->fetch_assoc();
            $getStmt->close();

            if (!$ssRow) {
                throw new RuntimeException('Không tìm thấy học phần đang đăng ký để hủy.');
            }

            $stmtWindow = $conn->prepare(
                "SELECT id FROM semesters
                 WHERE id = ?
                   AND status = 'open'
                   AND register_start <= NOW()
                   AND register_end >= NOW()
                 LIMIT 1"
            );
            $stmtWindow->bind_param('i', $ssRow['semester_id']);
            $stmtWindow->execute();
            $canCancel = $stmtWindow->get_result()->num_rows > 0;
            $stmtWindow->close();
            if (!$canCancel) {
                throw new RuntimeException('Đã hết thời gian hủy đăng ký học phần.');
            }

            $updReg = $conn->prepare("UPDATE student_subjects SET status='cancelled' WHERE id=? AND student_id=? AND status='registered'");
            $updReg->bind_param('ii', $ss_id, $student['id']);
            $updReg->execute();
            if ($updReg->affected_rows <= 0) {
                throw new RuntimeException('Không thể hủy học phần này.');
            }
            $updReg->close();

            $sectionId = (int)$ssRow['course_section_id'];
            $updSection = $conn->prepare(
                "UPDATE course_sections
                 SET current_students = GREATEST(0, current_students - 1),
                     status = CASE WHEN status = 'full' THEN 'open' ELSE status END
                 WHERE id = ?"
            );
            $updSection->bind_param('i', $sectionId);
            $updSection->execute();
            $updSection->close();

            $conn->commit();
            $success = 'Hủy đăng ký thành công!';
        } catch (Throwable $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
    // ── PRG ──
    if (!empty($success) || !empty($error)) {
        $_SESSION['_flash'] = ['type' => !empty($success) ? 'success' : 'danger', 'message' => !empty($success) ? $success : $error];
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit();
    }
}

// Lấy danh sách học phần đã đăng ký
$stmt = $conn->prepare("
    SELECT ss.id as ss_id, ss.status as reg_status, ss.register_date,
           cs.section_code, cs.schedule_text, cs.schedule_data, cs.day_sessions,
           cs.start_date, cs.end_date, cs.room, cs.tuition_fee,
           s.subject_name, s.credits,
           sm.semester_name, sm.school_year,
           u.full_name as teacher_name
    FROM student_subjects ss
    JOIN course_sections cs ON ss.course_section_id = cs.id
    JOIN subjects s ON cs.subject_id = s.id
    JOIN semesters sm ON cs.semester_id = sm.id
    JOIN teachers t ON cs.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    WHERE ss.student_id = ?
    ORDER BY sm.school_year DESC, sm.semester_name, s.subject_name
");
$stmt->bind_param('i', $student['id']);
$stmt->execute();
$subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$regOpen = $conn->query("SELECT id FROM semesters WHERE status='open' AND register_start <= NOW() AND register_end >= NOW() LIMIT 1")->fetch_assoc();

// Seed lịch học cho các section chưa có day_sessions/schedule_data
$colCheck = $conn->query("SHOW COLUMNS FROM course_sections LIKE 'schedule_data'");
if ($colCheck->num_rows == 0) {
    $conn->query("ALTER TABLE course_sections ADD COLUMN schedule_data JSON NULL AFTER schedule_text");
}
// Cập nhật schedule_data cho tất cả section chưa có day_sessions và chưa có schedule_data
// dựa theo pattern mã section
$allSections = $conn->query("SELECT id, section_code FROM course_sections WHERE (day_sessions IS NULL OR day_sessions='') AND (schedule_data IS NULL OR schedule_data='')");
if ($allSections) {
    $defaultSchedules = [
        'NNA101' => '[{"day":2,"session":"toi","period_start":1},{"day":4,"session":"toi","period_start":1},{"day":6,"session":"toi","period_start":1}]',
        'CNTT101'=> '[{"day":2,"session":"sang","period_start":1},{"day":4,"session":"sang","period_start":1},{"day":6,"session":"sang","period_start":1}]',
        'CNTT102'=> '[{"day":3,"session":"chieu","period_start":1},{"day":5,"session":"chieu","period_start":1},{"day":7,"session":"chieu","period_start":1}]',
        'CNTT201'=> '[{"day":2,"session":"toi","period_start":1},{"day":4,"session":"toi","period_start":1},{"day":6,"session":"toi","period_start":1}]',
        'CNTT202'=> '[{"day":3,"session":"sang","period_start":1},{"day":5,"session":"sang","period_start":1},{"day":7,"session":"sang","period_start":1}]',
        'CNTT203'=> '[{"day":2,"session":"chieu","period_start":1},{"day":4,"session":"chieu","period_start":1},{"day":6,"session":"chieu","period_start":1}]',
        'KTPM101'=> '[{"day":3,"session":"toi","period_start":1},{"day":5,"session":"toi","period_start":1},{"day":7,"session":"toi","period_start":1}]',
        'KTPM201'=> '[{"day":8,"session":"sang","period_start":1},{"day":8,"session":"chieu","period_start":1}]',
        'QTKD101'=> '[{"day":2,"session":"sang","period_start":1},{"day":4,"session":"sang","period_start":1},{"day":6,"session":"sang","period_start":1}]',
        'KT101'  => '[{"day":3,"session":"chieu","period_start":1},{"day":5,"session":"chieu","period_start":1},{"day":7,"session":"chieu","period_start":1}]',
    ];
    while ($row = $allSections->fetch_assoc()) {
        foreach ($defaultSchedules as $prefix => $json) {
            if (str_starts_with($row['section_code'], $prefix)) {
                $upd = $conn->prepare("UPDATE course_sections SET schedule_data=? WHERE id=?");
                if ($upd) { $upd->bind_param('si', $json, $row['id']); $upd->execute(); $upd->close(); }
                break;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
    <title>Học phần của tôi - Sinh viên</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/university/assets/css/style.css">
</head>
<body>
<div class="student-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="student-main">
        <div class="student-topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary d-lg-none" onclick="document.querySelector('.student-sidebar').classList.toggle('show')"><i class="bi bi-list fs-5"></i></button>
                <span class="fw-bold text-navy">Học phần của tôi</span>
            </div>
            <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i></a>
        </div>
        <div class="student-content">
            <?php $flash = getFlash(); if ($flash): ?><div class="alert alert-<?php echo $flash['type']; ?> auto-dismiss alert-dismissible fade show"><i class="bi bi-<?php echo $flash['type']=='success'?'check-circle-fill':'exclamation-circle-fill'; ?> me-2"></i><?php echo htmlspecialchars($flash['message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

            <div class="card">
                <div class="card-header"><i class="bi bi-journal-check me-2"></i>Danh sách học phần đã đăng ký</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Môn học</th>
                                    <th>Mã HP</th>
                                    <th>Học kỳ</th>
                                    <th>Giảng viên</th>
                                    <th>Lịch học</th>
                                    <th>Phòng</th>
                                    <th>Học phí</th>
                                    <th>Trạng thái</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $statusMap = ['registered'=>['Đã đăng ký','primary'],'cancelled'=>['Đã hủy','secondary'],'completed'=>['Hoàn thành','success']];
                                $dayNames    = [2=>'T2',3=>'T3',4=>'T4',5=>'T5',6=>'T6',7=>'T7',8=>'CN'];
                                $sessionColors = ['sang'=>'#f57c00','chieu'=>'#1976d2','toi'=>'#7b1fa2'];
                                $sessionLabels = ['sang'=>'Sáng','chieu'=>'Chiều','toi'=>'Tối'];
                                $sessionTimes  = ['sang'=>'7:00–11:30','chieu'=>'12:30–17:00','toi'=>'17:30–22:00'];

                                function parseDaySessionsMy(string $ds): array {
                                    $r = [];
                                    foreach (explode(',', $ds) as $p) {
                                        $a = explode(':', trim($p));
                                        if (count($a)==2 && $a[0] && $a[1]) $r[(int)$a[0]] = $a[1];
                                    }
                                    return $r;
                                }

                                $totalCredits = 0; $totalFee = 0; $totalCount = 0;
                                if (!empty($subjects)): $idx=1; foreach ($subjects as $sub):
                                if ($sub['reg_status'] === 'registered') {
                                    $totalCredits += $sub['credits'];
                                    $totalFee     += $sub['tuition_fee'];
                                    $totalCount++;
                                }
                                $s = $statusMap[$sub['reg_status']] ?? [$sub['reg_status'],'secondary'];

                                // Parse lịch học: ưu tiên day_sessions → schedule_data → schedule_text
                                $dayMap = [];
                                if (!empty($sub['day_sessions'])) {
                                    $dayMap = parseDaySessionsMy($sub['day_sessions']);
                                } elseif (!empty($sub['schedule_data'])) {
                                    $slots = json_decode($sub['schedule_data'], true) ?: [];
                                    foreach ($slots as $sl) $dayMap[(int)$sl['day']] = $sl['session'];
                                }
                                ?>
                                <tr class="<?php echo $sub['reg_status']=='cancelled'?'table-secondary text-muted':''; ?>">
                                    <td><?php echo $idx++; ?></td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($sub['subject_name']); ?></div>
                                        <span class="badge bg-navy"><?php echo $sub['credits']; ?> TC</span>
                                    </td>
                                    <td class="small text-muted"><?php echo htmlspecialchars($sub['section_code']); ?></td>
                                    <td class="small"><?php echo htmlspecialchars($sub['semester_name'] . ' ' . $sub['school_year']); ?></td>
                                    <td class="small"><?php echo htmlspecialchars($sub['teacher_name']); ?></td>
                                    <td>
                                        <?php if (!empty($dayMap)): ?>
                                        <div class="d-flex flex-wrap gap-1 mb-1">
                                            <?php foreach ($dayMap as $d => $sess): ?>
                                            <span class="badge"
                                                  style="background:<?php echo $sessionColors[$sess] ?? '#666'; ?>; font-size:0.75rem;"
                                                  title="<?php echo $sessionTimes[$sess] ?? ''; ?>">
                                                <?php echo $dayNames[$d] ?? 'N'.$d; ?>
                                                <?php echo $sessionLabels[$sess] ?? $sess; ?>
                                            </span>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="text-muted" style="font-size:0.7rem">
                                            <?php echo count($dayMap); ?> buổi/tuần × 5 tiết
                                        </div>
                                        <?php
                                        $sdStart = !empty($sub['start_date']) ? date('d/m/Y', strtotime($sub['start_date'])) : null;
                                        $sdEnd   = !empty($sub['end_date'])   ? date('d/m/Y', strtotime($sub['end_date']))   : null;
                                        if ($sdStart || $sdEnd): ?>
                                        <div class="text-muted" style="font-size:0.7rem; margin-top:2px;">
                                            <i class="bi bi-calendar-range"></i>
                                            <?php echo $sdStart ?? '--'; ?> → <?php echo $sdEnd ?? '--'; ?>
                                        </div>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <span class="text-muted small fst-italic">Chưa có lịch</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><?php echo htmlspecialchars($sub['room']); ?></td>
                                    <td class="small text-success"><?php echo number_format($sub['tuition_fee'],0,',','.'); ?>đ</td>
                                    <td><span class="badge bg-<?php echo $s[1]; ?>"><?php echo $s[0]; ?></span></td>
                                    <td>
                                        <?php if ($sub['reg_status'] === 'registered' && $regOpen): ?>
                                        <form method="POST" onsubmit="return confirm('Hủy đăng ký môn này?')">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="ss_id" value="<?php echo $sub['ss_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-x-circle me-1"></i>Hủy
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <span class="text-muted small">--</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr><td colspan="10" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                    Chưa đăng ký học phần nào
                                </td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($totalCount > 0): ?>
                    <div class="border-top px-4 py-3 d-flex flex-wrap gap-4 align-items-center justify-content-between bg-light">
                        <div class="d-flex gap-4 flex-wrap">
                            <div class="d-flex align-items-center gap-2">
                                <span class="text-muted small">Số môn đã đăng ký:</span>
                                <span class="badge bg-navy fs-6"><?php echo $totalCount; ?> môn</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="text-muted small">Tổng tín chỉ:</span>
                                <span class="badge bg-success fs-6"><?php echo $totalCredits; ?> TC</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted small fw-semibold">Tổng học phí:</span>
                            <span class="fw-bold text-success fs-5"><?php echo number_format($totalFee, 0, ',', '.'); ?>đ</span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="student-footer">&copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một</div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<?php include_once __DIR__ . "/../includes/analytics_widget.php"; ?>
</body>
</html>
