# Kịch bản trình bày hệ thống

Tài liệu này gợi ý cách trình bày hệ thống trong buổi demo hoặc bảo vệ. Kịch bản tập trung vào các nghiệp vụ liên kết với nhau để thể hiện hệ thống có quy trình vận hành đầy đủ, không chỉ là các màn hình quản lý dữ liệu đơn lẻ.

## 1. Mục tiêu trình bày

Khi demo, cần làm rõ ba điểm:

1. Hệ thống được chia theo đúng vai trò trong trường đại học.
2. Các thao tác nghiệp vụ có luồng xử lý, trạng thái và phân quyền.
3. Dữ liệu đi xuyên suốt từ mở lớp, đăng ký học phần, nhập điểm đến sinh viên xem kết quả.

## 2. Chuẩn bị trước khi demo

- Có sẵn tài khoản cho các vai trò: Phòng Đào tạo, Khoa/Viện, Giảng viên, Sinh viên, Tài chính.
- Có sẵn học kỳ đang mở đúng thời gian.
- Có dữ liệu môn học, chương trình đào tạo, phòng học, giảng viên và sinh viên mẫu.
- Có ít nhất một lớp học phần để minh họa quy trình mở lớp và đăng ký.

## 3. Kịch bản demo đề xuất

### Bước 1: Giới thiệu tổng quan

Mở trang chính hoặc dashboard để giới thiệu mục tiêu hệ thống và các phân hệ:

- Phòng Đào tạo.
- Khoa/Viện.
- Giảng viên.
- Sinh viên.
- Tài chính.
- Tuyển sinh.

### Bước 2: Phòng Đào tạo cấu hình học kỳ

Đăng nhập bằng tài khoản Phòng Đào tạo và trình bày:

- Danh sách học kỳ.
- Thời gian đề xuất mở lớp.
- Thời gian đăng ký học phần.
- Ý nghĩa của các mốc thời gian trong việc khóa/mở thao tác.

### Bước 3: Giảng viên đăng ký nguyện vọng

Đăng nhập bằng tài khoản Giảng viên và trình bày:

- Danh sách môn học có thể đăng ký nguyện vọng.
- Thao tác gửi nguyện vọng giảng dạy.
- Ý nghĩa của nguyện vọng trong quy trình phân công giảng viên.

### Bước 4: Khoa/Viện xử lý đề xuất

Đăng nhập bằng tài khoản Khoa/Viện và trình bày:

- Duyệt nguyện vọng giảng dạy.
- Tạo đề xuất mở lớp học phần.
- Đề xuất giảng viên cho lớp học phần.
- Theo dõi trạng thái đề xuất sau khi gửi lên Phòng Đào tạo.

### Bước 5: Phòng Đào tạo duyệt mở lớp và xếp phòng

Quay lại tài khoản Phòng Đào tạo và trình bày:

- Danh sách đề xuất từ Khoa/Viện.
- Kiểm tra điều kiện mở lớp.
- Duyệt mở lớp.
- Xếp phòng học hoặc để hệ thống gợi ý phòng phù hợp.
- Duyệt phân công giảng viên.

### Bước 6: Sinh viên đăng ký học phần

Đăng nhập bằng tài khoản Sinh viên và trình bày:

- Danh sách lớp học phần có thể đăng ký.
- Thao tác đăng ký học phần.
- Minh họa một trường hợp bị chặn, ví dụ trùng lịch, hết sĩ số, thiếu điều kiện tiên quyết hoặc ngoài thời gian đăng ký.

### Bước 7: Lập lịch thi và nhập điểm

Quay lại vai trò Phòng Đào tạo/Giảng viên:

- Phòng Đào tạo lập lịch thi cuối kỳ.
- Hệ thống kiểm tra trùng phòng hoặc trùng lịch sinh viên.
- Giảng viên nhập điểm trong thời gian cho phép.
- Phòng Đào tạo khóa điểm.

### Bước 8: Sinh viên xem kết quả

Đăng nhập lại tài khoản Sinh viên và trình bày:

- Thời khóa biểu.
- Lịch thi.
- Điểm học phần.
- Học phí hoặc trạng thái công nợ nếu có.

## 4. Điểm nhấn khi thuyết trình

- Khoa/Viện không tự mở lớp hoàn toàn, mà gửi đề xuất để Phòng Đào tạo duyệt.
- Giảng viên được phân công dựa trên nguyện vọng và điều kiện phù hợp.
- Sinh viên đăng ký học phần phải qua nhiều kiểm tra nghiệp vụ.
- Lịch thi có kiểm tra trùng phòng và trùng lịch sinh viên.
- Nhập điểm bị ràng buộc bởi thời gian và trạng thái khóa điểm.
- Mỗi vai trò chỉ nhìn thấy chức năng phù hợp với quyền của mình.

## 5. Kết luận demo

Kết thúc demo bằng cách nhấn mạnh hệ thống mô phỏng được chuỗi nghiệp vụ đào tạo tương đối đầy đủ: từ lập kế hoạch học kỳ, mở lớp, phân công giảng viên, đăng ký học phần, tổ chức thi, nhập điểm đến sinh viên xem kết quả.
