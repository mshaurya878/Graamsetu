<?php
/**
 * GraamSetu — Restore Demo Data to Verified State
 * Fixes regression: Mahesh Kirana Store got set to rejected during testing.
 */
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();

// Restore Mahesh Kirana Store (merchant_profiles id=1) to verified
$db->exec("UPDATE merchant_profiles SET is_verified=1, verification_status='verified' WHERE id=1");
echo "Restored merchant_profiles id=1 to verified\n";

// Confirm state
$r = $db->query("SELECT id, business_name, is_verified, verification_status FROM merchant_profiles ORDER BY id")->fetchAll();
echo "\nCurrent merchant_profiles state:\n";
foreach ($r as $row) {
    echo "  id={$row['id']} is_verified={$row['is_verified']} status={$row['verification_status']} - {$row['business_name']}\n";
}

echo "\nDone.\n";
