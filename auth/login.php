<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
if (current_user()) redirect(dashboard_path(current_user()));
$error = '';
if (is_post()) { verify_csrf(); $email=trim(strtolower($_POST['email'] ?? '')); $password=$_POST['password'] ?? '';
    $stmt=db()->prepare('SELECT id,password_hash,role,is_active FROM users WHERE email=? LIMIT 1');$stmt->execute([$email]);$account=$stmt->fetch();
    if ($account && $account['is_active'] && password_verify($password,$account['password_hash'])) { session_regenerate_id(true); $_SESSION['user_id']=$account['id']; log_activity(db(),'signed in',(int)$account['id'],'user',(int)$account['id']); flash('success','Welcome back—there’s good to do.'); redirect(dashboard_path(['role'=>$account['role']])); }
    $error='That email or password did not match an active account.';
}
$pageTitle='Welcome back';require __DIR__ . '/../includes/header.php'; ?>
<section class="auth-page auth-page-split">
  <aside class="auth-story">
    <a class="brand auth-brand" href="<?= e(url()) ?>"><span class="brand-mark">G</span><span>giftly</span></a>
    <div class="auth-story-copy">
      <span class="auth-story-kicker">A warm place for useful things</span>
      <h1>Start your day with <span>everything</span> in one place.</h1>
      <p>Manage requests, pledges and appointments while helping good clothes find their next chapter.</p>
    </div>
    <blockquote>
      <p>“Every item has a story, and every donation makes the next one possible.”</p>
      <cite>Giftly community</cite>
    </blockquote>
  </aside>
  <div class="auth-form-side">
    <div class="auth-form-wrap">
      <span class="auth-mobile-brand"><span>G</span> giftly</span>
      <h2>Welcome back</h2>
      <p class="auth-form-lead">Sign in to continue to your Giftly account.</p>
      <?php if($error): ?><div class="flash flash-error"><span><?= e($error) ?></span></div><?php endif; ?>
      <form method="post" data-loading>
        <?= csrf_field() ?>
        <div class="field"><label for="email">Email address</label><input class="input" type="email" id="email" name="email" placeholder="you@company.com" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>"></div>
        <div class="field"><div class="auth-label-row"><label for="password">Password</label><span class="auth-forgot">Forgot password?</span></div><input class="input" type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password"></div>
        <label class="auth-remember"><input type="checkbox" name="remember"> <span>Remember me</span></label>
        <button class="button auth-submit" type="submit">Sign in</button>
      </form>
      <p class="auth-alt">New here? <a class="text-link" href="<?= e(url('auth/register.php')) ?>">Create an account</a></p>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
