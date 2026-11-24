<?php
declare(strict_types=1);

/** Campus events: approval workflow, venue clash rules, capacity + waitlist, RSVP and code check-in. */
final class EventModel
{
    public const CATEGORIES = ['Academic', 'Sports', 'Arts', 'Service', 'Tech', 'Cultural', 'Social'];
    public const VENUES = ['Main Auditorium', 'Lecture Hall A', 'Lecture Hall B', 'Computer Lab 1', 'Sports Ground', 'Library Hall', 'Canteen Lawn', 'Seminar Room'];
    private const MAX_HOURS = 12;
    private const LATEST_END = '20:00';          // campus events end by 8 pm (see rejected "Late Night Party" in the seed)

    private static function q(string $sql, array $p = []): array { $s = db()->prepare($sql); $s->execute($p); return $s->fetchAll(); }
    private static function tx(callable $fn): mixed
    {
        $pdo = db(); $pdo->beginTransaction();
        try { $r = $fn(); $pdo->commit(); return $r; } catch (Throwable $e) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $e; }
    }

    private static function select(): string
    {
        return "SELECT e.id, e.title, e.description, e.category, e.club_id, e.organiser_id, e.venue, e.starts_at, e.ends_at, e.capacity, e.status, e.decision_note, e.created_at,
            c.name AS club_name, o.full_name AS organiser,
            (SELECT COUNT(*) FROM event_rsvps r WHERE r.event_id = e.id AND r.status = 'Going') AS going,
            (SELECT COUNT(*) FROM event_rsvps r WHERE r.event_id = e.id AND r.status = 'Interested') AS interested,
            (SELECT COUNT(*) FROM event_rsvps r WHERE r.event_id = e.id AND r.status = 'Waitlist') AS waitlist,
            (SELECT COUNT(*) FROM event_rsvps r WHERE r.event_id = e.id AND r.checked_in_at IS NOT NULL) AS checked_in,
            (SELECT r.status FROM event_rsvps r WHERE r.event_id = e.id AND r.user_id = ?) AS my_status,
            (SELECT r.checked_in_at FROM event_rsvps r WHERE r.event_id = e.id AND r.user_id = ?) AS my_checkin
            FROM events e LEFT JOIN clubs c ON c.id = e.club_id JOIN users o ON o.id = e.organiser_id";
    }
    private static function decorate(array $rows, array $u): array
    {
        foreach ($rows as &$r) {
            $r['can_manage'] = self::canManageRow($r, $u);
            $r['full'] = $r['capacity'] !== null && (int) $r['going'] >= (int) $r['capacity'];
            $r['is_past'] = strtotime($r['ends_at']) < time();
            $r['ongoing'] = strtotime($r['starts_at']) <= time() && !$r['is_past'];
            $r['checkin_open'] = $r['status'] === 'Approved' && time() >= strtotime($r['starts_at']) - CHECKIN_EARLY_MIN * 60 && time() <= strtotime($r['ends_at']) + 3600;
        }
        return $rows;
    }
    private static function canManageRow(array $e, array $u): bool
    {
        if ($u['role'] === 'admin' || (int) $e['organiser_id'] === (int) $u['id']) { return true; }
        return !empty($e['club_id']) && ClubModel::canManage((int) $e['club_id'], $u);
    }

    public static function find(int $id, array $u): ?array
    {
        $r = self::q(self::select() . ' WHERE e.id = ?', [(int) $u['id'], (int) $u['id'], $id]);
        return $r ? self::decorate($r, $u)[0] : null;
    }
    /** Non-approved events are only visible to their organisers, club managers and administrators. */
    public static function canView(array $e, array $u): bool { return $e['status'] === 'Approved' || $e['status'] === 'Cancelled' || self::canManageRow($e, $u); }

    /** @param array{scope?:string,category?:string,club_id?:int,q?:string} $f */
    public static function list(array $u, array $f = []): array
    {
        $w = []; $p = [(int) $u['id'], (int) $u['id']];
        $scope = $f['scope'] ?? 'upcoming';
        switch ($scope) {
            case 'past':    $w[] = "e.status = 'Approved' AND e.ends_at < NOW()"; $order = 'e.starts_at DESC'; break;
            case 'mine':    $w[] = "e.status = 'Approved' AND EXISTS (SELECT 1 FROM event_rsvps r WHERE r.event_id = e.id AND r.user_id = ?)"; $p[] = (int) $u['id']; $order = 'e.starts_at'; break;
            case 'organising': $w[] = '(e.organiser_id = ? OR e.club_id IN (SELECT club_id FROM club_members WHERE user_id = ? AND status = \'Active\' AND role IN (\'President\',\'Officer\')))'; array_push($p, (int) $u['id'], (int) $u['id']); $order = 'e.starts_at DESC'; break;
            case 'pending': $w[] = "e.status = 'Pending'"; if ($u['role'] !== 'admin') { $w[] = 'e.organiser_id = ?'; $p[] = (int) $u['id']; } $order = 'e.created_at'; break;
            default:        $w[] = "e.status = 'Approved' AND e.ends_at >= NOW()"; $order = 'e.starts_at';
        }
        if (!empty($f['category']) && in_array($f['category'], self::CATEGORIES, true)) { $w[] = 'e.category = ?'; $p[] = $f['category']; }
        if (!empty($f['club_id'])) { $w[] = 'e.club_id = ?'; $p[] = (int) $f['club_id']; }
        if (!empty($f['q'])) { $w[] = '(e.title LIKE ? OR e.venue LIKE ? OR e.description LIKE ?)'; $l = '%' . addcslashes((string) $f['q'], '%_') . '%'; array_push($p, $l, $l, $l); }
        return self::decorate(self::q(self::select() . ' WHERE ' . implode(' AND ', $w) . " ORDER BY $order LIMIT 200", $p), $u);
    }
    /** Approved events for one month, for the calendar grid. */
    public static function month(array $u, int $year, int $month): array
    {
        $from = sprintf('%04d-%02d-01 00:00:00', $year, $month); $to = date('Y-m-d 00:00:00', strtotime("$from +1 month"));
        return self::decorate(self::q(self::select() . " WHERE e.status = 'Approved' AND e.starts_at >= ? AND e.starts_at < ? ORDER BY e.starts_at", [(int) $u['id'], (int) $u['id'], $from, $to]), $u);
    }
    public static function next(array $u, int $n = 4): array
    {
        return self::decorate(self::q(self::select() . " WHERE e.status = 'Approved' AND e.ends_at >= NOW() ORDER BY e.starts_at LIMIT " . (int) $n, [(int) $u['id'], (int) $u['id']]), $u);
    }

    /** @return string|null error when the venue is already taken in that window */
    private static function venueClash(string $venue, string $start, string $end, int $exceptId = 0): ?string
    {
        $r = self::q("SELECT title, starts_at, ends_at FROM events WHERE status = 'Approved' AND venue = ? AND id <> ? AND starts_at < ? AND ends_at > ? LIMIT 1", [$venue, $exceptId, $end, $start]);
        return $r ? '"' . $r[0]['title'] . '" already has ' . $venue . ' booked from ' . date('H:i', strtotime($r[0]['starts_at'])) . ' to ' . date('H:i', strtotime($r[0]['ends_at'])) . ' that day.' : null;
    }
    /** Validate raw input -> [clean row, error]. */
    private static function check(array $in, array $u, int $exceptId = 0): array
    {
        $title = trim((string) ($in['title'] ?? '')); $desc = trim((string) ($in['description'] ?? '')); $venue = trim((string) ($in['venue'] ?? ''));
        if (mb_strlen($title) < 3 || mb_strlen($title) > 120) { return [null, 'Title must be 3-120 characters.']; }
        if (mb_strlen($desc) > 2000) { return [null, 'Description is too long (max 2000).']; }
        $cat = (string) ($in['category'] ?? ''); if (!in_array($cat, self::CATEGORIES, true)) { return [null, 'Choose a category.']; }
        if (!in_array($venue, self::VENUES, true)) { return [null, 'Choose a venue from the list.']; }
        $s = (string) ($in['starts_at'] ?? ''); $e = (string) ($in['ends_at'] ?? '');
        if (!valid_datetime($s) || !valid_datetime($e)) { return [null, 'Enter valid start and end times.']; }
        $s = norm_datetime($s); $e = norm_datetime($e);
        if (strtotime($s) < time() + 30 * 60 && !$exceptId) { return [null, 'Events must start at least 30 minutes from now.']; }
        if (strtotime($s) > strtotime('+180 days')) { return [null, 'Events can be scheduled at most 180 days ahead.']; }
        if (strtotime($e) <= strtotime($s)) { return [null, 'The end time must be after the start time.']; }
        if (date('Y-m-d', strtotime($s)) !== date('Y-m-d', strtotime($e))) { return [null, 'An event must start and end on the same day.']; }
        if (strtotime($e) - strtotime($s) > self::MAX_HOURS * 3600) { return [null, 'Events can last at most ' . self::MAX_HOURS . ' hours.']; }
        if (date('H:i', strtotime($e)) > self::LATEST_END) { return [null, 'Campus events must finish by ' . self::LATEST_END . '.']; }
        $cap = ($in['capacity'] ?? '') === '' || $in['capacity'] === null ? null : (int) $in['capacity'];
        if ($cap !== null && ($cap < 1 || $cap > 5000)) { return [null, 'Capacity must be between 1 and 5000, or empty for unlimited.']; }
        $club = (int) ($in['club_id'] ?? 0) ?: null;
        if ($club && !ClubModel::canManage($club, $u)) { return [null, 'You are not an officer of that club.']; }
        return [['title' => $title, 'description' => $desc ?: null, 'category' => $cat, 'venue' => $venue, 'starts_at' => $s, 'ends_at' => $e, 'capacity' => $cap, 'club_id' => $club], null];
    }

    /** @return array{0:?int,1:?string} */
    public static function create(array $u, array $in): array
    {
        [$d, $err] = self::check($in, $u); if ($err) { return [null, $err]; }
        if ($clash = self::venueClash($d['venue'], $d['starts_at'], $d['ends_at'])) { return [null, $clash]; }
        $official = in_array($u['role'], Auth::STAFF, true);
        $code = (string) random_int(100000, 999999);
        db()->prepare('INSERT INTO events (title, description, category, club_id, organiser_id, venue, starts_at, ends_at, capacity, status, decided_by, checkin_code) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$d['title'], $d['description'], $d['category'], $d['club_id'], (int) $u['id'], $d['venue'], $d['starts_at'], $d['ends_at'], $d['capacity'], $official ? 'Approved' : 'Pending', $official ? (int) $u['id'] : null, $code]);
        $id = (int) db()->lastInsertId();
        if (!$official) { NotifyModel::pushMany(NotifyModel::admins(), 'event', 'Event awaiting approval: ' . $d['title'], 'events/' . $id); }
        Audit::log('create', 'event', $id, $d['title'] . ($official ? ' (auto-approved)' : ' (pending)'));
        return [$id, null];
    }
    public static function update(int $id, array $u, array $in): ?string
    {
        $e = self::find($id, $u); if (!$e || !$e['can_manage']) { return 'Event not found.'; }
        if (in_array($e['status'], ['Cancelled', 'Rejected'], true)) { return 'This event is ' . strtolower($e['status']) . ' and can no longer be edited.'; }
        if ($e['is_past']) { return 'Past events cannot be edited.'; }
        [$d, $err] = self::check($in + ['club_id' => $e['club_id']], $u, $id); if ($err) { return $err; }
        if ($d['capacity'] !== null && $d['capacity'] < (int) $e['going']) { return 'Capacity cannot be lower than the ' . $e['going'] . ' people already going.'; }
        if ($e['status'] === 'Approved' && ($clash = self::venueClash($d['venue'], $d['starts_at'], $d['ends_at'], $id))) { return $clash; }
        $changedSlot = $d['venue'] !== $e['venue'] || $d['starts_at'] !== date('Y-m-d H:i:s', strtotime($e['starts_at'])) || $d['ends_at'] !== date('Y-m-d H:i:s', strtotime($e['ends_at']));
        $official = in_array($u['role'], Auth::STAFF, true);
        $status = $e['status'] === 'Approved' && $changedSlot && !$official ? 'Pending' : $e['status'];
        db()->prepare('UPDATE events SET title=?, description=?, category=?, venue=?, starts_at=?, ends_at=?, capacity=?, status=? WHERE id = ?')
            ->execute([$d['title'], $d['description'], $d['category'], $d['venue'], $d['starts_at'], $d['ends_at'], $d['capacity'], $status, $id]);
        if ($changedSlot) { self::notifyAttendees($id, 'event', '"' . $d['title'] . '" was rescheduled: ' . date('D d M, H:i', strtotime($d['starts_at'])) . ' at ' . $d['venue'] . '.'); }
        if ($status === 'Pending' && $e['status'] === 'Approved') { NotifyModel::pushMany(NotifyModel::admins(), 'event', 'Rescheduled event needs re-approval: ' . $d['title'], 'events/' . $id); }
        Audit::log('update', 'event', $id, $d['title']); return null;
    }
    public static function decide(int $id, bool $approve, string $note, array $u): ?string
    {
        if ($u['role'] !== 'admin') { return 'Only administrators can approve events.'; }
        $e = self::find($id, $u); if (!$e) { return 'Event not found.'; }
        if ($e['status'] !== 'Pending') { return 'This event is already ' . strtolower($e['status']) . '.'; }
        $note = trim($note);
        if ($approve) {
            if ($e['is_past']) { return 'This event is already over.'; }
            if ($clash = self::venueClash($e['venue'], date('Y-m-d H:i:s', strtotime($e['starts_at'])), date('Y-m-d H:i:s', strtotime($e['ends_at'])), $id)) { return 'Cannot approve: ' . $clash; }
        } elseif (mb_strlen($note) < 5) { return 'Please give the organiser a reason (at least 5 characters).'; }
        db()->prepare('UPDATE events SET status = ?, decision_note = ?, decided_by = ? WHERE id = ?')->execute([$approve ? 'Approved' : 'Rejected', $note ?: null, (int) $u['id'], $id]);
        NotifyModel::push((int) $e['organiser_id'], 'event', $approve ? 'Your event "' . $e['title'] . '" was approved.' : 'Your event "' . $e['title'] . '" was not approved: ' . $note, 'events/' . $id);
        if ($approve && $e['club_id']) {
            $ids = array_column(self::q("SELECT user_id FROM club_members WHERE club_id = ? AND status = 'Active'", [(int) $e['club_id']]), 'user_id');
            NotifyModel::pushMany($ids, 'event', 'New club event: ' . $e['title'], 'events/' . $id, (int) $e['organiser_id']);
        }
        Audit::log($approve ? 'approve' : 'reject', 'event', $id, $e['title']); return null;
    }
    public static function cancel(int $id, string $reason, array $u): ?string
    {
        $e = self::find($id, $u); if (!$e || !$e['can_manage']) { return 'Event not found.'; }
        if (!in_array($e['status'], ['Approved', 'Pending'], true)) { return 'This event is already ' . strtolower($e['status']) . '.'; }
        if ($e['is_past']) { return 'This event is already over.'; }
        $reason = trim($reason); if (mb_strlen($reason) < 5) { return 'Please give a short reason (at least 5 characters).'; }
        db()->prepare("UPDATE events SET status = 'Cancelled', decision_note = ? WHERE id = ?")->execute([mb_substr($reason, 0, 200), $id]);
        self::notifyAttendees($id, 'event', '"' . $e['title'] . '" was cancelled: ' . $reason, (int) $u['id']);
        Audit::log('cancel', 'event', $id, $e['title']); return null;
    }
    private static function notifyAttendees(int $id, string $type, string $msg, int $except = 0): void
    {
        $ids = array_column(self::q('SELECT user_id FROM event_rsvps WHERE event_id = ?', [$id]), 'user_id');
        NotifyModel::pushMany($ids, $type, $msg, 'events/' . $id, $except);
    }

    // -------------------------------------------------------------- RSVP
    /** Overlapping "Going" event for this user, if any. */
    private static function clash(int $userId, array $e): ?array
    {
        return self::q("SELECT ev.id, ev.title FROM event_rsvps r JOIN events ev ON ev.id = r.event_id WHERE r.user_id = ? AND r.status = 'Going' AND ev.status = 'Approved' AND ev.id <> ? AND ev.starts_at < ? AND ev.ends_at > ? LIMIT 1",
            [$userId, (int) $e['id'], $e['ends_at'], $e['starts_at']])[0] ?? null;
    }
    /** @return array{0:?string,1:?string} [resulting status, error] */
    public static function rsvp(int $id, array $u, string $status): array
    {
        if (!in_array($status, ['Going', 'Interested'], true)) { return [null, 'Choose Going or Interested.']; }
        return self::tx(function () use ($id, $u, $status) {
            $e = self::q("SELECT * FROM events WHERE id = ? FOR UPDATE", [$id])[0] ?? null;
            if (!$e || $e['status'] !== 'Approved') { return [null, 'This event is not open for RSVPs.']; }
            if (strtotime($e['ends_at']) < time()) { return [null, 'This event is already over.']; }
            $cur = self::q('SELECT status, checked_in_at FROM event_rsvps WHERE event_id = ? AND user_id = ?', [$id, (int) $u['id']])[0] ?? null;
            if ($cur && $cur['checked_in_at']) { return [null, 'You have already checked in.']; }
            if ($cur && $cur['status'] === $status) { return [null, $status === 'Going' ? 'You are already on the list.' : 'You already marked interest.']; }
            if ($status === 'Interested') {
                if ($cur && in_array($cur['status'], ['Going', 'Waitlist'], true)) { return [null, 'You are already registered. Cancel your RSVP first to change it.']; }
                db()->prepare("INSERT INTO event_rsvps (event_id, user_id, status) VALUES (?,?,'Interested')")->execute([$id, (int) $u['id']]); return ['Interested', null];
            }
            if ($cur && $cur['status'] === 'Waitlist') { return [null, 'You are already on the waitlist.']; }
            if ($c = self::clash((int) $u['id'], $e)) { return [null, 'You are already going to "' . $c['title'] . '" at that time.']; }
            $going = (int) self::q("SELECT COUNT(*) AS n FROM event_rsvps WHERE event_id = ? AND status = 'Going'", [$id])[0]['n'];
            $new = $e['capacity'] !== null && $going >= (int) $e['capacity'] ? 'Waitlist' : 'Going';
            db()->prepare('INSERT INTO event_rsvps (event_id, user_id, status) VALUES (?,?,?) ON DUPLICATE KEY UPDATE status = VALUES(status), created_at = NOW()')->execute([$id, (int) $u['id'], $new]);
            return [$new, null];
        });
    }
    public static function cancelRsvp(int $id, array $u): ?string
    {
        return self::tx(function () use ($id, $u) {
            $e = self::q('SELECT * FROM events WHERE id = ? FOR UPDATE', [$id])[0] ?? null;
            $cur = self::q('SELECT status, checked_in_at FROM event_rsvps WHERE event_id = ? AND user_id = ?', [$id, (int) $u['id']])[0] ?? null;
            if (!$e || !$cur) { return 'You have no RSVP for this event.'; }
            if ($cur['checked_in_at']) { return 'You already checked in, so the RSVP cannot be cancelled.'; }
            db()->prepare('DELETE FROM event_rsvps WHERE event_id = ? AND user_id = ?')->execute([$id, (int) $u['id']]);
            if ($cur['status'] === 'Going' && $e['status'] === 'Approved' && strtotime($e['ends_at']) > time()) { self::promote($e); }
            return null;
        });
    }
    /** Move waitlisted people into freed seats, oldest first, skipping anyone whose schedule now clashes. */
    private static function promote(array $e): void
    {
        $free = $e['capacity'] === null ? 0 : (int) $e['capacity'] - (int) self::q("SELECT COUNT(*) AS n FROM event_rsvps WHERE event_id = ? AND status = 'Going'", [(int) $e['id']])[0]['n'];
        if ($free <= 0) { return; }
        foreach (self::q("SELECT user_id FROM event_rsvps WHERE event_id = ? AND status = 'Waitlist' ORDER BY created_at, user_id", [(int) $e['id']]) as $w) {
            if ($free <= 0) { break; }
            if (self::clash((int) $w['user_id'], $e)) { continue; }
            db()->prepare("UPDATE event_rsvps SET status = 'Going' WHERE event_id = ? AND user_id = ?")->execute([(int) $e['id'], (int) $w['user_id']]);
            NotifyModel::push((int) $w['user_id'], 'event', 'A seat opened up - you are now going to "' . $e['title'] . '".', 'events/' . $e['id']); $free--;
        }
    }

    // ----------------------------------------------------------- check-in
    private static function window(array $e): ?string
    {
        if ($e['status'] !== 'Approved') { return 'This event is not active.'; }
        if (time() < strtotime($e['starts_at']) - CHECKIN_EARLY_MIN * 60) { return 'Check-in opens ' . CHECKIN_EARLY_MIN . ' minutes before the start.'; }
        if (time() > strtotime($e['ends_at']) + 3600) { return 'Check-in has closed.'; }
        return null;
    }
    /** Attendee enters the code shown by the organiser. */
    public static function checkin(int $id, array $u, string $code): ?string
    {
        $e = self::q('SELECT * FROM events WHERE id = ?', [$id])[0] ?? null; if (!$e) { return 'Event not found.'; }
        if ($err = self::window($e)) { return $err; }
        $r = self::q('SELECT status, checked_in_at FROM event_rsvps WHERE event_id = ? AND user_id = ?', [$id, (int) $u['id']])[0] ?? null;
        if (!$r || $r['status'] !== 'Going') { return 'Only people who are going (not on the waitlist) can check in.'; }
        if ($r['checked_in_at']) { return 'You are already checked in.'; }
        $_SESSION['ci_fail'][$id] = $_SESSION['ci_fail'][$id] ?? 0;
        if ($_SESSION['ci_fail'][$id] >= 5) { return 'Too many wrong codes. Ask the organiser to check you in.'; }
        if (!hash_equals((string) $e['checkin_code'], trim($code))) { $_SESSION['ci_fail'][$id]++; return 'That code is not correct.'; }
        db()->prepare('UPDATE event_rsvps SET checked_in_at = NOW() WHERE event_id = ? AND user_id = ?')->execute([$id, (int) $u['id']]); return null;
    }
    /** Organiser checks a person in (or undoes it). */
    public static function checkinMember(int $id, int $userId, bool $in, array $u): ?string
    {
        $e = self::find($id, $u); if (!$e || !$e['can_manage']) { return 'Event not found.'; }
        if ($err = self::window($e)) { return $err; }
        $r = self::q('SELECT status FROM event_rsvps WHERE event_id = ? AND user_id = ?', [$id, $userId])[0] ?? null;
        if (!$r || $r['status'] !== 'Going') { return 'That person is not on the going list.'; }
        db()->prepare('UPDATE event_rsvps SET checked_in_at = ' . ($in ? 'NOW()' : 'NULL') . ' WHERE event_id = ? AND user_id = ?')->execute([$id, $userId]); return null;
    }
    public static function attendees(int $id, array $u): ?array
    {
        $e = self::find($id, $u); if (!$e || !$e['can_manage']) { return null; }
        return self::q("SELECT r.user_id, r.status, r.checked_in_at, r.created_at, u.full_name, u.color, p.code AS programme, u.batch FROM event_rsvps r JOIN users u ON u.id = r.user_id LEFT JOIN programmes p ON p.id = u.programme_id
            WHERE r.event_id = ? ORDER BY FIELD(r.status,'Going','Waitlist','Interested'), u.full_name", [$id]);
    }
    public static function code(int $id, array $u): ?string
    {
        $e = self::find($id, $u); if (!$e || !$e['can_manage']) { return null; }
        return (string) self::q('SELECT checkin_code FROM events WHERE id = ?', [$id])[0]['checkin_code'];
    }
}
