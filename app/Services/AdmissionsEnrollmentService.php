<?php

require_once __DIR__ . '/../../includes/AcademicPolicy.php';

class AdmissionsEnrollmentService
{
    public static function getClassAcademicContext(mysqli $conn, int $classId): ?array
    {
        $stmt = $conn->prepare(
            "SELECT c.id, c.major_id, c.enrollment_year, c.cohort_id,
                    tc.program_id, tc.duration_years
             FROM classes c
             LEFT JOIN training_cohorts tc ON c.cohort_id = tc.id
             WHERE c.id = ?
             LIMIT 1"
        );
        $stmt->bind_param('i', $classId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    public static function createStudentProfile(
        mysqli $conn,
        int $userId,
        string $studentCode,
        int $classId,
        array $application,
        ?array $classContext = null,
        string $autoEnrollMode = 'system',
        ?int $autoEnrollSemesterId = null,
        bool $deferAutoEnrollment = false
    ): int {
        $classContext = $classContext ?: self::getClassAcademicContext($conn, $classId);
        if (!$classContext) {
            throw new Exception('Lớp hành chính không hợp lệ.');
        }

        if (isset($application['major_id']) && (int)$application['major_id'] > 0
            && (int)$classContext['major_id'] !== (int)$application['major_id']) {
            throw new Exception('Lớp hành chính không thuộc ngành trúng tuyển.');
        }

        $fallbackYear = !empty($application['created_at'])
            ? (int)date('Y', strtotime((string)$application['created_at']))
            : (int)date('Y');

        $year = (int)($classContext['enrollment_year'] ?: $fallbackYear);
        $cohortId = (int)($classContext['cohort_id'] ?? 0);
        $programId = (int)($classContext['program_id'] ?? 0);
        $duration = (float)($classContext['duration_years'] ?? 4);
        $expectedGradYear = $year + (int)ceil($duration > 0 ? $duration : 4);
        $dataMode = (($application['data_mode'] ?? null) === 'test') ? 'test' : 'system';
        $effectiveAutoEnrollMode = $dataMode === 'test' ? $autoEnrollMode : 'system';
        $demoBatchId = $dataMode === 'test' ? (string)($application['import_batch_id'] ?? '') : '';
        $hasStudentDataMode = self::columnExists($conn, 'students', 'data_mode');
        $hasStudentDemoBatch = self::columnExists($conn, 'students', 'demo_batch_id');

        $stmt = $conn->prepare(
            "INSERT INTO students
             (user_id, student_code, class_id, enrollment_year, cohort_id, training_program_id,
              expected_grad_year, academic_status, address, birthday, gender)
             VALUES (?,?,?,?,?,?,?,'Đang học',?,?,?)"
        );
        if ($hasStudentDataMode && $hasStudentDemoBatch) {
            $stmt = $conn->prepare(
                "INSERT INTO students
                 (user_id, student_code, class_id, enrollment_year, cohort_id, training_program_id,
                  expected_grad_year, academic_status, address, birthday, gender, data_mode, demo_batch_id)
                 VALUES (?,?,?,?,?,?,?,'Đang học',?,?,?,?,?)"
            );
        } elseif ($hasStudentDataMode) {
            $stmt = $conn->prepare(
                "INSERT INTO students
                 (user_id, student_code, class_id, enrollment_year, cohort_id, training_program_id,
                  expected_grad_year, academic_status, address, birthday, gender, data_mode)
                 VALUES (?,?,?,?,?,?,?,'Đang học',?,?,?,?)"
            );
        }
        $address = $application['address'] ?? '';
        $birthday = $application['birthday'] ?? null;
        $gender = $application['gender'] ?? 'Nam';
        if ($hasStudentDataMode && $hasStudentDemoBatch) {
            $stmt->bind_param(
                'isiiiiisssss',
                $userId,
                $studentCode,
                $classId,
                $year,
                $cohortId,
                $programId,
                $expectedGradYear,
                $address,
                $birthday,
                $gender,
                $dataMode,
                $demoBatchId
            );
        } elseif ($hasStudentDataMode) {
            $stmt->bind_param(
                'isiiiiissss',
                $userId,
                $studentCode,
                $classId,
                $year,
                $cohortId,
                $programId,
                $expectedGradYear,
                $address,
                $birthday,
                $gender,
                $dataMode
            );
        } else {
            $stmt->bind_param(
                'isiiiiisss',
                $userId,
                $studentCode,
                $classId,
                $year,
                $cohortId,
                $programId,
                $expectedGradYear,
                $address,
                $birthday,
                $gender
            );
        }
        if (!$stmt->execute()) {
            $error = $stmt->error ?: $conn->error;
            $stmt->close();
            throw new Exception('Lỗi tạo hồ sơ sinh viên: ' . $error);
        }

        $studentId = (int)$conn->insert_id;
        $stmt->close();
        if (!$deferAutoEnrollment) {
            self::autoEnrollFirstSemester($conn, $studentId, (int)$classContext['major_id'], $programId, $cohortId, $year, $effectiveAutoEnrollMode, $autoEnrollSemesterId, $classId);
        }
        return $studentId;
    }

    public static function ensureAutoEnrollmentRequestSchema(mysqli $conn): void
    {
        $conn->query(
            "CREATE TABLE IF NOT EXISTS admission_auto_enrollment_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                application_id INT NULL,
                class_id INT NOT NULL,
                major_id INT NOT NULL,
                cohort_id INT NULL,
                program_id INT NULL,
                enrollment_year INT NULL,
                auto_enroll_mode ENUM('system','test') NOT NULL DEFAULT 'system',
                status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                note TEXT NULL,
                requested_by INT NULL,
                approved_by INT NULL,
                approved_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_auto_enroll_student (student_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    public static function createAutoEnrollmentRequest(
        mysqli $conn,
        int $studentId,
        int $applicationId,
        int $classId,
        array $classContext,
        string $autoEnrollMode,
        int $requestedBy = 0
    ): void {
        self::ensureAutoEnrollmentRequestSchema($conn);

        $majorId = (int)($classContext['major_id'] ?? 0);
        $cohortId = (int)($classContext['cohort_id'] ?? 0);
        $programId = (int)($classContext['program_id'] ?? 0);
        $enrollmentYear = (int)($classContext['enrollment_year'] ?? date('Y'));
        $mode = $autoEnrollMode === 'test' ? 'test' : 'system';

        $stmt = $conn->prepare(
            "INSERT INTO admission_auto_enrollment_requests
             (student_id, application_id, class_id, major_id, cohort_id, program_id, enrollment_year, auto_enroll_mode, requested_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                application_id=VALUES(application_id),
                class_id=VALUES(class_id),
                major_id=VALUES(major_id),
                cohort_id=VALUES(cohort_id),
                program_id=VALUES(program_id),
                enrollment_year=VALUES(enrollment_year),
                auto_enroll_mode=VALUES(auto_enroll_mode),
                status='pending',
                requested_by=VALUES(requested_by),
                approved_by=NULL,
                approved_at=NULL,
                updated_at=NOW()"
        );
        if (!$stmt) {
            throw new Exception('Lỗi tạo yêu cầu đăng ký HK1: ' . $conn->error);
        }
        $stmt->bind_param('iiiiiiisi', $studentId, $applicationId, $classId, $majorId, $cohortId, $programId, $enrollmentYear, $mode, $requestedBy);
        if (!$stmt->execute()) {
            $error = $stmt->error ?: $conn->error;
            $stmt->close();
            throw new Exception('Lỗi tạo yêu cầu đăng ký HK1: ' . $error);
        }
        $stmt->close();
    }

    public static function approveAutoEnrollmentRequest(mysqli $conn, int $requestId, int $approvedBy, string $note = ''): array
    {
        self::ensureAutoEnrollmentRequestSchema($conn);

        $stmt = $conn->prepare(
            "SELECT aer.*, st.student_code, u.full_name
             FROM admission_auto_enrollment_requests aer
             JOIN students st ON st.id = aer.student_id
             JOIN users u ON u.id = st.user_id
             WHERE aer.id = ? AND aer.status = 'pending'
             LIMIT 1"
        );
        $stmt->bind_param('i', $requestId);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$request) {
            throw new Exception('Yêu cầu đã được xử lý hoặc không tồn tại.');
        }

        $result = self::autoEnrollFirstSemester(
            $conn,
            (int)$request['student_id'],
            (int)$request['major_id'],
            (int)$request['program_id'],
            (int)$request['cohort_id'],
            (int)$request['enrollment_year'],
            (string)$request['auto_enroll_mode'],
            null,
            (int)$request['class_id']
        );

        $stmt = $conn->prepare(
            "UPDATE admission_auto_enrollment_requests
             SET status='approved', note=?, approved_by=?, approved_at=NOW(), updated_at=NOW()
             WHERE id=?"
        );
        $stmt->bind_param('sii', $note, $approvedBy, $requestId);
        $stmt->execute();
        $stmt->close();

        return $result + ['student_code' => $request['student_code'], 'full_name' => $request['full_name']];
    }

    public static function rejectAutoEnrollmentRequest(mysqli $conn, int $requestId, int $approvedBy, string $note = ''): void
    {
        self::ensureAutoEnrollmentRequestSchema($conn);
        $stmt = $conn->prepare(
            "UPDATE admission_auto_enrollment_requests
             SET status='rejected', note=?, approved_by=?, approved_at=NOW(), updated_at=NOW()
             WHERE id=? AND status='pending'"
        );
        $stmt->bind_param('sii', $note, $approvedBy, $requestId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        if ($affected <= 0) {
            throw new Exception('Yêu cầu đã được xử lý hoặc không tồn tại.');
        }
    }

    public static function ensureAutoEnrollmentSchema(mysqli $conn): void
    {
        $conn->query("ALTER TABLE student_subjects MODIFY COLUMN status ENUM('registered','cancelled','completed','auto_enrolled') DEFAULT 'registered'");
        $conn->query(
            "CREATE TABLE IF NOT EXISTS pending_enrollments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                subject_id INT NOT NULL,
                semester_id INT NOT NULL,
                cohort_id INT NULL,
                program_id INT NULL,
                reason VARCHAR(255) DEFAULT 'Không tìm được lớp học phần phù hợp',
                status ENUM('pending','resolved','ignored') DEFAULT 'pending',
                note TEXT NULL,
                resolved_by INT NULL,
                resolved_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_pending_student_subject_semester (student_id, subject_id, semester_id),
                INDEX idx_pending_status (status),
                INDEX idx_pending_semester (semester_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    public static function autoEnrollFirstSemester(
        mysqli $conn,
        int $studentId,
        int $majorId,
        int $programId = 0,
        int $cohortId = 0,
        int $enrollmentYear = 0,
        string $mode = 'system',
        ?int $semesterIdOverride = null,
        int $classId = 0
    ): array {
        $semester = self::findAutoEnrollmentSemester($conn, $enrollmentYear, $mode, $semesterIdOverride);
        if (!$semester) {
            return ['enrolled' => 0, 'pending' => 0, 'message' => 'Khong tim thay hoc ky phu hop de dang ky tu dong.'];
        }
        $semesterId = (int)$semester['id'];

        $programCondition = '';
        if ($programId > 0 && self::columnExists($conn, 'curriculum', 'program_id')) {
            $programCondition = ' AND (program_id IS NULL OR program_id = ?)';
        }
        $sql = "SELECT subject_id
                FROM curriculum
                WHERE major_id = ?
                  AND suggested_semester = 1
                  AND deleted_at IS NULL
                  $programCondition
                ORDER BY id";
        $stmt = $conn->prepare($sql);
        if ($programCondition) {
            $stmt->bind_param('ii', $majorId, $programId);
        } else {
            $stmt->bind_param('i', $majorId);
        }
        $stmt->execute();
        $subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $enrolled = 0;
        $pending = 0;
        foreach ($subjects as $subject) {
            $subjectId = (int)$subject['subject_id'];
            if ($subjectId <= 0) {
                continue;
            }

            $section = self::findAutoEnrollmentSection($conn, $subjectId, $semesterId, $cohortId, $classId, $studentId);
            if (!$section) {
                self::createPendingEnrollment($conn, $studentId, $subjectId, $semesterId, $cohortId, $programId);
                $pending++;
                continue;
            }

            if (self::enrollSection($conn, $studentId, (int)$section['id'])) {
                $enrolled++;
            }
        }

        if ($pending > 0) {
            self::notifyAcademicPendingEnrollments($conn, $studentId, $semesterId, $pending);
        }

        return ['enrolled' => $enrolled, 'pending' => $pending, 'semester_id' => $semesterId];
    }

    private static function findAutoEnrollmentSemester(mysqli $conn, int $enrollmentYear, string $mode, ?int $semesterIdOverride): ?array
    {
        if ($semesterIdOverride && $semesterIdOverride > 0) {
            $stmt = $conn->prepare("SELECT * FROM semesters WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $semesterIdOverride);
            $stmt->execute();
            $semester = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();
            return $semester;
        }

        if ($mode === 'test') {
            $semester = self::findTestSemester($conn, $enrollmentYear);
            if ($semester) {
                return $semester;
            }
        }

        return self::findFirstSemester($conn, $enrollmentYear);
    }

    private static function findFirstSemester(mysqli $conn, int $enrollmentYear): ?array
    {
        if ($enrollmentYear <= 0) {
            return null;
        }
        $schoolYear = $enrollmentYear . '-' . ($enrollmentYear + 1);
        $stmt = $conn->prepare(
            "SELECT *
             FROM semesters
             WHERE school_year = ?
               AND (semester_name LIKE '%1%' OR LOWER(semester_name) LIKE '%hk1%' OR LOWER(semester_name) LIKE '%hoc ky 1%')
             ORDER BY FIELD(status, 'open', 'active', 'upcoming', 'closed'), id DESC
             LIMIT 1"
        );
        $stmt->bind_param('s', $schoolYear);
        $stmt->execute();
        $semester = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $semester;
    }

    private static function findTestSemester(mysqli $conn, int $enrollmentYear): ?array
    {
        if ($enrollmentYear <= 0) {
            return null;
        }
        $schoolYear = $enrollmentYear . '-' . ($enrollmentYear + 1);
        $stmt = $conn->prepare(
            "SELECT *
             FROM semesters
             WHERE school_year = ?
               AND LOWER(semester_name) LIKE '%test%'
             ORDER BY FIELD(status, 'open', 'active', 'upcoming', 'closed'), id DESC
             LIMIT 1"
        );
        $stmt->bind_param('s', $schoolYear);
        $stmt->execute();
        $semester = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $semester;
    }

    private static function findAutoEnrollmentSection(mysqli $conn, int $subjectId, int $semesterId, int $cohortId, int $classId = 0, int $studentId = 0): ?array
    {
        $hasClassId = self::columnExists($conn, 'course_sections', 'class_id');
        if ($hasClassId) {
            $stmt = $conn->prepare(
                "SELECT id, section_code, current_students, max_students
                 FROM course_sections
                 WHERE subject_id = ?
                   AND semester_id = ?
                   AND status = 'open'
                   AND current_students < max_students
                   AND (target_cohort_id IS NULL OR target_cohort_id = ?)
                   AND (class_id IS NULL OR class_id = ?)
                 ORDER BY CASE WHEN class_id = ? THEN 0 ELSE 1 END, id ASC"
            );
            $stmt->bind_param('iiiii', $subjectId, $semesterId, $cohortId, $classId, $classId);
        } else {
            $stmt = $conn->prepare(
                "SELECT id, section_code, current_students, max_students
                 FROM course_sections
                 WHERE subject_id = ?
                   AND semester_id = ?
                   AND status = 'open'
                   AND current_students < max_students
                   AND (target_cohort_id IS NULL OR target_cohort_id = ?)
                 ORDER BY id ASC"
            );
            $stmt->bind_param('iii', $subjectId, $semesterId, $cohortId);
        }
        $stmt->execute();
        $sections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($sections as $section) {
            if ($studentId > 0) {
                $conflict = academicPolicyStudentScheduleConflict($conn, $studentId, (int)$section['id']);
                if (!$conflict['ok']) {
                    continue;
                }
            }
            return $section;
        }

        return null;
    }

    public static function retryPendingEnrollment(mysqli $conn, int $pendingId, int $resolvedBy, string $note = ''): array
    {
        self::ensureAutoEnrollmentSchema($conn);

        $stmt = $conn->prepare(
            "SELECT pe.*, st.class_id, st.student_code, u.full_name, subj.subject_code, subj.subject_name,
                    sm.semester_name, sm.school_year
             FROM pending_enrollments pe
             JOIN students st ON st.id = pe.student_id
             JOIN users u ON u.id = st.user_id
             JOIN subjects subj ON subj.id = pe.subject_id
             JOIN semesters sm ON sm.id = pe.semester_id
             WHERE pe.id = ? AND pe.status = 'pending'
             LIMIT 1"
        );
        $stmt->bind_param('i', $pendingId);
        $stmt->execute();
        $pending = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$pending) {
            throw new Exception('Dòng chờ xử lý đã được xử lý hoặc không tồn tại.');
        }

        $studentId = (int)$pending['student_id'];
        $subjectId = (int)$pending['subject_id'];
        $semesterId = (int)$pending['semester_id'];
        $cohortId = (int)$pending['cohort_id'];
        $note = trim($note);

        $existing = $conn->prepare(
            "SELECT ss.id, ss.status, cs.section_code
             FROM student_subjects ss
             JOIN course_sections cs ON cs.id = ss.course_section_id
             WHERE ss.student_id = ?
               AND cs.subject_id = ?
               AND cs.semester_id = ?
               AND ss.status IN ('registered','auto_enrolled','completed')
             LIMIT 1"
        );
        $existing->bind_param('iii', $studentId, $subjectId, $semesterId);
        $existing->execute();
        $registered = $existing->get_result()->fetch_assoc();
        $existing->close();

        if ($registered) {
            $resolveNote = $note ?: ('Đã có đăng ký ở lớp HP ' . ($registered['section_code'] ?? 'phù hợp') . '.');
            self::resolvePendingEnrollment($conn, $pendingId, $resolvedBy, $resolveNote);
            return [
                'ok' => true,
                'status' => 'already_registered',
                'message' => 'Sinh viên đã có đăng ký môn này trong học kỳ.',
                'full_name' => $pending['full_name'],
                'subject_name' => $pending['subject_name'],
            ];
        }

        $section = self::findAutoEnrollmentSection($conn, $subjectId, $semesterId, $cohortId, (int)($pending['class_id'] ?? 0), $studentId);
        if (!$section) {
            $reason = self::explainMissingAutoEnrollmentSection($conn, $subjectId, $semesterId, $cohortId);
            $stmt = $conn->prepare(
                "UPDATE pending_enrollments
                 SET reason = ?, note = ?, resolved_by = NULL, resolved_at = NULL
                 WHERE id = ? AND status = 'pending'"
            );
            $stmt->bind_param('ssi', $reason, $note, $pendingId);
            $stmt->execute();
            $stmt->close();

            return [
                'ok' => false,
                'status' => 'still_pending',
                'message' => $reason,
                'full_name' => $pending['full_name'],
                'subject_name' => $pending['subject_name'],
            ];
        }

        if (!self::enrollSection($conn, $studentId, (int)$section['id'])) {
            $reason = 'Tìm thấy lớp học phần nhưng lớp vừa hết chỗ hoặc đã đóng. Vui lòng kiểm tra lại lớp HP.';
            $stmt = $conn->prepare("UPDATE pending_enrollments SET reason = ?, note = ? WHERE id = ? AND status = 'pending'");
            $stmt->bind_param('ssi', $reason, $note, $pendingId);
            $stmt->execute();
            $stmt->close();

            return [
                'ok' => false,
                'status' => 'section_unavailable',
                'message' => $reason,
                'full_name' => $pending['full_name'],
                'subject_name' => $pending['subject_name'],
            ];
        }

        $sectionCode = $section['section_code'] ?? ('#' . (int)$section['id']);
        $resolveNote = $note ?: ('Đăng ký lại thành công vào lớp HP ' . $sectionCode . '.');
        self::resolvePendingEnrollment($conn, $pendingId, $resolvedBy, $resolveNote);

        return [
            'ok' => true,
            'status' => 'registered',
            'message' => 'Đăng ký lại thành công vào lớp HP ' . $sectionCode . '.',
            'full_name' => $pending['full_name'],
            'subject_name' => $pending['subject_name'],
            'section_code' => $sectionCode,
        ];
    }

    private static function resolvePendingEnrollment(mysqli $conn, int $pendingId, int $resolvedBy, string $note): void
    {
        $stmt = $conn->prepare(
            "UPDATE pending_enrollments
             SET status = 'resolved', note = ?, resolved_by = ?, resolved_at = NOW()
             WHERE id = ? AND status = 'pending'"
        );
        $stmt->bind_param('sii', $note, $resolvedBy, $pendingId);
        $stmt->execute();
        $stmt->close();
    }

    private static function explainMissingAutoEnrollmentSection(mysqli $conn, int $subjectId, int $semesterId, int $cohortId): string
    {
        $stmt = $conn->prepare("SELECT status, current_students, max_students FROM course_sections WHERE subject_id = ? AND semester_id = ?");
        $stmt->bind_param('ii', $subjectId, $semesterId);
        $stmt->execute();
        $sections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (!$sections) {
            return 'Chưa có lớp học phần cho môn này trong học kỳ đã chọn.';
        }

        $open = 0;
        $full = 0;
        foreach ($sections as $section) {
            if (($section['status'] ?? '') === 'open') {
                $open++;
                if ((int)$section['current_students'] >= (int)$section['max_students']) {
                    $full++;
                }
            }
        }
        if ($open === 0) {
            return 'Đã có lớp học phần cho môn này nhưng chưa mở đăng ký.';
        }
        if ($full >= $open) {
            return 'Các lớp học phần đang mở cho môn này đã đủ sĩ số.';
        }

        if ($cohortId > 0) {
            return 'Có lớp học phần đang mở nhưng chưa khớp khóa/CTĐT của sinh viên.';
        }

        return 'Không tìm được lớp học phần phù hợp.';
    }

    private static function enrollSection(mysqli $conn, int $studentId, int $sectionId): bool
    {
        $demoContext = self::studentDemoContext($conn, $studentId);
        $check = $conn->prepare("SELECT id, status FROM student_subjects WHERE student_id = ? AND course_section_id = ? LIMIT 1");
        $check->bind_param('ii', $studentId, $sectionId);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        $check->close();

        if ($existing && $existing['status'] !== 'cancelled') {
            return false;
        }

        $lock = $conn->prepare(
            "UPDATE course_sections
             SET current_students = current_students + 1,
                 status = CASE WHEN current_students + 1 >= max_students THEN 'full' ELSE status END
             WHERE id = ? AND status = 'open' AND current_students < max_students"
        );
        $lock->bind_param('i', $sectionId);
        $lock->execute();
        $updated = $lock->affected_rows;
        $lock->close();
        if ($updated <= 0) {
            return false;
        }

        $hasMode = self::columnExists($conn, 'student_subjects', 'data_mode');
        $hasBatch = self::columnExists($conn, 'student_subjects', 'demo_batch_id');
        if ($existing) {
            if ($hasMode && $hasBatch) {
                $stmt = $conn->prepare("UPDATE student_subjects SET status='auto_enrolled', register_date=NOW(), data_mode=?, demo_batch_id=? WHERE id=?");
                $stmt->bind_param('ssi', $demoContext['data_mode'], $demoContext['demo_batch_id'], $existing['id']);
            } else {
                $stmt = $conn->prepare("UPDATE student_subjects SET status='auto_enrolled', register_date=NOW() WHERE id=?");
                $stmt->bind_param('i', $existing['id']);
            }
        } else {
            if ($hasMode && $hasBatch) {
                $stmt = $conn->prepare("INSERT INTO student_subjects (student_id, course_section_id, register_date, status, data_mode, demo_batch_id) VALUES (?, ?, NOW(), 'auto_enrolled', ?, ?)");
                $stmt->bind_param('iiss', $studentId, $sectionId, $demoContext['data_mode'], $demoContext['demo_batch_id']);
            } else {
                $stmt = $conn->prepare("INSERT INTO student_subjects (student_id, course_section_id, register_date, status) VALUES (?, ?, NOW(), 'auto_enrolled')");
                $stmt->bind_param('ii', $studentId, $sectionId);
            }
        }
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    private static function createPendingEnrollment(mysqli $conn, int $studentId, int $subjectId, int $semesterId, int $cohortId, int $programId): void
    {
        $reason = 'Không tìm được lớp học phần phù hợp';
        $demoContext = self::studentDemoContext($conn, $studentId);
        if (self::columnExists($conn, 'pending_enrollments', 'data_mode') && self::columnExists($conn, 'pending_enrollments', 'demo_batch_id')) {
            $stmt = $conn->prepare(
                "INSERT INTO pending_enrollments (student_id, subject_id, semester_id, cohort_id, program_id, reason, data_mode, demo_batch_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE status='pending', reason=VALUES(reason), data_mode=VALUES(data_mode), demo_batch_id=VALUES(demo_batch_id), created_at=CURRENT_TIMESTAMP"
            );
            $stmt->bind_param('iiiiisss', $studentId, $subjectId, $semesterId, $cohortId, $programId, $reason, $demoContext['data_mode'], $demoContext['demo_batch_id']);
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO pending_enrollments (student_id, subject_id, semester_id, cohort_id, program_id, reason)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE status='pending', reason=VALUES(reason), created_at=CURRENT_TIMESTAMP"
            );
            $stmt->bind_param('iiiiis', $studentId, $subjectId, $semesterId, $cohortId, $programId, $reason);
        }
        $stmt->execute();
        $stmt->close();
    }

    private static function studentDemoContext(mysqli $conn, int $studentId): array
    {
        $columns = "data_mode, demo_batch_id";
        if (!self::columnExists($conn, 'students', 'data_mode')) {
            return ['data_mode' => 'system', 'demo_batch_id' => ''];
        }
        if (!self::columnExists($conn, 'students', 'demo_batch_id')) {
            $columns = "data_mode, '' AS demo_batch_id";
        }
        $row = $conn->query("SELECT $columns FROM students WHERE id=" . (int)$studentId . " LIMIT 1")->fetch_assoc() ?: [];
        return [
            'data_mode' => (($row['data_mode'] ?? 'system') === 'test') ? 'test' : 'system',
            'demo_batch_id' => (string)($row['demo_batch_id'] ?? ''),
        ];
    }

    private static function notifyAcademicPendingEnrollments(mysqli $conn, int $studentId, int $semesterId, int $count): void
    {
        if ($conn->query("SHOW TABLES LIKE 'system_notifications'")->num_rows === 0) {
            return;
        }
        $student = $conn->query(
            "SELECT s.student_code, u.full_name
             FROM students s JOIN users u ON u.id = s.user_id
             WHERE s.id = " . (int)$studentId . " LIMIT 1"
        )->fetch_assoc();
        $title = 'Can xu ly dang ky tu dong HK1';
        $content = 'Sinh vien ' . ($student['full_name'] ?? ('#' . $studentId)) . ' (' . ($student['student_code'] ?? '') . ") con {$count} mon HK1 chua co lop HP phu hop.";
        $users = $conn->query(
            "SELECT DISTINCT u.id
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             WHERE u.status = 1 AND (u.role='admin' OR r.code IN ('academic_manager','academic_staff'))"
        );
        if (!$users) return;
        $stmt = $conn->prepare("INSERT INTO system_notifications (user_id, title, content) VALUES (?, ?, ?)");
        while ($user = $users->fetch_assoc()) {
            $uid = (int)$user['id'];
            $stmt->bind_param('iss', $uid, $title, $content);
            $stmt->execute();
        }
        $stmt->close();
    }

    private static function columnExists(mysqli $conn, string $table, string $column): bool
    {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS c
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $exists = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0) > 0;
        $stmt->close();
        return $exists;
    }

    public static function notifyFinanceNewEnrollment(mysqli $conn, int $studentId, string $studentCode, string $fullName): int
    {
        $financeUserIds = self::getFinanceUserIds($conn);
        if (!$financeUserIds) {
            return 0;
        }

        $columns = self::notificationColumns($conn);
        if (!isset($columns['user_id'])) {
            return 0;
        }

        $title = 'Sinh viên mới nhập học cần lập hóa đơn';
        $content = "Sinh viên {$fullName} ({$studentCode}) đã được cấp tài khoản nhập học. Vui lòng kiểm tra để tạo hóa đơn học phí đầu khóa.";
        $sent = 0;

        foreach ($financeUserIds as $userId) {
            $fields = ['user_id', 'title', 'content'];
            $placeholders = ['?', '?', '?'];
            $types = 'iss';
            $values = [(int)$userId, $title, $content];

            if (isset($columns['type'])) {
                $fields[] = 'type';
                $placeholders[] = '?';
                $types .= 's';
                $values[] = 'tuition';
            }
            if (isset($columns['status'])) {
                $fields[] = 'status';
                $placeholders[] = '?';
                $types .= 's';
                $values[] = 'show';
            }
            if (isset($columns['is_read'])) {
                $fields[] = 'is_read';
                $placeholders[] = '0';
            }
            if (isset($columns['ref_id'])) {
                $fields[] = 'ref_id';
                $placeholders[] = '?';
                $types .= 'i';
                $values[] = $studentId;
            }
            if (isset($columns['ref_type'])) {
                $fields[] = 'ref_type';
                $placeholders[] = '?';
                $types .= 's';
                $values[] = 'student';
            }

            $sql = 'INSERT INTO system_notifications (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                continue;
            }
            $stmt->bind_param($types, ...$values);
            if ($stmt->execute()) {
                $sent++;
            }
            $stmt->close();
        }

        return $sent;
    }

    public static function getFinanceUserIds(mysqli $conn): array
    {
        $ids = [];
        $stmt = $conn->prepare(
            "SELECT DISTINCT u.id
             FROM users u
             JOIN user_roles ur ON ur.user_id = u.id
             JOIN roles r ON r.id = ur.role_id
             WHERE r.code IN ('finance_manager','finance_staff')
               AND r.is_active = 1
               AND u.status = 1
               AND (ur.expires_at IS NULL OR ur.expires_at > NOW())"
        );
        if ($stmt) {
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $ids[] = (int)$row['id'];
            }
            $stmt->close();
        }

        if (!$ids) {
            $res = $conn->query("SELECT id FROM users WHERE role='staff' AND status=1");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $ids[] = (int)$row['id'];
                }
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    public static function notifyAcademicAndFacultyAssignment(
        mysqli $conn,
        int $studentId,
        int $majorId,
        string $studentCode,
        string $fullName,
        string $className
    ): int {
        $userIds = array_merge(
            self::getRoleUserIds($conn, ['academic_manager', 'academic_staff']),
            self::getFacultyUserIdsByMajor($conn, $majorId)
        );
        $userIds = array_values(array_unique(array_filter($userIds)));
        if (!$userIds) {
            return 0;
        }

        $title = 'Cần duyệt đăng ký môn HK1 cho sinh viên mới';
        $content = "Sinh viên {$fullName} ({$studentCode}) đã được phân vào lớp {$className}. Khoa/Viện phụ trách ngành và Phòng Đào tạo vui lòng kiểm tra; Phòng Đào tạo duyệt để hệ thống tự đăng ký môn học kỳ 1.";
        return self::sendSystemNotifications($conn, $userIds, $title, $content, 'academic', $studentId, 'student');
    }

    private static function getRoleUserIds(mysqli $conn, array $roleCodes): array
    {
        if (!$roleCodes) return [];
        $placeholders = implode(',', array_fill(0, count($roleCodes), '?'));
        $types = str_repeat('s', count($roleCodes));
        $stmt = $conn->prepare(
            "SELECT DISTINCT u.id
             FROM users u
             JOIN user_roles ur ON ur.user_id = u.id
             JOIN roles r ON r.id = ur.role_id
             WHERE r.code IN ($placeholders)
               AND r.is_active = 1
               AND u.status = 1
               AND (ur.expires_at IS NULL OR ur.expires_at > NOW())"
        );
        if (!$stmt) return [];
        $stmt->bind_param($types, ...$roleCodes);
        $stmt->execute();
        $ids = array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id'));
        $stmt->close();
        return $ids;
    }

    private static function getFacultyUserIdsByMajor(mysqli $conn, int $majorId): array
    {
        $stmt = $conn->prepare("SELECT faculty_id FROM majors WHERE id=? LIMIT 1");
        if (!$stmt) return [];
        $stmt->bind_param('i', $majorId);
        $stmt->execute();
        $facultyId = (int)($stmt->get_result()->fetch_assoc()['faculty_id'] ?? 0);
        $stmt->close();
        if ($facultyId <= 0) return [];

        $hasFacultyScope = self::columnExists($conn, 'user_roles', 'faculty_id');
        $facultyFilter = $hasFacultyScope ? 'AND ur.faculty_id = ?' : '';
        $stmt = $conn->prepare(
            "SELECT DISTINCT u.id
             FROM users u
             JOIN user_roles ur ON ur.user_id = u.id
             JOIN roles r ON r.id = ur.role_id
             WHERE (r.code IN ('faculty_manager','faculty_staff') OR r.code LIKE 'faculty_manager_%' OR r.code LIKE 'faculty_staff_%')
               $facultyFilter
               AND r.is_active = 1
               AND u.status = 1
               AND (ur.expires_at IS NULL OR ur.expires_at > NOW())"
        );
        if (!$stmt) return [];
        if ($hasFacultyScope) {
            $stmt->bind_param('i', $facultyId);
        }
        $stmt->execute();
        $ids = array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id'));
        $stmt->close();
        return $ids;
    }

    private static function sendSystemNotifications(
        mysqli $conn,
        array $userIds,
        string $title,
        string $content,
        string $type = 'info',
        ?int $refId = null,
        ?string $refType = null
    ): int {
        $columns = self::notificationColumns($conn);
        if (!isset($columns['user_id'])) {
            return 0;
        }

        $sent = 0;
        foreach ($userIds as $userId) {
            $fields = ['user_id', 'title', 'content'];
            $placeholders = ['?', '?', '?'];
            $types = 'iss';
            $values = [(int)$userId, $title, $content];

            if (isset($columns['type'])) {
                $fields[] = 'type';
                $placeholders[] = '?';
                $types .= 's';
                $values[] = $type;
            }
            if (isset($columns['status'])) {
                $fields[] = 'status';
                $placeholders[] = '?';
                $types .= 's';
                $values[] = 'show';
            }
            if (isset($columns['is_read'])) {
                $fields[] = 'is_read';
                $placeholders[] = '0';
            }
            if (isset($columns['ref_id']) && $refId !== null) {
                $fields[] = 'ref_id';
                $placeholders[] = '?';
                $types .= 'i';
                $values[] = $refId;
            }
            if (isset($columns['ref_type']) && $refType !== null) {
                $fields[] = 'ref_type';
                $placeholders[] = '?';
                $types .= 's';
                $values[] = $refType;
            }

            $stmt = $conn->prepare('INSERT INTO system_notifications (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')');
            if (!$stmt) continue;
            $stmt->bind_param($types, ...$values);
            if ($stmt->execute()) $sent++;
            $stmt->close();
        }
        return $sent;
    }

    private static function notificationColumns(mysqli $conn): array
    {
        static $columns = null;
        if ($columns !== null) {
            return $columns;
        }

        $columns = [];
        $res = $conn->query("SHOW COLUMNS FROM system_notifications");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $columns[$row['Field']] = true;
            }
        }
        return $columns;
    }
}
