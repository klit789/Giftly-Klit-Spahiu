<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/stories_gallery.php';
$user = require_role('admin');
$pdo = db();
if (is_post()) {
    verify_csrf();
    $id = filter_var($_POST['story_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'unpublish') {
        $stmt = $pdo->prepare("UPDATE change_stories SET status='draft' WHERE id=? AND status='published'");
        $stmt->execute([$id]);
        if ($stmt->rowCount()) {
            log_activity($pdo, 'unpublished a change story as administrator', (int)$user['id'], 'story', $id);
            flash('success', 'Story unpublished.');
        } else {
            flash('error', 'That story is not currently published.');
        }
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare('SELECT image_path FROM change_story_images WHERE story_id=? UNION ALL SELECT featured_image_path FROM change_stories WHERE id=?');
        $stmt->execute([$id,$id]);
        $paths = array_column($stmt->fetchAll(), 'image_path');
        $stmt = $pdo->prepare('DELETE FROM change_stories WHERE id=?');
        $stmt->execute([$id]);
        foreach ($paths as $path) delete_content_upload($path);
        log_activity($pdo, 'deleted a change story as administrator', (int)$user['id'], 'story', $id);
        flash('success', 'Story and its photos were removed.');
    } else {
        http_response_code(400);
        exit('Invalid story moderation action.');
    }
    redirect('admin/stories.php');
}
$stmt = $pdo->query("SELECT s.*,c.organization_name,sc.name AS category_name FROM change_stories s JOIN charities c ON c.id=s.charity_id JOIN story_categories sc ON sc.id=s.category_id ORDER BY s.updated_at DESC");
$stories = $stmt->fetchAll();
$pageTitle = 'Story Moderation';
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard"><div class="shell">
  <div class="welcome-row"><div><span class="eyebrow">Editorial oversight</span><h1>Story Moderation</h1><p>Review published charity stories and unpublish content that needs attention.</p></div><a class="button button-outline" href="<?= e(url('admin/dashboard.php')) ?>">Admin overview</a></div>
  <?php if (!$stories): ?><div class="content-empty"><span aria-hidden="true">✦</span><h2>No stories have been created yet.</h2><p>Charity stories will appear here for administrative oversight.</p></div><?php else: ?><div class="story-grid"><?php foreach ($stories as $story): ?><article class="story-card"><a class="story-card-image" href="<?= e(url('public/story.php?preview=' . (int)$story['id'])) ?>"><?= story_image_markup($story['featured_image_path'], $story['title'], '', !empty($story['featured_image_is_illustrative'])) ?></a><div class="story-card-body"><div class="story-card-meta"><span class="chip"><?= e($story['category_name']) ?></span><span class="status <?= $story['status'] === 'published' ? 'status-success' : 'status-warning' ?>"><?= e($story['status']) ?></span></div><h3><?= e($story['title']) ?></h3><p><?= e($story['excerpt']) ?></p><div class="story-byline"><strong><?= empty($story['created_by']) ? 'Giftly campaign update · ' : '' ?><?= e($story['organization_name']) ?></strong></div><div class="manage-content-actions"><a class="text-link" href="<?= e(url('public/story.php?preview=' . (int)$story['id'])) ?>">Preview</a><?php if ($story['status'] === 'published'): ?><form method="post" data-confirm="Unpublish this story?"><?= csrf_field() ?><input type="hidden" name="story_id" value="<?= (int)$story['id'] ?>"><input type="hidden" name="action" value="unpublish"><button class="button button-outline button-small" type="submit">Unpublish</button></form><?php endif; ?><form method="post" data-confirm="Permanently delete this story and its photos?"><?= csrf_field() ?><input type="hidden" name="story_id" value="<?= (int)$story['id'] ?>"><input type="hidden" name="action" value="delete"><button class="button button-danger button-small" type="submit">Delete</button></form></div></div></article><?php endforeach; ?></div><?php endif; ?>
</div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
