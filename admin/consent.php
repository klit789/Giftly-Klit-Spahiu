<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/stories_gallery.php';
$user = require_role('admin');
$pdo = db();
if (is_post()) {
    verify_csrf();
    $requestId = filter_var($_POST['request_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
    $action = (string)($_POST['action'] ?? '');
    $note = trim((string)($_POST['resolution_note'] ?? ''));
    if (!in_array($action, ['resolve','decline'], true) || mb_strlen($note) > 500) {
        http_response_code(400);
        exit('Invalid removal review action.');
    }
    $stmt = $pdo->prepare("SELECT * FROM content_removal_requests WHERE id=? AND status='open'");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();
    if (!$request) {
        flash('error', 'That open removal request could not be found.');
    } else {
        $pdo->beginTransaction();
        try {
            $status = $action === 'resolve' ? 'resolved' : 'declined';
            $stmt = $pdo->prepare('UPDATE content_removal_requests SET status=?,resolved_by=?,resolved_at=NOW(),resolution_note=? WHERE id=?');
            $stmt->execute([$status,(int)$user['id'],$note ?: null,$requestId]);
            $imagePath = null;
            if ($request['gallery_entry_id']) {
                if ($action === 'resolve') {
                    $stmt = $pdo->prepare("SELECT image_path FROM happy_gallery_entries WHERE id=?");
                    $stmt->execute([(int)$request['gallery_entry_id']]);
                    $imagePath = $stmt->fetchColumn() ?: null;
                    $stmt = $pdo->prepare("UPDATE happy_gallery_entries SET moderation_status='removed',is_published=0,reviewed_by=?,reviewed_at=NOW() WHERE id=?");
                    $stmt->execute([(int)$user['id'],(int)$request['gallery_entry_id']]);
                    $logAction = 'removed';
                } else {
                    $stmt = $pdo->prepare("UPDATE happy_gallery_entries SET is_published=IF(moderation_status='approved',?,0),removal_requested_at=NULL WHERE id=?");
                    $stmt->execute([(int)$request['was_published'],(int)$request['gallery_entry_id']]);
                    $logAction = 'removal_requested';
                }
                $stmt = $pdo->prepare('INSERT INTO content_moderation_log (gallery_entry_id,moderator_id,action,note) VALUES (?,?,?,?)');
                $stmt->execute([(int)$request['gallery_entry_id'],(int)$user['id'],$logAction,$note ?: null]);
            }
            $pdo->commit();
            if ($imagePath) delete_content_upload($imagePath);
            flash('success', $action === 'resolve' ? 'Removal request resolved; the photo is no longer available.' : 'Removal request declined.');
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }
    redirect('admin/consent.php');
}

$consentStmt = $pdo->query("SELECT g.id,g.caption,g.image_path,g.is_illustrative,g.moderation_status,g.consent_confirmed,g.guardian_consent,g.created_at,
    c.organization_name,gc.name AS category_name,gc.slug AS category_slug,u.name AS submitted_by,
    GROUP_CONCAT(DISTINCT CONCAT(co.consent_type, ' · ', DATE_FORMAT(co.confirmed_at,'%d %b %Y')) ORDER BY co.confirmed_at DESC SEPARATOR '; ') AS consent_history
    FROM happy_gallery_entries g
    JOIN charities c ON c.id=g.charity_id
    JOIN gallery_categories gc ON gc.id=g.category_id
    LEFT JOIN users u ON u.id=g.submitted_by
    LEFT JOIN happy_gallery_consents co ON co.gallery_entry_id=g.id
    GROUP BY g.id ORDER BY g.created_at DESC");
$consents = $consentStmt->fetchAll();
$requests = $pdo->query("SELECT r.*,g.caption,g.image_path,c.organization_name FROM content_removal_requests r LEFT JOIN happy_gallery_entries g ON g.id=r.gallery_entry_id LEFT JOIN charities c ON c.id=g.charity_id WHERE r.status='open' ORDER BY r.created_at ASC")->fetchAll();
$pageTitle = 'Consent Management';
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard"><div class="shell">
  <div class="welcome-row"><div><span class="eyebrow">Consent and removal records</span><h1>Consent Management</h1><p>Review consent audit history and prioritize requests to remove photographs.</p></div><a class="button button-outline" href="<?= e(url('admin/gallery.php')) ?>">Gallery moderation</a></div>
  <section class="panel consent-requests-panel"><div class="panel-heading"><div><span class="eyebrow">Privacy requests</span><h2>Open removal requests <span class="chip chip-sun"><?= count($requests) ?></span></h2></div></div>
    <?php if (!$requests): ?><div class="empty">There are no open photo removal requests.</div><?php else: ?><div class="consent-request-list"><?php foreach ($requests as $request): ?><article class="consent-request-item"><div><?php if ($request['image_path']): ?><?= gallery_image_markup($request['image_path'], $request['caption'] ?: 'Removal request photo') ?><?php endif; ?></div><section><strong><?= e($request['organization_name'] ?: 'Unavailable photograph') ?></strong><p><?= e($request['caption'] ?: 'This photograph is no longer available.') ?></p><small><?= e(format_date($request['created_at'])) ?><?= $request['requester_email'] ? ' · ' . e($request['requester_email']) : '' ?></small><p class="consent-request-reason"><?= e($request['reason']) ?></p><form method="post" class="consent-resolution-form"><?= csrf_field() ?><input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>"><input class="input" name="resolution_note" maxlength="500" placeholder="Optional note for the record"><button class="button button-small" name="action" value="resolve" type="submit">Approve removal</button><button class="button button-outline button-small" name="action" value="decline" type="submit">Decline</button></form></section></article><?php endforeach; ?></div><?php endif; ?>
  </section>
  <section class="panel consent-audit-panel"><div class="panel-heading"><div><span class="eyebrow">Submission audit</span><h2>Consent records</h2></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Photo / charity</th><th>Category</th><th>Subject consent</th><th>Child-safety confirmation</th><th>Consent history</th><th>Status</th></tr></thead><tbody><?php foreach ($consents as $entry): $history = (string)($entry['consent_history'] ?? ''); ?><tr><td><strong><?= e($entry['caption']) ?></strong><br><span class="muted"><?= $entry['is_illustrative'] ? 'Giftly illustrative content' : e($entry['organization_name']) ?></span></td><td><?= e($entry['category_name']) ?></td><td><?= $entry['is_illustrative'] ? (strpos($history, 'pictured_people') !== false ? 'Confirmed for illustrative photo' : 'Not applicable') : ($entry['consent_confirmed'] ? 'Confirmed' : 'Missing') ?></td><td><?= $entry['is_illustrative'] ? (strpos($history, 'guardian_for_child') !== false ? 'Confirmed' : 'Not applicable') : ($entry['guardian_consent'] ? 'Confirmed' : 'Missing') ?></td><td><?= e($history ?: ($entry['is_illustrative'] ? 'No consent audit events recorded.' : 'No audit record')) ?></td><td><span class="status <?= $entry['moderation_status'] === 'approved' ? 'status-success' : 'status-warning' ?>"><?= e($entry['moderation_status']) ?></span></td></tr><?php endforeach; ?></tbody></table></div></section>
</div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
