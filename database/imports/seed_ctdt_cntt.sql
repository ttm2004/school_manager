-- =====================================================
-- CHƯƠNG TRÌNH ĐÀO TẠO - Ngành Công nghệ thông tin
-- Mã ngành: 7480201 | Trường ĐH Thủ Dầu Một
-- Dựa trên CTĐT kế hoạch thực tế
-- =====================================================

USE school_registration;

-- Thêm cột nếu chưa có
ALTER TABLE subjects ADD COLUMN IF NOT EXISTS subject_type_new ENUM('required','elective','general') NOT NULL DEFAULT 'required';
ALTER TABLE subjects ADD COLUMN IF NOT EXISTS semester_order TINYINT NOT NULL DEFAULT 1;
ALTER TABLE subjects ADD COLUMN IF NOT EXISTS theory_periods INT DEFAULT 30;
ALTER TABLE subjects ADD COLUMN IF NOT EXISTS practice_periods INT DEFAULT 0;
ALTER TABLE subjects ADD COLUMN IF NOT EXISTS is_mandatory TINYINT(1) DEFAULT 1;

-- Lấy major_id của ngành CNTT
SET @cntt_id = (SELECT id FROM majors WHERE major_code = '7480201' LIMIT 1);

-- Xóa môn cũ của ngành CNTT (nếu muốn reset)
-- DELETE FROM subjects WHERE major_id = @cntt_id;

-- =====================================================
-- HỌC KỲ 1 - Năm học 2022-2023 (7 TC)
-- =====================================================
INSERT INTO subjects (major_id, subject_code, subject_name, credits, theory_periods, practice_periods, subject_type_new, semester_order, is_mandatory, description) VALUES
(@cntt_id, 'LING022', 'Cơ sở lập trình', 3, 45, 0, 'required', 1, 1, 'Lập trình căn bản với ngôn ngữ C/C++'),
(@cntt_id, 'LING175', 'Nhập môn nhóm ngành Công nghệ thông tin', 2, 30, 0, 'required', 1, 1, 'Tổng quan về ngành CNTT'),
(@cntt_id, 'LING266', 'Thực hành Cơ sở lập trình', 1, 0, 30, 'required', 1, 1, 'Thực hành lập trình C/C++'),
(@cntt_id, 'LING295', 'Thực hành Nhập môn nhóm ngành CNTT', 1, 0, 30, 'required', 1, 1, 'Thực hành nhập môn CNTT');

-- =====================================================
-- HỌC KỲ 2 - Năm học 2022-2023 (13 TC)
-- =====================================================
INSERT INTO subjects (major_id, subject_code, subject_name, credits, theory_periods, practice_periods, subject_type_new, semester_order, is_mandatory, description) VALUES
(@cntt_id, 'KTCH001', 'Phương pháp nghiên cứu khoa học', 3, 45, 0, 'general', 2, 1, 'Phương pháp luận nghiên cứu khoa học'),
(@cntt_id, 'KTCH002', 'Giáo dục thể chất (Lý thuyết)', 2, 30, 0, 'general', 2, 1, 'Lý thuyết giáo dục thể chất'),
(@cntt_id, 'LING105', 'Kỹ thuật lập trình', 2, 30, 0, 'required', 2, 1, 'Kỹ thuật lập trình nâng cao'),
(@cntt_id, 'LING256', 'Thiết kế Web', 2, 30, 0, 'required', 2, 1, 'HTML, CSS, JavaScript cơ bản'),
(@cntt_id, 'LING283', 'Thực hành Kỹ thuật lập trình', 1, 0, 30, 'required', 2, 1, 'Thực hành kỹ thuật lập trình'),
(@cntt_id, 'LING310', 'Thực hành thiết kế Web', 1, 0, 30, 'required', 2, 1, 'Thực hành thiết kế Web'),
(@cntt_id, 'LING344', 'Toán cao cấp A1', 2, 30, 0, 'required', 2, 1, 'Giải tích, đạo hàm, tích phân');

-- =====================================================
-- HỌC KỲ 3 - Năm học 2022-2023 (16 TC)
-- =====================================================
INSERT INTO subjects (major_id, subject_code, subject_name, credits, theory_periods, practice_periods, subject_type_new, semester_order, is_mandatory, description) VALUES
(@cntt_id, 'KTCH003', 'Giáo dục quốc phòng an ninh', 5, 75, 0, 'general', 3, 1, 'Kiến thức quốc phòng an ninh'),
(@cntt_id, 'KTCH004', 'Thực hành Giáo dục quốc phòng an ninh', 3, 0, 90, 'general', 3, 1, 'Thực hành quốc phòng an ninh'),
(@cntt_id, 'LING020', 'Cơ sở dữ liệu', 2, 30, 0, 'required', 3, 1, 'Mô hình quan hệ, SQL cơ bản'),
(@cntt_id, 'LING265', 'Thực hành Cơ sở dữ liệu', 1, 0, 30, 'required', 3, 1, 'Thực hành SQL và thiết kế CSDL'),
(@cntt_id, 'LING320', 'Thực hành Vật lý đại cương A1', 1, 0, 30, 'required', 3, 1, 'Thực hành vật lý đại cương'),
(@cntt_id, 'LING345', 'Toán cao cấp A2', 2, 30, 0, 'required', 3, 1, 'Đại số tuyến tính, ma trận'),
(@cntt_id, 'LING387', 'Vật lý đại cương A1', 2, 30, 0, 'required', 3, 1, 'Vật lý đại cương cho kỹ thuật');

-- =====================================================
-- HỌC KỲ 1 - Năm học 2023-2024 (21 TC)
-- =====================================================
INSERT INTO subjects (major_id, subject_code, subject_name, credits, theory_periods, practice_periods, subject_type_new, semester_order, is_mandatory, description) VALUES
(@cntt_id, 'KTCH005', 'Tư duy biện luận ứng dụng', 2, 30, 0, 'general', 4, 0, 'Tư duy phản biện và logic'),
(@cntt_id, 'KTCH006', 'Triết học Mác - Lênin', 3, 45, 0, 'general', 4, 1, 'Triết học duy vật biện chứng'),
(@cntt_id, 'KTCH009', 'Những vấn đề kinh tế - xã hội Đông Nam bộ', 2, 30, 0, 'general', 4, 0, 'Kinh tế xã hội vùng Đông Nam bộ'),
(@cntt_id, 'LING010', 'Cấu trúc dữ liệu và giải thuật', 3, 45, 0, 'required', 4, 1, 'Danh sách, cây, đồ thị, sắp xếp tìm kiếm'),
(@cntt_id, 'LING068', 'Hệ Quản trị cơ sở dữ liệu', 2, 30, 0, 'required', 4, 1, 'MySQL, Oracle, SQL Server'),
(@cntt_id, 'LING093', 'Kiến trúc máy tính', 2, 30, 0, 'required', 4, 1, 'Tổ chức và kiến trúc máy tính'),
(@cntt_id, 'LING185', 'Pháp luật', 2, 30, 0, 'general', 4, 1, 'Pháp luật đại cương'),
(@cntt_id, 'LING261', 'Thực hành Cấu trúc dữ liệu và giải thuật', 1, 0, 30, 'required', 4, 1, 'Thực hành CTDL và giải thuật'),
(@cntt_id, 'LING276', 'Thực hành Hệ Quản trị cơ sở dữ liệu', 1, 0, 30, 'required', 4, 1, 'Thực hành HQTCSDL'),
(@cntt_id, 'LING396', 'Xác suất thống kê', 3, 45, 0, 'required', 4, 1, 'Lý thuyết xác suất và thống kê toán');

-- =====================================================
-- HỌC KỲ 2 - Năm học 2023-2024 (26 TC)
-- =====================================================
INSERT INTO subjects (major_id, subject_code, subject_name, credits, theory_periods, practice_periods, subject_type_new, semester_order, is_mandatory, description) VALUES
(@cntt_id, 'KTCH007', 'Giáo dục thể chất (Thực hành)', 3, 0, 45, 'general', 5, 0, 'Thực hành thể dục thể thao'),
(@cntt_id, 'KTCH008', 'Kinh tế chính trị Mác - Lênin', 2, 30, 0, 'general', 5, 1, 'Kinh tế chính trị học'),
(@cntt_id, 'KTCH013', 'Giáo dục thể chất (Thực hành ngoài Trường)', 3, 0, 45, 'general', 5, 0, 'Thể dục ngoài trường'),
(@cntt_id, 'KTPM013', 'Thực hành Phân tích, thiết kế hướng đối tượng', 2, 0, 60, 'required', 5, 1, 'Thực hành UML, design patterns'),
(@cntt_id, 'KTPM031', 'Phân tích, thiết kế hướng đối tượng', 2, 30, 0, 'required', 5, 1, 'OOP, UML, design patterns'),
(@cntt_id, 'LING053', 'Đổi mới, sáng tạo và khởi nghiệp', 3, 45, 0, 'general', 5, 1, 'Tư duy khởi nghiệp, đổi mới sáng tạo'),
(@cntt_id, 'LING110', 'Lập trình trên Windows', 3, 45, 0, 'required', 5, 1, 'Lập trình ứng dụng Windows'),
(@cntt_id, 'LING196', 'Phương pháp lập trình hướng đối tượng', 3, 45, 0, 'required', 5, 1, 'OOP với Java/C#'),
(@cntt_id, 'LING286', 'Thực hành lập trình trên Windows', 1, 0, 30, 'required', 5, 1, 'Thực hành lập trình Windows'),
(@cntt_id, 'LING304', 'Thực hành Phương pháp lập trình hướng đối tượng', 1, 0, 30, 'required', 5, 1, 'Thực hành OOP'),
(@cntt_id, 'LING349', 'Toán rời rạc', 3, 45, 0, 'required', 5, 1, 'Logic, tập hợp, đồ thị, tổ hợp');

-- =====================================================
-- HỌC KỲ 1 - Năm học 2024-2025 (17 TC)
-- =====================================================
INSERT INTO subjects (major_id, subject_code, subject_name, credits, theory_periods, practice_periods, subject_type_new, semester_order, is_mandatory, description) VALUES
(@cntt_id, 'CNTT044', 'Đồ án cơ sở ngành', 2, 0, 60, 'required', 6, 1, 'Đồ án thực hành cơ sở ngành CNTT'),
(@cntt_id, 'KTCH010', 'Chủ nghĩa xã hội khoa học', 2, 30, 0, 'general', 6, 1, 'Lý luận chủ nghĩa xã hội khoa học'),
(@cntt_id, 'LING031', 'Công nghệ phần mềm', 2, 30, 0, 'required', 6, 1, 'Quy trình phát triển phần mềm, Agile, Scrum'),
(@cntt_id, 'LING109', 'Lập trình Web', 2, 30, 0, 'required', 6, 1, 'PHP, NodeJS, REST API'),
(@cntt_id, 'LING135', 'Lý thuyết đồ thị', 2, 30, 0, 'required', 6, 1, 'Lý thuyết đồ thị và ứng dụng'),
(@cntt_id, 'LING137', 'Mạng máy tính', 2, 30, 0, 'required', 6, 1, 'TCP/IP, mô hình OSI, giao thức mạng'),
(@cntt_id, 'LING267', 'Thực hành Công nghệ phần mềm', 1, 0, 30, 'required', 6, 1, 'Thực hành quy trình phát triển PM'),
(@cntt_id, 'LING285', 'Thực hành lập trình Web', 2, 0, 60, 'required', 6, 1, 'Thực hành lập trình Web'),
(@cntt_id, 'LING287', 'Thực hành Lý thuyết đồ thị', 1, 0, 30, 'required', 6, 1, 'Thực hành lý thuyết đồ thị'),
(@cntt_id, 'LING288', 'Thực hành Mạng máy tính', 1, 0, 30, 'required', 6, 1, 'Thực hành mạng máy tính');

-- =====================================================
-- HỌC KỲ 2 - Năm học 2024-2025 (22 TC)
-- =====================================================
INSERT INTO subjects (major_id, subject_code, subject_name, credits, theory_periods, practice_periods, subject_type_new, semester_order, is_mandatory, description) VALUES
(@cntt_id, 'CNTT018', 'Thực hành Kỹ thuật lập trình trong phân tích dữ liệu', 1, 0, 30, 'required', 7, 1, 'Thực hành lập trình phân tích dữ liệu'),
(@cntt_id, 'CNTT024', 'Kỹ thuật lập trình trong phân tích dữ liệu', 2, 30, 0, 'required', 7, 1, 'Python, Pandas, NumPy cho phân tích dữ liệu'),
(@cntt_id, 'CNTT043', 'Hệ điều hành', 2, 0, 60, 'required', 7, 1, 'Linux, Windows Server, quản lý tiến trình'),
(@cntt_id, 'KTCH011', 'Tư tưởng Hồ Chí Minh', 2, 30, 0, 'general', 7, 1, 'Tư tưởng, đạo đức Hồ Chí Minh'),
(@cntt_id, 'KTCH012', 'Lịch sử Đảng Cộng sản Việt Nam', 2, 30, 0, 'general', 7, 1, 'Lịch sử Đảng CSVN'),
(@cntt_id, 'KTPM034', 'Kiến trúc và thiết kế phần mềm', 2, 30, 0, 'required', 7, 1, 'Software architecture, design patterns'),
(@cntt_id, 'KTPM035', 'Thực hành Kiến trúc và thiết kế phần mềm', 2, 0, 60, 'required', 7, 1, 'Thực hành kiến trúc phần mềm'),
(@cntt_id, 'LING005', 'An toàn và bảo mật thông tin', 2, 30, 0, 'required', 7, 1, 'Mã hóa, bảo mật mạng, OWASP'),
(@cntt_id, 'LING210', 'Quản lý dự án công nghệ thông tin', 3, 45, 0, 'required', 7, 1, 'Quản lý dự án CNTT, PMP, Agile'),
(@cntt_id, 'LING260', 'Thực hành An toàn và bảo mật thông tin', 1, 0, 30, 'required', 7, 1, 'Thực hành bảo mật thông tin'),
(@cntt_id, 'LING314', 'Thực hành Trí tuệ nhân tạo', 1, 0, 30, 'required', 7, 1, 'Thực hành AI và machine learning'),
(@cntt_id, 'LING358', 'Trí tuệ nhân tạo', 2, 30, 0, 'required', 7, 1, 'AI, machine learning, deep learning cơ bản');

-- =====================================================
-- HỌC KỲ 1 - Năm học 2025-2026 (25 TC - Tự chọn)
-- =====================================================
INSERT INTO subjects (major_id, subject_code, subject_name, credits, theory_periods, practice_periods, subject_type_new, semester_order, is_mandatory, description) VALUES
(@cntt_id, 'CNTT001', 'An ninh mạng', 2, 30, 0, 'elective', 8, 0, 'Network security, penetration testing'),
(@cntt_id, 'CNTT002', 'An toàn hệ điều hành', 2, 30, 0, 'elective', 8, 0, 'OS security, hardening'),
(@cntt_id, 'CNTT008', 'Thực hành Phát triển hệ thống thông tin nhân sự và tiền lương', 1, 0, 30, 'elective', 8, 0, 'Thực hành phát triển HTTT nhân sự'),
(@cntt_id, 'CNTT027', 'Thực hành An toàn hệ điều hành', 1, 0, 30, 'elective', 8, 0, 'Thực hành an toàn HĐH'),
(@cntt_id, 'CNTT028', 'Thực hành An ninh mạng', 1, 0, 30, 'elective', 8, 0, 'Thực hành an ninh mạng'),
(@cntt_id, 'CNTT029', 'Phát triển hệ thống thông tin nhân sự và tiền lương', 2, 30, 0, 'elective', 8, 0, 'Phát triển HTTT nhân sự'),
(@cntt_id, 'KTPM008', 'Chuyên đề xử lý dữ liệu lớn', 2, 30, 0, 'elective', 8, 0, 'Big data, Hadoop, Spark'),
(@cntt_id, 'KTPM026', 'Thực hành Chuyên đề xử lý dữ liệu lớn', 1, 0, 30, 'elective', 8, 0, 'Thực hành xử lý dữ liệu lớn'),
(@cntt_id, 'LING014', 'Chuyên đề Internet of Things', 2, 30, 0, 'elective', 8, 0, 'IoT, Arduino, Raspberry Pi'),
(@cntt_id, 'LING042', 'Điện toán đám mây', 2, 30, 0, 'required', 8, 1, 'Cloud computing, AWS, Azure, GCP'),
(@cntt_id, 'LING100', 'Quản trị hệ thống', 2, 30, 0, 'required', 8, 1, 'System administration, DevOps'),
(@cntt_id, 'LING189', 'Phát triển ứng dụng di động', 2, 30, 0, 'required', 8, 1, 'Android, iOS, React Native'),
(@cntt_id, 'LING263', 'Thực hành Chuyên đề Internet of Things', 1, 0, 30, 'elective', 8, 0, 'Thực hành IoT'),
(@cntt_id, 'LING270', 'Thực hành Điện toán đám mây', 1, 0, 30, 'required', 8, 1, 'Thực hành cloud computing'),
(@cntt_id, 'LING301', 'Thực hành Phát triển ứng dụng di động', 2, 0, 60, 'elective', 8, 0, 'Thực hành lập trình di động'),
(@cntt_id, 'LING307', 'Thực hành Quản trị hệ thống', 1, 0, 30, 'required', 8, 1, 'Thực hành quản trị hệ thống');

-- =====================================================
-- HỌC KỲ 2 - Năm học 2025-2026 (23 TC - Tự chọn)
-- =====================================================
INSERT INTO subjects (major_id, subject_code, subject_name, credits, theory_periods, practice_periods, subject_type_new, semester_order, is_mandatory, description) VALUES
(@cntt_id, 'CNTT005', 'Các Kỹ thuật giấu tin', 2, 30, 0, 'elective', 9, 0, 'Steganography, watermarking'),
(@cntt_id, 'CNTT007', 'Thực hành Phát triển phần mềm mã nguồn mở', 1, 0, 30, 'elective', 9, 0, 'Thực hành open source development'),
(@cntt_id, 'CNTT012', 'Thực hành Mật mã học cơ sở', 1, 0, 30, 'elective', 9, 0, 'Thực hành mật mã học'),
(@cntt_id, 'CNTT014', 'Đồ án chuyên ngành', 2, 0, 60, 'required', 9, 1, 'Đồ án chuyên ngành CNTT'),
(@cntt_id, 'CNTT026', 'Thực hành Các Kỹ thuật giấu tin', 1, 0, 30, 'elective', 9, 0, 'Thực hành kỹ thuật giấu tin'),
(@cntt_id, 'CNTT031', 'Mật mã học cơ sở', 2, 30, 0, 'elective', 9, 0, 'Cryptography, mã hóa đối xứng và bất đối xứng'),
(@cntt_id, 'LING081', 'Học máy', 2, 30, 0, 'required', 9, 1, 'Machine learning algorithms, scikit-learn'),
(@cntt_id, 'LING188', 'Phát triển phần mềm mã nguồn mở', 2, 30, 0, 'elective', 9, 0, 'Open source development, Git, GitHub'),
(@cntt_id, 'LING190', 'Phát triển ứng dụng di động đa nền tảng', 2, 30, 0, 'required', 9, 1, 'Flutter, React Native'),
(@cntt_id, 'LING191', 'Phát triển ứng dụng trên điện toán đám mây', 2, 30, 0, 'elective', 9, 0, 'Cloud-native development'),
(@cntt_id, 'LING280', 'Thực hành học máy', 1, 0, 30, 'required', 9, 1, 'Thực hành machine learning'),
(@cntt_id, 'LING302', 'Thực hành Phát triển ứng dụng di động đa nền tảng', 1, 0, 30, 'required', 9, 1, 'Thực hành Flutter/React Native'),
(@cntt_id, 'LING303', 'Thực hành Phát triển ứng dụng trên điện toán đám mây', 1, 0, 30, 'elective', 9, 0, 'Thực hành cloud-native'),
(@cntt_id, 'LING315', 'Thực hành Trực quan hóa dữ liệu', 1, 0, 30, 'elective', 9, 0, 'Thực hành data visualization'),
(@cntt_id, 'LING403', 'Trực quan hóa dữ liệu', 2, 30, 0, 'elective', 9, 0, 'Data visualization, Tableau, Power BI');

-- =====================================================
-- HỌC KỲ 3 - Năm học 2025-2026 (5 TC - Thực tập)
-- =====================================================
INSERT INTO subjects (major_id, subject_code, subject_name, credits, theory_periods, practice_periods, subject_type_new, semester_order, is_mandatory, description) VALUES
(@cntt_id, 'CNTT006', 'Thực tập doanh nghiệp', 5, 0, 150, 'required', 10, 1, 'Thực tập tại doanh nghiệp CNTT');

-- =====================================================
-- HỌC KỲ 1 - Năm học 2026-2027 (15 TC - Tốt nghiệp)
-- =====================================================
INSERT INTO subjects (major_id, subject_code, subject_name, credits, theory_periods, practice_periods, subject_type_new, semester_order, is_mandatory, description) VALUES
(@cntt_id, 'CNTT003', 'Thực tập tốt nghiệp', 5, 0, 150, 'required', 11, 1, 'Thực tập tốt nghiệp tại doanh nghiệp'),
(@cntt_id, 'CNTT004', 'Báo cáo/Đồ án tốt nghiệp', 10, 0, 300, 'required', 11, 1, 'Nghiên cứu và bảo vệ đồ án tốt nghiệp');

-- Cập nhật cột subject_type cũ từ cột mới (nếu cần)
UPDATE subjects SET subject_type = CASE subject_type_new
    WHEN 'required' THEN 'Bắt buộc'
    WHEN 'elective' THEN 'Tự chọn'
    WHEN 'general' THEN 'Bắt buộc'
    ELSE 'Bắt buộc'
END WHERE major_id = @cntt_id;

SELECT CONCAT('Đã insert ', COUNT(*), ' môn học cho ngành CNTT (major_id=', @cntt_id, ')') as result
FROM subjects WHERE major_id = @cntt_id;
