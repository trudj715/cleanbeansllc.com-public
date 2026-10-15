<?php
/**
 * Clean Beans LLC website - one-time installer.
 *
 *  - Connects to an EMPTY MySQL database (refuses one that already has tables)
 *  - Creates the tables and the Clean Beans starting content
 *  - Creates the permanent hostmaster@trudj.net account and, optionally, an owner account
 *  - Writes app/config.php, then locks itself (app/install.lock)
 */
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require APP_DIR . '/seed.php';

$locked = is_file(APP_DIR . '/install.lock');
$err = '';
$done = false;
$checks = [];

$autoPath = rtrim(str_replace('/install.php', '', (string)($_SERVER['SCRIPT_NAME'] ?? '/w/install.php')), '/');
$autoUrl = (is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $autoPath;

if (!$locked && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $host = post('db_host', 'localhost');
    $name = post('db_name');
    $user = post('db_user');
    $pass = (string)($_POST['db_pass'] ?? '');
    $baseUrl = rtrim(post('base_url') ?: $autoUrl, '/');
    $basePath = rtrim((string)parse_url($baseUrl, PHP_URL_PATH), '/');
    $hmPass = (string)($_POST['hm_pass'] ?? '');
    $ownerEmail = strtolower(post('owner_email'));
    $ownerName = post('owner_name');
    $ownerPass = (string)($_POST['owner_pass'] ?? '');

    if ($name === '' || $user === '') {
        $err = 'Enter the database name and database user.';
    } elseif ($p = password_problem($hmPass)) {
        $err = 'Hostmaster password: ' . $p;
    } elseif (!hash_equals($hmPass, (string)($_POST['hm_pass2'] ?? ''))) {
        $err = 'The two hostmaster passwords do not match.';
    } elseif ($ownerEmail !== '' && !filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
        $err = 'The owner email address is not valid.';
    } elseif ($ownerEmail !== '' && ($p = password_problem($ownerPass))) {
        $err = 'Owner password: ' . $p;
    } elseif ($ownerEmail === HOSTMASTER_EMAIL) {
        $err = 'Use a different email for the owner account.';
    } else {
        mysqli_report(MYSQLI_REPORT_OFF);
        $my = @new mysqli($host, $user, $pass, $name);
        if ($my->connect_errno) {
            $err = 'Could not connect to the database: ' . $my->connect_error;
        } else {
            $my->set_charset('utf8mb4');
            $tables = $my->query('SHOW TABLES');
            if ($tables && $tables->num_rows > 0) {
                $err = 'That database already has tables in it. The installer only uses an EMPTY database so it can never overwrite a live site. '
                     . 'Empty it in phpMyAdmin (or create a new database) and try again.';
            } elseif (!$my->multi_query((string)file_get_contents(__DIR__ . '/sql/schema.sql'))) {
                $err = 'Creating tables failed: ' . $my->error;
            } else {
                do { if ($r = $my->store_result()) $r->free(); } while ($my->more_results() && $my->next_result());
                if ($my->errno) {
                    $err = 'Creating tables stopped with an error: ' . $my->error;
                }
            }
            $my->close();
        }

        if ($err === '') {
            // Write config, load it, then seed through the normal database layer.
            $cfg = "<?php\n// Written by the Clean Beans installer on " . date('c') . ". Contains database credentials: never commit to git.\n"
                 . "define('DB_HOST', " . var_export($host, true) . ");\n"
                 . "define('DB_NAME', " . var_export($name, true) . ");\n"
                 . "define('DB_USER', " . var_export($user, true) . ");\n"
                 . "define('DB_PASS', " . var_export($pass, true) . ");\n"
                 . "define('BASE_URL', " . var_export($baseUrl, true) . ");\n"
                 . "if (!defined('BASE_PATH')) define('BASE_PATH', " . var_export($basePath, true) . ");\n";
            if (@file_put_contents(APP_DIR . '/config.php', $cfg) === false) {
                $err = 'Tables were created, but app/config.php could not be written. Check that the app folder is writable.';
            } else {
                @chmod(APP_DIR . '/config.php', 0640);
                foreach (['DB_HOST' => $host, 'DB_NAME' => $name, 'DB_USER' => $user, 'DB_PASS' => $pass, 'BASE_URL' => $baseUrl] as $c => $v) {
                    if (!defined($c)) define($c, $v);
                }
                db()->beginTransaction();
                try {
                    seed_cleanbeans();
                    db_insert('users', ['email' => HOSTMASTER_EMAIL, 'name' => 'Hostmaster (St Croix Tech)', 'password_hash' => password_hash($hmPass, PASSWORD_DEFAULT),
                        'permissions' => PERM_ALL, 'is_enabled' => 1, 'is_protected' => 1, 'totp_secret' => null, 'totp_enabled' => 0, 'totp_last_step' => 0, 'created_at' => now()]);
                    if ($ownerEmail !== '') {
                        db_insert('users', ['email' => $ownerEmail, 'name' => $ownerName, 'password_hash' => password_hash($ownerPass, PASSWORD_DEFAULT),
                            'permissions' => PERM_ALL, 'is_enabled' => 1, 'is_protected' => 0, 'totp_secret' => null, 'totp_enabled' => 0, 'totp_last_step' => 0, 'created_at' => now()]);
                    }
                    db()->commit();
                } catch (Throwable $e) {
                    db()->rollBack();
                    err_log('install', $e->getMessage() . "\n" . $e->getTraceAsString());
                    $err = 'Loading the starting content failed (' . $e->getMessage() . '). Empty the database and run the installer again.';
                }
                if ($err === '') {
                    foreach (['pages', 'sections', 'media', 'settings', 'users'] as $t) {
                        $checks[$t] = (int)db_val("SELECT COUNT(*) FROM $t");
                    }
                    $checks['missing photo files'] = implode(', ', array_filter(array_column(db_all('SELECT file FROM media'), 'file'), fn($f) => !is_file(__DIR__ . '/uploads/' . $f))) ?: 'none';
                    @file_put_contents(APP_DIR . '/install.lock', 'Installed ' . date('c') . ' version ' . APP_VERSION . "\n");
                    $locked = true;
                    $done = true;
                }
            }
        }
    }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Install | Clean Beans website</title>
<link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>">
</head>
<body>
<main class="a-solo" style="max-width:720px">
  <div class="login-card">
    <img src="<?= e(url('assets/img/logo.svg')) ?>" alt="Clean Beans LLC">
    <h1>Website installer <small class="muted">(v<?= e(APP_VERSION) ?>)</small></h1>
    <?php if ($err): ?><div class="flash error" role="alert"><?= e($err) ?></div><?php endif; ?>

    <?php if ($done): ?>
      <div class="flash">Installed. The installer is now locked.</div>
      <table class="t"><?php foreach ($checks as $k => $v): ?><tr><th><?= e($k) ?></th><td><?= e((string)$v) ?></td></tr><?php endforeach; ?></table>
      <p style="margin-top:16px"><a class="btn primary" href="<?= e(url('admin/login')) ?>">Sign in to the admin</a> <a class="btn ghost" href="<?= e(url()) ?>">View the website</a></p>
    <?php elseif ($locked): ?>
      <p>This website is already installed, so the installer is locked.</p>
      <p class="small muted">To reinstall onto a new, empty database, delete <code>app/install.lock</code> in cPanel File Manager first.</p>
      <p><a class="btn" href="<?= e(url('admin/login')) ?>">Admin sign in</a></p>
    <?php else: ?>
      <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <h2>Database</h2>
        <p class="small muted">From cPanel, MySQL Databases. The database must be empty.</p>
        <div class="grid2">
          <label class="f"><span>Database host</span><input type="text" name="db_host" value="<?= e(post('db_host', 'localhost')) ?>"></label>
          <label class="f"><span>Database name</span><input type="text" name="db_name" value="<?= e(post('db_name')) ?>" required></label>
          <label class="f"><span>Database user</span><input type="text" name="db_user" value="<?= e(post('db_user')) ?>" required></label>
          <label class="f"><span>Database password</span><input type="password" name="db_pass" autocomplete="new-password"></label>
        </div>
        <label class="f"><span>Website address</span><small>Where this site lives, e.g. https://cleanbeansllc.com/w</small><input type="text" name="base_url" value="<?= e(post('base_url', $autoUrl)) ?>"></label>

        <h2>Hostmaster account</h2>
        <p class="small muted">Permanent full-access account <strong><?= e(HOSTMASTER_EMAIL) ?></strong> for St Croix Tech Services.</p>
        <div class="grid2">
          <label class="f"><span>Password (12+ characters)</span><input type="password" name="hm_pass" minlength="12" required autocomplete="new-password"></label>
          <label class="f"><span>Password again</span><input type="password" name="hm_pass2" minlength="12" required autocomplete="new-password"></label>
        </div>

        <h2>Owner account (optional)</h2>
        <p class="small muted">An account for the business owner. You can also add it later under Users.</p>
        <div class="grid3">
          <label class="f"><span>Email</span><input type="email" name="owner_email" value="<?= e(post('owner_email')) ?>"></label>
          <label class="f"><span>Name</span><input type="text" name="owner_name" value="<?= e(post('owner_name')) ?>"></label>
          <label class="f"><span>Password</span><input type="password" name="owner_pass" minlength="12" autocomplete="new-password"></label>
        </div>
        <button class="btn primary" type="submit">Install</button>
      </form>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
