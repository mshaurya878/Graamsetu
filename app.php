<?php
// GraamSetu Application Configuration

if (!defined('APP_NAME')) {
    define('APP_NAME', 'GraamSetu');
}
if (!defined('APP_TAGLINE')) {
    define('APP_TAGLINE', 'Connecting Rural India - Digital Services Platform');
}

// Base URL detection — works under XAMPP subfolder AND PHP built-in server
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';

if (!defined('BASE_URL')) {
    // Compute app's web path by comparing filesystem root against DOCUMENT_ROOT
    $doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $app_root  = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/'); // parent of /config
    if ($doc_root && str_starts_with($app_root, $doc_root)) {
        $web_path = substr($app_root, strlen($doc_root));
    } else {
        $web_path = '';
    }
    define('BASE_URL', rtrim($protocol . '://' . $host . $web_path, '/'));
}

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}
