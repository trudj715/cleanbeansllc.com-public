<?php
/** @var array $page @var array $sections @var ?array $hero */
$phone = setting('phone', '(715) 222-2444');
$tel = phone_digits($phone);
$isHome = $page['slug'] === 'home';
?>
<?php if ($isHome): ?>
<section class="hero<?= $hero ? '' : ' plain-hero' ?>">
  <?php if (!$hero): ?><img class="hero-sparkles" src="<?= e(asset('assets/img/hero-sparkles.svg')) ?>" alt=""><?php endif; ?>
  <?php if ($hero): ?><div class="hero-media"><?= media_img($hero, '', '100vw', false, '') ?></div><?php endif; ?>
  <div class="wrap">
    <div class="hero-inner">
      <h1><?= e($page['hero_heading'] ?: $page['title']) ?></h1>
      <?php if (!empty($page['hero_text'])): ?><p class="hero-lead"><?= e($page['hero_text']) ?></p><?php endif; ?>
      <a class="hero-phone" href="tel:<?= e($tel) ?>"><span class="hero-phone-label"><?= e(setting('call_label', 'Call or text Becky')) ?></span><?= e($phone) ?></a>
      <div class="hero-actions">
        <a class="btn btn-call" href="#request">Request a free quote</a>
        <a class="btn btn-light" href="<?= e(url('about')) ?>">About Clean Beans</a>
      </div>
      <?php $creds = array_filter(array_map('trim', explode('|', setting('credentials', '')))); if ($creds): ?>
      <ul class="hero-creds"><?php foreach ($creds as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php else: ?>
<section class="page-hero<?= $hero ? '' : ' plain' ?>">
  <?php if ($hero): ?><div class="hero-media"><?= media_img($hero, '', '100vw', false, '') ?></div><?php endif; ?>
  <div class="wrap">
    <h1><?= e($page['hero_heading'] ?: $page['title']) ?></h1>
    <?php if (!empty($page['hero_text'])): ?><p><?= e($page['hero_text']) ?></p><?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php foreach ($sections as $i => $s) { view('public/section', ['s' => $s, 'page' => $page, 'i' => $i]); } ?>
