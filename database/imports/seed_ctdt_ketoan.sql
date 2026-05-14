-- ============================================================
-- CHƯƠNG TRÌNH ĐÀO TẠO - Ngành Kế toán (major_id = 5)
-- Nguồn: chuong_trinh_dao_tao.csv (74 môn học)
-- Năm học: 2022-2023 đến 2025-2026
-- ============================================================

SET NAMES utf8mb4;
USE edu_management;

-- Lấy major_id ngành Kế toán
SET @kt_id = (SELECT id FROM majors WHERE major_code = '7340301' LIMIT 1);

-- Xóa dữ liệu cũ của ngành Kế toán (subjects + curriculum)
DELETE FROM curriculum WHERE major_id = @kt_id;
DELETE FROM subjects WHERE major_id = @kt_id;

-- ============================================================
-- INSERT SUBJECTS (74 môn)
-- Cấu trúc: major_id, subject_code, subject_name, credits,
--           theory_periods, practice_periods, total_periods,
--           subject_type (Bắt buộc/Tự chọn), is_mandatory,
--           semester_order, subject_type_new
-- ============================================================

INSERT INTO subjects
    (major_id, subject_code, subject_name, credits, theory_periods, practice_periods, total_periods, subject_type, is_mandatory, semester_order, subject_type_new)
VALUES
-- ── HỌC KỲ 1 - 2022-2023 ──────────────────────────────────
(@kt_id, 'KETO023', 'Nhập môn ngành Kế toán',                          2,   0,  60,  60, 'Bắt buộc', 1, 1, 'required'),
(@kt_id, 'LING127', 'Luật kinh tế',                                     2,  30,   0,  30, 'Bắt buộc', 1, 1, 'required'),
(@kt_id, 'LING185', 'Pháp luật',                                        2,  30,   0,  30, 'Bắt buộc', 1, 1, 'required'),
(@kt_id, 'LING346', 'Toán cao cấp C1',                                  2,  30,   0,  30, 'Bắt buộc', 1, 1, 'required'),

-- ── HỌC KỲ 2 - 2022-2023 ──────────────────────────────────
(@kt_id, 'KTCH001', 'Phương pháp nghiên cứu khoa học',                  3,  45,   0,  45, 'Bắt buộc', 1, 2, 'general'),
(@kt_id, 'KTCH002', 'Giáo dục thể chất (Lý thuyết)',                    2,  30,   0,  30, 'Bắt buộc', 1, 2, 'general'),
(@kt_id, 'LING095', 'Kinh tế vi mô',                                    2,  30,   0,  30, 'Bắt buộc', 1, 2, 'required'),
(@kt_id, 'LING138', 'Marketing căn bản',                                3,  45,   0,  45, 'Bắt buộc', 1, 2, 'required'),
(@kt_id, 'LING169', 'Nguyên lý thống kê kinh tế',                       2,   0,  60,  60, 'Bắt buộc', 1, 2, 'required'),
(@kt_id, 'LING347', 'Toán cao cấp C2',                                  2,  30,   0,  30, 'Bắt buộc', 1, 2, 'required'),

-- ── HỌC KỲ 3 - 2022-2023 ──────────────────────────────────
(@kt_id, 'KETO016', 'Thực tập doanh nghiệp 1',                          1,   0,  30,  30, 'Bắt buộc', 1, 3, 'required'),
(@kt_id, 'KTCH003', 'Giáo dục quốc phòng an ninh',                      5,  75,   0,  75, 'Bắt buộc', 1, 3, 'general'),
(@kt_id, 'KTCH004', 'Thực hành Giáo dục quốc phòng an ninh',            3,   0,  90,  90, 'Bắt buộc', 1, 3, 'general'),
(@kt_id, 'LING166', 'Nguyên lý kế toán',                                2,  30,   0,  30, 'Bắt buộc', 1, 3, 'required'),
(@kt_id, 'LING293', 'Thực hành nguyên lý kế toán',                      1,   0,  30,  30, 'Bắt buộc', 1, 3, 'required'),
(@kt_id, 'LING330', 'Thuế',                                             3,  45,   0,  45, 'Bắt buộc', 1, 3, 'required'),

-- ── HỌC KỲ 1 - 2023-2024 ──────────────────────────────────
(@kt_id, 'KETO010', 'Kế toán tài chính 1',                              2,  30,   0,  30, 'Bắt buộc', 1, 4, 'required'),
(@kt_id, 'KETO025', 'Thực hành kế toán tài chính 1',                    1,   0,  30,  30, 'Bắt buộc', 1, 4, 'required'),
(@kt_id, 'KETO028', 'Tài chính và quản lý tài chính',                   3,  45,   0,  45, 'Bắt buộc', 1, 4, 'required'),
(@kt_id, 'KTCH005', 'Tư duy biện luận ứng dụng',                        2,  30,   0,  30, 'Bắt buộc', 1, 4, 'general'),
(@kt_id, 'LING096', 'Kinh tế vĩ mô',                                    2,  30,   0,  30, 'Bắt buộc', 1, 4, 'required'),
(@kt_id, 'LING221', 'Quản trị hành chính văn phòng',                    2,   0,  60,  60, 'Bắt buộc', 1, 4, 'required'),
(@kt_id, 'LING222', 'Quản trị học',                                     2,  30,   0,  30, 'Bắt buộc', 1, 4, 'required'),
(@kt_id, 'LING238', 'Tài chính tiền tệ',                                2,  30,   0,  30, 'Bắt buộc', 1, 4, 'required'),

-- ── HỌC KỲ 2 - 2023-2024 ──────────────────────────────────
(@kt_id, 'KETO011', 'Kế toán tài chính 2',                              2,  30,   0,  30, 'Bắt buộc', 1, 5, 'required'),
(@kt_id, 'KETO022', 'Thực hành kế toán tài chính 2',                    1,   0,  30,  30, 'Bắt buộc', 1, 5, 'required'),
(@kt_id, 'KTCH006', 'Triết học Mác - Lênin',                            3,  45,   0,  45, 'Bắt buộc', 1, 5, 'general'),
(@kt_id, 'KTCH007', 'Giáo dục thể chất (Thực hành)',                    3,   0,  45,  45, 'Bắt buộc', 0, 5, 'general'),
(@kt_id, 'KTCH008', 'Kinh tế chính trị Mác - Lênin',                    2,  30,   0,  30, 'Bắt buộc', 1, 5, 'general'),
(@kt_id, 'KTCH013', 'Giáo dục thể chất (Thực hành ngoài Trường)',       3,   0,  45,  45, 'Tự chọn',  0, 5, 'elective'),
(@kt_id, 'LING070', 'Hệ thống thông tin kế toán',                       2,  30,   0,  30, 'Bắt buộc', 1, 5, 'required'),
(@kt_id, 'LING277', 'Thực hành hệ thống thông tin kế toán',             1,   0,  30,  30, 'Bắt buộc', 1, 5, 'required'),
(@kt_id, 'LING440', 'Kinh tế phát triển',                               2,   0,  60,  60, 'Bắt buộc', 1, 5, 'required'),

-- ── HỌC KỲ 3 - 2023-2024 ──────────────────────────────────
(@kt_id, 'KETO018', 'Lý thuyết kiểm toán',                              3,  45,   0,  45, 'Bắt buộc', 1, 6, 'required'),

-- ── HỌC KỲ 1 - 2024-2025 ──────────────────────────────────
(@kt_id, 'KETO012', 'Kế toán tài chính 3',                              3,  45,   0,  45, 'Bắt buộc', 1, 7, 'required'),
(@kt_id, 'KETO013', 'Kế toán tài chính 4',                              3,  45,   0,  45, 'Bắt buộc', 1, 7, 'required'),
(@kt_id, 'KETO014', 'Khai báo thuế',                                    2,   0,  60,  60, 'Bắt buộc', 1, 7, 'required'),
(@kt_id, 'KETO015', 'Thực tập doanh nghiệp 2',                          3,   0,  90,  90, 'Bắt buộc', 1, 7, 'required'),
(@kt_id, 'KETO019', 'Thực hành mô phỏng 1',                             2,   0,  60,  60, 'Bắt buộc', 1, 7, 'required'),
(@kt_id, 'KTCH009', 'Những vấn đề kinh tế - xã hội Đông Nam bộ',        2,  30,   0,  30, 'Bắt buộc', 0, 7, 'general'),
(@kt_id, 'LING470', 'Kinh tế tuần hoàn',                                2,  30,   0,  30, 'Tự chọn',  0, 7, 'elective'),

-- ── HỌC KỲ 2 - 2024-2025 ──────────────────────────────────
(@kt_id, 'KETO005', 'Kế toán excel',                                    2,   0,  60,  60, 'Tự chọn',  0, 8, 'elective'),
(@kt_id, 'KETO008', 'Kế toán Quản trị',                                 2,  30,   0,  30, 'Bắt buộc', 1, 8, 'required'),
(@kt_id, 'KETO017', 'Thực hành mô phỏng 2',                             3,   0,  90,  90, 'Bắt buộc', 1, 8, 'required'),
(@kt_id, 'KETO020', 'Nghiệp vụ ngân hàng thương mại',                   3,  45,   0,  45, 'Tự chọn',  0, 8, 'elective'),
(@kt_id, 'KETO024', 'Phần mềm kế toán Misa',                            2,   0,  60,  60, 'Bắt buộc', 0, 8, 'required'),
(@kt_id, 'KETO026', 'Thực hành kế toán quản trị',                       1,   0,  30,  30, 'Bắt buộc', 1, 8, 'required'),
(@kt_id, 'KETO034', 'Kiểm toán nội bộ',                                 3,  45,   0,  45, 'Bắt buộc', 0, 8, 'required'),
(@kt_id, 'KTCH010', 'Chủ nghĩa xã hội khoa học',                        2,  30,   0,  30, 'Bắt buộc', 1, 8, 'general'),
(@kt_id, 'KTCH011', 'Tư tưởng Hồ Chí Minh',                             2,  30,   0,  30, 'Bắt buộc', 1, 8, 'general'),
(@kt_id, 'TCNH034', 'Kinh tế lượng',                                    3,  45,   0,  45, 'Tự chọn',  0, 8, 'elective'),

-- ── HỌC KỲ 1 - 2025-2026 ──────────────────────────────────
(@kt_id, 'KETO004', 'Kế toán chi phí',                                  2,  30,   0,  30, 'Bắt buộc', 1, 9, 'required'),
(@kt_id, 'KETO027', 'Thực hành kế toán chi phí',                        1,   0,  30,  30, 'Bắt buộc', 1, 9, 'required'),
(@kt_id, 'KETO029', 'Thị trường chứng khoán',                           3,  45,   0,  45, 'Tự chọn',  0, 9, 'elective'),
(@kt_id, 'KETO030', 'Thanh toán quốc tế',                               3,  45,   0,  45, 'Tự chọn',  0, 9, 'elective'),
(@kt_id, 'KETO031', 'Kế toán tài chính hiện đại',                       3,  45,   0,  45, 'Bắt buộc', 0, 9, 'required'),
(@kt_id, 'KETO032', 'Kiểm toán báo cáo tài chính',                      3,  45,   0,  45, 'Tự chọn',  0, 9, 'elective'),
(@kt_id, 'KETO035', 'Kế toán ngân hàng thương mại',                     2,   0,  60,  60, 'Bắt buộc', 1, 9, 'required'),
(@kt_id, 'KETO036', 'Thực hành kiểm toán',                              2,   0,  60,  60, 'Bắt buộc', 1, 9, 'required'),
(@kt_id, 'KTCH012', 'Lịch sử Đảng Cộng sản Việt Nam',                   2,  30,   0,  30, 'Bắt buộc', 1, 9, 'general'),
(@kt_id, 'LING181', 'Phân tích hoạt động kinh doanh',                   3,  45,   0,  45, 'Bắt buộc', 1, 9, 'required'),

-- ── HỌC KỲ 2 - 2025-2026 ──────────────────────────────────
(@kt_id, 'KETO003', 'Thực tập tốt nghiệp',                              4,   0, 120, 120, 'Bắt buộc', 1, 10, 'required'),
(@kt_id, 'KETO006', 'Kế toán hành chính sự nghiệp',                     3,  45,   0,  45, 'Bắt buộc', 0, 10, 'required'),
(@kt_id, 'KETO009', 'Kế toán quốc tế',                                  3,  45,   0,  45, 'Tự chọn',  0, 10, 'elective'),
(@kt_id, 'KETO037', 'Phân tích báo cáo tài chính theo chuẩn mực IFRS',  3,  45,   0,  45, 'Tự chọn',  0, 10, 'elective'),
(@kt_id, 'KETO038', 'Tổ chức hệ thống kế toán trong các đơn vị',        3,  45,   0,  45, 'Tự chọn',  0, 10, 'elective'),
(@kt_id, 'KETO039', 'Khóa luận tốt nghiệp',                             7,   0, 210, 210, 'Bắt buộc', 1, 10, 'required'),
(@kt_id, 'KETO040', 'Kiểm soát nội bộ',                                 3,   0,  90,  90, 'Bắt buộc', 0, 10, 'required'),
(@kt_id, 'KETO041', 'Kế toán doanh nghiệp vừa và nhỏ',                  3,   0,  90,  90, 'Bắt buộc', 0, 10, 'required'),
(@kt_id, 'KETO042', 'Kế toán công ty',                                  3,   0,  90,  90, 'Bắt buộc', 1, 10, 'required'),
(@kt_id, 'KETO043', 'Tổ chức công tác kế toán quản trị trong doanh nghiệp', 3, 0, 90, 90, 'Bắt buộc', 0, 10, 'required'),
(@kt_id, 'KETO044', 'Kế toán dự án đầu tư',                             4,   0, 120, 120, 'Bắt buộc', 1, 10, 'required'),
(@kt_id, 'KITO009', 'Chuẩn mực báo cáo tài chính quốc tế',              2,  30,   0,  30, 'Bắt buộc', 1, 10, 'required'),
(@kt_id, 'TCNH044', 'Phân tích Báo cáo tài chính',                      4,   0, 120, 120, 'Tự chọn',  0, 10, 'elective');

-- ============================================================
-- INSERT CURRICULUM (liên kết subjects vào CTDT ngành Kế toán)
-- Mapping: semester_order → (semester_label, year_label)
-- 1 → HK1 2022-2023, 2 → HK2 2022-2023, 3 → HK3 2022-2023
-- 4 → HK1 2023-2024, 5 → HK2 2023-2024, 6 → HK3 2023-2024
-- 7 → HK1 2024-2025, 8 → HK2 2024-2025
-- 9 → HK1 2025-2026, 10 → HK2 2025-2026
-- ============================================================

INSERT INTO curriculum
    (major_id, subject_id, credits, suggested_semester, semester_label, year_label, subject_type)
SELECT
    @kt_id,
    s.id,
    s.credits,
    s.semester_order,
    CASE s.semester_order
        WHEN 1  THEN 'Học kỳ 1'
        WHEN 2  THEN 'Học kỳ 2'
        WHEN 3  THEN 'Học kỳ 3'
        WHEN 4  THEN 'Học kỳ 1'
        WHEN 5  THEN 'Học kỳ 2'
        WHEN 6  THEN 'Học kỳ 3'
        WHEN 7  THEN 'Học kỳ 1'
        WHEN 8  THEN 'Học kỳ 2'
        WHEN 9  THEN 'Học kỳ 1'
        WHEN 10 THEN 'Học kỳ 2'
        ELSE 'Học kỳ 1'
    END,
    CASE s.semester_order
        WHEN 1  THEN '2022-2023'
        WHEN 2  THEN '2022-2023'
        WHEN 3  THEN '2022-2023'
        WHEN 4  THEN '2023-2024'
        WHEN 5  THEN '2023-2024'
        WHEN 6  THEN '2023-2024'
        WHEN 7  THEN '2024-2025'
        WHEN 8  THEN '2024-2025'
        WHEN 9  THEN '2025-2026'
        WHEN 10 THEN '2025-2026'
        ELSE '2022-2023'
    END,
    s.subject_type_new
FROM subjects s
WHERE s.major_id = @kt_id;

-- ============================================================
-- Kiểm tra kết quả
-- ============================================================
SELECT
    CONCAT(c.semester_label, ' ', c.year_label) AS hoc_ky_nam_hoc,
    COUNT(*) AS so_mon,
    SUM(c.credits) AS tong_tin_chi
FROM curriculum c
WHERE c.major_id = @kt_id AND c.deleted_at IS NULL
GROUP BY c.year_label, c.semester_label, c.suggested_semester
ORDER BY c.suggested_semester;

SELECT CONCAT('Tong: ', COUNT(*), ' mon, ', SUM(credits), ' tin chi') AS ket_qua
FROM curriculum WHERE major_id = @kt_id AND deleted_at IS NULL;
