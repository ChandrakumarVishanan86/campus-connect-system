<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
$user = Auth::guard(Auth::ALL, json: true);
$ok = static fn(string $m, array $x = []) => json_response(['success' => true, 'message' => $m] + $x);
$bad = static fn(string $m, int $c = 422) => json_response(['success' => false, 'message' => $m], $c);
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $a = $_GET['action'] ?? '';
        if ($a === 'list') { json_response(['success' => true, 'data' => EventModel::list($user, $_GET)]); }
        if ($a === 'month') {
            $y = max(2020, min(2100, (int) ($_GET['year'] ?? date('Y')))); $m = max(1, min(12, (int) ($_GET['month'] ?? date('n'))));
            json_response(['success' => true, 'year' => $y, 'month' => $m, 'data' => EventModel::month($user, $y, $m)]);
        }
        if ($a === 'get') {
            $e = EventModel::find((int) ($_GET['id'] ?? 0), $user);
            if (!$e || !EventModel::canView($e, $user)) { $bad('Event not found.', 404); }
            json_response(['success' => true, 'data' => $e]);
        }
        if ($a === 'attendees') { $r = EventModel::attendees((int) ($_GET['id'] ?? 0), $user); $r === null ? $bad('Not allowed.', 403) : json_response(['success' => true, 'data' => $r]); }
        if ($a === 'code') { $c = EventModel::code((int) ($_GET['id'] ?? 0), $user); $c === null ? $bad('Not allowed.', 403) : json_response(['success' => true, 'code' => $c]); }
        $bad('Unknown action.', 400);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $bad('Method not allowed.', 405); }
    if (!$_POST) { $j = json_input(); if ($j) { $_POST = $j; } }
    require_csrf();
    $a = (string) ($_POST['action'] ?? ''); $id = (int) ($_POST['id'] ?? 0);
    switch ($a) {
        case 'save':
            if ($id) { ($e = EventModel::update($id, $user, $_POST)) ? $bad($e) : $ok('Event updated.', ['id' => $id]); }
            [$nid, $e] = EventModel::create($user, $_POST);
            $e ? $bad($e) : $ok(in_array($user['role'], Auth::STAFF, true) ? 'Event published.' : 'Event submitted - an admin will review it shortly.', ['id' => $nid]);
        case 'rsvp':
            [$s, $e] = EventModel::rsvp($id, $user, (string) ($_POST['status'] ?? '')); $e ? $bad($e, 409) : $ok($s === 'Waitlist' ? 'The event is full - you are on the waitlist.' : ($s === 'Going' ? 'You are going!' : 'Marked as interested.'), ['status' => $s]);
        case 'cancel_rsvp':
            ($e = EventModel::cancelRsvp($id, $user)) ? $bad($e, 409) : $ok('RSVP cancelled.');
        case 'checkin':
            ($e = EventModel::checkin($id, $user, (string) ($_POST['code'] ?? ''))) ? $bad($e, 409) : $ok('Checked in. Enjoy the event!');
        case 'checkin_member':
            ($e = EventModel::checkinMember($id, (int) ($_POST['user_id'] ?? 0), !empty($_POST['in']), $user)) ? $bad($e, 409) : $ok('Updated.');
        case 'decide':
            ($e = EventModel::decide($id, !empty($_POST['approve']), (string) ($_POST['note'] ?? ''), $user)) ? $bad($e, 409) : $ok(!empty($_POST['approve']) ? 'Event approved.' : 'Event rejected.');
        case 'cancel':
            ($e = EventModel::cancel($id, (string) ($_POST['reason'] ?? ''), $user)) ? $bad($e, 409) : $ok('Event cancelled and attendees notified.');
        default: $bad('Unknown action.', 400);
    }
} catch (Throwable $e) {
    error_log('[events api] ' . $e->getMessage());
    json_response(['success' => false, 'message' => APP_DEBUG ? $e->getMessage() : 'Server error. Please try again.'], 500);
}
