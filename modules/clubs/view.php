<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::ALL);
$club = ClubModel::detail((int) ($_GET['id'] ?? 0), $user);
if (!$club || !(int) $club['is_active']) { http_response_code(404); $pageTitle = 'Club not found'; $activeNav = 'clubs'; require_once dirname(__DIR__, 2) . '/includes/header.php'; echo '<div class="post empty"><i class="bi bi-people"></i>This club does not exist.<div class="mt-3"><a class="btn btn-gradient" href="' . url('clubs') . '">Browse clubs</a></div></div>'; require_once dirname(__DIR__, 2) . '/includes/footer.php'; exit; }
$pageTitle = $club['name']; $pageSub = $club['category'] . ' · ' . $club['code']; $activeNav = 'clubs'; $rail = false; $libs = ['flatpickr'];
$pageActions = '<a class="btn btn-soft" href="' . url('clubs') . '"><i class="bi bi-arrow-left me-1"></i>All clubs</a>';
$pageScripts = ['assets/js/event_form.js', 'assets/js/feed.js', 'assets/js/club_view.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div id="clubHead" class="card-panel mb-3"></div>
<ul class="nav nav-pills cc-tabs mb-3" id="clubTabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tPosts" type="button">Posts</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tEvents" type="button">Events</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tMembers" type="button">Members <span class="badge text-bg-danger d-none" id="pendBadge"></span></button></li>
    <?php if ($club['can_manage']): ?><li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tManage" type="button">Manage</button></li><?php endif; ?>
</ul>
<div class="tab-content">
    <div class="tab-pane fade show active" id="tPosts"><div id="feed"></div><div class="text-center my-3"><button class="btn btn-soft d-none" id="more">Load more</button></div></div>
    <div class="tab-pane fade" id="tEvents"><div id="clubEvents"></div></div>
    <div class="tab-pane fade" id="tMembers"><div id="clubMembers"></div></div>
<?php if ($club['can_manage']): ?>
    <div class="tab-pane fade" id="tManage"><form class="card-panel" id="aboutForm" style="max-width:640px">
        <div class="mb-3"><label class="form-label" for="abDesc">About the club</label><textarea class="form-control" id="abDesc" rows="4" maxlength="500"><?= e($club['description'] ?? '') ?></textarea></div>
        <div class="mb-3"><label class="form-label" for="abMeet">Meeting info</label><input class="form-control" id="abMeet" maxlength="160" value="<?= e($club['meeting_info'] ?? '') ?>" placeholder="e.g. Fridays 3 pm, Lecture Hall B"></div>
        <div class="mb-3"><label class="form-label" for="abPol">Who can join?</label><select class="form-select" id="abPol"><option value="Open"<?= $club['join_policy'] === 'Open' ? ' selected' : '' ?>>Anyone - instant</option><option value="Approval"<?= $club['join_policy'] === 'Approval' ? ' selected' : '' ?>>By approval of officers</option></select></div>
        <button class="btn btn-gradient">Save changes</button> <span class="text-danger small ms-2" id="abErr"></span>
    </form></div>
<?php endif; ?>
</div>
<div class="modal fade" id="reportModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content p-3">
    <h5 class="mb-3">Report this <span id="rpWhat">post</span></h5>
    <label class="form-label" for="rpReason">Reason</label><select class="form-select mb-2" id="rpReason"><?php foreach (PostModel::REASONS as $r): ?><option><?= e($r) ?></option><?php endforeach; ?></select>
    <label class="form-label" for="rpNote">Details (optional)</label><input class="form-control" id="rpNote" maxlength="200">
    <div class="d-flex gap-2 mt-3"><button class="btn btn-light flex-fill" data-bs-dismiss="modal">Cancel</button><button class="btn btn-coral flex-fill" id="rpGo">Report</button></div>
</div></div></div>
<?php include dirname(__DIR__) . '/events/_form.php';
$pageConfig = '<script>window.FEED = ' . json_encode(['club' => (int) $club['id']]) . '; window.EV = ' . json_encode(['clubId' => (int) $club['id']]) . '; window.CLUB = ' . json_encode(['id' => (int) $club['id'], 'mgr' => (bool) $club['can_manage']]) . ';</script>';
require_once dirname(__DIR__, 2) . '/includes/footer.php';
