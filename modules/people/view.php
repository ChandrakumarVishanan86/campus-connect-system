<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::ALL);
$p = UserModel::profile((int) ($_GET['id'] ?? 0), $user);
if (!$p) { http_response_code(404); $pageTitle = 'Not found'; $activeNav = 'people'; require_once dirname(__DIR__, 2) . '/includes/header.php'; echo '<div class="post empty"><i class="bi bi-person-x"></i>This person does not exist.<div class="mt-3"><a class="btn btn-gradient" href="' . url('people') . '">Back to people</a></div></div>'; require_once dirname(__DIR__, 2) . '/includes/footer.php'; exit; }
$pageTitle = $p['full_name']; $pageSub = $p['role'] === 'student' ? ($p['programme'] ?? '') . ($p['batch'] ? ' · Batch ' . $p['batch'] : '') : ($p['title'] ?: ucfirst($p['role'])); $activeNav = 'people'; $rail = false;
$pageActions = $p['mine'] ? '<a class="btn btn-gradient" href="' . url('profile') . '"><i class="bi bi-pencil me-1"></i>Edit profile</a>' : '<a class="btn btn-soft" href="' . url('people') . '"><i class="bi bi-arrow-left me-1"></i>People</a>';
$pageScripts = ['assets/js/feed.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="card-panel mb-3">
    <div class="d-flex flex-wrap gap-3 align-items-center">
        <?= avatar($p['full_name'], (int) $p['color'], 'xl') ?>
        <div class="flex-grow-1 min-w-0"><h3 class="mb-0" style="font-family:var(--head)"><?= e($p['full_name']) ?> <?php if ($p['role'] !== 'student'): ?><span class="badge badge-soft-plum fs-6 align-middle"><?= $p['role'] === 'admin' ? 'Admin' : 'Staff' ?></span><?php endif; ?></h3>
            <div class="text-muted"><?= e($p['role'] === 'student' ? ($p['programme_name'] ?? '') . ($p['batch'] ? ' · Batch ' . $p['batch'] : '') : ($p['title'] ?? '')) ?></div>
            <?php if ($p['bio']): ?><p class="mt-2 mb-1"><?= e($p['bio']) ?></p><?php endif; ?>
            <?php if ($p['interests']): ?><div class="mt-1"><?php foreach (array_filter(array_map('trim', explode(',', $p['interests']))) as $i): ?><span class="badge badge-soft-teal me-1"><?= e($i) ?></span><?php endforeach; ?></div><?php endif; ?>
            <?php if (!empty($p['email']) || !empty($p['phone'])): ?><div class="small text-muted mt-2"><?= !empty($p['email']) ? '<i class="bi bi-envelope me-1"></i>' . e($p['email']) : '' ?> <?= !empty($p['phone']) ? '<i class="bi bi-telephone ms-2 me-1"></i>' . e($p['phone']) : '' ?></div><?php endif; ?></div>
    </div>
    <div class="row text-center g-2 mt-3"><?php foreach (['posts' => 'Posts', 'comments' => 'Comments', 'attended' => 'Events attended', 'likes_received' => 'Likes received'] as $k => $l): ?><div class="col-6 col-md-3"><div class="fs-4 fw-bold" style="font-family:var(--head)"><?= (int) $p['stats'][$k] ?></div><div class="small text-muted"><?= $l ?></div></div><?php endforeach; ?></div>
</div>
<div class="row g-3"><div class="col-lg-4 order-lg-2"><div class="card-panel"><h6>Clubs</h6><?php if ($p['clubs']): foreach ($p['clubs'] as $c): ?><a class="d-flex justify-content-between py-1" href="<?= url('clubs/' . (int) $c['id']) ?>"><span><?= e($c['name']) ?></span><?php if ($c['role'] !== 'Member'): ?><span class="badge badge-soft-plum"><?= e($c['role']) ?></span><?php endif; ?></a><?php endforeach; else: ?><div class="text-muted small">Not in any club yet.</div><?php endif; ?></div></div>
<div class="col-lg-8 order-lg-1"><h6 class="mb-2">Posts</h6><div id="feed"></div><div class="text-center my-3"><button class="btn btn-soft d-none" id="more">Load more</button></div></div></div>
<div class="modal fade" id="reportModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content p-3">
    <h5 class="mb-3">Report this <span id="rpWhat">post</span></h5>
    <label class="form-label" for="rpReason">Reason</label><select class="form-select mb-2" id="rpReason"><?php foreach (PostModel::REASONS as $r): ?><option><?= e($r) ?></option><?php endforeach; ?></select>
    <label class="form-label" for="rpNote">Details (optional)</label><input class="form-control" id="rpNote" maxlength="200">
    <div class="d-flex gap-2 mt-3"><button class="btn btn-light flex-fill" data-bs-dismiss="modal">Cancel</button><button class="btn btn-coral flex-fill" id="rpGo">Report</button></div>
</div></div></div>
<?php $pageConfig = '<script>window.FEED = ' . json_encode(['user' => (int) $p['id']]) . ';</script>';
require_once dirname(__DIR__, 2) . '/includes/footer.php';
