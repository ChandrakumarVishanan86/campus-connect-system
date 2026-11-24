<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::ALL, json: true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_response(['success' => false, 'message' => 'Method not allowed.'], 405); }
if (!$_POST) { $j = json_input(); if ($j) { $_POST = $j; } }
require_csrf();
try {
    $a = (string) ($_POST['action'] ?? '');
    if ($a === 'update') {
        [$ok, $err] = UserModel::updateProfile((int) $user['id'], $_POST);
        $ok ? json_response(['success' => true, 'message' => 'Profile saved.']) : json_response(['success' => false, 'message' => implode(' ', $err), 'errors' => $err], 422);
    }
    if ($a === 'password') {
        if ((string) ($_POST['new'] ?? '') !== (string) ($_POST['new2'] ?? '')) { json_response(['success' => false, 'message' => 'The new passwords do not match.'], 422); }
        $e = UserModel::changePassword((int) $user['id'], (string) ($_POST['old'] ?? ''), (string) ($_POST['new'] ?? ''));
        $e ? json_response(['success' => false, 'message' => $e], 422) : json_response(['success' => true, 'message' => 'Password updated.']);
    }
    json_response(['success' => false, 'message' => 'Unknown action.'], 400);
} catch (Throwable $e) {
    error_log('[profile api] ' . $e->getMessage());
    json_response(['success' => false, 'message' => APP_DEBUG ? $e->getMessage() : 'Server error.'], 500);
}
