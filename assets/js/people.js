(function () {
    'use strict';
    var esc = SMS.esc, API = APP.base + '/people/api', grid = document.getElementById('pGrid');
    function load() {
        var p = new URLSearchParams({ action: 'list', q: document.getElementById('pQ').value.trim(), role: document.getElementById('pRole').value, programme_id: document.getElementById('pProg').value });
        SMS.api(API + '?' + p).then(function (r) {
            var d = r.data || [];
            grid.innerHTML = d.length ? d.map(function (u) {
                return '<div class="col-md-6 col-xl-4"><a class="card-panel d-flex gap-3 align-items-center h-100 text-reset text-decoration-none" href="' + APP.base + '/people/' + u.id + '">' + SMS.avatar(u.full_name, u.color) + '<div class="min-w-0 lh-sm"><div class="fw-semibold text-truncate">' + esc(u.full_name) + (u.role !== 'student' ? ' <span class="badge badge-soft-plum">' + (u.role === 'admin' ? 'Admin' : 'Staff') + '</span>' : '') + '</div><div class="small text-muted text-truncate">' + esc(u.role === 'student' ? (u.programme || '') + (u.batch ? ' · Batch ' + u.batch : '') : (u.title || '')) + '</div><div class="small text-muted"><i class="bi bi-people me-1"></i>' + u.clubs + ' clubs</div></div></a></div>';
            }).join('') : '<div class="col-12"><div class="post empty"><i class="bi bi-person-x"></i>Nobody matches that search.</div></div>';
            document.getElementById('pNote').textContent = d.length >= 120 ? 'Showing the first 120 matches - refine your search to narrow it down.' : d.length + ' people';
        });
    }
    ['pRole', 'pProg'].forEach(function (i) { document.getElementById(i).addEventListener('change', load); });
    document.getElementById('pQ').addEventListener('input', SMS.debounce(load, 250));
    load();
})();
