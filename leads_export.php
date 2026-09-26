<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_login();

/*
|--------------------------------------------------------------------------
| FORMAT
|--------------------------------------------------------------------------
*/

$format  = strtolower(trim((string)($_GET['format'] ?? 'csv')));
$allowed = ['csv', 'excel'];

if (!in_array($format, $allowed, true)) {
    $format = 'csv';
}


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$status        = trim($_GET['status'] ?? '');
$priority      = trim($_GET['priority'] ?? '');
$websiteStatus = trim($_GET['website_status'] ?? '');
$search        = trim($_GET['q'] ?? '');

$where  = [];
$params = [];

$allowedStatuses = [
    'new','researched','qualified','contact_required','contacted',
    'follow_up','interested','quote_sent','negotiation','won',
    'lost','do_not_contact',
];

$allowedPriorities = ['low', 'medium', 'high', 'hot'];

$allowedWebsiteStatuses = [
    'unknown','none','social_only','found','poor','good','investigate',
];

if ($status !== '' && in_array($status, $allowedStatuses, true)) {
    $where[]  = 'l.lead_status = ?';
    $params[] = $status;
}

if ($priority !== '' && in_array($priority, $allowedPriorities, true)) {
    $where[]  = 'l.priority = ?';
    $params[] = $priority;
}

if ($websiteStatus !== '' && in_array($websiteStatus, $allowedWebsiteStatuses, true)) {
    $where[]  = 'l.website_status = ?';
    $params[] = $websiteStatus;
}

if ($search !== '') {
    $where[] = "
        (
            l.business_name LIKE ?
            OR l.city LIKE ?
            OR l.postcode LIKE ?
            OR l.phone LIKE ?
            OR l.website_url LIKE ?
        )
    ";

    $like = '%' . $search . '%';

    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);


/*
|--------------------------------------------------------------------------
| LOAD ALL FILTERED LEADS (no pagination)
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        l.*,
        c.name AS campaign_name
    FROM leads l
    LEFT JOIN campaigns c ON c.id = l.campaign_id
    $whereSql
    ORDER BY l.lead_score DESC, l.created_at DESC
";

$stmt = db()->prepare($sql);
$stmt->execute($params);

$leads = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| COLUMNS
|--------------------------------------------------------------------------
*/

$columns = [
    'Business Name',
    'Business Type',
    'Phone',
    'Email',
    'Website',
    'Address',
    'City',
    'County',
    'Postcode',
    'Rating',
    'Reviews',
    'Lead Score',
    'Priority',
    'Status',
    'Campaign',
    'Google Maps URL',
    'Discovered',
];


/*
|--------------------------------------------------------------------------
| ROW BUILDER
|--------------------------------------------------------------------------
*/

function lead_to_row(array $lead): array
{
    $addressParts = array_filter([
        trim((string)($lead['address_line1'] ?? '')),
        trim((string)($lead['address_line2'] ?? '')),
    ]);

    return [
        $lead['business_name']  ?? '',
        $lead['business_type']  ?? '',
        $lead['phone']          ?? '',
        $lead['public_email']   ?? '',
        $lead['website_url']    ?? '',
        implode(', ', $addressParts),
        $lead['city']           ?? '',
        $lead['county']         ?? '',
        $lead['postcode']       ?? '',
        $lead['rating'] !== null ? number_format((float)$lead['rating'], 1) : '',
        (int)($lead['review_count'] ?? 0),
        (int)($lead['lead_score'] ?? 0),
        strtoupper((string)($lead['priority'] ?? '')),
        ucwords(str_replace('_', ' ', (string)($lead['lead_status'] ?? ''))),
        $lead['campaign_name']   ?? '',
        $lead['google_maps_url'] ?? '',
        !empty($lead['discovered_at'])
            ? date('Y-m-d', strtotime($lead['discovered_at']))
            : '',
    ];
}

$stamp    = date('Y-m-d');
$filename = 'leads-export-' . $stamp;


/*
|--------------------------------------------------------------------------
| CSV
|--------------------------------------------------------------------------
*/

if ($format === 'csv') {

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');

    // UTF-8 BOM so Excel opens accented characters correctly
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, $columns);

    foreach ($leads as $lead) {
        fputcsv($out, lead_to_row($lead));
    }

    fclose($out);
    exit;
}


/*
|--------------------------------------------------------------------------
| EXCEL (.xls via HTML table)
|--------------------------------------------------------------------------
*/

if ($format === 'excel') {

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF";

    echo '<html><head><meta charset="utf-8"></head><body>';
    echo '<table border="1" cellpadding="4" cellspacing="0">';

    echo '<thead><tr style="background:#111827;color:#fff;font-weight:bold;">';
    foreach ($columns as $col) {
        echo '<th>' . htmlspecialchars($col, ENT_QUOTES, 'UTF-8') . '</th>';
    }
    echo '</tr></thead><tbody>';

    foreach ($leads as $lead) {
        echo '<tr>';
        foreach (lead_to_row($lead) as $value) {
            echo '<td>' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '</td>';
        }
        echo '</tr>';
    }

    echo '</tbody></table></body></html>';
    exit;
}

http_response_code(400);
exit('Unsupported format.');