<?php
/**
 * AJAX endpoint cho analytics widget
 * Được gọi từ main.js để inject widget vào tất cả trang
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

if (!isLoggedIn()) { http_response_code(204); exit(); }

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store');

logVisit($conn);

$stats = getVisitStats($conn);
if (empty($stats)) exit();

$level = $stats['level'];

$rows = [];
if ($level === 'admin') {
    $rows[] = ['icon' => 'bi-people-fill',      'label' => 'Đang truy cập', 'value' => $stats['total']];
    $rows[] = ['icon' => 'bi-mortarboard-fill',  'label' => 'SV đăng nhập',  'value' => $stats['by_role']['student']];
    $rows[] = ['icon' => 'bi-person-badge-fill', 'label' => 'GV đăng nhập',  'value' => $stats['by_role']['teacher']];
    $rows[] = ['icon' => 'bi-person-gear',       'label' => 'Nhân viên',      'value' => $stats['by_role']['staff']];
    $rows[] = ['icon' => 'bi-calendar-day',      'label' => 'Hôm nay',        'value' => $stats['today']];
    $rows[] = ['icon' => 'bi-calendar-week',     'label' => 'Tuần này',       'value' => $stats['this_week']];
} elseif ($level === 'staff') {
    $rows[] = ['icon' => 'bi-people-fill',       'label' => 'Đang truy cập', 'value' => $stats['total']];
    $rows[] = ['icon' => 'bi-mortarboard-fill',  'label' => 'SV đăng nhập',  'value' => $stats['students']];
    $rows[] = ['icon' => 'bi-person-badge-fill', 'label' => 'GV đăng nhập',  'value' => $stats['teachers']];
    $rows[] = ['icon' => 'bi-calendar-day',      'label' => 'Hôm nay',        'value' => $stats['today']];
} else {
    $rows[] = ['icon' => 'bi-people-fill',       'label' => 'Đang truy cập', 'value' => $stats['total']];
    $rows[] = ['icon' => 'bi-mortarboard-fill',  'label' => 'SV đăng nhập',  'value' => $stats['students']];
    $rows[] = ['icon' => 'bi-person-badge-fill', 'label' => 'GV đăng nhập',  'value' => $stats['teachers']];
}
?>
<div id="analytics-widget" style="
    background:#fff;
    border-left:4px solid #1a5276;
    border-top:1px solid #e2e8f0;
    padding:20px 24px 16px;
    font-family:'Segoe UI',system-ui,sans-serif;
    margin-top:0;
">
    <div style="font-size:.85rem;font-weight:800;color:#1a3a6b;letter-spacing:.08em;text-transform:uppercase;margin-bottom:14px;">
        THỐNG KÊ TRUY CẬP
    </div>
    <div style="display:flex;flex-direction:column;gap:10px;">
        <?php foreach ($rows as $row): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <div style="display:flex;align-items:center;gap:8px;color:#1a3a6b;font-size:.88rem;">
                <i class="bi <?php echo $row['icon']; ?>" style="font-size:1rem;color:#1a5276;width:18px;text-align:center;"></i>
                <span><?php echo $row['label']; ?></span>
            </div>
            <span style="
                background:#1a5276;
                color:#fff;
                font-weight:700;
                font-size:.85rem;
                padding:3px 14px;
                border-radius:6px;
                min-width:52px;
                text-align:center;
            "><?php echo number_format($row['value']); ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
