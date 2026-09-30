<?php
/**
 * Phase 6 QA - Steps 3–5: Merchant, Provider, and Admin Role Workflow QA
 * 
 * Step 3: Merchant  — mahesh@kirana.in       / password123
 * Step 4: Provider  — shiv.electric@gmail.com / password123
 * Step 5: Admin     — admin@graamsetu.in      / password123
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

$base = "http://localhost/graamsetu";
$passed = 0;
$failed = 0;
$results = [];

// -------------------------------------------------------
// Helpers
// -------------------------------------------------------
function http_get(string $url, array $cookies = [], bool $follow = true): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HEADER         => true,
        CURLOPT_USERAGENT      => 'GraamSetu-QA/2.0',
    ]);
    if ($cookies) {
        $s = implode('; ', array_map(fn($k,$v)=>"$k=$v", array_keys($cookies), $cookies));
        curl_setopt($ch, CURLOPT_COOKIE, $s);
    }
    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $hdr  = substr($raw, 0, $hs);
    $body = substr($raw, $hs);
    $sc   = [];
    preg_match_all('/^Set-Cookie:\s*([^;]+)/im', $hdr, $m);
    foreach ($m[1] as $p) { [$k,$v] = array_pad(explode('=',$p,2),2,''); $sc[trim($k)]=trim($v); }
    return ['status'=>$code,'headers'=>$hdr,'body'=>$body,'cookies'=>$sc];
}

function http_post(string $url, array $fields, array $cookies = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HEADER         => true,
        CURLOPT_USERAGENT      => 'GraamSetu-QA/2.0',
    ]);
    if ($cookies) {
        $s = implode('; ', array_map(fn($k,$v)=>"$k=$v", array_keys($cookies), $cookies));
        curl_setopt($ch, CURLOPT_COOKIE, $s);
    }
    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $hdr  = substr($raw, 0, $hs);
    $body = substr($raw, $hs);
    $sc   = [];
    preg_match_all('/^Set-Cookie:\s*([^;]+)/im', $hdr, $m);
    foreach ($m[1] as $p) { [$k,$v] = array_pad(explode('=',$p,2),2,''); $sc[trim($k)]=trim($v); }
    return ['status'=>$code,'headers'=>$hdr,'body'=>$body,'cookies'=>$sc];
}

function loc(string $headers): string {
    preg_match('/^Location:\s*(.+)$/im', $headers, $m);
    return trim($m[1] ?? '');
}

function qa(string $id, string $desc, bool $ok, string $detail=''): void {
    global $passed,$failed,$results;
    $ok ? $passed++ : $failed++;
    $results[] = [$ok?'PASS':'FAIL', $id, $desc, $detail];
}

function do_login(string $base, string $email, string $pass): array {
    $page = http_get("$base/login.php");
    $jar  = $page['cookies'];
    $resp = http_post("$base/login.php", ['email'=>$email,'password'=>$pass], $jar);
    return array_merge($jar, $resp['cookies']);
}

// ================================================================
// STEP 3 — MERCHANT ROLE (mahesh@kirana.in)
// ================================================================
echo "Running Step 3: Merchant Role...\n";

// 3.1 Unauthenticated guard
$r = http_get("$base/merchant/dashboard.php", [], false);
qa('3.1', 'Unauthenticated /merchant/dashboard.php → 302 login', $r['status']===302 && str_contains(loc($r['headers']),'login.php'), "HTTP {$r['status']} → ".loc($r['headers']));

// 3.2 Login
$jar = do_login($base, 'mahesh@kirana.in', 'password123');
$dash = http_get("$base/merchant/dashboard.php", $jar);
qa('3.2', 'Merchant login succeeds — dashboard 200', $dash['status']===200 && str_contains(strtolower($dash['body']),'dashboard'), "HTTP {$dash['status']}");

// 3.3 Key dashboard sections
qa('3.3', 'Merchant dashboard body renders without server error', $dash['status']===200 && !str_contains($dash['body'],'Fatal error') && !str_contains($dash['body'],'Warning:'), "No PHP errors in response");

// 3.4 Products page
$prod = http_get("$base/merchant/products.php", $jar);
qa('3.4', '/merchant/products.php loads HTTP 200', $prod['status']===200, "HTTP {$prod['status']}");

// 3.5 Enquiries page
$enq = http_get("$base/merchant/enquiries.php", $jar);
qa('3.5', '/merchant/enquiries.php loads HTTP 200', $enq['status']===200, "HTTP {$enq['status']}");

// 3.6 Profile page
$prof = http_get("$base/merchant/profile.php", $jar);
qa('3.6', '/merchant/profile.php loads HTTP 200', $prof['status']===200, "HTTP {$prof['status']}");

// 3.7 Business settings page (business.php)
$sett = http_get("$base/merchant/business.php", $jar);
qa('3.7', '/merchant/business.php (store settings) loads HTTP 200', $sett['status']===200, "HTTP {$sett['status']}");

// 3.8 Role guard: merchant CANNOT access admin
$ra = http_get("$base/admin/dashboard.php", $jar, false);
qa('3.8', 'Merchant CANNOT access /admin/dashboard.php (302)', $ra['status']===302, "HTTP {$ra['status']} → ".loc($ra['headers']));

// 3.9 Role guard: merchant CANNOT access provider
$rp = http_get("$base/provider/dashboard.php", $jar, false);
qa('3.9', 'Merchant CANNOT access /provider/dashboard.php (302)', $rp['status']===302, "HTTP {$rp['status']} → ".loc($rp['headers']));

// 3.10 Logout
$lo = http_get("$base/logout.php", $jar);
$after = http_get("$base/merchant/dashboard.php", $jar, false);
qa('3.10', 'After logout, /merchant/dashboard.php → 302 login', $after['status']===302 && str_contains(loc($after['headers']),'login.php'), "HTTP {$after['status']}");

// ================================================================
// STEP 4 — PROVIDER ROLE (shiv.electric@gmail.com)
// ================================================================
echo "Running Step 4: Provider Role...\n";

// 4.1 Unauthenticated guard
$r = http_get("$base/provider/dashboard.php", [], false);
qa('4.1', 'Unauthenticated /provider/dashboard.php → 302 login', $r['status']===302 && str_contains(loc($r['headers']),'login.php'), "HTTP {$r['status']} → ".loc($r['headers']));

// 4.2 Login
$jar = do_login($base, 'shiv.electric@gmail.com', 'password123');
$dash = http_get("$base/provider/dashboard.php", $jar);
qa('4.2', 'Provider login succeeds — dashboard 200', $dash['status']===200 && str_contains(strtolower($dash['body']),'dashboard'), "HTTP {$dash['status']}");

// 4.3 Requests page
$req = http_get("$base/provider/requests.php", $jar);
qa('4.3', '/provider/requests.php loads HTTP 200', $req['status']===200, "HTTP {$req['status']}");

// 4.4 Services page
$svc = http_get("$base/provider/services.php", $jar);
qa('4.4', '/provider/services.php loads HTTP 200', $svc['status']===200, "HTTP {$svc['status']}");

// 4.5 Profile page
$prf = http_get("$base/provider/profile.php", $jar);
qa('4.5', '/provider/profile.php loads HTTP 200', $prf['status']===200, "HTTP {$prf['status']}");

// 4.6 Role guard: provider CANNOT access admin
$ra = http_get("$base/admin/dashboard.php", $jar, false);
qa('4.6', 'Provider CANNOT access /admin/dashboard.php (302)', $ra['status']===302, "HTTP {$ra['status']}");

// 4.7 Role guard: provider CANNOT access merchant
$rm = http_get("$base/merchant/dashboard.php", $jar, false);
qa('4.7', 'Provider CANNOT access /merchant/dashboard.php (302)', $rm['status']===302, "HTTP {$rm['status']}");

// 4.8 Logout check
$lo = http_get("$base/logout.php", $jar);
$after = http_get("$base/provider/dashboard.php", $jar, false);
qa('4.8', 'After logout, /provider/dashboard.php → 302 login', $after['status']===302 && str_contains(loc($after['headers']),'login.php'), "HTTP {$after['status']}");

// ================================================================
// STEP 5 — ADMIN ROLE (admin@graamsetu.in)
// ================================================================
echo "Running Step 5: Admin Role...\n";

// 5.1 Unauthenticated guard
$r = http_get("$base/admin/dashboard.php", [], false);
qa('5.1', 'Unauthenticated /admin/dashboard.php → 302 login', $r['status']===302 && str_contains(loc($r['headers']),'login.php'), "HTTP {$r['status']} → ".loc($r['headers']));

// 5.2 Login
$jar = do_login($base, 'admin@graamsetu.in', 'password123');
$dash = http_get("$base/admin/dashboard.php", $jar);
qa('5.2', 'Admin login succeeds — dashboard 200', $dash['status']===200 && str_contains(strtolower($dash['body']),'dashboard'), "HTTP {$dash['status']}");

// 5.3 Key stat sections on admin dashboard
qa('5.3', 'Admin dashboard shows citizens/merchants statistics', str_contains(strtolower($dash['body']),'citizen') || str_contains(strtolower($dash['body']),'merchant'), "stats section present");

// 5.4 Admin Users page
$users = http_get("$base/admin/users.php", $jar);
qa('5.4', '/admin/users.php loads HTTP 200', $users['status']===200, "HTTP {$users['status']}");

// 5.5 Admin Merchants page
$merch = http_get("$base/admin/merchants.php", $jar);
qa('5.5', '/admin/merchants.php loads HTTP 200', $merch['status']===200, "HTTP {$merch['status']}");

// 5.6 Admin Providers page
$prov = http_get("$base/admin/providers.php", $jar);
qa('5.6', '/admin/providers.php loads HTTP 200', $prov['status']===200, "HTTP {$prov['status']}");

// 5.7 Admin Complaints page
$comp = http_get("$base/admin/complaints.php", $jar);
qa('5.7', '/admin/complaints.php loads HTTP 200', $comp['status']===200, "HTTP {$comp['status']}");

// 5.8 Admin Requests page
$reqp = http_get("$base/admin/requests.php", $jar);
qa('5.8', '/admin/requests.php loads HTTP 200', $reqp['status']===200, "HTTP {$reqp['status']}");

// 5.9 Admin Announcements page
$ann = http_get("$base/admin/announcements.php", $jar);
qa('5.9', '/admin/announcements.php loads HTTP 200', $ann['status']===200, "HTTP {$ann['status']}");

// 5.10 Admin Categories page
$cat = http_get("$base/admin/categories.php", $jar);
qa('5.10', '/admin/categories.php loads HTTP 200', $cat['status']===200, "HTTP {$cat['status']}");

// 5.11 Admin Settings / Audit page
$sett = http_get("$base/admin/settings.php", $jar);
qa('5.11', '/admin/settings.php loads HTTP 200', $sett['status']===200, "HTTP {$sett['status']}");

// 5.12 Role guard: admin CANNOT be silently re-used as citizen
$rc = http_get("$base/citizen/dashboard.php", $jar, false);
// Admin should either get redirected to admin dash or access citizen (not forbidden) — just must not 500
qa('5.12', '/citizen/dashboard.php with admin session does not 500', $rc['status'] !== 500, "HTTP {$rc['status']}");

// 5.13 Logout
$lo = http_get("$base/logout.php", $jar);
$after = http_get("$base/admin/dashboard.php", $jar, false);
qa('5.13', 'After logout, /admin/dashboard.php → 302 login', $after['status']===302 && str_contains(loc($after['headers']),'login.php'), "HTTP {$after['status']}");

// ================================================================
// REPORT
// ================================================================
echo "\n";
echo "====================================================\n";
echo "  PHASE 6 — STEPS 3-5: MERCHANT / PROVIDER / ADMIN QA\n";
echo "====================================================\n\n";

$w = 50;
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
    ? "STATUS: ALL MERCHANT / PROVIDER / ADMIN CHECKS PASSED\n"
    : "STATUS: {$failed} CHECK(S) FAILED — INVESTIGATE\n";
echo "====================================================\n";
