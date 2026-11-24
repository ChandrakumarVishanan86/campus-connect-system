<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::ALL);
$pageTitle = 'Campus feed'; $pageSub = 'What is happening around ' . CAMPUS_NAME; $activeNav = 'feed'; $rail = true; $pageScripts = ['assets/js/feed.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
$types = ['' => 'All', 'Announcement' => 'Announcements', 'Question' => 'Questions', 'Achievement' => 'Achievements', 'Opportunity' => 'Opportunities', 'Discussion' => 'Discussions'];
?>
<div class="post composer" id="composerCard" role="button" tabindex="0" aria-label="Create a post">
    <?= avatar($user['full_name'], (int) $user['color']) ?>
    <div class="flex-grow-1"><div class="text-muted" style="background:var(--surface2);border-radius:14px;padding:.85rem 1rem;cursor:text">What is on your mind, <?= e(explode(' ', $user['full_name'])[0]) ?>?</div>
        <div class="tools"><span class="badge badge-soft-plum"><i class="bi bi-megaphone me-1"></i>Announce</span><span class="badge badge-soft-info"><i class="bi bi-question-circle me-1"></i>Ask</span><span class="badge badge-soft-gold"><i class="bi bi-trophy me-1"></i>Celebrate</span><span class="badge badge-soft-teal"><i class="bi bi-briefcase me-1"></i>Opportunity</span></div></div>
</div>
<div class="d-flex flex-wrap gap-2 align-items-center mb-2">
    <div class="chips flex-grow-1 mb-0" id="typeChips"><?php foreach ($types as $k => $l): ?><button class="chip<?= $k === '' ? ' on' : '' ?>" data-type="<?= e($k) ?>"><?= e($l) ?></button><?php endforeach; ?></div>
    <select class="form-select form-select-sm" id="scope" style="width:auto" aria-label="Show"><option value="">Everything</option><option value="clubs">My clubs</option><option value="mine">My posts</option><?php if ($user['role'] === 'admin'): ?><option value="hidden">Hidden (moderation)</option><?php endif; ?></select>
</div>
<div id="searchNote" class="small text-muted mb-2 d-none"></div>
<div id="feed" class="mt-3"></div>
<div class="text-center my-3"><button class="btn btn-soft d-none" id="more">Load more</button></div>
<div class="modal fade" id="reportModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content p-3">
    <h5 class="mb-3">Report this <span id="rpWhat">post</span></h5>
    <label class="form-label" for="rpReason">Reason</label><select class="form-select mb-2" id="rpReason"><?php foreach (PostModel::REASONS as $r): ?><option><?= e($r) ?></option><?php endforeach; ?></select>
    <label class="form-label" for="rpNote">Details (optional)</label><input class="form-control" id="rpNote" maxlength="200">
    <div class="d-flex gap-2 mt-3"><button class="btn btn-light flex-fill" data-bs-dismiss="modal">Cancel</button><button class="btn btn-coral flex-fill" id="rpGo">Report</button></div>
</div></div></div>
<?php
$pageConfig = '<script>window.FEED = ' . json_encode(['q' => (string) ($_GET['q'] ?? '')]) . ';</script>';
require_once dirname(__DIR__, 2) . '/includes/footer.php';
