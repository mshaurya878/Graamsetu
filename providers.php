<?php
$page_title = "Provider Verifications | GraamSetu Admin";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');
$user = current_user();
$db = Database::getConnection();

// Handle Verification Action (Approve / Reject)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $pid = (int)$_GET['id'];
    $action = $_GET['action'];
    $status = match ($action) {
        'approve' => 'verified',
        'reject' => 'rejected',
        default => 'pending',
    };
    $is_verified = $status === 'verified' ? 1 : 0;

    $upd = $db->prepare("UPDATE service_providers SET verification_status = ?, is_verified = ? WHERE id = ?");
    $upd->execute([$status, $is_verified, $pid]);

    // Notify Provider User
    $p_stmt = $db->prepare("
        SELECT sp.user_id, u.full_name, c.name as category_name
        FROM service_providers sp 
        JOIN users u ON sp.user_id = u.id 
        JOIN categories c ON sp.service_category_id = c.id 
        WHERE sp.id = ?
    ");
    $p_stmt->execute([$pid]);
    $p_info = $p_stmt->fetch();

    if ($p_info) {
        $msg = $status === 'verified' 
            ? "Congratulations {$p_info['full_name']}! Your trade profile ({$p_info['category_name']}) has been officially verified by Gram Panchayat. You are now live on the Citizen Discovery Hub!" 
            : "Verification for your trade profile was updated to $status by the Gram Panchayat.";

        // Schema-compliant column 'link'
        $db->prepare("INSERT INTO notifications (user_id, title, message, link, is_read) VALUES (?, ?, ?, 'provider/dashboard.php', 0)")
           ->execute([$p_info['user_id'], 'Technician Verification Status Update', $msg]);

        // Audit Log
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $db->prepare("INSERT INTO admin_logs (admin_id, action, target_table, target_id, details, ip_address) VALUES (?, 'Provider Verification', 'service_providers', ?, ?, ?)")
           ->execute([$user['id'], $pid, "Updated verification of technician {$p_info['full_name']} to $status", $ip]);
    }

    set_flash('success', 'Technician verification status updated to: ' . ucfirst($status));
    header('Location: ' . BASE_URL . '/admin/providers.php');
    exit;
}

// Filter parameters
$status_filter = trim($_GET['status'] ?? 'all');
$query_sql = "
    SELECT sp.*, u.full_name, u.phone, u.email,
           c.name as category_name, c.icon_class,
           l.village_name, l.gram_panchayat, l.district
    FROM service_providers sp 
    JOIN users u ON sp.user_id = u.id 
    JOIN categories c ON sp.service_category_id = c.id 
    LEFT JOIN locations l ON sp.location_id = l.id
    WHERE 1=1
";
$query_params = [];

if ($status_filter !== 'all' && in_array($status_filter, ['pending', 'verified', 'rejected'])) {
    $query_sql .= " AND sp.verification_status = :status";
    $query_params[':status'] = $status_filter;
}

$query_sql .= " ORDER BY (sp.verification_status = 'pending') DESC, sp.created_at DESC";
$stmt = $db->prepare($query_sql);
$stmt->execute($query_params);
$providers = $stmt->fetchAll();

// Counts for filter pills
$count_all = (int)$db->query("SELECT COUNT(*) FROM service_providers")->fetchColumn();
$count_pending = (int)$db->query("SELECT COUNT(*) FROM service_providers WHERE verification_status = 'pending'")->fetchColumn();
$count_verified = (int)$db->query("SELECT COUNT(*) FROM service_providers WHERE verification_status = 'verified'")->fetchColumn();
$count_rejected = (int)$db->query("SELECT COUNT(*) FROM service_providers WHERE verification_status = 'rejected'")->fetchColumn();
?>

<div class="dashboard-wrapper">
    <!-- Sidebar -->
    <div class="dashboard-sidebar">
        <div class="sidebar-heading">Admin Control Center</div>
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/admin/dashboard.php" class="sidebar-link"><i class="fa-solid fa-chart-pie"></i> Control Panel</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/admin/users.php" class="sidebar-link"><i class="fa-solid fa-users"></i> Manage Citizens</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/admin/merchants.php" class="sidebar-link"><i class="fa-solid fa-shop"></i> Verified Merchants</a></li>
            <li class="sidebar-menu-item">
                <a href="<?= BASE_URL ?>/admin/providers.php" class="sidebar-link active">
                    <i class="fa-solid fa-screwdriver-wrench"></i> Service Providers
                    <?php if ($count_pending > 0): ?>
                        <span class="badge bg-warning text-dark ms-auto"><?= $count_pending ?></span>
                    <?php endif; ?>
                </a>
            </li>
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
                <h3 class="fw-bold text-dark mb-1"><i class="fa-solid fa-screwdriver-wrench text-secondary-gs me-2"></i> Service Technician Verifications</h3>
                <p class="text-muted small mb-0">Approve village electricians, plumbers, borewell mechanics, and tractor operators.</p>
            </div>
            <div class="btn-group btn-group-sm">
                <a href="<?= BASE_URL ?>/admin/providers.php?status=all" class="btn <?= $status_filter === 'all' ? 'btn-primary-gs' : 'btn-outline-secondary' ?>">All (<?= $count_all ?>)</a>
                <a href="<?= BASE_URL ?>/admin/providers.php?status=pending" class="btn <?= $status_filter === 'pending' ? 'btn-warning text-dark' : 'btn-outline-secondary' ?>">Pending (<?= $count_pending ?>)</a>
                <a href="<?= BASE_URL ?>/admin/providers.php?status=verified" class="btn <?= $status_filter === 'verified' ? 'btn-success' : 'btn-outline-secondary' ?>">Verified (<?= $count_verified ?>)</a>
                <a href="<?= BASE_URL ?>/admin/providers.php?status=rejected" class="btn <?= $status_filter === 'rejected' ? 'btn-danger' : 'btn-outline-secondary' ?>">Rejected (<?= $count_rejected ?>)</a>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
            <div class="card-body p-0">
                <?php if (empty($providers)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fa-solid fa-toolbox display-5 mb-2 text-secondary"></i>
                        <h6 class="fw-bold text-dark">No Technicians Found in this Queue</h6>
                        <p class="small text-muted mb-0">New technician registrations awaiting skill verification will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Technician Name & Contact</th>
                                    <th>Specialty & Experience</th>
                                    <th>Village Location</th>
                                    <th>Hourly Rate</th>
                                    <th>Status</th>
                                    <th class="text-end">Verification Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($providers as $p): ?>
                                    <tr>
                                        <td>
                                            <strong class="text-dark d-block"><?= e($p['full_name']) ?></strong>
                                            <small class="text-muted">
                                                <a href="tel:<?= e($p['phone']) ?>" class="text-decoration-none text-success">
                                                    <i class="fa-solid fa-phone me-1"></i><?= e($p['phone']) ?>
                                                </a>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border mb-1">
                                                <i class="<?= e($p['icon_class']) ?> text-primary-gs me-1"></i> <?= e($p['category_name']) ?>
                                            </span>
                                            <small class="text-muted d-block"><?= (int)$p['experience_years'] ?> Years Experience</small>
                                        </td>
                                        <td>
                                            <small class="text-dark d-block"><i class="fa-solid fa-location-dot text-danger me-1"></i> <?= e($p['village_name'] ?? 'Rampur Village') ?></small>
                                            <small class="text-muted"><?= e($p['address'] ?: 'Village Center') ?></small>
                                        </td>
                                        <td>
                                            <strong class="text-success">₹<?= number_format($p['hourly_rate'], 0) ?>/hr</strong>
                                        </td>
                                        <td><?= get_status_badge($p['verification_status']) ?></td>
                                        <td class="text-end">
                                            <?php if ($p['verification_status'] === 'pending'): ?>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="<?= BASE_URL ?>/admin/providers.php?action=approve&id=<?= $p['id'] ?>" class="btn btn-success py-1 px-2" title="Approve Technician">
                                                        <i class="fa-solid fa-check me-1"></i> Approve
                                                    </a>
                                                    <a href="<?= BASE_URL ?>/admin/providers.php?action=reject&id=<?= $p['id'] ?>" class="btn btn-outline-danger py-1 px-2" onclick="return confirm('Reject verification for <?= htmlspecialchars(addslashes($p['full_name'])) ?>?')" title="Reject Technician">
                                                        <i class="fa-solid fa-xmark"></i> Reject
                                                    </a>
                                                </div>
                                            <?php elseif ($p['verification_status'] === 'verified'): ?>
                                                <a href="<?= BASE_URL ?>/admin/providers.php?action=reject&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger py-1 px-2" onclick="return confirm('Revoke verification for this technician?')">
                                                    Revoke
                                                </a>
                                            <?php else: ?>
                                                <a href="<?= BASE_URL ?>/admin/providers.php?action=approve&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-success py-1 px-2">
                                                    Re-Approve
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
