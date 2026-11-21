<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function flash(string $type, ?string $message = null): ?array
{
    if ($message !== null) {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
        return null;
    }
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(419);
        exit('Your form session expired. Please go back and try again.');
    }
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function valid_enum(string $value, array $allowed): bool
{
    return in_array($value, $allowed, true);
}

function format_date(?string $date, string $format = 'd M Y'): string
{
    return $date ? date($format, strtotime($date)) : '—';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    return strtoupper(substr($parts[0] ?? '?', 0, 1) . substr($parts[1] ?? '', 0, 1));
}

function log_activity(PDO $pdo, string $action, ?int $userId = null, ?string $entityType = null, ?int $entityId = null): void
{
    $stmt = $pdo->prepare('INSERT INTO activity_log (user_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, $action, $entityType, $entityId]);
}

function request_requirements(PDO $pdo, int $requestId): array
{
    $sql = 'SELECT rr.*, GREATEST(rr.quantity_needed - COALESCE((
                SELECT SUM(di.quantity) FROM donation_items di
                JOIN donation_pledges dp ON dp.id = di.pledge_id
                WHERE di.request_requirement_id = rr.id AND dp.status = "received"
            ), 0), 0) AS quantity_remaining,
            COALESCE((SELECT SUM(di.quantity) FROM donation_items di
                JOIN donation_pledges dp ON dp.id = di.pledge_id
                WHERE di.request_requirement_id = rr.id AND dp.status IN ("pending","approved","scheduled")), 0) AS quantity_pledged
            FROM request_requirements rr WHERE rr.request_id = ? ORDER BY rr.id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$requestId]);
    return $stmt->fetchAll();
}

function request_totals(PDO $pdo, int $requestId): array
{
    $requirements = request_requirements($pdo, $requestId);
    $total = $received = $pledged = 0;
    foreach ($requirements as $item) {
        $total += (int) $item['quantity_needed'];
        $received += (int) $item['quantity_needed'] - (int) $item['quantity_remaining'];
        $pledged += (int) $item['quantity_pledged'];
    }
    return ['needed' => $total, 'received' => $received, 'pledged' => $pledged, 'remaining' => max(0, $total - $received)];
}

function status_class(string $status): string
{
    return match ($status) {
        'received', 'active', 'confirmed' => 'status-success',
        'pending' => 'status-warning',
        'cancelled', 'closed', 'inactive' => 'status-danger',
        default => 'status-neutral',
    };
}

function clothing_categories(): array
{
    return ['Coats & jackets','Tops','Trousers & jeans','Dresses & skirts','Shoes','Children’s clothes','School uniforms','Accessories','Bedding'];
}

function clothing_sizes(): array
{
    return ['Baby','Children','XS','S','M','L','XL','XXL','One size'];
}

function giftly_countries(): array
{
    static $countries = null;
    if ($countries !== null) {
        return $countries;
    }

    $json = file_get_contents(__DIR__ . '/../assets/data/countries.json');
    if ($json === false) {
        throw new RuntimeException('The country list could not be loaded.');
    }

    $records = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($records)) {
        throw new UnexpectedValueException('The country list has an invalid format.');
    }

    $countries = [];
    foreach ($records as $record) {
        if (!is_array($record)
            || !is_string($record['code'] ?? null)
            || !preg_match('/^[A-Z0-9]{3}$/D', $record['code'])
            || !is_string($record['name'] ?? null)
            || $record['name'] === ''
            || isset($countries[$record['code']])) {
            throw new UnexpectedValueException('The country list contains an invalid entry.');
        }
        $countries[$record['code']] = $record['name'];
    }

    return $countries;
}

/** Validates and moves an optional charity image into the local public uploads folder. */
function upload_request_image(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 3 * 1024 * 1024) {
        throw new RuntimeException('Please upload an image smaller than 3 MB.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime]) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Images must be JPG, PNG, or WebP files.');
    }
    $folder = APP_ROOT . '/assets/uploads';
    if (!is_dir($folder) && !mkdir($folder, 0755, true) && !is_dir($folder)) {
        throw new RuntimeException('Could not prepare the uploads folder.');
    }
    $filename = 'request-' . bin2hex(random_bytes(10)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $folder . '/' . $filename)) {
        throw new RuntimeException('The image could not be saved.');
    }
    return 'assets/uploads/' . $filename;
}

function request_image_markup(?string $imagePath, string $fallback = '👕'): string
{
    if ($imagePath && str_starts_with($imagePath, 'assets/uploads/')) {
        return '<img src="' . e(url($imagePath)) . '" alt="" class="request-thumb">';
    }
    return e($imagePath ?: $fallback);
}
