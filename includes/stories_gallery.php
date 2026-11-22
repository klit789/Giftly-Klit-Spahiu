<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function story_categories(PDO $pdo): array
{
    return $pdo->query('SELECT id, name, slug FROM story_categories ORDER BY sort_order')->fetchAll();
}

function gallery_categories(PDO $pdo): array
{
    return $pdo->query('SELECT id, name, slug FROM gallery_categories ORDER BY sort_order')->fetchAll();
}

function content_upload_image(array $file, string $kind): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null)
        || ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Each image must be smaller than 5 MB.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('The uploaded image could not be verified.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $imageInfo = @getimagesize($file['tmp_name']);
    if (!isset($extensions[$mime]) || !$imageInfo || $imageInfo[0] > 12000 || $imageInfo[1] > 12000 || $imageInfo[0] * $imageInfo[1] > 40000000) {
        throw new RuntimeException('Images must be valid JPG, PNG, or WebP files.');
    }
    $folder = APP_ROOT . '/assets/uploads/' . $kind;
    if (!is_dir($folder) && !mkdir($folder, 0755, true) && !is_dir($folder)) {
        throw new RuntimeException('Could not prepare the image upload folder.');
    }
    $filename = $kind . '-' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $folder . '/' . $filename)) {
        throw new RuntimeException('The image could not be saved.');
    }
    return 'assets/uploads/' . $kind . '/' . $filename;
}

function content_upload_images(array $files, string $kind, int $limit = 8): array
{
    if (!isset($files['name']) || !is_array($files['name'])) {
        return [];
    }
    $images = [];
    try {
        foreach ($files['name'] as $index => $name) {
            if (($files['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if (count($images) >= $limit) {
                throw new RuntimeException('You can add up to ' . $limit . ' additional photos at a time.');
            }
            $image = content_upload_image([
                'name' => $name,
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$index] ?? 0,
            ], $kind);
            if ($image !== null) {
                $images[] = $image;
            }
        }
    } catch (RuntimeException $error) {
        foreach ($images as $path) delete_content_upload($path);
        throw $error;
    }
    return $images;
}

function delete_content_upload(?string $path): void
{
    if (!$path || !preg_match('~\Aassets/uploads/(stories|gallery)/[a-z0-9-]+\.(?:jpg|png|webp)\z~i', $path)) {
        return;
    }
    $fullPath = APP_ROOT . '/' . $path;
    if (is_file($fullPath) && !unlink($fullPath)) {
        error_log('Unable to remove uploaded content image: ' . $path);
    }
}

function content_slug(string $title): string
{
    $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);
    $base = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', (string)$transliterated), '-'));
    return substr($base ?: 'story', 0, 150) . '-' . bin2hex(random_bytes(5));
}

function charity_campaigns(PDO $pdo, int $charityId): array
{
    $stmt = $pdo->prepare("SELECT id, title FROM clothing_requests WHERE charity_id = ? ORDER BY created_at DESC");
    $stmt->execute([$charityId]);
    return $stmt->fetchAll();
}

function safe_impact_stats(string $json): array
{
    $stats = json_decode($json, true);
    if (!is_array($stats)) {
        return [];
    }
    return array_values(array_filter($stats, static fn ($row): bool =>
        is_array($row) && is_string($row['label'] ?? null) && is_string($row['value'] ?? null)
    ));
}

function charity_owns_campaign(PDO $pdo, int $campaignId, int $charityId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM clothing_requests WHERE id = ? AND charity_id = ?');
    $stmt->execute([$campaignId, $charityId]);
    return (bool)$stmt->fetchColumn();
}

function received_campaign_items(PDO $pdo, ?int $campaignId): int
{
    if (!$campaignId) {
        return 0;
    }
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(di.quantity), 0)
        FROM donation_items di
        JOIN donation_pledges dp ON dp.id = di.pledge_id
        WHERE dp.request_id = ? AND dp.status = 'received'");
    $stmt->execute([$campaignId]);
    return (int)$stmt->fetchColumn();
}

function gallery_public_sql(): string
{
    return "SELECT g.*, c.organization_name, gc.name AS category_name, cr.title AS campaign_title
        FROM happy_gallery_entries g
        JOIN charities c ON c.id = g.charity_id
        JOIN gallery_categories gc ON gc.id = g.category_id
        LEFT JOIN clothing_requests cr ON cr.id = g.campaign_id
        WHERE g.moderation_status = 'approved' AND g.is_published = 1
        AND (g.is_illustrative = 1 OR g.consent_confirmed = 1)";
}

function story_public_sql(): string
{
    return "SELECT s.*, c.organization_name, sc.name AS category_name, cr.title AS campaign_title,
        cr.id AS related_campaign_id
        FROM change_stories s
        JOIN charities c ON c.id = s.charity_id
        JOIN story_categories sc ON sc.id = s.category_id
        LEFT JOIN clothing_requests cr ON cr.id = s.campaign_id
        WHERE s.status = 'published' AND s.published_at IS NOT NULL AND s.published_at <= NOW()";
}

function story_image_markup(string $path, string $alt, string $class = '', bool $illustrative = false): string
{
    if (!str_starts_with($path, 'assets/uploads/stories/')) {
        return '';
    }
    $image = '<img src="' . e(url($path)) . '" alt="' . e($alt) . '" class="' . e($class) . '" loading="lazy">';
    return $illustrative
        ? '<span class="content-image-frame">' . $image . '<span class="content-illustrative-badge">Illustrative photo</span></span>'
        : $image;
}

function gallery_image_markup(string $path, string $alt, string $class = '', bool $illustrative = false): string
{
    if (!str_starts_with($path, 'assets/uploads/gallery/')) {
        return '';
    }
    $image = '<img src="' . e(url($path)) . '" alt="' . e($alt) . '" class="' . e($class) . '" loading="lazy">';
    return $illustrative
        ? '<span class="content-image-frame">' . $image . '<span class="content-illustrative-badge">Illustrative photo</span></span>'
        : $image;
}
