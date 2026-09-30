<?php
// ============================================================
// register.php — ALL server-side processing BEFORE HTML output
// ============================================================
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = "Register Account | GraamSetu";

$db = Database::getConnection();

// Fetch categories for dropdowns
$merchant_categories = $db->query("SELECT * FROM categories WHERE type = 'business' ORDER BY name ASC")->fetchAll();
$provider_categories = $db->query("SELECT * FROM categories WHERE type = 'service' ORDER BY name ASC")->fetchAll();

// Fetch locations
$locations = $db->query("SELECT * FROM locations ORDER BY village_name ASC")->fetchAll();

$selected_role = trim($_GET['role'] ?? ($_POST['role_name'] ?? 'citizen'));
if (!in_array($selected_role, ['citizen', 'merchant', 'provider', 'admin'], true)) {
    $selected_role = 'citizen';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role_name        = trim($_POST['role_name'] ?? 'citizen');
    $full_name        = trim($_POST['full_name'] ?? '');
    $email            = strtolower(trim($_POST['email'] ?? ''));
    $phone            = preg_replace('/[^0-9]/', '', trim($_POST['phone'] ?? ''));
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $location_id      = (int)($_POST['location_id'] ?? ($locations[0]['id'] ?? 1));
    $csrf_token       = $_POST['csrf_token'] ?? '';

    // 1. CSRF Verification
    if (!verify_csrf_token($csrf_token)) {
        set_flash('danger', 'Security session token expired or invalid. Please submit the form again.');

    // 2. Base Mandatory Fields
    } elseif (empty($full_name) || empty($email) || empty($phone) || empty($password)) {
        set_flash('danger', 'Please complete all required fields marked with an asterisk (*).');
    } elseif (strlen($full_name) < 3) {
        set_flash('danger', 'Full name must contain at least 3 characters.');

    // 3. Email Format Validation
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('danger', 'Please enter a valid email address (e.g. yourname@example.com).');

    // 4. Phone Number Validation (Standard 10-digit Indian Mobile)
    } elseif (!preg_match('/^[6-9]\d{9}$/', $phone)) {
        set_flash('danger', 'Please enter a valid 10-digit Indian mobile number starting with 6, 7, 8, or 9.');

    // 5. Password Security & Confirmation
    } elseif (strlen($password) < 6) {
        set_flash('danger', 'Account password must be at least 6 characters long for security.');
    } elseif ($password !== $confirm_password) {
        set_flash('danger', 'Password confirmation does not match. Please re-enter both password fields.');

    } else {
        // 6. Check Email & Phone Uniqueness
        $stmt = $db->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(:email) OR phone = :phone");
        $stmt->execute([':email' => $email, ':phone' => $phone]);
        if ($stmt->fetch()) {
            set_flash('danger', 'An account with this email address or mobile number already exists. Please login instead.');
        } else {
            // 7. Role-Specific Validations
            $role_valid = true;

            if ($role_name === 'admin') {
                $passkey = trim($_POST['admin_passkey'] ?? '');
                if ($passkey !== 'PANCHAYAT2026' && $passkey !== 'SARPANCH2026') {
                    set_flash('danger', 'Invalid Gram Panchayat Administrator Passkey. Admin registration is restricted.');
                    $role_valid = false;
                }
            } elseif ($role_name === 'merchant') {
                $biz_name = trim($_POST['business_name'] ?? '');
                $address  = trim($_POST['address'] ?? '');
                if (empty($biz_name) || strlen($biz_name) < 3) {
                    set_flash('danger', 'Please provide a valid Business / Shop name (at least 3 characters).');
                    $role_valid = false;
                } elseif (empty($address)) {
                    set_flash('danger', 'Please enter your shop physical address.');
                    $role_valid = false;
                }
            } elseif ($role_name === 'provider') {
                $address = trim($_POST['address'] ?? '');
                $rate    = (float)($_POST['hourly_rate'] ?? 0);
                if (empty($address)) {
                    set_flash('danger', 'Please enter your work address / village.');
                    $role_valid = false;
                } elseif ($rate <= 0) {
                    set_flash('danger', 'Please specify a valid positive hourly rate.');
                    $role_valid = false;
                }
            }

            // 8. Transactional User Creation
            if ($role_valid) {
                $role_stmt = $db->prepare("SELECT id FROM roles WHERE name = :name");
                $role_stmt->execute([':name' => $role_name]);
                $role_row = $role_stmt->fetch();
                $role_id  = $role_row['id'] ?? 1;

                $pass_hash = password_hash($password, PASSWORD_BCRYPT);

                $db->beginTransaction();
                try {
                    // Insert into Users table
                    $ins_user = $db->prepare("
                        INSERT INTO users (role_id, full_name, email, phone, password_hash, status)
                        VALUES (:role_id, :full_name, :email, :phone, :pass_hash, 'active')
                    ");
                    $ins_user->execute([
                        ':role_id'   => $role_id,
                        ':full_name' => $full_name,
                        ':email'     => $email,
                        ':phone'     => $phone,
                        ':pass_hash' => $pass_hash,
                    ]);
                    $user_id = $db->lastInsertId();

                    // Insert role-specific profile data
                    if ($role_name === 'citizen') {
                        $ration_no = trim($_POST['ration_card_no'] ?? '');
                        $address   = trim($_POST['address'] ?? '');
                        $db->prepare("INSERT INTO citizen_profiles (user_id, ration_card_no, address, location_id) VALUES (?, ?, ?, ?)")
                           ->execute([$user_id, $ration_no, $address, $location_id]);

                    } elseif ($role_name === 'merchant') {
                        $biz_name = trim($_POST['business_name'] ?? "$full_name Store");
                        $cat_id   = (int)($_POST['merchant_category_id'] ?? ($merchant_categories[0]['id'] ?? 1));
                        $address  = trim($_POST['address'] ?? 'Main Market');
                        $hours    = trim($_POST['opening_hours'] ?? '08:00 AM - 08:00 PM');

                        $db->prepare("
                            INSERT INTO merchant_profiles (user_id, business_name, category_id, location_id, address, contact_phone, opening_hours, is_verified, verification_status)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'pending')
                        ")->execute([$user_id, $biz_name, $cat_id, $location_id, $address, $phone, $hours]);

                    } elseif ($role_name === 'provider') {
                        $cat_id  = (int)($_POST['provider_category_id'] ?? ($provider_categories[0]['id'] ?? 5));
                        $exp     = max(0, (int)($_POST['experience_years'] ?? 1));
                        $rate    = (float)($_POST['hourly_rate'] ?? 200.00);
                        $address = trim($_POST['address'] ?? 'Village Center');

                        $db->prepare("
                            INSERT INTO service_providers (user_id, service_category_id, experience_years, hourly_rate, address, location_id, is_available, is_verified, verification_status)
                            VALUES (?, ?, ?, ?, ?, ?, 1, 0, 'pending')
                        ")->execute([$user_id, $cat_id, $exp, $rate, $address, $location_id]);

                    } elseif ($role_name === 'admin') {
                        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                        $db->prepare("INSERT INTO admin_logs (admin_id, action, details, ip_address) VALUES (?, 'Admin Registered', 'New Administrator account created with authorized passkey', ?)")
                           ->execute([$user_id, $ip]);
                    }

                    $db->commit();

                    set_flash('success', 'Registration successful! Your ' . ucfirst($role_name) . ' account is ready. Please sign in.');
                    header('Location: ' . BASE_URL . '/login.php');
                    exit;

                } catch (Exception $e) {
                    $db->rollBack();
                    set_flash('danger', 'Registration error occurred. Please try again.');
                    error_log('GraamSetu register error: ' . $e->getMessage());
                }
            }
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
        <div class="col-lg-8">
            <?= render_flash_messages() ?>

            <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5 bg-white">
                <div class="text-center mb-4">
                    <div class="logo-badge mx-auto mb-2" style="width:46px; height:46px; font-size:1.3rem;">
                        <i class="fa-solid fa-bridge"></i>
                    </div>
                    <h2 class="fw-bold text-dark mb-1">Create GraamSetu Account</h2>
                    <p class="text-muted small">Select your role to join your village digital network</p>

                    <!-- Role Selector Tabs -->
                    <div class="btn-group w-100 mt-3" role="group">
                        <a href="<?= BASE_URL ?>/register.php?role=citizen" class="btn <?= $selected_role === 'citizen' ? 'btn-primary-gs' : 'btn-outline-secondary' ?>">
                            <i class="fa-solid fa-user me-1"></i> Citizen
                        </a>
                        <a href="<?= BASE_URL ?>/register.php?role=merchant" class="btn <?= $selected_role === 'merchant' ? 'btn-secondary-gs' : 'btn-outline-secondary' ?>">
                            <i class="fa-solid fa-shop me-1"></i> Merchant
                        </a>
                        <a href="<?= BASE_URL ?>/register.php?role=provider" class="btn <?= $selected_role === 'provider' ? 'btn-warning text-dark fw-bold' : 'btn-outline-secondary' ?>">
                            <i class="fa-solid fa-wrench me-1"></i> Technician
                        </a>
                        <a href="<?= BASE_URL ?>/register.php?role=admin" class="btn <?= $selected_role === 'admin' ? 'btn-danger' : 'btn-outline-secondary' ?>">
                            <i class="fa-solid fa-user-shield me-1"></i> Admin
                        </a>
                    </div>
                </div>

                <form action="<?= BASE_URL ?>/register.php?role=<?= e($selected_role) ?>" method="POST" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="role_name" value="<?= e($selected_role) ?>">

                    <div class="row g-3 mb-4">
                        <div class="col-12"><h5 class="fw-bold text-dark border-bottom pb-2">1. Personal &amp; Account Credentials</h5></div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Full Name *</label>
                            <input type="text" name="full_name" class="form-control form-control-gs" placeholder="e.g. Ramesh Sahani" required value="<?= e($_POST['full_name'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Mobile Number (10 Digits) *</label>
                            <input type="tel" name="phone" class="form-control form-control-gs" placeholder="e.g. 9826011223" required value="<?= e($_POST['phone'] ?? '') ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold text-dark">Email Address (Login Username) *</label>
                            <input type="email" name="email" class="form-control form-control-gs" placeholder="name@example.com" required value="<?= e($_POST['email'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Account Password *</label>
                            <input type="password" name="password" class="form-control form-control-gs" placeholder="Min 6 characters" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Confirm Password *</label>
                            <input type="password" name="confirm_password" class="form-control form-control-gs" placeholder="Re-enter password" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold text-dark">Select Village / Gram Panchayat *</label>
                            <select name="location_id" class="form-select form-select-gs" required>
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?= $loc['id'] ?>" <?= (isset($_POST['location_id']) && $_POST['location_id'] == $loc['id']) ? 'selected' : '' ?>>
                                        <?= e($loc['village_name']) ?> (GP: <?= e($loc['gram_panchayat']) ?>, Dist: <?= e($loc['district']) ?> - <?= e($loc['pincode']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Role-Specific Fields -->
                    <?php if ($selected_role === 'citizen'): ?>
                        <div class="row g-3 mb-4">
                            <div class="col-12"><h5 class="fw-bold text-dark border-bottom pb-2">2. Citizen Profile Details</h5></div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Ration Card Number</label>
                                <input type="text" name="ration_card_no" class="form-control form-control-gs" placeholder="e.g. RC-10293847" value="<?= e($_POST['ration_card_no'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Residential Address</label>
                                <input type="text" name="address" class="form-control form-control-gs" placeholder="e.g. Ward No. 4, Near Primary School" value="<?= e($_POST['address'] ?? '') ?>">
                            </div>
                        </div>

                    <?php elseif ($selected_role === 'merchant'): ?>
                        <div class="row g-3 mb-4">
                            <div class="col-12"><h5 class="fw-bold text-dark border-bottom pb-2">2. Business &amp; Store Listing Details</h5></div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Business / Shop Name *</label>
                                <input type="text" name="business_name" class="form-control form-control-gs" placeholder="e.g. Mahesh Kirana &amp; General Store" required value="<?= e($_POST['business_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Business Category *</label>
                                <select name="merchant_category_id" class="form-select form-select-gs" required>
                                    <?php foreach ($merchant_categories as $mc): ?>
                                        <option value="<?= $mc['id'] ?>" <?= (isset($_POST['merchant_category_id']) && $_POST['merchant_category_id'] == $mc['id']) ? 'selected' : '' ?>>
                                            <?= e($mc['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Shop Physical Address *</label>
                                <input type="text" name="address" class="form-control form-control-gs" placeholder="e.g. Main Market, Opp Panchayat Bhavan" required value="<?= e($_POST['address'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Opening Hours</label>
                                <input type="text" name="opening_hours" class="form-control form-control-gs" value="<?= e($_POST['opening_hours'] ?? '08:00 AM - 08:00 PM') ?>">
                            </div>
                        </div>

                    <?php elseif ($selected_role === 'provider'): ?>
                        <div class="row g-3 mb-4">
                            <div class="col-12"><h5 class="fw-bold text-dark border-bottom pb-2">2. Technician Skill &amp; Service Details</h5></div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Service Category *</label>
                                <select name="provider_category_id" class="form-select form-select-gs" required>
                                    <?php foreach ($provider_categories as $pc): ?>
                                        <option value="<?= $pc['id'] ?>" <?= (isset($_POST['provider_category_id']) && $_POST['provider_category_id'] == $pc['id']) ? 'selected' : '' ?>>
                                            <?= e($pc['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-dark">Experience (Years)</label>
                                <input type="number" name="experience_years" class="form-control form-control-gs" value="<?= e($_POST['experience_years'] ?? '2') ?>" min="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-dark">Hourly Rate (₹) *</label>
                                <input type="number" name="hourly_rate" class="form-control form-control-gs" value="<?= e($_POST['hourly_rate'] ?? '200') ?>" min="10" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold text-dark">Work Base Address *</label>
                                <input type="text" name="address" class="form-control form-control-gs" placeholder="e.g. Bihta Road, Near Electric Substation" required value="<?= e($_POST['address'] ?? '') ?>">
                            </div>
                        </div>

                    <?php elseif ($selected_role === 'admin'): ?>
                        <div class="row g-3 mb-4">
                            <div class="col-12"><h5 class="fw-bold text-dark border-bottom pb-2 text-danger"><i class="fa-solid fa-shield-halved me-1"></i> 2. Administrator Authorization</h5></div>
                            <div class="col-12">
                                <label class="form-label fw-semibold text-dark">Gram Panchayat Admin Security Passkey *</label>
                                <input type="password" name="admin_passkey" class="form-control form-control-gs border-danger" placeholder="Enter official passkey (Default: PANCHAYAT2026)" required>
                                <small class="text-muted"><i class="fa-solid fa-circle-info text-primary me-1"></i> For testing and evaluation, the authorized passkey is <code>PANCHAYAT2026</code>.</small>
                            </div>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn <?= $selected_role === 'admin' ? 'btn-danger' : 'btn-primary-gs' ?> w-100 py-3 fs-6">
                        <i class="fa-solid fa-user-check me-2"></i> Register <?= ucfirst($selected_role) ?> Account
                    </button>
                </form>

                <div class="text-center border-top pt-3 mt-4">
                    <p class="small text-muted mb-0">Already registered? <a href="<?= BASE_URL ?>/login.php" class="text-primary-gs fw-bold text-decoration-none">Sign In Here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
