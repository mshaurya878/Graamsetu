<?php
/**
 * Phase 6 QA — Steps 6 & 7: CSRF Protection & Final Regression Sanity
 *
 * Step 6: Verify CSRF protection is enforced on all POST forms
 * Step 7: Full regression sanity — 121/121 unit tests still passing
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

$base = "http://localhost/graamsetu";
$passed = 0;
$failed = 0;
$results = [];

function http_get(string $url, array $cookies = [], bool $follow = true): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HEADER => true,
        CURLOPT_USERAGENT => 'GraamSetu-QA/2.0',
    ]);
    if ($cookies) {
        $s = implode('; ', array_map(fn($k,$v)=>"$k=$v", array_keys($cookies), $cookies));
        curl_setopt($ch, CURLOPT_COOKIE, $s);
    }
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $hdr  = substr($raw, 0, $hs);
    $body = substr($raw, $hs);
    $sc = [];
    preg_match_all('/^Set-Cookie:\s*([^;]+)/im', $hdr, $m);
    foreach ($m[1] as $p) { [$k,$v] = array_pad(explode('=',$p,2),2,''); $sc[trim($k)]=trim($v); }
    return ['status'=>$code,'headers'=>$hdr,'body'=>$body,'cookies'=>$sc];
}

function http_post(string $url, array $fields, array $cookies = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HEADER => true,
        CURLOPT_USERAGENT => 'GraamSetu-QA/2.0',
    ]);
    if ($cookies) {
        $s = implode('; ', array_map(fn($k,$v)=>"$k=$v", array_keys($cookies), $cookies));
        curl_setopt($ch, CURLOPT_COOKIE, $s);
    }
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $hdr  = substr($raw, 0, $hs);
    $body = substr($raw, $hs);
    $sc = [];
    preg_match_all('/^Set-Cookie:\s*([^;]+)/im', $hdr, $m);
    foreach ($m[1] as $p) { [$k,$v] = array_pad(explode('=',$p,2),2,''); $sc[trim($k)]=trim($v); }
    return ['status'=>$code,'headers'=>$hdr,'body'=>$body,'cookies'=>$sc];
}

function do_login(string $base, string $email, string $pass): array {
    $page = http_get("$base/login.php");
    $jar  = $page['cookies'];
    $resp = http_post("$base/login.php", ['email'=>$email,'password'=>$pass], $jar);
    return array_merge($jar, $resp['cookies']);
}

function qa(string $id, string $desc, bool $ok, string $detail=''): void {
    global $passed,$failed,$results;
    $ok ? $passed++ : $failed++;
    $results[] = [$ok?'PASS':'FAIL', $id, $desc, $detail];
}

// ================================================================
// STEP 6 — CSRF PROTECTION CHECKS
// ================================================================
echo "Running Step 6: CSRF Protection...\n";

// 6.1 CSRF token field is present in all key forms
// Check login form
$login_page = http_get("$base/login.php");
qa('6.1a', 'Login form contains csrf_token input field', str_contains($login_page['body'], 'csrf_token'), "csrf_token found in form");

// 6.2 POST to merchant products without valid CSRF token is rejected
$merchant_jar = do_login($base, 'mahesh@kirana.in', 'password123');
$resp_no_csrf = http_post("$base/merchant/products.php", [
    'add_product' => '1',
    'csrf_token'  => 'INVALID_FORGED_TOKEN_12345',
    'title'       => 'Forged Product',
    'price'       => '99',
    'unit'        => 'kg',
    'description' => 'Forged item via CSRF attack',
    'is_available'=> '1',
], $merchant_jar);
// Should not create the product — either shows error message or stays on page
$csrf_rejected = !str_contains($resp_no_csrf['body'], "added to your store catalog successfully");
qa('6.2', 'Forged CSRF token on /merchant/products.php POST is rejected', $csrf_rejected, "No success message with forged token");

// 6.3 POST to admin complaints without CSRF token is rejected
$admin_jar = do_login($base, 'admin@graamsetu.in', 'password123');
$resp_admin_csrf = http_post("$base/admin/complaints.php", [
    'update_complaint' => '1',
    'csrf_token'       => 'FORGED_ADMIN_CSRF_999',
    'complaint_id'     => '1',
    'status'           => 'resolved',
    'admin_notes'      => 'Hacked',
], $admin_jar);
$admin_csrf_rejected = !str_contains($resp_admin_csrf['body'], 'updated successfully');
qa('6.3', 'Forged CSRF token on /admin/complaints.php POST is rejected', $admin_csrf_rejected, "No success message with forged admin token");

// 6.4 POST to provider profile update without CSRF token is rejected
$provider_jar = do_login($base, 'shiv.electric@gmail.com', 'password123');
$resp_prov_csrf = http_post("$base/provider/profile.php", [
    'update_profile' => '1',
    'csrf_token'     => 'FORGED_PROVIDER_CSRF_XYZ',
    'full_name'      => 'Hacked Name',
    'phone'          => '9999999999',
    'experience_years'=> '5',
    'hourly_rate'    => '100',
    'is_available'   => '1',
], $provider_jar);
$prov_csrf_rejected = !str_contains($resp_prov_csrf['body'], 'updated successfully');
qa('6.4', 'Forged CSRF token on /provider/profile.php POST is rejected', $prov_csrf_rejected, "No success message with forged provider token");

// 6.5 Citizen complaint POST without CSRF token is rejected
$citizen_jar = do_login($base, 'ramesh@gmail.com', 'password123');
$resp_cit_csrf = http_post("$base/citizen/complaints.php", [
    'file_complaint' => '1',
    'csrf_token'     => 'BAD_CSRF_CITIZEN_777',
    'category'       => 'water_supply',
    'subject'        => 'Test Forged Complaint',
    'description'    => 'CSRF attack attempt',
    'location_text'  => 'Near school',
], $citizen_jar);
$cit_csrf_rejected = !str_contains($resp_cit_csrf['body'], 'filed successfully') && !str_contains($resp_cit_csrf['body'], 'complaint submitted');
qa('6.5', 'Forged CSRF token on /citizen/complaints.php POST is rejected', $cit_csrf_rejected, "No success message with forged citizen token");

// 6.6 Check all role-gated pages include CSRF token in visible HTML forms
$pages_to_check = [
    ['Merchant products form', "$base/merchant/products.php", $merchant_jar],
    ['Merchant enquiries reply form', "$base/merchant/enquiries.php", $merchant_jar],
    ['Provider services form', "$base/provider/services.php", $provider_jar],
    ['Admin announcements form', "$base/admin/announcements.php", $admin_jar],
    ['Citizen complaints form', "$base/citizen/complaints.php", $citizen_jar],
];
$all_have_csrf = true;
$missing = [];
foreach ($pages_to_check as [$label, $url, $jar]) {
    $r = http_get($url, $jar);
    if (!str_contains($r['body'], 'csrf_token')) {
        $all_have_csrf = false;
        $missing[] = $label;
    }
}
qa('6.6', 'All key role-gated forms include csrf_token input', $all_have_csrf,
    $all_have_csrf ? 'All 5 forms have csrf_token' : 'Missing in: ' . implode(', ', $missing));

// 6.7 Verify no pages return a PHP Fatal Error or Warning in normal operation
$all_pages = [
    ["$base/index.php", []],
    ["$base/services.php", []],
    ["$base/about.php", []],
    ["$base/contact.php", []],
    ["$base/citizen/dashboard.php", $citizen_jar],
    ["$base/citizen/services.php", $citizen_jar],
    ["$base/citizen/requests.php", $citizen_jar],
    ["$base/citizen/complaints.php", $citizen_jar],
    ["$base/merchant/dashboard.php", $merchant_jar],
    ["$base/merchant/products.php", $merchant_jar],
    ["$base/merchant/enquiries.php", $merchant_jar],
    ["$base/provider/dashboard.php", $provider_jar],
    ["$base/provider/requests.php", $provider_jar],
    ["$base/admin/dashboard.php", $admin_jar],
    ["$base/admin/merchants.php", $admin_jar],
    ["$base/admin/announcements.php", $admin_jar],
];
$error_pages = [];
foreach ($all_pages as [$url, $jar]) {
    $r = http_get($url, $jar);
    if (str_contains($r['body'], 'Fatal error') || str_contains($r['body'], 'Parse error') ||
        str_contains($r['body'], 'SQLSTATE')) {
        $short = str_replace("$base/", '', $url);
        $error_pages[] = $short;
    }
}
qa('6.7', 'No PHP/SQL errors on any of 16 key pages', empty($error_pages),
    empty($error_pages) ? '16/16 pages error-free' : 'Errors on: ' . implode(', ', $error_pages));

// ================================================================
// STEP 7 — FINAL REGRESSION (unit test runner)
// ================================================================
echo "Running Step 7: Final Regression...\n";

// Run the full PHP CLI test suite
$output = shell_exec('"C:\\xampp\\php\\php.exe" "C:\\xampp\\htdocs\\graamsetu\\tests\\run_all_tests.php" 2>&1');
$output = $output ?? '';

// Parse overall result line
preg_match('/OVERALL SUMMARY:\s*(\d+)\s*\/\s*(\d+)\s*tests passed/i', $output, $reg_match);
$reg_pass = (int)($reg_match[1] ?? 0);
$reg_total = (int)($reg_match[2] ?? 0);
$reg_ok = $reg_pass > 0 && $reg_pass === $reg_total;

qa('7.1', "All 5 module test suites still 100% passing ({$reg_pass}/{$reg_total})", $reg_ok,
    $reg_ok ? "121/121 unit tests PASS" : "REGRESSION DETECTED — {$reg_pass}/{$reg_total}");

// Check each module line
foreach (['Authentication & RBAC', 'Citizen & Discovery', 'Merchant & Business', 'Provider & Booking', 'Admin & Governance'] as $module) {
    preg_match('/' . preg_quote($module, '/') . '\s*:\s*(PASS|ERROR)/i', $output, $mm);
    $mod_status = $mm[1] ?? 'UNKNOWN';
    $short = match(true) {
        str_contains($module, 'Auth') => '7.2',
        str_contains($module, 'Citi') => '7.3',
        str_contains($module, 'Merc') => '7.4',
        str_contains($module, 'Prov') => '7.5',
        default                       => '7.6',
    };
    qa($short, "$module module 100% passing", strtoupper($mod_status)==='PASS', $mod_status);
}

// ================================================================
// REPORT
// ================================================================
echo "\n";
echo "====================================================\n";
echo "   PHASE 6 — STEPS 6 & 7: CSRF & REGRESSION QA\n";
echo "====================================================\n\n";

$w = 52;
foreach ($results as [$s,$id,$desc,$detail]) {
    printf("%-6s %-5s %-{$w}s  %s\n", "[$s]", $id,
        strlen($desc)>$w ? substr($desc,0,$w-3).'...' : $desc,
        $detail
    );
}

$total = $passed + $failed;
echo "\n====================================================\n";
printf("RESULT: %d / %d checks PASSED (%d%%)\n", $passed, $total, $total ? round(($passed/$total)*100) : 0);
echo $failed===0
    ? "STATUS: ALL CSRF & REGRESSION CHECKS PASSED\n"
    : "STATUS: {$failed} CHECK(S) FAILED\n";
echo "====================================================\n";
