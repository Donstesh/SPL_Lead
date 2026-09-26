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

$where  = [];
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
    $where[]  = 'l.lead_status = ?';
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
    $where[]  = 'l.priority = ?';
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
    $where[]  = 'l.website_status = ?';
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
| PAGINATION SETUP
|--------------------------------------------------------------------------
*/

$perPage = 20;

$page = max(1, (int)($_GET['page'] ?? 1));


/*
|--------------------------------------------------------------------------
| TOTAL ROW COUNT (respects filters)
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT COUNT(*)
    FROM leads l
    $whereSql
";

$countStmt = db()->prepare($countSql);
$countStmt->execute($params);

$totalRows = (int)$countStmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalRows / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;


/*
|--------------------------------------------------------------------------
| LOAD LEADS (paginated)
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

    LIMIT ? OFFSET ?
";

$stmt = db()->prepare($sql);

// Bind filter params first, then limit/offset as integers
$bindIndex = 1;

foreach ($params as $p) {
    $stmt->bindValue($bindIndex++, $p, PDO::PARAM_STR);
}

$stmt->bindValue($bindIndex++, $perPage, PDO::PARAM_INT);
$stmt->bindValue($bindIndex++, $offset,  PDO::PARAM_INT);

$stmt->execute();

$leads = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| DASHBOARD COUNTS
|--------------------------------------------------------------------------
|
| These are always across the whole table, not affected by filters.
|
*/

$totalLeads      = (int)db()->query("SELECT COUNT(*) FROM leads")->fetchColumn();
$hotLeads        = (int)db()->query("SELECT COUNT(*) FROM leads WHERE priority = 'hot'")->fetchColumn();
$noWebsite       = (int)db()->query("SELECT COUNT(*) FROM leads WHERE website_status = 'none'")->fetchColumn();
$contactRequired = (int)db()->query("
    SELECT COUNT(*) FROM leads
    WHERE lead_status IN ('new','researched','qualified','contact_required')
")->fetchColumn();
$wonLeads        = (int)db()->query("SELECT COUNT(*) FROM leads WHERE lead_status = 'won'")->fetchColumn();


/*
|--------------------------------------------------------------------------
| PAGINATION URL HELPER
|--------------------------------------------------------------------------
|
| Preserves all current filters when navigating pages.
|
*/

function leads_page_url(int $targetPage): string
{
    $params = $_GET;
    $params['page'] = $targetPage;

    return 'leads.php?' . http_build_query($params);
}


/*
|--------------------------------------------------------------------------
| FILTER STATE
|--------------------------------------------------------------------------
*/

$hasActiveFilters = (
    $search !== ''
    || $status !== ''
    || $priority !== ''
    || $websiteStatus !== ''
);


/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

$pageTitle = 'Leads';

require __DIR__ . '/includes/header.php';

?>

<style>
    /*
     * Leads page — mobile responsive.
     */

    @media (max-width: 767.98px) {

        /* Header: stack buttons below title */
        .leads-header {
            flex-direction: column;
            align-items: stretch !important;
            gap: .75rem;
        }

        .leads-header .btn-group-mobile {
            display: flex;
            gap: .5rem;
        }

        .leads-header .btn {
            flex: 1;
        }

        /* Stats cards: 2 per row */
        .metric-card .fs-3 {
            font-size: 1.35rem !important;
        }

        /* Hide the desktop table on mobile */
        .leads-table-desktop {
            display: none !important;
        }

        /* Show the mobile card list */
        .leads-cards-mobile {
            display: block !important;
        }

        /*
         * Compact filter card. Keep the "clear filters" link visible.
         */
        .filter-card .btn {
            min-height: 44px;
        }
    }

    /* Desktop: hide mobile cards */
    @media (min-width: 768px) {
        .leads-cards-mobile {
            display: none !important;
        }
    }

    /* Mobile lead card */
    .lead-card {
        border-bottom: 1px solid #e5e7eb;
        padding: 1rem;
    }

    .lead-card:last-child {
        border-bottom: 0;
    }

    .lead-card-title {
        font-weight: 600;
        font-size: 1rem;
        color: #111827;
        text-decoration: none;
        display: block;
        margin-bottom: .25rem;
    }

    .lead-card-title:hover {
        text-decoration: underline;
    }

    .lead-card-location {
        font-size: .8125rem;
        color: #6b7280;
        margin-bottom: .5rem;
    }

    .lead-card-badges {
        display: flex;
        flex-wrap: wrap;
        gap: .35rem;
        margin-bottom: .5rem;
    }

    .lead-card-meta {
        display: flex;
        flex-wrap: wrap;
        gap: .75rem;
        font-size: .8125rem;
        color: #4b5563;
        margin-bottom: .75rem;
    }

    .lead-card-actions {
        display: flex;
        gap: .5rem;
    }

    .lead-card-actions .btn {
        flex: 1;
        min-height: 40px;
    }

    /* Pagination compact on very small screens */
    @media (max-width: 575.98px) {
        .pagination {
            --bs-pagination-padding-x: .6rem;
            --bs-pagination-padding-y: .3rem;
            --bs-pagination-font-size: .85rem;
        }
    }
</style>


<!-- HEADER -->

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3 leads-header">

    <div>
        <h1 class="h3 mb-1">Leads</h1>

        <div class="text-muted small">
            SPL Web Services Sales Intelligence
        </div>
    </div>

    <div class="btn-group-mobile d-flex gap-2">

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

    <?php
    $stats = [
        ['Total Leads',      $totalLeads],
        ['Hot Leads',        $hotLeads],
        ['No Website',       $noWebsite],
        ['Contact Required', $contactRequired],
        ['Won',              $wonLeads],
    ];

    foreach ($stats as [$label, $value]):
    ?>

        <div class="col-6 col-md-6 col-xl">
            <div class="card border-0 shadow-sm h-100 metric-card">
                <div class="card-body">
                    <div class="text-muted small"><?= e($label) ?></div>
                    <div class="fs-3 fw-bold"><?= number_format($value) ?></div>
                </div>
            </div>
        </div>

    <?php endforeach; ?>

</div>


<!-- FILTERS -->

<div class="card border-0 shadow-sm mb-4 filter-card">

    <div class="card-body">

        <form method="get" action="leads.php">

            <div class="row g-3 align-items-end">

                <div class="col-12 col-lg-4">

                    <label class="form-label">Search Leads</label>

                    <input
                        type="text"
                        name="q"
                        class="form-control"
                        placeholder="Business, town, postcode, phone..."
                        value="<?= e($search) ?>"
                    >

                </div>


                <div class="col-6 col-lg-2">

                    <label class="form-label">Status</label>

                    <select name="status" class="form-select">

                        <option value="">All Statuses</option>

                        <?php foreach ($allowedStatuses as $item): ?>

                            <option
                                value="<?= e($item) ?>"
                                <?= $status === $item ? 'selected' : '' ?>
                            >
                                <?= e(ucwords(str_replace('_', ' ', $item))) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="col-6 col-lg-2">

                    <label class="form-label">Priority</label>

                    <select name="priority" class="form-select">

                        <option value="">All Priorities</option>

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


                <div class="col-12 col-lg-2">

                    <label class="form-label">Website</label>

                    <select name="website_status" class="form-select">

                        <option value="">All Websites</option>

                        <option value="none"        <?= $websiteStatus === 'none'        ? 'selected' : '' ?>>No Website</option>
                        <option value="found"       <?= $websiteStatus === 'found'       ? 'selected' : '' ?>>Website Found</option>
                        <option value="social_only" <?= $websiteStatus === 'social_only' ? 'selected' : '' ?>>Social Only</option>
                        <option value="poor"        <?= $websiteStatus === 'poor'        ? 'selected' : '' ?>>Poor Website</option>
                        <option value="good"        <?= $websiteStatus === 'good'        ? 'selected' : '' ?>>Good Website</option>
                        <option value="investigate" <?= $websiteStatus === 'investigate' ? 'selected' : '' ?>>Investigate</option>

                    </select>

                </div>


                <div class="col-12 col-lg-2">

                    <button type="submit" class="btn btn-dark w-100">
                        Filter Leads
                    </button>

                </div>

            </div>


            <?php if ($hasActiveFilters): ?>

                <div class="mt-3">

                    <a href="leads.php" class="btn btn-sm btn-outline-secondary">
                        Clear Filters
                    </a>

                </div>

            <?php endif; ?>

        </form>

    </div>

</div>


<!-- LEADS -->

<div class="card border-0 shadow-sm">

    <div class="card-header bg-white d-flex justify-content-between align-items-center">

        <strong>Lead Database</strong>

        <span class="text-muted small">

            <?php if ($totalRows > 0): ?>

                <?= number_format($offset + 1) ?>–<?= number_format(min($offset + $perPage, $totalRows)) ?>
                of <?= number_format($totalRows) ?>

            <?php else: ?>

                0 results

            <?php endif; ?>

        </span>

    </div>


    <?php if (!$leads): ?>

        <!-- EMPTY STATE -->

        <div class="text-center py-5 px-3">

            <div class="mb-3 text-muted">
                No leads found<?= $hasActiveFilters ? ' matching your filters' : '' ?>.
            </div>

            <?php if ($hasActiveFilters): ?>

                <a href="leads.php" class="btn btn-outline-secondary me-2">
                    Clear Filters
                </a>

            <?php endif; ?>

            <a href="search.php" class="btn btn-dark">
                Find Your First Leads
            </a>

        </div>

    <?php else: ?>


        <!-- =====================================================
             DESKTOP TABLE
        ====================================================== -->

        <div class="table-responsive leads-table-desktop">

            <table class="table table-hover align-middle mb-0">

                <thead>
                    <tr>
                        <th>Business</th>
                        <th>Campaign</th>
                        <th>Website</th>
                        <th>Rating</th>
                        <th>Score</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($leads as $lead):

                    $score = (int)$lead['lead_score'];

                    $scoreClass = 'secondary';
                    if ($score >= 80) {
                        $scoreClass = 'danger';
                    } elseif ($score >= 60) {
                        $scoreClass = 'warning';
                    } elseif ($score >= 40) {
                        $scoreClass = 'primary';
                    }

                    $priorityClass = 'secondary';
                    if ($lead['priority'] === 'hot') {
                        $priorityClass = 'danger';
                    } elseif ($lead['priority'] === 'high') {
                        $priorityClass = 'warning';
                    } elseif ($lead['priority'] === 'medium') {
                        $priorityClass = 'primary';
                    }
                ?>

                    <tr>

                        <td>
                            <a
                                class="fw-semibold text-decoration-none"
                                href="lead.php?id=<?= (int)$lead['id'] ?>"
                            >
                                <?= e($lead['business_name']) ?>
                            </a>

                            <div class="small text-muted">
                                <?= e(trim(($lead['city'] ?? '') . ' ' . ($lead['postcode'] ?? ''))) ?>
                            </div>

                            <?php if (!empty($lead['phone'])): ?>
                                <div class="small">
                                    <a href="tel:<?= e($lead['phone']) ?>" class="text-decoration-none">
                                        <?= e($lead['phone']) ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td><?= !empty($lead['campaign_name']) ? e($lead['campaign_name']) : '—' ?></td>

                        <td>
                            <?php if ($lead['website_status'] === 'none' || empty($lead['website_url'])): ?>
                                <span class="badge text-bg-danger">NO WEBSITE</span>
                            <?php else: ?>
                                <span class="badge text-bg-success">WEBSITE</span>
                                <div class="mt-1">
                                    <a href="<?= e($lead['website_url']) ?>" target="_blank" rel="noopener noreferrer" class="small">
                                        Visit
                                    </a>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($lead['rating'] !== null): ?>
                                <strong><?= number_format((float)$lead['rating'], 1) ?></strong> ★
                                <div class="small text-muted">
                                    <?= number_format((int)$lead['review_count']) ?> reviews
                                </div>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="badge text-bg-<?= e($scoreClass) ?>"><?= $score ?>/100</span>
                        </td>

                        <td>
                            <span class="badge text-bg-<?= e($priorityClass) ?>">
                                <?= e(strtoupper($lead['priority'])) ?>
                            </span>
                        </td>

                        <td>
                            <?= e(ucwords(str_replace('_', ' ', $lead['lead_status']))) ?>
                        </td>

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

                                <a href="lead.php?id=<?= (int)$lead['id'] ?>" class="btn btn-sm btn-dark">
                                    View Lead
                                </a>
                            </div>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>


        <!-- =====================================================
             MOBILE CARD LIST (hidden on desktop)
        ====================================================== -->

        <div class="leads-cards-mobile">

            <?php foreach ($leads as $lead):

                $score = (int)$lead['lead_score'];

                $scoreClass = 'secondary';
                if ($score >= 80) {
                    $scoreClass = 'danger';
                } elseif ($score >= 60) {
                    $scoreClass = 'warning';
                } elseif ($score >= 40) {
                    $scoreClass = 'primary';
                }

                $priorityClass = 'secondary';
                if ($lead['priority'] === 'hot') {
                    $priorityClass = 'danger';
                } elseif ($lead['priority'] === 'high') {
                    $priorityClass = 'warning';
                } elseif ($lead['priority'] === 'medium') {
                    $priorityClass = 'primary';
                }
            ?>

                <div class="lead-card">

                    <a
                        href="lead.php?id=<?= (int)$lead['id'] ?>"
                        class="lead-card-title"
                    >
                        <?= e($lead['business_name']) ?>
                    </a>

                    <div class="lead-card-location">
                        <?= e(trim(($lead['city'] ?? '') . ' ' . ($lead['postcode'] ?? ''))) ?: '—' ?>
                    </div>


                    <div class="lead-card-badges">

                        <span class="badge text-bg-<?= e($scoreClass) ?>">
                            Score <?= $score ?>/100
                        </span>

                        <span class="badge text-bg-<?= e($priorityClass) ?>">
                            <?= e(strtoupper($lead['priority'])) ?>
                        </span>

                        <?php if ($lead['website_status'] === 'none' || empty($lead['website_url'])): ?>
                            <span class="badge text-bg-danger">NO WEBSITE</span>
                        <?php else: ?>
                            <span class="badge text-bg-success">WEBSITE</span>
                        <?php endif; ?>

                    </div>


                    <div class="lead-card-meta">

                        <?php if ($lead['rating'] !== null): ?>
                            <span>
                                <strong><?= number_format((float)$lead['rating'], 1) ?></strong> ★
                                (<?= number_format((int)$lead['review_count']) ?>)
                            </span>
                        <?php endif; ?>

                        <span>
                            <?= e(ucwords(str_replace('_', ' ', $lead['lead_status']))) ?>
                        </span>

                        <?php if (!empty($lead['campaign_name'])): ?>
                            <span><?= e($lead['campaign_name']) ?></span>
                        <?php endif; ?>

                    </div>


                    <?php if (!empty($lead['phone'])): ?>
                        <div class="mb-2 small">
                            <a href="tel:<?= e($lead['phone']) ?>" class="text-decoration-none">
                                📞 <?= e($lead['phone']) ?>
                            </a>
                        </div>
                    <?php endif; ?>


                    <div class="lead-card-actions">

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

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         PAGINATION
    ====================================================== -->

    <?php if ($totalPages > 1): ?>

        <div class="card-footer bg-white border-top-0 pt-3 pb-3">

            <nav aria-label="Leads pagination">

                <ul class="pagination justify-content-center mb-0 flex-wrap">

                    <!-- Previous -->

                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a
                            class="page-link"
                            href="<?= $page <= 1 ? '#' : e(leads_page_url($page - 1)) ?>"
                            aria-label="Previous"
                            <?= $page <= 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>
                        >
                            &laquo; <span class="d-none d-sm-inline">Prev</span>
                        </a>
                    </li>


                    <?php
                    $windowSize = 5;

                    $start = max(1, $page - (int)floor($windowSize / 2));
                    $end   = min($totalPages, $start + $windowSize - 1);

                    if (($end - $start + 1) < $windowSize) {
                        $start = max(1, $end - $windowSize + 1);
                    }

                    if ($start > 1):
                    ?>

                        <li class="page-item">
                            <a class="page-link" href="<?= e(leads_page_url(1)) ?>">1</a>
                        </li>

                        <?php if ($start > 2): ?>
                            <li class="page-item disabled">
                                <span class="page-link">&hellip;</span>
                            </li>
                        <?php endif; ?>

                    <?php endif; ?>


                    <?php for ($i = $start; $i <= $end; $i++): ?>

                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a
                                class="page-link"
                                href="<?= e(leads_page_url($i)) ?>"
                                <?= $i === $page ? 'aria-current="page"' : '' ?>
                            >
                                <?= $i ?>
                            </a>
                        </li>

                    <?php endfor; ?>


                    <?php if ($end < $totalPages): ?>

                        <?php if ($end < $totalPages - 1): ?>
                            <li class="page-item disabled">
                                <span class="page-link">&hellip;</span>
                            </li>
                        <?php endif; ?>

                        <li class="page-item">
                            <a class="page-link" href="<?= e(leads_page_url($totalPages)) ?>"><?= $totalPages ?></a>
                        </li>

                    <?php endif; ?>


                    <!-- Next -->

                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a
                            class="page-link"
                            href="<?= $page >= $totalPages ? '#' : e(leads_page_url($page + 1)) ?>"
                            aria-label="Next"
                            <?= $page >= $totalPages ? 'tabindex="-1" aria-disabled="true"' : '' ?>
                        >
                            <span class="d-none d-sm-inline">Next</span> &raquo;
                        </a>
                    </li>

                </ul>

            </nav>

        </div>

    <?php endif; ?>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>