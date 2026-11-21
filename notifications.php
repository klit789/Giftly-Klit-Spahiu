<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$pdo = db();

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'all') {
        $stmt = $pdo->prepare('UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE user_id=?');
        $stmt->execute([$user['id']]);
    } elseif ($action === 'one') {
        $stmt = $pdo->prepare('UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE id=? AND user_id=?');
        $stmt->execute([(int)($_POST['notification_id'] ?? 0), $user['id']]);
    }
    redirect('notifications.php');
}

$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 100');
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();
$pageTitle = 'Notifications';
require __DIR__ . '/includes/header.php';
?>
<section class="dashboard">
    <div class="shell" style="max-width:880px">
        <div class="welcome-row">
            <div>
                <span class="eyebrow">Keep in the loop</span>
                <h1>Notifications</h1>
                <p>Updates about your activity and account.</p>
            </div>
            <form method="post" data-loading>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="all">
                <button class="button button-outline">Mark all read</button>
            </form>
        </div>
        <div class="panel">
            <?php if (!$notifications): ?>
                <div class="empty"><span class="empty-icon">◉</span>You’re all caught up.</div>
            <?php else: ?>
                <?php foreach ($notifications as $note): ?>
                    <article class="activity-item" style="<?= !$note['read_at'] ? 'background:#fff7f4;padding-left:10px;border-radius:10px' : '' ?>">
                        <div class="activity-icon"><?= e($note['type'] === 'achievement' ? '★' : ($note['type'] === 'challenge' ? '✦' : '♥')) ?></div>
                        <div style="flex:1">
                            <p><strong><?= e($note['title']) ?></strong></p>
                            <p class="muted"><?= e($note['message']) ?></p>
                            <time><?= format_date($note['created_at'], 'd M · H:i') ?></time>
                            <?php if ($note['link_path']): ?><br><a class="text-link" href="<?= e(url($note['link_path'])) ?>">Open →</a><?php endif; ?>
                        </div>
                        <?php if (!$note['read_at']): ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="one">
                                <input type="hidden" name="notification_id" value="<?= e($note['id']) ?>">
                                <button class="button button-small button-outline">Read</button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
