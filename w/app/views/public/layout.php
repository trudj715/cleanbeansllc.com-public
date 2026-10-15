<?php
/** @var string $title @var array $meta @var callable $content */
$company  = setting('company_name', 'Clean Beans LLC');
$phone    = setting('phone', '(715) 222-2444');
$tel      = phone_digits($phone);
$addr1    = setting('address_line1');
$addr2    = setting('address_line2');
$current  = $current ?? '';
$isHome   = $isHome ?? false;
$meta     = $meta ?? [];
$indexing = setting('allow_indexing', '0') === '1';
$robots   = !$indexing ? 'noindex,nofollow' : ($meta['robots'] ?? '');
// Don't repeat the company name when the page's search title already includes it.
$fullTitle = ($isHome || str_contains($title, $company)) ? $title : $title . ' | ' . $company;
$social = [];
foreach (['facebook' => 'Facebook', 'tiktok' => 'TikTok', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'google' => 'Google', 'twitter' => 'X', 'linkedin' => 'LinkedIn', 'pinterest' => 'Pinterest', 'snapchat' => 'Snapchat'] as $k => $label) {
    $u = setting('social_' . $k);
    if ($u !== '' && preg_match('#^https://#', $u)) $social[$k] = [$u, $label];
}
$business = [
    '@context' => 'https://schema.org', '@type' => 'HouseCleaningService',
    'name' => $company, 'telephone' => $phone, 'url' => abs_url(),
    'address' => array_filter(['@type' => 'PostalAddress', 'streetAddress' => $addr1, 'addressLocality' => setting('city', 'Hudson'),
                  'addressRegion' => setting('state', 'WI'), 'postalCode' => setting('zip', '54016'), 'addressCountry' => 'US']),
    'priceRange' => '$$',
    'areaServed' => setting('service_area_summary', 'Hudson, WI and the St. Croix Valley'),
    'logo' => abs_url('assets/img/logo-badge.png'),
];
if ($social) $business['sameAs'] = array_values(array_map(fn($s) => $s[0], $social));
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($fullTitle) ?></title>
<?php if (!empty($meta['description'])): ?><meta name="description" content="<?= e($meta['description']) ?>"><?php endif; ?>
<?php if ($robots !== ''): ?><meta name="robots" content="<?= e($robots) ?>"><?php endif; ?>
<?php if (!empty($meta['canonical'])): ?><link rel="canonical" href="<?= e($meta['canonical']) ?>"><?php endif; ?>
<meta property="og:site_name" content="<?= e($company) ?>">
<meta property="og:title" content="<?= e($fullTitle) ?>">
<meta property="og:type" content="<?= e($meta['type'] ?? 'website') ?>">
<?php if (!empty($meta['canonical'])): ?><meta property="og:url" content="<?= e($meta['canonical']) ?>"><?php endif; ?>
<?php if (!empty($meta['description'])): ?><meta property="og:description" content="<?= e($meta['description']) ?>"><?php endif; ?>
<?php if (!empty($meta['image'])): ?><meta property="og:image" content="<?= e($meta['image']) ?>"><?php endif; ?>
<link rel="icon" href="<?= e(url('assets/icons/favicon.ico')) ?>" sizes="any">
<link rel="icon" href="<?= e(url('assets/icons/favicon.svg')) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= e(url('assets/icons/apple-touch-icon.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600&display=swap">
<link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>">
<script type="application/ld+json"><?= json_encode($business, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php if (!empty($jsonld)): ?><script type="application/ld+json"><?= json_encode($jsonld, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script><?php endif; ?>
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<header class="site-header">
  <div class="wrap header-row">
    <a class="brand" href="<?= e(url()) ?>" aria-label="<?= e($company) ?> home">
      <img src="<?= e(asset('assets/img/logo.svg')) ?>" alt="" width="74" height="64">
      <span class="brand-text"><strong><?= e(setting('brand_name', 'Clean Beans')) ?></strong><small><?= e(setting('tagline', 'LLC · Hudson, WI')) ?></small></span>
    </a>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="main-nav">Menu</button>
    <nav class="main-nav" id="main-nav" aria-label="Main">
      <ul>
        <?php foreach (nav_items() as $n): ?>
          <li><a href="<?= e($n['href']) ?>"<?= $n['slug'] === $current ? ' aria-current="page"' : '' ?>><?= e($n['label']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <a class="btn btn-call header-call" href="tel:<?= e($tel) ?>">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1z"/></svg>
      <?= e($phone) ?>
    </a>
  </div>
  <div class="livery" aria-hidden="true"></div>
</header>

<main id="main">
<?php $content(); ?>
</main>

<footer class="site-footer">
  <div class="wrap footer-top">
    <div class="footer-brand">
      <img src="<?= e(asset('assets/img/logo.svg')) ?>" alt="" width="83" height="72" loading="lazy">
      <div>
        <h2><?= e($company) ?></h2>
        <?php $addr = array_filter([$addr1, $addr2], fn($l) => $l !== ''); if ($addr): ?><p><?= implode('<br>', array_map('e', $addr)) ?></p><?php endif; ?>
        <p><?= e(setting('call_label', 'Call or text Becky')) ?><br><a class="footer-phone" href="tel:<?= e($tel) ?>"><?= e($phone) ?></a></p>
        <?php $em = setting('contact_email'); if ($em !== ''): ?><p><a href="mailto:<?= e($em) ?>"><?= e($em) ?></a></p><?php endif; ?>
      </div>
    </div>
    <div>
      <h2>Pages</h2>
      <ul class="footer-links">
        <?php foreach (nav_items() as $n): ?><li><a href="<?= e($n['href']) ?>"><?= e($n['label']) ?></a></li><?php endforeach; ?>
      </ul>
    </div>
    <div>
      <?php if ($social): ?>
        <h2>Follow us</h2>
        <div class="social">
          <?php foreach ($social as $k => [$u, $label]): ?>
            <a href="<?= e($u) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e($company . ' on ' . $label) ?>"><img src="<?= e(url('assets/img/social/' . $k . '.png')) ?>" alt="" width="36" height="36" loading="lazy"></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <p style="margin-top:14px"><?= e(setting('service_area_summary', 'Serving Hudson, WI and the St. Croix Valley.')) ?></p>
    </div>
  </div>
  <div class="wrap footer-bottom">
    <div>&copy; <?= date('Y') ?> <?= e($company) ?>. All rights reserved.</div>
    <?php if (setting('show_credits', '1') === '1'): ?>
    <div class="credits">
      <span>Website by</span>
      <a href="https://stcroixtech.com" target="_blank" rel="noopener"><img src="<?= e(asset('assets/img/credit-stcroixtech.png')) ?>" alt="St Croix Tech Services" height="30" loading="lazy"></a>
      <a href="https://trudj.net" target="_blank" rel="noopener"><img src="<?= e(asset('assets/img/credit-trudjnet.png')) ?>" alt="TRUDJ.net" height="30" loading="lazy"></a>
    </div>
    <?php endif; ?>
  </div>
</footer>

<div class="callbar"><a href="tel:<?= e($tel) ?>">Call <?= e($phone) ?></a></div>
<script src="<?= e(asset('assets/js/site.js')) ?>" defer></script>
</body>
</html>
