<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$pageTitle = $pageTitle ?? APP_NAME;
$user = current_user();
$currentPath = basename($_SERVER['PHP_SELF']);
$activePath = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
$isActive = static fn (string $needle): string => str_contains($activePath, $needle) ? 'is-active' : '';
$unreadCount = $user ? unread_notifications(db(), (int)$user['id']) : 0;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Giftly matches useful clothes with the charities that need them.">
    <title><?= e($pageTitle) ?> · <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{coral:'#F04427',snow:'#FAFAF7',ink:'#20201E',sun:'#FFD928'},fontFamily:{display:['Arial Black','Arial','sans-serif'],sans:['Inter','Arial','sans-serif']}}}}</script>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/images.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/upgrade.css')) ?>">
    <script>document.documentElement.dataset.theme=localStorage.getItem('giftly-theme')||'light';</script>
</head>
<body class="bg-snow text-ink">
<header class="site-header">
    <div class="shell nav-wrap">
        <a href="<?= e(url()) ?>" class="brand" aria-label="Giftly home"><span class="brand-mark">G</span><span>giftly</span></a>
        <button class="menu-toggle" data-menu-toggle aria-label="Toggle navigation" aria-expanded="false">☰</button>
        <nav class="main-nav<?= $user ? ' main-nav-auth' : '' ?>" data-menu>
            <?php if (!$user): ?>
                <a class="<?= $isActive('/donor/browse.php') ?>" href="<?= e(url('donor/browse.php')) ?>">Explore requests</a>
                <a class="<?= $isActive('/public/urgent.php') ?>" href="<?= e(url('public/urgent.php')) ?>">Urgent needs</a>
                <a class="<?= $isActive('/public/challenges.php') ?>" href="<?= e(url('public/challenges.php')) ?>">Challenges</a>
                <a class="<?= $isActive('/public/impact.php') ?>" href="<?= e(url('public/impact.php')) ?>">Impact</a>
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Toggle dark mode">◐</button>
                <a href="<?= e(url('auth/login.php')) ?>">Sign in</a>
                <a class="button button-small" href="<?= e(url('auth/register.php')) ?>">Join Giftly</a>
            <?php else: ?>
                <a class="<?= $isActive('/'.explode('/', dashboard_path($user))[0].'/dashboard.php') ?>" href="<?= e(url(dashboard_path($user))) ?>">Dashboard</a>
                <?php if ($user['role'] === 'donor'): ?>
                    <a class="<?= $isActive('/donor/browse.php') ?>" href="<?=e(url('donor/browse.php'))?>">Explore requests</a>
                    <a class="<?= $isActive('/donor/matcher.php') ?>" href="<?=e(url('donor/matcher.php'))?>">Smart Matcher</a>
                    <a class="<?= $isActive('/donor/wardrobe.php') ?>" href="<?=e(url('donor/wardrobe.php'))?>">My Digital Wardrobe</a>
                    <a class="<?= $isActive('/donor/pledges.php') ?>" href="<?=e(url('donor/pledges.php'))?>">My Donations</a>
                    <a class="<?= $isActive('/donor/appointments.php') ?>" href="<?=e(url('donor/appointments.php'))?>">Appointments</a>
                    <a class="<?= $isActive('/donor/passport.php') ?>" href="<?=e(url('donor/passport.php'))?>">Impact Passport</a>
                    <a class="<?= $isActive('/donor/achievements.php') ?>" href="<?=e(url('donor/achievements.php'))?>">Achievements</a>
                    <a class="<?= $isActive('/donor/profile.php') ?>" href="<?=e(url('donor/profile.php'))?>">Profile</a>
                <?php elseif ($user['role'] === 'charity'): ?>
                    <a class="<?= $isActive('/charity/requests.php') ?>" href="<?=e(url('charity/requests.php'))?>">Clothing Needs</a>
                    <a class="<?= $isActive('/charity/donations.php') ?>" href="<?=e(url('charity/donations.php'))?>">Incoming Pledges</a>
                    <a class="<?= $isActive('/charity/appointments.php') ?>" href="<?=e(url('charity/appointments.php'))?>">Appointments</a>
                    <a class="<?= $isActive('/charity/insights.php') ?>" href="<?=e(url('charity/insights.php'))?>">Charity Insights</a>
                <?php else: ?>
                    <a class="<?= $isActive('/admin/users.php') ?>" href="<?=e(url('admin/users.php'))?>">Users & Charities</a>
                    <a class="<?= $isActive('/admin/requests.php') ?>" href="<?=e(url('admin/requests.php'))?>">Requests</a>
                    <a class="<?= $isActive('/admin/donations.php') ?>" href="<?=e(url('admin/donations.php'))?>">Donations</a>
                    <a class="<?= $isActive('/admin/insights.php') ?>" href="<?=e(url('admin/insights.php'))?>">GIFTLY INSIGHTS & Reports</a>
                <?php endif; ?>
                <a class="notification-link notification-icon <?= $isActive('/notifications.php') ?>" href="<?=e(url('notifications.php'))?>" aria-label="<?=e($unreadCount ? 'Notifications, '.$unreadCount.' unread' : 'Notifications')?>" title="Notifications">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
                    <?php if ($unreadCount): ?><b aria-hidden="true"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></b><?php endif; ?>
                </a>
                <span class="nav-user"><span class="avatar"><?= e(initials($user['name'])) ?></span><?= e(explode(' ', $user['name'])[0]) ?></span>
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Toggle dark mode">◐</button>
                <a class="button button-small button-dark signout-button" href="<?= e(url('auth/logout.php')) ?>">Sign out</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main>
<?php foreach (flash('read') ?? [] as $notice): ?>
    <div class="shell flash-wrap"><div class="flash flash-<?= e($notice['type']) ?>" data-flash><?= e($notice['message']) ?><button data-dismiss aria-label="Dismiss">×</button></div></div>
<?php endforeach; ?>
