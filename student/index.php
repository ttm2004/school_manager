<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Học viên | Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-color: #4e73df; --sidebar-width: 250px; }
        body { background-color: #f8f9fc; font-family: 'Inter', sans-serif; overflow-x: hidden; }
        
        .main-content { flex: 1; min-width: 0; }
        .course-card { border: none; border-radius: 12px; overflow: hidden; transition: 0.3s; background: white; cursor: pointer; }
        .course-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08); }
        .avatar-circle { width: 40px; height: 40px; background: #4e73df; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <header class="bg-white shadow-sm p-3 d-flex justify-content-between align-items-center px-4">
                <h5 class="m-0 fw-bold">Bảng điều khiển</h5>
                <div class="d-flex align-items-center">
                    <div class="me-3 text-end d-none d-md-block">
                        <div class="fw-bold"><?= $_SESSION['full_name'] ?></div>
                        <small class="text-muted">Mã SV: #<?= $_SESSION['user_id'] ?></small>
                    </div>
                    <div class="avatar-circle">
                        <?php 
                            $name_parts = explode(' ', $_SESSION['full_name']);
                            echo strtoupper(substr(end($name_parts), 0, 1)); 
                        ?>
                    </div>
                </div>
            </header>

            <main class="p-4">
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="p-4 bg-white rounded-4 shadow-sm d-flex align-items-center justify-content-between border-0">
                            <div>
                                <h3 class="fw-bold text-dark">Chào mừng trở lại, <?= end($name_parts) ?>! 👋</h3>
                                <p class="text-muted mb-0">Chúc bạn có một ngày học tập hiệu quả.</p>
                            </div>
                            <img src="https://illustrations.popsy.co/blue/studying.svg" style="width: 120px;" class="d-none d-lg-block">
                        </div>
                    </div>
                </div>

                <h5 class="fw-bold mb-3">Khóa học của tôi</h5>
                <div id="course-list" class="row g-4">
                    <div class="col-12 text-center py-5" id="loader">
                        <div class="spinner-border text-primary"></div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(() => {
            $.post('student_process.php', { action: 'get_my_courses' }, r => {
                $('#loader').remove();
                let h = '';
                if (r.status === 'success' && r.data && r.data.length > 0) {
                    r.data.forEach(c => {
                        h += `
                        <div class="col-md-6 col-xl-4">
                            <div class="card h-100 course-card shadow-sm" onclick="location.href='course_detail.php?class_id=${c.class_id}&subject_id=${c.subject_id}'">
                                <div style="height: 8px; background: #4e73df;"></div>
                                <div class="card-body p-4">
                                    <div class="badge bg-primary-subtle text-primary mb-2 px-3 rounded-pill">${c.class_name}</div>
                                    <h5 class="fw-bold mb-3 text-dark" style="min-height: 48px;">${c.subject_name}</h5>
                                    <div class="d-flex align-items-center text-muted small mb-3">
                                        <i class="far fa-user me-2"></i> Giảng viên: Đang cập nhật
                                    </div>
                                    <div class="progress mb-2" style="height: 6px; background-color: #eaecf4;">
                                        <div class="progress-bar" style="width: 0%"></div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <span class="small text-muted">Vào lớp ngay</span>
                                        <button class="btn btn-sm btn-primary rounded-pill px-4">Vào học</button>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                    });
                } else {
                    h = `<div class="col-12 text-center py-5"><p class="text-muted">Bạn chưa tham gia lớp học nào.</p></div>`;
                }
                $('#course-list').html(h);
            });
        });
    </script>
</body>
</html>