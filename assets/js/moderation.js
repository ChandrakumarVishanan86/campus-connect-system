(function () {
    'use strict'; var esc = SMS.esc, API = APP.base + '/admin/moderation-api', box = document.getElementById('modList');
    function load() {
        SMS.api(API).then(function (r) {
            var d = r.data || [];
            box.innerHTML = d.length ? d.map(function (x) {
                return '<div class="card-panel mb-3" data-e="' + x.entity + '" data-id="' + x.entity_id + '"><div class="d-flex flex-wrap gap-2 align-items-center mb-2"><span class="badge badge-soft-plum">' + esc(x.entity) + '</span> ' + SMS.badge(x.status) + '<span class="badge badge-soft-danger">' + x.reports + ' report' + (x.reports > 1 ? 's' : '') + '</span><span class="small text-muted">' + esc(x.reasons) + '</span><span class="ms-auto small text-muted">last ' + SMS.ago(x.last_at) + '</span></div>' +
                    '<div class="small text-muted mb-1">by <b>' + esc(x.author) + '</b></div><blockquote class="mb-3 p-3 rounded" style="background:var(--surface2);white-space:pre-wrap">' + esc(x.body) + '</blockquote>' +
                    '<div class="d-flex flex-wrap gap-2"><button class="btn btn-sm btn-coral" data-d="hide">Remove (hide)</button><button class="btn btn-sm btn-outline-danger" data-d="delete">Delete permanently</button><button class="btn btn-sm btn-soft" data-d="dismiss">Dismiss reports (keep)</button>' + (x.post_id ? '<a class="btn btn-sm btn-link" href="' + APP.base + '/feed#p' + x.post_id + '">View in feed</a>' : '') + '</div></div>';
            }).join('') : '<div class="empty"><i class="bi bi-shield-check"></i><h5>Queue is clear</h5><p>No open reports. The community is behaving.</p></div>';
        });
    }
    box.addEventListener('click', function (e) {
        var b = e.target.closest('button[data-d]'); if (!b) return; var c = b.closest('.card-panel'), go = function () { return SMS.api(API, { method: 'POST', body: { action: 'resolve', entity: c.dataset.e, id: c.dataset.id, decision: b.dataset.d } }).then(function (r) { SMS.toast(r.message, r.success ? 'success' : 'danger'); load(); }); };
        if (b.dataset.d === 'delete') SMS.confirmBox('Delete this permanently? This cannot be undone.', 'Delete', 'danger').then(function (ok) { if (ok) go(); }); else go();
    });
    load();
})();
