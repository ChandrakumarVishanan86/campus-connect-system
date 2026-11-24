<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
Auth::guard(['admin']);
$pageTitle = 'Insights'; $pageSub = 'How the community is doing'; $activeNav = 'insights'; $adminTab = 'insights'; $libs = ['chart']; $pageScripts = ['assets/js/insights.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php'; require __DIR__ . '/_tabs.php';
?>
<div class="row g-3 mb-3" id="kpis"></div>
<div class="row g-3">
    <div class="col-lg-8"><div class="card-panel"><h6>Posts and comments - last 14 days</h6><div style="height:260px"><canvas id="chAct" role="img" aria-label="Daily posts and comments"></canvas></div></div></div>
    <div class="col-lg-4"><div class="card-panel"><h6>What people post</h6><div style="height:260px"><canvas id="chTypes" role="img" aria-label="Posts by type"></canvas></div></div></div>
    <div class="col-lg-6"><div class="card-panel"><h6>Club membership</h6><div style="height:300px"><canvas id="chClubs" role="img" aria-label="Members per club"></canvas></div></div></div>
    <div class="col-lg-6"><div class="card-panel"><h6>Event turnout (going vs checked in)</h6><div id="evTable"></div></div></div>
    <div class="col-lg-6"><div class="card-panel"><h6>Programmes</h6><div id="progTable"></div></div></div>
    <div class="col-lg-6"><div class="card-panel"><h6>Most active students (30 days)</h6><div id="leaders"></div></div></div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php';
