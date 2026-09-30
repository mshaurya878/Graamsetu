<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();

echo "--- merchant_profiles ---\n";
foreach ($db->query("DESCRIBE merchant_profiles") as $r) {
    echo $r['Field'] . " (" . $r['Type'] . ")\n";
}

echo "\n--- service_providers ---\n";
foreach ($db->query("DESCRIBE service_providers") as $r) {
    echo $r['Field'] . " (" . $r['Type'] . ")\n";
}
