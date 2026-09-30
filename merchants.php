<?php
$page_title = "Merchant Verifications | GraamSetu Admin";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');
$user = current_user();
$db = Database::getConnection();

// Handle Verification Action (Approve / Reject)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $mid = (int)$_GET['id'];
    $action = $_GET['action'];
    $status = match ($action) {
        'approve' => 'verified',
        'reject' => 'rejected',
        default => 'pending',
    };
    $is_verified = $status === 'verified' ? 1 : 0;

    $upd = $db->prepare("UPDATE merchant_profiles SET verification_status = ?, is_verified = ? WHERE id = ?");
    $upd->execute([$status, $is_verified, $mid]);

    // Notify Merchant User
    $m_stmt = $db->prepare("SELECT user_id, business_name FROM merchant_profiles WHERE id = ?");
    $m_stmt->execute([$mid]);
    $m_info = $m_stmt->fetch();

    if ($m_info) {
        $msg = $status === 'verified' 
            ? 'Congratulations! Your shop "' . $m_info['business_name'] . '" has been verified by the Gram Panchayat Administration. You are now live on the Citizen Discovery Hub!' 
            : 'Verification for "' . $m_info['business_name'] . '" was updated to ' . $status . ' by the Panchayat.';

        // Schema-compliant column 'link'
        $db->prepare("INSERT INTO notifications (user_id, title, message, link, is_read) VALUES (?, ?, ?, 'merchant/dashboard.php', 0)")
           ->execute([$m_info['user_id'], 'Merchant Verification Status Update', $msg]);

        // Audit Log
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $db->prepare("INSERT INTO admin_logs (admin_id, action, target_table, target_id, details, ip_address) VALUES (?, 'Merchant Verification', 'merchant_profiles', ?, ?, ?)")
           ->execute([$user['id'], $mid, "Updated verification of {$m_info['business_name']} to $status", $ip]);
    }

    set_flash('success', 'Merchant verification status updated to: ' . ucfirst($status));
    header('Location: ' . BASE_URL . '/admin/merchants.php');
    exit;
}

// Filter parameters
$status_filter = trim($_GET['status'] ?? 'all');
$query_sql = "
    SELECT mp.*, u.full_name as owner_name, u.phone as owner_phone, u.email as owner_email,
           c.name as category_name, c.icon_class,
           l.village_name, l.gram_panchayat, l.district
    FROM merchant_profiles mp 
    JOIN users u ON mp.user_id = u.id 
    JOIN categories c ON mp.category_id = c.id 
    LEFT JOIN locations l ON mp.location_id = l.id
    WHERE 1=1
";
$query_params = [];

if ($status_filter !== 'all' && in_array($status_filter, ['pending', 'verified', 'rejected'])) {
    $query_sql .= " AND mp.verification_status = :status";
    $query_params[':status'] = $status_filter;
}

$query_sql .= " ORDER BY (mp.verification_status = 'pending') DESC, mp.created_at DESC";
$stmt = $db->prepare($query_sql);
$stmt->execute($query_params);
$merchants = $stmt->fetchAll();

// Counts for filter pills
$count_all = (int)$db->query("SELECT COUNT(*) FROM merchant_profiles")->fetchColumn();
$count_pending = (int)$db->query("SELECT COUNT(*) FROM merchant_profiles WHERE verification_status = 'pending'")->fetchColumn();
$count_verified = (int)$db->query("SELECT COUNT(*) FROM merchant_profiles WHERE verification_status = 'verified'")->fetchColumn();
$count_rejected = (int)$db->query("SELECT COUNT(*) FROM merchant_profiles WHERE verification_status = 'rejected'")->fetchColumn();
?>

<div class="dashboard-wrapper">
    <!-- Sidebar -->
    <div class="dashboard-sidebar">
        <div class="sidebar-heading">Admin Control Center</div>
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/admin/dashboard.php" class="sidebar-link"><i class="fa-solid fa-chart-pie"></i> Control Panel</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/admin/users.php" class="sidebar-link"><i class="fa-solid fa-users"></i> Manage Citizens</a></li>
            <li class="sidebar-menu-item">
                <a href="<?= BASE_URL ?>/admin/merchants.php" class="sidebar-link active">
                    <i class="fa-solid fa-shop"></i> Verified Merchants
                    <?php if ($count_pending > 0): ?>
                        <span class="badge bg-warning text-dark ms-auto"><?= $count_pending ?></span>
                    <?php endif; ?>
                </a>
            </li>
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
                <h3 class="fw-bold text-dark mb-1"><i class="fa-solid fa-shop text-primary-gs me-2"></i> Merchant Verification Queue</h3>
                <p class="text-muted small mb-0">Approve local Kirana stores, medical pharmacies, fertilizer seeds dealers, and CSC digital centers.</p>
            </div>
            <div class="btn-group btn-group-sm">
                <a href="<?= BASE_URL ?>/admin/merchants.php?status=all" class="btn <?= $status_filter === 'all' ? 'btn-primary-gs' : 'btn-outline-secondary' ?>">All (<?= $count_all ?>)</a>
                <a href="<?= BASE_URL ?>/admin/merchants.php?status=pending" class="btn <?= $status_filter === 'pending' ? 'btn-warning text-dark' : 'btn-outline-secondary' ?>">Pending (<?= $count_pending ?>)</a>
                <a href="<?= BASE_URL ?>/admin/merchants.php?status=verified" class="btn <?= $status_filter === 'verified' ? 'btn-success' : 'btn-outline-secondary' ?>">Verified (<?= $count_verified ?>)</a>
                <a href="<?= BASE_URL ?>/admin/merchants.php?status=rejected" class="btn <?= $status_filter === 'rejected' ? 'btn-danger' : 'btn-outline-secondary' ?>">Rejected (<?= $count_rejected ?>)</a>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
            <div class="card-body p-0">
                <?php if (empty($merchants)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fa-solid fa-store-slash display-5 mb-2 text-secondary"></i>
                        <h6 class="fw-bold text-dark">No Merchants Found in this Queue</h6>
                        <p class="small text-muted mb-0">New merchant registrations awaiting Gram Panchayat approval will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Shop Name & Owner</th>
                                    <th>Category</th>
                                    <th>Village & Market Address</th>
                                    <th>Operating Hours</th>
                                    <th>Status</th>
                                    <th class="text-end">Verification Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($merchants as $m): ?>
                                    <tr>
                                        <td>
                                            <strong class="text-dark d-block"><?= e($m['business_name']) ?></strong>
                                            <small class="text-muted">
                                                <i class="fa-solid fa-user me-1"></i> <?= e($m['owner_name']) ?> • 
                                                <a href="tel:<?= e($m['owner_phone']) ?>" class="text-decoration-none text-success">
                                                    <i class="fa-solid fa-phone ms-1 me-1"></i><?= e($m['owner_phone']) ?>
                                                </a>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <i class="<?= e($m['icon_class']) ?> text-primary-gs me-1"></i> <?= e($m['category_name']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-dark d-block"><i class="fa-solid fa-location-dot text-danger me-1"></i> <?= e($m['village_name'] ?? 'Rampur Village') ?></small>
                                            <small class="text-muted"><?= e($m['address']) ?></small>
                                        </td>
                                        <td><small class="text-dark"><?= e($m['opening_hours'] ?? '08:00 AM - 08:00 PM') ?></small></td>
                                        <td><?= get_status_badge($m['verification_status']) ?></td>
                                        <td class="text-end">
                                            <?php if ($m['verification_status'] === 'pending'): ?>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="<?= BASE_URL ?>/admin/merchants.php?action=approve&id=<?= $m['id'] ?>" class="btn btn-success py-1 px-2" title="Approve Store">
                                                        <i class="fa-solid fa-check me-1"></i> Approve
                                                    </a>
                                                    <a href="<?= BASE_URL ?>/admin/merchants.php?action=reject&id=<?= $m['id'] ?>" class="btn btn-outline-danger py-1 px-2" onclick="return confirm('Reject verification for <?= htmlspecialchars(addslashes($m['business_name'])) ?>?')" title="Reject Store">
                                                        <i class="fa-solid fa-xmark"></i> Reject
                                                    </a>
                                                </div>
                                            <?php elseif ($m['verification_status'] === 'verified'): ?>
                                                <a href="<?= BASE_URL ?>/admin/merchants.php?action=reject&id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-danger py-1 px-2" onclick="return confirm('Revoke verification for this shop?')">
                                                    Revoke
                                                </a>
                                            <?php else: ?>
                                                <a href="<?= BASE_URL ?>/admin/merchants.php?action=approve&id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-success py-1 px-2">
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
