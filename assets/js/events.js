/* Events: list tabs, month calendar, RSVP shortcuts */
(function () {
    'use strict';
    var esc = SMS.esc, API = APP.base + '/events/api', list = document.getElementById('evList'), calBox = document.getElementById('evCal'),
        st = { scope: 'upcoming', category: '', q: '', club: (window.EV && EV.clubId) || '' }, cal = new Date();
    cal.setDate(1);
    function rsvpBadge(e) {
        if (e.my_checkin) return '<span class="badge badge-soft-success"><i class="bi bi-check2-circle me-1"></i>Checked in</span>';
        if (e.my_status === 'Going') return '<span class="badge badge-soft-success">You are going</span>';
        if (e.my_status === 'Waitlist') return '<span class="badge badge-soft-warning">Waitlisted</span>';
        if (e.my_status === 'Interested') return '<span class="badge badge-soft-info">Interested</span>';
        return '';
    }
    function card(e) {
        var d = new Date(e.starts_at.replace(' ', 'T')), pct = e.capacity ? Math.min(100, Math.round(e.going / e.capacity * 100)) : 0;
        var acts = '';
        if (e.status === 'Approved' && !e.is_past) {
            if (!e.my_status) acts = '<button class="btn btn-sm btn-gradient" data-a="rsvp" data-s="Going">' + (e.full ? 'Join waitlist' : 'Going') + '</button> <button class="btn btn-sm btn-soft" data-a="rsvp" data-s="Interested">Interested</button>';
            else if (!e.my_checkin) acts = '<button class="btn btn-sm btn-soft" data-a="cancel_rsvp">Cancel RSVP</button>';
        }
        return '<div class="ev-card" data-id="' + e.id + '"><div class="datebox"><div class="m">' + d.toLocaleString('en', { month: 'short' }) + '</div><div class="d">' + d.getDate() + '</div></div>' +
            '<div class="flex-grow-1 min-w-0"><div class="d-flex flex-wrap gap-1 align-items-center mb-1"><span class="badge badge-soft-plum">' + esc(e.category) + '</span>' + (e.status !== 'Approved' ? SMS.badge(e.status) : '') + (e.ongoing ? ' <span class="badge badge-soft-danger">Happening now</span>' : '') + ' ' + rsvpBadge(e) + '</div>' +
            '<h5><a class="stretched-link-off" href="' + APP.base + '/events/' + e.id + '">' + esc(e.title) + '</a></h5>' +
            '<div class="ev-meta"><span><i class="bi bi-clock me-1"></i>' + SMS.ftime(e.starts_at) + ' – ' + SMS.ftime(e.ends_at) + ' · ' + d.toLocaleString('en', { weekday: 'short' }) + '</span><span><i class="bi bi-geo-alt me-1"></i>' + esc(e.venue) + '</span><span><i class="bi bi-' + (e.club_name ? 'people' : 'person') + ' me-1"></i>' + esc(e.club_name || e.organiser) + '</span></div>' +
            (e.status === 'Approved' ? '<div class="d-flex align-items-center gap-2 mt-2"><div class="meter flex-grow-1" style="max-width:200px"><i style="width:' + (e.capacity ? pct : Math.min(100, e.going * 4)) + '%"></i></div><span class="small text-muted">' + e.going + (e.capacity ? ' / ' + e.capacity : '') + ' going' + (e.waitlist ? ' · ' + e.waitlist + ' waiting' : '') + (e.full ? ' · <b class="text-danger">Full</b>' : '') + '</span></div>' : '') +
            (e.decision_note && e.status !== 'Approved' ? '<div class="small mt-2 text-muted"><i class="bi bi-chat-left-text me-1"></i>' + esc(e.decision_note) + '</div>' : '') +
            '<div class="mt-2 d-flex gap-2 flex-wrap">' + acts + '</div></div></div>';
    }
    function loadList() {
        list.innerHTML = '<div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm"></div></div>';
        var p = new URLSearchParams({ action: 'list', scope: st.scope, category: st.category, q: st.q, club_id: st.club });
        SMS.api(API + '?' + p).then(function (r) {
            if (!r.success) { list.innerHTML = '<div class="post empty">' + esc(r.message) + '</div>'; return; }
            var empty = { upcoming: 'No upcoming events match. Why not propose one?', mine: 'You have not RSVPed to anything yet.', organising: 'You are not organising any events.', pending: 'Nothing waiting for approval.', past: 'No past events.' }[st.scope] || 'Nothing here.';
            list.innerHTML = r.data.length ? r.data.map(card).join('') : '<div class="post empty"><i class="bi bi-calendar-event"></i>' + empty + '</div>';
        });
    }
    function loadCal() {
        var y = cal.getFullYear(), m = cal.getMonth() + 1;
        document.getElementById('calTitle').textContent = cal.toLocaleString('en', { month: 'long', year: 'numeric' });
        SMS.api(API + '?action=month&year=' + y + '&month=' + m).then(function (r) {
            var by = {}; (r.data || []).forEach(function (e) { (by[+e.starts_at.slice(8, 10)] = by[+e.starts_at.slice(8, 10)] || []).push(e); });
            var first = (new Date(y, m - 1, 1).getDay() + 6) % 7, days = new Date(y, m, 0).getDate(), today = new Date(), h = '';
            ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].forEach(function (d) { h += '<div class="dow">' + d + '</div>'; });
            for (var i = 0; i < first; i++) h += '<div class="cell off"></div>';
            for (var d = 1; d <= days; d++) {
                var isT = today.getFullYear() === y && today.getMonth() + 1 === m && today.getDate() === d;
                h += '<div class="cell' + (isT ? ' today' : '') + '"><div class="n">' + d + '</div>' + (by[d] || []).slice(0, 3).map(function (e) { return '<a class="pill' + (e.my_status === 'Going' ? ' mine' : '') + '" href="' + APP.base + '/events/' + e.id + '" title="' + esc(e.title) + '">' + SMS.ftime(e.starts_at) + ' ' + esc(e.title) + '</a>'; }).join('') + ((by[d] || []).length > 3 ? '<div class="text-muted">+' + ((by[d] || []).length - 3) + ' more</div>' : '') + '</div>';
            }
            document.getElementById('calGrid').innerHTML = h;
        });
    }
    function show() { var c = st.scope === 'calendar'; list.classList.toggle('d-none', c); calBox.classList.toggle('d-none', !c); c ? loadCal() : loadList(); }
    document.getElementById('evTabs').addEventListener('click', function (e) { var b = e.target.closest('.chip'); if (!b) return; this.querySelectorAll('.chip').forEach(function (x) { x.classList.toggle('on', x === b); }); st.scope = b.dataset.scope; show(); });
    document.getElementById('evCategory').addEventListener('change', function () { st.category = this.value; show(); });
    document.getElementById('evSearch').addEventListener('input', SMS.debounce(function () { st.q = this.value.trim(); if (st.scope === 'calendar') return; show(); }, 300));
    document.getElementById('calPrev').onclick = function () { cal.setMonth(cal.getMonth() - 1); loadCal(); };
    document.getElementById('calNext').onclick = function () { cal.setMonth(cal.getMonth() + 1); loadCal(); };
    document.getElementById('calToday').onclick = function () { cal = new Date(); cal.setDate(1); loadCal(); };
    document.getElementById('btnNew').onclick = function () { EventForm.open(null, function () { st.scope = APP.role === 'student' ? 'pending' : 'upcoming'; document.querySelectorAll('#evTabs .chip').forEach(function (x) { x.classList.toggle('on', x.dataset.scope === st.scope); }); show(); }); };
    list.addEventListener('click', function (e) {
        var b = e.target.closest('[data-a]'); if (!b) return; var id = b.closest('.ev-card').dataset.id;
        var body = b.dataset.a === 'rsvp' ? { action: 'rsvp', id: id, status: b.dataset.s } : { action: 'cancel_rsvp', id: id }; b.disabled = true;
        SMS.api(API, { method: 'POST', body: body }).then(function (r) { SMS.toast(r.message, r.success ? 'success' : 'danger'); loadList(); });
    });
    // pending badge for the first-time visitor
    SMS.api(API + '?action=list&scope=pending').then(function (r) { if (r.success && r.data.length) { var c = document.getElementById('pendCount'); c.textContent = r.data.length; c.classList.remove('d-none'); } });
    var q = new URLSearchParams(location.search).get('scope'); if (q && document.querySelector('#evTabs [data-scope="' + q + '"]')) { st.scope = q; document.querySelectorAll('#evTabs .chip').forEach(function (x) { x.classList.toggle('on', x.dataset.scope === q); }); }
    show();
})();
