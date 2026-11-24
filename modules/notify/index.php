<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::ALL);
$pageTitle = 'Notifications'; $pageSub = 'Replies, approvals and reminders'; $activeNav = 'notifications'; $rail = false; $pageScripts = ['assets/js/notify.js'];
$pageActions = '<button class="btn btn-soft" id="markAll"><i class="bi bi-check2-all me-1"></i>Mark all as read</button>';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="card-panel p-0" id="nList" style="max-width:760px"></div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php';
