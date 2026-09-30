<?php
$page_title = "Platform Settings & Audit Logs | GraamSetu Admin";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');
$user = current_user();
$db = Database::getConnection();

// Fetch Audit Logs with Pagination/Limit
$logs = $db->query("
    SELECT al.*, u.full_name as admin_name, u.email as admin_email
    FROM admin_logs al 
    JOIN users u ON al.admin_id = u.id 
    ORDER BY al.created_at DESC
    LIMIT 100
")->fetchAll();

// Diagnostics Queries
$total_users = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_locations = (int)$db->query("SELECT COUNT(*) FROM locations")->fetchColumn();
$total_categories = (int)$db->query("SELECT COUNT(*) FROM categories")->fetchColumn();
?>

<div class="dashboard-wrapper">
    <!-- Sidebar -->
    <div class="dashboard-sidebar">
        <div class="sidebar-heading">Admin Control Center</div>
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/admin/dashboard.php" class="sidebar-link"><i class="fa-solid fa-chart-pie"></i> Control Panel</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/admin/users.php" class="sidebar-link"><i class="fa-solid fa-users"></i> Manage Citizens</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/admin/merchants.php" class="sidebar-link"><i class="fa-solid fa-shop"></i> Verified Merchants</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/admin/providers.php" class="sidebar-link"><i class="fa-solid fa-screwdriver-wrench"></i> Service Providers</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/admin/complaints.php" class="sidebar-link"><i class="fa-solid fa-triangle-exclamation"></i> Grievances</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/admin/requests.php" class="sidebar-link"><i class="fa-solid fa-list-check"></i> Service Requests</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/admin/announcements.php" class="sidebar-link"><i class="fa-solid fa-bullhorn"></i> Village Notices</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/admin/categories.php" class="sidebar-link"><i class="fa-solid fa-tags"></i> Categories</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/admin/settings.php" class="sidebar-link active"><i class="fa-solid fa-sliders"></i> Audit Logs</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="dashboard-content">
        <?= render_flash_messages() ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1"><i class="fa-solid fa-shield-halved text-primary-gs me-2"></i> System Audit Logs & Diagnostics</h3>
                <p class="text-muted small mb-0">Platform administrative activity trails, security status, and environment health.</p>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                    <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-server text-secondary-gs me-2"></i> Environment Diagnostics</h5>
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between px-0"><span>PHP Runtime:</span> <strong><?= phpversion() ?></strong></li>
                        <li class="list-group-item d-flex justify-content-between px-0"><span>Database Engine:</span> <strong>MariaDB / MySQL (XAMPP PDO)</strong></li>
                        <li class="list-group-item d-flex justify-content-between px-0"><span>Password Encryption:</span> <strong class="text-success">BCRYPT (Cost 10)</strong></li>
                        <li class="list-group-item d-flex justify-content-between px-0"><span>CSRF Protection:</span> <strong class="text-success">Enabled (64-Char Hash)</strong></li>
                        <li class="list-group-item d-flex justify-content-between px-0"><span>Active Locations:</span> <strong><?= $total_locations ?> Gram Panchayats</strong></li>
                        <li class="list-group-item d-flex justify-content-between px-0"><span>System Categories:</span> <strong><?= $total_categories ?> Classifications</strong></li>
                    </ul>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                    <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-lock text-primary-gs me-2"></i> Role-Based Permissions (RBAC)</h5>
                    <p class="small text-muted mb-3">Role permissions are strictly verified at runtime using middleware (<code>require_role()</code>). Suspended users are immediately logged out upon their next HTTP interaction.</p>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge bg-danger py-2 px-3"><i class="fa-solid fa-crown me-1"></i> Admin (ID 4)</span>
                        <span class="badge bg-success py-2 px-3"><i class="fa-solid fa-shop me-1"></i> Merchant (ID 2)</span>
                        <span class="badge bg-warning text-dark py-2 px-3"><i class="fa-solid fa-screwdriver-wrench me-1"></i> Provider (ID 3)</span>
                        <span class="badge bg-primary-gs py-2 px-3"><i class="fa-solid fa-users me-1"></i> Citizen (ID 1)</span>
                    </div>
                    <div class="small text-muted">
                        Total active registered accounts on platform: <strong><?= $total_users ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-list-ol text-dark me-2"></i> Complete Administrative Audit Trail</h5>
                <span class="badge bg-light text-dark border"><?= count($logs) ?> Recorded Entries</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($logs)): ?>
                    <div class="text-center py-5 text-muted">
                        <p class="mb-0">No audit trail records present.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Log ID</th>
                                    <th>Admin Officer</th>
                                    <th>Action Performed</th>
                                    <th>Target Entity</th>
                                    <th>Action Description</th>
                                    <th>Client IP</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $l): ?>
                                    <tr>
                                        <td><strong>#LOG-<?= $l['id'] ?></strong></td>
                                        <td>
                                            <strong class="text-dark d-block"><?= e($l['admin_name']) ?></strong>
                                            <small class="text-muted"><?= e($l['admin_email']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= e($l['action']) ?></span>
                                        </td>
                                        <td>
                                            <?php if (!empty($l['target_table'])): ?>
                                                <code><?= e($l['target_table']) ?><?= $l['target_id'] ? '#' . $l['target_id'] : '' ?></code>
                                            <?php else: ?>
                                                <span class="text-muted small">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><small class="text-dark"><?= e($l['details']) ?></small></td>
                                        <td><small class="text-muted font-monospace"><?= e($l['ip_address']) ?></small></td>
                                        <td><small class="text-muted"><?= format_date($l['created_at'], 'd M Y, h:i A') ?></small></td>
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
