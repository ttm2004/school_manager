<?php
/**
 * Admissions — Central Fetch API Handler
 * Tất cả POST actions từ các trang trong module tuyển sinh
 * Trả về JSON: { success: bool, message: string, data?: any }
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAnyRole(['admissions_manager', 'admissions_staff']);

header('Content-Type: application/json; charset=utf-8');

// Chỉ chấp nhận POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ.']);
    exit();
}

// Xác minh CSRF
$csrfToken = $_POST['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!verifyCSRFToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ (CSRF). Vui lòng tải lại trang.']);
    exit();
}

$module = trim($_POST['module'] ?? '');
$action = trim($_POST['action'] ?? '');

function ok(string $msg, array $data = []): void {
    echo json_encode(['success' => true, 'message' => $msg] + ($data ? ['data' => $data] : []));
    exit();
}
function err(string $msg, int $code = 200): void {
    if ($code !== 200) http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit();
}

// ════════════════════════════════════════════════════════════
// MODULE: applications
// ════════════════════════════════════════════════════════════
if ($module === 'applications') {

    $roundPhase      = getRoundPhase();
    $isLocked        = in_array($roundPhase, ['reviewing', 'supp_reviewing', 'no_round', 'completed']);
    $canManualReview = !$isLocked;

    if ($action === 'update_status') {
        if (!hasPermission('admissions', 'edit_application')) err('Bạn không có quyền cập nhật trạng thái hồ sơ.');
        if (!$canManualReview) err('⚠️ Đang trong giai đoạn xét tuyển — không thể cập nhật trạng thái thủ công.');

        $id     = (int)($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        if (!$id || !in_array($status, ['new','checking','approved','rejected','enrolled'])) err('Dữ liệu không hợp lệ.');

        $stmt = $conn->prepare("UPDATE admission_applications SET status=? WHERE id=?");
        $stmt->bind_param('si', $status, $id);
        $stmt->execute() ? ok('Cập nhật trạng thái thành công!', ['status' => $status]) : err('Lỗi: ' . $conn->error);
        $stmt->close();
    }

    if ($action === 'delete') {
        if (!hasPermission('admissions', 'delete_application')) err('Bạn không có quyền xóa hồ sơ.');

        $id = (int)($_POST['id'] ?? 0);
        if (!$id) err('ID không hợp lệ.');

        $stmt = $conn->prepare("DELETE FROM admission_applications WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute() ? ok('Xóa hồ sơ thành công!') : err('Lỗi: ' . $conn->error);
        $stmt->close();
    }

    if ($action === 'bulk_approve' || $action === 'bulk_reject') {
        if (!hasPermission('admissions', 'approve_application')) err('Bạn không có quyền duyệt hồ sơ.');
        if ($isLocked) err('Chức năng chỉ khả dụng trong giai đoạn xét tuyển.');

        $ids    = $_POST['ids'] ?? [];
        $newSt  = $action === 'bulk_approve' ? 'approved' : 'rejected';
        $label  = $action === 'bulk_approve' ? 'duyệt' : 'từ chối';
        $cnt    = 0;
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id) {
                $upd = $conn->prepare("UPDATE admission_applications SET status=? WHERE id=?");
                $upd->bind_param('si', $newSt, $id);
                $upd->execute(); $upd->close(); $cnt++;
            }
        }
        ok("Đã $label <strong>$cnt</strong> hồ sơ.");
    }
}

// ════════════════════════════════════════════════════════════
// MODULE: methods (Phương thức xét tuyển)
// ════════════════════════════════════════════════════════════
if ($module === 'methods') {
    if (!hasRole('admissions_manager')) err('Chỉ Trưởng phòng mới có quyền thực hiện thao tác này.', 403);

    if ($action === 'add') {
        $method_name    = trim($_POST['method_name'] ?? '');
        $description    = trim($_POST['description'] ?? '');
        $condition_text = trim($_POST['condition_text'] ?? '');
        $status         = trim($_POST['status'] ?? 'open');
        if (!$method_name) err('Vui lòng nhập tên phương thức.');

        $stmt = $conn->prepare("INSERT INTO admission_methods (method_name, description, condition_text, status) VALUES (?,?,?,?)");
        $stmt->bind_param('ssss', $method_name, $description, $condition_text, $status);
        if ($stmt->execute()) {
            $newId = $conn->insert_id;
            $stmt->close();
            ok('Thêm phương thức thành công!', ['id' => $newId]);
        }
        err('Lỗi: ' . $conn->error);
    }

    if ($action === 'edit') {
        $id             = (int)($_POST['id'] ?? 0);
        $method_name    = trim($_POST['method_name'] ?? '');
        $description    = trim($_POST['description'] ?? '');
        $condition_text = trim($_POST['condition_text'] ?? '');
        $status         = trim($_POST['status'] ?? 'open');
        if (!$id || !$method_name) err('Dữ liệu không hợp lệ.');

        $stmt = $conn->prepare("UPDATE admission_methods SET method_name=?, description=?, condition_text=?, status=? WHERE id=?");
        $stmt->bind_param('ssssi', $method_name, $description, $condition_text, $status, $id);
        $stmt->execute() ? ok('Cập nhật thành công!') : err('Lỗi: ' . $conn->error);
        $stmt->close();
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) err('ID không hợp lệ.');

        $stmt = $conn->prepare("DELETE FROM admission_methods WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute() ? ok('Xóa thành công!') : err('Lỗi: ' . $conn->error);
        $stmt->close();
    }
}

// ════════════════════════════════════════════════════════════
// MODULE: news (Tin tuyển sinh)
// ════════════════════════════════════════════════════════════
if ($module === 'news') {
    if (!hasRole('admissions_manager')) err('Chỉ Trưởng phòng mới có quyền thực hiện thao tác này.', 403);

    if ($action === 'add') {
        $title      = trim($_POST['title'] ?? '');
        $content    = trim($_POST['content'] ?? '');
        $image      = trim($_POST['image'] ?? '');
        $start_date = trim($_POST['start_date'] ?? '') ?: null;
        $end_date   = trim($_POST['end_date'] ?? '') ?: null;
        $status     = trim($_POST['status'] ?? 'show');
        if (!$title || !$content) err('Vui lòng nhập tiêu đề và nội dung.');

        $stmt = $conn->prepare("INSERT INTO admission_news (title, image, content, start_date, end_date, status) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param('ssssss', $title, $image, $content, $start_date, $end_date, $status);
        if ($stmt->execute()) {
            $newId = $conn->insert_id;
            $stmt->close();
            ok('Thêm tin tuyển sinh thành công!', ['id' => $newId]);
        }
        err('Lỗi: ' . $conn->error);
    }

    if ($action === 'edit') {
        $id         = (int)($_POST['id'] ?? 0);
        $title      = trim($_POST['title'] ?? '');
        $content    = trim($_POST['content'] ?? '');
        $image      = trim($_POST['image'] ?? '');
        $start_date = trim($_POST['start_date'] ?? '') ?: null;
        $end_date   = trim($_POST['end_date'] ?? '') ?: null;
        $status     = trim($_POST['status'] ?? 'show');
        if (!$id || !$title || !$content) err('Dữ liệu không hợp lệ.');

        $stmt = $conn->prepare("UPDATE admission_news SET title=?, image=?, content=?, start_date=?, end_date=?, status=? WHERE id=?");
        $stmt->bind_param('ssssssi', $title, $image, $content, $start_date, $end_date, $status, $id);
        $stmt->execute() ? ok('Cập nhật thành công!') : err('Lỗi: ' . $conn->error);
        $stmt->close();
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) err('ID không hợp lệ.');

        $stmt = $conn->prepare("DELETE FROM admission_news WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute() ? ok('Xóa thành công!') : err('Lỗi: ' . $conn->error);
        $stmt->close();
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) err('ID không hợp lệ.');

        $stmt = $conn->prepare("UPDATE admission_news SET status = IF(status='show','hide','show') WHERE id=?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            // Lấy trạng thái mới
            $r = $conn->query("SELECT status FROM admission_news WHERE id=$id");
            $newStatus = $r ? $r->fetch_assoc()['status'] : 'show';
            $stmt->close();
            ok('Đã cập nhật trạng thái!', ['status' => $newStatus]);
        }
        err('Lỗi: ' . $conn->error);
    }
}

// ════════════════════════════════════════════════════════════
// MODULE: rounds (Đợt tuyển sinh)
// ════════════════════════════════════════════════════════════
if ($module === 'rounds') {
    if (!hasRole('admissions_manager')) err('Chỉ Trưởng phòng mới có quyền thực hiện thao tác này.', 403);

    if ($action === 'add' || $action === 'edit') {
        $id          = (int)($_POST['id'] ?? 0);
        $year        = (int)($_POST['year'] ?? date('Y'));
        $name        = trim($_POST['name'] ?? '');
        $reg_start   = trim($_POST['reg_start'] ?? '');
        $reg_end     = trim($_POST['reg_end'] ?? '');
        $rev_start   = trim($_POST['review_start'] ?? '');
        $rev_end     = trim($_POST['review_end'] ?? '');
        $enroll_dl   = trim($_POST['enroll_deadline'] ?? '');
        $status      = trim($_POST['status'] ?? 'draft');
        $notes       = trim($_POST['notes'] ?? '');
        $supp_reg_start = trim($_POST['supp_reg_start'] ?? '') ?: null;
        $supp_reg_end   = trim($_POST['supp_reg_end'] ?? '') ?: null;
        $supp_rev_end   = trim($_POST['supp_review_end'] ?? '') ?: null;
        $supp_enroll_dl = trim($_POST['supp_enroll_deadline'] ?? '') ?: null;
        $supp_bonus     = (float)($_POST['supp_score_bonus'] ?? 0);

        if (!$name || !$reg_start || !$reg_end || !$rev_start || !$rev_end || !$enroll_dl)
            err('Vui lòng điền đầy đủ các mốc thời gian bắt buộc.');
        if (strtotime($reg_end) <= strtotime($reg_start))
            err('Ngày kết thúc nhận hồ sơ phải sau ngày bắt đầu.');
        if (strtotime($rev_end) <= strtotime($rev_start))
            err('Ngày kết thúc xét tuyển phải sau ngày bắt đầu.');

        $by = (int)$_SESSION['user_id'];
        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO admission_rounds
                (year,name,reg_start,reg_end,review_start,review_end,enroll_deadline,
                 supp_reg_start,supp_reg_end,supp_review_end,supp_enroll_deadline,supp_score_bonus,
                 status,notes,created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('issssssssssdssi',
                $year,$name,$reg_start,$reg_end,$rev_start,$rev_end,$enroll_dl,
                $supp_reg_start,$supp_reg_end,$supp_rev_end,$supp_enroll_dl,$supp_bonus,
                $status,$notes,$by);
            if ($stmt->execute()) {
                $newId = $conn->insert_id;
                $stmt->close();
                ok('Tạo đợt tuyển sinh thành công!', ['id' => $newId]);
            }
        } else {
            if (!$id) err('ID không hợp lệ.');
            $stmt = $conn->prepare("UPDATE admission_rounds SET
                year=?,name=?,reg_start=?,reg_end=?,review_start=?,review_end=?,enroll_deadline=?,
                supp_reg_start=?,supp_reg_end=?,supp_review_end=?,supp_enroll_deadline=?,supp_score_bonus=?,
                status=?,notes=?
                WHERE id=?");
            $stmt->bind_param('issssssssssdssi',
                $year,$name,$reg_start,$reg_end,$rev_start,$rev_end,$enroll_dl,
                $supp_reg_start,$supp_reg_end,$supp_rev_end,$supp_enroll_dl,$supp_bonus,
                $status,$notes,$id);
            $stmt->execute() ? ok('Cập nhật thành công!') : err('Lỗi: ' . $conn->error);
            $stmt->close();
        }
        err('Lỗi: ' . $conn->error);
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) err('ID không hợp lệ.');

        $stmt = $conn->prepare("DELETE FROM admission_rounds WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute() ? ok('Đã xóa đợt tuyển sinh.') : err('Lỗi: ' . $conn->error);
        $stmt->close();
    }

    if ($action === 'change_status') {
        $id     = (int)($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $valid  = ['draft','open','reviewing','enrolling','supplementary','completed'];
        if (!$id || !in_array($status, $valid)) err('Dữ liệu không hợp lệ.');

        $stmt = $conn->prepare("UPDATE admission_rounds SET status=? WHERE id=?");
        $stmt->bind_param('si', $status, $id);
        $stmt->execute() ? ok('Đã cập nhật trạng thái!', ['status' => $status]) : err('Lỗi: ' . $conn->error);
        $stmt->close();
    }
}

// ════════════════════════════════════════════════════════════
// MODULE: auto_review
// ════════════════════════════════════════════════════════════
if ($module === 'auto_review') {
    if (!hasRole('admissions_manager')) err('Chỉ Trưởng phòng mới có quyền thực hiện thao tác này.', 403);

    $roundPhase    = getRoundPhase();
    $activeRound   = getActiveRound();
    $reviewAllowed = in_array($roundPhase, ['reviewing', 'supp_reviewing']);

    if ($action === 'clear_import') {
        unset($_SESSION['import_rows']);
        ok('Đã xóa dữ liệu import.');
    }

    if ($action === 'run_auto_review') {
        if (!$reviewAllowed) err('Chức năng chỉ khả dụng trong giai đoạn xét tuyển.');

        $source = $_POST['source'] ?? 'manual';
        $jobs   = [];

        if ($source === 'import' && !empty($_SESSION['import_rows'])) {
            $jobs = $_SESSION['import_rows'];
        } else {
            $major_id  = (int)($_POST['major_id'] ?? 0);
            $threshold = (float)($_POST['threshold'] ?? 0);
            $quota     = (int)($_POST['quota'] ?? 0);
            if (!$major_id || $threshold <= 0) err('Vui lòng chọn ngành và nhập điểm chuẩn hợp lệ.');

            $mRes = $conn->query("SELECT major_code, major_name FROM majors WHERE id=$major_id LIMIT 1");
            $mRow = $mRes ? $mRes->fetch_assoc() : [];
            $jobs[] = [
                'major_id'   => $major_id,
                'major_code' => $mRow['major_code'] ?? '',
                'major_name' => $mRow['major_name'] ?? '',
                'quota'      => $quota,
                'threshold'  => $threshold,
                'year'       => $activeRound['year'] ?? date('Y'),
            ];
        }

        if (empty($jobs)) err('Không có dữ liệu để xét tuyển.');

        $results = [];
        foreach ($jobs as $job) {
            $mid = (int)$job['major_id'];
            $thr = (float)$job['threshold'];
            $qta = (int)$job['quota'];

            $stmt = $conn->prepare("
                SELECT id, full_name, email,
                       (math_score + literature_score + english_score) AS total_score
                FROM admission_applications
                WHERE major_id = ? AND status IN ('new','checking')
                ORDER BY total_score DESC
            ");
            $stmt->bind_param('i', $mid);
            $stmt->execute();
            $candidates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $approved = $rejected = $rank = 0;
            foreach ($candidates as $c) {
                $rank++;
                $pass      = ($c['total_score'] >= $thr) && ($qta === 0 || $rank <= $qta);
                $newStatus = $pass ? 'approved' : 'rejected';
                $upd = $conn->prepare("UPDATE admission_applications SET status=? WHERE id=?");
                $upd->bind_param('si', $newStatus, $c['id']);
                $upd->execute();
                $upd->close();
                $pass ? $approved++ : $rejected++;
            }

            $results[] = [
                'major_name' => $job['major_name'],
                'total'      => count($candidates),
                'approved'   => $approved,
                'rejected'   => $rejected,
                'threshold'  => $thr,
                'quota'      => $qta,
            ];
        }

        ok('Xét tuyển hoàn tất!', ['results' => $results]);
    }
}

err('Hành động không hợp lệ.');
