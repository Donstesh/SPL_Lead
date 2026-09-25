<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

echo '<pre>';

echo 'bootstrap loaded OK' . "\n\n";

echo 'google.places_api_key present: ';
echo !empty($config['google']['places_api_key']) ? 'YES' : 'NO';
echo "\n";

echo 'length: ';
echo strlen($config['google']['places_api_key'] ?? '');
echo "\n";

echo 'prefix: ';
echo substr($config['google']['places_api_key'] ?? '', 0, 6);
echo "\n\n";

try {
    require_once __DIR__ . '/includes/google_places.php';

    $key = google_places_api_key();

    echo 'google_places_api_key() returned: YES' . "\n";
    echo 'returned length: ' . strlen($key) . "\n";
    echo 'returned prefix: ' . substr($key, 0, 6) . "\n";

} catch (Throwable $e) {
    echo 'google_places_api_key() FAILED: ' . $e->getMessage() . "\n";
}

echo '</pre>';