<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();

$stats = [
    'citizens' => (int)$db->query("SELECT COUNT(*) FROM users WHERE role_id = 2")->fetchColumn(),
    'merchants' => (int)$db->query("SELECT COUNT(*) FROM merchant_profiles WHERE verification_status = 'verified'")->fetchColumn(),
    'providers' => (int)$db->query("SELECT COUNT(*) FROM service_providers WHERE verification_status = 'verified'")->fetchColumn(),
    'open_complaints' => (int)$db->query("SELECT COUNT(*) FROM complaints WHERE status = 'open'")->fetchColumn(),
    'active_requests' => (int)$db->query("SELECT COUNT(*) FROM service_requests WHERE status = 'pending'")->fetchColumn()
];

echo json_encode($stats);
