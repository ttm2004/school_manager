<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: ../../../login.php"); exit; }
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý Giáo viên</title>
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
            <h4 class="m-0 fw-bold text-primary">QUẢN LÝ GIÁO VIÊN</h4>
        </nav>
        <div class="container-fluid px-4 py-4">
            <div class="card border-0 shadow rounded-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center bg-white">
                    <div class="d-flex gap-2 w-50">
                        <input type="text" id="search-input" class="form-control rounded-pill" placeholder="Tìm kiếm nhanh...">
                    </div>
                    <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" onclick="openModal()">
                        <i class="fas fa-plus me-2"></i>Thêm Mới
                    </button>
                </div>
                <div class="card-body p-0">
                    <table class="table align-middle mb-0 table-hover">
                        <thead>
                            <tr class="table-light">
                                <th class="ps-4">Giáo Viên</th>
                                <th>Liên Hệ</th>
                                <th>Tài Khoản</th>
                                <th class="text-end pe-4">Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody id="table-body"></tbody>
                    </table>
                </div>
                <div class="card-footer bg-white border-0 py-3">
                    <ul class="pagination justify-content-end mb-0" id="pagination"></ul>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="teacherModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-dark text-white border-0 rounded-top-4">
                <h5 class="modal-title fw-bold">Hồ Sơ Giáo Viên</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <ul class="nav nav-tabs px-4 pt-2 bg-light" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#info-tab" type="button">Thông tin cá nhân</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#teaching-tab" type="button">Lịch giảng dạy</button>
                    </li>
                </ul>
                <div class="tab-content p-4">
                    <div class="tab-pane fade show active" id="info-tab">
                        <form id="teacherForm">
                            <input type="hidden" name="id" id="teacher_id">
                            <input type="hidden" name="action" id="action">
                            <div class="row">
                                <div class="col-md-4 text-center">
                                    <div class="mb-3">
                                        <img id="previewAvatar" src="" class="rounded-circle border shadow-sm mb-3" width="140" height="140" style="object-fit:cover">
                                        <input type="file" name="avatar" class="form-control form-control-sm" accept="image/*" onchange="previewImage(this)">
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="row g-3">
                                        <div class="col-6"><label class="small fw-bold">Họ Tên</label><input type="text" name="full_name" id="full_name" class="form-control" required></div>
                                        <div class="col-6"><label class="small fw-bold">SĐT</label><input type="text" name="phone" id="phone" class="form-control"></div>
                                        <div class="col-12"><label class="small fw-bold">Địa chỉ</label><input type="text" name="address" id="address" class="form-control"></div>
                                        <div class="col-6"><label class="small fw-bold">Username</label><input type="text" name="username" id="username" class="form-control" required></div>
                                        <div class="col-6"><label class="small fw-bold">Mật khẩu</label><input type="password" name="password" id="password" class="form-control"></div>
                                        <div class="col-12"><label class="small fw-bold">Email</label><input type="email" name="email" id="email" class="form-control"></div>
                                    </div>
                                    <div class="mt-4 text-end">
                                        <button type="submit" class="btn btn-primary px-5 rounded-pill fw-bold">Lưu Hồ Sơ</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="tab-pane fade" id="teaching-tab">
                        <div class="alert alert-primary py-2 small"><i class="fas fa-info-circle me-2"></i>Phân công được quản lý tại module Lớp học</div>
                        <h6 class="fw-bold"><i class="fas fa-star text-warning me-2"></i>Lớp Chủ Nhiệm</h6>
                        <div class="scroll-section mb-4" id="homeroom-list"></div>
                        <h6 class="fw-bold"><i class="fas fa-book text-success me-2"></i>Lớp Bộ Môn</h6>
                        <div class="scroll-section" id="teaching-list"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let curP = 1, searchQ = '';
    function previewImage(i){if(i.files&&i.files[0]){let r=new FileReader();r.onload=e=>$('#previewAvatar').attr('src',e.target.result);r.readAsDataURL(i.files[0]);}}
    $(document).ready(()=> {
        load();
        $('#sidebarToggle').click(()=>$('#wrapper').toggleClass('sb-sidenav-toggled'));
        $('#search-input').keyup(function(){searchQ=$(this).val();curP=1;load();});
        $('#teacherForm').submit(function(e){
            e.preventDefault();
            $.ajax({url:'process.php',type:'POST',data:new FormData(this),contentType:false,processData:false,success:r=>{
                if(r.status==='success'){$('#teacherModal').modal('hide');Swal.fire('Xong!',r.message,'success');load();}
                else Swal.fire('Lỗi',r.message,'error');
            }});
        });
    });

    function load(){
        $.post('process.php',{action:'fetch',page:curP,search:searchQ},r=>{
            let h='';
            r.data.forEach(i=>{
                let av = i.avatar ? `../../../uploads/avatars/${i.avatar}` : `https://ui-avatars.com/api/?name=${i.full_name}`;
                h+=`<tr>
                    <td class="ps-4"><div class="d-flex align-items-center"><img src="${av}" class="rounded-circle me-3" width="40" height="40" style="object-fit:cover"><div><div class="fw-bold">${i.full_name}</div><div class="small text-muted">ID: #${i.id}</div></div></div></td>
                    <td><div class="small"><b>SĐT:</b> ${i.phone||'--'}</div><div class="small"><b>ĐC:</b> ${i.address||'--'}</div></td>
                    <td><div class="fw-bold">${i.username}</div><div class="small text-muted">${i.email||''}</div></td>
                    <td class="text-end pe-4">
                        <button class="btn btn-sm btn-info text-white rounded-circle" onclick="edit(${i.id})"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-danger rounded-circle" onclick="del(${i.id})"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>`;
            });
            $('#table-body').html(h);
            pagination(r.pagination);
        });
    }

    function pagination(p){
        let h='', t=p.total_pages, c=p.current_page;
        if(t>1){
            h+=`<li class="page-item ${c==1?'disabled':''}"><button class="page-link" onclick="changeP(${c-1})">«</button></li>`;
            for(let i=1;i<=t;i++) h+=`<li class="page-item ${i==c?'active':''}"><button class="page-link" onclick="changeP(${i})">${i}</button></li>`;
            h+=`<li class="page-item ${c==t?'disabled':''}"><button class="page-link" onclick="changeP(${c+1})">»</button></li>`;
        }
        $('#pagination').html(h);
    }

    function changeP(p){if(p>0){curP=p;load();}}

    function openModal(){
        $('#teacherForm')[0].reset();$('#teacher_id').val('');$('#action').val('add');$('#username').prop('readonly',false);
        $('#previewAvatar').attr('src','images/partner1.png');
        $('#homeroom-list').html('<div class="text-muted">Đang tạo...</div>');
        $('#teaching-list').html('<div class="text-muted">Đang tạo...</div>');
        new bootstrap.Tab($('[data-bs-target="#info-tab"]')[0]).show();
        $('#teacherModal').modal('show');
    }

    function edit(id){
        $.post('process.php',{action:'get_one',id:id},r=>{
            let d=r.data;
            $('#teacher_id').val(d.id);$('#full_name').val(d.full_name);$('#phone').val(d.phone);$('#address').val(d.address);
            $('#email').val(d.email);$('#username').val(d.username).prop('readonly',true);$('#action').val('update');
            $('#previewAvatar').attr('src',d.avatar?`../../../uploads/avatars/${d.avatar}`:`https://ui-avatars.com/api/?name=${d.full_name}`);
            
            let hr = r.homeroom.length ? r.homeroom.map(l=>`<div class="info-box">${l} <span class="badge bg-primary">Chủ nhiệm</span></div>`).join('') : '<div class="text-muted small">Không chủ nhiệm lớp nào</div>';
            $('#homeroom-list').html(hr);

            let tc = r.teaching.length ? r.teaching.map(l=>`<div class="info-box"><span>${l.class_name}</span> <span class="text-primary small">${l.subject_name}</span></div>`).join('') : '<div class="text-muted small">Không giảng dạy bộ môn</div>';
            $('#teaching-list').html(tc);

            new bootstrap.Tab($('[data-bs-target="#info-tab"]')[0]).show();
            $('#teacherModal').modal('show');
        });
    }

    function del(id){
        Swal.fire({title:'Xóa giáo viên?',icon:'warning',showCancelButton:true,confirmButtonText:'Xóa'}).then(r=>{
            if(r.isConfirmed)$.post('process.php',{action:'delete',id:id},res=>{
                if(res.status==='success'){Swal.fire('Xong!','','success');load();}
                else Swal.fire('Lỗi',res.message,'error');
            });
        });
    }
</script>
</body>
</html>