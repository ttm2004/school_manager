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

// ===== AUTO DISMISS ALERTS =====
document.addEventListener('DOMContentLoaded', function () {
    // Auto dismiss alerts after 4 seconds
    const alerts = document.querySelectorAll('.alert.auto-dismiss');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 4000);
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
