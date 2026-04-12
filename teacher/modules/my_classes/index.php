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
    <title>Lớp Giảng Dạy</title>
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
                <h4 class="m-0 fw-bold text-primary">DANH SÁCH LỚP GIẢNG DẠY</h4>
            </nav>

            <div class="container-fluid px-4 py-4">
                <div class="row g-4" id="teaching-classes-list">
                    </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(() => {
            $('#sidebarToggle').click(() => $('#wrapper').toggleClass('sb-sidenav-toggled'));
            loadTeachingClasses();
        });

        function loadTeachingClasses() {
            $.post('process.php', { action: 'get_my_teaching_classes' }, r => {
                let html = '';
                if (r.data && r.data.length > 0) {
                    r.data.forEach(item => {
                        html += `
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between mb-3">
                                        <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2">Môn học</span>
                                        <span class="text-muted small"><i class="fas fa-users me-1"></i>${item.student_count} HS</span>
                                    </div>
                                    <h5 class="fw-bold mb-1">${item.subject_name}</h5>
                                    <p class="text-secondary mb-4">Lớp: <span class="fw-bold text-dark">${item.class_name}</span></p>
                                    
                                    <a href="../class_detail/index.php?class_id=${item.class_id}&subject_id=${item.subject_id}" 
                                       class="btn btn-primary w-100 rounded-pill fw-bold shadow-sm">
                                        Chi tiết lớp học <i class="fas fa-arrow-right ms-2"></i>
                                    </a>
                                </div>
                            </div>
                        </div>`;
                    });
                } else {
                    html = '<div class="col-12 text-center py-5"><img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" width="100" class="opacity-25 mb-3"><p class="text-muted">Bạn chưa được phân công giảng dạy môn học nào.</p></div>';
                }
                $('#teaching-classes-list').html(html);
            });
        }
    </script>
</body>
</html>