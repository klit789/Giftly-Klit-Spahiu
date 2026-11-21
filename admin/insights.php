<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$insightsCharityId = null;
$insightsRoute = 'admin/insights.php';
require __DIR__ . '/../includes/insights_page.php';
