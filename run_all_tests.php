<?php
// Combined Platform Regression Runner for GraamSetu

$tests = [
    'Authentication & RBAC' => 'tests/test_auth.php',
    'Citizen & Discovery' => 'tests/test_citizen_module.php',
    'Merchant & Business' => 'tests/test_merchant_module.php',
    'Provider & Booking' => 'tests/test_provider_module.php',
    'Admin & Governance' => 'tests/test_admin_module.php'
];

echo "====================================================\n";
echo "   GRAAMSETU FULL PLATFORM REGRESSION CHECK\n";
echo "====================================================\n\n";

$all_passed = true;
$total_tests = 0;
$total_passed = 0;

foreach ($tests as $module => $file) {
    $cmd = '"C:\\xampp\\php\\php.exe" "' . __DIR__ . '/../' . $file . '"';
    $output = shell_exec($cmd);
    
    // Extract results
    if (preg_match('/TEST RESULTS: (\d+) \/ (\d+) tests passed \((\d+)%\)/', $output, $m)) {
        $p = (int)$m[1];
        $t = (int)$m[2];
        $pct = (int)$m[3];
        $total_passed += $p;
        $total_tests += $t;
        $status = ($p === $t) ? "PASS (100%)" : "FAIL ($p/$t)";
        echo sprintf("%-30s : %s [%d/%d]\n", $module, $status, $p, $t);
        if ($p !== $t) $all_passed = false;
    } else {
        echo sprintf("%-30s : ERROR IN EXECUTION\n", $module);
        $all_passed = false;
    }
}

echo "\n====================================================\n";
echo "OVERALL SUMMARY: $total_passed / $total_tests tests passed (" . round(($total_passed/$total_tests)*100) . "%)\n";
echo "STATUS: " . ($all_passed ? "ALL 5 MODULES 100% PASSING" : "REGRESSION DETECTED") . "\n";
echo "====================================================\n";
