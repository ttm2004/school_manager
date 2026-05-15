# Hướng dẫn vị trí xử lý các workflow trong hệ thống

Tài liệu này dùng để tra nhanh: một nghiệp vụ đang đi qua màn hình nào, file PHP nào xử lý POST/GET, service nào chứa luật nghiệp vụ và trạng thái dữ liệu chính nằm ở đâu.

## 1. Quy ước đọc workflow

| Thành phần | Ý nghĩa |
| --- | --- |
| File màn hình | File người dùng mở trên trình duyệt, thường vừa render UI vừa nhận POST. |
| Action | Giá trị `$_POST['action']` hoặc tham số `action` dùng để rẽ nhánh xử lý. |
| Service/Helper | File chứa luật dùng chung, kiểm tra điều kiện hoặc xử lý nghiệp vụ phức tạp. |
| Bảng chính | Bảng dữ liệu bị thay đổi trực tiếp trong workflow. |

Các file service quan trọng:

| File | Vai trò |
| --- | --- |
| `university/includes/AcademicPolicy.php` | Luật học kỳ, CTĐT, cửa sổ đề xuất/duyệt, kiểm tra trùng lịch, dữ liệu test/system. |
| `university/app/Services/RoomSchedulingService.php` | Kiểm tra lịch học, phòng học, sức chứa, tự chọn phòng/lịch khi mở lớp. |
| `university/app/Services/TeacherAssignmentService.php` | Kiểm tra giảng viên có phù hợp môn/lớp trước khi phân công. |
| `university/app/Services/StudentRegistrationService.php` | Kiểm tra đăng ký học phần, trùng lịch, giới hạn tín chỉ. |
| `university/app/Services/AdmissionsEnrollmentService.php` | Tạo hồ sơ sinh viên từ tuyển sinh, tạo yêu cầu đăng ký tự động HK1, xử lý hàng chờ. |
| `university/app/Services/ExamScheduleService.php` | Kiểm tra lịch thi, phòng thi, thời gian thi. |
| `university/app/Services/GradeWindowService.php` | Kiểm tra thời gian nhập điểm của giảng viên. |

## 2. Học kỳ và các mốc thời gian

| Workflow | File xử lý | Action chính | Bảng chính | Ghi chú |
| --- | --- | --- | --- | --- |
| Tạo học kỳ | `university/academic/semesters.php` | `add` | `semesters` | Tạo học kỳ, năm học, ngày bắt đầu/kết thúc, thời gian đề xuất, duyệt, đăng ký. |
| Sửa học kỳ | `university/academic/semesters.php` | `edit` | `semesters`, `course_sections`, `final_exam_schedules`, `evaluation_periods`, `tuition_periods` | Có hàm chuẩn hóa ngày liên quan để không lệch khỏi học kỳ. |
| Mở đăng ký học phần | `university/academic/semesters.php` | `open_registration` | `semesters`, `system_notifications` | Đặt `register_start`, `register_end`, status `open`, gửi thông báo sinh viên. |
| Đóng đăng ký học phần | `university/academic/semesters.php` | `close_registration` | `semesters` | Đổi học kỳ sang `active` và đóng cửa sổ đăng ký. |

## 3. Chương trình đào tạo, môn học, lớp hành chính

| Workflow | File xử lý | Vị trí xử lý | Bảng chính | Ghi chú |
| --- | --- | --- | --- | --- |
| Import/cập nhật CTĐT | `university/academic/curriculum.php` | POST import CSV/Excel | `subjects`, `curriculum`, `training_programs` | Tạo/cập nhật môn, số tín chỉ, học kỳ gợi ý, loại môn. |
| Quản lý môn học | `university/academic/subjects.php` | CRUD trong file | `subjects` | Dữ liệu môn dùng cho CTĐT và mở lớp. |
| Quản lý lớp hành chính | `university/academic/classes.php` | CRUD trong file | `classes`, `students` | Lớp hành chính dùng để mở lớp học phần theo khóa/ngành. |
| Quản lý phòng học | `university/academic/rooms.php` | CRUD trong file | `classrooms` | Phòng được dùng khi xếp lịch/mở lớp. |

## 4. Mở lớp học phần từ Phòng Đào tạo

| Workflow | File xử lý | Action chính | Bảng chính | Service/Helper |
| --- | --- | --- | --- | --- |
| Tạo lớp học phần từ CTĐT | `university/academic/course_sections.php` | `import_curriculum_sections` | `course_sections` | `AcademicPolicy.php` |
| Tự xếp lịch/phòng hàng loạt | `university/academic/course_sections.php` | `auto_schedule_sections` | `course_sections`, `course_section_schedule_changes` | `RoomSchedulingService.php`, `AcademicPolicy.php` |
| Thay đổi lịch/phòng | `university/academic/course_sections.php` | `schedule_change` | `course_sections`, `course_section_schedule_changes` | `RoomSchedulingService.php` |
| Thêm lớp học phần thủ công | `university/academic/course_sections.php` | `add` | `course_sections` | `RoomSchedulingService.php` |
| Sửa lớp học phần | `university/academic/course_sections.php` | `edit` | `course_sections` | Kiểm tra lịch/phòng/sĩ số trước khi lưu. |
| Xóa lớp học phần | `university/academic/course_sections.php` | `delete` | `course_sections` | Chỉ nên xóa khi chưa phát sinh đăng ký/phụ thuộc. |

Trạng thái thường gặp của `course_sections.status`:

| Trạng thái | Ý nghĩa |
| --- | --- |
| `draft` | Khoa/Phòng tạo nháp, chưa gửi duyệt. |
| `proposed` | Đề xuất mở lớp đang chờ Phòng Đào tạo duyệt. |
| `open` | Lớp đã mở, sinh viên có thể đăng ký nếu trong thời gian cho phép. |
| `full` | Lớp đã đủ sĩ số. |
| `cancelled` | Lớp/đề xuất bị hủy hoặc từ chối. |

## 5. Khoa/Viện đề xuất mở lớp và đề xuất giảng viên

| Workflow | File xử lý | Action chính | Bảng chính | Ghi chú |
| --- | --- | --- | --- | --- |
| Tạo nháp đề xuất mở lớp | `university/faculty/proposals.php` | `create_draft` | `course_sections` | Tạo một đề xuất nháp theo môn, học kỳ, khóa/ngành. |
| Tạo đề xuất hàng loạt từ CTĐT | `university/faculty/proposals.php` | `batch_create_drafts` | `course_sections` | Dùng bộ lọc khóa/ngành, tránh tạo sai khóa và kiểm tra trùng. |
| Gửi đề xuất mở lớp | `university/faculty/proposals.php` | `submit` | `course_sections` | Đổi `draft` sang `proposed`, ghi người gửi và thời gian gửi. |
| Hủy đề xuất mở lớp | `university/faculty/proposals.php` | `cancel` | `course_sections` | Đổi sang `cancelled` nếu còn quyền hủy. |
| Xóa đề xuất test | `university/faculty/proposals.php` | `delete_test_open_proposals` | `course_sections` | Chỉ dùng với học kỳ `data_mode='test'`. |
| Gửi lại sau khi chỉnh sửa | `university/faculty/proposals.php` | `resubmit_open_revision` | `course_sections` | Dùng khi đề xuất bị trả/từ chối cần gửi lại. |
| Đề xuất giảng viên | `university/faculty/proposals.php` | `propose_teacher` | `course_sections` | Ghi `proposed_teacher_id`, `proposal_status='pending'`. |
| Hủy đề xuất giảng viên | `university/faculty/proposals.php` | `cancel_teacher_proposal` | `course_sections` | Xóa `proposed_teacher_id`, `proposal_status`, `proposal_note`. |

Các hàm hỗ trợ nội bộ trong `faculty/proposals.php`:

| Hàm | Vai trò |
| --- | --- |
| `facultyProposalWindowOrRedirect()` | Chặn gửi đề xuất ngoài thời gian cho phép. |
| `facultyProposalRecommendedScheduleAndRoom()` | Gợi ý lịch/phòng khi Khoa tạo đề xuất. |
| `facultyProposalRoomOptions()` | Danh sách phòng khả dụng cho lịch đang chọn. |
| `facultyProposalEligibleTeachers()` | Danh sách giảng viên phù hợp môn/học kỳ/khoa. |
| `facultyProposalExistingOpeningSummary()` | Cảnh báo môn đã có lớp/đề xuất trong học kỳ. |

## 6. Phòng Đào tạo duyệt đề xuất từ Khoa

| Workflow | File xử lý | Action chính | Bảng chính | Service/Helper |
| --- | --- | --- | --- | --- |
| Duyệt một đề xuất mở lớp | `university/academic/proposals.php` | `approve_open` | `course_sections`, `system_notifications` | `AcademicPolicy.php`, `RoomSchedulingService.php` |
| Từ chối một đề xuất mở lớp | `university/academic/proposals.php` | `reject_open` | `course_sections`, `system_notifications` | Ghi `open_reject_reason`, `open_reviewed_by`, `open_reviewed_at`. |
| Duyệt hàng loạt đề xuất mở lớp | `university/academic/proposals.php` | `bulk_approve_open` | `course_sections` | Từng dòng vẫn kiểm tra lịch/phòng trước khi mở. |
| Từ chối hàng loạt đề xuất mở lớp | `university/academic/proposals.php` | `bulk_reject_open` | `course_sections` | Ghi chung lý do từ chối cho các dòng được chọn. |

Điểm cần nhớ: file này là nơi chốt trạng thái từ `proposed` sang `open` hoặc `cancelled`. Nếu lỗi nghiệp vụ duyệt mở lớp, kiểm tra trước ở `academic/proposals.php`, sau đó đến `RoomSchedulingService.php` và `AcademicPolicy.php`.

## 7. Phân công giảng viên

| Workflow | File xử lý | Action chính | Bảng chính | Service/Helper |
| --- | --- | --- | --- | --- |
| Phòng Đào tạo phân công trực tiếp | `university/academic/teacher_assignments.php` | `assign` | `course_sections` | `TeacherAssignmentService.php` |
| Duyệt giảng viên Khoa đề xuất | `university/academic/teacher_assignments.php` | `approve_assignment` | `course_sections`, `system_notifications` | `TeacherAssignmentService.php` |
| Từ chối giảng viên Khoa đề xuất | `university/academic/teacher_assignments.php` | `reject_assignment` | `course_sections` | Ghi `proposal_status='rejected'`, `proposal_note`. |
| Gỡ phân công | `university/academic/teacher_assignments.php` | `unassign` | `course_sections` | Đặt `teacher_id=NULL`. |
| Giảng viên xem lớp dạy | `university/teacher/index.php` | GET | `course_sections` | Hiển thị theo `teacher_id`. |
| Giảng viên xem thời khóa biểu | `university/teacher/timetable.php` | GET | `course_sections` | Dựa vào `day_sessions`, `start_date`, `end_date`. |

Trạng thái đề xuất giảng viên nằm ở `course_sections.proposal_status`:

| Trạng thái | Ý nghĩa |
| --- | --- |
| `pending` | Khoa đã gửi đề xuất, chờ Phòng Đào tạo duyệt. |
| `approved` | Phòng Đào tạo đã duyệt, `teacher_id` được cập nhật. |
| `rejected` | Phòng Đào tạo từ chối đề xuất. |
| `NULL` | Chưa có đề xuất hoặc đã hủy đề xuất. |

## 8. Đăng ký học phần

| Workflow | File xử lý | Action chính | Bảng chính | Service/Helper |
| --- | --- | --- | --- | --- |
| Sinh viên đăng ký học phần | `university/student/register_subject.php` | `register` | `student_subjects`, `course_sections` | `StudentRegistrationService.php` |
| Sinh viên hủy học phần | `university/student/register_subject.php` | `cancel` | `student_subjects`, `course_sections` | Kiểm tra cửa sổ đăng ký trước khi hủy. |
| Xem học phần đã đăng ký | `university/student/my_subjects.php` | GET | `student_subjects`, `course_sections` | Chỉ đọc dữ liệu. |
| Xem thời khóa biểu sinh viên | `university/student/timetable.php`, `university/student/semester_timetable.php` | GET | `student_subjects`, `course_sections` | Dựa vào lớp đã đăng ký. |
| Xử lý hàng chờ đăng ký tự động | `university/academic/pending_enrollments.php` | `retry_pending_enrollment` | `pending_enrollments`, `student_subjects`, `course_sections` | `AdmissionsEnrollmentService.php` |
| Duyệt yêu cầu đăng ký tự động HK1 | `university/academic/pending_enrollments.php` | `approve_auto_enroll`, `approve_auto_enroll_bulk` | `admission_auto_enrollment_requests`, `student_subjects` | `AdmissionsEnrollmentService.php` |
| Từ chối yêu cầu đăng ký tự động HK1 | `university/academic/pending_enrollments.php` | `reject_auto_enroll` | `admission_auto_enrollment_requests` | Ghi người duyệt và ghi chú. |

Các kiểm tra quan trọng khi sinh viên đăng ký nằm ở `student/register_subject.php` và `StudentRegistrationService.php`:

- Học kỳ còn trong thời gian đăng ký.
- Lớp học phần đang `open` và còn chỗ.
- Không đăng ký trùng môn trong cùng học kỳ.
- Không trùng lịch với học phần đã đăng ký.
- Không vượt giới hạn tín chỉ.
- Học kỳ 1 năm nhất có thể bị khóa đăng ký thủ công để đi theo luồng tự động.

## 9. Tuyển sinh, phân lớp và nhập học

| Workflow | File xử lý | Action/API chính | Bảng chính | Ghi chú |
| --- | --- | --- | --- | --- |
| Cấu hình đợt tuyển sinh | `university/admissions/rounds.php` | Form tạo/sửa | `adm_rounds` | Tách dữ liệu thật/test qua `data_mode`. |
| Nhập hoặc duyệt hồ sơ tự động | `university/admissions/auto_review.php` | `import_csv`, `clear_import`, `bulk_approve`, `bulk_reject` | `adm_registrations`, `adm_logs` | Dùng cho xét tuyển hàng loạt. |
| Duyệt/từ chối một hồ sơ | `university/admissions/api/process_registration.php` | POST API | `adm_registrations`, `adm_logs` | Đổi trạng thái hồ sơ sang approved/rejected. |
| Xem danh sách hồ sơ | `university/admissions/applications.php` | AJAX gọi API | `adm_registrations` | Giao diện quản lý hồ sơ. |
| Xem kết quả tuyển sinh | `university/admissions/results.php` | GET | `adm_registrations` | Lọc theo ngành, đợt, trạng thái, dữ liệu test/system. |
| Xác nhận nhập học | `university/admissions/api/confirm_enrollment.php` | POST API | `adm_confirmations`, `adm_quota`, `adm_logs` | Ghi xác nhận nhập học của thí sinh. |
| Phân lớp tự động | `university/admissions/auto_assign.php` | `preview`, `execute` | `users`, `students`, `classes` | Tạo tài khoản sinh viên, hồ sơ sinh viên, gán lớp. |
| Quản lý nhập học | `university/admissions/enrollment.php` | AJAX tới `enrollment_api.php` | `adm_registrations`, `students`, `classes` | Điều phối các thao tác nhập học từ giao diện. |
| API nhập học | `university/admissions/enrollment_api.php` | POST API | `adm_registrations`, `students`, `classes` | Nơi xử lý các thao tác AJAX của trang nhập học. |

Luồng đặc biệt sau khi phân lớp:

1. `auto_assign.php` tạo user/student/class assignment.
2. `AdmissionsEnrollmentService::createStudentProfile()` tạo hồ sơ sinh viên.
3. `AdmissionsEnrollmentService::createAutoEnrollmentRequest()` tạo yêu cầu đăng ký tự động HK1 nếu bật.
4. Phòng Đào tạo vào `academic/pending_enrollments.php` để duyệt yêu cầu.
5. `AdmissionsEnrollmentService::autoEnrollFirstSemester()` ghi danh các môn HK1 phù hợp.

## 10. Lịch thi

| Workflow | File xử lý | Action chính | Bảng chính | Service |
| --- | --- | --- | --- | --- |
| Tạo lịch thi | `university/academic/exam_schedules.php` | `add` | `final_exam_schedules` | `ExamScheduleService.php` |
| Sửa lịch thi | `university/academic/exam_schedules.php` | `edit` | `final_exam_schedules` | Kiểm tra trùng phòng, trùng thời gian. |
| Xóa lịch thi | `university/academic/exam_schedules.php` | `delete` | `final_exam_schedules` | Xóa lịch thi đã lập. |
| Sinh viên xem lịch thi | `university/student/exam_schedule.php` | GET | `student_subjects`, `final_exam_schedules` | Chỉ hiện lịch của các môn đã đăng ký. |
| Giảng viên xem lịch thi | `university/teacher/exam_schedule.php` | GET | `course_sections`, `final_exam_schedules` | Chỉ hiện lớp giảng viên phụ trách. |

## 11. Nhập điểm, khóa điểm, nhắc nhập điểm

| Workflow | File xử lý | Action chính | Bảng chính | Service/Helper |
| --- | --- | --- | --- | --- |
| Giảng viên nhập điểm | `university/teacher/grades.php` | `save_grade` | `grades` | `GradeWindowService.php` |
| Phòng Đào tạo sửa/nhập điểm | `university/academic/grades.php` | `save_grade` | `grades` | Chỉ role quản lý được sửa. |
| Khóa điểm lớp học phần | `university/academic/grade_locks.php` | `lock` | `course_sections` hoặc bảng khóa điểm liên quan | Chốt không cho sửa tự do. |
| Mở khóa điểm | `university/academic/grade_locks.php` | `unlock` | Bảng khóa điểm liên quan | Cho phép chỉnh lại khi cần. |
| Nhắc nhập điểm một GV | `university/academic/grade_reminder.php` | `remind_one` | `system_notifications` | Gửi thông báo cho giảng viên. |
| Nhắc nhập điểm hàng loạt | `university/academic/grade_reminder.php` | `remind_all` | `system_notifications` | Gửi cho các lớp/GV còn thiếu điểm. |
| Sinh viên xem điểm | `university/student/grades.php` | GET | `student_subjects`, `grades` | Chỉ đọc kết quả học tập. |

## 12. Học phí

| Workflow | File xử lý | Action chính | Bảng chính | Ghi chú |
| --- | --- | --- | --- | --- |
| Tạo đợt thu học phí | `university/finance/periods.php` | `create_period` | `tuition_periods`, `tuition_invoices` | Có thể sinh hóa đơn từ đăng ký hiện tại. |
| Sửa đợt thu | `university/finance/periods.php` | `update_period` | `tuition_periods` | Chỉ sửa khi còn `draft`. |
| Tái tạo hóa đơn | `university/finance/periods.php` | `regenerate` | `tuition_invoices` | Tính lại từ `student_subjects`. |
| Công bố đợt thu | `university/finance/periods.php` | `publish` | `tuition_periods`, `tuition_invoices` | Hóa đơn chuyển từ `draft` sang `unpaid`. |
| Đóng đợt thu | `university/finance/periods.php` | `close` | `tuition_periods`, `tuition_invoices` | Hóa đơn chưa thanh toán có thể thành `overdue`. |
| Ghi nhận thanh toán | `university/finance/invoices.php` | `record_payment` | `tuition_payments`, `tuition_invoices` | Cập nhật `paid_amount`, trạng thái hóa đơn. |
| Cập nhật miễn giảm | `university/finance/invoices.php` | `update_discount` | `tuition_invoices` | Điều chỉnh `discount`, `net_amount`. |
| Xem lịch sử thanh toán | `university/finance/payments.php` | GET | `tuition_payments`, `tuition_invoices` | Lọc theo sinh viên, mã giao dịch, hình thức thanh toán. |
| Báo cáo kế toán | `university/finance/reports.php` | GET | `tuition_periods`, `tuition_invoices`, `tuition_payments` | Tổng hợp phải thu, đã thu, còn nợ theo đợt thu/khoa/ngành/hình thức thanh toán. |
| Sinh viên xem học phí | `university/student/tuition.php` | GET | `tuition_invoices`, `tuition_payments` | Chỉ đọc hóa đơn của sinh viên. |

Luồng kế toán hiện tại:

1. Phòng Đào tạo mở lớp và sinh viên đăng ký học phần.
2. Kế toán tạo đợt thu ở `finance/periods.php`.
3. Hệ thống sinh hóa đơn nháp từ `student_subjects`, `course_sections`, `subjects`, `students`, `classes`, `majors`.
4. Kế toán kiểm tra, có thể tái tạo hóa đơn nếu dữ liệu đăng ký thay đổi.
5. Kế toán công bố đợt thu, hóa đơn chuyển từ `draft` sang `unpaid`.
6. Sinh viên xem hóa đơn ở `student/tuition.php`.
7. Kế toán ghi nhận thanh toán hoặc miễn giảm ở `finance/invoices.php`.
8. Sau khi sinh viên đăng ký hoặc hủy học phần, hệ thống tự đồng bộ lại hóa đơn của sinh viên trong học kỳ nếu đợt thu đã tồn tại.
9. Khi quá hạn đóng học phí, hệ thống tự đánh dấu hóa đơn chưa đóng đủ thành `overdue`.
10. Sinh viên còn công nợ quá hạn bị khóa các chức năng đăng ký môn, xem thời khóa biểu, xem lịch thi và xem điểm; sinh viên vẫn vào được trang học phí để xem/hoàn tất thanh toán.
11. Kế toán lọc hóa đơn theo đợt thu, trạng thái, khóa, khoa/viện, ngành, lớp ở `finance/invoices.php`.
12. Trưởng/nhân viên quản lý xem báo cáo tổng hợp ở `finance/reports.php`.

## 13. Dữ liệu test và xóa dữ liệu test

| Workflow | File xử lý | Action/chức năng | Bảng chính | Ghi chú |
| --- | --- | --- | --- | --- |
| Học kỳ test | `university/academic/semesters.php` | `data_mode='test'`, `demo_batch_id` | `semesters` | Dùng để tách dữ liệu demo/test khỏi dữ liệu thật. |
| Đề xuất mở lớp test | `university/faculty/proposals.php` | `delete_test_open_proposals` | `course_sections` | Cho chọn nhiều đề xuất test và xóa/hủy theo học kỳ test. |
| Xóa dữ liệu học kỳ demo | `university/academic/clear_demo_semester.php` | Form xóa demo | Nhiều bảng theo học kỳ | Dùng để dọn dữ liệu test theo học kỳ. |
| Tuyển sinh test | `university/admissions/*` | Bộ lọc `mode=test` | `adm_*`, `students`, `users`, `classes` | Nhiều màn hình tuyển sinh có filter test/system. |
| Xóa test từ Tuyển sinh | `university/admissions/applications.php`, `university/admissions/api/actions.php` | `clear_test_data` | `admission_applications`, `students`, `users`, `student_subjects`, `tuition_*`, `grades`, `pending_enrollments` | Nếu không chọn học kỳ: xóa toàn bộ test tuyển sinh. Nếu chọn học kỳ test: chỉ xóa dữ liệu nghiệp vụ phát sinh trong học kỳ đó. |

Khi dữ liệu test "xóa rồi vẫn còn", cần kiểm tra theo thứ tự:

1. Có đang lọc đúng `data_mode='test'` và đúng `demo_batch_id` không.
2. Dữ liệu còn lại nằm ở bảng nào: `course_sections`, `student_subjects`, `pending_enrollments`, `admission_auto_enrollment_requests`, `adm_registrations`, `students`, `users`.
3. File xóa hiện tại có xóa đúng workflow đó không. Ví dụ nút xóa đề xuất test ở `faculty/proposals.php` chỉ xử lý đề xuất mở lớp, không tự xóa hồ sơ tuyển sinh hoặc sinh viên đã tạo.
4. Ở module Tuyển sinh, dropdown cạnh nút `Xóa test` cho phép chọn học kỳ test. Chọn học kỳ thì chỉ dọn đăng ký môn/học phí/điểm/hàng chờ phát sinh trong học kỳ đó; chọn `Xóa toàn bộ test tuyển sinh` thì xóa cả hồ sơ test, tài khoản test và sinh viên test.

## 14. Trang xem/báo cáo, chủ yếu chỉ đọc

| Màn hình | File | Dữ liệu đọc chính |
| --- | --- | --- |
| Dashboard Phòng Đào tạo | `university/academic/index.php` | Học kỳ, lớp học phần, đề xuất, phân công, đăng ký. |
| Thời khóa biểu Phòng Đào tạo | `university/academic/timetable.php` | `course_sections`. |
| Báo cáo Phòng Đào tạo | `university/academic/reports.php` | Tổng hợp nhiều bảng. |
| Dashboard Khoa/Viện | `university/faculty/index.php` | Sinh viên, GV, lớp, đề xuất. |
| Cảnh báo học vụ Khoa | `university/faculty/academic_warnings.php` | Điểm, tín chỉ, sinh viên. |
| KPI giảng viên | `university/faculty/teacher_kpi.php` | Lớp dạy, điểm, đánh giá. |
| Dashboard Sinh viên | `university/student/index.php` | Đăng ký, lịch, học phí, điểm. |
| Dashboard Giảng viên | `university/teacher/index.php` | Lớp dạy, lịch, điểm cần nhập. |

## 15. Cách lần lỗi nhanh theo nghiệp vụ

### Lỗi đề xuất mở lớp sai khóa/ngành

1. Kiểm tra UI và POST tại `university/faculty/proposals.php`.
2. Tìm action `batch_create_drafts`, `create_draft`, `submit`.
3. Kiểm tra các cột `target_cohort_id`, `class_id`, `semester_id`, `data_mode` trong `course_sections`.
4. Kiểm tra luật CTĐT tại `AcademicPolicy.php`, đặc biệt các hàm tìm môn đủ điều kiện mở.

### Lỗi duyệt mở lớp, trùng lịch/phòng

1. Kiểm tra `university/academic/proposals.php`, action `approve_open` hoặc `bulk_approve_open`.
2. Kiểm tra `RoomSchedulingService::planSectionOpening()` và `RoomSchedulingService::validateRoom()`.
3. Kiểm tra `AcademicPolicy::academicPolicyHasScheduleConflict()` và các hàm tính khoảng ngày học.

### Lỗi phân công giảng viên

1. Nếu Khoa gửi đề xuất: `university/faculty/proposals.php`, action `propose_teacher`.
2. Nếu Phòng Đào tạo duyệt/từ chối: `university/academic/teacher_assignments.php`.
3. Luật phù hợp giảng viên nằm ở `TeacherAssignmentService.php`.

### Lỗi sinh viên không đăng ký được

1. Kiểm tra `university/student/register_subject.php`, action `register`.
2. Kiểm tra học kỳ có mở đăng ký ở `semesters.register_start/register_end`.
3. Kiểm tra lớp có `status='open'`, còn sĩ số, đúng khóa/lớp/CTĐT.
4. Kiểm tra trùng lịch và giới hạn tín chỉ trong `StudentRegistrationService.php`.

### Lỗi tự động đăng ký HK1

1. Kiểm tra hồ sơ tuyển sinh và phân lớp ở `university/admissions/auto_assign.php`.
2. Kiểm tra yêu cầu chờ duyệt ở `university/academic/pending_enrollments.php`.
3. Kiểm tra xử lý chính trong `AdmissionsEnrollmentService.php`.
4. Nếu bị treo hàng chờ, xem bảng `pending_enrollments` và action `retry_pending_enrollment`.

### Lỗi điểm hoặc không nhập được điểm

1. Kiểm tra `university/teacher/grades.php`, action `save_grade`.
2. Kiểm tra cửa sổ nhập điểm trong `GradeWindowService.php`.
3. Nếu Phòng Đào tạo khóa/mở khóa điểm, xem `academic/grade_locks.php`.

### Lỗi học phí

1. Kiểm tra đợt thu ở `finance/periods.php`.
2. Kiểm tra hóa đơn ở `finance/invoices.php`.
3. Đối chiếu đăng ký học phần trong `student_subjects` vì hóa đơn sinh từ dữ liệu đăng ký.
