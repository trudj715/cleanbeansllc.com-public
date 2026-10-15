<?php /** @var array $rows @var string $status */
$tabs = ['open' => 'Open', 'new' => 'New', 'contacted' => 'Contacted', 'closed' => 'Closed', 'all' => 'All']; ?>
<h1>Inbox</h1>
<div class="btn-row" style="margin-bottom:14px">
  <?php foreach ($tabs as $k => $label): ?><a class="btn <?= $status === $k ? '' : 'ghost' ?> sm" href="<?= e(url('admin/inbox?status=' . $k)) ?>"><?= $label ?></a><?php endforeach; ?>
</div>
<?php if (!$rows): ?>
  <div class="panel">Nothing here.</div>
<?php else: ?>
<div class="table-wrap"><table class="t">
  <tr><th>Received</th><th>From</th><th>About</th><th>Message</th><th>Status</th></tr>
  <?php foreach ($rows as $q): ?>
    <tr class="<?= $q['status'] === 'new' ? 'unread' : '' ?>">
      <td><?= e(date('M j, g:ia', strtotime($q['created_at']))) ?></td>
      <td><a href="<?= e(url('admin/inbox/' . $q['id'])) ?>"><?= e($q['name']) ?></a><br><span class="small"><?= $q['phone'] !== '' ? '<a href="tel:' . e(phone_digits($q['phone'])) . '">' . e($q['phone']) . '</a>' : e($q['email']) ?></span></td>
      <td><?= e($q['service_type']) ?></td>
      <td class="small"><?= e(mb_strimwidth((string)$q['message'], 0, 90, '…')) ?></td>
      <td><span class="tag <?= e($q['status']) ?>"><?= e(ucfirst($q['status'])) ?></span></td>
    </tr>
  <?php endforeach; ?>
</table></div>
<?php endif; ?>
