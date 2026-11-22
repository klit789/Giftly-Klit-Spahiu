<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/stories_gallery.php';
$user = require_role('admin');
$pdo = db();
if (is_post()) {
    verify_csrf();
    $id = filter_var($_POST['entry_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
    $action = (string)($_POST['action'] ?? '');
    $reason = trim((string)($_POST['reason'] ?? ''));
    $stmt = $pdo->prepare('SELECT g.*,gc.slug AS category_slug FROM happy_gallery_entries g JOIN gallery_categories gc ON gc.id=g.category_id WHERE g.id=?');
    $stmt->execute([$id]);
    $entry = $stmt->fetch();
    if (!$entry) {
        flash('error', 'That gallery submission could not be found.');
    } elseif ($action === 'approve') {
        if ($entry['removal_requested_at']) {
            flash('error', 'Resolve the open removal request before publishing this photo.');
        } elseif (!(int)$entry['is_illustrative'] && (!(int)$entry['consent_confirmed'] || !(int)$entry['guardian_consent'])) {
            flash('error', 'This photo cannot be published without consent and the child-safety confirmation.');
        } else {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE happy_gallery_entries SET moderation_status='approved',is_published=1,rejection_reason=NULL,reviewed_by=?,reviewed_at=NOW(),published_at=COALESCE(published_at,NOW()),removal_requested_at=NULL WHERE id=?");
                $stmt->execute([(int)$user['id'],$id]);
                $stmt = $pdo->prepare("INSERT INTO content_moderation_log (gallery_entry_id,moderator_id,action) VALUES (?,?,'approved')");
                $stmt->execute([$id,(int)$user['id']]);
                $pdo->commit();
                flash('success', 'Photograph approved and published.');
            } catch (Throwable $error) {
                $pdo->rollBack();
                throw $error;
            }
        }
    } elseif ($action === 'reject') {
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            flash('error', 'Add a moderation reason between 5 and 500 characters.');
        } else {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE happy_gallery_entries SET moderation_status='rejected',is_published=0,rejection_reason=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?");
                $stmt->execute([$reason,(int)$user['id'],$id]);
                $stmt = $pdo->prepare("INSERT INTO content_moderation_log (gallery_entry_id,moderator_id,action,note) VALUES (?,?,'rejected',?)");
                $stmt->execute([$id,(int)$user['id'],$reason]);
                $pdo->commit();
                flash('success', 'Submission rejected and the charity was given a reason.');
            } catch (Throwable $error) {
                $pdo->rollBack();
                throw $error;
            }
        }
    } elseif ($action === 'unpublish') {
        $stmt = $pdo->prepare("UPDATE happy_gallery_entries SET is_published=0,reviewed_by=?,reviewed_at=NOW() WHERE id=? AND moderation_status='approved'");
        $stmt->execute([(int)$user['id'],$id]);
        $log = $pdo->prepare("INSERT INTO content_moderation_log (gallery_entry_id,moderator_id,action,note) VALUES (?,?,'unpublished',?)");
        $log->execute([$id,(int)$user['id'],$reason ?: 'Unpublished by an administrator.']);
        flash('success', 'Photograph unpublished.');
    } elseif ($action === 'remove') {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE happy_gallery_entries SET moderation_status='removed',is_published=0,reviewed_by=?,reviewed_at=NOW() WHERE id=?");
            $stmt->execute([(int)$user['id'],$id]);
            $stmt = $pdo->prepare("INSERT INTO content_moderation_log (gallery_entry_id,moderator_id,action,note) VALUES (?,?,'removed',?)");
            $stmt->execute([$id,(int)$user['id'],$reason ?: 'Removed by an administrator.']);
            $pdo->commit();
            delete_content_upload($entry['image_path']);
            flash('success', 'Photograph removed from the public gallery.');
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    } else {
        http_response_code(400);
        exit('Invalid moderation action.');
    }
    redirect('admin/gallery.php');
}

$status = (string)($_GET['status'] ?? '');
$allowedStatuses = ['pending','approved','rejected','removed'];
$where = '';
$params = [];
if (in_array($status, $allowedStatuses, true)) {
    $where = ' WHERE g.moderation_status=?';
    $params[] = $status;
}
$stmt = $pdo->prepare("SELECT g.*,c.organization_name,u.name AS submitter,gc.name AS category_name,gc.slug AS category_slug,cr.title AS campaign_title FROM happy_gallery_entries g JOIN charities c ON c.id=g.charity_id LEFT JOIN users u ON u.id=g.submitted_by JOIN gallery_categories gc ON gc.id=g.category_id LEFT JOIN clothing_requests cr ON cr.id=g.campaign_id$where ORDER BY (g.removal_requested_at IS NOT NULL) DESC,FIELD(g.moderation_status,'pending','approved','rejected','removed'),g.created_at DESC");
$stmt->execute($params);
$entries = $stmt->fetchAll();
$pageTitle = 'Gallery Moderation';
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard"><div class="shell">
  <div class="welcome-row"><div><span class="eyebrow">Protect dignity, verify consent</span><h1>Gallery Moderation</h1><p>Review consent, image content and captions before anything reaches the public gallery.</p></div><a class="button button-outline" href="<?= e(url('admin/consent.php')) ?>">Consent management</a></div>
  <nav class="subnav"><a href="<?= e(url('admin/gallery.php')) ?>">All entries</a><?php foreach ($allowedStatuses as $filter): ?><a href="<?= e(url('admin/gallery.php?status=' . $filter)) ?>"><?= e(ucfirst($filter)) ?></a><?php endforeach; ?></nav>
  <?php if (!$entries): ?><div class="content-empty"><span aria-hidden="true">✓</span><h2>Nothing to review</h2><p>No gallery entries match this filter.</p></div><?php else: ?>
    <div class="moderation-grid"><?php foreach ($entries as $entry): ?>
      <article class="moderation-card"><div class="moderation-image"><?= gallery_image_markup($entry['image_path'], $entry['caption'], '', !empty($entry['is_illustrative'])) ?></div><div class="moderation-card-body"><div class="moderation-card-top"><span class="status <?= $entry['moderation_status'] === 'approved' ? 'status-success' : ($entry['moderation_status'] === 'rejected' || $entry['moderation_status'] === 'removed' ? 'status-danger' : 'status-warning') ?>"><?= e($entry['moderation_status']) ?></span><?php if ($entry['removal_requested_at']): ?><span class="status status-danger">Removal requested</span><?php endif; ?></div><h2><?= e($entry['caption']) ?></h2><p><?= $entry['is_illustrative'] ? 'Giftly illustrative content · no charity submission' : e($entry['organization_name']) ?> · <?= e($entry['category_name']) ?><?= $entry['campaign_title'] ? ' · ' . e($entry['campaign_title']) : '' ?></p><div class="consent-status"><?php if ($entry['is_illustrative']): ?><strong>Consent:</strong> Illustrative photo; not evidence of Giftly outcomes. See Consent Management for recorded releases.<?php else: ?><strong>Consent:</strong> <?= $entry['consent_confirmed'] ? 'Confirmed' : 'Missing' ?> · <strong>Child-safety confirmation:</strong> <?= $entry['guardian_consent'] ? 'Confirmed' : 'Missing' ?><?php endif; ?></div><div class="moderation-card-actions">
        <?php if ($entry['moderation_status'] === 'pending' && !$entry['removal_requested_at']): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="entry_id" value="<?= (int)$entry['id'] ?>"><input type="hidden" name="action" value="approve"><button class="button button-small" type="submit">Approve & publish</button></form>
          <form method="post" class="moderation-reject-form"><?= csrf_field() ?><input type="hidden" name="entry_id" value="<?= (int)$entry['id'] ?>"><input type="hidden" name="action" value="reject"><input class="input" name="reason" maxlength="500" required placeholder="Reason for rejection"><button class="button button-danger button-small" type="submit">Reject</button></form>
        <?php elseif ($entry['moderation_status'] === 'approved' && $entry['is_published']): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="entry_id" value="<?= (int)$entry['id'] ?>"><input type="hidden" name="action" value="unpublish"><button class="button button-outline button-small" type="submit">Unpublish</button></form>
        <?php endif; ?>
        <?php if ($entry['moderation_status'] !== 'removed'): ?><form method="post" data-confirm="Remove this photograph from public access?"><?= csrf_field() ?><input type="hidden" name="entry_id" value="<?= (int)$entry['id'] ?>"><input type="hidden" name="action" value="remove"><button class="button button-danger button-small" type="submit">Remove image</button></form><?php endif; ?>
      </div><?php if ($entry['rejection_reason']): ?><p class="moderation-reason"><strong>Previous review:</strong> <?= e($entry['rejection_reason']) ?></p><?php endif; ?></div></article>
    <?php endforeach; ?></div>
  <?php endif; ?>
</div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
