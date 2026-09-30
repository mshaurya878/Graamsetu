<?php
$page_title = "GraamSetu | Rural Digital Services Platform";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
require_once __DIR__ . '/config/database.php';

$db = Database::getConnection();

// Fetch platform statistics
$total_citizens = $db->query("SELECT COUNT(*) FROM users WHERE role_id = 1")->fetchColumn();
$total_merchants = $db->query("SELECT COUNT(*) FROM merchant_profiles WHERE verification_status = 'verified'")->fetchColumn();
$total_providers = $db->query("SELECT COUNT(*) FROM service_providers WHERE verification_status = 'verified'")->fetchColumn();
$total_announcements = $db->query("SELECT COUNT(*) FROM announcements WHERE is_active = 1")->fetchColumn();

// Fetch urgent / priority announcements
$announcements = $db->query("SELECT * FROM announcements WHERE is_active = 1 ORDER BY (priority = 'urgent' OR priority = 'high') DESC, created_at DESC LIMIT 3")->fetchAll();

// Fetch verified merchants for featured section
$featured_merchants = $db->query("
    SELECT mp.*, c.name AS category_name, c.icon_class, c.description AS category_desc
    FROM merchant_profiles mp 
    JOIN categories c ON mp.category_id = c.id 
    WHERE mp.verification_status = 'verified' 
    LIMIT 3
")->fetchAll();

// Fetch verified service providers
$featured_providers = $db->query("
    SELECT sp.*, u.full_name, u.phone, c.name AS category_name, c.icon_class, c.description AS category_desc
    FROM service_providers sp 
    JOIN users u ON sp.user_id = u.id 
    JOIN categories c ON sp.service_category_id = c.id 
    WHERE sp.verification_status = 'verified' 
    LIMIT 3
")->fetchAll();
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container position-relative">
        <?= render_flash_messages() ?>
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <div class="badge bg-primary-gs px-3 py-2 rounded-pill mb-3 fw-bold">
                    <i class="fa-solid fa-seedling me-1"></i> Digital India Civic-Tech Initiative
                </div>
                <h1 class="hero-title mb-3">Connecting Villages, Empowering Citizens & Local Commerce</h1>
                <p class="hero-subtitle mb-4">
                    GraamSetu bridges the gap between rural citizens, village merchants, skilled local service providers, and Panchayat administration through one unified digital ecosystem.
                </p>
                <div class="d-flex flex-wrap gap-3 mb-4">
                    <a href="<?= BASE_URL ?>/services.php" class="btn btn-primary-gs btn-lg shadow-sm">
                        <i class="fa-solid fa-magnifying-glass me-2"></i> Explore Services & Shops
                    </a>
                    <a href="<?= BASE_URL ?>/register.php" class="btn btn-outline-light btn-lg">
                        <i class="fa-solid fa-store me-2"></i> Register Business / Skill
                    </a>
                </div>

                <!-- Quick Stats Row -->
                <div class="row g-3 text-center border-top border-secondary pt-4 mt-2">
                    <div class="col-6 col-sm-3">
                        <h3 class="fw-extrabold text-warning mb-0"><?= number_format($total_citizens) ?>+</h3>
                        <small class="text-secondary-light">Rural Citizens</small>
                    </div>
                    <div class="col-6 col-sm-3">
                        <h3 class="fw-extrabold text-info mb-0"><?= number_format($total_merchants) ?>+</h3>
                        <small class="text-secondary-light">Verified Shops</small>
                    </div>
                    <div class="col-6 col-sm-3">
                        <h3 class="fw-extrabold text-success mb-0"><?= number_format($total_providers) ?>+</h3>
                        <small class="text-secondary-light">Service Providers</small>
                    </div>
                    <div class="col-6 col-sm-3">
                        <h3 class="fw-extrabold text-danger mb-0"><?= number_format($total_announcements) ?></h3>
                        <small class="text-secondary-light">Active Notices</small>
                    </div>
                </div>
            </div>
            
            <!-- Quick Search Card -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-lg rounded-4 p-4 text-dark bg-white">
                    <h4 class="fw-bold mb-3 text-dark d-flex align-items-center">
                        <i class="fa-solid fa-compass text-primary-gs me-2"></i> Find Local Services
                    </h4>
                    <form action="<?= BASE_URL ?>/services.php" method="GET">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">What service or shop do you need?</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fa-solid fa-search text-muted"></i></span>
                                <input type="text" name="q" class="form-control form-control-gs" placeholder="e.g. Kirana, Electrician, CSC, Tractor...">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Category</label>
                            <select name="category" class="form-select form-select-gs">
                                <option value="">All Categories</option>
                                <option value="kirana-grocery">Kirana & Grocery</option>
                                <option value="medical-pharmacy">Medical & Pharmacy</option>
                                <option value="csc-digital">CSC & Digital Services</option>
                                <option value="electrical-solar">Electrical & Solar Repair</option>
                                <option value="farm-transport">Farm & Transport Logistics</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary-gs w-100 py-2 fs-6">
                            <i class="fa-solid fa-filter me-2"></i> Search Village Directory
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Village Announcements Ticker -->
<?php if (!empty($announcements)): ?>
<section class="bg-warning-subtle py-3 border-bottom border-warning">
    <div class="container">
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-danger fs-6 px-3 py-2 text-uppercase d-flex align-items-center gap-1">
                <i class="fa-solid fa-bullhorn"></i> Notice
            </span>
            <div class="overflow-hidden flex-grow-1">
                <div class="d-flex gap-4 fw-semibold text-dark">
                    <?php foreach ($announcements as $a): ?>
                        <span>
                            <strong class="text-primary-gs">[<?= e(ucfirst($a['priority'])) ?>]</strong> 
                            <?= e($a['title']) ?> — <span class="text-muted small"><?= e(ucfirst($a['target_audience'])) ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- 4 Core Pillars Section -->
<section class="py-5 bg-white">
    <div class="container">
        <div class="text-center max-w-700 mx-auto mb-5">
            <span class="text-primary-gs fw-bold text-uppercase tracking-wider">Modular Platform Ecosystem</span>
            <h2 class="fw-extrabold text-dark mt-1">One Platform for the Entire Village</h2>
            <p class="text-muted">Designed specifically to connect every stakeholder in the rural community with dignity and convenience.</p>
        </div>

        <div class="row g-4">
            <!-- Citizen Pillar -->
            <div class="col-md-6 col-lg-3">
                <div class="feature-card">
                    <div class="icon-box icon-box-saffron">
                        <i class="fa-solid fa-user-group"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-2">Rural Citizens</h4>
                    <p class="text-muted small mb-3">Access verified local shops, hire nearby repair technicians, track service requests and lodge grievance complaints.</p>
                    <a href="<?= BASE_URL ?>/register.php?role=citizen" class="text-primary-gs fw-semibold small text-decoration-none">Join as Citizen <i class="fa-solid fa-arrow-right ms-1"></i></a>
                </div>
            </div>

            <!-- Merchant Pillar -->
            <div class="col-md-6 col-lg-3">
                <div class="feature-card">
                    <div class="icon-box icon-box-forest">
                        <i class="fa-solid fa-shop"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-2">Local Merchants</h4>
                    <p class="text-muted small mb-3">Digitalize your Kirana or Medical store. List inventory prices, receive citizen product enquiries and grow shop visibility.</p>
                    <a href="<?= BASE_URL ?>/register.php?role=merchant" class="text-secondary-gs fw-semibold small text-decoration-none">Register Shop <i class="fa-solid fa-arrow-right ms-1"></i></a>
                </div>
            </div>

            <!-- Service Provider Pillar -->
            <div class="col-md-6 col-lg-3">
                <div class="feature-card">
                    <div class="icon-box icon-box-gold">
                        <i class="fa-solid fa-screwdriver-wrench"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-2">Service Technicians</h4>
                    <p class="text-muted small mb-3">Electricians, plumbers, mechanics and tractor operators can list services, set rates and manage booking requests.</p>
                    <a href="<?= BASE_URL ?>/register.php?role=provider" class="text-warning fw-semibold small text-decoration-none">List Skills <i class="fa-solid fa-arrow-right ms-1"></i></a>
                </div>
            </div>

            <!-- Admin Pillar -->
            <div class="col-md-6 col-lg-3">
                <div class="feature-card">
                    <div class="icon-box icon-box-saffron" style="background-color: rgba(15, 23, 42, 0.1); color: var(--gs-dark);">
                        <i class="fa-solid fa-building-columns"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-2">Gram Panchayat</h4>
                    <p class="text-muted small mb-3">Powerful Sarpanch/Admin dashboard to verify businesses, publish scheme alerts and resolve citizen complaints.</p>
                    <a href="<?= BASE_URL ?>/login.php" class="text-dark fw-semibold small text-decoration-none">Admin Login <i class="fa-solid fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Interactive Village Map Section -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="row align-items-center mb-4">
            <div class="col-md-8">
                <span class="text-secondary-gs fw-bold text-uppercase">Geographic Connectivity</span>
                <h2 class="fw-bold text-dark">Interactive Local Village Map</h2>
                <p class="text-muted mb-0">Discover verified merchants, CSC centers, and service technicians located in your Gram Panchayat.</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="<?= BASE_URL ?>/services.php" class="btn btn-outline-gs"><i class="fa-solid fa-expand me-1"></i> View Fullscreen Map</a>
            </div>
        </div>

        <div id="villageMap"></div>
    </div>
</section>

<!-- Featured Shops & Providers -->
<section class="py-5 bg-white">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <span class="text-primary-gs fw-bold text-uppercase">Verified Local Listings</span>
                <h2 class="fw-bold text-dark">Featured Merchants & Providers</h2>
            </div>
            <a href="<?= BASE_URL ?>/services.php" class="btn btn-link text-primary-gs fw-bold text-decoration-none">View All Listings <i class="fa-solid fa-arrow-right"></i></a>
        </div>

        <div class="row g-4">
            <?php foreach ($featured_merchants as $m): ?>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm rounded-3 overflow-hidden">
                        <div class="bg-primary-gs p-3 text-white d-flex justify-content-between align-items-center">
                            <span class="badge bg-white text-dark"><i class="<?= e($m['icon_class']) ?> me-1"></i> <?= e($m['category_name']) ?></span>
                            <span class="badge bg-success"><i class="fa-solid fa-shield-check me-1"></i> Verified Shop</span>
                        </div>
                        <div class="card-body p-4">
                            <h5 class="fw-bold text-dark mb-2"><?= e($m['business_name']) ?></h5>
                            <p class="text-muted small mb-3"><i class="fa-solid fa-location-dot text-danger me-1"></i> <?= e($m['address']) ?></p>
                            <p class="small text-secondary mb-3"><?= e(substr($m['category_desc'] ?? $m['address'], 0, 90)) ?>...</p>
                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <span class="small fw-semibold text-dark"><i class="fa-solid fa-clock text-warning me-1"></i> <?= e($m['opening_hours']) ?></span>
                                <a href="<?= BASE_URL ?>/services.php?merchant=<?= $m['id'] ?>" class="btn btn-sm btn-outline-gs">View Shop</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php foreach ($featured_providers as $p): ?>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm rounded-3 overflow-hidden">
                        <div class="bg-secondary-gs p-3 text-white d-flex justify-content-between align-items-center">
                            <span class="badge bg-white text-dark"><i class="<?= e($p['icon_class']) ?> me-1"></i> <?= e($p['category_name']) ?></span>
                            <span class="badge bg-warning text-dark"><i class="fa-solid fa-certificate me-1"></i> Verified</span>
                        </div>
                        <div class="card-body p-4">
                            <h5 class="fw-bold text-dark mb-1"><?= e($p['category_name']) ?></h5>
                            <p class="text-muted small mb-2"><i class="fa-solid fa-user me-1"></i> <?= e($p['full_name']) ?> (<?= (int)$p['experience_years'] ?> yrs exp)</p>
                            <p class="small text-secondary mb-3"><?= e(substr($p['category_desc'] ?? $p['address'], 0, 90)) ?>...</p>
                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <span class="fw-bold text-success fs-6">₹<?= number_format($p['hourly_rate'], 0) ?>/hr</span>
                                <a href="<?= BASE_URL ?>/login.php" class="btn btn-sm btn-secondary-gs">Book Service</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Leaflet Map Script Initialization for Homepage -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Center map around Sehore / Badi Mungawali area (23.2054, 77.0872)
    const map = L.map('villageMap').setView([23.2054, 77.0872], 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors | GraamSetu'
    }).addTo(map);

    // Custom Markers
    const shopIcon = L.divIcon({
        className: 'custom-map-icon',
        html: '<div style="background-color:#E05A10; color:#fff; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; border:2px solid white; box-shadow:0 4px 8px rgba(0,0,0,0.3);"><i class="fa-solid fa-shop"></i></div>',
        iconSize: [36, 36]
    });

    const cscIcon = L.divIcon({
        className: 'custom-map-icon',
        html: '<div style="background-color:#055938; color:#fff; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; border:2px solid white; box-shadow:0 4px 8px rgba(0,0,0,0.3);"><i class="fa-solid fa-laptop-code"></i></div>',
        iconSize: [36, 36]
    });

    // Add Demo Markers from Database
    L.marker([23.2054, 77.0872], {icon: shopIcon}).addTo(map)
        .bindPopup('<strong>Gupta Kirana Store</strong><br>Main Market, Badi Mungawali<br><em>Open 07:00 AM - 09:00 PM</em>');

    L.marker([23.2062, 77.0890], {icon: shopIcon}).addTo(map)
        .bindPopup('<strong>Kisan Medical & Wellness</strong><br>Opp. Health Center<br><em>Medicines & First Aid</em>');

    L.marker([23.2040, 77.0845], {icon: cscIcon}).addTo(map)
        .bindPopup('<strong>Gramin Jan Seva Kendra (CSC)</strong><br>Bus Stand Complex<br><em>Digital India Services</em>');
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
