<?php
/** @var string $title @var callable $content */
$u = current_user();
$newCount = $u && can(PERM_INBOX) ? (int)db_val("SELECT COUNT(*) FROM inquiries WHERE status='new'") : 0;
$path = trim(substr((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), strlen(BASE_PATH)), '/');
$nav = [
    ['admin', 'Dashboard', 0],
    ['admin/inbox', 'Inbox', PERM_INBOX],
    ['admin/pages', 'Pages', PERM_PAGES],
    ['admin/media', 'Photos', PERM_MEDIA],
    ['admin/settings', 'Settings', PERM_SETTINGS],
    ['admin/users', 'Users', PERM_USERS],
    ['admin/account', 'My account', 0],
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($title) ?> | Site admin</title>
<link rel="icon" href="<?= e(url('assets/icons/favicon.ico')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
</head>
<body>
<?php if ($u): ?>
<header class="a-top">
  <a class="a-brand" href="<?= e(url('admin')) ?>"><img src="<?= e(asset('assets/img/logo.svg')) ?>" alt="" height="40"> Site admin</a>
  <button class="a-menu" type="button" aria-expanded="false" aria-controls="a-nav">Menu</button>
  <div class="a-user">
    <a href="<?= e(url()) ?>" target="_blank" rel="noopener">View website</a>
    <span><?= e($u['name'] ?: $u['email']) ?></span>
    <form method="post" action="<?= e(url('admin/logout')) ?>"><?= csrf_field() ?><button type="submit" class="linkish">Sign out</button></form>
  </div>
</header>
<div class="a-shell">
  <nav class="a-nav" id="a-nav" aria-label="Admin">
    <?php foreach ($nav as [$href, $label, $perm]): if ($perm && !can($perm)) continue;
      $active = $href === 'admin' ? $path === 'admin' : ($path === $href || str_starts_with($path, $href . '/')); ?>
      <a href="<?= e(url($href)) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= e($label) ?><?php if ($href === 'admin/inbox' && $newCount): ?> <span class="pill"><?= $newCount ?></span><?php endif; ?></a>
    <?php endforeach; ?>
    <div class="a-version"><?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?></div>
  </nav>
  <main class="a-main" id="main">
    <?php foreach (flashes() as $f): ?><div class="flash <?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>
    <?php $content(); ?>
  </main>
</div>
<?php else: ?>
<main class="a-solo" id="main">
  <?php foreach (flashes() as $f): ?><div class="flash <?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>
  <?php $content(); ?>
</main>
<?php endif; ?>
<script>window.KM_ADMIN = {base: <?= json_encode(BASE_PATH) ?>, csrf: <?= json_encode(csrf_token()) ?>};</script>
<script src="<?= e(asset('assets/js/admin.js')) ?>" defer></script>
</body>
</html>
