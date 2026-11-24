(function () {
    'use strict';
    var API = APP.base + '/profile/api', f = document.getElementById('pfForm'), pw = document.getElementById('pwForm');
    document.getElementById('pfColors').addEventListener('click', function (e) { var b = e.target.closest('.sw'); if (!b) return; this.querySelectorAll('.sw').forEach(function (x) { x.classList.toggle('on', x === b); }); document.getElementById('pfColor').value = b.dataset.c; });
    f.addEventListener('submit', function (e) {
        e.preventDefault(); var b = {}; new FormData(f).forEach(function (v, k) { b[k] = v; }); b.action = 'update';
        SMS.api(API, { method: 'POST', body: b }).then(function (r) { document.getElementById('pfErr').textContent = r.success ? '' : r.message; SMS.toast(r.message, r.success ? 'success' : 'danger'); });
    });
    pw.addEventListener('submit', function (e) {
        e.preventDefault();
        SMS.api(API, { method: 'POST', body: { action: 'password', old: document.getElementById('pwOld').value, 'new': document.getElementById('pwNew').value, new2: document.getElementById('pwNew2').value } }).then(function (r) {
            document.getElementById('pwErr').textContent = r.success ? '' : r.message; SMS.toast(r.message, r.success ? 'success' : 'danger'); if (r.success) pw.reset();
        });
    });
})();
