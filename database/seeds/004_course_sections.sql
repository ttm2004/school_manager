-- ============================================================
-- Seed: Lớp học phần HK1 2025-2026 (semester_id=1)
-- Đủ workflow: open, proposed, draft, có GV, không có GV
-- ============================================================
SET NAMES utf8mb4;

-- ── Lớp HP ngành CNTT đang mở (có GV) ───────────────────────
INSERT IGNORE INTO `course_sections`
    (`id`,`subject_id`,`teacher_id`,`semester_id`,`section_code`,
     `room`,`max_students`,`current_students`,`status`,
     `day_sessions`,`start_date`,`end_date`,`teaching_mode`)
VALUES
-- CNTT101 - Nhập môn lập trình
(100, (SELECT id FROM subjects WHERE subject_code='CNTT101' LIMIT 1),
 1, 1, 'CNTT101_01', 'A101', 45, 38, 'open', '2:sang,4:sang', '2025-09-08', '2026-01-10', 'offline'),
(101, (SELECT id FROM subjects WHERE subject_code='CNTT101' LIMIT 1),
 2, 1, 'CNTT101_02', 'A102', 45, 42, 'open', '3:chieu,5:chieu', '2025-09-08', '2026-01-10', 'offline'),

-- CNTT201 - CTDL
(102, (SELECT id FROM subjects WHERE subject_code='CNTT201' LIMIT 1),
 3, 1, 'CNTT201_01', 'B201', 40, 35, 'open', '2:chieu,4:chieu', '2025-09-08', '2026-01-10', 'offline'),

-- CNTT203 - CSDL
(103, (SELECT id FROM subjects WHERE subject_code='CNTT203' LIMIT 1),
 8, 1, 'CNTT203_01', 'C101', 40, 30, 'open', '3:sang,5:sang', '2025-09-08', '2026-01-10', 'offline'),

-- CNTT401 - Lập trình web
(104, (SELECT id FROM subjects WHERE subject_code='CNTT401' LIMIT 1),
 9, 1, 'CNTT401_01', 'C102', 35, 28, 'open', '2:toi,4:toi', '2025-09-08', '2026-01-10', 'hybrid'),

-- CNTT402 - An toàn thông tin
(105, (SELECT id FROM subjects WHERE subject_code='CNTT402' LIMIT 1),
 NULL, 1, 'CNTT402_01', 'B202', 40, 0, 'open', '5:chieu,7:chieu', '2025-09-08', '2026-01-10', 'offline'),

-- ── Lớp HP ngành Kế toán đang mở ─────────────────────────────
-- KETO010 - Kế toán tài chính 1
(110, (SELECT id FROM subjects WHERE subject_code='KETO010' LIMIT 1),
 22, 1, 'KETO010_01', 'A201', 50, 45, 'open', '2:sang,4:sang', '2025-09-08', '2026-01-10', 'offline'),
(111, (SELECT id FROM subjects WHERE subject_code='KETO010' LIMIT 1),
 22, 1, 'KETO010_02', 'A202', 50, 48, 'open', '3:chieu,5:chieu', '2025-09-08', '2026-01-10', 'offline'),

-- LING166 - Nguyên lý kế toán
(112, (SELECT id FROM subjects WHERE subject_code='LING166' LIMIT 1),
 23, 1, 'LING166_01', 'A203', 55, 50, 'open', '2:chieu,4:chieu', '2025-09-08', '2026-01-10', 'offline'),

-- KETO018 - Lý thuyết kiểm toán
(113, (SELECT id FROM subjects WHERE subject_code='KETO018' LIMIT 1),
 22, 1, 'KETO018_01', 'B101', 45, 40, 'open', '3:sang,5:sang', '2025-09-08', '2026-01-10', 'offline'),

-- LING330 - Thuế (chưa có GV)
(114, (SELECT id FROM subjects WHERE subject_code='LING330' LIMIT 1),
 NULL, 1, 'LING330_01', 'B102', 45, 0, 'open', '6:sang,7:sang', '2025-09-08', '2026-01-10', 'offline'),

-- ── Lớp HP đề xuất từ Khoa (workflow) ────────────────────────
-- Khoa CNTT đề xuất mở lớp CNTT403 - AI
(120, (SELECT id FROM subjects WHERE subject_code='CNTT403' LIMIT 1),
 NULL, 1, 'CNTT403_01', NULL, 35, 0, 'proposed',
 '3:toi,5:toi', '2025-09-08', '2026-01-10', 'hybrid'),

-- Khoa Kinh tế đề xuất mở lớp KETO008 - KT Quản trị
(121, (SELECT id FROM subjects WHERE subject_code='KETO008' LIMIT 1),
 NULL, 1, 'KETO008_01', NULL, 45, 0, 'proposed',
 '2:chieu,4:chieu', '2025-09-08', '2026-01-10', 'offline'),

-- ── Lớp HP nháp (draft) ──────────────────────────────────────
(122, (SELECT id FROM subjects WHERE subject_code='CNTT404' LIMIT 1),
 NULL, 1, 'CNTT404_01', NULL, 30, 0, 'draft',
 '7:sang', '2025-09-08', '2026-01-10', 'offline'),

-- ── Lớp HP đã đóng (HK2) ─────────────────────────────────────
(130, (SELECT id FROM subjects WHERE subject_code='CNTT102' LIMIT 1),
 1, 2, 'CNTT102_01', 'A101', 45, 40, 'closed', '2:sang,4:sang', '2026-02-08', '2026-06-10', 'offline'),
(131, (SELECT id FROM subjects WHERE subject_code='CNTT202' LIMIT 1),
 2, 2, 'CNTT202_01', 'A102', 45, 38, 'closed', '3:chieu,5:chieu', '2026-02-08', '2026-06-10', 'offline'),
(132, (SELECT id FROM subjects WHERE subject_code='KETO011' LIMIT 1),
 22, 2, 'KETO011_01', 'A201', 50, 46, 'closed', '2:sang,4:sang', '2026-02-08', '2026-06-10', 'offline');

-- ── Cập nhật proposal info cho lớp đề xuất ───────────────────
UPDATE `course_sections` SET
    `open_proposed_by`   = 340,
    `open_proposed_at`   = '2026-04-15 09:00:00',
    `open_proposal_note` = 'Đề xuất mở lớp AI cho SV năm 3 CNTT. Dự kiến 35 SV.',
    `expected_students`  = 35,
    `teaching_mode`      = 'hybrid'
WHERE id = 120;

UPDATE `course_sections` SET
    `open_proposed_by`   = 342,
    `open_proposed_at`   = '2026-04-16 10:30:00',
    `open_proposal_note` = 'Đề xuất mở lớp KT Quản trị cho SV năm 3 Kế toán.',
    `expected_students`  = 45
WHERE id = 121;

-- ── Đề xuất phân công GV (proposal_status=pending) ───────────
UPDATE `course_sections` SET
    `proposed_teacher_id` = 20,
    `proposal_status`     = 'pending'
WHERE id = 105; -- CNTT402 chưa có GV, Khoa đề xuất GV020

SELECT 'Seed 004: course_sections done' AS status;
SELECT status, COUNT(*) AS cnt FROM course_sections GROUP BY status;
