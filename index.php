<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

$pageTitle = 'Dashboard';


/*
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
*/

$stats = [
    'total_leads' => (int)db()->query("SELECT COUNT(*) FROM leads")->fetchColumn(),
    'hot_leads'   => (int)db()->query("SELECT COUNT(*) FROM leads WHERE priority='hot'")->fetchColumn(),
    'contacted'   => (int)db()->query("SELECT COUNT(*) FROM leads WHERE lead_status IN ('contacted','follow_up','interested','quote_sent','negotiation','won')")->fetchColumn(),
    'quotes'      => (int)db()->query("SELECT COUNT(*) FROM quotes WHERE status IN ('sent','viewed','accepted')")->fetchColumn(),
    'won'         => (int)db()->query("SELECT COUNT(*) FROM leads WHERE lead_status='won'")->fetchColumn(),
    'potential'   => (float)db()->query("SELECT COALESCE(SUM(estimated_value_max),0) FROM leads WHERE lead_status NOT IN ('lost','do_not_contact')")->fetchColumn(),
];


/*
|--------------------------------------------------------------------------
| RECENT LEADS — PAGINATION
|--------------------------------------------------------------------------
*/

$perPage = 10;

$page = max(1, (int)($_GET['page'] ?? 1));

$totalRows = (int)db()->query("SELECT COUNT(*) FROM leads")->fetchColumn();

$totalPages = max(1, (int)ceil($totalRows / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;


/*
|--------------------------------------------------------------------------
| RECENT LEADS — QUERY
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare("
    SELECT id, business_name, city, website_status, lead_score, priority, lead_status
    FROM leads
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
");

$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset,  PDO::PARAM_INT);
$stmt->execute();

$recent = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| PAGINATION URL HELPER
|--------------------------------------------------------------------------
|
| Preserves any future query params (filters, sort) while overriding page.
|
*/

function page_url(int $targetPage): string
{
    $params = $_GET;
    $params['page'] = $targetPage;

    return 'index.php?' . http_build_query($params);
}


/*
|--------------------------------------------------------------------------
| EXPORT URL HELPER
|--------------------------------------------------------------------------
|
| Links to the leads export with the same format the user picked.
| Always exports the full filtered lead set from leads_export.php.
|
*/

function export_url(string $format): string
{
    return 'leads_export.php?format=' . urlencode($format);
}

require __DIR__ . '/includes/header.php';
?>

<style>
    /*
     * Dashboard-specific responsive tweaks.
     * Keeps table rows readable on phones.
     */

    @media (max-width: 767.98px) {

        /* Stack "Find Leads" button below the title on phones */
        .dash-header {
            flex-direction: column;
            align-items: flex-start !important;
            gap: .75rem;
        }

        .dash-header .btn {
            width: 100%;
        }

        /* Tighter metric cards */
        .metric-card .fs-3 {
            font-size: 1.35rem !important;
        }

        /*
         * Compact table: hide the Website column and reduce padding
         * so more columns fit. Users can see full details on lead.php
         */
        .recent-table th:nth-child(3),
        .recent-table td:nth-child(3) {
            display: none;
        }

        .recent-table th,
        .recent-table td {
            padding: .5rem .4rem;
            font-size: .85rem;
            vertical-align: middle;
        }
    }

    /* Pagination on mobile: tighten spacing */
    @media (max-width: 575.98px) {
        .pagination {
            --bs-pagination-padding-x: .6rem;
            --bs-pagination-padding-y: .3rem;
            --bs-pagination-font-size: .85rem;
        }
    }

    /*
     * Print styles: hides navigation, buttons, filters, and pagination.
     * Keeps the dashboard metrics and recent leads table.
     */
    @media print {

        .sidebar,
        .mobile-topbar,
        .sidebar-backdrop,
        .dash-header,
        .export-bar,
        .pagination,
        .btn {
            display: none !important;
        }

        body {
            background: #fff !important;
        }

        main {
            padding: 0 !important;
        }

        .card {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
        }

        .card-header {
            background: #f3f4f6 !important;
        }

        .table {
            font-size: 11px;
        }

        .table th,
        .table td {
            padding: 4px 6px;
        }

        thead {
            display: table-header-group;
        }

        tr {
            page-break-inside: avoid;
        }
    }
</style>


<div class="d-flex justify-content-between align-items-center mb-4 dash-header">
    <div>
        <h1 class="h3 mb-0">Dashboard</h1>
        <div class="text-muted small">
            Welcome, <?= e(user()['name']) ?>
        </div>
    </div>
    <a href="search.php" class="btn btn-dark">Find Leads</a>
</div>


<!-- EXPORT BAR -->

<div class="export-bar d-flex flex-wrap gap-2 mb-3 align-items-center">

    <span class="text-muted small me-1">Export:</span>

    <a
        href="<?= e(export_url('csv')) ?>"
        class="btn btn-sm btn-outline-dark"
    >
        CSV
    </a>

    <a
        href="<?= e(export_url('excel')) ?>"
        class="btn btn-sm btn-outline-dark"
    >
        Excel
    </a>

    <button
        type="button"
        onclick="window.print()"
        class="btn btn-sm btn-outline-dark"
    >
        Print
    </button>

    <span class="text-muted small ms-auto">
        Exports full lead database
    </span>

</div>


<!-- STATS -->

<div class="row g-3 mb-4">

    <?php
    $cards = [
        ['Total Leads',     $stats['total_leads']],
        ['Hot Leads',       $stats['hot_leads']],
        ['Contacted',       $stats['contacted']],
        ['Quotes',          $stats['quotes']],
        ['Won',             $stats['won']],
        ['Potential Value', '£' . number_format($stats['potential'], 0)],
    ];

    foreach ($cards as [$label, $value]):
    ?>

        <div class="col-6 col-md-4 col-xl-2">
            <div class="card metric-card h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= e($label) ?></div>
                    <div class="fs-3 fw-bold"><?= e((string)$value) ?></div>
                </div>
            </div>
        </div>

    <?php endforeach; ?>

</div>


<!-- RECENT LEADS -->

<div class="card border-0 shadow-sm">

    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong>Recent Leads</strong>
        <span class="text-muted small">
            <?php if ($totalRows > 0): ?>
                <?= number_format($offset + 1) ?>–<?= number_format(min($offset + $perPage, $totalRows)) ?>
                of <?= number_format($totalRows) ?>
            <?php else: ?>
                0 leads
            <?php endif; ?>
        </span>
    </div>


    <div class="table-responsive">

        <table class="table table-hover align-middle mb-0 recent-table">

            <thead>
                <tr>
                    <th>Business</th>
                    <th>Location</th>
                    <th>Website</th>
                    <th>Score</th>
                    <th>Priority</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>

            <?php if (!$recent): ?>

                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">
                        No leads yet. Start your first campaign.
                    </td>
                </tr>

            <?php else: foreach ($recent as $lead): ?>

                <tr>
                    <td>
                        <a href="lead.php?id=<?= (int)$lead['id'] ?>" class="fw-semibold text-decoration-none">
                            <?= e($lead['business_name']) ?>
                        </a>
                    </td>
                    <td><?= e($lead['city'] ?: '—') ?></td>
                    <td><?= e($lead['website_status']) ?></td>
                    <td><?= (int)$lead['lead_score'] ?>/100</td>
                    <td>
                        <span class="badge text-bg-<?= e(
                            $lead['priority'] === 'hot' ? 'danger'
                            : ($lead['priority'] === 'high' ? 'warning'
                            : ($lead['priority'] === 'medium' ? 'primary' : 'secondary'))
                        ) ?>">
                            <?= e(strtoupper($lead['priority'])) ?>
                        </span>
                    </td>
                    <td><?= e(ucwords(str_replace('_',' ', $lead['lead_status']))) ?></td>
                </tr>

            <?php endforeach; endif; ?>

            </tbody>

        </table>

    </div>


    <!-- PAGINATION -->

    <?php if ($totalPages > 1): ?>

        <div class="card-footer bg-white border-top-0 pt-3 pb-3">

            <nav aria-label="Recent leads pagination">

                <ul class="pagination justify-content-center mb-0 flex-wrap">

                    <!-- Previous -->

                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a
                            class="page-link"
                            href="<?= $page <= 1 ? '#' : e(page_url($page - 1)) ?>"
                            aria-label="Previous"
                            <?= $page <= 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>
                        >
                            &laquo; <span class="d-none d-sm-inline">Prev</span>
                        </a>
                    </li>


                    <!-- Page numbers -->

                    <?php
                    /*
                     * Windowed pagination: show up to 5 page numbers
                     * centred around the current page.
                     */
                    $windowSize = 5;

                    $start = max(1, $page - (int)floor($windowSize / 2));
                    $end   = min($totalPages, $start + $windowSize - 1);

                    // Adjust start if we're near the end
                    if (($end - $start + 1) < $windowSize) {
                        $start = max(1, $end - $windowSize + 1);
                    }

                    // First page + ellipsis
                    if ($start > 1):
                    ?>

                        <li class="page-item">
                            <a class="page-link" href="<?= e(page_url(1)) ?>">1</a>
                        </li>

                        <?php if ($start > 2): ?>
                            <li class="page-item disabled">
                                <span class="page-link">&hellip;</span>
                            </li>
                        <?php endif; ?>

                    <?php endif; ?>


                    <!-- Numbered window -->

                    <?php for ($i = $start; $i <= $end; $i++): ?>

                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a
                                class="page-link"
                                href="<?= e(page_url($i)) ?>"
                                <?= $i === $page ? 'aria-current="page"' : '' ?>
                            >
                                <?= $i ?>
                            </a>
                        </li>

                    <?php endfor; ?>


                    <!-- Ellipsis + last page -->

                    <?php if ($end < $totalPages): ?>

                        <?php if ($end < $totalPages - 1): ?>
                            <li class="page-item disabled">
                                <span class="page-link">&hellip;</span>
                            </li>
                        <?php endif; ?>

                        <li class="page-item">
                            <a class="page-link" href="<?= e(page_url($totalPages)) ?>"><?= $totalPages ?></a>
                        </li>

                    <?php endif; ?>


                    <!-- Next -->

                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a
                            class="page-link"
                            href="<?= $page >= $totalPages ? '#' : e(page_url($page + 1)) ?>"
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