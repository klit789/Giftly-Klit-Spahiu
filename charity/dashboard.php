<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

$user = require_role('charity');
$pdo = db();
$charityId = (int) $user['charity_id'];
$countries = giftly_countries();

if (is_post()) {
    verify_csrf();
    $countryCode = $_POST['country_code'] ?? '';
    if (!is_string($countryCode) || !array_key_exists($countryCode, $countries)) {
        flash('error', 'Please select a country from the list.');
    } else {
        $updateCountry = $pdo->prepare('UPDATE charities SET country_code = ? WHERE id = ?');
        $updateCountry->execute([$countryCode, $charityId]);
        flash('success', 'Your charity country has been saved.');
    }
    redirect('charity/dashboard.php');
}

$countryStmt = $pdo->prepare('SELECT country_code FROM charities WHERE id = ?');
$countryStmt->execute([$charityId]);
$countryCode = (string) ($countryStmt->fetchColumn() ?: '');

$counts = $pdo->prepare("SELECT
    (SELECT COUNT(*) FROM clothing_requests WHERE charity_id=? AND status='active') live,
    (SELECT COUNT(*) FROM donation_pledges dp JOIN clothing_requests cr ON cr.id=dp.request_id WHERE cr.charity_id=? AND dp.status='pending') pending,
    (SELECT COUNT(*) FROM donation_pledges dp JOIN clothing_requests cr ON cr.id=dp.request_id WHERE cr.charity_id=? AND dp.status='scheduled') scheduled,
    (SELECT COALESCE(SUM(di.quantity),0) FROM donation_items di JOIN donation_pledges dp ON dp.id=di.pledge_id JOIN clothing_requests cr ON cr.id=dp.request_id WHERE cr.charity_id=? AND dp.status='received') received");
$counts->execute([$charityId, $charityId, $charityId, $charityId]);
$counts = $counts->fetch();

$requestsStmt = $pdo->prepare("SELECT cr.*,
    COALESCE((SELECT SUM(quantity_needed) FROM request_requirements WHERE request_id=cr.id),0) needed,
    COALESCE((SELECT SUM(di.quantity) FROM donation_items di
        JOIN donation_pledges dp ON dp.id=di.pledge_id
        JOIN request_requirements rr ON rr.id=di.request_requirement_id
        WHERE rr.request_id=cr.id AND dp.status='received'),0) received
    FROM clothing_requests cr WHERE cr.charity_id=? ORDER BY cr.created_at DESC LIMIT 5");
$requestsStmt->execute([$charityId]);
$requests = $requestsStmt->fetchAll();

$pending = $pdo->prepare("SELECT dp.*,u.name donor_name,cr.title,COALESCE(SUM(di.quantity),0) quantity
    FROM donation_pledges dp
    JOIN users u ON u.id=dp.donor_id
    JOIN clothing_requests cr ON cr.id=dp.request_id
    LEFT JOIN donation_items di ON di.pledge_id=dp.id
    WHERE cr.charity_id=? AND dp.status='pending'
    GROUP BY dp.id ORDER BY dp.created_at ASC LIMIT 5");
$pending->execute([$charityId]);
$pending = $pending->fetchAll();

$pageTitle = 'Charity dashboard';
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard">
    <div class="shell">
        <div class="welcome-row">
            <div>
                <span class="eyebrow">Your community is showing up</span>
                <h1><?= e($user['organization_name']) ?></h1>
                <p><?= $user['charity_verified'] ? 'Verified charity' : 'Profile under review' ?> · Manage the needs your community can fill.</p>
            </div>
            <a class="button" href="<?= e(url('charity/request_form.php')) ?>">Post a clothing need +</a>
        </div>

        <div class="stat-cards">
            <article class="stat-card"><span class="label">Live requests</span><strong><?= e($counts['live']) ?></strong><small>visible to donors</small></article>
            <article class="stat-card"><span class="label">New pledges</span><strong><?= e($counts['pending']) ?></strong><small>awaiting your review</small></article>
            <article class="stat-card"><span class="label">Booked drops</span><strong><?= e($counts['scheduled']) ?></strong><small>coming your way</small></article>
            <article class="stat-card"><span class="label">Items received</span><strong><?= e($counts['received']) ?></strong><small>confirmed impact</small></article>
        </div>

        <section class="panel impact-country-setting">
            <div class="panel-heading">
                <div>
                    <span class="eyebrow">Community map</span>
                    <h2>Confirm your country</h2>
                </div>
                <span class="impact-country-status"><?= $countryCode !== '' && isset($countries[$countryCode]) ? 'Country confirmed' : 'Not set yet' ?></span>
            </div>
            <p class="impact-country-help">Your country determines where your organization appears on Giftly’s charity map. Existing profiles remain unassigned until you confirm their country.</p>
            <form class="impact-country-form" method="post">
                <?= csrf_field() ?>
                <label class="impact-map-category" for="country_code">
                    <span>Organization country</span>
                    <select class="select" id="country_code" name="country_code" required>
                        <option value="">Choose your country</option>
                        <?php foreach ($countries as $code => $countryName): ?>
                            <option value="<?= e($code) ?>" <?= $countryCode === $code ? 'selected' : '' ?>><?= e($countryName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="button button-small" type="submit">Save country</button>
            </form>
        </section>

        <div class="layout-two">
            <div class="panel">
                <div class="panel-heading"><h2>Your clothing requests</h2><a class="text-link" href="<?= e(url('charity/requests.php')) ?>">Manage all →</a></div>
                <?php if (!$requests): ?>
                    <div class="empty"><span class="empty-icon">◫</span>Post your first specific clothing need and donors can start pledging.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead><tr><th>Request</th><th>Progress</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($requests as $request): $left = max(0, $request['needed'] - $request['received']); ?>
                                    <tr>
                                        <td><strong><?= e($request['title']) ?></strong><br><span class="muted"><?= e($request['season']) ?> · <?= e($request['needed']) ?> needed</span></td>
                                        <td><?= e($request['received']) ?> received<br><span class="muted"><?= e($left) ?> to go</span></td>
                                        <td><span class="status <?= e(status_class($request['status'])) ?>"><?= e($request['status']) ?></span></td>
                                        <td><a class="text-link" href="<?= e(url('charity/request_form.php?id=' . (int) $request['id'])) ?>">Edit</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <aside class="panel">
                <div class="panel-heading"><h2>Needs your review</h2><a class="text-link" href="<?= e(url('charity/donations.php')) ?>">See all →</a></div>
                <?php if (!$pending): ?>
                    <div class="empty"><span class="empty-icon">✦</span>You’re all caught up on new pledges.</div>
                <?php else: ?>
                    <?php foreach ($pending as $pledge): ?>
                        <div class="appointment-card">
                            <strong><?= e($pledge['donor_name']) ?> · <?= e($pledge['quantity']) ?> item(s)</strong>
                            <p><?= e($pledge['title']) ?><br><a class="text-link" href="<?= e(url('charity/donations.php')) ?>">Review pledge →</a></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
