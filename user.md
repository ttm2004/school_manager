# Hướng dẫn Test Hệ thống TDMU — Từ A đến Z

> **Hệ thống:** Quản lý Đại học Thủ Dầu Một (TDMU)  
> **URL local:** `http://localhost/university/`  
> **Database:** `edu_management` (MariaDB 10.4)  
> **Mật khẩu mặc định tất cả user:** `123456`

---

## 1. Danh sách Tài khoản Test

### 1.1 Admin Hệ thống

| Username | Mật khẩu | Họ tên | Role | URL vào |
|----------|----------|--------|------|---------|
| `admin` | `123456` | Quản trị viên | admin | `/university/admin/` |

---

### 1.2 Nhân viên Phòng ban (role = `staff` + RBAC role)

| Username | Mật khẩu | Họ tên | RBAC Role | Phòng ban | URL vào |
|----------|----------|--------|-----------|-----------|---------|
| `ttm123` | `123456` | Trần Trọng Mạnh | `admissions_staff` | Phòng Tuyển sinh | `/university/admissions/` |
| `huylv` | `123456` | Lê Văn Huy | `finance_staff` | Phòng Tài chính | `/university/finance/` |
| `manhtt` | `123456` | Hi | `admissions_manager` + `it_admin` | Tuyển sinh / CNTT | `/university/admissions/` |
| `taichinh01` | `123456` | Nguyễn Công Minh | `finance_staff` | Phòng Tài chính | `/university/finance/` |
| `huynq` | `123456` | Nguyễn Quang Huy | `academic_staff` | Phòng Đào tạo | `/university/academic/` |

---

### 1.3 Giảng viên (role = `teacher`)

| Username | Mật khẩu | Họ tên | Khoa | RBAC Role | URL vào |
|----------|----------|--------|------|-----------|---------|
| `gv001` | `123456` | ThS. Phạm Minh Tuấn | CNTT (faculty_id=1) | `faculty_manager` — Trưởng Khoa CNTT | `/university/faculty/` |
| `gv002` | `123456` | ThS. Nguyễn Thị Hạnh | CNTT (faculty_id=1) | `faculty_staff` — Thư ký Khoa CNTT | `/university/faculty/` |
| `gv003` | `123456` | TS. Trần Quốc Bảo | CNTT (faculty_id=1) | `academic_manager` — Trưởng phòng Đào tạo | `/university/academic/` |
| `gv004` | `123456` | ThS. Lê Thị Mỹ Duyên | Kinh tế (faculty_id=2) | `faculty_manager` — Trưởng Khoa Kinh tế | `/university/faculty/` |
| `gv005` | `123456` | ThS. Nguyễn Quốc Thịnh | Ngoại ngữ (faculty_id=3) | `faculty_manager` — Trưởng Khoa Ngoại ngữ | `/university/faculty/` |
| `gv007` | `123456` | Đinh Công Vy | CNTT (faculty_id=1) | `faculty_staff` — Thư ký Khoa CNTT | `/university/faculty/` |
| `gv008` | `123456` | Lê Thanh Hoài | CNTT (faculty_id=1) | `academic_staff` — NV Phòng Đào tạo | `/university/academic/` |

> **Lưu ý:** Seed file `database/seeds/001_staff_users.sql` phải được chạy để gán RBAC roles cho GV.

---

### 1.4 Sinh viên (role = `student`)

| Username | Mật khẩu | Họ tên | Ngành | URL vào |
|----------|----------|--------|-------|---------|
| `sv001` | `123456` | Nguyễn Văn An | CNTT | `/university/student/` |
| `sv002` | `123456` | Lê Hoàng Vy | CNTT | `/university/student/` |
| `sv003` | `123456` | Trần Minh Khang | CNTT | `/university/student/` |
| `sv004` | `123456` | Phạm Thị Ngọc Mai | CNTT | `/university/student/` |
| `sv005` | `123456` | Võ Quốc Huy | CNTT | `/university/student/` |
| `sv006` | `123456` | Đặng Thanh Trúc | CNTT | `/university/student/` |
| `sv007` | `123456` | Huỳnh Gia Bảo | CNTT | `/university/student/` |
| `sv008` | `123456` | Ngô Phương Linh | CNTT | `/university/student/` |
| `sv009` | `123456` | vyle | CNTT | `/university/student/` |
| `20267480103911` | `123456` | Nguyễn Kim Ngân | Kinh tế | `/university/student/` |

> Còn ~250 sinh viên khác được tạo tự động từ seed, username = mã sinh viên (VD: `20267340301573`).

---

## 2. Chuẩn bị Môi trường

### Bước 1: Cài đặt Database

```sql
-- 1. Import schema gốc
SOURCE university/edu_management.sql;

-- 2. Chạy migration faculty module
SOURCE university/faculty_module_migration.sql;

-- 3. Gán roles cho giảng viên
SOURCE university/database/seeds/001_staff_users.sql;

-- 4. Chạy các seeds còn lại (theo thứ tự)
SOURCE university/database/seeds/002_semesters_subjects.sql;
SOURCE university/database/seeds/003_curriculum.sql;
SOURCE university/database/seeds/004_course_sections.sql;
SOURCE university/database/seeds/005_enrollments_grades.sql;
SOURCE university/database/seeds/006_exam_schedules_notifications.sql;
SOURCE university/database/seeds/007_evaluation_tuition.sql;
```

### Bước 2: Cấu hình `.env`

```env
DB_HOST=localhost
DB_NAME=edu_management
DB_USER=root
DB_PASS=
```

---

## 3. Luồng Test Theo Module

---

### MODULE 1: Admin (`/university/admin/`)

**Đăng nhập:** `admin` / `123456`

| # | Chức năng | Trang | Thao tác test |
|---|-----------|-------|---------------|
| 1 | Quản lý Users | `/admin/users.php` | Xem danh sách, tạo user mới, gán role |
| 2 | Quản lý Khoa | `/admin/faculties.php` | Thêm/sửa/xóa khoa |
| 3 | Quản lý Ngành | `/admin/majors.php` | Thêm ngành vào khoa |
| 4 | Quản lý Môn học | `/admin/subjects.php` | Thêm môn học, nhập tín chỉ |
| 5 | Chương trình ĐT | `/admin/curriculum.php` | Xem CTĐT theo ngành |
| 6 | Học kỳ | `/admin/semesters.php` | Tạo học kỳ, set active |
| 7 | Lớp học phần | `/admin/course_sections.php` | Xem/duyệt lớp học phần |
| 8 | Phân công GV | `/admin/teacher_assignments.php` | Phân công GV dạy lớp |
| 9 | Sinh viên | `/admin/students.php` | Xem danh sách SV |
| 10 | Giảng viên | `/admin/teachers.php` | Xem danh sách GV |
| 11 | Điểm số | `/admin/grades.php` | Xem điểm toàn trường |
| 12 | Lịch thi | `/admin/final_exam_schedules.php` | Xem/tạo lịch thi |
| 13 | Đánh giá GV | `/admin/evaluation_results.php` | Xem kết quả đánh giá |
| 14 | Học phí | `/admin/tuition.php` | Quản lý học phí |
| 15 | Thông báo | `/admin/notifications.php` | Gửi thông báo |

---

### MODULE 2: Tuyển sinh (`/university/admissions/`)

**Đăng nhập:** `ttm123` / `123456` (admissions_staff) hoặc `manhtt` / `123456` (admissions_manager)

| # | Chức năng | Trang | Thao tác test |
|---|-----------|-------|---------------|
| 1 | Dashboard | `/admissions/index.php` | Xem tổng quan tuyển sinh |
| 2 | Hồ sơ đăng ký | `/admissions/applications.php` | Xem danh sách hồ sơ |
| 3 | Xét duyệt | `/admissions/auto_review.php` | Chạy xét duyệt tự động |
| 4 | Điểm chuẩn | `/admissions/results.php` | Xem/công bố điểm chuẩn |
| 5 | Nhập học | `/admissions/enrollment.php` | Xác nhận nhập học |
| 6 | Báo cáo | `/admissions/reports.php` | Xem báo cáo tuyển sinh |
| 7 | Admin tuyển sinh | `/admissions/admin/` | Quản lý đợt tuyển sinh |

---

### MODULE 3: Phòng Đào tạo (`/university/academic/`)

**Đăng nhập:** `huynq` / `123456` (academic_staff) hoặc `gv003` / `123456` (academic_manager)

| # | Chức năng | Trang | Thao tác test |
|---|-----------|-------|---------------|
| 1 | Dashboard | `/academic/index.php` | Xem tổng quan |
| 2 | Học kỳ | `/academic/semesters.php` | Quản lý học kỳ |
| 3 | Môn học | `/academic/subjects.php` | Quản lý môn học |
| 4 | Lớp học phần | `/academic/course_sections.php` | Duyệt/mở lớp học phần |
| 5 | Phân công GV | `/academic/teacher_assignments.php` | Phân công chính thức |
| 6 | Thời khóa biểu | `/academic/timetable.php` | Xem TKB toàn trường |
| 7 | Lịch thi | `/academic/exam_schedules.php` | Tạo lịch thi |
| 8 | Điểm số | `/academic/grades.php` | Xem/khóa điểm |
| 9 | Sinh viên | `/academic/students.php` | Quản lý SV |
| 10 | Đề xuất mở lớp | `/academic/proposals.php` | Duyệt đề xuất từ Khoa |
| 11 | Thông báo | `/academic/notifications.php` | Gửi thông báo |

---

### MODULE 4: Khoa/Viện (`/university/faculty/`)

**Đăng nhập Trưởng khoa CNTT:** `gv001` / `123456`  
**Đăng nhập Thư ký khoa CNTT:** `gv002` / `123456`

> **Lưu ý:** Phải chọn role `faculty_manager` hoặc `faculty_staff` ở trang chọn role sau khi đăng nhập.

#### 4.1 Dashboard

| Thao tác | Kết quả mong đợi |
|----------|-----------------|
| Vào `/faculty/index.php` | Hiển thị warning cards: lớp chưa có GV, lớp chưa có lịch thi, GV quá tải |
| Kiểm tra data isolation | Chỉ thấy dữ liệu Khoa CNTT, không thấy Khoa Kinh tế |

#### 4.2 Quản lý Giảng viên

| # | Trang | Thao tác | Kết quả mong đợi |
|---|-------|----------|-----------------|
| 1 | `/faculty/teachers.php` | Xem danh sách GV | Chỉ hiện GV Khoa CNTT |
| 2 | `/faculty/teachers.php` | Filter theo học vị (ThS/TS) | Lọc đúng |
| 3 | `/faculty/teachers.php` | Tìm kiếm theo tên | Tìm đúng |
| 4 | `/faculty/teacher_detail.php?id=10` | Xem hồ sơ GV | Hiện đầy đủ thông tin + teaching load |
| 5 | `/faculty/teaching_load.php` | Xem khối lượng giảng dạy | Hiện bảng tổng tín chỉ/GV |
| 6 | `/faculty/departments.php` | Quản lý bộ môn | Thêm/sửa/xóa bộ môn |

#### 4.3 Quản lý Sinh viên

| # | Trang | Thao tác | Kết quả mong đợi |
|---|-------|----------|-----------------|
| 1 | `/faculty/students.php` | Xem danh sách SV | Chỉ hiện SV ngành thuộc Khoa CNTT |
| 2 | `/faculty/students.php` | Filter theo trạng thái | Lọc đúng (đang học/bảo lưu/...) |
| 3 | `/faculty/student_detail.php?id=2` | Xem hồ sơ SV | Hiện GPA, cảnh báo học vụ |
| 4 | `/faculty/academic_warnings.php` | Xem cảnh báo học vụ | Hiện SV có GPA < 4.0 |

#### 4.4 Chương trình Đào tạo

| # | Trang | Thao tác | Kết quả mong đợi |
|---|-------|----------|-----------------|
| 1 | `/faculty/curriculum.php` | Xem CTĐT ngành CNTT | Hiện danh sách môn theo học kỳ |
| 2 | `/faculty/curriculum.php` | Thêm môn vào CTĐT | Lưu thành công, hiện trong danh sách |
| 3 | `/faculty/curriculum.php` | Xóa môn là tiên quyết | Hiện cảnh báo trước khi xóa |

#### 4.5 Đề xuất Mở lớp

| # | Trang | Thao tác | Kết quả mong đợi |
|---|-------|----------|-----------------|
| 1 | `/faculty/proposals.php` | Tạo đề xuất nháp | Status = `draft` |
| 2 | `/faculty/proposals.php` | Submit đề xuất | Status chuyển `draft` → `pending` |
| 3 | `/faculty/proposals.php` | Hủy đề xuất pending | Status = `cancelled` |
| 4 | `/faculty/proposals.php` | Đề xuất phân công GV | Chọn GV trong khoa, hiện teaching load |
| 5 | Đăng nhập `gv003` (academic_manager) | Duyệt đề xuất | Status → `approved`/`open` |

#### 4.6 Các trang khác

| Trang | Chức năng |
|-------|-----------|
| `/faculty/grades.php` | Xem kết quả học tập, pass_rate theo lớp |
| `/faculty/exam_schedules.php` | Xem lịch thi (read-only) |
| `/faculty/evaluation.php` | Xem kết quả đánh giá GV |
| `/faculty/reports.php` | Thống kê tổng hợp |
| `/faculty/notifications.php` | Gửi thông báo nội bộ |
| `/faculty/audit_log.php` | Xem nhật ký thao tác |
| `/faculty/export.php?type=teachers` | Export CSV danh sách GV |

---

### MODULE 5: Giảng viên (`/university/teacher/`)

**Đăng nhập:** `gv001` / `123456` → chọn role `teacher`

| # | Chức năng | Trang | Thao tác test |
|---|-----------|-------|---------------|
| 1 | Dashboard | `/teacher/index.php` | Xem tổng quan lớp dạy |
| 2 | Lớp của tôi | `/teacher/my_courses.php` | Xem danh sách lớp học phần |
| 3 | Nhập điểm | `/teacher/grades.php` | Nhập điểm giữa kỳ, cuối kỳ |
| 4 | Lịch thi | `/teacher/exam_schedule.php` | Xem lịch thi |
| 5 | Thời khóa biểu | `/teacher/timetable.php` | Xem TKB cá nhân |
| 6 | Đánh giá | `/teacher/evaluation.php` | Xem kết quả đánh giá từ SV |
| 7 | Hồ sơ | `/teacher/profile.php` | Xem/cập nhật thông tin cá nhân |

---

### MODULE 6: Sinh viên (`/university/student/`)

**Đăng nhập:** `sv001` / `123456`

| # | Chức năng | Trang | Thao tác test |
|---|-----------|-------|---------------|
| 1 | Dashboard | `/student/index.php` | Xem tổng quan |
| 2 | Đăng ký học phần | `/student/register_subject.php` | Đăng ký lớp học phần |
| 3 | Môn học của tôi | `/student/my_subjects.php` | Xem môn đã đăng ký |
| 4 | Xem điểm | `/student/grades.php` | Xem điểm các môn |
| 5 | Lịch thi | `/student/exam_schedule.php` | Xem lịch thi cá nhân |
| 6 | Thời khóa biểu | `/student/timetable.php` | Xem TKB cá nhân |
| 7 | Chương trình ĐT | `/student/curriculum.php` | Xem CTĐT ngành mình |
| 8 | Học phí | `/student/tuition.php` | Xem học phí, trạng thái đóng |
| 9 | Đánh giá GV | `/student/evaluation.php` | Đánh giá giảng viên |
| 10 | Hồ sơ | `/student/profile.php` | Xem/cập nhật thông tin |

---

### MODULE 7: Tài chính (`/university/finance/`)

**Đăng nhập:** `taichinh01` / `123456` hoặc `huylv` / `123456`

| # | Chức năng | Trang | Thao tác test |
|---|-----------|-------|---------------|
| 1 | Dashboard | `/finance/index.php` | Xem tổng quan thu chi |
| 2 | Hóa đơn | `/finance/invoices.php` | Xem danh sách hóa đơn học phí |
| 3 | Thanh toán | `/finance/payments.php` | Xác nhận thanh toán |
| 4 | Kỳ học phí | `/finance/periods.php` | Quản lý kỳ thu học phí |

---

## 4. Test Cases Bảo mật

### 4.1 Kiểm tra Data Isolation (Quan trọng nhất)

```
1. Đăng nhập gv001 (Trưởng khoa CNTT)
2. Truy cập /faculty/student_detail.php?id=[ID sinh viên Khoa Kinh tế]
   → Kết quả mong đợi: HTTP 403 "Không có quyền xem thông tin sinh viên thuộc khoa khác"

3. Truy cập /faculty/teacher_detail.php?id=[ID GV Khoa Kinh tế]
   → Kết quả mong đợi: HTTP 403 "Không có quyền"
```

### 4.2 Kiểm tra CSRF Protection

```
1. Mở DevTools → Network
2. Submit form bất kỳ (VD: thêm bộ môn)
3. Xóa _csrf_token trong request
   → Kết quả mong đợi: HTTP 403 "Yêu cầu không hợp lệ"
```

### 4.3 Kiểm tra Role-Based Access

```
1. Đăng nhập gv002 (faculty_staff — Thư ký)
2. Thử thêm môn vào CTĐT (POST action=add)
   → Kết quả mong đợi: "Bạn không có quyền thực hiện thao tác này"

3. Đăng nhập gv001 (faculty_manager — Trưởng khoa)
4. Thử thêm môn vào CTĐT
   → Kết quả mong đợi: Thành công
```

### 4.4 Kiểm tra SQL Injection

```
1. Vào /faculty/teachers.php
2. Nhập vào ô tìm kiếm: ' OR '1'='1
   → Kết quả mong đợi: Không có lỗi SQL, trả về 0 kết quả hoặc kết quả bình thường
```

---

## 5. Luồng Test End-to-End Hoàn chỉnh

### Kịch bản: Mở lớp học phần cho học kỳ mới

```
Bước 1 — Admin tạo học kỳ mới
  → Đăng nhập: admin / 123456
  → Vào /admin/semesters.php → Thêm học kỳ HK1 2026-2027
  → Set status = 'active'

Bước 2 — Trưởng khoa đề xuất mở lớp
  → Đăng nhập: gv001 / 123456 → chọn role faculty_manager
  → Vào /faculty/proposals.php → Tab "Mở lớp"
  → Tạo đề xuất nháp cho môn "Lập trình Web" (draft)
  → Submit đề xuất (pending)

Bước 3 — Phòng Đào tạo duyệt đề xuất
  → Đăng nhập: gv003 / 123456 → chọn role academic_manager
  → Vào /academic/proposals.php
  → Duyệt đề xuất → status = 'open'

Bước 4 — Trưởng khoa đề xuất phân công GV
  → Đăng nhập: gv001 / 123456
  → Vào /faculty/proposals.php → Tab "Phân công GV"
  → Chọn lớp vừa được duyệt → Đề xuất GV dạy

Bước 5 — Phòng Đào tạo duyệt phân công
  → Đăng nhập: gv003 / 123456
  → Duyệt phân công GV → proposal_status = 'approved'

Bước 6 — Sinh viên đăng ký học phần
  → Đăng nhập: sv001 / 123456
  → Vào /student/register_subject.php
  → Đăng ký lớp học phần vừa mở

Bước 7 — Giảng viên nhập điểm
  → Đăng nhập: gv001 / 123456 → chọn role teacher
  → Vào /teacher/grades.php
  → Nhập điểm giữa kỳ và cuối kỳ

Bước 8 — Sinh viên xem điểm
  → Đăng nhập: sv001 / 123456
  → Vào /student/grades.php → Xem điểm môn vừa học

Bước 9 — Trưởng khoa xem báo cáo
  → Đăng nhập: gv001 / 123456 → faculty_manager
  → Vào /faculty/reports.php → Xem pass_rate, cảnh báo học vụ
```

---

## 6. Kiểm tra Sau Khi Chạy Seed

Chạy các câu SQL sau để verify dữ liệu:

```sql
-- Kiểm tra roles đã gán cho GV
SELECT u.username, u.full_name, r.code, r.name
FROM users u
JOIN user_roles ur ON ur.user_id = u.id
JOIN roles r ON ur.role_id = r.id
WHERE u.role = 'teacher'
ORDER BY u.username;

-- Kiểm tra faculty_id của GV
SELECT u.username, t.teacher_code, t.faculty_id, f.faculty_name
FROM users u
JOIN teachers t ON t.user_id = u.id
LEFT JOIN faculties f ON f.id = t.faculty_id
ORDER BY t.faculty_id;

-- Kiểm tra học kỳ active
SELECT * FROM semesters WHERE status = 'active';

-- Kiểm tra lớp học phần đang mở
SELECT cs.section_code, s.subject_name, sem.semester_name, cs.status
FROM course_sections cs
JOIN subjects s ON s.id = cs.subject_id
JOIN semesters sem ON sem.id = cs.semester_id
WHERE cs.status = 'open'
LIMIT 10;

-- Kiểm tra SV có điểm
SELECT u.username, sub.subject_name, g.midterm_score, g.final_score, g.gpa_score
FROM grades g
JOIN students st ON st.id = g.student_id
JOIN users u ON u.id = st.user_id
JOIN course_sections cs ON cs.id = g.course_section_id
JOIN subjects sub ON sub.id = cs.subject_id
LIMIT 10;
```

---

## 7. Tài khoản Nhanh — Cheat Sheet

```
=== ADMIN ===
admin / 123456  →  /university/admin/

=== PHÒNG TUYỂN SINH ===
ttm123 / 123456  →  /university/admissions/   (staff)
manhtt / 123456  →  /university/admissions/   (manager)

=== PHÒNG ĐÀO TẠO ===
huynq  / 123456  →  /university/academic/     (staff)
gv003  / 123456  →  /university/academic/     (manager)

=== PHÒNG TÀI CHÍNH ===
taichinh01 / 123456  →  /university/finance/  (staff)
huylv      / 123456  →  /university/finance/  (staff)

=== KHOA CNTT ===
gv001 / 123456  →  /university/faculty/  (Trưởng khoa)
gv002 / 123456  →  /university/faculty/  (Thư ký)
gv007 / 123456  →  /university/faculty/  (Thư ký)

=== KHOA KINH TẾ ===
gv004 / 123456  →  /university/faculty/  (Trưởng khoa)

=== KHOA NGOẠI NGỮ ===
gv005 / 123456  →  /university/faculty/  (Trưởng khoa)

=== GIẢNG VIÊN (portal GV) ===
gv001 / 123456  →  /university/teacher/
gv002 / 123456  →  /university/teacher/
gv003 / 123456  →  /university/teacher/

=== SINH VIÊN ===
sv001 / 123456  →  /university/student/
sv002 / 123456  →  /university/student/
sv003 / 123456  →  /university/student/
sv004 / 123456  →  /university/student/
sv005 / 123456  →  /university/student/
```

---

*Cập nhật lần cuối: 2026-05-12*
