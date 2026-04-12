<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: ../../../login.php"); exit; }
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý Môn học</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="../../../assets/css/admin.css">
</head>
<body>
<div class="d-flex" id="wrapper">
    <?php include '../../includes/sidebar.php'; ?>
    <div id="page-content-wrapper" class="w-100 bg-light">
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm px-4 py-3">
            <button class="btn btn-light me-3" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h4 class="m-0 fw-bold text-primary text-uppercase">Danh mục Môn học</h4>
        </nav>
        <div class="container-fluid px-4 py-4">
            <div class="card border-0 shadow rounded-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center bg-white">
                    <input type="text" id="search-input" class="form-control rounded-pill w-50" placeholder="Tìm tên môn học...">
                    <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" onclick="openModal()"><i class="fas fa-plus me-2"></i>Thêm Môn</button>
                </div>
                <div class="card-body p-0">
                    <table class="table align-middle mb-0 table-hover text-center">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Tên Môn Học</th>
                                <th class="text-end pe-4">Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody id="table-body"></tbody>
                    </table>
                </div>
                <div class="card-footer bg-white border-0 py-3"><ul class="pagination justify-content-end mb-0" id="pagination"></ul></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="subjectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title fw-bold">Thông tin Môn học</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="subjectForm">
                    <input type="hidden" name="id" id="subject_id">
                    <input type="hidden" name="action" id="action">
                    <div class="mb-3">
                        <label class="fw-bold small mb-1">Tên môn học</label>
                        <input type="text" name="name" id="name" class="form-control" required placeholder="VD: Lập trình PHP">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold shadow mt-2">Lưu dữ liệu</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let curP = 1, searchQ = '';
    $(document).ready(() => {
        load();
        $('#sidebarToggle').click(() => $('#wrapper').toggleClass('sb-sidenav-toggled'));
        $('#search-input').keyup(function(){ searchQ = $(this).val(); curP = 1; load(); });
        $('#subjectForm').submit(function(e){
            e.preventDefault();
            $.post('process.php', $(this).serialize(), r => {
                if(r.status==='success'){ $('#subjectModal').modal('hide'); Swal.fire('Thành công', r.message, 'success'); load(); }
            });
        });
    });

    function load(){
        $.post('process.php', {action:'fetch', page:curP, search:searchQ}, r => {
            let h = '';
            r.data.forEach(i => {
                h += `<tr>
                    <td>#${i.id}</td>
                    <td class="fw-bold">${i.name}</td>
                    <td class="text-end pe-4">
                        <button class="btn btn-sm btn-info text-white rounded-circle" onclick="edit(${i.id})"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-danger rounded-circle" onclick="del(${i.id})"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>`;
            });
            $('#table-body').html(h);
            let p = '';
            for(let j=1; j<=r.pagination.total_pages; j++) p += `<li class="page-item ${j==curP?'active':''}"><button class="page-link" onclick="curP=${j};load()">${j}</button></li>`;
            $('#pagination').html(p);
        });
    }

    function openModal(){ $('#subjectForm')[0].reset(); $('#subject_id').val(''); $('#action').val('add'); $('#subjectModal').modal('show'); }

    function edit(id){
        $.post('process.php', {action:'get_one', id:id}, r => {
            let d = r.data; $('#subject_id').val(d.id); $('#name').val(d.name); $('#action').val('update'); $('#subjectModal').modal('show');
        });
    }

    function del(id){
        Swal.fire({title:'Xóa môn học này?', icon:'warning', showCancelButton:true, confirmButtonText:'Xóa'}).then(res => {
            if(res.isConfirmed) $.post('process.php', {action:'delete', id:id}, r => { if(r.status==='success') load(); });
        });
    }
</script>
</body>
</html>