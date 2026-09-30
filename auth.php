<?php
// GraamSetu Authentication & Role Middleware

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    
    // Always ensure the user account is still valid and active in database
    $db = Database::getConnection();
    $stmt = $db->prepare("
        SELECT u.*, r.name AS role_name, r.description AS role_display 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.id = :id
    ");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user && $user['status'] === 'active') {
        $_SESSION['user_data'] = $user;
        $_SESSION['role_name'] = $user['role_name'];
        $_SESSION['user_name'] = $user['full_name'];
        return $user;
    } else {
        // If user was suspended or deleted, terminate session immediately
        logout_user();
        return null;
    }
}

function has_role(string|array $roles): bool {
    $user = current_user();
    if (!$user) {
        return false;
    }
    if (is_string($roles)) {
        $roles = [$roles];
    }
    return in_array($user['role_name'], $roles, true);
}

function require_login(): void {
    if (!is_logged_in() || !current_user()) {
        set_flash('warning', 'Please login to access your account dashboard.');
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function require_role(string|array $allowed_roles): void {
    require_login();
    $user = current_user();
    
    if (is_string($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }

    if (!$user || !in_array($user['role_name'], $allowed_roles, true)) {
        set_flash('danger', 'Unauthorized access! You do not have permission to view that page.');
        
        // Redirect to appropriate dashboard based on actual role
        $role = $user['role_name'] ?? '';
        $redirect = match ($role) {
            'admin' => BASE_URL . '/admin/dashboard.php',
            'merchant' => BASE_URL . '/merchant/dashboard.php',
            'provider' => BASE_URL . '/provider/dashboard.php',
            'citizen' => BASE_URL . '/citizen/dashboard.php',
            default => BASE_URL . '/login.php',
        };
        header('Location: ' . $redirect);
        exit;
    }
}

function login_user(array $user): void {
    // Regenerate session ID to prevent session fixation attacks
    if (!headers_sent()) {
        session_regenerate_id(true);
    }
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['role_name'] = $user['role_name'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_data'] = $user;
}

function logout_user(): void {
    $_SESSION = [];
    if (!headers_sent() && ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
