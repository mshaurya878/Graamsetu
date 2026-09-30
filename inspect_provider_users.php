<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();

echo "--- service_providers rows ---\n";
foreach ($db->query("SELECT sp.*, u.full_name, u.email FROM service_providers sp JOIN users u ON sp.user_id = u.id") as $r) {
    echo "SP ID: {$r['id']}, User ID: {$r['user_id']}, Name: {$r['full_name']}, Email: {$r['email']}\n";
}
