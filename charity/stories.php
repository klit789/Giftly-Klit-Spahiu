<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/stories_gallery.php';
$user = require_role('charity');
$pdo = db();
$charityId = (int)$user['charity_id'];

if (is_post()) {
    verify_csrf();
    $id = filter_var($_POST['story_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
    $action = $_POST['action'] ?? '';
    $stmt = $pdo->prepare('SELECT id FROM change_stories WHERE id = ? AND charity_id = ?');
    $stmt->execute([$id, $charityId]);
    if (!$stmt->fetchColumn()) {
        flash('error', 'That story does not belong to your organisation.');
        redirect('charity/stories.php');
    }
    if ($action === 'publish' || $action === 'unpublish') {
        $status = $action === 'publish' ? 'published' : 'draft';
        $stmt = $pdo->prepare('UPDATE change_stories SET status = ?, published_at = IF(? = \'published\', COALESCE(published_at, NOW()), published_at) WHERE id = ? AND charity_id = ?');
        $stmt->execute([$status, $status, $id, $charityId]);
        log_activity($pdo, $action . ' a change story', (int)$user['id'], 'story', $id);
        flash('success', $action === 'publish' ? 'Your story is now public.' : 'Your story has been saved as a draft.');
    } elseif ($action === 'delete') {
        $imageStmt = $pdo->prepare('SELECT image_path FROM change_story_images WHERE story_id = ? UNION ALL SELECT featured_image_path FROM change_stories WHERE id = ?');
        $imageStmt->execute([$id, $id]);
        $imagePaths = array_column($imageStmt->fetchAll(), 'image_path');
        $stmt = $pdo->prepare('DELETE FROM change_stories WHERE id = ? AND charity_id = ?');
        $stmt->execute([$id, $charityId]);
        foreach ($imagePaths as $path) {
            delete_content_upload($path);
        }
        log_activity($pdo, 'deleted a change story', (int)$user['id'], 'story', $id);
        flash('success', 'Your story and its photos were deleted.');
    } else {
        http_response_code(400);
        exit('Invalid story action.');
    }
    redirect('charity/stories.php');
}

$term = trim((string)($_GET['q'] ?? ''));
$sql = 'SELECT s.*, sc.name AS category_name FROM change_stories s JOIN story_categories sc ON sc.id = s.category_id WHERE s.charity_id = ?';
$params = [$charityId];
if ($term !== '') {
    $sql .= ' AND (s.title LIKE ? OR s.excerpt LIKE ?)';
    array_push($params, '%' . $term . '%', '%' . $term . '%');
}
$stmt = $pdo->prepare($sql . ' ORDER BY s.updated_at DESC');
$stmt->execute($params);
$stories = $stmt->fetchAll();
$pageTitle = 'My Stories';
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard"><div class="shell">
  <div class="welcome-row"><div><span class="eyebrow">Tell the change you made</span><h1>My Stories</h1><p>Share thoughtful, true accounts of your community’s progress.</p></div><a class="button" href="<?= e(url('charity/story_form.php')) ?>">Write a story +</a></div>
  <div class="panel">
    <form class="content-filters content-filters-compact" method="get"><input class="input" type="search" name="q" value="<?= e($term) ?>" placeholder="Search your stories" aria-label="Search stories"><button class="button button-small" type="submit">Search</button></form>
    <?php if (!$stories): ?><div class="content-empty"><span aria-hidden="true">✦</span><h2>No stories yet</h2><p>Tell your community what their generosity has made possible.</p><a class="button" href="<?= e(url('charity/story_form.php')) ?>">Create your first story</a></div>
    <?php else: ?><div class="manage-content-list"><?php foreach ($stories as $story): ?>
      <article class="manage-content-item">
        <div class="manage-content-thumb"><?= story_image_markup($story['featured_image_path'], $story['title'], '', !empty($story['featured_image_is_illustrative'])) ?></div>
        <div class="manage-content-copy"><span class="chip"><?= e($story['category_name']) ?></span><h2><?= e($story['title']) ?></h2><p><?= e($story['excerpt']) ?></p><span class="status <?= $story['status'] === 'published' ? 'status-success' : 'status-warning' ?>"><?= e($story['status']) ?></span></div>
        <div class="manage-content-actions">
          <a class="button button-outline button-small" href="<?= e(url('charity/story_form.php?id=' . (int)$story['id'])) ?>">Edit</a>
          <a class="text-link" href="<?= e(url('public/story.php?preview=' . (int)$story['id'])) ?>">Preview</a>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="story_id" value="<?= (int)$story['id'] ?>"><input type="hidden" name="action" value="<?= $story['status'] === 'published' ? 'unpublish' : 'publish' ?>"><button class="button button-small <?= $story['status'] === 'published' ? 'button-dark' : '' ?>" type="submit"><?= $story['status'] === 'published' ? 'Unpublish' : 'Publish' ?></button></form>
          <form method="post" data-confirm="Delete this story and all of its photos?"><?= csrf_field() ?><input type="hidden" name="story_id" value="<?= (int)$story['id'] ?>"><input type="hidden" name="action" value="delete"><button class="button button-danger button-small" type="submit">Delete</button></form>
        </div>
      </article>
    <?php endforeach; ?></div><?php endif; ?>
  </div>
</div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
