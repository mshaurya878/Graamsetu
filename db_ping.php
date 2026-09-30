<?php
// Test multiple host variants for MySQL connectivity
$hosts = ['127.0.0.1', '::1', 'localhost'];
foreach ($hosts as $host) {
    try {
        $pdo = new PDO("mysql:host=$host;port=3306;dbname=graamsetu_db", 'root', '');
        $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        echo "Connected via '$host' — Users in DB: $count\n";
    } catch (Exception $e) {
        echo "Failed '$host': " . $e->getMessage() . "\n";
    }
}
