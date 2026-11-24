<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(['admin'], json: true);
if ($_SERVER['REQUEST_METHOD'] === 'GET') { json_response(['success' => true, 'data' => PostModel::openReports()]); }
if (!$_POST) { $j = json_input(); if ($j) { $_POST = $j; } }
require_csrf();
if (($_POST['action'] ?? '') === 'resolve') {
    ($e = PostModel::resolve((string) ($_POST['entity'] ?? ''), (int) ($_POST['id'] ?? 0), (string) ($_POST['decision'] ?? ''), $user)) ? json_response(['success' => false, 'message' => $e], 422) : json_response(['success' => true, 'message' => 'Decision recorded.']);
}
json_response(['success' => false, 'message' => 'Unknown action.'], 400);
