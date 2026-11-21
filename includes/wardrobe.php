<?php
declare(strict_types=1);

function wardrobe_categories(): array
{
    return ['Jackets','Coats','Shirts','T-shirts','Trousers','Jeans','Sweaters','Shoes','Dresses',"Children's clothing",'Accessories','Other'];
}

function wardrobe_conditions(): array
{
    return ['New','Like new','Good','Gently used'];
}

function wardrobe_request_category_matches(string $wardrobeCategory, string $requestCategory): bool
{
    $groups = [
        'outerwear' => ['jackets','coats','coats & jackets'],
        'tops' => ['shirts','t-shirts','sweaters','tops'],
        'trousers' => ['trousers','jeans','trousers & jeans'],
        'dresses' => ['dresses','dresses & skirts'],
        'children' => ["children's clothing",'children’s clothes','school uniforms'],
        'shoes' => ['shoes'],
        'accessories' => ['accessories'],
        'other' => ['other','bedding'],
    ];
    $normalize = static function (string $category) use ($groups): string {
        $category = mb_strtolower(trim($category));
        foreach ($groups as $group => $aliases) {
            if (in_array($category, $aliases, true)) {
                return $group;
            }
        }
        return $category;
    };
    return $normalize($wardrobeCategory) === $normalize($requestCategory);
}

function upload_wardrobe_image(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 3 * 1024 * 1024) {
        throw new RuntimeException('Please upload an image smaller than 3 MB.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('The selected image could not be verified.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Images must be JPG, PNG, or WebP files.');
    }
    $folder = APP_ROOT . '/assets/uploads/wardrobe';
    if (!is_dir($folder) && !mkdir($folder, 0755, true) && !is_dir($folder)) {
        throw new RuntimeException('Could not prepare the wardrobe image folder.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $folder . '/' . $filename)) {
        throw new RuntimeException('The wardrobe image could not be saved.');
    }
    return 'assets/uploads/wardrobe/' . $filename;
}

function wardrobe_log(PDO $pdo, int $itemId, int $userId, string $action, int $quantity = 0, ?int $reservationId = null, ?string $note = null): void
{
    $stmt = $pdo->prepare('INSERT INTO wardrobe_transactions (wardrobe_item_id,reservation_id,user_id,action,quantity,note) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$itemId, $reservationId, $userId, $action, $quantity, $note]);
}

function update_wardrobe_reservations(PDO $pdo, int $pledgeId, string $newStatus): void
{
    if (!in_array($newStatus, ['released','donated'], true)) {
        throw new InvalidArgumentException('Invalid wardrobe reservation status.');
    }
    $stmt = $pdo->prepare("SELECT wr.id,wr.wardrobe_item_id,wr.quantity,wi.user_id FROM wardrobe_reservations wr JOIN wardrobe_items wi ON wi.id=wr.wardrobe_item_id WHERE wr.pledge_id=? AND wr.status='reserved' FOR UPDATE");
    $stmt->execute([$pledgeId]);
    $reservations = $stmt->fetchAll();
    $update = $pdo->prepare('UPDATE wardrobe_reservations SET status=? WHERE id=? AND status="reserved"');
    foreach ($reservations as $reservation) {
        $update->execute([$newStatus, $reservation['id']]);
        if ($update->rowCount()) {
            wardrobe_log(
                $pdo,
                (int)$reservation['wardrobe_item_id'],
                (int)$reservation['user_id'],
                $newStatus,
                (int)$reservation['quantity'],
                (int)$reservation['id']
            );
        }
    }
}
