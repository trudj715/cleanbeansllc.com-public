<h1>New page</h1>
<form method="post" class="panel" style="max-width:640px">
  <?= csrf_field() ?>
  <label class="f"><span>Page title</span><input type="text" name="title" required></label>
  <label class="f"><span>Web address (optional)</span><small>Letters, numbers and dashes. Leave blank to make one from the title.</small><input type="text" name="slug" pattern="[a-z0-9\-]*"></label>
  <button class="btn primary" type="submit">Create page</button>
</form>
