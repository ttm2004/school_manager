# School Manager - Hệ thống quản lý đào tạo

Dự án PHP page-based mô phỏng hệ thống quản lý vận hành trong trường đại học. Hệ thống tập trung vào các nghiệp vụ chính: Đào tạo, Khoa/Viện, Giảng viên, Sinh viên, Tài chính và Tuyển sinh.

## Công nghệ sử dụng

- PHP thuần, MySQLi, session-based authentication.
- Bootstrap 5.3 và Bootstrap Icons cho giao diện.
- Chart.js cho một số màn hình thống kê tuyển sinh/báo cáo.
- Composer autoload cho helper nội bộ.
- PHPUnit cho kiểm thử nghiệp vụ.
- Không dùng framework lớn như Laravel, React/Vue/Angular, FullCalendar, DataTables hay jQuery.

## Cấu trúc chính

- `academic/`: Phòng Đào tạo quản lý học kỳ, chương trình đào tạo, lớp học phần, môn chung, xếp phòng, phân công giảng viên, lịch thi, khóa điểm và xử lý đăng ký chờ.
- `faculty/`: Khoa/Viện quản lý giảng viên, sinh viên thuộc khoa, nguyện vọng giảng dạy, đề xuất mở lớp, cảnh báo học vụ và báo cáo.
- `teacher/`: Giảng viên xem lớp được phân công, thời khóa biểu, lịch thi, danh sách sinh viên và nhập điểm trong thời gian cho phép.
- `student/`: Sinh viên đăng ký/hủy học phần, xem thời khóa biểu, lịch thi, điểm, chương trình đào tạo và học phí.
- `finance/`: Quản lý đợt thu học phí, hóa đơn, thanh toán, báo cáo, chỉnh trạng thái hóa đơn thủ công và khóa chức năng sinh viên khi nợ học phí quá hạn.
- `admissions/`: Quản lý tuyển sinh, hồ sơ, phương thức xét tuyển, phân lớp, nhập học, công bố kết quả và tra cứu công khai.
- `admin/`: Quản trị dữ liệu nền, tài khoản, khoa/viện, ngành học, chương trình đào tạo và cấu hình chung.
- `app/Services/`: Các service nghiệp vụ dùng chung cho những quy trình quan trọng.
- `includes/`: Helper, phân quyền, bảo mật phiên đăng nhập, chính sách học vụ và các hàm dùng chung.
- `database/`: Migration, seed, dữ liệu import và dữ liệu mẫu.
- `docs/`: Tài liệu mô tả hệ thống, workflow, demo và checklist nghiệm thu.
- `tests/`: Bộ kiểm thử PHPUnit cho các rule nghiệp vụ và luồng quan trọng.

## Tài liệu hệ thống

- [docs/MODULE_MAP.md](docs/MODULE_MAP.md): Bản đồ module, tác nhân và phạm vi từng phân hệ.
- [docs/WORKFLOWS.md](docs/WORKFLOWS.md): Các workflow nghiệp vụ chính.
- [docs/WORKFLOW_FILE_GUIDE.md](docs/WORKFLOW_FILE_GUIDE.md): Mapping workflow với file/màn hình liên quan.
- [docs/DEMO_SCRIPT.md](docs/DEMO_SCRIPT.md): Kịch bản demo/bảo vệ theo luồng vận hành thực tế.
- [docs/ACCEPTANCE_TEST_FULL_WORKFLOW.md](docs/ACCEPTANCE_TEST_FULL_WORKFLOW.md): Checklist nghiệm thu toàn luồng.

## Luồng vận hành tổng quát

1. Phòng Đào tạo cấu hình học kỳ, thời gian nghiệp vụ, chương trình đào tạo và lớp học phần.
2. Giảng viên đăng ký nguyện vọng giảng dạy.
3. Khoa/Viện duyệt nguyện vọng, đề xuất mở lớp và đề xuất giảng viên.
4. Phòng Đào tạo duyệt mở lớp, xếp phòng, phân công giảng viên và lập lịch thi.
5. Sinh viên đăng ký học phần trong thời gian mở.
6. Tài chính tạo đợt thu học phí, công bố hóa đơn và theo dõi thanh toán.
7. Sinh viên nợ học phí quá hạn hoặc bị đánh dấu quá hạn thủ công vẫn vào được trang chức năng nhưng sẽ thấy modal khóa, yêu cầu thanh toán để sử dụng.
8. Giảng viên nhập điểm khi môn học kết thúc và cửa sổ nhập điểm còn hiệu lực.
9. Phòng Đào tạo khóa điểm, sinh viên xem kết quả học tập.

## Nghiệp vụ học phí

- Đợt thu học phí được tạo theo học kỳ và tự sinh hóa đơn nháp theo số tín chỉ đăng ký.
- Khi công bố, hóa đơn chuyển sang trạng thái chưa đóng để sinh viên theo dõi.
- Tài chính có thể lọc hóa đơn theo tên/MSSV, trạng thái và lớp.
- Tài chính có thể chỉnh thủ công trạng thái từng sinh viên: chưa đóng, một phần, đã đóng, quá hạn.
- Nếu hóa đơn là `quá hạn`, sinh viên bị khóa chức năng ngay.
- Nếu hóa đơn `chưa đóng` hoặc `một phần`, sinh viên chỉ bị khóa khi đã quá hạn đóng học phí.
- Khi bị khóa, các trang thời khóa biểu, điểm, lịch thi và đăng ký môn hiển thị modal thông báo nợ học phí thay vì redirect khỏi trang.

## Kiểm tra nhanh

```powershell
php -l includes\auth.php
php -l finance\periods.php
vendor\bin\phpunit.bat
```
