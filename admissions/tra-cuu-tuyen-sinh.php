<?php
require_once 'php/config.php';

$page_title = "Tra cứu hồ sơ tuyển sinh";
require_once '../includes/header.php';
?>

<style>
    /* ==================== VARIABLES & RESET ==================== */
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
        --gradient-1: linear-gradient(135deg, #667eea, #764ba2);
        --gradient-2: linear-gradient(135deg, #4cc9f0, #4895ef);
        --gradient-3: linear-gradient(135deg, #f8961e, #f3722c);
        --gradient-4: linear-gradient(135deg, #9d4edd, #7b2cbf);
        --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
        --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
        --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
        --shadow-xl: 0 20px 25px rgba(0,0,0,0.15);
        --shadow-hover: 0 10px 30px rgba(102,126,234,0.3);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: linear-gradient(135deg, #667eea05, #764ba205);
        overflow-x: hidden;
    }

    /* ==================== PAGE HEADER ==================== */
    .page-header-modern {
        position: relative;
        background: linear-gradient(135deg, #667eea, #764ba2);
        padding: 100px 0 150px;
        overflow: hidden;
    }

    .page-header-modern::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        animation: rotate 20s linear infinite;
    }

    @keyframes rotate {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    .page-title {
        font-size: 48px;
        font-weight: 800;
        color: white;
        margin-bottom: 20px;
        line-height: 1.2;
        text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        animation: fadeInLeft 1s ease;
    }

    .text-gradient {
        background: linear-gradient(135deg, #fff, #f0f0f0);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .page-description {
        font-size: 18px;
        color: rgba(255,255,255,0.9);
        margin-bottom: 30px;
        line-height: 1.6;
        animation: fadeInLeft 1s ease 0.2s both;
    }

    .page-illustration {
        animation: float 3s ease-in-out infinite;
    }

    @keyframes float {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-20px); }
    }

    .wave-shape {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        line-height: 0;
    }

    .wave-shape svg {
        width: 100%;
        height: auto;
    }

    /* ==================== SEARCH SECTION ==================== */
    .search-section {
        margin-top: -80px;
        position: relative;
        z-index: 10;
        padding-bottom: 60px;
    }

    .search-card {
        background: white;
        border-radius: 30px;
        padding: 40px;
        box-shadow: var(--shadow-xl);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.2);
        animation: slideUp 1s ease;
        transition: all 0.3s ease;
    }

    .search-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-hover);
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(50px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .search-card-header {
        text-align: center;
        margin-bottom: 30px;
    }

    .icon-circle {
        width: 80px;
        height: 80px;
        background: var(--gradient-1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        font-size: 32px;
        color: white;
        animation: pulse 2s infinite;
        box-shadow: var(--shadow-lg);
    }

    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }

    .search-card-header h2 {
        font-size: 28px;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 10px;
    }

    .search-card-header p {
        color: var(--gray);
        font-size: 16px;
    }

    .search-form {
        max-width: 500px;
        margin: 0 auto;
    }

    .form-group {
        margin-bottom: 25px;
        animation: fadeInUp 1s ease;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .form-group label {
        display: block;
        font-size: 14px;
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 8px;
    }

    .form-group label i {
        color: var(--primary);
        margin-right: 5px;
    }

    .input-group {
        position: relative;
    }

    .input-group i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray);
        font-size: 18px;
        transition: all 0.3s ease;
    }

    .input-group input {
        width: 100%;
        padding: 15px 15px 15px 45px;
        border: 2px solid #e9ecef;
        border-radius: 15px;
        font-size: 16px;
        transition: all 0.3s ease;
        background: white;
    }

    .input-group input:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 4px rgba(102,126,234,0.1);
    }

    .input-group input:focus + i {
        color: var(--primary);
        transform: translateY(-50%) scale(1.1);
    }

    .input-hint {
        display: block;
        font-size: 12px;
        color: var(--gray);
        margin-top: 5px;
        margin-left: 15px;
    }

    .search-loading {
        display: none;
        text-align: center;
        margin: 20px 0;
    }

    .search-loading.active {
        display: block;
        animation: fadeIn 0.3s ease;
    }

    .spinner {
        width: 40px;
        height: 40px;
        border: 3px solid #f3f3f3;
        border-top: 3px solid var(--primary);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto 10px;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .btn-search {
        width: 100%;
        padding: 15px;
        background: var(--gradient-1);
        color: white;
        border: none;
        border-radius: 15px;
        font-size: 18px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .btn-search::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(255,255,255,0.3);
        transform: translate(-50%, -50%);
        transition: width 0.6s, height 0.6s;
    }

    .btn-search:hover::before {
        width: 300px;
        height: 300px;
    }

    .btn-search:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-hover);
    }

    .btn-search:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* ==================== RESULT SECTION ==================== */
    .result-section {
        padding: 60px 0;
        background: #f8f9fa;
        position: relative;
    }

    .result-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 20px;
    }

    .result-title {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .result-title i {
        font-size: 32px;
        color: var(--success);
        animation: bounce 2s infinite;
    }

    @keyframes bounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-5px); }
    }

    .result-title h2 {
        font-size: 28px;
        font-weight: 700;
        color: var(--dark);
    }

    .registration-badge {
        background: var(--gradient-4);
        color: white;
        padding: 10px 20px;
        border-radius: 50px;
        font-size: 14px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 5px;
        animation: slideInRight 1s ease;
    }

    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(50px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    /* Profile Card */
    .profile-card-modern {
        background: white;
        border-radius: 30px;
        overflow: hidden;
        box-shadow: var(--shadow-xl);
        animation: scaleIn 0.5s ease;
    }

    @keyframes scaleIn {
        from {
            opacity: 0;
            transform: scale(0.9);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    .profile-cover {
        height: 200px;
        background: var(--gradient-1);
        position: relative;
        overflow: hidden;
    }

    .profile-cover::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 70%);
        animation: rotate 20s linear infinite;
    }

    .profile-info {
        padding: 0 30px 30px;
        position: relative;
    }

    .profile-avatar-large {
        width: 120px;
        height: 120px;
        background: var(--gradient-2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: -60px auto 20px;
        font-size: 48px;
        color: white;
        border: 5px solid white;
        box-shadow: var(--shadow-lg);
        animation: slideDown 1s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-50px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .profile-name-section {
        text-align: center;
        margin-bottom: 30px;
    }

    .profile-name-section h3 {
        font-size: 28px;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 10px;
    }

    .status-badge-modern {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 8px 20px;
        border-radius: 50px;
        font-size: 14px;
        font-weight: 600;
        animation: pulse 2s infinite;
    }

    .status-badge-modern.status-pending {
        background: rgba(248,150,30,0.1);
        color: var(--warning);
        border: 2px solid var(--warning);
    }

    .status-badge-modern.status-approved {
        background: rgba(76,201,240,0.1);
        color: var(--success);
        border: 2px solid var(--success);
    }

    .status-badge-modern.status-rejected {
        background: rgba(249,65,68,0.1);
        color: var(--danger);
        border: 2px solid var(--danger);
    }

    /* Info Grid */
    .info-grid-modern {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .info-card-modern {
        background: #f8f9fa;
        border-radius: 20px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .info-card-modern::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: var(--gradient-1);
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .info-card-modern:hover {
        transform: translateY(-5px) scale(1.02);
        box-shadow: var(--shadow-lg);
    }

    .info-card-modern:hover::before {
        opacity: 1;
    }

    .info-icon {
        width: 50px;
        height: 50px;
        background: var(--gradient-1);
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: white;
        transition: all 0.3s ease;
    }

    .info-card-modern:hover .info-icon {
        transform: rotate(5deg) scale(1.1);
    }

    .info-label {
        font-size: 12px;
        color: var(--gray);
        margin-bottom: 3px;
    }

    .info-value {
        font-size: 15px;
        font-weight: 600;
        color: var(--dark);
    }

    /* Score Card */
    .score-card-modern {
        background: linear-gradient(135deg, #667eea10, #764ba210);
        border-radius: 20px;
        padding: 25px;
        margin-bottom: 30px;
        border: 1px solid rgba(102,126,234,0.2);
    }

    .score-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
    }

    .score-header i {
        font-size: 24px;
        color: var(--primary);
        animation: pulse 2s infinite;
    }

    .score-header h3 {
        font-size: 18px;
        font-weight: 600;
        color: var(--dark);
    }

    .score-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
    }

    .score-item {
        background: white;
        border-radius: 15px;
        padding: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: var(--shadow-sm);
        transition: all 0.3s ease;
    }

    .score-item:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-md);
    }

    .score-subject {
        font-weight: 500;
        color: var(--gray);
    }

    .score-value {
        font-size: 18px;
        font-weight: 700;
        color: var(--primary);
    }

    .score-total {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 2px dashed rgba(102,126,234,0.3);
        display: flex;
        justify-content: space-between;
        font-size: 18px;
        font-weight: 700;
    }

    .score-total span:last-child {
        color: var(--success);
        font-size: 24px;
    }

    /* Confirmation Box */
    .confirmation-box {
        background: white;
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 30px;
        border: 2px solid var(--success);
        position: relative;
        overflow: hidden;
    }

    .confirmation-box::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(76,201,240,0.1) 0%, transparent 70%);
        animation: rotate 20s linear infinite;
    }

    .confirmation-box h4 {
        font-size: 24px;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 15px;
    }

    .countdown-timer {
        font-size: 48px;
        font-weight: 700;
        color: var(--success);
        text-align: center;
        margin: 20px 0;
        font-family: 'Courier New', monospace;
        text-shadow: 0 0 10px rgba(76,201,240,0.3);
        animation: glow 2s infinite;
    }

    @keyframes glow {
        0%, 100% { text-shadow: 0 0 10px rgba(76,201,240,0.3); }
        50% { text-shadow: 0 0 20px rgba(76,201,240,0.6); }
    }

    .warning-box {
        background: #fff3cd;
        border: 1px solid #ffeeba;
        border-radius: 15px;
        padding: 20px;
        margin: 20px 0;
        display: flex;
        gap: 15px;
        color: #856404;
        animation: shake 0.5s ease;
    }

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
        20%, 40%, 60%, 80% { transform: translateX(5px); }
    }

    .warning-box i {
        font-size: 24px;
    }

    .btn-confirm-admission {
        width: 100%;
        padding: 15px;
        background: var(--gradient-2);
        color: white;
        border: none;
        border-radius: 15px;
        font-size: 18px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .btn-confirm-admission::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(255,255,255,0.3);
        transform: translate(-50%, -50%);
        transition: width 0.6s, height 0.6s;
    }

    .btn-confirm-admission:hover::before {
        width: 300px;
        height: 300px;
    }

    .btn-confirm-admission:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 30px rgba(76,201,240,0.4);
    }

    /* Confirmed Box */
    .confirmed-box {
        background: rgba(76,201,240,0.1);
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 30px;
        text-align: center;
        border: 2px solid var(--success);
        animation: scaleIn 0.5s ease;
    }

    .confirmed-box i {
        font-size: 60px;
        color: var(--success);
        margin-bottom: 15px;
        animation: pulse 2s infinite;
    }

    .confirmed-box h4 {
        font-size: 24px;
        color: var(--success);
        margin-bottom: 10px;
    }

    /* Expired Box */
    .expired-box {
        background: rgba(249,65,68,0.1);
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 30px;
        text-align: center;
        border: 2px solid var(--danger);
        animation: shake 0.5s ease;
    }

    .expired-box i {
        font-size: 60px;
        color: var(--danger);
        margin-bottom: 15px;
        animation: pulse 2s infinite;
    }

    .expired-box h4 {
        font-size: 24px;
        color: var(--danger);
        margin-bottom: 10px;
    }

    /* Documents Grid */
    .documents-grid-modern {
        margin-bottom: 30px;
    }

    .documents-grid-modern h3 {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 18px;
        margin-bottom: 20px;
    }

    .documents-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
    }

    .document-card {
        background: #f8f9fa;
        border-radius: 15px;
        padding: 15px;
        display: flex;
        align-items: center;
        gap: 15px;
        transition: all 0.3s ease;
    }

    .document-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-md);
    }

    .document-icon {
        width: 50px;
        height: 50px;
        background: var(--gradient-1);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: white;
    }

    .document-info {
        flex: 1;
    }

    .document-name {
        font-weight: 600;
        margin-bottom: 3px;
    }

    .document-size {
        font-size: 11px;
        color: var(--gray);
    }

    .btn-download {
        width: 35px;
        height: 35px;
        border-radius: 8px;
        background: white;
        border: none;
        color: var(--primary);
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-download:hover {
        background: var(--primary);
        color: white;
        transform: scale(1.1);
    }

    /* Timeline */
    .timeline-modern {
        margin-bottom: 30px;
    }

    .timeline-modern h3 {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 18px;
        margin-bottom: 20px;
    }

    .timeline-container {
        position: relative;
    }

    .timeline-container::before {
        content: '';
        position: absolute;
        left: 20px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: linear-gradient(to bottom, var(--primary), var(--secondary));
        opacity: 0.3;
    }

    .timeline-item-modern {
        position: relative;
        padding-left: 50px;
        margin-bottom: 20px;
        animation: slideInLeft 1s ease;
    }

    @keyframes slideInLeft {
        from {
            opacity: 0;
            transform: translateX(-50px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .timeline-item-modern::before {
        content: '';
        position: absolute;
        left: 12px;
        top: 0;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: var(--gradient-1);
        border: 3px solid white;
        box-shadow: var(--shadow-md);
        z-index: 1;
    }

    .timeline-date {
        font-size: 12px;
        color: var(--gray);
        margin-bottom: 5px;
    }

    .timeline-content {
        background: #f8f9fa;
        border-radius: 15px;
        padding: 15px;
        transition: all 0.3s ease;
    }

    .timeline-content:hover {
        transform: translateX(5px);
        box-shadow: var(--shadow-md);
    }

    .timeline-content h4 {
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 3px;
    }

    .timeline-content p {
        font-size: 13px;
        color: var(--gray);
        margin: 0;
    }

    /* Action Buttons */
    .action-buttons-modern {
        display: flex;
        gap: 15px;
        justify-content: center;
        flex-wrap: wrap;
        margin-top: 30px;
    }

    .btn-action-modern {
        padding: 12px 25px;
        border: none;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .btn-action-modern::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(255,255,255,0.2);
        transform: translate(-50%, -50%);
        transition: width 0.6s, height 0.6s;
    }

    .btn-action-modern:hover::before {
        width: 300px;
        height: 300px;
    }

    .btn-primary-modern {
        background: var(--gradient-1);
        color: white;
    }

    .btn-outline-modern {
        background: white;
        color: var(--dark);
        border: 2px solid var(--primary);
    }

    .btn-secondary-modern {
        background: var(--gradient-3);
        color: white;
    }

    .btn-action-modern:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-hover);
    }

    /* Error Message */
    .error-message-modern {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 30px;
        box-shadow: var(--shadow-xl);
        animation: scaleIn 0.5s ease;
    }

    .error-message-modern i {
        font-size: 80px;
        color: var(--danger);
        margin-bottom: 20px;
        animation: pulse 2s infinite;
    }

    .error-message-modern h3 {
        font-size: 24px;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 10px;
    }

    .error-message-modern p {
        color: var(--gray);
        margin-bottom: 30px;
    }

    .btn-retry {
        padding: 12px 30px;
        background: var(--gradient-1);
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
    }

    .btn-retry:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-hover);
    }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        backdrop-filter: blur(5px);
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }

    .modal.show {
        display: flex;
        animation: fadeIn 0.3s ease;
    }

    .modal-content {
        background: white;
        border-radius: 30px;
        width: 90%;
        max-width: 500px;
        max-height: 90vh;
        overflow-y: auto;
        animation: slideUp 0.3s ease;
    }

    .modal-header {
        padding: 25px;
        background: var(--gradient-1);
        color: white;
        display: flex;
        align-items: center;
        gap: 15px;
        border-radius: 30px 30px 0 0;
    }

    .modal-header i {
        font-size: 28px;
    }

    .modal-header h3 {
        flex: 1;
        margin: 0;
        font-size: 20px;
    }

    .modal-close {
        background: rgba(255,255,255,0.2);
        border: none;
        color: white;
        width: 35px;
        height: 35px;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .modal-close:hover {
        background: rgba(255,255,255,0.3);
        transform: rotate(90deg);
    }

    .modal-body {
        padding: 25px;
    }

    .modal-footer {
        padding: 25px;
        border-top: 1px solid #eee;
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }

    .btn-cancel {
        padding: 12px 25px;
        border: 1px solid #e9ecef;
        background: white;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-cancel:hover {
        background: #f8f9fa;
    }

    .btn-confirm {
        padding: 12px 25px;
        background: var(--gradient-1);
        color: white;
        border: none;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-confirm:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-hover);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .page-title {
            font-size: 36px;
        }

        .search-card {
            padding: 30px 20px;
        }

        .info-grid-modern {
            grid-template-columns: 1fr;
        }

        .score-grid {
            grid-template-columns: 1fr;
        }

        .action-buttons-modern {
            flex-direction: column;
        }

        .btn-action-modern {
            width: 100%;
            justify-content: center;
        }

        .countdown-timer {
            font-size: 36px;
        }
    }

    @media (max-width: 480px) {
        .page-title {
            font-size: 28px;
        }

        .result-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .profile-avatar-large {
            width: 80px;
            height: 80px;
            font-size: 32px;
        }

        .countdown-timer {
            font-size: 24px;
        }
    }

    /* Animation cho các phần tử khi scroll */
    .fade-in {
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.6s ease;
    }

    .fade-in.visible {
        opacity: 1;
        transform: translateY(0);
    }
</style>

<!-- Page Header với hiệu ứng đẹp -->
<section class="page-header-modern">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="page-title">Tra cứu hồ sơ<br><span class="text-gradient">tuyển sinh 2024</span></h1>
                <p class="page-description">Nhập thông tin để kiểm tra tình trạng hồ sơ và kết quả xét tuyển của bạn</p>
            </div>
            <div class="col-lg-6 text-center">
                <div class="page-illustration">
                    <i class="fas fa-search fa-6x text-white opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="wave-shape">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320">
            <path fill="#ffffff" fill-opacity="1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,154.7C960,171,1056,181,1152,170.7C1248,160,1344,128,1392,112L1440,96L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
        </svg>
    </div>
</section>

<!-- Search Section -->
<section class="search-section">
    <div class="container">
        <div class="search-card">
            <div class="search-card-header">
                <div class="icon-circle">
                    <i class="fas fa-id-card"></i>
                </div>
                <h2>Tra cứu hồ sơ</h2>
                <p>Nhập CCCD/CMND và ngày sinh để kiểm tra</p>
            </div>
            <div class="search-card-body">
                <form class="search-form" id="searchForm">
                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> Số CCCD/CMND <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <i class="fas fa-id-card"></i>
                            <input type="text" id="identification" name="identification" placeholder="Nhập số CCCD/CMND (9 hoặc 12 số)"
                                pattern="[0-9]{9,12}" maxlength="12" required>
                        </div>
                        <span class="input-hint">Nhập đúng số CCCD/CMND đã đăng ký</span>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-calendar-alt"></i> Ngày sinh <span class="text-danger">*</span></label>
                        <div class="input-group date">
                            <i class="fas fa-calendar-alt"></i>
                            <input type="date" id="birthday" name="birthday" required>
                        </div>
                        <span class="input-hint">Chọn ngày/tháng/năm sinh</span>
                    </div>

                    <div class="search-loading" id="searchLoading">
                        <div class="spinner"></div>
                        <p>Đang tra cứu...</p>
                    </div>

                    <button type="submit" class="btn-search" id="searchBtn">
                        <i class="fas fa-search"></i>
                        <span>Tra cứu ngay</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- Result Section -->
<section class="result-section" id="resultSection" style="display: none;">
    <div class="container">
        <div class="result-header">
            <div class="result-title">
                <i class="fas fa-check-circle"></i>
                <h2>Kết quả tra cứu</h2>
            </div>
            <div class="registration-badge">
                <i class="fas fa-hashtag"></i> Mã hồ sơ: <span id="regId"></span>
            </div>
        </div>

        <!-- Profile Card -->
        <div class="profile-card-modern">
            <div class="profile-cover"></div>
            <div class="profile-info">
                <div class="profile-avatar-large" id="profileAvatar">
                    <i class="fas fa-user-graduate"></i>
                </div>

                <div class="profile-name-section">
                    <h3 id="studentName"></h3>
                    <span class="status-badge-modern" id="statusBadge"></span>
                </div>

                <!-- Thông tin cơ bản -->
                <div class="info-grid-modern" id="basicInfo">
                    <!-- Dynamic content -->
                </div>

                <!-- Thông tin tuyển sinh -->
                <div class="info-grid-modern" id="admissionInfo">
                    <!-- Dynamic content -->
                </div>

                <!-- Điểm xét tuyển -->
                <div class="score-card-modern" id="scoreSection" style="display: none;">
                    <div class="score-header">
                        <i class="fas fa-chart-line"></i>
                        <h3>Điểm xét tuyển</h3>
                    </div>
                    <div class="score-grid" id="scoreGrid">
                        <!-- Dynamic scores -->
                    </div>
                </div>

                <!-- Kết quả xét tuyển và xác nhận nhập học -->
                <div class="admission-result-section" id="admissionResult">
                    <!-- Dynamic content -->
                </div>

                <!-- Hồ sơ đính kèm -->
                <div class="documents-grid-modern" id="documentsList" style="display: none;">
                    <h3><i class="fas fa-paperclip"></i> Hồ sơ đính kèm</h3>
                    <div class="documents-container" id="documentsContainer">
                        <!-- Dynamic documents -->
                    </div>
                </div>

                <!-- Lịch sử xử lý -->
                <div class="timeline-modern" id="timeline" style="display: none;">
                    <h3><i class="fas fa-history"></i> Lịch sử xử lý</h3>
                    <div class="timeline-container" id="timelineContainer">
                        <!-- Dynamic timeline -->
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons-modern">
                    <button class="btn-action-modern btn-primary-modern" onclick="window.print()">
                        <i class="fas fa-print"></i> In hồ sơ
                    </button>
                    <button class="btn-action-modern btn-outline-modern" onclick="downloadProfile()">
                        <i class="fas fa-file-pdf"></i> Tải PDF
                    </button>
                    <button class="btn-action-modern btn-secondary-modern" onclick="contactAdmission()">
                        <i class="fas fa-headset"></i> Liên hệ tư vấn
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Modal xác nhận nhập học -->
<div class="modal" id="confirmModal">
    <div class="modal-content">
        <div class="modal-header">
            <i class="fas fa-check-circle"></i>
            <h3>Xác nhận nhập học</h3>
            <button class="modal-close" onclick="hideModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Thông tin thí sinh:</strong>
                    <p id="confirmStudentName"></p>
                    <p id="confirmStudentMajor"></p>
                    <p id="confirmStudentScore"></p>
                </div>
            </div>

            <div class="countdown-timer" id="countdownTimer"></div>

            <div class="warning-box">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Lưu ý quan trọng:</strong>
                    <ul style="margin-top: 5px; padding-left: 20px;">
                        <li>Thời hạn xác nhận: <strong>7 ngày</strong> kể từ khi nhận được kết quả</li>
                        <li>Sau thời gian trên, nếu không xác nhận, kết quả trúng tuyển sẽ bị <strong>hủy bỏ</strong></li>
                        <li>Mỗi thí sinh chỉ được xác nhận nhập học <strong>một lần duy nhất</strong></li>
                        <li>Việc xác nhận nhập học đồng nghĩa với cam kết sẽ theo học tại trường</li>
                    </ul>
                </div>
            </div>

            <p style="text-align: center; margin: 20px 0; color: #6c757d;">
                Bạn có chắc chắn muốn xác nhận nhập học?
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="hideModal()">Hủy bỏ</button>
            <button class="btn-confirm" onclick="processConfirmation()">Xác nhận nhập học</button>
        </div>
    </div>
</div>

<script>
    let currentStudent = null;
    let countdownInterval = null;

    // Search form submission
    document.getElementById('searchForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const cccd = document.getElementById('identification').value.trim();
        const birthday = document.getElementById('birthday').value;

        // Validate
        if (!cccd || !birthday) {
            alert('Vui lòng nhập đầy đủ thông tin');
            return;
        }

        if (!/^[0-9]{9,12}$/.test(cccd)) {
            alert('Số CCCD/CMND không hợp lệ (phải là 9 hoặc 12 số)');
            return;
        }

        // Show loading
        document.getElementById('searchLoading').classList.add('active');
        document.getElementById('searchBtn').disabled = true;

        try {
            const response = await fetch(`api/tra-cuu.php?identification=${cccd}&birthday=${birthday}`);
            const data = await response.json();

            if (data.success) {
                currentStudent = data.data;
                displayResult(data.data);
            } else {
                showError(data.message || 'Không tìm thấy hồ sơ');
            }
        } catch (error) {
            console.error('Error:', error);
            showError('Có lỗi xảy ra, vui lòng thử lại sau');
        } finally {
            document.getElementById('searchLoading').classList.remove('active');
            document.getElementById('searchBtn').disabled = false;
        }
    });

    // Display result
    function displayResult(data) {
        const resultSection = document.getElementById('resultSection');
        resultSection.style.display = 'block';

        // Scroll to result
        setTimeout(() => {
            resultSection.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }, 300);

        // Update basic info
        document.getElementById('regId').textContent = '#' + String(data.id).padStart(6, '0');
        document.getElementById('studentName').textContent = data.fullname;

        // Update status
        const statusBadge = document.getElementById('statusBadge');
        statusBadge.className = 'status-badge-modern ' + data.status_class;
        statusBadge.innerHTML = `<i class="fas ${data.status_icon}"></i> ${data.status_text}`;

        // Update basic info grid
        const basicInfo = document.getElementById('basicInfo');
        basicInfo.innerHTML = `
        <div class="info-card-modern">
            <div class="info-icon"><i class="fas fa-id-card"></i></div>
            <div class="info-label">CCCD/CMND</div>
            <div class="info-value">${data.identification}</div>
        </div>
        <div class="info-card-modern">
            <div class="info-icon"><i class="fas fa-calendar-alt"></i></div>
            <div class="info-label">Ngày sinh</div>
            <div class="info-value">${formatDate(data.birthday)}</div>
        </div>
        <div class="info-card-modern">
            <div class="info-icon"><i class="fas fa-phone"></i></div>
            <div class="info-label">Số điện thoại</div>
            <div class="info-value">${data.phone}</div>
        </div>
        <div class="info-card-modern">
            <div class="info-icon"><i class="fas fa-envelope"></i></div>
            <div class="info-label">Email</div>
            <div class="info-value">${data.email}</div>
        </div>
        <div class="info-card-modern">
            <div class="info-icon"><i class="fas fa-map-marker-alt"></i></div>
            <div class="info-label">Địa chỉ</div>
            <div class="info-value">${data.address || 'Chưa cập nhật'}</div>
        </div>
        <div class="info-card-modern">
            <div class="info-icon"><i class="fas fa-graduation-cap"></i></div>
            <div class="info-label">Trường THPT</div>
            <div class="info-value">${data.school || 'Chưa cập nhật'}</div>
        </div>
    `;

        // Update admission info grid
        const admissionInfo = document.getElementById('admissionInfo');
        admissionInfo.innerHTML = `
        <div class="info-card-modern">
            <div class="info-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="info-label">Năm tốt nghiệp</div>
            <div class="info-value">${data.graduation_year}</div>
        </div>
        <div class="info-card-modern">
            <div class="info-icon"><i class="fas fa-book-open"></i></div>
            <div class="info-label">Ngành đăng ký</div>
            <div class="info-value">${data.major_name} (${data.major_code})</div>
        </div>
        <div class="info-card-modern">
            <div class="info-icon"><i class="fas fa-layer-group"></i></div>
            <div class="info-label">Phương thức</div>
            <div class="info-value">${data.method_name}</div>
        </div>
        <div class="info-card-modern">
            <div class="info-icon"><i class="fas fa-puzzle-piece"></i></div>
            <div class="info-label">Tổ hợp môn</div>
            <div class="info-value">${data.combination_name || 'Không áp dụng'}</div>
        </div>
        <div class="info-card-modern">
            <div class="info-icon"><i class="fas fa-star"></i></div>
            <div class="info-label">Điểm ưu tiên</div>
            <div class="info-value">${data.priority_score || '0'}</div>
        </div>
        <div class="info-card-modern">
            <div class="info-icon"><i class="fas fa-clock"></i></div>
            <div class="info-label">Ngày đăng ký</div>
            <div class="info-value">${formatDate(data.created_at)}</div>
        </div>
    `;

        // Display scores
        if (data.scores && data.scores.length > 0) {
            displayScores(data.scores);
        }

        // Display admission result and confirmation
        displayAdmissionResult(data);

        // Display documents
        if (data.documents && data.documents.length > 0) {
            displayDocuments(data.documents);
        }

        // Display timeline
        if (data.timeline && data.timeline.length > 0) {
            displayTimeline(data.timeline);
        }
    }

    // Display scores
    function displayScores(scores) {
        const scoreSection = document.getElementById('scoreSection');
        const scoreGrid = document.getElementById('scoreGrid');

        scoreSection.style.display = 'block';

        let html = '';
        let total = 0;

        scores.forEach(score => {
            html += `
            <div class="score-item">
                <span class="score-subject">${score.subject}</span>
                <span class="score-value">${score.score}</span>
            </div>
        `;
            total += parseFloat(score.score);
        });

        html += `
        <div class="score-total">
            <span>Tổng điểm</span>
            <span>${total.toFixed(2)}</span>
        </div>
    `;

        scoreGrid.innerHTML = html;
    }

    // Display admission result and confirmation
    // Display admission result and confirmation
    function displayAdmissionResult(data) {
        const admissionResult = document.getElementById('admissionResult');

        if (!data.admission_result) {
            admissionResult.innerHTML = '';
            return;
        }

        const result = data.admission_result;
        console.log('Admission result:', result); // Debug

        // Nếu thí sinh đã trúng tuyển
        if (result.status === 'admitted') {
            // Kiểm tra xem đã có xác nhận chưa
            if (result.confirmation) {
                // Đã có xác nhận
                if (result.confirmation.status === 'confirmed') {
                    admissionResult.innerHTML = `
                    <div class="confirmed-box">
                        <i class="fas fa-check-circle"></i>
                        <h4>Đã xác nhận nhập học</h4>
                        <p>Bạn đã hoàn tất xác nhận nhập học lúc ${formatDateTime(result.confirmation.confirmed_at)}</p>
                        <p style="margin-top: 10px; font-size: 13px;">Vui lòng chờ hướng dẫn nhập học từ nhà trường.</p>
                    </div>
                `;
                } else if (result.confirmation.status === 'expired') {
                    admissionResult.innerHTML = `
                    <div class="expired-box">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h4>Hết hạn xác nhận</h4>
                        <p>Thời hạn xác nhận nhập học đã kết thúc.</p>
                        <p style="margin-top: 10px;">Vui lòng liên hệ phòng tuyển sinh để được hỗ trợ.</p>
                    </div>
                `;
                }
            } else {
                // Chưa có xác nhận - HIỂN THỊ NÚT XÁC NHẬN
                const expiryDate = new Date();
                expiryDate.setDate(expiryDate.getDate() + 7);

                admissionResult.innerHTML = `
                <div class="confirmation-box">
                    <h4>Xác nhận nhập học</h4>
                    <p>Bạn cần xác nhận nhập học trước thời hạn:</p>
                    <div class="countdown-timer" id="countdownDisplay"></div>
                    <div class="warning-box">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            Sau thời gian trên, nếu không xác nhận, kết quả trúng tuyển sẽ bị hủy bỏ
                        </div>
                    </div>
                    <button class="btn-confirm-admission" onclick="showConfirmModal()">
                        <i class="fas fa-check-circle"></i>
                        Xác nhận nhập học ngay
                    </button>
                </div>
            `;

                // Start countdown
                startCountdown(expiryDate);
            }
        }
        // Nếu thí sinh trượt
        else if (result.status === 'rejected'){
            admissionResult.innerHTML = `
            <div class="expired-box">
                <i class="fas fa-frown"></i>
                <h4>Rất tiếc</h4>
                <p>Điểm của bạn không đủ để trúng tuyển vào ngành đã chọn.</p>
                <p style="margin-top: 10px;">Vui lòng tham khảo các ngành đào tạo khác hoặc đợt xét tuyển tiếp theo.</p>
            </div>
        `;
        }
        // Nếu đang chờ kết quả
        else {
            admissionResult.innerHTML = `
            <div class="confirmation-box">
                <i class="fas fa-hourglass-half"></i>
                <h4>Đang chờ kết quả</h4>
                <p>Kết quả xét tuyển của bạn đang được xử lý.</p>
                <p style="margin-top: 10px;">Vui lòng quay lại sau để kiểm tra kết quả.</p>
            </div>
        `;
        }
    }

    // Start countdown
    function startCountdown(expiryDate) {
        if (countdownInterval) {
            clearInterval(countdownInterval);
        }

        const countdownDisplay = document.getElementById('countdownDisplay');

        countdownInterval = setInterval(function() {
            const now = new Date().getTime();
            const distance = expiryDate - now;

            if (distance < 0) {
                clearInterval(countdownInterval);
                countdownDisplay.innerHTML = 'ĐÃ HẾT HẠN';
                location.reload();
                return;
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            countdownDisplay.innerHTML =
                String(days).padStart(2, '0') + ':' +
                String(hours).padStart(2, '0') + ':' +
                String(minutes).padStart(2, '0') + ':' +
                String(seconds).padStart(2, '0');
        }, 1000);
    }

    // Show confirm modal
    function showConfirmModal() {
        if (!currentStudent) return;

        document.getElementById('confirmStudentName').textContent = currentStudent.fullname;
        document.getElementById('confirmStudentMajor').textContent =
            'Ngành: ' + currentStudent.major_name + ' (' + currentStudent.major_code + ')';
        document.getElementById('confirmStudentScore').textContent =
            'Tổng điểm: ' + (currentStudent.scores ?
                currentStudent.scores.reduce((sum, s) => sum + parseFloat(s.score), 0).toFixed(2) : 'Chưa có');

        // Start countdown in modal
        const expiryDate = new Date();
        expiryDate.setDate(expiryDate.getDate() + 7);
        startModalCountdown(expiryDate);

        document.getElementById('confirmModal').classList.add('show');
    }

    // Start countdown in modal
    function startModalCountdown(expiryDate) {
        const timerDisplay = document.getElementById('countdownTimer');

        const interval = setInterval(function() {
            const now = new Date().getTime();
            const distance = expiryDate - now;

            if (distance < 0) {
                clearInterval(interval);
                timerDisplay.innerHTML = 'ĐÃ HẾT HẠN';
                return;
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            timerDisplay.innerHTML =
                String(days).padStart(2, '0') + ' ngày ' +
                String(hours).padStart(2, '0') + ' giờ ' +
                String(minutes).padStart(2, '0') + ' phút ' +
                String(seconds).padStart(2, '0') + ' giây';
        }, 1000);
    }

    // Process confirmation
    async function processConfirmation() {
        if (!currentStudent) return;

        hideModal();

        // Show loading
        const loading = document.createElement('div');
        loading.className = 'search-loading active';
        loading.innerHTML = '<div class="spinner"></div><p>Đang xử lý xác nhận...</p>';
        document.body.appendChild(loading);

        try {
            const response = await fetch('api/xac-nhan-nhap-hoc.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    registration_id: currentStudent.id
                })
            });

            const data = await response.json();
            console.log('API Response:', data);
            // loading.remove();

            if (data.success) {
                alert('Xác nhận nhập học thành công! Bạn sẽ được chuyển đến trang xác nhận.');
                window.location.href = 'xac-nhan-thanh-cong.php?id=' + currentStudent.id;
            } else {
                alert('Có lỗi xảy ra: ' + data.message);
            }
        } catch (error) {
            loading.remove();
            console.error('Error:', error);
            alert('Có lỗi xảy ra, vui lòng thử lại sau');
        }
    }

    // Hide modal
    function hideModal() {
        document.getElementById('confirmModal').classList.remove('show');
    }

    // Display documents
    function displayDocuments(documents) {
        const documentsList = document.getElementById('documentsList');
        const documentsContainer = document.getElementById('documentsContainer');

        documentsList.style.display = 'block';

        let html = '';
        documents.forEach(doc => {
            const icon = doc.type === 'pdf' ? 'fa-file-pdf' : 'fa-file-image';
            html += `
            <div class="document-card">
                <div class="document-icon">
                    <i class="fas ${icon}"></i>
                </div>
                <div class="document-info">
                    <div class="document-name">${doc.name}</div>
                    <div class="document-size">${doc.size}</div>
                </div>
                <button class="btn-download" onclick="downloadFile('${doc.file}')">
                    <i class="fas fa-download"></i>
                </button>
            </div>
        `;
        });

        documentsContainer.innerHTML = html;
    }

    // Display timeline
    function displayTimeline(timeline) {
        const timelineEl = document.getElementById('timeline');
        const timelineContainer = document.getElementById('timelineContainer');

        timelineEl.style.display = 'block';

        let html = '';
        timeline.forEach(item => {
            html += `
            <div class="timeline-item-modern">
                <div class="timeline-date">
                    <i class="far fa-clock"></i> ${item.date}
                </div>
                <div class="timeline-content">
                    <h4>${item.title}</h4>
                    <p>${item.description}</p>
                </div>
            </div>
        `;
        });

        timelineContainer.innerHTML = html;
    }

    // Show error
    function showError(message) {
        const resultSection = document.getElementById('resultSection');
        resultSection.style.display = 'block';

        resultSection.innerHTML = `
        <div class="container">
            <div class="error-message-modern">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Không tìm thấy hồ sơ</h3>
                <p>${message || 'Vui lòng kiểm tra lại CCCD/CMND và ngày sinh'}</p>
                <button class="btn-retry" onclick="location.reload()">
                    <i class="fas fa-redo"></i> Thử lại
                </button>
            </div>
        </div>
    `;
    }

    // Helper functions
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.getDate().toString().padStart(2, '0') + '/' +
            (date.getMonth() + 1).toString().padStart(2, '0') + '/' +
            date.getFullYear();
    }

    function formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.getDate().toString().padStart(2, '0') + '/' +
            (date.getMonth() + 1).toString().padStart(2, '0') + '/' +
            date.getFullYear() + ' ' +
            date.getHours().toString().padStart(2, '0') + ':' +
            date.getMinutes().toString().padStart(2, '0');
    }

    function downloadFile(file) {
        const regId = document.getElementById('regId').textContent;
        window.location.href = `php/download.php?file=${file}&id=${regId}`;
    }

    function downloadProfile() {
        const regId = document.getElementById('regId').textContent.replace('#', '');
        window.open(`php/export-pdf.php?id=${regId}`, '_blank');
    }

    function contactAdmission() {
        const regId = document.getElementById('regId').textContent;
        window.location.href = 'lien-he.php?subject=Tra cứu hồ sơ - ' + regId;
    }
</script>

<?php require_once '../includes/footer.php'; ?>