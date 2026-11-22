<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
if (current_user()) redirect(dashboard_path(current_user()));

$requestedRole = $_GET['role'] ?? 'donor';
$initialRole = is_string($requestedRole) && in_array($requestedRole, ['donor', 'charity'], true) ? $requestedRole : 'donor';
$errors = []; $old = ['role' => $initialRole, 'country_code' => ''];
$countries = giftly_countries();
if (is_post()) {
    verify_csrf();
    $postedRole = $_POST['role'] ?? 'donor';
    $old = ['name'=>trim($_POST['name'] ?? ''), 'email'=>trim(strtolower($_POST['email'] ?? '')), 'role'=>is_string($postedRole) ? $postedRole : '', 'organization_name'=>trim($_POST['organization_name'] ?? ''), 'country_code'=>isset($_POST['country_code']) && is_string($_POST['country_code']) ? strtoupper(trim($_POST['country_code'])) : ''];
    $password = $_POST['password'] ?? '';
    if (mb_strlen($old['name']) < 2 || mb_strlen($old['name']) > 100) $errors[] = 'Please enter your full name.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (!valid_enum($old['role'], ['donor','charity'])) $errors[] = 'Please choose a valid account type.';
    if (strlen($password) < 8) $errors[] = 'Your password must be at least 8 characters.';
    if ($old['role'] === 'charity' && mb_strlen($old['organization_name']) < 2) $errors[] = 'Please enter your organisation name.';
    if ($old['role'] === 'charity' && !array_key_exists($old['country_code'], $countries)) $errors[] = 'Please choose the country where your charity is based.';
    if (!$errors) {
        $pdo = db(); $check = $pdo->prepare('SELECT id FROM users WHERE email = ?'); $check->execute([$old['email']]);
        if ($check->fetch()) $errors[] = 'That email already has an account. Try signing in.';
        else {
            $pdo->beginTransaction();
            try {
                $stmt=$pdo->prepare('INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?)');
                $stmt->execute([$old['name'],$old['email'],password_hash($password, PASSWORD_DEFAULT),$old['role']]); $userId=(int)$pdo->lastInsertId();
                if ($old['role']==='charity') { $stmt=$pdo->prepare('INSERT INTO charities (user_id,organization_name,country_code) VALUES (?,?,?)'); $stmt->execute([$userId,$old['organization_name'],$old['country_code']]); }
                log_activity($pdo, 'created an account', $userId, 'user', $userId); $pdo->commit();
                session_regenerate_id(true); $_SESSION['user_id']=$userId; flash('success','Welcome to Giftly. Your account is ready.');
                redirect($old['role']==='charity' ? 'charity/dashboard.php' : 'donor/dashboard.php');
            } catch (Throwable $e) { $pdo->rollBack(); $errors[]='We could not create your account. Please try again.'; }
        }
    }
}
$pageTitle='Join the good closet club'; require __DIR__ . '/../includes/header.php'; ?>
<section class="auth-page auth-page-split">
  <aside class="auth-story">
    <a class="brand auth-brand" href="<?= e(url()) ?>"><span class="brand-mark">G</span><span>giftly</span></a>
    <div class="auth-story-copy">
      <span class="auth-story-kicker">Good clothes, new chapters</span>
      <h1>Create a little more <span>good</span> in your community.</h1>
      <p>Join as a donor or charity and make every useful item easier to share.</p>
    </div>
    <blockquote>
      <p>“The smallest act of giving can change someone’s whole day.”</p>
      <cite>Giftly community</cite>
    </blockquote>
  </aside>
  <div class="auth-form-side auth-form-side-register">
    <div class="auth-form-wrap">
      <span class="auth-mobile-brand"><span>G</span> giftly</span>
      <h2>Create your account</h2>
      <p class="auth-form-lead">A few details and you’re ready to make an impact.</p>
      <?php if ($errors): ?><div class="flash flash-error"><span><?= e(implode(' ', $errors)) ?></span></div><?php endif; ?>
      <form method="post" novalidate data-loading>
        <?= csrf_field() ?>
        <div class="field"><label for="name">Your name</label><input class="input" id="name" name="name" placeholder="Full name" maxlength="100" required value="<?= e($old['name'] ?? '') ?>"></div>
        <div class="field"><label for="email">Email address</label><input class="input" type="email" id="email" name="email" placeholder="you@example.com" maxlength="190" required value="<?= e($old['email'] ?? '') ?>"></div>
        <div class="field"><label for="password">Password</label><input class="input" type="password" id="password" name="password" placeholder="At least 8 characters" minlength="8" required></div>
        <div class="field"><label>I’m joining as</label><div class="role-choice"><label><input type="radio" name="role" value="donor" <?= ($old['role'] ?? '')==='donor'?'checked':'' ?>><span>♥ A clothing donor</span></label><label><input type="radio" name="role" value="charity" <?= ($old['role'] ?? '')==='charity'?'checked':'' ?>><span>⌂ A charity team</span></label></div></div>
        <div class="field" id="organisation-field"><label for="organization_name">Organisation name <small>(charities only)</small></label><input class="input" id="organization_name" name="organization_name" maxlength="150" value="<?= e($old['organization_name'] ?? '') ?>"></div>
        <div class="field hidden" id="charity-country-field"><label for="country_code">Country <small>(charities only)</small></label><select class="select" id="country_code" name="country_code"><option value="">Choose your country</option><?php foreach ($countries as $code => $countryName): ?><option value="<?= e($code) ?>" <?= ($old['country_code'] ?? '') === $code ? 'selected' : '' ?>><?= e($countryName) ?></option><?php endforeach; ?></select></div>
        <button class="button auth-submit" type="submit">Create account</button>
      </form>
      <p class="auth-alt">Already part of Giftly? <a class="text-link" href="<?= e(url('auth/login.php')) ?>">Sign in</a></p>
    </div>
  </div>
</section>
<script>document.addEventListener('DOMContentLoaded',()=>{const f=document.getElementById('organisation-field'),cf=document.getElementById('charity-country-field'),country=document.getElementById('country_code'),r=document.querySelectorAll('input[name=role]');const t=()=>{const selected=document.querySelector('input[name=role]:checked'),charity=selected&&selected.value==='charity';f.classList.toggle('hidden',!charity);cf.classList.toggle('hidden',!charity);country.required=Boolean(charity)};r.forEach(x=>x.onchange=t);t()})</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
