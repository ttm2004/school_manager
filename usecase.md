# Use Case Diagram — Hệ thống Quản lý Đại học TDMU

## Actors

| ID | Actor | Mô tả |
|----|-------|-------|
| A1 | Admin | Quản trị viên hệ thống, toàn quyền |
| A2 | Sinh viên | Người học, đăng ký học phần, xem điểm |
| A3 | Giảng viên | Người dạy, nhập điểm, quản lý lớp |
| A4 | Trưởng Khoa | faculty_manager, quản lý nội bộ khoa |
| A5 | Thư ký Khoa | faculty_staff, hỗ trợ nghiệp vụ khoa |
| A6 | Trưởng phòng Đào tạo | academic_manager, phê duyệt lớp học phần |
| A7 | Nhân viên Đào tạo | academic_staff, hỗ trợ phòng đào tạo |
| A8 | Trưởng phòng Tuyển sinh | admissions_manager, quản lý tuyển sinh |
| A9 | Nhân viên Tuyển sinh | admissions_staff, tiếp nhận hồ sơ |
| A10 | Trưởng phòng Tài chính | finance_manager, quản lý học phí |
| A11 | Nhân viên Kế toán | finance_staff, thu học phí |
| A12 | Phòng Nhân sự | hr_manager/hr_staff (placeholder) |
| A13 | Phòng Khảo thí | exam_manager/exam_staff (placeholder) |
| A14 | Phòng Công tác SV | student_affairs_manager/staff (placeholder) |
| A15 | Quản trị CNTT | it_admin, quản lý hệ thống |

---

## Use Cases theo Module

---

### UC-AUTH: Xác thực & Phân quyền

| UC ID | Use Case | Actor | Mô tả |
|-------|----------|-------|-------|
| UC-AUTH-01 | Đăng nhập hệ thống | Tất cả | Nhập username/password, xác thực session |
| UC-AUTH-02 | Đăng xuất | Tất cả | Hủy session, redirect về login |
| UC-AUTH-03 | Chọn vai trò | A3,A4,A5,A6,A7,A8,A9,A10,A11 | Chọn role khi có từ 2 role trở lên |
| UC-AUTH-04 | Chuyển vai trò | A3,A4,A5,A6,A7,A8,A9,A10,A11 | Chuyển giữa các role đang có |
| UC-AUTH-05 | Quản lý tài khoản | A1 | Tạo/sửa/khóa tài khoản người dùng |
| UC-AUTH-06 | Gán role cho user | A1 | Phân quyền role cho nhân viên |

---

### UC-ADMIN: Quản trị Hệ thống

| UC ID | Use Case | Actor | Mô tả |
|-------|----------|-------|-------|
| UC-ADMIN-01 | Xem dashboard tổng quan | A1 | Thống kê toàn trường |
| UC-ADMIN-02 | Quản lý Khoa/Viện | A1 | CRUD khoa, viện |
| UC-ADMIN-03 | Quản lý Ngành học | A1 | CRUD ngành, gán vào khoa |
| UC-ADMIN-04 | Quản lý Lớp học | A1 | CRUD lớp học (class) |
| UC-ADMIN-05 | Quản lý Môn học | A1 | CRUD môn học, tín chỉ |
| UC-ADMIN-06 | Quản lý Chương trình ĐT | A1 | Xem/sửa CTĐT toàn trường |
| UC-ADMIN-07 | Quản lý Học kỳ | A1,A6 | Tạo/mở/đóng học kỳ |
| UC-ADMIN-08 | Quản lý Lớp học phần | A1 | Xem/sửa tất cả lớp HP |
| UC-ADMIN-09 | Quản lý Sinh viên | A1 | CRUD sinh viên toàn trường |
| UC-ADMIN-10 | Quản lý Giảng viên | A1 | CRUD giảng viên toàn trường |
| UC-ADMIN-11 | Quản lý Điểm số | A1 | Xem/sửa điểm toàn trường |
| UC-ADMIN-12 | Quản lý Lịch thi | A1 | Xem/tạo lịch thi toàn trường |
| UC-ADMIN-13 | Quản lý Đánh giá GV | A1 | Xem kết quả đánh giá |
| UC-ADMIN-14 | Quản lý Học phí | A1 | Xem học phí toàn trường |
| UC-ADMIN-15 | Gửi thông báo | A1 | Gửi thông báo toàn hệ thống |
| UC-ADMIN-16 | Quản lý Liên hệ | A1 | Xem/xử lý form liên hệ |
| UC-ADMIN-17 | Nhập khẩu CTĐT | A1 | Import curriculum từ CSV |
| UC-ADMIN-18 | Phân công GV | A1 | Phân công GV dạy lớp HP |

---

### UC-STUDENT: Cổng Sinh viên

| UC ID | Use Case | Actor | Mô tả |
|-------|----------|-------|-------|
| UC-SV-01 | Xem dashboard cá nhân | A2 | Tổng quan học tập, thông báo |
| UC-SV-02 | Xem hồ sơ cá nhân | A2 | Thông tin cá nhân, ngành, lớp |
| UC-SV-03 | Cập nhật hồ sơ | A2 | Sửa email, SĐT, địa chỉ |
| UC-SV-04 | Đăng ký học phần | A2 | Chọn lớp HP, kiểm tra điều kiện |
| UC-SV-05 | Hủy đăng ký học phần | A2 | Hủy trong thời gian cho phép |
| UC-SV-06 | Xem học phần đã đăng ký | A2 | Danh sách môn đang học |
| UC-SV-07 | Xem thời khóa biểu | A2 | TKB cá nhân theo học kỳ |
| UC-SV-08 | Xem lịch thi | A2 | Lịch thi cuối kỳ cá nhân |
| UC-SV-09 | Xem kết quả học tập | A2 | Điểm các môn, GPA tích lũy |
| UC-SV-10 | Xem học phí | A2 | Hóa đơn, trạng thái đóng tiền |
| UC-SV-11 | Đánh giá giảng viên | A2 | Đánh giá GV sau khi học |
| UC-SV-12 | Xem chương trình ĐT | A2 | CTĐT ngành mình đang học |

---

### UC-TEACHER: Cổng Giảng viên

| UC ID | Use Case | Actor | Mô tả |
|-------|----------|-------|-------|
| UC-GV-01 | Xem dashboard cá nhân | A3 | Tổng quan lớp dạy, thông báo |
| UC-GV-02 | Xem hồ sơ cá nhân | A3 | Thông tin cá nhân, học vị |
| UC-GV-03 | Cập nhật hồ sơ | A3 | Sửa thông tin cá nhân |
| UC-GV-04 | Xem lớp học phần | A3 | Danh sách lớp được phân công |
| UC-GV-05 | Xem thời khóa biểu | A3 | TKB giảng dạy cá nhân |
| UC-GV-06 | Xem lịch thi | A3 | Lịch thi các lớp phụ trách |
| UC-GV-07 | Nhập điểm | A3 | Nhập điểm giữa kỳ, cuối kỳ |
| UC-GV-08 | Xem kết quả đánh giá | A3 | Kết quả SV đánh giá mình |

---

### UC-FACULTY: Quản lý Khoa/Viện

| UC ID | Use Case | Actor | Mô tả |
|-------|----------|-------|-------|
| UC-KHOA-01 | Xem dashboard khoa | A4,A5 | Cảnh báo, thống kê nội bộ khoa |
| UC-KHOA-02 | Xem danh sách GV | A4,A5 | GV thuộc khoa, filter/search |
| UC-KHOA-03 | Xem hồ sơ GV | A4,A5 | Chi tiết GV + teaching load |
| UC-KHOA-04 | Cập nhật hồ sơ GV | A4 | Sửa học vị, chuyên ngành GV |
| UC-KHOA-05 | Quản lý bộ môn | A4 | CRUD bộ môn trong khoa |
| UC-KHOA-06 | Phân GV vào bộ môn | A4 | Gán GV vào bộ môn |
| UC-KHOA-07 | Xem khối lượng GD | A4,A5 | Teaching load theo học kỳ |
| UC-KHOA-08 | Xem danh sách SV | A4,A5 | SV thuộc ngành trong khoa |
| UC-KHOA-09 | Xem hồ sơ SV | A4,A5 | Chi tiết SV + GPA + cảnh báo |
| UC-KHOA-10 | Thêm ghi chú cảnh báo SV | A4 | Ghi chú can thiệp học vụ |
| UC-KHOA-11 | Xem cảnh báo học vụ | A4,A5 | SV có GPA thấp, nợ môn |
| UC-KHOA-12 | Xem kết quả học tập | A4,A5 | Pass rate, điểm theo lớp HP |
| UC-KHOA-13 | Quản lý CTĐT | A4,A5 | Xem/thêm/sửa/xóa môn trong CTĐT |
| UC-KHOA-14 | Đề xuất mở lớp HP | A4 | Tạo/gửi đề xuất mở lớp |
| UC-KHOA-15 | Hủy đề xuất mở lớp | A4 | Hủy đề xuất chưa duyệt |
| UC-KHOA-16 | Đề xuất phân công GV | A4 | Đề xuất GV dạy lớp HP |
| UC-KHOA-17 | Hủy đề xuất phân công | A4 | Hủy đề xuất phân công GV |
| UC-KHOA-18 | Xem lịch thi cuối kỳ | A4,A5 | Lịch thi các lớp trong khoa |
| UC-KHOA-19 | Xem đánh giá GV | A4 | Kết quả SV đánh giá GV khoa |
| UC-KHOA-20 | Xem báo cáo thống kê | A4,A5 | Báo cáo tổng hợp khoa |
| UC-KHOA-21 | Gửi thông báo nội bộ | A4 | Gửi thông báo cho GV/SV khoa |
| UC-KHOA-22 | Xem nhật ký thao tác | A4 | Audit log các thao tác trong khoa |
| UC-KHOA-23 | Export dữ liệu | A4,A5 | Xuất CSV GV/SV/điểm/lịch thi |
| UC-KHOA-24 | Xem nguyện vọng GD | A4,A5 | Nguyện vọng giảng dạy của GV |
| UC-KHOA-25 | Xem KPI giảng viên | A4 | Chỉ số KPI của GV trong khoa |

---

### UC-ACADEMIC: Phòng Đào tạo

| UC ID | Use Case | Actor | Mô tả |
|-------|----------|-------|-------|
| UC-DT-01 | Xem dashboard đào tạo | A6,A7 | Tổng quan lớp HP, đề xuất chờ duyệt |
| UC-DT-02 | Quản lý học kỳ | A6,A7 | Tạo/mở/đóng học kỳ, set deadline |
| UC-DT-03 | Quản lý môn học | A6,A7 | CRUD môn học |
| UC-DT-04 | Quản lý CTĐT | A6,A7 | Xem/sửa CTĐT toàn trường |
| UC-DT-05 | Quản lý phòng học | A6,A7 | CRUD phòng học, loại phòng |
| UC-DT-06 | Quản lý lớp HP | A6,A7 | Xem/tạo/sửa lớp học phần |
| UC-DT-07 | Duyệt đề xuất mở lớp | A6 | Phê duyệt/từ chối đề xuất từ Khoa |
| UC-DT-08 | Phân công GV chính thức | A6 | Phân công GV sau khi duyệt |
| UC-DT-09 | Duyệt đề xuất phân công GV | A6 | Phê duyệt/từ chối đề xuất GV từ Khoa |
| UC-DT-10 | Xem thời khóa biểu | A6,A7 | TKB toàn trường |
| UC-DT-11 | Quản lý lịch thi | A6,A7 | Tạo/sửa lịch thi cuối kỳ |
| UC-DT-12 | Quản lý điểm | A6,A7 | Xem/khóa điểm |
| UC-DT-13 | Khóa điểm | A6 | Khóa điểm sau deadline |
| UC-DT-14 | Nhắc nhập điểm | A6,A7 | Gửi nhắc nhở GV chưa nhập điểm |
| UC-DT-15 | Quản lý sinh viên | A6,A7 | Xem danh sách SV toàn trường |
| UC-DT-16 | Quản lý giảng viên | A6,A7 | Xem danh sách GV toàn trường |
| UC-DT-17 | Xem báo cáo thống kê | A6,A7 | Báo cáo đào tạo toàn trường |
| UC-DT-18 | Gửi thông báo | A6,A7 | Gửi thông báo học vụ |

---

### UC-ADMISSIONS: Phòng Tuyển sinh

| UC ID | Use Case | Actor | Mô tả |
|-------|----------|-------|-------|
| UC-TS-01 | Xem dashboard tuyển sinh | A8,A9 | Thống kê hồ sơ, biểu đồ 7 ngày |
| UC-TS-02 | Xem danh sách hồ sơ | A8,A9 | Danh sách hồ sơ đăng ký |
| UC-TS-03 | Xét duyệt hồ sơ thủ công | A8,A9 | Duyệt/từ chối từng hồ sơ |
| UC-TS-04 | Xét tuyển tự động | A8 | Chạy thuật toán xét tuyển hàng loạt |
| UC-TS-05 | Đặt điểm chuẩn | A8 | Thiết lập điểm chuẩn theo ngành |
| UC-TS-06 | Công bố kết quả | A8 | Công bố điểm chuẩn, kết quả |
| UC-TS-07 | Quản lý nhập học | A8,A9 | Xác nhận nhập học, tạo tài khoản SV |
| UC-TS-08 | Phân lớp tự động | A8 | Tự động phân lớp cho SV nhập học |
| UC-TS-09 | Quản lý phương thức XT | A8 | CRUD phương thức xét tuyển |
| UC-TS-10 | Quản lý tin tuyển sinh | A8,A9 | Đăng/sửa tin tức tuyển sinh |
| UC-TS-11 | Quản lý đợt tuyển sinh | A8 | Tạo/mở/đóng đợt tuyển sinh |
| UC-TS-12 | Xem báo cáo tuyển sinh | A8,A9 | Thống kê hồ sơ, tỷ lệ đậu |
| UC-TS-13 | Export danh sách | A8,A9 | Xuất CSV danh sách thí sinh |
| UC-TS-14 | Tra cứu kết quả (public) | Thí sinh | Tra cứu kết quả xét tuyển |
| UC-TS-15 | Nộp hồ sơ trực tuyến (public) | Thí sinh | Đăng ký xét tuyển online |

---

### UC-FINANCE: Phòng Tài chính

| UC ID | Use Case | Actor | Mô tả |
|-------|----------|-------|-------|
| UC-TC-01 | Xem dashboard tài chính | A10,A11 | Tổng quan thu chi, hóa đơn |
| UC-TC-02 | Quản lý đợt thu học phí | A10 | Tạo/mở/đóng đợt thu |
| UC-TC-03 | Tạo hóa đơn học phí | A10,A11 | Tạo hóa đơn cho SV theo đợt |
| UC-TC-04 | Xem danh sách hóa đơn | A10,A11 | Danh sách hóa đơn, filter trạng thái |
| UC-TC-05 | Xác nhận thanh toán | A10,A11 | Ghi nhận SV đã đóng tiền |
| UC-TC-06 | Xem lịch sử thanh toán | A10,A11 | Lịch sử các lần thanh toán |
| UC-TC-07 | Xem báo cáo tài chính | A10 | Tổng thu, còn nợ, theo đợt |
| UC-TC-08 | Khóa chức năng SV nợ HP | Hệ thống | Tự động khóa đăng ký khi nợ HP |

---

### UC-PUBLIC: Trang công khai

| UC ID | Use Case | Actor | Mô tả |
|-------|----------|-------|-------|
| UC-PUB-01 | Xem trang chủ | Khách | Giới thiệu trường |
| UC-PUB-02 | Xem thông tin tuyển sinh | Khách | Thông tin ngành, chỉ tiêu |
| UC-PUB-03 | Nộp hồ sơ xét tuyển | Thí sinh | Form đăng ký xét tuyển |
| UC-PUB-04 | Tra cứu kết quả XT | Thí sinh | Tra cứu bằng CCCD/SBD |
| UC-PUB-05 | Xem tin tức | Khách | Tin tức tuyển sinh, sự kiện |
| UC-PUB-06 | Liên hệ | Khách | Gửi form liên hệ |

---

## Quan hệ giữa Use Cases

### Include (bắt buộc phải có)
```
UC-KHOA-14 (Đề xuất mở lớp)     <<include>> UC-AUTH-01 (Đăng nhập)
UC-KHOA-16 (Đề xuất phân công)  <<include>> UC-AUTH-01
UC-DT-07   (Duyệt đề xuất)      <<include>> UC-KHOA-14
UC-DT-09   (Duyệt phân công)    <<include>> UC-KHOA-16
UC-SV-04   (Đăng ký HP)         <<include>> UC-SV-09 (Kiểm tra điều kiện)
UC-GV-07   (Nhập điểm)          <<include>> UC-DT-13 (Kiểm tra cửa sổ nhập điểm)
```

### Extend (mở rộng tùy điều kiện)
```
UC-AUTH-03 (Chọn vai trò)       <<extend>> UC-AUTH-01 (khi có ≥2 roles)
UC-AUTH-04 (Chuyển vai trò)     <<extend>> UC-AUTH-03
UC-KHOA-10 (Ghi chú cảnh báo)  <<extend>> UC-KHOA-09 (khi SV có cảnh báo)
UC-TC-08   (Khóa SV nợ HP)     <<extend>> UC-SV-04  (khi SV nợ học phí)
UC-TS-04   (Xét tuyển tự động) <<extend>> UC-TS-03  (thay thế xét thủ công)
```

### Generalization (kế thừa)
```
Trưởng Khoa (A4)    --|> Thư ký Khoa (A5)     [A4 có thêm quyền ghi]
Trưởng phòng ĐT (A6) --|> NV Đào tạo (A7)     [A6 có thêm quyền duyệt]
Trưởng phòng TS (A8) --|> NV Tuyển sinh (A9)  [A8 có thêm quyền phê duyệt]
Trưởng phòng TC (A10) --|> NV Kế toán (A11)   [A10 có thêm quyền quản lý]
```

---

## Tổng số Use Cases

| Module | Số UC |
|--------|-------|
| Xác thực & Phân quyền | 6 |
| Admin | 18 |
| Sinh viên | 12 |
| Giảng viên | 8 |
| Khoa/Viện | 25 |
| Phòng Đào tạo | 18 |
| Phòng Tuyển sinh | 15 |
| Phòng Tài chính | 8 |
| Trang công khai | 6 |
| **Tổng** | **116** |

---

## Sơ đồ Use Case (Text)

```
+------------------------------------------------------------------+
|              HỆ THỐNG QUẢN LÝ ĐẠI HỌC TDMU                     |
|                                                                  |
|  [Đăng nhập] [Đăng xuất] [Chọn vai trò] [Chuyển vai trò]       |
|                                                                  |
|  ┌─────────────────┐  ┌──────────────────┐  ┌───────────────┐  |
|  │   ADMIN         │  │  PHÒNG ĐÀO TẠO   │  │  PHÒNG TS     │  |
|  │ - Quản lý users │  │ - Quản lý học kỳ │  │ - Tiếp nhận   │  |
|  │ - Quản lý khoa  │  │ - Duyệt lớp HP   │  │   hồ sơ       │  |
|  │ - Quản lý ngành │  │ - Phân công GV   │  │ - Xét tuyển   │  |
|  │ - Toàn quyền    │  │ - Quản lý điểm   │  │ - Nhập học    │  |
|  └─────────────────┘  └──────────────────┘  └───────────────┘  |
|                                                                  |
|  ┌─────────────────┐  ┌──────────────────┐  ┌───────────────┐  |
|  │  KHOA/VIỆN      │  │  GIẢNG VIÊN      │  │  SINH VIÊN    │  |
|  │ - Quản lý GV    │  │ - Xem lớp dạy    │  │ - Đăng ký HP  │  |
|  │ - Quản lý SV    │  │ - Nhập điểm      │  │ - Xem điểm    │  |
|  │ - Đề xuất lớp   │  │ - Xem TKB        │  │ - Xem TKB     │  |
|  │ - Quản lý CTĐT  │  │ - Xem lịch thi   │  │ - Đóng HP     │  |
|  └─────────────────┘  └──────────────────┘  └───────────────┘  |
|                                                                  |
|  ┌─────────────────┐  ┌──────────────────┐                      |
|  │  TÀI CHÍNH      │  │  TRANG CÔNG KHAI │                      |
|  │ - Quản lý HP    │  │ - Xem thông tin  │                      |
|  │ - Thu tiền      │  │ - Nộp hồ sơ XT   │                      |
|  │ - Báo cáo TC    │  │ - Tra cứu KQ     │                      |
|  └─────────────────┘  └──────────────────┘                      |
+------------------------------------------------------------------+

Actors bên ngoài:
Admin ──────────────────────────────────────────── [ADMIN module]
Trưởng/NV Đào tạo ──────────────────────────────── [ACADEMIC module]
Trưởng/NV Tuyển sinh ───────────────────────────── [ADMISSIONS module]
Trưởng/NV Tài chính ────────────────────────────── [FINANCE module]
Trưởng Khoa / Thư ký Khoa ──────────────────────── [FACULTY module]
Giảng viên ─────────────────────────────────────── [TEACHER module]
Sinh viên ──────────────────────────────────────── [STUDENT module]
Thí sinh (chưa có TK) ──────────────────────────── [PUBLIC pages]
```

---

*Tài liệu này mô tả 116 use cases cho 15 actors trong hệ thống TDMU.*
*Cập nhật: 2026-05-12*
