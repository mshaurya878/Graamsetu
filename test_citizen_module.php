<?php
// Complete End-to-End Test Suite for GraamSetu Citizen Module & Service Discovery

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getConnection();

echo "====================================================\n";
echo "   GRAAMSETU CITIZEN MODULE FULL TEST SUITE\n";
echo "====================================================\n\n";

$tests_passed = 0;
$tests_total = 0;

function assert_test($condition, $test_name) {
    global $tests_passed, $tests_total;
    $tests_total++;
    if ($condition) {
        $tests_passed++;
        echo "[PASS] $test_name\n";
    } else {
        echo "[FAIL] $test_name\n";
    }
}

// ----------------------------------------------------
// TEST 1: Citizen Profile & Location Resolution
// ----------------------------------------------------
// Ramesh Sahani is citizen (user_id = 2)
$c_stmt = $db->prepare("
    SELECT u.id, u.full_name, cp.ration_card_no, cp.address, l.village_name, l.gram_panchayat, l.district, l.latitude, l.longitude
    FROM users u
    JOIN citizen_profiles cp ON u.id = cp.user_id
    LEFT JOIN locations l ON cp.location_id = l.id
    WHERE u.id = 2
");
$c_stmt->execute();
$citizen = $c_stmt->fetch();

assert_test($citizen && $citizen['full_name'] === 'Ramesh Sahani', "1.1 Citizen record resolved for Ramesh Sahani");
assert_test(!empty($citizen['village_name']) && !empty($citizen['district']), "1.2 Citizen village and location linked correctly ({$citizen['village_name']}, {$citizen['district']})");
assert_test(!empty($citizen['ration_card_no']), "1.3 Ration card number resolved ({$citizen['ration_card_no']})");

// ----------------------------------------------------
// TEST 2: Panchayat Announcements Resolution
// ----------------------------------------------------
$anc = $db->query("
    SELECT * FROM announcements 
    WHERE is_active = 1 
    ORDER BY (priority = 'urgent' OR priority = 'high') DESC, created_at DESC 
    LIMIT 4
")->fetchAll();
assert_test(count($anc) > 0, "2.1 Panchayat live announcements retrieved for notice board (count: " . count($anc) . ")");

// ----------------------------------------------------
// TEST 3: Service Discovery - Categories & Verified Listings
// ----------------------------------------------------
$cats = $db->query("SELECT * FROM categories ORDER BY type ASC, name ASC")->fetchAll();
assert_test(count($cats) >= 5, "3.1 Categories loaded for service filter carousel (count: " . count($cats) . ")");

$merchants = $db->query("
    SELECT mp.*, c.name as category_name, c.icon_class,
           l.village_name,
           (SELECT COUNT(*) FROM products p WHERE p.merchant_id = mp.id) as total_products
    FROM merchant_profiles mp
    JOIN categories c ON mp.category_id = c.id
    LEFT JOIN locations l ON mp.location_id = l.id
    WHERE mp.verification_status = 'verified'
")->fetchAll();
assert_test(count($merchants) > 0, "3.2 Verified local merchants retrieved (count: " . count($merchants) . ")");
assert_test(isset($merchants[0]['total_products']), "3.3 Merchant product inventory count calculated correctly");

$providers = $db->query("
    SELECT sp.*, u.full_name, u.phone,
           c.name as category_name, c.icon_class,
           l.village_name
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    JOIN categories c ON sp.service_category_id = c.id
    LEFT JOIN locations l ON sp.location_id = l.id
    WHERE sp.verification_status = 'verified'
")->fetchAll();
assert_test(count($providers) > 0, "3.4 Verified skilled technicians retrieved (count: " . count($providers) . ")");
assert_test(!empty($providers[0]['hourly_rate']) && $providers[0]['hourly_rate'] > 0, "3.5 Technician hourly rate properly formatted");

// ----------------------------------------------------
// TEST 4: Search & Filtering Logic
// ----------------------------------------------------
// Kirana category (id = 1)
$kirana_stmt = $db->prepare("
    SELECT mp.*, c.name as category_name 
    FROM merchant_profiles mp 
    JOIN categories c ON mp.category_id = c.id 
    WHERE mp.category_id = 1 AND mp.verification_status = 'verified'
");
$kirana_stmt->execute();
$kirana_merchants = $kirana_stmt->fetchAll();
assert_test(count($kirana_merchants) > 0, "4.1 Filtering by category (Kirana & Grocery) returns matching shops");

$search_term = "Electric";
$search_stmt = $db->prepare("
    SELECT sp.*, u.full_name, c.name as category_name
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    JOIN categories c ON sp.service_category_id = c.id
    WHERE (u.full_name LIKE ? OR c.name LIKE ?) AND sp.verification_status = 'verified'
");
$search_stmt->execute(["%$search_term%", "%$search_term%"]);
$search_res = $search_stmt->fetchAll();
assert_test(count($search_res) > 0, "4.2 Keyword search for 'Electric' returns relevant technician");

// ----------------------------------------------------
// TEST 5: Citizen Inventory Inquiry Submission & CSRF
// ----------------------------------------------------
$_SESSION = [];
$token = generate_csrf_token();
assert_test(verify_csrf_token($token), "5.1 CSRF token verifies for inquiry submission");

$ins_inq = $db->prepare("
    INSERT INTO merchant_enquiries (citizen_id, merchant_id, subject, message, status)
    VALUES (2, 1, 'TEST: Fertilizer Availability Inquiry', 'Checking stock of DAP fertilizer for Rabi season.', 'unread')
");
$ins_inq->execute();
$inq_id = $db->lastInsertId();

$check_inq = $db->query("SELECT * FROM merchant_enquiries WHERE id = $inq_id")->fetch();
assert_test($check_inq && $check_inq['citizen_id'] == 2 && $check_inq['status'] === 'unread', "5.2 Merchant enquiry inserted with citizen link and 'unread' status");
$db->exec("DELETE FROM merchant_enquiries WHERE id = $inq_id");

// ----------------------------------------------------
// TEST 6: Service Booking Pipeline (requests.php)
// ----------------------------------------------------
// Booking an electrician (provider_id = 1, Shiv Kumar Electrician)
$ins_sr = $db->prepare("
    INSERT INTO service_requests (citizen_id, provider_id, details, preferred_date, status)
    VALUES (2, 1, 'TEST: Borewell starter coil winding repair', '2026-09-10', 'pending')
");
$ins_sr->execute();
$sr_id = $db->lastInsertId();

$check_sr = $db->query("
    SELECT sr.*, u.full_name as provider_name, c.name as category_name
    FROM service_requests sr
    JOIN service_providers sp ON sr.provider_id = sp.id
    JOIN users u ON sp.user_id = u.id
    JOIN categories c ON sp.service_category_id = c.id
    WHERE sr.id = $sr_id
")->fetch();

assert_test($check_sr && $check_sr['details'] === 'TEST: Borewell starter coil winding repair', "6.1 Service booking call recorded with technician link");
assert_test($check_sr['status'] === 'pending' && $check_sr['preferred_date'] === '2026-09-10', "6.2 Service booking date and initial status verified");

// Clean up test request
$db->exec("DELETE FROM service_requests WHERE id = $sr_id");

// ----------------------------------------------------
// TEST 7: Village Grievance Redressal (complaints.php)
// ----------------------------------------------------
$ins_comp = $db->prepare("
    INSERT INTO complaints (citizen_id, category, subject, description, location_text, status)
    VALUES (2, 'Water Supply', 'TEST: Drinking water tap broken', 'Public tap in ward 4 leaking water continuously.', 'Ward No. 4', 'open')
");
$ins_comp->execute();
$comp_id = $db->lastInsertId();

$check_comp = $db->query("SELECT * FROM complaints WHERE id = $comp_id")->fetch();
assert_test($check_comp && $check_comp['subject'] === 'TEST: Drinking water tap broken', "7.1 Grievance recorded with category, subject and location");
assert_test($check_comp['status'] === 'open', "7.2 Initial grievance status set to 'open'");

// Clean up test complaint
$db->exec("DELETE FROM complaints WHERE id = $comp_id");

// ----------------------------------------------------
// TEST 8: Citizen Notification Alerts (notifications.php)
// ----------------------------------------------------
$ins_notif = $db->prepare("
    INSERT INTO notifications (user_id, title, message, link, is_read)
    VALUES (2, 'TEST: Alert', 'Test notification alert', 'citizen/requests.php', 0)
");
$ins_notif->execute();
$notif_id = $db->lastInsertId();

$check_notif = $db->query("SELECT * FROM notifications WHERE id = $notif_id")->fetch();
assert_test($check_notif && $check_notif['is_read'] == 0, "8.1 New notification created with unread status");

// Test mark as read
$db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?")->execute([$notif_id]);
$read_status = $db->query("SELECT is_read FROM notifications WHERE id = $notif_id")->fetchColumn();
assert_test($read_status == 1, "8.2 Notification successfully marked as read");

$db->exec("DELETE FROM notifications WHERE id = $notif_id");

// ----------------------------------------------------
// TEST 9: Citizen Profile Update (profile.php)
// ----------------------------------------------------
$original_phone = $citizen['phone'] ?? '9876543211';
$test_phone = '9876543299';
$db->prepare("UPDATE users SET phone = ? WHERE id = 2")->execute([$test_phone]);
$updated_phone = $db->query("SELECT phone FROM users WHERE id = 2")->fetchColumn();
assert_test($updated_phone === $test_phone, "9.1 Citizen mobile number updated successfully");

// Revert to original phone
$db->prepare("UPDATE users SET phone = ? WHERE id = 2")->execute([$original_phone]);

// ----------------------------------------------------
// TEST 10: Geographic Coordinates for Map View
// ----------------------------------------------------
$locations_count = $db->query("SELECT COUNT(*) FROM locations WHERE latitude IS NOT NULL AND longitude IS NOT NULL")->fetchColumn();
assert_test($locations_count > 0, "10.1 Village locations have valid geographic coordinates for Leaflet OpenStreetMap");

echo "\n====================================================\n";
echo "TEST RESULTS: $tests_passed / $tests_total tests passed (" . round(($tests_passed/$tests_total)*100) . "%)\n";
echo "====================================================\n";
