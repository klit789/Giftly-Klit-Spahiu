<?php
declare(strict_types=1);

/** Application-wide settings and safe URL helpers. */
const APP_NAME = 'Giftly';
const APP_ROOT = __DIR__ . '/..';
const GEMINI_MODEL = 'gemini-3.5-flash';

date_default_timezone_set('Europe/Warsaw');

function base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $projectRoot = realpath(APP_ROOT) ?: APP_ROOT;
    $relative = str_replace('\\', '/', str_replace($documentRoot, '', $projectRoot));
    $base = str_replace('%2F', '/', rawurlencode(rtrim($relative, '/')));
    return $base === '/' ? '' : $base;
}

function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('giftly_session');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}
