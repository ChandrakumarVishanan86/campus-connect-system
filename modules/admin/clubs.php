<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
Auth::guard(['admin']);
$pageTitle = 'Manage clubs'; $pageSub = 'Create clubs, assign advisors and join policies'; $activeNav = 'a-clubs'; $adminTab = 'clubs'; $libs = ['datatables']; $pageScripts = ['assets/js/crud.js', 'assets/js/admin_clubs.js'];
$pageActions = '<button class="btn btn-gradient" id="btnAdd"><i class="bi bi-plus-lg me-1"></i>Add club</button>';
require_once dirname(__DIR__, 2) . '/includes/header.php'; require __DIR__ . '/_tabs.php';
$staff = db()->query("SELECT id, full_name FROM users WHERE role IN ('staff','admin') AND is_active = 1 ORDER BY full_name")->fetchAll(PDO::FETCH_NUM);
?>
<div class="card-panel"><div class="table-responsive"><table id="tbl" class="table table-hover align-middle w-100"><thead><tr><th>Code</th><th>Club</th><th>Category</th><th>Advisor</th><th class="text-center">Members</th><th>Policy</th><th>Status</th><th></th></tr></thead></table></div></div>
<?php $pageConfig = '<script>window.STAFF = ' . json_encode($staff) . ';</script>'; require_once dirname(__DIR__, 2) . '/includes/footer.php';
