<?php
declare(strict_types=1);

/** Cross-cutting impact, reward and notification services. Call inside the existing transaction where possible. */
function create_notification(PDO $pdo, int $userId, string $type, string $title, string $message, ?string $link = null): void
{
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id,type,title,message,link_path) VALUES (?,?,?,?,?)');
    $stmt->execute([$userId, $type, $title, $message, $link]);
}

function unread_notifications(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND read_at IS NULL');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function donor_impact(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT
        COALESCE((SELECT SUM(points) FROM points_transactions WHERE user_id=?),0) points,
        COALESCE((SELECT SUM(di.quantity) FROM donation_items di JOIN donation_pledges dp ON dp.id=di.pledge_id WHERE dp.donor_id=? AND dp.status='received'),0) items,
        (SELECT COUNT(*) FROM donation_pledges WHERE donor_id=? AND status='received') donations,
        (SELECT COUNT(*) FROM user_achievements WHERE user_id=?) achievements");
    $stmt->execute([$userId,$userId,$userId,$userId]);
    return $stmt->fetch() ?: ['points'=>0,'items'=>0,'donations'=>0,'achievements'=>0];
}

function update_achievements(PDO $pdo, int $userId): array
{
    $impact = donor_impact($pdo, $userId);
    $winterStmt = $pdo->prepare("SELECT COALESCE(SUM(di.quantity),0) FROM donation_items di JOIN donation_pledges dp ON dp.id=di.pledge_id JOIN clothing_requests cr ON cr.id=dp.request_id WHERE dp.donor_id=? AND dp.status='received' AND cr.season='Winter'");
    $winterStmt->execute([$userId]);
    $metrics = ['completed_donations'=>(int)$impact['donations'], 'items_donated'=>(int)$impact['items'], 'winter_items'=>(int)$winterStmt->fetchColumn()];
    $achievements = $pdo->query('SELECT * FROM achievements WHERE is_active=1')->fetchAll();
    $earned = [];
    $insert = $pdo->prepare('INSERT IGNORE INTO user_achievements (user_id,achievement_id) VALUES (?,?)');
    foreach ($achievements as $achievement) {
        if (($metrics[$achievement['metric']] ?? 0) >= (int)$achievement['target_value']) {
            $insert->execute([$userId, $achievement['id']]);
            if ($insert->rowCount()) {
                $earned[] = $achievement;
                create_notification($pdo, $userId, 'achievement', 'Badge unlocked: '.$achievement['name'], $achievement['description'], 'donor/achievements.php');
            }
        }
    }
    return $earned;
}

function update_challenge_progress(PDO $pdo, int $pledgeId, int $userId): void
{
    $challenges = $pdo->query("SELECT * FROM community_challenges WHERE status='active' AND start_date<=CURDATE() AND end_date>=CURDATE()")->fetchAll();
    foreach ($challenges as $challenge) {
        $sql = 'SELECT COALESCE(SUM(di.quantity),0) FROM donation_items di WHERE di.pledge_id=?';
        $params = [$pledgeId];
        if ($challenge['category']) { $sql .= ' AND di.category=?'; $params[] = $challenge['category']; }
        $qty = $pdo->prepare($sql); $qty->execute($params); $quantity = (int)$qty->fetchColumn();
        if ($quantity < 1) continue;
        $add = $pdo->prepare('INSERT IGNORE INTO challenge_contributions (challenge_id,pledge_id,user_id,quantity) VALUES (?,?,?,?)');
        $add->execute([$challenge['id'],$pledgeId,$userId,$quantity]);
        if (!$add->rowCount()) continue;
        $join = $pdo->prepare('INSERT INTO challenge_participations (challenge_id,user_id,items_contributed) VALUES (?,?,?) ON DUPLICATE KEY UPDATE items_contributed=items_contributed+VALUES(items_contributed)');
        $join->execute([$challenge['id'],$userId,$quantity]);
        create_notification($pdo, $userId, 'challenge', 'Challenge contribution counted', $quantity.' item(s) now count toward '.$challenge['title'].'.', 'public/challenges.php');
    }
}

/** Awards once per pledge via a unique database constraint; repeated receipt attempts cannot duplicate points. */
function complete_donation_impact(PDO $pdo, int $pledgeId): array
{
    $pledge = $pdo->prepare('SELECT dp.donor_id,dp.request_id,cr.title FROM donation_pledges dp JOIN clothing_requests cr ON cr.id=dp.request_id WHERE dp.id=?');
    $pledge->execute([$pledgeId]); $pledge = $pledge->fetch();
    if (!$pledge) throw new RuntimeException('Donation pledge could not be found.');
    $quantity = $pdo->prepare('SELECT COALESCE(SUM(quantity),0) FROM donation_items WHERE pledge_id=?');
    $quantity->execute([$pledgeId]); $items = (int)$quantity->fetchColumn();
    $setting = $pdo->prepare("SELECT setting_value FROM platform_settings WHERE setting_key='points_per_confirmed_item'");
    $setting->execute(); $perItem = max(1, (int)($setting->fetchColumn() ?: 10)); $points = $items * $perItem;
    $award = $pdo->prepare('INSERT IGNORE INTO points_transactions (user_id,pledge_id,points,reason_code,description) VALUES (?,?,?,?,?)');
    $award->execute([(int)$pledge['donor_id'],$pledgeId,$points,'confirmed_donation','Confirmed donation: '.$items.' item(s)']);
    if ($award->rowCount()) {
        update_challenge_progress($pdo, $pledgeId, (int)$pledge['donor_id']);
        $badges = update_achievements($pdo, (int)$pledge['donor_id']);
        create_notification($pdo, (int)$pledge['donor_id'], 'donation', 'Donation received — thank you!', $items.' item(s) for '.$pledge['title'].' were confirmed. You earned '.$points.' impact points.', 'donor/passport.php');
        return ['items'=>$items,'points'=>$points,'badges'=>$badges,'newly_awarded'=>true];
    }
    return ['items'=>$items,'points'=>0,'badges'=>[],'newly_awarded'=>false];
}

function appointment_qr_token(PDO $pdo, int $appointmentId): string
{
    $select = $pdo->prepare('SELECT token FROM donation_qr_tokens WHERE appointment_id=?'); $select->execute([$appointmentId]);
    $token = $select->fetchColumn(); if ($token) return (string)$token;
    $token = bin2hex(random_bytes(24));
    $insert = $pdo->prepare('INSERT INTO donation_qr_tokens (appointment_id,token) VALUES (?,?)'); $insert->execute([$appointmentId,$token]);
    return $token;
}
