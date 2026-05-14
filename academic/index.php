<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/academic_helpers.php';
requireAnyRole(['academic_manager','academic_staff']);

$pageTitle = 'Dashboard — Phong Dao tao';
$userId    = (int)$_SESSION['user_id'];
$flash     = getFlash();
$stats     = getAcademicDashboardStats($conn);
$activeSem = getActiveSemesterAcademic($conn);

// Lay danh sach de xuat moi nhat (5 cai)
$recentProposals = $conn->query(
    "SELECT cs.id, cs.section_code, cs.open_proposed_at, cs.expected_students,
            s.subject_name, f.faculty_name, sm.semester_name,
            u.full_name AS proposed_by_name
     FROM course_sections cs
     JOIN subjects s ON cs.subject_id = s.id
     JOIN semesters sm ON cs.semester_id = sm.id
     LEFT JOIN users u ON cs.open_proposed_by = u.id
     LEFT JOIN teachers t ON t.user_id = cs.open_proposed_by
     LEFT JOIN faculties f ON t.faculty_id = f.id
     WHERE cs.status = 'proposed'
     ORDER BY cs.open_proposed_at DESC
     LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);

// Lay GV chua nhap diem (lop HP dang mo, qua han)
$gradeReminders = $conn->query(
    "SELECT cs.id, cs.section_code, s.subject_name,
            u.full_name AS teacher_name, t.id AS teacher_id,
            COUNT(ss.id) AS total_students,
            SUM(CASE WHEN g.final_score IS NOT NULL THEN 1 ELSE 0 END) AS graded,
            sm.grade_submit_deadline
     FROM course_sections cs
     JOIN subjects s ON cs.subject_id = s.id
     JOIN semesters sm ON cs.semester_id = sm.id
     LEFT JOIN teachers t ON cs.teacher_id = t.id
     LEFT JOIN users u ON t.user_id = u.id
     LEFT JOIN student_subjects ss ON ss.course_section_id = cs.id AND ss.status IN ('registered','auto_enrolled')
     LEFT JOIN grades g ON g.student_subject_id = ss.id
     WHERE cs.status IN ('open','closed')
       AND sm.grade_submit_deadline IS NOT NULL
       AND sm.grade_submit_deadline < CURDATE()
     GROUP BY cs.id, cs.section_code, s.subject_name, u.full_name, t.id, sm.grade_submit_deadline
     HAVING graded < total_students AND total_students > 0
     ORDER BY sm.grade_submit_deadline ASC
     LIMIT 8"
)->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
<div class="admin-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
        <span class="admin-topbar-title"><i class="bi bi-speedometer2 me-2 text-navy"></i>Dashboard — Phong Dao tao</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <?php if ($activeSem): ?>
        <span class="badge bg-success"><i class="bi bi-calendar3 me-1"></i><?php echo htmlspecialchars($activeSem['semester_name']); ?></span>
        <?php endif; ?>
        <span class="text-muted small"><?php echo htmlspecialchars($_SESSION['full_name']??''); ?></span>
        <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right me-1"></i>Dang xuat</a>
    </div>
</div>
<div class="admin-content">

<?php if ($flash): ?>
<div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show auto-dismiss mb-3">
    <?php echo htmlspecialchars($flash['message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Stats row 1 -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card-admin stat-bg-1">
            <div class="stat-icon"><i class="bi bi-grid-3x3-gap-fill"></i></div>
            <div class="stat-value mt-2"><?php echo number_format($stats['open_sections']); ?></div>
            <div class="stat-label">Lop HP dang mo</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card-admin stat-bg-2">
            <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
            <div class="stat-value mt-2"><?php echo number_format($stats['total_students']); ?></div>
            <div class="stat-label">Sinh vien dang hoc</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card-admin stat-bg-3">
            <div class="stat-icon"><i class="bi bi-person-badge-fill"></i></div>
            <div class="stat-value mt-2"><?php echo number_format($stats['total_teachers']); ?></div>
            <div class="stat-label">Giang vien</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card-admin <?php echo $stats['pending_proposals']>0?'stat-bg-4':'stat-bg-1'; ?>">
            <div class="stat-icon"><i class="bi bi-send-fill"></i></div>
            <div class="stat-value mt-2"><?php echo number_format($stats['pending_proposals']); ?></div>
            <div class="stat-label">De xuat cho duyet</div>
        </div>
    </div>
</div>

<!-- Canh bao -->
<?php $totalWarnings = $stats['no_teacher'] + $stats['no_schedule'] + $stats['no_room'] + $stats['no_exam'] + $stats['missing_grades'] + $stats['pending_assignments']; ?>
<?php if ($totalWarnings > 0): ?>
<div class="row g-3 mb-4">
    <?php if ($stats['no_teacher'] > 0): ?>
    <div class="col-6 col-lg-3">
        <div class="card border-danger h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="bi bi-person-x-fill text-danger fs-2"></i>
                <div>
                    <div class="fw-bold fs-4 text-danger"><?php echo $stats['no_teacher']; ?></div>
                    <div class="small text-muted">Lop HP chua co GV</div>
                    <a href="teacher_assignments.php?filter=no_teacher" class="btn btn-sm btn-outline-danger mt-1">Xu ly</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($stats['no_schedule'] > 0): ?>
    <div class="col-6 col-lg-3">
        <div class="card border-warning h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="bi bi-calendar-week text-warning fs-2"></i>
                <div>
                    <div class="fw-bold fs-4 text-warning"><?php echo $stats['no_schedule']; ?></div>
                    <div class="small text-muted">Lớp chưa có lịch học</div>
                    <a href="course_sections.php" class="btn btn-sm btn-outline-warning mt-1">Xử lý</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($stats['no_room'] > 0): ?>
    <div class="col-6 col-lg-3">
        <div class="card border-warning h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="bi bi-door-open-fill text-warning fs-2"></i>
                <div>
                    <div class="fw-bold fs-4 text-warning"><?php echo $stats['no_room']; ?></div>
                    <div class="small text-muted">Lớp chưa có phòng</div>
                    <a href="course_sections.php" class="btn btn-sm btn-outline-warning mt-1">Xử lý</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($stats['no_exam'] > 0): ?>
    <div class="col-6 col-lg-3">
        <div class="card border-warning h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="bi bi-calendar-x-fill text-warning fs-2"></i>
                <div>
                    <div class="fw-bold fs-4 text-warning"><?php echo $stats['no_exam']; ?></div>
                    <div class="small text-muted">Lop HP chua co lich thi</div>
                    <a href="exam_schedules.php?filter=no_exam" class="btn btn-sm btn-outline-warning mt-1">Xu ly</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($stats['missing_grades'] > 0): ?>
    <div class="col-6 col-lg-3">
        <div class="card border-danger h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="bi bi-pencil-square text-danger fs-2"></i>
                <div>
                    <div class="fw-bold fs-4 text-danger"><?php echo $stats['missing_grades']; ?></div>
                    <div class="small text-muted">Lop HP thieu diem</div>
                    <a href="grade_reminder.php" class="btn btn-sm btn-outline-danger mt-1">Nhac GV</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($stats['pending_assignments'] > 0): ?>
    <div class="col-6 col-lg-3">
        <div class="card border-info h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="bi bi-person-check-fill text-info fs-2"></i>
                <div>
                    <div class="fw-bold fs-4 text-info"><?php echo $stats['pending_assignments']; ?></div>
                    <div class="small text-muted">De xuat phan cong GV</div>
                    <a href="teacher_assignments.php?filter=pending" class="btn btn-sm btn-outline-info mt-1">Duyet</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- De xuat mo lop moi nhat -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-send-fill me-2"></i>De xuat mo lop cho duyet</span>
                <a href="proposals.php" class="btn btn-sm btn-outline-light">Xem tat ca</a>
            </div>
            <?php if (empty($recentProposals)): ?>
            <div class="card-body text-center text-muted py-4">
                <i class="bi bi-check-circle-fill text-success fs-2 d-block mb-2"></i>
                Khong co de xuat nao dang cho duyet.
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Mon hoc</th><th>Khoa</th><th>HK</th><th>Si so DK</th><th>Ngay gui</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentProposals as $p): ?>
                    <tr>
                        <td>
                            <div class="small fw-semibold"><?php echo htmlspecialchars($p['subject_name']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($p['section_code']); ?></small>
                        </td>
                        <td class="small"><?php echo htmlspecialchars($p['faculty_name']??'—'); ?></td>
                        <td class="small"><?php echo htmlspecialchars($p['semester_name']); ?></td>
                        <td class="text-center"><span class="badge bg-light text-dark"><?php echo (int)$p['expected_students']; ?></span></td>
                        <td class="small text-muted"><?php echo $p['open_proposed_at'] ? date('d/m/Y', strtotime($p['open_proposed_at'])) : '—'; ?></td>
                        <td>
                            <a href="proposals.php?action=review&id=<?php echo $p['id']; ?>"
                               class="btn btn-sm btn-warning">Duyet</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- GV chua nhap diem -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-pencil-square me-2"></i>GV chua nhap diem day du</span>
                <a href="grade_reminder.php" class="btn btn-sm btn-outline-light">Nhac tat ca</a>
            </div>
            <?php if (empty($gradeReminders)): ?>
            <div class="card-body text-center text-muted py-4">
                <i class="bi bi-check-circle-fill text-success fs-2 d-block mb-2"></i>
                Tat ca GV da nhap diem day du.
            </div>
            <?php else: ?>
            <div class="list-group list-group-flush">
            <?php foreach ($gradeReminders as $r): ?>
            <div class="list-group-item py-2">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small fw-semibold"><?php echo htmlspecialchars($r['subject_name']); ?></div>
                        <small class="text-muted"><?php echo htmlspecialchars($r['teacher_name']??'Chua co GV'); ?></small>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-warning text-dark">
                            <?php echo (int)$r['graded']; ?>/<?php echo (int)$r['total_students']; ?>
                        </span>
                        <?php if ($r['teacher_id']): ?>
                        <br>
                        <a href="grade_reminder.php?teacher_id=<?php echo $r['teacher_id']; ?>"
                           class="btn btn-xs btn-outline-danger mt-1" style="font-size:.7rem;padding:1px 6px">
                            <i class="bi bi-bell"></i> Nhac
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</div><!-- /.admin-content -->
<div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU — Phong Dao tao</div>
</div><!-- /.admin-main -->
<?php include 'includes/footer.php'; ?>

