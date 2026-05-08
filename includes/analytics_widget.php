<?php
/**
 * Analytics Widget — thống kê truy cập theo phân quyền
 * Fixed position góc dưới phải, toggle mở/đóng
 * Dùng prefix $_aw_ để tránh conflict với biến của trang chứa
 */
if (!isLoggedIn()) return;

global $conn;
$_aw_stats = getVisitStats($conn);
if (empty($_aw_stats)) return;

$_aw_level = $_aw_stats['level'];
$_aw_rows  = [];

if ($_aw_level === 'admin') {
    $_aw_rows[] = ['icon' => 'bi-people-fill',      'label' => 'Đang trực tuyến', 'value' => $_aw_stats['total'],             'color' => '#6366f1'];
    $_aw_rows[] = ['icon' => 'bi-mortarboard-fill', 'label' => 'Sinh viên',        'value' => $_aw_stats['by_role']['student'], 'color' => '#0ea5e9'];
    $_aw_rows[] = ['icon' => 'bi-person-badge-fill','label' => 'Giảng viên',       'value' => $_aw_stats['by_role']['teacher'], 'color' => '#10b981'];
    $_aw_rows[] = ['icon' => 'bi-person-gear',      'label' => 'Nhân viên',        'value' => $_aw_stats['by_role']['staff'],   'color' => '#f59e0b'];
    $_aw_rows[] = ['icon' => 'bi-shield-fill',      'label' => 'Quản trị',         'value' => $_aw_stats['by_role']['admin'],   'color' => '#8b5cf6'];
} elseif ($_aw_level === 'staff') {
    $_aw_rows[] = ['icon' => 'bi-people-fill',      'label' => 'Đang trực tuyến', 'value' => $_aw_stats['total'],    'color' => '#6366f1'];
    $_aw_rows[] = ['icon' => 'bi-mortarboard-fill', 'label' => 'Sinh viên',        'value' => $_aw_stats['students'], 'color' => '#0ea5e9'];
    $_aw_rows[] = ['icon' => 'bi-person-badge-fill','label' => 'Giảng viên',       'value' => $_aw_stats['teachers'], 'color' => '#10b981'];
} else {
    $_aw_rows[] = ['icon' => 'bi-people-fill',      'label' => 'Đang trực tuyến', 'value' => $_aw_stats['total'],    'color' => '#6366f1'];
    $_aw_rows[] = ['icon' => 'bi-mortarboard-fill', 'label' => 'Sinh viên',        'value' => $_aw_stats['students'], 'color' => '#0ea5e9'];
    $_aw_rows[] = ['icon' => 'bi-person-badge-fill','label' => 'Giảng viên',       'value' => $_aw_stats['teachers'], 'color' => '#10b981'];
}

$_aw_onlineCount = $_aw_stats['total'] ?? 0;
?>
<style>
/* ── Toggle button ── */
#aw-btn {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9992;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 0 16px 0 10px;
    height: 40px;
    border-radius: 20px;
    background: #0d2d6b;
    color: #fff;
    border: none;
    box-shadow: 0 4px 18px rgba(13,45,107,.35);
    cursor: pointer;
    font-size: .82rem;
    font-weight: 600;
    font-family: 'Segoe UI', system-ui, sans-serif;
    transition: background .2s, box-shadow .2s, transform .15s;
    white-space: nowrap;
}
#aw-btn:hover {
    background: #1a4fa0;
    box-shadow: 0 6px 24px rgba(13,45,107,.45);
    transform: translateY(-1px);
}
#aw-btn .aw-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: #4ade80;
    box-shadow: 0 0 0 2px rgba(74,222,128,.3);
    flex-shrink: 0;
    animation: awPulse 2s ease-in-out infinite;
}
@keyframes awPulse {
    0%,100% { box-shadow: 0 0 0 2px rgba(74,222,128,.3); }
    50%      { box-shadow: 0 0 0 5px rgba(74,222,128,.0); }
}
#aw-btn .aw-btn-icon {
    font-size: .95rem;
    opacity: .85;
}
#aw-btn .aw-btn-count {
    background: rgba(255,255,255,.18);
    border-radius: 10px;
    padding: 1px 8px;
    font-size: .78rem;
    font-weight: 700;
    margin-left: 2px;
}

/* ── Panel ── */
#aw-panel {
    position: fixed;
    bottom: 74px;
    right: 24px;
    z-index: 9991;
    width: 260px;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 12px 40px rgba(13,45,107,.18), 0 2px 8px rgba(0,0,0,.08);
    overflow: hidden;
    font-family: 'Segoe UI', system-ui, sans-serif;
    transform-origin: bottom right;
    transition: opacity .2s ease, transform .2s ease;
}
#aw-panel.aw-hidden {
    opacity: 0;
    pointer-events: none;
    transform: scale(.92) translateY(8px);
}

/* Panel header */
.aw-head {
    background: linear-gradient(135deg, #0d2d6b 0%, #1a4fa0 100%);
    padding: 12px 16px 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.aw-head-left {
    display: flex;
    align-items: center;
    gap: 8px;
}
.aw-head-icon {
    width: 30px; height: 30px;
    border-radius: 8px;
    background: rgba(255,255,255,.15);
    display: flex; align-items: center; justify-content: center;
    font-size: .95rem; color: #fff;
}
.aw-head-title {
    color: #fff;
    font-size: .78rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    line-height: 1.2;
}
.aw-head-sub {
    color: rgba(255,255,255,.55);
    font-size: .65rem;
    margin-top: 1px;
}
.aw-head-close {
    background: rgba(255,255,255,.12);
    border: none;
    color: rgba(255,255,255,.7);
    width: 24px; height: 24px;
    border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    font-size: .75rem;
    transition: background .15s, color .15s;
}
.aw-head-close:hover { background: rgba(255,255,255,.25); color: #fff; }

/* Panel body */
.aw-body {
    padding: 12px 14px 14px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

/* Row */
.aw-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 7px 10px;
    border-radius: 8px;
    background: #f8faff;
    transition: background .15s;
}
.aw-row:hover { background: #eef2ff; }
.aw-row-icon {
    width: 28px; height: 28px;
    border-radius: 7px;
    display: flex; align-items: center; justify-content: center;
    font-size: .85rem;
    flex-shrink: 0;
}
.aw-row-label {
    flex: 1;
    font-size: .8rem;
    color: #374151;
    font-weight: 500;
}
.aw-row-val {
    font-size: .85rem;
    font-weight: 700;
    color: #0d2d6b;
    min-width: 28px;
    text-align: right;
}

/* Footer */
.aw-foot {
    padding: 8px 14px;
    border-top: 1px solid #f0f2f7;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: .68rem;
    color: #9ca3af;
}
.aw-foot .aw-dot-sm {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: #4ade80;
    flex-shrink: 0;
}
</style>

<!-- Panel -->
<div id="aw-panel" class="aw-hidden">
    <div class="aw-head">
        <div class="aw-head-left">
            <div class="aw-head-icon"><i class="bi bi-bar-chart-fill"></i></div>
            <div>
                <div class="aw-head-title">Thống kê truy cập</div>
                <div class="aw-head-sub"><?php echo date('d/m/Y H:i'); ?></div>
            </div>
        </div>
        <button class="aw-head-close" id="aw-close" title="Đóng"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="aw-body">
        <?php foreach ($_aw_rows as $row):
            $bg = $row['color'] . '1a';
        ?>
        <div class="aw-row">
            <div class="aw-row-icon" style="background:<?php echo $bg; ?>;">
                <i class="bi <?php echo $row['icon']; ?>" style="color:<?php echo $row['color']; ?>;"></i>
            </div>
            <span class="aw-row-label"><?php echo $row['label']; ?></span>
            <span class="aw-row-val"><?php echo number_format($row['value']); ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="aw-foot">
        <span class="aw-dot-sm"></span>
        Phiên hoạt động trong 15 phút gần nhất
    </div>
</div>

<!-- Toggle button -->
<button id="aw-btn" title="Thống kê truy cập">
    <span class="aw-dot"></span>
    <i class="bi bi-bar-chart-fill aw-btn-icon"></i>
    <span><?php echo number_format($_aw_onlineCount); ?> trực tuyến</span>
</button>

<script>
(function() {
    const panel = document.getElementById('aw-panel');
    const btn   = document.getElementById('aw-btn');
    const close = document.getElementById('aw-close');
    if (!panel || !btn) return;

    const KEY = 'tdmu_aw_open';

    function open()  { panel.classList.remove('aw-hidden'); localStorage.setItem(KEY,'1'); }
    function shut()  { panel.classList.add('aw-hidden');    localStorage.setItem(KEY,'0'); }
    function toggle(){ panel.classList.contains('aw-hidden') ? open() : shut(); }

    // Khôi phục trạng thái
    if (localStorage.getItem(KEY) === '1') open();

    btn.addEventListener('click', toggle);
    if (close) close.addEventListener('click', shut);

    // Click ngoài panel thì đóng
    document.addEventListener('click', function(e) {
        if (!panel.contains(e.target) && !btn.contains(e.target)) shut();
    });
})();
</script>
