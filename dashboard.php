<?php
$page_title = "Provider Dashboard | GraamSetu";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('provider');
$user = current_user();
$db = Database::getConnection();

// Fetch provider profile with category and location
$provider_stmt = $db->prepare("
    SELECT sp.*, c.name as category_name, c.icon_class,
           l.village_name, l.gram_panchayat, l.district, l.block, l.pincode
    FROM service_providers sp 
    JOIN categories c ON sp.service_category_id = c.id 
    LEFT JOIN locations l ON sp.location_id = l.id
    WHERE sp.user_id = :uid
");
$provider_stmt->execute([':uid' => $user['id']]);
$provider = $provider_stmt->fetch();

if (!$provider) {
    die("Service provider profile missing. Please contact Gram Panchayat Administrator.");
}
$provider_id = (int)$provider['id'];

// Handle 1-Click Availability Toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_availability'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $new_avail = $provider['is_available'] ? 0 : 1;
        $upd = $db->prepare("UPDATE service_providers SET is_available = ? WHERE id = ?");
        $upd->execute([$new_avail, $provider_id]);

        $status_text = $new_avail ? 'Available Now (On Duty)' : 'Busy / Off Duty';
        set_flash('success', "Your duty availability updated to: $status_text");
        header('Location: ' . BASE_URL . '/provider/dashboard.php');
        exit;
    } else {
        set_flash('danger', 'Security session expired. Please try again.');
    }
}

// Handle Quick Accept/Decline from Dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_action'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $req_id = (int)$_POST['request_id'];
        $action = trim($_POST['action'] ?? '');
        $allowed_actions = ['accepted', 'cancelled'];

        if (in_array($action, $allowed_actions)) {
            // Verify request belongs to current provider
            $chk = $db->prepare("SELECT citizen_id, preferred_date FROM service_requests WHERE id = ? AND provider_id = ?");
            $chk->execute([$req_id, $provider_id]);
            $req_data = $chk->fetch();

            if ($req_data) {
                $db->prepare("UPDATE service_requests SET status = ? WHERE id = ? AND provider_id = ?")
                   ->execute([$action, $req_id, $provider_id]);

                // Create Citizen Notification
                $notif_msg = $action === 'accepted'
                    ? "Technician {$user['full_name']} confirmed your service booking for {$req_data['preferred_date']}."
                    : "Technician {$user['full_name']} was unable to accept your service booking for {$req_data['preferred_date']}.";

                $db->prepare("INSERT INTO notifications (user_id, title, message, link, is_read) VALUES (?, ?, ?, 'citizen/requests.php', 0)")
                   ->execute([$req_data['citizen_id'], 'Service Booking ' . ucfirst($action), $notif_msg]);

                set_flash('success', "Booking request #SR-$req_id has been " . ($action === 'accepted' ? 'confirmed' : 'declined') . ".");
            } else {
                set_flash('danger', 'Unauthorized: Request does not belong to your account.');
            }
            header('Location: ' . BASE_URL . '/provider/dashboard.php');
            exit;
        }
    }
}

// Fetch Stats
$total_requests = (int)$db->query("SELECT COUNT(*) FROM service_requests WHERE provider_id = $provider_id")->fetchColumn();
$pending_requests = (int)$db->query("SELECT COUNT(*) FROM service_requests WHERE provider_id = $provider_id AND status = 'pending'")->fetchColumn();
$active_services_count = (int)$db->query("SELECT COUNT(*) FROM services WHERE provider_id = $provider_id AND status = 'active'")->fetchColumn();

// Fetch Recent Inbound Booking Calls (latest 5)
$recent_stmt = $db->prepare("
    SELECT sr.*, u.full_name as citizen_name, u.phone as citizen_phone,
           l.village_name as citizen_village
    FROM service_requests sr 
    JOIN users u ON sr.citizen_id = u.id 
    LEFT JOIN citizen_profiles cp ON u.id = cp.user_id
    LEFT JOIN locations l ON cp.location_id = l.id
    WHERE sr.provider_id = :pid 
    ORDER BY sr.created_at DESC 
    LIMIT 5
");
$recent_stmt->execute([':pid' => $provider_id]);
$recent_calls = $recent_stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="dashboard-wrapper">
    <!-- Sidebar -->
    <div class="dashboard-sidebar">
        <div class="sidebar-heading">Technician Portal</div>
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/provider/dashboard.php" class="sidebar-link active"><i class="fa-solid fa-gauge"></i> Overview</a></li>
            <li class="sidebar-menu-item">
                <a href="<?= BASE_URL ?>/provider/requests.php" class="sidebar-link">
                    <i class="fa-solid fa-list-check"></i> Job Requests 
                    <?php if ($pending_requests > 0): ?>
                        <span class="badge bg-warning text-dark ms-auto"><?= $pending_requests ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/provider/services.php" class="sidebar-link"><i class="fa-solid fa-screwdriver-wrench"></i> Services & Hourly Rates</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/provider/profile.php" class="sidebar-link"><i class="fa-solid fa-user-gear"></i> Bio & Credentials</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="dashboard-content">
        <?= render_flash_messages() ?>

        <!-- Welcome & Status Toggle Banner -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white border-start border-4 <?= $provider['is_available'] ? 'border-success' : 'border-secondary' ?>">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h3 class="fw-bold text-dark mb-0"><?= e($user['full_name']) ?></h3>
                        <?= get_status_badge($provider['verification_status']) ?>
                        <span class="badge <?= $provider['is_available'] ? 'bg-success' : 'bg-secondary' ?>">
                            <i class="fa-solid <?= $provider['is_available'] ? 'fa-circle-check' : 'fa-clock' ?> me-1"></i>
                            <?= $provider['is_available'] ? 'Available Now' : 'Busy / Off Duty' ?>
                        </span>
                    </div>
                    <p class="text-muted mb-0">
                        <i class="<?= e($provider['icon_class']) ?> text-primary-gs me-1"></i> 
                        Specialty: <strong><?= e($provider['category_name']) ?></strong> (<?= (int)$provider['experience_years'] ?> Years Experience) â€¢ 
                        <i class="fa-solid fa-location-dot text-danger ms-1 me-1"></i> 
                        <?= e($provider['village_name'] ?? 'Panchayat Area') ?> (GP: <?= e($provider['gram_panchayat'] ?? '') ?>)
                    </p>
                </div>
                
                <div class="d-flex gap-2">
                    <form action="<?= BASE_URL ?>/provider/dashboard.php" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="toggle_availability" value="1">
                        <button type="submit" class="btn <?= $provider['is_available'] ? 'btn-outline-danger' : 'btn-success' ?> btn-sm d-flex align-items-center gap-2">
                            <i class="fa-solid fa-power-off"></i>
                            <span><?= $provider['is_available'] ? 'Go Off Duty' : 'Set Available Now' ?></span>
                        </button>
                    </form>
                    <a href="<?= BASE_URL ?>/provider/services.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-indian-rupee-sign me-1"></i> Manage Rates
                    </a>
                </div>
            </div>
        </div>

        <!-- Metrics Row -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div>
                        <div class="stat-value text-primary-gs"><?= $total_requests ?></div>
                        <div class="stat-label">Total Booking Calls</div>
                    </div>
                    <div class="stat-icon bg-primary-gs text-white"><i class="fa-solid fa-phone-volume"></i></div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div>
                        <div class="stat-value text-warning"><?= $pending_requests ?></div>
                        <div class="stat-label">Pending Confirmation</div>
                    </div>
                    <div class="stat-icon bg-warning text-dark"><i class="fa-solid fa-clock"></i></div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div>
                        <div class="stat-value text-success">â‚¹<?= number_format($provider['hourly_rate'], 0) ?>/hr</div>
                        <div class="stat-label">Standard Hourly Rate</div>
                    </div>
                    <div class="stat-icon bg-success text-white"><i class="fa-solid fa-indian-rupee-sign"></i></div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div>
                        <div class="stat-value text-info"><?= $active_services_count ?></div>
                        <div class="stat-label">Active Service Packages</div>
                    </div>
                    <div class="stat-icon bg-info text-white"><i class="fa-solid fa-screwdriver-wrench"></i></div>
                </div>
            </div>
        </div>

        <!-- Recent Inbound Booking Calls Table -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-calendar-check text-primary-gs me-2"></i> Inbound Citizen Service Calls</h5>
                <a href="<?= BASE_URL ?>/provider/requests.php" class="btn btn-sm btn-link text-primary-gs fw-semibold text-decoration-none">
                    View All (<?= $total_requests ?>) <i class="fa-solid fa-arrow-right small"></i>
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recent_calls)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fa-solid fa-phone-slash display-5 text-secondary mb-2"></i>
                        <h6 class="fw-bold text-dark">No Job Requests Received Yet</h6>
                        <p class="small text-muted mb-0">When rural citizens book repair or technician services from your profile, requests appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Citizen & Contact</th>
                                    <th>Work Requirements</th>
                                    <th>Preferred Date</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_calls as $rc): ?>
                                    <tr>
                                        <td><strong>#SR-<?= $rc['id'] ?></strong></td>
                                        <td>
                                            <strong class="text-dark d-block"><?= e($rc['citizen_name']) ?></strong>
                                            <small class="text-muted">
                                                <a href="tel:<?= e($rc['citizen_phone']) ?>" class="text-decoration-none text-success">
                                                    <i class="fa-solid fa-phone me-1"></i><?= e($rc['citizen_phone']) ?>
                                                </a>
                                                <?php if (!empty($rc['citizen_village'])): ?>
                                                    â€¢ <?= e($rc['citizen_village']) ?>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <small class="text-dark d-block" style="max-width: 250px;"><?= e($rc['details']) ?></small>
                                            <small class="text-muted"><i class="fa-solid fa-clock me-1"></i> <?= format_date($rc['created_at'], 'd M, h:i A') ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= format_date($rc['preferred_date'], 'd M Y') ?></span>
                                        </td>
                                        <td><?= get_status_badge($rc['status']) ?></td>
                                        <td class="text-end">
                                            <?php if ($rc['status'] === 'pending'): ?>
                                                <div class="btn-group btn-group-sm">
                                                    <form action="<?= BASE_URL ?>/provider/dashboard.php" method="POST" class="d-inline">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="quick_action" value="1">
                                                        <input type="hidden" name="request_id" value="<?= $rc['id'] ?>">
                                                        <input type="hidden" name="action" value="accepted">
                                                        <button type="submit" class="btn btn-success btn-sm py-1 px-2" title="Accept Call">
                                                            <i class="fa-solid fa-check me-1"></i> Accept
                                                        </button>
                                                    </form>
                                                    <form action="<?= BASE_URL ?>/provider/dashboard.php" method="POST" class="d-inline ms-1">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="quick_action" value="1">
                                                        <input type="hidden" name="request_id" value="<?= $rc['id'] ?>">
                                                        <input type="hidden" name="action" value="cancelled">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm py-1 px-2" onclick="return confirm('Decline this service call?')" title="Decline Call">
                                                            <i class="fa-solid fa-xmark"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <a href="<?= BASE_URL ?>/provider/requests.php" class="btn btn-sm btn-outline-secondary py-1 px-2">
                                                    View Details
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>