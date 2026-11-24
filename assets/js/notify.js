(function () {
    'use strict';
    var esc = SMS.esc, API = APP.base + '/notifications/api', box = document.getElementById('nList');
    var ICON = { comment: 'chat-dots', like: 'heart', event: 'calendar-event', club: 'people', lost: 'box-seam', announcement: 'megaphone', moderation: 'shield-check', welcome: 'stars', mention: 'at' };
    function load() {
        SMS.api(API).then(function (r) {
            var d = r.data || [];
            box.innerHTML = d.length ? d.map(function (n) {
                return '<a class="nrow d-flex gap-3 p-3 border-bottom text-reset text-decoration-none' + (n.is_read == 0 ? ' unread' : '') + '" href="' + (n.link ? APP.base + '/' + n.link : '#') + '" data-id="' + n.id + '"><div class="nico"><i class="bi bi-' + (ICON[n.type] || 'bell') + '"></i></div><div class="flex-grow-1"><div>' + esc(n.message) + '</div><div class="small text-muted">' + SMS.ago(n.created_at) + '</div></div>' + (n.is_read == 0 ? '<span class="udot" aria-label="Unread"></span>' : '') + '</a>';
            }).join('') : '<div class="empty"><i class="bi bi-bell-slash"></i>You are all caught up.</div>';
        });
    }
    document.getElementById('markAll').onclick = function () { SMS.api(API, { method: 'POST', body: { action: 'read' } }).then(function () { var d = document.getElementById('bellDot'); if (d) d.remove(); load(); }); };
    box.addEventListener('click', function (e) { var a = e.target.closest('.nrow'); if (!a || !a.classList.contains('unread')) return; navigator.sendBeacon && 0; SMS.api(API, { method: 'POST', body: { action: 'read', id: a.dataset.id } }); });
    load();
})();
