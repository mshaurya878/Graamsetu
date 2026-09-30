<?php
// Verify Homepage Queries Against Live Database

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();

echo "====================================================\n";
echo "   HOMEPAGE QUERY VERIFICATION (LIVE DATABASE)\n";
echo "====================================================\n\n";

try {
    // 1. Platform statistics
    $total_citizens = $db->query("SELECT COUNT(*) FROM users WHERE role_id = 1")->fetchColumn();
    $total_merchants = $db->query("SELECT COUNT(*) FROM merchant_profiles WHERE verification_status = 'verified'")->fetchColumn();
    $total_providers = $db->query("SELECT COUNT(*) FROM service_providers WHERE verification_status = 'verified'")->fetchColumn();
    $total_announcements = $db->query("SELECT COUNT(*) FROM announcements WHERE is_active = 1")->fetchColumn();
    echo "[PASS] 1. Platform Statistics Queries:\n";
    echo "       Citizens: $total_citizens, Merchants: $total_merchants, Providers: $total_providers, Announcements: $total_announcements\n\n";

    // 2. Announcements Query (the previously failing query)
    $stmt = $db->query("SELECT * FROM announcements WHERE is_active = 1 ORDER BY (priority = 'urgent' OR priority = 'high') DESC, created_at DESC LIMIT 3");
    $announcements = $stmt->fetchAll();
    echo "[PASS] 2. Corrected Announcements Query executed successfully (" . count($announcements) . " notices retrieved):\n";
    foreach ($announcements as $a) {
        echo "       - [" . strtoupper($a['priority']) . "] {$a['title']} (Audience: {$a['target_audience']})\n";
    }
    echo "\n";

    // 3. Featured Merchants Query
    $featured_merchants = $db->query("
        SELECT mp.*, c.name AS category_name, c.icon_class, c.description AS category_desc
        FROM merchant_profiles mp 
        JOIN categories c ON mp.category_id = c.id 
        WHERE mp.verification_status = 'verified' 
        LIMIT 3
    ")->fetchAll();
    echo "[PASS] 3. Featured Merchants Query executed successfully (" . count($featured_merchants) . " shops retrieved):\n";
    foreach ($featured_merchants as $m) {
        echo "       - {$m['business_name']} ({$m['category_name']})\n";
    }
    echo "\n";

    // 4. Featured Providers Query
    $featured_providers = $db->query("
        SELECT sp.*, u.full_name, u.phone, c.name AS category_name, c.icon_class, c.description AS category_desc
        FROM service_providers sp 
        JOIN users u ON sp.user_id = u.id 
        JOIN categories c ON sp.service_category_id = c.id 
        WHERE sp.verification_status = 'verified' 
        LIMIT 3
    ")->fetchAll();
    echo "[PASS] 4. Featured Providers Query executed successfully (" . count($featured_providers) . " technicians retrieved):\n";
    foreach ($featured_providers as $p) {
        echo "       - {$p['full_name']} ({$p['category_name']}, Rate: ₹{$p['hourly_rate']}/hr)\n";
    }
    echo "\n";

    echo "====================================================\n";
    echo "RESULT: ALL HOMEPAGE QUERIES PASSED WITH ZERO ERRORS\n";
    echo "====================================================\n";
} catch (Exception $e) {
    echo "[FAIL] SQL Error: " . $e->getMessage() . "\n";
    exit(1);
}
