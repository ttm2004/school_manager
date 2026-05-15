<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/AcademicPolicy.php';
require_once '../includes/teacher_assignment_rules.php';
require_once '../app/Services/RoomSchedulingService.php';
require_once '../app/Services/TeacherAssignmentService.php';
require_once 'includes/academic_helpers.php';
requireAnyRole(['academic_manager','academic_staff']);
$pageTitle = 'Quản lý Lớp học phần';
$userId = (int)$_SESSION['user_id'];

function academicExtractSemesterTerm(?string $semesterName): int
{
    $text = mb_strtolower((string)$semesterName, 'UTF-8');
    if (preg_match('/(?:học kỳ|hoc ky|hk)\s*([0-9]+)/u', $text, $m)) {
        return max(1, (int)$m[1]);
    }
    if (preg_match('/\b([123])\b/u', $text, $m)) {
        return max(1, (int)$m[1]);
    }
    return 1;
}

function academicSchoolYearStart(?string $schoolYear): int
{
    if (preg_match('/(20[0-9]{2})/', (string)$schoolYear, $m)) {
        return (int)$m[1];
    }
    return (int)date('Y');
}

function academicCurriculumSemesterOrder(array $semester, array $cohort): int
{
    return academicPolicyCurriculumSemesterOrder((int)($cohort['enrollment_year'] ?? 0), $semester) ?? 0;
}

function academicBuildSectionCode(mysqli $conn, string $subjectCode, int $semesterId, ?int $cohortId, string $dataMode): string
{
    $safePrefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $subjectCode));
    if ($safePrefix === '') $safePrefix = 'HP';
    $like = $safePrefix . '_%';
    $stmt = $conn->prepare(
        "SELECT section_code
         FROM course_sections
         WHERE section_code LIKE ?
         ORDER BY section_code"
    );
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $existing = array_flip(array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'section_code'));
    $stmt->close();
    for ($i = 1; $i <= 99; $i++) {
        $code = $safePrefix . '_' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
        if (!isset($existing[$code])) return $code;
    }
    return $safePrefix . '_' . date('His');
}

function academicCourseSectionsRedirectTarget(?string $fallback = null): string
{
    $target = (string)($_POST['redirect_to'] ?? $fallback ?? 'course_sections.php');
    if ($target === '' || preg_match('/^[a-z][a-z0-9+.-]*:/i', $target) || str_starts_with($target, '//')) {
        return 'course_sections.php';
    }
    return $target;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['_csrf_token'] ?? '')) {
        $_SESSION['_flash'] = ['type'=>'danger','message'=>'CSRF invalid.']; header('Location: ' . academicCourseSectionsRedirectTarget()); exit();
    }
    if (!isAcademicManager()) {
        $_SESSION['_flash'] = ['type'=>'danger','message'=>'Chỉ Trưởng phòng mới có quyền.']; header('Location: ' . academicCourseSectionsRedirectTarget()); exit();
    }
    $action = trim($_POST['action'] ?? '');
    if ($action === 'import_curriculum_sections') {
        $semesterId = (int)($_POST['semester_id'] ?? 0);
        $targetMajorId = (int)($_POST['target_major_id'] ?? 0);
        $targetCohortId = (int)($_POST['target_cohort_id'] ?? 0);
        $classCount = max(1, min(20, (int)($_POST['class_count'] ?? 1)));
        $maxStudents = max(1, (int)($_POST['max_students'] ?? 70));
        $teacherId = (int)($_POST['teacher_id'] ?? 0) ?: null;
        $status = trim($_POST['status'] ?? 'open');
        if (!in_array($status, ['open', 'draft'], true)) $status = 'open';

        $semesterStmt = $conn->prepare("SELECT id, semester_name, school_year, start_date, end_date, data_mode, demo_batch_id FROM semesters WHERE id = ? LIMIT 1");
        $semesterStmt->bind_param('i', $semesterId);
        $semesterStmt->execute();
        $semester = $semesterStmt->get_result()->fetch_assoc();
        $semesterStmt->close();

        if ($targetMajorId <= 0 && $targetCohortId > 0) {
            $cohortMajorStmt = $conn->prepare("SELECT major_id FROM training_cohorts WHERE id = ? LIMIT 1");
            $cohortMajorStmt->bind_param('i', $targetCohortId);
            $cohortMajorStmt->execute();
            $targetMajorId = (int)($cohortMajorStmt->get_result()->fetch_assoc()['major_id'] ?? 0);
            $cohortMajorStmt->close();
        }

        if (!$semester || $targetMajorId <= 0) {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Vui lòng chọn đúng học kỳ và ngành cần mở lớp.'];
            header('Location: ' . academicCourseSectionsRedirectTarget('course_sections.php' . ($semesterId ? '?semester_id=' . $semesterId : ''))); exit();
        }

        $hasSectionClassId = academicPolicyColumnExists($conn, 'course_sections', 'class_id');
        $demoContext = academicPolicySemesterDemoContext($conn, $semesterId);
        $cohortStmt = $conn->prepare(
            "SELECT tc.id, tc.major_id, tc.enrollment_year, tc.program_id, tc.cohort_code, tc.duration_years, m.major_name
             FROM training_cohorts tc
             JOIN majors m ON m.id = tc.major_id
             WHERE tc.major_id = ?
               AND tc.status IN ('planned','active')
             ORDER BY tc.enrollment_year DESC, tc.id DESC"
        );
        $cohortStmt->bind_param('i', $targetMajorId);
        $cohortStmt->execute();
        $targetCohorts = $cohortStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $cohortStmt->close();
        if (empty($targetCohorts)) {
            $_SESSION['_flash'] = ['type'=>'warning','message'=>'Ngành đã chọn chưa có khóa đào tạo đang áp dụng.'];
            header('Location: ' . academicCourseSectionsRedirectTarget('course_sections.php?semester_id=' . $semesterId)); exit();
        }

        $created = 0;
        $cohortsHandled = 0;
        $classTotal = 0;
        $skipped = [];
        $conn->begin_transaction();
        try {
            foreach ($targetCohorts as $cohort) {
                $classStmt = $conn->prepare(
                    "SELECT id, class_code, class_name
                     FROM classes
                     WHERE major_id = ?
                       AND enrollment_year = ?
                       AND COALESCE(data_mode, 'system') = ?
                     ORDER BY class_code, id"
                );
                $classStmt->bind_param('iis', $cohort['major_id'], $cohort['enrollment_year'], $demoContext['data_mode']);
                $classStmt->execute();
                $adminClasses = $classStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $classStmt->close();
                if (empty($adminClasses)) {
                    $adminClasses = [['id' => null, 'class_code' => '', 'class_name' => '']];
                }
                $classTotal += count($adminClasses);

                $semesterOrder = academicCurriculumSemesterOrder($semester, $cohort);
                if ($semesterOrder <= 0) {
                    continue;
                }
                $currStmt = $conn->prepare(
                    "SELECT cur.subject_id, COALESCE(NULLIF(s.subject_code, ''), CONCAT('MH', s.id)) AS subject_code, s.subject_name
                     FROM curriculum cur
                     JOIN subjects s ON s.id = cur.subject_id
                     WHERE cur.major_id = ?
                       AND cur.deleted_at IS NULL
                       AND cur.suggested_semester = ?
                       AND (cur.program_id IS NULL OR cur.program_id = ?)
                     ORDER BY s.subject_code, s.subject_name"
                );
                $programId = (int)($cohort['program_id'] ?? 0);
                $currStmt->bind_param('iii', $cohort['major_id'], $semesterOrder, $programId);
                $currStmt->execute();
                $subjectsToOpen = $currStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $currStmt->close();
                if (empty($subjectsToOpen)) {
                    continue;
                }
                $cohortsHandled++;

                foreach ($subjectsToOpen as $subjectRow) {
                    $subjectId = (int)$subjectRow['subject_id'];
                    if ($teacherId) {
                        $assignmentCheck = TeacherAssignmentService::validate($conn, $teacherId, $subjectId, $semesterId);
                        if (!$assignmentCheck['ok']) {
                            $skipped[] = $subjectRow['subject_code'] . ': ' . $assignmentCheck['message'];
                            continue;
                        }
                    }
                    foreach ($adminClasses as $adminClass) {
                        $cohortId = (int)$cohort['id'];
                        $adminClassId = $hasSectionClassId && !empty($adminClass['id']) ? (int)$adminClass['id'] : null;
                        if ($hasSectionClassId) {
                            $dupStmt = $conn->prepare(
                                "SELECT id FROM course_sections
                                 WHERE subject_id = ? AND semester_id = ? AND target_cohort_id = ?
                                   AND ((class_id <=> ?) OR (? IS NULL AND class_id IS NULL))
                                   AND data_mode = ?
                                   AND status IN ('draft','proposed','open','full','closed')
                                 LIMIT 1"
                            );
                            $dupStmt->bind_param('iiiiis', $subjectId, $semesterId, $cohortId, $adminClassId, $adminClassId, $demoContext['data_mode']);
                        } else {
                            $dupStmt = $conn->prepare(
                                "SELECT id FROM course_sections
                                 WHERE subject_id = ? AND semester_id = ? AND target_cohort_id = ?
                                   AND data_mode = ?
                                   AND status IN ('draft','proposed','open','full','closed')
                                 LIMIT 1"
                            );
                            $dupStmt->bind_param('iiis', $subjectId, $semesterId, $cohortId, $demoContext['data_mode']);
                        }
                        $dupStmt->execute();
                        $exists = $dupStmt->get_result()->fetch_assoc();
                        $dupStmt->close();
                        if ($exists) {
                            continue;
                        }
                        $code = academicBuildSectionCode($conn, (string)$subjectRow['subject_code'], $semesterId, $cohortId, $demoContext['data_mode']);
                        $room = '';
                        $classroomId = null;
                        $daySession = '';
                        $startDate = $semester['start_date'] ?? null;
                        $endDate = $semester['end_date'] ?? null;
                        $mode = 'offline';
                        if ($hasSectionClassId) {
                            $stmt = $conn->prepare(
                                "INSERT INTO course_sections
                                 (subject_id,teacher_id,semester_id,target_cohort_id,class_id,section_code,room,classroom_id,max_students,current_students,status,data_mode,demo_batch_id,day_sessions,start_date,end_date,teaching_mode)
                                 VALUES (?,?,?,?,?,?,?,?,?,0,?,?,?,?,?,?,?)"
                            );
                            $stmt->bind_param('iiiiissiisssssss', $subjectId,$teacherId,$semesterId,$cohortId,$adminClassId,$code,$room,$classroomId,$maxStudents,$status,$demoContext['data_mode'],$demoContext['demo_batch_id'],$daySession,$startDate,$endDate,$mode);
                        } else {
                            $stmt = $conn->prepare(
                                "INSERT INTO course_sections
                                 (subject_id,teacher_id,semester_id,target_cohort_id,section_code,room,classroom_id,max_students,current_students,status,data_mode,demo_batch_id,day_sessions,start_date,end_date,teaching_mode)
                                 VALUES (?,?,?,?,?,?,?,?,0,?,?,?,?,?,?,?)"
                            );
                            $stmt->bind_param('iiiissiisssssss', $subjectId,$teacherId,$semesterId,$cohortId,$code,$room,$classroomId,$maxStudents,$status,$demoContext['data_mode'],$demoContext['demo_batch_id'],$daySession,$startDate,$endDate,$mode);
                        }
                        if (!$stmt->execute()) {
                            throw new Exception($stmt->error ?: $conn->error);
                        }
                        $stmt->close();
                        $created++;
                    }
                }
            }
            $conn->commit();
            $msg = $created > 0
                ? 'Đã mở ' . $created . ' lớp học phần từ CTĐT cho ' . $cohortsHandled . ' khóa áp dụng, theo ' . $classTotal . ' lớp hành chính.'
                : 'Không có môn CTĐT cần mở cho học kỳ đã chọn, hoặc các môn/lớp đã có lớp học phần.';
            if (!empty($skipped)) $msg .= ' Bỏ qua ' . count($skipped) . ' môn do giảng viên không phù hợp.';
            $_SESSION['_flash'] = ['type'=>$created > 0 ? 'success' : 'warning','message'=>$msg];
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Lỗi mở lớp từ CTĐT: ' . $e->getMessage()];
        }
        header('Location: ' . academicCourseSectionsRedirectTarget('course_sections.php?semester_id=' . $semesterId)); exit();
    }
    if ($action === 'auto_schedule_sections') {
        $semesterId = (int)($_POST['semester_id'] ?? 0);
        if ($semesterId <= 0) {
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Vui lòng chọn học kỳ cần xếp lịch/phòng.'];
            header('Location: ' . academicCourseSectionsRedirectTarget()); exit();
        }

        $hasSectionClassId = academicPolicyColumnExists($conn, 'course_sections', 'class_id');
        $classIdSelect = $hasSectionClassId ? 'cs.class_id,' : 'NULL AS class_id,';
        $classOrderSql = $hasSectionClassId ? 'cs.class_id,' : '';
        $stmtSections = $conn->prepare(
            "SELECT cs.id, cs.subject_id, cs.teacher_id, cs.semester_id, cs.target_cohort_id,
                    $classIdSelect cs.section_code, cs.max_students, cs.status, cs.data_mode,
                    cs.room_requirement, cs.day_sessions, cs.start_date, cs.end_date,
                    COALESCE(NULLIF(cs.teaching_mode, ''), 'offline') AS teaching_mode,
                    COALESCE(cs.room, '') AS room
             FROM course_sections cs
             WHERE cs.semester_id = ?
               AND cs.status IN ('draft','proposed','open')
               AND (
                    cs.day_sessions IS NULL OR cs.day_sessions = ''
                    OR ((cs.teaching_mode IS NULL OR cs.teaching_mode <> 'online') AND (cs.room IS NULL OR cs.room = ''))
               )
             ORDER BY cs.target_cohort_id, $classOrderSql cs.subject_id, cs.id
             LIMIT 300"
        );
        $stmtSections->bind_param('i', $semesterId);
        $stmtSections->execute();
        $sectionsToSchedule = $stmtSections->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtSections->close();

        if (empty($sectionsToSchedule)) {
            $_SESSION['_flash'] = ['type'=>'info','message'=>'Không có lớp học phần nào đang thiếu lịch/phòng trong học kỳ đã chọn.'];
            header('Location: ' . academicCourseSectionsRedirectTarget('course_sections.php?semester_id=' . $semesterId)); exit();
        }

        $scheduled = 0;
        $failed = [];
        $conn->begin_transaction();
        try {
            foreach ($sectionsToSchedule as $sectionRow) {
                $sectionId = (int)$sectionRow['id'];
                $mode = (string)($sectionRow['teaching_mode'] ?: 'offline');
                $maxStudents = max(1, (int)($sectionRow['max_students'] ?? 70));
                $plan = RoomSchedulingService::planSectionOpening($conn, $sectionRow, $maxStudents, $mode, '', '');
                if (!$plan['ok']) {
                    $failed[] = (string)$sectionRow['section_code'];
                    continue;
                }

                $daySession = (string)$plan['day_sessions'];
                $startDate = (string)$plan['start_date'];
                $endDate = (string)$plan['end_date'];
                $room = $mode === 'online' ? '' : (string)$plan['room'];
                $classroomId = $mode === 'online' ? null : ($plan['classroom_id'] ?? null);
                $stmtUpdate = $conn->prepare(
                    "UPDATE course_sections
                     SET day_sessions=?, start_date=?, end_date=?, room=?, classroom_id=?, teaching_mode=?
                     WHERE id=?"
                );
                $stmtUpdate->bind_param('ssssisi', $daySession, $startDate, $endDate, $room, $classroomId, $mode, $sectionId);
                if (!$stmtUpdate->execute()) {
                    throw new Exception($stmtUpdate->error ?: $conn->error);
                }
                $stmtUpdate->close();
                $scheduled++;
            }
            $conn->commit();
            $message = 'Đã tự động xếp lịch/phòng cho ' . $scheduled . ' lớp học phần.';
            if (!empty($failed)) {
                $message .= ' Còn ' . count($failed) . ' lớp chưa xếp được do hết ca/phòng phù hợp hoặc trùng lịch.';
            }
            $_SESSION['_flash'] = ['type'=>$scheduled > 0 ? 'success' : 'warning','message'=>$message];
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['_flash'] = ['type'=>'danger','message'=>'Lỗi xếp lịch/phòng hàng loạt: ' . $e->getMessage()];
        }
        header('Location: ' . academicCourseSectionsRedirectTarget('course_sections.php?semester_id=' . $semesterId)); exit();
    }
    if ($action === 'schedule_change') {
        $sectionId = (int)($_POST['section_id'] ?? 0);
        $originalDate = trim($_POST['original_date'] ?? '');
        $newDate = trim($_POST['new_date'] ?? '');
        $newDaySession = trim($_POST['new_day_session'] ?? '');
        $room = trim($_POST['room'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        if ($sectionId && $originalDate && $newDate && $newDaySession) {
            $sectionWindow = $conn->query(
                "SELECT sm.start_date, sm.end_date
                 FROM course_sections cs
                 JOIN semesters sm ON sm.id = cs.semester_id
                 WHERE cs.id = " . (int)$sectionId . " LIMIT 1"
            )->fetch_assoc();
            if (!$sectionWindow
                || $originalDate < $sectionWindow['start_date'] || $originalDate > $sectionWindow['end_date']
                || $newDate < $sectionWindow['start_date'] || $newDate > $sectionWindow['end_date']) {
                $_SESSION['_flash']=['type'=>'danger','message'=>'Ngày gốc và ngày học thay thế phải nằm trong thời gian bắt đầu và kết thúc học kỳ.'];
                header('Location: ' . academicCourseSectionsRedirectTarget()); exit();
            }
            academicEnsureScheduleChangesTable($conn);
            $demoContext = academicPolicySectionDemoContext($conn, $sectionId);
            $stmt = $conn->prepare(
                "INSERT INTO course_section_schedule_changes
                 (course_section_id, original_date, new_date, new_day_session, room, reason, data_mode, demo_batch_id, approved_by)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            );
            $stmt->bind_param('isssssssi', $sectionId, $originalDate, $newDate, $newDaySession, $room, $reason, $demoContext['data_mode'], $demoContext['demo_batch_id'], $userId);
            $stmt->execute()
                ? $_SESSION['_flash']=['type'=>'success','message'=>'Đã cập nhật lịch đổi cho buổi học. Lịch gốc của lớp không thay đổi.']
                : $_SESSION['_flash']=['type'=>'danger','message'=>'Loi: '.$conn->error];
            $stmt->close();
        } else {
            $_SESSION['_flash']=['type'=>'danger','message'=>'Vui lòng nhập đủ ngày gốc, ngày học bù và buổi học mới.'];
        }
        header('Location: ' . academicCourseSectionsRedirectTarget()); exit();
    }
    if ($action === 'add') {
        $subjectId   = (int)($_POST['subject_id'] ?? 0);
        $teacherId   = (int)($_POST['teacher_id'] ?? 0) ?: null;
        $semesterId  = (int)($_POST['semester_id'] ?? 0);
        $targetCohortId = (int)($_POST['target_cohort_id'] ?? 0) ?: null;
        $code        = trim($_POST['section_code'] ?? '');
        $room        = trim($_POST['room'] ?? '');
        $maxStudents = max(1,(int)($_POST['max_students'] ?? 40));
        $status      = trim($_POST['status'] ?? 'open');
        $daySession  = trim($_POST['day_sessions'] ?? '');
        $startDate   = trim($_POST['start_date'] ?? '') ?: null;
        $endDate     = trim($_POST['end_date'] ?? '') ?: null;
        $mode        = trim($_POST['teaching_mode'] ?? 'offline');
        if ($subjectId && $semesterId && $code) {
            $demoContext = academicPolicySemesterDemoContext($conn, $semesterId);
            if ($teacherId) {
                $assignmentCheck = TeacherAssignmentService::validate($conn, $teacherId, $subjectId, $semesterId);
                if (!$assignmentCheck['ok']) {
                    $_SESSION['_flash'] = ['type'=>'danger','message'=>$assignmentCheck['message']];
                    header('Location: ' . academicCourseSectionsRedirectTarget()); exit();
                }
            }
            $scheduleCheck = $daySession !== ''
                ? RoomSchedulingService::validateTeachingSchedule($mode, $daySession)
                : ['ok' => true, 'message' => ''];
            if (!$scheduleCheck['ok']) {
                $_SESSION['_flash'] = ['type'=>'danger','message'=>$scheduleCheck['message']];
                header('Location: ' . academicCourseSectionsRedirectTarget()); exit();
            }
            $plan = RoomSchedulingService::planSectionOpening($conn, [
                'id' => 0,
                'subject_id' => $subjectId,
                'semester_id' => $semesterId,
                'target_cohort_id' => $targetCohortId,
                'section_code' => $code,
                'teacher_id' => $teacherId,
                'data_mode' => $demoContext['data_mode'],
            ], $maxStudents, $mode, $daySession, $room);
            if (!$plan['ok']) {
                $_SESSION['_flash'] = ['type'=>'danger','message'=>$plan['message']];
                header('Location: ' . academicCourseSectionsRedirectTarget()); exit();
            }
            $daySession = (string)$plan['day_sessions'];
            $startDate = (string)$plan['start_date'];
            $endDate = (string)$plan['end_date'];
            $room = (string)$plan['room'];
            $classroomId = null;
            if ($mode === 'online') {
                $room = '';
            } else {
                $availableRooms = RoomSchedulingService::findAvailableClassrooms($conn, 0, $semesterId, $subjectId, $maxStudents, $mode, $daySession, '', $startDate, $endDate);
                if (empty($availableRooms)) {
                    $_SESSION['_flash'] = ['type'=>'danger','message'=>'Không còn phòng học trống và phù hợp với lịch/sĩ số/môn học đã chọn.'];
                    header('Location: ' . academicCourseSectionsRedirectTarget()); exit();
                }
                if ($room === '') {
                    $room = (string)$availableRooms[0]['room_code'];
                    $classroomId = (int)$availableRooms[0]['id'];
                } else {
                    $selectedRoom = null;
                    foreach ($availableRooms as $availableRoom) {
                        if ((string)$availableRoom['room_code'] === $room) {
                            $selectedRoom = $availableRoom;
                            break;
                        }
                    }
                    if (!$selectedRoom) {
                        $_SESSION['_flash'] = ['type'=>'danger','message'=>'Phòng đã chọn không trống hoặc không phù hợp. Vui lòng chọn phòng từ danh mục phòng trống.'];
                        header('Location: ' . academicCourseSectionsRedirectTarget()); exit();
                    }
                    $classroomId = (int)$selectedRoom['id'];
                }
            }
            if ($targetCohortId) {
                $stmtCohort = $conn->prepare(
                    "SELECT tc.id
                     FROM training_cohorts tc
                     JOIN curriculum cur ON cur.major_id = tc.major_id AND cur.subject_id = ? AND cur.deleted_at IS NULL
                     WHERE tc.id = ? LIMIT 1"
                );
                $stmtCohort->bind_param('ii', $subjectId, $targetCohortId);
                $stmtCohort->execute();
                $validCohort = $stmtCohort->get_result()->num_rows > 0;
                $stmtCohort->close();
                if (!$validCohort) {
                    $_SESSION['_flash'] = ['type'=>'danger','message'=>'Khóa/ngành được chọn không có môn này trong CTĐT.'];
                    header('Location: ' . academicCourseSectionsRedirectTarget()); exit();
                }
            }
            $stmt = $conn->prepare("INSERT INTO course_sections (subject_id,teacher_id,semester_id,target_cohort_id,section_code,room,classroom_id,max_students,current_students,status,data_mode,demo_batch_id,day_sessions,start_date,end_date,teaching_mode) VALUES (?,?,?,?,?,?,?,?,0,?,?,?,?,?,?,?)");
            $stmt->bind_param('iiiissiisssssss', $subjectId,$teacherId,$semesterId,$targetCohortId,$code,$room,$classroomId,$maxStudents,$status,$demoContext['data_mode'],$demoContext['demo_batch_id'],$daySession,$startDate,$endDate,$mode);
            $stmt->execute() ? $_SESSION['_flash']=['type'=>'success','message'=>'Thêm lớp học phần thành công.']
                             : $_SESSION['_flash']=['type'=>'danger','message'=>'Loi: '.$conn->error];
            $stmt->close();
        } else { $_SESSION['_flash']=['type'=>'danger','message'=>'Vui lòng điền đầy đủ thông tin.']; }
        header('Location: ' . academicCourseSectionsRedirectTarget()); exit();
    }
    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $teacherId   = (int)($_POST['teacher_id'] ?? 0) ?: null;
        $room        = trim($_POST['room'] ?? '');
        $maxStudents = max(1,(int)($_POST['max_students'] ?? 40));
        $status      = trim($_POST['status'] ?? 'open');
        $daySession  = trim($_POST['day_sessions'] ?? '');
        $startDate   = trim($_POST['start_date'] ?? '') ?: null;
        $endDate     = trim($_POST['end_date'] ?? '') ?: null;
        $mode        = trim($_POST['teaching_mode'] ?? 'offline');
        if ($id) {
            $stmtSection = $conn->prepare("SELECT id, subject_id, semester_id, target_cohort_id, section_code, data_mode, room_requirement FROM course_sections WHERE id = ? LIMIT 1");
            $stmtSection->bind_param('i', $id);
            $stmtSection->execute();
            $sectionForRoom = $stmtSection->get_result()->fetch_assoc();
            $stmtSection->close();
            if (!$sectionForRoom) {
                $_SESSION['_flash'] = ['type'=>'danger','message'=>'Không tìm thấy lớp học phần.'];
                header('Location: ' . academicCourseSectionsRedirectTarget()); exit();
            }
            if ($teacherId) {
                $assignmentCheck = TeacherAssignmentService::validateForSection($conn, $teacherId, $id);
                if (!$assignmentCheck['ok']) {
                    $_SESSION['_flash'] = ['type'=>'danger','message'=>$assignmentCheck['message']];
                    header('Location: ' . academicCourseSectionsRedirectTarget()); exit();
                }
            }
            $scheduleCheck = $daySession !== ''
                ? RoomSchedulingService::validateTeachingSchedule($mode, $daySession)
                : ['ok' => true, 'message' => ''];
            if (!$scheduleCheck['ok']) {
                $_SESSION['_flash'] = ['type'=>'danger','message'=>$scheduleCheck['message']];
                header('Location: ' . academicCourseSectionsRedirectTarget()); exit();
            }
            $sectionForPlan = $sectionForRoom;
            $sectionForPlan['teacher_id'] = $teacherId;
            $plan = RoomSchedulingService::planSectionOpening($conn, $sectionForPlan, $maxStudents, $mode, $daySession, $room);
            if (!$plan['ok']) {
                $_SESSION['_flash'] = ['type'=>'danger','message'=>$plan['message']];
                header('Location: ' . academicCourseSectionsRedirectTarget()); exit();
            }
            $daySession = (string)$plan['day_sessions'];
            $startDate = (string)$plan['start_date'];
            $endDate = (string)$plan['end_date'];
            $room = (string)$plan['room'];
            $classroomId = null;
            if ($mode === 'online') {
                $room = '';
            } else {
                $availableRooms = RoomSchedulingService::findAvailableClassrooms(
                    $conn,
                    $id,
                    (int)$sectionForRoom['semester_id'],
                    (int)$sectionForRoom['subject_id'],
                    $maxStudents,
                    $mode,
                    $daySession,
                    (string)($sectionForRoom['room_requirement'] ?? ''),
                    $startDate,
                    $endDate
                );
                if (empty($availableRooms)) {
                    $_SESSION['_flash'] = ['type'=>'danger','message'=>'Không còn phòng học trống và phù hợp với lịch/sĩ số/môn học đã chọn.'];
                    header('Location: ' . academicCourseSectionsRedirectTarget()); exit();
                }
                if ($room === '') {
                    $room = (string)$availableRooms[0]['room_code'];
                    $classroomId = (int)$availableRooms[0]['id'];
                } else {
                    $selectedRoom = null;
                    foreach ($availableRooms as $availableRoom) {
                        if ((string)$availableRoom['room_code'] === $room) {
                            $selectedRoom = $availableRoom;
                            break;
                        }
                    }
                    if (!$selectedRoom) {
                        $_SESSION['_flash'] = ['type'=>'danger','message'=>'Phòng đã chọn không trống hoặc không phù hợp. Vui lòng chọn phòng từ danh mục phòng trống.'];
                        header('Location: ' . academicCourseSectionsRedirectTarget()); exit();
                    }
                    $classroomId = (int)$selectedRoom['id'];
                }
            }
            $stmt = $conn->prepare("UPDATE course_sections SET teacher_id=?,room=?,classroom_id=?,max_students=?,status=?,day_sessions=?,start_date=?,end_date=?,teaching_mode=? WHERE id=?");
            $stmt->bind_param('isiisssssi', $teacherId,$room,$classroomId,$maxStudents,$status,$daySession,$startDate,$endDate,$mode,$id);
            $stmt->execute() ? $_SESSION['_flash']=['type'=>'success','message'=>'Cap nhat thanh cong.']
                             : $_SESSION['_flash']=['type'=>'danger','message'=>'Loi: '.$conn->error];
            $stmt->close();
        }
        header('Location: ' . academicCourseSectionsRedirectTarget()); exit();
    }
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $chk = $conn->query("SELECT COUNT(*) AS c FROM student_subjects WHERE course_section_id=$id")->fetch_assoc()['c'];
            if ($chk > 0) { $_SESSION['_flash']=['type'=>'danger','message'=>'Không thể xóa: có sinh viên đã đăng ký.']; }
            else {
                $stmt = $conn->prepare("DELETE FROM course_sections WHERE id=?");
                $stmt->bind_param('i',$id); $stmt->execute(); $stmt->close();
                $_SESSION['_flash']=['type'=>'success','message'=>'Đã xóa lớp học phần.'];
            }
        }
        header('Location: ' . academicCourseSectionsRedirectTarget()); exit();
    }
}

$flash = getFlash();
$filterSem     = (int)($_GET['semester_id'] ?? 0);
$filterYear    = (int)($_GET['enrollment_year'] ?? 0);
$filterFaculty = (int)($_GET['faculty_id'] ?? 0);
$filterMajor   = (int)($_GET['major_id'] ?? 0);
$filterClassRaw = trim((string)($_GET['class_filter'] ?? ($_GET['class_id'] ?? '0')));
$filterClass = 0;
$filterCohort = 0;
$filterCommonSections = false;
if ($filterClassRaw === 'common') {
    $filterCommonSections = true;
} elseif (str_starts_with($filterClassRaw, 'class:')) {
    $filterClass = (int)substr($filterClassRaw, 6);
} elseif (str_starts_with($filterClassRaw, 'cohort:')) {
    $filterCohort = (int)substr($filterClassRaw, 7);
} else {
    $filterClass = (int)$filterClassRaw;
    $filterClassRaw = $filterClass > 0 ? ('class:' . $filterClass) : '0';
}
$filterStatus  = trim($_GET['status'] ?? '');
$filterSectionType = trim($_GET['section_type'] ?? 'all');
if (!in_array($filterSectionType, ['all','ctdt','common','cohort_common','admin_class','manual'], true)) $filterSectionType = 'all';
$filterOps = trim($_GET['ops'] ?? 'all');
if (!in_array($filterOps, ['all','unscheduled','no_schedule','no_room','no_teacher','full','has_students'], true)) $filterOps = 'all';
$search        = trim($_GET['q'] ?? '');
$viewStudentsSectionId = (int)($_GET['view_students'] ?? 0);
$page          = max(1,(int)($_GET['page'] ?? 1));
$perPage       = 20;
$hasCourseSectionClassId = academicPolicyColumnExists($conn, 'course_sections', 'class_id');

if ($filterSem === 0) { $a = getActiveSemesterAcademic($conn); if ($a) $filterSem = (int)$a['id']; }
$unscheduledCount = 0;
if ($filterSem > 0) {
    $stmtUnscheduled = $conn->prepare(
        "SELECT COUNT(*) AS c
         FROM course_sections cs
         WHERE cs.semester_id = ?
           AND cs.status IN ('draft','proposed','open')
           AND (
                cs.day_sessions IS NULL OR cs.day_sessions = ''
                OR ((cs.teaching_mode IS NULL OR cs.teaching_mode <> 'online') AND (cs.room IS NULL OR cs.room = ''))
           )"
    );
    $stmtUnscheduled->bind_param('i', $filterSem);
    $stmtUnscheduled->execute();
    $unscheduledCount = (int)($stmtUnscheduled->get_result()->fetch_assoc()['c'] ?? 0);
    $stmtUnscheduled->close();
}
$selectedSemesterRow = null;
if ($filterSem > 0) {
    $semStmt = $conn->prepare("SELECT id, semester_name, school_year, data_mode FROM semesters WHERE id = ? LIMIT 1");
    $semStmt->bind_param('i', $filterSem);
    $semStmt->execute();
    $selectedSemesterRow = $semStmt->get_result()->fetch_assoc();
    $semStmt->close();
}
$filterEnrollmentYear = $selectedSemesterRow ? academicSchoolYearStart($selectedSemesterRow['school_year'] ?? '') : 0;
$selectedDataMode = ($selectedSemesterRow && academicPolicyIsTestSemester($selectedSemesterRow)) ? 'test' : 'system';
$currentFilterParams = [
    'semester_id' => $filterSem,
    'enrollment_year' => $filterYear,
    'faculty_id' => $filterFaculty,
    'major_id' => $filterMajor,
    'class_filter' => $filterClassRaw,
    'section_type' => $filterSectionType,
    'ops' => $filterOps,
    'status' => $filterStatus,
    'q' => $search,
    'page' => $page,
];
$currentRedirect = 'course_sections.php?' . http_build_query($currentFilterParams);

$where = ['1=1']; $types = ''; $params = [];
$classFilterJoinSql = $hasCourseSectionClassId ? "LEFT JOIN classes cl_filter ON cs.class_id=cl_filter.id" : "";
$filterYearExpr = $hasCourseSectionClassId ? 'COALESCE(tc.enrollment_year, cl_filter.enrollment_year)' : 'tc.enrollment_year';
if ($filterSem > 0)    { $where[] = 'cs.semester_id=?'; $types .= 'i'; $params[] = $filterSem; }
if ($filterYear > 0)   { $where[] = "$filterYearExpr=?"; $types .= 'i'; $params[] = $filterYear; }
if ($filterFaculty > 0){ $where[] = 'COALESCE(tf.id, f.id)=?'; $types .= 'i'; $params[] = $filterFaculty; }
if ($filterMajor > 0)  { $where[] = 'COALESCE(tm.id, m.id)=?'; $types .= 'i'; $params[] = $filterMajor; }
if ($filterClass > 0 && $hasCourseSectionClassId) {
    $where[] = "cs.class_id=?";
    $types .= 'i';
    $params[] = $filterClass;
} elseif ($filterCohort > 0) {
    $where[] = "cs.class_id IS NULL AND cs.target_cohort_id=?";
    $types .= 'i';
    $params[] = $filterCohort;
} elseif ($filterCommonSections && $hasCourseSectionClassId) {
    $where[] = "cs.class_id IS NULL AND cs.target_cohort_id IS NULL";
}
if ($filterSectionType === 'ctdt') {
    $where[] = $hasCourseSectionClassId
        ? "(cs.target_cohort_id IS NOT NULL OR cs.class_id IS NOT NULL)"
        : "cs.target_cohort_id IS NOT NULL";
} elseif ($filterSectionType === 'common') {
    $where[] = $hasCourseSectionClassId
        ? "s.is_common = 1 AND cs.target_cohort_id IS NULL AND cs.class_id IS NULL"
        : "s.is_common = 1 AND cs.target_cohort_id IS NULL";
} elseif ($filterSectionType === 'cohort_common') {
    $where[] = $hasCourseSectionClassId
        ? "cs.target_cohort_id IS NOT NULL AND cs.class_id IS NULL"
        : "cs.target_cohort_id IS NOT NULL";
} elseif ($filterSectionType === 'admin_class' && $hasCourseSectionClassId) {
    $where[] = "cs.class_id IS NOT NULL";
} elseif ($filterSectionType === 'manual') {
    $where[] = $hasCourseSectionClassId
        ? "COALESCE(s.is_common,0) = 0 AND cs.target_cohort_id IS NULL AND cs.class_id IS NULL"
        : "COALESCE(s.is_common,0) = 0 AND cs.target_cohort_id IS NULL";
}
if ($filterOps === 'unscheduled') {
    $where[] = "(cs.day_sessions IS NULL OR cs.day_sessions = '' OR ((cs.teaching_mode IS NULL OR cs.teaching_mode <> 'online') AND (cs.room IS NULL OR cs.room = '')))";
} elseif ($filterOps === 'no_schedule') {
    $where[] = "(cs.day_sessions IS NULL OR cs.day_sessions = '')";
} elseif ($filterOps === 'no_room') {
    $where[] = "((cs.teaching_mode IS NULL OR cs.teaching_mode <> 'online') AND (cs.room IS NULL OR cs.room = ''))";
} elseif ($filterOps === 'no_teacher') {
    $where[] = "cs.teacher_id IS NULL";
} elseif ($filterOps === 'full') {
    $where[] = "cs.max_students > 0 AND cs.current_students >= cs.max_students";
} elseif ($filterOps === 'has_students') {
    $where[] = "cs.current_students > 0";
}
if ($filterStatus !== ''){ $where[] = 'cs.status=?'; $types .= 's'; $params[] = $filterStatus; }
if ($search !== '')    { $where[] = '(s.subject_name LIKE ? OR cs.section_code LIKE ?)'; $like="%$search%"; $types .= 'ss'; $params[] = $like; $params[] = $like; }
$where[] = "cs.data_mode = COALESCE((SELECT data_mode FROM semesters WHERE id = cs.semester_id LIMIT 1), 'system')";
$whereSQL = implode(' AND ', $where);

$stmtCnt = $conn->prepare("SELECT COUNT(*) AS c
FROM course_sections cs
JOIN subjects s ON cs.subject_id=s.id
LEFT JOIN majors m ON s.major_id=m.id
LEFT JOIN faculties f ON m.faculty_id=f.id
LEFT JOIN training_cohorts tc ON cs.target_cohort_id=tc.id
$classFilterJoinSql
LEFT JOIN majors tm ON tc.major_id=tm.id
LEFT JOIN faculties tf ON tm.faculty_id=tf.id
WHERE $whereSQL");
if ($types) $stmtCnt->bind_param($types,...$params); $stmtCnt->execute();
$total = (int)($stmtCnt->get_result()->fetch_assoc()['c'] ?? 0); $stmtCnt->close();
$pag = paginateAcademic($total, $page, $perPage);

$classSelectSql = $hasCourseSectionClassId
    ? "cl.class_code AS target_class_code, cl.class_name AS target_class_name,"
    : "NULL AS target_class_code, NULL AS target_class_name,";
$classJoinSql = $hasCourseSectionClassId ? "LEFT JOIN classes cl ON cs.class_id=cl.id" : "";

$stmtData = $conn->prepare("SELECT cs.id, cs.section_code, cs.status, cs.max_students, cs.current_students, cs.room, cs.day_sessions, cs.start_date, cs.end_date, cs.teaching_mode, cs.proposal_status,
       s.subject_name, s.credits, COALESCE(tf.faculty_name, f.faculty_name) AS faculty_name,
       tc.cohort_code, tc.enrollment_year, tc.duration_years, tm.major_name AS target_major_name,
       $classSelectSql
       ut.full_name AS teacher_name, t.teacher_code,
       upt.full_name AS proposed_teacher_name, pt.teacher_code AS proposed_teacher_code,
       sm.semester_name
FROM course_sections cs JOIN subjects s ON cs.subject_id=s.id
JOIN semesters sm ON cs.semester_id=sm.id
LEFT JOIN majors m ON s.major_id=m.id LEFT JOIN faculties f ON m.faculty_id=f.id
LEFT JOIN training_cohorts tc ON cs.target_cohort_id=tc.id
$classFilterJoinSql
LEFT JOIN majors tm ON tc.major_id=tm.id
LEFT JOIN faculties tf ON tm.faculty_id=tf.id
$classJoinSql
LEFT JOIN teachers t ON cs.teacher_id=t.id LEFT JOIN users ut ON t.user_id=ut.id
LEFT JOIN teachers pt ON cs.proposed_teacher_id=pt.id LEFT JOIN users upt ON pt.user_id=upt.id
WHERE $whereSQL ORDER BY f.faculty_name, s.subject_name LIMIT ? OFFSET ?");
$allTypes = $types.'ii'; $allParams = array_merge($params,[$pag['per_page'],$pag['offset']]);
$stmtData->bind_param($allTypes,...$allParams); $stmtData->execute();
$sections = $stmtData->get_result()->fetch_all(MYSQLI_ASSOC); $stmtData->close();

$subjects  = $conn->query("SELECT id, subject_code, subject_name FROM subjects ORDER BY subject_name")->fetch_all(MYSQLI_ASSOC);
$teachers  = $conn->query("SELECT t.id, t.teacher_code, u.full_name, f.faculty_name FROM teachers t JOIN users u ON t.user_id=u.id LEFT JOIN faculties f ON t.faculty_id=f.id ORDER BY f.faculty_name, u.full_name")->fetch_all(MYSQLI_ASSOC);
$semesters = $conn->query(
    "SELECT id, semester_name, school_year, data_mode
     FROM semesters
     ORDER BY
       (CASE WHEN data_mode = 'test' OR LOWER(semester_name) LIKE '%test%' THEN 0 ELSE 1 END),
       CAST(SUBSTRING(school_year, 1, 4) AS UNSIGNED) DESC,
       (CASE
            WHEN LOWER(semester_name) LIKE '%hè%' OR LOWER(semester_name) LIKE '%he%' THEN 3
            WHEN semester_name REGEXP '[[:<:]]2[[:>:]]' THEN 2
            WHEN semester_name REGEXP '[[:<:]]1[[:>:]]' THEN 1
            ELSE 0
        END) ASC,
       id DESC"
)->fetch_all(MYSQLI_ASSOC);
$faculties = $conn->query("SELECT id, faculty_name FROM faculties ORDER BY faculty_name")->fetch_all(MYSQLI_ASSOC);
$majors    = $conn->query("SELECT m.id, m.major_name, m.faculty_id FROM majors m ORDER BY m.major_name")->fetch_all(MYSQLI_ASSOC);
$filterYears = [];
$yearStmt = $conn->prepare(
    "SELECT enrollment_year
     FROM (
         SELECT DISTINCT enrollment_year
         FROM training_cohorts
         WHERE status IN ('planned','active')
           AND enrollment_year IS NOT NULL
         UNION
         SELECT DISTINCT enrollment_year
         FROM classes
         WHERE COALESCE(data_mode, 'system') = ?
           AND enrollment_year IS NOT NULL
     ) y
     ORDER BY enrollment_year DESC"
);
$yearStmt->bind_param('s', $selectedDataMode);
$yearStmt->execute();
$filterYears = $yearStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$yearStmt->close();
$adminClasses = [];
if ($hasCourseSectionClassId && $filterSem > 0) {
    $classStmt = $conn->prepare(
        "SELECT c.id, c.class_code, c.class_name, c.major_id, c.enrollment_year,
                COUNT(DISTINCT st.id) AS student_count
         FROM classes c
         JOIN course_sections cs
           ON cs.semester_id = ?
          AND cs.data_mode = COALESCE((SELECT data_mode FROM semesters WHERE id = cs.semester_id LIMIT 1), 'system')
          AND cs.class_id = c.id
         LEFT JOIN students st ON st.class_id = c.id
         GROUP BY c.id, c.class_code, c.class_name, c.major_id, c.enrollment_year
         ORDER BY c.class_code"
    );
    $classStmt->bind_param('i', $filterSem);
    $classStmt->execute();
    $adminClasses = $classStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $classStmt->close();
}
$cohortClassFilters = [];
if ($hasCourseSectionClassId && $filterSem > 0) {
    $cohortFilterStmt = $conn->prepare(
        "SELECT DISTINCT tc.id AS cohort_id, tc.major_id, tc.enrollment_year, tc.cohort_code, m.major_name,
                COUNT(DISTINCT cs.id) AS section_count
         FROM course_sections cs
         JOIN training_cohorts tc ON tc.id = cs.target_cohort_id
         JOIN majors m ON m.id = tc.major_id
         WHERE cs.semester_id = ?
           AND cs.class_id IS NULL
           AND cs.target_cohort_id IS NOT NULL
           AND cs.data_mode = COALESCE((SELECT data_mode FROM semesters WHERE id = cs.semester_id LIMIT 1), 'system')
         GROUP BY tc.id, tc.major_id, tc.enrollment_year, tc.cohort_code, m.major_name
         ORDER BY m.major_name, tc.cohort_code"
    );
    $cohortFilterStmt->bind_param('i', $filterSem);
    $cohortFilterStmt->execute();
    $cohortClassFilters = $cohortFilterStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $cohortFilterStmt->close();
}
$cohorts   = $conn->query("SELECT tc.id, tc.cohort_code, tc.enrollment_year, tc.duration_years, tc.program_id, m.id AS major_id, m.major_name, f.id AS faculty_id, f.faculty_name FROM training_cohorts tc JOIN majors m ON tc.major_id=m.id LEFT JOIN faculties f ON m.faculty_id=f.id WHERE tc.status IN ('planned','active') ORDER BY f.faculty_name, m.major_name, tc.enrollment_year DESC")->fetch_all(MYSQLI_ASSOC);
$importMajors = [];
foreach ($cohorts as $cohort) {
    $majorId = (int)$cohort['major_id'];
    if (!isset($importMajors[$majorId])) {
        $importMajors[$majorId] = [
            'major_id' => $majorId,
            'major_name' => (string)$cohort['major_name'],
            'faculty_id' => (int)($cohort['faculty_id'] ?? 0),
            'faculty_name' => (string)($cohort['faculty_name'] ?? 'Khác'),
            'cohort_count' => 0,
        ];
    }
    $importMajors[$majorId]['cohort_count']++;
}
$cohortClasses = [];
$majorClasses = [];
$cohortClassResult = $conn->query(
    "SELECT tc.id AS cohort_id, tc.major_id, c.id, c.class_code, c.class_name, COALESCE(c.data_mode, 'system') AS data_mode
     FROM training_cohorts tc
     JOIN classes c ON c.major_id = tc.major_id AND c.enrollment_year = tc.enrollment_year
     WHERE tc.status IN ('planned','active')
     ORDER BY c.class_code, c.id"
);
if ($cohortClassResult) {
    while ($row = $cohortClassResult->fetch_assoc()) {
        $majorId = (int)$row['major_id'];
        $classId = (int)$row['id'];
        $cohortClasses[(int)$row['cohort_id']][] = [
            'id' => $classId,
            'class_code' => (string)$row['class_code'],
            'class_name' => (string)$row['class_name'],
        ];
        $majorClasses[$majorId][(string)$row['data_mode']][] = [
            'id' => $classId,
            'class_code' => (string)$row['class_code'],
            'class_name' => (string)$row['class_name'],
        ];
    }
}

$statusLabels = ['open'=>['success','Mở'],'proposed'=>['warning','Đề xuất'],'draft'=>['secondary','Nháp'],'full'=>['info','Đầy'],'closed'=>['dark','Đóng'],'cancelled'=>['danger','Hủy']];
$sectionTypeLabels = [
    'all' => 'Tất cả loại lớp',
    'ctdt' => 'Lớp theo CTĐT',
    'common' => 'HP chung toàn ngành',
    'cohort_common' => 'Lớp chung theo khóa',
    'admin_class' => 'Lớp hành chính',
    'manual' => 'Lớp mở tay',
];
$opsLabels = [
    'all' => 'Tất cả tình trạng',
    'unscheduled' => 'Thiếu lịch hoặc phòng',
    'no_schedule' => 'Chưa có lịch',
    'no_room' => 'Chưa có phòng',
    'no_teacher' => 'Chưa phân GV',
    'full' => 'Đã đầy',
    'has_students' => 'Có sinh viên đăng ký',
];
$modeLabels   = ['offline'=>'Offline','online'=>'Online','hybrid'=>'Hybrid'];

$viewSection = null;
$registeredStudents = [];
if ($viewStudentsSectionId > 0) {
    $sectionStmt = $conn->prepare(
        "SELECT cs.id, cs.section_code, s.subject_code, s.subject_name, sm.semester_name, sm.school_year
         FROM course_sections cs
         JOIN subjects s ON s.id = cs.subject_id
         JOIN semesters sm ON sm.id = cs.semester_id
         WHERE cs.id = ? LIMIT 1"
    );
    $sectionStmt->bind_param('i', $viewStudentsSectionId);
    $sectionStmt->execute();
    $viewSection = $sectionStmt->get_result()->fetch_assoc();
    $sectionStmt->close();

    if ($viewSection) {
        $regStmt = $conn->prepare(
            "SELECT st.student_code, u.full_name, cl.class_code, cl.class_name, ss.status, ss.register_date
             FROM student_subjects ss
             JOIN students st ON st.id = ss.student_id
             JOIN users u ON u.id = st.user_id
             LEFT JOIN classes cl ON cl.id = st.class_id
             WHERE ss.course_section_id = ?
               AND ss.status IN ('registered','auto_enrolled','completed')
             ORDER BY cl.class_code, u.full_name"
        );
        $regStmt->bind_param('i', $viewStudentsSectionId);
        $regStmt->execute();
        $registeredStudents = $regStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $regStmt->close();
    }
}

include 'includes/header.php'; include 'includes/sidebar.php';
?>
<div class="admin-main">
<div class="admin-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
        <span class="admin-topbar-title"><i class="bi bi-grid-3x3-gap-fill me-2 text-navy"></i>Quản lý Lớp học phần</span>
    </div>
    <div class="d-flex gap-2">
        <?php if (isAcademicManager()): ?>
        <button class="btn btn-sm btn-gold" data-bs-toggle="modal" data-bs-target="#importCurriculumModal"><i class="bi bi-journal-plus me-1"></i>Mở lớp từ CTĐT</button>
        <form method="post" action="course_sections.php" class="d-inline" onsubmit="return confirm('Tự động xếp lịch/phòng cho các lớp học phần đang thiếu lịch trong học kỳ này?')">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="auto_schedule_sections">
            <input type="hidden" name="semester_id" value="<?php echo (int)$filterSem; ?>">
            <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($currentRedirect); ?>">
            <button class="btn btn-sm btn-outline-primary" <?php echo $unscheduledCount <= 0 ? 'disabled' : ''; ?>>
                <i class="bi bi-magic me-1"></i>Xếp lịch/phòng <?php if ($unscheduledCount > 0): ?><span class="badge bg-primary ms-1"><?php echo (int)$unscheduledCount; ?></span><?php endif; ?>
            </button>
        </form>
        <button class="btn btn-sm btn-navy" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i>Thêm lớp học phần</button>
        <?php endif; ?>
        <span class="text-muted small align-self-center"><?php echo htmlspecialchars($_SESSION['full_name']??''); ?></span>
    </div>
</div>
<div class="admin-content">
<?php if ($flash): ?><div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show auto-dismiss mb-3"><?php echo htmlspecialchars($flash['message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="card mb-3"><div class="card-body py-3">
<form method="get" class="row g-3 align-items-end">
    <div class="col-12 col-xl-3">
        <div class="border rounded-2 p-2 h-100">
            <div class="text-muted small fw-bold mb-2">Học kỳ & loại lớp</div>
            <div class="row g-2">
                <div class="col-12"><label class="form-label small">Học kỳ</label>
                    <select name="semester_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <?php foreach ($semesters as $sm): ?><option value="<?php echo $sm['id']; ?>" <?php echo $filterSem==$sm['id']?'selected':''; ?>><?php echo htmlspecialchars($sm['semester_name'].' '.$sm['school_year']); ?></option><?php endforeach; ?>
                    </select>
                    <?php if ($selectedSemesterRow): ?><div class="form-text"><?php echo $selectedDataMode === 'test' ? 'Dữ liệu demo/test' : 'Dữ liệu hệ thật'; ?></div><?php endif; ?>
                </div>
                <div class="col-12"><label class="form-label small">Loại lớp</label>
                    <select name="section_type" class="form-select form-select-sm">
                        <?php foreach ($sectionTypeLabels as $k => $label): ?><option value="<?php echo $k; ?>" <?php echo $filterSectionType===$k?'selected':''; ?>><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="border rounded-2 p-2 h-100">
            <div class="text-muted small fw-bold mb-2">Phạm vi đào tạo</div>
            <div class="row g-2">
                <div class="col-6 col-md-3"><label class="form-label small">Khóa</label>
                    <select name="enrollment_year" id="filterYear" class="form-select form-select-sm">
                        <option value="0">-- Tất cả khóa --</option>
                        <?php foreach ($filterYears as $fy): ?>
                        <option value="<?php echo (int)$fy['enrollment_year']; ?>" <?php echo $filterYear===(int)$fy['enrollment_year']?'selected':''; ?>>Khóa <?php echo (int)$fy['enrollment_year']; ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="col-6 col-md-3"><label class="form-label small">Khoa</label>
                    <select name="faculty_id" id="filterFaculty" class="form-select form-select-sm">
                        <option value="0">-- Tất cả --</option>
                        <?php foreach ($faculties as $f): ?><option value="<?php echo $f['id']; ?>" <?php echo $filterFaculty==$f['id']?'selected':''; ?>><?php echo htmlspecialchars($f['faculty_name']); ?></option><?php endforeach; ?>
                    </select></div>
                <div class="col-6 col-md-3"><label class="form-label small">Ngành</label>
                    <select name="major_id" id="filterMajor" class="form-select form-select-sm">
                        <option value="0">-- Chọn khoa trước --</option>
                        <?php foreach ($majors as $m): ?><option value="<?php echo (int)$m['id']; ?>" data-faculty="<?php echo (int)$m['faculty_id']; ?>" <?php echo $filterMajor===(int)$m['id']?'selected':''; ?>><?php echo htmlspecialchars($m['major_name']); ?></option><?php endforeach; ?>
                    </select></div>
                <?php if ($hasCourseSectionClassId): ?>
                <div class="col-6 col-md-3"><label class="form-label small">Phạm vi lớp</label>
                    <select name="class_filter" id="filterClass" class="form-select form-select-sm">
                        <option value="0"><?php echo $filterMajor > 0 ? '-- Tất cả lớp đã mở --' : '-- Chọn ngành trước --'; ?></option>
                        <option value="common" <?php echo $filterCommonSections ? 'selected' : ''; ?>>Lớp HP chung toàn ngành</option>
                        <?php foreach ($cohortClassFilters as $cohortFilter): ?>
                        <option value="cohort:<?php echo (int)$cohortFilter['cohort_id']; ?>"
                                data-major="<?php echo (int)$cohortFilter['major_id']; ?>"
                                data-year="<?php echo (int)$cohortFilter['enrollment_year']; ?>"
                                <?php echo $filterClassRaw === ('cohort:' . (int)$cohortFilter['cohort_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars('Lớp chung ' . $cohortFilter['cohort_code'] . ' - ' . $cohortFilter['major_name'] . ' (' . (int)$cohortFilter['section_count'] . ' lớp HP)'); ?>
                        </option>
                        <?php endforeach; ?>
                        <?php foreach ($adminClasses as $cl): ?><option value="class:<?php echo (int)$cl['id']; ?>" data-major="<?php echo (int)$cl['major_id']; ?>" data-year="<?php echo (int)$cl['enrollment_year']; ?>" <?php echo $filterClassRaw === ('class:' . (int)$cl['id'])?'selected':''; ?>><?php echo htmlspecialchars($cl['class_code'] . ' - ' . $cl['class_name'] . ' (' . (int)($cl['student_count'] ?? 0) . ' SV)'); ?></option><?php endforeach; ?>
                    </select></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
        $hasVisibleClassOption = false;
        if ($filterMajor > 0) {
            foreach ($adminClasses as $cl) {
                if ((int)$cl['major_id'] === $filterMajor) { $hasVisibleClassOption = true; break; }
            }
            foreach ($cohortClassFilters as $cf) {
                if ((int)$cf['major_id'] === $filterMajor) { $hasVisibleClassOption = true; break; }
            }
        }
    ?>
    <?php if ($hasCourseSectionClassId && $filterMajor > 0 && !$hasVisibleClassOption): ?>
    <div class="col-12"><small class="text-warning">Học kỳ này chưa có lớp học phần nào được mở cho ngành đã chọn.</small></div>
    <?php endif; ?>
    <div class="col-12 col-xl-4">
        <div class="border rounded-2 p-2 h-100">
            <div class="text-muted small fw-bold mb-2">Tình trạng & tìm kiếm</div>
            <div class="row g-2 align-items-end">
                <div class="col-6"><label class="form-label small">Trạng thái</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">-- Tất cả --</option>
                        <?php foreach ($statusLabels as $k=>[$c,$l]): ?><option value="<?php echo $k; ?>" <?php echo $filterStatus===$k?'selected':''; ?>><?php echo $l; ?></option><?php endforeach; ?>
                    </select></div>
                <div class="col-6"><label class="form-label small">Tình trạng vận hành</label>
                    <select name="ops" class="form-select form-select-sm">
                        <?php foreach ($opsLabels as $k => $label): ?><option value="<?php echo $k; ?>" <?php echo $filterOps===$k?'selected':''; ?>><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?>
                    </select></div>
                <div class="col-8"><label class="form-label small">Tìm kiếm</label>
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="Tên môn, mã lớp, giảng viên..." value="<?php echo htmlspecialchars($search); ?>"></div>
                <div class="col-4 d-flex gap-1"><button type="submit" class="btn btn-sm btn-navy flex-fill"><i class="bi bi-search"></i></button>
                    <a href="course_sections.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a></div>
            </div>
        </div>
    </div>
</form></div></div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-grid-3x3-gap-fill me-2"></i>Lớp học phần <span class="badge bg-light text-dark ms-1"><?php echo number_format($total); ?></span></span>
    </div>
    <?php if (empty($sections)): ?>
    <div class="card-body text-center text-muted py-4"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Không có dữ liệu.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>Mã lớp / Môn học</th><th>Khoa</th><th>Dành cho</th><th>Giảng viên</th><th class="text-center">Sĩ số</th><th>Lịch học</th><th>Hình thức</th><th>Trạng thái</th><th class="text-center">Thao tác</th></tr></thead>
            <tbody>
            <?php foreach ($sections as $cs):
                [$sColor,$sLabel] = $statusLabels[$cs['status']] ?? ['secondary',$cs['status']];
                $pct = $cs['max_students'] > 0 ? round($cs['current_students']/$cs['max_students']*100) : 0;
            ?>
            <tr>
                <td><div class="fw-semibold small"><?php echo htmlspecialchars($cs['section_code']); ?></div>
                    <small class="text-muted"><?php echo htmlspecialchars($cs['subject_name']); ?> · <?php echo $cs['credits']; ?> TC</small></td>
                <td class="small"><?php echo htmlspecialchars($cs['faculty_name']??'—'); ?></td>
                <td class="small">
                    <?php if (!empty($cs['cohort_code'])): ?>
                    <span class="fw-semibold"><?php echo htmlspecialchars($cs['cohort_code']); ?></span><br>
                    <span class="text-muted"><?php echo htmlspecialchars($cs['target_major_name'] ?? ''); ?></span>
                    <?php if (!empty($cs['target_class_code'])): ?>
                    <br><span class="badge bg-light text-dark"><?php echo htmlspecialchars($cs['target_class_code']); ?></span>
                    <?php endif; ?>
                    <?php else: ?>
                    <span class="text-muted">Theo CTĐT ngành</span>
                    <?php endif; ?>
                </td>
                <td class="small">
                    <?php if (!empty($cs['teacher_name'])): ?>
                    <?php echo htmlspecialchars($cs['teacher_name']); ?>
                    <?php elseif (!empty($cs['proposed_teacher_name'])): ?>
                    <span class="fw-semibold"><?php echo htmlspecialchars($cs['proposed_teacher_name']); ?></span><br>
                    <span class="badge bg-info text-dark">Khoa đề xuất</span>
                    <?php else: ?>
                    <span class="text-warning">Chưa có</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <span class="badge bg-<?php echo $pct>=100?'danger':($pct>=80?'warning':'success'); ?>"><?php echo $cs['current_students']; ?>/<?php echo $cs['max_students']; ?></span></td>
                <td class="small text-muted">
                    <?php if (!empty($cs['day_sessions'])): ?>
                    <span class="fw-semibold"><?php echo htmlspecialchars($cs['day_sessions']); ?></span><br>
                    <span><?php echo htmlspecialchars(($cs['start_date'] ?? '') . ' - ' . ($cs['end_date'] ?? '')); ?></span>
                    <?php if (!empty($cs['room'])): ?><br><span>Phòng <?php echo htmlspecialchars($cs['room']); ?></span><?php endif; ?>
                    <?php else: ?>
                    <span class="text-warning">Chưa xếp</span>
                    <?php endif; ?>
                </td>
                <td class="small"><?php echo $modeLabels[$cs['teaching_mode']] ?? 'Offline'; ?></td>
                <td><span class="badge bg-<?php echo $sColor; ?>"><?php echo $sLabel; ?></span>
                    <?php if ($cs['proposal_status']==='pending'): ?><br><span class="badge bg-info" style="font-size:.65rem">Đề xuất GV</span><?php endif; ?></td>
                <td class="text-center">
                    <?php if (isAcademicManager()): ?>
                    <a class="btn btn-xs btn-outline-info" style="font-size:.7rem;padding:2px 6px"
                       href="course_sections.php?<?php echo htmlspecialchars(http_build_query(['semester_id'=>$filterSem,'enrollment_year'=>$filterYear,'faculty_id'=>$filterFaculty,'major_id'=>$filterMajor,'class_filter'=>$filterClassRaw,'section_type'=>$filterSectionType,'ops'=>$filterOps,'status'=>$filterStatus,'q'=>$search,'view_students'=>(int)$cs['id']])); ?>#registeredStudents"
                       title="Xem sinh viên đã đăng ký">
                        <i class="bi bi-people"></i>
                    </a>
                    <button class="btn btn-xs btn-outline-primary" style="font-size:.7rem;padding:2px 6px"
                            data-bs-toggle="modal" data-bs-target="#editModal"
                            data-id="<?php echo $cs['id']; ?>"
                            data-room="<?php echo htmlspecialchars($cs['room']??''); ?>"
                            data-max="<?php echo $cs['max_students']; ?>"
                            data-status="<?php echo $cs['status']; ?>"
                            data-day="<?php echo htmlspecialchars($cs['day_sessions']??''); ?>"
                            data-start="<?php echo $cs['start_date']??''; ?>"
                            data-end="<?php echo $cs['end_date']??''; ?>"
                            data-mode="<?php echo $cs['teaching_mode']??'offline'; ?>">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-xs btn-outline-warning" style="font-size:.7rem;padding:2px 6px"
                            data-bs-toggle="modal" data-bs-target="#changeScheduleModal"
                            data-id="<?php echo $cs['id']; ?>"
                            data-code="<?php echo htmlspecialchars($cs['section_code']); ?>"
                            data-room="<?php echo htmlspecialchars($cs['room']??''); ?>">
                        <i class="bi bi-calendar2-week"></i>
                    </button>
                    <form method="post" class="d-inline" onsubmit="return confirm('Xóa lớp học phần này?')">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $cs['id']; ?>">
                        <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($currentRedirect); ?>">
                        <button class="btn btn-xs btn-outline-danger" style="font-size:.7rem;padding:2px 6px"><i class="bi bi-trash"></i></button>
                    </form>
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
        <?php $qs2=http_build_query(['semester_id'=>$filterSem,'enrollment_year'=>$filterYear,'faculty_id'=>$filterFaculty,'major_id'=>$filterMajor,'class_filter'=>$filterClassRaw,'section_type'=>$filterSectionType,'ops'=>$filterOps,'status'=>$filterStatus,'q'=>$search]); echo renderAcademicPagination($pag,$qs2); ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($viewSection): ?>
<div class="card mt-3" id="registeredStudents">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-people-fill me-2"></i>Sinh viên đã đăng ký <?php echo htmlspecialchars($viewSection['section_code']); ?></span>
        <span class="badge bg-light text-dark"><?php echo number_format(count($registeredStudents)); ?> SV</span>
    </div>
    <div class="card-body py-2 small text-muted">
        <?php echo htmlspecialchars($viewSection['subject_code'] . ' - ' . $viewSection['subject_name'] . ' · ' . $viewSection['semester_name'] . ' ' . $viewSection['school_year']); ?>
    </div>
    <?php if (empty($registeredStudents)): ?>
    <div class="card-body text-center text-muted py-4">Chưa có sinh viên đăng ký lớp học phần này.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Mã SV</th><th>Họ tên</th><th>Lớp hành chính</th><th>Trạng thái</th><th>Ngày đăng ký</th></tr></thead>
            <tbody>
            <?php foreach ($registeredStudents as $st): ?>
            <tr>
                <td><code><?php echo htmlspecialchars($st['student_code']); ?></code></td>
                <td><?php echo htmlspecialchars($st['full_name']); ?></td>
                <td class="small"><?php echo htmlspecialchars(trim(($st['class_code'] ?? '') . ' - ' . ($st['class_name'] ?? ''), ' -')); ?></td>
                <td><span class="badge bg-<?php echo $st['status'] === 'auto_enrolled' ? 'info text-dark' : 'success'; ?>"><?php echo htmlspecialchars($st['status']); ?></span></td>
                <td class="small text-muted"><?php echo htmlspecialchars($st['register_date'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
</div>
<div class="admin-footer">&copy; <?php echo date('Y'); ?> TDMU</div>
</div>

<!-- Modal Mo lop tu CTDT -->
<div class="modal fade" id="importCurriculumModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Tạo lớp học phần theo lớp hành chính</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post" action="course_sections.php"><?php echo csrfField(); ?><input type="hidden" name="action" value="import_curriculum_sections"><input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($currentRedirect); ?>">
    <div class="modal-body"><div class="row g-3">
        <div class="col-md-6"><label class="form-label fw-semibold">Học kỳ cần mở <span class="text-danger">*</span></label>
            <select name="semester_id" id="importSemesterSelect" class="form-select" required>
                <?php foreach ($semesters as $sm): ?><option value="<?php echo (int)$sm['id']; ?>" data-mode="<?php echo htmlspecialchars(academicPolicyIsTestSemester($sm) ? 'test' : 'system'); ?>" <?php echo $filterSem==(int)$sm['id']?'selected':''; ?>><?php echo htmlspecialchars($sm['semester_name'].' '.$sm['school_year']); ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6"><label class="form-label fw-semibold">Khoa/Viện</label>
            <select id="importFacultyFilter" class="form-select">
                <option value="">-- Chọn khoa/viện --</option>
                <?php foreach ($faculties as $f): ?><option value="<?php echo (int)$f['id']; ?>"><?php echo htmlspecialchars($f['faculty_name']); ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-8"><label class="form-label fw-semibold">Ngành áp dụng <span class="text-danger">*</span></label>
            <select name="target_major_id" id="importMajorSelect" class="form-select" required>
                <option value="">-- Chọn ngành --</option>
                <?php $lastGroup=''; foreach ($importMajors as $major): ?>
                    <?php $group = (string)($major['faculty_name'] ?? 'Khác'); ?>
                    <?php if ($group !== $lastGroup): ?>
                        <?php if ($lastGroup !== '') echo '</optgroup>'; ?>
                        <optgroup label="<?php echo htmlspecialchars($group); ?>">
                        <?php $lastGroup = $group; ?>
                    <?php endif; ?>
                    <option value="<?php echo (int)$major['major_id']; ?>"
                            data-faculty="<?php echo (int)($major['faculty_id'] ?? 0); ?>"
                            data-cohorts="<?php echo (int)($major['cohort_count'] ?? 0); ?>"
                            data-classes-system="<?php echo htmlspecialchars(json_encode($majorClasses[(int)$major['major_id']]['system'] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)); ?>"
                            data-classes-test="<?php echo htmlspecialchars(json_encode($majorClasses[(int)$major['major_id']]['test'] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)); ?>">
                        <?php echo htmlspecialchars($major['major_name'] . ' - ' . (int)$major['cohort_count'] . ' khóa áp dụng'); ?>
                    </option>
                <?php endforeach; if ($lastGroup !== '') echo '</optgroup>'; ?>
            </select>
            <div class="form-text">Danh sách môn được lấy theo năm học và học kỳ tương ứng của từng khóa đang áp dụng CTĐT.</div>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Lớp hành chính sẽ mở</label>
            <input id="importClassCountView" class="form-control" value="Chọn ngành trước" readonly>
            <div class="form-text">Mỗi môn trong CTĐT sẽ được tạo một lớp học phần cho từng lớp hành chính.</div>
        </div>
        <input type="hidden" name="class_count" value="1">
        <div class="col-md-4"><label class="form-label fw-semibold">Sĩ số mặc định mỗi lớp</label><input type="number" name="max_students" class="form-control" value="70" min="1"></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Trạng thái</label>
            <select name="status" class="form-select"><option value="open">Mở đăng ký</option><option value="draft">Nháp</option></select>
        </div>
        <div class="col-md-12"><label class="form-label fw-semibold">Giảng viên mặc định</label>
            <select name="teacher_id" class="form-select"><option value="">-- Chưa phân công --</option>
            <?php $lastF=''; foreach ($teachers as $t): if ($t['faculty_name']!==$lastF) { if ($lastF!=='') echo '</optgroup>'; echo '<optgroup label="'.htmlspecialchars($t['faculty_name']??'Khác').'">'; $lastF=$t['faculty_name']; } ?>
            <option value="<?php echo (int)$t['id']; ?>"><?php echo htmlspecialchars($t['full_name'].' ('.$t['teacher_code'].')'); ?></option>
            <?php endforeach; if ($lastF!=='') echo '</optgroup>'; ?></select>
        </div>
        <div class="col-12">
            <div class="alert alert-info small mb-0">
                Chức năng này tạo lớp học phần theo từng lớp hành chính của tất cả khóa đang áp dụng CTĐT của ngành và bỏ qua các môn/lớp đã có trong học kỳ. Việc tự động đăng ký chỉ áp dụng cho khóa đang tuyển sinh qua luồng xếp lớp tuyển sinh/chờ xử lý, không chạy tại màn mở lớp học phần này. Nếu học kỳ là Test, lớp học phần sinh ra được gắn dữ liệu test để xóa demo không ảnh hưởng dữ liệu thật.
            </div>
        </div>
    </div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-gold"><i class="bi bi-journal-plus me-1"></i>Mở lớp</button></div>
    </form>
</div></div></div>

<!-- Modal Them -->
<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Thêm Lớp học phần</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post" action="course_sections.php"><?php echo csrfField(); ?><input type="hidden" name="action" value="add"><input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($currentRedirect); ?>">
    <div class="modal-body"><div class="row g-3">
        <div class="col-md-6"><label class="form-label fw-semibold">Mã lớp học phần <span class="text-danger">*</span></label><input type="text" name="section_code" class="form-control" required placeholder="VD: CNTT101_01"></div>
        <div class="col-md-6"><label class="form-label fw-semibold">Môn học <span class="text-danger">*</span></label>
            <select name="subject_id" class="form-select" required><option value="">-- Chọn môn --</option>
            <?php foreach ($subjects as $sub): ?><option value="<?php echo $sub['id']; ?>"><?php echo htmlspecialchars($sub['subject_code'].' - '.$sub['subject_name']); ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-6"><label class="form-label fw-semibold">Học kỳ <span class="text-danger">*</span></label>
            <select name="semester_id" class="form-select" required><option value="">-- Chọn học kỳ --</option>
            <?php foreach ($semesters as $sm): ?><option value="<?php echo $sm['id']; ?>" <?php echo $filterSem==$sm['id']?'selected':''; ?>><?php echo htmlspecialchars($sm['semester_name'].' '.$sm['school_year']); ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-6"><label class="form-label fw-semibold">Khóa/ngành áp dụng</label>
            <select name="target_cohort_id" class="form-select">
                <option value="">Theo CTĐT ngành, không giới hạn khóa</option>
                <?php $lastMajor=''; foreach ($cohorts as $cohort): ?>
                    <?php if ($cohort['major_name'] !== $lastMajor): ?>
                        <?php if ($lastMajor !== '') echo '</optgroup>'; ?>
                        <optgroup label="<?php echo htmlspecialchars($cohort['major_name']); ?>">
                        <?php $lastMajor = $cohort['major_name']; ?>
                    <?php endif; ?>
                    <?php
                        $gradYear = (int)$cohort['enrollment_year'] + (int)ceil((float)($cohort['duration_years'] ?? 4));
                    ?>
                    <option value="<?php echo (int)$cohort['id']; ?>">
                        <?php echo htmlspecialchars($cohort['cohort_code'] . ' - Khóa ' . $cohort['enrollment_year'] . '-' . $gradYear); ?>
                    </option>
                <?php endforeach; if ($lastMajor !== '') echo '</optgroup>'; ?>
            </select>
            <div class="form-text">Sinh viên chỉ thấy lớp đúng CTĐT ngành và đúng khóa nếu chọn khóa cụ thể.</div>
        </div>
        <div class="col-md-6"><label class="form-label fw-semibold">Giảng viên</label>
            <select name="teacher_id" class="form-select"><option value="">-- Chưa phân công --</option>
            <?php $lastF=''; foreach ($teachers as $t): if ($t['faculty_name']!==$lastF) { if ($lastF!=='') echo '</optgroup>'; echo '<optgroup label="'.htmlspecialchars($t['faculty_name']??'Khac').'">'; $lastF=$t['faculty_name']; } ?>
            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['full_name'].' ('.$t['teacher_code'].')'); ?></option>
            <?php endforeach; if ($lastF!=='') echo '</optgroup>'; ?></select></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Phong hoc</label><input type="text" name="room" class="form-control" placeholder="VD: A101"></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Si so toi da</label><input type="number" name="max_students" class="form-control" value="40" min="1"></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Hinh thuc</label>
            <select name="teaching_mode" class="form-select"><option value="offline">Offline</option><option value="online">Online</option><option value="hybrid">Hybrid</option></select></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Lịch học (day_sessions)</label><input type="text" name="day_sessions" class="form-control" placeholder="VD: 2:sang,4:chieu"></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Ngày bắt đầu</label><input type="date" name="start_date" class="form-control"></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Ngày kết thúc</label><input type="date" name="end_date" class="form-control"></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Trạng thái</label>
            <select name="status" class="form-select"><option value="open">Mở</option><option value="draft">Nháp</option><option value="closed">Đóng</option></select></div>
    </div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-navy"><i class="bi bi-save me-1"></i>Lưu</button></div>
    </form>
</div></div></div>

<!-- Modal Sua -->
<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Chỉnh sửa Lớp học phần</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post" action="course_sections.php"><?php echo csrfField(); ?><input type="hidden" name="action" value="edit"><input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($currentRedirect); ?>"><input type="hidden" name="id" id="editId">
    <div class="modal-body"><div class="row g-3">
        <div class="col-md-6"><label class="form-label fw-semibold">Giảng viên</label>
            <select name="teacher_id" class="form-select"><option value="">-- Chưa phân công --</option>
            <?php $lastF=''; foreach ($teachers as $t): if ($t['faculty_name']!==$lastF) { if ($lastF!=='') echo '</optgroup>'; echo '<optgroup label="'.htmlspecialchars($t['faculty_name']??'Khac').'">'; $lastF=$t['faculty_name']; } ?>
            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['full_name'].' ('.$t['teacher_code'].')'); ?></option>
            <?php endforeach; if ($lastF!=='') echo '</optgroup>'; ?></select></div>
        <div class="col-md-6"><label class="form-label fw-semibold">Phong hoc</label><input type="text" name="room" id="editRoom" class="form-control"></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Si so toi da</label><input type="number" name="max_students" id="editMax" class="form-control" min="1"></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Hinh thuc</label>
            <select name="teaching_mode" id="editMode" class="form-select"><option value="offline">Offline</option><option value="online">Online</option><option value="hybrid">Hybrid</option></select></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Trạng thái</label>
            <select name="status" id="editStatus" class="form-select">
                <?php foreach ($statusLabels as $k=>[$c,$l]): ?><option value="<?php echo $k; ?>"><?php echo $l; ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Lịch học</label><input type="text" name="day_sessions" id="editDay" class="form-control"></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Ngày bắt đầu</label><input type="date" name="start_date" id="editStart" class="form-control"></div>
        <div class="col-md-4"><label class="form-label fw-semibold">Ngày kết thúc</label><input type="date" name="end_date" id="editEnd" class="form-control"></div>
    </div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-navy"><i class="bi bi-save me-1"></i>Lưu</button></div>
    </form>
</div></div></div>

<!-- Modal Doi lich 1 buoi -->
<div class="modal fade" id="changeScheduleModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Đổi lịch một buổi học</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post" action="course_sections.php"><?php echo csrfField(); ?><input type="hidden" name="action" value="schedule_change"><input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($currentRedirect); ?>"><input type="hidden" name="section_id" id="chgSectionId">
    <div class="modal-body">
        <div class="alert alert-info py-2 small">Lịch đổi được lưu riêng, không thay đổi lịch học gốc của lớp.</div>
        <div class="mb-3"><label class="form-label fw-semibold">Lớp học phần</label><input type="text" id="chgSectionCode" class="form-control" readonly></div>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label fw-semibold">Ngày gốc cần đổi</label><input type="date" name="original_date" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Ngày học thay thế</label><input type="date" name="new_date" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Buổi mới</label>
                <select name="new_day_session" class="form-select" required>
                    <option value="sang">Sáng</option>
                    <option value="chieu">Chiều</option>
                    <option value="toi">Tối</option>
                </select>
            </div>
            <div class="col-md-6"><label class="form-label fw-semibold">Phòng</label><input type="text" name="room" id="chgRoom" class="form-control"></div>
            <div class="col-12"><label class="form-label fw-semibold">Lý do</label><textarea name="reason" class="form-control" rows="2" placeholder="VD: nghỉ lễ, giảng viên xin đổi lịch, sự cố phòng học..."></textarea></div>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-navy"><i class="bi bi-save me-1"></i>Lưu lịch đổi</button></div>
    </form>
</div></div></div>
<script>
document.getElementById('editModal').addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('editId').value = b.dataset.id;
    document.getElementById('editRoom').value = b.dataset.room;
    document.getElementById('editMax').value = b.dataset.max;
    document.getElementById('editStatus').value = b.dataset.status;
    document.getElementById('editDay').value = b.dataset.day;
    document.getElementById('editStart').value = b.dataset.start;
    document.getElementById('editEnd').value = b.dataset.end;
    document.getElementById('editMode').value = b.dataset.mode;
});
document.getElementById('changeScheduleModal').addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('chgSectionId').value = b.dataset.id;
    document.getElementById('chgSectionCode').value = b.dataset.code;
    document.getElementById('chgRoom').value = b.dataset.room || '';
});

const filterFaculty = document.getElementById('filterFaculty');
const filterYear = document.getElementById('filterYear');
const filterMajor = document.getElementById('filterMajor');
const filterClass = document.getElementById('filterClass');

function refreshCourseSectionFilters() {
    const facultyId = filterFaculty?.value || '0';
    const year = filterYear?.value || '0';
    const majorId = filterMajor?.value || '0';
    if (filterMajor) {
        filterMajor.disabled = facultyId === '0';
    }
    if (filterClass) {
        filterClass.disabled = majorId === '0' || filterMajor?.disabled;
        const defaultOption = filterClass.querySelector('option[value="0"]');
        if (defaultOption) {
            defaultOption.textContent = filterClass.disabled ? '-- Chọn ngành trước --' : '-- Tất cả lớp đã mở --';
        }
    }

    Array.from(filterMajor?.options || []).forEach((option) => {
        if (option.value === '0') return;
        option.hidden = facultyId !== '0' && option.dataset.faculty !== facultyId;
    });
    if (filterMajor?.selectedOptions[0]?.hidden) {
        filterMajor.value = '0';
    }

    const currentMajorId = filterMajor?.value || '0';
    Array.from(filterClass?.options || []).forEach((option) => {
        if (option.value === '0') return;
        const majorMatch = currentMajorId !== '0' && option.dataset.major === currentMajorId;
        const yearMatch = year === '0' || option.dataset.year === year;
        option.hidden = !majorMatch || !yearMatch;
    });
    if (filterClass?.selectedOptions[0]?.hidden) {
        filterClass.value = '0';
    }
}

filterYear?.addEventListener('change', function() {
    if (filterFaculty) filterFaculty.value = '0';
    if (filterMajor) filterMajor.value = '0';
    if (filterClass) filterClass.value = '0';
    refreshCourseSectionFilters();
});
filterFaculty?.addEventListener('change', function() {
    if (filterMajor) filterMajor.value = '0';
    if (filterClass) filterClass.value = '0';
    refreshCourseSectionFilters();
});
filterMajor?.addEventListener('change', function() {
    if (filterClass) filterClass.value = '0';
    refreshCourseSectionFilters();
});
refreshCourseSectionFilters();

const importFacultyFilter = document.getElementById('importFacultyFilter');
const importSemesterSelect = document.getElementById('importSemesterSelect');
const importMajorSelect = document.getElementById('importMajorSelect');
const importClassCountView = document.getElementById('importClassCountView');

function refreshImportMajors() {
    const facultyId = importFacultyFilter?.value || '';
    Array.from(importMajorSelect?.options || []).forEach((option) => {
        if (!option.value) return;
        option.hidden = facultyId !== '' && option.dataset.faculty !== facultyId;
    });
    if (importMajorSelect?.selectedOptions[0]?.hidden) {
        importMajorSelect.value = '';
    }
    refreshImportClassSummary();
}

function refreshImportClassSummary() {
    const option = importMajorSelect?.selectedOptions[0];
    if (!option || !option.value) {
        if (importClassCountView) importClassCountView.value = 'Chọn ngành trước';
        return;
    }
    const mode = importSemesterSelect?.selectedOptions?.[0]?.dataset.mode === 'test' ? 'test' : 'system';
    const classData = mode === 'test' ? option.dataset.classesTest : option.dataset.classesSystem;
    let classes = [];
    try {
        classes = JSON.parse(classData || '[]');
    } catch (_err) {
        classes = [];
    }
    if (importClassCountView) {
        const cohorts = Number(option.dataset.cohorts || 0);
        importClassCountView.value = classes.length
            ? `${classes.length} lớp / ${cohorts} khóa: ${classes.map((item) => item.class_code).join(', ')}`
            : `${cohorts} khóa, chưa có lớp ${mode === 'test' ? 'test' : 'thật'} - sẽ mở lớp chung theo khóa`;
    }
}

importFacultyFilter?.addEventListener('change', refreshImportMajors);
importSemesterSelect?.addEventListener('change', refreshImportClassSummary);
importMajorSelect?.addEventListener('change', refreshImportClassSummary);
refreshImportClassSummary();
</script>
<?php include 'includes/footer.php'; ?>

