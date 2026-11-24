<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::ALL);
$pageTitle = 'Events'; $pageSub = 'Workshops, matches, shows and meet-ups'; $activeNav = 'events'; $libs = ['flatpickr']; $rail = true;
$pageActions = '<button class="btn btn-gradient" id="btnNew"><i class="bi bi-plus-lg me-1"></i>' . ($user['role'] === 'student' ? 'Propose event' : 'Create event') . '</button>';
$pageScripts = ['assets/js/event_form.js', 'assets/js/events.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
$tabs = ['upcoming' => 'Upcoming', 'calendar' => 'Calendar', 'mine' => 'My RSVPs', 'organising' => 'Organising', 'pending' => $user['role'] === 'admin' ? 'To approve' : 'My pending', 'past' => 'Past'];
?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <div class="chips flex-grow-1 mb-0" id="evTabs" role="tablist"><?php foreach ($tabs as $k => $l): ?><button class="chip<?= $k === 'upcoming' ? ' on' : '' ?>" data-scope="<?= $k ?>" role="tab"><?= e($l) ?><?= $k === 'pending' ? ' <span class="badge text-bg-danger d-none" id="pendCount"></span>' : '' ?></button><?php endforeach; ?></div>
    <select class="form-select form-select-sm" id="evCategory" style="width:auto" aria-label="Category"><option value="">All categories</option><?php foreach (EventModel::CATEGORIES as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select>
    <input class="form-control form-control-sm" id="evSearch" style="width:170px" placeholder="Search events" aria-label="Search events">
</div>
<div id="evList"></div>
<div id="evCal" class="d-none">
    <div class="d-flex align-items-center gap-2 mb-2"><button class="btn btn-soft btn-sm" id="calPrev" aria-label="Previous month"><i class="bi bi-chevron-left"></i></button><h5 class="mb-0 flex-grow-1 text-center" id="calTitle"></h5><button class="btn btn-soft btn-sm" id="calNext" aria-label="Next month"><i class="bi bi-chevron-right"></i></button><button class="btn btn-soft btn-sm" id="calToday">Today</button></div>
    <div class="cal" id="calGrid"></div>
</div>
<?php include __DIR__ . '/_form.php';
$pageConfig = '<script>window.EV = ' . json_encode(['clubId' => (int) ($_GET['club'] ?? 0)]) . ';</script>';
require_once dirname(__DIR__, 2) . '/includes/footer.php';
