/* Clubs directory */
(function () {
    'use strict';
    var esc = SMS.esc, API = APP.base + '/clubs/api', grid = document.getElementById('clubGrid'), all = [], st = { cat: '', q: '' };
    function card(c) {
        var st2 = c.my_status === 'Active' ? '<span class="badge badge-soft-success">' + (c.my_role === 'Member' ? 'Member' : esc(c.my_role)) + '</span>' : c.my_status === 'Pending' ? '<span class="badge badge-soft-warning">Request pending</span>' : '';
        var btn = '';
        if (APP.role === 'student' && !c.my_status) btn = '<button class="btn btn-sm btn-gradient" data-id="' + c.id + '" data-a="join">' + (c.join_policy === 'Open' ? 'Join' : 'Request to join') + '</button>';
        return '<div class="col-md-6 col-xl-4"><div class="club-card h-100 d-flex flex-column"><div class="d-flex align-items-start gap-2 mb-2"><div class="club-logo av av-' + (c.id % 8) + '">' + esc(c.code.slice(0, 2)) + '</div><div class="min-w-0"><h5 class="mb-0"><a href="' + APP.base + '/clubs/' + c.id + '">' + esc(c.name) + '</a></h5><div class="small text-muted">' + esc(c.category) + '</div></div><div class="ms-auto">' + st2 + '</div></div>' +
            '<p class="small flex-grow-1 mb-2">' + esc(c.description || '').slice(0, 130) + '</p><div class="d-flex align-items-center small text-muted"><i class="bi bi-people me-1"></i>' + c.members + ' members<span class="ms-2"><i class="bi bi-' + (c.join_policy === 'Open' ? 'unlock' : 'lock') + ' me-1"></i>' + (c.join_policy === 'Open' ? 'Open' : 'Approval') + '</span><span class="ms-auto">' + btn + '</span></div></div></div>';
    }
    function render() {
        var rows = all.filter(function (c) { return (!st.cat || (st.cat === '__mine' ? c.my_status === 'Active' : c.category === st.cat)) && (!st.q || (c.name + ' ' + c.description).toLowerCase().indexOf(st.q) > -1); });
        grid.innerHTML = rows.length ? rows.map(card).join('') : '<div class="col-12"><div class="post empty"><i class="bi bi-people"></i>No clubs match.</div></div>';
    }
    function load() { SMS.api(API + '?action=list').then(function (r) { all = r.data || []; render(); }); }
    document.getElementById('clubChips').addEventListener('click', function (e) { var c = e.target.closest('.chip'); if (!c) return; this.querySelectorAll('.chip').forEach(function (x) { x.classList.toggle('on', x === c); }); st.cat = c.dataset.cat; render(); });
    document.getElementById('clubSearch').addEventListener('input', SMS.debounce(function () { st.q = this.value.trim().toLowerCase(); render(); }, 200));
    grid.addEventListener('click', function (e) {
        var b = e.target.closest('[data-a="join"]'); if (!b) return; b.disabled = true;
        SMS.api(API, { method: 'POST', body: { action: 'join', id: b.dataset.id } }).then(function (r) { SMS.toast(r.message, r.success ? 'success' : 'danger'); load(); });
    });
    load();
})();
