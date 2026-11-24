<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
Auth::guard(['admin']);
$pageTitle = 'Users'; $pageSub = 'Admins, lecturers and students'; $activeNav = 'a-users'; $adminTab = 'users'; $libs = ['datatables']; $pageScripts = ['assets/js/crud.js', 'assets/js/admin_users.js'];
$pageActions = '<button class="btn btn-gradient" id="btnAdd"><i class="bi bi-plus-lg me-1"></i>Add user</button>';
require_once dirname(__DIR__, 2) . '/includes/header.php'; require __DIR__ . '/_tabs.php';
$progs = db()->query('SELECT id, code FROM programmes WHERE is_active = 1 ORDER BY code')->fetchAll(PDO::FETCH_NUM);
?>
<div class="card-panel"><div class="table-responsive"><table id="tbl" class="table table-hover align-middle w-100"><thead><tr><th>Name</th><th>Role</th><th>Programme</th><th>Batch</th><th>Last login</th><th>Status</th><th></th></tr></thead></table></div></div>
<?php $pageConfig = '<script>window.PROGS = ' . json_encode($progs) . ';</script>'; require_once dirname(__DIR__, 2) . '/includes/footer.php';
