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
    <title>Bảng điểm | Student</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-color: #4e73df; --sidebar-width: 250px; }
        body { background: #f8f9fc; font-family: 'Inter', sans-serif; }
        .table-custom-header th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            color: #5a5c69;
            background-color: #f8f9fc;
            border-bottom: 2px solid #e3e6f0;
            padding: 1rem;
        }
        .table-hover tbody tr:hover { background-color: #f8f9fc; }
        .grade-badge { width: 35px; height: 35px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; font-weight: bold; font-size: 0.85rem; color: white; }
        .subject-main { font-weight: 600; color: #2e384d; margin-bottom: 2px; }
        .class-sub { font-size: 0.8rem; color: #858796; }
        .grade-val { font-weight: 500; color: #5a5c69; }
        .total-val { font-weight: 700; color: #4e73df; border: 2px solid #e3e6f0; padding: 5px 12px; border-radius: 20px; }
        .custom-scroll { max-height: 500px; overflow-y: auto; }
        .custom-scroll::-webkit-scrollbar { width: 6px; }
        .custom-scroll::-webkit-scrollbar-thumb { background-color: #d1d3e2; border-radius: 10px; }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include '../../includes/sidebar.php'; ?>
        <div class="flex-grow-1">
            <nav class="navbar navbar-light bg-white shadow-sm px-4 py-3">
                <button class="btn btn-light me-3 d-lg-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
                <h4 class="m-0 fw-bold text-primary">Kết quả học tập</h4>
            </nav>

            <div class="container-fluid p-4">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-4 text-dark">Bảng điểm tổng hợp</h5>
                        
                        <div class="table-responsive custom-scroll">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-custom-header sticky-top">
                                    <tr>
                                        <th style="width: 40%;">MÔN HỌC / LỚP</th>
                                        <th class="text-center">BÀI TẬP</th>
                                        <th class="text-center">THI</th>
                                        <th class="text-center">TỔNG KẾT</th>
                                        <th class="text-center">XẾP LOẠI</th>
                                    </tr>
                                </thead>
                                <tbody id="grade-list">
                                    <tr><td colspan="5" class="text-center py-5"><div class="spinner-border text-primary"></div></td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 pt-3 border-top small text-muted">
                            <i class="fas fa-info-circle me-1"></i> Cách tính điểm: Tổng kết = (Điểm Bài tập × 0.2) + (Điểm Thi × 0.8).
                        </div>
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
            loadGrades();
        });

        function loadGrades() {
            $.post('process.php', { action: 'get_grades' }, function(res) {
                let html = '';
                if (res.status === 'success' && res.data.length > 0) {
                    res.data.forEach(d => {
                        html += `
                            <tr>
                                <td class="ps-3">
                                    <div class="subject-main">${d.subject}</div>
                                    <div class="class-sub">${d.class_name}</div>
                                </td>
                                <td class="text-center"><span class="grade-val">${d.avg_asm}</span></td>
                                <td class="text-center"><span class="grade-val">${d.avg_exam}</span></td>
                                <td class="text-center">
                                    ${d.total !== '-' ? `<span class="total-val">${d.total}</span>` : '-'}
                                </td>
                                <td class="text-center">
                                    <span class="grade-badge ${d.rank_class}">${d.rank}</span>
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    html = '<tr><td colspan="5" class="text-center py-5 text-muted">Chưa có dữ liệu điểm số.</td></tr>';
                }
                $('#grade-list').html(html);
            }, 'json');
        }
    </script>
</body>
</html>