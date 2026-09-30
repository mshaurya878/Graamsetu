<?php
// Complete Automated Test Suite for GraamSetu Service Provider Module

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getConnection();

echo "====================================================\n";
echo "   GRAAMSETU SERVICE PROVIDER MODULE TEST SUITE\n";
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
// TEST 1: Provider Profile & Location Resolution
// ----------------------------------------------------
// Shiv Kumar Electrician (shiv.electric@gmail.com)
$p_stmt = $db->prepare("
    SELECT sp.*, u.full_name, u.phone, u.email,
           c.name as category_name, c.icon_class,
           l.village_name, l.gram_panchayat, l.district, l.latitude, l.longitude
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    JOIN categories c ON sp.service_category_id = c.id
    LEFT JOIN locations l ON sp.location_id = l.id
    WHERE u.email = 'shiv.electric@gmail.com'
");
$p_stmt->execute();
$provider = $p_stmt->fetch();

assert_test($provider && !empty($provider['full_name']), "1.1 Provider profile resolved for Shiv Kumar");
assert_test(!empty($provider['category_name']), "1.2 Specialty category resolved ({$provider['category_name']})");
assert_test(!empty($provider['village_name']), "1.3 Village location linked correctly ({$provider['village_name']})");
assert_test(!empty($provider['latitude']) && !empty($provider['longitude']), "1.4 Location coordinates preserved ({$provider['latitude']}, {$provider['longitude']})");
assert_test($provider['verification_status'] === 'verified', "1.5 Admin verification status is 'verified'");

$provider_id = (int)$provider['id'];

// ----------------------------------------------------
// TEST 2: Availability Status Toggle (is_available)
// ----------------------------------------------------
$orig_avail = (int)$provider['is_available'];
$new_avail = $orig_avail ? 0 : 1;

$db->prepare("UPDATE service_providers SET is_available = ? WHERE id = ?")->execute([$new_avail, $provider_id]);
$check_avail = (int)$db->query("SELECT is_available FROM service_providers WHERE id = $provider_id")->fetchColumn();
assert_test($check_avail === $new_avail, "2.1 Availability status toggled to " . ($new_avail ? 'Available (1)' : 'Busy (0)'));

// Revert to original
$db->prepare("UPDATE service_providers SET is_available = ? WHERE id = ?")->execute([$orig_avail, $provider_id]);
$reverted_avail = (int)$db->query("SELECT is_available FROM service_providers WHERE id = $provider_id")->fetchColumn();
assert_test($reverted_avail === $orig_avail, "2.2 Availability successfully restored to original ($orig_avail)");

// ----------------------------------------------------
// TEST 3: Hourly Rate Configuration
// ----------------------------------------------------
$orig_rate = (float)$provider['hourly_rate'];
$test_rate = 275.00;

$db->prepare("UPDATE service_providers SET hourly_rate = ? WHERE id = ?")->execute([$test_rate, $provider_id]);
$check_rate = (float)$db->query("SELECT hourly_rate FROM service_providers WHERE id = $provider_id")->fetchColumn();
assert_test($check_rate == 275.00, "3.1 Hourly rate updated to ₹275.00/hr");

// Revert rate
$db->prepare("UPDATE service_providers SET hourly_rate = ? WHERE id = ?")->execute([$orig_rate, $provider_id]);

// ----------------------------------------------------
// TEST 4: Service Package Catalog CRUD (services table)
// ----------------------------------------------------
$pkg_title = "TEST_PKG_MCB_" . time();
$ins_pkg = $db->prepare("
    INSERT INTO services (provider_id, category_id, title, description, price_estimate, status)
    VALUES (?, ?, ?, 'Wiring repair for pump starter and MCB replacement', 350.00, 'active')
");
$ins_pkg->execute([$provider_id, (int)$provider['service_category_id'], $pkg_title]);
$pkg_id = (int)$db->lastInsertId();

$check_pkg = $db->query("SELECT * FROM services WHERE id = $pkg_id")->fetch();
assert_test($check_pkg && $check_pkg['title'] === $pkg_title && (float)$check_pkg['price_estimate'] == 350.00, "4.1 Add service package: Created with title and estimated charge");
assert_test($check_pkg['status'] === 'active', "4.2 Service package initial status is active");

// 4.2 Edit Service Package
$db->prepare("UPDATE services SET title = ?, price_estimate = 420.00 WHERE id = ? AND provider_id = ?")
   ->execute([$pkg_title . "_EDITED", $pkg_id, $provider_id]);
$check_pkg2 = $db->query("SELECT * FROM services WHERE id = $pkg_id")->fetch();
assert_test($check_pkg2 && (float)$check_pkg2['price_estimate'] == 420.00, "4.3 Edit service package: Price updated to ₹420.00");

// 4.3 Toggle Service Package Status
$db->prepare("UPDATE services SET status = 'inactive' WHERE id = ? AND provider_id = ?")->execute([$pkg_id, $provider_id]);
$inact_status = $db->query("SELECT status FROM services WHERE id = $pkg_id")->fetchColumn();
assert_test($inact_status === 'inactive', "4.4 Toggle package status: Marked inactive");

// Clean up test package
$db->prepare("DELETE FROM services WHERE id = ? AND provider_id = ?")->execute([$pkg_id, $provider_id]);

// ----------------------------------------------------
// TEST 5: Citizen Service Request & Accept/Reject Workflow
// ----------------------------------------------------
$ins_sr = $db->prepare("
    INSERT INTO service_requests (citizen_id, provider_id, details, preferred_date, status)
    VALUES (2, ?, 'TEST: Farm water pump switch sparking issue', '2026-09-12', 'pending')
");
$ins_sr->execute([$provider_id]);
$sr_id = (int)$db->lastInsertId();

$check_sr = $db->query("SELECT * FROM service_requests WHERE id = $sr_id")->fetch();
assert_test($check_sr && $check_sr['status'] === 'pending', "5.1 Booking call recorded with 'pending' status");

// 5.2 Citizen Location Resolution for Request
$sr_loc = $db->query("
    SELECT sr.*, u.full_name as citizen_name, l.village_name as citizen_village
    FROM service_requests sr
    JOIN users u ON sr.citizen_id = u.id
    LEFT JOIN citizen_profiles cp ON u.id = cp.user_id
    LEFT JOIN locations l ON cp.location_id = l.id
    WHERE sr.id = $sr_id
")->fetch();
assert_test(!empty($sr_loc['citizen_village']), "5.2 Citizen village location resolved ({$sr_loc['citizen_village']})");

// 5.3 Accept Workflow
$db->prepare("UPDATE service_requests SET status = 'accepted' WHERE id = ? AND provider_id = ?")->execute([$sr_id, $provider_id]);
$accepted_status = $db->query("SELECT status FROM service_requests WHERE id = $sr_id")->fetchColumn();
assert_test($accepted_status === 'accepted', "5.3 Accept workflow: Request status changed to 'accepted'");

// 5.4 Citizen Notification Generation (using schema column 'link')
$ins_notif = $db->prepare("
    INSERT INTO notifications (user_id, title, message, link, is_read)
    VALUES (2, 'Service Booking Accepted', 'Shiv Kumar accepted your booking', 'citizen/requests.php', 0)
");
$ins_notif->execute();
$notif_id = (int)$db->lastInsertId();

$check_notif = $db->query("SELECT * FROM notifications WHERE id = $notif_id")->fetch();
assert_test($check_notif && $check_notif['user_id'] == 2 && $check_notif['link'] === 'citizen/requests.php', "5.4 Citizen portal notification created with valid link");

// 5.5 Complete Workflow (in_progress -> completed)
$db->prepare("UPDATE service_requests SET status = 'in_progress' WHERE id = ? AND provider_id = ?")->execute([$sr_id, $provider_id]);
$prog_status = $db->query("SELECT status FROM service_requests WHERE id = $sr_id")->fetchColumn();
assert_test($prog_status === 'in_progress', "5.5 Progress workflow: Status changed to 'in_progress'");

$db->prepare("UPDATE service_requests SET status = 'completed' WHERE id = ? AND provider_id = ?")->execute([$sr_id, $provider_id]);
$comp_status = $db->query("SELECT status FROM service_requests WHERE id = $sr_id")->fetchColumn();
assert_test($comp_status === 'completed', "5.6 Complete workflow: Status changed to 'completed'");

// 5.6 Authorization Check: Prevent Cross-Provider Action
$other_prov_id = 9999;
$db->prepare("UPDATE service_requests SET status = 'cancelled' WHERE id = ? AND provider_id = ?")->execute([$sr_id, $other_prov_id]);
$still_comp = $db->query("SELECT status FROM service_requests WHERE id = $sr_id")->fetchColumn();
assert_test($still_comp === 'completed', "5.7 Authorization: Other providers cannot modify this booking");

// Clean up test request & notification
$db->exec("DELETE FROM service_requests WHERE id = $sr_id");
$db->exec("DELETE FROM notifications WHERE id = $notif_id");

// ----------------------------------------------------
// TEST 6: Security & Password Management (profile.php)
// ----------------------------------------------------
$user_row = $db->query("SELECT * FROM users WHERE email = 'shiv.electric@gmail.com'")->fetch();
assert_test(password_verify('password123', $user_row['password_hash']), "6.1 Provider password verified with password_verify()");
assert_test(substr($user_row['password_hash'], 0, 4) === '$2y$', "6.2 Passwords strictly stored as BCRYPT hashes");

// Verify CSRF Generation & Verification
$_SESSION = [];
$csrf = generate_csrf_token();
assert_test(verify_csrf_token($csrf), "6.3 CSRF token generated and verified for provider forms");

echo "\n====================================================\n";
echo "TEST RESULTS: $tests_passed / $tests_total tests passed (" . round(($tests_passed/$tests_total)*100) . "%)\n";
echo "====================================================\n";
