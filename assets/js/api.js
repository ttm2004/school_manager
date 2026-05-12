/**
 * TDMU API Client — fetch wrapper
 * Dùng trong tất cả trang PHP của hệ thống
 *
 * Usage:
 *   const semesters = await api.get('semesters');
 *   const result    = await api.post('grades', { student_subject_id: 1, final_score: 8.5 });
 *   await api.put('course-sections/5', { status: 'open' });
 */

const api = (() => {
    const BASE = '/university/api';

    // Lấy CSRF token từ meta tag
    const getCsrf = () =>
        document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    async function request(method, endpoint, data = null) {
        const url = `${BASE}/${endpoint}`;
        const opts = {
            method,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': getCsrf(),
            },
        };
        if (data && ['POST','PUT','PATCH','DELETE'].includes(method)) {
            opts.body = JSON.stringify(data);
        }

        let res;
        try {
            res = await fetch(url, opts);
        } catch (err) {
            throw new Error('Lỗi kết nối mạng: ' + err.message);
        }

        const json = await res.json().catch(() => ({ success: false, message: 'Phản hồi không hợp lệ' }));

        if (!res.ok || !json.success) {
            const err = new Error(json.message ?? `HTTP ${res.status}`);
            err.status  = res.status;
            err.errors  = json.errors ?? null;
            err.data    = json;
            throw err;
        }
        return json.data;
    }

    return {
        get:    (ep, params = {}) => {
            const qs = new URLSearchParams(params).toString();
            return request('GET', qs ? `${ep}?${qs}` : ep);
        },
        post:   (ep, data)  => request('POST',   ep, data),
        put:    (ep, data)  => request('PUT',     ep, data),
        patch:  (ep, data)  => request('PATCH',   ep, data),
        delete: (ep, data)  => request('DELETE',  ep, data),

        // ── Shortcuts ──────────────────────────────────────────

        // Auth
        me:           ()     => request('GET', 'auth/me'),
        login:        (u, p) => request('POST', 'auth/login', { username: u, password: p }),
        logout:       ()     => request('POST', 'auth/logout'),

        // Semesters
        semesters:    (p)    => request('GET', 'semesters' + (p ? '?' + new URLSearchParams(p) : '')),
        activeSem:    ()     => request('GET', 'semesters/active'),

        // Course sections
        sections:     (p)    => request('GET', 'course-sections?' + new URLSearchParams(p)),
        section:      (id)   => request('GET', `course-sections/${id}`),
        sectionStudents: (id) => request('GET', `course-sections/${id}/students`),
        proposeSection: (id, data) => request('POST', `course-sections/${id}/propose`, data),
        approveSection: (id, data) => request('POST', `course-sections/${id}/approve`, data),
        rejectSection:  (id, data) => request('POST', `course-sections/${id}/reject`, data),

        // Grades
        gradesBySection: (sectionId) => request('GET', `grades?section_id=${sectionId}`),
        gradesByStudent: (studentId) => request('GET', `grades?student_id=${studentId}`),
        saveGrade:       (data)      => request('POST', 'grades', data),
        saveBatchGrades: (grades)    => request('POST', 'grades/batch', { grades }),

        // Students
        students:     (p)    => request('GET', 'students?' + new URLSearchParams(p)),
        student:      (id)   => request('GET', `students/${id}`),
        myProfile:    ()     => request('GET', 'students/me'),
        registerSubject: (studentId, sectionId) =>
            request('POST', `students/${studentId}/register`, { course_section_id: sectionId }),

        // Teachers
        teachers:     (p)    => request('GET', 'teachers?' + new URLSearchParams(p)),
        myTeacher:    ()     => request('GET', 'teachers/me'),
        teacherCourses: (id, semId) => request('GET', `teachers/${id}/courses?semester_id=${semId ?? ''}`),
        teacherWorkload: (id, semId) => request('GET', `teachers/${id}/workload?semester_id=${semId ?? ''}`),

        // Exam schedules
        exams:        (p)    => request('GET', 'exam-schedules?' + new URLSearchParams(p)),
        createExam:   (data) => request('POST', 'exam-schedules', data),

        // Notifications
        notifications: (p)   => request('GET', 'notifications?' + new URLSearchParams(p ?? {})),

        // Reports
        dashboard:    (semId) => request('GET', `reports/dashboard?semester_id=${semId ?? ''}`),
        gradeStats:   (p)     => request('GET', 'reports/grade-stats?' + new URLSearchParams(p)),
        studentsByFaculty: () => request('GET', 'reports/students-by-faculty'),
    };
})();

// ── Toast helper (dùng với Bootstrap 5) ──────────────────────
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer') ??
        (() => {
            const el = document.createElement('div');
            el.id = 'toastContainer';
            el.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            el.style.zIndex = '9999';
            document.body.appendChild(el);
            return el;
        })();

    const id   = 'toast_' + Date.now();
    const icon = type === 'success' ? 'check-circle-fill' : type === 'danger' ? 'exclamation-circle-fill' : 'info-circle-fill';
    container.insertAdjacentHTML('beforeend', `
        <div id="${id}" class="toast align-items-center text-bg-${type} border-0" role="alert" aria-live="assertive">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-${icon} me-2"></i>${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `);
    const toastEl = document.getElementById(id);
    const toast   = new bootstrap.Toast(toastEl, { delay: 3500 });
    toast.show();
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
}

// ── Form submit → fetch helper ────────────────────────────────
/**
 * Chuyển form submit thành fetch API call
 * Usage: <form data-api="grades" data-method="POST">
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-api]').forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const endpoint = form.dataset.api;
            const method   = (form.dataset.method ?? 'POST').toUpperCase();
            const btn      = form.querySelector('[type=submit]');
            const origText = btn?.innerHTML;

            if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang xử lý...'; }

            const data = Object.fromEntries(new FormData(form));

            try {
                const result = await api[method.toLowerCase()](endpoint, data);
                showToast(form.dataset.successMsg ?? 'Thành công!', 'success');
                form.dispatchEvent(new CustomEvent('api:success', { detail: result }));
                if (form.dataset.resetOnSuccess !== undefined) form.reset();
                if (form.dataset.redirectOnSuccess) window.location.href = form.dataset.redirectOnSuccess;
            } catch (err) {
                showToast(err.message, 'danger');
                form.dispatchEvent(new CustomEvent('api:error', { detail: err }));
            } finally {
                if (btn) { btn.disabled = false; btn.innerHTML = origText; }
            }
        });
    });
});
