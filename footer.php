<footer>
    <div class="container">
        <div class="row g-4 mb-4">
            <!-- Brand Column -->
            <div class="col-lg-4 col-md-6">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="logo-badge" style="width:36px; height:36px; font-size:1.1rem;">
                        <i class="fa-solid fa-bridge"></i>
                    </div>
                    <span class="fs-4 fw-bold text-white">Graam<span class="text-primary-gs">Setu</span></span>
                </div>
                <p class="small text-secondary-light">
                    GraamSetu is a modern civic-tech platform empowering rural citizens, local kirana merchants, skilled service providers, and Gram Panchayats with digital connectivity.
                </p>
                <div class="d-flex gap-3 mt-3 fs-5">
                    <a href="#" class="text-secondary-light"><i class="fa-brands fa-facebook"></i></a>
                    <a href="#" class="text-secondary-light"><i class="fa-brands fa-whatsapp"></i></a>
                    <a href="#" class="text-secondary-light"><i class="fa-brands fa-x-twitter"></i></a>
                    <a href="#" class="text-secondary-light"><i class="fa-brands fa-youtube"></i></a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="col-lg-2 col-md-6">
                <h5>Quick Links</h5>
                <ul class="list-unstyled small d-flex flex-column gap-2">
                    <li><a href="<?= BASE_URL ?>/index.php">Home Portal</a></li>
                    <li><a href="<?= BASE_URL ?>/about.php">About Project</a></li>
                    <li><a href="<?= BASE_URL ?>/services.php">Find Services</a></li>
                    <li><a href="<?= BASE_URL ?>/contact.php">Helpline Directory</a></li>
                    <li><a href="<?= BASE_URL ?>/register.php">Join as Merchant</a></li>
                </ul>
            </div>

            <!-- Rural Services -->
            <div class="col-lg-3 col-md-6">
                <h5>Rural Ecosystem</h5>
                <ul class="list-unstyled small d-flex flex-column gap-2">
                    <li><i class="fa-solid fa-angle-right me-2 text-primary-gs"></i>Gram Panchayat Notices</li>
                    <li><i class="fa-solid fa-angle-right me-2 text-primary-gs"></i>Jan Seva Kendra (CSC)</li>
                    <li><i class="fa-solid fa-angle-right me-2 text-primary-gs"></i>Kirana & Agri Kendra</li>
                    <li><i class="fa-solid fa-angle-right me-2 text-primary-gs"></i>Electrician & Plumber</li>
                    <li><i class="fa-solid fa-angle-right me-2 text-primary-gs"></i>Tractor & Freight Haulage</li>
                </ul>
            </div>

            <!-- Emergency Helplines -->
            <div class="col-lg-3 col-md-6">
                <h5>Emergency Helplines</h5>
                <div class="bg-dark p-3 rounded-3 border border-secondary small">
                    <div class="d-flex justify-content-between mb-2">
                        <span><i class="fa-solid fa-phone-volume text-warning me-2"></i>National Emergency:</span>
                        <strong class="text-white">112</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span><i class="fa-solid fa-truck-medical text-danger me-2"></i>Ambulance:</span>
                        <strong class="text-white">108</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span><i class="fa-solid fa-wheat-awn text-success me-2"></i>Kisan Call Center:</span>
                        <strong class="text-white">1800-180-1551</strong>
                    </div>
                </div>
            </div>
        </div>

        <hr class="border-secondary my-4">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center small text-secondary-light">
            <p class="mb-2 mb-md-0">&copy; <?= date('Y') ?> GraamSetu. Built for Digital India & Rural Prosperity.</p>
            <div>
                <a href="#" class="me-3">Privacy Policy</a>
                <a href="#" class="me-3">Terms of Service</a>
                <a href="<?= BASE_URL ?>/login.php">Portal Access</a>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Leaflet OpenStreetMap JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Chart.js JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- GraamSetu Custom Main JS -->
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
