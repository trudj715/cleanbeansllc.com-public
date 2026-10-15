<?php
/** Service request form. @var string $heading @var string $body @var array $page @var bool $band */
$errors = $_SESSION['form_errors'] ?? [];
$old = $_SESSION['form_old'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_old']);
$sent = isset($_GET['sent']);
$back = $page['slug'] === 'home' ? '' : $page['slug'];
$phone = setting('phone', '(715) 222-2444');
// Start the "What do you need?" list on the service this page is about.
$pageService = ['residential-cleaning' => 'House cleaning', 'move-in-move-out' => 'Move-in / move-out', 'rental-flips' => 'Rental flip / listing prep',
                'commercial-cleaning' => 'Commercial / office', 'post-construction' => 'Post-construction', 'specialty-cleaning' => 'Hoarding / biohazard'][$page['slug']] ?? 'House cleaning';
$chosen = $old['service_type'] ?? $pageService;
?>
<section class="section<?= $band ? ' band' : '' ?>" id="request"><div class="wrap split">
  <div class="prose">
    <h2><?= e($heading !== '' ? $heading : 'Request a free quote') ?></h2>
    <?= $body ?>
    <p><strong>For anything urgent, call <a href="tel:<?= e(phone_digits($phone)) ?>"><?= e($phone) ?></a>.</strong> Online requests get a reply as soon as Becky sees them.</p>
  </div>
  <div class="form-card">
    <?php if ($sent): ?>
      <div class="notice ok" role="status"><strong>Request received.</strong> Becky will get back to you soon. If it's urgent, call or text <?= e($phone) ?>.</div>
    <?php endif; ?>
    <?php if (!empty($errors['form'])): ?><div class="notice error" role="alert"><?= e($errors['form']) ?></div><?php endif; ?>
    <form method="post" action="<?= e(url('request')) ?>" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="_back" value="<?= e($back) ?>">
      <input type="hidden" name="_t" value="<?= time() ?>">
      <div class="hp" aria-hidden="true"><label>Leave this empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <div class="form-grid">
        <label class="field"><span>Your name</span>
          <input type="text" name="name" autocomplete="name" required value="<?= e($old['name'] ?? '') ?>">
          <?php if (!empty($errors['name'])): ?><div class="field-error"><?= e($errors['name']) ?></div><?php endif; ?></label>
        <label class="field"><span>Phone</span>
          <input type="tel" name="phone" autocomplete="tel" value="<?= e($old['phone'] ?? '') ?>">
          <?php if (!empty($errors['phone'])): ?><div class="field-error"><?= e($errors['phone']) ?></div><?php endif; ?></label>
        <label class="field"><span>Email (optional, so we can reply by email)</span>
          <input type="email" name="email" autocomplete="email" value="<?= e($old['email'] ?? '') ?>">
          <?php if (!empty($errors['email'])): ?><div class="field-error"><?= e($errors['email']) ?></div><?php endif; ?></label>
        <label class="field"><span>What do you need?</span>
          <select name="service_type">
            <?php foreach (SERVICE_TYPES as $t): ?><option<?= $chosen === $t ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
          </select></label>
        <label class="field full"><span>Town or address</span>
          <input type="text" name="location" placeholder="Where the cleaning is" value="<?= e($old['location'] ?? '') ?>"></label>
        <label class="field full"><span>Details</span>
          <textarea name="message" rows="4" placeholder="Size of the space, condition, preferred dates, anything we should know"><?= e($old['message'] ?? '') ?></textarea></label>
      </div>
      <div class="form-foot"><button class="btn btn-call" type="submit">Send request</button><span class="muted">Free quote, no obligation.</span></div>
    </form>
  </div>
</div></section>
