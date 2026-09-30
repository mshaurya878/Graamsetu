<?php
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();

// Hash password123 securely with BCRYPT
$secure_hash = password_hash('password123', PASSWORD_BCRYPT);

// Update all existing demo users to use this secure hash
$stmt = $db->prepare("UPDATE users SET password_hash = :hash");
$stmt->execute([':hash' => $secure_hash]);

$count = $stmt->rowCount();
echo "Successfully updated $count user accounts to secure BCRYPT hash for demo password 'password123'.\n";

// Verify with password_verify
$test_user = $db->query("SELECT email, password_hash FROM users WHERE id = 1")->fetch();
if (password_verify('password123', $test_user['password_hash'])) {
    echo "Verification SUCCESS: 'password123' verified for " . $test_user['email'] . "\n";
} else {
    echo "Verification FAILED!\n";
}
