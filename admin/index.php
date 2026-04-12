<?php
session_start();
// 1. Check quyền Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';

// --- PHẦN XỬ LÝ DỮ LIỆU PHP ---

// 1. Thống kê số lượng tổng (Cards)
$total_students = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$total_teachers = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
$total_classes  = $conn->query("SELECT COUNT(*) FROM classes")->fetchColumn();
$total_news     = $conn->query("SELECT COUNT(*) FROM news")->fetchColumn();

// 2. Dữ liệu cho Biểu đồ Tròn (Tỉ lệ thành viên)
$stmt_roles = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$role_data = [];
$role_labels = [];
while ($row = $stmt_roles->fetch()) {
    $role_labels[] = ucfirst($row['role']); 
    $role_data[] = $row['count'];
}

// 3. Dữ liệu cho Biểu đồ Cột (Số lớp theo Khoa)
$sql_dept = "SELECT d.name, COUNT(c.id) as class_count 
             FROM departments d 
             LEFT JOIN classes c ON d.id = c.department_id 
             GROUP BY d.id";
$stmt_dept = $conn->query($sql_dept);
$dept_labels = [];
$dept_data = [];
while ($row = $stmt_dept->fetch()) {
    $dept_labels[] = $row['name'];
    $dept_data[] = $row['class_count'];
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Smart School</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../assets/css/admin.css">
    
    <style>
        /* CSS riêng cho Dashboard */
        .card-box {
            border-radius: 15px;
            border: none;
            transition: all 0.3s ease;
            color: white;
            overflow: hidden;
            position: relative;
        }
        .card-box:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .card-box .icon-bg { position: absolute; right: 10px; bottom: 10px; font-size: 5rem; opacity: 0.2; }
        
        .bg-gradient-1 { background: linear-gradient(45deg, #FF512F, #DD2476); }
        .bg-gradient-2 { background: linear-gradient(45deg, #4facfe, #00f2fe); }
        .bg-gradient-3 { background: linear-gradient(45deg, #43e97b, #38f9d7); }
        .bg-gradient-4 { background: linear-gradient(45deg, #fa709a, #fee140); }

        .chart-container {
            background: white;
            padding: 15px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            /* Giới hạn chiều cao biểu đồ tại đây */
            height: 350px; 
            display: flex;
            flex-direction: column;
        }
        .chart-canvas-wrapper {
            flex-grow: 1;
            position: relative;
            width: 100%;
            height: 100%;
        }
    </style>
</head>
<body>

<div class="d-flex" id="wrapper">
    
    <?php include 'includes/sidebar.php'; ?>

    <div id="page-content-wrapper" class="w-100 bg-light">
        
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm px-4 py-3">
            <button class="btn btn-light me-3" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h4 class="m-0 fw-bold text-primary">Tổng quan</h4>
            
            <div class="ms-auto d-flex align-items-center">
                <span class="me-3 text-muted"><?= date('d/m/Y l') ?></span>
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle fw-bold text-dark" href="#" role="button" data-bs-toggle="dropdown">
                        <img src="https://ui-avatars.com/api/?name=<?= $_SESSION['full_name'] ?>&background=random" class="rounded-circle me-2" width="35">
                        <?= $_SESSION['full_name'] ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                        <li><a class="dropdown-item" href="#">Cài đặt</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="../logout.php">Đăng xuất</a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="container-fluid px-4 py-4">
            
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card card-box bg-gradient-1 h-100">
                        <div class="card-body">
                            <h5 class="card-title text-uppercase opacity-75" style="font-size: 0.9rem;">Học sinh</h5>
                            <h2 class="display-6 fw-bold"><?= $total_students ?></h2>
                            <i class="fas fa-user-graduate icon-bg"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-box bg-gradient-2 h-100">
                        <div class="card-body">
                            <h5 class="card-title text-uppercase opacity-75" style="font-size: 0.9rem;">Giáo viên</h5>
                            <h2 class="display-6 fw-bold"><?= $total_teachers ?></h2>
                            <i class="fas fa-chalkboard-teacher icon-bg"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-box bg-gradient-3 h-100 text-dark">
                        <div class="card-body">
                            <h5 class="card-title text-uppercase opacity-75" style="font-size: 0.9rem;">Lớp học</h5>
                            <h2 class="display-6 fw-bold"><?= $total_classes ?></h2>
                            <i class="fas fa-school icon-bg"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-box bg-gradient-4 h-100 text-dark">
                        <div class="card-body">
                            <h5 class="card-title text-uppercase opacity-75" style="font-size: 0.9rem;">Tin tức</h5>
                            <h2 class="display-6 fw-bold"><?= $total_news ?></h2>
                            <i class="fas fa-newspaper icon-bg"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-6">
                    <div class="chart-container">
                        <h6 class="fw-bold mb-2 text-secondary"><i class="fas fa-chart-bar me-2"></i>Thống kê Lớp học</h6>
                        <div class="chart-canvas-wrapper">
                            <canvas id="barChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="chart-container">
                        <h6 class="fw-bold mb-2 text-secondary"><i class="fas fa-chart-pie me-2"></i>Tỉ lệ Thành viên</h6>
                        <div class="chart-canvas-wrapper" style="padding: 10px;">
                            <canvas id="pieChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

        </div> </div> </div> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // 1. Toggle Sidebar
    var el = document.getElementById("wrapper");
    var toggleButton = document.getElementById("sidebarToggle");
    toggleButton.onclick = function () {
        el.classList.toggle("sb-sidenav-toggled");
    };

    // 2. Cấu hình Biểu đồ Cột
    const ctxBar = document.getElementById('barChart').getContext('2d');
    new Chart(ctxBar, {
        type: 'bar',
        data: {
            labels: <?= json_encode($dept_labels) ?>, 
            datasets: [{
                label: 'Số lớp',
                data: <?= json_encode($dept_data) ?>,
                backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0'],
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // Quan trọng để chỉnh size theo css
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } }
            },
            plugins: { legend: { display: false } } // Ẩn chú thích cho gọn
        }
    });

    // 3. Cấu hình Biểu đồ Tròn
    const ctxPie = document.getElementById('pieChart').getContext('2d');
    new Chart(ctxPie, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($role_labels) ?>,
            datasets: [{
                data: <?= json_encode($role_data) ?>,
                backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56'],
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // Quan trọng
            plugins: {
                legend: { position: 'right' } // Đưa chú thích sang phải cho gọn
            }
        }
    });
</script>

</body>
</html>