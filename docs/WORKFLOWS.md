# Quy trình vận hành chính

Tài liệu này mô tả các quy trình nghiệp vụ quan trọng trong hệ thống. Mỗi quy trình được trình bày theo thứ tự vận hành thực tế, từ tác nhân thực hiện đến kết quả sau cùng.

## 1. Quy trình mở lớp học phần

### Mục đích

Mở lớp học phần cho sinh viên đăng ký theo đúng học kỳ, chương trình đào tạo, sĩ số và điều kiện tổ chức giảng dạy.

### Tác nhân tham gia

- Phòng Đào tạo.
- Khoa/Viện.
- Giảng viên.
- Sinh viên.

### Luồng xử lý

1. Phòng Đào tạo cấu hình học kỳ và thời gian cho phép Khoa/Viện gửi đề xuất.
2. Khoa/Viện chọn môn học thuộc chương trình đào tạo phù hợp.
3. Khoa/Viện tạo đề xuất mở lớp học phần, bao gồm môn học, học kỳ, khoa/ngành, sĩ số và hình thức học.
4. Khoa/Viện gửi đề xuất lên Phòng Đào tạo.
5. Phòng Đào tạo kiểm tra điều kiện mở lớp.
6. Hệ thống kiểm tra các thông tin liên quan như học kỳ, chương trình đào tạo, sĩ số tối thiểu và lịch học.
7. Phòng Đào tạo duyệt mở lớp.
8. Lớp học phần chuyển sang trạng thái mở và có thể xuất hiện trong danh sách đăng ký của sinh viên.

### Kết quả

Lớp học phần được mở hợp lệ, có thể tiếp tục được xếp phòng, phân công giảng viên và cho sinh viên đăng ký.

## 2. Quy trình nguyện vọng giảng dạy và phân công giảng viên

### Mục đích

Bảo đảm giảng viên được phân công phù hợp với chuyên môn, khoa phụ trách và nguyện vọng đã được duyệt.

### Tác nhân tham gia

- Giảng viên.
- Bộ môn.
- Khoa/Viện.
- Phòng Đào tạo.

### Luồng xử lý

1. Phòng Đào tạo mở thời gian để giảng viên và Khoa/Viện thao tác.
2. Giảng viên đăng ký nguyện vọng giảng dạy theo học kỳ.
3. Bộ môn hoặc Khoa/Viện xem xét và duyệt nguyện vọng.
4. Khoa/Viện đề xuất giảng viên cho lớp học phần.
5. Hệ thống kiểm tra giảng viên có thuộc đúng khoa và có nguyện vọng hợp lệ hay không.
6. Phòng Đào tạo duyệt phân công giảng viên.
7. Giảng viên nhận thông báo và thấy lớp học phần trong danh sách được phân công.

### Kết quả

Lớp học phần có giảng viên chính thức, sẵn sàng đưa vào thời khóa biểu và các nghiệp vụ liên quan.

## 3. Quy trình xếp phòng học

### Mục đích

Xếp phòng học phù hợp với lịch học, sĩ số, hình thức học và yêu cầu phòng của môn học.

### Tác nhân tham gia

- Phòng Đào tạo.
- Khoa/Viện.

### Luồng xử lý

1. Khoa/Viện đề xuất lịch hoặc nhu cầu tổ chức lớp.
2. Phòng Đào tạo xem xét khi duyệt mở lớp.
3. Nếu lớp học offline hoặc hybrid, hệ thống yêu cầu có lịch học cụ thể.
4. Hệ thống lấy danh sách phòng học từ cơ sở dữ liệu.
5. Hệ thống loại các phòng đang bảo trì, không đủ sức chứa hoặc không phù hợp loại phòng.
6. Hệ thống kiểm tra trùng lịch phòng trong học kỳ.
7. Phòng Đào tạo chọn phòng thủ công hoặc để hệ thống gợi ý phòng phù hợp.

### Kết quả

Lớp học phần được gán phòng học hợp lệ, hạn chế trùng lịch và sai loại phòng.

## 4. Quy trình đăng ký học phần

### Mục đích

Cho phép sinh viên đăng ký học phần theo chương trình đào tạo và các ràng buộc học vụ.

### Tác nhân tham gia

- Sinh viên.
- Phòng Đào tạo.
- Tài chính.

### Luồng xử lý

1. Phòng Đào tạo mở thời gian đăng ký học phần.
2. Sinh viên xem danh sách lớp học phần có thể đăng ký.
3. Hệ thống kiểm tra học kỳ có đang mở đăng ký hay không.
4. Hệ thống kiểm tra môn học có thuộc chương trình đào tạo của sinh viên hay không.
5. Hệ thống kiểm tra điều kiện tiên quyết, trùng môn, trùng lịch, sĩ số, giới hạn tín chỉ và công nợ học phí.
6. Nếu hợp lệ, sinh viên đăng ký thành công và sĩ số lớp được cập nhật.
7. Nếu lớp đủ sĩ số, trạng thái lớp có thể chuyển sang đầy.
8. Sinh viên được phép hủy đăng ký trong thời gian cho phép.

### Kết quả

Sinh viên có danh sách học phần đã đăng ký, đồng thời hệ thống cập nhật sĩ số và dữ liệu học phí liên quan.

## 5. Quy trình lập lịch thi

### Mục đích

Tổ chức lịch thi cuối kỳ phù hợp với lớp học phần, phòng thi và sinh viên tham gia.

### Tác nhân tham gia

- Phòng Đào tạo.
- Sinh viên.
- Giảng viên.
- Khoa/Viện.

### Luồng xử lý

1. Phòng Đào tạo chọn lớp học phần cần lập lịch thi.
2. Nhập ngày thi, giờ bắt đầu, giờ kết thúc và phòng thi.
3. Hệ thống kiểm tra giờ kết thúc phải sau giờ bắt đầu.
4. Hệ thống kiểm tra ngày thi phải phù hợp với thời gian học kỳ.
5. Hệ thống kiểm tra phòng thi không bị trùng ca.
6. Hệ thống kiểm tra sinh viên không bị trùng lịch thi với môn khác.
7. Lịch thi được lưu và hiển thị cho các vai trò liên quan.

### Kết quả

Lịch thi được lập hợp lệ, hạn chế trùng phòng và trùng lịch thi của sinh viên.

## 6. Quy trình nhập điểm và khóa điểm

### Mục đích

Quản lý việc nhập điểm đúng thời gian, đúng lớp được phân công và có cơ chế khóa điểm sau khi hoàn tất.

### Tác nhân tham gia

- Giảng viên.
- Phòng Đào tạo.
- Sinh viên.
- Khoa/Viện.

### Luồng xử lý

1. Môn học kết thúc.
2. Phòng Đào tạo cấu hình thời hạn nhập điểm.
3. Giảng viên xem lớp được phân công và nhập điểm trong thời gian cho phép.
4. Hệ thống kiểm tra cửa sổ nhập điểm và trạng thái khóa điểm.
5. Phòng Đào tạo theo dõi lớp thiếu điểm, nhắc nhập điểm nếu cần.
6. Khi hoàn tất, Phòng Đào tạo khóa điểm.
7. Sinh viên xem kết quả học tập.
8. Khoa/Viện theo dõi thống kê điểm và cảnh báo học vụ.

### Kết quả

Điểm được ghi nhận, khóa và hiển thị cho sinh viên theo đúng quy trình quản lý đào tạo.
