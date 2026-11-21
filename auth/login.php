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
<section class="auth-page"><div class="auth-card"><a class="brand" href="<?= e(url()) ?>"><span class="brand-mark">G</span><span>giftly</span></a><h1>Welcome back.</h1><p>Your next act of kindness is a few clicks away.</p><?php if($error): ?><div class="flash flash-error" style="margin-top:15px"><span><?= e($error) ?></span></div><?php endif; ?><form method="post" data-loading><?= csrf_field() ?><div class="field"><label for="email">Email address</label><input class="input" type="email" id="email" name="email" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>"></div><div class="field"><label for="password">Password</label><input class="input" type="password" id="password" name="password" required autocomplete="current-password"></div><button class="button" type="submit">Sign in →</button></form><p class="auth-alt">New to Giftly? <a class="text-link" href="<?= e(url('auth/register.php')) ?>">Create an account</a></p></div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
