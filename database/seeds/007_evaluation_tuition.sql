-- ============================================================
-- Seed: Đánh giá giảng viên + Học phí
-- ============================================================
SET NAMES utf8mb4;

-- ── Đợt đánh giá (đã có sẵn trong DB, chỉ cập nhật) ─────────
UPDATE `evaluation_periods` SET
    `start_date` = '2025-12-01 08:00:00',
    `end_date`   = '2026-01-10 23:59:59',
    `status`     = 'open'
WHERE id = 1;

UPDATE `evaluation_periods` SET
    `start_date` = '2026-05-01 08:00:00',
    `end_date`   = '2026-06-10 23:59:59',
    `status`     = 'closed'
WHERE id = 2;

-- ── Câu hỏi đánh giá (đã có 15 câu, không cần thêm) ─────────
-- evaluation_questions đã có sẵn id 1-15 trong edu_management.sql

-- ── Kết quả đánh giá HK2 (per question) ──────────────────────
-- student_evaluations: student_id, course_section_id, teacher_id, period_id, question_id, rating, comment
-- SV1 đánh giá GV1 lớp CNTT102_01 (section_id=130), period_id=2
INSERT IGNORE INTO `student_evaluations`
    (`student_id`,`course_section_id`,`teacher_id`,`period_id`,`question_id`,`rating`,`comment`)
VALUES
(1, 130, 1, 2, 1, 5, NULL),(1, 130, 1, 2, 2, 5, NULL),(1, 130, 1, 2, 3, 4, NULL),
(1, 130, 1, 2, 4, 5, NULL),(1, 130, 1, 2, 5, 4, NULL),(1, 130, 1, 2, 6, 5, NULL),
(1, 130, 1, 2, 7, 4, NULL),(1, 130, 1, 2, 8, 5, NULL),(1, 130, 1, 2, 9, 4, NULL),
(1, 130, 1, 2, 10, 5, NULL),(1, 130, 1, 2, 11, 4, NULL),(1, 130, 1, 2, 12, 5, NULL),
(1, 130, 1, 2, 13, 4, NULL),(1, 130, 1, 2, 14, 5, NULL);

-- SV2 đánh giá GV1 lớp CNTT102_01
INSERT IGNORE INTO `student_evaluations`
    (`student_id`,`course_section_id`,`teacher_id`,`period_id`,`question_id`,`rating`)
VALUES
(2, 130, 1, 2, 1, 4),(2, 130, 1, 2, 2, 4),(2, 130, 1, 2, 3, 5),
(2, 130, 1, 2, 4, 4),(2, 130, 1, 2, 5, 4),(2, 130, 1, 2, 6, 4),
(2, 130, 1, 2, 7, 3),(2, 130, 1, 2, 8, 4),(2, 130, 1, 2, 9, 4),
(2, 130, 1, 2, 10, 4),(2, 130, 1, 2, 11, 4),(2, 130, 1, 2, 12, 4),
(2, 130, 1, 2, 13, 4),(2, 130, 1, 2, 14, 4);

-- SV4 đánh giá GV1
INSERT IGNORE INTO `student_evaluations`
    (`student_id`,`course_section_id`,`teacher_id`,`period_id`,`question_id`,`rating`)
VALUES
(4, 130, 1, 2, 1, 5),(4, 130, 1, 2, 2, 4),(4, 130, 1, 2, 3, 5),
(4, 130, 1, 2, 4, 5),(4, 130, 1, 2, 5, 5),(4, 130, 1, 2, 6, 5),
(4, 130, 1, 2, 7, 5),(4, 130, 1, 2, 8, 4),(4, 130, 1, 2, 9, 5),
(4, 130, 1, 2, 10, 5),(4, 130, 1, 2, 11, 5),(4, 130, 1, 2, 12, 4),
(4, 130, 1, 2, 13, 5),(4, 130, 1, 2, 14, 5);

-- SV3 đánh giá GV22 lớp KETO011_01 (section_id=132)
INSERT IGNORE INTO `student_evaluations`
    (`student_id`,`course_section_id`,`teacher_id`,`period_id`,`question_id`,`rating`,`comment`)
VALUES
(3, 132, 22, 2, 1, 5, NULL),(3, 132, 22, 2, 2, 5, NULL),(3, 132, 22, 2, 3, 5, NULL),
(3, 132, 22, 2, 4, 5, NULL),(3, 132, 22, 2, 5, 5, NULL),(3, 132, 22, 2, 6, 5, NULL),
(3, 132, 22, 2, 7, 5, NULL),(3, 132, 22, 2, 8, 4, NULL),(3, 132, 22, 2, 9, 5, NULL),
(3, 132, 22, 2, 10, 5, NULL),(3, 132, 22, 2, 11, 4, NULL),(3, 132, 22, 2, 12, 5, NULL),
(3, 132, 22, 2, 13, 5, NULL),(3, 132, 22, 2, 14, 5, NULL);

-- Comment tổng hợp
INSERT IGNORE INTO `student_extra_comments`
    (`student_id`,`course_section_id`,`teacher_id`,`period_id`,`comment`)
VALUES
(1, 130, 1, 2, 'Thầy dạy rất hay, dễ hiểu, nhiệt tình hỗ trợ sinh viên'),
(3, 132, 22, 2, 'Cô dạy rất tận tâm, nội dung thực tế và bổ ích');

-- ── Học phí ───────────────────────────────────────────────────
-- Đợt thu học phí (tuition_periods cần: semester_id, title, open_date, due_date, status)
INSERT IGNORE INTO `tuition_periods`
    (`id`,`semester_id`,`title`,`open_date`,`due_date`,`status`,`created_by`)
VALUES
(10, 1, 'Thu học phí HK1 2025-2026 - Đợt 1', '2025-08-25', '2025-09-30', 'published', 1),
(11, 1, 'Thu học phí HK1 2025-2026 - Đợt 2', '2025-10-01', '2025-11-30', 'published', 1),
(12, 2, 'Thu học phí HK2 2025-2026',          '2026-01-15', '2026-03-31', 'closed',    1);

-- Hóa đơn học phí (cần: period_id, student_id, semester_id, total_credits, unit_price, gross_amount, discount, net_amount, paid_amount, due_date, status)
INSERT IGNORE INTO `tuition_invoices`
    (`period_id`,`student_id`,`semester_id`,`total_credits`,`unit_price`,
     `gross_amount`,`discount`,`net_amount`,`paid_amount`,`due_date`,`status`,`created_by`)
VALUES
-- SV1: đã đóng đủ (4 môn × 3TC = 12TC × 450k)
(10, 1, 1, 12, 450000, 5400000, 0, 5400000, 5400000, '2025-09-30', 'paid', 1),
-- SV2: đã đóng đủ
(10, 2, 1, 9,  450000, 4050000, 0, 4050000, 4050000, '2025-09-30', 'paid', 1),
-- SV3: nợ học phí (overdue)
(10, 3, 1, 12, 450000, 5400000, 0, 5400000, 0,       '2025-09-30', 'overdue', 1),
-- SV4: đóng một phần (partial)
(10, 4, 1, 9,  450000, 4050000, 0, 4050000, 2000000, '2025-09-30', 'partial', 1),
-- SV5: đã đóng đủ
(10, 5, 1, 9,  450000, 4050000, 0, 4050000, 4050000, '2025-09-30', 'paid', 1),
-- SV6: chưa đóng (unpaid)
(10, 6, 1, 9,  450000, 4050000, 0, 4050000, 0,       '2025-09-30', 'unpaid', 1),
-- SV7: đã đóng
(10, 7, 1, 9,  450000, 4050000, 0, 4050000, 4050000, '2025-09-30', 'paid', 1),
-- SV8: đã đóng
(10, 8, 1, 9,  450000, 4050000, 0, 4050000, 4050000, '2025-09-30', 'paid', 1);

-- Ghi nhận thanh toán cho SV đã đóng
INSERT IGNORE INTO `tuition_payments`
    (`invoice_id`,`amount`,`method`,`reference`,`paid_by`)
SELECT ti.id, ti.paid_amount, 'bank_transfer',
    CONCAT('TT', LPAD(ti.student_id, 4, '0'), '2025'), 1
FROM tuition_invoices ti
WHERE ti.period_id = 10 AND ti.status = 'paid';

-- Thanh toán một phần SV4
INSERT IGNORE INTO `tuition_payments`
    (`invoice_id`,`amount`,`method`,`reference`,`paid_by`)
SELECT ti.id, 2000000, 'cash', 'TT00042025A', 1
FROM tuition_invoices ti
WHERE ti.period_id = 10 AND ti.student_id = 4;

SELECT 'Seed 007: evaluation & tuition done' AS status;
SELECT
    (SELECT COUNT(*) FROM student_evaluations) AS evaluations,
    (SELECT COUNT(*) FROM tuition_invoices WHERE period_id IN (10,11,12)) AS invoices,
    (SELECT COUNT(*) FROM tuition_periods WHERE id IN (10,11,12)) AS periods,
    (SELECT COUNT(*) FROM tuition_payments) AS payments;
