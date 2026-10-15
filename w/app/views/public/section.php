<?php
/** One page section. @var array $s @var array $page @var int $i */
$d = $s['data'];
$phone = setting('phone', '(715) 222-2444');
$tel = phone_digits($phone);
$heading = trim((string)$s['heading']);
$body = (string)($s['body_html'] ?? '');
$band = !empty($d['band']);   // white background band for contrast

switch ($s['type']):

case 'text': ?>
<section class="section<?= $band ? ' band' : '' ?>"><div class="wrap"><div class="prose">
  <?php if ($heading !== ''): ?><h2><?= e($heading) ?></h2><?php endif; ?>
  <?= $body ?>
</div></div></section>
<?php break;

case 'image_text':
  $m = media_get($s['media_id'] ? (int)$s['media_id'] : null); ?>
<section class="section<?= $band ? ' band' : '' ?>"><div class="wrap split<?= ($d['align'] ?? 'left') === 'right' ? ' media-right' : '' ?>">
  <div class="split-media"><?= media_img($m, '', '(max-width: 820px) 100vw, 50vw') ?></div>
  <div class="prose">
    <?php if ($heading !== ''): ?><h2><?= e($heading) ?></h2><?php endif; ?>
    <?= $body ?>
  </div>
</div></section>
<?php break;

case 'services':
  $svc = service_pages(); ?>
<section class="section<?= $band ? ' band' : '' ?>"><div class="wrap">
  <?php if ($heading !== ''): ?><h2><?= e($heading) ?></h2><?php endif; ?>
  <?php if ($body !== ''): ?><div class="prose"><?= $body ?></div><?php endif; ?>
  <div class="services">
    <?php foreach ($svc as $p): ?>
      <a class="service<?= $p['file'] ? '' : ' noimg' ?>" href="<?= e(url($p['slug'])) ?>">
        <?php if (!$p['file']): ?><span class="service-icon"><?= service_icon($p['slug']) ?></span><?php endif; ?>
        <?php if ($p['file']): ?><?= media_img(['file' => $p['file'], 'file_sm' => $p['file_sm'], 'alt_text' => '', 'width' => $p['width'], 'height' => $p['height']], '', '(max-width: 560px) 100vw, (max-width: 1000px) 50vw, 25vw', true, '') ?><?php endif; ?>
        <span class="service-label"><h3><?= e($p['title']) ?></h3><?php if (trim((string)$p['hero_text']) !== ''): ?><p><?= e(plain_text((string)$p['hero_text'], 140)) ?></p><?php endif; ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</div></section>
<?php break;

case 'gallery':
  $media = media_many($d['media_ids'] ?? []); ?>
<section class="section<?= $band ? ' band' : '' ?>"><div class="wrap">
  <?php if ($heading !== ''): ?><h2><?= e($heading) ?></h2><?php endif; ?>
  <?php if ($body !== ''): ?><div class="prose"><?= $body ?></div><?php endif; ?>
  <div class="gallery">
    <?php foreach ($d['media_ids'] ?? [] as $mid): $m = $media[(int)$mid] ?? null; if (!$m) continue; ?>
      <button type="button" data-full="<?= e(media_url($m)) ?>" data-alt="<?= e($m['alt_text']) ?>" aria-label="Enlarge photo: <?= e($m['alt_text']) ?>">
        <?= media_img($m, '', '(max-width: 600px) 100vw, 33vw') ?>
      </button>
    <?php endforeach; ?>
  </div>
</div></section>
<?php break;

case 'cta': ?>
<section class="cta-band"><div class="wrap">
  <div>
    <h2><?= e($heading !== '' ? $heading : 'Ready for a cleaner space?') ?></h2>
    <?php if ($body !== ''): ?><div><?= $body ?></div><?php endif; ?>
  </div>
  <a class="cta-phone" href="tel:<?= e($tel) ?>"><?= e($phone) ?></a>
</div></section>
<?php break;

case 'map':
  $lat = (float)($d['lat'] ?? 0); $lng = (float)($d['lng'] ?? 0);
  $miles = (float)($d['radius_miles'] ?? 0);
  $address = (string)($d['address'] ?? setting('address_line1') . ', ' . setting('address_line2'));
  $areas = array_filter(array_map('trim', (array)($d['areas'] ?? []))); ?>
<section class="section<?= $band ? ' band' : '' ?>"><div class="wrap map-wrap">
  <div class="map-canvas" <?php if ($lat && $lng): ?>data-map data-lat="<?= e($lat) ?>" data-lng="<?= e($lng) ?>" data-miles="<?= e($miles) ?>" data-zoom="<?= (int)($d['zoom'] ?? 7) ?>" data-label="<?= e(setting('company_name', 'Clean Beans LLC')) ?>"<?php endif; ?>>
    <?php if (!$lat || !$lng): ?>
      <iframe title="Map of <?= e($address) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
        src="https://www.google.com/maps?q=<?= e(urlencode($address)) ?>&z=<?= (int)($d['zoom'] ?? 10) ?>&output=embed"></iframe>
    <?php endif; ?>
  </div>
  <div class="prose">
    <?php if ($heading !== ''): ?><h2><?= e($heading) ?></h2><?php endif; ?>
    <p><strong><?= e($address) ?></strong></p>
    <?= $body ?>
    <?php if ($miles > 0): ?><p>The shaded circle on the map shows our <?= e(rtrim(rtrim(number_format($miles, 1), '0'), '.')) ?>-mile service radius. Outside it? Call anyway and ask.</p><?php endif; ?>
    <?php if ($areas): ?><ul class="areas"><?php foreach ($areas as $a): ?><li><?= e($a) ?></li><?php endforeach; ?></ul><?php endif; ?>
  </div>
</div></section>
<?php if ($lat && $lng): ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js" defer></script>
<?php endif; ?>
<?php break;

case 'faq':
  $items = array_filter((array)($d['items'] ?? []), fn($q) => trim((string)($q['q'] ?? '')) !== ''); ?>
<section class="section<?= $band ? ' band' : '' ?>"><div class="wrap"><div class="prose faq" style="max-width:820px">
  <?php if ($heading !== ''): ?><h2><?= e($heading) ?></h2><?php endif; ?>
  <?php foreach ($items as $q): ?>
    <details><summary><?= e($q['q']) ?></summary><div class="answer"><?= sanitize_html((string)($q['a'] ?? '')) ?></div></details>
  <?php endforeach; ?>
</div></div></section>
<?php break;

case 'request_form':
  view('public/request_form', ['heading' => $heading, 'body' => $body, 'page' => $page, 'band' => $band]);
  break;

endswitch;
