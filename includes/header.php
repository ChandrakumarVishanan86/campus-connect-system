<?php
/**
 * Shared layout: top bar + left rail (+ optional right rail). Pages may set before including:
 *   $pageTitle, $pageSub, $pageActions (html), $activeNav, $libs ['flatpickr','chart'], $rail (bool)
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
$user      = Auth::guard();
$role      = $user['role'];
$pageTitle = $pageTitle ?? 'Feed';
$activeNav = $activeNav ?? '';
$libs      = $libs ?? [];
$rail      = $rail ?? false;
$unread    = NotifyModel::unread((int) $user['id']);
$pendingEv = $role === 'admin' ? (int) db()->query("SELECT COUNT(*) FROM events WHERE status = 'Pending'")->fetchColumn() : 0;
$openRep   = $role === 'admin' ? (int) db()->query("SELECT COUNT(DISTINCT entity, entity_id) FROM reports WHERE status = 'Open'")->fetchColumn() : 0;
// [key, label, icon, href, roles, badge]
$nav = [
    ['feed', 'Feed', 'bi-house-heart-fill', 'feed', Auth::ALL, 0],
    ['events', 'Events', 'bi-calendar-event-fill', 'events', Auth::ALL, $pendingEv],
    ['clubs', 'Clubs', 'bi-people-fill', 'clubs', Auth::ALL, 0],
    ['lost', 'Lost & Found', 'bi-search-heart-fill', 'lost-found', Auth::ALL, 0],
    ['people', 'People', 'bi-person-lines-fill', 'people', Auth::ALL, 0],
    ['notifications', 'Notifications', 'bi-bell-fill', 'notifications', Auth::ALL, $unread],
];
$adminNav = [
    ['moderation', 'Moderation', 'bi-shield-check', 'admin/moderation', $openRep],
    ['insights', 'Insights', 'bi-graph-up-arrow', 'admin/insights', 0],
    ['a-users', 'Users', 'bi-person-gear', 'admin/users', 0],
    ['a-clubs', 'Manage clubs', 'bi-collection-fill', 'admin/clubs', 0],
    ['a-programmes', 'Programmes', 'bi-mortarboard-fill', 'admin/programmes', 0],
    ['a-audit', 'Audit log', 'bi-journal-text', 'admin/audit', 0],
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
    <script>try{var t=localStorage.getItem('cc_theme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<?php if (in_array('datatables', $libs, true)): ?>
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<?php endif; ?>
<?php if (in_array('flatpickr', $libs, true)): ?>
    <link href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css" rel="stylesheet">
<?php endif; ?>
    <link href="<?= url('assets/css/style.css') ?>?v=1" rel="stylesheet">
</head>
<body class="cc-body">
<header class="cc-top"><div class="cc-top-in">
    <a class="brand" href="<?= url('feed') ?>"><span class="brand-mark"><i class="bi bi-chat-heart-fill"></i></span><span>Campus<b>Connect</b></span></a>
    <form class="cc-search" action="<?= url('feed') ?>" method="get" role="search"><i class="bi bi-search"></i><input name="q" placeholder="Search posts, people, topics…" autocomplete="off" aria-label="Search the feed" value="<?= e($_GET['q'] ?? '') ?>"></form>
    <div class="top-tools">
        <a class="icon-btn" href="<?= url('notifications') ?>" title="Notifications" aria-label="Notifications"><i class="bi bi-bell"></i><?php if ($unread): ?><span class="dot" id="bellDot"><?= $unread > 99 ? '99+' : $unread ?></span><?php endif; ?></a>
        <button class="icon-btn" type="button" id="themeBtn" title="Toggle dark mode" aria-label="Toggle dark mode"><i class="bi bi-moon-stars"></i></button>
        <div class="dropdown">
            <button class="border-0 bg-transparent p-0" data-bs-toggle="dropdown" aria-label="Account"><?= avatar($user['full_name'], (int) $user['color']) ?></button>
            <ul class="dropdown-menu dropdown-menu-end p-2" style="min-width:230px">
                <li class="px-2 py-1"><div class="fw-bold"><?= e($user['full_name']) ?></div><span class="badge badge-soft-plum"><?= e(ucfirst($role)) ?></span></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= url('people/' . (int) $user['id']) ?>"><i class="bi bi-person-circle me-2"></i>My profile</a></li>
                <li><a class="dropdown-item" href="<?= url('profile') ?>"><i class="bi bi-pencil-square me-2"></i>Edit profile &amp; password</a></li>
                <li><a class="dropdown-item" href="<?= url('logout') ?>" data-confirm="Sign out of your account?"><i class="bi bi-box-arrow-right me-2"></i>Sign out</a></li>
            </ul>
        </div>
    </div>
</div></header>
<div class="cc-shell<?= $rail ? ' has-rail' : '' ?>">
    <aside class="cc-side">
        <button class="btn-create" type="button" id="btnCompose"><i class="bi bi-plus-lg me-1"></i> Create post</button>
<?php foreach ($nav as [$key, $label, $icon, $href, $roles, $badge]): if (!in_array($role, $roles, true)) continue; ?>
        <a class="nl <?= $activeNav === $key ? 'active' : '' ?>" href="<?= url($href) ?>"><i class="bi <?= $icon ?>"></i><?= e($label) ?><?php if ($badge): ?><span class="nb"><?= (int) $badge ?></span><?php endif; ?></a>
<?php endforeach; ?>
<?php if ($role === 'admin'): ?>
        <div class="side-sep">Administration</div>
<?php foreach ($adminNav as [$key, $label, $icon, $href, $badge]): ?>
        <a class="nl <?= $activeNav === $key ? 'active' : '' ?>" href="<?= url($href) ?>"><i class="bi <?= $icon ?>"></i><?= e($label) ?><?php if ($badge): ?><span class="nb"><?= (int) $badge ?></span><?php endif; ?></a>
<?php endforeach; endif; ?>
    </aside>
    <main class="cc-main fade-in">
<?php foreach (pull_flash() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show shadow-sm" role="alert"><?= e($f['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endforeach; ?>
<?php if ($pageTitle !== false && empty($noHead)): ?>
        <div class="pagehead">
            <div><h1 class="page-title"><?= e($pageTitle) ?></h1><?php if (!empty($pageSub)): ?><div class="sub"><?= e($pageSub) ?></div><?php endif; ?></div>
            <?php if (!empty($pageActions)): ?><div class="actions"><?= $pageActions ?></div><?php endif; ?>
        </div>
<?php endif; ?>
