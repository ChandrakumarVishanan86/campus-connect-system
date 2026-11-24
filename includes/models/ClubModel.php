<?php
declare(strict_types=1);

/** Clubs & societies: membership, approval for closed clubs, officer roles. */
final class ClubModel extends BaseModel
{
    protected static string $table = 'clubs';
    protected static array $fields = [
        'code'         => ['label' => 'Code', 'required' => true, 'max' => 10, 'upper' => true, 'unique' => true, 'regex' => '/^[A-Z0-9]{2,10}$/', 'regexMsg' => '2-10 letters or digits.'],
        'name'         => ['label' => 'Club name', 'required' => true, 'max' => 100],
        'category'     => ['label' => 'Category', 'type' => 'enum', 'in' => ['Academic', 'Sports', 'Arts', 'Service', 'Tech', 'Cultural'], 'required' => true],
        'description'  => ['label' => 'Description', 'max' => 500],
        'meeting_info' => ['label' => 'Meeting info', 'max' => 160],
        'join_policy'  => ['label' => 'Join policy', 'type' => 'enum', 'in' => ['Open', 'Approval'], 'required' => true],
        'advisor_id'   => ['label' => 'Advisor', 'type' => 'ref', 'ref' => 'users'],
        'is_active'    => ['label' => 'Active', 'type' => 'bool'],
    ];
    private static function q(string $sql, array $p = []): array { $s = db()->prepare($sql); $s->execute($p); return $s->fetchAll(); }

    protected static function baseSelect(): string
    {
        return "SELECT t.*, a.full_name AS advisor,
            (SELECT COUNT(*) FROM club_members m WHERE m.club_id = t.id AND m.status = 'Active') AS members,
            (SELECT COUNT(*) FROM club_members m WHERE m.club_id = t.id AND m.status = 'Pending') AS pending
            FROM clubs t LEFT JOIN users a ON a.id = t.advisor_id";
    }
    protected static function orderBy(): string { return 'ORDER BY t.name'; }
    public static function validate(array $d, int $id = 0): array
    {
        $err = parent::validate($d, $id);
        if (!isset($err['advisor_id']) && !empty($d['advisor_id']) && !self::q("SELECT 1 FROM users WHERE id = ? AND role IN ('staff','admin')", [(int) $d['advisor_id']])) { $err['advisor_id'] = 'The advisor must be a staff member.'; }
        return $err;
    }
    protected static function afterSave(int $id, array $d, bool $isNew, array $raw): void { Audit::log($isNew ? 'create' : 'update', 'club', $id, $d['name']); }
    public static function beforeDelete(int $id): ?string
    {
        return self::q('SELECT COUNT(*) AS n FROM events WHERE club_id = ?', [$id])[0]['n'] > 0 ? 'This club has events. Mark it inactive instead of deleting it.' : null;
    }

    /** Clubs with the viewer's own membership. */
    public static function listFor(array $u): array
    {
        return self::q("SELECT t.id, t.code, t.name, t.category, t.description, t.meeting_info, t.join_policy, t.is_active, a.full_name AS advisor,
            (SELECT COUNT(*) FROM club_members m WHERE m.club_id = t.id AND m.status = 'Active') AS members,
            (SELECT m.status FROM club_members m WHERE m.club_id = t.id AND m.user_id = ?) AS my_status,
            (SELECT m.role FROM club_members m WHERE m.club_id = t.id AND m.user_id = ?) AS my_role
            FROM clubs t LEFT JOIN users a ON a.id = t.advisor_id WHERE t.is_active = 1 ORDER BY t.name", [(int) $u['id'], (int) $u['id']]);
    }
    public static function detail(int $id, array $u): ?array
    {
        $c = self::find($id); if (!$c) { return null; }
        $m = self::q('SELECT status, role FROM club_members WHERE club_id = ? AND user_id = ?', [$id, (int) $u['id']])[0] ?? null;
        $c['my_status'] = $m['status'] ?? null; $c['my_role'] = $m['role'] ?? null; $c['can_manage'] = self::canManage($id, $u);
        return $c;
    }
    public static function members(int $id, bool $includePending): array
    {
        return self::q("SELECT m.user_id, m.role, m.status, m.joined_at, u.full_name, u.color, p.code AS programme, u.batch FROM club_members m JOIN users u ON u.id = m.user_id LEFT JOIN programmes p ON p.id = u.programme_id
            WHERE m.club_id = ?" . ($includePending ? '' : " AND m.status = 'Active'") . " ORDER BY FIELD(m.status,'Pending','Active'), FIELD(m.role,'President','Officer','Member'), u.full_name", [$id]);
    }
    /** President / officer of the club, its advisor, or an administrator. */
    public static function canManage(int $clubId, array $u): bool
    {
        if ($u['role'] === 'admin') { return true; }
        $c = self::q('SELECT advisor_id FROM clubs WHERE id = ? AND is_active = 1', [$clubId])[0] ?? null;
        if (!$c) { return false; }
        if ((int) $c['advisor_id'] === (int) $u['id']) { return true; }
        return (bool) self::q("SELECT 1 FROM club_members WHERE club_id = ? AND user_id = ? AND status = 'Active' AND role IN ('President','Officer')", [$clubId, (int) $u['id']]);
    }
    public static function managerIds(int $clubId): array
    {
        $r = self::q("SELECT user_id AS id FROM club_members WHERE club_id = ? AND status = 'Active' AND role IN ('President','Officer') UNION SELECT advisor_id FROM clubs WHERE id = ? AND advisor_id IS NOT NULL", [$clubId, $clubId]);
        return array_map('intval', array_column($r, 'id'));
    }

    /** @return array{0:?string,1:?string} [resulting status, error] */
    public static function join(int $id, array $u): array
    {
        if ($u['role'] !== 'student') { return [null, 'Only students can join clubs. Staff advise clubs from the admin area.']; }
        $c = self::find($id); if (!$c || !(int) $c['is_active']) { return [null, 'Club not found.']; }
        if (self::q('SELECT 1 FROM club_members WHERE club_id = ? AND user_id = ?', [$id, (int) $u['id']])) { return [null, 'You already belong to this club or have a pending request.']; }
        if ((int) self::q("SELECT COUNT(*) AS n FROM club_members WHERE user_id = ? AND status = 'Active'", [(int) $u['id']])[0]['n'] >= MAX_CLUB_JOINS) { return [null, 'You can be an active member of at most ' . MAX_CLUB_JOINS . ' clubs.']; }
        $status = $c['join_policy'] === 'Open' ? 'Active' : 'Pending';
        db()->prepare('INSERT INTO club_members (club_id, user_id, role, status) VALUES (?,?,?,?)')->execute([$id, (int) $u['id'], 'Member', $status]);
        if ($status === 'Pending') { NotifyModel::pushMany(self::managerIds($id), 'club', $u['full_name'] . ' asked to join ' . $c['name'] . '.', 'clubs/' . $id); }
        return [$status, null];
    }
    public static function leave(int $id, array $u): ?string
    {
        $m = self::q('SELECT role FROM club_members WHERE club_id = ? AND user_id = ?', [$id, (int) $u['id']])[0] ?? null;
        if (!$m) { return 'You are not a member of this club.'; }
        if ($m['role'] === 'President') { return 'Presidents must hand over the role to another member before leaving.'; }
        db()->prepare('DELETE FROM club_members WHERE club_id = ? AND user_id = ?')->execute([$id, (int) $u['id']]); return null;
    }
    public static function decide(int $id, int $userId, bool $approve, array $u): ?string
    {
        if (!self::canManage($id, $u)) { return 'You cannot manage this club.'; }
        $m = self::q("SELECT 1 FROM club_members WHERE club_id = ? AND user_id = ? AND status = 'Pending'", [$id, $userId]);
        if (!$m) { return 'No pending request from this member.'; }
        $name = self::find($id)['name'];
        if ($approve) {
            if ((int) self::q("SELECT COUNT(*) AS n FROM club_members WHERE user_id = ? AND status = 'Active'", [$userId])[0]['n'] >= MAX_CLUB_JOINS) { return 'This student is already in the maximum number of clubs.'; }
            db()->prepare("UPDATE club_members SET status = 'Active', joined_at = NOW() WHERE club_id = ? AND user_id = ?")->execute([$id, $userId]);
            NotifyModel::push($userId, 'club', "Welcome! Your request to join $name was approved.", 'clubs/' . $id);
        } else {
            db()->prepare('DELETE FROM club_members WHERE club_id = ? AND user_id = ?')->execute([$id, $userId]);
            NotifyModel::push($userId, 'club', "Your request to join $name was declined.", 'clubs/' . $id);
        }
        return null;
    }
    public static function setRole(int $id, int $userId, string $role, array $u): ?string
    {
        if (!in_array($role, ['Member', 'Officer', 'President'], true)) { return 'Unknown role.'; }
        if (!self::canManage($id, $u)) { return 'You cannot manage this club.'; }
        $m = self::q("SELECT role FROM club_members WHERE club_id = ? AND user_id = ? AND status = 'Active'", [$id, $userId])[0] ?? null;
        if (!$m) { return 'That person is not an active member.'; }
        $isTop = $u['role'] === 'admin' || (self::find($id)['advisor_id'] ?? 0) == $u['id'] || self::q("SELECT 1 FROM club_members WHERE club_id = ? AND user_id = ? AND role = 'President'", [$id, (int) $u['id']]);
        if (($role === 'President' || $m['role'] === 'President') && !$isTop) { return 'Only the president, the advisor or an administrator can change the presidency.'; }
        if ($m['role'] === 'President' && $role !== 'President') { return 'Appoint a new president instead - a club always needs one.'; }
        $pdo = db(); $pdo->beginTransaction();
        try {
            if ($role === 'President') { $pdo->prepare("UPDATE club_members SET role = 'Officer' WHERE club_id = ? AND role = 'President'")->execute([$id]); }
            $pdo->prepare('UPDATE club_members SET role = ? WHERE club_id = ? AND user_id = ?')->execute([$role, $id, $userId]);
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
        NotifyModel::push($userId, 'club', 'Your role in ' . self::find($id)['name'] . ' is now ' . $role . '.', 'clubs/' . $id);
        return null;
    }
    public static function remove(int $id, int $userId, array $u): ?string
    {
        if (!self::canManage($id, $u)) { return 'You cannot manage this club.'; }
        $m = self::q('SELECT role FROM club_members WHERE club_id = ? AND user_id = ?', [$id, $userId])[0] ?? null;
        if (!$m) { return 'Member not found.'; }
        if ($m['role'] === 'President') { return 'Appoint a new president before removing the current one.'; }
        if ($userId === (int) $u['id']) { return 'Use "Leave club" to remove yourself.'; }
        db()->prepare('DELETE FROM club_members WHERE club_id = ? AND user_id = ?')->execute([$id, $userId]);
        NotifyModel::push($userId, 'club', 'You were removed from ' . self::find($id)['name'] . '.', 'clubs'); return null;
    }
    public static function updateAbout(int $id, array $u, string $desc, string $meeting, string $policy): ?string
    {
        if (!self::canManage($id, $u)) { return 'You cannot manage this club.'; }
        if (mb_strlen($desc) > 500 || mb_strlen($meeting) > 160) { return 'Text is too long.'; }
        if (!in_array($policy, ['Open', 'Approval'], true)) { return 'Invalid join policy.'; }
        db()->prepare('UPDATE clubs SET description = ?, meeting_info = ?, join_policy = ? WHERE id = ?')->execute([trim($desc) ?: null, trim($meeting) ?: null, $policy, $id]);
        Audit::log('update', 'club', $id, 'About section updated'); return null;
    }
}
