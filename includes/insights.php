<?php
declare(strict_types=1);

function insights_valid_date(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function insights_filters(): array
{
    $today = date('Y-m-d');
    $from = $_GET['from'] ?? date('Y-m-d', strtotime('-11 months'));
    $to = $_GET['to'] ?? $today;
    if (!is_string($from) || !insights_valid_date($from)) $from = date('Y-m-d', strtotime('-11 months'));
    if (!is_string($to) || !insights_valid_date($to) || $to > $today) $to = $today;
    if ($from > $to) $from = $to;
    $category = is_string($_GET['category'] ?? null) ? $_GET['category'] : '';
    if ($category !== '' && !in_array($category, clothing_categories(), true)) $category = '';
    $season = is_string($_GET['season'] ?? null) ? $_GET['season'] : '';
    if ($season !== '' && !in_array($season, ['All season','Winter','Summer','Spring','Autumn'], true)) $season = '';
    $period = is_string($_GET['period'] ?? null) ? $_GET['period'] : 'month';
    if (!in_array($period, ['month','quarter','year'], true)) $period = 'month';
    $charitySearch = is_string($_GET['charity_search'] ?? null) ? mb_substr(trim($_GET['charity_search']),0,100) : '';
    return ['from'=>$from,'to'=>$to,'category'=>$category,'season'=>$season,'period'=>$period,'charity_search'=>$charitySearch];
}

function insights_conditions(array $filters, string $dateColumn, ?int $charityId = null, string $requestAlias = 'cr'): array
{
    $where = ["$dateColumn >= ?","$dateColumn < DATE_ADD(?, INTERVAL 1 DAY)"];
    $params = [$filters['from'],$filters['to']];
    if ($charityId !== null) {
        $where[] = "$requestAlias.charity_id=?";
        $params[] = $charityId;
    }
    if ($filters['season'] !== '') {
        $where[] = "$requestAlias.season=?";
        $params[] = $filters['season'];
    }
    if ($filters['category'] !== '') {
        $where[] = "EXISTS (SELECT 1 FROM request_requirements rrf WHERE rrf.request_id=$requestAlias.id AND rrf.category=?)";
        $params[] = $filters['category'];
    }
    return [implode(' AND ',$where),$params];
}

function insights_execute(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function insights_data(PDO $pdo, array $filters, ?int $charityId = null): array
{
    [$requestWhere,$requestParams] = insights_conditions($filters,'cr.created_at',$charityId);
    [$pledgeWhere,$pledgeParams] = insights_conditions($filters,'dp.created_at',$charityId);
    [$receivedWhere,$receivedParams] = insights_conditions($filters,'dp.updated_at',$charityId);
    $activeRequests = (int)(insights_execute($pdo,"SELECT COUNT(*) count FROM clothing_requests cr WHERE cr.status='active' AND $requestWhere",$requestParams)[0]['count'] ?? 0);
    $pendingPledges = (int)(insights_execute($pdo,"SELECT COUNT(*) count FROM donation_pledges dp JOIN clothing_requests cr ON cr.id=dp.request_id WHERE dp.status='pending' AND $pledgeWhere",$pledgeParams)[0]['count'] ?? 0);
    $completedDonations = (int)(insights_execute($pdo,"SELECT COUNT(*) count FROM donation_pledges dp JOIN clothing_requests cr ON cr.id=dp.request_id WHERE dp.status='received' AND $receivedWhere",$receivedParams)[0]['count'] ?? 0);
    $receivedItems = (int)(insights_execute($pdo,"SELECT COALESCE(SUM(di.quantity),0) quantity FROM donation_items di JOIN donation_pledges dp ON dp.id=di.pledge_id JOIN clothing_requests cr ON cr.id=dp.request_id WHERE dp.status='received' AND $receivedWhere",$receivedParams)[0]['quantity'] ?? 0);
    $pledgeTotals = insights_execute($pdo,"SELECT COUNT(*) total, SUM(dp.status='received') received FROM donation_pledges dp JOIN clothing_requests cr ON cr.id=dp.request_id WHERE dp.status<>'cancelled' AND $pledgeWhere",$pledgeParams)[0] ?? ['total'=>0,'received'=>0];
    $donationCompletionRate = (int)$pledgeTotals['total'] ? round(100*(int)$pledgeTotals['received']/(int)$pledgeTotals['total'],1) : 0;
    if ($charityId === null) {
        $donors = (int)(insights_execute($pdo,"SELECT COUNT(*) count FROM users WHERE role='donor' AND is_active=1 AND created_at>=? AND created_at<DATE_ADD(?,INTERVAL 1 DAY)",[$filters['from'],$filters['to']])[0]['count'] ?? 0);
        $charities = (int)(insights_execute($pdo,"SELECT COUNT(*) count FROM charities WHERE created_at>=? AND created_at<DATE_ADD(?,INTERVAL 1 DAY)",[$filters['from'],$filters['to']])[0]['count'] ?? 0);
    } else {
        $donors = (int)(insights_execute($pdo,"SELECT COUNT(DISTINCT dp.donor_id) count FROM donation_pledges dp JOIN clothing_requests cr ON cr.id=dp.request_id WHERE $pledgeWhere",$pledgeParams)[0]['count'] ?? 0);
        $charities = 1;
    }
    [$appointmentWhere,$appointmentParams] = insights_conditions($filters,'a.appointment_date',$charityId);
    $completedAppointments = (int)(insights_execute($pdo,"SELECT COUNT(*) count FROM appointments a JOIN donation_pledges dp ON dp.id=a.pledge_id JOIN clothing_requests cr ON cr.id=dp.request_id WHERE a.status='completed' AND $appointmentWhere",$appointmentParams)[0]['count'] ?? 0);
    $summary = [
        'items_received'=>$receivedItems,
        'completed_donations'=>$completedDonations,
        'active_requests'=>$activeRequests,
        'donors'=>$donors,
        'charities'=>$charities,
        'pending_pledges'=>$pendingPledges,
        'completed_appointments'=>$completedAppointments,
        'completion_rate'=>$donationCompletionRate,
    ];

    $periodSql = match($filters['period']) {
        'quarter' => "CONCAT(YEAR(dp.updated_at),'-Q',QUARTER(dp.updated_at))",
        'year' => 'YEAR(dp.updated_at)',
        default => "DATE_FORMAT(dp.updated_at,'%Y-%m')",
    };
    $monthly = insights_execute($pdo,"SELECT $periodSql label,COUNT(DISTINCT dp.id) value FROM donation_pledges dp JOIN clothing_requests cr ON cr.id=dp.request_id WHERE dp.status='received' AND $receivedWhere GROUP BY label ORDER BY MIN(dp.updated_at)",$receivedParams);

    $demandScope = "cr.status='active' AND $requestWhere";
    $demandParams = $requestParams;
    $categoryDemand = insights_execute($pdo,"SELECT CASE WHEN rr.category='Coats & jackets' THEN 'Coats / jackets' WHEN rr.category='Tops' THEN 'Shirts / tops' WHEN rr.category IN ('Trousers & jeans') THEN 'Trousers' WHEN rr.category='Shoes' THEN 'Shoes' ELSE rr.category END label,
SUM(GREATEST(rr.quantity_needed-COALESCE((SELECT SUM(di.quantity) FROM donation_items di JOIN donation_pledges dp ON dp.id=di.pledge_id WHERE di.request_requirement_id=rr.id AND dp.status='received'),0),0)) value
FROM request_requirements rr JOIN clothing_requests cr ON cr.id=rr.request_id WHERE $demandScope GROUP BY label ORDER BY value DESC",$demandParams);
    $seasonalDemand = insights_execute($pdo,"SELECT cr.season label,COUNT(DISTINCT cr.id) requests,SUM(GREATEST(rr.quantity_needed-COALESCE((SELECT SUM(di.quantity) FROM donation_items di JOIN donation_pledges dp ON dp.id=di.pledge_id WHERE di.request_requirement_id=rr.id AND dp.status='received'),0),0)) value FROM clothing_requests cr JOIN request_requirements rr ON rr.request_id=cr.id WHERE $demandScope GROUP BY cr.season ORDER BY value DESC",$demandParams);
    $sizeDemand = insights_execute($pdo,"SELECT rr.size_label label,SUM(GREATEST(rr.quantity_needed-COALESCE((SELECT SUM(di.quantity) FROM donation_items di JOIN donation_pledges dp ON dp.id=di.pledge_id WHERE di.request_requirement_id=rr.id AND dp.status='received'),0),0)) value FROM request_requirements rr JOIN clothing_requests cr ON cr.id=rr.request_id WHERE $demandScope GROUP BY rr.size_label ORDER BY value DESC",$demandParams);
    $statusRows = insights_execute($pdo,"SELECT dp.status label,COUNT(DISTINCT dp.id) value FROM donation_pledges dp JOIN clothing_requests cr ON cr.id=dp.request_id WHERE $pledgeWhere GROUP BY dp.status ORDER BY FIELD(dp.status,'pending','approved','scheduled','received','cancelled')",$pledgeParams);
    $urgencyRows = insights_execute($pdo,"SELECT cr.urgency label,COUNT(DISTINCT cr.id) value FROM clothing_requests cr WHERE cr.status='active' AND $requestWhere GROUP BY cr.urgency ORDER BY FIELD(cr.urgency,'low','normal','high','urgent')",$requestParams);
    $charitySearch = '';
    $charitySearchParams = [];
    if ($filters['charity_search'] !== '') {
        $charitySearch = ' AND c.organization_name LIKE ?';
        $charitySearchParams[] = '%'.$filters['charity_search'].'%';
    }
    $receivedRequestFilters = '';
    $receivedRequestParams = [];
    if ($filters['season'] !== '') {
        $receivedRequestFilters .= ' AND cr2.season=?';
        $receivedRequestParams[] = $filters['season'];
    }
    if ($filters['category'] !== '') {
        $receivedRequestFilters .= ' AND EXISTS (SELECT 1 FROM request_requirements rrf2 WHERE rrf2.request_id=cr2.id AND rrf2.category=?)';
        $receivedRequestParams[] = $filters['category'];
    }
    $charityRows = insights_execute($pdo,"SELECT c.id,c.organization_name,
COUNT(DISTINCT cr.id) requests,
COALESCE((SELECT SUM(di.quantity) FROM donation_items di JOIN donation_pledges dp2 ON dp2.id=di.pledge_id JOIN clothing_requests cr2 ON cr2.id=dp2.request_id WHERE cr2.charity_id=c.id AND dp2.status='received' AND dp2.updated_at>=? AND dp2.updated_at<DATE_ADD(?,INTERVAL 1 DAY)$receivedRequestFilters),0) items_received,
COUNT(DISTINCT CASE WHEN dp.status='received' THEN dp.id END) completed_donations,
COUNT(DISTINCT CASE WHEN cr.status='fulfilled' THEN cr.id END) fulfilled,
COUNT(DISTINCT cr.id) total_requests
FROM charities c LEFT JOIN clothing_requests cr ON cr.charity_id=c.id AND $requestWhere
LEFT JOIN donation_pledges dp ON dp.request_id=cr.id AND dp.updated_at>=? AND dp.updated_at<DATE_ADD(?,INTERVAL 1 DAY)".($charityId===null?'':' WHERE c.id=?')."$charitySearch
GROUP BY c.id ORDER BY items_received DESC,c.organization_name",
array_merge([$filters['from'],$filters['to']],$receivedRequestParams,$requestParams,[$filters['from'],$filters['to']],$charityId===null?[]:[$charityId],$charitySearchParams));
    foreach ($charityRows as &$row) {
        $row['fulfillment_rate'] = (int)$row['total_requests'] ? round(100*(int)$row['fulfilled']/(int)$row['total_requests'],1) : 0;
    }
    unset($row);
    return ['summary'=>$summary,'category'=>$categoryDemand,'monthly'=>$monthly,'season'=>$seasonalDemand,'sizes'=>$sizeDemand,'statuses'=>$statusRows,'charities'=>$charityRows,'urgency'=>$urgencyRows];
}
