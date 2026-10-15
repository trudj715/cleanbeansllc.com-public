<?php /** @var array $q */ ?>
<div class="head-row"><h1>Quote request from <?= e($q['name']) ?></h1><a href="<?= e(url('admin/inbox')) ?>">Back to inbox</a></div>
<div class="grid2" style="align-items:start">
  <div class="panel">
    <table class="t">
      <tr><th>Received</th><td><?= e(date('l, M j, Y g:ia', strtotime($q['created_at']))) ?></td></tr>
      <tr><th>Name</th><td><?= e($q['name']) ?></td></tr>
      <?php if ($q['phone'] !== ''): ?><tr><th>Phone</th><td><a href="tel:<?= e(phone_digits($q['phone'])) ?>"><?= e($q['phone']) ?></a></td></tr><?php endif; ?>
      <?php if ($q['email'] !== ''): ?><tr><th>Email</th><td><a href="mailto:<?= e($q['email']) ?>"><?= e($q['email']) ?></a></td></tr><?php endif; ?>
      <?php if ($q['service_type'] !== ''): ?><tr><th>Needs</th><td><?= e($q['service_type']) ?></td></tr><?php endif; ?>
      <?php if ($q['location'] !== ''): ?><tr><th>Location</th><td><?= e($q['location']) ?> <a class="small" href="https://www.google.com/maps/search/?api=1&amp;query=<?= e(urlencode($q['location'])) ?>" target="_blank" rel="noopener">map</a></td></tr><?php endif; ?>
    </table>
    <h2 style="margin-top:16px">Message</h2>
    <p style="white-space:pre-wrap"><?= e((string)$q['message']) ?: '<span class="muted">(no message)</span>' ?></p>
  </div>
  <div>
    <form method="post" class="panel">
      <?= csrf_field() ?>
      <label class="f"><span>Status</span><select name="status">
        <?php foreach (['new' => 'New', 'contacted' => 'Contacted', 'closed' => 'Closed'] as $k => $v): ?><option value="<?= $k ?>"<?= $q['status'] === $k ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></label>
      <label class="f"><span>Private notes</span><small>Only admins see these.</small><textarea name="admin_notes" rows="6"><?= e((string)$q['admin_notes']) ?></textarea></label>
      <button class="btn primary" type="submit">Save</button>
    </form>
    <form method="post" data-confirm="Delete this message permanently?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn danger sm">Delete message</button></form>
  </div>
</div>
