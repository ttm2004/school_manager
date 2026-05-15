<?php
/**
 * Partial: bảng danh sách hồ sơ nhập học
 * Được include từ enrollment.php — biến đã được chuẩn bị sẵn
 */
$testBulkEnroll = ($filter_mode ?? 'system') === 'test'
    && ($tab ?? 'approved') !== 'enrolled'
    && !empty($canEnroll);
$emptyColspan = ($tab ?? 'approved') === 'enrolled' ? 8 : 7;
if ($testBulkEnroll) $emptyColspan++;
?>
<?php if ($testBulkEnroll): ?>
<div class="d-flex justify-content-between align-items-center gap-2 flex-wrap px-3 py-2 border-bottom bg-light">
    <label class="form-check mb-0 small fw-semibold">
        <input class="form-check-input" type="checkbox" id="bulkSelectAll">
        Chọn tất cả trang này
    </label>
    <div class="d-flex align-items-center gap-2">
        <span class="text-muted small"><span id="bulkSelectedCount">0</span> hồ sơ được chọn</span>
        <button type="button" class="btn btn-sm btn-success" id="btnBulkEnroll" disabled>
            <i class="bi bi-check2-circle me-1"></i>Nhập học đã chọn
        </button>
    </div>
</div>
<?php endif; ?>
<div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead><tr>
            <?php if ($testBulkEnroll): ?><th style="width:42px"></th><?php endif; ?>
            <th>#</th><th>Họ tên</th><th>Ngành</th><th>Phương thức</th>
            <th>Tổng điểm</th><th>Ngày nộp</th>
            <?php if ($tab === 'enrolled'): ?><th>Tài khoản SV</th><?php endif; ?>
            <th>Thao tác</th>
        </tr></thead>
        <tbody>
        <?php if ($applications && $applications->num_rows > 0):
            $idx = $offset + 1;
            while ($app = $applications->fetch_assoc()): ?>
        <tr id="row-<?php echo $app['id']; ?>">
            <?php if ($testBulkEnroll): ?>
            <td>
                <input class="form-check-input bulk-enroll-check" type="checkbox" value="<?php echo (int)$app['id']; ?>" aria-label="Chọn hồ sơ">
            </td>
            <?php endif; ?>
            <td class="text-muted small"><?php echo $idx++; ?></td>
            <td>
                <div class="fw-semibold small"><?php echo htmlspecialchars($app['full_name']); ?></div>
                <div class="text-muted" style="font-size:.72rem"><?php echo htmlspecialchars($app['email']); ?></div>
                <?php if ($app['phone']): ?>
                <div class="text-muted" style="font-size:.72rem"><i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($app['phone']); ?></div>
                <?php endif; ?>
            </td>
            <td class="small text-muted"><?php echo htmlspecialchars($app['major_name'] ?? '--'); ?></td>
            <td class="small text-muted"><?php echo mb_substr($app['method_name'] ?? '--', 0, 20); ?></td>
            <td class="fw-bold text-success"><?php echo number_format($app['total_score'] ?? 0, 2); ?></td>
            <td class="text-muted small"><?php echo date('d/m/Y', strtotime($app['created_at'])); ?></td>

            <?php if ($tab === 'enrolled'): ?>
            <td>
                <?php if ($app['has_account']): ?>
                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Đã cấp</span>
                <?php else: ?>
                <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Chưa cấp</span>
                <?php endif; ?>
            </td>
            <?php endif; ?>

            <td>
                <?php if ($tab !== 'enrolled'): ?>
                    <?php if ($canEnroll && !$enrollLocked): ?>
                    <button class="btn btn-sm btn-success btn-enroll"
                        data-id="<?php echo $app['id']; ?>"
                        data-name="<?php echo htmlspecialchars($app['full_name'], ENT_QUOTES); ?>">
                        <i class="bi bi-person-check-fill me-1"></i>Nhập học
                    </button>
                    <?php elseif ($enrollLocked): ?>
                    <span class="text-muted small"><i class="bi bi-lock me-1"></i>Bị khóa</span>
                    <?php else: ?>
                    <span class="text-muted small"><i class="bi bi-lock me-1"></i>Không có quyền</span>
                    <?php endif; ?>
                <?php else: ?>
                <div class="d-flex gap-1 flex-wrap">
                    <?php if (!$app['has_account'] && $canEnroll && !$enrollLocked): ?>
                    <button class="btn btn-sm btn-gold btn-create-account"
                        data-id="<?php echo $app['id']; ?>"
                        data-name="<?php echo htmlspecialchars($app['full_name'], ENT_QUOTES); ?>"
                        data-major-id="<?php echo intval($app['major_id']); ?>"
                        data-target-year="<?php echo (int)($app['admission_year'] ?? $app['graduation_year'] ?? date('Y', strtotime($app['created_at']))); ?>">
                        <i class="bi bi-person-plus-fill me-1"></i>Cấp TK
                    </button>
                    <?php elseif ($app['has_account']): ?>
                    <span class="btn btn-sm btn-outline-success disabled"><i class="bi bi-check2 me-1"></i>Đã cấp TK</span>
                    <?php elseif ($enrollLocked): ?>
                    <span class="text-muted small"><i class="bi bi-lock me-1"></i>Bị khóa</span>
                    <?php endif; ?>

                    <?php if ($isManager && !$enrollLocked): ?>
                    <button class="btn btn-sm btn-outline-danger btn-cancel-enroll"
                        data-id="<?php echo $app['id']; ?>"
                        data-name="<?php echo htmlspecialchars($app['full_name'], ENT_QUOTES); ?>"
                        title="Hủy nhập học (chỉ Trưởng phòng)">
                        <i class="bi bi-x-circle"></i>
                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="<?php echo $emptyColspan; ?>" class="text-center text-muted py-5">
            <i class="bi bi-inbox fs-2 d-block mb-2"></i>Không có hồ sơ nào
        </td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
<div class="px-3 py-2 border-top d-flex justify-content-between align-items-center">
    <small class="text-muted">Hiển thị <?php echo $offset+1; ?>–<?php echo min($offset+$perPage,$total); ?> / <?php echo $total; ?></small>
    <nav><ul class="pagination pagination-sm mb-0">
        <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
        <li class="page-item <?php echo $p==$page?'active':''; ?>">
            <a class="page-link page-ajax" href="#" data-page="<?php echo $p; ?>"><?php echo $p; ?></a>
        </li>
        <?php endfor; ?>
    </ul></nav>
</div>
<?php endif; ?>
