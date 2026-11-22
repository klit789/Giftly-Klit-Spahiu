<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/stories_gallery.php';
$pdo = db();
$entryId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'entry_id', FILTER_VALIDATE_INT) ?: 0;
$entry = null;
if ($entryId) {
    $stmt = $pdo->prepare(gallery_public_sql() . ' AND g.id = ?');
    $stmt->execute([$entryId]);
    $entry = $stmt->fetch() ?: null;
}
$errors = [];
$sent = false;
if (is_post()) {
    verify_csrf();
    $reason = trim((string)($_POST['reason'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    if (!$entry) $errors[] = 'That photograph is no longer available.';
    if (mb_strlen($reason) < 10 || mb_strlen($reason) > 1000) $errors[] = 'Please describe the removal request in 10–1,000 characters.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid contact email or leave it blank.';
    $recentRequests = (int)($_SESSION['content_removal_request_count'] ?? 0);
    $windowStart = (int)($_SESSION['content_removal_request_window'] ?? 0);
    if ($windowStart < time() - 3600) {
        $recentRequests = 0;
        $windowStart = time();
    }
    if ($recentRequests >= 5) $errors[] = 'Please wait before submitting another removal request.';
    if (!$errors) {
        $stmt = $pdo->prepare("INSERT INTO content_removal_requests (gallery_entry_id,requester_email,reason,was_published) VALUES (?,?,?,?)");
        $stmt->execute([$entryId,$email ?: null,$reason,(int)$entry['is_published']]);
        $stmt = $pdo->prepare('UPDATE happy_gallery_entries SET removal_requested_at=NOW(),is_published=0 WHERE id=?');
        $stmt->execute([$entryId]);
        $_SESSION['content_removal_request_count'] = $recentRequests + 1;
        $_SESSION['content_removal_request_window'] = $windowStart;
        $sent = true;
    }
}
$pageTitle = 'Request photo removal';
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard"><div class="shell removal-request-shell">
  <div class="welcome-row"><div><span class="eyebrow">Respect and care come first</span><h1>Request photo removal</h1><p>Anyone pictured—or a parent or guardian—can ask us to remove a gallery photograph.</p></div></div>
  <div class="panel removal-request-panel">
    <?php if ($sent): ?><div class="content-empty"><span aria-hidden="true">✓</span><h2>Request received</h2><p>The photograph has been taken off public display while an administrator reviews your request.</p><a class="button" href="<?= e(url('public/gallery.php')) ?>">Back to gallery</a></div>
    <?php elseif (!$entry): ?><div class="content-empty"><h2>Photo unavailable</h2><p>This photograph is not currently published.</p><a class="button button-outline" href="<?= e(url('public/gallery.php')) ?>">Back to gallery</a></div>
    <?php else: ?>
      <div class="removal-request-preview"><?= gallery_image_markup($entry['image_path'], $entry['caption'], '', !empty($entry['is_illustrative'])) ?><p><?= e($entry['caption']) ?> · <?= $entry['is_illustrative'] ? 'Giftly illustrative image' : e($entry['organization_name']) ?></p></div>
      <?php if ($errors): ?><div class="flash flash-error"><span><?= e(implode(' ', $errors)) ?></span></div><?php endif; ?>
      <form class="content-form" method="post"><?= csrf_field() ?><input type="hidden" name="entry_id" value="<?= (int)$entryId ?>"><div class="field"><label for="reason">Why should this photograph be removed?</label><textarea class="textarea" id="reason" name="reason" maxlength="1000" required placeholder="For example, I am pictured and no longer consent to this photo being shared."></textarea></div><div class="field"><label for="email">Contact email <small>(optional)</small></label><input class="input" type="email" id="email" name="email" maxlength="190" placeholder="Only used if we need to follow up"></div><button class="button" type="submit">Submit removal request</button></form>
    <?php endif; ?>
  </div>
</div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
