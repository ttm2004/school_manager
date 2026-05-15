<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
$pageTitle = 'Tra cứu kết quả tuyển sinh';

$lookupModes = [];
foreach (['system', 'test'] as $mode) {
    if (isAdmissionLookupOpen($mode)) {
        $lookupModes[$mode] = getActiveRound($mode);
    }
}
$lookupOpen = !empty($lookupModes);

$citizenId = preg_replace('/\D+/', '', trim($_POST['citizen_id'] ?? $_GET['citizen_id'] ?? ''));
$birthday  = trim($_POST['birthday'] ?? $_GET['birthday'] ?? '');
$searched  = $lookupOpen && $_SERVER['REQUEST_METHOD'] === 'POST';
$error = '';
$result = null;

function admissionLookupStatusMeta(string $status): array
{
    return match ($status) {
        'approved' => [
            'label' => 'Trúng tuyển',
            'class' => 'success',
            'icon'  => 'bi-check-circle-fill',
            'title' => 'Chúc mừng, bạn đã trúng tuyển',
            'desc'  => 'Vui lòng theo dõi thông báo nhập học và chuẩn bị hồ sơ theo hướng dẫn của Phòng Tuyển sinh.',
        ],
        'enrolled' => [
            'label' => 'Đã nhập học',
            'class' => 'primary',
            'icon'  => 'bi-person-check-fill',
            'title' => 'Hồ sơ đã hoàn tất nhập học',
            'desc'  => 'Bạn đã được ghi nhận trạng thái nhập học trên hệ thống.',
        ],
        'rejected' => [
            'label' => 'Không trúng tuyển',
            'class' => 'danger',
            'icon'  => 'bi-x-circle-fill',
            'title' => 'Bạn chưa trúng tuyển trong đợt xét tuyển này',
            'desc'  => 'Bạn có thể theo dõi các thông báo tuyển sinh tiếp theo nếu nhà trường mở đợt bổ sung.',
        ],
        default => [
            'label' => 'Chưa công bố',
            'class' => 'secondary',
            'icon'  => 'bi-clock-history',
            'title' => 'Kết quả chưa được công bố',
            'desc'  => 'Hồ sơ đã được ghi nhận nhưng Phòng Tuyển sinh chưa công bố kết quả xét tuyển.',
        ],
    };
}

if ($searched) {
    if ($citizenId === '' || $birthday === '') {
        $error = 'Vui lòng nhập đầy đủ CCCD và ngày sinh.';
    } elseif (!preg_match('/^\d{9,12}$/', $citizenId)) {
        $error = 'CCCD không hợp lệ. Vui lòng kiểm tra lại.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) {
        $error = 'Ngày sinh không hợp lệ.';
    } else {
        $modeClauses = [];
        $bindTypes = 'ss';
        $bindValues = [$citizenId, $birthday];
        foreach ($lookupModes as $mode => $round) {
            $modeClauses[] = 'aa.data_mode = ?';
            $bindTypes .= 's';
            $bindValues[] = $mode;
        }

        $stmt = $conn->prepare(
            "SELECT aa.*, m.major_name, m.major_code, am.method_name,
                    ar.name AS round_name, ar.year AS round_year, ar.status AS round_status,
                    (COALESCE(aa.math_score, 0) + COALESCE(aa.literature_score, 0) + COALESCE(aa.english_score, 0)) AS total_score
             FROM admission_applications aa
             LEFT JOIN majors m ON aa.major_id = m.id
             LEFT JOIN admission_methods am ON aa.method_id = am.id
             LEFT JOIN admission_rounds ar ON aa.round_id = ar.id
             WHERE REPLACE(REPLACE(TRIM(aa.citizen_id), ' ', ''), '-', '') = ?
               AND DATE(aa.birthday) = ?
               AND (" . implode(' OR ', $modeClauses) . ")
               AND aa.status IN ('approved', 'rejected', 'enrolled')
             ORDER BY FIELD(aa.data_mode, 'system', 'test'), FIELD(aa.status, 'enrolled', 'approved', 'rejected'), aa.created_at DESC
             LIMIT 1"
        );
        $stmt->bind_param($bindTypes, ...$bindValues);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$result) {
            $error = 'Không tìm thấy kết quả đã công bố phù hợp. Vui lòng kiểm tra lại CCCD và ngày sinh.';
        }
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="/university/index.php">Trang chủ</a></li>
                <li class="breadcrumb-item"><a href="/university/admission.php">Tuyển sinh</a></li>
                <li class="breadcrumb-item active">Tra cứu kết quả</li>
            </ol>
        </nav>
        <h1><i class="bi bi-search-heart me-2"></i>Tra cứu kết quả tuyển sinh</h1>
        <p class="text-white-50 mb-0">Thí sinh tra cứu bằng CCCD và ngày sinh sau khi Phòng Tuyển sinh công bố kết quả.</p>
    </div>
</div>

<section class="py-5 bg-light">
    <div class="container">
        <?php if (!$lookupOpen): ?>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card shadow-custom border-0">
                        <div class="card-body p-5 text-center">
                            <div class="text-navy mb-3" style="font-size:4rem;"><i class="bi bi-eye-slash-fill"></i></div>
                            <h3 class="fw-bold text-navy">Chưa mở tra cứu kết quả</h3>
                            <p class="text-muted mb-0">
                                Trang tra cứu chỉ hiển thị khi đợt tuyển sinh đang ở giai đoạn nhập học và kết quả xét tuyển đã được công bố.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
        <div class="row g-4 justify-content-center">
            <div class="col-lg-5">
                <div class="card shadow-custom h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-card-checklist me-2"></i>Thông tin tra cứu</h5>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        <form method="POST" novalidate>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Số CCCD <span class="text-danger">*</span></label>
                                <input type="text" name="citizen_id" class="form-control" inputmode="numeric" maxlength="12"
                                       value="<?php echo htmlspecialchars($citizenId); ?>" placeholder="Nhập số CCCD đã dùng đăng ký" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Ngày sinh <span class="text-danger">*</span></label>
                                <input type="date" name="birthday" class="form-control"
                                       value="<?php echo htmlspecialchars($birthday); ?>" required>
                            </div>
                            <button type="submit" class="btn btn-navy w-100 py-2">
                                <i class="bi bi-search me-2"></i>Tra cứu kết quả
                            </button>
                        </form>
                        <div class="text-muted small mt-3">
                            Thông tin tra cứu phải trùng với hồ sơ đã đăng ký. Kết quả chỉ hiển thị sau khi Phòng Tuyển sinh công bố.
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <?php if ($result): ?>
                    <?php $meta = admissionLookupStatusMeta((string)$result['status']); ?>
                    <div class="card shadow-custom border-0 overflow-hidden">
                        <div class="card-body p-4 p-lg-5">
                            <div class="d-flex align-items-start gap-3 mb-4">
                                <div class="rounded-circle d-flex align-items-center justify-content-center bg-<?php echo $meta['class']; ?> bg-opacity-10 text-<?php echo $meta['class']; ?>" style="width:60px;height:60px;font-size:1.8rem;">
                                    <i class="bi <?php echo $meta['icon']; ?>"></i>
                                </div>
                                <div>
                                    <span class="badge bg-<?php echo $meta['class']; ?> mb-2"><?php echo htmlspecialchars($meta['label']); ?></span>
                                    <h3 class="fw-bold text-navy mb-2"><?php echo htmlspecialchars($meta['title']); ?></h3>
                                    <p class="text-muted mb-0"><?php echo htmlspecialchars($meta['desc']); ?></p>
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded">
                                        <div class="text-muted small">Họ và tên</div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($result['full_name']); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded">
                                        <div class="text-muted small">Ngày sinh</div>
                                        <div class="fw-bold"><?php echo date('d/m/Y', strtotime($result['birthday'])); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded">
                                        <div class="text-muted small">Ngành đăng ký</div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($result['major_name'] ?? 'Chưa xác định'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded">
                                        <div class="text-muted small">Phương thức xét tuyển</div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($result['method_name'] ?? 'Chưa xác định'); ?></div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive mb-4">
                                <table class="table table-bordered align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center">Toán</th>
                                            <th class="text-center">Ngữ văn</th>
                                            <th class="text-center">Tiếng Anh</th>
                                            <th class="text-center">Tổng điểm</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="text-center"><?php echo number_format((float)$result['math_score'], 2); ?></td>
                                            <td class="text-center"><?php echo number_format((float)$result['literature_score'], 2); ?></td>
                                            <td class="text-center"><?php echo number_format((float)$result['english_score'], 2); ?></td>
                                            <td class="text-center fw-bold text-success"><?php echo number_format((float)$result['total_score'], 2); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="alert alert-info mb-0">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                <?php if ($result['status'] === 'approved'): ?>
                                    Thí sinh trúng tuyển cần theo dõi thông báo nhập học chính thức từ nhà trường và hoàn tất hồ sơ đúng hạn.
                                <?php elseif ($result['status'] === 'enrolled'): ?>
                                    Hồ sơ đã được ghi nhận nhập học. Vui lòng đăng nhập tài khoản sinh viên nếu đã được cấp.
                                <?php else: ?>
                                    Kết quả này áp dụng cho đợt xét tuyển hiện tại. Thí sinh có thể theo dõi các đợt tuyển sinh bổ sung nếu có.
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card shadow-custom h-100 border-0">
                        <div class="card-body p-5 d-flex flex-column justify-content-center text-center">
                            <div class="text-navy mb-3" style="font-size:4rem;"><i class="bi bi-mortarboard-fill"></i></div>
                            <h3 class="fw-bold text-navy">Tra cứu kết quả tuyển sinh</h3>
                            <p class="text-muted mb-0">Kết quả xét tuyển sẽ được hiển thị tại đây sau khi thí sinh nhập đúng CCCD và ngày sinh.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
