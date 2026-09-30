<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$q = trim($_GET['q'] ?? '');
$db = Database::getConnection();

if (strlen($q) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$like = "%$q%";

// Search Merchants
$m_stmt = $db->prepare("
    SELECT mp.id, mp.business_name as title, 'merchant' as type, mp.address as details, c.name as category 
    FROM merchant_profiles mp 
    JOIN categories c ON mp.category_id = c.id 
    WHERE mp.verification_status = 'verified' AND (mp.business_name LIKE ? OR mp.address LIKE ?)
    LIMIT 5
");
$m_stmt->execute([$like, $like]);
$merchants = $m_stmt->fetchAll();

// Search Providers
$p_stmt = $db->prepare("
    SELECT sp.id, c.name as title, 'provider' as type, u.full_name as details, c.name as category 
    FROM service_providers sp 
    JOIN users u ON sp.user_id = u.id 
    JOIN categories c ON sp.service_category_id = c.id 
    WHERE sp.verification_status = 'verified' AND (c.name LIKE ? OR u.full_name LIKE ? OR sp.address LIKE ?)
    LIMIT 5
");
$p_stmt->execute([$like, $like, $like]);
$providers = $p_stmt->fetchAll();

echo json_encode([
    'results' => array_merge($merchants, $providers)
]);
