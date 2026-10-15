<?php /** @var array $rows @var array $me */
$permBoxes = function (int $current, bool $disabled = false) use ($me) {
    foreach (PERM_LABELS as $bit => $label) {
        $allowed = is_hostmaster($me) || ((int)$me['permissions'] & $bit);
        echo '<label class="check" style="display:inline-flex;margin-right:12px"><input type="checkbox" name="perm[]" value="' . $bit . '"'
            . (($current & $bit) ? ' checked' : '') . (($disabled || !$allowed) ? ' disabled' : '') . '> ' . e($label) . '</label>';
    }
}; ?>
<h1>Admin users</h1>
<?php foreach ($rows as $u): $prot = (int)$u['is_protected'] === 1; $self = (int)$u['id'] === (int)$me['id']; ?>
<div class="panel">
  <div class="head-row" style="margin-bottom:8px">
    <h2 style="margin:0"><?= e($u['email']) ?><?= $prot ? ' <span class="tag">Hostmaster</span>' : '' ?><?= $self ? ' <span class="tag draft">You</span>' : '' ?><?= (int)$u['is_enabled'] ? '' : ' <span class="tag sold">Disabled</span>' ?></h2>
    <span class="small muted">Two-step sign-in: <?= (int)$u['totp_enabled'] ? 'on' : 'off' ?>. Last sign-in: <?= $u['last_login_at'] ? e(date('M j, Y g:ia', strtotime($u['last_login_at']))) : 'never' ?></span>
  </div>
  <?php if ($prot && !is_hostmaster($me)): ?>
    <p class="muted">The permanent hostmaster account (St Croix Tech Services) has full access and can't be changed here.</p>
  <?php else: ?>
  <form method="post" action="<?= e(url('admin/users/' . $u['id'])) ?>">
    <?= csrf_field() ?><input type="hidden" name="action" value="update">
    <label class="f" style="max-width:360px"><span>Name</span><input type="text" name="name" value="<?= e($u['name']) ?>"></label>
    <?php if (!$prot): ?>
      <div><strong>Can manage:</strong><br><?php $permBoxes((int)$u['permissions']); ?></div>
      <?php if (!$self): ?><label class="check"><input type="checkbox" name="is_enabled" <?= (int)$u['is_enabled'] ? 'checked' : '' ?>> Account enabled</label><?php endif; ?>
    <?php else: ?><p class="small muted">Full access (permanent).</p><?php endif; ?>
    <button class="btn sm" type="submit">Save</button>
  </form>
  <div class="btn-row" style="margin-top:10px">
    <form method="post" action="<?= e(url('admin/users/' . $u['id'])) ?>" class="btn-row"><?= csrf_field() ?><input type="hidden" name="action" value="password">
      <input type="password" name="password" placeholder="New password (12+ characters)" autocomplete="new-password" minlength="12" style="max-width:260px"><button class="btn ghost sm" type="submit">Set password</button></form>
    <?php if ((int)$u['totp_enabled']): ?><form method="post" action="<?= e(url('admin/users/' . $u['id'])) ?>" data-confirm="Turn off two-step sign-in for this account? Use this if they lost their phone and recovery codes."><?= csrf_field() ?><input type="hidden" name="action" value="reset_mfa"><button class="btn ghost sm">Reset two-step sign-in</button></form><?php endif; ?>
    <?php if (!$prot && !$self): ?><form method="post" action="<?= e(url('admin/users/' . $u['id'])) ?>" data-confirm="Delete this account?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn danger sm">Delete</button></form><?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<form method="post" class="panel soft" action="<?= e(url('admin/users')) ?>">
  <?= csrf_field() ?><input type="hidden" name="action" value="create">
  <h2>Add an admin user</h2>
  <div class="grid3">
    <label class="f"><span>Email</span><input type="email" name="email" required></label>
    <label class="f"><span>Name</span><input type="text" name="name"></label>
    <label class="f"><span>Starting password</span><input type="password" name="password" minlength="12" required autocomplete="new-password"></label>
  </div>
  <div><strong>Can manage:</strong><br><?php $permBoxes(PERM_INBOX | PERM_MEDIA); ?></div>
  <button class="btn primary" type="submit">Create account</button>
</form>
