<?php
/**
 * Error logging to /w/err/ and a safe public error page.
 * - Never shows PHP errors, paths or stack traces to visitors.
 * - Non-fatal warnings are logged and the page keeps loading.
 * - Exceptions and fatal errors show a friendly page with a reference ID.
 * - Log lines never include passwords (request bodies are not logged).
 */
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

define('REQUEST_ID', bin2hex(random_bytes(6)));

function err_log(string $kind, string $body): void
{
    $dir = dirname(__DIR__, 2) . '/err';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    if (!is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "Options -Indexes\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
    }
    $head = 'Time: ' . date('c') . "\n"
          . 'Ref: ' . REQUEST_ID . "\n"
          . 'Version: ' . (defined('APP_VERSION') ? APP_VERSION : '?') . "\n"
          . 'Request: ' . ($_SERVER['REQUEST_METHOD'] ?? 'CLI') . ' ' . ($_SERVER['REQUEST_URI'] ?? '') . "\n";
    $file = sprintf('%s/err_%s_%s_%s.log', $dir, date('Ymd_His'), strtolower($kind), REQUEST_ID);
    @file_put_contents($file, "[{$kind}]\n" . $head . $body . "\n", FILE_APPEND);
}

function err_page(int $code = 500): void
{
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');
    }
    $phone = '(715) 222-2444';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<meta name="robots" content="noindex"><title>Temporarily unavailable | Clean Beans LLC</title>'
       . '<style>body{font-family:system-ui,Segoe UI,Arial,sans-serif;background:#f4f7f4;color:#1f2622;margin:0;padding:48px 20px}'
       . '.box{max-width:560px;margin:0 auto;background:#fff;border-top:6px solid #1d2a23;padding:28px;border-radius:6px}'
       . 'a.call{display:inline-block;margin-top:12px;background:#3dbb63;color:#07140b;font-weight:700;padding:12px 18px;border-radius:6px;text-decoration:none}'
       . 'small{color:#5b6673}</style></head><body><div class="box">'
       . '<h1>This page is temporarily unavailable</h1>'
       . '<p>Need a cleaning quote? Call or text us directly.</p>'
       . '<a class="call" href="tel:7152222444">Call ' . $phone . '</a>'
       . '<p><small>Reference: ' . REQUEST_ID . '</small></p></div></body></html>';
}

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    err_log('warning', "Severity: {$severity}\nMessage: {$message}\nFile: {$file}:{$line}\n");
    return true;
});

set_exception_handler(function (Throwable $e): void {
    err_log('exception', 'Type: ' . get_class($e) . "\nMessage: " . $e->getMessage()
        . "\nFile: " . $e->getFile() . ':' . $e->getLine() . "\nTrace:\n" . $e->getTraceAsString() . "\n");
    err_page(500);
});

register_shutdown_function(function (): void {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        err_log('fatal', "Message: {$e['message']}\nFile: {$e['file']}:{$e['line']}\n");
        err_page(500);
    }
});
