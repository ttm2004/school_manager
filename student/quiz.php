<?php
session_start();
require_once '../config/db.php';

$exam_id = (int)($_GET['exam_id'] ?? 0);
$student_id = (int)($_SESSION['user_id'] ?? 0);

if (!$exam_id || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit;
}

// 1. Chống làm lại bài thi
$check = $conn->prepare("SELECT id FROM exam_results WHERE exam_id = ? AND student_id = ?");
$check->execute([$exam_id, $student_id]);
if ($check->fetch()) {
    die("<script>alert('Bạn đã hoàn thành bài thi này rồi!'); window.location.href='index.php';</script>");
}

$stmtExam = $conn->prepare("SELECT * FROM exams WHERE id = ?");
$stmtExam->execute([$exam_id]);
$exam = $stmtExam->fetch(PDO::FETCH_ASSOC);
if (!$exam) die("Đề thi không tồn tại.");

$stmtQs = $conn->prepare("SELECT id, question_text, option_a, option_b, option_c, option_d FROM exam_questions WHERE exam_id = ? ORDER BY RAND()");
$stmtQs->execute([$exam_id]);
$questions = $stmtQs->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thi thử: <?= $exam['title'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; user-select: none; }
        .sticky-timer { position: sticky; top: 20px; }
        .question-card { border: none; border-radius: 15px; margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        #timer { font-size: 2rem; font-weight: bold; color: #e74a3b; }
        .btn-back { color: #858796; text-decoration: none; font-weight: 600; transition: 0.3s; }
        .btn-back:hover { color: #4e73df; }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="mb-4">
            <a href="javascript:void(0)" onclick="handleBack()" class="btn-back">
                <i class="fas fa-arrow-left me-2"></i>Thoát và nộp bài
            </a>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <h3 class="fw-bold text-dark mb-4"><?= $exam['title'] ?></h3>
                <form id="quizForm">
                    <input type="hidden" name="action" value="submit_exam">
                    <input type="hidden" name="exam_id" value="<?= $exam_id ?>">

                    <?php foreach ($questions as $index => $q): ?>
                        <div class="card question-card p-4">
                            <h6 class="fw-bold mb-3">Câu <?= $index + 1 ?>: <?= htmlspecialchars($q['question_text']) ?></h6>
                            <div class="options">
                                <?php foreach (['a', 'b', 'c', 'd'] as $opt): ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="answers[<?= $q['id'] ?>]" id="q<?= $q['id'].$opt ?>" value="<?= strtoupper($opt) ?>">
                                        <label class="form-check-label w-100" for="q<?= $q['id'].$opt ?>">
                                            <?= strtoupper($opt) ?>. <?= htmlspecialchars($q['option_'.$opt]) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="text-center mt-4 mb-5">
                        <button type="button" id="btnFinalSubmit" class="btn btn-success btn-lg px-5 shadow rounded-pill fw-bold" onclick="confirmSubmit()">
                            <i class="fas fa-paper-plane me-2"></i>NỘP BÀI THI
                        </button>
                    </div>
                </form>
            </div>

            <div class="col-lg-4">
                <div class="sticky-timer">
                    <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
                        <h6 class="text-muted small">THỜI GIAN CÒN LẠI</h6>
                        <div id="timer">--:--</div>
                        <hr>
                        <div class="text-start small">
                            <p class="mb-1"><i class="fas fa-user me-2"></i> <b><?= $_SESSION['full_name'] ?></b></p>
                            <p class="mb-0 text-muted">Lưu ý: Bạn chỉ có 1 lần nộp bài duy nhất.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let timeLeft = <?= $exam['duration'] * 60 ?>;
        let isSubmitted = false; // Biến kiểm soát trạng thái nộp bài

        const countdown = setInterval(() => {
            let min = Math.floor(timeLeft / 60);
            let sec = timeLeft % 60;
            document.getElementById('timer').innerHTML = `${min < 10 ? '0' : ''}${min}:${sec < 10 ? '0' : ''}${sec}`;
            if (timeLeft <= 0) {
                clearInterval(countdown);
                autoSubmit();
            }
            timeLeft--;
        }, 1000);

        // Xử lý khi ấn nút Back
        function handleBack() {
            Swal.fire({
                title: 'Bạn muốn thoát?',
                text: "Nếu bạn rời khỏi trang, hệ thống sẽ tự động nộp bài với những câu bạn đã chọn!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74a3b',
                cancelButtonColor: '#858796',
                confirmButtonText: 'Đồng ý, nộp và thoát',
                cancelButtonText: 'Ở lại làm tiếp'
            }).then((result) => {
                if (result.isConfirmed) autoSubmit();
            });
        }

        function confirmSubmit() {
            Swal.fire({
                title: 'Xác nhận nộp bài?',
                text: "Bạn chỉ có thể nộp bài 1 lần duy nhất!",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#1cc88a',
                confirmButtonText: 'Nộp bài ngay'
            }).then((result) => {
                if (result.isConfirmed) autoSubmit();
            });
        }

        function autoSubmit() {
            if (isSubmitted) return; // Nếu đang nộp thì không chạy tiếp
            isSubmitted = true;

            // Vô hiệu hóa nút và hiện trạng thái chờ
            $('#btnFinalSubmit').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Đang nộp...');
            
            clearInterval(countdown);
            const formData = $('#quizForm').serialize();
            
            $.post('student_process.php', formData, function(r) {
                if (r.status === 'success') {
                    Swal.fire({
                        title: 'Hoàn thành!',
                        html: `Điểm: <b class="text-primary h3">${r.score}</b><br>Đúng: ${r.correct}/${r.total}`,
                        icon: 'success',
                        allowOutsideClick: false
                    }).then(() => {
                        window.location.href = 'index.php';
                    });
                } else {
                    Swal.fire('Lỗi', r.message, 'error');
                    isSubmitted = false;
                    $('#btnFinalSubmit').prop('disabled', false).text('NỘP BÀI THI');
                }
            });
        }

        // Chặn quay lại bằng nút trình duyệt mà không cảnh báo
        window.history.pushState(null, null, window.location.href);
        window.onpopstate = function () {
            handleBack();
            window.history.pushState(null, null, window.location.href);
        };
    </script>
</body>
</html>