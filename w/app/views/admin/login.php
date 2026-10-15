<?php
/** @var string $error @var string $step */
view('admin/layout', ['title' => 'Sign in', 'content' => function () use ($error, $step) { ?>
  <div class="login-card">
    <img src="<?= e(asset('assets/img/logo.svg')) ?>" alt="Clean Beans LLC">
    <?php if ($step === 'mfa'): ?>
      <h1>Two-step sign-in</h1>
      <p class="muted">Enter the 6-digit code from your authenticator app. Lost your phone? Enter one of your recovery codes instead.</p>
      <?php if ($error): ?><div class="flash error" role="alert"><?= e($error) ?></div><?php endif; ?>
      <form method="post" action="<?= e(url('admin/mfa')) ?>">
        <?= csrf_field() ?>
        <label class="f"><span>Code</span><input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus required></label>
        <button class="btn primary" type="submit">Verify</button>
        <a href="<?= e(url('admin/login')) ?>" style="margin-left:10px">Start over</a>
      </form>
    <?php else: ?>
      <h1>Site admin sign in</h1>
      <?php if ($error): ?><div class="flash error" role="alert"><?= e($error) ?></div><?php endif; ?>
      <form method="post" action="<?= e(url('admin/login')) ?>">
        <?= csrf_field() ?>
        <label class="f"><span>Email</span><input type="email" name="email" autocomplete="username" autofocus required value="<?= e(post('email')) ?>"></label>
        <label class="f"><span>Password</span><input type="password" name="password" autocomplete="current-password" required></label>
        <button class="btn primary" type="submit">Sign in</button>
      </form>
      <p class="small muted" style="margin-top:16px">Forgot your password? Ask the site owner or St Croix Tech Services to reset it.</p>
    <?php endif; ?>
  </div>
<?php }]);
