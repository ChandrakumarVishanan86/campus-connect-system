<?php
/** Global "Create post" modal (opened from the left rail, the feed composer and club pages). */
$_u = Auth::user();
if ($_u['role'] === 'admin') { $_clubs = db()->query('SELECT id, name FROM clubs WHERE is_active = 1 ORDER BY name')->fetchAll(); }
else {
    $_s = db()->prepare("SELECT c.id, c.name FROM clubs c WHERE c.is_active = 1 AND (c.advisor_id = ? OR EXISTS (SELECT 1 FROM club_members m WHERE m.club_id = c.id AND m.user_id = ? AND m.status = 'Active' AND m.role IN ('President','Officer'))) ORDER BY c.name");
    $_s->execute([(int) $_u['id'], (int) $_u['id']]); $_clubs = $_s->fetchAll();
}
$_progs = in_array($_u['role'], Auth::STAFF, true) ? db()->query('SELECT id, code FROM programmes WHERE is_active = 1 ORDER BY code')->fetchAll() : [];
$_types = in_array($_u['role'], Auth::STAFF, true) ? PostModel::TYPES : array_values(array_diff(PostModel::TYPES, ['Announcement']));
?>
<div class="modal fade" id="composeModal" tabindex="-1" aria-labelledby="composeTitle"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" id="composeForm" novalidate>
    <div class="modal-header border-0 pb-0"><h5 class="modal-title" id="composeTitle">Create a post</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
        <div class="row g-2 mb-2">
            <div class="col-sm-6"><label class="form-label" for="cpType">Type</label><select class="form-select" id="cpType" name="type"><?php foreach ($_types as $t): ?><option><?= e($t) ?></option><?php endforeach; ?></select></div>
            <div class="col-sm-6"><label class="form-label" for="cpClub">Post as</label><select class="form-select" id="cpClub" name="club_id"><option value="">Myself</option><?php foreach ($_clubs as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?> (club)</option><?php endforeach; ?></select></div>
        </div>
        <div class="mb-2 d-none" id="cpTitleWrap"><label class="form-label" for="cpTitle">Title</label><input class="form-control" id="cpTitle" name="title" maxlength="120" placeholder="Announcement headline"></div>
<?php if ($_progs): ?>
        <div class="mb-2"><label class="form-label" for="cpProg">Audience</label><select class="form-select" id="cpProg" name="programme_id"><option value="">Everyone</option><?php foreach ($_progs as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['code']) ?> students only</option><?php endforeach; ?></select></div>
<?php endif; ?>
        <label class="form-label" for="cpBody">What is on your mind?</label>
        <textarea class="form-control" id="cpBody" name="body" rows="5" maxlength="<?= POST_MAX ?>" placeholder="Share an update, ask a question or celebrate a win…"></textarea>
        <div class="d-flex justify-content-between small text-muted mt-1"><span class="text-danger" id="cpErr"></span><span id="cpCount">0 / <?= POST_MAX ?></span></div>
    </div>
    <div class="modal-footer border-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-gradient" id="cpGo"><i class="bi bi-send me-1"></i>Post</button></div>
</form></div></div>
