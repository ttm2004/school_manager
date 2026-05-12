<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/teacher_assignment_rules.php';
require_once 'includes/academic_helpers.php';
requireAnyRole(['academic_manager','academic_staff']);

$pageTitle = 'Phân công Giảng viên';
$userId    = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['_csrf_token'] ?? '')) {
        $_SESSION['_flash'] = ['type'=>'danger','message'=>'Yêu cầu không hợp lệ.'];
        header('Location: teacher_assignments.php'); exit();
    }
    if (!isAcademicManager()) {
        $_SESSION['_flash'] = ['type'=>'danger','message'=>'Chỉ Trưởng phòng mới có quyền phân công.'];
        header('Location: teacher_assignments.php'); exit();
    }

    $action    = trim($_POST['action'] ?? '');
    $sectionId = (int)($_POST['section_id'] ?? 0);

    // PHAN CONG TRUC TIEP
    if ($action === 'assign') {
        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        if ($sectionId <= 0 || $teacherId <= 0) {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Dữ liệu không hợp lệ.'];
            header('Location: teacher_assignments.php'); exit();
        }
        $assignmentCheck = validateTeacherAssignmentForSection($conn, $teacherId, $sectionId);
        if (!$assignmentCheck['ok']) {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>$assignmentCheck['message']];
            header('Location: teacher_assignments.php'); exit();
        }
        $stmt = $conn->prepare(
            "UPDATE course_sections SET teacher_id=?, proposal_status=NULL, proposed_teacher_id=NULL WHERE id=?"
        );
        $stmt->bind_param('ii', $teacherId, $sectionId);
        if ($stmt->execute()) {
            // Thong bao cho GV
            $info = $conn->query("SELECT cs.section_code, s.subject_name, t.user_id AS teacher_user_id
                                  FROM course_sections cs JOIN subjects s ON cs.subject_id=s.id
                                  JOIN teachers t ON t.id=$teacherId WHERE cs.id=$sectionId LIMIT 1")->fetch_assoc();
            if ($info && $info['teacher_user_id']) {
                $title = "Bạn được phân công dạy: {$info['section_code']}";
                $content = "Phòng Đào tạo đã phân công bạn dạy lớp {$info['section_code']} ({$info['subject_name']}).";
                $stmtN = $conn->prepare("INSERT INTO notifications (user_id, title, content) VALUES (?,?,?)");
                $stmtN->bind_param('iss', $info['teacher_user_id'], $title, $content);
                $stmtN->execute(); $stmtN->close();
            }
            $_SESSION['_flash'] = ['type'=>'success','message'=>'Phân công giảng viên thành công.'];
        } else {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Loi: '.$conn->error];
        }
        $stmt->close();
        header('Location: teacher_assignments.php'); exit();
    }

    // DUYET DE XUAT PHAN CONG TU KHOA (proposal_status: pending -> approved)
    if ($action === 'approve_assignment') {
        $stmtProp = $conn->prepare("SELECT proposed_teacher_id FROM course_sections WHERE id = ? AND proposal_status = 'pending' LIMIT 1");
        $stmtProp->bind_param('i', $sectionId);
        $stmtProp->execute();
        $propRow = $stmtProp->get_result()->fetch_assoc();
        $stmtProp->close();
        $assignmentCheck = validateTeacherAssignmentForSection($conn, (int)($propRow['proposed_teacher_id'] ?? 0), $sectionId);
        if (!$assignmentCheck['ok']) {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>$assignmentCheck['message']];
            header('Location: teacher_assignments.php'); exit();
        }
        $stmt = $conn->prepare(
            "UPDATE course_sections
             SET teacher_id = proposed_teacher_id,
                 proposal_status = 'approved',
                 proposed_teacher_id = NULL
             WHERE id = ? AND proposal_status = 'pending'"
        );
        $stmt->bind_param('i', $sectionId);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            // Thong bao cho GV va Khoa
            $info = $conn->query("SELECT cs.section_code, s.subject_name, cs.teacher_id,
                                         t.user_id AS teacher_user_id, f.id AS faculty_id
                                  FROM course_sections cs JOIN subjects s ON cs.subject_id=s.id
                                  LEFT JOIN teachers t ON t.id=cs.teacher_id
                                  LEFT JOIN majors m ON s.major_id=m.id
                                  LEFT JOIN faculties f ON m.faculty_id=f.id
                                  WHERE cs.id=$sectionId LIMIT 1")->fetch_assoc();
            if ($info) {
                if ($info['teacher_user_id']) {
                    $title = "Phân công được duyệt: {$info['section_code']}";
                    $content = "Đề xuất phân công bạn dạy lớp {$info['section_code']} ({$info['subject_name']}) đã được Phòng Đào tạo phê duyệt.";
                    $stmtN = $conn->prepare("INSERT INTO notifications (user_id, title, content) VALUES (?,?,?)");
                    $stmtN->bind_param('iss', $info['teacher_user_id'], $title, $content);
                    $stmtN->execute(); $stmtN->close();
                }
                sendAcademicNotification($conn, $userId, 'assignment_approved',
                    "Phân công GV được duyệt: {$info['section_code']}",
                    "Đề xuất phân công GV cho lớp {$info['section_code']} đã được phê duyệt.",
                    $info['faculty_id'] ?? null, null, $sectionId, 'course_section'
                );
            }
            $_SESSION['_flash'] = ['type'=>'success','message'=>'Đã duyệt phân công giảng viên.'];
        }
        $stmt->close();
        header('Location: teacher_assignments.php'); exit();
    }

    // TU CHOI DE XUAT PHAN CONG
    if ($action === 'reject_assignment') {
        $reason = trim($_POST['reject_reason'] ?? '');
        $stmt = $conn->prepare(
            "UPDATE course_sections
             SET proposal_status='rejected',
                 open_reject_reason=?
             WHERE id=? AND proposal_status='pending'"
        );
        $stmt->bind_param('si', $reason, $sectionId);
        $stmt->execute();
        $stmt->close();
        $_SESSION['_flash'] = ['type'=>'warning','message'=>'Đã từ chối đề xuất phân công.'];
        header('Location: teacher_assignments.php'); exit();
    }

    // HUY PHAN CONG
    if ($action === 'unassign') {
        $stmt = $conn->prepare("UPDATE course_sections SET teacher_id=NULL WHERE id=?");
        $stmt->bind_param('i', $sectionId);
        $stmt->execute(); $stmt->close();
        $_SESSION['_flash'] = ['type'=>'warning','message'=>'Đã hủy phân công.'];
        header('Location: teacher_assignments.php'); exit();
    }
}

$flash = getFlash();

// Filters
$filterSem     = (int)($_GET['semester_id'] ?? 0);
$filterFaculty = (int)($_GET['faculty_id'] ?? 0);
$filterMode    = trim($_GET['filter'] ?? ''); // no_teacher | pending | all
$page          = max(1, (int)($_GET['page'] ?? 1));
$perPage       = 25;

// Active semester default
if ($filterSem === 0) {
    $activeSem = getActiveSemesterAcademic($conn);
    if ($activeSem) $filterSem = (int)$activeSem['id'];
}

$where  = ['1=1'];
$types  = '';
$params = [];

if ($filterSem > 0) {
    $where[] = 'cs.semester_id = ?'; $types .= 'i'; $params[] = $filterSem;
}
if ($filterFaculty > 0) {
    $where[] = 'f.id = ?'; $types .= 'i'; $params[] = $filterFaculty;
}
if ($filterMode === 'no_teacher') {
    $where[] = "(cs.teacher_id IS NULL OR cs.teacher_id = 0)";
    $where[] = "cs.status = 'open'";
} elseif ($filterMode === 'pending') {
    $where[] = "cs.proposal_status = 'pending'";
} else {
    $where[] = "cs.status IN ('open','proposed')";
}

$whereSQL = implode(' AND ', $where);

$countSQL = "SELECT COUNT(*) AS c
             FROM course_sections cs
             JOIN subjects s ON cs.subject_id = s.id
             JOIN semesters sm ON cs.semester_id = sm.id
             LEFT JOIN majors m ON s.major_id = m.id
             LEFT JOIN faculties f ON m.faculty_id = f.id
             WHERE $whereSQL";
$stmtCnt = $conn->prepare($countSQL);
if ($types) $stmtCnt->bind_param($types, ...$params);
$stmtCnt->execute();
$total = (int)($stmtCnt->get_result()->fetch_assoc()['c'] ?? 0);
$stmtCnt->close();

$pag = paginateAcademic($total, $page, $perPage);

$dataSQL = "SELECT cs.id, cs.section_code, cs.status, cs.proposal_status,
                   cs.max_students, cs.current_students, cs.room, cs.teaching_mode,
                   cs.proposed_teacher_id,
                   s.subject_name, s.credits,
                   sm.semester_name,
                   f.faculty_name,
                   ut.full_name AS teacher_name, t.teacher_code, t.degree,
                   upt.full_name AS proposed_teacher_name, tpt.teacher_code AS proposed_teacher_code
            FROM course_sections cs
            JOIN subjects s ON cs.subject_id = s.id
            JOIN semesters sm ON cs.semester_id = sm.id
            LEFT JOIN majors m ON s.major_id = m.id
            LEFT JOIN faculties f ON m.faculty_id = f.id
            LEFT JOIN teachers t ON cs.teacher_id = t.id
            LEFT JOIN users ut ON t.user_id = ut.id
            LEFT JOIN teachers tpt ON cs.proposed_teacher_id = tpt.id
            LEFT JOIN users upt ON tpt.user_id = upt.id
            WHERE $whereSQL
            ORDER BY f.faculty_name, s.subject_name
            LIMIT ? OFFSET ?";
$allTypes  = $types . 'ii';
$allParams = array_merge($params, [$pag['per_page'], $pag['offset']]);
$stmtData  = $conn->prepare($dataSQL);
$stmtData->bind_param($allTypes, ...$allParams);
$stmtData->execute();
$sections = $stmtData->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtData->close();

// Lay danh sach GV de assign (filter theo faculty neu co)
$teacherSQL = "SELECT t.id, t.teacher_code, t.degree, u.full_name, f.faculty_name,
                      (SELECT COUNT(*) FROM course_sections cs2
                       WHERE cs2.teacher_id = t.id AND cs2.semester_id = $filterSem
                         AND cs2.status IN ('open','closed')) AS load_count
               FROM teachers t JOIN users u ON t.user_id = u.id
               LEFT JOIN faculties f ON t.faculty_id = f.id
               WHERE u.status = 1";
if ($filterFaculty > 0) $teacherSQL .= " AND t.faculty_id = $filterFaculty";
$teacherSQL .= " ORDER BY f.faculty_name, u.full_name";
$allTeachers = $conn->query($teacherSQL)->fetch_all(MYSQLI_ASSOC);

$faculties = $conn->query("SELECT id, faculty_name FROM faculties ORDER BY faculty_name")->fetch_all(MYSQLI_ASSOC);
$semesters = $conn->query("SELECT id, semester_name, school_year FROM semesters ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
<div class="admin-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
        <span class="admin-topbar-title"><i class="bi bi-person-check-fill me-2 text-navy"></i>Phân công Giảng viên</span>
    </div>
    <span class="text-muted small"><?php echo htmlspecialchars($_SESSION['full_name']??''); ?></span>
</div>
<div class="admin-content">

<?php if ($flash): ?>
<div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show auto-dismiss mb-3">
    <?php echo htmlspecialchars($flash['message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label small">Học kỳ</label>
                <select name="semester_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($semesters as $sm): ?>
                    <option value="<?php echo $sm['id']; ?>" <?php echo $filterSem==$sm['id']?'selected':''; ?>>
                        <?php echo htmlspecialchars($sm['semester_name'].' '.$sm['school_year']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small">Khoa</label>
                <select name="faculty_id" class="form-select form-select-sm">
                    <option value="0">-- Tất cả --</option>
                    <?php foreach ($faculties as $f): ?>
                    <option value="<?php echo $f['id']; ?>" <?php echo $filterFaculty==$f['id']?'selected':''; ?>>
                        <?php echo htmlspecialchars($f['faculty_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small">Loc</label>
                <select name="filter" class="form-select form-select-sm">
                    <option value="" <?php echo $filterMode===''?'selected':''; ?>>Tất cả lớp mở</option>
                    <option value="no_teacher" <?php echo $filterMode==='no_teacher'?'selected':''; ?>>Chưa có GV</option>
                    <option value="pending" <?php echo $filterMode==='pending'?'selected':''; ?>>Đề xuất chờ duyệt</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-navy"><i class="bi bi-search"></i></button>
                <a href="teacher_assignments.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Bảng phân công -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-table me-2"></i>Danh sách Lớp học phần
            <span class="badge bg-light text-dark ms-1"><?php echo number_format($total); ?></span>
        </span>
    </div>
    <?php if (empty($sections)): ?>
    <div class="card-body text-center text-muted py-4"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Không có dữ liệu.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Lớp học phần / Môn học</th>
                    <th>Khoa</th>
                    <th class="text-center">Si so</th>
                    <th>Giảng viên</th>
                    <th>Đề xuất từ Khoa</th>
                    <th class="text-center">Thao tac</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sections as $cs): ?>
            <tr class="<?php echo (!$cs['teacher_name'] && $cs['status']==='open') ? 'table-warning' : ''; ?>">
                <td>
                    <div class="fw-semibold small"><?php echo htmlspecialchars($cs['section_code']); ?></div>
                    <small class="text-muted"><?php echo htmlspecialchars($cs['subject_name']); ?> · <?php echo $cs['credits']; ?> TC</small>
                    <?php if ($cs['room']): ?><br><small class="text-muted"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($cs['room']); ?></small><?php endif; ?>
                </td>
                <td class="small"><?php echo htmlspecialchars($cs['faculty_name']??'—'); ?></td>
                <td class="text-center">
                    <span class="badge bg-light text-dark"><?php echo $cs['current_students']; ?>/<?php echo $cs['max_students']; ?></span>
                </td>
                <td>
                    <?php if ($cs['teacher_name']): ?>
                    <div class="small fw-semibold"><?php echo htmlspecialchars($cs['teacher_name']); ?></div>
                    <small class="text-muted"><?php echo htmlspecialchars($cs['teacher_code']); ?> · <?php echo htmlspecialchars($cs['degree']??''); ?></small>
                    <?php else: ?>
                    <span class="badge bg-warning text-dark">Chưa có GV</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($cs['proposal_status'] === 'pending' && $cs['proposed_teacher_name']): ?>
                    <div class="small">
                        <span class="badge bg-info">Đề xuất: <?php echo htmlspecialchars($cs['proposed_teacher_name']); ?></span>
                    </div>
                    <?php if (isAcademicManager()): ?>
                    <div class="d-flex gap-1 mt-1">
                        <form method="post" class="d-inline">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="approve_assignment">
                            <input type="hidden" name="section_id" value="<?php echo $cs['id']; ?>">
                            <button class="btn btn-xs btn-success" style="font-size:.7rem;padding:2px 6px"
                                    onclick="return confirm('Duyệt phân công?')">
                                <i class="bi bi-check"></i> Duyệt
                            </button>
                        </form>
                        <button class="btn btn-xs btn-outline-danger" style="font-size:.7rem;padding:2px 6px"
                                data-bs-toggle="modal" data-bs-target="#rejectModal"
                                data-section-id="<?php echo $cs['id']; ?>">
                            <i class="bi bi-x"></i> Từ chối
                        </button>
                    </div>
                    <?php endif; ?>
                    <?php elseif ($cs['proposal_status'] === 'approved'): ?>
                    <span class="badge bg-success">Đã duyệt</span>
                    <?php elseif ($cs['proposal_status'] === 'rejected'): ?>
                    <span class="badge bg-danger">Từ chối</span>
                    <?php else: ?>
                    <span class="text-muted small">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php if (isAcademicManager()): ?>
                    <button class="btn btn-sm btn-outline-primary"
                            data-bs-toggle="modal" data-bs-target="#assignModal"
                            data-section-id="<?php echo $cs['id']; ?>"
                            data-section-code="<?php echo htmlspecialchars($cs['section_code']); ?>"
                            data-subject="<?php echo htmlspecialchars($cs['subject_name']); ?>"
                            title="Phân công GV">
                        <i class="bi bi-person-plus"></i>
                    </button>
                    <?php if ($cs['teacher_name']): ?>
                    <form method="post" class="d-inline" onsubmit="return confirm('Hủy phân công?')">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="unassign">
                        <input type="hidden" name="section_id" value="<?php echo $cs['id']; ?>">
                        <button class="btn btn-sm btn-outline-danger" title="Hủy phân công">
                            <i class="bi bi-person-dash"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pag['total_pages'] > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted"><?php echo $pag['offset']+1; ?>–<?php echo min($pag['offset']+$pag['per_page'],$pag['total']); ?> / <?php echo number_format($pag['total']); ?></small>
        <?php $qs2 = http_build_query(['semester_id'=>$filterSem,'faculty_id'=>$filterFaculty,'filter'=>$filterMode]);
        echo renderAcademicPagination($pag, $qs2); ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

</div><!-- /.admin-content -->
<div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU</div>
</div>

<!-- Modal phân công GV -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Phân công Giảng viên</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="post" action="teacher_assignments.php">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="assign">
            <input type="hidden" name="section_id" id="assignSectionId">
            <div class="modal-body">
                <p class="text-muted small mb-3">Lớp: <strong id="assignSectionInfo"></strong></p>
                <label class="form-label fw-semibold">Chọn Giảng viên <span class="text-danger">*</span></label>
                <select name="teacher_id" class="form-select" required>
                    <option value="">-- Chọn GV --</option>
                    <?php
                    $lastFaculty = '';
                    foreach ($allTeachers as $t):
                        if ($t['faculty_name'] !== $lastFaculty):
                            if ($lastFaculty !== '') echo '</optgroup>';
                            echo '<optgroup label="' . htmlspecialchars($t['faculty_name']??'Khac') . '">';
                            $lastFaculty = $t['faculty_name'];
                        endif;
                        $loadBadge = $t['load_count'] > 0 ? " [{$t['load_count']} lớp]" : '';
                    ?>
                    <option value="<?php echo $t['id']; ?>">
                        <?php echo htmlspecialchars($t['full_name'] . ' (' . $t['teacher_code'] . ' · ' . ($t['degree']??'') . ')' . $loadBadge); ?>
                    </option>
                    <?php endforeach; if ($lastFaculty !== '') echo '</optgroup>'; ?>
                </select>
                <div class="form-text">Số trong [] là số lớp học phần đang dạy trong học kỳ này.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check me-1"></i>Phân công</button>
            </div>
        </form>
    </div></div>
</div>

<!-- Modal từ chối phân công -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header bg-danger text-white">
            <h5 class="modal-title">Từ chối đề xuất phân công</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <form method="post" action="teacher_assignments.php">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="reject_assignment">
            <input type="hidden" name="section_id" id="rejectSectionId">
            <div class="modal-body">
                <label class="form-label fw-semibold">Ly do tu choi <span class="text-danger">*</span></label>
                <textarea name="reject_reason" class="form-control" rows="3" required
                          placeholder="Nhập lý do để thông báo cho Khoa..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn btn-danger">Từ chối</button>
            </div>
        </form>
    </div></div>
</div>

<script>
document.getElementById('assignModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('assignSectionId').value = btn.dataset.sectionId;
    document.getElementById('assignSectionInfo').textContent = btn.dataset.sectionCode + ' — ' + btn.dataset.subject;
});
document.getElementById('rejectModal').addEventListener('show.bs.modal', function(e) {
    document.getElementById('rejectSectionId').value = e.relatedTarget.dataset.sectionId;
});
</script>
<?php include 'includes/footer.php'; ?>
