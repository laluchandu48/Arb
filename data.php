<?php
// === CONFIGURATION ===
$fb_access_token = 'EAAOtitEadc4BO5AH5dNShfa6kl6gCtk009QpOIVbXTBAThH00ZABLSNKhuXzPMdVrzG8CnOuZC0TxYOmtBwUIw9cBHWfGvgdm8N9K5nnTlZBTFHNjezar260XQJy0VCFoA2awkRAkWVu5JTy6ZCzytINwZBh0ZCbw7kBS9c1igsyZAFXZAbncES2EmXQhop8qZBll';
$fb_ad_account_id = '24601780202743328';
$media_api_token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJpc3MiOiJodHRwczovL3B1YmNvbnNvbGUubWVkaWEubmV0Iiwic3ViIjoiOENVMjFENjhRIiwiaWF0IjoxNzUyMDQyNTAzLCJleHAiOjE3NTIxMjg5MDMsImF1ZCI6W119.d1XWNdswURGT8lPcJ1SslqLvDDh78T77XBs87EykmAm_ork2JMiWQVIYHP5g_gU2KbfWKpgYopcIvpiE19szbfugSvaUcLhfeGwFjS-UGKgJtoB_eh8rCvZQC9Cy8ODn2QC1Oi_d_ibsGd6sDDJyOBvPYqpY2us6WnjZdIbreBVtNAOaTbirWqQAJtlI1grD9s2MR5DuK_ZfGlrVxhFWIw7C8cv_MT15x2QgSOKwWwMXWBDF50dOzL_vb6FZcfuOqNXzb90fcfqvb--wNNRZp3rt3X2EOXTSPDW6uYSod-hyqJ57hNrEV742qMASZVZsT9bdGdtY5tXckJ3GiEgb8IMmpmzaV8Gg1HSEw_m34wWVYUKWn-lr3YO-aQ1ZtgP0405QYgyqVeio4dH4yQjRAAS-9y1_wv1PjysWXqxgK1sUNqVHSBO-W71SazA5CKf9pVSL6kLUHXrisSurtYapC-7n7fLjhJ8weQ8xk8CsKhV4ruTIEkbx8g_Urk1UvsyjKEyCrK-E0nsBhCuUK6W5IxRdiqxhU_vOd57ThfLIr8XbNNVdh1YxxoO0rv5KK4rAttmBje19v2qs5tktBy38H47Ho40VQmA3rlva3gXe-Ofsa3oPZPBRNLpJkwPnRMmVZnwzelDGzF304PllEavE-1zjqB4uYITWriJnheXfo8A';

// === FACEBOOK API (GET Request) ===
function getFacebookData($accountId, $accessToken, $dateRange = null) {
    $since = $dateRange['since'] ?? '2025-07-08';
    $until = $dateRange['until'] ?? '2025-07-08';
    
    $url = "https://graph.facebook.com/v19.0/act_$accountId/insights?fields=adset_id,adset_name,impressions,clicks,spend,actions&level=adset&access_token=$accessToken&time_range[since]=$since&time_range[until]=$until";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    $response = curl_exec($ch);
    if (curl_errno($ch)) return ['error' => curl_error($ch)];
    curl_close($ch);
    return json_decode($response, true);
}

// === MEDIA.NET API (POST Request) ===
function getMediaNetData($token, $dateRange = null) {
    $startDate = $dateRange['since'] ?? '2025-07-08';
    $endDate = $dateRange['until'] ?? '2025-07-08';
    
    $url = "https://api-pubconsole.media.net/v2/reports";
    $payload = [
        "start_date" => date("Ymd", strtotime($startDate)),
        "end_date" => date("Ymd", strtotime($endDate)),
        "group_by" => ["channel_name2"],
        "metrics" => ["revenue", "clicks", "impressions"],
        "type" => "json",
        "pagination" => ["page_size" => 900, "page_no" => 1]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Accept: application/json",
            "token: $token"
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    $response = curl_exec($ch);

    if (curl_errno($ch)) return ['error' => curl_error($ch)];

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);

    if ($httpCode !== 200 || isset($decoded['error'])) {
        return ['error' => $decoded['error'] ?? "HTTP $httpCode"];
    }

    return $decoded;
}

// === CAMPAIGN HISTORY FUNCTION ===
function getCampaignHistory($adsetId, $accountId, $accessToken, $mediaToken, $days = 30) {
    $history = [];
    
    for ($i = 0; $i < $days; $i++) {
        $date = date('Y-m-d', strtotime("-$i days"));
        
        $fbData = getFacebookData($accountId, $accessToken, [
            'since' => $date,
            'until' => $date
        ]);
        
        $mediaData = getMediaNetData($mediaToken, [
            'since' => $date,
            'until' => $date
        ]);
        
        // Find matching data for this adset
        $fbRow = null;
        $mediaRow = null;
        
        if (!empty($fbData['data'])) {
            foreach ($fbData['data'] as $row) {
                if ($row['adset_id'] == $adsetId) {
                    $fbRow = $row;
                    break;
                }
            }
        }
        
        if (!empty($mediaData['data']['rows'])) {
            foreach ($mediaData['data']['rows'] as $row) {
                $channelName = strtolower(trim($row['channelName2'] ?? ''));
                if (stripos($channelName, strtolower($adsetId)) !== false) {
                    $mediaRow = $row;
                    break;
                }
            }
        }
        
        if ($fbRow || $mediaRow) {
            $history[] = [
                'date' => $date,
                'fb' => $fbRow,
                'media' => $mediaRow
            ];
        }
    }
    
    return $history;
}

// Handle AJAX request for campaign history
if (isset($_GET['action']) && $_GET['action'] === 'get_history' && isset($_GET['adset_id'])) {
    $adsetId = $_GET['adset_id'];
    $history = getCampaignHistory($adsetId, $fb_ad_account_id, $fb_access_token, $media_api_token);
    
    echo json_encode($history);
    exit;
}

// Handle campaign history page
if (isset($_GET['campaign_history']) && isset($_GET['adset_id'])) {
    $adsetId = $_GET['adset_id'];
    $adsetName = $_GET['adset_name'] ?? 'Unknown Campaign';
    $history = getCampaignHistory($adsetId, $fb_ad_account_id, $fb_access_token, $media_api_token);
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Campaign History - <?= htmlspecialchars($adsetName) ?></title>
        <style>
            body { font-family: Arial; padding: 20px; }
            table { border-collapse: collapse; width: 100%; margin-top: 20px; }
            th, td { border: 1px solid #aaa; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            .back-btn { 
                background-color: #007cba; 
                color: white; 
                padding: 10px 20px; 
                border: none; 
                cursor: pointer; 
                margin-bottom: 20px;
                text-decoration: none;
                display: inline-block;
            }
            .back-btn:hover { background-color: #005a87; }
            .chart-container { margin: 20px 0; }
            .profit-positive { color: #28a745; font-weight: bold; }
            .profit-negative { color: #dc3545; font-weight: bold; }
            .profit-zero { color: #6c757d; font-weight: bold; }
        </style>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    </head>
    <body>
        <a href="javascript:history.back()" class="back-btn">← Back to Dashboard</a>
        
        <h1>📈 Campaign History: <?= htmlspecialchars($adsetName) ?></h1>
        <p><strong>Adset ID:</strong> <?= htmlspecialchars($adsetId) ?></p>
        
        <div class="chart-container">
            <canvas id="performanceChart" width="400" height="200"></canvas>
        </div>
        
        <table>
            <tr>
                <th>Date</th>
                <th>Adset Name</th>
                <th>Spend (USD)</th>
                <th>Revenue (USD)</th>
                <th>Profit (USD)</th>
                <th>ROI (%)</th>
                <th>Media Clicks</th>
                <th>CPL (USD)</th>
                <th>RPC (USD)</th>
                <th>FB Impressions</th>
                <th>Media Impressions</th>
            </tr>
            
            <?php foreach ($history as $day): ?>
                <?php
                $fbRow = $day['fb'];
                $mediaRow = $day['media'];
                
                $fbImpressions = (int)($fbRow['impressions'] ?? 0);
                $fbClicks = (int)($fbRow['clicks'] ?? 0);
                $fbSpend = (float)($fbRow['spend'] ?? 0);
                
                $mediaImpressions = (int)($mediaRow['impressions'] ?? 0);
                $mediaClicks = (int)($mediaRow['ad_clicks'] ?? 0);
                $mediaRevenue = (float)($mediaRow['estimated_revenue'] ?? 0);
                
                $profit = $mediaRevenue - $fbSpend;
                $roi = ($fbSpend > 0) ? ($profit / $fbSpend) * 100 : 0;
                $cpl = ($mediaClicks > 0) ? $fbSpend / $mediaClicks : 0;
                $rpc = ($mediaClicks > 0) ? $mediaRevenue / $mediaClicks : 0;
                
                // Determine profit color class
                $profitClass = 'profit-zero';
                if ($profit > 0) {
                    $profitClass = 'profit-positive';
                } elseif ($profit < 0) {
                    $profitClass = 'profit-negative';
                }
                ?>
                <tr>
                    <td><?= $day['date'] ?></td>
                    <td><?= htmlspecialchars($fbRow['adset_name'] ?? 'Unknown') ?></td>
                    <td>$<?= number_format($fbSpend, 2) ?></td>
                    <td><?= $mediaRevenue ? '$' . number_format($mediaRevenue, 2) : '' ?></td>
                    <td class="<?= $profitClass ?>">$<?= number_format($profit, 2) ?></td>
                    <td><?= $fbSpend > 0 ? number_format($roi, 2) . '%' : '0.00%' ?></td>
                    <td><?= $mediaClicks ?: '' ?></td>
                    <td><?= $mediaClicks > 0 ? '$' . number_format($cpl, 2) : '0.00' ?></td>
                    <td><?= $mediaClicks > 0 ? '$' . number_format($rpc, 2) : '0.00' ?></td>
                    <td><?= $fbImpressions ?></td>
                    <td><?= $mediaImpressions ?: '' ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        
        <script>
            // Prepare chart data
            const chartData = <?= json_encode($history) ?>;
            const labels = chartData.map(item => item.date).reverse();
            const spendData = chartData.map(item => parseFloat(item.fb?.spend || 0)).reverse();
            const revenueData = chartData.map(item => parseFloat(item.media?.estimated_revenue || 0)).reverse();
            const profitData = chartData.map(item => 
                parseFloat(item.media?.estimated_revenue || 0) - parseFloat(item.fb?.spend || 0)
            ).reverse();
            
            // Create chart
            const ctx = document.getElementById('performanceChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'FB Spend',
                        data: spendData,
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        tension: 0.1
                    }, {
                        label: 'Media Revenue',
                        data: revenueData,
                        borderColor: 'rgb(54, 162, 235)',
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        tension: 0.1
                    }, {
                        label: 'Profit',
                        data: profitData,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Campaign Performance Over Time'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toFixed(2);
                                }
                            }
                        }
                    }
                }
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

// === FETCH DATA ===
$fbData = getFacebookData($fb_ad_account_id, $fb_access_token);
$mediaNetData = getMediaNetData($media_api_token);

// === Map Media.net Data by channelName2 ===
$mediaMap = [];

if (!empty($mediaNetData['data']['rows']) && !empty($fbData['data'])) {
    foreach ($fbData['data'] as $fbRow) {
        $adsetId = strtolower(trim($fbRow['adset_id']));
        
        foreach ($mediaNetData['data']['rows'] as $mediaRow) {
            $channelName = strtolower(trim($mediaRow['channelName2'] ?? ''));

            if (stripos($channelName, $adsetId) !== false) {
                $mediaMap[$adsetId] = $mediaRow;
                break; // found, skip rest
            }
        }
    }
}

// === PREPARE DATA FOR SORTING ===
$tableData = [];
if (!empty($fbData['data'])) {
    foreach ($fbData['data'] as $fbRow) {
        $adsetId = strtolower($fbRow['adset_id']);
        $mediaRow = $mediaMap[$adsetId] ?? null;

        // Get values with proper defaults
        $fbImpressions = (int)($fbRow['impressions'] ?? 0);
        $fbClicks = (int)($fbRow['clicks'] ?? 0);
        $fbSpend = (float)($fbRow['spend'] ?? 0);
        
        $mediaImpressions = (int)($mediaRow['impressions'] ?? 0);
        $mediaClicks = (int)($mediaRow['ad_clicks'] ?? 0);
        $mediaRevenue = (float)($mediaRow['estimated_revenue'] ?? 0);
        
        // Calculate metrics with zero division protection
        $profit = $mediaRevenue - $fbSpend;
        $roi = ($fbSpend > 0) ? ($profit / $fbSpend) * 100 : 0;
        $cpl = ($mediaClicks > 0) ? $fbSpend / $mediaClicks : 0;
        $rpc = ($mediaClicks > 0) ? $mediaRevenue / $mediaClicks : 0;

        $tableData[] = [
            'adset_id' => $fbRow['adset_id'],
            'adset_name' => $fbRow['adset_name'],
            'spend' => $fbSpend,
            'revenue' => $mediaRevenue,
            'profit' => $profit,
            'roi' => $roi,
            'media_clicks' => $mediaClicks,
            'cpl' => $cpl,
            'rpc' => $rpc,
            'fb_impressions' => $fbImpressions,
            'media_impressions' => $mediaImpressions,
            'has_media_data' => $mediaRow !== null
        ];
    }
}

// === SORTING LOGIC ===
$sortColumn = $_GET['sort'] ?? 'adset_name';
$sortDirection = $_GET['dir'] ?? 'asc';

if (!empty($tableData)) {
    usort($tableData, function($a, $b) use ($sortColumn, $sortDirection) {
        $valueA = $a[$sortColumn] ?? 0;
        $valueB = $b[$sortColumn] ?? 0;
        
        // Handle string comparison for campaign names
        if ($sortColumn === 'adset_name') {
            $result = strcasecmp($valueA, $valueB);
        } else {
            // Numeric comparison
            $result = $valueA <=> $valueB;
        }
        
        return $sortDirection === 'desc' ? -$result : $result;
    });
}

// === CALCULATE TOTALS ===
$fbTotal = ['impressions' => 0, 'clicks' => 0, 'spend' => 0];
$mediaTotal = ['impressions' => 0, 'clicks' => 0, 'revenue' => 0, 'profit' => 0, 'roi' => 0, 'cpl' => 0, 'rpc' => 0];
$count = 0;
$validCplCount = 0;
$validRpcCount = 0;

foreach ($tableData as $row) {
    $fbTotal['impressions'] += $row['fb_impressions'];
    $fbTotal['clicks'] += $row['media_clicks'];
    $fbTotal['spend'] += $row['spend'];
    $count++;

    if ($row['has_media_data']) {
        $mediaTotal['impressions'] += $row['media_impressions'];
        $mediaTotal['clicks'] += $row['media_clicks'];
        $mediaTotal['revenue'] += $row['revenue'];
        $mediaTotal['profit'] += $row['profit'];
        $mediaTotal['roi'] += $row['roi'];
        
        if ($row['media_clicks'] > 0) {
            $mediaTotal['cpl'] += $row['cpl'];
            $mediaTotal['rpc'] += $row['rpc'];
            $validCplCount++;
            $validRpcCount++;
        }
    }
}

// Calculate overall ROI for totals
$totalRoi = ($fbTotal['spend'] > 0) ? ($mediaTotal['profit'] / $fbTotal['spend']) * 100 : 0;

// === HELPER FUNCTION FOR SORT ARROWS ===
function getSortArrow($column, $currentSort, $currentDir) {
    if ($column === $currentSort) {
        return $currentDir === 'asc' ? ' ▲' : ' ▼';
    }
    return '';
}

function getSortUrl($column, $currentSort, $currentDir) {
    $newDir = 'asc';
    if ($column === $currentSort && $currentDir === 'asc') {
        $newDir = 'desc';
    }
    return "?sort=$column&dir=$newDir";
}

// Function to determine profit color class
function getProfitColorClass($profit) {
    if ($profit > 0) {
        return 'profit-positive';
    } elseif ($profit < 0) {
        return 'profit-negative';
    } else {
        return 'profit-zero';
    }
}

// Determine total profit color class
$totalProfitClass = getProfitColorClass($mediaTotal['profit']);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Unified Ad Dashboard</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #aaa; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .bold { font-weight: bold; background-color: #f9f9f9; }
        .error { color: red; }
        .export-btn { 
            background-color: #4CAF50; 
            color: white; 
            padding: 10px 20px; 
            border: none; 
            cursor: pointer; 
            margin-bottom: 20px;
            margin-right: 10px;
        }
        .export-btn:hover { background-color: #45a049; }
        .clickable-name { 
            cursor: pointer; 
            transition: all 0.2s;
            color: #007cba;
            font-weight: bold;
            text-decoration: underline;
        }
        .clickable-name:hover { 
            background-color: #f0f8ff;
            color: #005a87;
        }
        .history-icon { color: #007cba; margin-right: 5px; }
        .tooltip { position: relative; display: inline-block; }
        .tooltip .tooltiptext {
            visibility: hidden;
            width: 200px;
            background-color: #555;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
        .sortable-header {
            cursor: pointer;
            user-select: none;
            transition: background-color 0.2s;
        }
        .sortable-header:hover {
            background-color: #e6e6e6;
        }
        .sort-arrow {
            color: #007cba;
            font-weight: bold;
        }
        .sort-info {
            margin-bottom: 10px;
            padding: 8px;
            background-color: #f0f8ff;
            border-radius: 4px;
            font-size: 14px;
        }
        /* Profit Color Classes */
        .profit-positive { 
            color: #28a745; 
            font-weight: bold; 
        }
        .profit-negative { 
            color: #dc3545; 
            font-weight: bold; 
        }
        .profit-zero { 
            color: #6c757d; 
            font-weight: bold; 
        }
    </style>
</head>
<script>
    function exportToExcel() {
        const table = document.getElementById("dashboardTable").outerHTML;
        const dataType = 'application/vnd.ms-excel';
        const blob = new Blob([table], { type: dataType });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = "dashboard.xls";
        a.click();
        URL.revokeObjectURL(url);
    }
    
    function viewCampaignHistory(adsetId, adsetName) {
        const url = `?campaign_history=1&adset_id=${encodeURIComponent(adsetId)}&adset_name=${encodeURIComponent(adsetName)}`;
        window.open(url, '_blank');
    }
</script>
<body>

<h1>📊 Facebook & Media.net Combined Report</h1>
<button class="export-btn" onclick="exportToExcel()">⬇️ Download Excel</button>
<div style="margin-bottom: 20px;">
    <small>💡 <strong>Tip:</strong> Click on any campaign name to view detailed history in a new tab</small>
</div>

<?php if ($sortColumn): ?>
    <div class="sort-info">
        📊 <strong>Sorted by:</strong> <?= ucfirst(str_replace('_', ' ', $sortColumn)) ?> 
        (<?= $sortDirection === 'asc' ? 'Ascending' : 'Descending' ?>)
        <span class="sort-arrow"><?= getSortArrow($sortColumn, $sortColumn, $sortDirection) ?></span>
    </div>
<?php endif; ?>

<?php if (isset($fbData['error'])): ?>
    <p class="error">⚠️ Facebook API Error: <?= htmlspecialchars($fbData['error']) ?></p>
<?php elseif (isset($mediaNetData['error'])): ?>
    <p class="error">⚠️ Media.net API Error: <?= htmlspecialchars($mediaNetData['error']) ?></p>
<?php elseif (empty($fbData['data'])): ?>
    <p class="error">⚠️ No Facebook data found.</p>
<?php else: ?>
    <table id="dashboardTable">
        <tr>
            <th class="sortable-header" onclick="location.href='<?= getSortUrl('adset_name', $sortColumn, $sortDirection) ?>'">
                <div class="tooltip">
                    📈 Adset Name<?= getSortArrow('adset_name', $sortColumn, $sortDirection) ?>
                    <span class="tooltiptext">Click to sort by campaign name</span>
                </div>
            </th>
            <th class="sortable-header" onclick="location.href='<?= getSortUrl('spend', $sortColumn, $sortDirection) ?>'">
                Spend (USD)<?= getSortArrow('spend', $sortColumn, $sortDirection) ?>
            </th>
            <th class="sortable-header" onclick="location.href='<?= getSortUrl('revenue', $sortColumn, $sortDirection) ?>'">
                Revenue (USD)<?= getSortArrow('revenue', $sortColumn, $sortDirection) ?>
            </th>
            <th class="sortable-header" onclick="location.href='<?= getSortUrl('profit', $sortColumn, $sortDirection) ?>'">
                Profit (USD)<?= getSortArrow('profit', $sortColumn, $sortDirection) ?>
            </th>
            <th class="sortable-header" onclick="location.href='<?= getSortUrl('roi', $sortColumn, $sortDirection) ?>'">
                ROI (%)<?= getSortArrow('roi', $sortColumn, $sortDirection) ?>
            </th>
            <th class="sortable-header" onclick="location.href='<?= getSortUrl('media_clicks', $sortColumn, $sortDirection) ?>'">
                Media Clicks<?= getSortArrow('media_clicks', $sortColumn, $sortDirection) ?>
            </th>
            <th class="sortable-header" onclick="location.href='<?= getSortUrl('cpl', $sortColumn, $sortDirection) ?>'">
                CPL (USD)<?= getSortArrow('cpl', $sortColumn, $sortDirection) ?>
            </th>
            <th class="sortable-header" onclick="location.href='<?= getSortUrl('rpc', $sortColumn, $sortDirection) ?>'">
                RPC (USD)<?= getSortArrow('rpc', $sortColumn, $sortDirection) ?>
            </th>
            <th class="sortable-header" onclick="location.href='<?= getSortUrl('fb_impressions', $sortColumn, $sortDirection) ?>'">
                FB Impressions<?= getSortArrow('fb_impressions', $sortColumn, $sortDirection) ?>
            </th>
            <th class="sortable-header" onclick="location.href='<?= getSortUrl('media_impressions', $sortColumn, $sortDirection) ?>'">
                Media Impressions<?= getSortArrow('media_impressions', $sortColumn, $sortDirection) ?>
            </th>
        </tr>

        <?php foreach ($tableData as $row): ?>
            <tr>
                <td class="clickable-name" onclick="viewCampaignHistory('<?= htmlspecialchars($row['adset_id']) ?>', '<?= htmlspecialchars($row['adset_name']) ?>')">
                    <span class="history-icon">📈</span>
                    <?= htmlspecialchars($row['adset_name']) ?>
                </td>
                <td>$<?= number_format($row['spend'], 2) ?></td>
                <td><?= $row['revenue'] ? '$' . number_format($row['revenue'], 2) : '' ?></td>
                <td class="<?= getProfitColorClass($row['profit']) ?>">
                    <?= $row['has_media_data'] ? '$' . number_format($row['profit'], 2) : '' ?>
                </td>
                <td><?= $row['has_media_data'] && $row['spend'] > 0 ? number_format($row['roi'], 2) . '%' : '0.00%' ?></td>
                <td><?= $row['media_clicks'] ?: '' ?></td>
                <td><?= $row['has_media_data'] && $row['media_clicks'] > 0 ? '$' . number_format($row['cpl'], 2) : '0.00' ?></td>
                <td><?= $row['has_media_data'] && $row['media_clicks'] > 0 ? '$' . number_format($row['rpc'], 2) : '0.00' ?></td>
                <td><?= $row['fb_impressions'] ?></td>
                <td><?= $row['media_impressions'] ?: '' ?></td>
            </tr>
        <?php endforeach; ?>

        <tr class="bold">
            <td>TOTAL</td>
            <td>$<?= number_format($fbTotal['spend'], 2) ?></td>
            <td>$<?= number_format($mediaTotal['revenue'], 2) ?></td>
            <td class="<?= $totalProfitClass ?>">$<?= number_format($mediaTotal['profit'], 2) ?></td>
            <td><?= number_format($totalRoi, 2) ?>%</td>
            <td><?= $mediaTotal['clicks'] ?></td>
            <td>$<?= $validCplCount > 0 ? number_format($mediaTotal['cpl'] / $validCplCount, 2) : '0.00' ?></td>
            <td>$<?= $validRpcCount > 0 ? number_format($mediaTotal['rpc'] / $validRpcCount, 2) : '0.00' ?></td>
            <td><?= $fbTotal['impressions'] ?></td>
            <td><?= $mediaTotal['impressions'] ?></td>
        </tr>
    </table>
<?php endif; ?>

</body>
</html>