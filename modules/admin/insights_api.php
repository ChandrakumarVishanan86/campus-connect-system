<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
Auth::guard(['admin'], json: true);
json_response(['success' => true] + InsightModel::overview());
