<?php
declare(strict_types=1);

/** Accounts (admin CRUD), self-registration, profiles and the people directory. */
final class UserModel extends BaseModel
{
    protected static string $table = 'users';
    protected static array $fields = [
        'username'     => ['label' => 'Username', 'required' => true, 'max' => 50, 'lower' => true, 'unique' => true, 'regex' => '/^[a-z0-9._\-]{3,50}$/', 'regexMsg' => '3-50 chars: a-z, 0-9 . _ -'],
        'full_name'    => ['label' => 'Full name', 'required' => true, 'max' => 120, 'regex' => "/^[\p{L}][\p{L} .'\-]*$/u", 'regexMsg' => 'Name may contain letters only.'],
        'email'        => ['label' => 'Email', 'type' => 'email', 'max' => 120, 'lower' => true, 'unique' => true],
        'phone'        => ['label' => 'Phone', 'max' => 20, 'regex' => '/^[0-9+\-\s]{9,20}$/', 'regexMsg' => 'Enter a valid phone number.'],
        'role'         => ['label' => 'Role', 'type' => 'enum', 'in' => ['admin', 'staff', 'student'], 'required' => true],
        'programme_id' => ['label' => 'Programme', 'type' => 'ref', 'ref' => 'programmes'],
        'batch'        => ['label' => 'Batch (intake year)', 'type' => 'int', 'min' => 2015, 'max' => 2035],
        'title'        => ['label' => 'Job title', 'max' => 60],
        'is_active'    => ['label' => 'Active', 'type' => 'bool'],
    ];
    private static function q(string $sql, array $p = []): array { $s = db()->prepare($sql); $s->execute($p); return $s->fetchAll(); }

    protected static function baseSelect(): string
    {
        return 'SELECT t.id, t.username, t.full_name, t.email, t.phone, t.role, t.programme_id, t.batch, t.title, t.color, t.is_active, t.last_login, t.created_at, p.code AS programme FROM users t LEFT JOIN programmes p ON p.id = t.programme_id';
    }
    protected static function orderBy(): string { return "ORDER BY FIELD(t.role,'admin','staff','student'), t.full_name"; }
    public static function validate(array $d, int $id = 0): array
    {
        $err = parent::validate($d, $id);
        $pw = (string) ($_POST['password'] ?? '');
        if (($id === 0 || $pw !== '') && strlen($pw) < 8) { $err['password'] = 'Password must be at least 8 characters.'; }
        if (($d['role'] ?? '') === 'student' && empty($d['programme_id'])) { $err['programme_id'] = 'Students need a programme.'; }
        if ($id > 0 && $id === Auth::id()) {
            if (($d['role'] ?? '') !== Auth::role()) { $err['role'] = 'You cannot change your own role.'; }
            if (empty($d['is_active'])) { $err['is_active'] = 'You cannot deactivate your own account.'; }
        }
        return $err;
    }
    protected static function extraInsert(array $raw): array { return ['password' => password_hash((string) ($raw['password'] ?? ''), PASSWORD_DEFAULT), 'color' => random_int(0, 7)]; }
    protected static function afterSave(int $id, array $d, bool $isNew, array $raw): void
    {
        $pw = (string) ($raw['password'] ?? '');
        if (!$isNew && $pw !== '') { db()->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), $id]); }
        Audit::log($isNew ? 'create' : 'update', 'user', $id, ($isNew ? 'Created ' : 'Updated ') . $d['username']);
    }
    public static function beforeDelete(int $id): ?string
    {
        if ($id === Auth::id()) { return 'You cannot delete your own account.'; }
        $u = static::find($id);
        if ($u && $u['role'] === 'admin' && (int) db()->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn() <= 1) { return 'You cannot delete the last administrator.'; }
        return null;
    }

    /** Public sign-up for students. @return array{0:?int,1:?string|array} */
    public static function register(array $in): array
    {
        $d = static::clean($in + ['role' => 'student', 'is_active' => 1]);
        $d['role'] = 'student'; $d['is_active'] = 1; $d['title'] = null;
        $_POST['password'] = (string) ($in['password'] ?? '');
        $err = static::validate($d);
        if ((string) ($in['password'] ?? '') !== (string) ($in['password2'] ?? '')) { $err['password2'] = 'Passwords do not match.'; }
        if (empty($d['email'])) { $err['email'] = 'Email is required.'; }
        if ($err) { return [null, $err]; }
        $id = static::save($d, 0, ['password' => (string) $in['password']]);
        NotifyModel::push($id, 'welcome', 'Welcome to CampusConnect! Join a club and RSVP to an event to get started.', 'clubs');
        return [$id, null];
    }
    /** Self-service profile edit. */
    public static function updateProfile(int $id, array $in): array
    {
        $name = trim((string) ($in['full_name'] ?? '')); $err = [];
        if (!preg_match("/^[\p{L}][\p{L} .'\-]{1,119}$/u", $name)) { $err['full_name'] = 'Name may contain letters only (2-120).'; }
        $email = strtolower(trim((string) ($in['email'] ?? '')));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $err['email'] = 'Enter a valid email.'; }
        elseif ($email !== '' && static::exists('email', $email, $id)) { $err['email'] = 'This email is already in use.'; }
        $phone = trim((string) ($in['phone'] ?? '')); if ($phone !== '' && !preg_match('/^[0-9+\-\s]{9,20}$/', $phone)) { $err['phone'] = 'Enter a valid phone number.'; }
        $bio = trim((string) ($in['bio'] ?? '')); if (mb_strlen($bio) > 300) { $err['bio'] = 'Bio is too long (max 300).'; }
        $int = trim((string) ($in['interests'] ?? '')); if (mb_strlen($int) > 160) { $err['interests'] = 'Interests are too long (max 160).'; }
        $color = (int) ($in['color'] ?? 0); if ($color < 0 || $color > 7) { $color = 0; }
        if ($err) { return [false, $err]; }
        db()->prepare('UPDATE users SET full_name = ?, email = ?, phone = ?, bio = ?, interests = ?, color = ? WHERE id = ?')->execute([$name, $email ?: null, $phone ?: null, $bio ?: null, $int ?: null, $color, $id]);
        $_SESSION['user']['full_name'] = $name; $_SESSION['user']['color'] = $color;
        return [true, null];
    }
    public static function changePassword(int $id, string $old, string $new): ?string
    {
        $h = self::q('SELECT password FROM users WHERE id = ?', [$id])[0]['password'] ?? '';
        if (!password_verify($old, $h)) { return 'Your current password is not correct.'; }
        if (strlen($new) < 8) { return 'The new password must be at least 8 characters.'; }
        if ($new === $old) { return 'Choose a password different from the current one.'; }
        db()->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $id]);
        Audit::log('password', 'user', $id, 'Password changed'); return null;
    }

    public static function directory(array $f = []): array
    {
        $w = ['u.is_active = 1']; $p = [];
        if (!empty($f['q'])) { $w[] = '(u.full_name LIKE ? OR u.username LIKE ? OR u.interests LIKE ?)'; $l = '%' . addcslashes((string) $f['q'], '%_') . '%'; array_push($p, $l, $l, $l); }
        if (!empty($f['programme_id'])) { $w[] = 'u.programme_id = ?'; $p[] = (int) $f['programme_id']; }
        if (in_array($f['role'] ?? '', ['admin', 'staff', 'student'], true)) { $w[] = 'u.role = ?'; $p[] = $f['role']; }
        if (!empty($f['batch'])) { $w[] = 'u.batch = ?'; $p[] = (int) $f['batch']; }
        return self::q("SELECT u.id, u.full_name, u.role, u.title, u.bio, u.color, u.batch, pr.code AS programme,
            (SELECT COUNT(*) FROM club_members m WHERE m.user_id = u.id AND m.status = 'Active') AS clubs FROM users u LEFT JOIN programmes pr ON pr.id = u.programme_id
            WHERE " . implode(' AND ', $w) . ' ORDER BY u.full_name LIMIT 120', $p);
    }
    public static function profile(int $id, array $viewer): ?array
    {
        $u = self::q('SELECT u.id, u.username, u.full_name, u.role, u.title, u.bio, u.interests, u.color, u.batch, u.created_at, pr.code AS programme, pr.name AS programme_name FROM users u LEFT JOIN programmes pr ON pr.id = u.programme_id WHERE u.id = ? AND u.is_active = 1', [$id])[0] ?? null;
        if (!$u) { return null; }
        if ($viewer['role'] === 'admin' || (int) $viewer['id'] === $id) { $x = self::q('SELECT email, phone FROM users WHERE id = ?', [$id])[0]; $u += $x; }
        $u['clubs'] = self::q("SELECT c.id, c.name, m.role FROM club_members m JOIN clubs c ON c.id = m.club_id WHERE m.user_id = ? AND m.status = 'Active' ORDER BY c.name", [$id]);
        $u['stats'] = self::q("SELECT (SELECT COUNT(*) FROM posts WHERE user_id = ? AND status = 'Published') AS posts, (SELECT COUNT(*) FROM comments WHERE user_id = ?) AS comments,
            (SELECT COUNT(*) FROM event_rsvps WHERE user_id = ? AND checked_in_at IS NOT NULL) AS attended, (SELECT COUNT(*) FROM post_likes l JOIN posts p ON p.id = l.post_id WHERE p.user_id = ?) AS likes_received", [$id, $id, $id, $id])[0];
        $u['mine'] = (int) $viewer['id'] === $id;
        return $u;
    }
}
