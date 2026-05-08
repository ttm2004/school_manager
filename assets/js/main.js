// ===== LOADING SCREEN =====
(function () {
    // Tạo loading overlay ngay khi script chạy (trước DOMContentLoaded)
    const overlay = document.createElement('div');
    overlay.id = 'tdmu-loading';
    overlay.innerHTML = `
        <div class="tdmu-loading-inner">
            <div class="tdmu-logo-wrap">
                <i class="bi bi-mortarboard-fill"></i>
            </div>
            <div class="tdmu-loading-name">TDMU</div>
            <div class="tdmu-loading-sub">Trường Đại học Thủ Dầu Một</div>
            <div class="tdmu-spinner">
                <div class="tdmu-bar"></div>
                <div class="tdmu-bar"></div>
                <div class="tdmu-bar"></div>
                <div class="tdmu-bar"></div>
                <div class="tdmu-bar"></div>
            </div>
        </div>
    `;

    const style = document.createElement('style');
    style.textContent = `
        #tdmu-loading {
            position: fixed; inset: 0; z-index: 99999;
            background: #003087;
            display: flex; align-items: center; justify-content: center;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }
        #tdmu-loading.hide {
            opacity: 0; visibility: hidden; pointer-events: none;
        }
        .tdmu-loading-inner {
            text-align: center; color: #fff;
            animation: tdmuFadeIn 0.4s ease;
        }
        @keyframes tdmuFadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .tdmu-logo-wrap {
            width: 80px; height: 80px; border-radius: 20px;
            background: #FFB81C;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
            font-size: 2.5rem; color: #003087;
            box-shadow: 0 8px 30px rgba(255,184,28,0.4);
            animation: tdmuPulse 1.5s ease-in-out infinite;
        }
        @keyframes tdmuPulse {
            0%, 100% { transform: scale(1); box-shadow: 0 8px 30px rgba(255,184,28,0.4); }
            50%       { transform: scale(1.08); box-shadow: 0 12px 40px rgba(255,184,28,0.6); }
        }
        .tdmu-loading-name {
            font-size: 2.2rem; font-weight: 800; letter-spacing: 4px;
            color: #FFB81C; line-height: 1;
        }
        .tdmu-loading-sub {
            font-size: 0.85rem; color: rgba(255,255,255,0.7);
            margin-top: 6px; margin-bottom: 28px; letter-spacing: 0.5px;
        }
        .tdmu-spinner {
            display: flex; gap: 6px; justify-content: center; align-items: flex-end;
            height: 36px;
        }
        .tdmu-bar {
            width: 6px; border-radius: 3px;
            background: #FFB81C; opacity: 0.85;
            animation: tdmuBounce 1s ease-in-out infinite;
        }
        .tdmu-bar:nth-child(1) { animation-delay: 0s;    height: 16px; }
        .tdmu-bar:nth-child(2) { animation-delay: 0.15s; height: 24px; }
        .tdmu-bar:nth-child(3) { animation-delay: 0.3s;  height: 32px; }
        .tdmu-bar:nth-child(4) { animation-delay: 0.15s; height: 24px; }
        .tdmu-bar:nth-child(5) { animation-delay: 0s;    height: 16px; }
        @keyframes tdmuBounce {
            0%, 100% { transform: scaleY(0.5); opacity: 0.5; }
            50%       { transform: scaleY(1);   opacity: 1; }
        }
    `;

    document.head.appendChild(style);
    // Chèn vào body ngay khi có thể
    if (document.body) {
        document.body.appendChild(overlay);
    } else {
        document.addEventListener('DOMContentLoaded', function () {
            document.body.appendChild(overlay);
        });
    }

    // Ẩn loading khi trang load xong
    window.addEventListener('load', function () {
        setTimeout(function () {
            overlay.classList.add('hide');
            setTimeout(function () { overlay.remove(); }, 500);
        }, 400); // Tối thiểu 400ms để thấy animation
    });

    // Fallback: ẩn sau 3s dù trang chưa load xong
    setTimeout(function () {
        overlay.classList.add('hide');
        setTimeout(function () { if (overlay.parentNode) overlay.remove(); }, 500);
    }, 3000);
})();

// ===== TOAST NOTIFICATION SYSTEM =====
// Tự động convert tất cả .alert thành toast nổi góc phải màn hình
(function () {
    // Inject toast container + styles một lần
    function initToastSystem() {
        if (document.getElementById('tdmu-toast-container')) return;

        // Container
        const container = document.createElement('div');
        container.id = 'tdmu-toast-container';
        document.body.appendChild(container);

        // Styles
        const style = document.createElement('style');
        style.textContent = `
            #tdmu-toast-container {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 99998;
                display: flex;
                flex-direction: column;
                gap: 10px;
                max-width: 420px;
                width: calc(100vw - 40px);
            }
            .tdmu-toast {
                background: #fff;
                border-radius: 12px;
                box-shadow: 0 8px 32px rgba(0,0,0,0.18);
                padding: 0;
                overflow: hidden;
                display: flex;
                align-items: stretch;
                animation: tdmuToastIn 0.35s cubic-bezier(0.34,1.56,0.64,1);
                border: 1px solid rgba(0,0,0,0.08);
            }
            .tdmu-toast.hiding {
                animation: tdmuToastOut 0.3s ease forwards;
            }
            @keyframes tdmuToastIn {
                from { opacity:0; transform: translateX(60px) scale(0.95); }
                to   { opacity:1; transform: translateX(0) scale(1); }
            }
            @keyframes tdmuToastOut {
                from { opacity:1; transform: translateX(0) scale(1); max-height:200px; margin-bottom:0; }
                to   { opacity:0; transform: translateX(60px) scale(0.95); max-height:0; margin-bottom:-10px; }
            }
            .tdmu-toast-bar {
                width: 5px;
                flex-shrink: 0;
                border-radius: 12px 0 0 12px;
            }
            .tdmu-toast-body {
                flex: 1;
                padding: 14px 16px;
                display: flex;
                align-items: flex-start;
                gap: 12px;
            }
            .tdmu-toast-icon {
                font-size: 1.2rem;
                flex-shrink: 0;
                margin-top: 1px;
            }
            .tdmu-toast-content { flex: 1; }
            .tdmu-toast-text {
                font-size: 0.875rem;
                color: #1a2340;
                line-height: 1.5;
                margin: 0;
            }
            .tdmu-toast-close {
                background: none;
                border: none;
                color: #9ca3af;
                cursor: pointer;
                padding: 4px;
                border-radius: 6px;
                font-size: 0.9rem;
                flex-shrink: 0;
                transition: color 0.2s, background 0.2s;
                align-self: flex-start;
            }
            .tdmu-toast-close:hover { color: #374151; background: #f3f4f6; }
            .tdmu-toast-progress {
                height: 3px;
                background: rgba(0,0,0,0.08);
                position: relative;
                overflow: hidden;
            }
            .tdmu-toast-progress-bar {
                position: absolute;
                left: 0; top: 0; bottom: 0;
                transition: width linear;
                width: 100%;
            }
            /* Types */
            .tdmu-toast.success .tdmu-toast-bar { background: #10b981; }
            .tdmu-toast.success .tdmu-toast-icon { color: #10b981; }
            .tdmu-toast.success .tdmu-toast-progress-bar { background: #10b981; }
            .tdmu-toast.danger .tdmu-toast-bar { background: #ef4444; }
            .tdmu-toast.danger .tdmu-toast-icon { color: #ef4444; }
            .tdmu-toast.danger .tdmu-toast-progress-bar { background: #ef4444; }
            .tdmu-toast.warning .tdmu-toast-bar { background: #f59e0b; }
            .tdmu-toast.warning .tdmu-toast-icon { color: #f59e0b; }
            .tdmu-toast.warning .tdmu-toast-progress-bar { background: #f59e0b; }
            .tdmu-toast.info .tdmu-toast-bar { background: #3b82f6; }
            .tdmu-toast.info .tdmu-toast-icon { color: #3b82f6; }
            .tdmu-toast.info .tdmu-toast-progress-bar { background: #3b82f6; }
            .tdmu-toast.secondary .tdmu-toast-bar { background: #6b7280; }
            .tdmu-toast.secondary .tdmu-toast-icon { color: #6b7280; }
            .tdmu-toast.secondary .tdmu-toast-progress-bar { background: #6b7280; }
        `;
        document.head.appendChild(style);
    }

    // Map Bootstrap alert class → toast type
    function getToastType(alertEl) {
        if (alertEl.classList.contains('alert-success'))   return 'success';
        if (alertEl.classList.contains('alert-danger'))    return 'danger';
        if (alertEl.classList.contains('alert-warning'))   return 'warning';
        if (alertEl.classList.contains('alert-info'))      return 'info';
        if (alertEl.classList.contains('alert-secondary')) return 'secondary';
        return 'info';
    }

    const typeIcons = {
        success:   'bi-check-circle-fill',
        danger:    'bi-exclamation-circle-fill',
        warning:   'bi-exclamation-triangle-fill',
        info:      'bi-info-circle-fill',
        secondary: 'bi-bell-fill',
    };

    // Tính thời gian tự đóng dựa trên độ dài text (5s-10s)
    function calcDuration(text) {
        const words = text.trim().split(/\s+/).length;
        const ms = Math.min(10000, Math.max(5000, words * 400));
        return ms;
    }

    // Tạo và hiển thị 1 toast
    function showToast(type, html, duration) {
        initToastSystem();
        const container = document.getElementById('tdmu-toast-container');

        // Strip HTML tags để tính độ dài
        const plainText = html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
        const ms = duration || calcDuration(plainText);

        const toast = document.createElement('div');
        toast.className = `tdmu-toast ${type}`;
        toast.innerHTML = `
            <div class="tdmu-toast-bar"></div>
            <div style="flex:1;display:flex;flex-direction:column;">
                <div class="tdmu-toast-body">
                    <i class="bi ${typeIcons[type] || 'bi-info-circle-fill'} tdmu-toast-icon"></i>
                    <p class="tdmu-toast-text">${html}</p>
                    <button class="tdmu-toast-close" title="Đóng"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="tdmu-toast-progress">
                    <div class="tdmu-toast-progress-bar" style="width:100%;transition-duration:${ms}ms;"></div>
                </div>
            </div>
        `;

        container.appendChild(toast);

        // Nút đóng
        toast.querySelector('.tdmu-toast-close').addEventListener('click', () => closeToast(toast));

        // Bắt đầu progress bar
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                const bar = toast.querySelector('.tdmu-toast-progress-bar');
                if (bar) bar.style.width = '0%';
            });
        });

        // Auto close
        const timer = setTimeout(() => closeToast(toast), ms);
        toast._timer = timer;

        // Pause on hover
        toast.addEventListener('mouseenter', () => {
            clearTimeout(toast._timer);
            const bar = toast.querySelector('.tdmu-toast-progress-bar');
            if (bar) { bar.style.transitionDuration = '0ms'; }
        });
        toast.addEventListener('mouseleave', () => {
            const remaining = 2000;
            const bar = toast.querySelector('.tdmu-toast-progress-bar');
            if (bar) { bar.style.transitionDuration = remaining + 'ms'; bar.style.width = '0%'; }
            toast._timer = setTimeout(() => closeToast(toast), remaining);
        });

        return toast;
    }

    function closeToast(toast) {
        if (!toast || toast._closing) return;
        toast._closing = true;
        clearTimeout(toast._timer);
        toast.classList.add('hiding');
        setTimeout(() => { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 320);
    }

    // Expose globally
    window.tdmuToast = {
        success: (msg, ms) => showToast('success', msg, ms),
        error:   (msg, ms) => showToast('danger',  msg, ms),
        danger:  (msg, ms) => showToast('danger',  msg, ms),
        warning: (msg, ms) => showToast('warning', msg, ms),
        info:    (msg, ms) => showToast('info',    msg, ms),
        show:    showToast,
    };

    // Auto-convert tất cả .alert trong DOM thành toast
    function convertAlertsToToasts() {
        // Chỉ convert các alert thông báo (success, danger, warning, info)
        // KHÔNG convert các alert dạng banner cố định (alert-danger không có btn-close, banner trạng thái)
        const alerts = document.querySelectorAll(
            '.alert.alert-success, .alert.alert-danger.auto-dismiss, .alert.alert-warning.auto-dismiss, .alert.alert-info.auto-dismiss'
        );

        alerts.forEach(function (alert) {
            // Bỏ qua alert không có nội dung hoặc là banner cố định (không có auto-dismiss và không có btn-close)
            const hasBtnClose = alert.querySelector('.btn-close');
            const isAutoDismiss = alert.classList.contains('auto-dismiss');
            const isSuccess = alert.classList.contains('alert-success');

            // Convert: success luôn convert, các loại khác chỉ convert nếu có auto-dismiss hoặc btn-close
            if (!isSuccess && !isAutoDismiss && !hasBtnClose) return;

            const type = getToastType(alert);

            // Lấy nội dung (bỏ btn-close)
            const clone = alert.cloneNode(true);
            const closeBtn = clone.querySelector('.btn-close, button[data-bs-dismiss]');
            if (closeBtn) closeBtn.remove();
            // Bỏ icon Bootstrap Icons đầu tiên nếu có (đã có icon trong toast)
            const firstIcon = clone.querySelector('i.bi:first-child');
            if (firstIcon && firstIcon === clone.firstElementChild) firstIcon.remove();

            const html = clone.innerHTML.trim();
            if (!html) return;

            // Ẩn alert gốc
            alert.style.display = 'none';

            // Hiện toast
            showToast(type, html);
        });
    }

    // Chạy sau khi DOM ready
    document.addEventListener('DOMContentLoaded', convertAlertsToToasts);

    // Cũng chạy ngay nếu DOM đã ready
    if (document.readyState !== 'loading') {
        setTimeout(convertAlertsToToasts, 100);
    }
})();


document.addEventListener('DOMContentLoaded', function () {
    // Auto dismiss alerts after 4 seconds (fallback cho alert không được convert)
    const alerts = document.querySelectorAll('.alert.auto-dismiss');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            try {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                bsAlert.close();
            } catch(e) { if (alert.parentNode) alert.style.display = 'none'; }
        }, 5000);
    });

    // ===== CONFIRM DELETE =====
    document.querySelectorAll('.btn-delete, [data-confirm]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            const msg = this.getAttribute('data-confirm') || 'Bạn có chắc chắn muốn xóa không? Hành động này không thể hoàn tác.';
            if (!confirm(msg)) {
                e.preventDefault();
                return false;
            }
        });
    });

    // ===== FORM VALIDATION =====
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // ===== ADMIN SIDEBAR TOGGLE =====
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.admin-sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('show');
        });
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function (e) {
            if (window.innerWidth < 992) {
                if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });
    }

    // ===== SCORE AUTO CALCULATE =====
    function calculateGrade() {
        const process = parseFloat(document.getElementById('process_score')?.value) || 0;
        const midterm = parseFloat(document.getElementById('midterm_score')?.value) || 0;
        const final = parseFloat(document.getElementById('final_score')?.value) || 0;

        const total = process * 0.2 + midterm * 0.3 + final * 0.5;
        const totalField = document.getElementById('total_score');
        const letterField = document.getElementById('letter_grade');

        if (totalField) {
            totalField.value = total.toFixed(2);
        }
        if (letterField) {
            let letter = 'F';
            if (total >= 8.5) letter = 'A';
            else if (total >= 8.0) letter = 'B+';
            else if (total >= 7.0) letter = 'B';
            else if (total >= 6.0) letter = 'C+';
            else if (total >= 5.0) letter = 'C';
            else if (total >= 4.0) letter = 'D+';
            else if (total >= 3.5) letter = 'D';
            letterField.value = letter;
        }
    }

    ['process_score', 'midterm_score', 'final_score'].forEach(function (id) {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', calculateGrade);
        }
    });

    // ===== SCORE INPUTS IN GRADE TABLE =====
    document.querySelectorAll('.score-input').forEach(function (input) {
        input.addEventListener('input', function () {
            const row = this.closest('tr');
            if (!row) return;
            const p = parseFloat(row.querySelector('.process-input')?.value) || 0;
            const m = parseFloat(row.querySelector('.midterm-input')?.value) || 0;
            const f = parseFloat(row.querySelector('.final-input')?.value) || 0;
            const total = p * 0.2 + m * 0.3 + f * 0.5;
            const totalCell = row.querySelector('.total-display');
            const letterCell = row.querySelector('.letter-display');
            if (totalCell) totalCell.textContent = total.toFixed(2);
            if (letterCell) {
                let letter = 'F';
                if (total >= 8.5) letter = 'A';
                else if (total >= 8.0) letter = 'B+';
                else if (total >= 7.0) letter = 'B';
                else if (total >= 6.0) letter = 'C+';
                else if (total >= 5.0) letter = 'C';
                else if (total >= 4.0) letter = 'D+';
                else if (total >= 3.5) letter = 'D';
                letterCell.textContent = letter;
            }
        });
    });

    // ===== SMOOTH SCROLL =====
    document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
        anchor.addEventListener('click', function (e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // ===== BACK TO TOP =====
    const backToTop = document.getElementById('backToTop');
    if (backToTop) {
        window.addEventListener('scroll', function () {
            if (window.scrollY > 300) {
                backToTop.style.display = 'flex';
            } else {
                backToTop.style.display = 'none';
            }
        });
        backToTop.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // ===== SEARCH FILTER TABLE =====
    const searchInput = document.getElementById('tableSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function () {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('#dataTable tbody tr');
            rows.forEach(function (row) {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    }
});

// ===== UTILITY FUNCTIONS =====
function confirmDelete(url, msg) {
    msg = msg || 'Bạn có chắc chắn muốn xóa không?';
    if (confirm(msg)) {
        window.location.href = url;
    }
}

function showModal(id) {
    const modal = new bootstrap.Modal(document.getElementById(id));
    modal.show();
}

function hideModal(id) {
    const modal = bootstrap.Modal.getInstance(document.getElementById(id));
    if (modal) modal.hide();
}

// ===== MODAL CONFIRM - Override window.confirm =====
// Thay thế tất cả confirm() trong toàn project bằng modal Bootstrap đẹp
(function () {
    // Tạo modal HTML một lần duy nhất
    function createConfirmModal() {
        if (document.getElementById('tdmuConfirmModal')) return;
        const html = `
        <div class="modal fade" id="tdmuConfirmModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header border-0 pb-0">
                        <div class="d-flex align-items-center gap-2">
                            <div id="tdmuConfirmIcon" style="width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;background:#fff3cd;">
                                <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                            </div>
                            <h5 class="modal-title fw-bold mb-0" id="tdmuConfirmTitle">Xác nhận</h5>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body pt-2 pb-3">
                        <p class="mb-0 text-muted" id="tdmuConfirmMessage" style="padding-left:52px;"></p>
                    </div>
                    <div class="modal-footer border-0 pt-0 gap-2">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" id="tdmuConfirmCancel">
                            <i class="bi bi-x-lg me-1"></i>Hủy
                        </button>
                        <button type="button" class="btn px-4" id="tdmuConfirmOk">
                            <i class="bi bi-check-lg me-1"></i>Xác nhận
                        </button>
                    </div>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', html);
    }

    // Hàm hiện modal confirm, trả về Promise<boolean>
    function showConfirm(message, options) {
        options = options || {};
        createConfirmModal();

        const modal    = document.getElementById('tdmuConfirmModal');
        const msgEl    = document.getElementById('tdmuConfirmMessage');
        const titleEl  = document.getElementById('tdmuConfirmTitle');
        const iconEl   = document.getElementById('tdmuConfirmIcon');
        const okBtn    = document.getElementById('tdmuConfirmOk');
        const bsModal  = new bootstrap.Modal(modal, { backdrop: 'static', keyboard: false });

        // Xác định loại (danger/warning)
        const isDanger = options.type === 'danger' ||
            /xóa|xoa|delete|remove|hủy|cancel/i.test(message);

        titleEl.textContent = options.title || (isDanger ? 'Xác nhận xóa' : 'Xác nhận');
        msgEl.textContent   = message;

        if (isDanger) {
            iconEl.style.background = '#f8d7da';
            iconEl.innerHTML = '<i class="bi bi-trash-fill text-danger"></i>';
            okBtn.className  = 'btn btn-danger px-4';
            okBtn.innerHTML  = '<i class="bi bi-trash-fill me-1"></i>Xóa';
        } else {
            iconEl.style.background = '#fff3cd';
            iconEl.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-warning"></i>';
            okBtn.className  = 'btn btn-warning px-4';
            okBtn.innerHTML  = '<i class="bi bi-check-lg me-1"></i>Xác nhận';
        }

        return new Promise(function (resolve) {
            // Clone để xóa event cũ
            const newOk = okBtn.cloneNode(true);
            okBtn.parentNode.replaceChild(newOk, okBtn);

            newOk.addEventListener('click', function () {
                bsModal.hide();
                resolve(true);
            });

            modal.addEventListener('hidden.bs.modal', function handler() {
                modal.removeEventListener('hidden.bs.modal', handler);
                resolve(false);
            });

            bsModal.show();
        });
    }

    // Override window.confirm — intercept tất cả form onsubmit="return confirm(...)"
    const _nativeConfirm = window.confirm.bind(window);
    window.confirm = function (message) {
        // Nếu Bootstrap chưa load, dùng native
        if (typeof bootstrap === 'undefined') return _nativeConfirm(message);

        // Tìm form đang submit (nếu có)
        const activeForm = window._tdmuPendingForm || null;
        if (!activeForm) return _nativeConfirm(message);

        // Ngăn submit ngay, hiện modal, rồi submit lại nếu OK
        showConfirm(message).then(function (ok) {
            if (ok) {
                window._tdmuPendingForm = null;
                activeForm.removeAttribute('data-tdmu-intercepted');
                activeForm.submit();
            }
            window._tdmuPendingForm = null;
        });

        return false; // Luôn ngăn submit ngay
    };

    // Intercept tất cả form có onsubmit chứa confirm()
    document.addEventListener('DOMContentLoaded', function () {
        document.addEventListener('submit', function (e) {
            const form = e.target;
            const onsubmitAttr = form.getAttribute('onsubmit') || '';
            if (onsubmitAttr.includes('confirm(') && !form.dataset.tdmuIntercepted) {
                form.dataset.tdmuIntercepted = '1';
                window._tdmuPendingForm = form;
            }
        }, true); // capture phase — chạy trước onsubmit
    });

    // Expose để dùng thủ công
    window.tdmuConfirm = showConfirm;
})();
