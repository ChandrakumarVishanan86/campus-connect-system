/* Right-rail widgets */
(function () {
    'use strict'; var esc = SMS.esc;
    SMS.api(APP.base + '/feed/api?action=rail').then(function (r) {
        if (!r.success) return;
        document.getElementById('railEvents').innerHTML = r.events.length ? r.events.map(function (e) {
            var d = new Date(e.starts_at.replace(' ', 'T'));
            return '<a class="d-flex gap-2 align-items-center mb-2 text-decoration-none" style="color:inherit" href="' + APP.base + '/events/' + e.id + '"><div class="datebox" style="width:46px"><div class="m">' + d.toLocaleString('en-GB', { month: 'short' }) + '</div><div class="d" style="font-size:1.15rem">' + d.getDate() + '</div></div><div class="flex-grow-1 lh-sm"><div class="fw-semibold">' + esc(e.title) + '</div><div class="text-muted" style="font-size:.76rem">' + SMS.ftime(e.starts_at) + ' · ' + esc(e.venue) + '</div></div></a>';
        }).join('') + '<a class="small fw-semibold" href="' + APP.base + '/events">All events →</a>' : 'No upcoming events.';
        document.getElementById('railClubs').innerHTML = r.clubs.length ? r.clubs.map(function (c) { return '<a class="d-flex justify-content-between py-1 text-decoration-none" style="color:inherit" href="' + APP.base + '/clubs/' + c.id + '"><span class="fw-semibold">' + esc(c.name) + '</span><span class="text-muted">' + esc(c.role) + '</span></a>'; }).join('') : 'You have not joined a club yet. <a href="' + APP.base + '/clubs">Browse clubs</a>';
        document.getElementById('railLost').innerHTML = r.lost.length ? r.lost.map(function (i) { return '<div class="d-flex gap-2 py-1 align-items-center">' + SMS.badge(i.kind) + '<span class="text-truncate">' + esc(i.title) + '</span></div>'; }).join('') + '<a class="small fw-semibold" href="' + APP.base + '/lost-found">Open the board →</a>' : 'Nothing reported lately.';
    });
})();
