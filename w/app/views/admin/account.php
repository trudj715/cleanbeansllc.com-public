<?php /** @var array $me @var ?string $pending @var ?array $codes @var int $codes_left */ ?>
<h1>My account</h1>
<div class="grid2" style="align-items:start">
  <form method="post" class="panel">
    <?= csrf_field() ?><input type="hidden" name="action" value="password">
    <h2>Change password</h2>
    <p class="small muted">Signed in as <?= e($me['email']) ?>.</p>
    <label class="f"><span>Current password</span><input type="password" name="current" autocomplete="current-password" required></label>
    <label class="f"><span>New password</span><small>At least 12 characters. A short sentence works well.</small><input type="password" name="password" autocomplete="new-password" minlength="12" required></label>
    <label class="f"><span>New password again</span><input type="password" name="password2" autocomplete="new-password" minlength="12" required></label>
    <button class="btn primary" type="submit">Change password</button>
  </form>

  <div class="panel" id="mfa">
    <h2>Two-step sign-in</h2>
    <?php if ($codes): ?>
      <div class="flash"><strong>Save these recovery codes now.</strong> Each one works once if you lose your phone. They won't be shown again.</div>
      <div class="codes"><?php foreach ($codes as $c): ?><div><?= e($c) ?></div><?php endforeach; ?></div>
      <p class="small muted">Print them or store them in a password manager.</p>
    <?php endif; ?>

    <?php if ((int)$me['totp_enabled'] === 1): ?>
      <p><span class="tag">On</span> Signing in asks for a code from your authenticator app. Recovery codes left: <strong><?= $codes_left ?></strong>.</p>
      <form method="post" style="margin-bottom:12px"><?= csrf_field() ?><input type="hidden" name="action" value="new_codes"><button class="btn ghost sm" type="submit">Make new recovery codes</button></form>
      <form method="post" class="panel soft"><?= csrf_field() ?><input type="hidden" name="action" value="mfa_off">
        <label class="f"><span>Turn off two-step sign-in (enter your password)</span><input type="password" name="current" autocomplete="current-password" required></label>
        <button class="btn danger sm" type="submit">Turn off</button>
      </form>
    <?php elseif ($pending): ?>
      <ol>
        <li>Open an authenticator app on your phone (Google Authenticator, Microsoft Authenticator, Authy or 1Password).</li>
        <li>Add an account by scanning this code:
          <div id="qr" data-otpauth="<?= e(totp_uri($pending, $me['email'])) ?>"></div>
          <p class="small">Can't scan? Enter this key instead: <code style="word-break:break-all"><?= e(trim(chunk_split($pending, 4, ' '))) ?></code></p></li>
        <li>Type the 6-digit code the app shows:</li>
      </ol>
      <form method="post" class="btn-row"><?= csrf_field() ?><input type="hidden" name="action" value="mfa_confirm">
        <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" style="max-width:140px" required>
        <button class="btn primary" type="submit">Turn on</button></form>
      <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>
    <?php else: ?>
      <p><span class="tag draft">Off</span> With two-step sign-in, a stolen password alone can't get into the admin. You'll enter a code from your phone when you sign in.</p>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="mfa_start"><button class="btn primary" type="submit">Set up two-step sign-in</button></form>
    <?php endif; ?>
  </div>
</div>
