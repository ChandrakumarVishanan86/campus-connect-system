/* Single event page: details, RSVP, check-in, organiser tools, admin decision */
(function () {
    'use strict';
    var esc = SMS.esc, API = APP.base + '/events/api', root = document.getElementById('evView'), ID = window.EVID, ev = null;
    function post(body) { return SMS.api(API, { method: 'POST', body: body }); }
    function done(r) { SMS.toast(r.message, r.success ? 'success' : 'danger'); load(); }
    function load() {
        SMS.api(API + '?action=get&id=' + ID).then(function (r) { if (!r.success) { root.innerHTML = '<div class="post empty">' + esc(r.message) + '</div>'; return; } ev = r.data; render(); });
    }
    function when() {
        var s = new Date(ev.starts_at.replace(' ', 'T')); return s.toLocaleDateString('en', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) + ' · ' + SMS.ftime(ev.starts_at) + ' – ' + SMS.ftime(ev.ends_at);
    }
    function rsvpPanel() {
        if (ev.status !== 'Approved') return '<div class="alert alert-' + (ev.status === 'Cancelled' || ev.status === 'Rejected' ? 'danger' : 'warning') + ' mb-0"><b>' + esc(ev.status) + '.</b> ' + esc(ev.decision_note || (ev.status === 'Pending' ? 'Waiting for an administrator to review this event.' : '')) + '</div>';
        if (ev.is_past) return '<div class="text-muted"><i class="bi bi-hourglass-bottom me-1"></i>This event has finished.' + (ev.my_checkin ? ' You attended.' : '') + '</div>';
        var h = '';
        if (ev.my_checkin) return '<div class="alert alert-success mb-0"><i class="bi bi-check2-circle me-1"></i>You are checked in. Enjoy!</div>';
        if (!ev.my_status) h = '<button class="btn btn-gradient" data-a="rsvp" data-s="Going"><i class="bi bi-check2-circle me-1"></i>' + (ev.full ? 'Join waitlist' : "I'm going") + '</button> <button class="btn btn-soft" data-a="rsvp" data-s="Interested"><i class="bi bi-star me-1"></i>Interested</button>';
        else h = '<div class="mb-2">' + ({ Going: '<span class="badge badge-soft-success fs-6">You are going</span>', Waitlist: '<span class="badge badge-soft-warning fs-6">You are on the waitlist</span>', Interested: '<span class="badge badge-soft-info fs-6">You are interested</span>' }[ev.my_status]) + '</div>' +
            (ev.my_status === 'Interested' ? '<button class="btn btn-gradient btn-sm me-1" data-a="rsvp" data-s="Going">' + (ev.full ? 'Join waitlist' : "I'm going") + '</button>' : '') + '<button class="btn btn-soft btn-sm" data-a="cancel_rsvp">Cancel RSVP</button>';
        if (ev.my_status === 'Going' && ev.checkin_open) h += '<form class="d-flex gap-2 mt-3" id="ciForm"><input class="form-control" id="ciCode" inputmode="numeric" maxlength="6" placeholder="6-digit check-in code" aria-label="Check-in code" style="max-width:220px"><button class="btn btn-coral">Check in</button></form><div class="small text-muted mt-1">Ask the organiser for the code displayed at the venue.</div>';
        else if (ev.my_status === 'Going') h += '<div class="small text-muted mt-2">Check-in opens 30 minutes before the start.</div>';
        return h;
    }
    function render() {
        var pct = ev.capacity ? Math.min(100, Math.round(ev.going / ev.capacity * 100)) : 0;
        var admin = APP.role === 'admin';
        var tools = '';
        if (ev.can_manage && !ev.is_past && ['Approved', 'Pending'].indexOf(ev.status) > -1) tools += '<button class="btn btn-soft btn-sm" data-a="edit"><i class="bi bi-pencil me-1"></i>Edit</button> <button class="btn btn-soft btn-sm text-danger" data-a="cancel"><i class="bi bi-x-circle me-1"></i>Cancel event</button>';
        var decision = admin && ev.status === 'Pending' ? '<div class="card-panel mb-3"><h6>Admin decision</h6><p class="small text-muted">Approving re-checks the venue for clashes.</p><button class="btn btn-gradient btn-sm" data-a="approve"><i class="bi bi-check2 me-1"></i>Approve</button> <button class="btn btn-soft btn-sm text-danger" data-a="reject"><i class="bi bi-x-lg me-1"></i>Reject</button></div>' : '';
        root.innerHTML = '<div class="row g-3"><div class="col-lg-8"><div class="card-panel">' +
            '<div class="d-flex flex-wrap gap-1 mb-2"><span class="badge badge-soft-plum">' + esc(ev.category) + '</span>' + SMS.badge(ev.status) + (ev.ongoing ? ' <span class="badge badge-soft-danger">Happening now</span>' : '') + '</div>' +
            '<h2 class="mb-3" style="font-family:var(--head)">' + esc(ev.title) + '</h2>' +
            '<dl class="kv mb-3"><dt>When</dt><dd>' + when() + '</dd><dt>Where</dt><dd>' + esc(ev.venue) + '</dd><dt>Hosted by</dt><dd>' + (ev.club_id ? '<a href="' + APP.base + '/clubs/' + ev.club_id + '">' + esc(ev.club_name) + '</a> · ' : '') + esc(ev.organiser) + '</dd>' +
            '<dt>Going</dt><dd>' + ev.going + (ev.capacity ? ' of ' + ev.capacity + ' seats' : ' (no limit)') + ' · ' + ev.interested + ' interested' + (ev.waitlist ? ' · ' + ev.waitlist + ' on waitlist' : '') + (ev.capacity ? '<div class="meter mt-1" style="max-width:260px"><i style="width:' + pct + '%"></i></div>' : '') + '</dd></dl>' +
            '<div style="white-space:pre-wrap">' + esc(ev.description || 'No description provided.') + '</div><hr>' + rsvpPanel() + (tools ? '<div class="mt-3">' + tools + '</div>' : '') + '</div></div>' +
            '<div class="col-lg-4">' + decision + '<div id="mgr"></div></div></div>';
        if (ev.can_manage && ev.status === 'Approved') loadManager();
    }
    function loadManager() {
        Promise.all([SMS.api(API + '?action=code&id=' + ID), SMS.api(API + '?action=attendees&id=' + ID)]).then(function (x) {
            var code = x[0].code, rows = x[1].data || [], going = rows.filter(function (r) { return r.status === 'Going'; });
            var h = '<div class="card-panel mb-3"><h6>Check-in code</h6><div class="codebox" id="codeBox">' + esc(code) + '</div><div class="small text-muted mt-2">Show this at the entrance. Attendees enter it to check in (' + ev.checked_in + ' / ' + ev.going + ' checked in).</div></div>' +
                '<div class="card-panel"><h6>Guest list <span class="text-muted fw-normal">(' + rows.length + ')</span></h6><div style="max-height:420px;overflow:auto">' +
                (rows.length ? rows.map(function (r) {
                    return '<div class="d-flex align-items-center gap-2 py-2 border-bottom">' + SMS.avatar(r.full_name, r.color, 'sm') + '<div class="flex-grow-1 min-w-0 lh-sm"><div class="fw-semibold text-truncate">' + esc(r.full_name) + '</div><div class="small text-muted">' + esc(r.programme || '') + (r.batch ? ' · ' + esc(r.batch) : '') + '</div></div>' +
                        (r.status === 'Going' ? (ev.checkin_open ? '<button class="btn btn-sm ' + (r.checked_in_at ? 'btn-gradient' : 'btn-soft') + '" data-a="ci" data-u="' + r.user_id + '" data-in="' + (r.checked_in_at ? 0 : 1) + '">' + (r.checked_in_at ? '<i class="bi bi-check2"></i> In' : 'Check in') + '</button>' : (r.checked_in_at ? '<span class="badge badge-soft-success">Attended</span>' : '<span class="badge badge-soft-success">Going</span>')) : SMS.badge(r.status)) + '</div>';
                }).join('') : '<div class="text-muted small">No RSVPs yet.</div>') + '</div></div>';
            document.getElementById('mgr').innerHTML = h;
        });
    }
    root.addEventListener('click', function (e) {
        var b = e.target.closest('[data-a]'); if (!b) return; var a = b.dataset.a;
        if (a === 'rsvp') return post({ action: 'rsvp', id: ID, status: b.dataset.s }).then(done);
        if (a === 'cancel_rsvp') return post({ action: 'cancel_rsvp', id: ID }).then(done);
        if (a === 'edit') return EventForm.open(ev, load);
        if (a === 'cancel') return EventForm.reason('Why is it cancelled?', function (t) { post({ action: 'cancel', id: ID, reason: t }).then(done); });
        if (a === 'approve') return post({ action: 'decide', id: ID, approve: 1 }).then(done);
        if (a === 'reject') return EventForm.reason('Reason for rejecting', function (t) { post({ action: 'decide', id: ID, approve: 0, note: t }).then(done); });
        if (a === 'ci') return post({ action: 'checkin_member', id: ID, user_id: b.dataset.u, in: +b.dataset.in }).then(function (r) { if (!r.success) SMS.toast(r.message, 'danger'); load(); });
    });
    root.addEventListener('submit', function (e) {
        if (e.target.id !== 'ciForm') return; e.preventDefault();
        post({ action: 'checkin', id: ID, code: document.getElementById('ciCode').value }).then(done);
    });
    load();
})();
