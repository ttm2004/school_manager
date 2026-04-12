<?php
session_start();
$class_id = (int)($_GET['class_id'] ?? 0);
$subject_id = (int)($_GET['subject_id'] ?? 0);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student' || !$class_id || !$subject_id) {
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết môn học | LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --sidebar-width: 250px;
        }

        body {
            background-color: #f8f9fc;
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
        }

        .main-content {
            flex: 1;
            min-width: 0;
        }

        .nav-pills .nav-link {
            color: #6e707e;
            font-weight: 600;
            border-radius: 10px;
            padding: 10px 20px;
            transition: 0.3s;
        }

        .nav-pills .nav-link.active {
            background-color: var(--primary-color);
            box-shadow: 0 4px 15px rgba(78, 115, 223, 0.3);
        }

        .content-card {
            border: none;
            border-radius: 15px;
            background: white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        }

        .asm-item {
            border: 1px solid #e3e6f0;
            border-radius: 12px;
            transition: 0.3s;
            background: white;
            padding: 1.25rem;
            margin-bottom: 1rem;
        }

        .asm-item:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        .avatar-circle {
            width: 35px;
            height: 35px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <header class="bg-white shadow-sm p-3 d-flex justify-content-between align-items-center px-4">
                <div class="d-flex align-items-center">
                    <a href="index.php" class="btn btn-light btn-sm rounded-circle me-3"><i class="fas fa-arrow-left"></i></a>
                    <h5 class="m-0 fw-bold text-dark">Nội dung học tập</h5>
                </div>
                <div class="avatar-circle">
                    <?php
                    $name_parts = explode(' ', $_SESSION['full_name']);
                    echo strtoupper(substr(end($name_parts), 0, 1));
                    ?>
                </div>
            </header>

            <main class="p-4">
                <div class="mb-4">
                    <h3 class="fw-bold mb-1 text-primary" id="subject-name-title">Đang tải...</h3>
                    <p class="text-muted small"><i class="fas fa-chalkboard me-2"></i>Lớp: <span id="class-name-badge">...</span></p>
                </div>

                <ul class="nav nav-pills mb-4 bg-white p-2 rounded-3 shadow-sm d-inline-flex">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-asm" onclick="loadAssignments()">Bài tập</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-att" onclick="loadAttendance()">Điểm danh</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-exam" onclick="loadExams()">Đề thi</button></li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-news" onclick="loadClassNews()">Bảng tin</button>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab-asm">
                        <div id="asm-list"></div>
                        <nav>
                            <ul class="pagination pagination-sm justify-content-center mt-3" id="asm-pagination"></ul>
                        </nav>
                    </div>

                    <div class="tab-pane fade" id="tab-att">
                        <div class="content-card p-4">
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Ngày học</th>
                                            <th>Thời gian</th>
                                            <th class="text-center">Trạng thái</th>
                                        </tr>
                                    </thead>
                                    <tbody id="att-list"></tbody>
                                </table>
                            </div>
                            <nav>
                                <ul class="pagination pagination-sm justify-content-center mt-3" id="att-pagination"></ul>
                            </nav>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-news">
                        <div id="class-news-list">
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-exam">
                        <div id="exam-list" class="row g-3"></div>
                        <nav>
                            <ul class="pagination pagination-sm justify-content-center mt-3" id="exam-pagination"></ul>
                        </nav>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div class="modal fade" id="submitModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <form id="submitForm" enctype="multipart/form-data">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="fw-bold">Gửi bài làm</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="submit_work">
                        <input type="hidden" name="assignment_id" id="submit_aid">
                        <div class="mb-3">
                            <label class="small fw-bold mb-1">Link bài làm (Drive/Github...)</label>
                            <input type="url" name="external_link" class="form-control" placeholder="https://...">
                        </div>
                        <div class="mb-0">
                            <label class="small fw-bold mb-1">Tải tệp đính kèm</label>
                            <input type="file" name="attachment" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold">Xác nhận nộp bài</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const cId = <?= $class_id ?>,
            sId = <?= $subject_id ?>;

        $(document).ready(() => loadAssignments(1));

        function renderPagination(total, current, target, func) {
            let h = '';
            for (let i = 1; i <= total; i++) {
                h += `<li class="page-item ${i == current ? 'active' : ''}"><button class="page-link" onclick="${func}(${i})">${i}</button></li>`;
            }
            $(`#${target}`).html(total > 1 ? h : '');
        }

        function loadAssignments(p = 1) {
            $.post('student_process.php', {
                action: 'get_assignments',
                class_id: cId,
                subject_id: sId,
                page: p
            }, r => {
                if (r.status === 'success') {
                    if (r.info) {
                        $('#subject-name-title').text(r.info.subject_name);
                        $('#class-name-badge').text(r.info.class_name);
                    }
                    let h = '';
                    if (r.data.length > 0) {
                        r.data.forEach(a => {
                            let isExp = r.now > a.deadline;
                            let status = a.submitted_at ? `<span class="badge bg-success-subtle text-success px-3 rounded-pill py-2">Đã nộp: ${a.grade || 'Chờ chấm'}đ</span>` :
                                (isExp ? `<span class="badge bg-danger-subtle text-danger px-3 rounded-pill py-2">Quá hạn</span>` :
                                    `<button class="btn btn-sm btn-primary rounded-pill px-4" onclick="openSubmit(${a.id})">Nộp bài</button>`);
                            h += `<div class="asm-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="fw-bold mb-1 text-dark">${a.title}</h6>
                                        <div class="small text-muted mb-2"><i class="far fa-clock me-1"></i>Hạn nộp: ${a.deadline_f}</div>
                                        <div class="d-flex gap-2">
                                            ${a.external_link ? `<a href="${a.external_link}" target="_blank" class="btn btn-xs btn-outline-primary py-0 px-2 small shadow-none"><i class="fas fa-link me-1"></i>Đề bài</a>` : ''}
                                            ${a.file_path ? `<a href="../../../uploads/assignments/${a.file_path}" target="_blank" class="btn btn-xs btn-outline-success py-0 px-2 small shadow-none"><i class="fas fa-download me-1"></i>Tài liệu</a>` : ''}
                                        </div>
                                    </div>
                                    <div class="text-end">${status}</div>
                                  </div>`;
                        });
                    } else h = '<div class="text-center py-5 text-muted">Chưa có bài tập nào.</div>';
                    $('#asm-list').html(h);
                    renderPagination(r.total_pages, r.current_page, 'asm-pagination', 'loadAssignments');
                }
            });
        }

        function loadAttendance(p = 1) {
            $.post('student_process.php', {
                action: 'get_attendance',
                class_id: cId,
                subject_id: sId,
                page: p
            }, r => {
                let h = '';
                if (r.data.length > 0) {
                    r.data.forEach(l => {
                        let st = l.status == 1 ? '<span class="badge bg-success-subtle text-success rounded-pill px-3 py-2">Có mặt</span>' : '<span class="badge bg-danger-subtle text-danger rounded-pill px-3 py-2">Vắng mặt</span>';
                        h += `<tr><td><b>${l.lesson_date.split('-').reverse().join('/')}</b></td><td>${l.start_time.substring(0,5)} - ${l.end_time.substring(0,5)}</td><td class="text-center">${st}</td></tr>`;
                    });
                } else h = '<tr><td colspan="3" class="text-center py-5 text-muted">Chưa có dữ liệu.</td></tr>';
                $('#att-list').html(h);
                renderPagination(r.total_pages, r.current_page, 'att-pagination', 'loadAttendance');
            });
        }

        function loadExams(p = 1) {
            $.post('student_process.php', {
                action: 'get_exams',
                class_id: cId,
                subject_id: sId,
                page: p
            }, r => {
                let h = '';
                if (r.data.length > 0) {
                    r.data.forEach(e => {
                        let res = e.score !== null ? `<div class="h4 fw-bold text-primary mb-0">${e.score}đ</div><small class="text-muted">Đã hoàn thành</small>` : `<button class="btn btn-dark btn-sm rounded-pill px-4" onclick="startExam(${e.id})">Vào thi ngay</button>`;
                        h += `<div class="col-md-6"><div class="asm-item text-center"><div class="mb-3 text-secondary"><i class="fas fa-file-signature fa-2x"></i></div><h6 class="fw-bold text-dark mb-1">${e.title}</h6><p class="small text-muted mb-3">${e.duration} phút</p><div>${res}</div></div></div>`;
                    });
                } else h = '<div class="col-12 text-center py-5 text-muted">Chưa có kỳ thi nào.</div>';
                $('#exam-list').html(h);
                renderPagination(r.total_pages, r.current_page, 'exam-pagination', 'loadExams');
            });
        }

        function openSubmit(id) {
            $('#submit_aid').val(id);
            $('#submitForm')[0].reset();
            new bootstrap.Modal('#submitModal').show();
        }

        $('#submitForm').submit(function(e) {
            e.preventDefault();
            $.ajax({
                url: 'student_process.php',
                type: 'POST',
                data: new FormData(this),
                contentType: false,
                processData: false,
                success: () => {
                    bootstrap.Modal.getInstance('#submitModal').hide();
                    Swal.fire('Thành công', 'Đã nộp bài làm', 'success').then(() => loadAssignments(1));
                }
            });
        });

        function startExam(id) {
            Swal.fire({
                title: 'Bắt đầu bài thi?',
                text: "Thời gian sẽ được tính ngay khi bạn xác nhận!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Bắt đầu'
            }).then((res) => {
                if (res.isConfirmed) window.location.href = `quiz.php?exam_id=${id}`;
            });
        }

        function loadClassNews() {
            $.post('student_process.php', {
                action: 'get_class_announcements',
                class_id: cId,
                subject_id: sId
            }, r => {
                let h = '';
                if (r.data && r.data.length > 0) {
                    r.data.forEach(n => {
                        h += `
                <div class="card border-0 shadow-sm mb-3 rounded-4">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0">${n.teacher_name}</h6>
                                <small class="text-muted">${n.created_at}</small>
                            </div>
                        </div>
                        <p class="mb-0 text-dark" style="white-space: pre-wrap;">${n.content}</p>
                    </div>
                </div>`;
                    });
                } else {
                    h = '<div class="text-center py-5 text-muted">Lớp học hiện chưa có thông báo nào.</div>';
                }
                $('#class-news-list').html(h);
            });
        }
    </script>
</body>

</html>