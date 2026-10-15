<?php
/**
 * General helpers: escaping, URLs, settings, sessions, CSRF, flash messages, views.
 */
declare(strict_types=1);

function e(mixed $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL inside the app: url('about') -> /w/about */
function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return BASE_PATH . '/' . $path;
}

function asset(string $path): string
{
    $file = APP_ROOT . '/' . ltrim($path, '/');
    $v = is_file($file) ? substr(md5((string)filemtime($file)), 0, 8) : APP_VERSION;
    return url($path) . '?v=' . $v;
}

/** Absolute URL for canonical tags, sitemap and Open Graph. */
function abs_url(string $path = ''): string
{
    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : ((is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_PATH);
    return $base . '/' . ltrim($path, '/');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function redirect(string $to, int $code = 302): never
{
    header('Location: ' . (preg_match('#^https?://#', $to) ? $to : url($to)), true, $code);
    exit;
}

function slugify(string $s): string
{
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s), '-'));
    return $s !== '' ? substr($s, 0, 80) : 'item';
}

function phone_digits(string $p): string
{
    return preg_replace('/\D+/', '', $p);
}

function money(?int $cents): string
{
    return $cents === null ? '' : '$' . number_format($cents / 100, $cents % 100 ? 2 : 0);
}

/* ---------- Settings (key/value table, cached per request) ---------- */

function settings_all(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db_all('SELECT skey, svalue FROM settings') as $r) {
            $cache[$r['skey']] = $r['svalue'];
        }
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $all = settings_all();
    return array_key_exists($key, $all) ? (string)$all[$key] : $default;
}

function setting_save(string $key, string $value): void
{
    if (db_val('SELECT COUNT(*) FROM settings WHERE skey=?', [$key])) {
        db_exec('UPDATE settings SET svalue=? WHERE skey=?', [$value, $key]);
    } else {
        db_exec('INSERT INTO settings (skey, svalue) VALUES (?,?)', [$key, $value]);
    }
}

/* ---------- Session, CSRF, flash ---------- */

function start_session(): void
{
    if (session_status() !== PHP_SESSION_NONE || PHP_SAPI === 'cli') {
        return;
    }
    session_name('cb_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_PATH === '' ? '/' : BASE_PATH . '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Call at the top of every POST handler. */
function csrf_check(): void
{
    $sent = (string)($_POST['_csrf'] ?? '');
    if ($sent === '' || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('This form expired. Go back, refresh the page and try again.');
    }
}

function flash(string $msg, string $type = 'ok'): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function post(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/* ---------- Views ---------- */

/** Render a template from app/views with variables. */
function view(string $name, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    require APP_DIR . '/views/' . $name . '.php';
}

function not_found(): never
{
    http_response_code(404);
    view('public/layout', [
        'title'   => 'Page not found',
        'meta'    => ['robots' => 'noindex'],
        'content' => fn() => view('public/notfound'),
    ]);
    exit;
}
