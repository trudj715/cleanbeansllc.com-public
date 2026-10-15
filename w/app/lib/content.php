<?php
/**
 * Read-side helpers for pages and navigation,
 * plus saving public inquiries with spam protection.
 */
declare(strict_types=1);

const SECTION_TYPES = [
    'text'         => 'Text',
    'image_text'   => 'Photo + text',
    'services'     => 'Service links',
    'gallery'      => 'Photo gallery',
    'cta'          => 'Call-to-action banner',
    'map'          => 'Map / service area',
    'faq'          => 'Questions & answers',
    'request_form' => 'Service request form',
];

const SERVICE_TYPES = ['House cleaning', 'Deep cleaning', 'Move-in / move-out', 'Rental flip / listing prep', 'Commercial / office', 'Post-construction', 'Hoarding / biohazard', 'Other'];

/* ---------- Pages & navigation ---------- */

function page_by_slug(string $slug, bool $includeHidden = false): ?array
{
    return db_one('SELECT * FROM pages WHERE slug=?' . ($includeHidden ? '' : ' AND is_published=1'), [$slug]);
}

function page_sections(int $pageId, bool $includeDisabled = false): array
{
    $rows = db_all('SELECT * FROM sections WHERE page_id=?' . ($includeDisabled ? '' : ' AND is_enabled=1') . ' ORDER BY sort_order, id', [$pageId]);
    foreach ($rows as &$r) {
        $r['data'] = json_decode((string)$r['data'], true) ?: [];
    }
    return $rows;
}

/** Navigation: published pages flagged for the menu. */
function nav_items(): array
{
    static $items = null;
    if ($items === null) {
        $items = [];
        foreach (db_all('SELECT slug, nav_label, title, nav_order FROM pages WHERE is_published=1 AND show_in_nav=1 ORDER BY nav_order, id') as $p) {
            $items[] = ['href' => $p['slug'] === 'home' ? url() : url($p['slug']), 'label' => $p['nav_label'] ?: $p['title'], 'slug' => $p['slug'], 'order' => (int)$p['nav_order']];
        }
    }
    return $items;
}

/** Service pages shown by the "services" section type (pages flagged with a service summary). */
function service_pages(): array
{
    return db_all("SELECT p.*, m.file, m.file_sm, m.alt_text, m.width, m.height FROM pages p LEFT JOIN media m ON m.id=p.hero_media_id
                   WHERE p.is_published=1 AND p.slug NOT IN ('home') AND p.show_in_nav=1 AND p.slug NOT IN ('service-area', 'about', 'our-work') ORDER BY p.nav_order, p.id");
}

/* ---------- Inquiries (public forms) ---------- */

/**
 * Validate and save a public inquiry. Returns [ok(bool), errors(array)].
 * Spam protection: hidden honeypot field, minimum time on page, per-IP hourly limit.
 */
function inquiry_submit(string $kind, array $in): array
{
    $errors = [];
    if (($in['website'] ?? '') !== '') {
        return [true, []];                         // honeypot filled: pretend success, store nothing
    }
    $started = (int)($in['_t'] ?? 0);
    if ($started && time() - $started < 3) {
        return [true, []];                         // submitted faster than a person can type
    }
    $since = date('Y-m-d H:i:s', time() - 3600);
    if ((int)db_val('SELECT COUNT(*) FROM inquiries WHERE ip=? AND created_at>=?', [client_ip(), $since]) >= 8) {
        return [false, ['form' => 'Too many requests from this connection. Please call us instead.']];
    }

    $name  = trim(mb_substr((string)($in['name'] ?? ''), 0, 120));
    $phone = trim(mb_substr((string)($in['phone'] ?? ''), 0, 40));
    $email = trim(mb_substr((string)($in['email'] ?? ''), 0, 190));
    $msg   = trim(mb_substr((string)($in['message'] ?? ''), 0, 4000));
    $loc   = trim(mb_substr((string)($in['location'] ?? ''), 0, 255));
    $svc   = (string)($in['service_type'] ?? '');

    if ($name === '') $errors['name'] = 'Enter your name.';
    if ($phone === '' && $email === '') $errors['phone'] = 'Enter a phone number or email so we can reach you.';
    if ($phone !== '' && strlen(phone_digits($phone)) < 7) $errors['phone'] = 'Check the phone number.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Check the email address.';
    if ($kind === 'service' && !in_array($svc, SERVICE_TYPES, true)) $svc = 'Other';
    if ($errors) return [false, $errors];

    $optIn = !empty($in['sms_opt_in']) && $phone !== '' ? 1 : 0;
    $now = now();
    $newId = db_insert('inquiries', [
        'kind' => $kind, 'name' => $name, 'phone' => $phone, 'email' => $email,
        'location' => $loc, 'service_type' => $kind === 'service' ? $svc : '', 'message' => $msg,
        'status' => 'new', 'admin_notes' => '', 'ip' => client_ip(),
        'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        'sms_opt_in' => $optIn, 'sms_opted_out' => 0, 'reply_token' => bin2hex(random_bytes(6)),
        'created_at' => $now, 'updated_at' => $now, 'last_activity_at' => $now,
    ]);
    thread_add($newId, 'in', 'web', $msg, null, ['author' => $name, 'addr' => $email ?: $phone, 'status' => 'received']);
    if ($optIn) {
        thread_add($newId, 'system', 'sms', 'Customer agreed to text messages on the quote form (' . date('M j, Y g:ia') . ', IP ' . client_ip() . ').');
    }
    inquiry_alert($newId, 'new');
    return [true, []];
}

/* ---------- Icons (used when a service page has no banner photo yet) ---------- */

/** Line icon for a service page slug; falls back to a sparkle. */
function service_icon(string $slug): string
{
    $paths = [
        'residential-cleaning' => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10v9.5h13V10"/><path d="M10 19.5v-5h4v5"/>',
        'move-in-move-out'     => '<path d="M3.5 8 12 4l8.5 4v8L12 20l-8.5-4Z"/><path d="M3.5 8 12 12l8.5-4"/><path d="M12 12v8"/>',
        'rental-flips'         => '<circle cx="8" cy="15" r="4"/><path d="m11 12 8.5-8.5"/><path d="m16.5 6.5 2.5 2.5"/><path d="m14 9 2 2"/>',
        'commercial-cleaning'  => '<path d="M4.5 20.5V5l8-2v17.5"/><path d="M12.5 8.5h7v12"/><path d="M3 20.5h18"/><path d="M7.5 8h2M7.5 11.5h2M7.5 15h2M15.5 12h1.5M15.5 15.5h1.5"/>',
        'post-construction'    => '<path d="M3 17h18"/><path d="M5 17v-2a7 7 0 0 1 14 0v2"/><path d="M10 8.3V6h4v2.3"/><path d="M4 17v2.5h16V17"/>',
        'specialty-cleaning'   => '<path d="M12 3 5 6v5.5c0 4.5 3 7.8 7 9.5 4-1.7 7-5 7-9.5V6Z"/><path d="m9 12 2 2 4-4"/>',
    ];
    $d = $paths[$slug] ?? '<path d="M12 3v4M12 17v4M3 12h4M17 12h4"/><path d="m6 6 2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6"/>';
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}
