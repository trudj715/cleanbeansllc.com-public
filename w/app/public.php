<?php
/**
 * Public site routes.
 */
declare(strict_types=1);

function public_route(string $path, string $method): void
{
    if ($path === 'sitemap.xml') { sitemap(); return; }
    if ($path === 'robots.txt')  { robots(); return; }

    if ($path === 'request' && $method === 'POST') { service_request_post(); return; }
    if (preg_match('#^sms/inbound/([a-f0-9]{32})$#', $path, $m)) { sms_webhook($m[1], $method); return; }

    // /home and /index.php go to the home page.
    if ($path === 'home' || $path === 'index.php') redirect('', 301);

    $slug = $path === '' ? 'home' : $path;
    if (!preg_match('#^[a-z0-9\-]+$#', $slug)) not_found();
    $page = page_by_slug($slug);
    if (!$page) not_found();
    render_page($page);
}

function render_page(array $page): void
{
    $sections = page_sections((int)$page['id']);
    $hero = media_get($page['hero_media_id'] ? (int)$page['hero_media_id'] : null);
    view('public/layout', [
        'title'   => $page['meta_title'] ?: $page['title'],
        'meta'    => [
            'description' => $page['meta_description'] ?: plain_text((string)($page['hero_text'] ?? ''), 160),
            'canonical'   => $page['slug'] === 'home' ? abs_url() : abs_url($page['slug']),
            'image'       => $hero ? abs_url('uploads/' . $hero['file']) : '',
        ],
        'current' => $page['slug'],
        'isHome'  => $page['slug'] === 'home',
        'content' => function () use ($page, $sections, $hero) {
            view('public/page', ['page' => $page, 'sections' => $sections, 'hero' => $hero]);
        },
    ]);
}

/* ---------- Service request form (posted from any page) ---------- */

function service_request_post(): void
{
    csrf_check();
    $back = (string)($_POST['_back'] ?? '');
    $back = preg_match('#^[a-z0-9\-/]*$#', $back) ? $back : '';
    [$ok, $errors] = inquiry_submit('service', $_POST);
    if ($ok) {
        redirect(abs_url($back) . '?sent=1#request');
    }
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_old'] = array_intersect_key($_POST, array_flip(['name', 'phone', 'email', 'location', 'service_type', 'message', 'sms_opt_in']));
    redirect(abs_url($back) . '#request');
}

/* ---------- SEO files ---------- */

function sitemap(): void
{
    header('Content-Type: application/xml; charset=utf-8');
    $urls = [];
    foreach (db_all('SELECT slug, updated_at FROM pages WHERE is_published=1') as $p) {
        $urls[] = [$p['slug'] === 'home' ? abs_url() : abs_url($p['slug']), $p['updated_at']];
    }
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as [$u, $mod]) {
        echo '  <url><loc>' . e($u) . '</loc>' . ($mod ? '<lastmod>' . e(substr($mod, 0, 10)) . '</lastmod>' : '') . "</url>\n";
    }
    echo "</urlset>\n";
}

function robots(): void
{
    header('Content-Type: text/plain; charset=utf-8');
    // While staged at /w/ before launch, keep search engines out (setting "allow_indexing").
    if (setting('allow_indexing', '0') !== '1') {
        echo "User-agent: *\nDisallow: /\n";
        return;
    }
    echo "User-agent: *\nDisallow: " . url('admin') . "\nSitemap: " . abs_url('sitemap.xml') . "\n";
}


/** Inbound text webhook (Twilio / Telnyx). URL secret + provider signature are both required. */
function sms_webhook(string $key, string $method): void
{
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    if ($method !== 'POST' || !hash_equals(sms_webhook_key(), $key)) { http_response_code(404); exit; }
    $raw = (string)file_get_contents('php://input');
    if (!sms_webhook_verified($raw)) {
        sms_log('REJECTED inbound webhook: bad or missing provider signature from ' . client_ip());
        http_response_code(403);
        exit;
    }
    $in = sms_parse_inbound($raw);
    if ($in) {
        try {
            sms_inbound($in);
        } catch (Throwable $e) {
            sms_log('ERROR handling inbound text: ' . $e->getMessage());
        }
    }
    sms_webhook_respond();
    exit;
}
