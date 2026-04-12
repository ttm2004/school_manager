<?php
session_start();
$class_id = (int)($_GET['class_id'] ?? 0);
$subject_id = (int)($_GET['subject_id'] ?? 0);
if (!$class_id || !$subject_id || $_SESSION['role'] !== 'teacher') {
    header("Location: ../my_classes/index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Quản lý lớp học</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/admin.css">
</head>
<style>
    .custom-scroll-report {
        max-height: 400px;
        overflow-y: auto;
    }

    .sticky-top {
        position: sticky;
        top: 0;
        z-index: 10;
        box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);
    }

    .custom-scroll-report::-webkit-scrollbar {
        width: 6px;
    }

    .custom-scroll-report::-webkit-scrollbar-thumb {
        background-color: #d1d3e2;
        border-radius: 10px;
    }
</style>

<body class="bg-light">
    <div class="d-flex" id="wrapper">
        <?php include '../../includes/sidebar.php'; ?>
        <div id="page-content-wrapper" class="w-100">
            <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm px-4 py-3">
                <button class="btn btn-light me-3" id="sidebarToggle"><i class="fas fa-bars"></i></button>
                <h4 class="m-0 fw-bold"><span id="txt-class-name">...</span> - <span id="txt-subject-name" class="text-primary">...</span></h4>
            </nav>
            <div class="container-fluid px-4 py-4">
                <div class="row g-3 mb-4 text-center">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-primary border-4">
                            <h6 class="text-muted small">Học sinh</h6>
                            <h4 class="fw-bold mb-0" id="stat-students">0</h4>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-success border-4">
                            <h6 class="text-muted small">Số buổi đã học</h6>
                            <h4 class="fw-bold mb-0" id="stat-lessons">0</h4>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-warning border-4">
                            <h6 class="text-muted small">Bài tập</h6>
                            <h4 class="fw-bold mb-0" id="stat-assignments">0</h4>
                        </div>
                    </div>
                </div>
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3 border-0">
                        <ul class="nav nav-pills card-header-pills">
                            <li class="nav-item"><button class="nav-link active fw-bold" data-bs-toggle="pill" data-bs-target="#tab-students">Học sinh</button></li>
                            <li class="nav-item"><button class="nav-link fw-bold" data-bs-toggle="pill" data-bs-target="#tab-schedule" onclick="loadLessonLogs()">Điểm danh</button></li>
                            <li class="nav-item"><button class="nav-link fw-bold" data-bs-toggle="pill" data-bs-target="#tab-assignments" onclick="loadAssignments()">Bài tập</button></li>
                            <li class="nav-item"><button class="nav-link fw-bold" data-bs-toggle="pill" data-bs-target="#tab-exams" onclick="loadExams()">Đề thi</button></li>
                            <li class="nav-item"><button class="nav-link fw-bold" data-bs-toggle="pill" data-bs-target="#tab-reports" onclick="loadReports()">Báo cáo</button></li>
                            <li class="nav-item"><button class="nav-link fw-bold" data-bs-toggle="pill" data-bs-target="#tab-news">Thông báo lớp</button></li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="tab-students">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="50">STT</th>
                                            <th>Học sinh</th>
                                            <th>Mã số</th>
                                            <th class="text-end">Trạng thái</th>
                                        </tr>
                                    </thead>
                                    <tbody id="student-list"></tbody>
                                </table>
                            </div>
                            <div class="tab-pane fade" id="tab-schedule">
                                <form id="lessonForm" class="row g-2 align-items-end mb-4 bg-light p-3 rounded-4">
                                    <input type="hidden" name="action" value="add_lesson">
                                    <div class="col-md-3"><label class="small fw-bold">Ngày</label><input type="date" name="lesson_date" class="form-control" required></div>
                                    <div class="col-md-2"><label class="small fw-bold">Bắt đầu</label><input type="time" name="start_time" class="form-control" required></div>
                                    <div class="col-md-2"><label class="small fw-bold">Kết thúc</label><input type="time" name="end_time" class="form-control" required></div>
                                    <div class="col-md-3"><label class="small fw-bold">Phòng</label><input type="text" name="room_name" class="form-control"></div>
                                    <div class="col-md-2"><button type="submit" class="btn btn-primary w-100 fw-bold">Tạo buổi</button></div>
                                </form>
                                <table class="table table-hover text-center border">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Ngày</th>
                                            <th>Thời gian</th>
                                            <th>Phòng</th>
                                            <th>Hành động</th>
                                            <th>Xóa</th>
                                        </tr>
                                    </thead>
                                    <tbody id="lesson-log-list"></tbody>
                                </table>
                                <nav>
                                    <ul class="pagination pagination-sm justify-content-end" id="log-pagination"></ul>
                                </nav>
                            </div>
                            <div class="tab-pane fade" id="tab-assignments">
                                <div class="d-flex justify-content-between mb-3">
                                    <h6 class="fw-bold">Bài tập</h6><button class="btn btn-primary btn-sm rounded-pill" onclick="openAddAssignment()"><i class="fas fa-plus"></i> Giao bài</button>
                                </div>
                                <div id="assignment-list" class="row g-3"></div>
                            </div>
                            <div class="tab-pane fade" id="tab-exams">
                                <div class="d-flex justify-content-between mb-3">
                                    <h6 class="fw-bold">Đề thi trắc nghiệm</h6><button class="btn btn-primary btn-sm rounded-pill" onclick="openExamModal()"><i class="fas fa-plus"></i> Tạo đề</button>
                                </div>
                                <div id="exam-list" class="row g-3"></div>
                            </div>
                            <div class="tab-pane fade" id="tab-reports">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="card border-0 shadow-sm p-3">
                                            <h6 class="fw-bold"><i class="fas fa-user-check me-2 text-success"></i>Thống kê Chuyên cần</h6>
                                            <div class="table-responsive custom-scroll-report">
                                                <table class="table table-sm small table-hover">
                                                    <thead class="sticky-top bg-white">
                                                        <tr>
                                                            <th>Tên</th>
                                                            <th>Tỉ lệ</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="report-attendance-list"></tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card border-0 shadow-sm p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h6 class="fw-bold mb-0"><i class="fas fa-star me-2 text-warning"></i>Bảng điểm tổng hợp</h6>
                                                <a href="print_grades.php?class_id=<?= $class_id ?>&subject_id=<?= $subject_id ?>" target="_blank" class="btn btn-sm btn-outline-dark rounded-pill">
                                                    <i class="fas fa-print me-1"></i> In bảng điểm
                                                </a>
                                            </div>
                                            <div class="table-responsive custom-scroll-report">
                                                <table class="table table-sm small table-hover">
                                                    <thead class="sticky-top bg-white">
                                                        <tr>
                                                            <th>Tên</th>
                                                            <th>BT (Avg)</th>
                                                            <th>Thi (Avg)</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="report-grade-list"></tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="tab-news">
                                <div class="card border-0 shadow-sm p-4">
                                    <h6 class="fw-bold mb-3">Gửi thông báo mới cho lớp</h6>
                                    <form id="announcementForm">
                                        <input type="hidden" name="action" value="send_announcement">
                                        <textarea name="content" class="form-control mb-3" rows="3" placeholder="Nhập nội dung thông báo tại đây..." required></textarea>
                                        <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold">Phát tin cho cả lớp</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="attendanceModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">Điểm danh: <span id="att-date"></span></h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="p-3 bg-light border-bottom"><input type="text" id="searchAttendance" class="form-control" placeholder="Tìm tên học sinh..."></div>
                <div class="modal-body p-0" style="max-height: 400px; overflow-y: auto;">
                    <form id="attendanceForm">
                        <input type="hidden" name="action" value="save_attendance"><input type="hidden" name="lesson_id" id="att_lesson_id">
                        <table class="table table-hover mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="ps-3">Học sinh</th>
                                    <th class="text-center">Đi học</th>
                                </tr>
                            </thead>
                            <tbody id="attendance-list"></tbody>
                        </table>
                    </form>
                </div>
                <div id="att-footer" class="modal-footer border-0"><button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Hủy</button><button type="submit" form="attendanceForm" class="btn btn-primary rounded-pill px-4">Lưu lại</button></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="assignmentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <form id="assignmentForm" enctype="multipart/form-data">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title fw-bold" id="modal-asm-title">Bài tập</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" id="asm_action"><input type="hidden" name="id" id="asm_id">
                        <div class="mb-3"><label class="small fw-bold">Tiêu đề</label><input type="text" name="title" id="asm_title" class="form-control" required></div>
                        <div class="mb-3"><label class="small fw-bold">Yêu cầu</label><textarea name="description" id="asm_desc" class="form-control" rows="3"></textarea></div>
                        <div class="mb-3"><label class="small fw-bold">Hạn nộp</label><input type="datetime-local" name="deadline" id="asm_deadline" class="form-control" required></div>
                        <div class="mb-3"><label class="small fw-bold text-primary">Link tài liệu</label><input type="url" name="external_link" id="asm_link" class="form-control"></div>
                        <div class="mb-0"><label class="small fw-bold text-success">File đính kèm</label><input type="file" name="attachment" class="form-control">
                            <div id="current_file_info" class="mt-2 small text-muted"></div>
                        </div>
                    </div>
                    <div class="modal-footer border-0"><button type="submit" class="btn btn-warning w-100 rounded-pill fw-bold">Lưu thông tin</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="examModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold">Thiết lập đề thi</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="examInfoForm" class="row g-3 border-bottom pb-4 mb-4">
                        <input type="hidden" name="exam_id" id="exam_id_field">
                        <div class="col-md-8"><label class="small fw-bold">Tên đề thi</label><input type="text" name="title" id="exam_title_field" class="form-control" required></div>
                        <div class="col-md-2"><label class="small fw-bold">Phút</label><input type="number" name="duration" id="exam_duration_field" class="form-control" required></div>
                        <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-success w-100 fw-bold">Lưu đề</button></div>
                    </form>
                    <div id="question-section" style="display:none;">
                        <div class="card p-3 bg-light text-center rounded-4 border-dashed mb-4">
                            <h6 class="fw-bold mb-2">Nhập nhanh CSV</h6>
                            <input type="file" id="csvFileInput" class="d-none" accept=".csv">
                            <button class="btn btn-outline-success btn-sm rounded-pill px-4" onclick="$('#csvFileInput').click()">Chọn file</button>
                        </div>
                        <form id="questionForm" class="bg-light p-3 rounded-4 mb-4">
                            <textarea name="q_text" class="form-control mb-2" placeholder="Câu hỏi..." required></textarea>
                            <div class="row g-2 mb-2">
                                <div class="col-6"><input type="text" name="q_a" class="form-control" placeholder="A" required></div>
                                <div class="col-6"><input type="text" name="q_b" class="form-control" placeholder="B" required></div>
                                <div class="col-6"><input type="text" name="q_c" class="form-control" placeholder="C" required></div>
                                <div class="col-6"><input type="text" name="q_d" class="form-control" placeholder="D" required></div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <select name="q_correct" class="form-select w-25" required>
                                    <option value="">Đ/A đúng?</option>
                                    <option value="A">A</option>
                                    <option value="B">B</option>
                                    <option value="C">C</option>
                                    <option value="D">D</option>
                                </select>
                                <button type="submit" class="btn btn-primary fw-bold">Thêm câu hỏi</button>
                            </div>
                        </form>
                        <div id="questions-container" style="max-height: 300px; overflow-y: auto;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="gradingModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title fw-bold">Danh sách nộp bài: <span id="asm-title-display"></span></h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="ps-3">Sinh viên</th>
                                    <th>Bài làm</th>
                                    <th>Ngày nộp</th>
                                    <th width="120">Điểm</th>
                                    <th>Nhận xét</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="submission-list"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.3.2/papaparse.min.js"></script>
    <script>
        const classId = <?= $class_id ?>,
            subjectId = <?= $subject_id ?>;
        let logPage = 1;

        $(document).ready(() => {
            $('#sidebarToggle').click(() => $('#wrapper').toggleClass('sb-sidenav-toggled'));
            loadDashboard();
            $('#searchAttendance').on('keyup', function() {
                let v = $(this).val().toLowerCase();
                $('.att-row').each(function() {
                    $(this).toggle($(this).data('name').includes(v));
                });
            });
            $('#csvFileInput').on('change', function(e) {
                const eid = $('#exam_id_field').val();
                if (!e.target.files[0]) return;
                Papa.parse(e.target.files[0], {
                    header: false,
                    skipEmptyLines: true,
                    complete: function(res) {
                        let qs = [];
                        res.data.forEach((r, i) => {
                            if (i === 0 && isNaN(parseInt(r[0]))) return;
                            if (r.length >= 6) qs.push({
                                text: r[0],
                                a: r[1],
                                b: r[2],
                                c: r[3],
                                d: r[4],
                                correct: r[5].trim()
                            });
                        });
                        if (qs.length) {
                            $.post('process.php', {
                                action: 'import_questions',
                                exam_id: eid,
                                questions: qs
                            }, () => {
                                Swal.fire('Thành công', '', 'success');
                                loadQuestions(eid);
                            });
                        }
                    }
                });
            });
        });

        function loadDashboard() {
            $.post('process.php', {
                action: 'get_class_dashboard',
                class_id: classId,
                subject_id: subjectId
            }, r => {
                if (r.status === 'success') {
                    $('#txt-class-name').text(r.info.class_name);
                    $('#txt-subject-name').text(r.info.subject_name);
                    $('#stat-students').text(r.stats.students);
                    $('#stat-lessons').text(r.stats.lessons);
                    $('#stat-assignments').text(r.stats.assignments);
                    let h = '';
                    r.students.forEach((s, i) => {
                        let av = s.avatar ? `../../../uploads/avatars/${s.avatar}` : `https://ui-avatars.com/api/?name=${encodeURIComponent(s.full_name)}`;
                        h += `<tr><td>${i+1}</td><td><div class="d-flex align-items-center"><img src="${av}" class="rounded-circle me-3 border" width="40" height="40"><b>${s.full_name}</b></div></td><td>${s.username}</td><td class="text-end"><span class="badge bg-success-subtle text-success">Đang học</span></td></tr>`;
                    });
                    $('#student-list').html(h || '<tr><td colspan="4" class="text-center">Trống</td></tr>');
                    loadReports();
                }
            });
        }

        function loadLessonLogs() {
            $.post('process.php', {
                action: 'get_lesson_logs',
                class_id: classId,
                subject_id: subjectId,
                page: logPage
            }, r => {
                let h = '';
                r.data.forEach(l => {
                    let et = l.end_time.substring(0, 5),
                        st = l.start_time.substring(0, 5);
                    let isPast = (r.current_date > l.lesson_date) || (r.current_date === l.lesson_date && r.current_time > et);
                    let isSt = (r.current_date > l.lesson_date) || (r.current_date === l.lesson_date && r.current_time >= st);
                    let act = '',
                        att = '';
                    if (l.is_completed == 1) {
                        act = `<button class="btn btn-sm btn-success rounded-circle" disabled><i class="fas fa-check"></i></button>`;
                        att = `<button class="btn btn-sm ${isPast ? 'btn-secondary' : 'btn-info text-white'} rounded-pill px-3 ms-2" onclick="openAttendance(${l.id}, '${l.lesson_date}', ${isPast})"><i class="fas ${isPast ? 'fa-eye' : 'fa-edit'}"></i></button>`;
                    } else if (l.is_completed == 2) {
                        act = `<button class="btn btn-sm btn-danger rounded-circle" disabled><i class="fas fa-times"></i></button>`;
                    } else {
                        if (isSt) act = `<button class="btn btn-sm btn-outline-success rounded-circle me-1" onclick="confirmStatus(${l.id}, 1, 'Học thành công?')"><i class="fas fa-check"></i></button>`;
                        act += `<button class="btn btn-sm btn-outline-danger rounded-circle" onclick="confirmStatus(${l.id}, 2, 'Báo bận?')"><i class="fas fa-times"></i></button>`;
                    }
                    h += `<tr><td>${l.lesson_date.split('-').reverse().join('/')}</td><td>${st} - ${et}</td><td>${l.room_name || ''}</td><td>${act}${att}</td><td>${l.is_completed == 0 ? `<i class="fas fa-trash text-danger" style="cursor:pointer" onclick="deleteLesson(${l.id})"></i>` : `<i class="fas fa-lock text-muted"></i>`}</td></tr>`;
                });
                $('#lesson-log-list').html(h || '<tr><td colspan="5">Trống</td></tr>');
                let p = '';
                for (let i = 1; i <= r.total_pages; i++) p += `<li class="page-item ${i == logPage ? 'active' : ''}"><button class="page-link" onclick="logPage=${i};loadLessonLogs()">${i}</button></li>`;
                $('#log-pagination').html(p);
            });
        }

        function confirmStatus(id, s, t) {
            Swal.fire({
                title: t,
                text: "Không thể đổi sau khi lưu!",
                icon: 'question',
                showCancelButton: true
            }).then(res => {
                if (res.isConfirmed) $.post('process.php', {
                    action: 'toggle_lesson_status',
                    id: id,
                    status: s
                }, () => {
                    loadLessonLogs();
                    loadDashboard();
                });
            });
        }

        function openAttendance(lid, d, ro) {
            $('#att_lesson_id').val(lid);
            $('#att-date').text(d);
            $('#searchAttendance').val('');
            $.post('process.php', {
                action: 'get_attendance',
                lesson_id: lid,
                class_id: classId,
                subject_id: subjectId
            }, r => {
                let h = '';
                r.data.forEach(s => {
                    h += `<tr class="att-row" data-name="${s.full_name.toLowerCase()}"><td class="ps-3"><b>${s.full_name}</b><br><small>${s.username}</small></td><td class="text-center"><div class="form-check form-switch d-inline-block"><input class="form-check-input" type="checkbox" name="attendance[${s.id}]" value="1" ${s.attendance_status == 1 ? 'checked' : ''} ${ro ? 'disabled' : ''}></div></td></tr>`;
                });
                $('#attendance-list').html(h);
                $('#att-footer button[type="submit"]').toggle(!ro);
                new bootstrap.Modal('#attendanceModal').show();
            });
        }

        function loadAssignments() {
            $.post('process.php', {
                action: 'get_assignments',
                class_id: classId,
                subject_id: subjectId
            }, r => {
                let h = '';
                r.data.forEach(a => {
                    let od = r.current_now > a.deadline;
                    h += `<div class="col-md-6"><div class="card border shadow-sm rounded-4 h-100" style="cursor:pointer" onclick="viewAssignment(${a.id})"><div class="card-body">
                        <div class="d-flex justify-content-between"><b>${a.title}</b><span class="badge ${od ? 'bg-danger' : 'bg-success'}">${od ? 'Hết hạn' : 'Mở'}</span></div>
                        <p class="small text-secondary text-truncate my-2">${a.description || ''}</p>
                        <div class="mb-2" onclick="event.stopPropagation()">
                            <button class="btn btn-xs btn-info text-white" onclick="openGrading(${a.id},'${a.title}')">Chấm bài</button>
                            ${a.file_path ? `<a href="../../../uploads/assignments/${a.file_path}" target="_blank" class="btn btn-xs btn-outline-success">Đề</a>` : ''}
                        </div>
                        <div class="d-flex justify-content-between align-items-center border-top pt-2" onclick="event.stopPropagation()"><small>Hạn: ${a.deadline_format}</small><i class="fas fa-trash text-danger" onclick="deleteAssignment(${a.id})"></i></div>
                    </div></div></div>`;
                });
                $('#assignment-list').html(h || '<div class="col-12 text-center text-muted">Trống</div>');
                $('#stat-assignments').text(r.data.length);
            });
        }

        function openAddAssignment() {
            $('#asm_action').val('add_assignment');
            $('#asm_id').val('');
            $('#assignmentForm')[0].reset();
            $('#modal-asm-title').text('Giao bài mới');
            $('#current_file_info').text('');
            $('#assignmentModal').modal('show');
        }

        function viewAssignment(id) {
            $.post('process.php', {
                action: 'get_assignment_detail',
                id: id
            }, r => {
                const a = r.data;
                $('#asm_action').val('update_assignment');
                $('#asm_id').val(a.id);
                $('#asm_title').val(a.title);
                $('#asm_desc').val(a.description);
                $('#asm_deadline').val(a.deadline.replace(' ', 'T').substring(0, 16));
                $('#asm_link').val(a.external_link);
                $('#modal-asm-title').text('Sửa bài');
                $('#current_file_info').text(a.file_path ? 'File: ' + a.file_path : '');
                $('#assignmentModal').modal('show');
            });
        }

        function loadExams() {
            $.post('process.php', {
                action: 'get_exams',
                class_id: classId,
                subject_id: subjectId
            }, r => {
                let h = '';
                r.data.forEach(e => {
                    h += `<div class="col-md-6"><div class="card border shadow-sm rounded-4"><div class="card-body">
                        <div class="d-flex justify-content-between"><b>${e.title}</b><span class="badge bg-dark">${e.duration}p</span></div>
                        <div class="mt-3 d-flex justify-content-between"><div class="btn-group"><button class="btn btn-sm btn-outline-primary" onclick="editExam(${e.id},'${e.title}',${e.duration})">Sửa</button><button class="btn btn-sm btn-outline-info" onclick="viewExamResults(${e.id})">Kết quả</button></div><button class="btn btn-sm btn-outline-danger border-0" onclick="deleteExam(${e.id})"><i class="fas fa-trash"></i></button></div>
                    </div></div></div>`;
                });
                $('#exam-list').html(h || '<div class="col-12 text-center text-muted">Trống</div>');
            });
        }

        function openExamModal() {
            $('#exam_id_field').val('');
            $('#examInfoForm')[0].reset();
            $('#question-section').hide();
            $('#examModal').modal('show');
        }

        function loadQuestions(id) {
            $.post('process.php', {
                action: 'get_exam_questions',
                exam_id: id
            }, r => {
                let h = '';
                r.data.forEach((q, i) => {
                    h += `<div class="p-2 border-bottom small"><b>C${i+1}:</b> ${q.question_text} <span class="text-success">(${q.correct_option})</span></div>`;
                });
                $('#questions-container').html(h);
            });
        }

        function editExam(id, t, d) {
            $('#exam_id_field').val(id);
            $('#exam_title_field').val(t);
            $('#exam_duration_field').val(d);
            $('#question-section').show();
            loadQuestions(id);
            $('#examModal').modal('show');
        }

        function openGrading(aid, t) {
            $('#asm-title-display').text(t);
            $.post('process.php', {
                action: 'get_submissions',
                assignment_id: aid,
                class_id: classId,
                subject_id: subjectId
            }, r => {
                let h = '';
                r.data.forEach(s => {
                    let w = s.submission_id ? (s.file_path ? `<a href="../../../uploads/submissions/${s.file_path}" target="_blank" class="btn btn-xs btn-success">File</a>` : '') + (s.external_link ? `<a href="${s.external_link}" target="_blank" class="btn btn-xs btn-primary ms-1">Link</a>` : '') : '<i>Chưa nộp</i>';
                    h += `<tr><td class="ps-3"><b>${s.full_name}</b></td><td>${w}</td><td><small>${s.submitted_at||'-'}</small></td><td><input type="number" step="0.1" class="form-control form-control-sm grade-v" value="${s.grade||''}" ${!s.submission_id?'disabled':''}></td><td><input type="text" class="form-control form-control-sm fb-v" value="${s.feedback||''}" ${!s.submission_id?'disabled':''}></td><td>${s.submission_id?`<button class="btn btn-sm btn-primary" onclick="saveGrade(this,${s.submission_id})"><i class="fas fa-save"></i></button>`:''}</td></tr>`;
                });
                $('#submission-list').html(h);
                new bootstrap.Modal('#gradingModal').show();
            });
        }

        function saveGrade(b, sid) {
            let r = $(b).closest('tr');
            $.post('process.php', {
                action: 'save_grade',
                submission_id: sid,
                grade: r.find('.grade-v').val(),
                feedback: r.find('.fb-v').val()
            }, () => Swal.fire('Xong', '', 'success'));
        }

        function viewExamResults(eid) {
            $.post('process.php', {
                action: 'get_exam_results',
                exam_id: eid
            }, r => {
                let h = '<table class="table table-sm text-start"><thead><tr><th>Tên</th><th>Đúng</th><th>Điểm</th></tr></thead><tbody>';
                r.data.forEach(d => {
                    h += `<tr><td>${d.full_name}</td><td>${d.correct_answers}/${d.total_questions}</td><td><b>${d.score}</b></td></tr>`;
                });
                h += '</tbody></table>';
                Swal.fire({
                    title: 'Kết quả thi',
                    html: h
                });
            });
        }

        $('#lessonForm').submit(function(e) {
            e.preventDefault();
            $.post('process.php', $(this).serialize() + `&class_id=${classId}&subject_id=${subjectId}`, () => {
                loadLessonLogs();
                this.reset();
            });
        });
        $('#attendanceForm').submit(function(e) {
            e.preventDefault();
            let d = $(this).serializeArray();
            $('#attendance-list input[type="checkbox"]:not(:checked)').each(function() {
                d.push({
                    name: $(this).attr('name'),
                    value: '0'
                });
            });
            $.post('process.php', d, () => bootstrap.Modal.getInstance('#attendanceModal').hide());
        });
        $('#assignmentForm').submit(function(e) {
            e.preventDefault();
            let fd = new FormData(this);
            fd.append('class_id', classId);
            fd.append('subject_id', subjectId);
            $.ajax({
                url: 'process.php',
                type: 'POST',
                data: fd,
                contentType: false,
                processData: false,
                success: () => {
                    $('#assignmentModal').modal('hide');
                    loadAssignments();
                    loadDashboard();
                }
            });
        });
        $('#examInfoForm').submit(function(e) {
            e.preventDefault();
            $.post('process.php', $(this).serialize() + `&action=save_exam&class_id=${classId}&subject_id=${subjectId}`, r => {
                $('#exam_id_field').val(r.exam_id);
                $('#question-section').fadeIn();
                loadExams();
            });
        });
        $('#questionForm').submit(function(e) {
            e.preventDefault();
            $.post('process.php', $(this).serialize() + `&action=add_question&exam_id=${$('#exam_id_field').val()}`, () => {
                this.reset();
                loadQuestions($('#exam_id_field').val());
            });
        });

        function deleteLesson(id) {
            Swal.fire({
                title: 'Xóa?',
                icon: 'warning',
                showCancelButton: true
            }).then(r => r.isConfirmed && $.post('process.php', {
                action: 'delete_lesson',
                id: id
            }, () => loadLessonLogs()));
        }

        function deleteAssignment(id) {
            Swal.fire({
                title: 'Xóa bài?',
                icon: 'warning',
                showCancelButton: true
            }).then(r => r.isConfirmed && $.post('process.php', {
                action: 'delete_assignment',
                id: id
            }, () => loadAssignments()));
        }

        function deleteExam(id) {
            Swal.fire({
                title: 'Xóa đề?',
                icon: 'warning',
                showCancelButton: true
            }).then(r => r.isConfirmed && $.post('process.php', {
                action: 'delete_exam',
                id: id
            }, () => loadExams()));
        }

        function loadReports() {
            // 1. Tải báo cáo chuyên cần (Giữ nguyên)
            $.post('process.php', {
                action: 'get_attendance_report',
                class_id: classId,
                subject_id: subjectId
            }, r => {
                let h = '';
                r.data.forEach(d => {
                    let percent = d.total_lessons > 0 ? Math.round((d.present_count / d.total_lessons) * 100) : 0;
                    let color = percent < 70 ? 'text-danger' : 'text-success';
                    h += `<tr><td>${d.full_name}</td><td><b class="${color}">${d.present_count}/${d.total_lessons}</b> (${percent}%)</td></tr>`;
                });
                $('#report-attendance-list').html(h || '<tr><td colspan="2">Chưa có dữ liệu</td></tr>');
            });

            // 2. Tải báo cáo điểm số (Sửa lại logic hiển thị điểm thi trung bình)
            $.post('process.php', {
                action: 'get_grade_report',
                class_id: classId,
                subject_id: subjectId
            }, r => {
                let h = '';
                r.data.forEach(d => {
                    // Lấy trung bình bài tập
                    let avgAsm = d.avg_assignment_grade ? parseFloat(d.avg_assignment_grade).toFixed(1) : '-';

                    // Lấy trung bình điểm thi (Thay đổi tên biến từ d.latest_exam_score thành d.avg_exam_score)
                    let avgExam = d.avg_exam_score ? parseFloat(d.avg_exam_score).toFixed(1) : '-';

                    h += `<tr>
                    <td>${d.full_name}</td>
                    <td>${avgAsm}</td>
                    <td><b>${avgExam}</b></td>
                  </tr>`;
                });
                $('#report-grade-list').html(h || '<tr><td colspan="3">Chưa có dữ liệu</td></tr>');
            });
        }

        // Xử lý gửi thông báo
        $('#announcementForm').submit(function(e) {
            e.preventDefault();
            $.post('process.php', $(this).serialize() + `&class_id=${classId}&subject_id=${subjectId}`, r => {
                if (r.status === 'success') {
                    Swal.fire('Thành công', r.message, 'success');
                    this.reset();
                }
            });
        });
    </script>
</body>

</html>