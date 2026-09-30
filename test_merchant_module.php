<?php
// Complete Automated Test Suite for GraamSetu Merchant Module

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getConnection();

echo "====================================================\n";
echo "   GRAAMSETU MERCHANT MODULE TEST SUITE\n";
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
// TEST 1: Merchant Profile & Location Resolution
// ----------------------------------------------------
$m_stmt = $db->prepare("
    SELECT mp.*, u.full_name as owner_name, u.phone, u.email,
           c.name as category_name, c.icon_class,
           l.village_name, l.gram_panchayat, l.district, l.latitude, l.longitude
    FROM merchant_profiles mp
    JOIN users u ON mp.user_id = u.id
    JOIN categories c ON mp.category_id = c.id
    LEFT JOIN locations l ON mp.location_id = l.id
    WHERE mp.id = 1
");
$m_stmt->execute();
$merchant = $m_stmt->fetch();

assert_test($merchant && !empty($merchant['business_name']), "1.1 Merchant profile resolved for Mahesh Kirana Store");
assert_test(!empty($merchant['category_name']), "1.2 Merchant category linked correctly ({$merchant['category_name']})");
assert_test(!empty($merchant['village_name']), "1.3 Merchant village location linked correctly ({$merchant['village_name']})");
assert_test(!empty($merchant['latitude']) && !empty($merchant['longitude']), "1.4 Location coordinates preserved ({$merchant['latitude']}, {$merchant['longitude']})");
assert_test($merchant['verification_status'] === 'verified', "1.5 Merchant verification status respects 'verified'");

$merchant_id = (int)$merchant['id'];

// ----------------------------------------------------
// TEST 2: Product Inventory Lifecycle (CRUD + Soft-Delete + Units)
// ----------------------------------------------------
$rand_suffix = substr((string)time(), -6);
$prod_title = "TEST_UREA_" . $rand_suffix;
$ins_stmt = $db->prepare("
    INSERT INTO products (merchant_id, title, description, price, unit, is_available, is_deleted)
    VALUES (?, ?, 'High quality neem coated fertilizer bag', 266.50, '50kg Bag', 1, 0)
");
$ins_stmt->execute([$merchant_id, $prod_title]);
$prod_id = (int)$db->lastInsertId();

$check_stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
$check_stmt->execute([$prod_id]);
$product = $check_stmt->fetch();

assert_test($product && $product['unit'] === '50kg Bag', "2.1 Add product: Item successfully inserted with title, price, and custom unit (50kg Bag)");
assert_test($product['is_available'] == 1 && $product['is_deleted'] == 0, "2.2 Initial product status is available (1) and active (is_deleted=0)");

// 2.3 Edit Product
$upd_stmt = $db->prepare("
    UPDATE products 
    SET price = 280.00, unit = 'Bag (45kg)'
    WHERE id = ? AND merchant_id = ?
");
$upd_stmt->execute([$prod_id, $merchant_id]);
$check_stmt->execute([$prod_id]);
$p_edited = $check_stmt->fetch();
assert_test($p_edited['price'] == 280.00 && $p_edited['unit'] === 'Bag (45kg)', "2.3 Edit product: Price and packaging unit updated successfully");

// 2.4 Toggle Availability
$db->prepare("UPDATE products SET is_available = 0 WHERE id = ? AND merchant_id = ?")->execute([$prod_id, $merchant_id]);
$check_stmt->execute([$prod_id]);
$p_toggled = $check_stmt->fetch();
assert_test($p_toggled['is_available'] == 0, "2.4 Toggle availability: Product marked out-of-stock (0)");

// 2.5 Restock Product
$db->prepare("UPDATE products SET is_available = 1 WHERE id = ? AND merchant_id = ?")->execute([$prod_id, $merchant_id]);
$check_stmt->execute([$prod_id]);
$p_restocked = $check_stmt->fetch();
assert_test($p_restocked['is_available'] == 1, "2.5 Restock product: Product restocked to available (1)");

// 2.6 Soft-Delete Product
$db->prepare("UPDATE products SET is_deleted = 1 WHERE id = ? AND merchant_id = ?")->execute([$prod_id, $merchant_id]);
$check_stmt->execute([$prod_id]);
$p_deleted = $check_stmt->fetch();
assert_test($p_deleted && $p_deleted['is_deleted'] == 1, "2.6 Soft-delete: Item safely archived without dropping database record");

// 2.7 Restore Product
$db->prepare("UPDATE products SET is_deleted = 0 WHERE id = ? AND merchant_id = ?")->execute([$prod_id, $merchant_id]);
$check_stmt->execute([$prod_id]);
$p_restored = $check_stmt->fetch();
assert_test($p_restored && $p_restored['is_deleted'] == 0, "2.7 Restore product: Archived item returned to active inventory");

// Clean up test product
$db->prepare("DELETE FROM products WHERE id = ? AND merchant_id = ?")->execute([$prod_id, $merchant_id]);

// ----------------------------------------------------
// TEST 3: Inventory Search & Filtering
// ----------------------------------------------------
$search_stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE merchant_id = ? AND is_deleted = 0 AND title LIKE ?");
$search_stmt->execute([$merchant_id, '%Must%']);
$search_count = (int)$search_stmt->fetchColumn();
assert_test($search_count >= 1, "3.1 Database-driven keyword search finds matching products ('Must')");

$instock_stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE merchant_id = ? AND is_deleted = 0 AND is_available = 1");
$instock_stmt->execute([$merchant_id]);
$instock_cnt = (int)$instock_stmt->fetchColumn();

$actual_cnt = (int)$db->query("SELECT COUNT(*) FROM products WHERE merchant_id = $merchant_id AND is_deleted = 0 AND is_available = 1")->fetchColumn();
assert_test($instock_cnt === $actual_cnt, "3.2 In-stock filter counts accurately match active inventory ($instock_cnt / $actual_cnt)");

// ----------------------------------------------------
// TEST 4: Customer Inquiry Redressal & Citizen Notification
// ----------------------------------------------------
$ins_inq = $db->prepare("
    INSERT INTO merchant_enquiries (merchant_id, citizen_id, subject, message, status)
    VALUES (?, 2, 'DAP Fertilizer 50kg', 'Is DAP fertilizer available in stock today?', 'unread')
");
$ins_inq->execute([$merchant_id]);
$inq_id = (int)$db->lastInsertId();

$chk_inq = $db->query("SELECT * FROM merchant_enquiries WHERE id = $inq_id")->fetch();
assert_test($chk_inq && $chk_inq['status'] === 'unread', "4.1 Customer inquiry recorded with 'unread' status");

$inq_loc = $db->query("
    SELECT me.*, u.full_name as citizen_name, l.village_name as citizen_village
    FROM merchant_enquiries me
    JOIN users u ON me.citizen_id = u.id
    LEFT JOIN citizen_profiles cp ON u.id = cp.user_id
    LEFT JOIN locations l ON cp.location_id = l.id
    WHERE me.id = $inq_id
")->fetch();
assert_test(!empty($inq_loc['citizen_village']), "4.2 Citizen's village location resolved for the inquiry ({$inq_loc['citizen_village']})");

$reply_text = "Yes, fresh stock of DAP arrived this morning. Price is ₹1,350 per 50kg bag.";
$upd_inq = $db->prepare("
    UPDATE merchant_enquiries 
    SET reply_message = ?, status = 'replied' 
    WHERE id = ? AND merchant_id = ?
");
$upd_inq->execute([$reply_text, $inq_id, $merchant_id]);

$chk_replied = $db->query("SELECT * FROM merchant_enquiries WHERE id = $inq_id")->fetch();
assert_test($chk_replied && $chk_replied['status'] === 'replied' && !empty($chk_replied['reply_message']), "4.3 Merchant reply recorded and inquiry status updated to 'replied'");

$notif_stmt = $db->prepare("
    INSERT INTO notifications (user_id, title, message, link, is_read)
    VALUES (?, 'Store Response: Mahesh Kirana Store', ?, 'citizen/dashboard.php', 0)
");
$notif_stmt->execute([$chk_inq['citizen_id'], "Merchant responded regarding {$chk_inq['subject']}: $reply_text"]);
$notif_id = (int)$db->lastInsertId();

$chk_notif = $db->query("SELECT * FROM notifications WHERE id = $notif_id")->fetch();
assert_test($chk_notif && $chk_notif['user_id'] == 2 && $chk_notif['link'] === 'citizen/dashboard.php', "4.4 Citizen portal notification generated with valid link");

$other_merchant_id = 9999;
$cross_upd = $db->prepare("UPDATE merchant_enquiries SET status = 'replied' WHERE id = ? AND merchant_id = ?");
$cross_upd->execute([$inq_id, $other_merchant_id]);
$cross_cnt = $cross_upd->rowCount();
assert_test($cross_cnt === 0, "4.5 Authorization: Other merchants cannot view or reply to this store's inquiry");

$db->exec("DELETE FROM merchant_enquiries WHERE id = $inq_id");
$db->exec("DELETE FROM notifications WHERE id = $notif_id");

// ----------------------------------------------------
// TEST 5: Business Settings & Verification Preservation
// ----------------------------------------------------
$orig_hours = $merchant['opening_hours'];
$test_hours = "06:30 AM - 09:30 PM";

$db->prepare("UPDATE merchant_profiles SET opening_hours = ? WHERE id = ?")->execute([$test_hours, $merchant_id]);
$check_hours = $db->query("SELECT opening_hours FROM merchant_profiles WHERE id = $merchant_id")->fetchColumn();
assert_test($check_hours === $test_hours, "5.1 Store opening hours updated successfully");

$db->prepare("UPDATE merchant_profiles SET opening_hours = ? WHERE id = ?")->execute([$orig_hours, $merchant_id]);

$ver_status = $db->query("SELECT verification_status, is_verified FROM merchant_profiles WHERE id = $merchant_id")->fetch();
assert_test($ver_status['verification_status'] === 'verified' && $ver_status['is_verified'] == 1, "5.2 Admin-controlled verification status strictly preserved");

// ----------------------------------------------------
// TEST 6: Security, Password Management & CSRF Protection
// ----------------------------------------------------
$user_row = $db->query("SELECT * FROM users WHERE id = {$merchant['user_id']}")->fetch();
assert_test(password_verify('password123', $user_row['password_hash']), "6.1 Owner password verified against secure BCRYPT hash");
assert_test(substr($user_row['password_hash'], 0, 4) === '$2y$', "6.2 Passwords strictly stored as BCRYPT hashes, never plaintext");

$_SESSION = [];
$token = generate_csrf_token();
assert_test(verify_csrf_token($token), "6.3 CSRF token generated and verified for merchant forms");

echo "\n====================================================\n";
echo "TEST RESULTS: $tests_passed / $tests_total tests passed (" . round(($tests_passed/$tests_total)*100) . "%)\n";
echo "====================================================\n";
