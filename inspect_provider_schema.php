<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();

echo "--- service_providers ---\n";
foreach ($db->query("DESCRIBE service_providers") as $r) {
    echo $r['Field'] . " (" . $r['Type'] . ")\n";
}

echo "\n--- services ---\n";
foreach ($db->query("DESCRIBE services") as $r) {
    echo $r['Field'] . " (" . $r['Type'] . ")\n";
}

echo "\n--- service_requests ---\n";
foreach ($db->query("DESCRIBE service_requests") as $r) {
    echo $r['Field'] . " (" . $r['Type'] . ")\n";
}
