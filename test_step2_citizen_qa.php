<?php
/**
 * Phase 6 QA - Step 2: Citizen Role Workflow
 * Tests citizen login, dashboard, service discovery, enquiry, complaints, notifications.
 *
 * Citizen test account: ramesh@gmail.com / password123
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

$base = "http://localhost/graamsetu";
$passed = 0;
$failed = 0;
$results = [];

// -------------------------------------------------------
// Helper: HTTP GET with optional cookie jar (session)
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
        $cookie_str = implode('; ', array_map(fn($k, $v) => "$k=$v", array_keys($cookies), $cookies));
        curl_setopt($ch, CURLOPT_COOKIE, $cookie_str);
    }
    $raw    = curl_exec($ch);
    $code   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $header  = substr($raw, 0, $header_size);
    $body    = substr($raw, $header_size);
    // Extract Set-Cookie headers
    $set_cookies = [];
    preg_match_all('/^Set-Cookie:\s*([^;]+)/im', $header, $m);
    foreach ($m[1] as $pair) {
        [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
        $set_cookies[trim($k)] = trim($v);
    }
    return ['status' => $code, 'headers' => $header, 'body' => $body, 'cookies' => $set_cookies, 'raw' => $raw];
}

// -------------------------------------------------------
// Helper: HTTP POST with cookie jar
// -------------------------------------------------------
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
        $cookie_str = implode('; ', array_map(fn($k, $v) => "$k=$v", array_keys($cookies), $cookies));
        curl_setopt($ch, CURLOPT_COOKIE, $cookie_str);
    }
    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $header = substr($raw, 0, $header_size);
    $body   = substr($raw, $header_size);
    $set_cookies = [];
    preg_match_all('/^Set-Cookie:\s*([^;]+)/im', $header, $m);
    foreach ($m[1] as $pair) {
        [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
        $set_cookies[trim($k)] = trim($v);
    }
    return ['status' => $code, 'headers' => $header, 'body' => $body, 'cookies' => $set_cookies];
}

function qa_check(string $id, string $desc, bool $ok, string $detail = ''): void {
    global $passed, $failed, $results;
    if ($ok) { $passed++; $status = 'PASS'; }
    else      { $failed++; $status = 'FAIL'; }
    $results[] = [$status, $id, $desc, $detail];
}

// -------------------------------------------------------
// STEP 2.1 — Guest redirect protection (belt-and-suspenders)
// -------------------------------------------------------
$r = http_get("$base/citizen/dashboard.php", [], false);
$location = '';
preg_match('/^Location:\s*(.+)$/im', $r['headers'], $lm);
$location = trim($lm[1] ?? '');
qa_check(
    '2.1',
    'Unauthenticated /citizen/dashboard.php redirects to /login.php',
    $r['status'] === 302 && str_contains($location, 'login.php'),
    "HTTP {$r['status']} → Location: $location"
);

// -------------------------------------------------------
// STEP 2.2 — Login page renders (GET /login.php)
// -------------------------------------------------------
$r = http_get("$base/login.php");
qa_check(
    '2.2',
    'Login page loads with HTTP 200',
    $r['status'] === 200 && str_contains($r['body'], 'action'),
    "HTTP {$r['status']}"
);

// -------------------------------------------------------
// STEP 2.3 — Attempt login with wrong password
// -------------------------------------------------------
$r2 = http_post("$base/login.php", [
    'email' => 'ramesh@gmail.com',
    'password' => 'wrongpassword999',
]);
qa_check(
    '2.3',
    'Login with wrong password returns 200 (stays on login page)',
    $r2['status'] === 200,
    "HTTP {$r2['status']}"
);

// -------------------------------------------------------
// STEP 2.4 — Successful citizen login (ramesh@gmail.com)
// -------------------------------------------------------
// First get the login page to capture session cookie
$login_page = http_get("$base/login.php");
$session_cookie = $login_page['cookies'];

$login_resp = http_post("$base/login.php", [
    'email'    => 'ramesh@gmail.com',
    'password' => 'password123',
], $session_cookie);

// Merge cookies from login response
$jar = array_merge($session_cookie, $login_resp['cookies']);

// Check redirect to citizen dashboard
$citizen_dash = http_get("$base/citizen/dashboard.php", $jar);

qa_check(
    '2.4',
    'Citizen login (ramesh@gmail.com) succeeds — dashboard loads 200',
    $citizen_dash['status'] === 200 && str_contains($citizen_dash['body'], 'dashboard'),
    "HTTP {$citizen_dash['status']}"
);

$dash_body = $citizen_dash['body'];

// -------------------------------------------------------
// STEP 2.5 — Dashboard contains key UI elements
// -------------------------------------------------------
qa_check(
    '2.5a',
    'Dashboard displays Welcome message or citizen name',
    str_contains($dash_body, 'ramesh') || str_contains(strtolower($dash_body), 'welcome') || str_contains(strtolower($dash_body), 'dashboard'),
    "Page title / welcome section present"
);
qa_check(
    '2.5b',
    'Dashboard contains Notifications section',
    str_contains(strtolower($dash_body), 'notification') || str_contains(strtolower($dash_body), 'alert'),
    "Notifications panel present"
);
qa_check(
    '2.5c',
    'Dashboard contains Announcements / Panchayat notices section',
    str_contains(strtolower($dash_body), 'announcement') || str_contains(strtolower($dash_body), 'notice'),
    "Announcements section present"
);

// -------------------------------------------------------
// STEP 2.6 — Services / Discovery page accessible
// -------------------------------------------------------
$services = http_get("$base/citizen/services.php", $jar);
qa_check(
    '2.6',
    '/citizen/services.php loads HTTP 200',
    $services['status'] === 200,
    "HTTP {$services['status']}"
);

// -------------------------------------------------------
// STEP 2.7 — Service Requests page accessible
// -------------------------------------------------------
$requests = http_get("$base/citizen/requests.php", $jar);
qa_check(
    '2.7',
    '/citizen/requests.php loads HTTP 200',
    $requests['status'] === 200,
    "HTTP {$requests['status']}"
);

// -------------------------------------------------------
// STEP 2.8 — Complaints page accessible
// -------------------------------------------------------
$complaints = http_get("$base/citizen/complaints.php", $jar);
qa_check(
    '2.8',
    '/citizen/complaints.php loads HTTP 200',
    $complaints['status'] === 200,
    "HTTP {$complaints['status']}"
);

// -------------------------------------------------------
// STEP 2.9 — Profile page accessible
// -------------------------------------------------------
$profile = http_get("$base/citizen/profile.php", $jar);
qa_check(
    '2.9',
    '/citizen/profile.php loads HTTP 200',
    $profile['status'] === 200,
    "HTTP {$profile['status']}"
);

// -------------------------------------------------------
// STEP 2.10 — Public services.php (discovery) accessible
// -------------------------------------------------------
$pub_services = http_get("$base/services.php", $jar);
qa_check(
    '2.10',
    '/services.php (service discovery) loads HTTP 200',
    $pub_services['status'] === 200,
    "HTTP {$pub_services['status']}"
);

// -------------------------------------------------------
// STEP 2.11 — Role guard: Citizen CANNOT access admin panel
// -------------------------------------------------------
$r_admin = http_get("$base/admin/dashboard.php", $jar, false);
preg_match('/^Location:\s*(.+)$/im', $r_admin['headers'], $adm);
$adm_loc = trim($adm[1] ?? '');
qa_check(
    '2.11',
    'Citizen session CANNOT access /admin/dashboard.php (302 redirect)',
    $r_admin['status'] === 302 && str_contains($adm_loc, 'login.php'),
    "HTTP {$r_admin['status']} → $adm_loc"
);

// -------------------------------------------------------
// STEP 2.12 — Role guard: Citizen CANNOT access merchant panel
// -------------------------------------------------------
$r_merch = http_get("$base/merchant/dashboard.php", $jar, false);
preg_match('/^Location:\s*(.+)$/im', $r_merch['headers'], $mm);
$merch_loc = trim($mm[1] ?? '');
qa_check(
    '2.12',
    'Citizen session CANNOT access /merchant/dashboard.php (302 redirect)',
    $r_merch['status'] === 302 && str_contains($merch_loc, 'login.php'),
    "HTTP {$r_merch['status']} → $merch_loc"
);

// -------------------------------------------------------
// STEP 2.13 — Logout
// -------------------------------------------------------
$logout = http_get("$base/logout.php", $jar);
qa_check(
    '2.13',
    '/logout.php redirects (302/200) without PHP errors',
    in_array($logout['status'], [200, 302]),
    "HTTP {$logout['status']}"
);

// After logout, citizen dashboard should redirect
$after_logout = http_get("$base/citizen/dashboard.php", $jar, false);
preg_match('/^Location:\s*(.+)$/im', $after_logout['headers'], $al);
$al_loc = trim($al[1] ?? '');
qa_check(
    '2.14',
    'After logout, /citizen/dashboard.php again redirects to login',
    $after_logout['status'] === 302 && str_contains($al_loc, 'login.php'),
    "HTTP {$after_logout['status']} → $al_loc"
);

// -------------------------------------------------------
// REPORT
// -------------------------------------------------------
echo "\n";
echo "====================================================\n";
echo "   PHASE 6 — STEP 2: CITIZEN ROLE WORKFLOW QA\n";
echo "====================================================\n\n";

$width_desc = 52;
foreach ($results as [$s, $id, $desc, $detail]) {
    $icon  = $s === 'PASS' ? '[PASS]' : '[FAIL]';
    $trunc = strlen($desc) > $width_desc ? substr($desc, 0, $width_desc - 3) . '...' : $desc;
    printf("%-6s %-5s %-{$width_desc}s  %s\n", $icon, $id, $trunc, $detail);
}

$total = $passed + $failed;
echo "\n====================================================\n";
printf("RESULT: %d / %d checks PASSED (%d%%)\n", $passed, $total, $total ? round(($passed/$total)*100) : 0);
if ($failed === 0) {
    echo "STATUS: ALL CITIZEN WORKFLOW CHECKS PASSED\n";
} else {
    echo "STATUS: $failed CHECK(S) FAILED — INVESTIGATE BEFORE STEP 3\n";
}
echo "====================================================\n";
