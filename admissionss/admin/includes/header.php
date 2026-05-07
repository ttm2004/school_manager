<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin Tuyển sinh</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom Admin CSS -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --secondary: #764ba2;
            --success: #4cc9f0;
            --warning: #f8961e;
            --danger: #f94144;
            --dark: #1a1a2e;
            --darker: #16213e;
            --light: #f8f9fa;
            --gray: #6c757d;
            --text-primary: #ffffff;
            --text-secondary: #a8b2d1;
            --text-muted: #6c757d;
            --border-color: rgba(255,255,255,0.1);
            --sidebar-width: 280px;
            --header-height: 70px;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 25px rgba(0,0,0,0.15);
            --shadow-hover: 0 10px 30px rgba(102,126,234,0.3);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f0f2f5;
            overflow-x: hidden;
            min-height: 100vh;
        }

        /* Main Container */
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
            position: relative;
        }

        /* Content Wrapper */
        .content-wrapper {
            flex: 1;
            margin-left: var(--sidebar-width);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background: #f8f9fa;
            transition: margin-left 0.3s ease;
        }

        .content-wrapper.expanded {
            margin-left: 0;
        }

        /* Top Navigation */
        .top-nav {
            height: var(--header-height);
            background: white;
            padding: 0 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 99;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .menu-toggle {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--light);
            border: none;
            color: var(--dark);
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: none;
        }

        .menu-toggle:hover {
            background: var(--primary);
            color: white;
            transform: rotate(90deg);
        }

        .page-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
            position: relative;
        }

        .page-title::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 50px;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            border-radius: 3px;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .nav-item {
            position: relative;
        }

        .nav-link {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            background: var(--light);
            border: none;
            color: var(--dark);
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .nav-link:hover {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .nav-link .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 20px;
            height: 20px;
            background: var(--danger);
            color: white;
            border-radius: 10px;
            font-size: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 5px;
        }

        /* Dropdown Menu */
        .dropdown-menu {
            position: absolute;
            top: 60px;
            right: 0;
            width: 320px;
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow-xl);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .dropdown-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dropdown-header h4 {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
            color: var(--dark);
        }

        .dropdown-body {
            max-height: 350px;
            overflow-y: auto;
        }

        .dropdown-item {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
            transition: all 0.3s ease;
            border-bottom: 1px solid #f0f0f0;
        }

        .dropdown-item:hover {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            transform: translateX(5px);
        }

        .dropdown-item .item-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .item-icon.pending {
            background: rgba(248, 150, 30, 0.1);
            color: var(--warning);
        }

        .item-icon.success {
            background: rgba(76, 201, 240, 0.1);
            color: var(--success);
        }

        .item-content {
            flex: 1;
        }

        .item-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 3px;
        }

        .item-subtitle {
            font-size: 12px;
            color: var(--gray);
        }

        .item-time {
            font-size: 10px;
            color: var(--gray);
        }

        .dropdown-footer {
            padding: 15px 20px;
            border-top: 1px solid #eee;
        }

        .dropdown-footer a {
            color: var(--primary);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .user-info-dropdown {
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            background: linear-gradient(135deg, #667eea10 0%, #764ba210 100%);
        }

        .user-avatar.large {
            width: 50px;
            height: 50px;
            border-radius: 15px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 30px;
            background: #f8f9fa;
        }

        /* Footer */
        .admin-footer {
            background: white;
            padding: 20px 30px;
            border-top: 1px solid rgba(0,0,0,0.05);
            margin-top: auto;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .footer-left p {
            margin: 0;
            color: var(--gray);
            font-size: 13px;
        }

        .footer-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .version {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: white;
        }

        .footer-links {
            display: flex;
            gap: 10px;
        }

        .footer-links a {
            width: 35px;
            height: 35px;
            border-radius: 10px;
            background: var(--light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .footer-links a:hover {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            transform: translateY(-2px);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200px;
            height: 200px;
            background: linear-gradient(135deg, rgba(102,126,234,0.1) 0%, rgba(118,75,162,0.1) 100%);
            border-radius: 50%;
            transition: all 0.5s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card:hover::before {
            transform: scale(1.2);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            position: relative;
            z-index: 1;
        }

        .stat-card.total .stat-icon {
            background: linear-gradient(135deg, #667eea20 0%, #764ba220 100%);
            color: var(--primary);
        }

        .stat-card.pending .stat-icon {
            background: linear-gradient(135deg, #f8961e20 0%, #f5576c20 100%);
            color: var(--warning);
        }

        .stat-card.approved .stat-icon {
            background: linear-gradient(135deg, #4cc9f020 0%, #4895ef20 100%);
            color: var(--success);
        }

        .stat-card.rejected .stat-icon {
            background: linear-gradient(135deg, #f9414420 0%, #f3722c20 100%);
            color: var(--danger);
        }

        .stat-card.today .stat-icon {
            background: linear-gradient(135deg, #43aa8b20 0%, #4c956c20 100%);
            color: #43aa8b;
        }

        .stat-info h3 {
            font-size: 32px;
            font-weight: 700;
            margin: 0;
            color: var(--dark);
        }

        .stat-info p {
            margin: 5px 0 0;
            color: var(--gray);
            font-size: 14px;
        }

        .stat-footer {
            border-top: 1px solid #eee;
            padding-top: 15px;
        }

        .stat-footer a {
            color: var(--primary);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .stat-footer a:hover {
            gap: 10px;
        }

        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow-sm);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-header h4 {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-header h4 i {
            color: var(--primary);
        }

        .btn-icon {
            width: 35px;
            height: 35px;
            border-radius: 10px;
            background: var(--light);
            border: none;
            color: var(--gray);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-icon:hover {
            background: var(--primary);
            color: white;
            transform: rotate(180deg);
        }

        .chart-body {
            height: 300px;
            position: relative;
        }

        /* Table Card */
        .table-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow-sm);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-header h4 {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-header h4 i {
            color: var(--primary);
        }

        .btn-view-all {
            padding: 8px 15px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .btn-view-all:hover {
            transform: translateX(5px);
            box-shadow: var(--shadow-hover);
            color: white;
        }

        /* Data Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            text-align: left;
            padding: 15px;
            font-size: 13px;
            font-weight: 600;
            color: var(--gray);
            border-bottom: 2px solid #eee;
        }

        .data-table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            color: var(--dark);
        }

        .data-table tbody tr {
            transition: all 0.3s ease;
        }

        .data-table tbody tr:hover {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            transform: scale(1.01);
            box-shadow: var(--shadow-md);
        }

        .user-info-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar.small {
            width: 35px;
            height: 35px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-details strong {
            font-size: 14px;
            font-weight: 600;
        }

        .user-details small {
            font-size: 11px;
            color: var(--gray);
        }

        .date-info {
            display: flex;
            flex-direction: column;
        }

        .date-info span {
            font-size: 13px;
            font-weight: 500;
        }

        .date-info small {
            font-size: 11px;
            color: var(--gray);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .status-badge.warning {
            background: rgba(248, 150, 30, 0.1);
            color: var(--warning);
        }

        .status-badge.success {
            background: rgba(76, 201, 240, 0.1);
            color: var(--success);
        }

        .status-badge.danger {
            background: rgba(249, 65, 68, 0.1);
            color: var(--danger);
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn-action {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-action.view {
            background: rgba(102, 126, 234, 0.1);
            color: var(--primary);
        }

        .btn-action.approve {
            background: rgba(76, 201, 240, 0.1);
            color: var(--success);
        }

        .btn-action.reject {
            background: rgba(249, 65, 68, 0.1);
            color: var(--danger);
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-action.view:hover {
            background: var(--primary);
            color: white;
        }

        .btn-action.approve:hover {
            background: var(--success);
            color: white;
        }

        .btn-action.reject:hover {
            background: var(--danger);
            color: white;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .menu-toggle {
                display: flex;
            }
            
            .content-wrapper {
                margin-left: 0;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .main-content {
                padding: 20px;
            }
            
            .footer-content {
                flex-direction: column;
                text-align: center;
            }
            
            .footer-right {
                flex-direction: column;
                gap: 10px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .data-table {
                font-size: 12px;
            }
            
            .action-buttons {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">