/* Feed: posts, likes, comments, moderation menu, infinite "load more" */
(function () {
    'use strict';
    var esc = SMS.esc, API = APP.base + '/feed/api', box = document.getElementById('feed'), moreBtn = document.getElementById('more'),
        st = { type: '', scope: '', q: (window.FEED && FEED.q) || '', page: 1 }, rp = null;
    var rm = new bootstrap.Modal(document.getElementById('reportModal'));

    function linkify(t) { return esc(t).replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'); }
    function menu(p) {
        var h = '';
        if (p.can_pin) h += '<li><button class="dropdown-item" data-a="' + (p.is_pinned == 1 ? 'unpin' : 'pin') + '"><i class="bi bi-pin-angle me-2"></i>' + (p.is_pinned == 1 ? 'Unpin' : 'Pin to top') + '</button></li>';
        if (p.can_hide) h += '<li><button class="dropdown-item" data-a="' + (p.status === 'Hidden' ? 'unhide' : 'hide') + '"><i class="bi bi-eye-slash me-2"></i>' + (p.status === 'Hidden' ? 'Restore post' : 'Hide post') + '</button></li>';
        if (!p.mine) h += '<li><button class="dropdown-item" data-a="report"><i class="bi bi-flag me-2"></i>Report</button></li>';
        if (p.can_delete) h += '<li><button class="dropdown-item text-danger" data-a="delete"><i class="bi bi-trash3 me-2"></i>Delete</button></li>';
        return h ? '<div class="dropdown ms-auto"><button class="act" data-bs-toggle="dropdown" aria-label="More actions"><i class="bi bi-three-dots"></i></button><ul class="dropdown-menu dropdown-menu-end">' + h + '</ul></div>' : '';
    }
    function card(p) {
        var staff = p.author_role !== 'student';
        return '<article class="post' + (p.is_pinned == 1 ? ' pinned' : '') + (p.status === 'Hidden' ? ' hidden-post' : '') + '" id="p' + p.id + '" data-id="' + p.id + '">' +
            '<div class="meta">' + SMS.avatar(p.author, p.author_color) + '<div class="lh-sm"><div class="name">' + esc(p.author) + (staff ? ' <span class="badge badge-soft-plum">' + (p.author_role === 'admin' ? 'Admin' : 'Staff') + '</span>' : '') + '</div>' +
            '<div class="when">' + (p.club_name ? '<i class="bi bi-people-fill"></i> <a href="' + APP.base + '/clubs/' + p.club_id + '">' + esc(p.club_name) + '</a> · ' : (p.author_title ? esc(p.author_title) + ' · ' : '')) + SMS.ago(p.created_at) + (p.audience ? ' · <span title="Audience">' + esc(p.audience) + ' only</span>' : '') + '</div></div>' +
            '<div class="ms-2">' + (p.type !== 'Discussion' ? SMS.badge(p.type) : '') + (p.is_pinned == 1 ? ' <i class="bi bi-pin-angle-fill text-danger" title="Pinned"></i>' : '') + (p.status === 'Hidden' ? ' ' + SMS.badge('Hidden') : '') + '</div>' + menu(p) + '</div>' +
            (p.title ? '<div class="title">' + esc(p.title) + '</div>' : '') + '<div class="body">' + linkify(p.body) + '</div>' +
            '<div class="acts"><button class="act like' + (p.liked > 0 ? ' on' : '') + '" data-a="like"><i class="bi ' + (p.liked > 0 ? 'bi-heart-fill' : 'bi-heart') + '"></i><span>' + p.likes + '</span></button>' +
            '<button class="act" data-a="comments"><i class="bi bi-chat"></i><span class="cc">' + p.comments + '</span></button></div>' +
            '<div class="comments"><div class="clist"></div><form class="d-flex gap-2 mt-2 cform"><input class="form-control form-control-sm" maxlength="' + APP.limits.comment + '" placeholder="Write a comment…" aria-label="Write a comment"><button class="btn btn-sm btn-gradient">Send</button></form></div></article>';
    }
    function load(reset) {
        if (reset) { st.page = 1; box.innerHTML = ''; }
        var p = new URLSearchParams({ action: 'list', type: st.type, scope: st.scope, q: st.q, page: st.page, club_id: (window.FEED && FEED.club) || '', user_id: (window.FEED && FEED.user) || '' });
        return SMS.api(API + '?' + p).then(function (r) {
            if (!r.success) { SMS.toast(r.message, 'danger'); return; }
            if (reset && !r.posts.length) box.innerHTML = '<div class="post empty"><i class="bi bi-chat-square-heart"></i>' + (st.q ? 'No posts match "' + esc(st.q) + '".' : 'Nothing here yet. Be the first to post!') + '</div>';
            box.insertAdjacentHTML('beforeend', r.posts.map(card).join('')); moreBtn.classList.toggle('d-none', !r.more);
            var h = location.hash.match(/^#p(\d+)$/); if (h && reset) focusPost(+h[1]);
        });
    }
    function focusPost(id) {
        var el = document.getElementById('p' + id);
        if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); el.style.boxShadow = '0 0 0 3px var(--coral)'; setTimeout(function () { el.style.boxShadow = ''; }, 2200); return; }
        SMS.api(API + '?action=post&id=' + id).then(function (r) { if (r.success && r.post) { box.insertAdjacentHTML('afterbegin', card(r.post)); focusPost(id); } });
    }
    function loadComments(art) {
        var id = art.dataset.id;
        SMS.api(API + '?action=comments&post_id=' + id).then(function (r) {
            art.querySelector('.clist').innerHTML = r.success ? (r.data.length ? r.data.map(function (c) {
                return '<div class="cmt" data-cid="' + c.id + '">' + SMS.avatar(c.author, c.author_color, 'sm') + '<div class="bubble"><div class="d-flex"><span class="who">' + esc(c.author) + '</span><span class="text-muted ms-2" style="font-size:.74rem">' + SMS.ago(c.created_at) + '</span>' +
                    '<span class="ms-auto">' + (c.status === 'Hidden' ? SMS.badge('Hidden') + ' ' : '') + (!c.mine ? '<button class="act p-0 d-inline" data-a="report-c" title="Report"><i class="bi bi-flag"></i></button> ' : '') + (c.can_delete ? '<button class="act p-0 d-inline" data-a="del-c" title="Delete"><i class="bi bi-trash3"></i></button>' : '') + '</span></div><div class="txt">' + linkify(c.body) + '</div></div></div>';
            }).join('') : '<div class="small text-muted mb-2">No comments yet.</div>') : '';
            art.querySelector('.cc').textContent = r.success ? r.data.filter(function (c) { return c.status === 'Published'; }).length : '';
        });
    }
    function act(art, a, btn) {
        var id = art.dataset.id;
        if (a === 'like') return SMS.api(API, { method: 'POST', body: { action: 'like', id: id } }).then(function (r) { if (!r.success) { SMS.toast(r.message, 'danger'); return; } btn.classList.toggle('on', r.liked == 1); btn.querySelector('i').className = 'bi ' + (r.liked == 1 ? 'bi-heart-fill' : 'bi-heart'); btn.querySelector('span').textContent = r.likes; });
        if (a === 'comments') { var c = art.querySelector('.comments'); c.classList.toggle('open'); if (c.classList.contains('open')) { loadComments(art); c.querySelector('input').focus(); } return; }
        if (a === 'report') { rp = { entity: 'post', id: id }; document.getElementById('rpWhat').textContent = 'post'; rm.show(); return; }
        if (a === 'delete') return SMS.confirmBox('Delete this post? This cannot be undone.', 'Delete').then(function (ok) { if (ok) SMS.api(API, { method: 'POST', body: { action: 'delete', id: id } }).then(function (r) { SMS.toast(r.message, r.success ? 'success' : 'danger'); if (r.success) art.remove(); }); });
        SMS.api(API, { method: 'POST', body: { action: a, id: id } }).then(function (r) { SMS.toast(r.message, r.success ? 'success' : 'danger'); if (r.success) load(true); });
    }
    box.addEventListener('click', function (e) {
        var b = e.target.closest('[data-a]'); if (!b) return; var art = b.closest('.post'); if (!art) return;
        if (b.dataset.a === 'report-c') { rp = { entity: 'comment', id: b.closest('.cmt').dataset.cid }; document.getElementById('rpWhat').textContent = 'comment'; rm.show(); return; }
        if (b.dataset.a === 'del-c') { SMS.api(API, { method: 'POST', body: { action: 'delete_comment', id: b.closest('.cmt').dataset.cid } }).then(function (r) { SMS.toast(r.message, r.success ? 'success' : 'danger'); if (r.success) loadComments(art); }); return; }
        act(art, b.dataset.a, b);
    });
    box.addEventListener('submit', function (e) {
        e.preventDefault(); var f = e.target, art = f.closest('.post'), inp = f.querySelector('input'); if (!inp.value.trim()) return;
        SMS.api(API, { method: 'POST', body: { action: 'comment', post_id: art.dataset.id, body: inp.value } }).then(function (r) { if (r.success) { inp.value = ''; loadComments(art); } else SMS.toast(r.message, 'danger'); });
    });
    document.getElementById('rpGo').addEventListener('click', function () {
        SMS.api(API, { method: 'POST', body: { action: 'report', entity: rp.entity, id: rp.id, reason: document.getElementById('rpReason').value, note: document.getElementById('rpNote').value } }).then(function (r) { SMS.toast(r.message, r.success ? 'success' : 'danger'); if (r.success) { rm.hide(); document.getElementById('rpNote').value = ''; } });
    });
    var tc = document.getElementById('typeChips'); if (tc) tc.addEventListener('click', function (e) { var c = e.target.closest('.chip'); if (!c) return; this.querySelectorAll('.chip').forEach(function (x) { x.classList.toggle('on', x === c); }); st.type = c.dataset.type; load(true); });
    var sc = document.getElementById('scope'); if (sc) sc.addEventListener('change', function () { st.scope = this.value; load(true); });
    moreBtn.addEventListener('click', function () { st.page++; load(false); });
    var cc = document.getElementById('composerCard') || document.createElement('div'); function open() { window.openCompose && window.openCompose(); }
    cc.addEventListener('click', open); cc.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); } });
    if (st.q && document.getElementById('searchNote')) { var n = document.getElementById('searchNote'); n.classList.remove('d-none'); n.innerHTML = 'Showing results for <b>' + esc(st.q) + '</b> · <a href="' + APP.base + '/feed">clear</a>'; }
    window.onPostCreated = function () { load(true); };
    load(true);
})();
