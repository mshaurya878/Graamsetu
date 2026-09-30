<?php
$page_title = "Village Complaints & Grievances | GraamSetu";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('citizen');
$user = current_user();
$db = Database::getConnection();

// Fetch citizen's default location
$loc_stmt = $db->prepare("
    SELECT l.village_name, l.gram_panchayat, l.district 
    FROM citizen_profiles cp 
    LEFT JOIN locations l ON cp.location_id = l.id 
    WHERE cp.user_id = ?
");
$loc_stmt->execute([$user['id']]);
$citizen_loc = $loc_stmt->fetch() ?: ['village_name' => 'Rampur Village'];

// Handle New Complaint Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_complaint'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $category = trim($_POST['category'] ?? 'General Civic Issue');
        $subject = trim($_POST['subject'] ?? ($_POST['title'] ?? ''));
        $description = trim($_POST['description'] ?? '');
        $location_text = trim($_POST['location_text'] ?? ($_POST['village_location'] ?? ($citizen_loc['village_name'] ?? 'Village Center')));

        if (!empty($subject) && !empty($description)) {
            $stmt = $db->prepare("
                INSERT INTO complaints (citizen_id, category, subject, description, location_text, status)
                VALUES (?, ?, ?, ?, ?, 'open')
            ");
            $stmt->execute([$user['id'], $category, $subject, $description, $location_text]);

            // Notify Admin
            $admin_user = $db->query("SELECT id FROM users WHERE role_id = 4 LIMIT 1")->fetch();
            if ($admin_user) {
                $db->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)")
                   ->execute([$admin_user['id'], 'New Village Grievance Lodged', "Citizen {$user['full_name']} lodged a complaint: $subject", 'admin/complaints.php']);
            }

            set_flash('success', 'Grievance complaint lodged with Gram Panchayat successfully! The Panchayat administrator has been notified.');
            header('Location: ' . BASE_URL . '/citizen/complaints.php');
            exit;
        } else {
            set_flash('danger', 'Please enter both a complaint subject and detailed description.');
        }
    } else {
        set_flash('danger', 'Security session expired. Please submit the form again.');
    }
}

// Fetch Citizen Complaints
$stmt = $db->prepare("SELECT * FROM complaints WHERE citizen_id = :uid ORDER BY created_at DESC");
$stmt->execute([':uid' => $user['id']]);
$complaints = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="dashboard-wrapper">
    <div class="dashboard-sidebar">
        <div class="sidebar-heading">Citizen Navigation</div>
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/citizen/dashboard.php" class="sidebar-link"><i class="fa-solid fa-gauge"></i> Overview</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/citizen/services.php" class="sidebar-link"><i class="fa-solid fa-store"></i> Discover Services</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/citizen/requests.php" class="sidebar-link"><i class="fa-solid fa-list-check"></i> My Bookings</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/citizen/complaints.php" class="sidebar-link active"><i class="fa-solid fa-triangle-exclamation"></i> Village Grievances</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/citizen/notifications.php" class="sidebar-link"><i class="fa-solid fa-bell"></i> Alerts & Notices</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/citizen/profile.php" class="sidebar-link"><i class="fa-solid fa-user-gear"></i> My Profile</a></li>
        </ul>
    </div>

    <div class="dashboard-content">
        <?= render_flash_messages() ?>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1"><i class="fa-solid fa-triangle-exclamation text-danger me-2"></i> Village Grievance Redressal</h3>
                <p class="text-muted small mb-0">Lodge civic complaints regarding drinking water, electricity, drainage, streetlights, or roads.</p>
            </div>
            <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#newComplaintModal">
                <i class="fa-solid fa-plus me-1"></i> Lodge New Grievance
            </button>
        </div>

        <!-- Complaints Table -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
            <div class="card-body p-0">
                <?php if (empty($complaints)): ?>
                    <div class="text-center py-5">
                        <i class="fa-solid fa-circle-check text-success display-4 mb-3"></i>
                        <h5 class="fw-bold text-dark">No Grievance Complaints Lodged</h5>
                        <p class="text-muted small mb-3">Report any infrastructure or civic issues directly to your Gram Panchayat Sarpanch.</p>
                        <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#newComplaintModal">
                            <i class="fa-solid fa-plus me-1"></i> Lodge a Grievance
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Grievance ID</th>
                                    <th>Category & Subject</th>
                                    <th>Location</th>
                                    <th>Date Lodged</th>
                                    <th>Status</th>
                                    <th>Panchayat Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($complaints as $c): ?>
                                    <tr>
                                        <td><strong>#GR-<?= $c['id'] ?></strong></td>
                                        <td>
                                            <span class="badge bg-secondary mb-1 text-uppercase small"><?= e($c['category']) ?></span>
                                            <strong class="d-block text-dark"><?= e($c['subject']) ?></strong>
                                            <small class="text-muted"><?= e($c['description']) ?></small>
                                        </td>
                                        <td><small><i class="fa-solid fa-location-dot text-danger me-1"></i> <?= e($c['location_text']) ?></small></td>
                                        <td><small><?= format_date($c['created_at'], 'd M Y') ?></small></td>
                                        <td><?= get_status_badge($c['status']) ?></td>
                                        <td>
                                            <?php if (!empty($c['admin_notes'])): ?>
                                                <small class="text-dark bg-light p-1.5 rounded d-inline-block border"><i class="fa-solid fa-comment-dots text-primary me-1"></i> <?= e($c['admin_notes']) ?></small>
                                            <?php else: ?>
                                                <small class="text-muted fst-italic">Under review by Panchayat</small>
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

<!-- Modal: Lodge New Complaint -->
<div class="modal fade" id="newComplaintModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Lodge Village Grievance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>/citizen/complaints.php" method="POST" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="submit_complaint" value="1">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Grievance Category *</label>
                        <select name="category" class="form-select form-select-gs" required>
                            <option value="Water Supply">Drinking Water & Borewell Supply</option>
                            <option value="Electricity">Electricity, Transformer & Streetlights</option>
                            <option value="Roads & Drainage">Roads, Culverts & Drainage</option>
                            <option value="Sanitation">Sanitation & Garbage Cleaning</option>
                            <option value="Ration / PDS">PDS / Fair Price Shop Issues</option>
                            <option value="Other Civic Issue">Other Civic Matter</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Subject / Title *</label>
                        <input type="text" name="subject" class="form-control form-control-gs" placeholder="e.g. Drinking water pipeline leak near Temple" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Specific Location in Village *</label>
                        <input type="text" name="location_text" class="form-control form-control-gs" value="<?= e($citizen_loc['village_name'] ?? 'Ward No. 4, Village Center') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Describe Issue in Detail *</label>
                        <textarea name="description" class="form-control form-control-gs" rows="4" placeholder="Explain the problem, duration, and exact spot..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Lodge Grievance</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (isset($_GET['new'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var myModal = new bootstrap.Modal(document.getElementById('newComplaintModal'));
    myModal.show();
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>