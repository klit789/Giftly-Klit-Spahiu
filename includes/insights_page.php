<?php
declare(strict_types=1);
require_once __DIR__ . '/insights.php';
$pdo = db();
$filters = insights_filters();
$data = insights_data($pdo,$filters,$insightsCharityId);
$exportUrl = url($insightsRoute.'?'.http_build_query(array_merge($filters,['export'=>'csv'])));
if (($_GET['export'] ?? '') === 'csv') {
    $safe = static function (string|int|float|null $value): string {
        $text = (string)$value;
        return preg_match('/^[=+\-@\t\r]/', $text) ? "'".$text : $text;
    };
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="giftly-insights-'.date('Y-m-d').'.csv"');
    $output = fopen('php://output','wb');
    if (!$output) {
        throw new RuntimeException('Could not create the CSV report.');
    }
    fwrite($output,"\xEF\xBB\xBF");
    fputcsv($output,['GIFTLY INSIGHTS','Value','Additional detail']);
    foreach ($data['summary'] as $label=>$value) {
        fputcsv($output,[$safe(str_replace('_',' ',ucfirst($label))),$safe($value),'']);
    }
    foreach (['category'=>'Category demand','monthly'=>'Donation activity','season'=>'Seasonal demand','sizes'=>'Size demand','statuses'=>'Donation status','urgency'=>'Request urgency'] as $key=>$label) {
        foreach ($data[$key] as $row) {
            fputcsv($output,[$safe($label),$safe($row['label']),$safe($row['value'])]);
        }
    }
    fputcsv($output,['Charity performance','Requests','Items received','Completed donations','Fulfillment rate']);
    foreach ($data['charities'] as $row) {
        fputcsv($output,[$safe($row['organization_name']),$safe($row['requests']),$safe($row['items_received']),$safe($row['completed_donations']),$safe($row['fulfillment_rate'].'%')]);
    }
    fclose($output);
    exit;
}
$pageTitle = 'GIFTLY INSIGHTS';
require __DIR__ . '/header.php';
$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE;
$chartData = [
    'category'=>['labels'=>array_column($data['category'],'label'),'values'=>array_map('intval',array_column($data['category'],'value'))],
    'monthly'=>['labels'=>array_column($data['monthly'],'label'),'values'=>array_map('intval',array_column($data['monthly'],'value'))],
    'season'=>['labels'=>array_column($data['season'],'label'),'values'=>array_map('intval',array_column($data['season'],'value'))],
    'sizes'=>['labels'=>array_column($data['sizes'],'label'),'values'=>array_map('intval',array_column($data['sizes'],'value'))],
    'statuses'=>['labels'=>array_column($data['statuses'],'label'),'values'=>array_map('intval',array_column($data['statuses'],'value'))],
    'urgency'=>['labels'=>array_column($data['urgency'],'label'),'values'=>array_map('intval',array_column($data['urgency'],'value'))],
];
?>
<section class="dashboard insights-page">
    <div class="shell">
        <div class="subnav">
            <?php if ($insightsCharityId === null): ?>
                <a href="<?=e(url('admin/dashboard.php'))?>">Dashboard</a><a href="<?=e(url('admin/users.php'))?>">Users</a><a href="<?=e(url('admin/requests.php'))?>">Requests</a><a href="<?=e(url('admin/donations.php'))?>">Donations</a><a class="is-active" href="<?=e(url($insightsRoute))?>">GIFTLY INSIGHTS</a>
            <?php else: ?>
                <a href="<?=e(url('charity/dashboard.php'))?>">Dashboard</a><a href="<?=e(url('charity/requests.php'))?>">Clothing needs</a><a href="<?=e(url('charity/donations.php'))?>">Incoming pledges</a><a href="<?=e(url('charity/appointments.php'))?>">Appointments</a><a class="is-active" href="<?=e(url($insightsRoute))?>">Charity insights</a>
            <?php endif; ?>
        </div>
        <div class="insights-hero">
            <div><span class="eyebrow">Real activity. Measurable impact.</span><h1>GIFTLY <span>INSIGHTS</span></h1><p><?=$insightsCharityId === null ? 'A live view of clothing demand, donor activity, and community impact across Giftly.' : 'Understand demand and donation progress for your organization.'?></p></div>
            <div class="insights-actions"><a class="button button-sun" href="<?=e($exportUrl)?>">Export CSV ↓</a><button class="button button-outline" type="button" data-print-report>Print report</button></div>
        </div>
        <form method="get" class="panel insights-filters">
            <div class="field"><label for="insights-from">From</label><input class="input" type="date" id="insights-from" name="from" value="<?=e($filters['from'])?>"></div>
            <div class="field"><label for="insights-to">To</label><input class="input" type="date" id="insights-to" name="to" value="<?=e($filters['to'])?>" max="<?=e(date('Y-m-d'))?>"></div>
            <div class="field"><label for="insights-period">Donation trend</label><select class="select" id="insights-period" name="period"><?php foreach (['month'=>'Monthly','quarter'=>'Quarterly','year'=>'Yearly'] as $value=>$label): ?><option value="<?=e($value)?>" <?=$filters['period']===$value?'selected':''?>><?=e($label)?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="insights-category">Clothing category</label><select class="select" id="insights-category" name="category"><option value="">All categories</option><?php foreach (clothing_categories() as $value): ?><option value="<?=e($value)?>" <?=$filters['category']===$value?'selected':''?>><?=e($value)?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="insights-season">Season</label><select class="select" id="insights-season" name="season"><option value="">All seasons</option><?php foreach (['All season','Winter','Summer','Spring','Autumn'] as $value): ?><option value="<?=e($value)?>" <?=$filters['season']===$value?'selected':''?>><?=e($value)?></option><?php endforeach; ?></select></div>
            <button class="button" type="submit">Update insights</button>
        </form>
        <p class="insights-period">Reporting period: <strong><?=format_date($filters['from'])?></strong> to <strong><?=format_date($filters['to'])?></strong> · Only received donations count as completed impact.</p>
        <div class="stat-cards insights-stats">
            <?php
            $labels = [
                'items_received'=>'Clothing items received',
                'completed_donations'=>'Completed donations',
                'active_requests'=>'Active charity requests',
                'donors'=>$insightsCharityId === null ? 'Registered donors' : 'Donors reached',
                'charities'=>$insightsCharityId === null ? 'Registered charities' : 'Organization',
                'pending_pledges'=>'Pending pledges',
                'completed_appointments'=>'Completed appointments',
                'completion_rate'=>'Donation completion rate',
            ];
            foreach ($labels as $key=>$label):
            ?>
                <article class="stat-card"><span class="label"><?=e($label)?></span><strong><?=e($data['summary'][$key])?><?=$key==='completion_rate'?'%':''?></strong><small><?=$key==='completion_rate'?'received / non-cancelled pledges in range':'from verified platform records'?></small></article>
            <?php endforeach; ?>
        </div>
        <div class="insights-chart-grid">
            <article class="panel insights-chart-card"><div class="panel-heading"><h2>Clothing category demand</h2><span class="chip">Pieces still needed</span></div><div class="chart-canvas"><canvas id="chart-category" aria-label="Clothing category demand chart"></canvas></div></article>
            <article class="panel insights-chart-card"><div class="panel-heading"><h2>Donation activity</h2><span class="chip chip-sun"><?=e(ucfirst($filters['period']))?> view</span></div><div class="chart-canvas"><canvas id="chart-monthly" aria-label="Completed donations over time"></canvas></div></article>
            <article class="panel insights-chart-card"><div class="panel-heading"><h2>Seasonal demand</h2><span class="chip">Active needs</span></div><div class="chart-canvas"><canvas id="chart-season" aria-label="Seasonal demand chart"></canvas></div></article>
            <article class="panel insights-chart-card"><div class="panel-heading"><h2>Most requested sizes</h2><span class="chip">Pieces still needed</span></div><div class="chart-canvas"><canvas id="chart-sizes" aria-label="Most requested sizes chart"></canvas></div></article>
            <article class="panel insights-chart-card"><div class="panel-heading"><h2>Donation status overview</h2><span class="chip">Pledges</span></div><div class="chart-canvas"><canvas id="chart-statuses" aria-label="Donation statuses chart"></canvas></div></article>
            <article class="panel insights-chart-card"><div class="panel-heading"><h2>Urgency distribution</h2><span class="chip">Active requests</span></div><div class="chart-canvas"><canvas id="chart-urgency" aria-label="Request urgency chart"></canvas></div></article>
        </div>
        <div class="panel insights-charities">
            <div class="panel-heading"><div><h2>Charity performance</h2><p class="muted">Requests posted, confirmed items, completed donations, and fulfillment.</p></div><input class="input charity-search" type="search" placeholder="Search charities" data-charity-search aria-label="Search charity statistics"></div>
            <?php if (!$data['charities']): ?><div class="empty">No charity records found for this report period.</div><?php else: ?>
                <div class="table-wrap"><table class="data-table"><thead><tr><th>Charity</th><th>Requests</th><th>Items received</th><th>Completed donations</th><th>Fulfillment</th></tr></thead><tbody data-charity-rows>
                    <?php foreach ($data['charities'] as $row): ?><tr data-searchable-row><td><strong><?=e($row['organization_name'])?></strong></td><td><?=number_format((int)$row['requests'])?></td><td><?=number_format((int)$row['items_received'])?></td><td><?=number_format((int)$row['completed_donations'])?></td><td><div class="insights-rate"><span><?=e($row['fulfillment_rate'])?>%</span><i><b style="width:<?=e($row['fulfillment_rate'])?>%"></b></i></div></td></tr><?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </div>
    </div>
</section>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
window.giftlyInsights = <?=json_encode($chartData,$jsonFlags)?>;
</script>
<script src="<?=e(url('assets/js/insights.js'))?>"></script>
<?php require __DIR__ . '/footer.php'; ?>
