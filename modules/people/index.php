<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::ALL);
$pageTitle = 'People'; $pageSub = 'Students, lecturers and staff'; $activeNav = 'people'; $rail = false; $pageScripts = ['assets/js/people.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
$progs = db()->query('SELECT id, code FROM programmes WHERE is_active = 1 ORDER BY code')->fetchAll();
?>
<div class="row g-2 mb-3">
    <div class="col-md-5"><input class="form-control" id="pQ" placeholder="Search by name, username or interest" aria-label="Search people"></div>
    <div class="col-6 col-md-3"><select class="form-select" id="pRole" aria-label="Role"><option value="">Everyone</option><option value="student">Students</option><option value="staff">Lecturers &amp; staff</option></select></div>
    <div class="col-6 col-md-4"><select class="form-select" id="pProg" aria-label="Programme"><option value="">All programmes</option><?php foreach ($progs as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['code']) ?></option><?php endforeach; ?></select></div>
</div>
<div class="row g-3" id="pGrid"></div>
<div class="small text-muted mt-3" id="pNote"></div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php';
