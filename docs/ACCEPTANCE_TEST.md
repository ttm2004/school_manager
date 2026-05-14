# Tiêu chí kiểm tra và nghiệm thu

Tài liệu này mô tả các tiêu chí kiểm tra chức năng chính của hệ thống. Nội dung được viết theo hướng giúp người đọc đánh giá hệ thống có đáp ứng các nghiệp vụ đào tạo cơ bản hay không.

## 1. Tài khoản và phân quyền

### Mục tiêu kiểm tra

Bảo đảm mỗi người dùng chỉ truy cập được đúng phân hệ và chức năng theo vai trò.

### Tiêu chí đạt

- Admin truy cập được khu vực quản trị.
- Phòng Đào tạo truy cập được các chức năng học vụ cấp trường.
- Khoa/Viện truy cập được các chức năng quản lý cấp khoa.
- Giảng viên truy cập được lớp được phân công, lịch dạy và nhập điểm.
- Sinh viên truy cập được đăng ký học phần, thời khóa biểu, lịch thi, điểm và học phí.
- Người dùng không đúng vai trò bị chặn khỏi chức năng không có quyền.

## 2. Học kỳ và thời gian nghiệp vụ

### Mục tiêu kiểm tra

Bảo đảm các thao tác quan trọng chỉ thực hiện được trong thời gian được cấu hình.

### Tiêu chí đạt

- Có thể tạo và cập nhật học kỳ.
- Có thể mở/đóng thời gian Khoa/Viện gửi đề xuất.
- Có thể mở/đóng thời gian sinh viên đăng ký học phần.
- Có thể cấu hình thời hạn nhập điểm.
- Khi hết thời gian, hệ thống chặn các thao tác tương ứng.

## 3. Nguyện vọng giảng dạy

### Mục tiêu kiểm tra

Bảo đảm giảng viên đăng ký nguyện vọng đúng phạm vi và được duyệt theo quy trình.

### Tiêu chí đạt

- Giảng viên đăng ký được nguyện vọng dạy môn phù hợp.
- Nguyện vọng ngoài phạm vi hoặc ngoài thời gian cho phép bị chặn.
- Bộ môn hoặc Khoa/Viện có thể duyệt/từ chối nguyện vọng.
- Nguyện vọng được duyệt có thể dùng làm căn cứ đề xuất phân công giảng viên.

## 4. Đề xuất mở lớp học phần

### Mục tiêu kiểm tra

Bảo đảm Khoa/Viện đề xuất mở lớp đúng chương trình đào tạo và đúng thời gian.

### Tiêu chí đạt

- Khoa/Viện tạo được đề xuất mở lớp học phần.
- Môn học được kiểm tra theo chương trình đào tạo.
- Đề xuất ngoài thời gian cho phép bị chặn.
- Đề xuất được gửi lên Phòng Đào tạo để duyệt.
- Phòng Đào tạo có thể duyệt hoặc từ chối đề xuất.

## 5. Phân công giảng viên

### Mục tiêu kiểm tra

Bảo đảm giảng viên được phân công đúng điều kiện chuyên môn và đúng quy trình.

### Tiêu chí đạt

- Khoa/Viện đề xuất được giảng viên cho lớp học phần.
- Giảng viên ngoài khoa hoặc chưa có nguyện vọng phù hợp bị chặn.
- Phòng Đào tạo duyệt phân công thành công.
- Sau khi duyệt, giảng viên thấy lớp trong danh sách được phân công.

## 6. Xếp phòng và thời khóa biểu

### Mục tiêu kiểm tra

Bảo đảm lớp học phần có phòng học và lịch học hợp lệ.

### Tiêu chí đạt

- Lớp offline/hybrid phải có lịch học.
- Hệ thống kiểm tra phòng còn trống theo lịch.
- Phòng không đủ sức chứa hoặc sai loại phòng bị loại khỏi lựa chọn.
- Phòng bị trùng lịch không được chấp nhận.
- Lớp online không bắt buộc phòng học vật lý.

## 7. Đăng ký học phần

### Mục tiêu kiểm tra

Bảo đảm sinh viên chỉ đăng ký được lớp học phần hợp lệ.

### Tiêu chí đạt

- Sinh viên thấy danh sách lớp học phần phù hợp với chương trình đào tạo.
- Đăng ký thành công khi lớp còn chỗ và đang trong thời gian đăng ký.
- Hệ thống chặn các trường hợp trùng lịch, trùng môn, hết sĩ số, thiếu tiên quyết hoặc ngoài thời gian đăng ký.
- Hệ thống có thể chặn đăng ký nếu sinh viên còn công nợ học phí theo cấu hình.
- Sinh viên hủy đăng ký được trong thời gian cho phép.
- Sĩ số lớp được cập nhật sau khi đăng ký hoặc hủy.

## 8. Lịch thi

### Mục tiêu kiểm tra

Bảo đảm lịch thi được lập hợp lệ và không gây xung đột.

### Tiêu chí đạt

- Phòng Đào tạo tạo được lịch thi cho lớp học phần.
- Giờ kết thúc phải sau giờ bắt đầu.
- Phòng thi không bị trùng ca.
- Sinh viên không bị trùng lịch thi giữa các môn.
- Sinh viên, giảng viên và Khoa/Viện xem được lịch thi theo quyền.

## 9. Nhập điểm và khóa điểm

### Mục tiêu kiểm tra

Bảo đảm điểm được nhập đúng lớp, đúng thời gian và có kiểm soát khóa điểm.

### Tiêu chí đạt

- Giảng viên chỉ nhập điểm cho lớp được phân công.
- Điểm chỉ được nhập trong thời gian cho phép.
- Khi quá hạn hoặc lớp đã khóa điểm, hệ thống chặn sửa điểm.
- Phòng Đào tạo theo dõi được lớp thiếu điểm.
- Phòng Đào tạo khóa/mở khóa điểm theo quyền.
- Sinh viên xem được điểm sau khi có dữ liệu.

## 10. Báo cáo và theo dõi

### Mục tiêu kiểm tra

Bảo đảm các vai trò quản lý có thông tin tổng quan để theo dõi nghiệp vụ.

### Tiêu chí đạt

- Dashboard Phòng Đào tạo hiển thị các cảnh báo học vụ quan trọng.
- Dashboard Khoa/Viện hiển thị thông tin giảng viên, sinh viên, đề xuất và báo cáo.
- Các báo cáo hoặc export chính có thể sử dụng được.
- Dữ liệu hiển thị đúng theo phạm vi quyền của người dùng.
