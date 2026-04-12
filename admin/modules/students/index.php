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
    <title>Quản lý Học sinh</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="../../../assets/css/admin.css">
    <link rel="stylesheet" href="../teachers/css/style.css">
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include '../../includes/sidebar.php'; ?>
        <div id="page-content-wrapper" class="w-100 bg-light">
            <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm px-4 py-3">
                <button class="btn btn-light me-3" id="sidebarToggle"><i class="fas fa-bars"></i></button>
                <h4 class="m-0 fw-bold text-primary text-uppercase">Phân luồng học sinh</h4>
            </nav>
            <div class="container-fluid px-4 py-4">
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="small fw-bold text-muted">Lọc theo Khoa</label>
                                <select id="filter-dept" class="form-select rounded-pill" onchange="filterClasses(this.value)">
                                    <option value="">Tất cả Khoa</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold text-muted">Lọc theo Lớp</label>
                                <select id="filter-class" class="form-select rounded-pill" onchange="curP=1;load()">
                                    <option value="">Tất cả Lớp</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="small fw-bold text-muted">Tìm kiếm tên/SĐT</label>
                                <input type="text" id="search-input" class="form-control rounded-pill" placeholder="Nhập từ khóa...">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button class="btn btn-primary rounded-pill w-100 fw-bold shadow-sm" onclick="openModal()">
                                    <i class="fas fa-plus me-2"></i>Thêm Mới
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card border-0 shadow rounded-4">
                    <div class="card-body p-0">
                        <table class="table align-middle mb-0 table-hover">
                            <thead>
                                <tr class="table-light">
                                    <th class="ps-4">Học Sinh</th>
                                    <th>Khoa</th>
                                    <th>Lớp Học</th>
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

    <div class="modal fade" id="studentModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-dark text-white border-0">
                    <h5 class="modal-title fw-bold">Hồ Sơ Học Sinh</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="studentForm" enctype="multipart/form-data">
                        <input type="hidden" name="id" id="student_id">
                        <input type="hidden" name="action" id="action">
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <img id="previewAvatar" src="" class="rounded-circle border mb-3" width="140" height="140" style="object-fit:cover">
                                <input type="file" name="avatar" class="form-control form-control-sm" onchange="previewImage(this)">
                            </div>
                            <div class="col-md-8">
                                <div class="row g-2">
                                    <div class="col-6"><label class="small fw-bold">Họ Tên</label><input type="text" name="full_name" id="full_name" class="form-control" required></div>
                                    <div class="col-6"><label class="small fw-bold">SĐT</label><input type="text" name="phone" id="phone" class="form-control"></div>
                                    <div class="col-12"><label class="small fw-bold">Khoa trực thuộc</label><select name="department_id" id="department_id" class="form-select" required></select></div>
                                    <div class="col-12"><label class="small fw-bold">Địa chỉ</label><input type="text" name="address" id="address" class="form-control"></div>
                                    <div class="col-6"><label class="small fw-bold">Username</label><input type="text" name="username" id="username" class="form-control" required></div>
                                    <div class="col-6"><label class="small fw-bold">Mật khẩu</label><input type="password" name="password" id="password" class="form-control"></div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 mt-4 rounded-pill fw-bold">Lưu Hồ Sơ</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="assignClassModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title fw-bold">Xếp Lớp: <span id="displayStudentName"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="assignClassForm">
                        <input type="hidden" name="student_id" id="assign_student_id">
                        <input type="hidden" name="action" value="save_student_classes">
                        <table class="table table-bordered align-middle text-center">
                            <thead class="table-light">
                                <tr><th>Lớp Học</th><th width="50">Xóa</th></tr>
                            </thead>
                            <tbody id="studentClassList"></tbody>
                        </table>
                        <button type="button" class="btn btn-outline-secondary btn-sm mb-3" onclick="addClassRow()"><i class="fas fa-plus me-1"></i>Thêm lớp</button>
                        <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold">Cập nhật</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="assignSubjectModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-success text-white border-0">
                    <h5 class="modal-title fw-bold">Đăng ký môn: <span id="subjStudentName"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="assignSubjectForm">
                        <input type="hidden" name="student_id" id="subj_student_id">
                        <input type="hidden" name="action" value="save_student_subjects">
                        <div class="mb-3">
                            <label class="small fw-bold">Chọn lớp để đăng ký môn</label>
                            <select id="subj_class_select" name="class_id" class="form-select rounded-pill" onchange="loadSubjectsOfClass(this.value)" required></select>
                        </div>
                        <div id="subjectRegistrationArea">
                            <label class="small fw-bold text-primary mb-2"><i class="fas fa-plus-circle me-1"></i> Môn học chưa đăng ký</label>
                            <div id="availableSubjects" class="border rounded p-3 bg-white mb-3" style="max-height: 200px; overflow-y: auto;"></div>

                            <label class="small fw-bold text-success mb-2"><i class="fas fa-check-double me-1"></i> Môn học đã đăng ký</label>
                            <div id="registeredSubjects" class="border rounded p-3 bg-light" style="max-height: 200px; overflow-y: auto;"></div>
                        </div>
                        <button type="submit" class="btn btn-success w-100 rounded-pill fw-bold mt-3 shadow">Lưu đăng ký</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let curP = 1, searchQ = '', classesCache = [];
        const previewImage = i => {
            if (i.files[0]) {
                let r = new FileReader();
                r.onload = e => $('#previewAvatar').attr('src', e.target.result);
                r.readAsDataURL(i.files[0]);
            }
        };

        $(document).ready(() => {
            load();
            loadInitMeta();
            $('#sidebarToggle').click(() => $('#wrapper').toggleClass('sb-sidenav-toggled'));
            $('#search-input').keyup(function() {
                searchQ = $(this).val();
                curP = 1;
                load();
            });

            $('#studentForm').submit(function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'process.php',
                    type: 'POST',
                    data: new FormData(this),
                    contentType: false,
                    processData: false,
                    success: r => {
                        if (r.status === 'success') {
                            $('#studentModal').modal('hide');
                            Swal.fire('Thành công', r.message, 'success');
                            load();
                        }
                    }
                });
            });

            $('#assignClassForm').submit(function(e) {
                e.preventDefault();
                $.post('process.php', $(this).serialize(), r => {
                    if (r.status === 'success') {
                        $('#assignClassModal').modal('hide');
                        Swal.fire('Thành công', r.message, 'success');
                        load();
                    }
                });
            });

            $('#assignSubjectForm').submit(function(e) {
                e.preventDefault();
                $.post('process.php', $(this).serialize(), r => {
                    if (r.status === 'success') {
                        $('#assignSubjectModal').modal('hide');
                        Swal.fire('Thành công', r.message, 'success');
                    } else Swal.fire('Lỗi', r.message, 'error');
                });
            });
        });

        function loadInitMeta() {
            $.post('process.php', { action: 'get_metadata' }, r => {
                let d = '<option value="">Tất cả Khoa</option>', dm = '<option value="">-- Chọn Khoa --</option>';
                r.depts.forEach(i => {
                    d += `<option value="${i.id}">${i.name}</option>`;
                    dm += `<option value="${i.id}">${i.name}</option>`;
                });
                $('#filter-dept').html(d);
                $('#department_id').html(dm);
            });
        }

        function filterClasses(deptId) {
            $('#filter-class').html('<option value="">Đang tải...</option>');
            if (!deptId) {
                $('#filter-class').html('<option value="">Tất cả Lớp</option>');
                curP = 1; load(); return;
            }
            $.post('process.php', { action: 'get_classes_by_dept', dept_id: deptId }, r => {
                let h = '<option value="">Tất cả Lớp</option>';
                r.data.forEach(i => h += `<option value="${i.id}">${i.name}</option>`);
                $('#filter-class').html(h);
                curP = 1; load();
            });
        }

        function load() {
            let deptId = $('#filter-dept').val();
            let classId = $('#filter-class').val();
            $.post('process.php', {
                action: 'fetch',
                page: curP,
                search: searchQ,
                dept_id: deptId,
                class_id: classId
            }, r => {
                let h = '';
                r.data.forEach(i => {
                    let av = i.avatar ? `../../../uploads/avatars/${i.avatar}` : `https://ui-avatars.com/api/?name=${i.full_name}`;
                    h += `<tr>
                    <td class="ps-4"><div class="d-flex align-items-center"><img src="${av}" class="rounded-circle me-3" width="40" height="40" style="object-fit:cover"><div><div class="fw-bold">${i.full_name}</div><div class="small text-muted">ID: #${i.id}</div></div></div></td>
                    <td><span class="text-secondary small">${i.dept_name || 'Chưa gán'}</span></td>
                    <td><span class="badge bg-light text-dark border p-2 fw-normal">${i.classes || '--'}</span></td>
                    <td><div class="small"><b>User:</b> ${i.username}</div><div class="small"><b>SĐT:</b> ${i.phone||'--'}</div></td>
                    <td class="text-end pe-4">
                        <button class="btn btn-sm btn-outline-success me-1" onclick="openAssignSubject(${i.id}, '${i.full_name}')" title="Đăng ký môn"><i class="fas fa-book"></i></button>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="openAssignClass(${i.id}, '${i.full_name}', ${i.department_id})" title="Xếp lớp"><i class="fas fa-school"></i></button>
                        <button class="btn btn-sm btn-info text-white rounded-circle" onclick="edit(${i.id})"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-danger rounded-circle" onclick="del(${i.id})"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>`;
                });
                $('#table-body').html(h);
                let p = '';
                if (r.pagination.total_pages > 1) {
                    for (let j = 1; j <= r.pagination.total_pages; j++) p += `<li class="page-item ${j==curP?'active':''}"><button class="page-link" onclick="curP=${j};load()">${j}</button></li>`;
                }
                $('#pagination').html(p);
            });
        }

        function openModal() {
            $('#studentForm')[0].reset();
            $('#student_id').val('');
            $('#action').val('add');
            $('#previewAvatar').attr('src', 'images/partner1.png');
            $('#studentModal').modal('show');
        }

        function edit(id) {
            $.post('process.php', { action: 'get_one', id: id }, r => {
                let d = r.data;
                $('#student_id').val(d.id);
                $('#full_name').val(d.full_name);
                $('#phone').val(d.phone);
                $('#address').val(d.address);
                $('#email').val(d.email);
                $('#username').val(d.username).prop('readonly', true);
                $('#department_id').val(d.department_id);
                $('#action').val('update');
                $('#previewAvatar').attr('src', d.avatar ? `../../../uploads/avatars/${d.avatar}` : `https://ui-avatars.com/api/?name=${d.full_name}`);
                $('#studentModal').modal('show');
            });
        }

        function openAssignClass(id, name, deptId) {
            if (!deptId) {
                Swal.fire('Cảnh báo', 'Vui lòng gán Khoa cho học sinh này trước khi xếp lớp!', 'warning');
                return;
            }
            $('#assign_student_id').val(id);
            $('#displayStudentName').text(name);
            $('#studentClassList').html('');
            $.post('process.php', { action: 'get_classes_by_dept', dept_id: deptId }, r => {
                classesCache = r.data;
                $.post('process.php', { action: 'get_student_classes', id: id }, res => {
                    if (res.data.length > 0) res.data.forEach(cId => addClassRow(cId));
                    else addClassRow();
                    $('#assignClassModal').modal('show');
                });
            });
        }

        function addClassRow(selectedId = '') {
            let opt = '<option value="">-- Chọn Lớp --</option>';
            classesCache.forEach(c => opt += `<option value="${c.id}" ${c.id == selectedId ? 'selected' : ''}>${c.name}</option>`);
            let row = `<tr><td><select name="class_ids[]" class="form-select" required>${opt}</select></td><td><button type="button" class="btn btn-link text-danger p-0" onclick="$(this).closest('tr').remove()"><i class="fas fa-times-circle fs-5"></i></button></td></tr>`;
            $('#studentClassList').append(row);
        }

        function openAssignSubject(id, name) {
            $('#subj_student_id').val(id);
            $('#subjStudentName').text(name);
            $('#subj_class_select').html('<option value="">Đang tải lớp học...</option>');
            $('#availableSubjects, #registeredSubjects').html('<p class="text-muted small m-0 text-center">Chọn lớp để xem</p>');
            $.post('process.php', { action: 'get_student_classes', id: id }, r => {
                if (r.status === 'success' && r.data.length > 0) {
                    let studentClassIds = r.data.map(cid => cid.toString());
                    $.post('process.php', { action: 'get_metadata' }, meta => {
                        let opt = '<option value="">-- Chọn lớp học --</option>';
                        meta.classes.forEach(c => {
                            if (studentClassIds.includes(c.id.toString())) opt += `<option value="${c.id}">${c.name}</option>`;
                        });
                        $('#subj_class_select').html(opt);
                        $('#assignSubjectModal').modal('show');
                    });
                } else {
                    Swal.fire('Chú ý', 'Học sinh này chưa tham gia lớp học nào!', 'warning');
                }
            });
        }

        function loadSubjectsOfClass(classId) {
            if (!classId) {
                $('#availableSubjects, #registeredSubjects').html('<p class="text-muted small m-0 text-center">Chọn lớp để xem</p>');
                return;
            }
            let studentId = $('#subj_student_id').val();
            $('#availableSubjects, #registeredSubjects').html('<p class="text-center small">Đang tải...</p>');
            $.post('process.php', { action: 'get_class_subjects', class_id: classId }, r => {
                $.post('process.php', { action: 'get_student_subjects', student_id: studentId }, res => {
                    let htmlAvailable = '', htmlRegistered = '', countAvailable = 0;
                    if (r.status === 'success' && r.data.length > 0) {
                        let registeredIds = res.data.map(id => id.toString());
                        r.data.forEach(s => {
                            if (registeredIds.includes(s.id.toString())) {
                                htmlRegistered += `<div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom"><span class="fw-bold text-success small"><i class="fas fa-check me-2"></i>${s.name}</span><input type="hidden" name="subject_ids[]" value="${s.id}"></div>`;
                            } else {
                                countAvailable++;
                                htmlAvailable += `<div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="subject_ids[]" value="${s.id}" id="sub${s.id}"><label class="form-check-label small fw-bold" for="sub${s.id}">${s.name}</label></div>`;
                            }
                        });
                        if (countAvailable === 0) htmlAvailable = '<p class="text-center text-danger small fw-bold m-0">🎉 Không còn môn nào cần đăng ký nữa</p>';
                        if (htmlRegistered === '') htmlRegistered = '<p class="text-muted small text-center m-0">Chưa có môn nào</p>';
                    } else {
                        htmlAvailable = htmlRegistered = '<p class="text-muted small text-center m-0">Không có dữ liệu</p>';
                    }
                    $('#availableSubjects').html(htmlAvailable);
                    $('#registeredSubjects').html(htmlRegistered);
                });
            });
        }

        function del(id) {
            Swal.fire({
                title: 'Xóa học sinh này?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Xóa ngay'
            }).then(result => {
                if (result.isConfirmed) $.post('process.php', { action: 'delete', id: id }, r => { if (r.status === 'success') load(); });
            });
        }
    </script>
</body>
</html>