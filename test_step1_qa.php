<?php
// Browser QA Step 1: Public Portal & Guest Exploration Runner

error_reporting(E_ALL);
ini_set('display_errors', 1);

function test_http_url($url, $expected_status = 200, $follow_redirects = false) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $follow_redirects);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    $redirect_url = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    $err = curl_error($ch);
    curl_close($ch);

    preg_match('/<title>(.*?)<\/title>/is', $body, $m);
    $title = isset($m[1]) ? trim($m[1]) : '';

    return [
        'url' => $url,
        'http_code' => $http_code,
        'redirect_url' => $redirect_url,
        'title' => $title,
        'body_len' => strlen($body),
        'error' => $err
    ];
}

echo "====================================================\n";
echo "   GRAAMSETU BROWSER QA: STEP 1 (PUBLIC & GUEST)\n";
echo "====================================================\n\n";

$tests = [
    [
        'name' => '1. /index.php (Public Home Portal)',
        'url' => 'http://localhost:8000/index.php',
        'expected_code' => 200,
        'check' => function($res) {
            return $res['http_code'] === 200 && str_contains($res['title'], 'GraamSetu') && $res['body_len'] > 1000;
        }
    ],
    [
        'name' => '2. /services.php (Public Services & Directory)',
        'url' => 'http://localhost:8000/services.php',
        'expected_code' => 200,
        'check' => function($res) {
            return $res['http_code'] === 200 && str_contains($res['title'], 'Services') && $res['body_len'] > 1000;
        }
    ],
    [
        'name' => '3. /about.php (Public About Page)',
        'url' => 'http://localhost:8000/about.php',
        'expected_code' => 200,
        'check' => function($res) {
            return $res['http_code'] === 200 && str_contains($res['title'], 'About') && $res['body_len'] > 1000;
        }
    ],
    [
        'name' => '4. /contact.php (Public Contact & Helplines)',
        'url' => 'http://localhost:8000/contact.php',
        'expected_code' => 200,
        'check' => function($res) {
            return $res['http_code'] === 200 && str_contains($res['title'], 'Contact') && $res['body_len'] > 1000;
        }
    ],
    [
        'name' => '5. Unauthenticated /citizen/dashboard.php redirect',
        'url' => 'http://localhost:8000/citizen/dashboard.php',
        'expected_code' => 302,
        'check' => function($res) {
            return ($res['http_code'] === 302 || $res['http_code'] === 301) && str_contains($res['redirect_url'], 'login.php');
        }
    ],
    [
        'name' => '6. Unauthenticated /admin/dashboard.php redirect',
        'url' => 'http://localhost:8000/admin/dashboard.php',
        'expected_code' => 302,
        'check' => function($res) {
            return ($res['http_code'] === 302 || $res['http_code'] === 301) && str_contains($res['redirect_url'], 'login.php');
        }
    ],
];

foreach ($tests as $t) {
    $res = test_http_url($t['url']);
    $passed = $t['check']($res);
    $status = $passed ? "[PASS]" : "[FAIL]";
    
    echo "$status {$t['name']}\n";
    echo "       URL: {$res['url']}\n";
    echo "       HTTP Status: {$res['http_code']}\n";
    if (!empty($res['redirect_url'])) {
        echo "       Redirect Target: {$res['redirect_url']}\n";
    }
    if (!empty($res['title'])) {
        echo "       Page Title: {$res['title']}\n";
    }
    echo "       Payload Size: {$res['body_len']} bytes\n";
    if (!empty($res['error'])) {
        echo "       cURL Error: {$res['error']}\n";
    }
    echo "\n";
}
