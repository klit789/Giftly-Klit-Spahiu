<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/wardrobe.php';
$user = require_role('donor');
$pdo = db();
$errors = [];
$categories = wardrobe_categories();
$conditions = wardrobe_conditions();
$seasons = ['All season','Spring','Summer','Autumn','Winter'];
$sizes = clothing_sizes();

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $itemId = filter_var($_POST['item_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
        $name = trim($_POST['name'] ?? '');
        $category = $_POST['category'] ?? '';
        $size = $_POST['size_label'] ?? '';
        $season = $_POST['season'] ?? 'All season';
        $quantity = filter_var($_POST['quantity'] ?? 0, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>500]]);
        $condition = $_POST['condition_label'] ?? '';
        $color = trim($_POST['color'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($name === '' || mb_strlen($name) > 120 || !in_array($category, $categories, true)
            || !in_array($size, $sizes, true) || !in_array($season, $seasons, true)
            || !$quantity || !in_array($condition, $conditions, true)
            || mb_strlen($color) > 50 || mb_strlen($description) > 500) {
            $errors[] = 'Enter a valid name, category, size, season, quantity, condition, color and description.';
        }
        $newImage = null;
        if (!$errors) {
            try {
                $newImage = upload_wardrobe_image($_FILES['image'] ?? []);
            } catch (RuntimeException $error) {
                $errors[] = $error->getMessage();
            }
        }
        if (!$errors) {
            $pdo->beginTransaction();
            try {
                if ($itemId) {
                    $stmt = $pdo->prepare('SELECT * FROM wardrobe_items WHERE id=? AND user_id=? AND deleted_at IS NULL FOR UPDATE');
                    $stmt->execute([$itemId, $user['id']]);
                    $existing = $stmt->fetch();
                    if (!$existing) {
                        throw new RuntimeException('That wardrobe item could not be found.');
                    }
                    $committed = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM wardrobe_reservations WHERE wardrobe_item_id=? AND status IN ('reserved','donated')");
                    $committed->execute([$itemId]);
                    if ((int)$quantity < (int)$committed->fetchColumn()) {
                        throw new RuntimeException('Quantity cannot be lower than reserved and already donated items.');
                    }
                    $imagePath = $newImage ?: $existing['image_path'];
                    $stmt = $pdo->prepare('UPDATE wardrobe_items SET name=?,category=?,size_label=?,season=?,quantity=?,condition_label=?,color=?,description=?,image_path=?,availability=? WHERE id=? AND user_id=?');
                    $stmt->execute([$name,$category,$size,$season,$quantity,$condition,$color ?: null,$description ?: null,$imagePath,($_POST['availability'] ?? 'available') === 'unavailable' ? 'unavailable' : 'available',$itemId,$user['id']]);
                    wardrobe_log($pdo, $itemId, (int)$user['id'], 'updated', (int)$quantity);
                    $message = 'Wardrobe item updated.';
                } else {
                    $availability = ($_POST['availability'] ?? 'available') === 'unavailable' ? 'unavailable' : 'available';
                    $stmt = $pdo->prepare('INSERT INTO wardrobe_items (user_id,name,category,size_label,season,quantity,condition_label,color,description,image_path,availability) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
                    $stmt->execute([$user['id'],$name,$category,$size,$season,$quantity,$condition,$color ?: null,$description ?: null,$newImage,$availability]);
                    $itemId = (int)$pdo->lastInsertId();
                    wardrobe_log($pdo, $itemId, (int)$user['id'], 'added', (int)$quantity);
                    $message = 'Clothing added to your digital wardrobe.';
                }
                $pdo->commit();
                flash('success', $message);
                redirect('donor/wardrobe.php');
            } catch (Throwable $error) {
                $pdo->rollBack();
                if ($newImage && is_file(APP_ROOT . '/' . $newImage)) {
                    unlink(APP_ROOT . '/' . $newImage);
                }
                $errors[] = $error instanceof RuntimeException ? $error->getMessage() : 'We could not save this wardrobe item.';
            }
        }
    } elseif ($action === 'availability' || $action === 'delete') {
        $itemId = filter_var($_POST['item_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM wardrobe_items WHERE id=? AND user_id=? AND deleted_at IS NULL FOR UPDATE');
            $stmt->execute([$itemId, $user['id']]);
            $item = $stmt->fetch();
            if (!$item) {
                throw new RuntimeException('That wardrobe item could not be found.');
            }
            $reserved = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM wardrobe_reservations WHERE wardrobe_item_id=? AND status='reserved'");
            $reserved->execute([$itemId]);
            $reserved = (int)$reserved->fetchColumn();
            if ($action === 'delete' && $reserved > 0) {
                throw new RuntimeException('Release or complete active pledges before removing this item.');
            }
            if ($action === 'delete') {
                $pdo->prepare('UPDATE wardrobe_items SET availability="unavailable",deleted_at=NOW() WHERE id=?')->execute([$itemId]);
                wardrobe_log($pdo, $itemId, (int)$user['id'], 'removed');
                $message = 'Wardrobe item removed.';
            } else {
                $availability = $item['availability'] === 'available' ? 'unavailable' : 'available';
                $pdo->prepare('UPDATE wardrobe_items SET availability=? WHERE id=?')->execute([$availability,$itemId]);
                wardrobe_log($pdo, $itemId, (int)$user['id'], 'updated', 0, null, 'Availability set to '.$availability);
                $message = $availability === 'available' ? 'Item is available for matching.' : 'Item marked unavailable.';
            }
            $pdo->commit();
            flash('success', $message);
        } catch (Throwable $error) {
            $pdo->rollBack();
            flash('error', $error instanceof RuntimeException ? $error->getMessage() : 'We could not update that wardrobe item.');
        }
        redirect('donor/wardrobe.php');
    } elseif ($action === 'pledge') {
        $itemId = filter_var($_POST['item_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
        $requirementId = filter_var($_POST['requirement_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
        $pledgeQuantity = filter_var($_POST['pledge_quantity'] ?? 0, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>500]]);
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM wardrobe_items WHERE id=? AND user_id=? AND deleted_at IS NULL FOR UPDATE');
            $stmt->execute([$itemId, $user['id']]);
            $item = $stmt->fetch();
            if (!$item || $item['availability'] !== 'available' || !$pledgeQuantity) {
                throw new RuntimeException('Choose an available wardrobe item and a valid quantity.');
            }
            $committed = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN status='reserved' THEN quantity ELSE 0 END),0) reserved,COALESCE(SUM(CASE WHEN status='donated' THEN quantity ELSE 0 END),0) donated FROM wardrobe_reservations WHERE wardrobe_item_id=?");
            $committed->execute([$itemId]);
            $committed = $committed->fetch();
            $available = (int)$item['quantity'] - (int)$committed['reserved'] - (int)$committed['donated'];
            if ($pledgeQuantity > $available) {
                throw new RuntimeException('There are not enough available items in your wardrobe.');
            }
            $stmt = $pdo->prepare('SELECT rr.*,cr.title,cr.status request_status,cr.season request_season,cr.urgency,c.organization_name,c.user_id charity_user_id FROM request_requirements rr JOIN clothing_requests cr ON cr.id=rr.request_id JOIN charities c ON c.id=cr.charity_id WHERE rr.id=? FOR UPDATE');
            $stmt->execute([$requirementId]);
            $requirement = $stmt->fetch();
            if (!$requirement || $requirement['request_status'] !== 'active'
                || !wardrobe_request_category_matches($item['category'], $requirement['category'])
                || $item['size_label'] !== $requirement['size_label']
                || ($item['season'] !== 'All season' && $requirement['request_season'] !== 'All season' && $item['season'] !== $requirement['request_season'])) {
                throw new RuntimeException('This clothing item no longer matches an active request.');
            }
            $used = $pdo->prepare("SELECT COALESCE(SUM(di.quantity),0) FROM donation_items di JOIN donation_pledges dp ON dp.id=di.pledge_id WHERE di.request_requirement_id=? AND dp.status IN ('pending','approved','scheduled','received')");
            $used->execute([$requirementId]);
            $needLeft = max(0, (int)$requirement['quantity_needed'] - (int)$used->fetchColumn());
            if ($pledgeQuantity > $needLeft) {
                throw new RuntimeException('The request now needs only '.$needLeft.' more matching item(s).');
            }
            $stmt = $pdo->prepare('INSERT INTO donation_pledges (donor_id,request_id,status) VALUES (?,?, "pending")');
            $stmt->execute([$user['id'],$requirement['request_id']]);
            $pledgeId = (int)$pdo->lastInsertId();
            $stmt = $pdo->prepare('INSERT INTO donation_items (pledge_id,request_requirement_id,category,size_label,quantity) VALUES (?,?,?,?,?)');
            $stmt->execute([$pledgeId,$requirementId,$requirement['category'],$requirement['size_label'],$pledgeQuantity]);
            $donationItemId = (int)$pdo->lastInsertId();
            $stmt = $pdo->prepare('INSERT INTO wardrobe_reservations (wardrobe_item_id,pledge_id,donation_item_id,quantity) VALUES (?,?,?,?)');
            $stmt->execute([$itemId,$pledgeId,$donationItemId,$pledgeQuantity]);
            $reservationId = (int)$pdo->lastInsertId();
            wardrobe_log($pdo, $itemId, (int)$user['id'], 'reserved', (int)$pledgeQuantity, $reservationId);
            create_notification($pdo, (int)$requirement['charity_user_id'], 'pledge', 'A new wardrobe pledge is waiting', 'A donor pledged '.$pledgeQuantity.' '.$item['name'].' item(s) for '.$requirement['title'].'.', 'charity/donations.php');
            log_activity($pdo, 'pledged wardrobe items for '.$requirement['title'], (int)$user['id'], 'pledge', $pledgeId);
            $pdo->commit();
            flash('success', 'Your wardrobe items are reserved in a pledge. The charity will review it.');
        } catch (Throwable $error) {
            $pdo->rollBack();
            flash('error', $error instanceof RuntimeException ? $error->getMessage() : 'We could not send this wardrobe pledge.');
        }
        redirect('donor/wardrobe.php');
    }
}

$editId = filter_var($_POST['item_id'] ?? $_GET['edit'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
$editing = null;
if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM wardrobe_items WHERE id=? AND user_id=? AND deleted_at IS NULL');
    $stmt->execute([$editId,$user['id']]);
    $editing = $stmt->fetch();
}
$filters = [
    'q' => trim($_GET['q'] ?? ''),
    'category' => $_GET['category'] ?? '',
    'size' => $_GET['size'] ?? '',
    'season' => $_GET['season'] ?? '',
    'condition' => $_GET['condition'] ?? '',
    'sort' => $_GET['sort'] ?? 'recent',
];
$where = ['wi.user_id=?','wi.deleted_at IS NULL'];
$params = [$user['id']];
if ($filters['q'] !== '') {
    $where[] = '(wi.name LIKE ? OR wi.description LIKE ? OR wi.color LIKE ?)';
    $term = '%'.$filters['q'].'%';
    array_push($params,$term,$term,$term);
}
foreach (['category'=>'category','size_label'=>'size','season'=>'season','condition_label'=>'condition'] as $column=>$filter) {
    if ($filters[$filter] !== '') {
        $allowed = $filter === 'category' ? $categories : ($filter === 'size' ? $sizes : ($filter === 'season' ? $seasons : $conditions));
        if (in_array($filters[$filter], $allowed, true)) {
            $where[] = 'wi.'.$column.'=?';
            $params[] = $filters[$filter];
        }
    }
}
$sorts = ['recent'=>'wi.created_at DESC','category'=>'wi.category,wi.name','size'=>'wi.size_label,wi.name','season'=>'wi.season,wi.name','condition'=>'wi.condition_label,wi.name'];
$order = $sorts[$filters['sort']] ?? $sorts['recent'];
$stmt = $pdo->prepare("SELECT wi.*,COALESCE((SELECT SUM(quantity) FROM wardrobe_reservations WHERE wardrobe_item_id=wi.id AND status='reserved'),0) reserved,COALESCE((SELECT SUM(quantity) FROM wardrobe_reservations WHERE wardrobe_item_id=wi.id AND status='donated'),0) donated FROM wardrobe_items wi WHERE ".implode(' AND ',$where)." ORDER BY $order");
$stmt->execute($params);
$items = $stmt->fetchAll();

$statsStmt = $pdo->prepare("SELECT COALESCE(SUM(wi.quantity),0) total,COALESCE(SUM(CASE WHEN wi.availability='available' THEN wi.quantity ELSE 0 END),0) available,
COALESCE((SELECT SUM(wr.quantity) FROM wardrobe_reservations wr JOIN wardrobe_items w2 ON w2.id=wr.wardrobe_item_id WHERE w2.user_id=? AND w2.deleted_at IS NULL AND wr.status='reserved'),0) reserved,
COALESCE((SELECT SUM(wr.quantity) FROM wardrobe_reservations wr JOIN wardrobe_items w3 ON w3.id=wr.wardrobe_item_id WHERE w3.user_id=? AND w3.deleted_at IS NULL AND wr.status='donated'),0) donated
FROM wardrobe_items wi WHERE wi.user_id=? AND wi.deleted_at IS NULL");
$statsStmt->execute([$user['id'],$user['id'],$user['id']]);
$stats = $statsStmt->fetch();
$stats['available'] = max(0,(int)$stats['available'] - (int)$stats['reserved'] - (int)$stats['donated']);
$categoryStmt = $pdo->prepare('SELECT category,SUM(quantity) quantity FROM wardrobe_items WHERE user_id=? AND deleted_at IS NULL GROUP BY category ORDER BY quantity DESC');
$categoryStmt->execute([$user['id']]);
$categoryStats = $categoryStmt->fetchAll();
$historyStmt = $pdo->prepare("SELECT wt.action,wt.quantity,wt.note,wt.created_at,wi.name FROM wardrobe_transactions wt JOIN wardrobe_items wi ON wi.id=wt.wardrobe_item_id WHERE wt.user_id=? ORDER BY wt.created_at DESC LIMIT 6");
$historyStmt->execute([$user['id']]);
$history = $historyStmt->fetchAll();

$needStmt = $pdo->query("SELECT rr.id requirement_id,rr.category,rr.size_label,rr.quantity_needed,rr.condition_note,cr.id request_id,cr.title,cr.season,cr.urgency,cr.location,c.organization_name,
GREATEST(rr.quantity_needed-COALESCE((SELECT SUM(di.quantity) FROM donation_items di JOIN donation_pledges dp ON dp.id=di.pledge_id WHERE di.request_requirement_id=rr.id AND dp.status IN ('pending','approved','scheduled','received')),0),0) remaining
FROM request_requirements rr JOIN clothing_requests cr ON cr.id=rr.request_id JOIN charities c ON c.id=cr.charity_id WHERE cr.status='active' ORDER BY FIELD(cr.urgency,'urgent','high','normal','low'),cr.created_at DESC");
$needs = $needStmt->fetchAll();
$matches = [];
foreach ($items as $item) {
    if ($item['availability'] !== 'available') {
        continue;
    }
    $available = max(0,(int)$item['quantity'] - (int)$item['reserved'] - (int)$item['donated']);
    if (!$available) {
        continue;
    }
    foreach ($needs as $need) {
        if (!$need['remaining'] || !wardrobe_request_category_matches($item['category'],$need['category'])
            || $item['size_label'] !== $need['size_label']
            || ($item['season'] !== 'All season' && $need['season'] !== 'All season' && $item['season'] !== $need['season'])) {
            continue;
        }
        $conditionScore = $item['condition_label'] === 'Gently used' ? 6 : 10;
        if (preg_match('/\b(new only|unworn)\b/i', (string)$need['condition_note']) && !in_array($item['condition_label'], ['New','Like new'], true)) {
            continue;
        }
        $needQuantityScore = min(10,(int)round(10 * min($available,(int)$need['remaining']) / max(1,(int)$need['remaining'])));
        $seasonScore = $item['season'] === 'All season' || $need['season'] === 'All season' ? 10 : 15;
        $urgencyScore = ['urgent'=>10,'high'=>7,'normal'=>4,'low'=>2][$need['urgency']] ?? 0;
        $matchScore = 30 + 25 + $seasonScore + $conditionScore + $needQuantityScore + $urgencyScore;
        $matches[] = ['item'=>$item,'need'=>$need,'available'=>$available,'score'=>min(100,$matchScore)];
    }
}
usort($matches, static fn(array $a,array $b): int => $b['score'] <=> $a['score'] ?: ['urgent'=>4,'high'=>3,'normal'=>2,'low'=>1][$b['need']['urgency']] <=> ['urgent'=>4,'high'=>3,'normal'=>2,'low'=>1][$a['need']['urgency']]);
$pageTitle = 'My Digital Wardrobe';
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard">
    <div class="shell">
        <div class="subnav">
            <a href="<?=e(url('donor/dashboard.php'))?>">Dashboard</a>
            <a href="<?=e(url('donor/browse.php'))?>">Explore requests</a>
            <a href="<?=e(url('donor/matcher.php'))?>">Smart matcher</a>
            <a class="is-active" href="<?=e(url('donor/wardrobe.php'))?>">My Digital Wardrobe</a>
            <a href="<?=e(url('donor/pledges.php'))?>">My donations</a>
            <a href="<?=e(url('donor/appointments.php'))?>">Appointments</a>
            <a href="<?=e(url('donor/passport.php'))?>">Impact passport</a>
        </div>
        <div class="welcome-row">
            <div><span class="eyebrow">Good clothes, ready to go</span><h1>My Digital Wardrobe</h1><p>Keep track of what you can give and match it with a real community need.</p></div>
            <a class="button" href="#add-clothing">Add clothing +</a>
        </div>
        <div class="stat-cards">
            <?php foreach (['total'=>'Total items','available'=>'Available','reserved'=>'Reserved','donated'=>'Donated'] as $key=>$label): ?>
                <article class="stat-card"><span class="label"><?=e($label)?></span><strong><?=number_format((int)$stats[$key])?></strong><small>clothing pieces</small></article>
            <?php endforeach; ?>
        </div>
        <div class="layout-two">
            <div class="panel">
                <div class="panel-heading"><h2>Wardrobe categories</h2><span class="chip chip-sun"><?=count($categoryStats)?> categories</span></div>
                <?php if (!$categoryStats): ?><div class="empty">Add your first clothing item to see a category summary.</div><?php else: ?>
                    <?php foreach ($categoryStats as $category): ?><div class="wardrobe-category-row"><span><?=e($category['category'])?></span><strong><?=number_format((int)$category['quantity'])?></strong><span class="wardrobe-category-bar"><i style="width:<?=min(100,(int)round(100*(int)$category['quantity']/max(1,(int)$stats['total'])))?>%"></i></span></div><?php endforeach; ?>
                <?php endif; ?>
            </div>
            <aside class="panel">
                <div class="panel-heading"><h2>Recently added</h2></div>
                <?php if (!$items): ?><div class="empty">Your recent clothing additions will show here.</div><?php else: foreach (array_slice($items,0,4) as $recent): ?>
                    <article class="activity-item"><div class="activity-icon">♥</div><div><p><strong><?=e($recent['name'])?></strong></p><p class="muted"><?=e($recent['category'])?> · <?=e($recent['size_label'])?> · <?=e($recent['quantity'])?> item(s)</p></div></article>
                <?php endforeach; endif; ?>
            </aside>
        </div>

        <div class="panel" id="add-clothing" style="margin-top:22px">
            <div class="panel-heading"><h2><?=$editing ? 'Edit wardrobe item' : 'Add clothing'?></h2><?php if ($editing): ?><a class="text-link" href="<?=e(url('donor/wardrobe.php'))?>">Cancel edit</a><?php endif; ?></div>
            <?php if ($errors): ?><div class="flash flash-error"><span><?=e(implode(' ',$errors))?></span></div><?php endif; ?>
            <form method="post" enctype="multipart/form-data" class="form-grid" data-loading>
                <?=csrf_field()?><input type="hidden" name="action" value="save"><input type="hidden" name="item_id" value="<?=e($editing['id'] ?? 0)?>">
                <div class="field"><label for="wardrobe-name">Clothing name</label><input class="input" id="wardrobe-name" name="name" maxlength="120" required value="<?=e($_POST['name'] ?? $editing['name'] ?? '')?>"></div>
                <div class="field"><label for="wardrobe-category">Category</label><select class="select" id="wardrobe-category" name="category" required><option value="">Choose category</option><?php foreach ($categories as $value): ?><option value="<?=e($value)?>" <?=($_POST['category'] ?? $editing['category'] ?? '')===$value?'selected':''?>><?=e($value)?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="wardrobe-size">Size</label><select class="select" id="wardrobe-size" name="size_label" required><option value="">Choose size</option><?php foreach ($sizes as $value): ?><option value="<?=e($value)?>" <?=($_POST['size_label'] ?? $editing['size_label'] ?? '')===$value?'selected':''?>><?=e($value)?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="wardrobe-season">Season</label><select class="select" id="wardrobe-season" name="season"><?php foreach ($seasons as $value): ?><option value="<?=e($value)?>" <?=($_POST['season'] ?? $editing['season'] ?? 'All season')===$value?'selected':''?>><?=e($value)?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="wardrobe-quantity">Quantity</label><input class="input" type="number" id="wardrobe-quantity" name="quantity" min="1" max="500" required value="<?=e($_POST['quantity'] ?? $editing['quantity'] ?? 1)?>"></div>
                <div class="field"><label for="wardrobe-condition">Condition</label><select class="select" id="wardrobe-condition" name="condition_label" required><?php foreach ($conditions as $value): ?><option value="<?=e($value)?>" <?=($_POST['condition_label'] ?? $editing['condition_label'] ?? 'Good')===$value?'selected':''?>><?=e($value)?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="wardrobe-color">Color <small>(optional)</small></label><input class="input" id="wardrobe-color" name="color" maxlength="50" value="<?=e($_POST['color'] ?? $editing['color'] ?? '')?>"></div>
                <div class="field"><label for="wardrobe-image">Image <small>(JPG, PNG or WebP; max 3 MB)</small></label><input class="input" type="file" id="wardrobe-image" name="image" accept="image/jpeg,image/png,image/webp"></div>
                <div class="field field-full"><label for="wardrobe-description">Description <small>(optional)</small></label><textarea class="textarea" id="wardrobe-description" name="description" maxlength="500"><?=e($_POST['description'] ?? $editing['description'] ?? '')?></textarea></div>
                <div class="field"><label for="wardrobe-availability">Availability</label><select class="select" id="wardrobe-availability" name="availability"><option value="available" <?=($_POST['availability'] ?? $editing['availability'] ?? 'available')==='available'?'selected':''?>>Available</option><option value="unavailable" <?=($_POST['availability'] ?? $editing['availability'] ?? 'available')==='unavailable'?'selected':''?>>Unavailable</option></select></div>
                <div class="field field-full"><button class="button" type="submit"><?=$editing ? 'Save changes' : 'Add to my wardrobe'?></button></div>
            </form>
        </div>

        <div class="panel" style="margin-top:22px">
            <div class="panel-heading"><h2>Your clothing</h2><span class="muted"><?=count($items)?> item records</span></div>
            <form method="get" class="filters wardrobe-filters">
                <input class="input" type="search" name="q" placeholder="Search name or color" value="<?=e($filters['q'])?>">
                <select class="select" name="category"><option value="">All categories</option><?php foreach ($categories as $value): ?><option value="<?=e($value)?>" <?=$filters['category']===$value?'selected':''?>><?=e($value)?></option><?php endforeach; ?></select>
                <select class="select" name="size"><option value="">All sizes</option><?php foreach ($sizes as $value): ?><option value="<?=e($value)?>" <?=$filters['size']===$value?'selected':''?>><?=e($value)?></option><?php endforeach; ?></select>
                <select class="select" name="season"><option value="">All seasons</option><?php foreach ($seasons as $value): ?><option value="<?=e($value)?>" <?=$filters['season']===$value?'selected':''?>><?=e($value)?></option><?php endforeach; ?></select>
                <select class="select" name="condition"><option value="">All conditions</option><?php foreach ($conditions as $value): ?><option value="<?=e($value)?>" <?=$filters['condition']===$value?'selected':''?>><?=e($value)?></option><?php endforeach; ?></select>
                <select class="select" name="sort"><?php foreach (['recent'=>'Recently added','category'=>'Category','size'=>'Size','season'=>'Season','condition'=>'Condition'] as $value=>$label): ?><option value="<?=e($value)?>" <?=$filters['sort']===$value?'selected':''?>><?=e($label)?></option><?php endforeach; ?></select>
                <button class="button button-small" type="submit">Filter</button>
            </form>
            <?php if (!$items): ?><div class="empty">No clothing matches your filters yet.</div><?php else: ?>
                <div class="wardrobe-grid">
                    <?php foreach ($items as $item): $itemAvailable=max(0,(int)$item['quantity']-(int)$item['reserved']-(int)$item['donated']); ?>
                        <article class="wardrobe-card">
                            <?php if ($item['image_path']): ?><img src="<?=e(url($item['image_path']))?>" alt="<?=e($item['name'])?>" class="wardrobe-image"><?php else: ?><div class="wardrobe-image wardrobe-image-empty">♥</div><?php endif; ?>
                            <div class="wardrobe-card-body">
                                <div class="card-top"><span class="chip"><?=e($item['category'])?></span><span class="status <?=e($item['availability']==='available'?'status-success':'status-neutral')?>"><?=e($item['availability'])?></span></div>
                                <h3><?=e($item['name'])?></h3><p><?=e($item['size_label'])?> · <?=e($item['season'])?> · <?=e($item['condition_label'])?></p>
                                <p><strong><?=number_format($itemAvailable)?></strong> available of <?=number_format((int)$item['quantity'])?> · <?=number_format((int)$item['reserved'])?> reserved · <?=number_format((int)$item['donated'])?> donated</p>
                                <?php if ($item['color']): ?><p>Color: <?=e($item['color'])?></p><?php endif; ?>
                                <div class="wardrobe-actions"><a class="button button-small button-outline" href="<?=e(url('donor/wardrobe.php?edit='.$item['id'].'#add-clothing'))?>">Edit</a>
                                    <form method="post" data-loading><?=csrf_field()?><input type="hidden" name="action" value="availability"><input type="hidden" name="item_id" value="<?=e($item['id'])?>"><button class="button button-small button-outline"><?=$item['availability']==='available'?'Unavailable':'Make available'?></button></form>
                                    <form method="post" data-loading data-confirm="Remove this item from your wardrobe?"><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="item_id" value="<?=e($item['id'])?>"><button class="button button-small button-danger">Remove</button></form>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel" style="margin-top:22px"><div class="panel-heading"><h2>Matching opportunities</h2><span class="chip chip-sun"><?=count($matches)?> real matches</span></div>
            <?php if (!$matches): ?><div class="empty">No active requests currently match your available wardrobe items. Try another size or category, or check back later.</div><?php else: ?>
                <div class="wardrobe-match-grid"><?php foreach (array_slice($matches,0,12) as $match): $pledgeMax=min($match['available'],(int)$match['need']['remaining']); ?>
                    <article class="request-card">
                        <div class="card-top"><span class="status <?=e(status_class($match['need']['urgency']==='urgent'?'pending':'active'))?>"><?=e($match['need']['urgency'])?> need</span><strong class="wardrobe-score"><?=e($match['score'])?>% match</strong></div>
                        <h3><?=e($match['need']['title'])?></h3><p><?=e($match['need']['organization_name'])?> · <?=e($match['need']['location'])?></p>
                        <div class="progress"><span style="width:<?=e($match['score'])?>%"></span></div>
                        <div class="progress-info"><span><?=e($match['need']['category'])?> · <?=e($match['need']['size_label'])?></span><span><?=e($match['need']['remaining'])?> needed</span></div>
                        <p>Your <strong><?=e($match['item']['name'])?></strong>: <?=e($match['available'])?> available</p>
                        <a class="button button-small button-outline" href="<?=e(url('donor/request.php?id='.$match['need']['request_id']))?>">View request</a>
                        <form method="post" class="wardrobe-pledge-form" data-loading><?=csrf_field()?><input type="hidden" name="action" value="pledge"><input type="hidden" name="item_id" value="<?=e($match['item']['id'])?>"><input type="hidden" name="requirement_id" value="<?=e($match['need']['requirement_id'])?>"><label class="sr-only" for="pledge-<?=$match['item']['id']?>-<?=$match['need']['requirement_id']?>">How many items to pledge?</label><input class="input" id="pledge-<?=$match['item']['id']?>-<?=$match['need']['requirement_id']?>" type="number" name="pledge_quantity" min="1" max="<?=e($pledgeMax)?>" value="1"><button class="button button-small" type="submit">Pledge matching items</button></form>
                    </article>
                <?php endforeach; ?></div>
            <?php endif; ?>
        </div>
        <div class="panel" style="margin-top:22px"><div class="panel-heading"><h2>Wardrobe activity</h2></div><?php if (!$history): ?><div class="empty">Your reservation and donation history will appear here.</div><?php else: foreach ($history as $entry): ?><article class="activity-item"><div class="activity-icon">✦</div><div><p><strong><?=e(ucfirst($entry['action']))?> · <?=e($entry['name'])?></strong><?php if ($entry['quantity']): ?> · <?=e($entry['quantity'])?> item(s)<?php endif; ?></p><time><?=format_date($entry['created_at'],'d M · H:i')?></time></div></article><?php endforeach; endif; ?></div>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
