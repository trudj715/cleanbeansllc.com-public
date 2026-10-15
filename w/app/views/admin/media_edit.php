<?php /** @var array $m @var int $uses */ ?>
<div class="head-row"><h1>Photo details</h1><a href="<?= e(url('admin/media')) ?>">Back to photos</a></div>
<div class="grid2">
  <div class="panel"><img src="<?= e(media_url($m)) ?>" alt="<?= e($m['alt_text']) ?>">
    <p class="small muted"><?= (int)$m['width'] ?> × <?= (int)$m['height'] ?> px. Uploaded <?= e(date('M j, Y', strtotime($m['created_at']))) ?>. Used in <?= $uses ?> place<?= $uses === 1 ? '' : 's' ?>.</p>
  </div>
  <div>
    <form method="post" class="panel">
      <?= csrf_field() ?>
      <label class="f"><span>Description</span><small>Say what the photo shows, e.g. "Kitchen after a move-out deep clean". Used by search engines and screen readers.</small><input type="text" name="alt_text" value="<?= e($m['alt_text']) ?>" maxlength="255"></label>
      <label class="f"><span>Caption (optional)</span><input type="text" name="caption" value="<?= e($m['caption']) ?>" maxlength="255"></label>
      <label class="f"><span>Folder</span><select name="category"><?php foreach (['general' => 'General', 'site' => 'Website pages'] as $k => $v): ?><option value="<?= $k ?>"<?= $m['category'] === $k ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></label>
      <button class="btn primary" type="submit">Save</button>
    </form>
    <form method="post" class="panel" data-confirm="Delete this photo permanently?">
      <?= csrf_field() ?><input type="hidden" name="action" value="delete">
      <p class="small muted"><?= $uses ? 'This photo is in use, so it can\'t be deleted until it is removed from those pages.' : 'Not used anywhere. Safe to delete.' ?></p>
      <button class="btn danger" <?= $uses ? 'disabled' : '' ?>>Delete photo</button>
    </form>
  </div>
</div>
