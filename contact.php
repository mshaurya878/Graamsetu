<?php
$page_title = "Contact & Helplines | GraamSetu";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('success', 'Thank you! Your inquiry has been submitted to the Gram Panchayat Helpdesk.');
        $sent = true;
    } else {
        set_flash('danger', 'Invalid security token.');
    }
}
?>

<div class="bg-dark-gs text-white py-5">
    <div class="container text-center">
        <h1 class="fw-extrabold">Gram Panchayat & Emergency Helplines</h1>
        <p class="lead text-secondary-light">Essential contact numbers, disaster assistance and citizen helpdesk.</p>
    </div>
</div>

<div class="container py-5">
    <?= render_flash_messages() ?>

    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 text-center h-100 rounded-3">
                <div class="icon-box icon-box-saffron mx-auto mb-3">
                    <i class="fa-solid fa-phone-volume"></i>
                </div>
                <h5 class="fw-bold text-dark">Gram Panchayat Helpdesk</h5>
                <p class="text-muted small">Panchayat Bhavan, Badi Mungawali, Dist. Sehore (M.P.)</p>
                <h4 class="fw-extrabold text-primary-gs">+91 98765 43210</h4>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 text-center h-100 rounded-3">
                <div class="icon-box icon-box-forest mx-auto mb-3">
                    <i class="fa-solid fa-wheat-awn"></i>
                </div>
                <h5 class="fw-bold text-dark">Kisan Call Center</h5>
                <p class="text-muted small">Free Agriculture & Crop Support (24x7 Toll-Free)</p>
                <h4 class="fw-extrabold text-secondary-gs">1800-180-1551</h4>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 text-center h-100 rounded-3">
                <div class="icon-box icon-box-gold mx-auto mb-3">
                    <i class="fa-solid fa-truck-medical"></i>
                </div>
                <h5 class="fw-bold text-dark">Emergency Services</h5>
                <p class="text-muted small">National Emergency & Ambulance Response</p>
                <h4 class="fw-extrabold text-danger">112 / 108</h4>
            </div>
        </div>
    </div>

    <!-- Contact Form -->
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm p-4 p-md-5 rounded-4">
                <h3 class="fw-bold text-dark mb-2">Send Message to Panchayat Helpdesk</h3>
                <p class="text-muted mb-4">Have questions about village schemes, merchant verification, or technical help?</p>

                <form action="<?= BASE_URL ?>/contact.php" method="POST">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Your Full Name</label>
                            <input type="text" name="name" class="form-control form-control-gs" required placeholder="e.g. Ramesh Patel">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Mobile Number</label>
                            <input type="tel" name="phone" class="form-control form-control-gs" required placeholder="e.g. 9826011223">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold text-dark">Village Name</label>
                            <input type="text" name="village" class="form-control form-control-gs" required value="Badi Mungawali">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold text-dark">Your Message / Query</label>
                            <textarea name="message" class="form-control form-control-gs" rows="4" required placeholder="Describe your query or assistance request..."></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary-gs btn-lg w-100">
                                <i class="fa-solid fa-paper-plane me-2"></i> Submit Inquiry
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
