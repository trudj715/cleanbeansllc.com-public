<?php /** @var array $fields */ ?>
<h1>Settings</h1>
<form method="post">
  <?= csrf_field() ?>
  <?php foreach ($fields as $group => $items): ?>
    <div class="panel">
      <h2><?= e($group) ?></h2>
      <div class="grid2">
        <?php foreach ($items as $key => $label): $val = setting($key, $key === 'notify_from' ? 'hostmaster@cleanbeansllc.com' : ''); $long = in_array($key, ['service_area_summary'], true); ?>
          <label class="f"<?= $long ? ' style="grid-column:1/-1"' : '' ?>><span><?= e($label) ?></span>
            <?php if ($long): ?><textarea name="<?= e($key) ?>" rows="2"><?= e($val) ?></textarea>
            <?php else: ?><input type="text" name="<?= e($key) ?>" value="<?= e($val) ?>"><?php endif; ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <button class="btn primary" type="submit">Save settings</button>
</form>
