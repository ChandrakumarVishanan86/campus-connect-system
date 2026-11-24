<?php
declare(strict_types=1);

/** Read-only analytics for the admin insights page. */
final class InsightModel
{
    private static function q(string $sql, array $p = []): array { $s = db()->prepare($sql); $s->execute($p); return $s->fetchAll(); }

    public static function overview(): array
    {
        $k = self::q("SELECT
            (SELECT COUNT(*) FROM users WHERE is_active = 1) AS people,
            (SELECT COUNT(*) FROM users WHERE role = 'student' AND is_active = 1) AS students,
            (SELECT COUNT(*) FROM posts WHERE status = 'Published' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS posts_7d,
            (SELECT COUNT(*) FROM clubs WHERE is_active = 1) AS clubs,
            (SELECT COUNT(*) FROM events WHERE status = 'Approved' AND ends_at >= NOW()) AS upcoming,
            (SELECT COUNT(*) FROM events WHERE status = 'Pending') AS pending_events,
            (SELECT COUNT(DISTINCT entity, entity_id) FROM reports WHERE status = 'Open') AS open_reports,
            (SELECT COUNT(*) FROM lost_items WHERE status <> 'Resolved') AS open_items")[0];
        $active = self::q("SELECT COUNT(*) AS n FROM (SELECT user_id FROM posts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) UNION SELECT user_id FROM comments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) UNION SELECT user_id FROM post_likes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) UNION SELECT user_id FROM event_rsvps WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) x")[0]['n'];
        $k['active_7d'] = (int) $active;
        $k['engagement_pct'] = (int) $k['students'] > 0 ? round(100 * (int) $active / ((int) $k['people']), 1) : 0;
        $att = self::q("SELECT SUM(r.status = 'Going') AS going, SUM(r.status = 'Going' AND r.checked_in_at IS NOT NULL) AS shown FROM event_rsvps r JOIN events e ON e.id = r.event_id WHERE e.status = 'Approved' AND e.ends_at < NOW()")[0];
        $k['attendance_pct'] = (int) $att['going'] > 0 ? round(100 * (int) $att['shown'] / (int) $att['going'], 1) : null;
        return [
            'kpi' => $k,
            'activity' => self::q("SELECT d.day, (SELECT COUNT(*) FROM posts WHERE DATE(created_at) = d.day) AS posts, (SELECT COUNT(*) FROM comments WHERE DATE(created_at) = d.day) AS comments
                FROM (SELECT DATE_SUB(CURDATE(), INTERVAL n DAY) AS day FROM (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12 UNION SELECT 13) x) d ORDER BY d.day"),
            'types' => self::q("SELECT type AS k, COUNT(*) AS n FROM posts WHERE status = 'Published' GROUP BY type ORDER BY n DESC"),
            'clubs' => self::q("SELECT c.name AS k, COUNT(m.user_id) AS n FROM clubs c LEFT JOIN club_members m ON m.club_id = c.id AND m.status = 'Active' WHERE c.is_active = 1 GROUP BY c.id, c.name ORDER BY n DESC"),
            'programmes' => self::q("SELECT p.code AS k, COUNT(DISTINCT u.id) AS people, (SELECT COUNT(*) FROM posts po JOIN users x ON x.id = po.user_id WHERE x.programme_id = p.id) AS posts,
                (SELECT COUNT(*) FROM club_members m JOIN users x ON x.id = m.user_id WHERE x.programme_id = p.id AND m.status = 'Active') AS memberships FROM programmes p LEFT JOIN users u ON u.programme_id = p.id AND u.is_active = 1 GROUP BY p.id, p.code ORDER BY people DESC"),
            'events' => self::q("SELECT e.id, e.title, e.starts_at, e.capacity, SUM(r.status = 'Going') AS going, SUM(r.status = 'Going' AND r.checked_in_at IS NOT NULL) AS shown
                FROM events e LEFT JOIN event_rsvps r ON r.event_id = e.id WHERE e.status = 'Approved' GROUP BY e.id, e.title, e.starts_at, e.capacity ORDER BY going DESC LIMIT 8"),
            'leaders' => self::q("SELECT u.id, u.full_name, u.color, u.role, (SELECT COUNT(*) FROM posts p WHERE p.user_id = u.id AND p.status = 'Published' AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS posts,
                (SELECT COUNT(*) FROM comments c WHERE c.user_id = u.id AND c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS comments,
                (SELECT COUNT(*) FROM event_rsvps r WHERE r.user_id = u.id AND r.checked_in_at IS NOT NULL) AS attended FROM users u WHERE u.role = 'student' AND u.is_active = 1 ORDER BY (posts * 3 + comments + attended * 2) DESC LIMIT 6"),
        ];
    }
}
