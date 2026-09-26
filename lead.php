<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

$id = (int)($_GET['id'] ?? 0);

$stmt = db()->prepare("
    SELECT l.*, c.name AS campaign_name
    FROM leads l
    LEFT JOIN campaigns c ON c.id = l.campaign_id
    WHERE l.id = ?
");
$stmt->execute([$id]);
$lead = $stmt->fetch();

if (!$lead) {
    http_response_code(404);
    exit('Lead not found.');
}


/*
|--------------------------------------------------------------------------
| DISPLAY HELPERS
|--------------------------------------------------------------------------
*/

$addressParts = array_filter([
    trim((string)($lead['address_line1'] ?? '')),
    trim((string)($lead['address_line2'] ?? '')),
    trim((string)($lead['city'] ?? '')),
    trim((string)($lead['county'] ?? '')),
    trim((string)($lead['postcode'] ?? '')),
]);

$fullAddress = implode(', ', $addressParts);

$websiteStatusLabels = [
    'unknown'     => ['Unknown',     'secondary'],
    'none'        => ['No Website',  'danger'],
    'social_only' => ['Social Only', 'warning'],
    'found'       => ['Website',     'success'],
    'poor'        => ['Poor',        'warning'],
    'good'        => ['Good',        'success'],
    'investigate' => ['Investigate', 'info'],
];

$ws = $websiteStatusLabels[$lead['website_status']] ?? ['Unknown', 'secondary'];
$wsLabel = $ws[0];
$wsClass = $ws[1];

$leadStatusLabel = ucwords(str_replace('_', ' ', $lead['lead_status']));

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

$pageTitle = $lead['business_name'];
require __DIR__ . '/includes/header.php';
?>

<style>
    /*
     * Lead detail page — mobile responsive.
     */

    @media (max-width: 767.98px) {

        /* Header: stack score block below title */
        .lead-header {
            flex-direction: column;
            align-items: stretch !important;
            gap: 1rem;
        }

        .lead-header .lead-header-score {
            text-align: left !important;
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .75rem;
            background: #f3f4f6;
            border-radius: .5rem;
        }

        .lead-header .lead-header-score .score-value {
            font-size: 1.5rem !important;
            margin: 0;
        }

        .lead-header .lead-header-score .score-priority {
            font-size: .85rem;
        }

        /* Detail table: two columns on desktop, stack on mobile */
        .lead-detail-table th {
            width: 40%;
            font-weight: 600;
            color: #6b7280;
            font-size: .85rem;
            text-transform: uppercase;
            letter-spacing: .02em;
            vertical-align: top;
            padding: .6rem .5rem .6rem 0;
            border-top: 0;
        }

        .lead-detail-table td {
            padding: .6rem 0;
            border-top: 0;
            word-break: break-word;
        }

        /* Contact action buttons */
        .lead-contact-actions {
            display: flex;
            gap: .5rem;
            margin-bottom: 1rem;
        }

        .lead-contact-actions .btn {
            flex: 1;
            min-height: 44px;
        }
    }

    /* Desktop-style table th widths */
    @media (min-width: 768px) {
        .lead-detail-table th {
            width: 30%;
            font-weight: 600;
            color: #6b7280;
        }
    }
</style>


<!-- HEADER -->

<div class="d-flex justify-content-between align-items-start mb-4 lead-header">

    <div class="flex-grow-1">

        <h1 class="h3 mb-1"><?= e($lead['business_name']) ?></h1>

        <?php if ($fullAddress !== ''): ?>
            <div class="text-muted small">
                <?= e($fullAddress) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($lead['campaign_name'])): ?>
            <div class="small mt-1">
                <span class="text-muted">Campaign:</span>
                <?= e($lead['campaign_name']) ?>
            </div>
        <?php endif; ?>

    </div>

    <div class="text-end lead-header-score">

        <div class="fs-2 fw-bold score-value">
            <?= $score ?>/100
        </div>

        <div class="score-priority">
            <span class="badge text-bg-<?= e($priorityClass) ?>">
                <?= e(strtoupper($lead['priority'])) ?>
            </span>
        </div>

    </div>

</div>


<!-- QUICK ACTIONS (mobile-first) -->

<div class="lead-contact-actions mb-4">

    <?php if (!empty($lead['phone'])): ?>
        <a href="tel:<?= e($lead['phone']) ?>" class="btn btn-dark">
            📞 Call
        </a>
    <?php endif; ?>

    <?php if (!empty($lead['google_maps_url'])): ?>
        <a
            href="<?= e($lead['google_maps_url']) ?>"
            target="_blank"
            rel="noopener noreferrer"
            class="btn btn-outline-dark"
        >
            📍 Maps
        </a>
    <?php endif; ?>

    <?php if (!empty($lead['website_url'])): ?>
        <a
            href="<?= e($lead['website_url']) ?>"
            target="_blank"
            rel="noopener noreferrer"
            class="btn btn-outline-dark"
        >
            🌐 Website
        </a>
    <?php endif; ?>

</div>


<!-- BODY -->

<div class="row g-4">

    <!-- LEFT: Business Intelligence -->

    <div class="col-12 col-lg-7">

        <div class="card border-0 shadow-sm">

            <div class="card-body">

                <h2 class="h5 mb-3">Business Intelligence</h2>

                <table class="table lead-detail-table mb-0">

                    <tr>
                        <th>Phone</th>
                        <td>
                            <?php if (!empty($lead['phone'])): ?>
                                <a href="tel:<?= e($lead['phone']) ?>" class="text-decoration-none">
                                    <?= e($lead['phone']) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th>Email</th>
                        <td>
                            <?php if (!empty($lead['public_email'])): ?>
                                <a href="mailto:<?= e($lead['public_email']) ?>" class="text-decoration-none">
                                    <?= e($lead['public_email']) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th>Website</th>
                        <td>
                            <?php if (!empty($lead['website_url'])): ?>
                                <a
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    href="<?= e($lead['website_url']) ?>"
                                    class="text-decoration-none"
                                >
                                    <?= e($lead['website_url']) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">None detected</span>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th>Website Status</th>
                        <td>
                            <span class="badge text-bg-<?= e($wsClass) ?>">
                                <?= e($wsLabel) ?>
                            </span>
                        </td>
                    </tr>

                    <tr>
                        <th>Rating</th>
                        <td>
                            <?php if ($lead['rating'] !== null): ?>
                                <strong><?= number_format((float)$lead['rating'], 1) ?></strong> ★
                                <span class="text-muted small">
                                    (<?= number_format((int)$lead['review_count']) ?> reviews)
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th>CRM Status</th>
                        <td><?= e($leadStatusLabel) ?></td>
                    </tr>

                    <?php if (!empty($lead['discovered_at'])): ?>
                        <tr>
                            <th>Discovered</th>
                            <td class="small text-muted">
                                <?= e(date('j M Y', strtotime($lead['discovered_at']))) ?>
                            </td>
                        </tr>
                    <?php endif; ?>

                </table>

            </div>

        </div>

    </div>


    <!-- RIGHT: Opportunity -->

    <div class="col-12 col-lg-5">

        <div class="card border-0 shadow-sm">

            <div class="card-body">

                <h2 class="h5 mb-3">Opportunity</h2>

                <?php if (
                    $lead['estimated_value_min'] !== null
                    || $lead['estimated_value_max'] !== null
                ): ?>

                    <div class="mb-3">

                        <div class="text-muted small">Estimated value</div>

                        <div class="fs-4 fw-bold">
                            £<?= number_format((float)($lead['estimated_value_min'] ?? 0), 0) ?>
                            –
                            £<?= number_format((float)($lead['estimated_value_max'] ?? 0), 0) ?>
                        </div>

                    </div>

                <?php else: ?>

                    <p class="text-muted mb-3">
                        No estimated value set yet.
                    </p>

                <?php endif; ?>

                <p class="text-muted small mb-0">
                    Service-gap recommendations and automated website audits will appear here in the next phase.
                </p>

            </div>

        </div>

    </div>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>