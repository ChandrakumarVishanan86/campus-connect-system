<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
Auth::guard(['admin']);
$pageTitle = 'Programmes'; $pageSub = 'Used for audience targeting and the directory'; $activeNav = 'a-programmes'; $adminTab = 'programmes'; $libs = ['datatables']; $pageScripts = ['assets/js/crud.js', 'assets/js/admin_programmes.js'];
$pageActions = '<button class="btn btn-gradient" id="btnAdd"><i class="bi bi-plus-lg me-1"></i>Add programme</button>';
require_once dirname(__DIR__, 2) . '/includes/header.php'; require __DIR__ . '/_tabs.php';
?>
<div class="card-panel"><div class="table-responsive"><table id="tbl" class="table table-hover align-middle w-100"><thead><tr><th>Code</th><th>Name</th><th class="text-center">People</th><th>Status</th><th></th></tr></thead></table></div></div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php';
