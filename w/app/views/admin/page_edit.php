<?php
/** @var array $page @var array $sections @var ?array $hero */
$isHome = $page['slug'] === 'home';
$help = [
    'text'         => 'Headline and formatted text.',
    'image_text'   => 'A photo beside text. Choose which side the photo goes on.',
    'services'     => 'Photo tiles linking to the service pages (uses each page\'s banner photo and intro text).',
    'gallery'      => 'A grid of photos visitors can tap to enlarge.',
    'cta'          => 'Dark banner with the big phone number.',
    'map'          => 'Map with a pin and a shaded service-radius circle, plus a list of towns.',
    'faq'          => 'Questions that expand to show the answer.',
    'request_form' => 'The "Request a free quote" form. Messages go to the Inbox.',
];
$photoPicker = function (string $name, ?int $mediaId) {
    $m = media_get($mediaId); ?>
    <div class="hero-pick" data-picker="single">
      <img src="<?= e($m ? media_url($m, true) : '') ?>" alt="" <?= $m ? '' : 'hidden' ?>>
      <input type="hidden" name="<?= e($name) ?>" value="<?= (int)$mediaId ?: '' ?>">
      <div class="btn-row"><button type="button" class="btn ghost sm" data-pick>Choose photo</button><button type="button" class="btn ghost sm" data-clear <?= $m ? '' : 'hidden' ?>>Remove</button></div>
    </div>
<?php };
?>
<div class="head-row">
  <h1><?= e($page['title']) ?></h1>
  <a class="btn ghost" href="<?= e($isHome ? url() : url($page['slug'])) ?>" target="_blank" rel="noopener">View page</a>
</div>

<form method="post" class="panel">
  <?= csrf_field() ?><input type="hidden" name="action" value="save_page">
  <h2>Page settings</h2>
  <div class="grid2">
    <label class="f"><span>Page title</span><input type="text" name="title" value="<?= e($page['title']) ?>" required></label>
    <label class="f"><span>Web address</span><small><?= $isHome ? 'The home page is always /' : e(abs_url('')) ?></small>
      <input type="text" name="slug" value="<?= e($page['slug']) ?>" <?= $isHome ? 'readonly' : '' ?> pattern="[a-z0-9\-]+"></label>
    <label class="f"><span>Menu label</span><input type="text" name="nav_label" value="<?= e($page['nav_label']) ?>"></label>
    <div>
      <label class="check"><input type="checkbox" name="show_in_nav" <?= (int)$page['show_in_nav'] ? 'checked' : '' ?>> Show in the menu</label>
      <?php if (!$isHome): ?><label class="check"><input type="checkbox" name="is_published" <?= (int)$page['is_published'] ? 'checked' : '' ?>> Published (visible to the public)</label><?php endif; ?>
    </div>
  </div>
  <h2 style="margin-top:8px">Top banner</h2>
  <div class="grid2">
    <div>
      <label class="f"><span>Headline</span><input type="text" name="hero_heading" value="<?= e($page['hero_heading']) ?>"></label>
      <label class="f"><span>Intro text</span><textarea name="hero_text" rows="3"><?= e((string)$page['hero_text']) ?></textarea></label>
    </div>
    <div><label class="f"><span>Banner photo</span></label><?php $photoPicker('hero_media_id', $page['hero_media_id'] ? (int)$page['hero_media_id'] : null); ?></div>
  </div>
  <h2 style="margin-top:8px">Search engines</h2>
  <label class="f"><span>Search title</span><small>Shown as the clickable title in Google. Leave blank to use the page title.</small><input type="text" name="meta_title" value="<?= e($page['meta_title']) ?>" maxlength="200"></label>
  <label class="f"><span>Search description</span><small>One or two sentences, about 150 characters. Also used on service tiles.</small><textarea name="meta_description" rows="2" maxlength="300"><?= e($page['meta_description']) ?></textarea></label>
  <button class="btn primary" type="submit">Save page settings</button>
</form>

<h2>Sections</h2>
<p class="muted">Sections appear on the page from top to bottom in this order.</p>

<?php foreach ($sections as $k => $s): $d = $s['data']; ?>
<div class="section-card<?= (int)$s['is_enabled'] ? '' : ' off' ?>" id="s<?= (int)$s['id'] ?>">
  <header>
    <h3><?= e(SECTION_TYPES[$s['type']] ?? $s['type']) ?><?= $s['heading'] !== '' ? ': ' . e($s['heading']) : '' ?><?= (int)$s['is_enabled'] ? '' : ' <span class="tag draft">Hidden</span>' ?></h3>
    <form method="post" class="btn-row"><?= csrf_field() ?><input type="hidden" name="section_id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="action" value="move_section">
      <button class="btn ghost sm" name="dir" value="up" <?= $k === 0 ? 'disabled' : '' ?> aria-label="Move up">Move up</button>
      <button class="btn ghost sm" name="dir" value="down" <?= $k === count($sections) - 1 ? 'disabled' : '' ?> aria-label="Move down">Move down</button>
    </form>
    <form method="post" data-confirm="Remove this section from the page?"><?= csrf_field() ?><input type="hidden" name="section_id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="action" value="delete_section"><button class="btn danger sm">Remove</button></form>
  </header>
  <form method="post" class="body">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_section"><input type="hidden" name="section_id" value="<?= (int)$s['id'] ?>">
    <p class="type-help"><?= e($help[$s['type']] ?? '') ?></p>
    <label class="f"><span>Heading</span><input type="text" name="heading" value="<?= e($s['heading']) ?>"></label>

    <?php if ($s['type'] === 'image_text'): ?>
      <div class="grid2">
        <div><label class="f"><span>Photo</span></label><?php $photoPicker('media_id', $s['media_id'] ? (int)$s['media_id'] : null); ?></div>
        <label class="f"><span>Photo side</span><select name="align"><option value="left">Left</option><option value="right"<?= ($d['align'] ?? '') === 'right' ? ' selected' : '' ?>>Right</option></select></label>
      </div>
    <?php endif; ?>

    <?php if ($s['type'] === 'gallery'): $gm = media_many($d['media_ids'] ?? []); ?>
      <label class="f"><span>Photos</span></label>
      <div data-picker="multi">
        <input type="hidden" name="media_ids" value="<?= e(implode(',', $d['media_ids'] ?? [])) ?>">
        <div class="chosen"><?php foreach ($d['media_ids'] ?? [] as $mid): if (empty($gm[$mid])) continue; ?>
          <div class="ch" data-id="<?= (int)$mid ?>"><img src="<?= e(media_url($gm[$mid], true)) ?>" alt=""><div class="ctl"><button type="button" data-left aria-label="Move left">‹</button><button type="button" data-right aria-label="Move right">›</button><button type="button" data-remove aria-label="Remove">✕</button></div></div>
        <?php endforeach; ?></div>
        <button type="button" class="btn ghost sm" data-pick>Add photos</button>
      </div>
    <?php endif; ?>

    <?php if ($s['type'] === 'map'): ?>
      <div class="grid2">
        <label class="f"><span>Address shown</span><input type="text" name="address" value="<?= e($d['address'] ?? '') ?>" data-geo-address></label>
        <label class="f"><span>Service radius (miles)</span><input type="number" name="radius_miles" min="0" max="1000" step="1" value="<?= e($d['radius_miles'] ?? 150) ?>"></label>
        <label class="f"><span>Latitude</span><input type="text" name="lat" value="<?= e($d['lat'] ?? '') ?>" data-geo-lat></label>
        <label class="f"><span>Longitude</span><input type="text" name="lng" value="<?= e($d['lng'] ?? '') ?>" data-geo-lng></label>
      </div>
      <p class="btn-row"><button type="button" class="btn ghost sm" data-geocode>Find map position from the address</button><span class="small muted" data-geo-msg></span></p>
      <label class="f"><span>Starting zoom (3 = very wide, 12 = streets)</span><input type="number" name="zoom" min="3" max="16" value="<?= (int)($d['zoom'] ?? 7) ?>" style="max-width:120px"></label>
      <label class="f"><span>Towns and areas served</span><small>One per line.</small><textarea name="areas" rows="6"><?= e(implode("\n", $d['areas'] ?? [])) ?></textarea></label>
    <?php endif; ?>

    <?php if ($s['type'] === 'faq'): ?>
      <div data-faq>
        <?php foreach (($d['items'] ?? []) ?: [['q' => '', 'a' => '']] as $item): ?>
          <div class="faq-row">
            <label class="f"><span>Question</span><input type="text" name="faq_q[]" value="<?= e($item['q'] ?? '') ?>"></label>
            <label class="f"><span>Answer</span><textarea name="faq_a[]" rows="3"><?= e($item['a'] ?? '') ?></textarea></label>
            <button type="button" class="btn danger sm" data-faq-remove>Remove question</button>
          </div>
        <?php endforeach; ?>
        <button type="button" class="btn ghost sm" data-faq-add>+ Add a question</button>
      </div>
    <?php endif; ?>

    <label class="f"><span><?= $s['type'] === 'cta' ? 'Text beside the phone number' : 'Text' ?></span></label>
    <textarea name="body_html" class="rte-input" rows="8"><?= e((string)$s['body_html']) ?></textarea>

    <div class="btn-row" style="margin-top:12px">
      <label class="check" style="margin:0"><input type="checkbox" name="is_enabled" <?= (int)$s['is_enabled'] ? 'checked' : '' ?>> Show this section</label>
      <?php if ($s['type'] !== 'cta'): ?><label class="check" style="margin:0"><input type="checkbox" name="band" <?= !empty($d['band']) ? 'checked' : '' ?>> White background</label><?php endif; ?>
      <button class="btn primary" type="submit" style="margin-left:auto">Save section</button>
    </div>
  </form>
</div>
<?php endforeach; ?>

<form method="post" class="panel soft">
  <?= csrf_field() ?><input type="hidden" name="action" value="add_section">
  <h2>Add a section</h2>
  <div class="btn-row">
    <select name="type" style="max-width:320px"><?php foreach (SECTION_TYPES as $t => $label): ?><option value="<?= e($t) ?>"><?= e($label) ?></option><?php endforeach; ?></select>
    <button class="btn" type="submit">Add section</button>
  </div>
</form>

<?php if (!$isHome): ?>
<form method="post" data-confirm="Delete this whole page and all its sections? This cannot be undone." style="margin-top:28px">
  <?= csrf_field() ?><input type="hidden" name="action" value="delete_page"><button class="btn danger">Delete this page</button>
</form>
<?php endif; ?>
