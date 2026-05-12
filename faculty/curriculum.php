<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once 'includes/faculty_helpers.php';
requireAnyRole(['faculty_manager', 'faculty_staff', 'dept_head']);

$pageTitle = 'Chương trình đào tạo';
$userId    = (int)$_SESSION['user_id'];
$facultyId = getFacultyId($conn, $userId);
$ip        = $_SERVER['REMOTE_ADDR'] ?? '';

if ($facultyId <= 0 && ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Tài khoản chưa được gán vào khoa nào.'];
    header('Location: /university/login.php');
    exit();
}

function normalizeCurriculumType(?string $type): string
{
    $type = trim((string)$type);
    return match ($type) {
        'elective', 'tu chon', 'Tự chọn', 'Tá»± chá»n' => 'elective',
        'general', 'dai cuong', 'Đại cương', 'Äáº¡i cÆ°Æ¡ng' => 'general',
        default => 'required',
    };
}

function curriculumTypeMeta(?string $type): array
{
    return match (normalizeCurriculumType($type)) {
        'elective' => ['Tự chọn', 'warning'],
        'general' => ['Đại cương', 'info'],
        default => ['Bắt buộc', 'primary'],
    };
}

function isCurriculumMandatory(array $row): bool
{
    if (array_key_exists('subject_is_mandatory', $row) && $row['subject_is_mandatory'] !== null) {
        return (int)$row['subject_is_mandatory'] === 1;
    }
    return normalizeCurriculumType($row['subject_type'] ?? '') !== 'elective';
}

function displayUtf8(?string $value): string
{
    $value = (string)$value;
    if ($value === '') return '';

    if (!preg_match('/(Ã|Æ|áº|á»|Ä)/u', $value)) {
        return $value;
    }

    $bytes = @iconv('UTF-8', 'Windows-1252//IGNORE', $value);
    if ($bytes !== false && mb_check_encoding($bytes, 'UTF-8')) {
        $badBefore = preg_match_all('/(Ã|Æ|áº|á»|Ä)/u', $value);
        $badAfter  = preg_match_all('/(Ã|Æ|áº|á»|Ä)/u', $bytes);
        if ($badAfter < $badBefore) return $bytes;
    }

    return $value;
}

// POST Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isFacultyManager()) {
        $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Bạn không có quyền thực hiện thao tác này.'];
        header('Location: curriculum.php');
        exit();
    }
    $action = trim($_POST['action'] ?? '');

    // ADD
    if ($action === 'add') {
        $majorId2   = (int)($_POST['major_id'] ?? 0);
        $subjectId  = (int)($_POST['subject_id'] ?? 0);
        $credits    = (int)($_POST['credits'] ?? 0);
        $sugSem     = (int)($_POST['suggested_semester'] ?? 0);
        $subType    = normalizeCurriculumType($_POST['subject_type'] ?? 'required');
        $prereqIds  = trim($_POST['prerequisite_ids'] ?? '');

        if ($majorId2 <= 0 || $subjectId <= 0 || $credits <= 0 || $sugSem <= 0) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Dữ liệu không hợp lệ.'];
            header('Location: curriculum.php?major_id=' . $majorId2);
            exit();
        }

        // Kiem tra major thuoc faculty
        $stmtMChk = $conn->prepare("SELECT id FROM majors WHERE id = ? AND faculty_id = ? LIMIT 1");
        $stmtMChk->bind_param('ii', $majorId2, $facultyId);
        $stmtMChk->execute();
        if ($stmtMChk->get_result()->num_rows === 0) {
            $stmtMChk->close();
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Không có quyền chỉnh sửa chương trình đào tạo của ngành thuộc khoa khác.'];
            header('Location: curriculum.php');
            exit();
        }
        $stmtMChk->close();

        // Kiem tra duplicate
        $stmtDup = $conn->prepare("SELECT id FROM curriculum WHERE major_id = ? AND subject_id = ? AND deleted_at IS NULL LIMIT 1");
        $stmtDup->bind_param('ii', $majorId2, $subjectId);
        $stmtDup->execute();
        if ($stmtDup->get_result()->num_rows > 0) {
            $stmtDup->close();
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Môn học này đã có trong chương trình đào tạo của ngành.'];
            header('Location: curriculum.php?major_id=' . $majorId2);
            exit();
        }
        $stmtDup->close();

        // Validate prerequisite_ids
        if ($prereqIds !== '') {
            $prereqArr = array_filter(array_map('intval', explode(',', $prereqIds)));
            foreach ($prereqArr as $pid) {
                $stmtPre = $conn->prepare("SELECT id FROM curriculum WHERE major_id = ? AND subject_id = ? AND deleted_at IS NULL LIMIT 1");
                $stmtPre->bind_param('ii', $majorId2, $pid);
                $stmtPre->execute();
                if ($stmtPre->get_result()->num_rows === 0) {
                    $stmtPre->close();
                    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Môn tiên quyết không tồn tại trong chương trình đào tạo của ngành.'];
                    header('Location: curriculum.php?major_id=' . $majorId2);
                    exit();
                }
                $stmtPre->close();
            }
        }

        $stmtIns = $conn->prepare("INSERT INTO curriculum (major_id, subject_id, credits, suggested_semester, semester_label, year_label, subject_type, prerequisite_ids) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $semLabel2 = trim($_POST['semester_label'] ?? '');
        $yearLabel2 = trim($_POST['year_label'] ?? '');
        $stmtIns->bind_param('iiiissss', $majorId2, $subjectId, $credits, $sugSem, $semLabel2, $yearLabel2, $subType, $prereqIds);
        $stmtIns->execute();
        $newId = (int)$conn->insert_id;
        $stmtIns->close();

        logAudit($conn, $userId, 'create', 'faculty', 'curriculum', $newId, null,
            json_encode(['major_id' => $majorId2, 'subject_id' => $subjectId, 'credits' => $credits]), $ip);
        invalidateDashboardCache($facultyId);

        $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Đã thêm môn học vào chương trình đào tạo.'];
        header('Location: curriculum.php?major_id=' . $majorId2);
        exit();
    }

    // EDIT
    if ($action === 'edit') {
        $currId  = (int)($_POST['curr_id'] ?? 0);
        $credits = (int)($_POST['credits'] ?? 0);
        $sugSem  = (int)($_POST['suggested_semester'] ?? 0);
        $subType = normalizeCurriculumType($_POST['subject_type'] ?? 'required');
        $prereqIds = trim($_POST['prerequisite_ids'] ?? '');
        $majorId2  = (int)($_POST['major_id'] ?? 0);

        if ($currId <= 0 || $credits <= 0 || $sugSem <= 0) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Dữ liệu không hợp lệ.'];
            header('Location: curriculum.php?major_id=' . $majorId2);
            exit();
        }
        if (!assertFacultyOwnership($conn, 'curriculum', $currId, $facultyId)) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Không có quyền chỉnh sửa chương trình đào tạo của ngành thuộc khoa khác.'];
            header('Location: curriculum.php');
            exit();
        }

        $stmtOld = $conn->prepare("SELECT credits, suggested_semester, subject_type, prerequisite_ids FROM curriculum WHERE id = ? LIMIT 1");
        $stmtOld->bind_param('i', $currId);
        $stmtOld->execute();
        $oldRow = $stmtOld->get_result()->fetch_assoc();
        $stmtOld->close();

        $stmtUpd = $conn->prepare("UPDATE curriculum SET credits = ?, suggested_semester = ?, semester_label = ?, year_label = ?, subject_type = ?, prerequisite_ids = ? WHERE id = ?");
        $semLabel2  = trim($_POST['semester_label'] ?? '');
        $yearLabel2 = trim($_POST['year_label'] ?? '');
        $stmtUpd->bind_param('iissssi', $credits, $sugSem, $semLabel2, $yearLabel2, $subType, $prereqIds, $currId);
        $stmtUpd->execute();
        $stmtUpd->close();

        logAudit($conn, $userId, 'update', 'faculty', 'curriculum', $currId, json_encode($oldRow),
            json_encode(['credits' => $credits, 'suggested_semester' => $sugSem, 'subject_type' => $subType]), $ip);
        invalidateDashboardCache($facultyId);

        $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Cập nhật chương trình đào tạo thành công.'];
        header('Location: curriculum.php?major_id=' . $majorId2);
        exit();
    }

    // SOFT DELETE
    if ($action === 'soft_delete') {
        $currId   = (int)($_POST['curr_id'] ?? 0);
        $majorId2 = (int)($_POST['major_id'] ?? 0);

        if ($currId <= 0 || !assertFacultyOwnership($conn, 'curriculum', $currId, $facultyId)) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Không có quyền xóa môn học này.'];
            header('Location: curriculum.php?major_id=' . $majorId2);
            exit();
        }

        // Lay subject_id cua entry nay
        $stmtSub = $conn->prepare("SELECT subject_id FROM curriculum WHERE id = ? LIMIT 1");
        $stmtSub->bind_param('i', $currId);
        $stmtSub->execute();
        $subRow = $stmtSub->get_result()->fetch_assoc();
        $stmtSub->close();
        $subjectId = (int)($subRow['subject_id'] ?? 0);

        // Kiem tra co la tien quyet cua mon khac khong
        $stmtPreChk = $conn->prepare("SELECT id, subject_id FROM curriculum WHERE major_id = ? AND deleted_at IS NULL AND FIND_IN_SET(?, REPLACE(prerequisite_ids, ' ', ''))");
        $stmtPreChk->bind_param('ii', $majorId2, $subjectId);
        $stmtPreChk->execute();
        $dependents = $stmtPreChk->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtPreChk->close();

        if (!empty($dependents)) {
            $depIds = implode(', ', array_column($dependents, 'subject_id'));
            $_SESSION['_flash'] = ['type' => 'warning', 'message' => 'Môn học này là tiên quyết của các môn: ' . $depIds . '. Xóa sẽ ảnh hưởng đến cấu trúc chương trình.'];
            header('Location: curriculum.php?major_id=' . $majorId2 . '&confirm_delete=' . $currId);
            exit();
        }

        $stmtDel = $conn->prepare("UPDATE curriculum SET deleted_at = NOW(), deleted_by = ? WHERE id = ?");
        $stmtDel->bind_param('ii', $userId, $currId);
        $stmtDel->execute();
        $stmtDel->close();

        logAudit($conn, $userId, 'delete', 'faculty', 'curriculum', $currId, null, null, $ip);
        invalidateDashboardCache($facultyId);

        $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Đã xóa môn học khỏi chương trình đào tạo.'];
        header('Location: curriculum.php?major_id=' . $majorId2);
        exit();
    }

    // FORCE DELETE (confirmed)
    if ($action === 'force_delete') {
        $currId   = (int)($_POST['curr_id'] ?? 0);
        $majorId2 = (int)($_POST['major_id'] ?? 0);

        if ($currId <= 0 || !assertFacultyOwnership($conn, 'curriculum', $currId, $facultyId)) {
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Không có quyền xóa môn học này.'];
            header('Location: curriculum.php?major_id=' . $majorId2);
            exit();
        }

        $stmtDel = $conn->prepare("UPDATE curriculum SET deleted_at = NOW(), deleted_by = ? WHERE id = ?");
        $stmtDel->bind_param('ii', $userId, $currId);
        $stmtDel->execute();
        $stmtDel->close();

        logAudit($conn, $userId, 'delete', 'faculty', 'curriculum', $currId, null, null, $ip);
        invalidateDashboardCache($facultyId);

        $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Đã xóa môn học (có thể khôi phục).'];
        header('Location: curriculum.php?major_id=' . $majorId2);
        exit();
    }

    // RESTORE
    if ($action === 'restore') {
        $currId   = (int)($_POST['curr_id'] ?? 0);
        $majorId2 = (int)($_POST['major_id'] ?? 0);

        $stmtChk = $conn->prepare("SELECT c.id FROM curriculum c JOIN majors m ON c.major_id = m.id WHERE c.id = ? AND m.faculty_id = ? LIMIT 1");
        $stmtChk->bind_param('ii', $currId, $facultyId);
        $stmtChk->execute();
        if ($stmtChk->get_result()->num_rows === 0) {
            $stmtChk->close();
            $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Không có quyền khôi phục môn học này.'];
            header('Location: curriculum.php?major_id=' . $majorId2 . '&archived=1');
            exit();
        }
        $stmtChk->close();

        $stmtRes = $conn->prepare("UPDATE curriculum SET deleted_at = NULL, deleted_by = NULL WHERE id = ?");
        $stmtRes->bind_param('i', $currId);
        $stmtRes->execute();
        $stmtRes->close();

        logAudit($conn, $userId, 'restore', 'faculty', 'curriculum', $currId, null, null, $ip);
        invalidateDashboardCache($facultyId);

        $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Đã khôi phục môn học vào chương trình đào tạo.'];
        header('Location: curriculum.php?major_id=' . $majorId2);
        exit();
    }

    $_SESSION['_flash'] = ['type' => 'danger', 'message' => 'Hành động không hợp lệ.'];
    header('Location: curriculum.php');
    exit();
}

// GET Handler
$flash        = getFlash();
$selectedMajorId = (int)($_GET['major_id'] ?? 0);
$showArchived = isset($_GET['archived']) && $_GET['archived'] === '1';
$confirmDelete = (int)($_GET['confirm_delete'] ?? 0);

// Lay danh sach nganh
$majors = [];
$stmtMajors = $conn->prepare("SELECT id, major_code, major_name FROM majors WHERE faculty_id = ? ORDER BY major_name ASC");
$stmtMajors->bind_param('i', $facultyId);
$stmtMajors->execute();
$majors = $stmtMajors->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtMajors->close();

// Lay CTDT neu da chon nganh
$curriculumData = [];
$majorInfo = null;
$catalogCredits = 0;
$requiredCredits = 0;
$electiveCatalogCredits = 0;
$generalCredits = 0;
$mandatoryCredits = 0;
$requiredCount = 0;
$electiveCount = 0;
$generalCount = 0;
$creditsBySem = [];

if ($selectedMajorId > 0) {
    // Kiem tra nganh thuoc faculty
    $stmtMInfo = $conn->prepare("SELECT id, major_code, major_name, total_credits FROM majors WHERE id = ? AND faculty_id = ? LIMIT 1");
    $stmtMInfo->bind_param('ii', $selectedMajorId, $facultyId);
    $stmtMInfo->execute();
    $majorInfo = $stmtMInfo->get_result()->fetch_assoc();
    $stmtMInfo->close();

    if ($majorInfo) {
        $deletedCond = $showArchived ? 'c.deleted_at IS NOT NULL' : 'c.deleted_at IS NULL';
        $stmtCurr = $conn->prepare(
            "SELECT c.id, c.subject_id, c.credits, c.suggested_semester, c.subject_type,
                    c.prerequisite_ids, c.deleted_at, c.deleted_by,
                    c.semester_label, c.year_label,
                    s.subject_code, s.subject_name,
                    s.theory_periods, s.practice_periods, s.is_mandatory AS subject_is_mandatory,
                    COALESCE(s.total_periods, s.theory_periods + s.practice_periods) AS total_periods,
                    CASE WHEN c.subject_type IN ('required','general') THEN 1 ELSE 0 END AS is_mandatory
             FROM curriculum c
             JOIN subjects s ON c.subject_id = s.id
             WHERE c.major_id = ? AND {$deletedCond}
             ORDER BY c.year_label ASC, c.suggested_semester ASC, s.subject_name ASC"
        );
        $stmtCurr->bind_param('i', $selectedMajorId);
        $stmtCurr->execute();
        $rows = $stmtCurr->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtCurr->close();

        foreach ($rows as $row) {
            $sem = (int)$row['suggested_semester'];
            $yearLabel = $row['year_label'] ?? '';
            $semLabel  = $row['semester_label'] ?? '';
            // Key để sort đúng thứ tự: year + semOrder
            $key = ($yearLabel ?: '0000') . '|' . sprintf('%02d', $sem) . '|' . $semLabel;
            $curriculumData[$key][] = $row;
            if (!$showArchived) {
                $credits = (int)$row['credits'];
                $type = normalizeCurriculumType($row['subject_type'] ?? '');
                $isMandatory = isCurriculumMandatory($row);

                $catalogCredits += $credits;
                $creditsBySem[$key] = ($creditsBySem[$key] ?? 0) + $credits;

                if ($type === 'elective') {
                    $electiveCatalogCredits += $credits;
                    $electiveCount++;
                } elseif ($type === 'general') {
                    $generalCredits += $credits;
                    $generalCount++;
                    $requiredCredits += $credits;
                    $requiredCount++;
                } else {
                    $requiredCredits += $credits;
                    $requiredCount++;
                }

                if ($isMandatory) {
                    $mandatoryCredits += $credits;
                }
            }
        }
        ksort($curriculumData);
    }
}

// Lay danh sach mon hoc de them vao CTDT
$allSubjects = [];
$stmtSubj = $conn->prepare("SELECT id, subject_code, subject_name, credits FROM subjects ORDER BY subject_name ASC");
$stmtSubj->execute();
$allSubjects = $stmtSubj->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtSubj->close();

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="admin-main">
    <div class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle" aria-label="Mo/dong menu">
                <i class="bi bi-list fs-5" aria-hidden="true"></i>
            </button>
            <span class="admin-topbar-title">
                <i class="bi bi-journal-bookmark-fill me-2 text-navy" aria-hidden="true"></i>Chương trình đào tạo
            </span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <?php if ($selectedMajorId > 0): ?>
            <?php if ($showArchived): ?>
            <a href="curriculum.php?major_id=<?php echo $selectedMajorId; ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Hiện tại
            </a>
            <?php else: ?>
            <a href="curriculum.php?major_id=<?php echo $selectedMajorId; ?>&archived=1" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-archive me-1" aria-hidden="true"></i>Đã xóa
            </a>
            <?php endif; ?>
            <?php if (isFacultyManager() && !$showArchived): ?>
            <button class="btn btn-sm btn-navy" data-bs-toggle="modal" data-bs-target="#addCurrModal"
                    aria-label="Thêm môn học vào chương trình đào tạo">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Thêm môn học
            </button>
            <?php endif; ?>
            <?php endif; ?>
            <a href="/university/login.php?logout=1" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Đăng xuất
            </a>
        </div>
    </div>

    <div class="admin-content">

        <?php if ($flash): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show auto-dismiss mb-4" role="alert">
            <?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
        </div>
        <?php endif; ?>

        <!-- Major selector -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" action="curriculum.php" class="row g-3 align-items-end">
                    <div class="col-12 col-md-5">
                        <label for="major_id" class="form-label">Chọn ngành</label>
                        <select id="major_id" name="major_id" class="form-select">
                            <option value="0">-- Chọn ngành --</option>
                            <?php foreach ($majors as $m): ?>
                            <option value="<?php echo (int)$m['id']; ?>"
                                <?php echo $selectedMajorId === (int)$m['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m['major_code'] . ' - ' . displayUtf8($m['major_name'])); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-navy" aria-label="Xem chương trình đào tạo">
                            <i class="bi bi-search me-1" aria-hidden="true"></i>Xem
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($selectedMajorId > 0 && $majorInfo): ?>

        <!-- Major info -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">
                <i class="bi bi-mortarboard-fill me-2 text-navy" aria-hidden="true"></i>
                <?php echo htmlspecialchars(displayUtf8($majorInfo['major_name'])); ?>
                <span class="badge bg-light text-dark ms-2"><?php echo htmlspecialchars($majorInfo['major_code']); ?></span>
            </h5>
            <?php if (!$showArchived): ?>
            <span class="badge bg-navy fs-6">Yêu cầu tốt nghiệp: <?php echo (int)($majorInfo['total_credits'] ?? 120); ?> tín chỉ</span>
            <?php endif; ?>
        </div>

        <?php if (empty($curriculumData)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2" aria-hidden="true"></i>
            <?php echo $showArchived ? 'Không có môn học nào đã xóa.' : 'Chương trình đào tạo chưa có môn học nào.'; ?>
        </div>
        <?php else: ?>

        <?php if (!$showArchived): ?>
        <div class="row g-3 mb-3">
            <div class="col-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-body py-3">
                        <div class="text-muted small">Tín chỉ tốt nghiệp</div>
                        <div class="fs-4 fw-bold text-navy"><?php echo (int)($majorInfo['total_credits'] ?? 120); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-body py-3">
                        <div class="text-muted small">Bắt buộc + đại cương</div>
                        <div class="fs-4 fw-bold text-success"><?php echo $requiredCredits; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-body py-3">
                        <div class="text-muted small">Danh mục tự chọn</div>
                        <div class="fs-4 fw-bold text-warning"><?php echo $electiveCatalogCredits; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-body py-3">
                        <div class="text-muted small">Tổng danh mục CTĐT</div>
                        <div class="fs-4 fw-bold text-secondary"><?php echo $catalogCredits; ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
            $graduationCredits = (int)($majorInfo['total_credits'] ?? 120);
            $electiveNeed = max(0, $graduationCredits - $requiredCredits);
        ?>
        <div class="alert alert-light border d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>
                <i class="bi bi-info-circle me-1 text-navy" aria-hidden="true"></i>
                Theo mô hình đại học, CTĐT gồm khối bắt buộc và danh mục tự chọn. Sinh viên chỉ cần tích lũy đủ
                <strong><?php echo $graduationCredits; ?> tín chỉ</strong>, không phải học toàn bộ danh mục tự chọn.
            </span>
            <span class="badge bg-info text-dark">
                Tự chọn cần tối thiểu khoảng <?php echo $electiveNeed; ?> TC
            </span>
        </div>
        <?php endif; ?>

        <?php
        $cumCredits = 0;
        $prevYear = null;
        foreach ($curriculumData as $key => $entries):
            [$yearLabel, $semOrderPad, $semLabel] = explode('|', $key);
            $sem = (int)$semOrderPad;
            $semCredits = $creditsBySem[$key] ?? 0;
            $cumCredits += $semCredits;
            // Header năm học
            if ($yearLabel && $yearLabel !== $prevYear):
                $prevYear = $yearLabel;
        ?>
        <div class="d-flex align-items-center gap-2 mb-2 mt-3">
            <i class="bi bi-calendar-range-fill text-navy"></i>
            <strong class="text-navy">Năm học <?php echo htmlspecialchars($yearLabel); ?></strong>
            <hr class="flex-grow-1 my-0">
        </div>
        <?php endif; ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>
                    <i class="bi bi-calendar3 me-2" aria-hidden="true"></i>
                    Học kỳ <?php echo $sem; ?>
                    <?php if ($semLabel): ?><span class="text-muted ms-1 small"><?php echo htmlspecialchars($semLabel); ?></span><?php endif; ?>
                </span>
                <?php if (!$showArchived): ?>
                <span class="text-muted small">
                    <?php echo $semCredits; ?> TC | Lũy kế danh mục: <?php echo $cumCredits; ?> TC
                </span>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Mã môn</th>
                            <th>Tên môn học</th>
                            <th class="text-center">TC</th>
                            <th class="text-center">LT</th>
                            <th class="text-center">TH</th>
                            <th class="text-center">Tổng tiết</th>
                            <th>Loại</th>
                            <th class="text-center">Bắt buộc</th>
                            <th>Tiên quyết</th>
                            <?php if ($showArchived): ?><th>Ngày xóa</th><?php endif; ?>
                            <?php if (isFacultyManager()): ?><th></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $entry):
                        $lt  = (int)($entry['theory_periods'] ?? 0);
                        $th  = (int)($entry['practice_periods'] ?? 0);
                        $tot = (int)($entry['total_periods'] ?? ($lt + $th));
                        [$typeLabel, $typeColor] = curriculumTypeMeta($entry['subject_type'] ?? '');
                        $isMandatory = isCurriculumMandatory($entry);
                        ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($entry['subject_code']); ?></code></td>
                            <td><?php echo htmlspecialchars(displayUtf8($entry['subject_name'])); ?></td>
                            <td class="text-center"><?php echo (int)$entry['credits']; ?></td>
                            <td class="text-center text-muted small"><?php echo $lt ?: '-'; ?></td>
                            <td class="text-center text-muted small"><?php echo $th ?: '-'; ?></td>
                            <td class="text-center text-muted small"><?php echo $tot ?: '-'; ?></td>
                            <td>
                                <span class="badge bg-<?php echo $typeColor; ?>"><?php echo $typeLabel; ?></span>
                            </td>
                            <td class="text-center">
                                <?php if ($isMandatory): ?>
                                <span class="badge bg-success">Có</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Không</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?php echo htmlspecialchars($entry['prerequisite_ids'] ?? '—'); ?></td>
                            <?php if ($showArchived): ?>
                            <td class="text-muted small"><?php echo htmlspecialchars($entry['deleted_at'] ?? ''); ?></td>
                            <?php endif; ?>
                            <?php if (isFacultyManager()): ?>
                            <td>
                                <?php if ($showArchived): ?>
                                <form method="post" action="curriculum.php" class="d-inline">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="restore">
                                    <input type="hidden" name="curr_id" value="<?php echo (int)$entry['id']; ?>">
                                    <input type="hidden" name="major_id" value="<?php echo $selectedMajorId; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success"
                                        aria-label="Khôi phục môn học <?php echo htmlspecialchars(displayUtf8($entry['subject_name'])); ?>">
                                        <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                                    </button>
                                </form>
                                <?php else: ?>
                                <button class="btn btn-sm btn-outline-navy me-1"
                                        data-bs-toggle="modal" data-bs-target="#editCurrModal"
                                        data-id="<?php echo (int)$entry['id']; ?>"
                                        data-credits="<?php echo (int)$entry['credits']; ?>"
                                        data-sem="<?php echo (int)$entry['suggested_semester']; ?>"
                                        data-type="<?php echo htmlspecialchars($entry['subject_type']); ?>"
                                        data-prereq="<?php echo htmlspecialchars($entry['prerequisite_ids'] ?? ''); ?>"
                                        data-name="<?php echo htmlspecialchars(displayUtf8($entry['subject_name'])); ?>"
                                        data-semlabel="<?php echo htmlspecialchars($entry['semester_label'] ?? ''); ?>"
                                        data-yearlabel="<?php echo htmlspecialchars($entry['year_label'] ?? ''); ?>"
                                        aria-label="Sửa môn học <?php echo htmlspecialchars(displayUtf8($entry['subject_name'])); ?>">
                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                </button>
                                <form method="post" action="curriculum.php" class="d-inline"
                                      onsubmit="return confirm('Xóa môn học này khỏi CTĐT?')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="soft_delete">
                                    <input type="hidden" name="curr_id" value="<?php echo (int)$entry['id']; ?>">
                                    <input type="hidden" name="major_id" value="<?php echo $selectedMajorId; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                            aria-label="Xóa môn học <?php echo htmlspecialchars(displayUtf8($entry['subject_name'])); ?>">
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (!$showArchived): ?>
        <div class="alert alert-info d-flex justify-content-between align-items-center">
            <span><i class="bi bi-info-circle me-2" aria-hidden="true"></i>Tổng tín chỉ danh mục chương trình đào tạo</span>
            <strong><?php echo $catalogCredits; ?> tín chỉ</strong>
        </div>
        <?php endif; ?>

        <?php endif; ?>

        <?php elseif ($selectedMajorId > 0): ?>
        <div class="alert alert-danger">Ngành không tồn tại hoặc không thuộc khoa này.</div>
        <?php else: ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2" aria-hidden="true"></i>
            Vui lòng chọn ngành để xem chương trình đào tạo.
        </div>
        <?php endif; ?>

    </div>

    <div class="admin-footer">
        &copy; <?php echo date('Y'); ?> TDMU - Trường Đại học Thủ Dầu Một
    </div>
</div>

<?php if (isFacultyManager() && $selectedMajorId > 0 && !$showArchived): ?>
<!-- Add Modal -->
<div class="modal fade" id="addCurrModal" tabindex="-1" aria-labelledby="addCurrModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="curriculum.php">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="major_id" value="<?php echo $selectedMajorId; ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCurrModalLabel">Thêm môn học vào CTĐT</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="add_subject_id" class="form-label">Môn học <span class="text-danger">*</span></label>
                        <select id="add_subject_id" name="subject_id" class="form-select" required>
                            <option value="">-- Chọn môn học --</option>
                            <?php foreach ($allSubjects as $subj): ?>
                            <option value="<?php echo (int)$subj['id']; ?>">
                                <?php echo htmlspecialchars($subj['subject_code'] . ' - ' . displayUtf8($subj['subject_name']) . ' (' . $subj['credits'] . ' TC)'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label for="add_credits" class="form-label">Tín chỉ <span class="text-danger">*</span></label>
                            <input type="number" id="add_credits" name="credits" class="form-control" min="1" max="10" required>
                        </div>
                        <div class="col-6">
                            <label for="add_sem" class="form-label">Thứ tự học kỳ <span class="text-danger">*</span></label>
                            <input type="number" id="add_sem" name="suggested_semester" class="form-control" min="1" max="12" required>
                        </div>
                        <div class="col-6">
                            <label for="add_sem_label" class="form-label">Tên học kỳ</label>
                            <input type="text" id="add_sem_label" name="semester_label" class="form-control" placeholder="VD: Học kỳ 1">
                        </div>
                        <div class="col-6">
                            <label for="add_year_label" class="form-label">Năm học</label>
                            <input type="text" id="add_year_label" name="year_label" class="form-control" placeholder="VD: 2022-2023">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label for="add_type" class="form-label">Loại môn</label>
                        <select id="add_type" name="subject_type" class="form-select">
                            <option value="required">Bắt buộc</option>
                            <option value="elective">Tự chọn</option>
                            <option value="general">Đại cương</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="add_prereq" class="form-label">ID môn tiên quyết (cách nhau bởi dấu phẩy)</label>
                        <input type="text" id="add_prereq" name="prerequisite_ids" class="form-control"
                               placeholder="VD: 5,12,18">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-navy">
                        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Thêm
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editCurrModal" tabindex="-1" aria-labelledby="editCurrModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="curriculum.php">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="curr_id" id="edit_curr_id">
                <input type="hidden" name="major_id" value="<?php echo $selectedMajorId; ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="editCurrModalLabel">Sửa môn học trong CTĐT</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted fw-semibold" id="edit_curr_name"></p>
                    <div class="row g-3">
                        <div class="col-6">
                            <label for="edit_credits" class="form-label">Tín chỉ <span class="text-danger">*</span></label>
                            <input type="number" id="edit_credits" name="credits" class="form-control" min="1" max="10" required>
                        </div>
                        <div class="col-6">
                            <label for="edit_sem" class="form-label">Thứ tự học kỳ <span class="text-danger">*</span></label>
                            <input type="number" id="edit_sem" name="suggested_semester" class="form-control" min="1" max="12" required>
                        </div>
                        <div class="col-6">
                            <label for="edit_sem_label" class="form-label">Tên học kỳ</label>
                            <input type="text" id="edit_sem_label" name="semester_label" class="form-control" placeholder="VD: Học kỳ 1">
                        </div>
                        <div class="col-6">
                            <label for="edit_year_label" class="form-label">Năm học</label>
                            <input type="text" id="edit_year_label" name="year_label" class="form-control" placeholder="VD: 2022-2023">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label for="edit_type" class="form-label">Loại môn</label>
                        <select id="edit_type" name="subject_type" class="form-select">
                            <option value="required">Bắt buộc</option>
                            <option value="elective">Tự chọn</option>
                            <option value="general">Đại cương</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_prereq" class="form-label">ID môn tiên quyết</label>
                        <input type="text" id="edit_prereq" name="prerequisite_ids" class="form-control"
                               placeholder="VD: 5,12,18">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-navy">
                        <i class="bi bi-save me-1" aria-hidden="true"></i>Lưu
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('editCurrModal').addEventListener('show.bs.modal', function(e) {
    var btn = e.relatedTarget;
    document.getElementById('edit_curr_id').value    = btn.dataset.id;
    document.getElementById('edit_curr_name').textContent = btn.dataset.name;
    document.getElementById('edit_credits').value    = btn.dataset.credits;
    document.getElementById('edit_sem').value        = btn.dataset.sem;
    document.getElementById('edit_sem_label').value  = btn.dataset.semlabel || '';
    document.getElementById('edit_year_label').value = btn.dataset.yearlabel || '';
    document.getElementById('edit_type').value       = btn.dataset.type || 'required';
    document.getElementById('edit_prereq').value     = btn.dataset.prereq || '';
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
