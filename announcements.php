<?php
$page_title = "Village Notices | GraamSetu Admin";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');
$user = current_user();
$db = Database::getConnection();

// 1. Handle Create Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $audience = trim($_POST['target_audience'] ?? 'all');
        $priority = trim($_POST['priority'] ?? 'normal');
        $allowed_aud = ['all', 'citizens', 'merchants', 'providers'];
        $allowed_prio = ['low', 'normal', 'high', 'urgent'];

        if (empty($title) || empty($content)) {
            set_flash('danger', 'Notice headline and announcement content are required.');
        } elseif (!in_array($audience, $allowed_aud) || !in_array($priority, $allowed_prio)) {
            set_flash('danger', 'Invalid audience or priority option selected.');
        } else {
            // Schema-compliant columns
            $stmt = $db->prepare("
                INSERT INTO announcements (author_id, title, content, target_audience, priority, is_active)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$user['id'], $title, $content, $audience, $priority]);
            $new_aid = (int)$db->lastInsertId();

            // Audit Log
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $db->prepare("INSERT INTO admin_logs (admin_id, action, target_table, target_id, details, ip_address) VALUES (?, 'Published Notice', 'announcements', ?, ?, ?)")
               ->execute([$user['id'], $new_aid, "Published village notice: $title ($priority priority)", $ip]);

            set_flash('success', 'Village notice published successfully to Panchayat board!');
            header('Location: ' . BASE_URL . '/admin/announcements.php');
            exit;
        }
    } else {
        set_flash('danger', 'Security session expired. Please submit again.');
    }
}

// 2. Handle Toggle Active Status
if (isset($_GET['toggle_status'])) {
    $aid = (int)$_GET['toggle_status'];
    $db->prepare("UPDATE announcements SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?")->execute([$aid]);
    set_flash('success', 'Notice active status toggled.');
    header('Location: ' . BASE_URL . '/admin/announcements.php');
    exit;
}

// 3. Handle Delete Announcement
if (isset($_GET['delete'])) {
    $aid = (int)$_GET['delete'];
    $db->prepare("DELETE FROM announcements WHERE id = ?")->execute([$aid]);

    // Audit Log
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $db->prepare("INSERT INTO admin_logs (admin_id, action, target_table, target_id, details, ip_address) VALUES (?, 'Deleted Notice', 'announcements', ?, 'Deleted announcement ID $aid', ?)")
       ->execute([$user['id'], $aid, $ip]);

    set_flash('success', 'Notice removed from notice board.');
    header('Location: ' . BASE_URL . '/admin/announcements.php');
    exit;
}

// Fetch Announcements
$announcements = $db->query("
    SELECT a.*, u.full_name as author_name 
    FROM announcements a 
    JOIN users u ON a.author_id = u.id 
    ORDER BY (a.priority = 'urgent' OR a.priority = 'high') DESC, a.created_at DESC
")->fetchAll();

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
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/admin/announcements.php" class="sidebar-link active"><i class="fa-solid fa-bullhorn"></i> Village Notices</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/admin/categories.php" class="sidebar-link"><i class="fa-solid fa-tags"></i> Categories</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/admin/settings.php" class="sidebar-link"><i class="fa-solid fa-sliders"></i> Audit Logs</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="dashboard-content">
        <?= render_flash_messages() ?>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1"><i class="fa-solid fa-bullhorn text-danger me-2"></i> Gram Panchayat Notice & Scheme Publisher</h3>
                <p class="text-muted small mb-0">Publish official government scheme alerts, weather warnings, health camps, and Panchayat meetings.</p>
            </div>
            <button class="btn btn-primary-gs btn-sm" data-bs-toggle="modal" data-bs-target="#newNoticeModal">
                <i class="fa-solid fa-plus me-1"></i> Publish New Notice
            </button>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
            <div class="card-body p-0">
                <?php if (empty($announcements)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fa-solid fa-bullhorn display-5 mb-2 text-secondary"></i>
                        <h6 class="fw-bold text-dark">No Notices Broadcasted Yet</h6>
                        <p class="small text-muted mb-0">Broadcast your first village circular or agricultural alert.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Priority & Title</th>
                                    <th>Target Audience</th>
                                    <th>Content Excerpt</th>
                                    <th>Status</th>
                                    <th>Published Date</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($announcements as $a): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge <?= match($a['priority']) {
                                                    'urgent' => 'bg-danger',
                                                    'high' => 'bg-warning text-dark',
                                                    'low' => 'bg-secondary',
                                                    default => 'bg-primary-gs'
                                                } ?> py-1 px-2">
                                                    <?= ucfirst(e($a['priority'])) ?>
                                                </span>
                                                <strong class="text-dark"><?= e($a['title']) ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <i class="fa-solid fa-users me-1"></i> <?= ucfirst(e($a['target_audience'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted d-block text-truncate" style="max-width: 300px;"><?= e($a['content']) ?></small>
                                        </td>
                                        <td>
                                            <a href="<?= BASE_URL ?>/admin/announcements.php?toggle_status=<?= $a['id'] ?>" class="text-decoration-none" title="Click to toggle status">
                                                <span class="badge <?= $a['is_active'] ? 'bg-success' : 'bg-secondary' ?> py-1 px-2">
                                                    <?= $a['is_active'] ? 'Active (Live)' : 'Inactive (Hidden)' ?>
                                                </span>
                                            </a>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?= format_date($a['created_at'], 'd M Y, h:i A') ?></small>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= BASE_URL ?>/admin/announcements.php?delete=<?= $a['id'] ?>" 
                                               class="btn btn-outline-danger btn-sm py-1 px-2"
                                               onclick="return confirm('Permanently remove this village notice?')">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
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

<!-- Modal: Publish New Notice -->
<div class="modal fade" id="newNoticeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Publish Official Village Notice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>/admin/announcements.php" method="POST" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="create_announcement" value="1">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Notice Headline / Subject *</label>
                        <input type="text" name="title" class="form-control form-control-gs" placeholder="e.g. Free Soil Health Card Testing Camp at Panchayat Bhawan" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Target Audience *</label>
                            <select name="target_audience" class="form-select form-select-gs" required>
                                <option value="all">All Villagers (Citizens, Shops, Techs)</option>
                                <option value="citizens">Citizens / Farmers Only</option>
                                <option value="merchants">Local Shopkeepers Only</option>
                                <option value="providers">Skilled Technicians Only</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Priority Level *</label>
                            <select name="priority" class="form-select form-select-gs" required>
                                <option value="normal">Normal (Information)</option>
                                <option value="high">High (Upcoming Deadline)</option>
                                <option value="urgent">Urgent (Weather / Power / Health Alert)</option>
                                <option value="low">Low (General Archive)</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Full Notice Content *</label>
                        <textarea name="content" class="form-control form-control-gs" rows="4" placeholder="Write the announcement details, timing, venue, contact officers, eligibility..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-gs">Publish to Notice Board</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>