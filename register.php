<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
if (Auth::check()) { redirect('feed'); }
$errors = []; $v = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v = array_map(static fn($x) => is_string($x) ? trim($x) : '', $_POST);
    if (!csrf_verify()) { $errors['_'] = 'Your session expired. Please try again.'; }
    else {
        [$id, $err] = UserModel::register($_POST);
        if ($id) { flash('success', 'Account created - you can sign in now.'); redirect('login'); }
        $errors = $err;
    }
}
$progs = db()->query('SELECT id, code, name FROM programmes WHERE is_active = 1 ORDER BY code')->fetchAll();
$f = static fn(string $k) => isset($errors[$k]) ? '<div class="text-danger small mt-1">' . e($errors[$k]) . '</div>' : '';
$val = static fn(string $k) => e($v[$k] ?? '');
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Join · <?= e(APP_NAME) ?></title>
    <script>try{var t=localStorage.getItem('cc_theme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= url('assets/css/style.css') ?>?v=1" rel="stylesheet">
</head>
<body class="login-body">
<div class="auth-wrap">
    <section class="auth-art">
        <span class="bubble-f" style="width:200px;height:200px;right:-50px;top:120px"></span><span class="bubble-f" style="width:100px;height:100px;left:35%;bottom:25%;animation-delay:-4s"></span>
        <a class="brand position-relative" href="<?= url('login') ?>"><span class="brand-mark"><i class="bi bi-chat-heart-fill"></i></span><span>Campus<b>Connect</b></span></a>
        <div class="position-relative"><h1>Join the<br><em>community.</em></h1><p class="mt-3">Follow announcements for your programme, join clubs, RSVP to events and never lose your stuff again.</p></div>
        <div class="small opacity-75 position-relative">Student accounts are created instantly. Staff accounts are created by an administrator.</div>
    </section>
    <section class="auth-form"><div class="auth-card">
        <h2 class="mb-1">Create your account</h2><p class="text-muted mb-4">It takes less than a minute.</p>
<?php if (!empty($errors['_'])): ?><div class="alert alert-danger py-2"><?= e($errors['_']) ?></div><?php endif; ?>
        <form method="post" novalidate autocomplete="off" id="regForm">
            <?= csrf_field() ?>
            <div class="mb-2"><label class="form-label" for="full_name">Full name</label><input class="form-control" id="full_name" name="full_name" value="<?= $val('full_name') ?>" maxlength="120" required><?= $f('full_name') ?></div>
            <div class="row g-2 mb-2">
                <div class="col-sm-6"><label class="form-label" for="username">Username</label><input class="form-control" id="username" name="username" value="<?= $val('username') ?>" maxlength="50" required><?= $f('username') ?></div>
                <div class="col-sm-6"><label class="form-label" for="email">Email</label><input type="email" class="form-control" id="email" name="email" value="<?= $val('email') ?>" maxlength="120" required><?= $f('email') ?></div>
            </div>
            <div class="row g-2 mb-2">
                <div class="col-sm-8"><label class="form-label" for="programme_id">Programme</label><select class="form-select" id="programme_id" name="programme_id" required><option value="">Select…</option><?php foreach ($progs as $p): ?><option value="<?= (int) $p['id'] ?>"<?= ($v['programme_id'] ?? '') == $p['id'] ? ' selected' : '' ?>><?= e($p['code']) ?> – <?= e($p['name']) ?></option><?php endforeach; ?></select><?= $f('programme_id') ?></div>
                <div class="col-sm-4"><label class="form-label" for="batch">Batch</label><input type="number" class="form-control" id="batch" name="batch" min="2015" max="2035" value="<?= $val('batch') ?: date('Y') ?>"><?= $f('batch') ?></div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-sm-6"><label class="form-label" for="password">Password</label><input type="password" class="form-control" id="password" name="password" minlength="8" required><?= $f('password') ?></div>
                <div class="col-sm-6"><label class="form-label" for="password2">Confirm</label><input type="password" class="form-control" id="password2" name="password2" required><?= $f('password2') ?></div>
            </div>
            <button class="btn btn-gradient btn-lg w-100" type="submit">Create account</button>
        </form>
        <p class="text-center mt-3 mb-0">Already have an account? <a href="<?= url('login') ?>" class="fw-semibold">Sign in</a></p>
    </div></section>
</div>
</body>
</html>
