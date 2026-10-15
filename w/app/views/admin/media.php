<?php /** @var array $rows @var string $cat */ ?>
<div class="head-row"><h1>Photos</h1>
  <form method="get" class="btn-row"><select name="category" onchange="this.form.submit()">
    <option value="">All photos</option>
    <?php foreach (['general' => 'General', 'site' => 'Website pages'] as $k => $v): ?><option value="<?= $k ?>"<?= $cat === $k ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
  </select></form>
</div>
<form method="post" enctype="multipart/form-data" class="panel">
  <?= csrf_field() ?>
  <div class="dropzone" data-dropzone>
    <p><strong>Drop photos here</strong> or choose them from your phone or computer.</p>
    <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
  </div>
  <div class="btn-row" style="margin-top:10px">
    <label>Put in <select name="category"><option value="general">General</option><option value="site">Website pages</option></select></label>
    <button class="btn primary" type="submit">Upload</button>
    <span class="small muted">Large phone photos are resized automatically. Location data is removed.</span>
  </div>
</form>
<?php if (!$rows): ?>
  <div class="panel">No photos yet.</div>
<?php else: ?>
  <div class="media-grid">
    <?php foreach ($rows as $m): ?>
      <a href="<?= e(url('admin/media/' . $m['id'])) ?>"><img src="<?= e(media_url($m, true)) ?>" alt="" loading="lazy"><div class="cap"><?= e($m['alt_text'] ?: $m['original_name']) ?></div></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
