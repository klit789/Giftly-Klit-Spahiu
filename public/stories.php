<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/stories_gallery.php';

$pdo = db();
$categories = story_categories($pdo);
$term = trim((string)($_GET['q'] ?? ''));
$categoryId = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT) ?: 0;
$where = [ "s.status = 'published'" ];
$params = [];
if ($term !== '') {
    $where[] = '(s.title LIKE ? OR s.excerpt LIKE ? OR c.organization_name LIKE ?)';
    array_push($params, '%' . $term . '%', '%' . $term . '%', '%' . $term . '%');
}
if ($categoryId && array_filter($categories, static fn ($category): bool => (int)$category['id'] === $categoryId)) {
    $where[] = 's.category_id = ?';
    $params[] = $categoryId;
}
$sql = str_replace("WHERE s.status = 'published'", 'WHERE ' . implode(' AND ', $where), story_public_sql());
$stmt = $pdo->prepare($sql . ' ORDER BY s.published_at DESC, s.id DESC LIMIT 25');
$stmt->execute($params);
$stories = $stmt->fetchAll();
$featured = array_shift($stories);
$pageTitle = 'Stories of Change';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-hero stories-hero">
  <div class="shell">
    <span class="eyebrow">Verified campaign updates and charity stories</span>
    <h1>Stories of Change</h1>
    <p>Read transparent campaign updates and community stories shared with care. Personal recipient stories are published only by charities with permission.</p>
  </div>
</section>
<section class="stories-page">
  <div class="shell">
    <form class="content-filters" method="get">
      <label class="sr-only" for="story-search">Search stories</label>
      <input class="input" type="search" id="story-search" name="q" value="<?= e($term) ?>" placeholder="Search stories or charities">
      <label class="sr-only" for="story-category">Filter by category</label>
      <select class="select" id="story-category" name="category">
        <option value="">All story categories</option>
        <?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= $categoryId === (int)$category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?>
      </select>
      <button class="button button-small" type="submit">Find stories</button>
    </form>

    <?php if ($featured): ?>
      <article class="story-feature">
        <a class="story-feature-image" href="<?= e(url('public/story.php?slug=' . rawurlencode($featured['slug']))) ?>">
          <?= story_image_markup($featured['featured_image_path'], $featured['title'], '', !empty($featured['featured_image_is_illustrative'])) ?>
          <span class="story-feature-badge"><?= empty($featured['created_by']) ? 'Campaign update' : 'Featured story' ?></span>
        </a>
        <div class="story-feature-copy">
          <span class="chip chip-sun"><?= e($featured['category_name']) ?></span>
          <h2><?= e($featured['title']) ?></h2>
          <p><?= e($featured['excerpt']) ?></p>
          <div class="story-byline"><strong><?= empty($featured['created_by']) ? 'Giftly campaign update · ' : '' ?><?= e($featured['organization_name']) ?></strong><span><?= e(format_date($featured['published_at'])) ?></span></div>
          <a class="button" href="<?= e(url('public/story.php?slug=' . rawurlencode($featured['slug']))) ?>">Read the story <span aria-hidden="true">→</span></a>
        </div>
      </article>
    <?php endif; ?>

    <?php if ($stories): ?>
      <div class="section-head stories-list-head"><div><span class="eyebrow">From our community</span><h2 class="section-title">More stories of change.</h2></div></div>
      <div class="story-grid">
        <?php foreach ($stories as $story): ?>
          <article class="story-card">
            <a class="story-card-image" href="<?= e(url('public/story.php?slug=' . rawurlencode($story['slug']))) ?>"><?= story_image_markup($story['featured_image_path'], $story['title'], '', !empty($story['featured_image_is_illustrative'])) ?></a>
            <div class="story-card-body">
              <div class="story-card-meta"><span class="chip"><?= e($story['category_name']) ?></span><time><?= e(format_date($story['published_at'])) ?></time></div>
              <h3><a href="<?= e(url('public/story.php?slug=' . rawurlencode($story['slug']))) ?>"><?= e($story['title']) ?></a></h3>
              <p><?= e($story['excerpt']) ?></p>
              <div class="story-byline"><strong><?= empty($story['created_by']) ? 'Giftly campaign update · ' : '' ?><?= e($story['organization_name']) ?></strong><a class="text-link" href="<?= e(url('public/story.php?slug=' . rawurlencode($story['slug']))) ?>">Read more →</a></div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php elseif (!$featured): ?>
      <div class="content-empty"><span aria-hidden="true">✦</span><h2>Stories are taking shape.</h2><p>Our charity partners are preparing stories to share with the community. Please check back soon.</p></div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
