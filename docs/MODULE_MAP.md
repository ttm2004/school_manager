# Tổng quan phân hệ và tác nhân

Tài liệu này mô tả các phân hệ chính của hệ thống quản lý đào tạo, vai trò người dùng và phạm vi nghiệp vụ của từng nhóm chức năng. Mục tiêu là giúp giảng viên hoặc người đọc nhanh chóng hiểu hệ thống được tổ chức như thế nào và mỗi tác nhân vận hành phần nào trong quy trình đào tạo.

## 1. Mục tiêu hệ thống

Hệ thống mô phỏng hoạt động quản lý đào tạo trong trường đại học, bao gồm các nghiệp vụ từ cấu hình học kỳ, đề xuất mở lớp, phân công giảng viên, đăng ký học phần, lập lịch thi, nhập điểm, theo dõi học phí đến tuyển sinh.

Các nghiệp vụ không được xử lý rời rạc như CRUD đơn thuần, mà được liên kết theo một chuỗi vận hành có kiểm tra điều kiện, phân quyền, trạng thái và thời gian xử lý.

## 2. Các tác nhân chính

| Tác nhân | Vai trò trong hệ thống |
| --- | --- |
| Admin | Quản trị dữ liệu nền, tài khoản, khoa/viện, ngành học, lớp hành chính và cấu hình chung. |
| Phòng Đào tạo | Điều phối học kỳ, mở lớp học phần, xếp phòng, phân công giảng viên, lập lịch thi và khóa điểm. |
| Khoa/Viện | Quản lý giảng viên, sinh viên thuộc khoa, nguyện vọng giảng dạy, đề xuất mở lớp và báo cáo. |
| Bộ môn | Hỗ trợ duyệt nguyện vọng giảng dạy và quản lý chuyên môn trong phạm vi khoa. |
| Giảng viên | Đăng ký nguyện vọng giảng dạy, xem lớp được phân công, xem lịch dạy và nhập điểm. |
| Sinh viên | Đăng ký/hủy học phần, xem thời khóa biểu, lịch thi, điểm và học phí. |
| Tài chính | Quản lý học phí, hóa đơn, thanh toán và trạng thái công nợ. |
| Tuyển sinh | Quản lý đợt tuyển sinh, phương thức xét tuyển, hồ sơ đăng ký và nhập học. |

## 3. Bản đồ phân hệ

### Phân hệ Phòng Đào tạo

Thư mục: `academic/`

Phân hệ này đóng vai trò điều phối học vụ cấp trường:

- Quản lý học kỳ và các mốc thời gian nghiệp vụ.
- Duyệt đề xuất mở lớp từ Khoa/Viện.
- Xếp phòng học và kiểm tra trùng lịch.
- Duyệt hoặc phân công giảng viên cho lớp học phần.
- Lập lịch thi cuối kỳ.
- Quản lý điểm, khóa điểm và nhắc nhập điểm.
- Theo dõi các cảnh báo như lớp thiếu giảng viên, thiếu lịch, thiếu phòng hoặc thiếu điểm.

### Phân hệ Khoa/Viện

Thư mục: `faculty/`

Phân hệ này đại diện cho đơn vị đào tạo cấp khoa:

- Quản lý giảng viên, sinh viên và bộ môn.
- Ghi nhận nguyện vọng giảng dạy của giảng viên.
- Duyệt nguyện vọng ở cấp bộ môn/khoa.
- Đề xuất mở lớp học phần theo chương trình đào tạo.
- Đề xuất giảng viên phù hợp cho lớp học phần.
- Theo dõi điểm, lịch thi, cảnh báo học vụ và báo cáo.

### Phân hệ Giảng viên

Thư mục: `teacher/`

Giảng viên sử dụng hệ thống để:

- Xem các lớp học phần được phân công.
- Xem thời khóa biểu và lịch thi liên quan.
- Nhập điểm trong thời gian được Phòng Đào tạo mở.
- Theo dõi phản hồi/đánh giá nếu có.

### Phân hệ Sinh viên

Thư mục: `student/`

Sinh viên sử dụng hệ thống để:

- Xem danh sách lớp học phần có thể đăng ký.
- Đăng ký hoặc hủy học phần trong thời gian cho phép.
- Xem thời khóa biểu cá nhân.
- Xem lịch thi, điểm và học phí.

### Phân hệ Tài chính

Thư mục: `finance/`

Phân hệ này phục vụ quản lý học phí:

- Quản lý kỳ thu học phí.
- Tạo và theo dõi hóa đơn.
- Ghi nhận thanh toán.
- Cung cấp trạng thái công nợ để ràng buộc đăng ký học phần khi cần.

### Phân hệ Tuyển sinh

Thư mục: `admissions/`

Phân hệ này mô phỏng quy trình tuyển sinh:

- Quản lý tin tuyển sinh, phương thức xét tuyển và đợt tuyển sinh.
- Tiếp nhận hồ sơ đăng ký.
- Xét duyệt, công bố kết quả và xác nhận nhập học.
- Hỗ trợ tạo tài khoản/lớp cho sinh viên trúng tuyển.

### Phân hệ Quản trị

Thư mục: `admin/`

Admin quản lý dữ liệu nền và cấu hình hệ thống:

- Tài khoản người dùng.
- Khoa/Viện, ngành học, lớp hành chính.
- Danh mục môn học, chương trình đào tạo và dữ liệu nền khác.
- Thông báo chung và thống kê truy cập.

## 4. Các thành phần dùng chung

| Thành phần | Vai trò |
| --- | --- |
| `includes/` | Chứa phân quyền, xác thực, helper và các thành phần dùng chung. |
| `app/Services/` | Chứa service xử lý nghiệp vụ quan trọng như đăng ký học phần, xếp phòng, lịch thi, phân công giảng viên. |
| `database/` | Chứa migration, seed, dữ liệu import và dump phục vụ cài đặt. |
| `assets/` | Chứa CSS, JavaScript và tài nguyên giao diện. |
| `tests/` | Chứa bộ kiểm thử cho các quy tắc nghiệp vụ chính. |

## 5. Ý nghĩa thiết kế

Hệ thống được chia theo vai trò vận hành thực tế trong trường đại học. Mỗi phân hệ có phạm vi riêng nhưng liên kết với nhau qua các quy trình nghiệp vụ. Cách tổ chức này giúp người dùng thao tác đúng chức năng, đồng thời giúp người đọc dễ theo dõi luồng dữ liệu từ lúc mở lớp đến khi sinh viên nhận kết quả học tập.
 
## 6. Cap nhat workflow lop hanh chinh va lop hoc phan

- `academic/classes.php`: Phong Dao tao mo lop hanh chinh tu dot tuyen sinh `system` hoac `test`. Chi chan khi dot da `completed` hoac khong co dot phu hop. Sau khi tao lop chi duoc sua ten lop va si so; ma lop, nganh, khoa va che do du lieu la dinh danh he thong.
- `academic/course_sections.php`: Phong Dao tao tao lop hoc phan tu CTDT theo tung lop hanh chinh cua nganh/khoa, khong tao trung mon/lop/hoc ky da co.
- `academic/course_sections.php`: Nut `Xep lich/phong` tu dong phan bo ca hoc, khoang tuan hoc va phong cho cac lop hoc phan dang thieu lich/phong trong hoc ky dang chon.
- `includes/AcademicPolicy.php` va `app/Services/RoomSchedulingService.php`: Chua logic tinh so buoi/so tuan theo tong so tiet, random tuan bat dau trong hoc ky va kiem tra trung phong, giang vien, lop hanh chinh theo khoang ngay hoc.
- `app/Services/AdmissionsEnrollmentService.php`: Dung cho luong tu dong dang ky HK1 nam nhat; cac hoc ky sau sinh vien tu dang ky.
