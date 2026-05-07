-- =====================================================
-- CHƯƠNG TRÌNH ĐÀO TẠO - Trường ĐH Thủ Dầu Một
-- 120 tín chỉ / ngành | 8 học kỳ
-- Chạy file này sau khi đã có dữ liệu bảng majors
-- =====================================================

-- Thêm cột nếu chưa có
ALTER TABLE subjects ADD COLUMN IF NOT EXISTS subject_type ENUM('required','elective','general') NOT NULL DEFAULT 'required';
ALTER TABLE subjects ADD COLUMN IF NOT EXISTS semester_order TINYINT NOT NULL DEFAULT 1;

-- Xóa dữ liệu cũ để seed lại (bỏ comment nếu muốn reset)
-- DELETE FROM subjects;

-- =====================================================
-- NGÀNH: CÔNG NGHỆ THÔNG TIN (7480201) - 120 TC
-- =====================================================
SET @cntt = (SELECT id FROM majors WHERE major_code = '7480201' LIMIT 1);

INSERT INTO subjects (major_id, subject_code, subject_name, credits, subject_type, semester_order, description) VALUES
-- HK1 (18 TC)
(@cntt,'GDTC1','Giáo dục thể chất 1',1,'general',1,'Thể dục thể thao cơ bản'),
(@cntt,'GDQP1','Giáo dục quốc phòng 1',3,'general',1,'Kiến thức quốc phòng an ninh'),
(@cntt,'TOAN1','Toán cao cấp 1',3,'required',1,'Giải tích, đạo hàm, tích phân'),
(@cntt,'XSTK','Xác suất thống kê',3,'required',1,'Lý thuyết xác suất và thống kê toán'),
(@cntt,'LTLT','Lập trình căn bản',3,'required',1,'Ngôn ngữ C/C++, thuật toán cơ bản'),
(@cntt,'TTHCM','Tư tưởng Hồ Chí Minh',2,'general',1,'Tư tưởng, đạo đức Hồ Chí Minh'),
(@cntt,'TOAN2','Toán cao cấp 2',3,'required',1,'Đại số tuyến tính, ma trận'),
-- HK2 (18 TC)
(@cntt,'CTDL','Cấu trúc dữ liệu và giải thuật',3,'required',2,'Danh sách, cây, đồ thị, sắp xếp tìm kiếm'),
(@cntt,'LTHDT','Lập trình hướng đối tượng',3,'required',2,'OOP với Java/C++'),
(@cntt,'KTMT','Kiến trúc máy tính',3,'required',2,'Tổ chức và kiến trúc máy tính'),
(@cntt,'MLNL','Mạng máy tính',3,'required',2,'Giao thức TCP/IP, mô hình OSI'),
(@cntt,'TOAN3','Toán rời rạc',3,'required',2,'Logic, tập hợp, đồ thị, tổ hợp'),
(@cntt,'GDTC2','Giáo dục thể chất 2',1,'general',2,'Thể dục thể thao nâng cao'),
(@cntt,'LLCT1','Triết học Mác-Lênin',2,'general',2,'Triết học duy vật biện chứng'),
-- HK3 (18 TC)
(@cntt,'CSDL','Cơ sở dữ liệu',3,'required',3,'Mô hình quan hệ, SQL, thiết kế CSDL'),
(@cntt,'HTTT','Hệ thống thông tin',3,'required',3,'Phân tích và thiết kế hệ thống'),
(@cntt,'LTMANG','Lập trình mạng',3,'required',3,'Socket, giao thức ứng dụng'),
(@cntt,'HHDH','Hệ điều hành',3,'required',3,'Quản lý tiến trình, bộ nhớ, file'),
(@cntt,'LLCT2','Kinh tế chính trị Mác-Lênin',2,'general',3,'Kinh tế chính trị học'),
(@cntt,'GDQP2','Giáo dục quốc phòng 2',2,'general',3,'Kỹ năng quân sự'),
(@cntt,'TIENGANH1','Tiếng Anh 1',2,'general',3,'Tiếng Anh cơ bản A1-A2'),
-- HK4 (18 TC)
(@cntt,'PTTKHT','Phân tích thiết kế hệ thống',3,'required',4,'UML, use case, class diagram'),
(@cntt,'LTJAVA','Lập trình Java',3,'required',4,'Java SE, collections, I/O'),
(@cntt,'ANTT','An toàn thông tin',3,'required',4,'Mã hóa, bảo mật mạng'),
(@cntt,'TIENGANH2','Tiếng Anh 2',2,'general',4,'Tiếng Anh B1'),
(@cntt,'LLCT3','Chủ nghĩa xã hội khoa học',2,'general',4,'Lý luận CNXH'),
(@cntt,'LTMOBILE','Lập trình di động',3,'required',4,'Android/iOS cơ bản'),
(@cntt,'TIENGANH3','Tiếng Anh chuyên ngành CNTT',2,'required',4,'Đọc hiểu tài liệu kỹ thuật'),
-- HK5 (18 TC)
(@cntt,'LTWEBFE','Lập trình Web Frontend',3,'required',5,'HTML, CSS, JavaScript, ReactJS'),
(@cntt,'LTWEBBE','Lập trình Web Backend',3,'required',5,'PHP/NodeJS, REST API'),
(@cntt,'KPDL','Khai phá dữ liệu',3,'required',5,'Data mining, machine learning cơ bản'),
(@cntt,'TTNT','Trí tuệ nhân tạo',3,'required',5,'AI, tìm kiếm, học máy'),
(@cntt,'LLCT4','Lịch sử Đảng Cộng sản VN',2,'general',5,'Lịch sử Đảng'),
(@cntt,'QLDA','Quản lý dự án phần mềm',2,'required',5,'Scrum, Agile, quản lý dự án'),
(@cntt,'GDTC3','Giáo dục thể chất 3',2,'general',5,'Thể thao tự chọn'),
-- HK6 (15 TC)
(@cntt,'DTVT','Điện toán đám mây',3,'required',6,'Cloud computing, AWS/Azure cơ bản'),
(@cntt,'BIGDATA','Dữ liệu lớn',3,'required',6,'Hadoop, Spark, xử lý dữ liệu lớn'),
(@cntt,'TTCS1','Chuyên đề CNTT 1',3,'elective',6,'Chủ đề chuyên sâu tự chọn'),
(@cntt,'TTCS2','Chuyên đề CNTT 2',3,'elective',6,'Chủ đề chuyên sâu tự chọn'),
(@cntt,'THUCHANH','Thực hành nghề nghiệp',3,'required',6,'Thực tập tại doanh nghiệp'),
-- HK7 (9 TC)
(@cntt,'TTDN','Thực tập doanh nghiệp',6,'required',7,'Thực tập tốt nghiệp tại công ty'),
(@cntt,'TTCS3','Chuyên đề tốt nghiệp',3,'elective',7,'Nghiên cứu chuyên sâu'),
-- HK8 (6 TC)
(@cntt,'KLTN','Khóa luận tốt nghiệp',6,'required',8,'Nghiên cứu và bảo vệ khóa luận');

-- =====================================================
-- NGÀNH: KỸ THUẬT PHẦN MỀM (7480103) - 120 TC
-- =====================================================
SET @ktpm = (SELECT id FROM majors WHERE major_code = '7480103' LIMIT 1);

INSERT INTO subjects (major_id, subject_code, subject_name, credits, subject_type, semester_order, description) VALUES
-- HK1 (18 TC)
(@ktpm,'KTPM_TOAN1','Toán cao cấp 1',3,'required',1,'Giải tích, đạo hàm, tích phân'),
(@ktpm,'KTPM_XSTK','Xác suất thống kê',3,'required',1,'Lý thuyết xác suất và thống kê'),
(@ktpm,'KTPM_LTC','Lập trình C',3,'required',1,'Ngôn ngữ C, con trỏ, cấu trúc'),
(@ktpm,'KTPM_TTHCM','Tư tưởng Hồ Chí Minh',2,'general',1,'Tư tưởng đạo đức HCM'),
(@ktpm,'KTPM_GDQP1','Giáo dục quốc phòng 1',3,'general',1,'Kiến thức quốc phòng'),
(@ktpm,'KTPM_GDTC1','Giáo dục thể chất 1',1,'general',1,'Thể dục cơ bản'),
(@ktpm,'KTPM_TOAN2','Toán rời rạc',3,'required',1,'Logic, tập hợp, đồ thị'),
-- HK2 (18 TC)
(@ktpm,'KTPM_CTDL','Cấu trúc dữ liệu và giải thuật',3,'required',2,'Danh sách, cây, đồ thị'),
(@ktpm,'KTPM_OOP','Lập trình hướng đối tượng',3,'required',2,'OOP với Java'),
(@ktpm,'KTPM_KTMT','Kiến trúc máy tính',3,'required',2,'Tổ chức máy tính'),
(@ktpm,'KTPM_LLCT1','Triết học Mác-Lênin',2,'general',2,'Triết học duy vật'),
(@ktpm,'KTPM_GDTC2','Giáo dục thể chất 2',1,'general',2,'Thể dục nâng cao'),
(@ktpm,'KTPM_MANG','Mạng máy tính',3,'required',2,'TCP/IP, mô hình OSI'),
(@ktpm,'KTPM_TIENGANH1','Tiếng Anh 1',3,'general',2,'Tiếng Anh A1-A2'),
-- HK3 (18 TC)
(@ktpm,'KTPM_CSDL','Cơ sở dữ liệu',3,'required',3,'SQL, thiết kế CSDL'),
(@ktpm,'KTPM_PTTKHT','Phân tích thiết kế hệ thống',3,'required',3,'UML, use case'),
(@ktpm,'KTPM_HDD','Hệ điều hành',3,'required',3,'Quản lý tiến trình, bộ nhớ'),
(@ktpm,'KTPM_LLCT2','Kinh tế chính trị',2,'general',3,'Kinh tế chính trị học'),
(@ktpm,'KTPM_TIENGANH2','Tiếng Anh 2',3,'general',3,'Tiếng Anh B1'),
(@ktpm,'KTPM_GDQP2','Giáo dục quốc phòng 2',2,'general',3,'Kỹ năng quân sự'),
(@ktpm,'KTPM_KYTHUAT','Kỹ thuật lập trình',2,'required',3,'Clean code, design patterns'),
-- HK4 (18 TC)
(@ktpm,'KTPM_CNPM','Công nghệ phần mềm',3,'required',4,'Quy trình phát triển phần mềm'),
(@ktpm,'KTPM_KIEMTHU','Kiểm thử phần mềm',3,'required',4,'Unit test, integration test, automation'),
(@ktpm,'KTPM_TIENGANH3','Tiếng Anh chuyên ngành',2,'required',4,'Đọc tài liệu kỹ thuật'),
(@ktpm,'KTPM_LLCT3','Chủ nghĩa xã hội khoa học',2,'general',4,'Lý luận CNXH'),
(@ktpm,'KTPM_WEBFE','Lập trình Web Frontend',3,'required',4,'HTML, CSS, JavaScript'),
(@ktpm,'KTPM_WEBBE','Lập trình Web Backend',3,'required',4,'NodeJS/PHP, REST API'),
(@ktpm,'KTPM_MOBILE','Lập trình di động',2,'required',4,'Android cơ bản'),
-- HK5 (18 TC)
(@ktpm,'KTPM_DEVOPS','DevOps và CI/CD',3,'required',5,'Git, Docker, Jenkins, CI/CD pipeline'),
(@ktpm,'KTPM_CLOUD','Điện toán đám mây',3,'required',5,'AWS/Azure, microservices'),
(@ktpm,'KTPM_ANTT','An toàn phần mềm',3,'required',5,'Bảo mật ứng dụng, OWASP'),
(@ktpm,'KTPM_QLDA','Quản lý dự án phần mềm',3,'required',5,'Scrum, Agile, Kanban'),
(@ktpm,'KTPM_LLCT4','Lịch sử Đảng',2,'general',5,'Lịch sử Đảng CSVN'),
(@ktpm,'KTPM_GDTC3','Giáo dục thể chất 3',2,'general',5,'Thể thao tự chọn'),
(@ktpm,'KTPM_CHUYENDE1','Chuyên đề KTPM 1',2,'elective',5,'Chủ đề chuyên sâu'),
-- HK6 (15 TC)
(@ktpm,'KTPM_MICROSERVICE','Kiến trúc Microservices',3,'required',6,'Thiết kế hệ thống phân tán'),
(@ktpm,'KTPM_AI','Ứng dụng AI trong phần mềm',3,'required',6,'ML, NLP ứng dụng'),
(@ktpm,'KTPM_CHUYENDE2','Chuyên đề KTPM 2',3,'elective',6,'Chủ đề chuyên sâu'),
(@ktpm,'KTPM_CHUYENDE3','Chuyên đề KTPM 3',3,'elective',6,'Chủ đề chuyên sâu'),
(@ktpm,'KTPM_THUCHANH','Thực hành nghề nghiệp',3,'required',6,'Thực tập tại doanh nghiệp'),
-- HK7 (9 TC)
(@ktpm,'KTPM_TTDN','Thực tập doanh nghiệp',6,'required',7,'Thực tập tốt nghiệp'),
(@ktpm,'KTPM_CHUYENDE4','Chuyên đề tốt nghiệp',3,'elective',7,'Nghiên cứu chuyên sâu'),
-- HK8 (6 TC)
(@ktpm,'KTPM_KLTN','Khóa luận tốt nghiệp',6,'required',8,'Nghiên cứu và bảo vệ khóa luận');

-- =====================================================
-- NGÀNH: KẾ TOÁN (7340301) - 120 TC
-- =====================================================
SET @kt = (SELECT id FROM majors WHERE major_code = '7340301' LIMIT 1);

INSERT INTO subjects (major_id, subject_code, subject_name, credits, subject_type, semester_order, description) VALUES
-- HK1 (18 TC)
(@kt,'KT_TOAN1','Toán cao cấp',3,'required',1,'Giải tích, đại số tuyến tính'),
(@kt,'KT_TTHCM','Tư tưởng Hồ Chí Minh',2,'general',1,'Tư tưởng đạo đức HCM'),
(@kt,'KT_GDQP1','Giáo dục quốc phòng 1',3,'general',1,'Kiến thức quốc phòng'),
(@kt,'KT_GDTC1','Giáo dục thể chất 1',1,'general',1,'Thể dục cơ bản'),
(@kt,'KT_KTCT','Kinh tế chính trị',3,'general',1,'Kinh tế chính trị Mác-Lênin'),
(@kt,'KT_PHAP','Pháp luật đại cương',3,'required',1,'Kiến thức pháp luật cơ bản'),
(@kt,'KT_TIENGANH1','Tiếng Anh 1',3,'general',1,'Tiếng Anh A1-A2'),
-- HK2 (18 TC)
(@kt,'KT_KTVM','Kinh tế vi mô',3,'required',2,'Cung cầu, thị trường, doanh nghiệp'),
(@kt,'KT_KTVMO','Kinh tế vĩ mô',3,'required',2,'GDP, lạm phát, chính sách kinh tế'),
(@kt,'KT_TAICHINH','Tài chính tiền tệ',3,'required',2,'Hệ thống tài chính, ngân hàng'),
(@kt,'KT_LLCT1','Triết học Mác-Lênin',2,'general',2,'Triết học duy vật'),
(@kt,'KT_GDTC2','Giáo dục thể chất 2',1,'general',2,'Thể dục nâng cao'),
(@kt,'KT_TIENGANH2','Tiếng Anh 2',3,'general',2,'Tiếng Anh B1'),
(@kt,'KT_THONGKE','Thống kê kinh tế',3,'required',2,'Thống kê mô tả, suy diễn'),
-- HK3 (18 TC)
(@kt,'KT_KTDC','Kế toán đại cương',3,'required',3,'Nguyên lý kế toán, tài khoản'),
(@kt,'KT_TCKT','Tài chính kế toán',3,'required',3,'Báo cáo tài chính, phân tích'),
(@kt,'KT_LLCT2','Chủ nghĩa xã hội khoa học',2,'general',3,'Lý luận CNXH'),
(@kt,'KT_GDQP2','Giáo dục quốc phòng 2',2,'general',3,'Kỹ năng quân sự'),
(@kt,'KT_TIENGANH3','Tiếng Anh chuyên ngành',3,'required',3,'Tiếng Anh kế toán tài chính'),
(@kt,'KT_PHAPKT','Pháp luật kế toán',3,'required',3,'Luật kế toán, chuẩn mực kế toán'),
(@kt,'KT_GDTC3','Giáo dục thể chất 3',2,'general',3,'Thể thao tự chọn'),
-- HK4 (18 TC)
(@kt,'KT_KTDN','Kế toán doanh nghiệp',3,'required',4,'Kế toán tài sản, nguồn vốn'),
(@kt,'KT_KTTC','Kế toán tài chính',3,'required',4,'Lập báo cáo tài chính'),
(@kt,'KT_KTQT','Kế toán quản trị',3,'required',4,'Chi phí, giá thành, ngân sách'),
(@kt,'KT_LLCT3','Lịch sử Đảng',2,'general',4,'Lịch sử Đảng CSVN'),
(@kt,'KT_THUE','Thuế',3,'required',4,'Luật thuế, kê khai nộp thuế'),
(@kt,'KT_EXCEL','Tin học kế toán',2,'required',4,'Excel, phần mềm kế toán'),
(@kt,'KT_TAICHINH2','Phân tích tài chính',2,'required',4,'Phân tích báo cáo tài chính'),
-- HK5 (18 TC)
(@kt,'KT_KTNH','Kế toán ngân hàng',3,'required',5,'Nghiệp vụ kế toán ngân hàng'),
(@kt,'KT_KTNN','Kế toán nhà nước',3,'required',5,'Kế toán đơn vị hành chính sự nghiệp'),
(@kt,'KT_KTQT2','Kế toán quản trị nâng cao',3,'required',5,'Quyết định kinh doanh, BSC'),
(@kt,'KT_KIEMTOAN','Kiểm toán',3,'required',5,'Kiểm toán độc lập, nội bộ'),
(@kt,'KT_CHUYENDE1','Chuyên đề kế toán 1',3,'elective',5,'Chủ đề chuyên sâu'),
(@kt,'KT_PHANMEM','Phần mềm kế toán',3,'required',5,'MISA, Fast Accounting'),
-- HK6 (15 TC)
(@kt,'KT_KIEMTOAN2','Kiểm toán nâng cao',3,'required',6,'Kiểm toán báo cáo tài chính'),
(@kt,'KT_CHUYENDE2','Chuyên đề kế toán 2',3,'elective',6,'Chủ đề chuyên sâu'),
(@kt,'KT_CHUYENDE3','Chuyên đề kế toán 3',3,'elective',6,'Chủ đề chuyên sâu'),
(@kt,'KT_THUCHANH','Thực hành nghề nghiệp',3,'required',6,'Thực tập tại doanh nghiệp'),
(@kt,'KT_NGHIEPVU','Nghiệp vụ kế toán tổng hợp',3,'required',6,'Thực hành kế toán tổng hợp'),
-- HK7 (9 TC)
(@kt,'KT_TTDN','Thực tập doanh nghiệp',6,'required',7,'Thực tập tốt nghiệp'),
(@kt,'KT_CHUYENDE4','Chuyên đề tốt nghiệp',3,'elective',7,'Nghiên cứu chuyên sâu'),
-- HK8 (6 TC)
(@kt,'KT_KLTN','Khóa luận tốt nghiệp',6,'required',8,'Nghiên cứu và bảo vệ khóa luận');

-- =====================================================
-- NGÀNH: QUẢN TRỊ KINH DOANH (7340101) - 120 TC
-- =====================================================
SET @qtkd = (SELECT id FROM majors WHERE major_code = '7340101' LIMIT 1);

INSERT INTO subjects (major_id, subject_code, subject_name, credits, subject_type, semester_order, description) VALUES
(@qtkd,'QTKD_TOAN','Toán cao cấp',3,'required',1,'Giải tích, đại số'),
(@qtkd,'QTKD_TTHCM','Tư tưởng Hồ Chí Minh',2,'general',1,'Tư tưởng đạo đức HCM'),
(@qtkd,'QTKD_GDQP1','Giáo dục quốc phòng 1',3,'general',1,'Kiến thức quốc phòng'),
(@qtkd,'QTKD_GDTC1','Giáo dục thể chất 1',1,'general',1,'Thể dục cơ bản'),
(@qtkd,'QTKD_PHAP','Pháp luật đại cương',3,'required',1,'Kiến thức pháp luật'),
(@qtkd,'QTKD_TIENGANH1','Tiếng Anh 1',3,'general',1,'Tiếng Anh A1-A2'),
(@qtkd,'QTKD_KTCT','Kinh tế chính trị',3,'general',1,'Kinh tế chính trị Mác-Lênin'),
(@qtkd,'QTKD_KTVM','Kinh tế vi mô',3,'required',2,'Cung cầu, thị trường'),
(@qtkd,'QTKD_KTVMO','Kinh tế vĩ mô',3,'required',2,'GDP, lạm phát, chính sách'),
(@qtkd,'QTKD_THONGKE','Thống kê kinh doanh',3,'required',2,'Thống kê mô tả, phân tích'),
(@qtkd,'QTKD_LLCT1','Triết học Mác-Lênin',2,'general',2,'Triết học duy vật'),
(@qtkd,'QTKD_GDTC2','Giáo dục thể chất 2',1,'general',2,'Thể dục nâng cao'),
(@qtkd,'QTKD_TIENGANH2','Tiếng Anh 2',3,'general',2,'Tiếng Anh B1'),
(@qtkd,'QTKD_KTDC','Kế toán đại cương',3,'required',2,'Nguyên lý kế toán'),
(@qtkd,'QTKD_QTDN','Quản trị doanh nghiệp',3,'required',3,'Tổ chức, quản lý doanh nghiệp'),
(@qtkd,'QTKD_MARKETING','Marketing căn bản',3,'required',3,'4P, phân tích thị trường'),
(@qtkd,'QTKD_TAICHINH','Tài chính doanh nghiệp',3,'required',3,'Quản lý tài chính, đầu tư'),
(@qtkd,'QTKD_LLCT2','Chủ nghĩa xã hội khoa học',2,'general',3,'Lý luận CNXH'),
(@qtkd,'QTKD_GDQP2','Giáo dục quốc phòng 2',2,'general',3,'Kỹ năng quân sự'),
(@qtkd,'QTKD_TIENGANH3','Tiếng Anh chuyên ngành',3,'required',3,'Tiếng Anh kinh doanh'),
(@qtkd,'QTKD_GDTC3','Giáo dục thể chất 3',2,'general',3,'Thể thao tự chọn'),
(@qtkd,'QTKD_QTNS','Quản trị nhân sự',3,'required',4,'Tuyển dụng, đào tạo, đánh giá'),
(@qtkd,'QTKD_QTCL','Quản trị chiến lược',3,'required',4,'Phân tích SWOT, chiến lược cạnh tranh'),
(@qtkd,'QTKD_QTSX','Quản trị sản xuất',3,'required',4,'Hoạch định sản xuất, kiểm soát chất lượng'),
(@qtkd,'QTKD_LLCT3','Lịch sử Đảng',2,'general',4,'Lịch sử Đảng CSVN'),
(@qtkd,'QTKD_THUONG','Luật thương mại',3,'required',4,'Hợp đồng, doanh nghiệp, thương mại'),
(@qtkd,'QTKD_ECOM','Thương mại điện tử',2,'required',4,'E-commerce, digital marketing'),
(@qtkd,'QTKD_TINHOC','Tin học ứng dụng',2,'required',4,'Excel, phần mềm quản lý'),
(@qtkd,'QTKD_QTDA','Quản trị dự án',3,'required',5,'Lập kế hoạch, quản lý dự án'),
(@qtkd,'QTKD_KHỞI','Khởi nghiệp kinh doanh',3,'required',5,'Business plan, startup'),
(@qtkd,'QTKD_QTQT','Quản trị quốc tế',3,'required',5,'Kinh doanh quốc tế, FDI'),
(@qtkd,'QTKD_CHUYENDE1','Chuyên đề QTKD 1',3,'elective',5,'Chủ đề chuyên sâu'),
(@qtkd,'QTKD_CHUYENDE2','Chuyên đề QTKD 2',3,'elective',5,'Chủ đề chuyên sâu'),
(@qtkd,'QTKD_NGHIEPVU','Nghiệp vụ kinh doanh',3,'required',5,'Thực hành kỹ năng kinh doanh'),
(@qtkd,'QTKD_CHUYENDE3','Chuyên đề QTKD 3',3,'elective',6,'Chủ đề chuyên sâu'),
(@qtkd,'QTKD_CHUYENDE4','Chuyên đề QTKD 4',3,'elective',6,'Chủ đề chuyên sâu'),
(@qtkd,'QTKD_THUCHANH','Thực hành nghề nghiệp',3,'required',6,'Thực tập tại doanh nghiệp'),
(@qtkd,'QTKD_PHANTICH','Phân tích kinh doanh',3,'required',6,'Business analytics, BI'),
(@qtkd,'QTKD_QTCL2','Quản trị chiến lược nâng cao',3,'required',6,'Case study, mô phỏng kinh doanh'),
(@qtkd,'QTKD_TTDN','Thực tập doanh nghiệp',6,'required',7,'Thực tập tốt nghiệp'),
(@qtkd,'QTKD_CHUYENDE5','Chuyên đề tốt nghiệp',3,'elective',7,'Nghiên cứu chuyên sâu'),
(@qtkd,'QTKD_KLTN','Khóa luận tốt nghiệp',6,'required',8,'Nghiên cứu và bảo vệ khóa luận');

-- =====================================================
-- NGÀNH: LUẬT (7380101) - 120 TC
-- =====================================================
SET @luat = (SELECT id FROM majors WHERE major_code = '7380101' LIMIT 1);

INSERT INTO subjects (major_id, subject_code, subject_name, credits, subject_type, semester_order, description) VALUES
(@luat,'LUAT_TTHCM','Tư tưởng Hồ Chí Minh',2,'general',1,'Tư tưởng đạo đức HCM'),
(@luat,'LUAT_GDQP1','Giáo dục quốc phòng 1',3,'general',1,'Kiến thức quốc phòng'),
(@luat,'LUAT_GDTC1','Giáo dục thể chất 1',1,'general',1,'Thể dục cơ bản'),
(@luat,'LUAT_NHANUOC','Nhà nước và pháp luật đại cương',3,'required',1,'Lý luận nhà nước và pháp luật'),
(@luat,'LUAT_HIENPHAP','Luật Hiến pháp',3,'required',1,'Hiến pháp, tổ chức nhà nước'),
(@luat,'LUAT_TIENGANH1','Tiếng Anh 1',3,'general',1,'Tiếng Anh A1-A2'),
(@luat,'LUAT_LLCT1','Triết học Mác-Lênin',3,'general',1,'Triết học duy vật'),
(@luat,'LUAT_HANH','Luật Hành chính',3,'required',2,'Quản lý nhà nước, xử phạt hành chính'),
(@luat,'LUAT_HINH','Luật Hình sự',3,'required',2,'Tội phạm, hình phạt'),
(@luat,'LUAT_DAN','Luật Dân sự',3,'required',2,'Tài sản, hợp đồng, thừa kế'),
(@luat,'LUAT_LLCT2','Kinh tế chính trị',2,'general',2,'Kinh tế chính trị Mác-Lênin'),
(@luat,'LUAT_GDTC2','Giáo dục thể chất 2',1,'general',2,'Thể dục nâng cao'),
(@luat,'LUAT_TIENGANH2','Tiếng Anh 2',3,'general',2,'Tiếng Anh B1'),
(@luat,'LUAT_GDQP2','Giáo dục quốc phòng 2',3,'general',2,'Kỹ năng quân sự'),
(@luat,'LUAT_TOTHUNG','Luật Tố tụng hình sự',3,'required',3,'Điều tra, truy tố, xét xử hình sự'),
(@luat,'LUAT_TOTUNGDS','Luật Tố tụng dân sự',3,'required',3,'Khởi kiện, xét xử dân sự'),
(@luat,'LUAT_THUONG','Luật Thương mại',3,'required',3,'Doanh nghiệp, hợp đồng thương mại'),
(@luat,'LUAT_LLCT3','Chủ nghĩa xã hội khoa học',2,'general',3,'Lý luận CNXH'),
(@luat,'LUAT_TIENGANH3','Tiếng Anh pháp lý',3,'required',3,'Tiếng Anh chuyên ngành luật'),
(@luat,'LUAT_GDTC3','Giáo dục thể chất 3',2,'general',3,'Thể thao tự chọn'),
(@luat,'LUAT_LAODONG','Luật Lao động',2,'required',3,'Hợp đồng lao động, tranh chấp'),
(@luat,'LUAT_HONNHAN','Luật Hôn nhân gia đình',3,'required',4,'Kết hôn, ly hôn, nuôi con'),
(@luat,'LUAT_DATDAI','Luật Đất đai',3,'required',4,'Quyền sử dụng đất, tranh chấp đất'),
(@luat,'LUAT_DOANHNGHIEP','Luật Doanh nghiệp',3,'required',4,'Thành lập, quản lý, giải thể DN'),
(@luat,'LUAT_LLCT4','Lịch sử Đảng',2,'general',4,'Lịch sử Đảng CSVN'),
(@luat,'LUAT_QUOCTE','Công pháp quốc tế',3,'required',4,'Luật quốc tế, điều ước'),
(@luat,'LUAT_TPQUOCTE','Tư pháp quốc tế',3,'required',4,'Xung đột pháp luật, tố tụng quốc tế'),
(@luat,'LUAT_THUE','Luật Thuế',2,'required',4,'Hệ thống thuế Việt Nam'),
(@luat,'LUAT_MOIDAU','Luật Đầu tư',3,'required',5,'Đầu tư trong nước và nước ngoài'),
(@luat,'LUAT_SOHUU','Luật Sở hữu trí tuệ',3,'required',5,'Bản quyền, nhãn hiệu, sáng chế'),
(@luat,'LUAT_MTRG','Luật Môi trường',3,'required',5,'Bảo vệ môi trường, xử lý vi phạm'),
(@luat,'LUAT_CHUYENDE1','Chuyên đề Luật 1',3,'elective',5,'Chủ đề chuyên sâu'),
(@luat,'LUAT_CHUYENDE2','Chuyên đề Luật 2',3,'elective',5,'Chủ đề chuyên sâu'),
(@luat,'LUAT_KYNANGLUAT','Kỹ năng hành nghề luật',3,'required',5,'Tư vấn pháp lý, soạn thảo văn bản'),
(@luat,'LUAT_CHUYENDE3','Chuyên đề Luật 3',3,'elective',6,'Chủ đề chuyên sâu'),
(@luat,'LUAT_CHUYENDE4','Chuyên đề Luật 4',3,'elective',6,'Chủ đề chuyên sâu'),
(@luat,'LUAT_THUCHANH','Thực hành nghề nghiệp',3,'required',6,'Thực tập tại văn phòng luật'),
(@luat,'LUAT_TOANPHAP','Toà án và tố tụng thực hành',3,'required',6,'Mô phỏng phiên toà'),
(@luat,'LUAT_PHAPLUAT','Pháp luật kinh doanh nâng cao',3,'required',6,'Case study pháp lý kinh doanh'),
(@luat,'LUAT_TTDN','Thực tập doanh nghiệp',6,'required',7,'Thực tập tốt nghiệp'),
(@luat,'LUAT_CHUYENDE5','Chuyên đề tốt nghiệp',3,'elective',7,'Nghiên cứu chuyên sâu'),
(@luat,'LUAT_KLTN','Khóa luận tốt nghiệp',6,'required',8,'Nghiên cứu và bảo vệ khóa luận');

-- =====================================================
-- NGÀNH: NGÔN NGỮ ANH (7220201) - 120 TC
-- =====================================================
SET @nna = (SELECT id FROM majors WHERE major_code = '7220201' LIMIT 1);

INSERT INTO subjects (major_id, subject_code, subject_name, credits, subject_type, semester_order, description) VALUES
(@nna,'NNA_TTHCM','Tư tưởng Hồ Chí Minh',2,'general',1,'Tư tưởng đạo đức HCM'),
(@nna,'NNA_GDQP1','Giáo dục quốc phòng 1',3,'general',1,'Kiến thức quốc phòng'),
(@nna,'NNA_GDTC1','Giáo dục thể chất 1',1,'general',1,'Thể dục cơ bản'),
(@nna,'NNA_NGHE1','Nghe - Nói 1',3,'required',1,'Kỹ năng nghe nói tiếng Anh A2-B1'),
(@nna,'NNA_DOC1','Đọc - Viết 1',3,'required',1,'Kỹ năng đọc viết tiếng Anh A2-B1'),
(@nna,'NNA_NGU1','Ngữ pháp tiếng Anh 1',3,'required',1,'Ngữ pháp cơ bản đến trung cấp'),
(@nna,'NNA_LLCT1','Triết học Mác-Lênin',3,'general',1,'Triết học duy vật'),
(@nna,'NNA_NGHE2','Nghe - Nói 2',3,'required',2,'Kỹ năng nghe nói B1-B2'),
(@nna,'NNA_DOC2','Đọc - Viết 2',3,'required',2,'Kỹ năng đọc viết B1-B2'),
(@nna,'NNA_NGU2','Ngữ pháp tiếng Anh 2',3,'required',2,'Ngữ pháp nâng cao'),
(@nna,'NNA_LLCT2','Kinh tế chính trị',2,'general',2,'Kinh tế chính trị Mác-Lênin'),
(@nna,'NNA_GDTC2','Giáo dục thể chất 2',1,'general',2,'Thể dục nâng cao'),
(@nna,'NNA_GDQP2','Giáo dục quốc phòng 2',3,'general',2,'Kỹ năng quân sự'),
(@nna,'NNA_VANHOA','Văn hoá Anh - Mỹ',3,'required',2,'Văn hoá, lịch sử Anh - Mỹ'),
(@nna,'NNA_NGHE3','Nghe - Nói 3',3,'required',3,'Kỹ năng nghe nói B2-C1'),
(@nna,'NNA_DOC3','Đọc - Viết 3',3,'required',3,'Kỹ năng đọc viết B2-C1'),
(@nna,'NNA_DICH1','Dịch thuật 1',3,'required',3,'Dịch Anh-Việt, Việt-Anh cơ bản'),
(@nna,'NNA_LLCT3','Chủ nghĩa xã hội khoa học',2,'general',3,'Lý luận CNXH'),
(@nna,'NNA_GDTC3','Giáo dục thể chất 3',2,'general',3,'Thể thao tự chọn'),
(@nna,'NNA_VANHOC','Văn học Anh',3,'required',3,'Tác phẩm văn học Anh tiêu biểu'),
(@nna,'NNA_NGONNGUHOC','Ngôn ngữ học đại cương',2,'required',3,'Lý thuyết ngôn ngữ học'),
(@nna,'NNA_NGHE4','Nghe - Nói 4',3,'required',4,'Kỹ năng nghe nói C1'),
(@nna,'NNA_DICH2','Dịch thuật 2',3,'required',4,'Dịch thuật nâng cao, phiên dịch'),
(@nna,'NNA_VIET1','Viết học thuật',3,'required',4,'Academic writing, essay, report'),
(@nna,'NNA_LLCT4','Lịch sử Đảng',2,'general',4,'Lịch sử Đảng CSVN'),
(@nna,'NNA_THUONGMAI','Tiếng Anh thương mại',3,'required',4,'Business English, thư tín thương mại'),
(@nna,'NNA_GIAODUC','Phương pháp giảng dạy tiếng Anh',3,'required',4,'TESOL, phương pháp dạy học'),
(@nna,'NNA_BIENPHIEN','Biên phiên dịch',3,'required',5,'Kỹ năng biên dịch, phiên dịch chuyên nghiệp'),
(@nna,'NNA_CHUYENANH','Tiếng Anh chuyên ngành',3,'required',5,'Tiếng Anh pháp lý/y tế/kỹ thuật'),
(@nna,'NNA_VIET2','Viết sáng tạo',3,'required',5,'Creative writing, storytelling'),
(@nna,'NNA_CHUYENDE1','Chuyên đề Ngôn ngữ Anh 1',3,'elective',5,'Chủ đề chuyên sâu'),
(@nna,'NNA_CHUYENDE2','Chuyên đề Ngôn ngữ Anh 2',3,'elective',5,'Chủ đề chuyên sâu'),
(@nna,'NNA_NGHIEPVU','Nghiệp vụ ngôn ngữ',3,'required',5,'Thực hành kỹ năng nghề'),
(@nna,'NNA_CHUYENDE3','Chuyên đề Ngôn ngữ Anh 3',3,'elective',6,'Chủ đề chuyên sâu'),
(@nna,'NNA_CHUYENDE4','Chuyên đề Ngôn ngữ Anh 4',3,'elective',6,'Chủ đề chuyên sâu'),
(@nna,'NNA_THUCHANH','Thực hành nghề nghiệp',3,'required',6,'Thực tập tại doanh nghiệp/trường học'),
(@nna,'NNA_DICHTHUAT','Dịch thuật chuyên nghiệp',3,'required',6,'Dịch tài liệu chuyên ngành'),
(@nna,'NNA_GIAOTIEP','Giao tiếp liên văn hoá',3,'required',6,'Cross-cultural communication'),
(@nna,'NNA_TTDN','Thực tập doanh nghiệp',6,'required',7,'Thực tập tốt nghiệp'),
(@nna,'NNA_CHUYENDE5','Chuyên đề tốt nghiệp',3,'elective',7,'Nghiên cứu chuyên sâu'),
(@nna,'NNA_KLTN','Khóa luận tốt nghiệp',6,'required',8,'Nghiên cứu và bảo vệ khóa luận');

-- =====================================================
-- NGÀNH: GIÁO DỤC TIỂU HỌC (7140202) - 120 TC
-- =====================================================
SET @gdth = (SELECT id FROM majors WHERE major_code = '7140202' LIMIT 1);

INSERT INTO subjects (major_id, subject_code, subject_name, credits, subject_type, semester_order, description) VALUES
(@gdth,'GDTH_TTHCM','Tư tưởng Hồ Chí Minh',2,'general',1,'Tư tưởng đạo đức HCM'),
(@gdth,'GDTH_GDQP1','Giáo dục quốc phòng 1',3,'general',1,'Kiến thức quốc phòng'),
(@gdth,'GDTH_GDTC1','Giáo dục thể chất 1',1,'general',1,'Thể dục cơ bản'),
(@gdth,'GDTH_TAMLY','Tâm lý học đại cương',3,'required',1,'Tâm lý học cơ bản'),
(@gdth,'GDTH_GIAODUC','Giáo dục học đại cương',3,'required',1,'Lý luận giáo dục'),
(@gdth,'GDTH_TIENGANH1','Tiếng Anh 1',3,'general',1,'Tiếng Anh A1-A2'),
(@gdth,'GDTH_LLCT1','Triết học Mác-Lênin',3,'general',1,'Triết học duy vật'),
(@gdth,'GDTH_TAMLYTRE','Tâm lý học trẻ em',3,'required',2,'Phát triển tâm lý trẻ tiểu học'),
(@gdth,'GDTH_GIAODUC2','Giáo dục học tiểu học',3,'required',2,'Lý luận dạy học tiểu học'),
(@gdth,'GDTH_TIENGANH2','Tiếng Anh 2',3,'general',2,'Tiếng Anh B1'),
(@gdth,'GDTH_LLCT2','Kinh tế chính trị',2,'general',2,'Kinh tế chính trị Mác-Lênin'),
(@gdth,'GDTH_GDTC2','Giáo dục thể chất 2',1,'general',2,'Thể dục nâng cao'),
(@gdth,'GDTH_GDQP2','Giáo dục quốc phòng 2',3,'general',2,'Kỹ năng quân sự'),
(@gdth,'GDTH_TINHOC','Tin học ứng dụng',3,'required',2,'Tin học văn phòng, dạy học trực tuyến'),
(@gdth,'GDTH_PPDAY_TOAN','PP dạy học Toán tiểu học',3,'required',3,'Phương pháp dạy Toán lớp 1-5'),
(@gdth,'GDTH_PPDAY_TV','PP dạy học Tiếng Việt',3,'required',3,'Phương pháp dạy Tiếng Việt lớp 1-5'),
(@gdth,'GDTH_PPDAY_TN','PP dạy học Tự nhiên - Xã hội',3,'required',3,'Phương pháp dạy TN-XH'),
(@gdth,'GDTH_LLCT3','Chủ nghĩa xã hội khoa học',2,'general',3,'Lý luận CNXH'),
(@gdth,'GDTH_GDTC3','Giáo dục thể chất 3',2,'general',3,'Thể thao tự chọn'),
(@gdth,'GDTH_AMNHAC','Âm nhạc và PP dạy học',3,'required',3,'Dạy âm nhạc tiểu học'),
(@gdth,'GDTH_MYTHUAT','Mỹ thuật và PP dạy học',2,'required',3,'Dạy mỹ thuật tiểu học'),
(@gdth,'GDTH_PPDAY_ANH','PP dạy học Tiếng Anh TH',3,'required',4,'Phương pháp dạy Tiếng Anh lớp 3-5'),
(@gdth,'GDTH_PPDAY_TD','PP dạy học Thể dục TH',3,'required',4,'Phương pháp dạy Thể dục tiểu học'),
(@gdth,'GDTH_DANH','Đánh giá trong giáo dục',3,'required',4,'Kiểm tra đánh giá học sinh'),
(@gdth,'GDTH_LLCT4','Lịch sử Đảng',2,'general',4,'Lịch sử Đảng CSVN'),
(@gdth,'GDTH_CHUYEN1','Chuyên đề GD tiểu học 1',3,'elective',4,'Chủ đề chuyên sâu'),
(@gdth,'GDTH_CHUYEN2','Chuyên đề GD tiểu học 2',3,'elective',4,'Chủ đề chuyên sâu'),
(@gdth,'GDTH_QUANLY','Quản lý lớp học',3,'required',4,'Kỹ năng quản lý lớp, xử lý tình huống'),
(@gdth,'GDTH_GIAODUC3','Giáo dục đặc biệt',3,'required',5,'Dạy học hoà nhập, trẻ khuyết tật'),
(@gdth,'GDTH_CONGNGHE','Công nghệ dạy học',3,'required',5,'E-learning, phần mềm giáo dục'),
(@gdth,'GDTH_CHUYEN3','Chuyên đề GD tiểu học 3',3,'elective',5,'Chủ đề chuyên sâu'),
(@gdth,'GDTH_CHUYEN4','Chuyên đề GD tiểu học 4',3,'elective',5,'Chủ đề chuyên sâu'),
(@gdth,'GDTH_NGHIEPVU','Nghiệp vụ sư phạm',3,'required',5,'Thực hành kỹ năng sư phạm'),
(@gdth,'GDTH_TUVANTL','Tư vấn tâm lý học đường',3,'required',5,'Hỗ trợ tâm lý học sinh'),
(@gdth,'GDTH_CHUYEN5','Chuyên đề GD tiểu học 5',3,'elective',6,'Chủ đề chuyên sâu'),
(@gdth,'GDTH_CHUYEN6','Chuyên đề GD tiểu học 6',3,'elective',6,'Chủ đề chuyên sâu'),
(@gdth,'GDTH_THUCHANH','Thực hành nghề nghiệp',3,'required',6,'Kiến tập sư phạm tại trường TH'),
(@gdth,'GDTH_NGHIENCUU','Nghiên cứu khoa học GD',3,'required',6,'Phương pháp NCKH trong giáo dục'),
(@gdth,'GDTH_GIAODUC4','Giáo dục gia đình - cộng đồng',3,'required',6,'Phối hợp gia đình, cộng đồng'),
(@gdth,'GDTH_TTDN','Thực tập sư phạm',6,'required',7,'Thực tập tốt nghiệp tại trường TH'),
(@gdth,'GDTH_CHUYEN7','Chuyên đề tốt nghiệp',3,'elective',7,'Nghiên cứu chuyên sâu'),
(@gdth,'GDTH_KLTN','Khóa luận tốt nghiệp',6,'required',8,'Nghiên cứu và bảo vệ khóa luận');
