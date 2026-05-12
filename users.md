# Danh sách tài khoản hệ thống TDMU

> **Lưu ý:** File này chỉ dùng cho môi trường **development/testing**.

---

## Nguyên tắc phân quyền

- **Không tạo user mới** cho trưởng phòng/trưởng khoa
- Giảng viên được **cấp thêm role** kiêm nhiệm qua bảng `user_roles`
- User có **≥2 roles** → sau đăng nhập sẽ thấy **màn hình chọn vai trò**
- Mỗi lần đăng nhập chọn 1 vai trò để làm việc

---

## 🔑 Admin

| Username | Password | Ghi chú |
|----------|----------|---------|
| `admin` | `123456` | Toàn quyền, không qua màn hình chọn role |

---

## 👨‍🏫 Giảng viên — Tài khoản & Roles

| Username | Password | Họ tên | Mã GV | Khoa | Roles (chọn khi đăng nhập) |
|----------|----------|--------|-------|------|---------------------------|
| `gv001` | `123456` | ThS. Phạm Minh Tuấn | GV001 | CNTT | `faculty_manager` (Trưởng khoa CNTT) · `faculty_lecturer` |
| `gv002` | `123456` | ThS. Nguyễn Thị Hạnh | GV002 | CNTT | `faculty_staff` (Thư ký khoa CNTT) · `faculty_lecturer` |
| `gv003` | `123456` | TS. Trần Quốc Bảo | GV003 | CNTT | `academic_manager` (Trưởng phòng ĐT) · `faculty_lecturer` |
| `gv004` | `123456` | ThS. Lê Thị Mỹ Duyên | GV004 | Kinh tế | `faculty_manager` (Trưởng khoa KT) · `faculty_lecturer` |
| `gv005` | `123456` | ThS. Nguyễn Quốc Thịnh | GV005 | Ngoại ngữ | `faculty_manager` (Trưởng khoa NN) · `faculty_lecturer` |
| `gv007` | `123456` | Đinh Công Vy | GV007 | CNTT | `faculty_staff` (Thư ký CNTT) · `faculty_lecturer` |
| `gv008` | `123456` | Lê Thanh Hoài | GV008 | CNTT | `academic_staff` (NV Phòng ĐT) · `faculty_lecturer` |

> **Lưu ý:** GV có 2 roles → sau đăng nhập sẽ thấy màn hình chọn:
> - **Giảng viên** → vào `/university/teacher/`
> - **Trưởng khoa / Thư ký / Phòng ĐT** → vào module tương ứng

---

## 🎓 Sinh viên

| Username | Password | Họ tên | Mã SV | Ghi chú |
|----------|----------|--------|-------|---------|
| `sv001` | `123456` | Nguyễn Văn An | 222480201001 | Có điểm, đăng ký môn |
| `sv002` | `123456` | Lê Hoàng Vy | 222480201002 | Có điểm |
| `sv003` | `123456` | Trần Minh Khang | 222480201003 | Điểm thấp → cảnh báo học vụ |
| `sv004` | `123456` | Phạm Thị Ngọc Mai | 222480201004 | Nợ học phí một phần |
| `sv005` | `123456` | Võ Quốc Huy | 222480201005 | Bình thường |
| `sv006` | `123456` | Đặng Thanh Trúc | 222480201006 | Chưa đóng học phí |
| `sv007` | `123456` | Huỳnh Gia Bảo | 222480201007 | Bình thường |
| `sv008` | `123456` | Ngô Phương Linh | 222480201008 | Bình thường |

---

## 🌐 URL đăng nhập

```
http://localhost/university/login.php
```

| Sau khi chọn role | Module |
|-------------------|--------|
| Trưởng khoa / Thư ký | `/university/faculty/` |
| Trưởng/NV Phòng ĐT | `/university/academic/` |
| Giảng viên | `/university/teacher/` |
| Sinh viên | `/university/student/` |
| Admin | `/university/admin/` |

---

## 📡 API

```bash
# Đăng nhập
curl -X POST http://localhost/university/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"gv001","password":"123456"}'
```
