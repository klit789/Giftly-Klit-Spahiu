<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/stories_gallery.php';
$pdo = db();
$categories = gallery_categories($pdo);
$term = trim((string)($_GET['q'] ?? ''));
$categoryId = filter_var($_GET['category'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
$page = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
$limit = 12;
$where = [];
$params = [];
if ($term !== '') {
    $where[] = '(g.caption LIKE ? OR c.organization_name LIKE ? OR cr.title LIKE ? OR g.location_safe LIKE ?)';
    array_push($params, '%' . $term . '%', '%' . $term . '%', '%' . $term . '%', '%' . $term . '%');
}
if ($categoryId && array_filter($categories, static fn ($category): bool => (int)$category['id'] === $categoryId)) {
    $where[] = 'g.category_id = ?';
    $params[] = $categoryId;
}
$sql = gallery_public_sql();
if ($where) $sql .= ' AND ' . implode(' AND ', $where);
$sql .= ' ORDER BY g.published_at DESC, g.id DESC LIMIT ' . ($limit + 1) . ' OFFSET ' . (($page - 1) * $limit);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll();
$hasMore = count($entries) > $limit;
if ($hasMore) array_pop($entries);
$mapEntry = static fn (array $entry): array => [
    'id'=>(int)$entry['id'],
    'image'=>str_starts_with($entry['image_path'], 'assets/uploads/gallery/') ? url($entry['image_path']) : '',
    'caption'=>$entry['caption'],
    'charity'=>(int)$entry['is_illustrative'] ? 'Giftly illustrative image' : $entry['organization_name'],
    'campaign'=>$entry['campaign_title'] ?: '',
    'campaign_id'=>(int)($entry['campaign_id'] ?? 0),
    'category'=>$entry['category_name'],
    'location'=>$entry['location_safe'] ?: '',
    'date'=>format_date($entry['published_at']),
    'illustrative'=>(int)$entry['is_illustrative'] === 1,
];
if (($_GET['format'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['items'=>array_map($mapEntry, $entries), 'has_more'=>$hasMore], JSON_THROW_ON_ERROR);
    exit;
}
$pageTitle = 'The Happy People Gallery';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-hero gallery-hero">
  <div class="shell gallery-hero-inner"><div><span class="eyebrow">Shared with care</span><h1>The Happy People Gallery</h1><p>Explore clothing imagery and approved community photos. Illustrations are clearly labeled; recipient photos appear only with consent.</p></div><div class="gallery-hero-stamp"><span>✦</span><strong>Made possible<br>by giving</strong></div></div>
</section>
<section class="gallery-page">
  <div class="shell">
    <form class="content-filters gallery-filters" method="get">
      <label class="sr-only" for="gallery-search">Search photographs</label><input class="input" type="search" id="gallery-search" name="q" value="<?= e($term) ?>" placeholder="Search captions, charities, campaigns">
      <label class="sr-only" for="gallery-category">Filter by category</label><select class="select" id="gallery-category" name="category"><option value="">All photographs</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= $categoryId === (int)$category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select>
      <button class="button button-small" type="submit">Search gallery</button>
    </form>
    <?php if ($entries): ?><div class="gallery-grid" id="happy-gallery" data-page="<?= $page ?>" data-query="<?= e($term) ?>" data-category="<?= $categoryId ?>" data-base-url="<?= e(url()) ?>">
      <?php foreach ($entries as $index => $entry): $item = $mapEntry($entry); ?>
        <button type="button" class="gallery-card<?= $index === 0 && $page === 1 ? ' gallery-card-featured' : '' ?>" data-gallery-item data-id="<?= $item['id'] ?>" data-image="<?= e($item['image']) ?>" data-caption="<?= e($item['caption']) ?>" data-charity="<?= e($item['charity']) ?>" data-campaign="<?= e($item['campaign']) ?>" data-campaign-id="<?= $item['campaign_id'] ?>" data-category="<?= e($item['category']) ?>" data-location="<?= e($item['location']) ?>" data-date="<?= e($item['date']) ?>" data-illustrative="<?= $item['illustrative'] ? '1' : '0' ?>" aria-label="View photograph: <?= e($item['caption']) ?>">
          <img src="<?= e($item['image']) ?>" alt="<?= e($item['caption']) ?>" loading="<?= $index < 3 ? 'eager' : 'lazy' ?>">
          <span class="gallery-card-overlay"><?php if ($item['illustrative']): ?><span class="chip chip-sun">Illustrative</span><?php endif; ?><span class="chip"><?= e($item['category']) ?></span><strong><?= e($item['caption']) ?></strong><small><?= e($item['charity']) ?><?= $item['location'] ? ' · ' . e($item['location']) : '' ?></small></span><span class="gallery-card-zoom" aria-hidden="true">↗</span>
        </button>
      <?php endforeach; ?>
    </div>
    <?php if ($hasMore): ?><div class="gallery-load-wrap"><button class="button button-outline" id="gallery-load-more" type="button">Load more moments <span aria-hidden="true">↓</span></button></div><?php endif; ?>
    <?php else: ?><div class="content-empty"><span aria-hidden="true">♡</span><h2>Good moments are on their way.</h2><p>No approved photographs match your search yet. Please check back soon.</p></div><?php endif; ?>
    <p class="gallery-privacy-note">Illustrative photos are labeled and are not evidence of recipient outcomes. Real recipient photos require consent and review. <a href="<?= e(url('public/removal_request.php')) ?>">Request removal of a photo</a>.</p>
  </div>
</section>
<div class="gallery-lightbox" data-gallery-lightbox role="dialog" aria-modal="true" aria-label="Photograph viewer" hidden>
  <button class="lightbox-close" type="button" data-lightbox-close aria-label="Close photograph viewer">×</button>
  <button class="lightbox-nav lightbox-prev" type="button" data-lightbox-prev aria-label="Previous photograph">‹</button>
  <figure><img data-lightbox-image src="" alt=""><figcaption><span class="chip chip-sun" data-lightbox-category></span><span class="content-illustrative-badge lightbox-illustrative-note" data-lightbox-illustrative hidden>Illustrative image — not a verified Giftly outcome</span><h2 data-lightbox-caption></h2><p><strong data-lightbox-charity></strong><span data-lightbox-location></span><span data-lightbox-campaign-wrap> · <a data-lightbox-campaign></a></span><time data-lightbox-date></time></p><a class="lightbox-removal-link" data-lightbox-removal href="#">Request removal</a></figcaption></figure>
  <button class="lightbox-nav lightbox-next" type="button" data-lightbox-next aria-label="Next photograph">›</button>
</div>
<script src="<?= e(url('assets/js/stories-gallery.js')) ?>?v=<?= @filemtime(__DIR__ . '/../assets/js/stories-gallery.js') ?: '1' ?>" defer></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
