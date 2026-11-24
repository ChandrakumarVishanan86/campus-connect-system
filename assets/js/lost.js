/* Lost & found board */
(function () {
    'use strict';
    var esc = SMS.esc, API = APP.base + '/lost-found/api', grid = document.getElementById('lfGrid'), st = { kind: '', status: 'open', q: '' }, claimId = null;
    var mForm = new bootstrap.Modal(document.getElementById('lfModal')), mClaim = new bootstrap.Modal(document.getElementById('claimModal'));
    var fp = flatpickr('#lfDate', { dateFormat: 'Y-m-d', maxDate: 'today', minDate: new Date(Date.now() - 60 * 864e5), defaultDate: 'today', disableMobile: true });
    function card(i) {
        var lost = i.kind === 'Lost', phone = i.mine ? i.claimant_phone : i.poster_phone;
        var acts = '';
        if (i.can_claim) acts += '<button class="btn btn-sm btn-gradient" data-a="claim" data-id="' + i.id + '" data-kind="' + i.kind + '">' + (lost ? 'I found it' : 'This is mine') + '</button> ';
        if (i.can_decide) acts += '<button class="btn btn-sm btn-gradient" data-a="confirm" data-id="' + i.id + '">Confirm</button> <button class="btn btn-sm btn-soft" data-a="reject" data-id="' + i.id + '">Decline</button> ';
        if (i.can_delete) acts += '<button class="btn btn-sm btn-soft text-danger ms-auto" data-a="delete" data-id="' + i.id + '" aria-label="Delete report"><i class="bi bi-trash3"></i></button>';
        return '<div class="col-md-6"><div class="item-card h-100"><div class="d-flex gap-2 align-items-center mb-2"><span class="badge badge-soft-' + (lost ? 'danger' : 'success') + '"><i class="bi bi-' + (lost ? 'search' : 'box-seam') + ' me-1"></i>' + i.kind + '</span>' + SMS.badge(i.status) + '<span class="ms-auto small text-muted">' + SMS.ago(i.created_at) + '</span></div>' +
            '<h5 class="mb-1">' + esc(i.title) + '</h5><div class="small text-muted mb-2"><i class="bi bi-geo-alt me-1"></i>' + esc(i.location) + ' · ' + SMS.fdate(i.happened_on) + '</div>' + (i.description ? '<p class="small mb-2">' + esc(i.description) + '</p>' : '') +
            '<div class="small text-muted mb-2">Posted by ' + esc(i.mine ? 'you' : i.poster) + '</div>' +
            (i.claim_note ? '<div class="alert alert-warning py-2 small mb-2"><b>' + esc(i.is_claimant ? 'Your message' : (i.claimant || 'Claimant')) + ':</b> ' + esc(i.claim_note) + '</div>' : '') +
            (i.status === 'Resolved' && phone ? '<div class="alert alert-success py-2 small mb-2"><i class="bi bi-telephone me-1"></i>Contact: <b>' + esc(phone) + '</b></div>' : '') +
            '<div class="d-flex gap-2 flex-wrap">' + acts + '</div></div></div>';
    }
    function load() {
        var p = new URLSearchParams({ action: 'list', kind: st.kind, status: st.status, q: st.q });
        SMS.api(API + '?' + p).then(function (r) { grid.innerHTML = (r.data || []).length ? r.data.map(card).join('') : '<div class="col-12"><div class="post empty"><i class="bi bi-box-seam"></i>Nothing here. That is good news!</div></div>'; });
    }
    document.getElementById('lfKind').addEventListener('click', function (e) { var c = e.target.closest('.chip'); if (!c) return; this.querySelectorAll('.chip').forEach(function (x) { x.classList.toggle('on', x === c); }); st.kind = c.dataset.kind; load(); });
    document.getElementById('lfStatus').addEventListener('change', function () { st.status = this.value; load(); });
    document.getElementById('lfSearch').addEventListener('input', SMS.debounce(function () { st.q = this.value.trim(); load(); }, 300));
    document.getElementById('btnNew').onclick = function () { document.getElementById('lfForm').reset(); fp.setDate('today'); document.getElementById('lfErr').textContent = ''; mForm.show(); };
    document.getElementById('lfForm').addEventListener('submit', function (e) {
        e.preventDefault(); var f = e.target, b = { action: 'create', kind: f.kind.value, title: f.title.value, location: f.location.value, happened_on: f.happened_on.value, description: f.description.value };
        SMS.api(API, { method: 'POST', body: b }).then(function (r) { if (!r.success) { document.getElementById('lfErr').textContent = r.message; return; } mForm.hide(); SMS.toast(r.message, 'success'); st.status = 'open'; document.getElementById('lfStatus').value = 'open'; load(); });
    });
    grid.addEventListener('click', function (e) {
        var b = e.target.closest('[data-a]'); if (!b) return; var a = b.dataset.a, id = b.dataset.id;
        if (a === 'claim') { claimId = id; var lost = b.dataset.kind === 'Lost'; document.getElementById('clTitle').textContent = lost ? 'You found this item?' : 'Is this yours?'; document.getElementById('clHelp').textContent = lost ? 'Tell the owner where and when you found it.' : 'Describe the item (a detail only the owner would know) so the finder can verify it.'; document.getElementById('clNote').value = ''; document.getElementById('clErr').textContent = ''; mClaim.show(); return; }
        if (a === 'delete') return SMS.confirmBox('Delete this report?', 'Delete').then(function (ok) { if (ok) SMS.api(API, { method: 'POST', body: { action: 'delete', id: id } }).then(function (r) { SMS.toast(r.message, r.success ? 'success' : 'danger'); load(); }); });
        SMS.api(API, { method: 'POST', body: { action: a, id: id } }).then(function (r) { SMS.toast(r.message, r.success ? 'success' : 'danger'); load(); });
    });
    document.getElementById('clGo').addEventListener('click', function () {
        SMS.api(API, { method: 'POST', body: { action: 'claim', id: claimId, note: document.getElementById('clNote').value } }).then(function (r) { if (!r.success) { document.getElementById('clErr').textContent = r.message; return; } mClaim.hide(); SMS.toast(r.message, 'success'); load(); });
    });
    load();
})();
