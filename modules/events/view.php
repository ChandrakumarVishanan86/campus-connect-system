<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::ALL);
$ev = EventModel::find((int) ($_GET['id'] ?? 0), $user);
if (!$ev || !EventModel::canView($ev, $user)) { http_response_code(404); $pageTitle = 'Event not found'; $activeNav = 'events'; require_once dirname(__DIR__, 2) . '/includes/header.php'; echo '<div class="post empty"><i class="bi bi-calendar-x"></i>This event does not exist or is not public yet.<div class="mt-3"><a class="btn btn-gradient" href="' . url('events') . '">Back to events</a></div></div>'; require_once dirname(__DIR__, 2) . '/includes/footer.php'; exit; }
$pageTitle = $ev['title']; $pageSub = $ev['club_name'] ? 'Hosted by ' . $ev['club_name'] : 'Organised by ' . $ev['organiser']; $activeNav = 'events'; $libs = ['flatpickr']; $rail = false;
$pageActions = '<a class="btn btn-soft" href="' . url('events') . '"><i class="bi bi-arrow-left me-1"></i>All events</a>';
$pageScripts = ['assets/js/event_form.js', 'assets/js/event_view.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div id="evView"></div>
<?php include __DIR__ . '/_form.php';
$pageConfig = '<script>window.EVID = ' . (int) $ev['id'] . ';</script>';
require_once dirname(__DIR__, 2) . '/includes/footer.php';
