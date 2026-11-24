(function () {
    'use strict'; var esc = SMS.esc;
    new Crud({ table: '#tbl', api: APP.base + '/admin/programmes-api', label: 'Programme', canWrite: true, rowName: function (r) { return r.code; },
        columns: [{ data: 'code', render: function (d) { return '<b>' + esc(d) + '</b>'; } }, { data: 'name' }, { data: 'people', className: 'text-center' },
            { data: 'is_active', render: function (d) { return d == 1 ? '<span class="badge badge-soft-success">Active</span>' : '<span class="badge badge-soft-secondary">Inactive</span>'; } }],
        fields: [{ name: 'code', label: 'Code', required: true, max: 10, upper: true, unique: true, pattern: '^[A-Z0-9]{2,10}$', patternMsg: '2-10 capital letters or digits.', col: 4 },
            { name: 'name', label: 'Programme name', required: true, max: 100, unique: true, col: 8 }, { name: 'is_active', label: 'Active', type: 'checkbox', default: true }] });
})();
