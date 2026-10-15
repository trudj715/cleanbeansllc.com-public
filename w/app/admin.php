<?php
/**
 * Admin area routes and actions. Every action checks permissions on the server,
 * and every form POST is CSRF-checked.
 */
declare(strict_types=1);

function admin_route(string $path, string $method): void
{
    $parts = $path === '' ? [] : explode('/', $path);
    $section = $parts[0] ?? '';
    $id = isset($parts[1]) && ctype_digit($parts[1]) ? (int)$parts[1] : null;
    $sub = $parts[$id !== null ? 2 : 1] ?? '';
    if ($method === 'POST' && !in_array($section, ['login', 'mfa'], true)) {
        csrf_check();
    }

    switch ($section) {
        case 'login':      admin_login($method); return;
        case 'mfa':        admin_mfa($method); return;
        case 'logout':     if ($method === 'POST') { logout(); } redirect('admin/login');
        case '':           admin_dashboard(); return;
        case 'pages':      admin_pages($id, $sub, $method); return;
        case 'media':      admin_media($id, $sub, $method); return;
        case 'inbox':      admin_inbox($id, $method); return;
        case 'users':      admin_users($id, $sub, $method); return;
        case 'settings':   admin_settings($method); return;
        case 'account':    admin_account($sub, $method); return;
    }
    http_response_code(404);
    admin_render('Not found', fn() => print('<div class="panel"><h1>Not found</h1><p><a href="' . e(url('admin')) . '">Back to the dashboard</a></p></div>'));
}

function admin_render(string $title, callable $content): void
{
    view('admin/layout', ['title' => $title, 'content' => $content]);
}

/* ======================= Sign in ======================= */

function admin_login(string $method): void
{
    if (current_user()) redirect('admin');
    $error = '';
    if ($method === 'POST') {
        csrf_check();
        $r = login_password(post('email'), (string)($_POST['password'] ?? ''));
        if ($r === 'ok') admin_after_login();
        if ($r === 'mfa') redirect('admin/mfa');
        $error = $r === 'locked'
            ? 'Too many failed sign-in attempts. Wait 15 minutes and try again.'
            : 'That email and password did not match.';
    }
    view('admin/login', ['error' => $error, 'step' => 'password']);
}

function admin_mfa(string $method): void
{
    if (empty($_SESSION['mfa_uid'])) redirect('admin/login');
    $error = '';
    if ($method === 'POST') {
        csrf_check();
        $r = login_mfa(post('code'));
        if ($r === 'ok') admin_after_login();
        if ($r === 'expired') { flash('Sign-in timed out. Start again.', 'error'); redirect('admin/login'); }
        $error = $r === 'locked' ? 'Too many failed attempts. Wait 15 minutes.' : 'That code did not work. Use the current 6-digit code, or a recovery code.';
    }
    view('admin/login', ['error' => $error, 'step' => 'mfa']);
}

function admin_after_login(): never
{
    $to = (string)($_SESSION['after_login'] ?? '');
    unset($_SESSION['after_login']);
    if ($to !== '' && str_starts_with($to, BASE_PATH . '/admin') && !str_contains($to, '//')) {
        header('Location: ' . $to, true, 303);
        exit;
    }
    redirect('admin');
}

/* ======================= Dashboard ======================= */

function admin_dashboard(): void
{
    $u = require_login();
    $stats = [
        'new_inquiries' => (int)db_val("SELECT COUNT(*) FROM inquiries WHERE status='new'"),
        'pages'         => (int)db_val('SELECT COUNT(*) FROM pages WHERE is_published=1'),
        'photos'        => (int)db_val('SELECT COUNT(*) FROM media'),
    ];
    $recent = can(PERM_INBOX) ? db_all('SELECT i.* FROM inquiries i ORDER BY i.id DESC LIMIT 6') : [];
    admin_render('Dashboard', fn() => view('admin/dashboard', ['u' => $u, 'stats' => $stats, 'recent' => $recent]));
}

/* ======================= Pages & sections ======================= */

function admin_pages(?int $id, string $sub, string $method): void
{
    require_perm(PERM_PAGES);

    if ($id === null && $sub === 'new') {
        if ($method === 'POST') {
            $title = post('title');
            $slug = slugify(post('slug') ?: $title);
            if ($title === '') { flash('Give the page a title.', 'error'); redirect('admin/pages/new'); }
            if (db_val('SELECT COUNT(*) FROM pages WHERE slug=?', [$slug]) || in_array($slug, ['admin', 'request', 'uploads', 'assets'], true)) {
                flash('That web address is already used. Choose another.', 'error'); redirect('admin/pages/new');
            }
            $newId = db_insert('pages', ['slug' => $slug, 'title' => $title, 'nav_label' => $title, 'show_in_nav' => 1,
                'nav_order' => (int)db_val('SELECT COALESCE(MAX(nav_order),0)+10 FROM pages'), 'is_published' => 0,
                'hero_heading' => $title, 'hero_text' => '', 'meta_title' => '', 'meta_description' => '', 'created_at' => now(), 'updated_at' => now()]);
            flash('Page created as a draft. Add sections, then publish it.');
            redirect('admin/pages/' . $newId);
        }
        admin_render('New page', fn() => view('admin/page_new'));
        return;
    }

    if ($id === null) {
        if ($method === 'POST' && post('action') === 'reorder') {
            foreach ((array)($_POST['order'] ?? []) as $pid => $ord) {
                db_exec('UPDATE pages SET nav_order=? WHERE id=?', [(int)$ord, (int)$pid]);
            }
            flash('Menu order saved.');
            redirect('admin/pages');
        }
        admin_render('Pages', fn() => view('admin/pages', ['pages' => db_all('SELECT p.*, (SELECT COUNT(*) FROM sections s WHERE s.page_id=p.id) AS n FROM pages p ORDER BY nav_order, id')]));
        return;
    }

    $page = db_one('SELECT * FROM pages WHERE id=?', [$id]);
    if (!$page) redirect('admin/pages');

    if ($method === 'POST') {
        $action = post('action');
        if ($action === 'save_page') {
            $slug = $page['slug'] === 'home' ? 'home' : slugify(post('slug') ?: $page['slug']);
            if ($slug !== $page['slug'] && db_val('SELECT COUNT(*) FROM pages WHERE slug=? AND id<>?', [$slug, $id])) {
                flash('That web address is already used by another page.', 'error');
                redirect('admin/pages/' . $id);
            }
            db_update('pages', $id, [
                'title' => post('title') ?: $page['title'], 'slug' => $slug, 'nav_label' => post('nav_label'),
                'show_in_nav' => isset($_POST['show_in_nav']) ? 1 : 0,
                'is_published' => $page['slug'] === 'home' ? 1 : (isset($_POST['is_published']) ? 1 : 0),
                'hero_media_id' => (int)post('hero_media_id') ?: null, 'hero_heading' => post('hero_heading'),
                'hero_text' => post('hero_text'), 'meta_title' => post('meta_title'), 'meta_description' => mb_substr(post('meta_description'), 0, 300),
                'updated_at' => now(),
            ]);
            flash('Page saved.');
        } elseif ($action === 'add_section') {
            $type = post('type');
            if (isset(SECTION_TYPES[$type])) {
                $sid = db_insert('sections', ['page_id' => $id, 'type' => $type, 'heading' => '', 'body_html' => '', 'media_id' => null,
                    'data' => json_encode(section_default_data($type)), 'sort_order' => (int)db_val('SELECT COALESCE(MAX(sort_order),0)+10 FROM sections WHERE page_id=?', [$id]),
                    'is_enabled' => 1, 'created_at' => now(), 'updated_at' => now()]);
                flash(SECTION_TYPES[$type] . ' section added. Fill it in below.');
                redirect('admin/pages/' . $id . '#s' . $sid);
            }
        } elseif ($action === 'save_section') {
            $sid = (int)post('section_id');
            $s = db_one('SELECT * FROM sections WHERE id=? AND page_id=?', [$sid, $id]);
            if ($s) {
                db_update('sections', $sid, [
                    'heading' => post('heading'), 'body_html' => sanitize_html((string)($_POST['body_html'] ?? '')),
                    'media_id' => (int)post('media_id') ?: null, 'data' => json_encode(section_data_from_post($s['type'])),
                    'is_enabled' => isset($_POST['is_enabled']) ? 1 : 0, 'updated_at' => now(),
                ]);
                db_update('pages', $id, ['updated_at' => now()]);
                flash('Section saved.');
                redirect('admin/pages/' . $id . '#s' . $sid);
            }
        } elseif ($action === 'move_section') {
            $sid = (int)post('section_id');
            $dir = post('dir') === 'up' ? -1 : 1;
            $rows = db_all('SELECT id FROM sections WHERE page_id=? ORDER BY sort_order, id', [$id]);
            $ids = array_map('intval', array_column($rows, 'id'));
            $pos = array_search($sid, $ids, true);
            if ($pos !== false && isset($ids[$pos + $dir])) {
                [$ids[$pos], $ids[$pos + $dir]] = [$ids[$pos + $dir], $ids[$pos]];
                foreach ($ids as $k => $x) db_exec('UPDATE sections SET sort_order=? WHERE id=?', [($k + 1) * 10, $x]);
            }
            redirect('admin/pages/' . $id . '#s' . $sid);
        } elseif ($action === 'delete_section') {
            db_exec('DELETE FROM sections WHERE id=? AND page_id=?', [(int)post('section_id'), $id]);
            flash('Section removed.');
        } elseif ($action === 'delete_page' && $page['slug'] !== 'home') {
            db_exec('DELETE FROM pages WHERE id=?', [$id]);
            flash('Page deleted.');
            redirect('admin/pages');
        }
        redirect('admin/pages/' . $id);
    }

    admin_render('Edit: ' . $page['title'], fn() => view('admin/page_edit', [
        'page' => $page, 'sections' => page_sections($id, true), 'hero' => media_get($page['hero_media_id'] ? (int)$page['hero_media_id'] : null),
    ]));
}

function section_default_data(string $type): array
{
    return match ($type) {
        'image_text' => ['align' => 'left'],
        'gallery'    => ['media_ids' => []],
        'map'        => ['address' => setting('address_line1') . ', ' . setting('address_line2'), 'lat' => '', 'lng' => '', 'radius_miles' => 150, 'zoom' => 7, 'areas' => []],
        'faq'        => ['items' => [['q' => '', 'a' => '']]],
        default      => [],
    };
}

function section_data_from_post(string $type): array
{
    $d = ['band' => isset($_POST['band']) ? 1 : 0];
    switch ($type) {
        case 'image_text':
            $d['align'] = post('align') === 'right' ? 'right' : 'left';
            break;
        case 'gallery':
            $d['media_ids'] = array_values(array_filter(array_map('intval', explode(',', post('media_ids')))));
            break;
        case 'map':
            $d['address'] = post('address');
            $d['lat'] = is_numeric(post('lat')) ? (float)post('lat') : '';
            $d['lng'] = is_numeric(post('lng')) ? (float)post('lng') : '';
            $d['radius_miles'] = max(0, min(1000, (float)post('radius_miles')));
            $d['zoom'] = max(3, min(16, (int)post('zoom', '7')));
            $d['areas'] = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string)($_POST['areas'] ?? '')))));
            break;
        case 'faq':
            $d['items'] = [];
            foreach ((array)($_POST['faq_q'] ?? []) as $k => $q) {
                $q = trim((string)$q);
                $a = trim((string)($_POST['faq_a'][$k] ?? ''));
                if ($q !== '') $d['items'][] = ['q' => mb_substr($q, 0, 300), 'a' => sanitize_html($a)];
            }
            break;
    }
    return $d;
}

/* ======================= Photo library ======================= */

function admin_media(?int $id, string $sub, string $method): void
{
    $u = require_perm(PERM_MEDIA);

    // JSON list for the photo picker used across the admin.
    if ($sub === 'picker' || ($id === null && ($_GET['format'] ?? '') === 'json')) {
        header('Content-Type: application/json');
        $rows = db_all('SELECT id, file, file_sm, alt_text, width, height FROM media ORDER BY id DESC LIMIT 500');
        echo json_encode(array_map(fn($m) => ['id' => (int)$m['id'], 'thumb' => media_url($m, true), 'url' => media_url($m), 'alt' => $m['alt_text']], $rows));
        return;
    }

    if ($id === null && $method === 'POST') {
        $files = admin_files_array($_FILES['photos'] ?? null);
        $ok = 0; $errs = [];
        foreach ($files as $f) {
            try { media_store_upload($f, '', post('category') ?: 'general', (int)$u['id']); $ok++; }
            catch (InvalidArgumentException $e) { $errs[] = $f['name'] . ': ' . $e->getMessage(); }
        }
        if ($ok) flash($ok . ' photo' . ($ok > 1 ? 's' : '') . ' uploaded. Add a short description to each so search engines and screen readers know what they show.');
        foreach ($errs as $er) flash($er, 'error');
        if (!empty($_POST['ajax'])) { header('Content-Type: application/json'); echo json_encode(['ok' => $ok, 'errors' => $errs]); return; }
        redirect('admin/media');
    }

    if ($id !== null) {
        $m = media_get($id);
        if (!$m) redirect('admin/media');
        if ($method === 'POST') {
            if (post('action') === 'delete') {
                if (media_delete($id)) flash('Photo deleted.');
                else flash('This photo is still used on a page. Remove it there first.', 'error');
                redirect('admin/media');
            }
            db_update('media', $id, ['alt_text' => mb_substr(post('alt_text'), 0, 255), 'caption' => mb_substr(post('caption'), 0, 255), 'category' => post('category') ?: 'general']);
            flash('Photo details saved.');
            redirect('admin/media/' . $id);
        }
        admin_render('Photo', fn() => view('admin/media_edit', ['m' => $m, 'uses' => media_usage_count($id)]));
        return;
    }

    $cat = (string)($_GET['category'] ?? '');
    $rows = $cat !== '' ? db_all('SELECT * FROM media WHERE category=? ORDER BY id DESC', [$cat]) : db_all('SELECT * FROM media ORDER BY id DESC');
    admin_render('Photos', fn() => view('admin/media', ['rows' => $rows, 'cat' => $cat]));
}

/** Normalise a multi-file upload field into a list of single-file arrays. */
function admin_files_array(?array $field): array
{
    if (!$field || !isset($field['name'])) return [];
    if (!is_array($field['name'])) return [$field];
    $out = [];
    foreach ($field['name'] as $k => $n) {
        if ($field['error'][$k] === UPLOAD_ERR_NO_FILE) continue;
        $out[] = ['name' => $n, 'type' => $field['type'][$k], 'tmp_name' => $field['tmp_name'][$k], 'error' => $field['error'][$k], 'size' => $field['size'][$k]];
    }
    return $out;
}

/* ======================= Inbox ======================= */

function admin_inbox(?int $id, string $method): void
{
    $me = require_perm(PERM_INBOX);
    if ($id !== null) {
        $q = db_one('SELECT i.* FROM inquiries i WHERE i.id=?', [$id]);
        if (!$q) redirect('admin/inbox');
        if ($method === 'POST') {
            $action = post('action');
            $by = ['user_id' => (int)$me['id'], 'name' => $me['name'] ?: $me['email']];
            if ($action === 'delete') {
                db_exec('DELETE FROM messages WHERE inquiry_id=?', [$id]);
                db_exec('DELETE FROM inquiries WHERE id=?', [$id]);
                flash('Conversation deleted.');
                redirect('admin/inbox');
            }
            if ($action === 'reply') {
                $channel = post('channel');
                $text = mb_substr(post('text'), 0, 8000);
                if ($channel === 'note') {
                    if (trim($text) !== '') thread_add($id, 'note', 'admin', trim($text), (int)$me['id'], ['author' => $by['name']]);
                    flash('Note saved.');
                } else {
                    [$ok, $err] = inquiry_reply($q, $channel === 'sms' ? 'sms' : 'email', $text, $by);
                    flash($ok ? ($channel === 'sms' ? 'Text sent.' : 'Email sent.') : $err, $ok ? 'ok' : 'error');
                    if (!$ok) $_SESSION['reply_draft'] = $text;
                }
                redirect('admin/inbox/' . $id . '#reply');
            }
            $st = in_array(post('status'), ['new', 'contacted', 'closed'], true) ? post('status') : $q['status'];
            $row = ['status' => $st, 'admin_notes' => mb_substr(post('admin_notes'), 0, 4000), 'updated_at' => now()];
            if (isset($_POST['sms_opt_in_record'])) {   // staff record consent given by phone / in person
                $row['sms_opt_in'] = 1;
                thread_add($id, 'system', 'sms', 'Text consent recorded by ' . $by['name'] . ' (customer agreed by phone or in person).', (int)$me['id']);
            }
            if ($st !== $q['status']) thread_add($id, 'system', 'admin', 'Status changed to ' . $st . ' by ' . $by['name'] . '.', (int)$me['id']);
            db_update('inquiries', $id, $row);
            flash('Saved.');
            redirect('admin/inbox/' . $id);
        }
        $draft = $_SESSION['reply_draft'] ?? '';
        unset($_SESSION['reply_draft']);
        admin_render('Conversation', fn() => view('admin/inbox_view', ['q' => $q, 'thread' => thread_messages($id), 'draft' => $draft,
            'canText' => inquiry_can_text($q), 'smsOn' => sms_enabled()]));
        return;
    }
    $status = (string)($_GET['status'] ?? 'open');
    $where = match ($status) { 'new' => "i.status='new'", 'contacted' => "i.status='contacted'", 'closed' => "i.status='closed'", 'all' => '1=1', default => "i.status<>'closed'" };
    $rows = db_all("SELECT i.*,
        (SELECT COUNT(*) FROM messages m WHERE m.inquiry_id=i.id AND m.direction IN ('in','out')) AS msg_count,
        (SELECT m.author FROM messages m WHERE m.inquiry_id=i.id AND m.direction='out' ORDER BY m.id DESC LIMIT 1) AS last_reply_by,
        (SELECT m.direction FROM messages m WHERE m.inquiry_id=i.id AND m.direction IN ('in','out') ORDER BY m.id DESC LIMIT 1) AS last_dir,
        (SELECT m.channel FROM messages m WHERE m.inquiry_id=i.id AND m.direction IN ('in','out') ORDER BY m.id DESC LIMIT 1) AS last_channel
        FROM inquiries i WHERE $where ORDER BY COALESCE(i.last_activity_at, i.created_at) DESC LIMIT 300");
    admin_render('Inbox', fn() => view('admin/inbox', ['rows' => $rows, 'status' => $status]));
}

/* ======================= Users ======================= */

function admin_users(?int $id, string $sub, string $method): void
{
    $me = require_perm(PERM_USERS);
    if ($method === 'POST') {
        $action = post('action');
        if ($action === 'create') {
            $email = strtolower(post('email'));
            $pw = (string)($_POST['password'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { flash('Enter a valid email address.', 'error'); redirect('admin/users'); }
            if (db_val('SELECT COUNT(*) FROM users WHERE email=?', [$email])) { flash('An account with that email already exists.', 'error'); redirect('admin/users'); }
            if ($p = password_problem($pw)) { flash('Password: ' . $p, 'error'); redirect('admin/users'); }
            $perms = admin_perms_from_post($me);
            db_insert('users', ['email' => $email, 'name' => post('name'), 'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'permissions' => $perms,
                'is_enabled' => 1, 'is_protected' => 0, 'totp_secret' => null, 'totp_enabled' => 0, 'totp_last_step' => 0, 'created_at' => now()]);
            flash('Account created for ' . $email . '. Give them the password privately and ask them to change it and turn on two-step sign-in.');
            redirect('admin/users');
        }
        $target = $id ? db_one('SELECT * FROM users WHERE id=?', [$id]) : null;
        if (!$target) redirect('admin/users');
        if ((int)$target['is_protected'] === 1 && !is_hostmaster($me)) {
            flash('Only the hostmaster account can change itself.', 'error'); redirect('admin/users');
        }
        if ($action === 'update') {
            $row = ['name' => post('name')];
            $ph = trim(post('phone'));
            if ($ph !== '' && sms_e164($ph) === '') { flash('Check the mobile number (10 digits).', 'error'); redirect('admin/users'); }
            $row += ['phone' => $ph === '' ? '' : sms_pretty(sms_e164($ph)), 'notify_email' => isset($_POST['notify_email']) ? 1 : 0,
                     'notify_sms' => isset($_POST['notify_sms']) && $ph !== '' ? 1 : 0, 'sms_style' => post('sms_style') === 'link' ? 'link' : 'details',
                     'sms_relay' => isset($_POST['sms_relay']) && $ph !== '' ? 1 : 0];
            if ((int)$target['is_protected'] !== 1) {
                $row['permissions'] = admin_perms_from_post($me);
                $row['is_enabled'] = (int)$target['id'] === (int)$me['id'] ? 1 : (isset($_POST['is_enabled']) ? 1 : 0);
            }
            db_update('users', $id, $row);
            flash('Account updated.');
        } elseif ($action === 'password') {
            $pw = (string)($_POST['password'] ?? '');
            if ($p = password_problem($pw)) { flash('Password: ' . $p, 'error'); redirect('admin/users'); }
            db_update('users', $id, ['password_hash' => password_hash($pw, PASSWORD_DEFAULT)]);
            if ((int)$id === (int)$me['id']) session_refresh_password($id);
            flash('Password changed for ' . $target['email'] . '.');
        } elseif ($action === 'reset_mfa') {
            db_update('users', $id, ['totp_enabled' => 0, 'totp_secret' => null, 'totp_last_step' => 0]);
            db_exec('DELETE FROM recovery_codes WHERE user_id=?', [$id]);
            flash('Two-step sign-in turned off for ' . $target['email'] . '. They can set it up again under My account.');
        } elseif ($action === 'delete') {
            if ((int)$target['is_protected'] === 1 || (int)$target['id'] === (int)$me['id']) {
                flash('This account cannot be deleted.', 'error');
            } else {
                db_exec('DELETE FROM users WHERE id=?', [$id]);
                flash('Account deleted.');
            }
        }
        redirect('admin/users');
    }
    admin_render('Users', fn() => view('admin/users', ['rows' => db_all('SELECT * FROM users ORDER BY is_protected DESC, email'), 'me' => $me]));
}

/** Permissions from the form; a non-hostmaster can only grant permissions they have. */
function admin_perms_from_post(array $me): int
{
    $p = 0;
    foreach ((array)($_POST['perm'] ?? []) as $bit) {
        $bit = (int)$bit;
        if (isset(PERM_LABELS[$bit]) && (is_hostmaster($me) || ((int)$me['permissions'] & $bit))) $p |= $bit;
    }
    return $p;
}

/* ======================= Settings ======================= */

const SETTINGS_FIELDS = [
    'Business' => [
        'company_name' => 'Company name', 'tagline' => 'Short tagline (under the logo)', 'phone' => 'Phone number',
        'call_label' => 'Call prompt (e.g. "Call or text Becky")', 'contact_email' => 'Public contact email (leave blank to hide)',
        'address_line1' => 'Street address', 'address_line2' => 'City, state ZIP line', 'city' => 'City (for search engines)',
        'state' => 'State', 'zip' => 'ZIP', 'service_area_summary' => 'Service area summary (footer)',
    ],
    'Notifications' => [
        'notify_name' => 'Primary recipient name (shown in conversations)',
        'notify_email' => 'Email new inquiries and replies to (blank = no email alert)',
        'notify_sms_phone' => 'Text alerts to this mobile number (blank = no text alert)',
        'notify_sms_style' => 'Text alerts contain',
        'notify_sms_relay' => 'Customer texts go to this phone, and replying answers the customer',
        'alert_reply_mode' => 'When you reply to an alert email',
        'notify_from' => 'Send alerts from (a cleanbeansllc.com address)',
    ],
    'Domain email (mail sent to @cleanbeansllc.com addresses)' => [
        'mail_fwd_catchall' => 'Forward all other @cleanbeansllc.com mail (info@, becky@ …) to. One or more addresses, comma-separated. Blank = send to the hostmaster destination',
    ],
    'Social links (full https:// address, blank to hide)' => [
        'social_facebook' => 'Facebook', 'social_instagram' => 'Instagram', 'social_youtube' => 'YouTube', 'social_google' => 'Google Business',
        'social_tiktok' => 'TikTok', 'social_twitter' => 'X / Twitter', 'social_linkedin' => 'LinkedIn', 'social_pinterest' => 'Pinterest', 'social_snapchat' => 'Snapchat',
    ],
];
const SETTINGS_DEFAULTS = ['notify_name' => 'Becky', 'notify_sms_style' => 'details', 'notify_sms_relay' => '1', 'alert_reply_mode' => 'thread',
    'sms_enabled' => '0', 'sms_provider' => 'log', 'notify_from' => 'hostmaster@cleanbeansllc.com', 'mail_fwd_catchall' => 'owner@example.com', 'mail_fwd_hostmaster' => 'hostmaster@trudj.net'];
const SETTINGS_SMS = [
    'sms_enabled' => 'Text messages', 'sms_provider' => 'Provider', 'sms_from' => 'Business texting number (the number customers text)',
    'sms_twilio_sid' => 'Twilio Account SID', 'sms_twilio_token' => 'Twilio Auth Token',
    'sms_telnyx_key' => 'Telnyx API key', 'sms_telnyx_public_key' => 'Telnyx public key (webhook signing)', 'sms_telnyx_profile' => 'Telnyx messaging profile ID (optional)',
];
const SETTINGS_SECRETS = ['sms_twilio_token', 'sms_telnyx_key'];
const SETTINGS_CHOICES = [
    'notify_sms_style' => ['details' => 'Full details and a link to the admin', 'link' => 'Just a link to the admin'],
    'notify_sms_relay' => ['1' => 'Yes', '0' => 'No'],
    'alert_reply_mode' => ['thread' => 'Reply goes to the customer from Clean Beans and is saved in the conversation', 'direct' => 'Reply goes straight to the customer (not saved)'],
    'sms_enabled' => ['0' => 'Off', '1' => 'On'],
    'sms_provider' => SMS_PROVIDERS,
];
const SETTINGS_HOSTMASTER = ['mail_fwd_hostmaster' => 'Forward hostmaster@cleanbeansllc.com to (comma-separated)', 'allow_indexing' => 'Let search engines index the site (1 = yes; leave 0 while staging)', 'show_credits' => 'Show St Croix Tech / TRUDJ.net credits (1 = yes)'];

function admin_settings(string $method): void
{
    $me = require_perm(PERM_SETTINGS);
    $fields = SETTINGS_FIELDS;
    if (is_hostmaster($me)) {
        $fields['Text messages (hostmaster only)'] = SETTINGS_SMS;
        $fields['Hostmaster only'] = SETTINGS_HOSTMASTER;
    }
    if ($method === 'POST' && post('action') === 'test_sms' && is_hostmaster($me)) {
        $to = trim(post('test_to'));
        $res = sms_e164($to) === '' ? ['ok' => false, 'error' => 'enter a mobile number'] : sms_send($to, setting('brand_name', 'Clean Beans') . ' website: test text. Reply to this number to test replies.');
        flash($res['ok'] ? 'Test text sent to ' . $to . '.' : 'Test text not sent: ' . $res['error'], $res['ok'] ? 'ok' : 'error');
        redirect('admin/settings#sms');
    }
    if ($method === 'POST') {
        foreach ($fields as $group) {
            foreach ($group as $key => $label) {
                if (!array_key_exists($key, $_POST)) continue;
                $v = mb_substr(trim((string)$_POST[$key]), 0, 1000);
                if (in_array($key, SETTINGS_SECRETS, true) && $v === '') continue;   // blank = keep the saved secret
                if (isset(SETTINGS_CHOICES[$key]) && !array_key_exists($v, SETTINGS_CHOICES[$key])) continue;
                if (in_array($key, ['notify_sms_phone', 'sms_from'], true) && $v !== '') {
                    if (sms_e164($v) === '') { flash($label . ': check the number (10 digits, or +country code).', 'error'); continue; }
                    $v = $key === 'sms_from' ? sms_e164($v) : sms_pretty(sms_e164($v));
                }
                if (str_starts_with($key, 'social_') && $v !== '' && !preg_match('#^https://#', $v)) {
                    flash($label . ' link must start with https:// - not saved.', 'error');
                    continue;
                }
                if (str_starts_with($key, 'mail_fwd_') && $v !== '') {
                    $list = array_map('trim', explode(',', $v));
                    $bad = array_filter($list, fn($a) => !filter_var($a, FILTER_VALIDATE_EMAIL));
                    if ($bad) { flash($label . ': not a valid email address (' . e(implode(', ', $bad)) . ') - not saved.', 'error'); continue; }
                    if (array_filter($list, fn($a) => str_ends_with(strtolower($a), '@cleanbeansllc.com'))) { flash('Domain email: forward to an outside address, not an @cleanbeansllc.com one (that would loop) - not saved.', 'error'); continue; }
                    $v = implode(', ', $list);
                }
                if (in_array($key, ['contact_email', 'notify_email', 'notify_from'], true) && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                    flash($label . ': not a valid email address - not saved.', 'error');
                    continue;
                }
                setting_save($key, $v);
            }
        }
        flash('Settings saved.');
        redirect('admin/settings');
    }
    admin_render('Settings', fn() => view('admin/settings', ['fields' => $fields, 'hostmaster' => is_hostmaster($me)]));
}

/* ======================= My account (password + two-step sign-in) ======================= */

function admin_account(string $sub, string $method): void
{
    $me = require_login();
    $id = (int)$me['id'];
    $newCodes = null;
    if ($method === 'POST') {
        $action = post('action');
        if ($action === 'password') {
            if (!password_verify((string)($_POST['current'] ?? ''), $me['password_hash'])) {
                flash('Your current password was not correct.', 'error');
            } elseif ($p = password_problem((string)($_POST['password'] ?? ''))) {
                flash('New password: ' . $p, 'error');
            } elseif (($_POST['password'] ?? '') !== ($_POST['password2'] ?? '')) {
                flash('The new passwords do not match.', 'error');
            } else {
                db_update('users', $id, ['password_hash' => password_hash((string)$_POST['password'], PASSWORD_DEFAULT)]);
                session_refresh_password($id);
                flash('Password changed.');
            }
            redirect('admin/account');
        }
        if ($action === 'mfa_start') {
            $_SESSION['totp_pending'] = totp_new_secret();
            redirect('admin/account#mfa');
        }
        if ($action === 'mfa_confirm') {
            $secret = (string)($_SESSION['totp_pending'] ?? '');
            $step = totp_verify($secret, preg_replace('/\D/', '', post('code')));
            if ($step > 0) {
                db_update('users', $id, ['totp_secret' => $secret, 'totp_enabled' => 1, 'totp_last_step' => $step]);
                unset($_SESSION['totp_pending']);
                $_SESSION['show_codes'] = recovery_codes_generate($id);
                flash('Two-step sign-in is on. Save your recovery codes now.');
            } else {
                flash('That code did not match. Check the time on your phone and try the newest code.', 'error');
            }
            redirect('admin/account#mfa');
        }
        if ($action === 'mfa_off') {
            if (!password_verify((string)($_POST['current'] ?? ''), $me['password_hash'])) {
                flash('Enter your current password to turn off two-step sign-in.', 'error');
            } else {
                db_update('users', $id, ['totp_enabled' => 0, 'totp_secret' => null, 'totp_last_step' => 0]);
                db_exec('DELETE FROM recovery_codes WHERE user_id=?', [$id]);
                flash('Two-step sign-in turned off.');
            }
            redirect('admin/account#mfa');
        }
        if ($action === 'new_codes' && (int)$me['totp_enabled'] === 1) {
            $_SESSION['show_codes'] = recovery_codes_generate($id);
            flash('New recovery codes made. The old ones no longer work.');
            redirect('admin/account#mfa');
        }
        redirect('admin/account');
    }
    $newCodes = $_SESSION['show_codes'] ?? null;
    unset($_SESSION['show_codes']);
    admin_render('My account', fn() => view('admin/account', [
        'me' => $me, 'pending' => $_SESSION['totp_pending'] ?? null, 'codes' => $newCodes,
        'codes_left' => (int)db_val('SELECT COUNT(*) FROM recovery_codes WHERE user_id=? AND used_at IS NULL', [$id]),
    ]));
}
