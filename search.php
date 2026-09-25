<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_login();

require_once __DIR__ . '/includes/google_places.php';


/*
|--------------------------------------------------------------------------
| LOAD CAMPAIGNS
|--------------------------------------------------------------------------
*/

$campaigns = db()->query("
    SELECT
        id,
        name,
        business_type,
        location_text,
        radius_miles,
        min_rating,
        min_reviews,
        website_filter
    FROM campaigns
    WHERE status <> 'archived'
    ORDER BY created_at DESC
")->fetchAll();


/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$error = null;
$results = [];

$campaignId   = 0;
$businessType = '';
$locationText = '';
$radiusMiles  = '10';
$minRating    = '';
$minReviews   = '';
$websiteFilter = 'any';


/*
|--------------------------------------------------------------------------
| SEARCH GOOGLE PLACES
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $campaignId = isset($_POST['campaign_id'])
        ? (int)$_POST['campaign_id']
        : 0;

    $businessType = trim(
        isset($_POST['business_type'])
            ? $_POST['business_type']
            : ''
    );

    $locationText = trim(
        isset($_POST['location_text'])
            ? $_POST['location_text']
            : ''
    );

    $radiusMiles = trim(
        isset($_POST['radius_miles'])
            ? $_POST['radius_miles']
            : '10'
    );

    $minRating = trim(
        isset($_POST['min_rating'])
            ? $_POST['min_rating']
            : ''
    );

    $minReviews = trim(
        isset($_POST['min_reviews'])
            ? $_POST['min_reviews']
            : ''
    );

    $websiteFilter = isset($_POST['website_filter'])
        ? $_POST['website_filter']
        : 'any';


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($businessType === '') {

        $error = 'Please enter a business type.';

    } elseif ($locationText === '') {

        $error = 'Please enter a location.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | BUILD GOOGLE QUERY
            |--------------------------------------------------------------------------
            |
            | Example:
            |
            | Dentists within 10 miles of West London
            |
            */

            $googleQuery =
                $businessType
                . ' within '
                . $radiusMiles
                . ' miles of '
                . $locationText;


            /*
            |--------------------------------------------------------------------------
            | CALL GOOGLE
            |--------------------------------------------------------------------------
            */

            $response = google_places_text_search(
                $googleQuery,
                null,
                20
            );


            $places = isset($response['places'])
                ? $response['places']
                : [];


            /*
            |--------------------------------------------------------------------------
            | PROCESS RESULTS
            |--------------------------------------------------------------------------
            */

            foreach ($places as $place) {

                $lead = google_place_to_lead($place);


                /*
                |--------------------------------------------------------------------------
                | MINIMUM RATING
                |--------------------------------------------------------------------------
                */

                if ($minRating !== '') {

                    $rating = isset($lead['rating'])
                        ? (float)$lead['rating']
                        : 0;

                    if ($rating < (float)$minRating) {
                        continue;
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | MINIMUM REVIEWS
                |--------------------------------------------------------------------------
                */

                if ($minReviews !== '') {

                    $reviews = isset($lead['review_count'])
                        ? (int)$lead['review_count']
                        : 0;

                    if ($reviews < (int)$minReviews) {
                        continue;
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | WEBSITE FILTER
                |--------------------------------------------------------------------------
                */

                if ($websiteFilter === 'none') {

                    if ($lead['website_status'] !== 'none') {
                        continue;
                    }

                } elseif ($websiteFilter === 'existing') {

                    if ($lead['website_status'] === 'none') {
                        continue;
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | INITIAL LEAD SCORE
                |--------------------------------------------------------------------------
                */

                $scoring = calculate_initial_lead_score($lead);

                $lead['lead_score'] = $scoring['score'];
                $lead['priority']   = $scoring['priority'];

                $results[] = $lead;
            }


            /*
            |--------------------------------------------------------------------------
            | STORE RESULTS IN SESSION FOR IMPORT
            |--------------------------------------------------------------------------
            */

            $_SESSION['google_places_results'] = $results;

            $_SESSION['google_places_search'] = [
                'campaign_id'    => $campaignId,
                'business_type'  => $businessType,
                'location_text'  => $locationText,
                'radius_miles'   => $radiusMiles,
                'min_rating'     => $minRating,
                'min_reviews'    => $minReviews,
                'website_filter' => $websiteFilter
            ];


        } catch (Exception $e) {

            $error = $e->getMessage();

        } catch (Throwable $e) {

            $error = $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

$pageTitle = 'Find Leads';

require __DIR__ . '/includes/header.php';

?>


<div class="row">

    <div class="col-xl-12">


        <!-- ============================================================
             HEADER
        ============================================================ -->

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h1 class="h3 mb-1">
                    Find Businesses
                </h1>

                <div class="text-muted">
                    Search Google Places for potential SPL Web Services clients.
                </div>

            </div>

            <a
                href="leads.php"
                class="btn btn-outline-dark"
            >
                View Leads
            </a>

        </div>



        <!-- ============================================================
             ERROR
        ============================================================ -->

        <?php if ($error): ?>

            <div class="alert alert-danger">

                <strong>Google Places Error:</strong>

                <?= e($error) ?>

            </div>

        <?php endif; ?>



        <!-- ============================================================
             SEARCH FORM
        ============================================================ -->

        <div class="card border-0 shadow-sm mb-4">

            <div class="card-body p-4">

                <form
                    method="post"
                    action="search.php"
                >

                    <?= csrf_field() ?>


                    <div class="row g-3">


                        <!-- CAMPAIGN -->

                        <div class="col-lg-4">

                            <label class="form-label">
                                Campaign
                            </label>

                            <select
                                class="form-select"
                                name="campaign_id"
                                id="campaign_id"
                            >

                                <option value="0">
                                    No Campaign
                                </option>


                                <?php foreach ($campaigns as $c): ?>

                                    <option
                                        value="<?= (int)$c['id'] ?>"

                                        data-type="<?= e($c['business_type']) ?>"

                                        data-location="<?= e($c['location_text']) ?>"

                                        data-radius="<?= e(
                                            isset($c['radius_miles'])
                                                ? (string)$c['radius_miles']
                                                : '10'
                                        ) ?>"

                                        data-rating="<?= e(
                                            isset($c['min_rating'])
                                                ? (string)$c['min_rating']
                                                : ''
                                        ) ?>"

                                        data-reviews="<?= e(
                                            isset($c['min_reviews'])
                                                ? (string)$c['min_reviews']
                                                : ''
                                        ) ?>"

                                        data-website="<?= e(
                                            isset($c['website_filter'])
                                                ? $c['website_filter']
                                                : 'any'
                                        ) ?>"

                                        <?= $campaignId === (int)$c['id']
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >

                                        <?= e($c['name']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>



                        <!-- BUSINESS TYPE -->

                        <div class="col-lg-4">

                            <label class="form-label">
                                Business Type
                            </label>

                            <input
                                class="form-control"
                                type="text"
                                name="business_type"
                                id="business_type"
                                placeholder="Dentists"
                                value="<?= e($businessType) ?>"
                                required
                            >

                        </div>



                        <!-- LOCATION -->

                        <div class="col-lg-4">

                            <label class="form-label">
                                Location
                            </label>

                            <input
                                class="form-control"
                                type="text"
                                name="location_text"
                                id="location_text"
                                placeholder="West London"
                                value="<?= e($locationText) ?>"
                                required
                            >

                        </div>



                        <!-- RADIUS -->

                        <div class="col-md-3">

                            <label class="form-label">
                                Radius
                            </label>

                            <div class="input-group">

                                <input
                                    class="form-control"
                                    type="number"
                                    name="radius_miles"
                                    id="radius_miles"
                                    min="1"
                                    max="100"
                                    step="1"
                                    value="<?= e($radiusMiles) ?>"
                                >

                                <span class="input-group-text">
                                    miles
                                </span>

                            </div>

                        </div>



                        <!-- MIN RATING -->

                        <div class="col-md-3">

                            <label class="form-label">
                                Min Rating
                            </label>

                            <input
                                class="form-control"
                                type="number"
                                name="min_rating"
                                id="min_rating"
                                step="0.1"
                                min="0"
                                max="5"
                                placeholder="4.0"
                                value="<?= e($minRating) ?>"
                            >

                        </div>



                        <!-- MIN REVIEWS -->

                        <div class="col-md-3">

                            <label class="form-label">
                                Min Reviews
                            </label>

                            <input
                                class="form-control"
                                type="number"
                                name="min_reviews"
                                id="min_reviews"
                                min="0"
                                placeholder="10"
                                value="<?= e($minReviews) ?>"
                            >

                        </div>



                        <!-- WEBSITE FILTER -->

                        <div class="col-md-3">

                            <label class="form-label">
                                Website
                            </label>

                            <select
                                class="form-select"
                                name="website_filter"
                                id="website_filter"
                            >

                                <option
                                    value="any"
                                    <?= $websiteFilter === 'any'
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Any
                                </option>


                                <option
                                    value="none"
                                    <?= $websiteFilter === 'none'
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    No Website
                                </option>


                                <option
                                    value="existing"
                                    <?= $websiteFilter === 'existing'
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Has Website
                                </option>

                            </select>

                        </div>



                        <!-- SEARCH BUTTON -->

                        <div class="col-12">

                            <button
                                type="submit"
                                class="btn btn-dark btn-lg"
                            >
                                Search Google Places
                            </button>

                        </div>


                    </div>

                </form>

            </div>

        </div>



        <!-- ============================================================
             RESULTS
        ============================================================ -->

        <?php if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && !$error
        ): ?>


            <div class="card border-0 shadow-sm">


                <!-- HEADER -->

                <div
                    class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2"
                >

                    <div>

                        <strong>
                            Google Places Results
                        </strong>

                        <div class="small text-muted">

                            <?= number_format(count($results)) ?>

                            qualifying business<?= count($results) === 1
                                ? ''
                                : 'es'
                            ?>

                        </div>

                    </div>


                    <?php if (!empty($results)): ?>

                        <form
                            method="post"
                            action="import_google_leads.php"
                            class="m-0"
                        >

                            <?= csrf_field() ?>

                            <button
                                type="submit"
                                class="btn btn-success"
                            >
                                Import All Results
                            </button>

                        </form>

                    <?php endif; ?>


                </div>



                <!-- TABLE -->

                <div class="table-responsive">

                    <table
                        class="table table-hover align-middle mb-0"
                    >

                        <thead>

                            <tr>

                                <th>
                                    Business
                                </th>

                                <th>
                                    Phone
                                </th>

                                <th>
                                    Website
                                </th>

                                <th>
                                    Rating
                                </th>

                                <th>
                                    Reviews
                                </th>

                                <th>
                                    Score
                                </th>

                                <th>
                                    Priority
                                </th>

                                <th>
                                    Maps
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php if (empty($results)): ?>


                            <tr>

                                <td
                                    colspan="8"
                                    class="text-center py-5 text-muted"
                                >

                                    No businesses matched the filters.

                                    <br><br>

                                    Try lowering the minimum rating/reviews
                                    or changing Website to "Any".

                                </td>

                            </tr>


                        <?php else: ?>


                            <?php foreach ($results as $lead): ?>


                                <?php

                                $priorityClass = 'secondary';

                                if (
                                    $lead['priority'] === 'hot'
                                ) {

                                    $priorityClass = 'danger';

                                } elseif (
                                    $lead['priority'] === 'high'
                                ) {

                                    $priorityClass = 'warning';

                                } elseif (
                                    $lead['priority'] === 'medium'
                                ) {

                                    $priorityClass = 'primary';
                                }

                                ?>


                                <tr>


                                    <!-- BUSINESS -->

                                    <td>

                                        <strong>

                                            <?= e(
                                                $lead['business_name']
                                            ) ?>

                                        </strong>


                                        <div class="small text-muted">

                                            <?= e(
                                                isset(
                                                    $lead['address_line1']
                                                )
                                                    ? $lead['address_line1']
                                                    : ''
                                            ) ?>

                                        </div>


                                        <?php if (
                                            !empty(
                                                $lead['business_type']
                                            )
                                        ): ?>

                                            <div class="small text-muted">

                                                <?= e(
                                                    $lead['business_type']
                                                ) ?>

                                            </div>

                                        <?php endif; ?>

                                    </td>



                                    <!-- PHONE -->

                                    <td>

                                        <?php if (
                                            !empty($lead['phone'])
                                        ): ?>

                                            <a
                                                href="tel:<?= e(
                                                    $lead['phone']
                                                ) ?>"
                                            >

                                                <?= e(
                                                    $lead['phone']
                                                ) ?>

                                            </a>

                                        <?php else: ?>

                                            —

                                        <?php endif; ?>

                                    </td>



                                    <!-- WEBSITE -->

                                    <td>

                                        <?php if (
                                            empty(
                                                $lead['website_url']
                                            )
                                        ): ?>

                                            <span
                                                class="badge text-bg-danger"
                                            >
                                                NO WEBSITE
                                            </span>

                                        <?php else: ?>

                                            <span
                                                class="badge text-bg-success"
                                            >
                                                WEBSITE
                                            </span>

                                            <div class="mt-1">

                                                <a
                                                    href="<?= e(
                                                        $lead[
                                                            'website_url'
                                                        ]
                                                    ) ?>"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                >
                                                    Visit
                                                </a>

                                            </div>

                                        <?php endif; ?>

                                    </td>



                                    <!-- RATING -->

                                    <td>

                                        <?php if (
                                            isset($lead['rating'])
                                            && $lead['rating'] !== null
                                        ): ?>

                                            <strong>

                                                <?= number_format(
                                                    (float)$lead[
                                                        'rating'
                                                    ],
                                                    1
                                                ) ?>

                                            </strong>

                                            ★

                                        <?php else: ?>

                                            —

                                        <?php endif; ?>

                                    </td>



                                    <!-- REVIEWS -->

                                    <td>

                                        <?= number_format(
                                            isset(
                                                $lead[
                                                    'review_count'
                                                ]
                                            )
                                                ? (int)$lead[
                                                    'review_count'
                                                ]
                                                : 0
                                        ) ?>

                                    </td>



                                    <!-- SCORE -->

                                    <td>

                                        <strong>

                                            <?= (int)$lead[
                                                'lead_score'
                                            ] ?>/100

                                        </strong>

                                    </td>



                                    <!-- PRIORITY -->

                                    <td>

                                        <span
                                            class="badge text-bg-<?= e(
                                                $priorityClass
                                            ) ?>"
                                        >

                                            <?= e(
                                                strtoupper(
                                                    $lead['priority']
                                                )
                                            ) ?>

                                        </span>

                                    </td>



                                    <!-- MAPS -->

                                    <td>

                                        <?php if (
                                            !empty(
                                                $lead[
                                                    'google_maps_url'
                                                ]
                                            )
                                        ): ?>

                                            <a
                                                href="<?= e(
                                                    $lead[
                                                        'google_maps_url'
                                                    ]
                                                ) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="btn btn-sm btn-outline-dark"
                                            >
                                                Maps
                                            </a>

                                        <?php else: ?>

                                            —

                                        <?php endif; ?>

                                    </td>


                                </tr>


                            <?php endforeach; ?>


                        <?php endif; ?>


                        </tbody>

                    </table>

                </div>

            </div>


        <?php endif; ?>


    </div>

</div>



<!-- ============================================================
     CAMPAIGN AUTO-FILL
============================================================ -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        var campaignSelect =
            document.getElementById('campaign_id');

        if (!campaignSelect) {
            return;
        }


        campaignSelect.addEventListener(
            'change',
            function () {

                var option =
                    campaignSelect.options[
                        campaignSelect.selectedIndex
                    ];

                if (
                    !option
                    || option.value === '0'
                    || option.value === ''
                ) {
                    return;
                }


                document.getElementById(
                    'business_type'
                ).value =
                    option.getAttribute(
                        'data-type'
                    ) || '';


                document.getElementById(
                    'location_text'
                ).value =
                    option.getAttribute(
                        'data-location'
                    ) || '';


                document.getElementById(
                    'radius_miles'
                ).value =
                    option.getAttribute(
                        'data-radius'
                    ) || '10';


                document.getElementById(
                    'min_rating'
                ).value =
                    option.getAttribute(
                        'data-rating'
                    ) || '';


                document.getElementById(
                    'min_reviews'
                ).value =
                    option.getAttribute(
                        'data-reviews'
                    ) || '';


                var website =
                    option.getAttribute(
                        'data-website'
                    ) || 'any';


                if (
                    website !== 'any'
                    && website !== 'none'
                    && website !== 'existing'
                ) {
                    website = 'any';
                }


                document.getElementById(
                    'website_filter'
                ).value = website;

            }
        );

    }
);

</script>


<?php require __DIR__ . '/includes/footer.php'; ?>