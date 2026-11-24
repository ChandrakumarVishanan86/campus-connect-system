<?php
/** Create / edit event modal (included by index.php and view.php). Needs $user. */
if ($user['role'] === 'admin') { $_mc = db()->query('SELECT id, name FROM clubs WHERE is_active = 1 ORDER BY name')->fetchAll(); }
else {
    $_s = db()->prepare("SELECT c.id, c.name FROM clubs c WHERE c.is_active = 1 AND (c.advisor_id = ? OR EXISTS (SELECT 1 FROM club_members m WHERE m.club_id = c.id AND m.user_id = ? AND m.status = 'Active' AND m.role IN ('President','Officer'))) ORDER BY c.name");
    $_s->execute([(int) $user['id'], (int) $user['id']]); $_mc = $_s->fetchAll();
}
?>
<div class="modal fade" id="evModal" tabindex="-1" aria-labelledby="evModalTitle"><div class="modal-dialog modal-dialog-centered modal-lg"><form class="modal-content" id="evForm" novalidate>
    <div class="modal-header border-0 pb-0"><h5 class="modal-title" id="evModalTitle">Create event</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body"><input type="hidden" name="id" id="evId">
        <?php if (!in_array($user['role'], Auth::STAFF, true)): ?><div class="alert alert-info py-2 small"><i class="bi bi-info-circle me-1"></i>Student events are reviewed by an administrator before they go public.</div><?php endif; ?>
        <div class="row g-3">
            <div class="col-md-8"><label class="form-label" for="evTitle">Title *</label><input class="form-control" id="evTitle" name="title" maxlength="120" required></div>
            <div class="col-md-4"><label class="form-label" for="evCat">Category *</label><select class="form-select" id="evCat" name="category"><?php foreach (EventModel::CATEGORIES as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label" for="evStart">Starts *</label><input class="form-control" id="evStart" name="starts_at" placeholder="Pick date and time" autocomplete="off" required></div>
            <div class="col-md-6"><label class="form-label" for="evEnd">Ends * <span class="text-muted fw-normal">(same day, by 20:00)</span></label><input class="form-control" id="evEnd" name="ends_at" placeholder="Pick date and time" autocomplete="off" required></div>
            <div class="col-md-6"><label class="form-label" for="evVenue">Venue *</label><select class="form-select" id="evVenue" name="venue"><?php foreach (EventModel::VENUES as $v): ?><option><?= e($v) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label" for="evCap">Capacity</label><input class="form-control" type="number" min="1" max="5000" id="evCap" name="capacity" placeholder="Unlimited"></div>
            <div class="col-md-3"><label class="form-label" for="evClub">Hosted by</label><select class="form-select" id="evClub" name="club_id"><option value="">Myself</option><?php foreach ($_mc as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-12"><label class="form-label" for="evDesc">Description</label><textarea class="form-control" id="evDesc" name="description" rows="4" maxlength="2000" placeholder="What is it about? Who should come?"></textarea></div>
        </div>
        <div class="text-danger small mt-2" id="evErr" role="alert"></div>
    </div>
    <div class="modal-footer border-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-gradient" id="evSave"><i class="bi bi-check2 me-1"></i>Save event</button></div>
</form></div></div>
<div class="modal fade" id="reasonModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content p-3">
    <h5 class="mb-2" id="rsTitle">Reason</h5><textarea class="form-control" id="rsText" rows="3" maxlength="200" placeholder="At least 5 characters"></textarea><div class="text-danger small mt-1" id="rsErr"></div>
    <div class="d-flex gap-2 mt-3"><button class="btn btn-light flex-fill" data-bs-dismiss="modal">Back</button><button class="btn btn-coral flex-fill" id="rsGo">Confirm</button></div>
</div></div></div>
