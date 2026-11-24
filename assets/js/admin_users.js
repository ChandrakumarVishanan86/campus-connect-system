(function () {
    'use strict'; var esc = SMS.esc, cls = { admin: 'danger', staff: 'plum', student: 'info' };
    new Crud({ table: '#tbl', api: APP.base + '/admin/users-api', label: 'User', canWrite: true, rowName: function (r) { return r.username; }, order: [[1, 'asc']],
        columns: [{ data: 'full_name', render: function (d, t, r) { return '<div class="d-flex align-items-center gap-2">' + SMS.avatar(d, r.color, 30) + '<div><div class="fw-semibold">' + esc(d) + '</div><div class="small text-muted">@' + esc(r.username) + '</div></div></div>'; } },
            { data: 'role', render: function (d) { return '<span class="badge badge-soft-' + (cls[d] || 'secondary') + '">' + esc(d) + '</span>'; } },
            { data: 'programme', defaultContent: '–' }, { data: 'batch', defaultContent: '–' },
            { data: 'last_login', render: function (d, t) { return t === 'display' ? (d ? SMS.fdatetime(d) : '<span class="text-muted">never</span>') : d; } },
            { data: 'is_active', render: function (d) { return d == 1 ? '<span class="badge badge-soft-success">Active</span>' : '<span class="badge badge-soft-secondary">Inactive</span>'; } }],
        fields: [{ name: 'full_name', label: 'Full name', required: true, max: 120 }, { name: 'username', label: 'Username', required: true, max: 50, unique: true, pattern: '^[a-z0-9._\\-]{3,50}$', patternMsg: '3-50 chars: a-z, 0-9 . _ -' },
            { name: 'role', label: 'Role', type: 'select', required: true, options: [['student', 'Student'], ['staff', 'Lecturer / staff'], ['admin', 'Admin']], col: 4 },
            { name: 'programme_id', label: 'Programme', type: 'select', options: window.PROGS, col: 4, help: 'Required for students.' }, { name: 'batch', label: 'Batch', type: 'number', col: 4 },
            { name: 'email', label: 'Email', type: 'email', max: 120, unique: true }, { name: 'phone', label: 'Phone', max: 20, pattern: '^[0-9+\\-\\s]{9,20}$', patternMsg: 'Enter a valid phone number.' },
            { name: 'title', label: 'Job title (staff)', max: 60 }, { name: 'password', label: 'Password', type: 'password', min: 0, minLen: 8, max: 72, placeholder: 'At least 8 characters', placeholderEdit: 'Leave blank to keep current' },
            { name: 'is_active', label: 'Active', type: 'checkbox', default: true }] });
})();
