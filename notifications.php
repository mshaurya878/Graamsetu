<?php
$page_title = "Notifications & Alerts | GraamSetu";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('citizen');
$user = current_user();
$db = Database::getConnection();

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid");
    $stmt->execute([':uid' => $user['id']]);
    set_flash('success', 'All notifications marked as read.');
    header('Location: ' . BASE_URL . '/citizen/notifications.php');
    exit;
}

// Fetch Notifications
$stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC");
$stmt->execute([':uid' => $user['id']]);
$notifications = $stmt->fetchAll();
?>

<div class="dashboard-wrapper">
    <div class="dashboard-sidebar">
        <div class="sidebar-heading">Citizen Navigation</div>
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/citizen/dashboard.php" class="sidebar-link"><i class="fa-solid fa-gauge"></i> Overview</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/citizen/services.php" class="sidebar-link"><i class="fa-solid fa-magnifying-glass"></i> Explore Services</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/citizen/requests.php" class="sidebar-link"><i class="fa-solid fa-list-check"></i> My Requests</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/citizen/complaints.php" class="sidebar-link"><i class="fa-solid fa-triangle-exclamation"></i> Village Grievances</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/citizen/notifications.php" class="sidebar-link active"><i class="fa-solid fa-bell"></i> Alerts & Notices</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/citizen/profile.php" class="sidebar-link"><i class="fa-solid fa-user-gear"></i> My Profile</a></li>
        </ul>
    </div>

    <div class="dashboard-content">
        <?= render_flash_messages() ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1"><i class="fa-solid fa-bell text-warning me-2"></i> Notifications & System Alerts</h3>
                <p class="text-muted mb-0">Updates on your service requests, merchant replies, and Panchayat notices.</p>
            </div>
            <?php if (!empty($notifications)): ?>
                <a href="<?= BASE_URL ?>/citizen/notifications.php?mark_all_read=1" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-check-double me-1"></i> Mark All as Read
                </a>
            <?php endif; ?>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
            <?php if (empty($notifications)): ?>
                <div class="text-center py-5">
                    <i class="fa-solid fa-bell-slash text-muted display-4 mb-3"></i>
                    <h5 class="fw-bold text-dark">No Notifications</h5>
                    <p class="text-muted">You are all caught up!</p>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($notifications as $n): ?>
                        <div class="p-3 rounded-3 border d-flex justify-content-between align-items-center <?= $n['is_read'] ? 'bg-white border-light' : 'bg-warning-subtle border-warning' ?>">
                            <div class="d-flex align-items-center gap-3">
                                <div class="icon-box <?= $n['is_read'] ? 'bg-light text-muted' : 'bg-warning text-dark' ?>" style="width:40px; height:40px; font-size:1.1rem;">
                                    <i class="fa-solid fa-bell"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-dark mb-1"><?= e($n['title']) ?></h6>
                                    <p class="small text-muted mb-0"><?= e($n['message']) ?></p>
                                </div>
                            </div>
                            <small class="text-muted ms-3 text-nowrap"><?= format_date($n['created_at'], 'd M, h:i A') ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
