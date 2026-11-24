<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::ALL, json: true);
$ok = static fn(string $m, array $x = []) => json_response(['success' => true, 'message' => $m] + $x);
$bad = static fn(string $m, int $c = 422) => json_response(['success' => false, 'message' => $m], $c);
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (($_GET['action'] ?? '') === 'list') { json_response(['success' => true, 'data' => LostModel::list($user, $_GET)]); }
        $bad('Unknown action.', 400);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $bad('Method not allowed.', 405); }
    if (!$_POST) { $j = json_input(); if ($j) { $_POST = $j; } }
    require_csrf();
    $a = (string) ($_POST['action'] ?? ''); $id = (int) ($_POST['id'] ?? 0);
    switch ($a) {
        case 'create':
            [$nid, $e] = LostModel::create($user, $_POST); $e ? $bad($e) : $ok('Report posted.', ['id' => $nid]);
        case 'claim':
            ($e = LostModel::claim($id, $user, (string) ($_POST['note'] ?? ''))) ? $bad($e, 409) : $ok('Sent! The poster will review your message.');
        case 'confirm': case 'reject':
            ($e = LostModel::decide($id, $a === 'confirm', $user)) ? $bad($e, 409) : $ok($a === 'confirm' ? 'Marked as resolved. Contact details are now shared.' : 'Claim declined - the item is open again.');
        case 'delete':
            ($e = LostModel::delete($id, $user)) ? $bad($e, 403) : $ok('Report deleted.');
        default: $bad('Unknown action.', 400);
    }
} catch (Throwable $e) {
    error_log('[lost api] ' . $e->getMessage());
    json_response(['success' => false, 'message' => APP_DEBUG ? $e->getMessage() : 'Server error. Please try again.'], 500);
}
