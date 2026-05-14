# School Manager - Hệ thống quản lý đào tạo

Dự án PHP page-based mô phỏng hệ thống quản lý đào tạo trong trường đại học. Hệ thống tập trung vào các nghiệp vụ chính: Phòng Đào tạo, Khoa/Viện, Giảng viên, Sinh viên, Tài chính và Tuyển sinh.

## Cấu trúc chính

- `academic/`: Phòng Đào tạo quản lý học kỳ, mở lớp học phần, xếp phòng, phân công giảng viên, lịch thi và khóa điểm.
- `faculty/`: Khoa/Viện quản lý giảng viên, sinh viên thuộc khoa, nguyện vọng giảng dạy, đề xuất mở lớp và báo cáo.
- `teacher/`: Giảng viên xem lớp được phân công, thời khóa biểu, lịch thi và nhập điểm trong thời gian cho phép.
- `student/`: Sinh viên đăng ký/hủy học phần, xem thời khóa biểu, lịch thi, điểm và học phí.
- `finance/`: Quản lý học phí, hóa đơn và thanh toán.
- `admissions/`: Quản lý tuyển sinh, hồ sơ, phương thức xét tuyển, đợt tuyển sinh và nhập học.
- `admin/`: Quản trị dữ liệu nền, tài khoản, khoa/viện, ngành học và cấu hình chung.
- `app/Services/`: Các service nghiệp vụ dùng chung cho những quy trình quan trọng.
- `includes/`: Helper, phân quyền, bảo mật phiên đăng nhập và các hàm dùng chung.
- `database/`: Migration, seed, dữ liệu import và bản dump phục vụ cài đặt dữ liệu.
- `docs/`: Tài liệu mô tả hệ thống dành cho giảng viên/người đọc.
- `tests/`: Bộ kiểm thử PHPUnit cho các rule nghiệp vụ và luồng quan trọng.

## Tài liệu mô tả hệ thống

- [docs/MODULE_MAP.md](docs/MODULE_MAP.md): Bản đồ module, tác nhân và phạm vi từng phân hệ.
- [docs/WORKFLOWS.md](docs/WORKFLOWS.md): Các workflow nghiệp vụ chính từ đề xuất mở lớp tới nhập điểm.
- [docs/DEMO_SCRIPT.md](docs/DEMO_SCRIPT.md): Kịch bản demo/bảo vệ theo luồng vận hành thực tế.
- [docs/ACCEPTANCE_TEST.md](docs/ACCEPTANCE_TEST.md): Checklist nghiệm thu để kiểm tra các chức năng chính.

## Luồng vận hành tổng quát

1. Phòng Đào tạo cấu hình học kỳ và thời gian nghiệp vụ.
2. Giảng viên đăng ký nguyện vọng giảng dạy.
3. Khoa/Viện duyệt nguyện vọng, đề xuất mở lớp và đề xuất giảng viên.
4. Phòng Đào tạo duyệt mở lớp, xếp phòng, phân công giảng viên và lập lịch thi.
5. Sinh viên đăng ký học phần trong thời gian mở.
6. Giảng viên nhập điểm khi môn học kết thúc và cửa sổ nhập điểm còn hiệu lực.
7. Phòng Đào tạo khóa điểm, Sinh viên xem kết quả và học phí.

## Kiểm tra nhanh

```powershell
php -l academic\proposals.php
vendor\bin\phpunit.bat
```
