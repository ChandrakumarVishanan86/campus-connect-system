<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::ALL, json: true);
if (($_GET['action'] ?? '') === 'list') { json_response(['success' => true, 'data' => UserModel::directory($_GET)]); }
json_response(['success' => false, 'message' => 'Unknown action.'], 400);
