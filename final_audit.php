<?php
/**
 * GraamSetu — Full Platform Deep Audit
 * Scans every PHP file for syntax errors and all HTTP routes for errors.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

$project = "C:\\xampp\\htdocs\\graamsetu";
$base    = "http://localhost/graamsetu";

$php_bin = "C:\\xampp\\php\\php.exe";

echo "====================================================\n";
echo "   GRAAMSETU FINAL PRODUCTION AUDIT\n";
echo "====================================================\n\n";

// ── 1. PHP SYNTAX CHECK ──────────────────────────────
echo "--- STEP 1: PHP Syntax Check (all .php files) ---\n";
$rdi = new RecursiveDirectoryIterator($project, RecursiveDirectoryIterator::SKIP_DOTS);
$rii = new RecursiveIteratorIterator($rdi);

$syntax_errors = [];
$file_count = 0;
foreach ($rii as $file) {
    if ($file->getExtension() !== 'php') continue;
    $path = $file->getPathname();
    // Skip test files and scratch scripts
    if (str_contains($path, '\\tests\\')) continue;
    $output = shell_exec('"' . $php_bin . '" -l "' . $path . '" 2>&1');
    if (!str_contains($output, 'No syntax errors detected')) {
        $syntax_errors[] = str_replace($project . '\\', '', $path) . ' → ' . trim($output);
    }
    $file_count++;
}
echo "Checked $file_count PHP files\n";
if (empty($syntax_errors)) {
    echo "[PASS] All PHP files have no syntax errors\n";
} else {
    foreach ($syntax_errors as $e) echo "[FAIL] $e\n";
}

echo "\n--- STEP 2: HTTP Route Audit (all public routes) ---\n";

function http_check(string $url, array $cookies = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HEADER         => false,
        CURLOPT_USERAGENT      => 'GraamSetu-Audit/1.0',
    ]);
    if ($cookies) {
        curl_setopt($ch, CURLOPT_COOKIE, implode('; ', array_map(fn($k,$v)=>"$k=$v", array_keys($cookies), $cookies)));
    }
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $has_error = str_contains($body ?? '', 'Fatal error') ||
                 str_contains($body ?? '', 'SQLSTATE') ||
                 str_contains($body ?? '', 'Parse error') ||
                 str_contains($body ?? '', 'Uncaught');
    return ['code' => $code, 'error' => $has_error, 'body' => $body ?? ''];
}

function do_login(string $base, string $email, string $pass): array {
    $ch = curl_init("$base/login.php");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>5,CURLOPT_TIMEOUT=>8,CURLOPT_HEADER=>true,CURLOPT_USERAGENT=>'GraamSetu-Audit/1.0']);
    $raw = curl_exec($ch);
    $hs  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $hdr = substr($raw, 0, $hs);
    $sc = [];
    preg_match_all('/^Set-Cookie:\s*([^;]+)/im', $hdr, $m);
    foreach ($m[1] as $p) { [$k,$v] = array_pad(explode('=',$p,2),2,''); $sc[trim($k)]=trim($v); }

    $ch2 = curl_init("$base/login.php");
    curl_setopt_array($ch2, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['email'=>$email,'password'=>$pass]),CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>5,CURLOPT_TIMEOUT=>8,CURLOPT_HEADER=>true,CURLOPT_USERAGENT=>'GraamSetu-Audit/1.0',CURLOPT_COOKIE=>implode('; ',array_map(fn($k,$v)=>"$k=$v",array_keys($sc),$sc))]);
    $raw2 = curl_exec($ch2);
    $hs2  = curl_getinfo($ch2, CURLINFO_HEADER_SIZE);
    curl_close($ch2);
    $hdr2 = substr($raw2, 0, $hs2);
    $sc2  = [];
    preg_match_all('/^Set-Cookie:\s*([^;]+)/im', $hdr2, $m2);
    foreach ($m2[1] as $p) { [$k,$v] = array_pad(explode('=',$p,2),2,''); $sc2[trim($k)]=trim($v); }
    return array_merge($sc, $sc2);
}

$citizen_jar  = do_login($base, 'ramesh@gmail.com',         'password123');
$merchant_jar = do_login($base, 'mahesh@kirana.in',         'password123');
$provider_jar = do_login($base, 'shiv.electric@gmail.com',  'password123');
$admin_jar    = do_login($base, 'admin@graamsetu.in',       'password123');

$routes = [
    // Public
    ['GET', "$base/index.php",           [],           'Public homepage'],
    ['GET', "$base/services.php",        [],           'Public service directory'],
    ['GET', "$base/about.php",           [],           'About page'],
    ['GET', "$base/contact.php",         [],           'Contact page'],
    ['GET', "$base/login.php",           [],           'Login page'],
    ['GET', "$base/register.php",        [],           'Register page'],
    // Citizen
    ['GET', "$base/citizen/dashboard.php", $citizen_jar, 'Citizen dashboard'],
    ['GET', "$base/citizen/services.php",  $citizen_jar, 'Citizen services'],
    ['GET', "$base/citizen/requests.php",  $citizen_jar, 'Citizen requests'],
    ['GET', "$base/citizen/complaints.php",$citizen_jar, 'Citizen complaints'],
    ['GET', "$base/citizen/profile.php",   $citizen_jar, 'Citizen profile'],
    // Merchant
    ['GET', "$base/merchant/dashboard.php", $merchant_jar, 'Merchant dashboard'],
    ['GET', "$base/merchant/products.php",  $merchant_jar, 'Merchant products'],
    ['GET', "$base/merchant/enquiries.php", $merchant_jar, 'Merchant enquiries'],
    ['GET', "$base/merchant/profile.php",   $merchant_jar, 'Merchant profile'],
    ['GET', "$base/merchant/business.php",  $merchant_jar, 'Merchant business settings'],
    // Provider
    ['GET', "$base/provider/dashboard.php", $provider_jar, 'Provider dashboard'],
    ['GET', "$base/provider/requests.php",  $provider_jar, 'Provider requests'],
    ['GET', "$base/provider/services.php",  $provider_jar, 'Provider services'],
    ['GET', "$base/provider/profile.php",   $provider_jar, 'Provider profile'],
    // Admin
    ['GET', "$base/admin/dashboard.php",     $admin_jar, 'Admin dashboard'],
    ['GET', "$base/admin/users.php",         $admin_jar, 'Admin users'],
    ['GET', "$base/admin/merchants.php",     $admin_jar, 'Admin merchants'],
    ['GET', "$base/admin/providers.php",     $admin_jar, 'Admin providers'],
    ['GET', "$base/admin/complaints.php",    $admin_jar, 'Admin complaints'],
    ['GET', "$base/admin/requests.php",      $admin_jar, 'Admin requests'],
    ['GET', "$base/admin/announcements.php", $admin_jar, 'Admin announcements'],
    ['GET', "$base/admin/categories.php",    $admin_jar, 'Admin categories'],
    ['GET', "$base/admin/settings.php",      $admin_jar, 'Admin settings/audit'],
];

$route_errors = [];
foreach ($routes as [$method, $url, $jar, $label]) {
    $r = http_check($url, $jar);
    $status_ok  = in_array($r['code'], [200, 302]);
    $content_ok = !$r['error'];
    if (!$status_ok || !$content_ok) {
        $route_errors[] = [
            'label'  => $label,
            'url'    => str_replace($base . '/', '', $url),
            'code'   => $r['code'],
            'error'  => $r['error'],
            'snippet'=> $r['error'] ? substr(strip_tags($r['body']), 0, 120) : '',
        ];
    } else {
        printf("[PASS] HTTP %d  %-45s  %s\n", $r['code'], str_replace($base.'/', '', $url), $label);
    }
}

if (!empty($route_errors)) {
    echo "\n[FAILURES]\n";
    foreach ($route_errors as $e) {
        printf("[FAIL] HTTP %d  %-45s  %s\n", $e['code'], $e['url'], $e['label']);
        if ($e['snippet']) echo "       Error: " . $e['snippet'] . "\n";
    }
}

echo "\n====================================================\n";
$total_issues = count($syntax_errors) + count($route_errors);
if ($total_issues === 0) {
    echo "RESULT: ZERO ISSUES FOUND — PLATFORM IS CLEAN\n";
} else {
    echo "RESULT: $total_issues ISSUE(S) FOUND — FIX BEFORE FINAL RELEASE\n";
}
echo "====================================================\n";
