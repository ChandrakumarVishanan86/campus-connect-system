<?php
/** Closes the layout opened in header.php, renders the optional right rail and loads scripts. */
declare(strict_types=1);
$libs        = $libs ?? [];
$pageScripts = $pageScripts ?? [];
$rail        = $rail ?? false;
$user        = Auth::user();
$role        = $user['role'] ?? '';
$tabs = [['feed', 'Feed', 'bi-house-heart-fill', 'feed'], ['events', 'Events', 'bi-calendar-event-fill', 'events'], ['clubs', 'Clubs', 'bi-people-fill', 'clubs'], ['lost', 'Lost', 'bi-search-heart-fill', 'lost-found'], ['people', 'People', 'bi-person-lines-fill', 'people']];
?>
    </main>
<?php if ($rail): ?>
    <aside class="cc-rail" id="rail">
        <div class="widget"><h6><i class="bi bi-calendar-event me-1"></i>Coming up</h6><div id="railEvents" class="small text-muted">Loading…</div></div>
        <div class="widget"><h6><i class="bi bi-people me-1"></i>My clubs</h6><div id="railClubs" class="small text-muted">Loading…</div></div>
        <div class="widget"><h6><i class="bi bi-search-heart me-1"></i>Lost &amp; found</h6><div id="railLost" class="small text-muted">Loading…</div></div>
        <div class="cc-foot p-0 text-start">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> · by Chandra Kumar Vishanan</div>
    </aside>
<?php endif; ?>
</div>
<nav class="tabbar" aria-label="Main">
<?php foreach ($tabs as [$k, $l, $i, $h]): ?><a href="<?= url($h) ?>" class="<?= ($activeNav ?? '') === $k ? 'active' : '' ?>"><i class="bi <?= $i ?>"></i><?= $l ?></a><?php endforeach; ?>
</nav>
<div class="toast-container position-fixed top-0 end-0 p-3" id="toastHost" style="z-index:2000"></div>
<script>window.APP = { base: <?= json_encode(APP_BASE) ?>, csrf: <?= json_encode(csrf_token()) ?>, role: <?= json_encode($role) ?>, me: <?= json_encode((int) ($user['id'] ?? 0)) ?>, programme: <?= json_encode($user['programme_id'] ?? null) ?>, limits: { post: <?= POST_MAX ?>, comment: <?= COMMENT_MAX ?> } };</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (in_array('datatables', $libs, true)): ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<?php endif; ?>
<?php if (in_array('flatpickr', $libs, true)): ?>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<?php endif; ?>
<?php if (in_array('chart', $libs, true)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<?php endif; ?>
<script src="<?= url('assets/js/main.js') ?>?v=1"></script>
<?php if (in_array($role, ['admin', 'staff', 'student'], true) && empty($noCompose)): ?>
<?php require __DIR__ . '/compose_modal.php'; ?>
<script src="<?= url('assets/js/compose.js') ?>?v=1"></script>
<?php endif; ?>
<?php if ($rail): ?><script src="<?= url('assets/js/rail.js') ?>?v=1"></script><?php endif; ?>
<?= $pageConfig ?? '' ?>
<?php foreach ($pageScripts as $src): ?>
<script src="<?= url($src) ?>?v=1"></script>
<?php endforeach; ?>
<?= $pageInline ?? '' ?>
</body>
</html>
