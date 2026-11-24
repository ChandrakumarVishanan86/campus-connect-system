/* Global "Create post" modal */
(function () {
    'use strict';
    var el = document.getElementById('composeModal'); if (!el) return;
    var m = new bootstrap.Modal(el), f = document.getElementById('composeForm'), body = document.getElementById('cpBody'), cnt = document.getElementById('cpCount'), err = document.getElementById('cpErr'), go = document.getElementById('cpGo');
    function sync() { document.getElementById('cpTitleWrap').classList.toggle('d-none', f.type.value !== 'Announcement'); }
    window.openCompose = function (opts) {
        opts = opts || {}; f.reset(); err.textContent = ''; cnt.textContent = '0 / ' + APP.limits.post;
        if (opts.type) f.type.value = opts.type; if (opts.club) f.club_id.value = opts.club; sync(); m.show(); setTimeout(function () { body.focus(); }, 300);
    };
    f.type.addEventListener('change', sync);
    body.addEventListener('input', function () { cnt.textContent = body.value.length + ' / ' + APP.limits.post; });
    var btn = document.getElementById('btnCompose'); if (btn) btn.addEventListener('click', function () { window.openCompose(); });
    f.addEventListener('submit', function (e) {
        e.preventDefault(); err.textContent = '';
        if (body.value.trim().length < 3) { err.textContent = 'Write at least a few words.'; return; }
        if (f.type.value === 'Announcement' && f.title.value.trim().length < 3) { err.textContent = 'Announcements need a title.'; return; }
        go.disabled = true;
        var d = {}; new FormData(f).forEach(function (v, k) { d[k] = v; });
        SMS.api(APP.base + '/feed/api', { method: 'POST', body: Object.assign({ action: 'create' }, d) }).then(function (r) {
            go.disabled = false;
            if (!r.success) { err.textContent = r.message; return; }
            m.hide();
            if (window.onPostCreated) { SMS.toast(r.message, 'success'); window.onPostCreated(r); }
            else SMS.reloadWith(r.message, 'success');
        });
    });
})();
