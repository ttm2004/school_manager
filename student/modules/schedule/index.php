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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thời Khóa Biểu | Student</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/admin.css">
    <style>
        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            background: white;
        }
        .day-col {
            border-right: 1px solid #dee2e6;
            min-height: 500px;
            display: flex;
            flex-direction: column;
        }
        .day-col:last-child {
            border-right: none;
        }
        .day-header {
            background-color: #f8f9fa;
            padding: 15px 10px;
            text-align: center;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
            flex-shrink: 0;
        }
        .day-body {
            flex-grow: 1;
            max-height: 400px;
            overflow-y: auto;
            padding-bottom: 5px;
        }
        .day-body::-webkit-scrollbar {
            width: 5px;
        }
        .day-body::-webkit-scrollbar-track {
            background: transparent;
        }
        .day-body::-webkit-scrollbar-thumb {
            background: #adb5bd;
            border-radius: 10px;
        }
        .day-body::-webkit-scrollbar-thumb:hover {
            background: #6c757d;
        }
        .day-header .date-display {
            display: block;
            font-size: 1.1rem;
            color: #495057;
            margin-top: 5px;
        }
        .day-header .day-name {
            color: #6c757d;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        .schedule-card {
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 10px;
            margin: 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            transition: transform 0.2s;
        }
        .schedule-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .time-badge {
            font-size: 0.75rem;
            color: #1565c0;
            margin-bottom: 4px;
            display: block;
            font-weight: 600;
        }
        .subject-title {
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 2px;
            display: block;
        }
        .class-name {
            font-size: 0.8rem;
            color: #546e7a;
            font-weight: 600;
        }
        .room-loc {
            font-size: 0.75rem;
            color: #78909c;
        }
        .today-highlight {
            background-color: #fff9c4 !important;
        }
        .today-header-highlight {
            background-color: #fff176 !important;
            color: #f57f17 !important;
        }
        .today-header-highlight .date-display {
            color: #f57f17 !important;
            font-weight: bold;
        }
        .notification-banner {
            background-color: #d1e7dd;
            border: 1px solid #badbcc;
            color: #0f5132;
        }
    </style>
</head>
<body class="bg-light">
    <div class="d-flex" id="wrapper">
        <?php include '../../includes/sidebar.php'; ?>
        <div id="page-content-wrapper" class="w-100">
            <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm px-4 py-3">
                <button class="btn btn-light me-3" id="sidebarToggle"><i class="fas fa-bars"></i></button>
                <h4 class="m-0 fw-bold text-primary"><i class="fas fa-calendar-alt me-2"></i>Thời Khóa Biểu</h4>
            </nav>

            <div class="container-fluid px-4 py-4">
                <div id="today-notification" class="alert notification-banner rounded-4 shadow-sm mb-4 d-none">
                    <div class="d-flex align-items-start">
                        <div class="fs-4 me-3"><i class="fas fa-bell"></i></div>
                        <div>
                            <h6 class="fw-bold mb-1">Hôm nay bạn có lịch học! <i class="fas fa-graduation-cap"></i></h6>
                            <ul class="mb-0 ps-3 small" id="today-list"></ul>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                            <h5 class="fw-bold m-0" id="current-range-display">Tuần: ...</h5>
                            <div class="d-flex gap-2">
                                <select class="form-select w-auto shadow-none" id="week-selector"></select>
                                <button class="btn btn-primary px-3" onclick="goToToday()">Hôm nay</button>
                            </div>
                        </div>

                        <div class="schedule-grid" id="calendar-container">
                            <div class="day-col"><div class="day-header"><span class="day-name">Thứ 2</span><span class="date-display" id="date-1"></span></div><div class="day-body" id="body-1"></div></div>
                            <div class="day-col"><div class="day-header"><span class="day-name">Thứ 3</span><span class="date-display" id="date-2"></span></div><div class="day-body" id="body-2"></div></div>
                            <div class="day-col"><div class="day-header"><span class="day-name">Thứ 4</span><span class="date-display" id="date-3"></span></div><div class="day-body" id="body-3"></div></div>
                            <div class="day-col"><div class="day-header"><span class="day-name">Thứ 5</span><span class="date-display" id="date-4"></span></div><div class="day-body" id="body-4"></div></div>
                            <div class="day-col"><div class="day-header"><span class="day-name">Thứ 6</span><span class="date-display" id="date-5"></span></div><div class="day-body" id="body-5"></div></div>
                            <div class="day-col"><div class="day-header"><span class="day-name">Thứ 7</span><span class="date-display" id="date-6"></span></div><div class="day-body" id="body-6"></div></div>
                            <div class="day-col"><div class="day-header"><span class="day-name">CN</span><span class="date-display" id="date-0"></span></div><div class="day-body" id="body-0"></div></div>
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
            $('#sidebarToggle').click(() => $('#wrapper').toggleClass('sb-sidenav-toggled'));
            generateWeeks();
        });

        function getMonday(d) {
            d = new Date(d);
            var day = d.getDay(),
                diff = d.getDate() - day + (day == 0 ? -6 : 1); 
            return new Date(d.setDate(diff));
        }

        function formatDate(d) {
            let dd = String(d.getDate()).padStart(2, '0');
            let mm = String(d.getMonth() + 1).padStart(2, '0');
            return `${dd}/${mm}`;
        }

        function formatDateSQL(d) {
            return d.toISOString().split('T')[0];
        }

        function generateWeeks() {
            const today = new Date();
            const currentMonday = getMonday(today);
            const select = $('#week-selector');
            select.empty();

            for (let i = -12; i <= 12; i++) {
                let start = new Date(currentMonday);
                start.setDate(start.getDate() + (i * 7));
                let end = new Date(start);
                end.setDate(end.getDate() + 6);

                let label = `Tuần ${formatDate(start)} - ${formatDate(end)}`;
                let val = formatDateSQL(start);
                
                let isCurrent = (start.getTime() === currentMonday.getTime());
                let opt = `<option value="${val}" ${isCurrent ? 'selected' : ''}>${label}</option>`;
                select.append(opt);
            }

            select.change(function() {
                loadSchedule(this.value);
            });

            loadSchedule(formatDateSQL(currentMonday));
        }

        function goToToday() {
            const today = new Date();
            const mon = getMonday(today);
            const val = formatDateSQL(mon);
            $('#week-selector').val(val).trigger('change');
        }

        function loadSchedule(startDateStr) {
            const start = new Date(startDateStr);
            const end = new Date(start);
            end.setDate(end.getDate() + 6);

            $('#current-range-display').text(`Tuần: ${formatDate(start)} - ${formatDate(end)}/${start.getFullYear()}`);

            const todayStr = formatDateSQL(new Date());

            $('.day-col, .day-header').removeClass('today-highlight today-header-highlight');
            for (let i = 1; i <= 7; i++) {
                let currentDay = new Date(start);
                currentDay.setDate(start.getDate() + (i - 1));
                
                let dayIdx = currentDay.getDay(); 
                let dateDisplay = formatDate(currentDay);
                
                $(`#date-${dayIdx}`).text(dateDisplay);
                $(`#body-${dayIdx}`).empty();

                if (formatDateSQL(currentDay) === todayStr) {
                    $(`#date-${dayIdx}`).parent().parent().addClass('today-highlight');
                    $(`#date-${dayIdx}`).parent().addClass('today-header-highlight');
                }
            }

            $.post('process.php', {
                action: 'get_schedule',
                start_date: formatDateSQL(start),
                end_date: formatDateSQL(end)
            }, function(res) {
                if(res.status === 'success') {
                    if (res.today_classes && res.today_classes.length > 0) {
                        let html = '';
                        res.today_classes.forEach(c => {
                            html += `<li><b>${c.subject_name}</b> - Lớp: ${c.class_name} (${c.start_time.substr(0,5)} - ${c.end_time.substr(0,5)})</li>`;
                        });
                        $('#today-list').html(html);
                        $('#today-notification').removeClass('d-none');
                    } else {
                        $('#today-notification').addClass('d-none');
                    }

                    res.data.forEach(item => {
                        let d = new Date(item.lesson_date);
                        let dayIdx = d.getDay();
                        
                        let html = `
                            <div class="schedule-card">
                                <span class="time-badge"><i class="far fa-clock me-1"></i>${item.start_time.substr(0,5)} - ${item.end_time.substr(0,5)}</span>
                                <span class="subject-title">${item.subject_name}</span>
                                <div class="class-name">${item.class_name}</div>
                                <div class="room-loc"><i class="fas fa-map-marker-alt me-1"></i>${item.room_name || 'Chưa cập nhật'}</div>
                            </div>
                        `;
                        $(`#body-${dayIdx}`).append(html);
                    });
                }
            });
        }
    </script>
</body>
</html>