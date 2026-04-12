<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: ../../../login.php"); exit; }
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý Lớp học</title>
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
            <h4 class="m-0 fw-bold text-primary">QUẢN LÝ LỚP HỌC</h4>
        </nav>
        <div class="container-fluid px-4 py-4">
            <div class="card border-0 shadow rounded-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center bg-white">
                    <div class="d-flex gap-2 w-75">
                        <input type="text" id="search-input" class="form-control rounded-pill w-50" placeholder="Tìm tên lớp...">
                        <select id="filter-dept" class="form-select rounded-pill w-25" onchange="curP=1;load()">
                            <option value="">Tất cả khoa</option>
                        </select>
                    </div>
                    <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" onclick="openModal()"><i class="fas fa-plus me-2"></i>Thêm Lớp</button>
                </div>
                <div class="card-body p-0">
                    <table class="table align-middle mb-0 table-hover">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">ID</th>
                                <th>Tên Lớp</th>
                                <th>Khoa Trực Thuộc</th>
                                <th>GV Chủ Nhiệm</th>
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

<div class="modal fade" id="classModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-primary text-white border-0 rounded-top-4">
                <h5 class="modal-title fw-bold">Thông tin Lớp học</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="classForm">
                    <input type="hidden" name="id" id="class_id"><input type="hidden" name="action" id="action">
                    <div class="mb-3"><label class="small fw-bold">Tên lớp</label><input type="text" name="name" id="name" class="form-control" required></div>
                    <div class="mb-3"><label class="small fw-bold">Chọn Khoa</label><select name="department_id" id="department_id" class="form-select" required></select></div>
                    <div class="mb-3"><label class="small fw-bold">GV Chủ nhiệm</label><select name="teacher_id" id="teacher_id" class="form-select" required></select></div>
                    <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold py-2 mt-2 shadow">Lưu Dữ Liệu</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-dark text-white border-0 rounded-top-4">
                <h5 class="modal-title fw-bold">Phân công Giáo viên Bộ môn - Lớp <span id="displayClassName"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="assignForm">
                    <input type="hidden" name="class_id" id="assign_class_id">
                    <input type="hidden" name="action" value="save_assignments">
                    
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Môn học</th>
                                    <th>Giáo viên giảng dạy</th>
                                    <th width="50"></th>
                                </tr>
                            </thead>
                            <tbody id="subjectList"></tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm fw-bold mb-3" onclick="addSubjectRow()">
                        <i class="fas fa-plus me-1"></i>Thêm môn học
                    </button>
                    <button type="submit" class="btn btn-dark w-100 rounded-pill fw-bold py-2 shadow">Cập nhật phân công</button>
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
    let teachersCache = [], subjectsCache = [];

    $(document).ready(() => {
        load(); loadMeta();
        $('#sidebarToggle').click(() => $('#wrapper').toggleClass('sb-sidenav-toggled'));
        $('#search-input').keyup(function(){ searchQ = $(this).val(); curP = 1; load(); });
        
        // Submit Lớp
        $('#classForm').submit(function(e){
            e.preventDefault();
            $.post('process.php', $(this).serialize(), r => {
                if(r.status==='success'){ $('#classModal').modal('hide'); Swal.fire('Thành công', r.message, 'success'); load(); }
            });
        });

        // Submit Phân công
        $('#assignForm').submit(function(e){
            e.preventDefault();
            $.post('process.php', $(this).serialize(), r => {
                if(r.status==='success'){ $('#assignModal').modal('hide'); Swal.fire('Thành công', r.message, 'success'); }
            });
        });
    });

    function loadMeta() {
        $.post('process.php', {action:'get_metadata'}, r => {
            teachersCache = r.teachers;
            subjectsCache = r.subjects; // Bạn cần đảm bảo backend trả về subjects trong get_metadata
            
            let d = '<option value="">Chọn khoa</option>', t = '<option value="">Chọn GV</option>', f = '<option value="">Tất cả khoa</option>';
            r.depts.forEach(i => { d += `<option value="${i.id}">${i.name}</option>`; f += `<option value="${i.id}">${i.name}</option>`; });
            r.teachers.forEach(i => { t += `<option value="${i.id}">${i.full_name}</option>`; });
            $('#department_id').html(d); $('#filter-dept').html(f); $('#teacher_id').html(t);
        });
    }

    function load(){
        $.post('process.php', {action:'fetch', page:curP, search:searchQ, dept_id:$('#filter-dept').val()}, r => {
            let h = '';
            r.data.forEach(i => {
                h += `<tr>
                    <td class="ps-4 text-muted">#${i.id}</td>
                    <td class="fw-bold text-primary">${i.name}</td>
                    <td><span class="badge bg-light text-dark border">${i.dept_name || '--'}</span></td>
                    <td>${i.teacher_name || '<i class="text-danger small">Chưa phân công</i>'}</td>
                    <td class="text-end pe-4">
                        <button class="btn btn-sm btn-outline-dark me-1" onclick="openAssignment(${i.id}, '${i.name}')" title="Phân công môn học"><i class="fas fa-chalkboard-teacher"></i></button>
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

    function openModal(){ $('#classForm')[0].reset(); $('#class_id').val(''); $('#action').val('add'); $('#classModal').modal('show'); }

    function edit(id){
        $.post('process.php', {action:'get_one', id:id}, r => {
            let d = r.data; $('#class_id').val(d.id); $('#name').val(d.name); $('#department_id').val(d.department_id); $('#teacher_id').val(d.teacher_id);
            $('#action').val('update'); $('#classModal').modal('show');
        });
    }

    // Logic Phân công mới
    function openAssignment(classId, className) {
        $('#assign_class_id').val(classId);
        $('#displayClassName').text(className);
        $('#subjectList').html('');
        
        $.post('process.php', {action:'get_assignments', class_id:classId}, r => {
            if(r.data && r.data.length > 0) {
                r.data.forEach(item => addSubjectRow(item.subject_id, item.teacher_id));
            } else {
                addSubjectRow();
            }
            $('#assignModal').modal('show');
        });
    }

    function addSubjectRow(sId = '', tId = '') {
        let sOpt = '<option value="">-- Chọn Môn --</option>';
        subjectsCache.forEach(s => sOpt += `<option value="${s.id}" ${s.id == sId ? 'selected' : ''}>${s.name}</option>`);

        let tOpt = '<option value="">-- Chọn GV --</option>';
        teachersCache.forEach(t => tOpt += `<option value="${t.id}" ${t.id == tId ? 'selected' : ''}>${t.full_name}</option>`);

        let row = `<tr>
            <td><select name="subject_ids[]" class="form-select" required>${sOpt}</select></td>
            <td><select name="teacher_ids[]" class="form-select" required>${tOpt}</select></td>
            <td><button type="button" class="btn btn-link text-danger p-0" onclick="$(this).closest('tr').remove()"><i class="fas fa-times-circle fs-5"></i></button></td>
        </tr>`;
        $('#subjectList').append(row);
    }

    function del(id){
        Swal.fire({title:'Xóa lớp học?', icon:'warning', showCancelButton:true, confirmButtonText:'Xóa ngay'}).then(res => {
            if(res.isConfirmed) $.post('process.php', {action:'delete', id:id}, r => {
                if(r.status==='success') { Swal.fire('Xong', r.message, 'success'); load(); }
                else Swal.fire('Lỗi', r.message, 'error');
            });
        });
    }
</script>
</body>
</html>