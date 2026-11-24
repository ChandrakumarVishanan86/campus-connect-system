<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
if (Auth::check()) { redirect(Auth::homeFor(Auth::role())); }

$error = ''; $username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? '')); $password = (string) ($_POST['password'] ?? '');
    if (!csrf_verify()) { $error = 'Your session expired. Please try again.'; }
    elseif (($wait = Auth::lockedFor()) > 0) { $error = "Too many failed attempts. Try again in {$wait} seconds."; }
    elseif ($username === '' || $password === '') { $error = 'Please enter your username and password.'; }
    else {
        $u = Auth::attempt($username, $password);
        if ($u) { redirect(Auth::homeFor($u['role'])); }
        $error = ($wait = Auth::lockedFor()) > 0 ? "Too many failed attempts. Try again in {$wait} seconds." : 'Invalid username or password.';
    }
}
$stats = db()->query("SELECT (SELECT COUNT(*) FROM users WHERE is_active = 1) AS people, (SELECT COUNT(*) FROM clubs WHERE is_active = 1) AS clubs, (SELECT COUNT(*) FROM events WHERE status = 'Approved' AND ends_at >= NOW()) AS events")->fetch();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · <?= e(APP_NAME) ?></title>
    <script>try{var t=localStorage.getItem('cc_theme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= url('assets/css/style.css') ?>?v=1" rel="stylesheet">
</head>
<body class="login-body">
<div class="auth-wrap">
    <section class="auth-art">
        <span class="bubble-f" style="width:220px;height:220px;right:-60px;top:80px"></span><span class="bubble-f" style="width:120px;height:120px;left:40%;bottom:20%;animation-delay:-3s"></span><span class="bubble-f" style="width:70px;height:70px;left:12%;top:46%;animation-delay:-5s"></span>
        <a class="brand position-relative" href="<?= url('login') ?>"><span class="brand-mark"><i class="bi bi-chat-heart-fill"></i></span><span>Campus<b>Connect</b></span></a>
        <div class="position-relative">
            <h1>Your campus,<br><em>all in one place.</em></h1>
            <p class="mt-3">Announcements, clubs, events and lost &amp; found &mdash; the community of <?= e(CAMPUS_NAME) ?> on one friendly feed.</p>
        </div>
        <div class="stat-row position-relative"><div><b><?= (int) $stats['people'] ?></b><span>people</span></div><div><b><?= (int) $stats['clubs'] ?></b><span>clubs</span></div><div><b><?= (int) $stats['events'] ?></b><span>upcoming events</span></div></div>
    </section>
    <section class="auth-form"><div class="auth-card">
        <h2 class="mb-1">Welcome back</h2><p class="text-muted mb-4">Sign in to <?= e(APP_NAME) ?>.</p>
<?php foreach (pull_flash() as $f): ?><div class="alert alert-<?= e($f['type']) ?> py-2"><?= e($f['message']) ?></div><?php endforeach; ?>
<?php if ($error): ?><div class="alert alert-danger py-2" role="alert"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div><?php endif; ?>
        <form method="post" novalidate autocomplete="off">
            <?= csrf_field() ?>
            <div class="mb-3"><label class="form-label" for="username">Username</label><input class="form-control form-control-lg" id="username" name="username" value="<?= e($username) ?>" autofocus required></div>
            <div class="mb-4"><label class="form-label" for="password">Password</label><input type="password" class="form-control form-control-lg" id="password" name="password" required></div>
            <button class="btn btn-gradient btn-lg w-100" type="submit">Sign in <i class="bi bi-arrow-right ms-1"></i></button>
        </form>
        <p class="text-center mt-3 mb-0">New here? <a href="<?= url('register') ?>" class="fw-semibold">Create a student account</a></p>
        <div class="mt-4 p-3 rounded-4" style="background:var(--surface);border:1px dashed var(--line)" id="demoBox">
            <div class="small fw-bold mb-2 text-muted text-uppercase" style="letter-spacing:.08em">Demo accounts</div>
            <div class="d-flex flex-wrap gap-2">
<?php foreach ([['admin', 'admin123', 'Admin'], ['lecturer1', 'staff123', 'Lecturer'], ['student1', 'student123', 'Student'], ['student2', 'student123', 'Student 2']] as [$u, $p, $l]): ?>
                <button type="button" class="btn btn-soft btn-sm" data-u="<?= $u ?>" data-p="<?= $p ?>"><?= $l ?></button>
<?php endforeach; ?>
            </div>
        </div>
    </div></section>
</div>
<script>document.querySelectorAll('#demoBox [data-u]').forEach(function (b) { b.addEventListener('click', function () { document.getElementById('username').value = b.dataset.u; document.getElementById('password').value = b.dataset.p; }); });</script>
</body>
</html>
