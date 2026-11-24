(function () {
    'use strict'; var esc = SMS.esc;
    new Crud({ table: '#tbl', api: APP.base + '/admin/clubs-api', label: 'Club', canWrite: true, rowName: function (r) { return r.name; }, order: [[1, 'asc']],
        columns: [{ data: 'code', render: function (d) { return '<b>' + esc(d) + '</b>'; } }, { data: 'name', render: function (d, t, r) { return '<a href="' + APP.base + '/clubs/' + r.id + '">' + esc(d) + '</a>'; } }, { data: 'category' }, { data: 'advisor', defaultContent: '–' }, { data: 'members', className: 'text-center', defaultContent: '0' },
            { data: 'join_policy', render: function (d) { return d === 'Open' ? 'Open' : 'Approval'; } },
            { data: 'is_active', render: function (d) { return d == 1 ? '<span class="badge badge-soft-success">Active</span>' : '<span class="badge badge-soft-secondary">Inactive</span>'; } }],
        fields: [{ name: 'code', label: 'Code', required: true, max: 10, upper: true, unique: true, pattern: '^[A-Z0-9]{2,10}$', patternMsg: '2-10 capital letters or digits.', col: 4 }, { name: 'name', label: 'Club name', required: true, max: 100, col: 8 },
            { name: 'category', label: 'Category', type: 'select', required: true, options: [['Academic', 'Academic'], ['Sports', 'Sports'], ['Arts', 'Arts'], ['Service', 'Service'], ['Tech', 'Tech'], ['Cultural', 'Cultural']], col: 4 },
            { name: 'join_policy', label: 'Join policy', type: 'select', required: true, options: [['Open', 'Open to all'], ['Approval', 'Officer approval']], col: 4 }, { name: 'advisor_id', label: 'Advisor', type: 'select', options: window.STAFF, col: 4 },
            { name: 'meeting_info', label: 'Meeting info', max: 160 }, { name: 'description', label: 'Description', type: 'textarea', max: 500 }, { name: 'is_active', label: 'Active', type: 'checkbox', default: true }] });
})();
