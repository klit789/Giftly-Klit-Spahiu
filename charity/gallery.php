<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/stories_gallery.php';
$user = require_role('charity');
$pdo = db();
$charityId = (int)$user['charity_id'];
$categories = gallery_categories($pdo);
$campaigns = charity_campaigns($pdo, $charityId);

if (is_post()) {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $id = filter_var($_POST['entry_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
    if ($action === 'delete') {
        $stmt = $pdo->prepare('SELECT image_path FROM happy_gallery_entries WHERE id = ? AND charity_id = ? AND is_illustrative = 0');
        $stmt->execute([$id, $charityId]);
        $path = $stmt->fetchColumn();
        if (!$path) {
            flash('error', 'That gallery photo does not belong to your organisation.');
        } else {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE happy_gallery_entries SET moderation_status='removed', is_published=0, removal_requested_at=COALESCE(removal_requested_at,NOW()) WHERE id=? AND charity_id=?");
                $stmt->execute([$id, $charityId]);
                $log = $pdo->prepare("INSERT INTO content_moderation_log (gallery_entry_id,moderator_id,action,note) VALUES (?,?,'removed','Removed by the owning charity.')");
                $log->execute([$id,(int)$user['id']]);
                $pdo->commit();
                delete_content_upload((string)$path);
                flash('success', 'The photo has been removed from the gallery.');
            } catch (Throwable $error) {
                $pdo->rollBack();
                throw $error;
            }
        }
        redirect('charity/gallery.php');
    }

    $categoryId = filter_var($_POST['category_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
    $campaignId = filter_var($_POST['campaign_id'] ?? 0, FILTER_VALIDATE_INT) ?: null;
    $caption = trim((string)($_POST['caption'] ?? ''));
    $location = trim((string)($_POST['location_safe'] ?? ''));
    $confirmed = ($_POST['consent_confirmed'] ?? '') === '1';
    $guardian = ($_POST['guardian_consent'] ?? '') === '1';
    $errors = [];
    if (!array_filter($categories, static fn ($category): bool => (int)$category['id'] === $categoryId)) $errors[] = 'Choose a valid gallery category.';
    if (mb_strlen($caption) < 5 || mb_strlen($caption) > 500) $errors[] = 'Write a caption between 5 and 500 characters.';
    if ($location !== '' && !preg_match('/\A[\p{L}\p{M} .,\x27-]{1,100}\z/u', $location)) $errors[] = 'Location must be a broad city or region only; do not enter addresses or postcodes.';
    if ($campaignId && !charity_owns_campaign($pdo, (int)$campaignId, $charityId)) $errors[] = 'Choose one of your own donation campaigns.';
    if (!$confirmed) $errors[] = 'Confirm you have informed consent from every identifiable person pictured.';
    if (!$guardian) $errors[] = 'Confirm that no children are pictured, or that appropriate guardian consent has been obtained for every child pictured.';

    $pathStmt = $pdo->prepare('SELECT image_path FROM happy_gallery_entries WHERE id=? AND charity_id=? AND is_illustrative=0');
    $pathStmt->execute([$id,$charityId]);
    $oldPath = $id ? $pathStmt->fetchColumn() : false;
    if ($id && !$oldPath) $errors[] = 'That gallery photo does not belong to your organisation.';
    $newPath = null;
    try {
        $newPath = content_upload_image($_FILES['image'] ?? [], 'gallery');
        if (!$id && !$newPath) $errors[] = 'Choose a photograph to submit.';
    } catch (RuntimeException $error) {
        $errors[] = $error->getMessage();
    }
    $imagePath = $newPath ?: (string)$oldPath;

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE happy_gallery_entries SET campaign_id=?,category_id=?,image_path=?,caption=?,location_safe=?,consent_confirmed=1,guardian_consent=1,is_illustrative=0,moderation_status='pending',is_published=0,rejection_reason=NULL,reviewed_by=NULL,reviewed_at=NULL,published_at=NULL,removal_requested_at=NULL WHERE id=? AND charity_id=?");
                $stmt->execute([$campaignId ?: null,$categoryId,$imagePath,$caption,$location ?: null,$id,$charityId]);
                $entryId = $id;
            } else {
                $stmt = $pdo->prepare("INSERT INTO happy_gallery_entries (charity_id,submitted_by,campaign_id,category_id,image_path,caption,location_safe,consent_confirmed,guardian_consent) VALUES (?,?,?,?,?,?,?,1,1)");
                $stmt->execute([$charityId,(int)$user['id'],$campaignId ?: null,$categoryId,$imagePath,$caption,$location ?: null]);
                $entryId = (int)$pdo->lastInsertId();
            }
            $consent = $pdo->prepare('INSERT INTO happy_gallery_consents (gallery_entry_id,confirmed_by,consent_type,statement_version) VALUES (?,?,?,?)');
            $consent->execute([$entryId,(int)$user['id'],'pictured_people','2026-01']);
            $consent->execute([$entryId,(int)$user['id'],'child_safety_attestation','2026-01']);
            $pdo->commit();
            if ($newPath && $oldPath) delete_content_upload((string)$oldPath);
            flash('success', 'Your photo was submitted for administrator review and will stay private until approved.');
            redirect('charity/gallery.php');
        } catch (Throwable $error) {
            $pdo->rollBack();
            if ($newPath) delete_content_upload($newPath);
            throw $error;
        }
    }
    if ($newPath) delete_content_upload($newPath);
    $formError = implode(' ', $errors);
}

$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: 0;
$editingEntry = null;
if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM happy_gallery_entries WHERE id=? AND charity_id=? AND is_illustrative=0 AND moderation_status <> \'removed\'');
    $stmt->execute([$editId,$charityId]);
    $editingEntry = $stmt->fetch() ?: null;
}
$stmt = $pdo->prepare("SELECT g.*,gc.name AS category_name,cr.title AS campaign_title FROM happy_gallery_entries g JOIN gallery_categories gc ON gc.id=g.category_id LEFT JOIN clothing_requests cr ON cr.id=g.campaign_id WHERE g.charity_id=? AND g.is_illustrative=0 ORDER BY g.created_at DESC");
$stmt->execute([$charityId]);
$entries = $stmt->fetchAll();
$pageTitle = 'Manage Gallery';
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard"><div class="shell">
  <div class="welcome-row"><div><span class="eyebrow">Share joyful moments with care</span><h1>Manage Gallery</h1><p>Photos stay private until an administrator approves them.</p></div><a class="button button-outline" href="<?= e(url('public/gallery.php')) ?>">View public gallery</a></div>
  <div class="content-manage-layout">
    <section class="panel content-editor-panel"><div class="panel-heading"><h2><?= $editingEntry ? 'Edit photo' : 'Submit a photo' ?></h2></div>
      <?php if (!empty($formError)): ?><div class="flash flash-error"><span><?= e($formError) ?></span></div><?php endif; ?>
      <form method="post" enctype="multipart/form-data" class="content-form" data-loading>
        <?= csrf_field() ?><input type="hidden" name="entry_id" value="<?= (int)($editingEntry['id'] ?? 0) ?>"><input type="hidden" name="action" value="save">
        <div class="field"><label for="gallery-image">Photograph</label><?php if ($editingEntry): ?><div class="content-current-image"><?= gallery_image_markup($editingEntry['image_path'], $editingEntry['caption'], '', !empty($editingEntry['is_illustrative'])) ?></div><?php endif; ?><input class="input" type="file" id="gallery-image" name="image" accept="image/jpeg,image/png,image/webp" <?= $editingEntry ? '' : 'required' ?>><p class="help">JPG, PNG or WebP, up to 5 MB.</p></div>
        <div class="field"><label for="caption">Caption</label><textarea class="textarea" id="caption" name="caption" maxlength="500" required><?= e($_POST['caption'] ?? $editingEntry['caption'] ?? '') ?></textarea><p class="help">Avoid names and identifying personal details.</p></div>
        <div class="field"><label for="gallery-category">Category</label><select class="select" id="gallery-category" name="category_id" required><option value="">Choose category</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= (int)($_POST['category_id'] ?? $editingEntry['category_id'] ?? 0) === (int)$category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="campaign">Related campaign <small>(optional)</small></label><select class="select" id="campaign" name="campaign_id"><option value="">No campaign</option><?php foreach ($campaigns as $campaign): ?><option value="<?= (int)$campaign['id'] ?>" <?= (int)($_POST['campaign_id'] ?? $editingEntry['campaign_id'] ?? 0) === (int)$campaign['id'] ? 'selected' : '' ?>><?= e($campaign['title']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="safe-location">Broad location <small>(optional)</small></label><input class="input" id="safe-location" name="location_safe" maxlength="100" placeholder="City or region only" value="<?= e($_POST['location_safe'] ?? $editingEntry['location_safe'] ?? '') ?>"></div>
        <label class="consent-check"><input type="checkbox" name="consent_confirmed" value="1" required <?= ($_POST['consent_confirmed'] ?? '') === '1' ? 'checked' : '' ?>><span>I confirm informed consent has been obtained from everyone identifiable in this image. I have not included private information.</span></label>
        <label class="consent-check guardian-consent"><input type="checkbox" name="guardian_consent" value="1" required <?= ($_POST['guardian_consent'] ?? '') === '1' ? 'checked' : '' ?>><span>I confirm no children are pictured, or appropriate guardian consent has been obtained for every child pictured and sharing the image is in their best interests.</span></label>
        <p class="privacy-note">You may request removal at any time. Children’s images require guardian consent and additional admin review.</p>
        <button class="button" type="submit"><?= $editingEntry ? 'Resubmit for review' : 'Submit for approval' ?> →</button>
      </form>
    </section>
    <section class="panel"><div class="panel-heading"><div><span class="eyebrow">Your submissions</span><h2>Photo status</h2></div></div>
      <?php if (!$entries): ?><div class="content-empty content-empty-small"><span aria-hidden="true">▧</span><p>Your gallery submissions will appear here.</p></div><?php else: ?>
        <div class="gallery-manage-list"><?php foreach ($entries as $entry): ?>
          <article class="gallery-manage-item"><div class="gallery-manage-image"><?= gallery_image_markup($entry['image_path'], $entry['caption'], '', !empty($entry['is_illustrative'])) ?></div><div class="gallery-manage-copy"><span class="status <?= $entry['moderation_status'] === 'approved' ? 'status-success' : ($entry['moderation_status'] === 'rejected' ? 'status-danger' : 'status-warning') ?>"><?= e($entry['moderation_status']) ?><?= $entry['is_published'] ? ' · public' : '' ?></span><h3><?= e($entry['caption']) ?></h3><p><?= e($entry['category_name']) ?><?= $entry['campaign_title'] ? ' · ' . e($entry['campaign_title']) : '' ?></p><?php if ($entry['rejection_reason']): ?><p class="moderation-reason"><?= e($entry['rejection_reason']) ?></p><?php endif; ?></div><div class="gallery-manage-actions"><?php if ($entry['moderation_status'] !== 'removed'): ?><a class="text-link" href="<?= e(url('charity/gallery.php?edit=' . (int)$entry['id'])) ?>">Edit</a><form method="post" data-confirm="Remove this photograph from the public gallery?"><?= csrf_field() ?><input type="hidden" name="entry_id" value="<?= (int)$entry['id'] ?>"><input type="hidden" name="action" value="delete"><button class="button button-danger button-small" type="submit">Delete</button></form><?php else: ?><span>Removed</span><?php endif; ?></div></article>
        <?php endforeach; ?></div>
      <?php endif; ?>
    </section>
  </div>
</div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
