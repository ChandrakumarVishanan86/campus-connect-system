<?php
declare(strict_types=1);

/** Lost & Found board: post -> claim -> owner confirms -> resolved. */
final class LostModel
{
    private static function q(string $sql, array $p = []): array { $s = db()->prepare($sql); $s->execute($p); return $s->fetchAll(); }
    public static function find(int $id): ?array { return self::q('SELECT * FROM lost_items WHERE id = ?', [$id])[0] ?? null; }

    public static function list(array $u, array $f = []): array
    {
        $w = ['1=1']; $p = [];
        if (in_array($f['kind'] ?? '', ['Lost', 'Found'], true)) { $w[] = 'i.kind = ?'; $p[] = $f['kind']; }
        $st = $f['status'] ?? 'open';
        if ($st === 'open') { $w[] = "i.status <> 'Resolved'"; } elseif (in_array($st, ['Open', 'Claim Pending', 'Resolved'], true)) { $w[] = 'i.status = ?'; $p[] = $st; }
        if ($st === 'mine') { $w[] = '(i.user_id = ? OR i.claimant_id = ?)'; array_push($p, (int) $u['id'], (int) $u['id']); }
        if (!empty($f['q'])) { $w[] = '(i.title LIKE ? OR i.description LIKE ? OR i.location LIKE ?)'; $l = '%' . addcslashes((string) $f['q'], '%_') . '%'; array_push($p, $l, $l, $l); }
        $rows = self::q("SELECT i.*, u.full_name AS poster, u.phone AS poster_phone, u.color AS poster_color, c.full_name AS claimant, c.phone AS claimant_phone FROM lost_items i JOIN users u ON u.id = i.user_id LEFT JOIN users c ON c.id = i.claimant_id
            WHERE " . implode(' AND ', $w) . ' ORDER BY FIELD(i.status,\'Open\',\'Claim Pending\',\'Resolved\'), i.created_at DESC LIMIT 150', $p);
        foreach ($rows as &$r) {
            $r['mine'] = (int) $r['user_id'] === (int) $u['id']; $r['is_claimant'] = (int) $r['claimant_id'] === (int) $u['id'];
            $involved = $r['mine'] || $r['is_claimant'] || $u['role'] === 'admin';
            $r['can_claim'] = !$r['mine'] && $r['status'] === 'Open';
            $r['can_decide'] = ($r['mine'] || $u['role'] === 'admin') && $r['status'] === 'Claim Pending';
            $r['can_delete'] = ($r['mine'] || $u['role'] === 'admin');
            if (!$involved || $r['status'] === 'Open') { unset($r['poster_phone'], $r['claimant_phone'], $r['claim_note'], $r['claimant_id']); $r['claimant'] = $r['status'] === 'Open' ? null : 'Someone'; }
            elseif ($r['mine'] || $u['role'] === 'admin') { unset($r['poster_phone']); } else { unset($r['claimant_phone']); }
        }
        return $rows;
    }
    /** @return array{0:?int,1:?string} */
    public static function create(array $u, array $in): array
    {
        $kind = (string) ($in['kind'] ?? ''); $title = trim((string) ($in['title'] ?? '')); $desc = trim((string) ($in['description'] ?? '')); $loc = trim((string) ($in['location'] ?? '')); $on = (string) ($in['happened_on'] ?? '');
        if (!in_array($kind, ['Lost', 'Found'], true)) { return [null, 'Choose Lost or Found.']; }
        if (mb_strlen($title) < 3 || mb_strlen($title) > 100) { return [null, 'Item name must be 3-100 characters.']; }
        if (mb_strlen($desc) > 400) { return [null, 'Description is too long (max 400).']; }
        if (mb_strlen($loc) < 2 || mb_strlen($loc) > 100) { return [null, 'Tell us where (2-100 characters).']; }
        if (!valid_date($on) || $on > date('Y-m-d') || $on < date('Y-m-d', strtotime('-60 days'))) { return [null, 'The date must be within the last 60 days and not in the future.']; }
        if ((int) self::q("SELECT COUNT(*) AS n FROM lost_items WHERE user_id = ? AND status <> 'Resolved'", [(int) $u['id']])[0]['n'] >= 10) { return [null, 'You already have 10 open reports. Resolve or delete some first.']; }
        db()->prepare('INSERT INTO lost_items (user_id, kind, title, description, location, happened_on) VALUES (?,?,?,?,?,?)')->execute([(int) $u['id'], $kind, $title, $desc ?: null, $loc, $on]);
        return [(int) db()->lastInsertId(), null];
    }
    public static function claim(int $id, array $u, string $note): ?string
    {
        $i = self::find($id); if (!$i) { return 'Report not found.'; }
        if ((int) $i['user_id'] === (int) $u['id']) { return 'You cannot claim your own report.'; }
        if ($i['status'] !== 'Open') { return 'This item already has a pending claim or is resolved.'; }
        $note = trim($note);
        if (mb_strlen($note) < 10 || mb_strlen($note) > 300) { return $i['kind'] === 'Found' ? 'Describe the item so the finder can verify it is yours (10-300 characters).' : 'Tell the owner where and when you found it (10-300 characters).'; }
        db()->prepare("UPDATE lost_items SET status = 'Claim Pending', claimant_id = ?, claim_note = ? WHERE id = ? AND status = 'Open'")->execute([(int) $u['id'], $note, $id]);
        NotifyModel::push((int) $i['user_id'], 'lost', $u['full_name'] . ' responded to "' . $i['title'] . '". Review the claim.', 'lost-found'); return null;
    }
    public static function decide(int $id, bool $confirm, array $u): ?string
    {
        $i = self::find($id); if (!$i) { return 'Report not found.'; }
        if ((int) $i['user_id'] !== (int) $u['id'] && $u['role'] !== 'admin') { return 'Only the person who posted can decide.'; }
        if ($i['status'] !== 'Claim Pending') { return 'There is no pending claim.'; }
        if ($confirm) { db()->prepare("UPDATE lost_items SET status = 'Resolved', resolved_at = NOW() WHERE id = ?")->execute([$id]); }
        else { db()->prepare("UPDATE lost_items SET status = 'Open', claimant_id = NULL, claim_note = NULL WHERE id = ?")->execute([$id]); }
        NotifyModel::push((int) $i['claimant_id'], 'lost', $confirm ? '"' . $i['title'] . '" is confirmed. Arrange the hand-over with the poster.' : 'Your claim on "' . $i['title'] . '" was not accepted.', 'lost-found'); return null;
    }
    public static function delete(int $id, array $u): ?string
    {
        $i = self::find($id); if (!$i) { return 'Report not found.'; }
        if ((int) $i['user_id'] !== (int) $u['id'] && $u['role'] !== 'admin') { return 'You can only delete your own reports.'; }
        db()->prepare('DELETE FROM lost_items WHERE id = ?')->execute([$id]); return null;
    }
}
