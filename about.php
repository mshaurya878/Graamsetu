<?php
$page_title = "About Us | GraamSetu Vision & Mission";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Banner Header -->
<div class="bg-dark-gs text-white py-5">
    <div class="container text-center">
        <h1 class="fw-extrabold display-5">About <span class="text-primary-gs">GraamSetu</span></h1>
        <p class="lead max-w-700 mx-auto text-secondary-light">
            Building a digital bridge of opportunity, connectivity, and essential service discovery for rural India.
        </p>
    </div>
</div>

<div class="container py-5">
    <!-- Vision & Concept -->
    <div class="row align-items-center g-5 mb-5">
        <div class="col-lg-6">
            <span class="text-primary-gs fw-bold text-uppercase">Original Concept</span>
            <h2 class="fw-bold text-dark mb-3">Why GraamSetu?</h2>
            <p class="text-muted leading-relaxed mb-3">
                In Hindi, <strong>"Setu"</strong> means bridge, and <strong>"Graam"</strong> represents our vibrant Indian villages. GraamSetu was conceived to solve a fundamental challenge in rural connectivity: while smartphones and mobile internet reach deep into rural interiors, discovering local Kirana availability, skilled electricians, tractor haulage providers, or lodging Gram Panchayat grievances still relies on word-of-mouth or long physical travel.
            </p>
            <p class="text-muted leading-relaxed">
                GraamSetu digitizes rural commerce and governance in a clean, accessible, mobile-first web interface that is easy to navigate even for citizens with limited digital literacy.
            </p>
        </div>
        <div class="col-lg-6">
            <div class="p-4 bg-white rounded-4 shadow-sm border border-secondary border-opacity-25">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="logo-badge" style="width:60px; height:60px; font-size:1.8rem;">
                        <i class="fa-solid fa-bridge"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold text-dark mb-0">The 4 Pillars of GraamSetu</h4>
                        <small class="text-muted">Digital India Civic-Tech Framework</small>
                    </div>
                </div>
                <div class="d-flex flex-column gap-3">
                    <div class="p-3 bg-light rounded-3 d-flex align-items-center gap-3">
                        <i class="fa-solid fa-people-roof text-primary-gs fs-4"></i>
                        <div>
                            <strong class="d-block text-dark">Empowering Citizens</strong>
                            <small class="text-muted">Easy service discovery, transparent pricing & grievance tracking.</small>
                        </div>
                    </div>
                    <div class="p-3 bg-light rounded-3 d-flex align-items-center gap-3">
                        <i class="fa-solid fa-store text-secondary-gs fs-4"></i>
                        <div>
                            <strong class="d-block text-dark">Local Merchant Growth</strong>
                            <small class="text-muted">Digital store profiles for Kirana, Medical & Jan Seva Kendras.</small>
                        </div>
                    </div>
                    <div class="p-3 bg-light rounded-3 d-flex align-items-center gap-3">
                        <i class="fa-solid fa-toolbox text-warning fs-4"></i>
                        <div>
                            <strong class="d-block text-dark">Dignity for Skilled Work</strong>
                            <small class="text-muted">Direct visibility and bookings for electricians, plumbers & drivers.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tech Architecture Card -->
    <div class="bg-light p-4 p-md-5 rounded-4 border">
        <div class="text-center max-w-700 mx-auto mb-4">
            <span class="badge bg-secondary-gs px-3 py-2">Technical Excellence</span>
            <h3 class="fw-bold text-dark mt-2">Built with Production-Grade Architecture</h3>
            <p class="text-muted">GraamSetu is engineered using modern, robust web standards prioritizing speed, security, and low data bandwidth consumption.</p>
        </div>
        <div class="row g-4 text-center">
            <div class="col-md-3">
                <div class="bg-white p-3 rounded-3 shadow-sm h-100">
                    <i class="fa-brands fa-php text-primary-gs fs-1 mb-2"></i>
                    <h5 class="fw-bold text-dark">PHP 8.2 & PDO</h5>
                    <p class="small text-muted mb-0">Secure backend with prepared SQL queries preventing injection attacks.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="bg-white p-3 rounded-3 shadow-sm h-100">
                    <i class="fa-solid fa-database text-secondary-gs fs-1 mb-2"></i>
                    <h5 class="fw-bold text-dark">MySQL / MariaDB</h5>
                    <p class="small text-muted mb-0">Normalized database structure with strict constraints and foreign keys.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="bg-white p-3 rounded-3 shadow-sm h-100">
                    <i class="fa-solid fa-map-location-dot text-warning fs-1 mb-2"></i>
                    <h5 class="fw-bold text-dark">Leaflet & OpenStreetMap</h5>
                    <p class="small text-muted mb-0">Interactive GIS map mapping rural business locations and service providers.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="bg-white p-3 rounded-3 shadow-sm h-100">
                    <i class="fa-solid fa-chart-pie text-danger fs-1 mb-2"></i>
                    <h5 class="fw-bold text-dark">Chart.js Analytics</h5>
                    <p class="small text-muted mb-0">Real-time dynamic dashboards for Sarpanch and merchant performance tracking.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
