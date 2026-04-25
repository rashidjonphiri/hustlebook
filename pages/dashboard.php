<?php include("../config/db.php");

function hb_result_rows($result): array
{
    $rows = [];
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function hb_format_duration($minutes): string
{
    if (!is_numeric($minutes)) {
        return '0 min';
    }

    $minutes = (float)$minutes;

    if ($minutes > 60) {
        $hours = round($minutes / 60, 1);
        return $hours . ' hr';
    }

    if (floor($minutes) != $minutes) {
        return round($minutes, 1) . ' min';
    }

    return (int)$minutes . ' min';
}

function hb_format_money($amount): string
{
    if (!is_numeric($amount)) {
        return '$0.00';
    }

    return '$' . number_format((float)$amount, 2);
}

$dashboardErrors = [];


$locationQuery = "
SELECT c.location, COUNT(*) as total
FROM interactions i
JOIN customers c ON i.customer_id = c.customer_id
WHERE i.is_sale = 1
GROUP BY c.location
";
$locationData = $conn->query($locationQuery);
$locationData = $locationData ?: false;
$dashboardErrors = $locationData === false ? array_merge($dashboardErrors, ["location data failed to load"]) : $dashboardErrors;
$locationChartData = [];
foreach (hb_result_rows($locationData) as $row) {
    $locationChartData[] = ['value' => (int)$row['total'], 'name' => $row['location']];
}

$responseTimeQuery = "
SELECT interaction_id, response_time, delivery_time, total_time FROM (
SELECT 
interaction_id, 
TIMESTAMPDIFF(MINUTE, inbox_datetime, response_datetime) as response_time, 
TIMESTAMPDIFF(MINUTE, response_datetime, delivery_datetime) as delivery_time,
TIMESTAMPDIFF(MINUTE, inbox_datetime, delivery_datetime) as total_time
FROM interactions) as sub
";
$responseTimeData = $conn->query($responseTimeQuery);
$dashboardErrors = $responseTimeData === false ? array_merge($dashboardErrors, ["Response time data failed to load"]) : $dashboardErrors;
$rt_ids = [];
$rt_resp = [];
$rt_del = [];
$rt_total = [];
foreach (hb_result_rows($responseTimeData) as $row) {
    $rt_ids[] = $row['interaction_id'];
    $rt_resp[] = $row['response_time'];
    $rt_del[] = $row['delivery_time'];
    $rt_total[] = $row['total_time'];
}



$genderQuery = "
SELECT c.gender, COUNT(*) as total
FROM interactions i
JOIN customers c ON i.customer_id = c.customer_id
GROUP BY c.gender
";
$genderData = $conn->query($genderQuery);
$dashboardErrors = $genderData === false ? array_merge($dashboardErrors, ["Gender data failed to load"]) : $dashboardErrors;
$genderChartData = [];
foreach (hb_result_rows($genderData) as $row) {
    $genderChartData[] = ['value' => (int)$row['total'], 'name' => $row['gender']];
}

$sourceQuery = "
SELECT s.source_name, COUNT(*) as total
FROM interactions i
JOIN sources s ON i.source_id = s.source_id
GROUP BY s.source_name
";
$sourceData = $conn->query($sourceQuery);
$dashboardErrors = $sourceData === false ? array_merge($dashboardErrors, ["Source data failed to load"]) : $dashboardErrors;
$sourceNames = [];
$sourceValues = [];
foreach (hb_result_rows($sourceData) as $row) {
    $sourceNames[] = $row['source_name'];
    $sourceValues[] = (int)$row['total'];
}

// Total inquiries
$totalRes = $conn->query("SELECT COUNT(*) as total FROM interactions");
$totalQuery = $totalRes ? ($totalRes->fetch_assoc() ?: ['total' => 0]) : ['total' => 0];

// Total sales
$salesRes = $conn->query("SELECT COUNT(*) as total FROM interactions WHERE is_sale = 1");
$salesQuery = $salesRes ? ($salesRes->fetch_assoc() ?: ['total' => 0]) : ['total' => 0];

// Conversion rate
$conversionRate = ($totalQuery['total'] > 0)
    ? ($salesQuery['total'] / $totalQuery['total']) * 100
    : 0;

// Financial Overview
$financialSalesRes = $conn->query("
    SELECT
        COALESCE(SUM(sm.quantity), 0) AS items_sold,
        COALESCE(SUM(sm.quantity * p.selling_price), 0) AS sales_amount,
        COALESCE(SUM(sm.quantity * p.stocking_price), 0) AS cogs_amount
    FROM stock_movement sm
    JOIN products p ON p.product_id = sm.product_id
    WHERE sm.movement_type = 'OUT'
      AND sm.comments LIKE 'Sale from Interaction #%'
");
$dashboardErrors = $financialSalesRes === false ? array_merge($dashboardErrors, ["Financial sales data failed to load"]) : $dashboardErrors;
$financialSales = $financialSalesRes ? ($financialSalesRes->fetch_assoc() ?: []) : [];

$inventoryValueRes = $conn->query("
    SELECT
        COALESCE(SUM(stock_quantity), 0) AS stock_units,
        COALESCE(SUM(stock_quantity * stocking_price), 0) AS stock_cost_value,
        COALESCE(SUM(stock_quantity * selling_price), 0) AS stock_retail_value
    FROM products
");
$dashboardErrors = $inventoryValueRes === false ? array_merge($dashboardErrors, ["Inventory valuation data failed to load"]) : $dashboardErrors;
$inventoryValue = $inventoryValueRes ? ($inventoryValueRes->fetch_assoc() ?: []) : [];

$drawingsRes = $conn->query("
    SELECT
        COALESCE(SUM(sm.quantity), 0) AS drawing_units,
        COALESCE(SUM(sm.quantity * p.stocking_price), 0) AS drawing_cost_value,
        COALESCE(SUM(sm.quantity * p.selling_price), 0) AS drawing_retail_value
    FROM stock_movement sm
    JOIN products p ON p.product_id = sm.product_id
    WHERE sm.movement_type = 'OUT'
      AND (sm.comments IS NULL OR sm.comments = '' OR sm.comments NOT LIKE 'Sale from Interaction #%')
");
$dashboardErrors = $drawingsRes === false ? array_merge($dashboardErrors, ["Drawings data failed to load"]) : $dashboardErrors;
$drawings = $drawingsRes ? ($drawingsRes->fetch_assoc() ?: []) : [];

$restockRes = $conn->query("
    SELECT
        COALESCE(SUM(sm.quantity * p.stocking_price), 0) AS restock_spend
    FROM stock_movement sm
    JOIN products p ON p.product_id = sm.product_id
    WHERE sm.movement_type = 'IN'
");
$dashboardErrors = $restockRes === false ? array_merge($dashboardErrors, ["Restock spend data failed to load"]) : $dashboardErrors;
$restock = $restockRes ? ($restockRes->fetch_assoc() ?: []) : [];

$itemsSold = (int)($financialSales['items_sold'] ?? 0);
$salesAmount = (float)($financialSales['sales_amount'] ?? 0);
$cogsAmount = (float)($financialSales['cogs_amount'] ?? 0);
$grossProfit = $salesAmount - $cogsAmount;
$grossMarginPercent = $salesAmount > 0 ? ($grossProfit / $salesAmount) * 100 : 0;

$stockUnits = (int)($inventoryValue['stock_units'] ?? 0);
$stockCostValue = (float)($inventoryValue['stock_cost_value'] ?? 0);
$stockRetailValue = (float)($inventoryValue['stock_retail_value'] ?? 0);

$drawingUnits = (int)($drawings['drawing_units'] ?? 0);
$drawingCostValue = (float)($drawings['drawing_cost_value'] ?? 0);
$drawingRetailValue = (float)($drawings['drawing_retail_value'] ?? 0);

$restockSpend = (float)($restock['restock_spend'] ?? 0);
$estimatedCashInHand = $salesAmount - $restockSpend;
$businessNetWorth = $estimatedCashInHand + $stockCostValue;

$avgSaleValue = ((int)$salesQuery['total'] > 0) ? ($salesAmount / (int)$salesQuery['total']) : 0;
$sellThroughRate = ($itemsSold + $stockUnits) > 0 ? ($itemsSold / ($itemsSold + $stockUnits)) * 100 : 0;
$drawingsShare = ($itemsSold + $drawingUnits) > 0 ? ($drawingUnits / ($itemsSold + $drawingUnits)) * 100 : 0;

// Average response time
$avgStatsRes = $conn->query("
    SELECT AVG(TIMESTAMPDIFF(MINUTE, inbox_datetime, response_datetime)) as avg_time
    FROM interactions WHERE response_datetime IS NOT NULL
");
$avgStats = $avgStatsRes ? ($avgStatsRes->fetch_assoc() ?: ['avg_time' => 0]) : ['avg_time' => 0];

$fastestStatsRes = $conn->query("
    SELECT TIMESTAMPDIFF(MINUTE, inbox_datetime, response_datetime) as min_time, inbox_datetime 
    FROM interactions WHERE response_datetime IS NOT NULL ORDER BY min_time ASC LIMIT 1
");
$fastestStats = $fastestStatsRes ? ($fastestStatsRes->fetch_assoc() ?: ['min_time' => 0, 'inbox_datetime' => null]) : ['min_time' => 0, 'inbox_datetime' => null];

$slowestStatsRes = $conn->query("
    SELECT TIMESTAMPDIFF(MINUTE, inbox_datetime, response_datetime) as max_time, inbox_datetime 
    FROM interactions WHERE response_datetime IS NOT NULL ORDER BY max_time DESC LIMIT 1
");
$slowestStats = $slowestStatsRes ? ($slowestStatsRes->fetch_assoc() ?: ['max_time' => 0, 'inbox_datetime' => null]) : ['max_time' => 0, 'inbox_datetime' => null];


$bestSourceRes = $conn->query("
SELECT s.source_name, COUNT(*) as total
FROM interactions i
JOIN sources s ON i.source_id = s.source_id
WHERE i.is_sale = 1
GROUP BY s.source_name
ORDER BY total DESC
LIMIT 1
");
$bestSource = $bestSourceRes ? ($bestSourceRes->fetch_assoc() ?: ['source_name' => 'N/A']) : ['source_name' => 'N/A'];

$comments = $conn->query("
SELECT comment FROM interactions 
WHERE comment IS NOT NULL
");

$dropReasons = $conn->query("
SELECT comment FROM interactions 
WHERE is_sale = 0 AND comment IS NOT NULL
");

$customerNeeds = $conn->query("SELECT comment FROM interactions
where comment LIKE '%wanted%' LIMIT 100");
function needs($mysqlResult)
{
    $needText = [];

    $prefix = "wanted";
    if (!$mysqlResult instanceof mysqli_result) {
        return $needText;
    }

    while ($row = $mysqlResult->fetch_assoc()) {
        $needComment = $row["comment"];
        $startPos = stripos($needComment, $prefix);
        if ($startPos === false) {
            continue;
        }

        $startPos += strlen($prefix);
        $extracted = trim(substr($needComment, $startPos));

        // Keep only text before punctuation (comma, period, etc.)
        $parts = preg_split('/[[:punct:]]/u', $extracted, 2);
        $extracted = trim($parts[0] ?? '');

        // Remove leading standalone "a" / "A"
        $extracted = preg_replace('/^\s*a\s+/i', '', $extracted);
        $extracted = trim($extracted);

        if ($extracted !== '') {
            $needText[] = $extracted;
        }
    }

    return array_values(array_unique($needText));
}
$needTextArray = needs($customerNeeds);
$keywords = [];

function filterWords($mysqlResult)
{
    $keywords = [];
    if (!$mysqlResult instanceof mysqli_result) {
        return $keywords;
    }

    while ($row = $mysqlResult->fetch_assoc()) {
        $words = explode(" ", strtolower($row['comment']));

        foreach ($words as $word) {
            if (strlen($word) > 3) { // ignore small words
                if (!isset($keywords[$word])) {
                    $keywords[$word] = 0;
                }
                $keywords[$word]++;
            }
        }
    }
    return $keywords;
}

$keywords = filterWords($comments);
$dropReasons = filterWords($dropReasons);
arsort($keywords);
$topKeywords = array_slice($keywords, 0, 4);

$wordCloudData = [];
foreach ($keywords as $word => $count) {
    $wordCloudData[] = ['name' => $word, 'value' => $count];
}


// Activity Trends (Time Series)
$trendInboxSale = [];
$trendInboxNoSale = [];
$trendInboxRaw = [];
$inboxHours = [];

$rawQ = $conn->query("SELECT inbox_datetime, is_sale FROM interactions ORDER BY inbox_datetime ASC");

foreach (hb_result_rows($rawQ) as $row) {
    if (!empty($row['inbox_datetime'])) {
        $t = strtotime($row['inbox_datetime']);
        $hour = (float)date('H', $t) + (float)date('i', $t) / 60;
        $day = date('Y-m-d', $t);
        $exactTime = date('g:i A', $t);
        $exactDateTime = date('M d, Y g:i A', $t);
        $pt = [
            'value' => [$day, $hour],
            'exact_time' => $exactTime,
            'exact_datetime' => $exactDateTime,
        ];
        $outcome = $row['is_sale'] == 1 ? 'Sale' : 'No Sale';
        $inboxHours[] = $hour;
        $trendInboxRaw[] = [
            'day' => $day,
            'hour' => $hour,
            'exact_time' => $exactTime,
            'exact_datetime' => $exactDateTime,
            'outcome' => $outcome,
        ];

        if ($row['is_sale'] == 1) {
            $trendInboxSale[] = $pt;
        } else {
            $trendInboxNoSale[] = $pt;
        }
    }
}

$inboxAvg = !empty($inboxHours) ? array_sum($inboxHours) / count($inboxHours) : 0;

// Hourly Distribution for Radar Chart (24 hours)
$hourlyDist = array_fill(0, 24, 0);
$hourQuery = "SELECT HOUR(inbox_datetime) as hr, COUNT(*) as total 
              FROM interactions 
              WHERE inbox_datetime IS NOT NULL 
              GROUP BY hr";
$hourRes = $conn->query($hourQuery);
foreach (hb_result_rows($hourRes) as $row) {
    $hourlyDist[(int)$row['hr']] = (int)$row['total'];
}

// Prepare data for Clockwise Radar Chart (12AM at top, then 11PM, 10PM... 1AM counter-clockwise)
// This makes the labels appear clockwise (12AM -> 1AM -> 2AM) to the user.
$radarData = [$hourlyDist[0]];
for ($i = 23; $i >= 1; $i--) {
    $radarData[] = $hourlyDist[$i];
}

// Logic for Ad Posting Advice
$maxHourValue = !empty($hourlyDist) ? max($hourlyDist) : 1;
if ($maxHourValue == 0) $maxHourValue = 1;

// Hourly Distribution for Sales only
$hourlySalesDist = array_fill(0, 24, 0);
$hourSalesRes = $conn->query("SELECT HOUR(inbox_datetime) as hr, COUNT(*) as total FROM interactions WHERE is_sale = 1 AND inbox_datetime IS NOT NULL GROUP BY hr");
foreach (hb_result_rows($hourSalesRes) as $row) {
    $hourlySalesDist[(int)$row['hr']] = (int)$row['total'];
}

// Inbox Probabilities by Time Range
$totalInboxes = (int)$totalQuery['total'];
$rangeStats = [];
$timeRanges = [
    'Early Morning' => [4, 5, 6, 7],
    'Morning'       => [8, 9, 10, 11],
    'Afternoon'     => [12, 13, 14, 15],
    'Evening'       => [16, 17, 18, 19],
    'Night'         => [20, 21, 22, 23],
    'Late Night'    => [0, 1, 2, 3]
];

foreach ($timeRanges as $label => $hours) {
    $sum = 0;
    foreach ($hours as $h) {
        $sum += ($hourlyDist[$h] ?? 0);
    }
    $sum = 0;
    $sumSales = 0;
    foreach ($hours as $h) {
        $sum += ($hourlyDist[$h] ?? 0);
        $sumSales += ($hourlySalesDist[$h] ?? 0);
    }

    // Map labels to contextual icons
    $icon = 'bi-clock';
    if ($label === 'Early Morning') $icon = 'bi-sunrise';
    elseif ($label === 'Morning') $icon = 'bi-sun';
    elseif ($label === 'Afternoon') $icon = 'bi-brightness-high';
    elseif ($label === 'Evening') $icon = 'bi-sunset';
    elseif ($label === 'Night') $icon = 'bi-moon';
    elseif ($label === 'Late Night') $icon = 'bi-moon-stars';

    $rangeStats[] = [
        'label' => $label,
        'icon'  => $icon,
        'count' => $sum,
        'sales' => $sumSales,
        'conv'  => $sum > 0 ? round(($sumSales / $sum) * 100, 1) : 0,
        'prob'  => $totalInboxes > 0 ? round(($sum / $totalInboxes) * 100, 1) : 0
    ];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
    <title>Dashboard</title>
    <?php session_start(); ?>
    <link href="../assets/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../bootstrap-icons/font/bootstrap-icons.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .chart {
            height: 400px;
        }

        /* Viridian Greenish-Gray Dark Mode Palette */
        [data-bs-theme="dark"] body,
        [data-bs-theme="dark"] .main-content {
            background-color: #121717 !important;
            color: #e8eaed !important;
        }

        [data-bs-theme="dark"] .card,
        [data-bs-theme="dark"] .section-card,
        [data-bs-theme="dark"] .page-header {
            background-color: #1b2222 !important;
            border-color: #2d3333 !important;
            box-shadow: none !important;
        }

        [data-bs-theme="dark"] .metric-card {
            background-color: #232b2b !important;
            border-color: #343d3d !important;
        }

        [data-bs-theme="dark"] .table {
            color: #e8eaed !important;
            border-color: #2d3333 !important;
        }

        [data-bs-theme="dark"] .alert-info {
            background-color: #1a2a2a !important;
            border-color: #008b8b !important;
            color: #4db6ac !important;
        }

        [data-bs-theme="dark"] .btn-outline-light {
            border-color: #3c4043;
            color: #80cbc4;
        }

        .bg-lime {
            background-color: #a3ff00 !important;
        }

        .text-lime {
            color: #a3ff00 !important;
        }

        /* Custom Tab Styling to look like cards */
        .nav-metric .nav-link {
            background: none;
            border: none;
            padding: 0;
            width: 100%;
            color: inherit;
            transition: transform 0.2s;
        }

        .nav-metric .nav-link.active {
            background-color: transparent !important;
        }

        .nav-metric .nav-link.active .metric-card {
            border-color: #a3ff00 !important;
            background: #a3ff00 !important;
            transform: translateY(-3px);
        }

        .nav-metric .nav-link.active .metric-card h3,
        .nav-metric .nav-link.active .metric-card h6 {
            color: #000 !important;
        }
    </style>
</head>

<body>
    <?php include("../includes/sidebar.php"); ?>
    <div class="main-content">
        <div class="container page-shell">
            <div class="card page-header">
                <div>
                    <h2>Analytics Dashboard</h2>
                    <p>Track conversation performance, timing patterns, source quality, and customer signals from one premium overview.</p>
                </div>
            </div>

            <?php if (!empty($dashboardErrors)): ?>
                <div class="alert alert-warning rounded-4">
                    Some dashboard sections could not load because of missing or incompatible data.
                </div>
            <?php elseif ((int)$totalQuery['total'] === 0): ?>
                <div class="alert alert-info rounded-4">
                    No interaction data is available yet. Save a few records to populate the dashboard.
                </div>
            <?php endif; ?>

            <div class="row mb-4">
                <div class="col-12">
                    <div class="card card1 section-card">
                        <h5>Inbox Timestamps</h5>
                        <div id="trendChart" class="chart"></div>

                        <ul class="nav nav-pills nav-metric row g-3 mb-4 border-0" id="metricTabs" role="tablist">
                            <li class="nav-item col-md-4" role="presentation">
                                <button class="nav-link" id="inquiry-tab" data-bs-toggle="tab" data-bs-target="#inquiry-pane" type="button" role="tab">
                                    <div class="metric-card text-center mb-0">
                                        <h6>Total Inquiries</h6>
                                        <h3><?= $totalQuery['total'] ?></h3>
                                    </div>
                                </button>
                            </li>
                            <li class="nav-item col-md-4" role="presentation">
                                <button class="nav-link" id="sales-tab" data-bs-toggle="tab" data-bs-target="#sales-pane" type="button" role="tab">
                                    <div class="metric-card text-center mb-0">
                                        <h6>Total Sales</h6>
                                        <h3><?= $salesQuery['total'] ?></h3>
                                    </div>
                                </button>
                            </li>
                            <li class="nav-item col-md-4" role="presentation">
                                <button class="nav-link active" id="conv-tab" data-bs-toggle="tab" data-bs-target="#conv-pane" type="button" role="tab">
                                    <div class="metric-card text-center mb-0">
                                        <h6>Conversion Rate</h6>
                                        <h3><?= round($conversionRate, 1) ?>%</h3>
                                    </div>
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content bg-dark-subtle rounded-4 p-3" id="metricTabsContent">
                            <div class="tab-pane fade" id="inquiry-pane" role="tabpanel">
                                <div class="row text-center">
                                    <?php foreach ($rangeStats as $stat): ?>
                                        <div class="col-md-2 col-6 mb-3">
                                            <div class="small text-white-50"><?= $stat['label'] ?></div>
                                            <div class="fw-bold"><?= $stat['count'] ?> <small class="text-secondary">inboxes</small></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="sales-pane" role="tabpanel">
                                <div class="row text-center">
                                    <?php foreach ($rangeStats as $stat): ?>
                                        <div class="col-md-2 col-6 mb-3">
                                            <div class="small text-white-50"><?= $stat['label'] ?></div>
                                            <div class="fw-bold "><?= $stat['sales'] ?> <small class="text-secondary">sales</small></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="tab-pane fade show active" id="conv-pane" role="tabpanel">
                                <div class="row text-center">
                                    <?php foreach ($rangeStats as $stat): ?>
                                        <div class="col-md-2 col-6 mb-3">
                                            <div class="small text-white-50"><?= $stat['label'] ?></div>
                                            <div class="fw-bold"><?= $stat['conv'] ?>%</div>
                                            <div class="progress mt-1" style="height: 5px; background: #92929f; border: 1px solid #818080">
                                                <div class="progress-bar bg-lime" role="progressbar" style="width: <?= $stat['conv'] ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                        </div>


                    </div>
                </div>
            </div>
            <div class="card card1">

                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class=" section-card h-100">
                            <h5>24-Hour Inbox Frequency</h5>
                            <div id="hourChart" class="chart"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class=" section-card border-lime h-100">
                            <div class="rounded-4">
                                <h5><i class="bi bi-clock-history"></i> Inbox Probability</h5>
                                <p class="section-subtitle small text-white-50">Likelihood of receiving leads by time range.</p>
                                <ul class="list-group list-group-flush bg-transparent mt-3">
                                    <?php $maxProb = !empty($rangeStats) ? max(array_column($rangeStats, 'prob')) : 0; ?>
                                    <?php foreach ($rangeStats as $stat): ?>
                                        <?php
                                        $badgeStyle = 'background-color: transparent;';
                                        $rowHighlight = '';

                                        // Apply lime background to the highest probability range
                                        if ($stat['prob'] > 0 && $stat['prob'] == $maxProb) {
                                            $badgeStyle = 'background-color: #a3ff00; color: #000; font-weight: 600;';
                                            $rowHighlight = 'style="background: rgba(163, 255, 0, 0.1) !important;"';
                                        }
                                        ?>
                                        <li class="list-group-item bg-transparent border-secondary d-flex align-items-center px-2" <?= $rowHighlight ?>>
                                            <i class="bi <?= $stat['icon'] ?> me-2 text-secondary" style="font-size: 0.9rem;"></i>
                                            <span class="small flex-grow-1 text-body"><?= $stat['label'] ?></span>
                                            <span class="small me-4 text-secondary" title="Frequency"><?= $stat['count'] ?></span>
                                            <span class="badge rounded-pill border border-secondary text-secondary" style="min-width: 65px; <?= $badgeStyle ?>"><?= $stat['prob'] ?>%</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <div class="mt-3 pt-2 border-top border-secondary text-secondary" style="font-size: 0.72rem;">
                                    <i class="bi bi-info-circle me-1"></i>
                                    EM: 04-08 | M: 08-12 | A: 12-16 | E: 16-20 | N: 20-00 | LN: 00-04
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-12">
                    <div class="card card1 section-card">
                        <h5>time</h5>
                        <div id="timeChart" class="chart"></div>
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="metric-card text-center h-100">
                                    <h6>Avg Response Time</h6>
                                    <h3><?= hb_format_duration($avgStats['avg_time']) ?></h3>

                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="metric-card text-center">
                                    <h6>Fastest Response</h6>
                                    <h3><?= hb_format_duration($fastestStats['min_time'] ?? 0) ?></h3>
                                    <small class="text-white-50"><?= !empty($fastestStats['inbox_datetime']) ? date('M d, h:i A', strtotime($fastestStats['inbox_datetime'])) : '' ?></small>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="metric-card text-center">
                                    <h6>Slowest Response</h6>
                                    <h3><?= hb_format_duration($slowestStats['max_time'] ?? 0) ?></h3>
                                    <small class="text-white-50"><?= !empty($slowestStats['inbox_datetime']) ? date('M d, h:i A', strtotime($slowestStats['inbox_datetime'])) : '' ?></small>
                                </div>
                            </div>

                        </div>

                    </div>
                </div>

            </div>


            <div class="card card1 section-card mb-3">
                <h4>Sales & Financial Overview</h4>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="metric-card text-center h-100">
                            <h6>Total Items Sold <i class="bi bi-question-circle-fill ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Total units sold from stock movements linked to sales interactions."></i></h6>
                            <h3><?= number_format($itemsSold) ?></h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="metric-card text-center h-100">
                            <h6>Total Sales Amount <i class="bi bi-question-circle-fill ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Total revenue estimate based on sold quantity multiplied by product selling price."></i></h6>
                            <h3><?= hb_format_money($salesAmount) ?></h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="metric-card text-center h-100">
                            <h6>Stock Value (Cost) <i class="bi bi-question-circle-fill ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Current inventory valued at stocking price (cost price)."></i></h6>
                            <h3><?= hb_format_money($stockCostValue) ?></h3>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="metric-card text-center h-100">
                            <h6>Stock Value (Sale) <i class="bi bi-question-circle-fill ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Current inventory valued at selling price to show revenue potential after sale."></i></h6>
                            <h3><?= hb_format_money($stockRetailValue) ?></h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="metric-card text-center h-100">
                            <h6>Drawings Value (Cost) <i class="bi bi-question-circle-fill ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Value of stock moved out for non-sale reasons, measured at cost price."></i></h6>
                            <h3><?= hb_format_money($drawingCostValue) ?></h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="metric-card text-center h-100">
                            <h6>Estimated Cash In Hand <i class="bi bi-question-circle-fill ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Estimated as Sales Amount minus Recorded Restock Spend."></i></h6>
                            <h3><?= hb_format_money($estimatedCashInHand) ?></h3>
                        </div>
                    </div>


                </div>

                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="metric-card text-center h-100">
                            <h6>Business Net Worth <i class="bi bi-question-circle-fill ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Estimated as Cash In Hand plus current Stock Value (Cost)."></i></h6>
                            <h3><?= hb_format_money($businessNetWorth) ?></h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="metric-card text-center h-100">
                            <h6>Gross Profit <i class="bi bi-question-circle-fill ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Sales Amount minus cost of sold items (COGS)."></i></h6>
                            <h3><?= hb_format_money($grossProfit) ?></h3>
                            <small class="text-white-50"><?= round($grossMarginPercent, 1) ?>% margin</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="metric-card text-center h-100">
                            <h6>Sell-Through Rate <i class="bi bi-question-circle-fill ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Share of total handled units that were sold: Sold / (Sold + Current Stock)."></i></h6>
                            <h3><?= round($sellThroughRate, 1) ?>%</h3>
                            <small class="text-white-50"><?= number_format($stockUnits) ?> units in stock</small>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-8">
                        <div id="financialChart" class="chart"></div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-info rounded-4 h-100 mb-0">
                            <h6 class="mb-2"><i class="bi bi-info-circle"></i> Financial Notes</h6>
                            <p class="mb-2">Cash in hand is estimated as <b>Sales Amount - Recorded Restock Spend</b>.</p>
                            <p class="mb-2">Net worth is estimated as <b>Cash In Hand + Stock Value (Cost)</b>.</p>
                            <p class="mb-0">Drawings represent stock-out records not tied to <code>Sale from Interaction #...</code> comments.</p>
                        </div>
                    </div>
                </div>
                <div class="mt-3 text-white-50 small">
                    Extra insights: Average sale value <?= hb_format_money($avgSaleValue) ?>, Drawings share <?= round($drawingsShare, 1) ?>%, Stock value at retail <?= hb_format_money($stockRetailValue) ?>, Drawings value at retail <?= hb_format_money($drawingRetailValue) ?>.
                </div>
            </div>

            <div class="card card1 section-card mb-3">
                <h4>Comments</h4>
                <div class="row">
                    <div class="col-md-6">
                        <div class="rounded-4">
                            <div id="commentCloud" class="" style="height: 200px;"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="rounded-4 h-100">
                            <div class="card-body">
                                <h6 class="mb-2">Customer Needs</h6>
                                <p class="mb-0">
                                    <?php if (!empty($needTextArray)): ?>
                                        <?php foreach ($needTextArray as $needText): ?>
                                            <span class="badge rounded-pill me-1 mb-1" style="background-color: #a3ff00; color: #000; border: 1px solid #2d3333;"><?= htmlspecialchars($needText) ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted">No customer needs found in comments yet.</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>


            <div class="row">
                <div class="col-md-12">
                    <div class="card card1 section-card mb-3">
                        <h5>Source Performance</h5>
                        <div id="sourceChart" class="chart"></div>
                        <div class="metric-card mb-3">
                            <h5>Best Source</h5>
                            <h3><?= $bestSource['source_name'] ?? 'N/A' ?></h3>
                        </div>

                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card card1 section-card mb-3">
                        <h5>location Distribution</h5>
                        <div id="locationChart" class="chart"></div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card card1 section-card mb-3">
                        <h5>Gender Ratio</h5>
                        <div id="genderChart" class="chart"></div>
                    </div>
                </div>

            </div>

        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/echarts/dist/echarts.min.js"></script>
    <script src="https://echarts.apache.org/en/js/vendors/echarts-simple-transform/dist/ecSimpleTransform.min.js"></script>

    <script src="../../echarts-5.6.0/dist/echarts.min.js"></script>
    <script src="../lib/vintage.js"></script>
    <script src="../lib/infographic.js"></script>
    <script src="../lib/infographic.js"></script>

    <script src="../lib/echarts-wordcloud.js"></script>

    <script>
        let trendChart, timeChart, locationChart, genderChart, sourceChart, wordChart, hourChart, financialChart;
        let trendIsAggregated = false;

        function initTooltips() {
            if (!window.bootstrap || !bootstrap.Tooltip) return;
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
                bootstrap.Tooltip.getOrCreateInstance(el);
            });
        }

        function initCharts() {
            // Dispose of existing instances to allow re-initialization with new theme
            if (trendChart) trendChart.dispose();
            if (timeChart) timeChart.dispose();
            if (locationChart) locationChart.dispose();
            if (genderChart) genderChart.dispose();
            if (sourceChart) sourceChart.dispose();
            if (wordChart) wordChart.dispose();
            if (hourChart) hourChart.dispose();
            if (financialChart) financialChart.dispose();

            const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
            const chartTheme = isDark ? 'dark' : '';
            const textColor = isDark ? '#fff' : '#212529';
            const splitLineColor = isDark ? '#3c4043' : '#dee2e6';
            const trendSourceData = <?= json_encode($trendInboxRaw) ?>;

            // Unified High-Contrast Palette
            const limePalette = ['#a3ff00', '#d4ff70', '#ffffff', '#76ba00', '#e0ffb3', '#2d3333'];
            const accentLime = '#a3ff00';

            if (window.ecSimpleTransform?.aggregate && !window.__hbAggregateRegistered) {
                echarts.registerTransform(window.ecSimpleTransform.aggregate);
                window.__hbAggregateRegistered = true;
            }

            function getTrendOption(isAggregated) {
                return {
                    backgroundColor: 'transparent',
                    animationDurationUpdate: 500,
                    tooltip: isAggregated ? {
                        trigger: 'item'
                    } : {
                        trigger: 'axis',
                        axisPointer: {
                            type: 'cross',
                            label: {
                                backgroundColor: '#6a7985'
                            }
                        },
                        formatter: function(params) {
                            return params.map(function(param) {
                                const point = param.data || {};
                                const exactDateTime = point.exact_datetime || param.axisValueLabel;
                                return `${param.marker}${param.seriesName}: ${exactDateTime}`;
                            }).join('<br>');
                        }
                    },
                    legend: {
                        show: !isAggregated,
                        data: ['Sale', 'No Sale'],
                        textStyle: {
                            color: textColor
                        }
                    },
                    dataset: [{
                        id: 'inboxRaw',
                        source: trendSourceData
                    }, {
                        id: 'inboxAggregate',
                        fromDatasetId: 'inboxRaw',
                        transform: {
                            type: 'ecSimpleTransform:aggregate',
                            config: {
                                groupBy: 'outcome',
                                resultDimensions: [{
                                    from: 'outcome'
                                }, {
                                    from: 'hour',
                                    method: 'count',
                                    name: 'total'
                                }]
                            }
                        }
                    }],
                    toolbox: {
                        feature: {
                            myAggregateToggle: {
                                show: true,
                                title: isAggregated ? 'Show Scatter' : 'Show Aggregate Bars',
                                icon: 'path://M4 18h4V8H4zm6 0h4V4h-4zm6 0h4v-6h-4z',
                                onclick: function() {
                                    trendIsAggregated = !trendIsAggregated;
                                    trendChart.setOption(getTrendOption(trendIsAggregated), true);
                                }
                            },
                            dataZoom: {
                                yAxisIndex: isAggregated ? 'all' : 'none'
                            },
                            restore: {},
                            saveAsImage: {}
                        }
                    },
                    xAxis: isAggregated ? {
                        type: 'category',
                        name: 'Outcome',
                        axisLabel: {
                            color: textColor
                        }
                    } : {
                        type: 'time',
                        name: 'Day of Month',
                        axisLabel: {
                            interval: 0,
                            color: textColor
                        }
                    },
                    yAxis: isAggregated ? {
                        type: 'value',
                        name: 'Inbox Count',
                        minInterval: 1,
                        axisLabel: {
                            color: textColor
                        },
                        splitLine: {
                            lineStyle: {
                                color: splitLineColor
                            }
                        }
                    } : {
                        type: 'value',
                        name: 'interaction time(hour)',
                        min: 0,
                        max: 24,
                        axisLabel: {
                            color: textColor
                        },
                        splitLine: {
                            lineStyle: {
                                color: splitLineColor
                            }
                        }
                    },
                    series: isAggregated ? [{
                        name: 'Inbox Count',
                        type: 'bar',
                        datasetId: 'inboxAggregate',
                        encode: {
                            x: 'outcome',
                            y: 'total',
                            tooltip: ['outcome', 'total']
                        },
                        universalTransition: true,
                        barMaxWidth: 120,
                        label: {
                            show: true,
                            position: 'top',
                            color: textColor
                        },
                        itemStyle: {
                            color: function(params) {
                                const outcome = params.name ?? params.value?.[0] ?? params.data?.outcome;
                                return outcome === 'Sale' ? accentLime : '#444';
                            },
                            borderRadius: [12, 12, 0, 0]
                        }
                    }] : [{
                            name: 'Sale',
                            type: 'scatter',
                            symbolSize: 15,
                            opacity: 0.1,
                            universalTransition: true,
                            datasetId: 'inboxRaw',
                            encode: {
                                x: 'day',
                                y: 'hour',
                                tooltip: ['day', 'hour']
                            },
                            data: <?= json_encode($trendInboxSale) ?>,
                            color: accentLime,
                            markLine: {
                                symbol: 'none',
                                data: [{
                                    yAxis: <?= $inboxAvg ?>,
                                    name: 'Avg Time',
                                    lineStyle: {
                                        color: '#ceb15f',
                                        type: 'dotted',
                                    },
                                }]
                            }
                        },
                        {
                            name: 'No Sale',
                            type: 'scatter',
                            symbolSize: 15,
                            universalTransition: true,
                            data: <?= json_encode($trendInboxNoSale) ?>,
                            color: '#444'
                        }
                    ]
                };
            }

            // TREND CHART
            trendChart = echarts.init(document.getElementById('trendChart'), chartTheme);
            trendChart.setOption(getTrendOption(trendIsAggregated), true);
            trendChart.off('restore');
            trendChart.on('restore', function() {
                trendIsAggregated = false;
                trendChart.setOption(getTrendOption(false), true);
            });

            // TIME CHART
            timeChart = echarts.init(document.getElementById('timeChart'), 'infographic', chartTheme);

            timeChart.setOption({
                xAxis: {
                    type: 'category',
                    data: <?= json_encode($rt_ids) ?>,
                },
                yAxis: {
                    type: 'value',
                    splitLine: {
                        lineStyle: {
                            color: splitLineColor
                        }
                    }

                },
                legend: {
                    data: ['Response Time', 'Delivery Time']
                },
                toolbox: {
                    feature: {
                        magicType: {
                            type: ['line', 'bar']
                        },
                        saveAsImage: {},
                        dataZoom: {
                            yAxisIndex: 'none'
                        },
                        restore: {},

                    }
                },
                backgroundColor: 'transparent',
                series: [{
                        name: 'Delivery Time',
                        data: <?= json_encode($rt_del) ?>,
                        type: 'bar',
                        barGap: '-100%',
                        z: 2,
                        label: {
                            show: false,
                            position: 'top'
                        },
                        itemStyle: {
                            borderRadius: [10, 10, 0, 0]
                        }
                    },
                    {
                        name: 'Response Time',
                        data: <?= json_encode($rt_resp) ?>,
                        type: 'bar',
                        z: 3,
                        label: {
                            show: true,
                            position: 'top',
                            color: textColor,
                            fontSize: 9
                        },
                        itemStyle: {
                            borderRadius: [10, 10, 0, 0],
                            color: accentLime
                        }
                    }
                ]
            });

            // FINANCIAL CHART
            financialChart = echarts.init(document.getElementById('financialChart'), 'infographic', chartTheme);
            financialChart.setOption({
                backgroundColor: 'transparent',
                tooltip: {
                    trigger: 'axis',
                    axisPointer: {
                        type: 'shadow'
                    }
                },
                xAxis: {
                    type: 'category',
                    axisLabel: {
                        color: textColor,
                        interval: 0
                    },
                    data: ['Sales Amount', 'Stock Value', 'Drawings', 'Restock Spend', 'Net Worth']
                },
                yAxis: {
                    type: 'value',
                    axisLabel: {
                        color: textColor,
                        formatter: function(value) {
                            return '$' + Number(value).toLocaleString();
                        }
                    },
                    splitLine: {
                        lineStyle: {
                            color: splitLineColor
                        }
                    }
                },
                series: [{
                    name: 'Value',
                    type: 'bar',
                    data: [
                        <?= json_encode(round($salesAmount, 2)) ?>,
                        <?= json_encode(round($stockCostValue, 2)) ?>,
                        <?= json_encode(round($drawingCostValue, 2)) ?>,
                        <?= json_encode(round($restockSpend, 2)) ?>,
                        <?= json_encode(round($businessNetWorth, 2)) ?>
                    ],
                    itemStyle: {
                        borderRadius: [10, 10, 0, 0],
                        color: function(params) {
                            return limePalette[params.dataIndex % limePalette.length];
                        }
                    },
                    label: {
                        show: true,
                        position: 'top',
                        color: textColor,
                        formatter: function(params) {
                            return '$' + Number(params.value).toLocaleString();
                        }
                    }
                }],
                toolbox: {
                    feature: {
                        magicType: {
                            type: ['bar', 'line']
                        },
                        saveAsImage: {},
                        restore: {}
                    }
                }
            });

            // location CHART
            locationChart = echarts.init(document.getElementById('locationChart'), 'infographic', chartTheme);

            locationChart.setOption({
                color: limePalette,
                series: [{
                    type: 'pie',
                    data: <?= json_encode($locationChartData) ?>,
                    radius: ['50%', '65%'],
                    padAngle: 5,
                    label: {
                        color: textColor,
                    },
                    emphasis: {
                        label: {
                            show: true,
                            fontSize: 20,
                            fontWeight: 'bold',
                            color: textColor,
                        }
                    },
                    itemStyle: {
                        borderRadius: 20,
                        borderWidth: 1,
                        borderColor: '#000'
                    },

                }],
                backgroundColor: 'transparent',

            });

            // GENDER CHART
            genderChart = echarts.init(document.getElementById('genderChart'), 'infographic', chartTheme);

            genderChart.setOption({
                legend: {
                    top: 'bottom',
                    textStyle: {
                        color: textColor
                    }
                },
                color: [accentLime, '#343d3d', '#ffffff'],
                series: [{
                    type: 'pie',
                    radius: ['45%', '65%'],
                    padAngle: 2,
                    label: {
                        show: false
                    },
                    
                    itemStyle: {
                        borderRadius: 10,
                        borderWidth: 1,
                        borderColor: '#000'
                    },
                    data: <?= json_encode($genderChartData) ?>
                }],
                backgroundColor: 'transparent'
            });

            // SOURCE CHART
            sourceChart = echarts.init(document.getElementById('sourceChart'), 'infographic', chartTheme);

            sourceChart.setOption({
                xAxis: {
                    type: 'value',
                    splitLine: {
                        show: true,
                        lineStyle: {
                            color: '#524444'
                        }
                    },
                },
                yAxis: {
                    type: 'category',
                    axisLine: {
                        show: true
                    },
                    axisLabel: {
                        interval: 0,
                        rotate: 0,
                        color: textColor
                    },

                    data: <?= json_encode($sourceNames) ?>
                },
                series: [{
                    data: <?= json_encode($sourceValues) ?>,
                    type: 'bar',
                    itemStyle: {
                        normal: {
                            color: accentLime,
                            borderRadius: [0, 10, 10, 0],
                            borderWidth: 1,
                            borderColor: '#000',
                            shadowBlur: 0,
                            shadowColor: '#000',
                            label: {
                                show: true,
                                position: 'right',
                                color: '#cacada',
                                fontSize: 12,
                                angle: 0
                            },
                        },
                        barBorderRadius: 5,

                    },
                }],
                backgroundColor: 'transparent'
            });

            // WORD CLOUD CHART
            wordChart = echarts.init(document.getElementById('commentCloud'), chartTheme);
            wordChart.setOption({
                backgroundColor: 'transparent',
                tooltip: {
                    show: true
                },
                series: [{
                    type: 'wordCloud',
                    gridSize: 8,
                    sizeRange: [14, 50],
                    rotationRange: [-45, 90],
                    rotationStep: 45,
                    shape: 'rectangle',
                    width: '100%',
                    height: '100%',
                    drawOutOfBound: false,
                    textStyle: {
                        color: function() {
                            const choices = [accentLime, '#ffffff', '#d4ff70', '#e0ffb3'];
                            return choices[Math.floor(Math.random() * choices.length)];
                        }
                    },
                    emphasis: {
                        textStyle: {
                            shadowBlur: 10,
                            shadowColor: '#333'
                        }
                    },
                    data: <?= json_encode($wordCloudData) ?>
                }]
            });

            // HOUR RADAR CHART
            hourChart = echarts.init(document.getElementById('hourChart'), chartTheme);
            const hours = ['12AM', '11PM', '10PM', '9PM', '8PM', '7PM', '6PM', '5PM', '4PM', '3PM', '2PM', '1PM', '12PM', '11AM', '10AM', '9AM', '8AM', '7AM', '6AM', '5AM', '4AM', '3AM', '2AM', '1AM'];

            const hourToGroup = (h) => {
                if (h >= 4 && h <= 7) return 'Early Morning';
                if (h >= 8 && h <= 11) return 'Morning';
                if (h >= 12 && h <= 15) return 'Afternoon';
                if (h >= 16 && h <= 19) return 'Evening';
                if (h >= 20 && h <= 23) return 'Night';
                return 'Late Night';
            };
            const groupColors = {
                'Early Morning': {
                    bg: 'rgba(255, 255, 255, 0.02)',
                    arc: 'rgba(255, 255, 255, 0.1)'
                },
                'Morning': {
                    bg: 'rgba(255, 255, 255, 0.06)',
                    arc: 'rgba(255, 255, 255, 0.2)'
                },
                'Afternoon': {
                    bg: 'rgba(255, 255, 255, 0.02)',
                    arc: 'rgba(255, 255, 255, 0.1)'
                },
                'Evening': {
                    bg: 'rgba(255, 255, 255, 0.06)',
                    arc: 'rgba(255, 255, 255, 0.2)'
                },
                'Night': {
                    bg: 'rgba(255, 255, 255, 0.02)',
                    arc: 'rgba(255, 255, 255, 0.1)'
                },
                'Late Night': {
                    bg: 'rgba(255, 255, 255, 0.06)',
                    arc: 'rgba(255, 255, 255, 0.2)'
                }
            };
            const indicatorHours = [0, 23, 22, 21, 20, 19, 18, 17, 16, 15, 14, 13, 12, 11, 10, 9, 8, 7, 6, 5, 4, 3, 2, 1];
            const pieDataBg = indicatorHours.map(h => ({
                value: 1,
                itemStyle: {
                    color: groupColors[hourToGroup(h)].bg
                }
            }));
            const pieDataArc = indicatorHours.map(h => ({
                value: 1,
                itemStyle: {
                    color: groupColors[hourToGroup(h)].arc
                }
            }));

            hourChart.setOption({
                radar: {
                    radius: '70%',
                    indicator: hours.map(h => ({
                        name: h,
                        max: <?= $maxHourValue ?>
                    })),
                    splitNumber: 4,
                    axisName: {
                        color: textColor,
                        fontSize: 10
                    },
                    splitLine: {
                        lineStyle: {
                            color: splitLineColor
                        }
                    },
                    splitArea: {
                        show: false
                    },
                    axisLine: {
                        lineStyle: {
                            color: splitLineColor
                        }
                    }
                },
                backgroundColor: 'transparent',
                series: [{
                        type: 'pie',
                        silent: true,
                        z: 1,
                        center: ['50%', '50%'],
                        radius: [0, '70%'],
                        startAngle: 82.5, // Aligns indicator center with slice center
                        clockwise: false,
                        label: {
                            show: false
                        },
                        data: pieDataBg
                    },
                    {
                        type: 'pie',
                        silent: true,
                        z: 2,
                        center: ['50%', '50%'],
                        radius: ['71%', '74%'],
                        startAngle: 82.5,
                        clockwise: false,
                        label: {
                            show: false
                        },
                        data: pieDataArc
                    },
                    {
                        name: 'Inboxes per Hour',
                        type: 'radar',
                        z: 3,
                        data: [{
                            value: <?= json_encode($radarData) ?>,
                            name: 'Frequency',
                            areaStyle: {
                                color: 'rgba(163, 255, 0, 0.3)'
                            },
                            lineStyle: {
                                color: '#a3ff00',
                                width: 2
                            },
                            symbol: 'none'
                        }]
                    }
                ],
                tooltip: {
                    trigger: 'item'
                }
            });
        }

        // Initial initialization
        document.addEventListener('DOMContentLoaded', function() {
            initCharts();
            initTooltips();
        });

        // Re-initialize charts when theme is toggled in sidebar
        window.addEventListener('themeChanged', function() {
            initCharts();
            initTooltips();
        });

        // Global listener to fix the theme toggle button if it's not working elsewhere
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('#themeToggle') || e.target.closest('.theme-toggle') || e.target.closest('#bd-theme');
            if (btn) {
                const html = document.documentElement;
                const newTheme = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
                html.setAttribute('data-bs-theme', newTheme);
                localStorage.setItem('theme', newTheme);
                window.dispatchEvent(new Event('themeChanged'));
            }
        });
    </script>
</body>

</html>
