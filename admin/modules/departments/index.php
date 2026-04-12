<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý Khoa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <link rel="stylesheet" href="../../../assets/css/admin.css">
    
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="d-flex" id="wrapper">
    <?php include '../../includes/sidebar.php'; ?>

    <div id="page-content-wrapper" class="w-100 bg-light">
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm px-4 py-3">
            <button class="btn btn-light me-3" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h4 class="m-0 fw-bold text-primary">QUẢN LÝ KHOA</h4>
        </nav>

        <div class="container-fluid px-4 py-4">
            <div class="card border-0 shadow rounded-4 overflow-hidden">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex gap-2 w-50">
                        <input type="text" id="search-input" class="form-control rounded-pill border-0 shadow-sm" placeholder="Nhập tên khoa để tìm kiếm...">
                    </div>
                    <button class="btn btn-light text-primary rounded-pill px-4 fw-bold shadow-sm" onclick="openModal()">
                        <i class="fas fa-plus me-2"></i>Thêm Mới
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="bg-light text-secondary">
                                <tr>
                                    <th class="ps-4 py-3">ID</th>
                                    <th>Tên Khoa</th>
                                    <th>Mô Tả</th>
                                    <th class="text-end pe-4">Thao Tác</th>
                                </tr>
                            </thead>
                            <tbody id="table-body">
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white border-0 py-3">
                    <nav>
                        <ul class="pagination justify-content-end mb-0" id="pagination"></ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deptModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 rounded-top-4">
                <h5 class="modal-title fw-bold" id="modalTitle">Thêm Khoa Mới</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="deptForm">
                    <input type="hidden" id="dept_id" name="id">
                    <input type="hidden" id="action" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">Tên Khoa</label>
                        <input type="text" class="form-control" name="name" id="name" required placeholder="Ví dụ: Khoa CNTT">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">Mô tả</label>
                        <textarea class="form-control" name="description" id="description" rows="3" placeholder="Mô tả ngắn gọn về khoa..."></textarea>
                    </div>
                    <div class="d-grid pt-2">
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill fw-bold shadow-sm">Lưu Dữ Liệu</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    let currentPage = 1;
    let searchQuery = '';

    $(document).ready(function() {
        loadData();

        $('#sidebarToggle').on('click', function() {
            $('#wrapper').toggleClass('sb-sidenav-toggled');
        });

        $('#search-input').on('keyup', function() {
            searchQuery = $(this).val();
            currentPage = 1;
            loadData();
        });

        $('#deptForm').on('submit', function(e) {
            e.preventDefault();
            let formData = $(this).serialize();
            $.post('process.php', formData, function(res) {
                if (res.status === 'success') {
                    $('#deptModal').modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: 'Thành công',
                        text: res.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    loadData();
                } else {
                    Swal.fire('Lỗi', res.message, 'error');
                }
            });
        });
    });

    function loadData() {
        $.post('process.php', { action: 'fetch', page: currentPage, search: searchQuery }, function(res) {
            if (res.status === 'success') {
                let html = '';
                res.data.forEach(item => {
                    html += `<tr>
                        <td class="ps-4 fw-bold text-secondary">#${item.id}</td>
                        <td class="fw-bold text-primary">${item.name}</td>
                        <td class="text-muted">${item.description || ''}</td>
                        <td class="text-end pe-4">
                            <button class="btn btn-outline-info btn-circle me-2 rounded-circle" onclick="editData(${item.id})"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-outline-danger btn-circle rounded-circle" onclick="deleteData(${item.id})"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>`;
                });
                $('#table-body').html(html);
                renderPagination(res.pagination);
            }
        });
    }

    function renderPagination(pagination) {
        let html = '';
        let total = pagination.total_pages;
        let current = pagination.current_page;
        
        if (total > 1) {
            html += `<li class="page-item ${current === 1 ? 'disabled' : ''}"><button class="page-link border-0 rounded-circle mx-1" onclick="changePage(${current - 1})"><i class="fas fa-chevron-left"></i></button></li>`;
            for (let i = 1; i <= total; i++) {
                html += `<li class="page-item ${i === current ? 'active' : ''}"><button class="page-link border-0 rounded-circle mx-1" onclick="changePage(${i})">${i}</button></li>`;
            }
            html += `<li class="page-item ${current === total ? 'disabled' : ''}"><button class="page-link border-0 rounded-circle mx-1" onclick="changePage(${current + 1})"><i class="fas fa-chevron-right"></i></button></li>`;
        }
        $('#pagination').html(html);
    }

    function changePage(page) {
        if (page < 1) return;
        currentPage = page;
        loadData();
    }

    function openModal() {
        $('#deptForm')[0].reset();
        $('#dept_id').val('');
        $('#action').val('add');
        $('#modalTitle').text('Thêm Khoa Mới');
        $('#deptModal').modal('show');
    }

    function editData(id) {
        $.post('process.php', { action: 'get_one', id: id }, function(res) {
            if (res.status === 'success') {
                $('#dept_id').val(res.data.id);
                $('#name').val(res.data.name);
                $('#description').val(res.data.description);
                $('#action').val('update');
                $('#modalTitle').text('Cập Nhật Khoa');
                $('#deptModal').modal('show');
            }
        });
    }

    function deleteData(id) {
        Swal.fire({
            title: 'Bạn chắc chắn?',
            text: "Dữ liệu sẽ bị xóa vĩnh viễn!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Xóa ngay',
            cancelButtonText: 'Hủy'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('process.php', { action: 'delete', id: id }, function(res) {
                    if (res.status === 'success') {
                        Swal.fire('Đã xóa!', res.message, 'success');
                        loadData();
                    } else {
                        Swal.fire('Lỗi', res.message, 'error');
                    }
                });
            }
        });
    }
</script>
</body>
</html>