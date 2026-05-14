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
$requestDataMode = (($_POST['data_mode'] ?? $_POST['mode'] ?? 'system') === 'test') ? 'test' : 'system';

function ok(string $msg, array $data = []): void {
    echo json_encode(['success' => true, 'message' => $msg] + ($data ? ['data' => $data] : []));
    exit();
}
function err(string $msg, int $code = 200): void {
    if ($code !== 200) http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit();
}

function admTableExists(mysqli $conn, string $table): bool {
    $safe = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $res && $res->num_rows > 0;
}

function admNormalizeHeader(string $value): string {
    $value = strtolower(trim($value));
    $value = str_replace(["\xef\xbb\xbf", ' ', '-', '.'], ['', '_', '_', '_'], $value);
    return preg_replace('/[^a-z0-9_]/', '', $value) ?: '';
}

function admCsvValue(array $row, array $keys, string $default = ''): string {
    foreach ($keys as $key) {
        if (isset($row[$key]) && trim((string)$row[$key]) !== '') {
            return trim((string)$row[$key]);
        }
    }
    return $default;
}

// ════════════════════════════════════════════════════════════
// MODULE: applications
// ════════════════════════════════════════════════════════════
if ($module === 'applications') {

    $roundPhase      = getRoundPhase($requestDataMode);
    $isLocked        = in_array($roundPhase, ['reviewing', 'supp_reviewing', 'no_round', 'completed']);
    $canManualReview = !$isLocked;

    if ($action === 'update_status') {
        if (!hasPermission('admissions', 'edit_application')) err('Bạn không có quyền cập nhật trạng thái hồ sơ.');
        if (!$canManualReview) err('⚠️ Đang trong giai đoạn xét tuyển — không thể cập nhật trạng thái thủ công.');

        $id     = (int)($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        if (!$id || !in_array($status, ['new','checking','approved','rejected','enrolled'])) err('Dữ liệu không hợp lệ.');

        $stmt = $conn->prepare("UPDATE admission_applications SET status=? WHERE id=? AND data_mode=?");
        $stmt->bind_param('sis', $status, $id, $requestDataMode);
        $stmt->execute() ? ok('Cập nhật trạng thái thành công!', ['status' => $status]) : err('Lỗi: ' . $conn->error);
        $stmt->close();
    }

    if ($action === 'delete') {
        if (!hasPermission('admissions', 'delete_application')) err('Bạn không có quyền xóa hồ sơ.');

        $id = (int)($_POST['id'] ?? 0);
        if (!$id) err('ID không hợp lệ.');

        $stmt = $conn->prepare("DELETE FROM admission_applications WHERE id=? AND data_mode=?");
        $stmt->bind_param('is', $id, $requestDataMode);
        $stmt->execute() ? ok('Xóa hồ sơ thành công!') : err('Lỗi: ' . $conn->error);
        $stmt->close();
    }

    if ($action === 'import_test_csv') {
        if (!hasRole('admissions_manager')) err('Chi Truong phong moi co quyen import du lieu test.', 403);
        if (empty($_FILES['csv_file']['tmp_name'])) err('Vui long chon file CSV.');

        $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt'], true)) err('Chi ho tro file CSV.');

        $defaultMethod = (int)($conn->query("SELECT id FROM admission_methods WHERE status='open' ORDER BY id LIMIT 1")->fetch_assoc()['id'] ?? 0);
        if ($defaultMethod <= 0) err('Chua co phuong thuc xet tuyen dang mo.');

        $majorMap = [];
        $majors = $conn->query("SELECT id, major_code, major_name FROM majors");
        while ($major = $majors->fetch_assoc()) {
            $majorMap[(string)$major['id']] = (int)$major['id'];
            $majorMap[strtolower(trim($major['major_code']))] = (int)$major['id'];
            $majorMap[strtolower(trim($major['major_name']))] = (int)$major['id'];
        }
        if (isset($majorMap['7480201'])) {
            $majorMap['cntt'] = $majorMap['7480201'];
            $majorMap['congnghethongtin'] = $majorMap['7480201'];
        }
        if (isset($majorMap['7340301'])) {
            $majorMap['ketoan'] = $majorMap['7340301'];
            $majorMap['ke_toan'] = $majorMap['7340301'];
        }

        $methodMap = [];
        $methods = $conn->query("SELECT id, method_name FROM admission_methods");
        while ($method = $methods->fetch_assoc()) {
            $methodMap[(string)$method['id']] = (int)$method['id'];
            $methodMap[strtolower(trim($method['method_name']))] = (int)$method['id'];
        }

        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if (!$handle) err('Khong doc duoc file CSV.');
        $header = fgetcsv($handle, 0, ',');
        if (!$header) err('File CSV rong hoac sai dinh dang.');
        $keys = array_map('admNormalizeHeader', $header);

        $batchId = 'TEST-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
        $activeRound = getActiveRound('test');
        if (!$activeRound) err('Chua co dot tuyen sinh Demo/Test dang hoat dong. Hay tao dot test trong menu Dot tuyen sinh va dat trang thai dang mo/xet tuyen.');
        $roundId = (int)($activeRound['id'] ?? 0) ?: null;
        $importedBy = (int)($_SESSION['user_id'] ?? 0);
        $inserted = 0; $skipped = 0; $line = 1; $errors = [];

        $stmt = $conn->prepare(
            "INSERT INTO admission_applications
             (major_id, method_id, full_name, gender, birthday, citizen_id, email, phone, address,
              high_school, graduation_year, math_score, literature_score, english_score, total_score,
              note, status, round_id, data_mode, import_batch_id, imported_by, imported_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'test',?,?,NOW())"
        );

        while (($raw = fgetcsv($handle, 0, ',')) !== false) {
            $line++;
            if (count(array_filter($raw, fn($v) => trim((string)$v) !== '')) === 0) continue;
            $row = [];
            foreach ($keys as $idx => $key) $row[$key] = $raw[$idx] ?? '';

            $fullName = admCsvValue($row, ['full_name','fullname','ho_ten','hoten','name']);
            $majorKey = strtolower(admCsvValue($row, ['major_code','major_id','ma_nganh','nganh','major']));
            $email = admCsvValue($row, ['email']);
            $phone = admCsvValue($row, ['phone','sdt','dien_thoai']);
            $citizenId = admCsvValue($row, ['citizen_id','cccd','cmnd','identification']);
            if ($fullName === '' || $majorKey === '' || $email === '') {
                $errors[] = "Dong {$line}: thieu ho ten, email hoac nganh.";
                $skipped++;
                continue;
            }
            $majorId = $majorMap[$majorKey] ?? 0;
            if ($majorId <= 0) {
                $errors[] = "Dong {$line}: khong tim thay nganh {$majorKey}.";
                $skipped++;
                continue;
            }

            if ($citizenId !== '') {
                $dup = $conn->prepare("SELECT id FROM admission_applications WHERE email=? OR citizen_id=? LIMIT 1");
                $dup->bind_param('ss', $email, $citizenId);
            } else {
                $dup = $conn->prepare("SELECT id FROM admission_applications WHERE email=? LIMIT 1");
                $dup->bind_param('s', $email);
            }
            $dup->execute();
            $exists = $dup->get_result()->num_rows > 0;
            $dup->close();
            if ($exists) {
                $errors[] = "Dong {$line}: email/CCCD da ton tai ({$email}).";
                $skipped++;
                continue;
            }

            $methodKey = strtolower(admCsvValue($row, ['method_id','method','method_name','phuong_thuc'], (string)$defaultMethod));
            $methodId = $methodMap[$methodKey] ?? (ctype_digit($methodKey) ? (int)$methodKey : $defaultMethod);
            $gender = admCsvValue($row, ['gender','gioi_tinh'], 'Nam');
            if (!in_array($gender, ['Nam','Nữ','Nu','Khác','Khac'], true)) $gender = 'Nam';
            if ($gender === 'Nu') $gender = 'Nữ';
            if ($gender === 'Khac') $gender = 'Khác';
            $birthday = admCsvValue($row, ['birthday','ngay_sinh','dob']);
            $birthday = $birthday ? date('Y-m-d', strtotime($birthday)) : null;
            $address = admCsvValue($row, ['address','dia_chi']);
            $highSchool = admCsvValue($row, ['high_school','truong_thpt','school']);
            $gradYear = admCsvValue($row, ['graduation_year','nam_tot_nghiep'], (string)date('Y'));
            $math = (float)admCsvValue($row, ['math_score','toan','math'], '0');
            $literature = (float)admCsvValue($row, ['literature_score','van','literature'], '0');
            $english = (float)admCsvValue($row, ['english_score','anh','english'], '0');
            $total = $math + $literature + $english;
            $note = trim('TEST IMPORT ' . $batchId . ' ' . admCsvValue($row, ['note','ghi_chu']));
            $status = admCsvValue($row, ['status','trang_thai'], 'new');
            if (!in_array($status, ['new','checking','approved','rejected','enrolled'], true)) $status = 'new';

            $stmt->bind_param(
                'iisssssssssddddssisi',
                $majorId, $methodId, $fullName, $gender, $birthday, $citizenId, $email, $phone, $address,
                $highSchool, $gradYear, $math, $literature, $english, $total,
                $note, $status, $roundId, $batchId, $importedBy
            );
            if ($stmt->execute()) $inserted++;
            else { $errors[] = "Dong {$line}: " . $stmt->error; $skipped++; }
        }
        fclose($handle);
        $stmt->close();

        if ($inserted === 0) {
            $reason = $errors ? ' Ly do: ' . implode(' | ', array_slice($errors, 0, 5)) : '';
            err("Khong import duoc ho so nao. Da bo qua {$skipped} dong.{$reason}");
        }

        ok("Da import {$inserted} ho so test" . ($skipped ? ", bo qua {$skipped} dong" : '') . '.', [
            'batch_id' => $batchId,
            'inserted' => $inserted,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 10),
        ]);
    }

    if ($action === 'clear_test_data') {
        if (!hasRole('admissions_manager')) err('Chi Truong phong moi co quyen xoa du lieu test.', 403);

        $batchId = trim($_POST['batch_id'] ?? '');
        $batchSql = $batchId !== '' ? " AND import_batch_id='" . $conn->real_escape_string($batchId) . "'" : '';
        $studentBatchSql = $batchId !== '' ? " AND (s.demo_batch_id='" . $conn->real_escape_string($batchId) . "' OR aa.import_batch_id='" . $conn->real_escape_string($batchId) . "')" : '';
        $stats = ['applications' => 0, 'students' => 0, 'users' => 0, 'rounds' => 0];
        $conn->begin_transaction();
        try {
            $stats['applications'] = (int)($conn->query("SELECT COUNT(*) AS c FROM admission_applications WHERE data_mode='test' $batchSql")->fetch_assoc()['c'] ?? 0);
            $stats['students'] = (int)($conn->query("SELECT COUNT(*) AS c FROM users u JOIN students s ON s.user_id=u.id LEFT JOIN admission_applications aa ON aa.email=u.email WHERE (s.data_mode='test' OR aa.data_mode='test') AND u.role='student' $studentBatchSql")->fetch_assoc()['c'] ?? 0);
            $stats['users'] = (int)($conn->query("SELECT COUNT(*) AS c FROM users u JOIN students s ON s.user_id=u.id LEFT JOIN admission_applications aa ON aa.email=u.email WHERE (s.data_mode='test' OR aa.data_mode='test') AND u.role='student' $studentBatchSql")->fetch_assoc()['c'] ?? 0);

            $conn->query("DROP TEMPORARY TABLE IF EXISTS tmp_adm_test_students");
            $conn->query("CREATE TEMPORARY TABLE tmp_adm_test_students AS
                SELECT DISTINCT u.id AS user_id, s.id AS student_id
                FROM users u
                JOIN students s ON s.user_id = u.id
                LEFT JOIN admission_applications aa ON aa.email = u.email
                WHERE (s.data_mode = 'test' OR aa.data_mode = 'test') AND u.role = 'student' $studentBatchSql");
            $conn->query("UPDATE course_sections cs
                JOIN (
                    SELECT ss.course_section_id, COUNT(*) AS c
                    FROM student_subjects ss
                    JOIN tmp_adm_test_students t ON t.student_id = ss.student_id
                    WHERE ss.status IN ('registered','auto_enrolled')
                    GROUP BY ss.course_section_id
                ) x ON x.course_section_id = cs.id
                SET cs.current_students = GREATEST(0, cs.current_students - x.c),
                    cs.status = CASE WHEN cs.status='full' AND GREATEST(0, cs.current_students - x.c) < cs.max_students THEN 'open' ELSE cs.status END");
            if (admTableExists($conn, 'tuition_payments') && admTableExists($conn, 'tuition_invoices')) {
                $conn->query("DELETE tp FROM tuition_payments tp JOIN tuition_invoices ti ON ti.id = tp.invoice_id JOIN tmp_adm_test_students t ON t.student_id = ti.student_id");
            }
            if (admTableExists($conn, 'tuition_invoices')) {
                $conn->query("DELETE ti FROM tuition_invoices ti JOIN tmp_adm_test_students t ON t.student_id = ti.student_id");
            }
            if (admTableExists($conn, 'student_evaluations')) {
                $conn->query("DELETE se FROM student_evaluations se JOIN tmp_adm_test_students t ON t.student_id = se.student_id");
            }
            if (admTableExists($conn, 'student_extra_comments')) {
                $conn->query("DELETE sec FROM student_extra_comments sec JOIN tmp_adm_test_students t ON t.student_id = sec.student_id");
            }
            if (admTableExists($conn, 'student_warnings')) {
                $conn->query("DELETE sw FROM student_warnings sw JOIN tmp_adm_test_students t ON t.student_id = sw.student_id");
            }
            if (admTableExists($conn, 'grades')) {
                $conn->query("DELETE g FROM grades g JOIN student_subjects ss ON ss.id = g.student_subject_id JOIN tmp_adm_test_students t ON t.student_id = ss.student_id");
            }
            if (admTableExists($conn, 'system_notifications')) {
                $conn->query("DELETE sn FROM system_notifications sn JOIN tmp_adm_test_students t ON t.user_id = sn.user_id");
            }
            $conn->query("DELETE pe FROM pending_enrollments pe JOIN tmp_adm_test_students t ON t.student_id = pe.student_id");
            $conn->query("DELETE ss FROM student_subjects ss JOIN tmp_adm_test_students t ON t.student_id = ss.student_id");
            $conn->query("DELETE s FROM students s JOIN tmp_adm_test_students t ON t.student_id = s.id");
            $conn->query("DELETE u FROM users u JOIN tmp_adm_test_students t ON t.user_id = u.id");
            $conn->query("DELETE FROM admission_applications WHERE data_mode='test' $batchSql");
            if ($batchId === '' && admTableExists($conn, 'admission_rounds')) {
                $stats['rounds'] = (int)($conn->query("SELECT COUNT(*) AS c FROM admission_rounds WHERE data_mode='test'")->fetch_assoc()['c'] ?? 0);
                $conn->query("DELETE FROM admission_rounds WHERE data_mode='test'");
            }
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            err('Loi xoa du lieu test: ' . $e->getMessage());
        }
        $batchMsg = $batchId !== '' ? " batch {$batchId}" : '';
        $roundMsg = $batchId === '' ? ", {$stats['rounds']} dot tuyen sinh test" : '';
        ok("Da xoa du lieu test{$batchMsg}: {$stats['applications']} ho so, {$stats['students']} sinh vien, {$stats['users']} tai khoan{$roundMsg}.");
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
                $upd = $conn->prepare("UPDATE admission_applications SET status=? WHERE id=? AND data_mode=?");
                $upd->bind_param('sis', $newSt, $id, $requestDataMode);
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
    $dataMode = $requestDataMode;
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
            $demoBatchId = $dataMode === 'test' ? ('ADMISSION-ROUND-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6)) : null;
            $stmt = $conn->prepare("INSERT INTO admission_rounds
                (year,name,reg_start,reg_end,review_start,review_end,enroll_deadline,
                 supp_reg_start,supp_reg_end,supp_review_end,supp_enroll_deadline,supp_score_bonus,
                 status,data_mode,demo_batch_id,notes,created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('issssssssssdssssi',
                $year,$name,$reg_start,$reg_end,$rev_start,$rev_end,$enroll_dl,
                $supp_reg_start,$supp_reg_end,$supp_rev_end,$supp_enroll_dl,$supp_bonus,
                $status,$dataMode,$demoBatchId,$notes,$by);
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
                WHERE id=? AND data_mode=?");
            $stmt->bind_param('issssssssssdssis',
                $year,$name,$reg_start,$reg_end,$rev_start,$rev_end,$enroll_dl,
                $supp_reg_start,$supp_reg_end,$supp_rev_end,$supp_enroll_dl,$supp_bonus,
                $status,$notes,$id,$dataMode);
            $stmt->execute() ? ok('Cập nhật thành công!') : err('Lỗi: ' . $conn->error);
            $stmt->close();
        }
        err('Lỗi: ' . $conn->error);
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) err('ID không hợp lệ.');

        $stmt = $conn->prepare("DELETE FROM admission_rounds WHERE id=? AND data_mode=?");
        $stmt->bind_param('is', $id, $dataMode);
        $stmt->execute() ? ok('Đã xóa đợt tuyển sinh.') : err('Lỗi: ' . $conn->error);
        $stmt->close();
    }

    if ($action === 'change_status') {
        $id     = (int)($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $valid  = ['draft','open','reviewing','enrolling','supplementary','completed'];
        if (!$id || !in_array($status, $valid)) err('Dữ liệu không hợp lệ.');

        $stmt = $conn->prepare("UPDATE admission_rounds SET status=? WHERE id=? AND data_mode=?");
        $stmt->bind_param('sis', $status, $id, $dataMode);
        $stmt->execute() ? ok('Đã cập nhật trạng thái!', ['status' => $status]) : err('Lỗi: ' . $conn->error);
        $stmt->close();
    }
}

// ════════════════════════════════════════════════════════════
// MODULE: auto_review
// ════════════════════════════════════════════════════════════
if ($module === 'auto_review') {
    if (!hasRole('admissions_manager')) err('Chỉ Trưởng phòng mới có quyền thực hiện thao tác này.', 403);

    $dataMode = $requestDataMode;
    $roundPhase    = getRoundPhase($dataMode);
    $activeRound   = getActiveRound($dataMode);
    $reviewAllowed = in_array($roundPhase, ['reviewing', 'supp_reviewing']);
    $modeImportKey = 'import_rows_' . $dataMode;

    if ($action === 'clear_import') {
        unset($_SESSION[$modeImportKey]);
        unset($_SESSION['auto_review_preview_' . $dataMode]);
        ok('Đã xóa dữ liệu import.');
    }

    if ($action === 'run_auto_review') {
        if (!$reviewAllowed) err('Chuc nang chi kha dung trong giai doan xet tuyen.');

        $source = $_POST['source'] ?? 'manual';
        $jobs   = [];

        if ($source === 'import' && !empty($_SESSION[$modeImportKey])) {
            $jobs = $_SESSION[$modeImportKey];
        } else {
            $major_id  = (int)($_POST['major_id'] ?? 0);
            $quota     = (int)($_POST['quota'] ?? 0);
            if (!$major_id || $quota <= 0) err('Vui long chon nganh va nhap chi tieu hop le.');

            $mRes = $conn->query("SELECT major_code, major_name FROM majors WHERE id=$major_id LIMIT 1");
            $mRow = $mRes ? $mRes->fetch_assoc() : [];
            $jobs[] = [
                'major_id'   => $major_id,
                'major_code' => $mRow['major_code'] ?? '',
                'major_name' => $mRow['major_name'] ?? '',
                'quota'      => $quota,
                'year'       => $activeRound['year'] ?? date('Y'),
            ];
        }

        if (empty($jobs)) err('Khong co du lieu de xet tuyen.');

        $results = [];
        $publishItems = [];
        foreach ($jobs as $job) {
            $mid = (int)$job['major_id'];
            $qta = (int)$job['quota'];
            if ($qta <= 0) {
                $results[] = [
                    'major_id' => $mid,
                    'major_name' => $job['major_name'],
                    'total' => 0,
                    'approved' => 0,
                    'rejected' => 0,
                    'threshold' => 0,
                    'quota' => $qta,
                    'message' => 'Chua nhap chi tieu.',
                    'approved_list' => [],
                    'rejected_list' => [],
                ];
                continue;
            }

            $stmt = $conn->prepare("
                SELECT id, full_name, email, math_score, literature_score, english_score,
                       (math_score + literature_score + english_score) AS total_score
                FROM admission_applications
                WHERE major_id = ? AND data_mode = ? AND status IN ('new','checking')
                ORDER BY total_score DESC
            ");
            $stmt->bind_param('is', $mid, $dataMode);
            $stmt->execute();
            $candidates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $cutoffIndex = min($qta, count($candidates)) - 1;
            $thr = $cutoffIndex >= 0 ? (float)$candidates[$cutoffIndex]['total_score'] : 0.0;
            $approvedList = [];
            $rejectedList = [];
            $approved = $rejected = $rank = 0;
            foreach ($candidates as $c) {
                $rank++;
                $pass = $rank <= $qta;
                $newStatus = $pass ? 'approved' : 'rejected';
                $item = [
                    'id' => (int)$c['id'],
                    'rank' => $rank,
                    'full_name' => $c['full_name'],
                    'email' => $c['email'],
                    'math_score' => (float)$c['math_score'],
                    'literature_score' => (float)$c['literature_score'],
                    'english_score' => (float)$c['english_score'],
                    'total_score' => (float)$c['total_score'],
                    'result' => $newStatus,
                ];
                if ($pass) $approvedList[] = $item;
                else $rejectedList[] = $item;
                $publishItems[] = ['id' => (int)$c['id'], 'status' => $newStatus];
                $pass ? $approved++ : $rejected++;
            }

            $results[] = [
                'major_id' => $mid,
                'major_name' => $job['major_name'],
                'total' => count($candidates),
                'approved' => $approved,
                'rejected' => $rejected,
                'threshold' => $thr,
                'quota' => $qta,
                'approved_list' => $approvedList,
                'rejected_list' => $rejectedList,
            ];
        }

        $previewToken = bin2hex(random_bytes(16));
        $_SESSION['auto_review_preview_' . $dataMode] = [
            'token' => $previewToken,
            'data_mode' => $dataMode,
            'created_at' => time(),
            'items' => $publishItems,
            'results' => $results,
        ];

        ok('Da tao ban xem truoc. Kiem tra danh sach roi bam Cong bo ket qua.', [
            'results' => $results,
            'preview_token' => $previewToken,
            'pending_publish' => count($publishItems),
        ]);
    }

    if ($action === 'publish_auto_review') {
        if (!$reviewAllowed) err('Chuc nang chi kha dung trong giai doan xet tuyen.');
        $previewKey = 'auto_review_preview_' . $dataMode;
        $preview = $_SESSION[$previewKey] ?? null;
        $token = trim($_POST['preview_token'] ?? '');
        if (!$preview || !hash_equals((string)$preview['token'], $token)) {
            err('Ban xem truoc khong con hop le. Vui long chay lai xet tuyen.');
        }
        if (time() - (int)$preview['created_at'] > 1800) {
            unset($_SESSION[$previewKey]);
            err('Ban xem truoc da het han. Vui long chay lai xet tuyen.');
        }

        $published = 0;
        $stmt = $conn->prepare("UPDATE admission_applications SET status=? WHERE id=? AND data_mode=? AND status IN ('new','checking')");
        if (!$stmt) err('Loi: ' . $conn->error);
        foreach ($preview['items'] as $item) {
            $newStatus = $item['status'];
            $id = (int)$item['id'];
            $stmt->bind_param('sis', $newStatus, $id, $dataMode);
            $stmt->execute();
            $published += $stmt->affected_rows > 0 ? 1 : 0;
        }
        $stmt->close();
        unset($_SESSION[$previewKey]);
        ok("Da cong bo ket qua cho {$published} ho so.", ['published' => $published]);
    }

}

err('Hành động không hợp lệ.');
