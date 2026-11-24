<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::ALL, json: true);
$ok = static fn(string $m, array $x = []) => json_response(['success' => true, 'message' => $m] + $x);
$bad = static fn(string $m, int $c = 422) => json_response(['success' => false, 'message' => $m], $c);
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $a = $_GET['action'] ?? '';
        if ($a === 'list') { json_response(['success' => true, 'data' => ClubModel::listFor($user)]); }
        if ($a === 'members') {
            $id = (int) ($_GET['id'] ?? 0); $c = ClubModel::detail($id, $user); if (!$c || !(int) $c['is_active']) { $bad('Club not found.', 404); }
            json_response(['success' => true, 'data' => ClubModel::members($id, $c['can_manage'])]);
        }
        $bad('Unknown action.', 400);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $bad('Method not allowed.', 405); }
    if (!$_POST) { $j = json_input(); if ($j) { $_POST = $j; } }
    require_csrf();
    $a = (string) ($_POST['action'] ?? ''); $id = (int) ($_POST['id'] ?? 0); $uid = (int) ($_POST['user_id'] ?? 0);
    switch ($a) {
        case 'join':
            [$s, $e] = ClubModel::join($id, $user); $e ? $bad($e, 409) : $ok($s === 'Active' ? 'Welcome to the club!' : 'Request sent - the club officers will review it.', ['status' => $s]);
        case 'leave':
            ($e = ClubModel::leave($id, $user)) ? $bad($e, 409) : $ok('You left the club.');
        case 'decide':
            ($e = ClubModel::decide($id, $uid, !empty($_POST['approve']), $user)) ? $bad($e, 409) : $ok(!empty($_POST['approve']) ? 'Member approved.' : 'Request declined.');
        case 'role':
            ($e = ClubModel::setRole($id, $uid, (string) ($_POST['role'] ?? ''), $user)) ? $bad($e, 409) : $ok('Role updated.');
        case 'remove':
            ($e = ClubModel::remove($id, $uid, $user)) ? $bad($e, 409) : $ok('Member removed.');
        case 'about':
            ($e = ClubModel::updateAbout($id, $user, (string) ($_POST['description'] ?? ''), (string) ($_POST['meeting_info'] ?? ''), (string) ($_POST['join_policy'] ?? ''))) ? $bad($e) : $ok('Club details saved.');
        default: $bad('Unknown action.', 400);
    }
} catch (Throwable $e) {
    error_log('[clubs api] ' . $e->getMessage());
    json_response(['success' => false, 'message' => APP_DEBUG ? $e->getMessage() : 'Server error. Please try again.'], 500);
}
