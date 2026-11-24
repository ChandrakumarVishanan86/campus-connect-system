<?php
/** Shared helpers: escaping, URLs, JSON, CSRF, formatting, avatars, autoloader. */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db_connect.php';

spl_autoload_register(static function (string $class): void {
    foreach ([__DIR__ . '/models/', __DIR__ . '/'] as $dir) {
        $file = $dir . $class . '.php';
        if (is_file($file)) { require_once $file; return; }
    }
});

function e(?string $v): string { return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $path = ''): string { return APP_BASE . '/' . ltrim($path, '/'); }
function redirect(string $path): never { header('Location: ' . url($path)); exit; }

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

function json_input(): array
{
    $data = json_decode(file_get_contents('php://input') ?: '[]', true);
    return is_array($data) ? $data : [];
}

// ------------------------------------------------------------------ CSRF
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'; }
function csrf_verify(?array $json = null): bool
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? ($json['csrf_token'] ?? '');
    return is_string($sent) && $sent !== '' && hash_equals($_SESSION['csrf'] ?? '', $sent);
}
function require_csrf(?array $json = null): void
{
    if (!csrf_verify($json)) {
        json_response(['success' => false, 'message' => 'Security token expired. Please reload the page.'], 403);
    }
}

// ------------------------------------------------------------- flash msgs
function flash(string $type, string $msg): void { $_SESSION['flash'][] = ['type' => $type, 'message' => $msg]; }
function pull_flash(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

// ------------------------------------------------------------ formatting
function fdate(?string $d, string $fmt = 'd M Y'): string { return $d ? date($fmt, strtotime($d)) : '–'; }
function fdatetime(?string $d): string { return $d ? date('d M Y, H:i', strtotime($d)) : '–'; }
function valid_date(string $d): bool
{
    $x = DateTime::createFromFormat('Y-m-d', $d);
    return $x !== false && $x->format('Y-m-d') === $d;
}
function valid_datetime(string $d): bool
{
    $x = DateTime::createFromFormat('Y-m-d H:i', $d) ?: DateTime::createFromFormat('Y-m-d\TH:i', $d);
    return $x !== false;
}
function norm_datetime(string $d): string { $x = DateTime::createFromFormat('Y-m-d H:i', $d) ?: DateTime::createFromFormat('Y-m-d\TH:i', $d); return $x->format('Y-m-d H:i:00'); }

/** "5 min ago", "yesterday", "12 Nov". */
function time_ago(?string $d): string
{
    if (!$d) { return ''; }
    $diff = time() - strtotime($d);
    if ($diff < 60) { return 'just now'; }
    if ($diff < 3600) { return floor($diff / 60) . ' min ago'; }
    if ($diff < 86400) { return floor($diff / 3600) . ' h ago'; }
    if ($diff < 172800) { return 'yesterday'; }
    if ($diff < 604800) { return floor($diff / 86400) . ' days ago'; }
    return date('d M', strtotime($d));
}

/** Round initials avatar; $color picks one of 8 palette classes (users.color). */
function avatar(string $name, int $color = 0, string $size = ''): string
{
    $parts = preg_split('/\s+/', trim(preg_replace('/^(Dr|Mr|Mrs|Ms|Prof)\.?\s+/i', '', $name)));
    $ini = mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1) . (isset($parts[1]) ? mb_substr($parts[1], 0, 1) : ''));
    return '<span class="av av-' . ($color % 8) . ($size ? " av-$size" : '') . '">' . e($ini) . '</span>';
}

/** Colour class for any status / type chip (see .badge-soft-* in style.css). */
function status_class(string $s): string
{
    return match ($s) {
        'Approved', 'Active', 'Going', 'Resolved', 'Published', 'Open' => 'success',
        'Pending', 'Waitlist', 'Claim Pending', 'Interested'         => 'warning',
        'Rejected', 'Cancelled', 'Hidden', 'Actioned'                => 'danger',
        'Announcement'                                               => 'plum',
        'Question'                                                   => 'info',
        'Achievement'                                                => 'gold',
        'Opportunity'                                                => 'teal',
        'Lost'                                                       => 'danger',
        'Found'                                                      => 'success',
        default                                                      => 'secondary',
    };
}
function status_badge(string $s): string { return '<span class="badge badge-soft-' . status_class($s) . '">' . e($s) . '</span>'; }
