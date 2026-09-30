<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();

// Fetch Verified Merchants with Lat/Lng
$merchants = $db->query("
    SELECT mp.id, mp.business_name, mp.address, mp.latitude, mp.longitude, mp.opening_hours, mp.contact_phone, c.name as category_name, c.icon_class
    FROM merchant_profiles mp
    JOIN categories c ON mp.category_id = c.id
    WHERE mp.verification_status = 'verified' AND mp.latitude IS NOT NULL
")->fetchAll();

// Map features
$features = [];
foreach ($merchants as $m) {
    $features[] = [
        'type' => 'Feature',
        'geometry' => [
            'type' => 'Point',
            'coordinates' => [(float)$m['longitude'], (float)$m['latitude']]
        ],
        'properties' => [
            'id' => $m['id'],
            'title' => $m['business_name'],
            'category' => $m['category_name'],
            'address' => $m['address'],
            'hours' => $m['opening_hours'],
            'phone' => $m['contact_phone'],
            'icon' => $m['icon_class']
        ]
    ];
}

echo json_encode([
    'type' => 'FeatureCollection',
    'features' => $features
]);
