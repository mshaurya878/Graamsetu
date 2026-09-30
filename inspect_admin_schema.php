<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();

echo "--- announcements ---\n";
foreach ($db->query("DESCRIBE announcements") as $r) {
    echo $r['Field'] . " (" . $r['Type'] . ")\n";
}

echo "\n--- categories ---\n";
foreach ($db->query("DESCRIBE categories") as $r) {
    echo $r['Field'] . " (" . $r['Type'] . ")\n";
}

echo "\n--- admin_logs ---\n";
foreach ($db->query("DESCRIBE admin_logs") as $r) {
    echo $r['Field'] . " (" . $r['Type'] . ")\n";
}
