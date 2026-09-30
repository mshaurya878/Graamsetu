<?php
$page_title = "Technician Profile & Settings | GraamSetu Provider";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('provider');
$user = current_user();
$db = Database::getConnection();

// Fetch provider profile with location
$stmt = $db->prepare("
    SELECT sp.*, l.village_name, l.gram_panchayat, l.district, l.pincode
    FROM service_providers sp
    LEFT JOIN locations l ON sp.location_id = l.id
    WHERE sp.user_id = :uid
");
$stmt->execute([':uid' => $user['id']]);
$provider = $stmt->fetch();

if (!$provider) {
    die("Service provider profile missing. Please contact Gram Panchayat Administrator.");
}

$categories = $db->query("SELECT * FROM categories WHERE type = 'service' ORDER BY name ASC")->fetchAll();
$locations = $db->query("SELECT * FROM locations ORDER BY village_name ASC")->fetchAll();

// 1. Handle Profile & Credentials Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = preg_replace('/[^0-9]/', '', trim($_POST['phone'] ?? ''));
        $cat_id = (int)($_POST['service_category_id'] ?? $provider['service_category_id']);
        $exp = max(0, (int)($_POST['experience_years'] ?? 0));
        $location_id = (int)($_POST['location_id'] ?? ($provider['location_id'] ?? 1));
        $address = trim($_POST['address'] ?? '');

        if (empty($full_name) || empty($phone)) {
            set_flash('danger', 'Full name and mobile number are required.');
        } elseif (!preg_match('/^[6-9]\d{9}$/', $phone)) {
            set_flash('danger', 'Please enter a valid 10-digit Indian mobile number.');
        } else {
            // Update User
            $upd_user = $db->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
            $upd_user->execute([$full_name, $phone, $user['id']]);

            // Update Provider Profile (preserving admin-controlled verification)
            $upd_prov = $db->prepare("
                UPDATE service_providers 
                SET service_category_id = ?, experience_years = ?, location_id = ?, address = ?
                WHERE user_id = ?
            ");
            $upd_prov->execute([$cat_id, $exp, $location_id, $address, $user['id']]);

            unset($_SESSION['user_data']);
            set_flash('success', 'Technician credentials & workshop details updated!');
            header('Location: ' . BASE_URL . '/provider/profile.php');
            exit;
        }
    } else {
        set_flash('danger', 'Security session expired. Please submit again.');
    }
}

// 2. Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $current_pass = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';

        // Fetch fresh password hash
        $pwd_stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $pwd_stmt->execute([$user['id']]);
        $current_hash = $pwd_stmt->fetchColumn();

        if (!password_verify($current_pass, $current_hash)) {
            set_flash('danger', 'Current password entered does not match our records.');
        } elseif (strlen($new_pass) < 6) {
            set_flash('danger', 'New password must be at least 6 characters long.');
        } elseif ($new_pass !== $confirm_pass) {
            set_flash('danger', 'New password confirmation does not match.');
        } else {
            $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $upd_pass = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $upd_pass->execute([$new_hash, $user['id']]);

            set_flash('success', 'Your password has been changed securely. Use your new password on next sign in.');
            header('Location: ' . BASE_URL . '/provider/profile.php');
            exit;
        }
    } else {
        set_flash('danger', 'Security session expired. Please submit again.');
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="dashboard-wrapper">
    <!-- Sidebar -->
    <div class="dashboard-sidebar">
        <div class="sidebar-heading">Technician Portal</div>
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/provider/dashboard.php" class="sidebar-link"><i class="fa-solid fa-gauge"></i> Overview</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/provider/requests.php" class="sidebar-link"><i class="fa-solid fa-list-check"></i> Job Requests</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/provider/services.php" class="sidebar-link"><i class="fa-solid fa-screwdriver-wrench"></i> Services & Hourly Rates</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/provider/profile.php" class="sidebar-link active"><i class="fa-solid fa-user-gear"></i> Bio & Credentials</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="dashboard-content">
        <?= render_flash_messages() ?>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Verification Status Card (Read-only, Admin controlled) -->
                <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white border-start border-4 <?= $provider['verification_status'] === 'verified' ? 'border-success' : 'border-warning' ?>">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Gram Panchayat Skill Verification</span>
                            <div class="d-flex align-items-center gap-2 mt-1">
                                <h5 class="fw-bold text-dark mb-0"><?= ucfirst(e($provider['verification_status'])) ?> Technician</h5>
                                <?= get_status_badge($provider['verification_status']) ?>
                            </div>
                            <p class="small text-muted mb-0 mt-1">
                                <?= $provider['verification_status'] === 'verified'
                                    ? 'Your technician credentials and trade skills are verified by the Gram Panchayat. You appear prominently in citizen service searches.'
                                    : 'Your technician trade profile is currently under review by the Gram Panchayat administration.' ?>
                            </p>
                        </div>
                        <div class="fs-1 <?= $provider['verification_status'] === 'verified' ? 'text-success' : 'text-warning' ?>">
                            <i class="fa-solid <?= $provider['verification_status'] === 'verified' ? 'fa-award' : 'fa-clock' ?>"></i>
                        </div>
                    </div>
                </div>

                <!-- Technician Details Form -->
                <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
                    <h4 class="fw-bold text-dark mb-1"><i class="fa-solid fa-user-gear text-primary-gs me-2"></i> Technician Profile & Credentials</h4>
                    <p class="text-muted small mb-4">Manage your public contact details, trade specialty, and village location.</p>

                    <form action="<?= BASE_URL ?>/provider/profile.php" method="POST" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="update_profile" value="1">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Full Name *</label>
                                <input type="text" name="full_name" class="form-control form-control-gs" value="<?= e($user['full_name']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Email Address (Login ID)</label>
                                <input type="email" class="form-control form-control-gs bg-light" value="<?= e($user['email']) ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Contact Phone (10 Digits) *</label>
                                <input type="tel" name="phone" class="form-control form-control-gs" value="<?= e($user['phone']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Primary Trade / Specialty *</label>
                                <select name="service_category_id" class="form-select form-select-gs" required>
                                    <?php foreach ($categories as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= $provider['service_category_id'] == $c['id'] ? 'selected' : '' ?>>
                                            <?= e($c['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Years of Trade Experience *</label>
                                <input type="number" name="experience_years" class="form-control form-control-gs" value="<?= (int)$provider['experience_years'] ?>" min="0" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Base Village / Panchayat *</label>
                                <select name="location_id" class="form-select form-select-gs" required>
                                    <?php foreach ($locations as $loc): ?>
                                        <option value="<?= $loc['id'] ?>" <?= ($provider['location_id'] ?? 1) == $loc['id'] ? 'selected' : '' ?>>
                                            <?= e($loc['village_name']) ?> (GP: <?= e($loc['gram_panchayat']) ?>, Dist: <?= e($loc['district']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold text-dark">Workshop / Service Station Address</label>
                                <input type="text" name="address" class="form-control form-control-gs" value="<?= e($provider['address'] ?? '') ?>" placeholder="e.g. Near Bus Stand, Main Market">
                            </div>
                            <div class="col-12 mt-4 pt-2 border-top">
                                <button type="submit" class="btn btn-primary-gs px-4 py-2">
                                    <i class="fa-solid fa-floppy-disk me-1"></i> Save Profile Details
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Password Change Card -->
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                    <h5 class="fw-bold text-dark mb-1"><i class="fa-solid fa-lock text-warning me-2"></i> Security & Change Password</h5>
                    <p class="text-muted small mb-4">Ensure your account is protected with a secure password.</p>

                    <form action="<?= BASE_URL ?>/provider/profile.php" method="POST" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="change_password" value="1">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold text-dark">Current Password *</label>
                                <input type="password" name="current_password" class="form-control form-control-gs" placeholder="Enter current password" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">New Password *</label>
                                <input type="password" name="new_password" class="form-control form-control-gs" placeholder="Min 6 characters" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Confirm New Password *</label>
                                <input type="password" name="confirm_password" class="form-control form-control-gs" placeholder="Re-type new password" required>
                            </div>
                            <div class="col-12 mt-4 pt-2 border-top">
                                <button type="submit" class="btn btn-outline-dark px-4 py-2">
                                    <i class="fa-solid fa-key me-1"></i> Update Password
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>