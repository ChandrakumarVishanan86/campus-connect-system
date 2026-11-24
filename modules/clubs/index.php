<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::ALL);
$pageTitle = 'Clubs & societies'; $pageSub = 'Find your people'; $activeNav = 'clubs'; $rail = true; $pageScripts = ['assets/js/clubs.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
$cats = db()->query('SELECT DISTINCT category FROM clubs WHERE is_active = 1 ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);
?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <div class="chips flex-grow-1 mb-0" id="clubChips"><button class="chip on" data-cat="">All</button><button class="chip" data-cat="__mine">My clubs</button><?php foreach ($cats as $c): ?><button class="chip" data-cat="<?= e($c) ?>"><?= e($c) ?></button><?php endforeach; ?></div>
    <input class="form-control form-control-sm" id="clubSearch" style="width:180px" placeholder="Search clubs" aria-label="Search clubs">
</div>
<div class="row g-3" id="clubGrid"></div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php';
