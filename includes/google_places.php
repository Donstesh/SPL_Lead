<?php
declare(strict_types=1);

/**
 * SPL Lead Intelligence
 * Google Places API (New) connector
 */

function google_places_api_key(): string
{
    global $config;

    $key = trim((string)($config['google']['places_api_key'] ?? ''));

    if ($key === '') {
        throw new RuntimeException(
            'Google Places API key is missing. Add google.places_api_key to config.php.'
        );
    }

    return $key;
}

/**
 * Search Google Places (New) Text Search endpoint.
 *
 * @return array{places: array, nextPageToken: ?string}
 */
function google_places_text_search(
    string $textQuery,
    ?string $pageToken = null,
    int $pageSize = 20
): array {
    $apiKey = google_places_api_key();

    $endpoint = 'https://places.googleapis.com/v1/places:searchText';

    $pageSize = max(1, min(20, $pageSize));

    $payload = [
        'textQuery' => $textQuery,
        'pageSize' => $pageSize,
        'languageCode' => 'en',
        'regionCode' => 'GB',
    ];

    if ($pageToken !== null && $pageToken !== '') {
        $payload['pageToken'] = $pageToken;
    }

    /*
     * Important:
     * Google bills according to fields requested.
     * We request the business intelligence required by SPL Lead Intelligence.
     */
    $fieldMask = implode(',', [
        'places.id',
        'places.displayName',
        'places.formattedAddress',
        'places.addressComponents',
        'places.location',
        'places.primaryType',
        'places.primaryTypeDisplayName',
        'places.googleMapsUri',
        'places.businessStatus',
        'places.nationalPhoneNumber',
        'places.rating',
        'places.userRatingCount',
        'places.websiteUri',
        'nextPageToken',
    ]);

    $ch = curl_init($endpoint);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Goog-Api-Key: ' . $apiKey,
            'X-Goog-FieldMask: ' . $fieldMask,
        ],
    ]);

    $body = curl_exec($ch);

    if ($body === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Google Places connection failed: ' . $error);
    }

    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($body, true);

    if (!is_array($decoded)) {
        throw new RuntimeException('Google Places returned an invalid JSON response.');
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $message = $decoded['error']['message']
            ?? ('Google Places returned HTTP ' . $httpCode);

        throw new RuntimeException($message);
    }

    log_google_api_usage('places_text_search');

    return [
        'places' => $decoded['places'] ?? [],
        'nextPageToken' => $decoded['nextPageToken'] ?? null,
    ];
}

function log_google_api_usage(string $endpoint): void
{
    try {
        $stmt = db()->prepare("
            INSERT INTO api_usage
                (provider, endpoint, request_count, usage_date)
            VALUES
                ('google_places', ?, 1, CURDATE())
            ON DUPLICATE KEY UPDATE
                request_count = request_count + 1
        ");

        $stmt->execute([$endpoint]);
    } catch (Throwable $e) {
        // API usage logging must never break the actual lead search.
    }
}

/**
 * Extract an address component by Google component type.
 */
function google_address_component(array $place, string $type): ?string
{
    foreach (($place['addressComponents'] ?? []) as $component) {
        $types = $component['types'] ?? [];

        if (in_array($type, $types, true)) {
            return $component['longText']
                ?? $component['shortText']
                ?? null;
        }
    }

    return null;
}

function google_place_to_lead(array $place): array
{
    $website = trim((string)($place['websiteUri'] ?? ''));

    $city =
        google_address_component($place, 'postal_town')
        ?? google_address_component($place, 'locality')
        ?? google_address_component($place, 'administrative_area_level_2');

    $county =
        google_address_component($place, 'administrative_area_level_2')
        ?? google_address_component($place, 'administrative_area_level_1');

    return [
        'external_provider' => 'google_places',
        'external_id' => $place['id'] ?? null,
        'business_name' => $place['displayName']['text'] ?? 'Unknown Business',
        'business_type' =>
            $place['primaryTypeDisplayName']['text']
            ?? $place['primaryType']
            ?? null,
        'address_line1' => $place['formattedAddress'] ?? null,
        'city' => $city,
        'county' => $county,
        'postcode' => google_address_component($place, 'postal_code'),
        'country' =>
            google_address_component($place, 'country')
            ?? 'United Kingdom',
        'latitude' => $place['location']['latitude'] ?? null,
        'longitude' => $place['location']['longitude'] ?? null,
        'phone' => $place['nationalPhoneNumber'] ?? null,
        'website_url' => $website !== '' ? $website : null,
        'google_maps_url' => $place['googleMapsUri'] ?? null,
        'rating' => isset($place['rating']) ? (float)$place['rating'] : null,
        'review_count' => isset($place['userRatingCount'])
            ? (int)$place['userRatingCount']
            : 0,
        'website_status' => $website === '' ? 'none' : 'found',
    ];
}

function calculate_initial_lead_score(array $lead): array
{
    $score = 0;

    if (($lead['website_status'] ?? '') === 'none') {
        $score += 50;
    }

    $reviews = (int)($lead['review_count'] ?? 0);
    $rating = (float)($lead['rating'] ?? 0);

    if ($reviews >= 100) {
        $score += 25;
    } elseif ($reviews >= 50) {
        $score += 20;
    } elseif ($reviews >= 20) {
        $score += 15;
    } elseif ($reviews >= 5) {
        $score += 8;
    }

    if ($rating >= 4.5) {
        $score += 15;
    } elseif ($rating >= 4.0) {
        $score += 10;
    } elseif ($rating >= 3.5) {
        $score += 5;
    }

    if (!empty($lead['phone'])) {
        $score += 10;
    }

    $score = min(100, $score);

    $priority =
        $score >= 80 ? 'hot'
        : ($score >= 60 ? 'high'
        : ($score >= 40 ? 'medium' : 'low'));

    return [
        'score' => $score,
        'priority' => $priority,
    ];
}
