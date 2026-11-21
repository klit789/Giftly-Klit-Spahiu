<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/impact.php';

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return $user = null;
    }
    $stmt = db()->prepare('SELECT u.*, c.id AS charity_id, c.organization_name, c.verified AS charity_verified FROM users u LEFT JOIN charities c ON c.user_id = u.id WHERE u.id = ? AND u.is_active = 1');
    $stmt->execute([$id]);
    return $user = ($stmt->fetch() ?: null);
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('error', 'Please sign in to continue.');
        redirect('auth/login.php');
    }
    return $user;
}

function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        flash('error', 'You do not have permission to view that page.');
        redirect('index.php');
    }
    return $user;
}

function dashboard_path(array $user): string
{
    return match ($user['role']) {
        'charity' => 'charity/dashboard.php',
        'admin' => 'admin/dashboard.php',
        default => 'donor/dashboard.php',
    };
}
