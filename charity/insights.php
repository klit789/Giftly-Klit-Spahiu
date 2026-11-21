<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('charity');
$insightsCharityId = (int)$user['charity_id'];
$insightsRoute = 'charity/insights.php';
require __DIR__ . '/../includes/insights_page.php';
