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
    <title>Giáo viên</title>
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
                <h4 class="m-0 fw-bold text-primary">TỔNG QUAN GIẢNG DẠY</h4>
            </nav>

            <div class="container-fluid px-4 py-4">
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm rounded-4 bg-primary text-white p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 opacity-75 small">Lớp Chủ Nhiệm</h6>
                                    <h3 class="mb-0 fw-bold" id="count-homeroom">0</h3>
                                </div>
                                <i class="fas fa-user-tie fs-1 opacity-25"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm rounded-4 bg-success text-white p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 opacity-75 small">Lớp Giảng Dạy</h6>
                                    <h3 class="mb-0 fw-bold" id="count-teaching">0</h3>
                                </div>
                                <i class="fas fa-chalkboard-teacher fs-1 opacity-25"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm rounded-4 bg-info text-white p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 opacity-75 small">Môn Học Đảm Nhiệm</h6>
                                    <h3 class="mb-0 fw-bold" id="count-subjects">0</h3>
                                </div>
                                <i class="fas fa-book-open fs-1 opacity-25"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm rounded-4 bg-warning text-dark p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 opacity-75 small">Tổng Học Sinh</h6>
                                    <h3 class="mb-0 fw-bold" id="count-students">0</h3>
                                </div>
                                <i class="fas fa-user-graduate fs-1 opacity-25"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card border-0 shadow-sm rounded-4 h-100">
                            <div class="card-header bg-white py-3 border-0">
                                <h6 class="m-0 fw-bold text-dark"><i class="fas fa-list-ul me-2 text-primary"></i>Danh sách môn học được giao</h6>
                            </div>
                            <div class="card-body">
                                <div id="subjects-list" class="d-flex flex-wrap gap-2">
                                    </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-4">
                        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                            <div class="card-body d-flex align-items-center justify-content-center text-center p-5">
                                <div>
                                    <img src="https://cdn-icons-png.flaticon.com/512/2997/2997300.png" width="80" class="mb-3">
                                    <h5 class="fw-bold">Xin chào, GV. <?php echo $_SESSION['full_name']; ?>!</h5>
                                    <p class="text-muted small">Hãy chọn các mục bên thanh Sidebar để quản lý điểm danh và điểm số học sinh.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(() => {
            $('#sidebarToggle').click(() => $('#wrapper').toggleClass('sb-sidenav-toggled'));
            loadOverview();
        });

        function loadOverview() {
            $.post('process.php', { action: 'get_overview' }, r => {
                if (r.status === 'success') {
                    $('#count-homeroom').text(r.counts.homeroom);
                    $('#count-teaching').text(r.counts.teaching);
                    $('#count-subjects').text(r.counts.subjects_count);
                    $('#count-students').text(r.counts.students);

                    let subHtml = '';
                    if (r.subjects_list.length > 0) {
                        r.subjects_list.forEach(sub => {
                            subHtml += `<span class="badge bg-light text-primary border border-primary px-3 py-2 rounded-pill fw-bold">${sub}</span>`;
                        });
                    } else {
                        subHtml = '<p class="text-muted small">Chưa được phân công môn nào.</p>';
                    }
                    $('#subjects-list').html(subHtml);
                }
            });
        }
    </script>
</body>
</html>