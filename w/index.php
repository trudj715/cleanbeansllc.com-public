<?php
/**
 * Clean Beans LLC website - single entry point.
 * Apache sends every URL that is not a real file here (see .htaccess).
 */
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

if (!IS_INSTALLED) {
    redirect('install.php');
}

// Path relative to the app, e.g. "", "deep-cleaning", "admin/inbox"
$path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (BASE_PATH !== '' && str_starts_with($path, BASE_PATH)) {
    $path = substr($path, strlen(BASE_PATH));
}
$path = trim(rawurldecode($path), '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');

if ($path === 'admin' || str_starts_with($path, 'admin/')) {
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
    require APP_DIR . '/admin.php';
    admin_route(substr($path, 6) ?: '', $method);
    exit;
}

require APP_DIR . '/public.php';
public_route($path, $method);
