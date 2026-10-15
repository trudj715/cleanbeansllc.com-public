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
    $_SESSION['form_old'] = array_intersect_key($_POST, array_flip(['name', 'phone', 'email', 'location', 'service_type', 'message']));
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
