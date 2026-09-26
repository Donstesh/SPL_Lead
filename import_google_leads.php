<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_login();

/*
|--------------------------------------------------------------------------
| IMPORT GOOGLE PLACES RESULTS
|--------------------------------------------------------------------------
|
| Reads the cached results from search.php (stored in the session) and
| inserts them into the leads table.
|
| Dedup is handled by the uniq_external unique index:
|   UNIQUE (external_provider, external_id)
|
| Uses INSERT ... ON DUPLICATE KEY UPDATE so that re-importing the same
| Google Place just refreshes its live fields (rating, reviews, website)
| instead of creating a duplicate row.
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| METHOD GUARD
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('search.php');
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

verify_csrf();


/*
|--------------------------------------------------------------------------
| LOAD SESSION DATA
|--------------------------------------------------------------------------
*/

$results = $_SESSION['google_places_results'] ?? [];
$search  = $_SESSION['google_places_search']  ?? [];

if (empty($results) || !is_array($results)) {

    set_flash(
        'error',
        'No Google Places results to import. Please run a search first.'
    );

    redirect('search.php');
}


/*
|--------------------------------------------------------------------------
| PREPARE CONTEXT
|--------------------------------------------------------------------------
*/

$campaignId = isset($search['campaign_id'])
    ? (int)$search['campaign_id']
    : 0;

$campaignId = $campaignId > 0 ? $campaignId : null;

$userId = (int)(user()['id'] ?? 0);

$imported = 0;
$updated  = 0;
$skipped  = 0;
$errors   = [];


/*
|--------------------------------------------------------------------------
| INSERT / UPDATE PREPARED STATEMENT
|--------------------------------------------------------------------------
*/

$sql = "
    INSERT INTO leads
    (
        campaign_id,
        external_provider,
        external_id,
        business_name,
        business_type,
        address_line1,
        address_line2,
        city,
        county,
        postcode,
        country,
        latitude,
        longitude,
        phone,
        public_email,
        website_url,
        google_maps_url,
        rating,
        review_count,
        website_status,
        lead_status,
        priority,
        lead_score,
        source_url,
        assigned_user_id,
        discovered_at,
        created_at,
        updated_at
    )
    VALUES
    (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
        'new', ?, ?, ?, ?, NOW(), NOW(), NOW()
    )
    ON DUPLICATE KEY UPDATE
        business_name     = VALUES(business_name),
        business_type     = VALUES(business_type),
        address_line1     = VALUES(address_line1),
        address_line2     = VALUES(address_line2),
        city              = VALUES(city),
        county            = VALUES(county),
        postcode          = VALUES(postcode),
        country           = VALUES(country),
        latitude          = VALUES(latitude),
        longitude         = VALUES(longitude),
        phone             = VALUES(phone),
        public_email      = VALUES(public_email),
        website_url       = VALUES(website_url),
        google_maps_url   = VALUES(google_maps_url),
        rating            = VALUES(rating),
        review_count      = VALUES(review_count),
        website_status    = VALUES(website_status),
        priority          = VALUES(priority),
        lead_score        = VALUES(lead_score),
        source_url        = VALUES(source_url),
        assigned_user_id  = VALUES(assigned_user_id),
        campaign_id       = COALESCE(leads.campaign_id, VALUES(campaign_id)),
        updated_at        = NOW()
";

$stmt = db()->prepare($sql);


/*
|--------------------------------------------------------------------------
| TRANSACTION
|--------------------------------------------------------------------------
*/

db()->beginTransaction();

try {

    foreach ($results as $lead) {

        /*
        |------------------------------------------------------------------
        | SAFETY: require a Google Place ID
        |------------------------------------------------------------------
        */

        $externalId = trim((string)($lead['external_id'] ?? ''));

        if ($externalId === '') {
            $skipped++;
            continue;
        }


        /*
        |------------------------------------------------------------------
        | SANITISE / COERCE VALUES
        |------------------------------------------------------------------
        */

        $websiteUrl = trim((string)($lead['website_url'] ?? ''));

        // Just the domain, for quick visual reference.
        $sourceUrl = $websiteUrl !== ''
            ? parse_url($websiteUrl, PHP_URL_HOST)
            : null;

        if (is_string($sourceUrl) === false) {
            $sourceUrl = null;
        }


        $params = [
            // campaign_id
            $campaignId,

            // external_provider
            'google_places',

            // external_id
            $externalId,

            // business_name
            trim((string)($lead['business_name'] ?? 'Unknown Business')),

            // business_type
            ($v = trim((string)($lead['business_type'] ?? ''))) !== ''
                ? $v
                : null,

            // address_line1
            ($v = trim((string)($lead['address_line1'] ?? ''))) !== ''
                ? $v
                : null,

            // address_line2
            ($v = trim((string)($lead['address_line2'] ?? ''))) !== ''
                ? $v
                : null,

            // city
            ($v = trim((string)($lead['city'] ?? ''))) !== ''
                ? $v
                : null,

            // county
            ($v = trim((string)($lead['county'] ?? ''))) !== ''
                ? $v
                : null,

            // postcode
            ($v = trim((string)($lead['postcode'] ?? ''))) !== ''
                ? $v
                : null,

            // country (NOT NULL, default United Kingdom)
            ($v = trim((string)($lead['country'] ?? ''))) !== ''
                ? $v
                : 'United Kingdom',

            // latitude
            isset($lead['latitude']) && $lead['latitude'] !== null
                ? (float)$lead['latitude']
                : null,

            // longitude
            isset($lead['longitude']) && $lead['longitude'] !== null
                ? (float)$lead['longitude']
                : null,

            // phone
            ($v = trim((string)($lead['phone'] ?? ''))) !== ''
                ? $v
                : null,

            // public_email (not provided by Places API)
            null,

            // website_url
            $websiteUrl !== '' ? $websiteUrl : null,

            // google_maps_url
            ($v = trim((string)($lead['google_maps_url'] ?? ''))) !== ''
                ? $v
                : null,

            // rating
            isset($lead['rating']) && $lead['rating'] !== null
                ? (float)$lead['rating']
                : null,

            // review_count
            isset($lead['review_count'])
                ? (int)$lead['review_count']
                : 0,

            // website_status
            in_array(
                (string)($lead['website_status'] ?? ''),
                ['unknown', 'none', 'social_only', 'found', 'poor', 'good', 'investigate'],
                true
            )
                ? (string)$lead['website_status']
                : 'unknown',

            // priority
            in_array(
                (string)($lead['priority'] ?? ''),
                ['low', 'medium', 'high', 'hot'],
                true
            )
                ? (string)$lead['priority']
                : 'medium',

            // lead_score
            isset($lead['lead_score'])
                ? max(0, min(100, (int)$lead['lead_score']))
                : 0,

            // source_url (domain only, from website)
            $sourceUrl,

            // assigned_user_id
            $userId > 0 ? $userId : null,
        ];


        try {

            $stmt->execute($params);

            /*
            |--------------------------------------------------------------
            | mysql_affected_rows() semantics under ON DUPLICATE KEY UPDATE:
            |   1 = inserted
            |   2 = updated (row changed)
            |   0 = matched but nothing changed
            |--------------------------------------------------------------
            */

            $affected = $stmt->rowCount();

            if ($affected === 1) {
                $imported++;
            } elseif ($affected === 2) {
                $updated++;
            } else {
                $skipped++;
            }

        } catch (Throwable $rowError) {

            $errors[] = sprintf(
                '%s: %s',
                $lead['business_name'] ?? 'Unknown',
                $rowError->getMessage()
            );

            $skipped++;
        }
    }


    /*
    |----------------------------------------------------------------------
    | COMMIT
    |----------------------------------------------------------------------
    */

    db()->commit();


    /*
    |----------------------------------------------------------------------
    | CLEAR SESSION CACHE
    |----------------------------------------------------------------------
    */

    unset($_SESSION['google_places_results']);
    unset($_SESSION['google_places_search']);

} catch (Throwable $e) {

    if (db()->inTransaction()) {
        db()->rollBack();
    }

    set_flash(
        'error',
        'Import failed: ' . $e->getMessage()
    );

    redirect('search.php');
}


/*
|--------------------------------------------------------------------------
| FLASH SUMMARY
|--------------------------------------------------------------------------
*/

$parts = [];

if ($imported > 0) {
    $parts[] = $imported . ' new lead' . ($imported === 1 ? '' : 's') . ' imported';
}

if ($updated > 0) {
    $parts[] = $updated . ' existing lead' . ($updated === 1 ? '' : 's') . ' refreshed';
}

if ($skipped > 0) {
    $parts[] = $skipped . ' skipped';
}

if (empty($parts)) {
    $parts[] = 'Nothing to import';
}

if (!empty($errors)) {
    $parts[] = count($errors) . ' error' . (count($errors) === 1 ? '' : 's');
}

$message = implode(', ', $parts) . '.';

$flashType = !empty($errors) && $imported === 0 && $updated === 0
    ? 'error'
    : 'success';

set_flash($flashType, $message);


/*
|--------------------------------------------------------------------------
| REDIRECT
|--------------------------------------------------------------------------
*/

redirect('leads.php');