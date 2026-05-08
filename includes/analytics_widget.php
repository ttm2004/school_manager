<?php
/**
 * Analytics Widget — hiển thị dạng panel cố định góc dưới phải
 * Không ảnh hưởng layout, hoạt động trên mọi trang
 */
if (!isLoggedIn()) return;

global $conn;
$stats = getVisitStats($conn);
if (empty($stats)) return;

$level = $stats['level'];

$rows = [];
if ($level === 'admin') {
    $rows[] = ['icon' => 'bi-people-fill',       'label' => 'Đang truy cập',  'value' => $stats['total']];
    $rows[] = ['icon' => 'bi-mortarboard-fill',   'label' => 'SV đăng nhập',   'value' => $stats['by_role']['student']];
    $rows[] = ['icon' => 'bi-person-badge-fill',  'label' => 'GV đăng nhập',   'value' => $stats['by_role']['teacher']];
    $rows[] = ['icon' => 'bi-person-gear',        'label' => 'Nhân viên',       'value' => $stats['by_role']['staff']];
    $rows[] = ['icon' => 'bi-calendar-day',       'label' => 'Hôm nay',         'value' => $stats['today']];
    $rows[] = ['icon' => 'bi-calendar-week',      'label' => 'Tuần này',        'value' => $stats['this_week']];
} elseif ($level === 'staff') {
    $rows[] = ['icon' => 'bi-people-fill',        'label' => 'Đang truy cập',  'value' => $stats['total']];
    $rows[] = ['icon' => 'bi-mortarboard-fill',   'label' => 'SV đăng nhập',   'value' => $stats['students']];
    $rows[] = ['icon' => 'bi-person-badge-fill',  'label' => 'GV đăng nhập',   'value' => $stats['teachers']];
    $rows[] = ['icon' => 'bi-calendar-day',       'label' => 'Hôm nay',         'value' => $stats['today']];
} else {
    // teacher / student
    $rows[] = ['icon' => 'bi-people-fill',        'label' => 'Đang truy cập',  'value' => $stats['total']];
    $rows[] = ['icon' => 'bi-mortarboard-fill',   'label' => 'SV đăng nhập',   'value' => $stats['students']];
    $rows[] = ['icon' => 'bi-person-badge-fill',  'label' => 'GV đăng nhập',   'value' => $stats['teachers']];
}
?>
<style>
#aw-toggle {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9990;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #1a5276;
    color: #fff;
    border: none;
    box-shadow: 0 4px 16px rgba(26,82,118,.45);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    cursor: pointer;
    transition: background .2s, transform .2s;
}
#aw-toggle:hover { background: #0d2d6b; transform: scale(1.08); }

#analytics-widget {
    position: fixed;
    bottom: 74px;
    right: 20px;
    z-index: 9989;
    width: 240px;
    background: #fff;
    border-left: 4px solid #1a5276;
    border-radius: 10px;
    box-shadow: 0 8px 32px rgba(26,82,118,.18);
    padding: 16px 18px 14px;
    font-family: 'Segoe UI', system-ui, sans-serif;
    transition: opacity .2s, transform .2s;
}
#analytics-widget.aw-hidden {
    opacity: 0;
    pointer-events: none;
    transform: translateY(10px);
}
.aw-title {
    font-size: .75rem;
    font-weight: 800;
    color: #1a3a6b;
    letter-spacing: .08em;
    text-transform: uppercase;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.aw-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 8px;
}
.aw-row:last-child { margin-bottom: 0; }
.aw-label {
    display: flex;
    align-items: center;
    gap: 7px;
    color: #1a3a6b;
    font-size: .82rem;
}
.aw-label i {
    font-size: .9rem;
    color: #1a5276;
    width: 16px;
    text-align: center;
}
.aw-badge {
    background: #1a5276;
    color: #fff;
    font-weight: 700;
    font-size: .78rem;
    padding: 2px 10px;
    border-radius: 5px;
    min-width: 44px;
    text-align: center;
}
</style>

<div id="analytics-widget" class="aw-hidden">
    <div class="aw-title">
        <span>Thống kê truy cập</span>
    </div>
    <?php foreach ($rows as $row): ?>
    <div class="aw-row">
        <div class="aw-label">
            <i class="bi <?php echo $row['icon']; ?>"></i>
            <span><?php echo $row['label']; ?></span>
        </div>
        <span class="aw-badge"><?php echo number_format($row['value']); ?></span>
    </div>
    <?php endforeach; ?>
</div>

<button id="aw-toggle" title="Thống kê truy cập">
    <i class="bi bi-bar-chart-fill"></i>
</button>

<script>
(function() {
    const widget = document.getElementById('analytics-widget');
    const btn    = document.getElementById('aw-toggle');
    if (!widget || !btn) return;

    const KEY = 'tdmu_aw_open';
    // Khôi phục trạng thái
    if (localStorage.getItem(KEY) === '1') {
        widget.classList.remove('aw-hidden');
        btn.innerHTML = '<i class="bi bi-x-lg"></i>';
    }

    btn.addEventListener('click', function() {
        const isOpen = !widget.classList.contains('aw-hidden');
        if (isOpen) {
            widget.classList.add('aw-hidden');
            btn.innerHTML = '<i class="bi bi-bar-chart-fill"></i>';
            localStorage.setItem(KEY, '0');
        } else {
            widget.classList.remove('aw-hidden');
            btn.innerHTML = '<i class="bi bi-x-lg"></i>';
            localStorage.setItem(KEY, '1');
        }
    });
})();
</script>
