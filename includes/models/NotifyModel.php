<?php
declare(strict_types=1);

/** In-app notifications. */
final class NotifyModel
{
    public static function push(int $userId, string $type, string $message, ?string $link = null): void
    {
        db()->prepare('INSERT INTO notifications (user_id, type, message, link) VALUES (?,?,?,?)')->execute([$userId, $type, mb_substr($message, 0, 255), $link]);
    }
    /** @param int[] $userIds */
    public static function pushMany(array $userIds, string $type, string $message, ?string $link = null, int $exclude = 0): void
    {
        foreach (array_unique($userIds) as $id) { if ((int) $id !== $exclude) { self::push((int) $id, $type, $message, $link); } }
    }
    public static function admins(): array { return array_map('intval', db()->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1")->fetchAll(PDO::FETCH_COLUMN)); }

    public static function list(int $userId, int $limit = 40): array
    {
        $s = db()->prepare('SELECT id, type, message, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int) $limit);
        $s->execute([$userId]); return $s->fetchAll();
    }
    public static function unread(int $userId): int
    {
        $s = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0'); $s->execute([$userId]); return (int) $s->fetchColumn();
    }
    public static function read(int $userId, int $id = 0): void
    {
        if ($id > 0) { db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([$id, $userId]); }
        else { db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$userId]); }
    }
}
