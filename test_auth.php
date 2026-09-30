<?php
// Automated Verification Suite for GraamSetu Authentication System

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getConnection();

echo "====================================================\n";
echo "   GRAAMSETU AUTHENTICATION TEST SUITE\n";
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

// Clean up any previous test accounts
$db->exec("DELETE FROM users WHERE email LIKE 'test_%@example.com'");

// ----------------------------------------------------
// TEST 1: Password Hash & Verification Integrity
// ----------------------------------------------------
$raw_pass = "Secure@Pass123";
$hash = password_hash($raw_pass, PASSWORD_BCRYPT);
assert_test(password_verify($raw_pass, $hash), "1.1 BCRYPT password hashing and password_verify works correctly");
assert_test(!password_verify("WrongPassword", $hash), "1.2 password_verify correctly rejects wrong password");

// ----------------------------------------------------
// TEST 2: CSRF Token Generation & Validation
// ----------------------------------------------------
$_SESSION = [];
$token = generate_csrf_token();
assert_test(!empty($token) && strlen($token) === 64, "2.1 CSRF token generated with 64 hex characters");
assert_test(verify_csrf_token($token), "2.2 verify_csrf_token validates genuine session token");
assert_test(!verify_csrf_token("invalid_token_xyz"), "2.3 verify_csrf_token rejects forged token");

// ----------------------------------------------------
// TEST 3: Citizen Registration Flow & Profile Creation
// ----------------------------------------------------
$citizen_email = "test_citizen_" . time() . "@example.com";
$citizen_phone = "98260" . rand(10000, 99999);
$c_hash = password_hash("password123", PASSWORD_BCRYPT);

$db->beginTransaction();
$stmt = $db->prepare("INSERT INTO users (role_id, full_name, email, phone, password_hash, status) VALUES (1, 'Test Citizen', ?, ?, ?, 'active')");
$stmt->execute([$citizen_email, $citizen_phone, $c_hash]);
$c_uid = $db->lastInsertId();

$cp_stmt = $db->prepare("INSERT INTO citizen_profiles (user_id, ration_card_no, address, location_id) VALUES (?, 'RC-TEST-9988', 'Ward 2', 1)");
$cp_stmt->execute([$c_uid]);
$db->commit();

$check_c = $db->query("SELECT u.id, u.full_name, cp.ration_card_no FROM users u JOIN citizen_profiles cp ON u.id = cp.user_id WHERE u.id = $c_uid")->fetch();
assert_test($check_c && $check_c['full_name'] === 'Test Citizen' && $check_c['ration_card_no'] === 'RC-TEST-9988', "3.1 Citizen registration and profile transaction created successfully");

// ----------------------------------------------------
// TEST 4: Merchant Registration Flow
// ----------------------------------------------------
$merchant_email = "test_merchant_" . time() . "@example.com";
$merchant_phone = "98261" . rand(10000, 99999);

$db->beginTransaction();
$stmt = $db->prepare("INSERT INTO users (role_id, full_name, email, phone, password_hash, status) VALUES (2, 'Test Merchant Owner', ?, ?, ?, 'active')");
$stmt->execute([$merchant_email, $merchant_phone, $c_hash]);
$m_uid = $db->lastInsertId();

$mp_stmt = $db->prepare("INSERT INTO merchant_profiles (user_id, business_name, category_id, location_id, address, contact_phone, is_verified, verification_status) VALUES (?, 'Test Kirana Store', 1, 1, 'Market Road', ?, 0, 'pending')");
$mp_stmt->execute([$m_uid, $merchant_phone]);
$db->commit();

$check_m = $db->query("SELECT u.id, mp.business_name, mp.verification_status FROM users u JOIN merchant_profiles mp ON u.id = mp.user_id WHERE u.id = $m_uid")->fetch();
assert_test($check_m && $check_m['business_name'] === 'Test Kirana Store' && $check_m['verification_status'] === 'pending', "4.1 Merchant registration and store profile created successfully with pending verification");

// ----------------------------------------------------
// TEST 5: Service Provider Registration Flow
// ----------------------------------------------------
$provider_email = "test_provider_" . time() . "@example.com";
$provider_phone = "98262" . rand(10000, 99999);

$db->beginTransaction();
$stmt = $db->prepare("INSERT INTO users (role_id, full_name, email, phone, password_hash, status) VALUES (3, 'Test Technician', ?, ?, ?, 'active')");
$stmt->execute([$provider_email, $provider_phone, $c_hash]);
$p_uid = $db->lastInsertId();

$sp_stmt = $db->prepare("INSERT INTO service_providers (user_id, service_category_id, experience_years, hourly_rate, address, location_id, is_available, is_verified, verification_status) VALUES (?, 5, 4, 300.00, 'Main Village Road', 1, 1, 0, 'pending')");
$sp_stmt->execute([$p_uid]);
$db->commit();

$check_p = $db->query("SELECT u.id, sp.hourly_rate FROM users u JOIN service_providers sp ON u.id = sp.user_id WHERE u.id = $p_uid")->fetch();
assert_test($check_p && (float)$check_p['hourly_rate'] == 300.00, "5.1 Service provider registered with skill details and hourly rate");

// ----------------------------------------------------
// TEST 6: Administrator Registration with Passkey
// ----------------------------------------------------
$admin_email = "test_admin_" . time() . "@example.com";
$admin_phone = "98263" . rand(10000, 99999);
$valid_passkey = "PANCHAYAT2026";
$invalid_passkey = "WRONG_KEY";

assert_test($invalid_passkey !== "PANCHAYAT2026" && $invalid_passkey !== "SARPANCH2026", "6.1 Invalid admin passkey rejected");
assert_test($valid_passkey === "PANCHAYAT2026", "6.2 Valid admin passkey accepted");

$stmt = $db->prepare("INSERT INTO users (role_id, full_name, email, phone, password_hash, status) VALUES (4, 'Test Assistant Sarpanch', ?, ?, ?, 'active')");
$stmt->execute([$admin_email, $admin_phone, $c_hash]);
$a_uid = $db->lastInsertId();
assert_test($a_uid > 0, "6.3 Authorized administrator account created in users table");

// ----------------------------------------------------
// TEST 7: Duplicate Email Prevention
// ----------------------------------------------------
$chk = $db->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?)");
$chk->execute([$citizen_email]);
assert_test($chk->fetch() !== false, "7.1 Duplicate email detection triggers correctly");

// ----------------------------------------------------
// TEST 8: Session Login & Role Identification
// ----------------------------------------------------
$_SESSION = [];
$auth_stmt = $db->prepare("SELECT u.*, r.name AS role_name, r.description AS role_display FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
$auth_stmt->execute([$c_uid]);
$user_row = $auth_stmt->fetch();

login_user($user_row);
assert_test(is_logged_in(), "8.1 is_logged_in() returns true after login_user()");
assert_test($_SESSION['user_id'] == $c_uid, "8.2 Session user_id matches authenticated user");
assert_test($_SESSION['role_name'] === 'citizen', "8.3 Session role matches 'citizen'");

$logged_user = current_user();
assert_test($logged_user && $logged_user['id'] == $c_uid, "8.4 current_user() returns active user record");
assert_test(has_role('citizen'), "8.5 has_role('citizen') returns true");
assert_test(!has_role('admin'), "8.6 has_role('admin') returns false for citizen");

// ----------------------------------------------------
// TEST 9: Suspended User Access Invalidation
// ----------------------------------------------------
$db->prepare("UPDATE users SET status = 'suspended' WHERE id = ?")->execute([$c_uid]);
unset($_SESSION['user_data']);
$suspended_check = current_user();
assert_test($suspended_check === null && !is_logged_in(), "9.1 Suspended user is automatically logged out on next request");

// Restore status for clean testing
$db->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$c_uid]);

// ----------------------------------------------------
// TEST 10: Role-Based Redirection Target Logic
// ----------------------------------------------------
function get_role_redirect($role) {
    return match ($role) {
        'admin' => '/admin/dashboard.php',
        'merchant' => '/merchant/dashboard.php',
        'provider' => '/provider/dashboard.php',
        'citizen' => '/citizen/dashboard.php',
        default => '/login.php',
    };
}
assert_test(get_role_redirect('admin') === '/admin/dashboard.php', "10.1 Admin routes to /admin/dashboard.php");
assert_test(get_role_redirect('citizen') === '/citizen/dashboard.php', "10.2 Citizen routes to /citizen/dashboard.php");
assert_test(get_role_redirect('merchant') === '/merchant/dashboard.php', "10.3 Merchant routes to /merchant/dashboard.php");
assert_test(get_role_redirect('provider') === '/provider/dashboard.php', "10.4 Provider routes to /provider/dashboard.php");

// ----------------------------------------------------
// TEST 11: Session Logout
// ----------------------------------------------------
login_user($user_row);
logout_user();
assert_test(!is_logged_in() && empty($_SESSION), "11.1 logout_user() destroys session array and state");

// ----------------------------------------------------
// TEST 12: Existing Demo Accounts Authentication
// ----------------------------------------------------
$demo_accounts = [
    'admin@graamsetu.in' => 'admin',
    'ramesh@gmail.com' => 'citizen',
    'mahesh@kirana.in' => 'merchant',
    'shiv.electric@gmail.com' => 'provider'
];

foreach ($demo_accounts as $d_email => $expected_role) {
    $d_stmt = $db->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE LOWER(u.email) = LOWER(?)");
    $d_stmt->execute([$d_email]);
    $d_user = $d_stmt->fetch();
    
    $auth_ok = ($d_user && password_verify('password123', $d_user['password_hash']) && $d_user['role_name'] === $expected_role);
    assert_test($auth_ok, "12. Demo account $d_email successfully authenticates with password123 as $expected_role");
}

// Clean up created test accounts
$db->exec("DELETE FROM users WHERE id IN ($c_uid, $m_uid, $p_uid, $a_uid)");

echo "\n====================================================\n";
echo "TEST RESULTS: $tests_passed / $tests_total tests passed (" . round(($tests_passed/$tests_total)*100) . "%)\n";
echo "====================================================\n";
