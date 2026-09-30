<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();

echo "=== Categories (Kirana/Grocery) ===\n";
$r = $db->query("SELECT id, name, type FROM categories ORDER BY id")->fetchAll();
foreach ($r as $row) echo "  id={$row['id']} type={$row['type']} name={$row['name']}\n";

echo "\n=== Verified Merchants ===\n";
$m = $db->query("SELECT mp.id, mp.category_id, mp.business_name, mp.verification_status FROM merchant_profiles mp WHERE mp.verification_status='verified'")->fetchAll();
if (empty($m)) {
    echo "  NONE — no verified merchants!\n";
} else {
    foreach ($m as $row) echo "  id={$row['id']} cat_id={$row['category_id']} status={$row['verification_status']} name={$row['business_name']}\n";
}

echo "\n=== Merchant 1.5 & 5.2 test: is_verified column ===\n";
$v = $db->query("SELECT id, business_name, is_verified, verification_status FROM merchant_profiles LIMIT 5")->fetchAll();
foreach ($v as $row) echo "  id={$row['id']} is_verified={$row['is_verified']} status={$row['verification_status']} name={$row['business_name']}\n";
