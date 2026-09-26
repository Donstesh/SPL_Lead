<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();


/*
|--------------------------------------------------------------------------
| CREATE CAMPAIGN
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $name         = trim((string)($_POST['name'] ?? ''));
    $businessType = trim((string)($_POST['business_type'] ?? ''));
    $locationText = trim((string)($_POST['location_text'] ?? ''));
    $radiusMiles  = (float)($_POST['radius_miles'] ?? 10);

    $minRating  = ($_POST['min_rating']  ?? '') !== '' ? (float)$_POST['min_rating']  : null;
    $minReviews = ($_POST['min_reviews'] ?? '') !== '' ? (int)  $_POST['min_reviews'] : null;

    $allowedWebsiteFilters = ['any', 'none', 'social_only', 'existing', 'poor'];
    $websiteFilter = in_array(
        $_POST['website_filter'] ?? '',
        $allowedWebsiteFilters,
        true
    ) ? $_POST['website_filter'] : 'any';

    if ($name === '' || $businessType === '' || $locationText === '') {

        set_flash('error', 'Please fill in all required fields.');

    } else {

        $stmt = db()->prepare("
            INSERT INTO campaigns
            (
                name,
                business_type,
                location_text,
                radius_miles,
                min_rating,
                min_reviews,
                website_filter,
                status,
                created_by
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, 'draft', ?)
        ");

        $stmt->execute([
            $name,
            $businessType,
            $locationText,
            $radiusMiles,
            $minRating,
            $minReviews,
            $websiteFilter,
            user()['id'],
        ]);

        set_flash('success', 'Campaign created.');
    }

    redirect('campaigns.php');
}


/*
|--------------------------------------------------------------------------
| LOAD CAMPAIGNS
|--------------------------------------------------------------------------
*/

$campaigns = db()->query("
    SELECT
        c.*,
        (SELECT COUNT(*) FROM leads l WHERE l.campaign_id = c.id) AS lead_count
    FROM campaigns c
    ORDER BY c.created_at DESC
")->fetchAll();


/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

$pageTitle = 'Campaigns';
require __DIR__ . '/includes/header.php';
?>

<style>
    /*
     * Campaigns page — mobile responsive.
     *
     * Desktop: form on the left, table on the right.
     * Mobile:  form on top, campaigns as a card list below.
     */

    @media (max-width: 767.98px) {

        /* Header buttons stack full width */
        .campaigns-header {
            flex-direction: column;
            align-items: stretch !important;
            gap: .75rem;
        }

        .campaigns-header .btn {
            width: 100%;
        }

        /* Hide the desktop table on mobile */
        .campaigns-table-desktop {
            display: none !important;
        }

        /* Show the mobile card list */
        .campaigns-cards-mobile {
            display: block !important;
        }

        /* Touch-friendly form controls */
        .campaigns-form .form-control,
        .campaigns-form .form-select {
            min-height: 48px;
            font-size: 16px;
        }

        .campaigns-form .btn {
            min-height: 48px;
            font-size: 16px;
        }
    }

    /* Desktop: hide mobile cards */
    @media (min-width: 768px) {
        .campaigns-cards-mobile {
            display: none !important;
        }
    }

    /* Mobile campaign card */
    .campaign-card {
        border-bottom: 1px solid #e5e7eb;
        padding: 1rem;
    }

    .campaign-card:last-child {
        border-bottom: 0;
    }

    .campaign-card-title {
        font-weight: 600;
        font-size: 1rem;
        color: #111827;
        margin: 0 0 .25rem 0;
    }

    .campaign-card-meta {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        font-size: .8125rem;
        color: #4b5563;
        margin-bottom: .6rem;
    }

    .campaign-card-badges {
        display: flex;
        flex-wrap: wrap;
        gap: .35rem;
    }
</style>


<!-- HEADER -->

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3 campaigns-header">

    <div>
        <h1 class="h3 mb-1">Campaigns</h1>
        <div class="text-muted small">
            Group your lead searches by business type and location.
        </div>
    </div>

    <div>
        <a href="search.php" class="btn btn-outline-dark">
            Find Leads
        </a>
    </div>

</div>


<div class="row g-4">

    <!-- =====================================================
         FORM
    ====================================================== -->

    <div class="col-12 col-lg-4">

        <div class="card border-0 shadow-sm">

            <div class="card-body campaigns-form">

                <h2 class="h5 mb-3">New Campaign</h2>

                <form method="post" novalidate>

                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label">Campaign Name</label>
                        <input
                            class="form-control"
                            name="name"
                            required
                            placeholder="Hounslow Barbers"
                            autocomplete="off"
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Business Type</label>
                        <input
                            class="form-control"
                            name="business_type"
                            required
                            placeholder="Barber"
                            autocomplete="off"
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <input
                            class="form-control"
                            name="location_text"
                            required
                            placeholder="Hounslow, London"
                            autocomplete="off"
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Radius (miles)</label>
                        <input
                            class="form-control"
                            type="number"
                            min="1"
                            max="100"
                            step="0.5"
                            name="radius_miles"
                            value="10"
                            inputmode="decimal"
                            required
                        >
                    </div>

                    <div class="row">

                        <div class="col-6 mb-3">
                            <label class="form-label">Min Rating</label>
                            <input
                                class="form-control"
                                type="number"
                                min="0"
                                max="5"
                                step="0.1"
                                name="min_rating"
                                placeholder="4.0"
                                inputmode="decimal"
                            >
                        </div>

                        <div class="col-6 mb-3">
                            <label class="form-label">Min Reviews</label>
                            <input
                                class="form-control"
                                type="number"
                                min="0"
                                name="min_reviews"
                                placeholder="10"
                                inputmode="numeric"
                            >
                        </div>

                    </div>

                    <div class="mb-3">
                        <label class="form-label">Website Filter</label>
                        <select class="form-select" name="website_filter">
                            <option value="any">Any</option>
                            <option value="none">No Website</option>
                            <option value="social_only">Social Only</option>
                            <option value="existing">Existing Website</option>
                            <option value="poor">Poor Website</option>
                        </select>
                    </div>

                    <button class="btn btn-dark w-100">
                        Create Campaign
                    </button>

                </form>

            </div>

        </div>

    </div>


    <!-- =====================================================
         LIST
    ====================================================== -->

    <div class="col-12 col-lg-8">

        <div class="card border-0 shadow-sm">

            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Your Campaigns</strong>
                <span class="text-muted small">
                    <?= number_format(count($campaigns)) ?>
                    campaign<?= count($campaigns) === 1 ? '' : 's' ?>
                </span>
            </div>


            <?php if (!$campaigns): ?>

                <div class="text-center py-5 px-3">

                    <div class="mb-3 text-muted">
                        No campaigns yet.
                    </div>

                    <p class="text-muted small">
                        Create your first campaign using the form to group
                        lead searches by business type and location.
                    </p>

                </div>

            <?php else: ?>


                <!-- =====================================================
                     DESKTOP TABLE
                ====================================================== -->

                <div class="table-responsive campaigns-table-desktop">

                    <table class="table table-hover align-middle mb-0">

                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Location</th>
                                <th>Radius</th>
                                <th>Leads</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($campaigns as $campaign): ?>

                            <tr>

                                <td>
                                    <strong><?= e($campaign['name']) ?></strong>
                                </td>

                                <td><?= e($campaign['business_type']) ?></td>

                                <td><?= e($campaign['location_text']) ?></td>

                                <td><?= e((string)(float)$campaign['radius_miles']) ?> mi</td>

                                <td>
                                    <span class="badge text-bg-<?= (int)$campaign['lead_count'] > 0 ? 'primary' : 'secondary' ?>">
                                        <?= (int)$campaign['lead_count'] ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="badge text-bg-<?= $campaign['status'] === 'active' ? 'success' : 'secondary' ?>">
                                        <?= e(ucfirst($campaign['status'])) ?>
                                    </span>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>


                <!-- =====================================================
                     MOBILE CARD LIST
                ====================================================== -->

                <div class="campaigns-cards-mobile">

                    <?php foreach ($campaigns as $campaign): ?>

                        <div class="campaign-card">

                            <h3 class="campaign-card-title">
                                <?= e($campaign['name']) ?>
                            </h3>

                            <div class="campaign-card-meta">
                                <span><?= e($campaign['business_type']) ?></span>
                                <span>·</span>
                                <span><?= e($campaign['location_text']) ?></span>
                                <span>·</span>
                                <span><?= e((string)(float)$campaign['radius_miles']) ?> mi</span>
                            </div>

                            <div class="campaign-card-badges">

                                <span class="badge text-bg-<?= (int)$campaign['lead_count'] > 0 ? 'primary' : 'secondary' ?>">
                                    <?= (int)$campaign['lead_count'] ?>
                                    lead<?= (int)$campaign['lead_count'] === 1 ? '' : 's' ?>
                                </span>

                                <span class="badge text-bg-<?= $campaign['status'] === 'active' ? 'success' : 'secondary' ?>">
                                    <?= e(ucfirst($campaign['status'])) ?>
                                </span>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>


            <?php endif; ?>

        </div>

    </div>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>