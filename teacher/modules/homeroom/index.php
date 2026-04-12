<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../../../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Lớp Chủ Nhiệm</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/admin.css">
</head>
<body class="bg-light">
    <div class="d-flex" id="wrapper">
        <?php include '../../includes/sidebar.php'; ?>
        <div id="page-content-wrapper" class="w-100">
            <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm px-4 py-3">
                <button class="btn btn-light me-3" id="sidebarToggle"><i class="fas fa-bars"></i></button>
                <h4 class="m-0 fw-bold text-primary">QUẢN LÝ LỚP CHỦ NHIỆM</h4>
            </nav>

            <div class="container-fluid px-4 py-4">
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="m-0 fw-bold"><i class="fas fa-user-tie me-2 text-primary"></i>Lớp đang chủ nhiệm</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Tên Lớp</th>
                                        <th>Ngày tạo</th>
                                        <th>Sĩ Số</th>
                                        <th class="text-end">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody id="homeroom-list"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="studentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-users me-2"></i>Lớp: <span id="modal-class-name"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">STT</th>
                                    <th>Ảnh</th>
                                    <th>Mã HS (User)</th>
                                    <th>Họ và Tên</th>
                                    <th>Email</th>
                                    <th>Số điện thoại</th>
                                    <th>Địa chỉ</th>z
                                    <th class="pe-3">Ngày tham gia</th>
                                </tr>
                            </thead>
                            <tbody id="modal-student-list"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(() => {
            $('#sidebarToggle').click(() => $('#wrapper').toggleClass('sb-sidenav-toggled'));
            loadHomeRoom();
        });

        function loadHomeRoom() {
            $.post('process.php', { action: 'get_my_homeroom' }, r => {
                let html = '';
                if (r.data.length > 0) {
                    r.data.forEach(item => {
                        html += `
                            <tr>
                                <td class="fw-bold text-primary">${item.class_name}</td>
                                <td>${item.created_date}</td>
                                <td><span class="badge bg-info text-dark">${item.total_students} học sinh</span></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-primary rounded-pill px-3" onclick="viewStudents(${item.id}, '${item.class_name}')">
                                        <i class="fas fa-eye me-1"></i> Xem danh sách
                                    </button>
                                </td>
                            </tr>`;
                    });
                } else {
                    html = '<tr><td colspan="4" class="text-center">Bạn không chủ nhiệm lớp nào.</td></tr>';
                }
                $('#homeroom-list').html(html);
            });
        }

        function viewStudents(classId, className) {
            $('#modal-class-name').text(className);
            $('#modal-student-list').html('<tr><td colspan="8" class="text-center p-4"><div class="spinner-border text-primary"></div></td></tr>');
            
            const studentModal = new bootstrap.Modal(document.getElementById('studentModal'));
            studentModal.show();

            $.post('process.php', { action: 'get_students', class_id: classId }, r => {
                let html = '';
                if (r.status === 'success' && r.data.length > 0) {
                    r.data.forEach((st, index) => {
                        let avatar = st.avatar ? `../../../uploads/avatars/${st.avatar}` : `https://ui-avatars.com/api/?name=${encodeURIComponent(st.full_name)}&background=random`;
                        html += `
                            <tr>
                                <td class="ps-3">${index + 1}</td>
                                <td><img src="${avatar}" class="rounded-circle border" width="40" height="40" style="object-fit:cover"></td>
                                <td class="fw-bold text-secondary">${st.username}</td>
                                <td class="fw-bold">${st.full_name}</td>
                                <td>${st.email || '<i class="text-muted small">N/A</i>'}</td>
                                <td>${st.phone || '<i class="text-muted small">N/A</i>'}</td>
                                <td><small>${st.address || '<i class="text-muted small">N/A</i>'}</small></td>
                                <td class="pe-3 small text-muted">${st.created_at}</td>
                            </tr>`;
                    });
                } else {
                    html = '<tr><td colspan="8" class="text-center p-4">Lớp hiện chưa có học sinh nào tham gia.</td></tr>';
                }
                $('#modal-student-list').html(html);
            });
        }
    </script>
</body>
</html>