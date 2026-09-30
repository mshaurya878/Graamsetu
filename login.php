<?php
// ============================================================
// login.php — ALL server-side processing BEFORE HTML output
// ============================================================
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = "Login | GraamSetu Portal";

// If already logged in, redirect to respective role dashboard
if (is_logged_in()) {
    $user = current_user();
    if ($user) {
        $redirect = match ($user['role_name']) {
            'admin'    => BASE_URL . '/admin/dashboard.php',
            'merchant' => BASE_URL . '/merchant/dashboard.php',
            'provider' => BASE_URL . '/provider/dashboard.php',
            'citizen'  => BASE_URL . '/citizen/dashboard.php',
            default    => BASE_URL . '/index.php',
        };
        header("Location: $redirect");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf_token)) {
        set_flash('danger', 'Security session token expired or invalid. Please refresh and try again.');
    } elseif (empty($email) || empty($password)) {
        set_flash('danger', 'Please enter both your registered email address and password.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('danger', 'Please provide a valid email format (e.g. name@example.com).');
    } else {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT u.*, r.name AS role_name, r.description AS role_display 
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE LOWER(u.email) = LOWER(:email)
        ");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['status'] === 'suspended') {
                set_flash('danger', 'Access Denied: Your account has been suspended by the Gram Panchayat Administrator.');
            } else {
                login_user($user);

                // Audit log for admin logins
                if ($user['role_name'] === 'admin') {
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                    $log_stmt = $db->prepare("INSERT INTO admin_logs (admin_id, action, details, ip_address) VALUES (?, 'Admin Login', 'Administrator signed into control panel', ?)");
                    $log_stmt->execute([$user['id'], $ip]);
                }

                set_flash('success', 'Welcome back, ' . e($user['full_name']) . '! Signed in as ' . e($user['role_name']) . '.');

                $redirect = match ($user['role_name']) {
                    'admin'    => BASE_URL . '/admin/dashboard.php',
                    'merchant' => BASE_URL . '/merchant/dashboard.php',
                    'provider' => BASE_URL . '/provider/dashboard.php',
                    'citizen'  => BASE_URL . '/citizen/dashboard.php',
                    default    => BASE_URL . '/index.php',
                };
                header("Location: $redirect");
                exit;
            }
        } else {
            set_flash('danger', 'Invalid credentials: Email address or password does not match.');
        }
    }
}

// ============================================================
// HTML OUTPUT — only after all server-side processing is done
// ============================================================
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-7">
            <?= render_flash_messages() ?>

            <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5 bg-white">
                <div class="text-center mb-4">
                    <div class="logo-badge mx-auto mb-2" style="width:50px; height:50px; font-size:1.5rem;">
                        <i class="fa-solid fa-bridge"></i>
                    </div>
                    <h3 class="fw-bold text-dark mb-1">Welcome to GraamSetu</h3>
                    <p class="text-muted small">Sign in to access your role dashboard</p>
                </div>

                <form action="<?= BASE_URL ?>/login.php" method="POST" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-envelope text-muted"></i></span>
                            <input type="email" name="email" id="loginEmail" class="form-control form-control-gs" placeholder="name@example.com" required value="<?= e($_POST['email'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold text-dark">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-lock text-muted"></i></span>
                            <input type="password" name="password" id="loginPassword" class="form-control form-control-gs" placeholder="Enter your account password" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary-gs w-100 py-2 fs-6 mb-3">
                        <i class="fa-solid fa-right-to-bracket me-2"></i> Sign In to Account
                    </button>
                </form>

                <div class="text-center border-top pt-3 mt-2">
                    <p class="small text-muted mb-0">Don't have an account yet? <a href="<?= BASE_URL ?>/register.php" class="text-primary-gs fw-bold text-decoration-none">Create an Account</a></p>
                </div>
            </div>

            <!-- Demo Login Presets Card for Quick Testing & Viva Defense -->
            <div class="card border-0 shadow-sm rounded-4 p-4 mt-4 bg-light border">
                <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-key text-warning me-2"></i> Viva / Demo Quick Sign-in Presets</h6>
                <p class="small text-muted mb-3">Click any button below to populate demo credentials (Password: <code>password123</code>):</p>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-sm btn-outline-dark text-start" onclick="fillDemo('admin@graamsetu.in')">
                        <i class="fa-solid fa-user-shield me-2 text-danger"></i> <strong>Panchayat Admin:</strong> Rajesh Kumar Singh (admin@graamsetu.in)
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-dark text-start" onclick="fillDemo('ramesh@gmail.com')">
                        <i class="fa-solid fa-user me-2 text-primary"></i> <strong>Rural Citizen:</strong> Ramesh Sahani (ramesh@gmail.com)
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-dark text-start" onclick="fillDemo('mahesh@kirana.in')">
                        <i class="fa-solid fa-shop me-2 text-success"></i> <strong>Local Merchant:</strong> Mahesh Kirana Store (mahesh@kirana.in)
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-dark text-start" onclick="fillDemo('shiv.electric@gmail.com')">
                        <i class="fa-solid fa-bolt me-2 text-warning"></i> <strong>Service Provider:</strong> Shiv Kumar Electrician (shiv.electric@gmail.com)
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function fillDemo(email) {
    document.getElementById('loginEmail').value = email;
    document.getElementById('loginPassword').value = 'password123';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
