<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/stories_gallery.php';

$pdo = db();
$previewId = filter_input(INPUT_GET, 'preview', FILTER_VALIDATE_INT) ?: 0;
$story = false;
if ($previewId) {
    $viewer = current_user();
    if (!$viewer || !in_array($viewer['role'], ['charity', 'admin'], true)) {
        http_response_code(404);
        exit('Story not found.');
    }
    $query = str_replace("WHERE s.status = 'published' AND s.published_at IS NOT NULL AND s.published_at <= NOW()", 'WHERE s.id = ?', story_public_sql());
    $stmt = $pdo->prepare($query);
    $stmt->execute([$previewId]);
    $story = $stmt->fetch();
    if ($story && $viewer['role'] === 'charity' && (int)$story['charity_id'] !== (int)$viewer['charity_id']) {
        $story = false;
    }
} else {
    $slug = trim((string)($_GET['slug'] ?? ''));
    $stmt = $pdo->prepare(story_public_sql() . ' AND s.slug = ?');
    $stmt->execute([$slug]);
    $story = $stmt->fetch();
}
if (!$story) {
    http_response_code(404);
    $pageTitle = 'Story not found';
    require __DIR__ . '/../includes/header.php';
    echo '<section class="dashboard"><div class="shell content-empty"><h1>Story not found</h1><p>This story may be a draft, or it may no longer be available.</p><a class="button" href="' . e(url('public/stories.php')) . '">Explore stories</a></div></section>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}
$imagesStmt = $pdo->prepare('SELECT image_path, alt_text FROM change_story_images WHERE story_id = ? ORDER BY sort_order, id');
$imagesStmt->execute([(int)$story['id']]);
$storyImages = $imagesStmt->fetchAll();
$impactStats = safe_impact_stats((string)$story['impact_stats_json']);
$verifiedItems = received_campaign_items($pdo, $story['related_campaign_id'] ? (int)$story['related_campaign_id'] : null);
$moreStmt = $pdo->prepare(str_replace("WHERE s.status = 'published'", 'WHERE s.status = \'published\' AND s.charity_id = ? AND s.id <> ?', story_public_sql()) . ' ORDER BY s.published_at DESC LIMIT 3');
$moreStmt->execute([(int)$story['charity_id'], (int)$story['id']]);
$moreStories = $moreStmt->fetchAll();
$pageTitle = $story['title'];
require __DIR__ . '/../includes/header.php';
?>
<article class="story-detail">
  <header class="story-detail-head">
    <div class="shell story-detail-head-inner">
      <?php if ($previewId): ?><span class="status status-warning">Private preview</span><?php endif; ?>
      <span class="chip chip-sun"><?= e($story['category_name']) ?></span>
      <h1><?= e($story['title']) ?></h1>
      <p class="story-detail-excerpt"><?= e($story['excerpt']) ?></p>
      <div class="story-byline"><strong><?= empty($story['created_by']) ? 'Giftly campaign update · ' : '' ?><?= e($story['organization_name']) ?></strong><span><?= e(format_date($story['published_at'])) ?></span></div>
    </div>
  </header>
  <div class="shell story-detail-layout">
    <div class="story-detail-main">
      <figure class="story-cover"><?= story_image_markup($story['featured_image_path'], $story['featured_alt'] ?: $story['title'], '', !empty($story['featured_image_is_illustrative'])) ?></figure>
      <?php if ($story['quote']): ?><blockquote class="story-quote"><span aria-hidden="true">“</span><p><?= e($story['quote']) ?></p></blockquote><?php endif; ?>
      <div class="story-prose"><?= nl2br(e($story['description'])) ?></div>
      <?php if ($storyImages): ?>
        <div class="story-photo-grid"><?php foreach ($storyImages as $image): ?><figure><?= story_image_markup($image['image_path'], $image['alt_text'] ?: $story['title']) ?><?php if ($image['alt_text']): ?><figcaption><?= e($image['alt_text']) ?></figcaption><?php endif; ?></figure><?php endforeach; ?></div>
      <?php endif; ?>
    </div>
    <aside class="story-detail-aside">
      <section class="story-impact-panel"><span class="eyebrow">Change in action</span><h2>Impact from this campaign</h2>
        <?php if ($story['campaign_title']): ?><p class="related-campaign"><?= e($story['campaign_title']) ?></p><?php endif; ?>
        <div class="verified-impact"><strong><?= number_format($verifiedItems) ?></strong><span>items received and confirmed</span></div>
        <?php if ($impactStats): ?><div class="reported-impact"><small>Shared by the charity</small><?php foreach ($impactStats as $stat): ?><div><strong><?= e($stat['value']) ?></strong><span><?= e($stat['label']) ?></span></div><?php endforeach; ?></div><?php endif; ?>
      </section>
      <button class="button button-outline story-share" type="button" data-share-story data-title="<?= e($story['title']) ?>">Share this story <span aria-hidden="true">↗</span></button>
      <div class="story-charity-note"><span class="story-charity-mark">G</span><div><strong><?= e($story['organization_name']) ?></strong><p><?= empty($story['created_by']) ? 'Campaign partner for this Giftly-verified progress update.' : 'A local charity sharing the change made possible by community donations.' ?></p></div></div>
    </aside>
  </div>
  <?php if ($moreStories): ?><section class="section section-soft"><div class="shell"><div class="section-head"><div><span class="eyebrow">More from this charity</span><h2 class="section-title">Keep reading.</h2></div></div><div class="story-grid"><?php foreach ($moreStories as $more): ?><article class="story-card"><a class="story-card-image" href="<?= e(url('public/story.php?slug=' . rawurlencode($more['slug']))) ?>"><?= story_image_markup($more['featured_image_path'], $more['title'], '', !empty($more['featured_image_is_illustrative'])) ?></a><div class="story-card-body"><span class="chip"><?= e($more['category_name']) ?></span><h3><a href="<?= e(url('public/story.php?slug=' . rawurlencode($more['slug']))) ?>"><?= e($more['title']) ?></a></h3><p><?= e($more['excerpt']) ?></p></div></article><?php endforeach; ?></div></div></section><?php endif; ?>
</article>
<script src="<?= e(url('assets/js/stories-gallery.js')) ?>?v=<?= @filemtime(__DIR__ . '/../assets/js/stories-gallery.js') ?: '1' ?>" defer></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
