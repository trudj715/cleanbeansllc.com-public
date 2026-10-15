<?php /** @var array $q @var array $thread @var string $draft @var bool $canText @var bool $smsOn */
$chanLabel = ['web' => 'Website form', 'email' => 'Email', 'sms' => 'Text', 'admin' => 'Admin', 'alert' => 'Alert'];
$hasEmail = filter_var((string)$q['email'], FILTER_VALIDATE_EMAIL) !== false;
$defaultChannel = $canText && (!$hasEmail || (end($thread)['channel'] ?? '') === 'sms') ? 'sms' : ($hasEmail ? 'email' : 'note');
?>
<div class="head-row"><h1><?= e($q['name']) ?></h1><a href="<?= e(url('admin/inbox')) ?>">Back to inbox</a></div>
<div class="grid2 convo-grid" style="align-items:start">
  <div>
    <div class="panel">
      <table class="t">
        <tr><th>Started</th><td><?= e(date('l, M j, Y g:ia', strtotime($q['created_at']))) ?> · <?= $q['kind'] === 'sms' ? 'by text' : 'website form' ?></td></tr>
        <?php if ($q['phone'] !== ''): ?><tr><th>Phone</th><td><a href="tel:<?= e(phone_digits($q['phone'])) ?>"><?= e($q['phone']) ?></a>
          <span class="small muted"> · texts: <?= !empty($q['sms_opted_out']) ? '<strong>opted out (STOP)</strong>' : ($canText ? 'allowed' : (!empty($q['sms_opt_in']) ? 'allowed once texts are switched on' : 'no consent yet')) ?></span></td></tr><?php endif; ?>
        <?php if ($q['email'] !== ''): ?><tr><th>Email</th><td><a href="mailto:<?= e($q['email']) ?>"><?= e($q['email']) ?></a></td></tr><?php endif; ?>
        <?php if ($q['service_type'] !== ''): ?><tr><th>Needs</th><td><?= e($q['service_type']) ?></td></tr><?php endif; ?>
        <?php if ($q['location'] !== ''): ?><tr><th>Location</th><td><?= e($q['location']) ?> <a class="small" href="https://www.google.com/maps/search/?api=1&amp;query=<?= e(urlencode($q['location'])) ?>" target="_blank" rel="noopener">map</a></td></tr><?php endif; ?>
      </table>
    </div>

    <h2 style="margin:18px 0 8px">Conversation</h2>
    <div class="thread">
      <?php foreach ($thread as $m): $dir = $m['direction']; ?>
        <?php if ($dir === 'system'): ?>
          <div class="msg sys"><span><?= e(date('M j, g:ia', strtotime($m['created_at']))) ?> · <?= e((string)$m['body']) ?></span></div>
        <?php else:
          $who = $dir === 'in' ? $q['name'] : ($m['user_name'] ?: ($m['author'] ?: 'Staff'));
          if ($dir === 'out' && !$m['user_id'] && $m['author']) $who = $m['author']; ?>
          <div class="msg <?= e($dir) ?>">
            <div class="msg-meta"><strong><?= e($who) ?></strong>
              <?= $dir === 'note' ? ' · internal note' : ' · ' . e($chanLabel[$m['channel']] ?? $m['channel']) ?>
              · <?= e(date('M j, g:ia', strtotime($m['created_at']))) ?>
              <?= $m['status'] === 'failed' ? ' · <span class="tag sold">not sent</span>' : '' ?></div>
            <div class="msg-body"><?= trim((string)$m['body']) !== '' ? nl2br(e((string)$m['body'])) : '<span class="muted">(no message)</span>' ?></div>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>

    <form method="post" class="panel reply-box" id="reply">
      <?= csrf_field() ?><input type="hidden" name="action" value="reply">
      <div class="btn-row" role="radiogroup" aria-label="Send as" style="margin-bottom:8px">
        <label class="check"><input type="radio" name="channel" value="email"<?= $defaultChannel === 'email' ? ' checked' : '' ?><?= $hasEmail ? '' : ' disabled' ?>> Email<?= $hasEmail ? '' : ' (no address)' ?></label>
        <label class="check"><input type="radio" name="channel" value="sms"<?= $defaultChannel === 'sms' ? ' checked' : '' ?><?= $canText ? '' : ' disabled' ?>> Text<?= $canText ? '' : (!empty($q['sms_opted_out']) ? ' (opted out)' : ($q['phone'] === '' ? ' (no phone)' : ($smsOn ? ' (no consent)' : ' (texts off)'))) ?></label>
        <label class="check"><input type="radio" name="channel" value="note"<?= $defaultChannel === 'note' ? ' checked' : '' ?>> Internal note</label>
      </div>
      <textarea name="text" rows="5" placeholder="Write your reply…" required><?= e($draft) ?></textarea>
      <div class="btn-row" style="margin-top:8px"><button class="btn primary" type="submit">Send</button>
        <span class="small muted">Sent from <?= e(setting('company_name', 'Clean Beans LLC')) ?> and saved here with your name. Customer replies come back to this conversation.</span></div>
    </form>
  </div>
  <div>
    <form method="post" class="panel">
      <?= csrf_field() ?>
      <label class="f"><span>Status</span><select name="status">
        <?php foreach (['new' => 'New', 'contacted' => 'Contacted', 'closed' => 'Closed'] as $k => $v): ?><option value="<?= $k ?>"<?= $q['status'] === $k ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></label>
      <label class="f"><span>Private notes</span><small>Only admins see these.</small><textarea name="admin_notes" rows="6"><?= e((string)$q['admin_notes']) ?></textarea></label>
      <?php if ($q['phone'] !== '' && empty($q['sms_opt_in']) && empty($q['sms_opted_out'])): ?>
        <label class="check"><input type="checkbox" name="sms_opt_in_record" value="1"> Customer agreed to texts (by phone or in person)</label>
      <?php endif; ?>
      <button class="btn primary" type="submit">Save</button>
    </form>
    <form method="post" data-confirm="Delete this conversation and all its messages permanently?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn danger sm">Delete conversation</button></form>
  </div>
</div>
