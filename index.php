<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/stories_gallery.php';

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
$featuredStory = $pdo->query(story_public_sql() . ' ORDER BY s.published_at DESC, s.id DESC LIMIT 1')->fetch() ?: null;
$galleryHighlights = $pdo->query(gallery_public_sql() . ' ORDER BY g.published_at DESC, g.id DESC LIMIT 4')->fetchAll();
$recipientPhotos = $pdo->query(gallery_public_sql() . ' AND g.is_illustrative = 0 ORDER BY g.published_at DESC, g.id DESC LIMIT 3')->fetchAll();
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
  <div><strong><span data-count="<?= (int)$stats['items'] ?>"><?= number_format((int)$stats['items']) ?></span>+</strong><span>items received with care</span></div>
  <div><strong><span data-count="<?= (int)$stats['donors'] ?>"><?= number_format((int)$stats['donors']) ?></span>+</strong><span>generous donors</span></div>
  <div><strong><span data-count="<?= (int)$stats['charities'] ?>"><?= number_format((int)$stats['charities']) ?></span></strong><span>local charities matched</span></div>
  <div><strong><span data-count="<?= (int)$stats['needs'] ?>"><?= number_format((int)$stats['needs']) ?></span></strong><span>live needs right now</span></div>
</div></section>
<section class="section" id="how-it-works"><div class="shell">
  <div class="section-head"><div><span class="eyebrow">Ridiculously straightforward</span><h2 class="section-title">The kindest way to clear your closet.</h2></div><p class="section-description">Every request is posted by a real local organisation, with sizes and quantities they can put to use today.</p></div>
  <div class="steps">
    <article class="step"><div class="step-top"><span class="step-num">01</span><span class="step-icon" aria-hidden="true">⌕</span></div><h3>See what’s wanted</h3><p>Browse precise, local clothing requests—from school uniforms to warm coats.</p><span class="step-link">Explore needs <b>→</b></span></article>
    <article class="step step-featured"><div class="step-top"><span class="step-num">02</span><span class="step-icon" aria-hidden="true">♡</span></div><h3>Make a pledge</h3><p>Tell a charity what you can give. They approve it before you make the trip.</p><span class="step-link">Choose your items <b>→</b></span></article>
    <article class="step"><div class="step-top"><span class="step-num">03</span><span class="step-icon" aria-hidden="true">✓</span></div><h3>Drop it with love</h3><p>Choose a time, hand it over, and see the difference you made in your history.</p><span class="step-link">Track your impact <b>→</b></span></article>
  </div>
</div></section>
<section class="section section-soft journey-section" id="useful-giving"><div class="shell">
  <div class="section-head"><div><span class="eyebrow">One circle, every role</span><h2 class="section-title">Built around useful giving.</h2></div><p class="section-description">Giftly keeps donors, charities and communities connected from the first pledge to the final delivery.</p></div>
  <div class="journey-grid">
    <article class="journey-card"><div class="journey-card-icon" aria-hidden="true">👕</div><h3>Give what is needed</h3><p>Find live requests by size, season, category and location so every item has a clear purpose.</p></article>
    <article class="journey-card journey-card-accent"><div class="journey-card-icon" aria-hidden="true">✓</div><h3>Stay in the loop</h3><p>Track pledges, appointments and receipts in one place, with clear updates at every step.</p></article>
    <article class="journey-card journey-card-dark"><div class="journey-card-icon" aria-hidden="true">♥</div><h3>See community impact</h3><p>Follow verified deliveries and discover how local wardrobes become warmer, stronger neighbourhoods.</p></article>
  </div>
</div></section>
<section class="section section-soft local-needs-section" id="local-needs"><div class="shell">
  <div class="section-head"><div><span class="eyebrow">Local needs, right now</span><h2 class="section-title">Your wardrobe can be someone’s warm welcome.</h2></div><a class="button button-outline" href="<?= e(url('donor/browse.php')) ?>">See every request</a></div>
  <div class="request-grid">
  <?php foreach ($featured as $request): $remaining = max(0, (int)$request['needed'] - (int)$request['received']); $percent = $request['needed'] ? min(100, round($request['received'] / $request['needed'] * 100)) : 0; ?>
    <article class="request-card"><div class="card-top"><div class="category-icon"><?= request_image_markup($request['image_path']) ?></div><span class="status status-success">active</span></div><h3><?= e($request['title']) ?></h3><p><?= e($request['organization_name']) ?> · <?= e($request['location']) ?></p><div class="request-meta"><span class="chip"><?= e($request['season']) ?></span><span class="chip chip-sun"><?= e($request['urgency']) ?> need</span></div><div class="progress"><span style="width:<?= $percent ?>%"></span></div><div class="progress-info"><span><?= $remaining ?> items to go</span><span><?= (int)$request['received'] ?>/<?= (int)$request['needed'] ?></span></div><a class="text-link" href="<?= e(url('donor/request.php?id=' . $request['id'])) ?>">View request →</a></article>
  <?php endforeach; ?>
  </div>
</div></section>
<section class="section every-thread-section" id="stories-of-change"><div class="shell">
  <div class="section-head"><div><span class="eyebrow">Good things travel</span><h2 class="section-title">Every thread has a story.</h2></div><p class="section-description">Behind every warm coat and school uniform is a person, a community and a little more hope for what comes next.</p></div>
  <div class="every-thread-layout">
    <?php if ($featuredStory): ?><article class="home-feature-story">
      <a class="home-feature-story-image" href="<?= e(url('public/story.php?slug=' . rawurlencode($featuredStory['slug']))) ?>"><?= story_image_markup($featuredStory['featured_image_path'], $featuredStory['title'], '', !empty($featuredStory['featured_image_is_illustrative'])) ?>      <span class="home-feature-tag"><?= empty($featuredStory['created_by']) ? 'Campaign update' : 'Featured story' ?></span></a>
      <div class="home-feature-story-copy"><span class="chip chip-sun"><?= e($featuredStory['category_name']) ?></span><h3><?= e($featuredStory['title']) ?></h3><p><?= e($featuredStory['excerpt']) ?></p><div class="story-byline"><strong><?= empty($featuredStory['created_by']) ? 'Giftly campaign update · ' : '' ?><?= e($featuredStory['organization_name']) ?></strong><span><?= e(format_date($featuredStory['published_at'])) ?></span></div>
        <?php if ($featuredStory['related_campaign_id']): ?><div class="home-story-impact"><strong><?= number_format(received_campaign_items($pdo, (int)$featuredStory['related_campaign_id'])) ?></strong><span>items received for this campaign</span></div><?php endif; ?>
        <a class="text-link" href="<?= e(url('public/story.php?slug=' . rawurlencode($featuredStory['slug']))) ?>">Read the full story →</a></div>
    </article><?php else: ?><article class="home-story-empty"><span class="home-story-empty-mark">G</span><span class="eyebrow">Stories of Change</span><h3>Real stories, written with care.</h3><p>Our charity partners are preparing the first stories of the people and communities Giftly supports.</p></article><?php endif; ?>
    <div class="home-gallery-ribbon"><div class="home-gallery-ribbon-head"><span class="eyebrow">The Happy People Gallery</span><a class="text-link" href="<?= e(url('public/gallery.php')) ?>">See gallery →</a></div>
      <?php if ($galleryHighlights): ?><div class="home-gallery-strip"><?php foreach ($galleryHighlights as $photo): ?><a class="home-gallery-tile" href="<?= e(url('public/gallery.php')) ?>"><img src="<?= e(url($photo['image_path'])) ?>" alt="<?= e($photo['caption']) ?>" loading="lazy"><span><?php if ($photo['is_illustrative']): ?><span class="chip chip-sun">Illustrative</span><?php endif; ?><strong><?= e($photo['caption']) ?></strong><small><?= $photo['is_illustrative'] ? 'Giftly illustrative image' : e($photo['organization_name']) ?></small></span></a><?php endforeach; ?></div>
      <?php else: ?><div class="home-gallery-empty"><span aria-hidden="true">♡</span><p>Illustrative clothing photos and approved community photos will appear here. Real recipient images require consent and review.</p></div><?php endif; ?>
      <div class="home-story-actions"><a class="button button-small" href="<?= e(url('public/stories.php')) ?>">Explore all stories</a><a class="button button-outline button-small" href="<?= e(url('public/gallery.php')) ?>">View full gallery</a></div>
    </div>
  </div>
</div></section>
<section class="section real-impact-section"><div class="shell">
  <div class="section-head"><div><span class="eyebrow">Moments that matter</span><h2 class="section-title">Real people. Real impact.</h2></div><p class="section-description">Recipient images appear only after consent and review; labelled illustrations are kept separate.</p></div>
  <?php if ($recipientPhotos): ?><div class="home-impact-grid"><?php foreach ($recipientPhotos as $photo): ?><a class="home-impact-card" href="<?= e(url('public/gallery.php')) ?>"><img src="<?= e(url($photo['image_path'])) ?>" alt="<?= e($photo['caption']) ?>" loading="lazy"><span class="home-impact-caption"><span class="chip chip-sun"><?= e($photo['category_name']) ?></span><strong><?= e($photo['caption']) ?></strong><small><?= e($photo['organization_name']) ?><?= $photo['location_safe'] ? ' · ' . e($photo['location_safe']) : '' ?></small></span></a><?php endforeach; ?></div><?php else: ?><div class="home-impact-empty"><span aria-hidden="true">✦</span><p>Recipient photographs will appear here only after informed consent and admin review. The labelled illustrations in the gallery are not recipient stories.</p><a class="text-link" href="<?= e(url('public/gallery.php')) ?>">Visit the gallery →</a></div><?php endif; ?>
</div></section>
<section class="section faq-section" id="giftly-faq"><div class="shell home-faq">
  <div><span class="eyebrow">Good to know</span><h2 class="section-title">A simpler way to donate.</h2></div>
  <div class="faq-list">
    <details class="faq-item"><summary>Who posts the clothing requests?</summary><p>Registered charities post specific needs for the people they support, including category, size and quantity.</p></details>
    <details class="faq-item"><summary>Is my pledge confirmed immediately?</summary><p>No. A charity reviews each pledge before it becomes a confirmed appointment, helping them plan useful collections.</p></details>
    <details class="faq-item"><summary>Can I choose where my clothes go?</summary><p>Yes. Browse local needs and choose the charity and request that match the clothes you want to donate.</p></details>
  </div>
</div></section>
<section class="section"><div class="shell"><div class="impact-cta"><div><span class="eyebrow" style="color:#FFD928">Make room. Make an impact.</span><h2>Someone nearby is waiting for exactly what you have.</h2><p>Join people putting useful things back into circulation.</p></div><a class="button button-sun" href="<?= e(url('auth/register.php')) ?>">Start giving today →</a></div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
