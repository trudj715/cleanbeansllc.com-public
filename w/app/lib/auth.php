<?php
/**
 * Admin accounts, permissions, login throttling and two-factor sign-in.
 *
 * - hostmaster@trudj.net is permanent: cannot be deleted, disabled or lose permissions.
 * - Permissions are a bitmask checked on the server for every admin action.
 * - 5 failed sign-ins for an email or IP within 15 minutes locks sign-in for 15 minutes.
 */
declare(strict_types=1);

const PERM_PAGES    = 1;    // edit pages and sections
const PERM_MEDIA    = 2;    // upload / edit photos
const PERM_LISTINGS = 4;    // unused (kept so permission bits match the kmtow platform)
const PERM_INBOX    = 8;    // inquiries and service requests
const PERM_USERS    = 16;   // manage admin users
const PERM_SETTINGS = 32;   // company info, contact details, social links
const PERM_ALL      = 63;

const PERM_LABELS = [
    PERM_PAGES    => 'Pages',
    PERM_MEDIA    => 'Photos',
    PERM_INBOX    => 'Inbox',
    PERM_USERS    => 'Users',
    PERM_SETTINGS => 'Settings',
];

const LOGIN_MAX_FAILS = 5;
const LOGIN_WINDOW    = 900; // seconds

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $id = (int)($_SESSION['uid'] ?? 0);
        $user = $id ? db_one('SELECT * FROM users WHERE id=? AND is_enabled=1', [$id]) : null;
        // Session is tied to the password hash: changing the password signs out other sessions.
        if ($user && !hash_equals((string)($_SESSION['pwv'] ?? ''), substr(hash('sha256', $user['password_hash']), 0, 16))) {
            $user = null;
        }
    }
    return $user;
}

function is_hostmaster(?array $u = null): bool
{
    $u ??= current_user();
    return $u !== null && (int)$u['is_protected'] === 1;
}

function can(int $perm): bool
{
    $u = current_user();
    if (!$u) return false;
    if (is_hostmaster($u)) return true;
    return ((int)$u['permissions'] & $perm) === $perm;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('admin/login');
    }
    return $u;
}

function require_perm(int $perm): array
{
    $u = require_login();
    if (!can($perm)) {
        http_response_code(403);
        view('admin/layout', ['title' => 'Not allowed', 'content' => function () {
            echo '<div class="panel"><h1>Not allowed</h1><p>Your account does not have access to this section. Ask the site owner to change your permissions.</p></div>';
        }]);
        exit;
    }
    return $u;
}

/* ---------- Throttling ---------- */

function login_locked(string $email): bool
{
    $since = date('Y-m-d H:i:s', time() - LOGIN_WINDOW);
    $n = (int)db_val('SELECT COUNT(*) FROM login_attempts WHERE success=0 AND attempted_at>=? AND (email=? OR ip=?)',
        [$since, strtolower($email), client_ip()]);
    return $n >= LOGIN_MAX_FAILS;
}

function login_record(string $email, bool $ok): void
{
    db_insert('login_attempts', ['email' => strtolower(substr($email, 0, 190)), 'ip' => client_ip(), 'success' => $ok ? 1 : 0, 'attempted_at' => now()]);
    // Keep the table small.
    db_exec('DELETE FROM login_attempts WHERE attempted_at < ?', [date('Y-m-d H:i:s', time() - 86400 * 30)]);
}

/* ---------- Sign-in ---------- */

/**
 * Step 1: email + password.
 * Returns 'ok', 'mfa' (second step required), 'locked' or 'bad'.
 */
function login_password(string $email, string $password): string
{
    $email = strtolower(trim($email));
    if (login_locked($email)) {
        return 'locked';
    }
    $u = db_one('SELECT * FROM users WHERE email=?', [$email]);
    // Always run a hash check so response time does not reveal whether the email exists.
    $hash = $u['password_hash'] ?? '$2y$10$uBxUI/C3tCJvA4Z8PXY0Ru//gVmsrVs0VpL5.fzaTvYL/koAACcSC';
    $valid = password_verify($password, $hash) && $u && (int)$u['is_enabled'] === 1;
    if (!$valid) {
        login_record($email, false);
        return 'bad';
    }
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        db_update('users', (int)$u['id'], ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        $u = db_one('SELECT * FROM users WHERE id=?', [$u['id']]);
    }
    if ((int)$u['totp_enabled'] === 1) {
        $_SESSION['mfa_uid'] = (int)$u['id'];
        $_SESSION['mfa_at']  = time();
        return 'mfa';
    }
    login_complete($u);
    return 'ok';
}

/** Step 2: 6-digit authenticator code or a one-time recovery code. */
function login_mfa(string $code): string
{
    $uid = (int)($_SESSION['mfa_uid'] ?? 0);
    if (!$uid || time() - (int)($_SESSION['mfa_at'] ?? 0) > 600) {
        unset($_SESSION['mfa_uid'], $_SESSION['mfa_at']);
        return 'expired';
    }
    $u = db_one('SELECT * FROM users WHERE id=? AND is_enabled=1', [$uid]);
    if (!$u || login_locked($u['email'])) {
        return 'locked';
    }
    $ok = false;
    // A 6-digit authenticator code (spaces allowed); anything else is treated as a recovery code.
    $digits = preg_replace('/\s+/', '', $code);
    if (preg_match('/^\d{6}$/', $digits)) {
        $step = totp_verify((string)$u['totp_secret'], $digits, (int)$u['totp_last_step']);
        if ($step > 0) {
            db_update('users', $uid, ['totp_last_step' => $step]); // block reuse of the same code
            $ok = true;
        }
    } else {
        $ok = recovery_code_use($uid, $code);
    }
    if (!$ok) {
        login_record($u['email'], false);
        return 'bad';
    }
    login_complete($u);
    return 'ok';
}

function login_complete(array $u): void
{
    session_regenerate_id(true);
    unset($_SESSION['mfa_uid'], $_SESSION['mfa_at']);
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['pwv'] = substr(hash('sha256', $u['password_hash']), 0, 16);
    login_record($u['email'], true);
    db_update('users', (int)$u['id'], ['last_login_at' => now()]);
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Re-sync the session after the signed-in user changes their own password. */
function session_refresh_password(int $uid): void
{
    $h = (string)db_val('SELECT password_hash FROM users WHERE id=?', [$uid]);
    $_SESSION['pwv'] = substr(hash('sha256', $h), 0, 16);
}

function password_problem(string $pw): ?string
{
    if (mb_strlen($pw) < 12) return 'Use at least 12 characters.';
    if (mb_strlen($pw) > 200) return 'That password is too long.';
    return null;
}

/* ---------- Recovery codes (backup for a lost phone) ---------- */

function recovery_codes_generate(int $uid): array
{
    db_exec('DELETE FROM recovery_codes WHERE user_id=?', [$uid]);
    $codes = [];
    for ($i = 0; $i < 8; $i++) {
        $c = strtoupper(bin2hex(random_bytes(2)) . '-' . bin2hex(random_bytes(2)) . '-' . bin2hex(random_bytes(2)));
        $codes[] = $c;
        db_insert('recovery_codes', ['user_id' => $uid, 'code_hash' => password_hash($c, PASSWORD_DEFAULT), 'created_at' => now()]);
    }
    return $codes;
}

function recovery_code_use(int $uid, string $code): bool
{
    $code = strtoupper(trim($code));
    if ($code === '') return false;
    foreach (db_all('SELECT id, code_hash FROM recovery_codes WHERE user_id=? AND used_at IS NULL', [$uid]) as $r) {
        if (password_verify($code, $r['code_hash'])) {
            db_exec('UPDATE recovery_codes SET used_at=? WHERE id=?', [now(), $r['id']]);
            return true;
        }
    }
    return false;
}
