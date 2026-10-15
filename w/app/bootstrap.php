<?php
/**
 * Clean Beans LLC website - startup.
 * Every request (public, admin, installer) goes through this file first.
 *
 * Order: error logging -> constants -> config -> session -> libraries.
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));          // .../w
define('APP_DIR', __DIR__);                    // .../w/app
define('APP_NAME', 'Clean Beans Website');
define('APP_VERSION', '1.2.0');   // 1.2.0 = featured Facebook photos lead the site, text screenshots removed; 1.1.1 = Domain email forwarding set in admin Settings; 1.1.0 = job photos from Facebook, Our Work gallery; 1.0.1 = Clean Beans logo from the patch; 1.0.0 = first build (staged at /w/), from the kmtow.com 3.0.0 platform
define('APP_BUILDER', 'St Croix Tech Services / TRUDJ.net');
define('HOSTMASTER_EMAIL', 'hostmaster@trudj.net');

require APP_DIR . '/lib/errors.php';

// Site config (database credentials) is written by install.php and never committed to git.
$cfg = APP_DIR . '/config.php';
if (is_file($cfg)) {
    require $cfg;
}
if (!defined('BASE_PATH')) {
    define('BASE_PATH', '/w');   // URL path of this app, no trailing slash ("" when at the domain root)
}
define('IS_INSTALLED', is_file(APP_DIR . '/install.lock') && defined('DB_NAME'));

date_default_timezone_set(defined('SITE_TIMEZONE') ? SITE_TIMEZONE : 'America/Chicago');
mb_internal_encoding('UTF-8');

require APP_DIR . '/lib/db.php';
require APP_DIR . '/lib/helpers.php';
require APP_DIR . '/lib/sanitize.php';
require APP_DIR . '/lib/auth.php';
require APP_DIR . '/lib/totp.php';
require APP_DIR . '/lib/media.php';
require APP_DIR . '/lib/content.php';
require APP_DIR . '/lib/migrations.php';

start_session();
run_content_migrations();
