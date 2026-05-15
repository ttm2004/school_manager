<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../app/Services/AdmissionsEnrollmentService.php';
requireAnyRole(['admissions_manager']);
$pageTitle = 'Phân lớp & Cấp tài khoản tự động';

$success = $error = '';
$preview = null;
$assignResult = null;
$dataMode = ($_GET['mode'] ?? 'system') === 'test' ? 'test' : 'system';
$classModeColumn = false;
$classModeCheck = $conn->query("SHOW COLUMNS FROM `classes` LIKE 'data_mode'");
if ($classModeCheck && $classModeCheck->num_rows > 0) {
    $classModeColumn = true;
}

// ── Kiểm tra giai đoạn đợt tuyển sinh ──────────────────────
$roundPhase  = getRoundPhase($dataMode);
$activeRound = getActiveRound($dataMode);
$roundMsg    = getRoundStatusMessage($dataMode);

// auto_assign CHỈ mở sau khi xét tuyển kết thúc:
// enrolling, supp_enrolling, completed (nếu còn SV chưa phân lớp)
$assignAllowed = in_array($roundPhase, ['enrolling', 'supp_enrolling', 'after_enroll', 'completed']);
$assignLocked  = !$assignAllowed;

// Kiểm tra còn sinh viên chưa phân lớp không
$pendingAssign = $conn->query("
    SELECT COUNT(*) as c FROM admission_applications aa
    WHERE aa.status = 'enrolled'
    AND aa.data_mode = '" . $conn->real_escape_string($dataMode) . "'
    AND (SELECT u.id FROM users u JOIN students s ON u.id=s.user_id WHERE u.email=aa.email AND s.data_mode=aa.data_mode LIMIT 1) IS NULL
")->fetch_assoc()['c'] ?? 0;

// Nếu không còn SV nào chờ phân lớp → khóa luôn
if ($pendingAssign === 0 && $assignAllowed) {
    $assignLocked = true;
    $noStudentsLeft = true;
} else {
    $noStudentsLeft = false;
}

// Lấy danh sách ngành có hồ sơ enrolled chưa có tài khoản
$majors = $conn->query("
    SELECT m.id, m.major_name, m.major_code,
        COALESCE(ar.year, aa.graduation_year, YEAR(aa.created_at)) as target_year,
        COUNT(aa.id) as enrolled_count,
        SUM(CASE WHEN (SELECT u.id FROM users u JOIN students s ON u.id=s.user_id WHERE u.email=aa.email AND s.data_mode=aa.data_mode LIMIT 1) IS NOT NULL THEN 1 ELSE 0 END) as has_account_count
    FROM majors m
    JOIN admission_applications aa ON aa.major_id = m.id AND aa.status = 'enrolled' AND aa.data_mode = '" . $conn->real_escape_string($dataMode) . "'
    LEFT JOIN admission_rounds ar ON ar.id = aa.round_id
    GROUP BY m.id, target_year
    HAVING enrolled_count > 0
    ORDER BY target_year DESC, m.major_name
");

// Lấy tất cả lớp học
$classModeSql = $classModeColumn ? "WHERE COALESCE(c.data_mode, 'system') = '" . $conn->real_escape_string($dataMode) . "'" : '';
$allClasses = $conn->query("
    SELECT c.id, c.class_name, c.class_code, c.school_year, c.enrollment_year, m.id as major_id, m.major_name,
        " . ($classModeColumn ? "COALESCE(c.data_mode, 'system')" : "'system'") . " as class_data_mode,
        SUM(CASE WHEN s.data_mode = '" . $conn->real_escape_string($dataMode) . "' THEN 1 ELSE 0 END) as current_count
    FROM classes c
    LEFT JOIN majors m ON c.major_id = m.id
    LEFT JOIN students s ON s.class_id = c.id
    $classModeSql
    GROUP BY c.id
    ORDER BY m.major_name, c.class_name
");
$classArr = [];
if ($allClasses) while ($cl = $allClasses->fetch_assoc()) $classArr[] = $cl;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action'] ?? '';
    $major_id   = intval($_POST['major_id'] ?? 0);
    $targetYear = intval($_POST['target_year'] ?? 0);
    $algorithm  = $_POST['algorithm'] ?? 'alpha'; // alpha | score
    $class_ids  = array_map('intval', $_POST['class_ids'] ?? []);
    $autoEnrollMode = ($_POST['auto_enroll_mode'] ?? 'system') === 'test' ? 'test' : 'system';
    $dataMode = ($_POST['data_mode'] ?? 'system') === 'test' ? 'test' : 'system';

    if (!$major_id || !$targetYear || empty($class_ids)) {
        $error = 'Vui lòng chọn ngành và ít nhất một lớp.';
    } else {
        // Lấy sinh viên enrolled chưa có tài khoản của ngành này
        $stmt = $conn->prepare("
            SELECT aa.id, aa.full_name, aa.email, aa.phone, aa.address, aa.birthday, aa.gender,
                aa.major_id, aa.data_mode, aa.import_batch_id, aa.created_at, m.major_code,
                (aa.math_score + aa.literature_score + aa.english_score) as total_score,
                COALESCE(ar.year, aa.graduation_year, YEAR(aa.created_at)) as enroll_year
            FROM admission_applications aa
            LEFT JOIN majors m ON aa.major_id = m.id
            LEFT JOIN admission_rounds ar ON ar.id = aa.round_id
            WHERE aa.major_id = ? AND aa.status = 'enrolled' AND aa.data_mode = ?
            AND COALESCE(ar.year, aa.graduation_year, YEAR(aa.created_at)) = ?
            AND (SELECT u.id FROM users u JOIN students s ON u.id=s.user_id WHERE u.email=aa.email AND s.data_mode=aa.data_mode LIMIT 1) IS NULL
        ");
        $stmt->bind_param('isi', $major_id, $dataMode, $targetYear);
        $stmt->execute();
        $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($students)) {
            $error = 'Không có sinh viên nào cần phân lớp (tất cả đã có tài khoản hoặc chưa nhập học).';
        } else {
            $validClassIds = array_values(array_unique(array_filter($class_ids)));
            if (empty($validClassIds)) {
                $validClassCount = 0;
            } else {
                $placeholders = implode(',', array_fill(0, count($validClassIds), '?'));
                $types = str_repeat('i', count($validClassIds)) . 'ii';
                $params = array_merge($validClassIds, [$major_id, $targetYear]);
                $classCheck = $conn->prepare("
                    SELECT COUNT(*) as c
                    FROM classes
                    WHERE id IN ($placeholders)
                      AND major_id = ?
                      AND enrollment_year = ?
                      " . ($classModeColumn ? "AND COALESCE(data_mode, 'system') = ?" : "") . "
                ");
                if ($classModeColumn) {
                    $types .= 's';
                    $params[] = $dataMode;
                }
                $classCheck->bind_param($types, ...$params);
                $classCheck->execute();
                $validClassCount = (int)($classCheck->get_result()->fetch_assoc()['c'] ?? 0);
                $classCheck->close();
            }

            if (empty($validClassIds) || $validClassCount !== count($validClassIds)) {
                $error = 'Chỉ được chọn lớp đúng ngành và đúng khóa tuyển sinh ' . $targetYear . '. Khoa/Viện hoặc Phòng Đào tạo cần mở lớp hành chính đúng ngành, đúng năm tuyển sinh trước.';
            } else {
            $class_ids = $validClassIds;
            // Sắp xếp theo thuật toán
            if ($algorithm === 'alpha') {
                // Sắp xếp theo họ (từ cuối cùng trong tên) A→Z
                usort($students, function($a, $b) {
                    $nameA = mb_strtolower(trim(mb_substr($a['full_name'], mb_strrpos($a['full_name'], ' ') + 1)));
                    $nameB = mb_strtolower(trim(mb_substr($b['full_name'], mb_strrpos($b['full_name'], ' ') + 1)));
                    return strcmp($nameA, $nameB);
                });
            } else {
                // Sắp xếp theo điểm DESC
                usort($students, function($a, $b) {
                    return $b['total_score'] <=> $a['total_score'];
                });
            }

            // Phân lớp round-robin
            $numClasses = count($class_ids);
            $assignments = []; // app_id => class_id
            foreach ($students as $i => $sv) {
                $assignments[$sv['id']] = $class_ids[$i % $numClasses];
            }

            // Preview hoặc thực hiện
            if ($action === 'preview') {
                // Nhóm theo lớp để hiển thị
                $preview = [];
                foreach ($class_ids as $cid) {
                    $className = '';
                    foreach ($classArr as $cl) {
                        if ($cl['id'] == $cid) { $className = $cl['class_name']; break; }
                    }
                    $preview[$cid] = ['class_name' => $className, 'students' => []];
                }
                foreach ($students as $i => $sv) {
                    $cid = $class_ids[$i % $numClasses];
                    $preview[$cid]['students'][] = $sv;
                }
            } elseif ($action === 'execute') {
                // Thực hiện phân lớp + tạo tài khoản
                $conn->begin_transaction();
                try {
                    $created = 0; $failed = 0;
                    foreach ($students as $i => $sv) {
                        $cid = $class_ids[$i % $numClasses];
                        $classStmt = $conn->prepare(
                            "SELECT c.major_id, c.class_name, c.enrollment_year, c.cohort_id, tc.program_id, tc.duration_years
                             FROM classes c
                             LEFT JOIN training_cohorts tc ON c.cohort_id = tc.id
                             WHERE c.id = ? AND c.major_id = ? AND c.enrollment_year = ?
                             " . ($classModeColumn ? "AND COALESCE(c.data_mode, 'system') = ?" : "") . "
                             LIMIT 1"
                        );
                        if ($classModeColumn) {
                            $classStmt->bind_param('iiis', $cid, $major_id, $targetYear, $dataMode);
                        } else {
                            $classStmt->bind_param('iii', $cid, $major_id, $targetYear);
                        }
                        $classStmt->execute();
                        $classInfo = $classStmt->get_result()->fetch_assoc();
                        $classStmt->close();
                        if (!$classInfo) {
                            throw new Exception('Lớp phân vào không đúng ngành hoặc không đúng khóa tuyển sinh.');
                        }
                        $year = (int)($classInfo['enrollment_year'] ?? $sv['enroll_year']);
                        $cohortId = (int)($classInfo['cohort_id'] ?? 0);
                        $programId = (int)($classInfo['program_id'] ?? 0);
                        $expectedGradYear = $year + (int)ceil((float)($classInfo['duration_years'] ?? 4));
                        $majorCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $sv['major_code'] ?? 'SV'));

                        // Sinh mã SV ổn định theo hồ sơ và kiểm tra cả tài khoản đăng nhập.
                        $baseStudentCode = $year . $majorCode . str_pad((string)$sv['id'], 5, '0', STR_PAD_LEFT);
                        $suffix = 0;
                        do {
                            $studentCode = $baseStudentCode . ($suffix > 0 ? str_pad((string)$suffix, 2, '0', STR_PAD_LEFT) : '');
                            $username = strtolower($studentCode);
                            $dupStmt = $conn->prepare(
                                "(SELECT 1 FROM users WHERE username = ?)
                                 UNION
                                 (SELECT 1 FROM students WHERE student_code = ?)
                                 LIMIT 1"
                            );
                            $dupStmt->bind_param('ss', $username, $studentCode);
                            $dupStmt->execute();
                            $exists = $dupStmt->get_result()->num_rows > 0;
                            $dupStmt->close();
                            $suffix++;
                        } while ($exists);

                        $hashed   = password_hash($studentCode, PASSWORD_DEFAULT);

                        // Tạo user
                        $us = $conn->prepare("INSERT INTO users (username,password,full_name,email,phone,role,status) VALUES (?,?,?,?,?,'student',1)");
                        $us->bind_param('sssss', $username, $hashed, $sv['full_name'], $sv['email'], $sv['phone']);
                        if (!$us->execute()) {
                            $userError = $us->error ?: $conn->error;
                            $us->close();
                            throw new Exception('Không tạo được tài khoản cho ' . $sv['full_name'] . ': ' . $userError);
                        }
                        $userId = $conn->insert_id;
                        $us->close();

                        // Tạo student
                        $studentId = AdmissionsEnrollmentService::createStudentProfile($conn, $userId, $studentCode, $cid, $sv, $classInfo, $autoEnrollMode, null, true);
                        AdmissionsEnrollmentService::createAutoEnrollmentRequest(
                            $conn,
                            $studentId,
                            (int)$sv['id'],
                            $cid,
                            $classInfo,
                            $autoEnrollMode,
                            (int)($_SESSION['user_id'] ?? 0)
                        );
                        AdmissionsEnrollmentService::notifyAcademicAndFacultyAssignment(
                            $conn,
                            $studentId,
                            (int)$sv['major_id'],
                            $studentCode,
                            $sv['full_name'],
                            (string)($classInfo['class_name'] ?? ('#' . $cid))
                        );
                        AdmissionsEnrollmentService::notifyFinanceNewEnrollment($conn, $studentId, $studentCode, $sv['full_name']);
                        $created++;
                    }
                    $conn->commit();
                    $assignResult = ['created' => $created, 'failed' => $failed, 'total' => count($students)];
                    $success = "Phân lớp hoàn tất: <strong>$created</strong> tài khoản đã tạo" . ($failed > 0 ? ", <strong>$failed</strong> lỗi" : '') . '. Danh sách đã gửi cho Khoa/Viện và Phòng Đào tạo duyệt đăng ký môn HK1.';
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = 'Lỗi: ' . $e->getMessage();
                }
            }
            }
        }
    }

    // ── PRG: redirect sau POST để tránh F5 gửi lại form ──
    // Chỉ redirect khi không có preview/result cần hiển thị
    if ((!empty($success) || !empty($error)) && $preview === null && $assignResult === null) {
        $_SESSION['_flash'] = [
            'type'    => !empty($success) ? 'success' : 'danger',
            'message' => !empty($success) ? $success : $error,
        ];
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . ($qs ? '?' . $qs : ''));
        exit();
    }
}

include __DIR__ . '/includes/header.php';
?>

<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show auto-dismiss">
    <i class="bi bi-<?php echo $flash['type']==='success'?'check-circle-fill':'exclamation-circle-fill'; ?> me-2"></i>
    <?php echo htmlspecialchars($flash['message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show auto-dismiss"><i class="bi bi-<?php echo $flash['type']==='success'?'check-circle-fill':'exclamation-circle-fill'; ?> me-2"></i><?php echo htmlspecialchars($flash['message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show auto-dismiss"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show auto-dismiss"><i class="bi bi-exclamation-circle-fill me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row g-4">
    <!-- Form phân lớp -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-diagram-3-fill me-2"></i>Cấu hình phân lớp tự động</div>
            <div class="card-body">
                <form method="POST" id="assignForm">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Luồng hồ sơ</label>
                        <select name="data_mode" class="form-select" onchange="window.location.href='auto_assign.php?mode='+this.value">
                            <option value="system" <?php echo $dataMode==='system'?'selected':''; ?>>Dữ liệu thật</option>
                            <option value="test" <?php echo $dataMode==='test'?'selected':''; ?>>Test / Demo</option>
                        </select>
                    </div>
                    <!-- Chọn ngành -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Ngành xét tuyển <span class="text-danger">*</span></label>
                        <select name="major_id" id="majorSelect" class="form-select" required onchange="filterClasses()">
                            <option value="">-- Chọn ngành --</option>
                            <?php if ($majors && $majors->num_rows > 0): while ($m = $majors->fetch_assoc()): ?>
                            <option value="<?php echo $m['id']; ?>"
                                data-year="<?php echo (int)$m['target_year']; ?>"
                                data-count="<?php echo $m['enrolled_count']; ?>"
                                data-done="<?php echo $m['has_account_count']; ?>">
                                <?php echo htmlspecialchars($m['major_name']); ?>
                                - khóa <?php echo (int)$m['target_year']; ?>
                                (<?php echo $m['enrolled_count'] - $m['has_account_count']; ?> chờ phân lớp)
                            </option>
                            <?php endwhile; else: ?>
                            <option disabled>Không có ngành nào có sinh viên chờ phân lớp</option>
                            <?php endif; ?>
                        </select>
                        <input type="hidden" name="target_year" id="targetYearInput" value="">
                        <div id="majorInfo" class="form-text"></div>
                    </div>

                    <!-- Thuật toán -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Thuật toán sắp xếp</label>
                        <div class="d-flex flex-column gap-2">
                            <div class="form-check p-3 border rounded" style="cursor:pointer" onclick="document.getElementById('algoAlpha').click()">
                                <input class="form-check-input" type="radio" name="algorithm" id="algoAlpha" value="alpha" checked>
                                <label class="form-check-label fw-semibold" for="algoAlpha" style="cursor:pointer">
                                    <i class="bi bi-sort-alpha-down me-2 text-primary"></i>Theo tên (A→Z)
                                </label>
                                <div class="text-muted small mt-1">Sắp xếp theo tên cuối (họ tên đệm + tên), phân đều vào các lớp theo vòng tròn. Phổ biến nhất tại các trường ĐH Việt Nam.</div>
                            </div>
                            <div class="form-check p-3 border rounded" style="cursor:pointer" onclick="document.getElementById('algoScore').click()">
                                <input class="form-check-input" type="radio" name="algorithm" id="algoScore" value="score">
                                <label class="form-check-label fw-semibold" for="algoScore" style="cursor:pointer">
                                    <i class="bi bi-bar-chart-fill me-2 text-success"></i>Theo điểm (Round-robin)
                                </label>
                                <div class="text-muted small mt-1">Sắp xếp điểm cao→thấp, phân vòng tròn. Mỗi lớp có trình độ đồng đều, tránh lớp toàn giỏi/yếu.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Chọn lớp -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Chọn lớp phân vào <span class="text-danger">*</span></label>
                        <div id="classCheckboxes" class="border rounded p-3" style="max-height:220px;overflow-y:auto;">
                            <div class="text-muted small fst-italic">Chọn ngành trước để lọc lớp</div>
                        </div>
                        <div class="form-text">Chọn nhiều lớp — sinh viên sẽ được phân đều theo vòng tròn.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Chế độ đăng ký HK1 tự động</label>
                        <div class="d-flex flex-column gap-2">
                            <label class="form-check border rounded p-2 mb-0">
                                <input class="form-check-input" type="radio" name="auto_enroll_mode" value="system" <?php echo $dataMode==='test'?'':'checked'; ?>>
                                <span class="form-check-label fw-semibold">Theo hệ thống thật</span>
                                <div class="small text-muted">Tìm HK1 đúng khóa/năm học, dùng cho vận hành thật.</div>
                            </label>
                            <label class="form-check border rounded p-2 mb-0">
                                <input class="form-check-input" type="radio" name="auto_enroll_mode" value="test" <?php echo $dataMode==='test'?'checked':''; ?>>
                                <span class="form-check-label fw-semibold">Theo học kỳ Test</span>
                                <div class="small text-muted">Ưu tiên học kỳ Test cùng năm học để demo quy trình nhanh.</div>
                            </label>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" name="action" value="preview" class="btn btn-outline-primary">
                            <i class="bi bi-eye me-2"></i>Xem trước kết quả phân lớp
                        </button>
                        <button type="submit" name="action" value="execute" class="btn btn-gold"
                            onclick="return confirm('Xác nhận phân lớp và tạo tài khoản cho tất cả sinh viên?')">
                            <i class="bi bi-play-circle-fill me-2"></i>Thực hiện phân lớp & Cấp tài khoản
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Preview / Kết quả -->
    <div class="col-lg-7">
        <?php if ($assignResult): ?>
        <!-- Kết quả thực hiện -->
        <div class="card border-0" style="background:linear-gradient(135deg,#d1fae5,#a7f3d0)">
            <div class="card-body text-center py-4">
                <i class="bi bi-check-circle-fill text-success" style="font-size:3rem"></i>
                <h4 class="fw-bold mt-3 mb-1">Phân lớp hoàn tất!</h4>
                <div class="row g-3 mt-2 justify-content-center">
                    <div class="col-4">
                        <div class="bg-white rounded p-3">
                            <div class="fs-2 fw-bold text-success"><?php echo $assignResult['created']; ?></div>
                            <div class="small text-muted">Tài khoản đã tạo</div>
                        </div>
                    </div>
                    <?php if ($assignResult['failed'] > 0): ?>
                    <div class="col-4">
                        <div class="bg-white rounded p-3">
                            <div class="fs-2 fw-bold text-danger"><?php echo $assignResult['failed']; ?></div>
                            <div class="small text-muted">Lỗi</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="mt-3">
                    <a href="enrollment.php?tab=enrolled" class="btn btn-navy me-2">
                        <i class="bi bi-list me-1"></i>Xem danh sách nhập học
                    </a>
                    <a href="assigned_classes.php?mode=<?php echo urlencode($dataMode); ?>" class="btn btn-outline-primary me-2">
                        <i class="bi bi-collection-fill me-1"></i>Xem lớp đã phân
                    </a>
                    <a href="auto_assign.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise me-1"></i>Phân lớp tiếp
                    </a>
                </div>
            </div>
        </div>

        <?php elseif ($preview): ?>
        <!-- Preview phân lớp -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-eye me-2"></i>Xem trước kết quả phân lớp</span>
                <span class="badge bg-gold text-navy"><?php echo array_sum(array_map(fn($c) => count($c['students']), $preview)); ?> sinh viên</span>
            </div>
            <div class="card-body p-0">
                <?php foreach ($preview as $cid => $classData): ?>
                <div class="border-bottom">
                    <div class="px-3 py-2 d-flex justify-content-between align-items-center" style="background:#f8f9ff">
                        <span class="fw-bold text-navy"><i class="bi bi-people-fill me-2"></i><?php echo htmlspecialchars($classData['class_name']); ?></span>
                        <span class="badge bg-navy"><?php echo count($classData['students']); ?> SV</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>#</th><th>Họ tên</th><th>Điểm</th><th>Email</th></tr></thead>
                            <tbody>
                            <?php foreach ($classData['students'] as $i => $sv): ?>
                            <tr>
                                <td class="text-muted small"><?php echo $i+1; ?></td>
                                <td class="small fw-semibold"><?php echo htmlspecialchars($sv['full_name']); ?></td>
                                <td class="small text-success fw-bold"><?php echo number_format($sv['total_score'],2); ?></td>
                                <td class="small text-muted"><?php echo htmlspecialchars($sv['email']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="card-body border-top">
                <form method="POST">
                    <input type="hidden" name="major_id" value="<?php echo intval($_POST['major_id']); ?>">
                    <input type="hidden" name="target_year" value="<?php echo intval($_POST['target_year'] ?? 0); ?>">
                    <input type="hidden" name="algorithm" value="<?php echo htmlspecialchars($_POST['algorithm']); ?>">
                    <input type="hidden" name="data_mode" value="<?php echo htmlspecialchars($_POST['data_mode'] ?? $dataMode); ?>">
                    <input type="hidden" name="auto_enroll_mode" value="<?php echo htmlspecialchars($_POST['auto_enroll_mode'] ?? 'system'); ?>">
                    <?php foreach ($_POST['class_ids'] ?? [] as $cid): ?>
                    <input type="hidden" name="class_ids[]" value="<?php echo intval($cid); ?>">
                    <?php endforeach; ?>
                    <div class="alert alert-warning py-2 small mb-3">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Sau khi thực hiện, hệ thống sẽ tạo tài khoản và phân lớp cho tất cả sinh viên trên. Không thể hoàn tác tự động.
                    </div>
                    <button type="submit" name="action" value="execute" class="btn btn-gold w-100"
                        onclick="return confirm('Xác nhận thực hiện phân lớp?')">
                        <i class="bi bi-play-circle-fill me-2"></i>Xác nhận thực hiện phân lớp & Cấp tài khoản
                    </button>
                </form>
            </div>
        </div>

        <?php else: ?>
        <!-- Hướng dẫn -->
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-info-circle me-2"></i>Hướng dẫn</div>
            <div class="card-body">
                <div class="d-flex flex-column gap-3">
                    <div class="d-flex gap-3 align-items-start">
                        <div class="rounded-circle bg-navy text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width:32px;height:32px;font-weight:700">1</div>
                        <div><div class="fw-semibold">Chọn ngành</div><div class="text-muted small">Chọn ngành có sinh viên đã nhập học nhưng chưa được phân lớp.</div></div>
                    </div>
                    <div class="d-flex gap-3 align-items-start">
                        <div class="rounded-circle bg-navy text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width:32px;height:32px;font-weight:700">2</div>
                        <div><div class="fw-semibold">Chọn thuật toán</div>
                            <div class="text-muted small"><strong>Theo tên A→Z:</strong> Sắp xếp tên, phân đều vào lớp. Phổ biến nhất.<br>
                            <strong>Round-robin điểm:</strong> Mỗi lớp có trình độ đồng đều.</div>
                        </div>
                    </div>
                    <div class="d-flex gap-3 align-items-start">
                        <div class="rounded-circle bg-navy text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width:32px;height:32px;font-weight:700">3</div>
                        <div><div class="fw-semibold">Chọn lớp</div><div class="text-muted small">Chọn các lớp sẽ nhận sinh viên. Sinh viên được phân đều theo vòng tròn.</div></div>
                    </div>
                    <div class="d-flex gap-3 align-items-start">
                        <div class="rounded-circle bg-navy text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width:32px;height:32px;font-weight:700">4</div>
                        <div><div class="fw-semibold">Xem trước → Thực hiện</div><div class="text-muted small">Xem trước kết quả trước khi tạo tài khoản hàng loạt.</div></div>
                    </div>
                </div>

                <div class="alert alert-info mt-4 small">
                    <i class="bi bi-lightbulb me-1"></i>
                    <strong>Ví dụ:</strong> 100 SV ngành CNTT, 5 lớp → mỗi lớp 20 SV.<br>
                    Thuật toán A→Z: Nguyễn Văn An → lớp 01, Trần Thị Bình → lớp 02, ...<br>
                    Thuật toán điểm: SV điểm cao nhất → lớp 01, cao nhì → lớp 02, ... (đồng đều trình độ)
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<script>
const allClasses = <?php echo json_encode($classArr, JSON_UNESCAPED_UNICODE); ?>;

function filterClasses() {
    const majorId = parseInt(document.getElementById('majorSelect').value);
    const opt = document.getElementById('majorSelect').selectedOptions[0];
    const count = opt ? parseInt(opt.dataset.count || 0) - parseInt(opt.dataset.done || 0) : 0;
    const targetYear = opt ? parseInt(opt.dataset.year || 0) : 0;
    const targetYearInput = document.getElementById('targetYearInput');
    if (targetYearInput) targetYearInput.value = targetYear || '';
    document.getElementById('majorInfo').textContent = majorId
        ? `${count} sinh viên chờ phân lớp cho khóa tuyển sinh ${targetYear || 'chưa xác định'}`
        : '';

    const container = document.getElementById('classCheckboxes');
    if (!majorId) {
        container.innerHTML = '<div class="text-muted small fst-italic">Chọn ngành trước để lọc lớp</div>';
        return;
    }

    const filtered = allClasses.filter(c =>
        c.major_id == majorId && parseInt(c.enrollment_year || 0) === targetYear
    );

    if (filtered.length === 0) {
        container.innerHTML = `<div class="text-muted small fst-italic">
            Chưa có lớp hành chính đúng ngành cho khóa tuyển sinh ${targetYear || ''}.
            Phòng Đào tạo cần vào Lớp hành chính và dùng nút "Mở lớp từ đợt tuyển sinh" để tạo lớp đúng ngành, đúng khóa, đúng chế độ dữ liệu trước.
        </div>`;
        return;
    }

    container.innerHTML = filtered.map(c => `
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="class_ids[]"
                value="${c.id}" id="cls${c.id}">
            <label class="form-check-label" for="cls${c.id}">
                <span class="fw-semibold">${c.class_name}</span>
                <span class="text-muted small ms-1">(${c.school_year || 'N/A'}) - khóa ${c.enrollment_year || targetYear} - hiện có ${c.current_count || 0} SV trong luồng này</span>
            </label>
        </div>
    `).join('') + `
        <div class="mt-2 pt-2 border-top">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAllClasses()">Chọn tất cả</button>
            <button type="button" class="btn btn-sm btn-outline-secondary ms-1" onclick="clearAllClasses()">Bỏ chọn</button>
        </div>
    `;
}

function selectAllClasses() {
    document.querySelectorAll('#classCheckboxes input[type=checkbox]').forEach(cb => cb.checked = true);
}

function clearAllClasses() {
    document.querySelectorAll('#classCheckboxes input[type=checkbox]').forEach(cb => cb.checked = false);
}

// Restore selections after preview
<?php if ($preview || $assignResult): ?>
(() => {
    const selectedMajor = '<?php echo intval($_POST['major_id'] ?? 0); ?>';
    const selectedYear = '<?php echo intval($_POST['target_year'] ?? 0); ?>';
    const majorSelect = document.getElementById('majorSelect');
    Array.from(majorSelect.options).forEach(opt => {
        opt.selected = opt.value === selectedMajor && (opt.dataset.year || '') === selectedYear;
    });
})();
filterClasses();
<?php $selectedIds = array_map('intval', $_POST['class_ids'] ?? []); ?>
setTimeout(() => {
    <?php foreach ($selectedIds as $cid): ?>
    const cb<?php echo $cid; ?> = document.getElementById('cls<?php echo $cid; ?>');
    if (cb<?php echo $cid; ?>) cb<?php echo $cid; ?>.checked = true;
    <?php endforeach; ?>
}, 100);
<?php endif; ?>
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
