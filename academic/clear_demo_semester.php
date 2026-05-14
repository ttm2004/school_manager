<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/AcademicPolicy.php';
requireAnyRole(['academic_manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

if (!verifyCSRFToken($_POST['_csrf_token'] ?? '')) {
    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Yeu cau khong hop le.'];
    header('Location: semesters.php');
    exit();
}

$semesterId = (int)($_POST['semester_id'] ?? 0);
$sem = $conn->query("SELECT id, data_mode, demo_batch_id FROM semesters WHERE id=$semesterId LIMIT 1")->fetch_assoc();
if (!$sem || ($sem['data_mode'] ?? 'system') !== 'test') {
    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Chi duoc xoa hoc ky demo/test.'];
    header('Location: semesters.php');
    exit();
}

$conn->begin_transaction();
try {
    $conn->query("DROP TEMPORARY TABLE IF EXISTS tmp_demo_sections");
    $conn->query("CREATE TEMPORARY TABLE tmp_demo_sections AS SELECT id FROM course_sections WHERE semester_id=$semesterId AND data_mode='test'");
    $conn->query("DROP TEMPORARY TABLE IF EXISTS tmp_demo_student_subjects");
    $conn->query("CREATE TEMPORARY TABLE tmp_demo_student_subjects AS SELECT ss.id, ss.student_id FROM student_subjects ss JOIN tmp_demo_sections ds ON ds.id=ss.course_section_id");

    $conn->query("DELETE tp FROM tuition_payments tp JOIN tuition_invoices ti ON ti.id=tp.invoice_id WHERE ti.semester_id=$semesterId AND ti.data_mode='test'");
    $conn->query("DELETE FROM tuition_invoices WHERE semester_id=$semesterId AND data_mode='test'");
    $conn->query("DELETE FROM tuition_periods WHERE semester_id=$semesterId AND data_mode='test'");
    $conn->query("DELETE se FROM student_evaluations se JOIN tmp_demo_sections ds ON ds.id=se.course_section_id");
    $conn->query("DELETE sec FROM student_extra_comments sec JOIN tmp_demo_sections ds ON ds.id=sec.course_section_id");
    $conn->query("DELETE gl FROM grade_locks gl JOIN tmp_demo_sections ds ON ds.id=gl.course_section_id");
    $conn->query("DELETE g FROM grades g JOIN tmp_demo_student_subjects tss ON tss.id=g.student_subject_id");
    $conn->query("DELETE pe FROM pending_enrollments pe JOIN tmp_demo_student_subjects tss ON tss.student_id=pe.student_id AND pe.data_mode='test'");
    $conn->query("DELETE ss FROM student_subjects ss JOIN tmp_demo_student_subjects tss ON tss.id=ss.id");
    $conn->query("DELETE fes FROM final_exam_schedules fes JOIN tmp_demo_sections ds ON ds.id=fes.course_section_id");
    $conn->query("DELETE csc FROM course_section_schedule_changes csc JOIN tmp_demo_sections ds ON ds.id=csc.course_section_id");
    $conn->query("DELETE cs FROM course_sections cs JOIN tmp_demo_sections ds ON ds.id=cs.id");
    $conn->query("DELETE FROM semesters WHERE id=$semesterId AND data_mode='test'");

    $conn->commit();
    $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Da xoa hoc ky demo va du lieu nghiep vu test lien quan.'];
} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Loi xoa demo: ' . $e->getMessage()];
}

header('Location: semesters.php');
exit();
