<?php /** @var array $u @var array $stats @var array $recent */ ?>
<div class="head-row">
  <h1>Hello<?= $u['name'] !== '' ? ', ' . e(explode(' ', $u['name'])[0]) : '' ?></h1>
  <a class="btn ghost" href="<?= e(url()) ?>" target="_blank" rel="noopener">View the website</a>
</div>

<div class="stats">
  <?php if (can(PERM_INBOX)): ?><a class="stat<?= $stats['new_inquiries'] ? ' hot' : '' ?>" href="<?= e(url('admin/inbox?status=new')) ?>"><b><?= $stats['new_inquiries'] ?></b>new message<?= $stats['new_inquiries'] === 1 ? '' : 's' ?></a><?php endif; ?>
  <?php if (can(PERM_PAGES)): ?><a class="stat" href="<?= e(url('admin/pages')) ?>"><b><?= $stats['pages'] ?></b>published page<?= $stats['pages'] === 1 ? '' : 's' ?></a><?php endif; ?>
  <?php if (can(PERM_MEDIA)): ?><a class="stat" href="<?= e(url('admin/media')) ?>"><b><?= $stats['photos'] ?></b>photo<?= $stats['photos'] === 1 ? '' : 's' ?></a><?php endif; ?>
</div>

<?php if ((int)$u['totp_enabled'] !== 1): ?>
  <div class="panel soft"><strong>Protect this account:</strong> turn on two-step sign-in so a stolen password alone can't get in. <a href="<?= e(url('admin/account#mfa')) ?>">Set it up</a> (takes 2 minutes).</div>
<?php endif; ?>

<?php if ($recent): ?>
<div class="panel">
  <div class="head-row"><h2 style="margin:0">Latest messages</h2><a href="<?= e(url('admin/inbox')) ?>">Open inbox</a></div>
  <div class="table-wrap"><table class="t">
    <tr><th>Received</th><th>From</th><th>About</th><th>Status</th></tr>
    <?php foreach ($recent as $q): ?>
      <tr class="<?= $q['status'] === 'new' ? 'unread' : '' ?>">
        <td><?= e(date('M j, g:ia', strtotime($q['created_at']))) ?></td>
        <td><a href="<?= e(url('admin/inbox/' . $q['id'])) ?>"><?= e($q['name']) ?></a><br><span class="small muted"><?= e($q['phone'] ?: $q['email']) ?></span></td>
        <td><?= e($q['service_type'] ?: 'Request') ?></td>
        <td><span class="tag <?= e($q['status']) ?>"><?= e(ucfirst($q['status'])) ?></span></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php endif; ?>

<div class="panel">
  <h2>Quick help</h2>
  <ul>
    <?php if (can(PERM_MEDIA)): ?><li><strong>Adding job photos:</strong> Photos, then drag photos in. Add them to a page's gallery in Pages.</li><?php endif; ?>
    <?php if (can(PERM_PAGES)): ?><li><strong>Changing page text or photos:</strong> Pages, then pick a page and edit its sections.</li><?php endif; ?>
    <?php if (can(PERM_INBOX)): ?><li><strong>Quote requests:</strong> Inbox. Mark them Contacted or Closed as you handle them.</li><?php endif; ?>
  </ul>
</div>
