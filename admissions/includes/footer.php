    </div><!-- /.adm-content -->
    <div class="adm-footer">
        &copy; <?php echo date('Y'); ?> Trường Đại học Thủ Dầu Một &mdash; Phòng Tuyển sinh
        <span class="ms-3 text-muted"><?php echo date('H:i d/m/Y'); ?></span>
        <?php if (function_exists('isLoggedIn') && isLoggedIn() && function_exists('getVisitStats')): ?>
        <?php global $conn; $__s = getVisitStats($conn); $__total = $__s['total'] ?? 0; ?>
        <span class="ms-3">
            <i class="bi bi-circle-fill me-1" style="color:#4ade80;font-size:.5rem;vertical-align:middle;"></i>
            <strong><?php echo number_format($__total); ?></strong> người đang trực tuyến
        </span>
        <?php endif; ?>
    </div>
</div><!-- /.adm-main -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>

</body>
</html>
