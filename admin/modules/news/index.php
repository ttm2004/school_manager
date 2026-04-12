<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: ../../../login.php"); exit; }
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý Tin tức & Slide</title>
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
            <h4 class="m-0 fw-bold text-primary">TIN TỨC & SLIDE BANNER</h4>
        </nav>
        <div class="container-fluid px-4 py-4">
            <div class="card border-0 shadow rounded-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center bg-white">
                    <input type="text" id="search-input" class="form-control rounded-pill w-50" placeholder="Tìm kiếm tiêu đề...">
                    <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" onclick="openModal()"><i class="fas fa-plus me-2"></i>Thêm Mới</button>
                </div>
                <div class="card-body p-0">
                    <table class="table align-middle mb-0 table-hover">
                        <thead>
                            <tr class="table-light">
                                <th class="ps-4">Hình ảnh</th>
                                <th>Tiêu đề</th>
                                <th>Phân loại</th>
                                <th>Ngày đăng</th>
                                <th class="text-end pe-4">Thao tác</th>
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

<div class="modal fade" id="newsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-primary text-white border-0 rounded-top-4">
                <h5 class="modal-title fw-bold">Nội dung hiển thị</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="newsForm" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="news_id"><input type="hidden" name="action" id="action">
                    <div class="row">
                        <div class="col-md-5">
                            <label class="fw-bold mb-2">Ảnh minh họa</label>
                            <img id="previewImg" src="" class="img-fluid rounded border shadow-sm mb-2 w-100" style="height:200px; object-fit:cover">
                            <input type="file" name="image" class="form-control form-control-sm" onchange="preview(this)">
                        </div>
                        <div class="col-md-7">
                            <div class="mb-3"><label class="fw-bold small">Tiêu đề</label><input type="text" name="title" id="title" class="form-control" required></div>
                            <div class="mb-3">
                                <label class="fw-bold small">Loại nội dung</label>
                                <select name="type" id="type" class="form-select">
                                    <option value="news">Tin tức</option>
                                    <option value="slide">Slide Banner</option>
                                </select>
                            </div>
                            <div class="mb-3"><label class="fw-bold small">Nội dung chi tiết/tóm tắt</label><textarea name="content" id="content" class="form-control" rows="4"></textarea></div>
                        </div>
                    </div>
                    <div class="text-end mt-3"><button type="submit" class="btn btn-primary px-5 rounded-pill fw-bold shadow-sm">Lưu dữ liệu</button></div>
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
    const preview = i => { if(i.files[0]){ let r = new FileReader(); r.onload = e => $('#previewImg').attr('src', e.target.result); r.readAsDataURL(i.files[0]); } };
    
    $(document).ready(() => {
        load();
        $('#sidebarToggle').click(() => $('#wrapper').toggleClass('sb-sidenav-toggled'));
        $('#search-input').keyup(function(){ searchQ = $(this).val(); curP = 1; load(); });
        
        $('#newsForm').submit(function(e){
            e.preventDefault();
            $.ajax({ 
                url:'process.php', 
                type:'POST', 
                data: new FormData(this), 
                contentType:false, 
                processData:false, 
                success: r => {
                    if(r.status==='success'){ 
                        $('#newsModal').modal('hide'); 
                        Swal.fire({ icon: 'success', title: 'Thành công', text: r.message, timer: 1500, showConfirmButton: false });
                        load(); 
                    } else {
                        Swal.fire('Lỗi', r.message, 'error');
                    }
                }
            });
        });
    });

    function load(){
        $.post('process.php', {action:'fetch', page:curP, search:searchQ}, r => {
            let h = '';
            r.data.forEach(i => {
                let img = i.image_url ? `../../../uploads/news/${i.image_url}` : 'images/partner1.png';
                let badge = i.type === 'slide' ? 'bg-warning text-dark' : 'bg-info text-white';
                h += `<tr>
                    <td class="ps-4"><img src="${img}" class="rounded shadow-sm" width="80" height="45" style="object-fit:cover"></td>
                    <td class="fw-bold text-truncate" style="max-width:250px">${i.title}</td>
                    <td><span class="badge ${badge}">${i.type.toUpperCase()}</span></td>
                    <td class="small text-muted">${i.created_at}</td>
                    <td class="text-end pe-4">
                        <button class="btn btn-sm btn-info text-white rounded-circle" onclick="edit(${i.id})"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-danger rounded-circle" onclick="del(${i.id})"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>`;
            });
            $('#table-body').html(h);
            let p = '';
            if(r.pagination.total_pages > 1) {
                for(let j=1; j<=r.pagination.total_pages; j++) p += `<li class="page-item ${j==curP?'active':''}"><button class="page-link" onclick="curP=${j};load()">${j}</button></li>`;
            }
            $('#pagination').html(p);
        });
    }

    function openModal(){
        $('#newsForm')[0].reset(); $('#news_id').val(''); $('#action').val('add');
        $('#previewImg').attr('src','images/partner1.png'); $('#newsModal').modal('show');
    }

    function edit(id){
        $.post('process.php', {action:'get_one', id:id}, r => {
            let d = r.data; $('#news_id').val(d.id); $('#title').val(d.title); $('#content').val(d.content);
            $('#type').val(d.type); $('#action').val('update');
            $('#previewImg').attr('src', d.image_url ? `../../../uploads/news/${d.image_url}` : 'images/partner1.png');
            $('#newsModal').modal('show');
        });
    }

    function del(id){
        Swal.fire({
            title: 'Xóa bài viết này?',
            text: "Dữ liệu sẽ biến mất khỏi trang chủ!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Đồng ý xóa',
            cancelButtonText: 'Hủy'
        }).then((res) => {
            if (res.isConfirmed) {
                $.post('process.php', {action:'delete', id:id}, r => {
                    if(r.status==='success'){ 
                        Swal.fire('Đã xóa!', r.message, 'success'); 
                        load(); 
                    }
                });
            }
        });
    }
</script>
</body>
</html>