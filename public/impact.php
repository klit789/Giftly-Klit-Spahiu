<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$pdo = db();
$filter = $_GET['category'] ?? '';
if (!is_string($filter) || !in_array($filter, clothing_categories(), true)) {
    $filter = '';
}
$urgent = ($_GET['urgent'] ?? '') === '1';
$where = "cr.status='active'";
$params = [];

if ($urgent) {
    $where .= " AND cr.urgency='urgent'";
}
if ($filter !== '') {
    $where .= ' AND EXISTS (SELECT 1 FROM request_requirements rr WHERE rr.request_id=cr.id AND rr.category=?)';
    $params[] = $filter;
}

$stmt = $pdo->prepare(
    "SELECT c.id,c.organization_name,c.address,c.latitude,c.longitude,
        COUNT(DISTINCT cr.id) active_requests,
        SUM(cr.urgency='urgent') urgent_requests
    FROM charities c
    LEFT JOIN clothing_requests cr ON cr.charity_id=c.id AND $where
    GROUP BY c.id
    HAVING active_requests>0
    ORDER BY urgent_requests DESC,active_requests DESC,c.organization_name"
);
$stmt->execute($params);
$charities = $stmt->fetchAll();

$markers = [];
foreach ($charities as $charity) {
    if ($charity['latitude'] === null || $charity['longitude'] === null) {
        continue;
    }
    $markers[] = [
        'name' => (string)$charity['organization_name'],
        'address' => (string)($charity['address'] ?? ''),
        'lat' => (float)$charity['latitude'],
        'lng' => (float)$charity['longitude'],
        'needs' => (int)$charity['active_requests'],
        'urgent' => (int)$charity['urgent_requests'],
    ];
}

$totals = [
    'needs' => array_sum(array_map(static fn(array $charity): int => (int)$charity['active_requests'], $charities)),
    'charities' => count($charities),
    'urgent' => array_sum(array_map(static fn(array $charity): int => (int)$charity['urgent_requests'], $charities)),
    'locations' => count($markers),
];

$countryCounts = [];
$countryStmt = $pdo->query("SELECT country_code, COUNT(*) charity_count
    FROM charities
    WHERE country_code IS NOT NULL
    GROUP BY country_code");
foreach ($countryStmt->fetchAll() as $countryRow) {
    $countryCounts[(string)$countryRow['country_code']] = (int)$countryRow['charity_count'];
}

$worldMapSvg = file_get_contents(__DIR__ . '/../assets/images/world-political-map.svg');
if ($worldMapSvg === false) {
    throw new RuntimeException('The community map asset could not be loaded.');
}

$pageTitle = 'Community impact map';
require __DIR__ . '/../includes/header.php';
?>
<section class="impact-map-page">
    <div class="shell">
        <header class="impact-map-hero">
            <div>
                <span class="eyebrow">Real places. Real needs.</span>
                <h1>Community impact map</h1>
                <p>Explore active clothing needs and see where registered charities are based. Country shading reflects confirmed charity locations; pins use coordinates shared by charities.</p>
            </div>
            <span class="impact-map-note"><i aria-hidden="true"></i> Verified community needs</span>
        </header>

        <div class="impact-map-stats" aria-label="Community map summary">
            <article class="impact-map-stat">
                <span>Active needs</span>
                <strong><?= e($totals['needs']) ?></strong>
                <small>Open requests in this view</small>
            </article>
            <article class="impact-map-stat">
                <span>Organizations</span>
                <strong><?= e($totals['charities']) ?></strong>
                <small>With matching active needs</small>
            </article>
            <article class="impact-map-stat impact-map-stat-accent">
                <span>Urgent needs</span>
                <strong><?= e($totals['urgent']) ?></strong>
                <small>Requests marked urgent</small>
            </article>
            <article class="impact-map-stat">
                <span>Map locations</span>
                <strong><?= e($totals['locations']) ?></strong>
                <small>Shared by the organizations</small>
            </article>
        </div>

        <form class="impact-map-filters panel" method="get">
            <label class="impact-map-category">
                <span>Clothing category</span>
                <select class="select" name="category">
                    <option value="">All clothing categories</option>
                    <?php foreach (clothing_categories() as $category): ?>
                        <option value="<?= e($category) ?>" <?= $filter === $category ? 'selected' : '' ?>><?= e($category) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="impact-map-urgent">
                <input type="checkbox" name="urgent" value="1" <?= $urgent ? 'checked' : '' ?>>
                <span>Urgent only</span>
            </label>
            <button class="button button-small" type="submit">Update map</button>
        </form>

        <div class="impact-map-layout">
            <section class="panel impact-map-organizations">
                <div class="impact-map-panel-heading">
                    <div>
                        <span class="eyebrow">Community snapshot</span>
                        <h2>Organizations</h2>
                    </div>
                    <span class="impact-map-count"><?= e($totals['charities']) ?></span>
                </div>
                <div class="impact-map-table-wrap">
                    <table class="data-table impact-map-table">
                        <thead>
                            <tr><th>Organization</th><th>Needs</th><th>Urgent</th><th>Location</th></tr>
                        </thead>
                        <tbody>
                            <?php if (!$charities): ?>
                                <tr><td colspan="4"><div class="empty">No active needs match these filters.</div></td></tr>
                            <?php endif; ?>
                            <?php foreach ($charities as $charity): ?>
                                <tr>
                                    <td>
                                        <a class="impact-map-charity-link" href="<?= e(url('donor/browse.php?charity=' . (int)$charity['id'])) ?>"><?= e($charity['organization_name']) ?></a>
                                        <?php if (!empty($charity['address'])): ?><small><?= e($charity['address']) ?></small><?php endif; ?>
                                    </td>
                                    <td><?= e((int)$charity['active_requests']) ?></td>
                                    <td><span class="impact-map-urgent-count"><?= e((int)$charity['urgent_requests']) ?></span></td>
                                    <td><?= $charity['latitude'] !== null && $charity['longitude'] !== null ? 'Pinned' : 'Not shared' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="impact-map-footnote">Only charity-provided coordinates are shown.</p>
            </section>

            <section class="panel impact-map-card" aria-label="Charities by country map">
                <div class="impact-map-panel-heading">
                    <div>
                        <span class="eyebrow">Community footprint</span>
                        <h2>Where needs are growing</h2>
                    </div>
                    <span class="impact-map-pin-key"><i aria-hidden="true"></i> Shared location</span>
                </div>
                <div class="impact-world-map" id="impact-world-map">
                    <?= $worldMapSvg ?>
                    <svg class="impact-world-pins" viewBox="0 0 1200 600" role="img" aria-label="Giftly charity locations; markers appear only for coordinates shared with Giftly">
                        <g id="impact-map-pins" aria-label="Charity locations"></g>
                    </svg>
                    <?php if (!$markers): ?>
                        <div class="impact-map-empty">No charity-shared coordinates yet</div>
                    <?php endif; ?>
                    <div class="impact-map-tooltip" id="impact-map-tooltip" role="tooltip" aria-hidden="true"></div>
                </div>
                <div class="impact-country-legend" aria-label="Map shading shows number of charities with confirmed countries">
                    <span><i class="impact-country-swatch" style="--country-shade:#ffffff"></i>0</span>
                    <span><i class="impact-country-swatch" style="--country-shade:#fff0e6"></i>1</span>
                    <span><i class="impact-country-swatch" style="--country-shade:#ffd5bd"></i>2</span>
                    <span><i class="impact-country-swatch" style="--country-shade:#ffb18a"></i>3</span>
                    <span><i class="impact-country-swatch" style="--country-shade:#f47d4c"></i>4</span>
                    <span><i class="impact-country-swatch" style="--country-shade:#c94320"></i>5+</span>
                    <small>Charities</small>
                </div>
                <p class="impact-map-footnote">Shading counts charity profiles that have confirmed their country. Hover over or focus a country to see its name and exact count.</p>
            </section>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const markers = <?= json_encode($markers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const countryCounts = <?= json_encode($countryCounts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const layer = document.querySelector('#impact-map-pins');
    const map = document.querySelector('#impact-world-map');
    const tooltip = document.querySelector('#impact-map-tooltip');

    const shades = ['#ffffff', '#fff0e6', '#ffd5bd', '#ffb18a', '#f47d4c', '#c94320'];
    const countryPaths = map.querySelectorAll('.impact-world-image path[data-country-code]');
    countryPaths.forEach(country => {
        const count = Number(countryCounts[country.dataset.countryCode] || 0);
        const label = `${country.dataset.countryName}: ${count} ${count === 1 ? 'charity' : 'charities'}`;
        country.style.fill = shades[Math.min(count, shades.length - 1)];
        country.setAttribute('tabindex', '0');
        country.setAttribute('aria-label', label);

        let title = country.querySelector('title');
        if (!title) {
            title = document.createElementNS('http://www.w3.org/2000/svg', 'title');
            country.append(title);
        }
        title.textContent = label;

        const showTooltip = (x, y) => {
            tooltip.textContent = label;
            tooltip.setAttribute('aria-hidden', 'false');
            tooltip.classList.add('is-visible');
            const bounds = map.getBoundingClientRect();
            const left = Math.max(8, Math.min(x - bounds.left + 12, bounds.width - tooltip.offsetWidth - 8));
            const top = Math.max(8, Math.min(y - bounds.top + 12, bounds.height - tooltip.offsetHeight - 8));
            tooltip.style.left = `${left}px`;
            tooltip.style.top = `${top}px`;
            country.classList.add('is-highlighted');
        };
        const hideTooltip = () => {
            tooltip.classList.remove('is-visible');
            tooltip.setAttribute('aria-hidden', 'true');
            country.classList.remove('is-highlighted');
        };

        country.addEventListener('pointerenter', event => showTooltip(event.clientX, event.clientY));
        country.addEventListener('pointermove', event => showTooltip(event.clientX, event.clientY));
        country.addEventListener('pointerleave', hideTooltip);
        country.addEventListener('focus', () => {
            const bounds = country.getBoundingClientRect();
            showTooltip(bounds.left + bounds.width / 2, bounds.top + bounds.height / 2);
        });
        country.addEventListener('blur', hideTooltip);
    });

    markers.forEach(marker => {
        const x = ((marker.lng + 180) / 360) * 1200;
        const y = ((90 - marker.lat) / 180) * 600;
        const pin = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        pin.setAttribute('class', marker.urgent ? 'impact-world-pin is-urgent' : 'impact-world-pin');
        pin.setAttribute('cx', String(x));
        pin.setAttribute('cy', String(y));
        pin.setAttribute('r', String(Math.min(14, 6 + Math.sqrt(marker.needs))));
        pin.setAttribute('tabindex', '0');
        pin.setAttribute('aria-label', `${marker.name}: ${marker.needs} active needs, ${marker.urgent} urgent`);
        const title = document.createElementNS('http://www.w3.org/2000/svg', 'title');
        title.textContent = `${marker.name} · ${marker.needs} active needs · ${marker.urgent} urgent`;
        pin.append(title);
        layer.append(pin);
    });
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
