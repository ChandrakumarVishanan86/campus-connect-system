/* Single club page: header, posts (feed.js), events, members and management */
(function () {
    'use strict';
    var esc = SMS.esc, API = APP.base + '/clubs/api', ID = CLUB.id, head = document.getElementById('clubHead'), club = null;
    function post(b) { return SMS.api(API, { method: 'POST', body: b }); }
    function renderHead(c, members) {
        var btns = '';
        if (APP.role === 'student') {
            if (!c.my_status) btns = '<button class="btn btn-gradient" data-a="join">' + (c.join_policy === 'Open' ? 'Join club' : 'Request to join') + '</button>';
            else if (c.my_status === 'Pending') btns = '<span class="badge badge-soft-warning fs-6">Request pending</span> <button class="btn btn-soft btn-sm" data-a="leave">Withdraw</button>';
            else btns = '<span class="badge badge-soft-success fs-6 me-1">' + esc(c.my_role) + '</span><button class="btn btn-soft btn-sm" data-a="leave">Leave</button>';
        }
        if (c.can_manage) btns += ' <button class="btn btn-gradient btn-sm" data-a="post"><i class="bi bi-megaphone me-1"></i>Post as club</button> <button class="btn btn-soft btn-sm" data-a="event"><i class="bi bi-calendar-plus me-1"></i>New event</button>';
        head.innerHTML = '<div class="d-flex flex-wrap gap-3 align-items-start"><div class="club-logo lg av av-' + (c.id % 8) + '">' + esc(c.code.slice(0, 2)) + '</div><div class="flex-grow-1 min-w-0"><h3 class="mb-1" style="font-family:var(--head)">' + esc(c.name) + '</h3>' +
            '<div class="text-muted small mb-2"><span class="badge badge-soft-plum">' + esc(c.category) + '</span> <i class="bi bi-people ms-2"></i> ' + c.members + ' members · <i class="bi bi-' + (c.join_policy === 'Open' ? 'unlock' : 'lock') + '"></i> ' + (c.join_policy === 'Open' ? 'Open to all' : 'Join by approval') + (c.advisor ? ' · Advisor: ' + esc(c.advisor) : '') + '</div>' +
            '<p class="mb-2">' + esc(c.description || 'No description yet.') + '</p>' + (c.meeting_info ? '<div class="small text-muted mb-2"><i class="bi bi-clock me-1"></i>' + esc(c.meeting_info) + '</div>' : '') + '<div class="d-flex flex-wrap gap-2 align-items-center">' + btns + '</div></div></div>';
    }
    function loadHead() { return SMS.api(APP.base + '/clubs/api?action=list').then(function (r) { club = (r.data || []).filter(function (c) { return c.id == ID; })[0]; if (!club) return; club.can_manage = !!CLUB.mgr; renderHead(club); }); }
    function loadEvents() {
        SMS.api(APP.base + '/events/api?action=list&scope=upcoming&club_id=' + ID).then(function (r) {
            document.getElementById('clubEvents').innerHTML = (r.data || []).length ? r.data.map(function (e) {
                var d = new Date(e.starts_at.replace(' ', 'T'));
                return '<div class="ev-card"><div class="datebox"><div class="m">' + d.toLocaleString('en', { month: 'short' }) + '</div><div class="d">' + d.getDate() + '</div></div><div class="flex-grow-1"><h5><a href="' + APP.base + '/events/' + e.id + '">' + esc(e.title) + '</a></h5><div class="ev-meta"><span><i class="bi bi-clock me-1"></i>' + SMS.ftime(e.starts_at) + '</span><span><i class="bi bi-geo-alt me-1"></i>' + esc(e.venue) + '</span><span>' + e.going + ' going</span></div></div></div>';
            }).join('') : '<div class="post empty"><i class="bi bi-calendar-event"></i>No upcoming events for this club.</div>';
        });
    }
    function loadMembers() {
        SMS.api(API + '?action=members&id=' + ID).then(function (r) {
            var rows = r.data || [], mgr = club && club.can_manage, pend = rows.filter(function (m) { return m.status === 'Pending'; });
            var pb = document.getElementById('pendBadge'); if (pend.length && mgr) { pb.textContent = pend.length; pb.classList.remove('d-none'); } else pb.classList.add('d-none');
            document.getElementById('clubMembers').innerHTML = '<div class="card-panel p-0">' + rows.map(function (m) {
                var act = '';
                if (mgr && m.status === 'Pending') act = '<button class="btn btn-sm btn-gradient" data-m="approve" data-u="' + m.user_id + '">Approve</button> <button class="btn btn-sm btn-soft" data-m="decline" data-u="' + m.user_id + '">Decline</button>';
                else if (mgr && m.status === 'Active' && m.role !== 'President') act = '<div class="dropdown"><button class="act" data-bs-toggle="dropdown" aria-label="Member actions"><i class="bi bi-three-dots"></i></button><ul class="dropdown-menu dropdown-menu-end">' +
                    (m.role === 'Member' ? '<li><button class="dropdown-item" data-m="role" data-r="Officer" data-u="' + m.user_id + '">Make officer</button></li>' : '<li><button class="dropdown-item" data-m="role" data-r="Member" data-u="' + m.user_id + '">Make regular member</button></li>') +
                    '<li><button class="dropdown-item" data-m="role" data-r="President" data-u="' + m.user_id + '">Make president</button></li><li><button class="dropdown-item text-danger" data-m="remove" data-u="' + m.user_id + '">Remove</button></li></ul></div>';
                return '<div class="d-flex align-items-center gap-2 p-3 border-bottom">' + SMS.avatar(m.full_name, m.color) + '<div class="flex-grow-1 min-w-0 lh-sm"><a class="fw-semibold" href="' + APP.base + '/people/' + m.user_id + '">' + esc(m.full_name) + '</a><div class="small text-muted">' + esc(m.programme || '') + (m.batch ? ' · ' + esc(m.batch) : '') + '</div></div>' +
                    (m.status === 'Pending' ? '<span class="badge badge-soft-warning">Pending</span>' : m.role !== 'Member' ? '<span class="badge badge-soft-plum">' + esc(m.role) + '</span>' : '') + act + '</div>';
            }).join('') + '</div>';
        });
    }
    head.addEventListener('click', function (e) {
        var b = e.target.closest('[data-a]'); if (!b) return; var a = b.dataset.a;
        if (a === 'post') return window.openCompose({ club: ID });
        if (a === 'event') return EventForm.open(null, function () { loadEvents(); });
        if (a === 'leave') return SMS.confirmBox('Leave ' + club.name + '?', 'Leave').then(function (ok) { if (ok) post({ action: 'leave', id: ID }).then(function (r) { SMS.toast(r.message, r.success ? 'success' : 'danger'); refresh(); }); });
        post({ action: a, id: ID }).then(function (r) { SMS.toast(r.message, r.success ? 'success' : 'danger'); refresh(); });
    });
    document.getElementById('clubMembers').addEventListener('click', function (e) {
        var b = e.target.closest('[data-m]'); if (!b) return; var m = b.dataset.m, u = b.dataset.u, req;
        if (m === 'approve' || m === 'decline') req = post({ action: 'decide', id: ID, user_id: u, approve: m === 'approve' ? 1 : 0 });
        else if (m === 'role') req = post({ action: 'role', id: ID, user_id: u, role: b.dataset.r });
        else return SMS.confirmBox('Remove this member from the club?', 'Remove').then(function (ok) { if (ok) post({ action: 'remove', id: ID, user_id: u }).then(function (r) { SMS.toast(r.message, r.success ? 'success' : 'danger'); loadMembers(); }); });
        req.then(function (r) { SMS.toast(r.message, r.success ? 'success' : 'danger'); loadMembers(); });
    });
    var af = document.getElementById('aboutForm'); if (af) af.addEventListener('submit', function (e) {
        e.preventDefault(); post({ action: 'about', id: ID, description: document.getElementById('abDesc').value, meeting_info: document.getElementById('abMeet').value, join_policy: document.getElementById('abPol').value }).then(function (r) { document.getElementById('abErr').textContent = r.success ? '' : r.message; SMS.toast(r.message, r.success ? 'success' : 'danger'); if (r.success) refresh(); });
    });
    function refresh() { loadHead().then(function () { loadMembers(); }); }
    refresh(); loadEvents();
})();
