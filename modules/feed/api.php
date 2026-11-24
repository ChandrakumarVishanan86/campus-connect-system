<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::ALL, json: true);
$ok = static fn(string $m, array $x = []) => json_response(['success' => true, 'message' => $m] + $x);
$bad = static fn(string $m, int $c = 422) => json_response(['success' => false, 'message' => $m], $c);
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $a = $_GET['action'] ?? '';
        if ($a === 'list') { json_response(['success' => true] + PostModel::list($user, $_GET)); }
        if ($a === 'post') {
            $p = PostModel::find((int) ($_GET['id'] ?? 0));
            if (!$p || !PostModel::canView($user, $p)) { $bad('Post not found.', 404); }
            $r = PostModel::list($user, ['user_id' => $p['user_id']]);          // reuse the decorated row shape
            $row = null; foreach ($r['posts'] as $x) { if ((int) $x['id'] === (int) $p['id']) { $row = $x; } }
            if (!$row) { $row = PostModel::list($user, ['q' => mb_substr($p['body'], 0, 20)])['posts'][0] ?? null; }
            json_response(['success' => true, 'post' => $row]);
        }
        if ($a === 'comments') {
            $p = PostModel::find((int) ($_GET['post_id'] ?? 0));
            if (!$p || !PostModel::canView($user, $p)) { $bad('Post not found.', 404); }
            json_response(['success' => true, 'data' => PostModel::comments((int) $p['id'], $user)]);
        }
        if ($a === 'rail') {
            $clubs = db()->prepare("SELECT c.id, c.name, m.role FROM club_members m JOIN clubs c ON c.id = m.club_id WHERE m.user_id = ? AND m.status = 'Active' AND c.is_active = 1 ORDER BY c.name LIMIT 5"); $clubs->execute([(int) $user['id']]);
            $lost = db()->query("SELECT kind, title FROM lost_items WHERE status = 'Open' ORDER BY id DESC LIMIT 4")->fetchAll();
            json_response(['success' => true, 'events' => array_map(static fn($e) => ['id' => $e['id'], 'title' => $e['title'], 'starts_at' => $e['starts_at'], 'venue' => $e['venue']], EventModel::next($user, 3)), 'clubs' => $clubs->fetchAll(), 'lost' => $lost]);
        }
        $bad('Unknown action.', 400);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $bad('Method not allowed.', 405); }
    if (!$_POST) { $j = json_input(); if ($j) { $_POST = $j; } }
    require_csrf();
    $a = (string) ($_POST['action'] ?? ''); $id = (int) ($_POST['id'] ?? 0);
    switch ($a) {
        case 'create':
            [$nid, $e] = PostModel::create($user, $_POST); $e ? $bad($e) : $ok('Posted!', ['id' => $nid]);
        case 'comment':
            [$c, $e] = PostModel::comment((int) ($_POST['post_id'] ?? 0), $user, (string) ($_POST['body'] ?? '')); $e ? $bad($e) : $ok('Comment added.', ['comment' => $c]);
        case 'like':
            [$s, $e] = PostModel::like($id, $user); $e ? $bad($e, 404) : json_response(['success' => true] + $s);
        case 'pin': case 'unpin':
            ($e = PostModel::pin($id, $a === 'pin', $user)) ? $bad($e, 403) : $ok($a === 'pin' ? 'Post pinned to the top.' : 'Post unpinned.');
        case 'hide': case 'unhide':
            ($e = PostModel::setHidden($id, $a === 'hide', $user)) ? $bad($e, 403) : $ok($a === 'hide' ? 'Post hidden.' : 'Post restored.');
        case 'delete':
            ($e = PostModel::delete($id, $user)) ? $bad($e, 403) : $ok('Post deleted.');
        case 'delete_comment':
            ($e = PostModel::deleteComment($id, $user)) ? $bad($e, 403) : $ok('Comment deleted.');
        case 'report':
            ($e = PostModel::report((string) ($_POST['entity'] ?? ''), $id, $user, (string) ($_POST['reason'] ?? ''), (string) ($_POST['note'] ?? ''))) ? $bad($e, 409) : $ok('Thanks - a moderator will take a look.');
        default: $bad('Unknown action.', 400);
    }
} catch (Throwable $e) {
    error_log('[feed api] ' . $e->getMessage());
    json_response(['success' => false, 'message' => APP_DEBUG ? $e->getMessage() : 'Server error. Please try again.'], 500);
}
