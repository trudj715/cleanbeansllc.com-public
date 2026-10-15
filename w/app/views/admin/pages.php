<?php /** @var array $pages */ ?>
<div class="head-row"><h1>Pages</h1><a class="btn primary" href="<?= e(url('admin/pages/new')) ?>">+ New page</a></div>
<form method="post">
  <?= csrf_field() ?><input type="hidden" name="action" value="reorder">
  <div class="table-wrap"><table class="t">
    <tr><th>Page</th><th>Web address</th><th>In menu</th><th>Status</th><th style="width:110px">Menu order</th></tr>
    <?php foreach ($pages as $p): ?>
      <tr>
        <td><a href="<?= e(url('admin/pages/' . $p['id'])) ?>"><strong><?= e($p['title']) ?></strong></a><br><span class="small muted"><?= (int)$p['n'] ?> section<?= (int)$p['n'] === 1 ? '' : 's' ?></span></td>
        <td><a href="<?= e($p['slug'] === 'home' ? url() : url($p['slug'])) ?>" target="_blank" rel="noopener"><?= e($p['slug'] === 'home' ? '/' : '/' . $p['slug']) ?></a></td>
        <td><?= (int)$p['show_in_nav'] ? 'Yes' : 'No' ?></td>
        <td><?= (int)$p['is_published'] ? '<span class="tag">Published</span>' : '<span class="tag draft">Draft</span>' ?></td>
        <td><input type="number" name="order[<?= (int)$p['id'] ?>]" value="<?= (int)$p['nav_order'] ?>" style="width:90px"></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <p class="small muted">Lower numbers appear first in the menu. Pages with "Show in menu" off stay out of the menu but are still reachable by their link.</p>
  <button class="btn" type="submit">Save menu order</button>
</form>
