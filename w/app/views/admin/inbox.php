<?php /** @var array $rows @var string $status */
$tabs = ['open' => 'Open', 'new' => 'New', 'contacted' => 'Contacted', 'closed' => 'Closed', 'all' => 'All'];
$chan = ['web' => 'form', 'email' => 'email', 'sms' => 'text']; ?>
<h1>Inbox</h1>
<div class="btn-row" style="margin-bottom:14px">
  <?php foreach ($tabs as $k => $label): ?><a class="btn <?= $status === $k ? '' : 'ghost' ?> sm" href="<?= e(url('admin/inbox?status=' . $k)) ?>"><?= $label ?></a><?php endforeach; ?>
</div>
<?php if (!$rows): ?>
  <div class="panel">Nothing here.</div>
<?php else: ?>
<div class="table-wrap"><table class="t">
  <tr><th>Last activity</th><th>From</th><th>About</th><th>Latest</th><th>Answered by</th><th>Status</th></tr>
  <?php foreach ($rows as $q): $waiting = ($q['last_dir'] ?? '') === 'in'; ?>
    <tr class="<?= $q['status'] === 'new' || $waiting ? 'unread' : '' ?>">
      <td><?= e(date('M j, g:ia', strtotime($q['last_activity_at'] ?: $q['created_at']))) ?></td>
      <td><a href="<?= e(url('admin/inbox/' . $q['id'])) ?>"><?= e($q['name']) ?></a><br><span class="small"><?= $q['phone'] !== '' ? '<a href="tel:' . e(phone_digits($q['phone'])) . '">' . e($q['phone']) . '</a>' : e($q['email']) ?></span></td>
      <td><?= e($q['service_type'] ?: ($q['kind'] === 'sms' ? 'Text message' : '')) ?></td>
      <td class="small"><?= $waiting ? '<strong>Waiting for reply</strong> · ' : '' ?><?= e((string)($chan[$q['last_channel'] ?? ''] ?? '')) ?> · <?= (int)$q['msg_count'] ?> message<?= (int)$q['msg_count'] === 1 ? '' : 's' ?></td>
      <td class="small"><?= e((string)($q['last_reply_by'] ?? '')) ?: '<span class="muted">not yet</span>' ?></td>
      <td><span class="tag <?= e($q['status']) ?>"><?= e(ucfirst($q['status'])) ?></span></td>
    </tr>
  <?php endforeach; ?>
</table></div>
<?php endif; ?>
