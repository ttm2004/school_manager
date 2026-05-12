-- ============================================================
-- Seed: Lịch thi + Thông báo + Teaching wishes + KPI
-- ============================================================
SET NAMES utf8mb4;

-- ── Lịch thi HK2 (đã kết thúc) ───────────────────────────────
INSERT IGNORE INTO `final_exam_schedules`
    (`course_section_id`,`exam_date`,`start_time`,`end_time`,`room`,`exam_form`,`status`)
VALUES
(130, '2026-06-20', '07:00:00', '09:00:00', 'A101', 'Tự luận',    'completed'),
(131, '2026-06-21', '07:00:00', '09:00:00', 'A102', 'Trắc nghiệm','completed'),
(132, '2026-06-22', '13:00:00', '15:00:00', 'A201', 'Tự luận',    'completed');

-- ── Lịch thi HK1 (sắp tới) ───────────────────────────────────
INSERT IGNORE INTO `final_exam_schedules`
    (`course_section_id`,`exam_date`,`start_time`,`end_time`,`room`,`exam_form`,`status`)
VALUES
(100, '2026-01-17', '07:00:00', '09:00:00', 'A101', 'Tự luận',    'scheduled'),
(101, '2026-01-17', '09:30:00', '11:30:00', 'A102', 'Tự luận',    'scheduled'),
(102, '2026-01-18', '07:00:00', '09:00:00', 'B201', 'Tự luận',    'scheduled'),
(103, '2026-01-18', '13:00:00', '15:00:00', 'C101', 'Trắc nghiệm','scheduled'),
(104, '2026-01-19', '07:00:00', '09:00:00', 'C102', 'Tiểu luận',  'scheduled'),
(110, '2026-01-20', '07:00:00', '09:00:00', 'A201', 'Tự luận',    'scheduled'),
(111, '2026-01-20', '09:30:00', '11:30:00', 'A202', 'Tự luận',    'scheduled'),
(112, '2026-01-21', '07:00:00', '09:00:00', 'A203', 'Tự luận',    'scheduled'),
(113, '2026-01-21', '13:00:00', '15:00:00', 'B101', 'Tự luận',    'scheduled');
-- CNTT402 và LING330 chưa có lịch thi (để test cảnh báo)

-- ── Thông báo hệ thống (broadcast, không có user_id) ─────────
INSERT IGNORE INTO `notifications` (`id`,`title`,`content`,`type`,`status`) VALUES
(10, 'Lịch thi HK1 2025-2026 đã được công bố',
 'Phòng Đào tạo thông báo lịch thi cuối kỳ HK1 2025-2026. Sinh viên vui lòng kiểm tra lịch thi tại mục Lịch thi.',
 'general', 'show'),
(11, 'Nhắc nhở: Hạn đăng ký học phần HK2 2025-2026',
 'Thời gian đăng ký học phần HK2 2025-2026 sẽ bắt đầu từ 10/01/2026. Vui lòng chuẩn bị đăng ký đúng hạn.',
 'registration', 'show'),
(12, 'Thông báo phân công giảng dạy HK1 2025-2026',
 'Giảng viên vui lòng kiểm tra lịch phân công giảng dạy HK1 2025-2026 trong hệ thống.',
 'general', 'show'),
(13, 'Nhắc nhở: Hạn nộp điểm HK1 2025-2026',
 'Hạn nộp điểm HK1 2025-2026 là ngày 31/01/2026. Giảng viên vui lòng nhập điểm đầy đủ trước hạn.',
 'grade', 'show'),
(14, 'Đề xuất mở lớp CNTT403 đang chờ Phòng Đào tạo duyệt',
 'Khoa CNTT đã gửi đề xuất mở lớp CNTT403_01 (Trí tuệ nhân tạo). Phòng Đào tạo đang xem xét.',
 'general', 'show'),
(15, 'Cảnh báo học vụ: Một số sinh viên có điểm trung bình thấp',
 'Khoa vui lòng rà soát danh sách sinh viên cảnh báo học vụ và có biện pháp hỗ trợ kịp thời.',
 'grade', 'show'),
(16, 'Thông báo nộp học phí HK1 2025-2026',
 'Sinh viên chưa hoàn thành học phí HK1 2025-2026 vui lòng đóng trước ngày 30/09/2025 để tránh bị khóa đăng ký.',
 'tuition', 'show');

-- ── Teaching wishes (nguyện vọng giảng dạy) ──────────────────
-- Kiểm tra bảng tồn tại trước
SET @tw_exists = (SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'teaching_wishes');

-- GV1 đăng ký nguyện vọng dạy CNTT101 HK1
INSERT IGNORE INTO `teaching_wishes`
    (`teacher_id`,`subject_id`,`semester_id`,`faculty_id`,`priority`,`note`,`status`)
SELECT 1, s.id, 1, 1, 1, 'Đã dạy môn này 3 năm, có kinh nghiệm', 'faculty_approved'
FROM subjects s WHERE s.subject_code = 'CNTT101' LIMIT 1;

-- GV2 đăng ký nguyện vọng dạy CNTT201
INSERT IGNORE INTO `teaching_wishes`
    (`teacher_id`,`subject_id`,`semester_id`,`faculty_id`,`priority`,`note`,`status`)
SELECT 2, s.id, 1, 1, 1, 'Chuyên ngành phù hợp', 'dept_approved'
FROM subjects s WHERE s.subject_code = 'CNTT201' LIMIT 1;

-- GV3 đăng ký nguyện vọng dạy CNTT403 (chờ duyệt)
INSERT IGNORE INTO `teaching_wishes`
    (`teacher_id`,`subject_id`,`semester_id`,`faculty_id`,`priority`,`note`,`status`)
SELECT 3, s.id, 1, 1, 2, 'Có nghiên cứu về AI', 'pending'
FROM subjects s WHERE s.subject_code = 'CNTT403' LIMIT 1;

-- GV22 (Kế toán) đăng ký nguyện vọng
INSERT IGNORE INTO `teaching_wishes`
    (`teacher_id`,`subject_id`,`semester_id`,`faculty_id`,`priority`,`note`,`status`)
SELECT 22, s.id, 1, 2, 1, 'Chuyên môn kế toán tài chính', 'faculty_approved'
FROM subjects s WHERE s.subject_code = 'KETO010' LIMIT 1;

-- ── KPI Giảng viên ────────────────────────────────────────────
-- Kiểm tra bảng tồn tại
SET @kpi_exists = (SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'teacher_kpi');

INSERT IGNORE INTO `teacher_kpi_periods` (`period_name`,`year_start`,`year_end`,`status`)
VALUES ('Năm học 2025-2026', 2025, 2026, 'open');

-- KPI GV1
INSERT IGNORE INTO `teacher_kpi`
    (`teacher_id`,`period_id`,`faculty_id`,
     `teaching_hours_plan`,`teaching_hours_actual`,
     `research_projects`,`papers_published`,`papers_in_progress`,
     `thesis_supervised`,`project_graded`,`training_courses`,
     `note`,`status`)
SELECT 1, p.id, 1,
    300, 280, 1, 2, 1, 3, 5, 2,
    'Đang hoàn thiện bài báo ISI', 'submitted'
FROM teacher_kpi_periods p WHERE p.period_name = 'Năm học 2025-2026' LIMIT 1;

-- KPI GV2
INSERT IGNORE INTO `teacher_kpi`
    (`teacher_id`,`period_id`,`faculty_id`,
     `teaching_hours_plan`,`teaching_hours_actual`,
     `research_projects`,`papers_published`,`papers_in_progress`,
     `thesis_supervised`,`project_graded`,`training_courses`,
     `note`,`status`)
SELECT 2, p.id, 1,
    280, 280, 0, 1, 0, 5, 8, 1,
    '', 'approved'
FROM teacher_kpi_periods p WHERE p.period_name = 'Năm học 2025-2026' LIMIT 1;

-- KPI GV22 (Kế toán)
INSERT IGNORE INTO `teacher_kpi`
    (`teacher_id`,`period_id`,`faculty_id`,
     `teaching_hours_plan`,`teaching_hours_actual`,
     `research_projects`,`papers_published`,`papers_in_progress`,
     `thesis_supervised`,`project_graded`,`training_courses`,
     `note`,`status`)
SELECT 22, p.id, 2,
    320, 300, 2, 3, 2, 4, 6, 3,
    'Đề tài cấp trường đang thực hiện', 'submitted'
FROM teacher_kpi_periods p WHERE p.period_name = 'Năm học 2025-2026' LIMIT 1;

-- ── Cập nhật current_students cho các lớp HP ─────────────────
UPDATE course_sections cs SET current_students = (
    SELECT COUNT(*) FROM student_subjects ss
    WHERE ss.course_section_id = cs.id AND ss.status = 'registered'
);

SELECT 'Seed 006: exam schedules, notifications, wishes, KPI done' AS status;
