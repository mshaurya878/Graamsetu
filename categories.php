<?php
$page_title = "Manage Categories | GraamSetu Admin";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');
$user = current_user();
$db = Database::getConnection();

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $name = trim($_POST['name'] ?? '');
        $type = trim($_POST['type'] ?? 'business');
        $icon = trim($_POST['icon_class'] ?? 'fa-solid fa-store');
        $desc = trim($_POST['description'] ?? '');
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        $allowed_types = ['business', 'service', 'general'];

        if (empty($name)) {
            set_flash('danger', 'Category title is required.');
        } elseif (!in_array($type, $allowed_types)) {
            set_flash('danger', 'Invalid category type selected.');
        } else {
            $stmt = $db->prepare("INSERT INTO categories (name, slug, type, icon_class, description) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $slug, $type, $icon, $desc]);
            $new_cid = (int)$db->lastInsertId();

            // Audit Log
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $db->prepare("INSERT INTO admin_logs (admin_id, action, target_table, target_id, details, ip_address) VALUES (?, 'Created Category', 'categories', ?, ?, ?)")
               ->execute([$user['id'], $new_cid, "Created $type category: $name ($slug)", $ip]);

            set_flash('success', "Category '$name' created successfully!");
            header('Location: ' . BASE_URL . '/admin/categories.php');
            exit;
        }
    } else {
        set_flash('danger', 'Security session expired. Please submit again.');
    }
}

// Filter parameters
$type_filter = trim($_GET['type'] ?? 'all');
$sql = "SELECT * FROM categories WHERE 1=1";
$params = [];

if ($type_filter !== 'all' && in_array($type_filter, ['business', 'service', 'general'])) {
    $sql .= " AND type = :type";
    $params[':type'] = $type_filter;
}
$sql .= " ORDER BY type ASC, name ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$categories = $stmt->fetchAll();

// Counts for filter pills
$count_all = (int)$db->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$count_business = (int)$db->query("SELECT COUNT(*) FROM categories WHERE type = 'business'")->fetchColumn();
$count_service = (int)$db->query("SELECT COUNT(*) FROM categories WHERE type = 'service'")->fetchColumn();
$count_general = (int)$db->query("SELECT COUNT(*) FROM categories WHERE type = 'general'")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
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
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/admin/categories.php" class="sidebar-link active"><i class="fa-solid fa-tags"></i> Categories</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/admin/settings.php" class="sidebar-link"><i class="fa-solid fa-sliders"></i> Audit Logs</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="dashboard-content">
        <?= render_flash_messages() ?>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1"><i class="fa-solid fa-tags text-warning me-2"></i> Category Management</h3>
                <p class="text-muted small mb-0">Merchant business trades and technician skill classifications.</p>
            </div>
            <div class="d-flex gap-2">
                <div class="btn-group btn-group-sm">
                    <a href="<?= BASE_URL ?>/admin/categories.php?type=all" class="btn <?= $type_filter === 'all' ? 'btn-primary-gs' : 'btn-outline-secondary' ?>">All (<?= $count_all ?>)</a>
                    <a href="<?= BASE_URL ?>/admin/categories.php?type=business" class="btn <?= $type_filter === 'business' ? 'btn-primary-gs' : 'btn-outline-secondary' ?>">Businesses (<?= $count_business ?>)</a>
                    <a href="<?= BASE_URL ?>/admin/categories.php?type=service" class="btn <?= $type_filter === 'service' ? 'btn-primary-gs' : 'btn-outline-secondary' ?>">Services (<?= $count_service ?>)</a>
                </div>
                <button class="btn btn-primary-gs btn-sm" data-bs-toggle="modal" data-bs-target="#addCatModal">
                    <i class="fa-solid fa-plus me-1"></i> Add Category
                </button>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Icon & Category Name</th>
                                <th>Type Classification</th>
                                <th>URL Slug</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="p-2 rounded-2 bg-light border text-primary-gs">
                                                <i class="<?= e($cat['icon_class']) ?> fs-6"></i>
                                            </div>
                                            <strong class="text-dark"><?= e($cat['name']) ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?= match($cat['type']) {
                                            'business' => 'bg-primary-gs',
                                            'service' => 'bg-secondary-gs',
                                            default => 'bg-info text-dark'
                                        } ?>">
                                            <?= ucfirst(e($cat['type'])) ?>
                                        </span>
                                    </td>
                                    <td><code><?= e($cat['slug']) ?></code></td>
                                    <td><small class="text-muted"><?= e($cat['description'] ?: 'Standard platform category') ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Category -->
<div class="modal fade" id="addCatModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Add New Platform Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>/admin/categories.php" method="POST" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="add_category" value="1">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Category Name *</label>
                        <input type="text" name="name" class="form-control form-control-gs" placeholder="e.g. Dairy & Animal Feed, Solar Pump Installation" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Classification Type *</label>
                        <select name="type" class="form-select form-select-gs" required>
                            <option value="business">Business / Merchant Shop</option>
                            <option value="service">Service / Skilled Technician</option>
                            <option value="general">General Village Trade</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">FontAwesome Icon Class *</label>
                        <input type="text" name="icon_class" class="form-control form-control-gs" value="fa-solid fa-store" placeholder="e.g. fa-solid fa-cow, fa-solid fa-solar-panel" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Description</label>
                        <textarea name="description" class="form-control form-control-gs" rows="3" placeholder="Brief summary of businesses or services included in this category..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-gs">Create Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>