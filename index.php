<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$pdo = db();
$stats = $pdo->query("SELECT
    (SELECT COUNT(*) FROM users WHERE role = 'donor' AND is_active = 1) AS donors,
    (SELECT COUNT(*) FROM charities WHERE verified = 1) AS charities,
    (SELECT COALESCE(SUM(di.quantity), 0) FROM donation_items di JOIN donation_pledges dp ON dp.id = di.pledge_id WHERE dp.status = 'received') AS items,
    (SELECT COUNT(*) FROM clothing_requests WHERE status = 'active') AS needs")->fetch();
$featured = $pdo->query("SELECT cr.*, c.organization_name, u.name AS contact_name,
    COALESCE((SELECT SUM(rr.quantity_needed) FROM request_requirements rr WHERE rr.request_id = cr.id),0) AS needed,
    COALESCE((SELECT SUM(di.quantity) FROM donation_items di JOIN donation_pledges dp ON dp.id = di.pledge_id JOIN request_requirements rr ON rr.id = di.request_requirement_id WHERE rr.request_id=cr.id AND dp.status='received'),0) AS received
    FROM clothing_requests cr JOIN charities c ON c.id = cr.charity_id JOIN users u ON u.id = c.user_id
    WHERE cr.status='active' ORDER BY cr.is_featured DESC, cr.created_at DESC LIMIT 3")->fetchAll();
$pageTitle = 'Clothes with a next chapter';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="shell hero-grid">
    <div>
      <span class="eyebrow">Wear the change</span>
      <h1>Good clothes deserve a <span class="paint">next chapter.</span></h1>
      <p class="lead">Giftly gets your quality clothes directly to the charities asking for them. No guesswork. Just exactly what’s needed, right when it matters.</p>
      <div class="hero-actions">
        <a class="button" href="<?= e(url('donor/browse.php')) ?>">Find a clothing need <span>→</span></a>
        <a class="button button-outline" href="<?= e(url('auth/register.php?role=charity')) ?>">I represent a charity</a>
      </div>
    </div>
    <aside class="hero-card" aria-label="Example clothing request">
      <span class="eyebrow" style="color:#FFD928">Needed this week</span>
      <h2>Winter layers for families finding home</h2>
      <div class="request-mini"><div class="cloth-icon">🧥</div><div><small>Harbour House</small><strong>Coats · Adult M–XL</strong><small>42 pieces still needed</small></div></div>
    </aside>
  </div>
</section>
<section class="stat-band"><div class="shell stat-grid">
  <div><strong><?= number_format((int)$stats['items']) ?>+</strong><span>items received with care</span></div>
  <div><strong><?= number_format((int)$stats['donors']) ?>+</strong><span>generous donors</span></div>
  <div><strong><?= number_format((int)$stats['charities']) ?></strong><span>local charities matched</span></div>
  <div><strong><?= number_format((int)$stats['needs']) ?></strong><span>live needs right now</span></div>
</div></section>
<section class="section" id="how-it-works"><div class="shell">
  <div class="section-head"><div><span class="eyebrow">Ridiculously straightforward</span><h2 class="section-title">The kindest way to clear your closet.</h2></div><p class="section-description">Every request is posted by a real local organisation, with sizes and quantities they can put to use today.</p></div>
  <div class="steps">
    <article class="step"><span class="step-num">01</span><h3>See what’s wanted</h3><p>Browse precise, local clothing requests—from school uniforms to warm coats.</p></article>
    <article class="step"><span class="step-num">02</span><h3>Make a pledge</h3><p>Tell a charity what you can give. They approve it before you make the trip.</p></article>
    <article class="step"><span class="step-num">03</span><h3>Drop it with love</h3><p>Choose a time, hand it over, and see the difference you made in your history.</p></article>
  </div>
</div></section>
<section class="section section-soft"><div class="shell">
  <div class="section-head"><div><span class="eyebrow">Local needs, right now</span><h2 class="section-title">Your wardrobe can be someone’s warm welcome.</h2></div><a class="button button-outline" href="<?= e(url('donor/browse.php')) ?>">See every request</a></div>
  <div class="request-grid">
  <?php foreach ($featured as $request): $remaining = max(0, (int)$request['needed'] - (int)$request['received']); $percent = $request['needed'] ? min(100, round($request['received'] / $request['needed'] * 100)) : 0; ?>
    <article class="request-card"><div class="card-top"><div class="category-icon"><?= request_image_markup($request['image_path']) ?></div><span class="status status-success">active</span></div><h3><?= e($request['title']) ?></h3><p><?= e($request['organization_name']) ?> · <?= e($request['location']) ?></p><div class="request-meta"><span class="chip"><?= e($request['season']) ?></span><span class="chip chip-sun"><?= e($request['urgency']) ?> need</span></div><div class="progress"><span style="width:<?= $percent ?>%"></span></div><div class="progress-info"><span><?= $remaining ?> items to go</span><span><?= (int)$request['received'] ?>/<?= (int)$request['needed'] ?></span></div><a class="text-link" href="<?= e(url('donor/request.php?id=' . $request['id'])) ?>">View request →</a></article>
  <?php endforeach; ?>
  </div>
</div></section>
<section class="section"><div class="shell"><div class="impact-cta"><div><span class="eyebrow" style="color:#FFD928">Make room. Make an impact.</span><h2>Someone nearby is waiting for exactly what you have.</h2><p>Join people putting useful things back into circulation.</p></div><a class="button button-sun" href="<?= e(url('auth/register.php')) ?>">Start giving today →</a></div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
