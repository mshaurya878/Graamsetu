<?php
$page_title = "Manage Users | GraamSetu Admin";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');
$user = current_user();
$db = Database::getConnection();

// 1. Handle Status Toggle (Suspend / Activate)
if (isset($_GET['toggle_status'])) {
    $uid = (int)$_GET['toggle_status'];
    $stmt = $db->prepare("SELECT id, status, full_name, email FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $u_data = $stmt->fetch();

    if ($u_data) {
        if ($uid === $user['id']) {
            set_flash('danger', 'Security Restriction: Administrators cannot suspend their own account.');
        } else {
            $new_status = $u_data['status'] === 'active' ? 'suspended' : 'active';
            $upd = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
            $upd->execute([$new_status, $uid]);

            // Audit Log
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $db->prepare("INSERT INTO admin_logs (admin_id, action, target_table, target_id, details, ip_address) VALUES (?, 'User Status Change', 'users', ?, ?, ?)")
               ->execute([$user['id'], $uid, "Updated account status of {$u_data['full_name']} ({$u_data['email']}) to $new_status", $ip]);

            set_flash('success', "Account status of {$u_data['full_name']} changed to " . ucfirst($new_status) . ".");
        }
    }
    header('Location: ' . BASE_URL . '/admin/users.php');
    exit;
}

// 2. Handle Add / Provision New User by Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = preg_replace('/[^0-9]/', '', trim($_POST['phone'] ?? ''));
        $password = $_POST['password'] ?? '';
        $role_id = (int)($_POST['role_id'] ?? 1);
        $location_id = (int)($_POST['location_id'] ?? 1);

        if (empty($full_name) || empty($email) || empty($phone) || empty($password)) {
            set_flash('danger', 'All user fields marked with an asterisk are required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('danger', 'Invalid email address format.');
        } elseif (!preg_match('/^[6-9]\d{9}$/', $phone)) {
            set_flash('danger', 'Mobile number must be a valid 10-digit Indian number.');
        } elseif (strlen($password) < 6) {
            set_flash('danger', 'Password must be at least 6 characters.');
        } else {
            // Uniqueness check
            $chk = $db->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?) OR phone = ?");
            $chk->execute([$email, $phone]);
            if ($chk->fetch()) {
                set_flash('danger', 'A user with this email or phone number already exists.');
            } else {
                $pass_hash = password_hash($password, PASSWORD_BCRYPT);
                $db->beginTransaction();
                try {
                    $ins = $db->prepare("
                        INSERT INTO users (role_id, full_name, email, phone, password_hash, status)
                        VALUES (?, ?, ?, ?, ?, 'active')
                    ");
                    $ins->execute([$role_id, $full_name, $email, $phone, $pass_hash]);
                    $new_id = (int)$db->lastInsertId();

                    // Insert corresponding profile
                    if ($role_id === 1) { // citizen
                        $db->prepare("INSERT INTO citizen_profiles (user_id, ration_card_no, address, location_id) VALUES (?, '', 'Panchayat Ward', ?)")
                           ->execute([$new_id, $location_id]);
                    } elseif ($role_id === 2) { // merchant
                        $db->prepare("INSERT INTO merchant_profiles (user_id, business_name, category_id, location_id, address, contact_phone, is_verified, verification_status) VALUES (?, ?, 1, ?, 'Main Market', ?, 1, 'verified')")
                           ->execute([$new_id, "$full_name Store", $location_id, $phone]);
                    } elseif ($role_id === 3) { // provider
                        $db->prepare("INSERT INTO service_providers (user_id, service_category_id, experience_years, hourly_rate, address, location_id, is_available, is_verified, verification_status) VALUES (?, 5, 2, 250.00, 'Village Center', ?, 1, 1, 'verified')")
                           ->execute([$new_id, $location_id]);
                    }

                    // Audit Log
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                    $db->prepare("INSERT INTO admin_logs (admin_id, action, target_table, target_id, details, ip_address) VALUES (?, 'Provisioned User', 'users', ?, ?, ?)")
                       ->execute([$user['id'], $new_id, "Admin created account for $full_name ($email)", $ip]);

                    $db->commit();
                    set_flash('success', "Account for $full_name provisioned successfully.");
                    header('Location: ' . BASE_URL . '/admin/users.php');
                    exit;
                } catch (Exception $e) {
                    $db->rollBack();
                    set_flash('danger', 'Failed to provision user: ' . e($e->getMessage()));
                }
            }
        }
    } else {
        set_flash('danger', 'Security session expired. Please submit again.');
    }
}

// 3. Search & Filter Parameters (Database-driven)
$search_q = trim($_GET['q'] ?? '');
$role_filter = (int)($_GET['role_id'] ?? 0);
$status_filter = trim($_GET['status'] ?? 'all');

$query_sql = "
    SELECT u.*, r.name as role_name, r.description as role_desc,
           COALESCE(lc.village_name, lm.village_name, lp.village_name, 'Panchayat Area') as village_name
    FROM users u
    JOIN roles r ON u.role_id = r.id
    LEFT JOIN citizen_profiles cp ON u.id = cp.user_id
    LEFT JOIN locations lc ON cp.location_id = lc.id
    LEFT JOIN merchant_profiles mp ON u.id = mp.user_id
    LEFT JOIN locations lm ON mp.location_id = lm.id
    LEFT JOIN service_providers sp ON u.id = sp.user_id
    LEFT JOIN locations lp ON sp.location_id = lp.id
    WHERE 1=1
";
$query_params = [];

if ($role_filter > 0) {
    $query_sql .= " AND u.role_id = :role_id";
    $query_params[':role_id'] = $role_filter;
}

if ($status_filter === 'active' || $status_filter === 'suspended') {
    $query_sql .= " AND u.status = :status";
    $query_params[':status'] = $status_filter;
}

if (!empty($search_q)) {
    $query_sql .= " AND (u.full_name LIKE :q OR u.email LIKE :q2 OR u.phone LIKE :q3)";
    $query_params[':q'] = "%$search_q%";
    $query_params[':q2'] = "%$search_q%";
    $query_params[':q3'] = "%$search_q%";
}

$query_sql .= " ORDER BY u.id ASC";
$stmt = $db->prepare($query_sql);
$stmt->execute($query_params);
$users_list = $stmt->fetchAll();

// Dropdowns for Modal
$roles = $db->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();
$locations = $db->query("SELECT * FROM locations ORDER BY village_name ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="dashboard-wrapper">
    <!-- Sidebar -->
    <div class="dashboard-sidebar">
        <div class="sidebar-heading">Admin Control Center</div>
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/admin/dashboard.php" class="sidebar-link"><i class="fa-solid fa-chart-pie"></i> Control Panel</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/admin/users.php" class="sidebar-link active"><i class="fa-solid fa-users"></i> Manage Citizens</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/admin/merchants.php" class="sidebar-link"><i class="fa-solid fa-shop"></i> Verified Merchants</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/admin/providers.php" class="sidebar-link"><i class="fa-solid fa-screwdriver-wrench"></i> Service Providers</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/admin/complaints.php" class="sidebar-link"><i class="fa-solid fa-triangle-exclamation"></i> Grievances</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/admin/requests.php" class="sidebar-link"><i class="fa-solid fa-list-check"></i> Service Requests</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/admin/announcements.php" class="sidebar-link"><i class="fa-solid fa-bullhorn"></i> Village Notices</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/admin/categories.php" class="sidebar-link"><i class="fa-solid fa-tags"></i> Categories</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/admin/settings.php" class="sidebar-link"><i class="fa-solid fa-sliders"></i> Audit Logs</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="dashboard-content">
        <?= render_flash_messages() ?>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1"><i class="fa-solid fa-users text-primary-gs me-2"></i> User Directory & Access Control</h3>
                <p class="text-muted small mb-0">Manage registered citizens, merchants, technicians, and platform administrators.</p>
            </div>
            <button class="btn btn-primary-gs btn-sm" data-bs-toggle="modal" data-bs-target="#createUserModal">
                <i class="fa-solid fa-user-plus me-1"></i> Provision New User
            </button>
        </div>

        <!-- Filter & Search Controls Card -->
        <div class="card border-0 shadow-sm rounded-4 p-3 mb-4 bg-white">
            <form action="<?= BASE_URL ?>/admin/users.php" method="GET" class="row g-3 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" name="q" class="form-control form-control-gs" placeholder="Search by name, email, or phone..." value="<?= e($search_q) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="role_id" class="form-select form-select-gs" onchange="this.form.submit()">
                        <option value="0">All Roles</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= $role_filter == $r['id'] ? 'selected' : '' ?>><?= e($r['description']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-gs" onchange="this.form.submit()">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active Only</option>
                        <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended Only</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary-gs btn-sm w-100">Filter</button>
                    <?php if (!empty($search_q) || $role_filter > 0 || $status_filter !== 'all'): ?>
                        <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>User #ID</th>
                                <th>User Details</th>
                                <th>Role</th>
                                <th>Village Location</th>
                                <th>Account Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users_list as $u): ?>
                                <tr>
                                    <td><strong>#USR-<?= $u['id'] ?></strong></td>
                                    <td>
                                        <strong class="text-dark d-block"><?= e($u['full_name']) ?></strong>
                                        <small class="text-muted">
                                            <i class="fa-solid fa-envelope me-1"></i><?= e($u['email']) ?> â€¢ 
                                            <i class="fa-solid fa-phone ms-1 me-1"></i><?= e($u['phone']) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge <?= match($u['role_name']) {
                                            'admin' => 'bg-danger',
                                            'merchant' => 'bg-success',
                                            'provider' => 'bg-warning text-dark',
                                            default => 'bg-primary-gs'
                                        } ?>">
                                            <?= e($u['role_desc']) ?>
                                        </span>
                                    </td>
                                    <td><small class="text-dark"><i class="fa-solid fa-location-dot text-danger me-1"></i> <?= e($u['village_name']) ?></small></td>
                                    <td>
                                        <span class="badge <?= $u['status'] === 'active' ? 'bg-success' : 'bg-danger' ?>">
                                            <?= ucfirst(e($u['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($u['id'] !== $user['id']): ?>
                                            <a href="<?= BASE_URL ?>/admin/users.php?toggle_status=<?= $u['id'] ?>" 
                                               class="btn btn-sm <?= $u['status'] === 'active' ? 'btn-outline-danger' : 'btn-outline-success' ?> py-1 px-2"
                                               onclick="return confirm('<?= $u['status'] === 'active' ? 'Suspend access for this user? They will be immediately blocked.' : 'Reactivate account for this user?' ?>')">
                                                <i class="fa-solid <?= $u['status'] === 'active' ? 'fa-ban' : 'fa-check' ?> me-1"></i>
                                                <?= $u['status'] === 'active' ? 'Suspend' : 'Activate' ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border">Current User</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Provision New User -->
<div class="modal fade" id="createUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Provision New User Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>/admin/users.php" method="POST" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="create_user" value="1">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Full Name *</label>
                            <input type="text" name="full_name" class="form-control form-control-gs" placeholder="e.g. Ramesh Sahani" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Email Address *</label>
                            <input type="email" name="email" class="form-control form-control-gs" placeholder="e.g. ramesh@gmail.com" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Mobile Number *</label>
                            <input type="tel" name="phone" class="form-control form-control-gs" placeholder="10-digit mobile" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Initial Password *</label>
                            <input type="password" name="password" class="form-control form-control-gs" placeholder="Min 6 characters" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Assign Role *</label>
                            <select name="role_id" class="form-select form-select-gs" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>"><?= e($r['description']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Village Location *</label>
                            <select name="location_id" class="form-select form-select-gs" required>
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?= $loc['id'] ?>"><?= e($loc['village_name']) ?> (GP: <?= e($loc['gram_panchayat']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-gs">Provision User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>