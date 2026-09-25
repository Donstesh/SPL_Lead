<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_login();

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$status        = trim($_GET['status'] ?? '');
$priority      = trim($_GET['priority'] ?? '');
$websiteStatus = trim($_GET['website_status'] ?? '');
$search        = trim($_GET['q'] ?? '');

$where = [];
$params = [];


/*
|--------------------------------------------------------------------------
| STATUS FILTER
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'new',
    'researched',
    'qualified',
    'contact_required',
    'contacted',
    'follow_up',
    'interested',
    'quote_sent',
    'negotiation',
    'won',
    'lost',
    'do_not_contact'
];

if (
    $status !== ''
    && in_array($status, $allowedStatuses, true)
) {
    $where[] = 'l.lead_status = ?';
    $params[] = $status;
}


/*
|--------------------------------------------------------------------------
| PRIORITY FILTER
|--------------------------------------------------------------------------
*/

$allowedPriorities = [
    'low',
    'medium',
    'high',
    'hot'
];

if (
    $priority !== ''
    && in_array($priority, $allowedPriorities, true)
) {
    $where[] = 'l.priority = ?';
    $params[] = $priority;
}


/*
|--------------------------------------------------------------------------
| WEBSITE STATUS FILTER
|--------------------------------------------------------------------------
*/

$allowedWebsiteStatuses = [
    'unknown',
    'none',
    'social_only',
    'found',
    'poor',
    'good',
    'investigate'
];

if (
    $websiteStatus !== ''
    && in_array($websiteStatus, $allowedWebsiteStatuses, true)
) {
    $where[] = 'l.website_status = ?';
    $params[] = $websiteStatus;
}


/*
|--------------------------------------------------------------------------
| BUSINESS SEARCH
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| BUILD WHERE CLAUSE
|--------------------------------------------------------------------------
*/

$whereSql = '';

if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}


/*
|--------------------------------------------------------------------------
| LOAD LEADS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        l.*,
        c.name AS campaign_name
    FROM leads l

    LEFT JOIN campaigns c
        ON c.id = l.campaign_id

    $whereSql

    ORDER BY
        l.lead_score DESC,
        l.created_at DESC

    LIMIT 500
";

$stmt = db()->prepare($sql);
$stmt->execute($params);

$leads = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| DASHBOARD COUNTS
|--------------------------------------------------------------------------
*/

$totalLeads = (int)db()->query("
    SELECT COUNT(*)
    FROM leads
")->fetchColumn();

$hotLeads = (int)db()->query("
    SELECT COUNT(*)
    FROM leads
    WHERE priority = 'hot'
")->fetchColumn();

$noWebsite = (int)db()->query("
    SELECT COUNT(*)
    FROM leads
    WHERE website_status = 'none'
")->fetchColumn();

$contactRequired = (int)db()->query("
    SELECT COUNT(*)
    FROM leads
    WHERE lead_status IN (
        'new',
        'researched',
        'qualified',
        'contact_required'
    )
")->fetchColumn();

$wonLeads = (int)db()->query("
    SELECT COUNT(*)
    FROM leads
    WHERE lead_status = 'won'
")->fetchColumn();


/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

$pageTitle = 'Leads';

require __DIR__ . '/includes/header.php';

?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">

    <div>
        <h1 class="h3 mb-1">Leads</h1>

        <div class="text-muted">
            SPL Web Services Sales Intelligence
        </div>
    </div>

    <div class="d-flex gap-2">

        <a
            href="campaigns.php"
            class="btn btn-outline-dark"
        >
            Campaigns
        </a>

        <a
            href="search.php"
            class="btn btn-dark"
        >
            Find New Leads
        </a>

    </div>

</div>


<!-- STATS -->

<div class="row g-3 mb-4">

    <div class="col-md-6 col-xl">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">
                    Total Leads
                </div>

                <div class="fs-3 fw-bold">
                    <?= number_format($totalLeads) ?>
                </div>
            </div>
        </div>
    </div>


    <div class="col-md-6 col-xl">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">
                    Hot Leads
                </div>

                <div class="fs-3 fw-bold">
                    <?= number_format($hotLeads) ?>
                </div>
            </div>
        </div>
    </div>


    <div class="col-md-6 col-xl">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">
                    No Website
                </div>

                <div class="fs-3 fw-bold">
                    <?= number_format($noWebsite) ?>
                </div>
            </div>
        </div>
    </div>


    <div class="col-md-6 col-xl">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">
                    Contact Required
                </div>

                <div class="fs-3 fw-bold">
                    <?= number_format($contactRequired) ?>
                </div>
            </div>
        </div>
    </div>


    <div class="col-md-6 col-xl">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">
                    Won
                </div>

                <div class="fs-3 fw-bold">
                    <?= number_format($wonLeads) ?>
                </div>
            </div>
        </div>
    </div>

</div>


<!-- FILTERS -->

<div class="card border-0 shadow-sm mb-4">

    <div class="card-body">

        <form method="get" action="leads.php">

            <div class="row g-3 align-items-end">

                <div class="col-lg-4">

                    <label class="form-label">
                        Search Leads
                    </label>

                    <input
                        type="text"
                        name="q"
                        class="form-control"
                        placeholder="Business, town, postcode, phone..."
                        value="<?= e($search) ?>"
                    >

                </div>


                <div class="col-lg-2">

                    <label class="form-label">
                        Status
                    </label>

                    <select
                        name="status"
                        class="form-select"
                    >

                        <option value="">
                            All Statuses
                        </option>

                        <?php foreach ($allowedStatuses as $item): ?>

                            <option
                                value="<?= e($item) ?>"
                                <?= $status === $item ? 'selected' : '' ?>
                            >
                                <?= e(
                                    ucwords(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $item
                                        )
                                    )
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="col-lg-2">

                    <label class="form-label">
                        Priority
                    </label>

                    <select
                        name="priority"
                        class="form-select"
                    >

                        <option value="">
                            All Priorities
                        </option>

                        <?php foreach ($allowedPriorities as $item): ?>

                            <option
                                value="<?= e($item) ?>"
                                <?= $priority === $item ? 'selected' : '' ?>
                            >
                                <?= e(ucfirst($item)) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="col-lg-2">

                    <label class="form-label">
                        Website
                    </label>

                    <select
                        name="website_status"
                        class="form-select"
                    >

                        <option value="">
                            All Websites
                        </option>

                        <option
                            value="none"
                            <?= $websiteStatus === 'none' ? 'selected' : '' ?>
                        >
                            No Website
                        </option>

                        <option
                            value="found"
                            <?= $websiteStatus === 'found' ? 'selected' : '' ?>
                        >
                            Website Found
                        </option>

                        <option
                            value="social_only"
                            <?= $websiteStatus === 'social_only' ? 'selected' : '' ?>
                        >
                            Social Only
                        </option>

                        <option
                            value="poor"
                            <?= $websiteStatus === 'poor' ? 'selected' : '' ?>
                        >
                            Poor Website
                        </option>

                        <option
                            value="good"
                            <?= $websiteStatus === 'good' ? 'selected' : '' ?>
                        >
                            Good Website
                        </option>

                        <option
                            value="investigate"
                            <?= $websiteStatus === 'investigate' ? 'selected' : '' ?>
                        >
                            Investigate
                        </option>

                    </select>

                </div>


                <div class="col-lg-2">

                    <button
                        type="submit"
                        class="btn btn-dark w-100"
                    >
                        Filter Leads
                    </button>

                </div>

            </div>


            <?php if (
                $search !== ''
                || $status !== ''
                || $priority !== ''
                || $websiteStatus !== ''
            ): ?>

                <div class="mt-3">

                    <a
                        href="leads.php"
                        class="btn btn-sm btn-outline-secondary"
                    >
                        Clear Filters
                    </a>

                </div>

            <?php endif; ?>

        </form>

    </div>

</div>


<!-- LEADS TABLE -->

<div class="card border-0 shadow-sm">

    <div class="card-header bg-white d-flex justify-content-between align-items-center">

        <strong>
            Lead Database
        </strong>

        <span class="text-muted small">

            <?= number_format(count($leads)) ?>

            result<?= count($leads) === 1 ? '' : 's' ?>

        </span>

    </div>


    <div class="table-responsive">

        <table class="table table-hover align-middle mb-0">

            <thead>

                <tr>

                    <th>
                        Business
                    </th>

                    <th>
                        Campaign
                    </th>

                    <th>
                        Website
                    </th>

                    <th>
                        Rating
                    </th>

                    <th>
                        Score
                    </th>

                    <th>
                        Priority
                    </th>

                    <th>
                        Status
                    </th>

                    <th class="text-end">
                        Action
                    </th>

                </tr>

            </thead>


            <tbody>

            <?php if (!$leads): ?>

                <tr>

                    <td
                        colspan="8"
                        class="text-center py-5"
                    >

                        <div class="mb-3 text-muted">
                            No leads found.
                        </div>

                        <a
                            href="search.php"
                            class="btn btn-dark"
                        >
                            Find Your First Leads
                        </a>

                    </td>

                </tr>


            <?php else: ?>

                <?php foreach ($leads as $lead): ?>

                    <?php

                    /*
                    |--------------------------------------------------------------------------
                    | SCORE COLOUR
                    |--------------------------------------------------------------------------
                    */

                    $score = (int)$lead['lead_score'];

                    $scoreClass = 'secondary';

                    if ($score >= 80) {
                        $scoreClass = 'danger';
                    } elseif ($score >= 60) {
                        $scoreClass = 'warning';
                    } elseif ($score >= 40) {
                        $scoreClass = 'primary';
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | PRIORITY COLOUR
                    |--------------------------------------------------------------------------
                    |
                    | PHP 7 compatible.
                    | We deliberately do NOT use PHP 8 match().
                    |
                    */

                    $priorityClass = 'secondary';

                    if ($lead['priority'] === 'hot') {

                        $priorityClass = 'danger';

                    } elseif ($lead['priority'] === 'high') {

                        $priorityClass = 'warning';

                    } elseif ($lead['priority'] === 'medium') {

                        $priorityClass = 'primary';

                    } elseif ($lead['priority'] === 'low') {

                        $priorityClass = 'secondary';

                    }

                    ?>


                    <tr>


                        <!-- BUSINESS -->

                        <td>

                            <a
                                class="fw-semibold text-decoration-none"
                                href="lead.php?id=<?= (int)$lead['id'] ?>"
                            >
                                <?= e($lead['business_name']) ?>
                            </a>


                            <div class="small text-muted">

                                <?= e(
                                    trim(
                                        ($lead['city'] ?? '')
                                        . ' '
                                        . ($lead['postcode'] ?? '')
                                    )
                                ) ?>

                            </div>


                            <?php if (!empty($lead['phone'])): ?>

                                <div class="small">

                                    <a
                                        href="tel:<?= e($lead['phone']) ?>"
                                        class="text-decoration-none"
                                    >
                                        <?= e($lead['phone']) ?>
                                    </a>

                                </div>

                            <?php endif; ?>

                        </td>


                        <!-- CAMPAIGN -->

                        <td>

                            <?php if (!empty($lead['campaign_name'])): ?>

                                <?= e($lead['campaign_name']) ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>


                        <!-- WEBSITE -->

                        <td>

                            <?php if (
                                $lead['website_status'] === 'none'
                                || empty($lead['website_url'])
                            ): ?>

                                <span class="badge text-bg-danger">
                                    NO WEBSITE
                                </span>

                            <?php else: ?>

                                <span class="badge text-bg-success">
                                    WEBSITE
                                </span>

                                <div class="mt-1">

                                    <a
                                        href="<?= e($lead['website_url']) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="small"
                                    >
                                        Visit
                                    </a>

                                </div>

                            <?php endif; ?>

                        </td>


                        <!-- RATING -->

                        <td>

                            <?php if ($lead['rating'] !== null): ?>

                                <strong>

                                    <?= number_format(
                                        (float)$lead['rating'],
                                        1
                                    ) ?>

                                </strong>

                                ★

                                <div class="small text-muted">

                                    <?= number_format(
                                        (int)$lead['review_count']
                                    ) ?>

                                    reviews

                                </div>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>


                        <!-- SCORE -->

                        <td>

                            <span
                                class="badge text-bg-<?= e($scoreClass) ?>"
                            >

                                <?= $score ?>/100

                            </span>

                        </td>


                        <!-- PRIORITY -->

                        <td>

                            <span
                                class="badge text-bg-<?= e($priorityClass) ?>"
                            >

                                <?= e(
                                    strtoupper(
                                        $lead['priority']
                                    )
                                ) ?>

                            </span>

                        </td>


                        <!-- STATUS -->

                        <td>

                            <?= e(
                                ucwords(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $lead['lead_status']
                                    )
                                )
                            ) ?>

                        </td>


                        <!-- ACTION -->

                        <td class="text-end">

                            <div class="d-flex gap-1 justify-content-end">

                                <?php if (!empty($lead['google_maps_url'])): ?>

                                    <a
                                        href="<?= e($lead['google_maps_url']) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="btn btn-sm btn-outline-secondary"
                                    >
                                        Maps
                                    </a>

                                <?php endif; ?>


                                <a
                                    href="lead.php?id=<?= (int)$lead['id'] ?>"
                                    class="btn btn-sm btn-dark"
                                >
                                    View Lead
                                </a>

                            </div>

                        </td>


                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<?php require __DIR__ . '/includes/footer.php'; ?>