<?php
$page_title = "Inbound Job Requests | GraamSetu Provider";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('provider');
$user = current_user();
$db = Database::getConnection();

// Fetch provider profile
$provider_stmt = $db->prepare("SELECT id FROM service_providers WHERE user_id = :uid");
$provider_stmt->execute([':uid' => $user['id']]);
$provider = $provider_stmt->fetch();

if (!$provider) {
    die("Service provider profile missing. Please contact Gram Panchayat Administrator.");
}
$provider_id = (int)$provider['id'];

// Handle Job Status Updates (Accept, Start, Complete, Cancel)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_job_status'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $req_id = (int)$_POST['request_id'];
        $new_status = trim($_POST['status'] ?? '');
        $allowed_statuses = ['pending', 'accepted', 'in_progress', 'completed', 'cancelled'];

        if (in_array($new_status, $allowed_statuses)) {
            // Verify request ownership
            $chk = $db->prepare("SELECT citizen_id, preferred_date FROM service_requests WHERE id = ? AND provider_id = ?");
            $chk->execute([$req_id, $provider_id]);
            $req = $chk->fetch();

            if ($req) {
                $upd = $db->prepare("UPDATE service_requests SET status = ? WHERE id = ? AND provider_id = ?");
                $upd->execute([$new_status, $req_id, $provider_id]);

                // Human-readable status messages
                $status_labels = [
                    'accepted' => 'confirmed and accepted',
                    'in_progress' => 'marked In-Progress (technician on the way / working)',
                    'completed' => 'completed successfully',
                    'cancelled' => 'declined / cancelled'
                ];
                $msg_text = $status_labels[$new_status] ?? $new_status;

                // Create Portal Notification for Citizen
                $db->prepare("INSERT INTO notifications (user_id, title, message, link, is_read) VALUES (?, ?, ?, ?, 0)")
                   ->execute([
                       $req['citizen_id'], 
                       'Service Booking Status: ' . ucfirst(str_replace('_', ' ', $new_status)), 
                       "Technician {$user['full_name']} has $msg_text your booking call for {$req['preferred_date']}.", 
                       'citizen/requests.php'
                   ]);

                set_flash('success', "Service call #SR-$req_id status changed to: " . ucfirst(str_replace('_', ' ', $new_status)));
            } else {
                set_flash('danger', 'Unauthorized: Request does not belong to your account.');
            }
            header('Location: ' . BASE_URL . '/provider/requests.php');
            exit;
        } else {
            set_flash('danger', 'Invalid status selected.');
        }
    } else {
        set_flash('danger', 'Security session expired. Please submit again.');
    }
}

// Filter parameters (Database-driven)
$status_filter = trim($_GET['status'] ?? 'all');
$search_q = trim($_GET['q'] ?? '');

$sql = "
    SELECT sr.*, 
           COALESCE(s.title, 'General Service Call') as service_package_title,
           u.full_name as citizen_name, u.phone as citizen_phone,
           l.village_name as citizen_village, l.district as citizen_district
    FROM service_requests sr
    JOIN users u ON sr.citizen_id = u.id
    LEFT JOIN citizen_profiles cp ON u.id = cp.user_id
    LEFT JOIN locations l ON cp.location_id = l.id
    LEFT JOIN services s ON sr.service_id = s.id
    WHERE sr.provider_id = :pid
";
$params = [':pid' => $provider_id];

if ($status_filter !== 'all' && in_array($status_filter, ['pending', 'accepted', 'in_progress', 'completed', 'cancelled'])) {
    $sql .= " AND sr.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($search_q)) {
    $sql .= " AND (u.full_name LIKE :q OR sr.details LIKE :q2 OR u.phone LIKE :q3)";
    $params[':q'] = "%$search_q%";
    $params[':q2'] = "%$search_q%";
    $params[':q3'] = "%$search_q%";
}

$sql .= " ORDER BY sr.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

// Counts for filter badges
$count_all = (int)$db->query("SELECT COUNT(*) FROM service_requests WHERE provider_id = $provider_id")->fetchColumn();
$count_pending = (int)$db->query("SELECT COUNT(*) FROM service_requests WHERE provider_id = $provider_id AND status = 'pending'")->fetchColumn();
$count_accepted = (int)$db->query("SELECT COUNT(*) FROM service_requests WHERE provider_id = $provider_id AND status = 'accepted'")->fetchColumn();
$count_in_progress = (int)$db->query("SELECT COUNT(*) FROM service_requests WHERE provider_id = $provider_id AND status = 'in_progress'")->fetchColumn();
$count_completed = (int)$db->query("SELECT COUNT(*) FROM service_requests WHERE provider_id = $provider_id AND status = 'completed'")->fetchColumn();
$count_cancelled = (int)$db->query("SELECT COUNT(*) FROM service_requests WHERE provider_id = $provider_id AND status = 'cancelled'")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="dashboard-wrapper">
    <!-- Sidebar -->
    <div class="dashboard-sidebar">
        <div class="sidebar-heading">Technician Portal</div>
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/provider/dashboard.php" class="sidebar-link"><i class="fa-solid fa-gauge"></i> Overview</a></li>
            <li class="sidebar-menu-item">
                <a href="<?= BASE_URL ?>/provider/requests.php" class="sidebar-link active">
                    <i class="fa-solid fa-list-check"></i> Job Requests 
                    <?php if ($count_pending > 0): ?>
                        <span class="badge bg-warning text-dark ms-auto"><?= $count_pending ?></span>
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

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-list-check text-primary-gs me-2"></i> Inbound Citizen Job Calls
                </h3>
                <p class="text-muted small mb-0">Review service requests, accept or decline calls, and track ongoing jobs to completion.</p>
            </div>
        </div>

        <!-- Filter & Search Controls -->
        <div class="card border-0 shadow-sm rounded-4 p-3 mb-4 bg-white">
            <form action="<?= BASE_URL ?>/provider/requests.php" method="GET" class="row g-3 align-items-center">
                <div class="col-md-7">
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" name="q" class="form-control form-control-gs" placeholder="Search by citizen name, phone, or work details..." value="<?= e($search_q) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-gs" onchange="this.form.submit()">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Requests (<?= $count_all ?>)</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending Confirmation (<?= $count_pending ?>)</option>
                        <option value="accepted" <?= $status_filter === 'accepted' ? 'selected' : '' ?>>Accepted / Scheduled (<?= $count_accepted ?>)</option>
                        <option value="in_progress" <?= $status_filter === 'in_progress' ? 'selected' : '' ?>>In Progress (<?= $count_in_progress ?>)</option>
                        <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed Jobs (<?= $count_completed ?>)</option>
                        <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled / Declined (<?= $count_cancelled ?>)</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary-gs btn-sm w-100">Filter</button>
                    <?php if (!empty($search_q) || $status_filter !== 'all'): ?>
                        <a href="<?= BASE_URL ?>/provider/requests.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Requests List Table -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
            <div class="card-body p-0">
                <?php if (empty($jobs)): ?>
                    <div class="text-center py-5">
                        <i class="fa-solid fa-phone-slash text-muted display-4 mb-3"></i>
                        <h5 class="fw-bold text-dark">No Job Calls Found</h5>
                        <p class="text-muted small mb-0">When village citizens submit service bookings, they will appear in this list.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Citizen & Location</th>
                                    <th>Work Requirements</th>
                                    <th>Preferred Date</th>
                                    <th>Current Status</th>
                                    <th class="text-end">Update Workflow</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jobs as $j): ?>
                                    <tr>
                                        <td><strong>#SR-<?= $j['id'] ?></strong></td>
                                        <td>
                                            <strong class="text-dark d-block"><?= e($j['citizen_name']) ?></strong>
                                            <small class="text-muted">
                                                <a href="tel:<?= e($j['citizen_phone']) ?>" class="text-decoration-none text-success">
                                                    <i class="fa-solid fa-phone me-1"></i><?= e($j['citizen_phone']) ?>
                                                </a>
                                                <?php if (!empty($j['citizen_village'])): ?>
                                                    <br><i class="fa-solid fa-location-dot text-danger me-1"></i><?= e($j['citizen_village']) ?>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <small class="text-dark d-block" style="max-width: 250px;"><?= e($j['details']) ?></small>
                                            <small class="text-muted"><i class="fa-solid fa-clock me-1"></i><?= format_date($j['created_at'], 'd M Y, h:i A') ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= format_date($j['preferred_date'], 'd M Y') ?></span>
                                        </td>
                                        <td><?= get_status_badge($j['status']) ?></td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <?php if ($j['status'] === 'pending'): ?>
                                                    <form action="<?= BASE_URL ?>/provider/requests.php" method="POST" class="d-inline">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="update_job_status" value="1">
                                                        <input type="hidden" name="request_id" value="<?= $j['id'] ?>">
                                                        <input type="hidden" name="status" value="accepted">
                                                        <button type="submit" class="btn btn-success btn-sm py-1 px-2" title="Accept and confirm booking">
                                                            <i class="fa-solid fa-check me-1"></i> Accept
                                                        </button>
                                                    </form>
                                                    <form action="<?= BASE_URL ?>/provider/requests.php" method="POST" class="d-inline ms-1">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="update_job_status" value="1">
                                                        <input type="hidden" name="request_id" value="<?= $j['id'] ?>">
                                                        <input type="hidden" name="status" value="cancelled">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm py-1 px-2" onclick="return confirm('Decline this service call?')" title="Decline call">
                                                            <i class="fa-solid fa-xmark me-1"></i> Decline
                                                        </button>
                                                    </form>
                                                <?php elseif ($j['status'] === 'accepted'): ?>
                                                    <form action="<?= BASE_URL ?>/provider/requests.php" method="POST" class="d-inline">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="update_job_status" value="1">
                                                        <input type="hidden" name="request_id" value="<?= $j['id'] ?>">
                                                        <input type="hidden" name="status" value="in_progress">
                                                        <button type="submit" class="btn btn-primary-gs btn-sm py-1 px-2">
                                                            <i class="fa-solid fa-play me-1"></i> Start Job
                                                        </button>
                                                    </form>
                                                <?php elseif ($j['status'] === 'in_progress'): ?>
                                                    <form action="<?= BASE_URL ?>/provider/requests.php" method="POST" class="d-inline">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="update_job_status" value="1">
                                                        <input type="hidden" name="request_id" value="<?= $j['id'] ?>">
                                                        <input type="hidden" name="status" value="completed">
                                                        <button type="submit" class="btn btn-success btn-sm py-1 px-2">
                                                            <i class="fa-solid fa-circle-check me-1"></i> Mark Completed
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted small">Job Closed</span>
                                                <?php endif; ?>
                                            </div>
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