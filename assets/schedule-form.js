// Trang phân lịch: kiểm tra trực tiếp + gợi ý giảng viên + gợi ý tiết trống
(function () {
    const $ = (id) => document.getElementById(id);
    const fields = ['subject_name', 'branch_id', 'room', 'day_of_week', 'p_start', 'p_end', 'teacher_id'];
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function params(extra = {}) {
        const p = new URLSearchParams();
        fields.forEach((f) => p.set(f, $(f).value));
        p.set('exclude_id', $('exclude_id').value);
        Object.entries(extra).forEach(([k, v]) => p.set(k, v));
        return p;
    }

    async function api(action, extra) {
        const res = await fetch('api/suggest.php?' + params({ action, ...extra }));
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Lỗi máy chủ');
        return data;
    }

    // 1. Kiểm tra trực tiếp khi thay đổi dữ liệu
    let timer;
    async function liveCheck() {
        const box = $('liveCheck');
        try {
            const r = await api('check');
            if (r.incomplete) { box.innerHTML = ''; return; }
            let html = '';
            r.errors.forEach((m) => (html += `<div class="text-danger"><i class="bi bi-x-octagon"></i> ${esc(m)}</div>`));
            r.warnings.forEach((m) => (html += `<div class="text-warning-emphasis"><i class="bi bi-exclamation-triangle"></i> ${esc(m)}</div>`));
            if (!html) html = '<div class="text-success"><i class="bi bi-check-circle"></i> Không trùng lịch, không trùng phòng, đủ thời gian di chuyển.</div>';
            box.innerHTML = html;
        } catch (e) { box.innerHTML = `<div class="text-danger">${esc(e.message)}</div>`; }
    }
    fields.forEach((f) => $(f).addEventListener('change', () => { clearTimeout(timer); timer = setTimeout(liveCheck, 250); }));
    $('room').addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(liveCheck, 500); });

    // Giữ "Đến tiết" >= "Từ tiết"
    $('p_start').addEventListener('change', () => {
        if (+$('p_end').value < +$('p_start').value) $('p_end').value = $('p_start').value;
    });
    document.querySelectorAll('.quick').forEach((b) => b.addEventListener('click', () => {
        $('p_start').value = b.dataset.s; $('p_end').value = b.dataset.e; liveCheck();
    }));

    // 2. Gợi ý giảng viên
    $('btnSuggestTeachers').addEventListener('click', async () => {
        const box = $('teacherSuggest');
        box.innerHTML = '<div class="text-muted">Đang phân tích...</div>';
        try {
            const { items } = await api('teachers');
            const ok = items.filter((t) => t.available && t.match >= 0.5);
            const other = items.filter((t) => t.available && t.match < 0.5);
            const busy = items.filter((t) => !t.available && t.match >= 0.5);
            let html = '';
            const row = (t, cls = '') => `
                <div class="list-group-item suggest-item ${cls}">
                  <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                      <div class="fw-semibold">${esc(t.full_name)} <span class="badge text-bg-light">${Math.round(t.match * 100)}% chuyên môn</span></div>
                      <div class="text-muted">${esc(t.expertise)}</div>
                      <div>${t.reasons.map((r) => `<span class="badge rounded-pill text-bg-info-subtle text-info-emphasis me-1">${esc(r)}</span>`).join('')}</div>
                      ${t.warnings.map((w) => `<div class="text-warning-emphasis"><i class="bi bi-exclamation-triangle"></i> ${esc(w)}</div>`).join('')}
                      ${t.errors.map((w) => `<div class="text-danger"><i class="bi bi-x-octagon"></i> ${esc(w)}</div>`).join('')}
                    </div>
                    <div class="text-end">
                      <span class="badge text-bg-primary score-badge mb-1">${t.score}</span><br>
                      ${t.available ? `<button type="button" class="btn btn-sm btn-outline-primary pick" data-id="${t.id}">Chọn</button>` : ''}
                    </div>
                  </div>
                </div>`;
            html += `<div class="fw-semibold mb-1 text-success">✔ Phù hợp chuyên môn & đang rảnh (${ok.length})</div>`;
            html += ok.length ? `<div class="list-group mb-3">${ok.map((t) => row(t)).join('')}</div>` : '<div class="text-muted mb-3">Không có giảng viên đúng chuyên môn rảnh trong khung giờ này — thử đổi tiết/thứ.</div>';
            if (busy.length) html += `<details class="mb-2"><summary class="text-danger">Đúng chuyên môn nhưng bị trùng/không kịp di chuyển (${busy.length})</summary><div class="list-group mt-2">${busy.map((t) => row(t, 'unavailable')).join('')}</div></details>`;
            if (other.length) html += `<details><summary class="text-muted">Giảng viên rảnh khác chuyên môn (${other.length})</summary><div class="list-group mt-2">${other.map((t) => row(t)).join('')}</div></details>`;
            box.innerHTML = html;
            box.querySelectorAll('.pick').forEach((b) => b.addEventListener('click', () => { $('teacher_id').value = b.dataset.id; liveCheck(); }));
        } catch (e) { box.innerHTML = `<div class="text-danger">${esc(e.message)}</div>`; }
    });

    // 3. Gợi ý tiết trống
    $('btnSuggestSlots').addEventListener('click', async () => {
        const box = $('slotSuggest');
        box.innerHTML = '<div class="text-muted">Đang tìm tiết trống...</div>';
        try {
            const { items } = await api('slots', { length: $('slotLength').value });
            if (!items.length) { box.innerHTML = '<div class="text-muted">Không còn khung giờ phù hợp trong tuần.</div>'; return; }
            box.innerHTML = '<div class="list-group">' + items.map((s) => `
                <div class="list-group-item d-flex justify-content-between align-items-center gap-2">
                  <div>
                    <div class="fw-semibold">${esc(s.label)} · ${esc(s.session)}</div>
                    <div>${s.reasons.map((r) => `<span class="badge rounded-pill text-bg-success-subtle text-success-emphasis me-1">${esc(r)}</span>`).join('')}</div>
                    ${s.warnings.map((w) => `<div class="text-warning-emphasis"><i class="bi bi-exclamation-triangle"></i> ${esc(w)}</div>`).join('')}
                  </div>
                  <div class="text-end"><span class="badge text-bg-success score-badge mb-1">${s.score}</span><br>
                  <button type="button" class="btn btn-sm btn-outline-success use" data-d="${s.day_of_week}" data-s="${s.p_start}" data-e="${s.p_end}">Dùng</button></div>
                </div>`).join('') + '</div>';
            box.querySelectorAll('.use').forEach((b) => b.addEventListener('click', () => {
                $('day_of_week').value = b.dataset.d; $('p_start').value = b.dataset.s; $('p_end').value = b.dataset.e; liveCheck();
            }));
        } catch (e) { box.innerHTML = `<div class="text-danger">${esc(e.message)}</div>`; }
    });

    liveCheck();
})();
