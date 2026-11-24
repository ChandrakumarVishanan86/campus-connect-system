<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::ALL, json: true);
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_response(['success' => true, 'unread' => NotifyModel::unread((int) $user['id']), 'data' => NotifyModel::list((int) $user['id'])]);
}
if (!$_POST) { $j = json_input(); if ($j) { $_POST = $j; } }
require_csrf();
if (($_POST['action'] ?? '') === 'read') { NotifyModel::read((int) $user['id'], (int) ($_POST['id'] ?? 0)); json_response(['success' => true, 'message' => 'Done.', 'unread' => NotifyModel::unread((int) $user['id'])]); }
json_response(['success' => false, 'message' => 'Unknown action.'], 400);
