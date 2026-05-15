# Checklist nghiệm thu luồng nghiệp vụ đào tạo

## Quy ước chung

- **System/Thật**: chạy đúng ràng buộc khóa, lớp hành chính, học kỳ, CTĐT.
- **Test/Demo**: dùng để thử nhanh, nhưng các luồng chính vẫn nên giống thật trừ chỗ được nới test.
- **CTĐT hiện tại**:
  - Chỉ dùng học kỳ 1 và 2.
  - Dòng CTĐT Học kỳ 3 coi như bỏ qua.
  - Vẫn giữ slot 3 trong thứ tự, nên HK1 năm 2 = kỳ CTĐT 4.

## 1. Tuyển Sinh → Lớp Hành Chính

### Các bước kiểm tra

- Tạo đợt tuyển sinh.
- Nhập/xét tuyển thí sinh.
- Chuyển thí sinh trúng tuyển sang sinh viên.
- Phòng đào tạo mở lớp hành chính từ đợt tuyển sinh.
- Kiểm tra lớp có đúng ngành, khóa, chế độ dữ liệu.

### Đạt khi

- Sinh viên có tài khoản.
- Sinh viên có `class_id`, `cohort_id`, `training_program_id`.
- Lớp hành chính xem được danh sách sinh viên.
- Không cho xóa lớp đã có sinh viên.

## 2. CTĐT Và Môn Chung

### Các bước kiểm tra

- Chọn ngành + khóa.
- Import CTĐT riêng cho khóa.
- Kiểm tra các môn Học kỳ 3 không còn xuất hiện.
- Kiểm tra HK1 năm 2 hiển thị là kỳ CTĐT 4.
- Lọc môn chung.
- Import danh sách môn chung.
- Kiểm tra badge môn chung/chuyên ngành.

### Đạt khi

- CTĐT đúng khóa, không mặc định sai về 2022-2023 khi đang xem khóa khác.
- Môn chung chỉ hiện trong phạm vi lọc môn chung.
- CTĐT có nút import riêng theo khóa.

## 3. Học Kỳ Và Cửa Sổ Nghiệp Vụ

### Các bước kiểm tra

- Tạo học kỳ.
- Thiết lập:
  - thời gian khoa đề xuất,
  - thời gian phòng đào tạo duyệt,
  - thời gian sinh viên đăng ký,
  - thời gian nhập điểm.
- Test học kỳ đang mở.
- Test học kỳ đã qua hoặc chưa mở.

### Đạt khi

- Ngoài thời gian thì không thao tác được.
- Học kỳ thật bị chặn nếu đã qua/chưa mở.
- Test/Demo có thể mở full khi cần kiểm thử.

## 4. Phòng Đào Tạo Mở Lớp Học Phần Từ CTĐT

### Các bước kiểm tra

- Vào Quản lý lớp học phần.
- Chọn học kỳ.
- Chọn khóa/ngành/lớp hành chính.
- Bấm mở lớp từ CTĐT.
- Kiểm tra lớp học phần sinh ra đúng môn CTĐT theo kỳ hiện tại.
- Kiểm tra không tạo trùng môn/lớp/học kỳ.
- Dùng bộ lọc:
  - học kỳ,
  - khóa,
  - khoa,
  - ngành,
  - lớp hành chính,
  - trạng thái,
  - loại lớp,
  - tình trạng thiếu lịch/phòng/GV.

### Đạt khi

- K25 + HK1 2026-2027 lấy kỳ CTĐT 4.
- Nếu chưa mở lớp HP cho K25 thì sinh viên K25 không thấy môn.
- Nếu mở lớp HP cho K25 thì sinh viên K25 thấy đúng.
- Lớp có thể xem chi tiết danh sách sinh viên, xuất Excel/CSV.

## 5. Mở Lớp Học Phần Chung

### Các bước kiểm tra

- Chọn học kỳ.
- Mở màn “Lớp HP chung”.
- Chọn ngành hoặc tất cả ngành có môn chung.
- Kiểm tra môn đủ điều kiện.
- Tick từng môn hoặc chọn tất cả.
- Mở lớp còn thiếu.
- Kiểm tra lớp chung không bị trùng với lớp đã mở.

### Đạt khi

- Học kỳ thật chỉ thao tác nếu kỳ đang mở hợp lệ.
- Test/Demo có thể bung môn chung để kiểm thử.
- Có checkbox chọn từng môn.
- Không mở trùng lớp chung cùng môn/học kỳ.

## 6. Khoa/Viện Đề Xuất Mở Lớp

### Các bước kiểm tra

- Khoa chọn học kỳ.
- Mở modal “Lập đề xuất mở lớp theo kế hoạch đào tạo”.
- Chọn khóa/ngành/lớp.
- Kiểm tra danh sách môn được gợi ý.
- Chọn môn cần đề xuất.
- Chỉnh:
  - số lớp,
  - sĩ số/lớp,
  - lịch đề xuất,
  - phòng đề xuất,
  - giảng viên đề xuất.
- Gửi hàng loạt.

### Đạt khi

- Danh sách môn theo đúng CTĐT và học kỳ của khóa.
- Môn đã đủ lớp phải báo “Đã có đủ lớp”.
- Nếu tăng số lớp lớn hơn số đã có thì chỉ mở thêm phần thiếu.
- Không gửi được nếu trùng lịch/phòng/GV hoặc hết cửa sổ đề xuất.

## 7. Duyệt Đề Xuất Mở Lớp

### Các bước kiểm tra

- Phòng đào tạo vào duyệt đề xuất.
- Xem đề xuất từ khoa.
- Duyệt hoặc từ chối.
- Khi duyệt, hệ thống kiểm tra:
  - đúng CTĐT,
  - đúng học kỳ,
  - không trùng lịch,
  - phòng đủ sức chứa,
  - giảng viên hợp lệ nếu có.

### Đạt khi

- Duyệt thành công chuyển lớp sang open.
- Từ chối có lý do.
- Lớp đã duyệt hiện ở Quản lý lớp học phần.

## 8. Xếp Lịch/Phòng

### Các bước kiểm tra

- Vào Quản lý lớp học phần.
- Lọc lớp thiếu lịch/phòng.
- Bấm xếp lịch/phòng.
- Kiểm tra các lớp được gán:
  - `day_sessions`,
  - `start_date`,
  - `end_date`,
  - phòng.
- Tạo tình huống trùng phòng/trùng GV/trùng lớp hành chính.

### Đạt khi

- Lớp thiếu lịch/phòng được xếp.
- Lớp đã có lịch/phòng hợp lệ không bị ghi đè vô lý.
- Cùng phòng cùng ca chỉ được phép nếu khoảng ngày không giao nhau.
- Trùng khoảng ngày thì bị chặn.

## 9. Nguyện Vọng Và Phân Công Giảng Viên

### Các bước kiểm tra

- Giảng viên đăng ký nguyện vọng dạy.
- Bộ môn/Khoa duyệt nguyện vọng.
- Khoa đề xuất GV cho lớp HP.
- Phòng đào tạo duyệt phân công.
- Giảng viên đăng nhập kiểm tra lớp được phân công.

### Đạt khi

- GV không phù hợp hoặc chưa có nguyện vọng bị cảnh báo/chặn tùy rule.
- GV bị trùng lịch không được phân công.
- Sau duyệt, GV thấy lớp và lịch dạy.

## 10. Sinh Viên Đăng Ký Học Phần

### Các bước kiểm tra

- Sinh viên đăng nhập.
- Vào Đăng ký học phần.
- Kiểm tra bộ lọc mặc định “Lớp của tôi”.
- Kiểm tra bảng có:
  - Mã HP,
  - Mã môn,
  - tên môn,
  - nhóm/tổ,
  - lịch,
  - phòng,
  - sĩ số.
- Đăng ký môn còn chỗ.
- Thử đăng ký trùng môn.
- Thử đăng ký trùng lịch.
- Thử đăng ký môn đã đạt.
- Thử hủy đăng ký trong thời gian mở.

### Đạt khi

- Chỉ hiện lớp HP phù hợp trong hệ thống thật.
- Nếu một môn có lớp riêng cho lớp hành chính thì mặc định ưu tiên lớp đó, không hiện lớp mở chung trùng môn.
- “Tất cả môn mở” dùng để kiểm thử rộng hơn.
- Đăng ký tăng sĩ số, hủy giảm sĩ số.
- Trùng lịch/trùng môn/hết chỗ bị chặn.

## 11. HK1 Năm Nhất Đăng Ký Tự Động

### Các bước kiểm tra

- Có sinh viên năm nhất.
- Có lớp HP kỳ CTĐT 1.
- Chạy/duyệt đăng ký tự động.
- Sinh viên đăng nhập kiểm tra học phần đã được xếp.
- Sinh viên HK1 năm nhất thử tự đăng ký/hủy.

### Đạt khi

- HK1 năm nhất được xếp tự động.
- Sinh viên không tự đăng ký/hủy như các kỳ sau.
- Nếu thiếu lớp hoặc trùng lịch thì đưa vào hàng chờ/xử lý.

## 12. Học Phí

### Các bước kiểm tra

- Sinh viên đăng ký môn.
- Vào học phí.
- Kiểm tra học phí phát sinh theo môn đã đăng ký.
- Tạo trạng thái nợ.
- Thử đăng ký học phần khi nợ.

### Đạt khi

- Học phí tính theo danh sách đăng ký.
- Nếu cấu hình chặn nợ, sinh viên nợ không đăng ký được.

## 13. Thời Khóa Biểu

### Các bước kiểm tra

- Sinh viên xem TKB.
- Giảng viên xem lịch dạy.
- Phòng đào tạo xem TKB tổng.
- Kiểm tra lớp có ngày bắt đầu/kết thúc khác nhau.

### Đạt khi

- Lịch hiển thị đúng `day_sessions`, `start_date`, `end_date`.
- Không hiển thị lớp ngoài khoảng học.
- Sinh viên/GV chỉ thấy lịch liên quan.

## 14. Lịch Thi

### Các bước kiểm tra

- Phòng đào tạo tạo lịch thi cho lớp HP.
- Chọn phòng thi, ngày, giờ.
- Tạo tình huống trùng phòng.
- Tạo tình huống sinh viên trùng ca thi.
- Sinh viên/GV xem lịch thi.

### Đạt khi

- Giờ kết thúc sau giờ bắt đầu.
- Phòng thi không trùng.
- Sinh viên không bị trùng lịch thi.
- Lịch thi hiện đúng theo vai trò.

## 15. Nhập Điểm Và Khóa Điểm

### Các bước kiểm tra

- GV vào lớp được phân công.
- Nhập điểm.
- Lưu điểm.
- Phòng đào tạo xem lớp thiếu điểm.
- Khóa điểm.
- GV thử sửa sau khi khóa.
- Sinh viên xem kết quả.

### Đạt khi

- GV chỉ nhập lớp của mình.
- Ngoài thời gian nhập điểm bị chặn.
- Khóa điểm xong không sửa được.
- Sinh viên thấy điểm sau khi có dữ liệu.

## 16. Báo Cáo Và Dashboard

### Các bước kiểm tra

- Phòng đào tạo xem dashboard.
- Khoa xem dashboard.
- Kiểm tra cảnh báo:
  - lớp thiếu GV,
  - thiếu lịch,
  - thiếu phòng,
  - lớp chưa đủ sĩ số,
  - thiếu điểm.
- Xuất danh sách sinh viên/lớp nếu có.

### Đạt khi

- Số liệu theo đúng học kỳ/chế độ.
- Không lẫn Test/Demo với dữ liệu thật.
- Export mở được bằng Excel/CSV.

## Checklist đánh giá nhanh

| Tiêu chí | Kết quả |
| --- | --- |
| Đúng vai trò | Pass/Fail |
| Đúng học kỳ/cửa sổ thời gian | Pass/Fail |
| Đúng CTĐT/kỳ CTĐT | Pass/Fail |
| Không tạo trùng lớp/môn | Pass/Fail |
| Không trùng lịch/phòng/GV/SV | Pass/Fail |
| Đăng ký/hủy cập nhật sĩ số | Pass/Fail |
| Test/Demo không làm bẩn dữ liệu thật | Pass/Fail |
| Export/báo cáo đúng dữ liệu đang lọc | Pass/Fail |
