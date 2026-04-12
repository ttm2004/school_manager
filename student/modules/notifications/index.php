<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../../../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thông báo | Student</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-color: #4e73df; --sidebar-width: 250px; }
        body { background: #f8f9fc; font-family: 'Inter', sans-serif; height: 100vh; overflow: hidden; }
        .wrapper { display: flex; height: 100%; }
        .main-content { flex: 1; display: flex; flex-direction: column; height: 100%; overflow: hidden; }
        
        .notification-container {
            flex: 1;
            overflow-y: auto;
            padding: 20px 40px;
        }
        .notification-container::-webkit-scrollbar { width: 8px; }
        .notification-container::-webkit-scrollbar-thumb { background-color: #cbd3da; border-radius: 4px; }
        .notification-container::-webkit-scrollbar-track { background: transparent; }

        .noti-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            border: 1px solid #f0f0f0;
            transition: transform 0.2s;
        }
        .noti-card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .avatar-circle { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid #e3e6f0; }
        .teacher-name { font-weight: 700; color: #2c3e50; font-size: 0.95rem; }
        .class-badge { background-color: #dbeafe; color: #1e40af; font-size: 0.75rem; font-weight: 700; padding: 2px 8px; border-radius: 6px; margin-left: 8px; }
        .meta-info { font-size: 0.8rem; color: #9ca3af; margin-top: 2px; }
        .content-box { background-color: #f9fafb; border-radius: 8px; padding: 15px; margin-top: 15px; color: #4b5563; font-size: 0.95rem; line-height: 1.5; white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <nav class="navbar navbar-light bg-white shadow-sm px-4 py-3 flex-shrink-0">
                <button class="btn btn-light me-3 d-lg-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
                <h4 class="m-0 fw-bold text-primary"><i class="fas fa-bullhorn me-2"></i>Bảng tin lớp học</h4>
            </nav>

            <div class="notification-container">
                <div class="row justify-content-center">
                    <div class="col-lg-10" id="noti-list">
                        <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#sidebarToggle').click(() => $('#sidebar').toggleClass('d-none'));
            loadNotifications();
        });

        function loadNotifications() {
            $.post('process.php', { action: 'get_notifications' }, function(res) {
                let html = '';
                if (res.status === 'success' && res.data.length > 0) {
                    res.data.forEach(n => {
                        let avatar = n.teacher_avatar ? `../../../uploads/avatars/${n.teacher_avatar}` : `https://ui-avatars.com/api/?name=${encodeURIComponent(n.teacher_name)}&background=random`;
                        html += `
                            <div class="noti-card">
                                <div class="d-flex align-items-center">
                                    <img src="${avatar}" class="avatar-circle me-3">
                                    <div>
                                        <div class="d-flex align-items-center">
                                            <div class="teacher-name">${n.teacher_name}</div>
                                            <div class="class-badge">${n.class_name}</div>
                                        </div>
                                        <div class="meta-info">
                                            <span>${n.created_at_fmt}</span>
                                            <span class="mx-1">•</span>
                                            <span>Môn: <strong class="text-dark">${n.subject_name}</strong></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="content-box">${n.content}</div>
                            </div>
                        `;
                    });
                } else {
                    html = `
                        <div class="text-center py-5">
                            <img src="https://cdn-icons-png.flaticon.com/512/4076/4076478.png" width="120" class="mb-3 opacity-50">
                            <h6 class="text-muted fw-bold">Không có thông báo nào</h6>
                            <p class="text-muted small">Bảng tin lớp học hiện đang trống.</p>
                        </div>`;
                }
                $('#noti-list').html(html);
            }, 'json');
        }
    </script>
</body>
</html>