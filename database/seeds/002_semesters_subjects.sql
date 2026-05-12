-- ============================================================
-- Seed: Học kỳ đầy đủ + Môn học ngành Kế toán từ CSV
-- ============================================================
SET NAMES utf8mb4;

-- ── Cập nhật học kỳ hiện có ───────────────────────────────────
UPDATE `semesters` SET
    `status`                = 'active',
    `start_date`            = '2025-09-01',
    `end_date`              = '2026-01-15',
    `register_start`        = '2025-08-01 08:00:00',
    `register_end`          = '2025-08-20 23:59:59',
    `grade_submit_deadline` = '2026-01-31',
    `proposal_deadline`     = '2025-07-15'
WHERE id = 1;

UPDATE `semesters` SET
    `status`                = 'closed',
    `start_date`            = '2026-02-01',
    `end_date`              = '2026-06-15',
    `register_start`        = '2026-01-10 08:00:00',
    `register_end`          = '2026-01-25 23:59:59',
    `grade_submit_deadline` = '2026-06-30',
    `proposal_deadline`     = '2025-12-15'
WHERE id = 2;

-- Thêm học kỳ mới
INSERT IGNORE INTO `semesters`
    (`id`,`semester_name`,`school_year`,`start_date`,`end_date`,
     `register_start`,`register_end`,`grade_submit_deadline`,`proposal_deadline`,`status`)
VALUES
(3, 'Học kỳ hè', '2025-2026', '2026-06-20', '2026-08-10',
    '2026-06-01 08:00:00', '2026-06-10 23:59:59', '2026-08-20', '2026-05-20', 'upcoming'),
(4, 'Học kỳ 1',  '2026-2027', '2026-09-01', '2027-01-15',
    '2026-08-01 08:00:00', '2026-08-20 23:59:59', '2027-01-31', '2026-07-15', 'upcoming');

-- ── Môn học ngành Kế toán (từ CSV) ───────────────────────────
-- major_id = 5 (Kế toán)
INSERT IGNORE INTO `subjects`
    (`subject_code`,`subject_name`,`major_id`,`credits`,`description`,`subject_type`,`semester_order`)
VALUES
('KETO023','Nhập môn ngành Kế toán',5,2,'Giới thiệu ngành Kế toán','required',1),
('LING127','Luật kinh tế',5,2,'Pháp luật kinh tế cơ bản','required',1),
('LING185','Pháp luật',5,2,'Pháp luật đại cương','required',1),
('LING346','Toán cao cấp C1',5,2,'Giải tích, đạo hàm','required',1),
('KTCH001','Phương pháp nghiên cứu khoa học',5,3,'PPNCKH','required',2),
('LING095','Kinh tế vi mô',5,2,'Kinh tế vi mô cơ bản','required',2),
('LING138','Marketing căn bản',5,3,'Marketing cơ bản','required',2),
('LING169','Nguyên lý thống kê kinh tế',5,2,'Thống kê kinh tế','required',2),
('LING347','Toán cao cấp C2',5,2,'Đại số tuyến tính','required',2),
('LING166','Nguyên lý kế toán',5,2,'Kế toán cơ bản','required',3),
('LING293','Thực hành nguyên lý kế toán',5,1,'Thực hành kế toán','required',3),
('LING330','Thuế',5,3,'Luật thuế và thực hành','required',3),
('KETO010','Kế toán tài chính 1',5,2,'KTTC phần 1','required',4),
('KETO025','Thực hành kế toán tài chính 1',5,1,'Thực hành KTTC 1','required',4),
('KETO028','Tài chính và quản lý tài chính',5,3,'Tài chính doanh nghiệp','required',4),
('LING096','Kinh tế vĩ mô',5,2,'Kinh tế vĩ mô cơ bản','required',4),
('LING238','Tài chính tiền tệ',5,2,'Tài chính tiền tệ','required',4),
('KETO011','Kế toán tài chính 2',5,2,'KTTC phần 2','required',5),
('KETO022','Thực hành kế toán tài chính 2',5,1,'Thực hành KTTC 2','required',5),
('LING070','Hệ thống thông tin kế toán',5,2,'HTTT kế toán','required',5),
('LING277','Thực hành hệ thống thông tin kế toán',5,1,'Thực hành HTTT','required',5),
('KETO018','Lý thuyết kiểm toán',5,3,'Kiểm toán cơ bản','required',6),
('KETO012','Kế toán tài chính 3',5,3,'KTTC phần 3','required',6),
('KETO013','Kế toán tài chính 4',5,3,'KTTC phần 4','required',6),
('KETO014','Khai báo thuế',5,2,'Thực hành khai báo thuế','required',6),
('KETO008','Kế toán Quản trị',5,2,'Kế toán quản trị','required',7),
('KETO017','Thực hành mô phỏng 2',5,3,'Mô phỏng kế toán 2','required',7),
('KETO024','Phần mềm kế toán Misa',5,2,'Thực hành Misa','required',7),
('KETO026','Thực hành kế toán quản trị',5,1,'Thực hành KTQT','required',7),
('KETO034','Kiểm toán nội bộ',5,3,'Kiểm toán nội bộ','required',7),
('KETO004','Kế toán chi phí',5,2,'Kế toán chi phí','required',8),
('KETO027','Thực hành kế toán chi phí',5,1,'Thực hành KT chi phí','required',8),
('KETO031','Kế toán tài chính hiện đại',5,3,'KTTC hiện đại','required',8),
('KETO035','Kế toán ngân hàng thương mại',5,2,'KT ngân hàng','required',8),
('KETO036','Thực hành kiểm toán',5,2,'Thực hành kiểm toán','required',8),
('LING181','Phân tích hoạt động kinh doanh',5,3,'Phân tích HĐKD','required',8),
('KETO003','Thực tập tốt nghiệp',5,4,'Thực tập tốt nghiệp','required',9),
('KETO039','Khóa luận tốt nghiệp',5,7,'Khóa luận TN','required',9),
('KETO042','Kế toán công ty',5,3,'Kế toán công ty','required',9),
('KETO044','Kế toán dự án đầu tư',5,4,'KT dự án đầu tư','required',9),
-- Môn tự chọn
('KETO005','Kế toán excel',5,2,'Excel kế toán','elective',7),
('KETO020','Nghiệp vụ ngân hàng thương mại',5,3,'NV ngân hàng','elective',7),
('KETO029','Thị trường chứng khoán',5,3,'Chứng khoán','elective',8),
('KETO030','Thanh toán quốc tế',5,3,'TT quốc tế','elective',8),
('KETO032','Kiểm toán báo cáo tài chính',5,3,'Kiểm toán BCTC','elective',8),
('TCNH034','Kinh tế lượng',5,3,'Kinh tế lượng','elective',7),
('KETO006','Kế toán hành chính sự nghiệp',5,3,'KT HCSN','elective',9),
('KETO009','Kế toán quốc tế',5,3,'KT quốc tế','elective',9);

-- ── Môn học ngành CNTT (bổ sung thêm) ────────────────────────
-- major_id = 1 (CNTT)
INSERT IGNORE INTO `subjects`
    (`subject_code`,`subject_name`,`major_id`,`credits`,`description`,`subject_type`,`semester_order`)
VALUES
('CNTT101','Nhập môn lập trình',1,3,'Lập trình C/C++ cơ bản','required',1),
('CNTT102','Toán rời rạc',1,3,'Logic, tập hợp, đồ thị','required',1),
('CNTT201','Cấu trúc dữ liệu và giải thuật',1,3,'CTDL và giải thuật','required',2),
('CNTT202','Lập trình hướng đối tượng',1,3,'OOP với Java','required',2),
('CNTT203','Cơ sở dữ liệu',1,3,'Mô hình quan hệ, SQL','required',3),
('CNTT301','Mạng máy tính',1,3,'TCP/IP, OSI','required',3),
('CNTT302','Hệ điều hành',1,3,'Quản lý tiến trình, bộ nhớ','required',4),
('CNTT303','Công nghệ phần mềm',1,3,'Quy trình phát triển PM','required',4),
('CNTT401','Lập trình web',1,3,'HTML, CSS, JS, PHP','required',5),
('CNTT402','An toàn thông tin',1,3,'Bảo mật mạng, mã hóa','required',5),
('CNTT403','Trí tuệ nhân tạo',1,3,'ML, AI cơ bản','required',6),
('CNTT404','Lập trình di động',1,3,'Android/iOS','required',6),
('CNTT501','Đồ án tốt nghiệp',1,7,'Đồ án TN','required',8);

SELECT 'Seed 002: semesters & subjects done' AS status;
