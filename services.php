<?php
$page_title = "Local Services & Merchant Directory | GraamSetu";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
require_once __DIR__ . '/config/database.php';

$db = Database::getConnection();

// Get Search & Filter Parameters
$q = trim($_GET['q'] ?? '');
$category_slug = trim($_GET['category'] ?? '');
$type_filter = trim($_GET['type'] ?? 'all'); // 'all', 'merchant', 'provider'

// Fetch all categories for sidebar filter
$categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Build Merchant Query
$merchant_sql = "
    SELECT mp.*, u.full_name as owner_name, c.name as category_name, c.slug as category_slug, c.icon_class
    FROM merchant_profiles mp
    JOIN users u ON mp.user_id = u.id
    JOIN categories c ON mp.category_id = c.id
    WHERE mp.verification_status = 'verified'
";
$merchant_params = [];

if (!empty($q)) {
    $merchant_sql .= " AND (mp.business_name LIKE :q1 OR mp.address LIKE :q2)";
    $merchant_params[':q1'] = "%$q%";
    $merchant_params[':q2'] = "%$q%";
}

if (!empty($category_slug)) {
    $merchant_sql .= " AND c.slug = :cat_slug";
    $merchant_params[':cat_slug'] = $category_slug;
}

$merchant_stmt = $db->prepare($merchant_sql);
$merchant_stmt->execute($merchant_params);
$merchants = ($type_filter === 'all' || $type_filter === 'merchant') ? $merchant_stmt->fetchAll() : [];

// Build Provider Query
$provider_sql = "
    SELECT sp.*, u.full_name, u.phone, c.name as category_name, c.slug as category_slug, c.icon_class,
           l.village_name, l.gram_panchayat
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    JOIN categories c ON sp.service_category_id = c.id
    LEFT JOIN locations l ON sp.location_id = l.id
    WHERE sp.verification_status = 'verified'
";
$provider_params = [];

if (!empty($q)) {
    $provider_sql .= " AND (c.name LIKE :pq1 OR u.full_name LIKE :pq2 OR sp.address LIKE :pq3)";
    $provider_params[':pq1'] = "%$q%";
    $provider_params[':pq2'] = "%$q%";
    $provider_params[':pq3'] = "%$q%";
}

if (!empty($category_slug)) {
    $provider_sql .= " AND c.slug = :pcat_slug";
    $provider_params[':pcat_slug'] = $category_slug;
}

$provider_stmt = $db->prepare($provider_sql);
$provider_stmt->execute($provider_params);
$providers = ($type_filter === 'all' || $type_filter === 'provider') ? $provider_stmt->fetchAll() : [];
?>

<div class="bg-light py-4 border-bottom">
    <div class="container">
        <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-store text-primary-gs me-2"></i> Village Services & Shops Directory</h2>
        <p class="text-muted mb-0">Discover verified local business listings, Kirana stores, medical pharmacies, and skilled service technicians.</p>
    </div>
</div>

<div class="container py-4">
    <div class="row g-4">
        <!-- Sidebar Filters -->
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 p-4 sticky-top" style="top: 90px;">
                <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-filter me-2 text-primary-gs"></i> Search & Filters</h5>
                
                <form action="<?= BASE_URL ?>/services.php" method="GET">
                    <!-- Search Input -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Search Keyword</label>
                        <input type="text" name="q" class="form-control form-control-gs" placeholder="Store name, skill, item..." value="<?= e($q) ?>">
                    </div>

                    <!-- Type Filter -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Listing Type</label>
                        <select name="type" class="form-select form-select-gs">
                            <option value="all" <?= $type_filter === 'all' ? 'selected' : '' ?>>All Listings</option>
                            <option value="merchant" <?= $type_filter === 'merchant' ? 'selected' : '' ?>>Shops & Merchants Only</option>
                            <option value="provider" <?= $type_filter === 'provider' ? 'selected' : '' ?>>Service Technicians Only</option>
                        </select>
                    </div>

                    <!-- Category Filter -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Category</label>
                        <select name="category" class="form-select form-select-gs">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= e($cat['slug']) ?>" <?= $category_slug === $cat['slug'] ? 'selected' : '' ?>>
                                    <?= e($cat['name']) ?> (<?= ucfirst($cat['type']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary-gs w-100 mb-2">Apply Filters</button>
                    <a href="<?= BASE_URL ?>/services.php" class="btn btn-outline-secondary w-100 btn-sm">Reset</a>
                </form>
            </div>
        </div>

        <!-- Main Results Column -->
        <div class="col-lg-9">
            <?php if (empty($merchants) && empty($providers)): ?>
                <div class="card border-0 shadow-sm p-5 text-center my-4">
                    <i class="fa-solid fa-store-slash text-muted display-4 mb-3"></i>
                    <h4 class="fw-bold text-dark">No Listings Found</h4>
                    <p class="text-muted">No verified merchants or service providers matched your search parameters.</p>
                    <div><a href="<?= BASE_URL ?>/services.php" class="btn btn-primary-gs">View All Listings</a></div>
                </div>
            <?php else: ?>
                <!-- Merchants Section -->
                <?php if (!empty($merchants)): ?>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h4 class="fw-bold text-dark mb-0"><i class="fa-solid fa-shop text-primary-gs me-2"></i> Local Merchants & Stores (<?= count($merchants) ?>)</h4>
                    </div>

                    <div class="row g-4 mb-5">
                        <?php foreach ($merchants as $m): ?>
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm h-100 rounded-3 overflow-hidden">
                                    <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                                        <span class="badge bg-primary-gs"><i class="<?= e($m['icon_class']) ?> me-1"></i> <?= e($m['category_name']) ?></span>
                                        <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i> Verified</span>
                                    </div>
                                    <div class="card-body p-4">
                                        <h5 class="fw-bold text-dark mb-2"><?= e($m['business_name']) ?></h5>
                                        <p class="text-muted small mb-2"><i class="fa-solid fa-location-dot text-danger me-1"></i> <?= e($m['address']) ?></p>
                                        <p class="small text-secondary mb-3"><i class="fa-solid fa-user me-1"></i> Owner: <?= e($m['owner_name']) ?></p>
                                        
                                        <div class="bg-light p-2 rounded mb-3 small d-flex justify-content-between">
                                            <span><i class="fa-solid fa-clock text-warning me-1"></i> <?= e($m['opening_hours']) ?></span>
                                            <span><i class="fa-solid fa-phone text-success me-1"></i> <?= e($m['contact_phone']) ?></span>
                                        </div>

                                        <div class="d-flex gap-2">
                                            <?php if (is_logged_in()): ?>
                                                <a href="<?= BASE_URL ?>/citizen/services.php?enquire_merchant=<?= $m['id'] ?>" class="btn btn-sm btn-primary-gs w-100">Send Enquiry</a>
                                            <?php else: ?>
                                                <a href="<?= BASE_URL ?>/login.php" class="btn btn-sm btn-outline-gs w-100">Login to Contact</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Service Providers Section -->
                <?php if (!empty($providers)): ?>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h4 class="fw-bold text-dark mb-0"><i class="fa-solid fa-screwdriver-wrench text-secondary-gs me-2"></i> Service Technicians & Providers (<?= count($providers) ?>)</h4>
                    </div>

                    <div class="row g-4 mb-4">
                        <?php foreach ($providers as $p): ?>
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm h-100 rounded-3 overflow-hidden">
                                    <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                                        <span class="badge bg-secondary-gs"><i class="<?= e($p['icon_class']) ?> me-1"></i> <?= e($p['category_name']) ?></span>
                                        <?= get_status_badge($p['is_available'] ? 'available' : 'busy') ?>
                                    </div>
                                    <div class="card-body p-4">
                                        <h5 class="fw-bold text-dark mb-1"><?= e($p['category_name']) ?> Technician</h5>
                                        <p class="text-muted small mb-2"><i class="fa-solid fa-user me-1"></i> <?= e($p['full_name']) ?> • <?= e($p['village_name'] ?? '') ?></p>
                                        <p class="small text-secondary mb-3"><?= e($p['experience_years'] ?? 0) ?> yr<?= ($p['experience_years'] ?? 0) != 1 ? 's' : '' ?> experience • <?= e($p['address'] ?? '') ?></p>

                                        <div class="d-flex justify-content-between align-items-center bg-light p-2 rounded mb-3">
                                            <span class="fw-bold text-success">₹<?= number_format($p['hourly_rate'], 0) ?> / hr</span>
                                            <span class="small text-dark fw-semibold"><i class="fa-solid fa-shield-check text-success me-1"></i> Verified</span>
                                        </div>

                                        <?php if (is_logged_in()): ?>
                                            <a href="<?= BASE_URL ?>/citizen/requests.php?provider_id=<?= $p['id'] ?>" class="btn btn-sm btn-secondary-gs w-100">
                                                <i class="fa-solid fa-calendar-check me-1"></i> Request Service
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= BASE_URL ?>/login.php" class="btn btn-sm btn-outline-gs w-100">Login to Book Service</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
