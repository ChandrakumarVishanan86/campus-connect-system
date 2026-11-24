<?php /** Create / edit event modal (included by events pages). Needs $user. */
$myClubs = db()->prepare("SELECT c.id, c.name FROM clubs c WHERE c.is_active = 1 AND (? = 'admin' OR c.advisor_id = ? OR EXISTS (SELECT 1 FROM club_members m WHERE m.club_id = c.id AND m.user_id = ? AND m.status = 'Active' AND m.role IN ('President','Officer'))) ORDER BY c.name");
$myClubs->execute([$user['role'], (int) $user['id'], (int) $user['id']]); $myClubs = $myClubs->fetchAll();
?>
<div class="modal fade" id="evModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><form class="modal-content" id="evForm" novalidate>
    <div class="modal-header"><h5 class="modal-title" id="evTitleH">Create an event</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body row g-3">
        <input type="hidden" name="id" value="0">
        <div class="col-12"><label class="form-label" for="evT">Title</label><input class="form-control" id="evT" name="title" maxlength="120" required><div class="invalid-feedback"></div></div>
        <div class="col-md-6"><label class="form-label" for="evCat">Category</label><select class="form-select" id="evCat" name="category"><?php foreach (EventModel::CATEGORIES as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label" for="evVen">Venue</label><select class="form-select" id="evVen" name="venue"><?php foreach (EventModel::VENUES as $v): ?><option><?= e($v) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label" for="evS">Starts</label><input class="form-control dt" id="evS" name="starts_at" placeholder="Pick date &amp; time" required></div>
        <div class="col-md-4"><label class="form-label" for="evE">Ends</label><input class="form-control dt" id="evE" name="ends_at" placeholder="Pick date &amp; time" required></div>
        <div class="col-md-4"><label class="form-label" for="evCap">Capacity</label><input class="form-control" id="evCap" name="capacity" type="number" min="1" max="5000" placeholder="Unlimited"></div>
        <div class="col-12" id="evClubRow"><label class="form-label" for="evClub">Hosted by</label><select class="form-select" id="evClub" name="club_id"><option value="">Myself (personal event)</option><?php foreach ($myClubs as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-12"><label class="form-label" for="evD">Description</label><textarea class="form-control" id="evD" name="description" rows="4" maxlength="2000"></textarea></div>
        <div class="col-12"><div class="small text-muted"><i class="bi bi-info-circle me-1"></i>Events run on one day, up to 12 hours, and must finish by 20:00. <?= in_array($user['role'], Auth::STAFF, true) ? 'Your events are published immediately.' : 'Student events are reviewed by an administrator before they go live.' ?></div></div>
        <div class="col-12 d-none" id="evErr"><div class="alert alert-danger py-2 mb-0"></div></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-gradient" id="evSave">Save event</button></div>
</form></div></div>
