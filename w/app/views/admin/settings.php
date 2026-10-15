<?php /** @var array $fields */ ?>
<h1>Settings</h1>
<form method="post">
  <?= csrf_field() ?>
  <?php foreach ($fields as $group => $items): ?>
    <div class="panel">
      <h2><?= e($group) ?></h2>
      <?php if ($group === 'Notifications'): ?><p class="small muted">The primary recipient gets every alert. Each admin user can also turn on their own email or text alerts under Users. Text alerts work once text messages are switched on.</p><?php endif; ?>
      <?php if (str_starts_with($group, 'Text messages')): ?><p class="small muted" id="sms">Status: <strong><?= sms_enabled() ? 'sending' : 'not sending' ?></strong>.
        Point the provider's inbound message webhook at <code><?= e(sms_webhook_url()) ?></code> (HTTP POST). Test mode writes texts to err/sms.log instead of sending.</p><?php endif; ?>
      <?php if (str_starts_with($group, 'Domain email')): ?><p class="small muted">Mail to hostmaster@cleanbeansllc.com and to any other @cleanbeansllc.com address is re-sent from email@cleanbeansllc.com, with Reply-To set to the original sender. Spam is dropped. Changes take effect on the next message.</p><?php endif; ?>
      <div class="grid2">
        <?php foreach ($items as $key => $label): $val = setting($key, SETTINGS_DEFAULTS[$key] ?? ''); $long = in_array($key, ['service_area_summary'], true); $wide = str_starts_with($key, 'mail_fwd_') || in_array($key, ['alert_reply_mode', 'notify_sms_relay'], true);
          $secret = in_array($key, SETTINGS_SECRETS, true); $choices = SETTINGS_CHOICES[$key] ?? null; ?>
          <label class="f"<?= ($long || $wide) ? ' style="grid-column:1/-1"' : '' ?>><span><?= e($label) ?></span>
            <?php if ($long): ?><textarea name="<?= e($key) ?>" rows="2"><?= e($val) ?></textarea>
            <?php elseif ($choices): ?><select name="<?= e($key) ?>"><?php foreach ($choices as $ck => $cl): ?><option value="<?= e((string)$ck) ?>"<?= (string)$ck === $val ? ' selected' : '' ?>><?= e($cl) ?></option><?php endforeach; ?></select>
            <?php elseif ($secret): ?><input type="password" name="<?= e($key) ?>" value="" autocomplete="new-password" placeholder="<?= $val !== '' ? 'saved (leave blank to keep)' : 'not set' ?>">
            <?php else: ?><input type="text" name="<?= e($key) ?>" value="<?= e($val) ?>"><?php endif; ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <button class="btn primary" type="submit">Save settings</button>
</form>
<?php if (!empty($hostmaster)): ?>
<form method="post" class="panel soft" style="margin-top:16px">
  <?= csrf_field() ?><input type="hidden" name="action" value="test_sms">
  <h2>Send a test text</h2>
  <div class="btn-row"><input type="tel" name="test_to" placeholder="Mobile number" style="max-width:220px"><button class="btn sm" type="submit">Send test</button></div>
</form>
<?php endif; ?>
