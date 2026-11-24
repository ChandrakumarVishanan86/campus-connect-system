<?php
declare(strict_types=1);

/** Campus feed: posts, comments, likes, pinning, reporting and moderation. */
final class PostModel
{
    public const TYPES = ['Announcement', 'Discussion', 'Question', 'Achievement', 'Opportunity'];
    public const REASONS = ['Spam', 'Harassment', 'Inappropriate', 'Misinformation', 'Other'];
    public const MAX_PINNED = 3;

    private static function clean(string $s): string { return trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s)); }
    private static function q(string $sql, array $p = []): array { $s = db()->prepare($sql); $s->execute($p); return $s->fetchAll(); }

    /** SQL (alias p) limiting what $u may see: published, audience-matching posts; admins also see hidden ones. */
    private static function visible(array $u): array
    {
        if ($u['role'] === 'admin') { return ['1=1', []]; }
        if ($u['role'] === 'staff') { return ["p.status = 'Published'", []]; }
        return ["p.status = 'Published' AND (p.programme_id IS NULL OR p.programme_id = ?)", [(int) ($u['programme_id'] ?? 0)]];
    }

    public static function find(int $id): ?array
    {
        $r = self::q('SELECT p.*, u.full_name AS author, u.role AS author_role, u.color AS author_color, c.name AS club_name FROM posts p JOIN users u ON u.id = p.user_id LEFT JOIN clubs c ON c.id = p.club_id WHERE p.id = ?', [$id]);
        return $r[0] ?? null;
    }
    public static function canView(array $u, array $p): bool
    {
        if ($u['role'] === 'admin') { return true; }
        if ($p['status'] !== 'Published') { return (int) $p['user_id'] === (int) $u['id']; }
        return $u['role'] === 'staff' || $p['programme_id'] === null || (int) $p['programme_id'] === (int) ($u['programme_id'] ?? 0);
    }

    /** @param array{type?:string,scope?:string,club_id?:int,user_id?:int,q?:string,page?:int} $f */
    public static function list(array $u, array $f = []): array
    {
        [$where, $par] = self::visible($u);
        $w = [$where];
        if (!empty($f['type']) && in_array($f['type'], self::TYPES, true)) { $w[] = 'p.type = ?'; $par[] = $f['type']; }
        if (!empty($f['club_id'])) { $w[] = 'p.club_id = ?'; $par[] = (int) $f['club_id']; }
        if (!empty($f['user_id'])) { $w[] = 'p.user_id = ?'; $par[] = (int) $f['user_id']; }
        if (($f['scope'] ?? '') === 'clubs') { $w[] = 'p.club_id IN (SELECT club_id FROM club_members WHERE user_id = ? AND status = \'Active\')'; $par[] = (int) $u['id']; }
        if (($f['scope'] ?? '') === 'mine') { $w[] = 'p.user_id = ?'; $par[] = (int) $u['id']; }
        if (($f['scope'] ?? '') === 'hidden' && $u['role'] === 'admin') { $w[] = "p.status = 'Hidden'"; }
        if (!empty($f['q'])) { $w[] = '(p.body LIKE ? OR p.title LIKE ? OR us.full_name LIKE ?)'; $l = '%' . addcslashes((string) $f['q'], '%_') . '%'; array_push($par, $l, $l, $l); }
        $limit = POSTS_PER_PAGE; $off = max(0, ((int) ($f['page'] ?? 1)) - 1) * $limit;
        $sql = "SELECT p.id, p.user_id, p.club_id, p.programme_id, p.type, p.title, p.body, p.is_pinned, p.status, p.created_at,
                    us.full_name AS author, us.role AS author_role, us.color AS author_color, us.title AS author_title, c.name AS club_name, pr.code AS audience,
                    (SELECT COUNT(*) FROM post_likes l WHERE l.post_id = p.id) AS likes,
                    (SELECT COUNT(*) FROM comments m WHERE m.post_id = p.id AND m.status = 'Published') AS comments,
                    (SELECT COUNT(*) FROM post_likes l2 WHERE l2.post_id = p.id AND l2.user_id = ?) AS liked
                FROM posts p JOIN users us ON us.id = p.user_id LEFT JOIN clubs c ON c.id = p.club_id LEFT JOIN programmes pr ON pr.id = p.programme_id
                WHERE " . implode(' AND ', $w) . " ORDER BY p.is_pinned DESC, p.created_at DESC, p.id DESC LIMIT " . ($limit + 1) . " OFFSET $off";
        $rows = self::q($sql, array_merge([(int) $u['id']], $par));
        $more = count($rows) > $limit; $rows = array_slice($rows, 0, $limit);
        foreach ($rows as &$r) {
            $r['mine'] = (int) $r['user_id'] === (int) $u['id'];
            $r['can_delete'] = $r['mine'] || $u['role'] === 'admin';
            $r['can_pin'] = in_array($u['role'], Auth::STAFF, true) && $r['status'] === 'Published';
            $r['can_hide'] = $u['role'] === 'admin' || ($u['role'] === 'staff' && $r['mine']);
        }
        return ['posts' => $rows, 'more' => $more];
    }

    /** @return array{0:?int,1:?string} */
    public static function create(array $u, array $in): array
    {
        $body = self::clean((string) ($in['body'] ?? '')); $title = self::clean((string) ($in['title'] ?? ''));
        $type = (string) ($in['type'] ?? 'Discussion');
        if (!in_array($type, self::TYPES, true)) { return [null, 'Choose a valid post type.']; }
        if ($type === 'Announcement' && !in_array($u['role'], Auth::STAFF, true)) { return [null, 'Only staff can publish announcements.']; }
        if (mb_strlen($body) < 3) { return [null, 'Write at least a few words.']; }
        if (mb_strlen($body) > POST_MAX) { return [null, 'Posts are limited to ' . POST_MAX . ' characters.']; }
        if (mb_strlen($title) > 120) { return [null, 'Title is too long (max 120).']; }
        if ($type === 'Announcement' && mb_strlen($title) < 3) { return [null, 'Announcements need a title.']; }
        $club = (int) ($in['club_id'] ?? 0) ?: null;
        if ($club) {
            if (!ClubModel::canManage($club, $u)) { return [null, 'Only club officers can post on behalf of a club.']; }
            if ($type === 'Announcement' && $u['role'] === 'student') { return [null, 'Announcements are for staff.']; }
        }
        $prog = (int) ($in['programme_id'] ?? 0) ?: null;
        if ($prog && !in_array($u['role'], Auth::STAFF, true)) { return [null, 'Only staff can target a post at one programme.']; }
        if ($prog && !self::q('SELECT 1 FROM programmes WHERE id = ?', [$prog])) { return [null, 'Unknown programme.']; }
        $last = self::q('SELECT COUNT(*) AS n FROM posts WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL ' . (int) POST_COOLDOWN_SEC . ' SECOND)', [(int) $u['id']]);
        if ((int) $last[0]['n'] > 0) { return [null, 'Slow down - please wait a few seconds before posting again.']; }
        db()->prepare('INSERT INTO posts (user_id, club_id, programme_id, type, title, body) VALUES (?,?,?,?,?,?)')->execute([(int) $u['id'], $club, $prog, $type, $title ?: null, $body]);
        $id = (int) db()->lastInsertId();
        if ($type === 'Announcement') {
            $ids = self::q("SELECT id FROM users WHERE is_active = 1 AND role = 'student' AND (? IS NULL OR programme_id = ?)", [$prog, $prog]);
            NotifyModel::pushMany(array_column($ids, 'id'), 'post', 'New announcement: ' . ($title ?: mb_substr($body, 0, 60)), 'feed#p' . $id, (int) $u['id']);
        } elseif ($club) {
            $ids = self::q("SELECT user_id FROM club_members WHERE club_id = ? AND status = 'Active'", [$club]);
            NotifyModel::pushMany(array_column($ids, 'user_id'), 'club', 'New post in ' . (ClubModel::find($club)['name'] ?? 'your club'), 'clubs/' . $club, (int) $u['id']);
        }
        return [$id, null];
    }

    public static function comments(int $postId, array $u): array
    {
        $rows = self::q("SELECT m.id, m.user_id, m.body, m.status, m.created_at, us.full_name AS author, us.color AS author_color, us.role AS author_role FROM comments m JOIN users us ON us.id = m.user_id
            WHERE m.post_id = ? AND (m.status = 'Published' OR ? = 'admin') ORDER BY m.id", [$postId, $u['role']]);
        foreach ($rows as &$r) { $r['can_delete'] = (int) $r['user_id'] === (int) $u['id'] || $u['role'] === 'admin'; $r['mine'] = (int) $r['user_id'] === (int) $u['id']; }
        return $rows;
    }
    /** @return array{0:?array,1:?string} */
    public static function comment(int $postId, array $u, string $body): array
    {
        $p = self::find($postId);
        if (!$p || !self::canView($u, $p) || $p['status'] !== 'Published') { return [null, 'Post not found.']; }
        $body = self::clean($body);
        if ($body === '') { return [null, 'Write a comment first.']; }
        if (mb_strlen($body) > COMMENT_MAX) { return [null, 'Comments are limited to ' . COMMENT_MAX . ' characters.']; }
        $n = self::q('SELECT COUNT(*) AS n FROM comments WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)', [(int) $u['id']]);
        if ((int) $n[0]['n'] >= 3) { return [null, 'You are commenting too fast. Please wait a moment.']; }
        db()->prepare('INSERT INTO comments (post_id, user_id, body) VALUES (?,?,?)')->execute([$postId, (int) $u['id'], $body]);
        $id = (int) db()->lastInsertId();
        if ((int) $p['user_id'] !== (int) $u['id']) { NotifyModel::push((int) $p['user_id'], 'comment', $u['full_name'] . ' commented on your post.', 'feed#p' . $postId); }
        return [self::comments($postId, $u)[count(self::comments($postId, $u)) - 1] ?? ['id' => $id], null];
    }
    public static function deleteComment(int $id, array $u): ?string
    {
        $c = self::q('SELECT * FROM comments WHERE id = ?', [$id])[0] ?? null;
        if (!$c) { return 'Comment not found.'; }
        if ((int) $c['user_id'] !== (int) $u['id'] && $u['role'] !== 'admin') { return 'You can only delete your own comments.'; }
        db()->prepare('DELETE FROM comments WHERE id = ?')->execute([$id]);
        Audit::log('delete', 'comment', $id, 'Comment removed'); return null;
    }

    /** @return array{0:?array,1:?string} [state, error] */
    public static function like(int $postId, array $u): array
    {
        $p = self::find($postId);
        if (!$p || !self::canView($u, $p) || $p['status'] !== 'Published') { return [null, 'Post not found.']; }
        $had = self::q('SELECT 1 FROM post_likes WHERE post_id = ? AND user_id = ?', [$postId, (int) $u['id']]);
        if ($had) { db()->prepare('DELETE FROM post_likes WHERE post_id = ? AND user_id = ?')->execute([$postId, (int) $u['id']]); }
        else {
            db()->prepare('INSERT IGNORE INTO post_likes (post_id, user_id) VALUES (?,?)')->execute([$postId, (int) $u['id']]);
            if ((int) $p['user_id'] !== (int) $u['id']) { NotifyModel::push((int) $p['user_id'], 'like', $u['full_name'] . ' liked your post.', 'feed#p' . $postId); }
        }
        $n = (int) self::q('SELECT COUNT(*) AS n FROM post_likes WHERE post_id = ?', [$postId])[0]['n'];
        return [['liked' => $had ? 0 : 1, 'likes' => $n], null];
    }

    public static function pin(int $id, bool $pin, array $u): ?string
    {
        if (!in_array($u['role'], Auth::STAFF, true)) { return 'Only staff can pin posts.'; }
        $p = self::find($id); if (!$p || $p['status'] !== 'Published') { return 'Post not found.'; }
        if ($pin && !(int) $p['is_pinned'] && (int) self::q('SELECT COUNT(*) AS n FROM posts WHERE is_pinned = 1')[0]['n'] >= self::MAX_PINNED) { return 'You can pin at most ' . self::MAX_PINNED . ' posts. Unpin one first.'; }
        db()->prepare('UPDATE posts SET is_pinned = ? WHERE id = ?')->execute([$pin ? 1 : 0, $id]);
        Audit::log($pin ? 'pin' : 'unpin', 'post', $id); return null;
    }
    public static function delete(int $id, array $u): ?string
    {
        $p = self::find($id);
        if (!$p) { return 'Post not found.'; }
        if ((int) $p['user_id'] !== (int) $u['id'] && $u['role'] !== 'admin') { return 'You can only delete your own posts.'; }
        db()->prepare('DELETE FROM posts WHERE id = ?')->execute([$id]);
        Audit::log('delete', 'post', $id, 'Post deleted by ' . $u['username']); return null;
    }
    public static function setHidden(int $id, bool $hide, array $u): ?string
    {
        $p = self::find($id); if (!$p) { return 'Post not found.'; }
        if ($u['role'] !== 'admin' && !($u['role'] === 'staff' && (int) $p['user_id'] === (int) $u['id'])) { return 'Only moderators can hide posts.'; }
        db()->prepare('UPDATE posts SET status = ?, is_pinned = IF(?, 0, is_pinned) WHERE id = ?')->execute([$hide ? 'Hidden' : 'Published', $hide ? 1 : 0, $id]);
        if ($hide && (int) $p['user_id'] !== (int) $u['id']) { NotifyModel::push((int) $p['user_id'], 'moderation', 'One of your posts was hidden by a moderator.', 'feed'); }
        Audit::log($hide ? 'hide' : 'unhide', 'post', $id); return null;
    }

    // ------------------------------------------------------------ reports
    public static function report(string $entity, int $id, array $u, string $reason, string $note = ''): ?string
    {
        if (!in_array($entity, ['post', 'comment'], true)) { return 'Unknown item.'; }
        if (!in_array($reason, self::REASONS, true)) { return 'Choose a reason.'; }
        $row = $entity === 'post' ? self::find($id) : (self::q('SELECT c.*, p.programme_id FROM comments c JOIN posts p ON p.id = c.post_id WHERE c.id = ?', [$id])[0] ?? null);
        if (!$row) { return 'Item not found.'; }
        if ((int) $row['user_id'] === (int) $u['id']) { return 'You cannot report your own content.'; }
        if (self::q('SELECT 1 FROM reports WHERE entity = ? AND entity_id = ? AND reporter_id = ?', [$entity, $id, (int) $u['id']])) { return 'You already reported this.'; }
        db()->prepare('INSERT INTO reports (entity, entity_id, reporter_id, reason, note) VALUES (?,?,?,?,?)')->execute([$entity, $id, (int) $u['id'], $reason, mb_substr(trim($note), 0, 200) ?: null]);
        $n = (int) self::q("SELECT COUNT(DISTINCT reporter_id) AS n FROM reports WHERE entity = ? AND entity_id = ? AND status = 'Open'", [$entity, $id])[0]['n'];
        if ($n >= REPORTS_TO_HIDE && $row['status'] === 'Published') {
            db()->prepare('UPDATE ' . ($entity === 'post' ? 'posts' : 'comments') . " SET status = 'Hidden' WHERE id = ?")->execute([$id]);
            NotifyModel::push((int) $row['user_id'], 'moderation', 'Your ' . $entity . ' was hidden after several reports and is awaiting review.', 'feed');
            Audit::log('auto-hide', $entity, $id, "$n reports");
        }
        NotifyModel::pushMany(NotifyModel::admins(), 'report', "A $entity was reported ($reason).", 'admin/moderation', (int) $u['id']);
        return null;
    }
    public static function openReports(): array
    {
        $rows = self::q("SELECT r.entity, r.entity_id, COUNT(*) AS reports, GROUP_CONCAT(DISTINCT r.reason ORDER BY r.reason SEPARATOR ', ') AS reasons, MAX(r.created_at) AS last_at FROM reports r WHERE r.status = 'Open' GROUP BY r.entity, r.entity_id ORDER BY reports DESC, last_at DESC");
        foreach ($rows as &$r) {
            if ($r['entity'] === 'post') { $c = self::find((int) $r['entity_id']); $r['body'] = $c['body'] ?? '(deleted)'; $r['author'] = $c['author'] ?? '–'; $r['status'] = $c['status'] ?? 'Deleted'; $r['post_id'] = (int) $r['entity_id']; }
            else { $c = self::q('SELECT m.body, m.status, m.post_id, u.full_name AS author FROM comments m JOIN users u ON u.id = m.user_id WHERE m.id = ?', [(int) $r['entity_id']])[0] ?? null; $r['body'] = $c['body'] ?? '(deleted)'; $r['author'] = $c['author'] ?? '–'; $r['status'] = $c['status'] ?? 'Deleted'; $r['post_id'] = (int) ($c['post_id'] ?? 0); }
        }
        return $rows;
    }
    /** Moderator decision: 'hide' keeps the item hidden, 'delete' removes it, 'dismiss' restores it. */
    public static function resolve(string $entity, int $id, string $decision, array $u): ?string
    {
        if ($u['role'] !== 'admin') { return 'Only administrators can moderate.'; }
        if (!in_array($decision, ['hide', 'delete', 'dismiss'], true) || !in_array($entity, ['post', 'comment'], true)) { return 'Unknown decision.'; }
        $table = $entity === 'post' ? 'posts' : 'comments';
        $row = self::q("SELECT * FROM $table WHERE id = ?", [$id])[0] ?? null;
        if (!$row && $decision !== 'dismiss') { return 'Item not found.'; }
        if ($row) {
            if ($decision === 'hide') { db()->prepare("UPDATE $table SET status = 'Hidden' WHERE id = ?")->execute([$id]); NotifyModel::push((int) $row['user_id'], 'moderation', "Your $entity was removed for breaking the community rules.", 'feed'); }
            if ($decision === 'delete') { db()->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]); NotifyModel::push((int) $row['user_id'], 'moderation', "Your $entity was deleted by a moderator.", 'feed'); }
            if ($decision === 'dismiss') { db()->prepare("UPDATE $table SET status = 'Published' WHERE id = ?")->execute([$id]); }
        }
        db()->prepare("UPDATE reports SET status = ?, handled_by = ? WHERE entity = ? AND entity_id = ? AND status = 'Open'")->execute([$decision === 'dismiss' ? 'Dismissed' : 'Actioned', (int) $u['id'], $entity, $id]);
        Audit::log('moderate', $entity, $id, $decision); return null;
    }
}
