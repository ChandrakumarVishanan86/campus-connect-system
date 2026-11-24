<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
Auth::guard(['admin']);
$pageTitle = 'Moderation'; $pageSub = 'Reported posts and comments. ' . REPORTS_TO_HIDE . ' reports hide an item automatically until you decide.'; $activeNav = 'moderation'; $adminTab = 'moderation'; $pageScripts = ['assets/js/moderation.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php'; require __DIR__ . '/_tabs.php';
?>
<div id="modList"></div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php';
