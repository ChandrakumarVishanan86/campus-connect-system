<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::ALL);
$me = db()->prepare('SELECT full_name, email, phone, bio, interests, color, username FROM users WHERE id = ?'); $me->execute([(int) $user['id']]); $me = $me->fetch();
$pageTitle = 'Edit profile'; $pageSub = 'How others see you on campus'; $activeNav = ''; $rail = false; $pageScripts = ['assets/js/profile.js'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="row g-3">
<div class="col-lg-7"><form class="card-panel" id="pfForm" novalidate>
    <h5 class="mb-3">Profile</h5>
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label" for="pfName">Full name</label><input class="form-control" id="pfName" name="full_name" maxlength="120" value="<?= e($me['full_name']) ?>"></div>
        <div class="col-md-6"><label class="form-label" for="pfUser">Username</label><input class="form-control" id="pfUser" value="<?= e($me['username']) ?>" disabled></div>
        <div class="col-md-6"><label class="form-label" for="pfEmail">Email</label><input class="form-control" id="pfEmail" name="email" type="email" maxlength="120" value="<?= e($me['email'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label" for="pfPhone">Phone</label><input class="form-control" id="pfPhone" name="phone" maxlength="20" value="<?= e($me['phone'] ?? '') ?>"></div>
        <div class="col-12"><label class="form-label" for="pfBio">Bio</label><textarea class="form-control" id="pfBio" name="bio" rows="3" maxlength="300"><?= e($me['bio'] ?? '') ?></textarea></div>
        <div class="col-12"><label class="form-label" for="pfInt">Interests <span class="text-muted fw-normal">(comma separated)</span></label><input class="form-control" id="pfInt" name="interests" maxlength="160" value="<?= e($me['interests'] ?? '') ?>"></div>
        <div class="col-12"><label class="form-label">Avatar colour</label><div class="d-flex gap-2 flex-wrap" id="pfColors"><?php for ($i = 0; $i < 8; $i++): ?><button type="button" class="av av-<?= $i ?> sw<?= (int) $me['color'] === $i ? ' on' : '' ?>" data-c="<?= $i ?>" aria-label="Colour <?= $i + 1 ?>"><?= e(mb_substr($me['full_name'], 0, 1)) ?></button><?php endfor; ?></div><input type="hidden" name="color" id="pfColor" value="<?= (int) $me['color'] ?>"></div>
    </div>
    <div class="mt-3"><button class="btn btn-gradient">Save profile</button> <span class="text-danger small ms-2" id="pfErr" role="alert"></span></div>
</form></div>
<div class="col-lg-5"><form class="card-panel" id="pwForm" novalidate>
    <h5 class="mb-3">Change password</h5>
    <div class="mb-3"><label class="form-label" for="pwOld">Current password</label><input class="form-control" type="password" id="pwOld" autocomplete="current-password"></div>
    <div class="mb-3"><label class="form-label" for="pwNew">New password</label><input class="form-control" type="password" id="pwNew" autocomplete="new-password"><div class="form-text">At least 8 characters.</div></div>
    <div class="mb-3"><label class="form-label" for="pwNew2">Repeat new password</label><input class="form-control" type="password" id="pwNew2" autocomplete="new-password"></div>
    <button class="btn btn-gradient">Update password</button> <span class="text-danger small ms-2" id="pwErr" role="alert"></span>
</form></div></div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php';
