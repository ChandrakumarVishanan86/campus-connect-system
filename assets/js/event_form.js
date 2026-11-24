/* Shared create/edit event modal + reason prompt */
window.EventForm = (function () {
    'use strict';
    var API = APP.base + '/events/api', form = document.getElementById('evForm'), modal = new bootstrap.Modal(document.getElementById('evModal')),
        rsModal = new bootstrap.Modal(document.getElementById('reasonModal')), after = null, rsCb = null, fp = {};
    function pick(id) { return flatpickr('#' + id, { enableTime: true, time_24hr: true, dateFormat: 'Y-m-d H:i', minuteIncrement: 15, minDate: 'today', maxDate: new Date(Date.now() + 180 * 864e5), disableMobile: true }); }
    fp.s = pick('evStart'); fp.e = pick('evEnd');
    fp.s.config.onChange.push(function (d) { if (d[0] && !fp.e.selectedDates.length) { var t = new Date(d[0].getTime() + 2 * 36e5); fp.e.setDate(t); } });
    function val(n) { return form.elements[n].value; }
    function open(ev, cb) {
        after = cb; form.reset(); document.getElementById('evErr').textContent = ''; fp.s.clear(); fp.e.clear();
        document.getElementById('evModalTitle').textContent = ev ? 'Edit event' : (APP.role === 'student' ? 'Propose an event' : 'Create event');
        document.getElementById('evId').value = ev ? ev.id : '';
        if (ev) {
            form.elements.title.value = ev.title; form.elements.category.value = ev.category; form.elements.venue.value = ev.venue; form.elements.capacity.value = ev.capacity || ''; form.elements.description.value = ev.description || '';
            form.elements.club_id.value = ev.club_id || ''; form.elements.club_id.disabled = true; fp.s.setDate(ev.starts_at.slice(0, 16)); fp.e.setDate(ev.ends_at.slice(0, 16));
        } else { form.elements.club_id.disabled = false; if (window.EV && EV.clubId && form.elements.club_id.querySelector('option[value="' + EV.clubId + '"]')) form.elements.club_id.value = EV.clubId; }
        modal.show();
    }
    form.addEventListener('submit', function (e) {
        e.preventDefault(); var err = document.getElementById('evErr'), btn = document.getElementById('evSave'); err.textContent = '';
        if (val('title').trim().length < 3) { err.textContent = 'Please enter a title (at least 3 characters).'; return; }
        if (!val('starts_at') || !val('ends_at')) { err.textContent = 'Pick the start and end time.'; return; }
        btn.disabled = true;
        var body = { action: 'save', id: val('id'), title: val('title'), category: val('category'), venue: val('venue'), starts_at: val('starts_at'), ends_at: val('ends_at'), capacity: val('capacity'), club_id: form.elements.club_id.disabled ? '' : val('club_id'), description: val('description') };
        SMS.api(API, { method: 'POST', body: body }).then(function (r) {
            btn.disabled = false; if (!r.success) { err.textContent = r.message; return; }
            modal.hide(); SMS.toast(r.message, 'success'); after && after(r);
        });
    });
    /** Ask for a short written reason, then call cb(reason). */
    function reason(title, cb) {
        document.getElementById('rsTitle').textContent = title; document.getElementById('rsText').value = ''; document.getElementById('rsErr').textContent = ''; rsCb = cb; rsModal.show();
    }
    document.getElementById('rsGo').addEventListener('click', function () {
        var t = document.getElementById('rsText').value.trim(); if (t.length < 5) { document.getElementById('rsErr').textContent = 'Please write at least 5 characters.'; return; }
        rsModal.hide(); rsCb && rsCb(t);
    });
    return { open: open, reason: reason };
})();
