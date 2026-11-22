<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/stories_gallery.php';
$user = require_role('charity');
$pdo = db();
$charityId = (int)$user['charity_id'];
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
$editing = $id > 0;
$categories = story_categories($pdo);
$campaigns = charity_campaigns($pdo, $charityId);
$errors = [];
$publicationDate = date('Y-m-d');
$existingFeaturedPath = '';
$story = ['title'=>'','excerpt'=>'','description'=>'','category_id'=>0,'campaign_id'=>'','featured_image_path'=>'','featured_alt'=>'','quote'=>'','status'=>'draft','impact_stats_json'=>''];
$images = [];
if ($editing) {
    $stmt = $pdo->prepare('SELECT * FROM change_stories WHERE id = ? AND charity_id = ?');
    $stmt->execute([$id, $charityId]);
    $storedStory = $stmt->fetch();
    if (!$storedStory) {
        flash('error', 'That story does not belong to your organisation.');
        redirect('charity/stories.php');
    }
    $story = $storedStory;
    $existingFeaturedPath = (string)$storedStory['featured_image_path'];
    if ($story['published_at']) $publicationDate = date('Y-m-d', strtotime($story['published_at']));
    $imageStmt = $pdo->prepare('SELECT * FROM change_story_images WHERE story_id = ? ORDER BY sort_order, id');
    $imageStmt->execute([$id]);
    $images = $imageStmt->fetchAll();
}

if (is_post()) {
    verify_csrf();
    $story = [
        'title' => trim((string)($_POST['title'] ?? '')),
        'excerpt' => trim((string)($_POST['excerpt'] ?? '')),
        'description' => trim((string)($_POST['description'] ?? '')),
        'category_id' => filter_var($_POST['category_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0,
        'campaign_id' => filter_var($_POST['campaign_id'] ?? 0, FILTER_VALIDATE_INT) ?: null,
        'featured_image_path' => $story['featured_image_path'] ?? '',
        'quote' => trim((string)($_POST['quote'] ?? '')),
        'status' => ($_POST['action'] ?? '') === 'publish' ? 'published' : 'draft',
        'impact_stats_json' => '',
    ];
    $publicationDate = trim((string)($_POST['publication_date'] ?? ''));
    $dateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $publicationDate);
    if (!$dateValue || $dateValue->format('Y-m-d') !== $publicationDate) $errors[] = 'Choose a valid publication date.';
    $story['featured_alt'] = trim((string)($_POST['featured_alt'] ?? ''));
    if (mb_strlen($story['title']) < 5 || mb_strlen($story['title']) > 180) $errors[] = 'Use a story title between 5 and 180 characters.';
    if (mb_strlen($story['excerpt']) < 20 || mb_strlen($story['excerpt']) > 400) $errors[] = 'Write a short description between 20 and 400 characters.';
    if (mb_strlen($story['description']) < 40 || mb_strlen($story['description']) > 30000) $errors[] = 'The story must be between 40 and 30,000 characters.';
    if (!array_filter($categories, static fn ($category): bool => (int)$category['id'] === (int)$story['category_id'])) $errors[] = 'Choose a valid story category.';
    if ($story['campaign_id'] && !charity_owns_campaign($pdo, (int)$story['campaign_id'], $charityId)) $errors[] = 'Choose one of your own donation campaigns.';
    if (mb_strlen($story['quote']) > 500) $errors[] = 'Keep the quote to 500 characters or fewer.';
    if (mb_strlen($story['featured_alt']) > 180) $errors[] = 'Keep the image description to 180 characters or fewer.';

    $statLabels = $_POST['impact_label'] ?? [];
    $statValues = $_POST['impact_value'] ?? [];
    $impactStats = [];
    if (is_array($statLabels) && is_array($statValues)) {
        foreach ($statLabels as $index => $label) {
            $label = trim((string)$label);
            $value = trim((string)($statValues[$index] ?? ''));
            if ($label === '' && $value === '') continue;
            if ($label === '' || $value === '' || mb_strlen($label) > 80 || mb_strlen($value) > 40 || count($impactStats) >= 3) {
                $errors[] = 'Impact statistics need a label and value (up to three).';
                break;
            }
            $impactStats[] = ['label'=>$label, 'value'=>$value];
        }
    }
    $story['impact_stats_json'] = json_encode($impactStats, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    $newMain = null;
    $newImages = [];
    try {
        $newMain = content_upload_image($_FILES['featured_image'] ?? [], 'stories');
        $newImages = content_upload_images($_FILES['additional_images'] ?? [], 'stories');
        if ($newMain !== null) $story['featured_image_path'] = $newMain;
        if (!$story['featured_image_path']) $errors[] = 'Choose a featured image for this story.';
    } catch (RuntimeException $error) {
        $errors[] = $error->getMessage();
    }

    $removeIds = array_values(array_filter(array_map('intval', is_array($_POST['remove_image'] ?? null) ? $_POST['remove_image'] : [])));
    if (!$errors) {
        $pdo->beginTransaction();
        try {
            if ($editing) {
                $stmt = $pdo->prepare("UPDATE change_stories SET campaign_id=?, category_id=?, title=?, excerpt=?, description=?, featured_image_path=?, featured_alt=?, featured_image_is_illustrative=IF(? IS NULL,featured_image_is_illustrative,0), quote=?, impact_stats_json=?, status=?, published_at=IF(?='published',?,published_at) WHERE id=? AND charity_id=?");
                $stmt->execute([$story['campaign_id'] ?: null,$story['category_id'],$story['title'],$story['excerpt'],$story['description'],$story['featured_image_path'],$story['featured_alt'] ?: null,$newMain,$story['quote'] ?: null,$story['impact_stats_json'],$story['status'],$story['status'],$publicationDate . ' 00:00:00',$id,$charityId]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO change_stories (charity_id,created_by,campaign_id,category_id,title,slug,excerpt,description,featured_image_path,featured_alt,quote,impact_stats_json,status,published_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,IF(?='published',?,NULL))");
                $stmt->execute([$charityId,(int)$user['id'],$story['campaign_id'] ?: null,$story['category_id'],$story['title'],content_slug($story['title']),$story['excerpt'],$story['description'],$story['featured_image_path'],$story['featured_alt'] ?: null,$story['quote'] ?: null,$story['impact_stats_json'],$story['status'],$story['status'],$publicationDate . ' 00:00:00']);
                $id = (int)$pdo->lastInsertId();
            }
            $removedPaths = [];
            if ($removeIds) {
                $marks = implode(',', array_fill(0, count($removeIds), '?'));
                $stmt = $pdo->prepare("SELECT id,image_path FROM change_story_images WHERE story_id=? AND id IN ($marks)");
                $stmt->execute(array_merge([$id], $removeIds));
                $removedPaths = array_column($stmt->fetchAll(), 'image_path');
                $stmt = $pdo->prepare("DELETE FROM change_story_images WHERE story_id=? AND id IN ($marks)");
                $stmt->execute(array_merge([$id], $removeIds));
            }
            $alt = $story['featured_alt'] ?: null;
            $stmt = $pdo->prepare('INSERT INTO change_story_images (story_id,image_path,alt_text,sort_order) VALUES (?,?,?,?)');
            foreach ($newImages as $index => $path) $stmt->execute([$id,$path,$alt,100 + $index]);
            log_activity($pdo, $editing ? 'updated a change story' : 'created a change story', (int)$user['id'], 'story', $id);
            $pdo->commit();
            foreach ($removedPaths as $path) delete_content_upload($path);
            if ($newMain && $existingFeaturedPath) delete_content_upload($existingFeaturedPath);
            flash('success', $story['status'] === 'published' ? 'Your story has been published.' : 'Your story was saved as a draft.');
            redirect('charity/stories.php');
        } catch (Throwable $error) {
            $pdo->rollBack();
            if ($newMain) delete_content_upload($newMain);
            foreach ($newImages as $path) delete_content_upload($path);
            $errors[] = 'We could not save the story. Please try again.';
        }
    } else {
        if ($newMain) delete_content_upload($newMain);
        foreach ($newImages as $path) delete_content_upload($path);
        $story['featured_image_path'] = $existingFeaturedPath;
    }
}
$pageTitle = $editing ? 'Edit story' : 'Write a story';
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard"><div class="shell content-form-shell">
  <div class="welcome-row"><div><span class="eyebrow">Stories of Change</span><h1><?= $editing ? 'Edit your story' : 'Write a new story' ?></h1><p>Share only truthful, dignified accounts. Never include private information.</p></div><a class="button button-outline" href="<?= e(url('charity/stories.php')) ?>">Back to stories</a></div>
  <div class="panel"><?php if ($errors): ?><div class="flash flash-error"><span><?= e(implode(' ', $errors)) ?></span></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="content-form" data-loading>
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
      <div class="form-grid">
        <div class="field field-full"><label for="title">Story title</label><input class="input" id="title" name="title" maxlength="180" required value="<?= e($story['title']) ?>"></div>
        <div class="field field-full"><label for="excerpt">Short introduction</label><textarea class="textarea" id="excerpt" name="excerpt" maxlength="400" required><?= e($story['excerpt']) ?></textarea><p class="help">This appears on story cards (20–400 characters).</p></div>
        <div class="field field-full"><label for="description">Full story</label><textarea class="textarea content-story-textarea" id="description" name="description" maxlength="30000" required><?= e($story['description']) ?></textarea><p class="help">Please protect people’s privacy and describe outcomes with care.</p></div>
        <div class="field"><label for="category_id">Category</label><select class="select" id="category_id" name="category_id" required><option value="">Choose a category</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= (int)$story['category_id'] === (int)$category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="publication_date">Publication date</label><input class="input" type="date" id="publication_date" name="publication_date" required value="<?= e($publicationDate) ?>"></div>
        <div class="field"><label for="campaign_id">Related donation campaign <small>(optional)</small></label><select class="select" id="campaign_id" name="campaign_id"><option value="">No campaign selected</option><?php foreach ($campaigns as $campaign): ?><option value="<?= (int)$campaign['id'] ?>" <?= (int)($story['campaign_id'] ?? 0) === (int)$campaign['id'] ? 'selected' : '' ?>><?= e($campaign['title']) ?></option><?php endforeach; ?></select></div>
        <div class="field field-full"><label for="featured_image">Featured image</label><?php if ($story['featured_image_path']): ?><div class="content-current-image"><?= story_image_markup($story['featured_image_path'], $story['title'], '', !empty($story['featured_image_is_illustrative'])) ?></div><?php endif; ?><input class="input" id="featured_image" type="file" name="featured_image" accept="image/jpeg,image/png,image/webp" <?= $editing ? '' : 'required' ?>><p class="help">JPG, PNG or WebP, up to 5 MB. No AI-generated images presented as real outcomes.</p></div>
        <div class="field"><label for="featured_alt">Featured image description</label><input class="input" id="featured_alt" name="featured_alt" maxlength="180" value="<?= e($story['featured_alt'] ?? '') ?>" placeholder="Describe the image for accessibility"></div>
        <div class="field"><label for="quote">Short quote <small>(optional)</small></label><input class="input" id="quote" name="quote" maxlength="500" value="<?= e($story['quote'] ?? '') ?>"></div>
        <div class="field field-full"><label>Additional photos <small>(optional, up to 8 per save)</small></label><input class="input" type="file" name="additional_images[]" accept="image/jpeg,image/png,image/webp" multiple><p class="help">Only upload photos you have permission to publish.</p></div>
        <?php if ($images): ?><div class="field field-full"><label>Existing additional photos</label><div class="content-photo-manage"><?php foreach ($images as $image): ?><label class="content-photo-item"><?= story_image_markup($image['image_path'], $image['alt_text'] ?: $story['title']) ?><span><input type="checkbox" name="remove_image[]" value="<?= (int)$image['id'] ?>"> Remove photo</span></label><?php endforeach; ?></div></div><?php endif; ?>
        <div class="field field-full"><label>Optional impact statistics <small>(up to 3)</small></label><div class="impact-stat-inputs"><?php $statRows = safe_impact_stats((string)($story['impact_stats_json'] ?? '')); for ($i=0; $i<3; $i++): ?><div><input class="input" name="impact_label[]" maxlength="80" placeholder="e.g. Coats received" value="<?= e($statRows[$i]['label'] ?? '') ?>"><input class="input" name="impact_value[]" maxlength="40" placeholder="e.g. 120" value="<?= e($statRows[$i]['value'] ?? '') ?>"></div><?php endfor; ?></div><p class="help">Charity-reported figures are labelled separately from confirmed campaign receipts.</p></div>
      </div>
      <div class="form-actions"><a class="button button-outline" href="<?= e(url('charity/stories.php')) ?>">Cancel</a><?php if ($editing): ?><a class="button button-outline" href="<?= e(url('public/story.php?preview=' . (int)$id)) ?>">Preview</a><?php endif; ?><button class="button button-dark" name="action" value="draft" type="submit">Save draft</button><button class="button" name="action" value="publish" type="submit">Publish story →</button></div>
    </form>
  </div>
</div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
