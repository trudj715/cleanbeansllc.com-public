<?php /** @var array $fields */ ?>
<h1>Settings</h1>
<form method="post">
  <?= csrf_field() ?>
  <?php foreach ($fields as $group => $items): ?>
    <div class="panel">
      <h2><?= e($group) ?></h2>
      <?php if (str_starts_with($group, 'Domain email')): ?><p class="small muted">Mail to hostmaster@cleanbeansllc.com and to any other @cleanbeansllc.com address is re-sent from email@cleanbeansllc.com, with Reply-To set to the original sender. Spam is dropped. Changes take effect on the next message.</p><?php endif; ?>
      <div class="grid2">
        <?php foreach ($items as $key => $label): $val = setting($key, SETTINGS_DEFAULTS[$key] ?? ''); $long = in_array($key, ['service_area_summary'], true); $wide = str_starts_with($key, 'mail_fwd_'); ?>
          <label class="f"<?= ($long || $wide) ? ' style="grid-column:1/-1"' : '' ?>><span><?= e($label) ?></span>
            <?php if ($long): ?><textarea name="<?= e($key) ?>" rows="2"><?= e($val) ?></textarea>
            <?php else: ?><input type="text" name="<?= e($key) ?>" value="<?= e($val) ?>"><?php endif; ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <button class="btn primary" type="submit">Save settings</button>
</form>
