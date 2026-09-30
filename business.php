<?php
$page_title = "Store Profile & Location Settings | GraamSetu Merchant";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('merchant');
$user = current_user();
$db = Database::getConnection();

// Fetch merchant profile with location
$merchant_stmt = $db->prepare("
    SELECT mp.*, l.village_name, l.gram_panchayat, l.district, l.pincode
    FROM merchant_profiles mp 
    LEFT JOIN locations l ON mp.location_id = l.id
    WHERE mp.user_id = :uid
");
$merchant_stmt->execute([':uid' => $user['id']]);
$merchant = $merchant_stmt->fetch();

if (!$merchant) {
    die("Merchant profile missing. Please contact Gram Panchayat Administrator.");
}

// Fetch Business Categories & Locations
$categories = $db->query("SELECT * FROM categories WHERE type = 'business' ORDER BY name ASC")->fetchAll();
$locations = $db->query("SELECT * FROM locations ORDER BY village_name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $biz_name = trim($_POST['business_name'] ?? '');
        $cat_id = (int)($_POST['category_id'] ?? 1);
        $location_id = (int)($_POST['location_id'] ?? 1);
        $address = trim($_POST['address'] ?? '');
        $hours = trim($_POST['opening_hours'] ?? '08:00 AM - 08:00 PM');
        $phone = preg_replace('/[^0-9]/', '', trim($_POST['contact_phone'] ?? ''));
        $image_url = trim($_POST['image_url'] ?? ($merchant['image_url'] ?? 'assets/images/default-shop.jpg'));

        if (empty($biz_name)) {
            set_flash('danger', 'Business / Shop name is required.');
        } elseif (empty($address)) {
            set_flash('danger', 'Shop physical address is required.');
        } elseif (!preg_match('/^[6-9]\d{9}$/', $phone)) {
            set_flash('danger', 'Contact phone number must be a valid 10-digit mobile number.');
        } else {
            // Note: verification_status is NOT modified here; it is strictly preserved under admin control
            $upd = $db->prepare("
                UPDATE merchant_profiles 
                SET business_name = ?, category_id = ?, location_id = ?, address = ?, opening_hours = ?, contact_phone = ?, image_url = ?
                WHERE user_id = ?
            ");
            $upd->execute([$biz_name, $cat_id, $location_id, $address, $hours, $phone, $image_url, $user['id']]);

            set_flash('success', 'Store profile and village location details updated successfully!');
            header('Location: ' . BASE_URL . '/merchant/business.php');
            exit;
        }
    } else {
        set_flash('danger', 'Security session expired. Please submit the form again.');
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="dashboard-wrapper">
    <!-- Sidebar -->
    <div class="dashboard-sidebar">
        <div class="sidebar-heading">Merchant Portal</div>
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/merchant/dashboard.php" class="sidebar-link"><i class="fa-solid fa-gauge"></i> Store Overview</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/merchant/products.php" class="sidebar-link"><i class="fa-solid fa-boxes-stacked"></i> Products Inventory</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/merchant/enquiries.php" class="sidebar-link"><i class="fa-solid fa-comments"></i> Customer Inquiries</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/merchant/business.php" class="sidebar-link active"><i class="fa-solid fa-shop"></i> Store Profile</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/merchant/profile.php" class="sidebar-link"><i class="fa-solid fa-user-gear"></i> Owner Account</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="dashboard-content">
        <?= render_flash_messages() ?>

        <div class="row justify-content-center">
            <div class="col-lg-9">
                <!-- Verification Status Card (Read-only, Admin controlled) -->
                <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white border-start border-4 <?= $merchant['verification_status'] === 'verified' ? 'border-success' : 'border-warning' ?>">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Panchayat Verification Status</span>
                            <div class="d-flex align-items-center gap-2 mt-1">
                                <h5 class="fw-bold text-dark mb-0"><?= ucfirst(e($merchant['verification_status'])) ?> Merchant</h5>
                                <?= get_status_badge($merchant['verification_status']) ?>
                            </div>
                            <p class="small text-muted mb-0 mt-1">
                                <?php if ($merchant['verification_status'] === 'verified'): ?>
                                    Your store is verified by the Gram Panchayat administration and prominently displayed on the Citizen Discovery Map.
                                <?php elseif ($merchant['verification_status'] === 'pending'): ?>
                                    Your store profile is currently under review by the Gram Panchayat Sarpanch. Verification badge will be issued once approved.
                                <?php else: ?>
                                    Your verification status is flagged for revision by the Panchayat. Please contact the administrator.
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="fs-1 <?= $merchant['verification_status'] === 'verified' ? 'text-success' : 'text-warning' ?>">
                            <i class="fa-solid <?= $merchant['verification_status'] === 'verified' ? 'fa-certificate' : 'fa-clock' ?>"></i>
                        </div>
                    </div>
                </div>

                <!-- Business Settings Form -->
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                    <h4 class="fw-bold text-dark mb-1"><i class="fa-solid fa-shop text-primary-gs me-2"></i> Manage Store Details & Village Listing</h4>
                    <p class="text-muted small mb-4">Keep your business contact information and operating hours updated for local customers.</p>

                    <form action="<?= BASE_URL ?>/merchant/business.php" method="POST" novalidate>
                        <?= csrf_field() ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Business / Shop Name *</label>
                                <input type="text" name="business_name" class="form-control form-control-gs" value="<?= e($merchant['business_name']) ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Store Category *</label>
                                <select name="category_id" class="form-select form-select-gs" required>
                                    <?php foreach ($categories as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= $merchant['category_id'] == $c['id'] ? 'selected' : '' ?>>
                                            <?= e($c['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Store Contact Phone *</label>
                                <input type="tel" name="contact_phone" class="form-control form-control-gs" value="<?= e($merchant['contact_phone']) ?>" placeholder="10-digit mobile" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Opening Hours *</label>
                                <input type="text" name="opening_hours" class="form-control form-control-gs" value="<?= e($merchant['opening_hours'] ?? '08:00 AM - 08:00 PM') ?>" placeholder="e.g. 07:00 AM - 08:30 PM" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold text-dark">Village / Gram Panchayat Location *</label>
                                <select name="location_id" class="form-select form-select-gs" required>
                                    <?php foreach ($locations as $loc): ?>
                                        <option value="<?= $loc['id'] ?>" <?= ($merchant['location_id'] ?? 1) == $loc['id'] ? 'selected' : '' ?>>
                                            <?= e($loc['village_name']) ?> (GP: <?= e($loc['gram_panchayat']) ?>, Dist: <?= e($loc['district']) ?> - <?= e($loc['pincode']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold text-dark">Physical Market / Shop Address *</label>
                                <input type="text" name="address" class="form-control form-control-gs" value="<?= e($merchant['address']) ?>" placeholder="e.g. Near Panchayat Bhavan, Main Market Road" required>
                            </div>

                            <div class="col-12 mt-4 pt-2 border-top">
                                <button type="submit" class="btn btn-primary-gs px-4 py-2">
                                    <i class="fa-solid fa-floppy-disk me-1"></i> Save Store Profile
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