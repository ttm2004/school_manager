<?php
// Auth check — cho phép admin hoặc bất kỳ ai có role tuyển sinh
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAnyRole(['admissions_manager', 'admissions_staff']);

$_currentPage = basename($_SERVER['PHP_SELF']);
$_userName    = $_SESSION['full_name'] ?? 'Nhân viên';
$_userRoles   = getUserRoles();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : ''; ?>Tuyển sinh · TDMU</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --navy:#0d2d6b; --navy-dark:#071a45; --navy-light:#1a4fa0;
            --gold:#f5a623; --gold-dark:#d4891a;
            --sidebar-w:260px;
            --sidebar-collapsed-w:60px;
        }
        body { font-family:'Segoe UI',system-ui,sans-serif; background:#f0f2f7; color:#1a2340; }

        /* Sidebar */
        .adm-sidebar {
            position:fixed; top:0; left:0; width:var(--sidebar-w); height:100vh;
            background:linear-gradient(180deg,var(--navy-dark) 0%,var(--navy) 100%);
            overflow-y:auto; overflow-x:hidden; z-index:1000;
            transition:width .25s cubic-bezier(.4,0,.2,1), transform .3s;
            display:flex; flex-direction:column;
        }
        .adm-sidebar.collapsed { width:var(--sidebar-collapsed-w); }
        .adm-sidebar::-webkit-scrollbar{width:3px}
        .adm-sidebar::-webkit-scrollbar-thumb{background:rgba(255,255,255,.2);border-radius:3px}

        /* Brand */
        .sidebar-brand{padding:14px 10px;border-bottom:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:10px;min-height:60px;position:relative;}
        .sidebar-brand-icon{width:40px;height:40px;background:var(--gold);border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--navy-dark);font-size:1.2rem;flex-shrink:0}
        .sidebar-brand-text{overflow:hidden;white-space:nowrap;transition:opacity .2s,width .2s;}
        .sidebar-brand-text .t{color:#fff;font-weight:700;font-size:.88rem;line-height:1.2}
        .sidebar-brand-text .s{color:var(--gold);font-size:.7rem}
        .adm-sidebar.collapsed .sidebar-brand-text{opacity:0;width:0;pointer-events:none;}

        /* Collapse toggle button */
        .sidebar-collapse-btn {
            position:absolute; right:-12px; top:50%; transform:translateY(-50%);
            width:24px; height:24px; border-radius:50%;
            background:#fff; border:2px solid #e2e8f0;
            display:flex; align-items:center; justify-content:center;
            cursor:pointer; z-index:10; box-shadow:0 2px 8px rgba(0,0,0,.15);
            transition:all .2s; color:var(--navy); font-size:.7rem;
        }
        .sidebar-collapse-btn:hover{background:var(--gold);border-color:var(--gold);color:var(--navy-dark);}
        .adm-sidebar.collapsed .sidebar-collapse-btn i{transform:rotate(180deg);}

        /* User */
        .sidebar-user{margin:10px 8px;padding:8px;background:rgba(255,255,255,.06);border-radius:10px;border:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:8px;overflow:hidden;min-height:52px;}
        .sidebar-user .av{width:34px;height:34px;background:var(--gold);border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--navy-dark);font-weight:700;font-size:.85rem;flex-shrink:0}
        .sidebar-user .user-text{overflow:hidden;white-space:nowrap;transition:opacity .2s,width .2s;min-width:0;}
        .sidebar-user .nm{color:#fff;font-size:.82rem;font-weight:600;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .sidebar-user .rl{color:rgba(255,255,255,.5);font-size:.62rem;white-space:nowrap;overflow:hidden;}
        .adm-sidebar.collapsed .sidebar-user .user-text{opacity:0;width:0;pointer-events:none;}

        /* Nav */
        .sidebar-nav{padding:6px 0;flex:1}
        .sidebar-sec{padding:8px 16px 3px;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.35);white-space:nowrap;overflow:hidden;transition:opacity .2s;}
        .adm-sidebar.collapsed .sidebar-sec{opacity:0;height:0;padding:0;pointer-events:none;}

        .sidebar-link{display:flex;align-items:center;gap:9px;padding:9px 16px;color:rgba(255,255,255,.72);text-decoration:none;font-size:.855rem;border-left:3px solid transparent;transition:all .2s;white-space:nowrap;overflow:hidden;position:relative;}
        .sidebar-link i{width:18px;text-align:center;font-size:.95rem;flex-shrink:0;}
        .sidebar-link .link-text{overflow:hidden;white-space:nowrap;transition:opacity .2s,width .2s;}
        .sidebar-link:hover{color:#fff;background:rgba(255,255,255,.07);border-left-color:var(--gold)}
        .sidebar-link.active{color:var(--gold);background:rgba(245,166,35,.12);border-left-color:var(--gold);font-weight:600}
        .sidebar-link .bc{margin-left:auto;background:var(--gold);color:var(--navy-dark);font-size:.65rem;font-weight:700;padding:1px 6px;border-radius:20px;flex-shrink:0;}
        .adm-sidebar.collapsed .sidebar-link .link-text{opacity:0;width:0;pointer-events:none;}
        .adm-sidebar.collapsed .sidebar-link .bc{display:none;}
        .adm-sidebar.collapsed .sidebar-link{padding:9px 0;justify-content:center;border-left:none;border-right:3px solid transparent;}
        .adm-sidebar.collapsed .sidebar-link:hover{border-right-color:var(--gold);}
        .adm-sidebar.collapsed .sidebar-link.active{border-right-color:var(--gold);}

        /* Tooltip khi collapsed */
        .adm-sidebar.collapsed .sidebar-link{position:relative;}
        .adm-sidebar.collapsed .sidebar-link::after{
            content:attr(data-tooltip);
            position:absolute; left:calc(var(--sidebar-collapsed-w) + 8px); top:50%;
            transform:translateY(-50%);
            background:rgba(13,45,107,.95); color:#fff;
            padding:5px 10px; border-radius:6px; font-size:.78rem;
            white-space:nowrap; pointer-events:none;
            opacity:0; transition:opacity .15s;
            z-index:9999;
        }
        .adm-sidebar.collapsed .sidebar-link:hover::after{opacity:1;}

        .sidebar-divider{height:1px;background:rgba(255,255,255,.08);margin:8px 16px}
        .adm-sidebar.collapsed .sidebar-divider{margin:8px 6px;}

        /* Main */
        .adm-main{margin-left:var(--sidebar-w);min-height:100vh;display:flex;flex-direction:column;transition:margin-left .25s cubic-bezier(.4,0,.2,1);}
        .adm-main.collapsed{margin-left:var(--sidebar-collapsed-w);}
        .adm-topbar{background:#fff;padding:0 22px;height:58px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 1px 8px rgba(0,0,0,.07);position:sticky;top:0;z-index:100}
        .adm-topbar .pt{font-weight:700;color:var(--navy);font-size:1rem}
        .adm-content{padding:22px;flex:1}
        .adm-footer{background:#fff;padding:12px 22px;text-align:center;color:#6b7a99;font-size:.8rem;border-top:1px solid #e2e8f0}

        /* Cards */
        .card{border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 2px 10px rgba(13,45,107,.07)}
        .card-header{background:var(--navy);color:#fff;border-radius:12px 12px 0 0!important;padding:12px 18px;font-weight:600;border-bottom:3px solid var(--gold)}
        .stat-card{background:#fff;border-radius:12px;padding:18px;box-shadow:0 2px 10px rgba(13,45,107,.07);border:1px solid #e2e8f0;transition:all .3s}
        .stat-card:hover{transform:translateY(-3px);box-shadow:0 8px 22px rgba(13,45,107,.13)}
        .stat-card .ic{width:48px;height:48px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;margin-bottom:10px}
        .stat-card .vl{font-size:1.8rem;font-weight:800;color:var(--navy);line-height:1}
        .stat-card .lb{font-size:.78rem;color:#6b7a99;margin-top:3px}

        /* Table */
        .table thead th{background:var(--navy);color:#fff;font-weight:600;padding:11px 13px;border:none;font-size:.82rem}
        .table tbody td{padding:10px 13px;vertical-align:middle;border-color:#f0f2f7}
        .table tbody tr:hover{background:#f8f9ff}

        /* Badges */
        .bs{padding:4px 10px;border-radius:20px;font-size:.75rem;font-weight:600;display:inline-flex;align-items:center;gap:4px}
        .bs-new{background:rgba(248,150,30,.12);color:#c97a00}
        .bs-checking{background:rgba(59,130,246,.12);color:#2563eb}
        .bs-approved{background:rgba(16,185,129,.12);color:#059669}
        .bs-rejected{background:rgba(239,68,68,.12);color:#dc2626}
        .bs-enrolled{background:rgba(139,92,246,.12);color:#7c3aed}

        /* Buttons */
        .btn-navy{background:var(--navy);color:#fff;border:none;font-weight:600}
        .btn-navy:hover{background:var(--navy-light);color:#fff}
        .btn-gold{background:var(--gold);color:var(--navy-dark);border:none;font-weight:700}
        .btn-gold:hover{background:var(--gold-dark);color:var(--navy-dark)}

        /* Modal */
        .modal-header{background:var(--navy);color:#fff;border-bottom:3px solid var(--gold)}
        .modal-header .btn-close{filter:invert(1)}

        /* Responsive */
        @media(max-width:991px){
            .adm-sidebar{transform:translateX(-100%)}
            .adm-sidebar.show{transform:translateX(0)}
            .adm-main{margin-left:0}
            .sb-toggle{display:flex!important}
        }
        .sb-toggle{display:none;width:34px;height:34px;border:none;background:#f4f6fb;border-radius:8px;align-items:center;justify-content:center;color:var(--navy);cursor:pointer}
        .sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:999}
        .sb-overlay.show{display:block}
    </style>
</head>
<body>

<?php
// Pending count for badge
$_pendingCount = $conn->query("SELECT COUNT(*) as c FROM admission_applications WHERE status='new'")->fetch_assoc()['c'] ?? 0;
$_enrollCount  = $conn->query("SELECT COUNT(*) as c FROM admission_applications WHERE status='approved'")->fetch_assoc()['c'] ?? 0;
?>

<div class="adm-sidebar" id="admSidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon"><i class="bi bi-mortarboard-fill"></i></div>
        <div class="sidebar-brand-text">
            <div class="t">Phòng Tuyển sinh</div>
            <div class="s">TDMU Admissions</div>
        </div>
        <button class="sidebar-collapse-btn" id="sidebarCollapseBtn" title="Thu gọn/Mở rộng">
            <i class="bi bi-chevron-left"></i>
        </button>
    </div>
    <div class="sidebar-user">
        <div class="av"><?php echo mb_strtoupper(mb_substr($_userName,0,1)); ?></div>
        <div class="user-text">
            <div class="nm"><?php echo htmlspecialchars($_userName); ?></div>
            <div class="rl">
                <?php foreach($_userRoles as $r): ?>
                <span style="background:<?php echo $r['color']??'#666';?>;color:#fff;padding:1px 5px;border-radius:4px;font-size:.62rem;margin-right:2px;"><?php echo htmlspecialchars($r['name']); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="sidebar-sec">Tổng quan</div>
        <a href="/university/admissions/index.php" class="sidebar-link <?php echo $_currentPage==='index.php'?'active':''; ?>" data-tooltip="Dashboard">
            <i class="bi bi-speedometer2"></i><span class="link-text"> Dashboard</span>
        </a>

        <div class="sidebar-sec">Hồ sơ</div>
        <a href="/university/admissions/applications.php" class="sidebar-link <?php echo in_array($_currentPage,['applications.php','application_detail.php'])?'active':''; ?>" data-tooltip="Quản lý hồ sơ">
            <i class="bi bi-file-earmark-person-fill"></i><span class="link-text"> Quản lý hồ sơ</span>
            <?php if($_pendingCount>0): ?><span class="bc"><?php echo $_pendingCount; ?></span><?php endif; ?>
        </a>

        <?php if(hasRole('admissions_manager')): ?>
        <div class="sidebar-sec">Xét tuyển</div>
        <a href="/university/admissions/auto_review.php" class="sidebar-link <?php echo $_currentPage==='auto_review.php'?'active':''; ?>" data-tooltip="Xét tuyển tự động">
            <i class="bi bi-robot"></i><span class="link-text"> Xét tuyển tự động</span>
        </a>
        <a href="/university/admissions/results.php" class="sidebar-link <?php echo $_currentPage==='results.php'?'active':''; ?>" data-tooltip="Kết quả xét tuyển">
            <i class="bi bi-trophy-fill"></i><span class="link-text"> Kết quả xét tuyển</span>
        </a>
        <?php endif; ?>

        <div class="sidebar-sec">Nhập học</div>
        <a href="/university/admissions/enrollment.php" class="sidebar-link <?php echo $_currentPage==='enrollment.php'?'active':''; ?>" data-tooltip="Thủ tục nhập học">
            <i class="bi bi-person-check-fill"></i><span class="link-text"> Thủ tục nhập học</span>
            <?php if($_enrollCount>0): ?><span class="bc"><?php echo $_enrollCount; ?></span><?php endif; ?>
        </a>
        <?php if(hasRole('admissions_manager') || $_SESSION['role']==='admin'): ?>
        <a href="/university/admissions/auto_assign.php" class="sidebar-link <?php echo $_currentPage==='auto_assign.php'?'active':''; ?>" data-tooltip="Phân lớp tự động">
            <i class="bi bi-diagram-3-fill"></i><span class="link-text"> Phân lớp tự động</span>
        </a>
        <?php endif; ?>

        <?php if(hasRole('admissions_manager')): ?>
        <div class="sidebar-sec">Quản lý</div>
        <a href="/university/admissions/rounds.php" class="sidebar-link <?php echo $_currentPage==='rounds.php'?'active':''; ?>" data-tooltip="Đợt tuyển sinh">
            <i class="bi bi-calendar-range-fill"></i><span class="link-text"> Đợt tuyển sinh</span>
        </a>
        <a href="/university/admissions/methods.php" class="sidebar-link <?php echo $_currentPage==='methods.php'?'active':''; ?>" data-tooltip="Phương thức xét tuyển">
            <i class="bi bi-list-check"></i><span class="link-text"> Phương thức xét tuyển</span>
        </a>
        <a href="/university/admissions/news.php" class="sidebar-link <?php echo $_currentPage==='news.php'?'active':''; ?>" data-tooltip="Tin tức tuyển sinh">
            <i class="bi bi-newspaper"></i><span class="link-text"> Tin tức tuyển sinh</span>
        </a>
        <a href="/university/admissions/reports.php" class="sidebar-link <?php echo $_currentPage==='reports.php'?'active':''; ?>" data-tooltip="Báo cáo thống kê">
            <i class="bi bi-bar-chart-fill"></i><span class="link-text"> Báo cáo thống kê</span>
        </a>
        <?php endif; ?>

        <div class="sidebar-divider"></div>
        <?php if($_SESSION['role']==='admin'): ?>
        <a href="/university/admin/" class="sidebar-link" data-tooltip="Về Admin chính">
            <i class="bi bi-arrow-left-circle"></i><span class="link-text"> Về Admin chính</span>
        </a>
        <?php endif; ?>
        <a href="/university/staff/profile.php" class="sidebar-link <?php echo $_currentPage==='profile.php'?'active':''; ?>" data-tooltip="Thông tin cá nhân">
            <i class="bi bi-person-circle"></i><span class="link-text"> Thông tin cá nhân</span>
        </a>
        <a href="/university/login.php?logout=1" class="sidebar-link" style="color:rgba(239,68,68,.8)" data-tooltip="Đăng xuất">
            <i class="bi bi-box-arrow-right"></i><span class="link-text"> Đăng xuất</span>
        </a>
    </nav>
</div>
<div class="sb-overlay" id="sbOverlay" onclick="closeSidebar()"></div>

<div class="adm-main" id="admMain">
    <div class="adm-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="sb-toggle" onclick="openSidebar()"><i class="bi bi-list fs-5"></i></button>
            <span class="pt"><?php echo isset($pageTitle)?htmlspecialchars($pageTitle):'Dashboard'; ?></span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="/university/admissions/applications.php?status=new" class="btn btn-sm btn-outline-warning position-relative">
                <i class="bi bi-bell"></i>
                <?php if($_pendingCount>0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.6rem"><?php echo $_pendingCount; ?></span>
                <?php endif; ?>
            </a>
            <span class="text-muted small d-none d-md-inline"><i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($_userName); ?></span>
        </div>
    </div>
    <div class="adm-content">

<script>
function openSidebar(){document.getElementById('admSidebar').classList.add('show');document.getElementById('sbOverlay').classList.add('show')}
function closeSidebar(){document.getElementById('admSidebar').classList.remove('show');document.getElementById('sbOverlay').classList.remove('show')}

// Collapse sidebar
(function(){
    const STORAGE_KEY = 'tdmu_sidebar_collapsed';
    const sidebar = document.getElementById('admSidebar');
    const main    = document.getElementById('admMain');
    const btn     = document.getElementById('sidebarCollapseBtn');

    function setCollapsed(collapsed) {
        if (collapsed) {
            sidebar.classList.add('collapsed');
            main.classList.add('collapsed');
        } else {
            sidebar.classList.remove('collapsed');
            main.classList.remove('collapsed');
        }
        try { localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0'); } catch(e){}
    }

    // Restore state
    try {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved === '1') setCollapsed(true);
    } catch(e){}

    if (btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            setCollapsed(!sidebar.classList.contains('collapsed'));
        });
    }
})();
</script>
