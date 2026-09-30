<?php
$page_title = "Customer Inquiries | GraamSetu Merchant";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('merchant');
$user = current_user();
$db = Database::getConnection();

// Fetch merchant profile
$merchant_stmt = $db->prepare("SELECT id, business_name FROM merchant_profiles WHERE user_id = :uid");
$merchant_stmt->execute([':uid' => $user['id']]);
$merchant = $merchant_stmt->fetch();

if (!$merchant) {
    die("Merchant profile missing. Please contact Gram Panchayat Administrator.");
}
$merchant_id = (int)$merchant['id'];

// Handle Reply to Enquiry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_enquiry'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $enquiry_id = (int)$_POST['enquiry_id'];
        $reply = trim($_POST['reply_message'] ?? '');

        if (!empty($reply)) {
            // Verify enquiry ownership
            $check_stmt = $db->prepare("SELECT citizen_id, subject FROM merchant_enquiries WHERE id = ? AND merchant_id = ?");
            $check_stmt->execute([$enquiry_id, $merchant_id]);
            $enq = $check_stmt->fetch();

            if ($enq) {
                $upd = $db->prepare("
                    UPDATE merchant_enquiries 
                    SET reply_message = ?, status = 'replied' 
                    WHERE id = ? AND merchant_id = ?
                ");
                $upd->execute([$reply, $enquiry_id, $merchant_id]);

                // Create portal notification for citizen
                $db->prepare("INSERT INTO notifications (user_id, title, message, link, is_read) VALUES (?, ?, ?, ?, 0)")
                   ->execute([
                       $enq['citizen_id'], 
                       'Merchant Replied to Your Stock Inquiry', 
                       "{$merchant['business_name']} replied to your query regarding: {$enq['subject']}", 
                       'citizen/dashboard.php'
                   ]);

                set_flash('success', 'Your reply was successfully dispatched to the customer!');
            } else {
                set_flash('danger', 'Unauthorized access: Inquiry does not belong to your store.');
            }
            header('Location: ' . BASE_URL . '/merchant/enquiries.php');
            exit;
        } else {
            set_flash('danger', 'Please enter a response message for the citizen.');
        }
    } else {
        set_flash('danger', 'Security session expired. Please submit again.');
    }
}

// Filter parameters (Database-driven)
$status_filter = trim($_GET['status'] ?? 'all'); // 'all', 'unread', 'replied'
$search_q = trim($_GET['q'] ?? '');

$enq_sql = "
    SELECT me.*, u.full_name as citizen_name, u.phone as citizen_phone,
           l.village_name as citizen_village, l.district as citizen_district
    FROM merchant_enquiries me
    JOIN users u ON me.citizen_id = u.id
    LEFT JOIN citizen_profiles cp ON u.id = cp.user_id
    LEFT JOIN locations l ON cp.location_id = l.id
    WHERE me.merchant_id = :mid
";
$enq_params = [':mid' => $merchant_id];

if ($status_filter === 'unread') {
    $enq_sql .= " AND me.status = 'unread'";
} elseif ($status_filter === 'replied') {
    $enq_sql .= " AND me.status = 'replied'";
}

if (!empty($search_q)) {
    $enq_sql .= " AND (u.full_name LIKE :q OR me.subject LIKE :q2 OR me.message LIKE :q3)";
    $enq_params[':q'] = "%$search_q%";
    $enq_params[':q2'] = "%$search_q%";
    $enq_params[':q3'] = "%$search_q%";
}

$enq_sql .= " ORDER BY me.created_at DESC";
$stmt = $db->prepare($enq_sql);
$stmt->execute($enq_params);
$enquiries = $stmt->fetchAll();

// Counts for filter badges
$count_all = (int)$db->query("SELECT COUNT(*) FROM merchant_enquiries WHERE merchant_id = $merchant_id")->fetchColumn();
$count_unread = (int)$db->query("SELECT COUNT(*) FROM merchant_enquiries WHERE merchant_id = $merchant_id AND status = 'unread'")->fetchColumn();
$count_replied = (int)$db->query("SELECT COUNT(*) FROM merchant_enquiries WHERE merchant_id = $merchant_id AND status = 'replied'")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="dashboard-wrapper">
    <!-- Sidebar -->
    <div class="dashboard-sidebar">
        <div class="sidebar-heading">Merchant Portal</div>
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/merchant/dashboard.php" class="sidebar-link"><i class="fa-solid fa-gauge"></i> Store Overview</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/merchant/products.php" class="sidebar-link"><i class="fa-solid fa-boxes-stacked"></i> Products Inventory</a></li>
            <li class="sidebar-menu-item">
                <a href="<?= BASE_URL ?>/merchant/enquiries.php" class="sidebar-link active">
                    <i class="fa-solid fa-comments"></i> Customer Inquiries
                    <?php if ($count_unread > 0): ?>
                        <span class="badge bg-danger ms-auto"><?= $count_unread ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/merchant/business.php" class="sidebar-link"><i class="fa-solid fa-shop"></i> Store Profile</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/merchant/profile.php" class="sidebar-link"><i class="fa-solid fa-user-gear"></i> Owner Account</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="dashboard-content">
        <?= render_flash_messages() ?>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-comments text-primary-gs me-2"></i> Citizen Product & Stock Inquiries
                </h3>
                <p class="text-muted small mb-0">Respond to customer stock inquiries, pricing requests, and store availability.</p>
            </div>
        </div>

        <!-- Filter & Search Card -->
        <div class="card border-0 shadow-sm rounded-4 p-3 mb-4 bg-white">
            <form action="<?= BASE_URL ?>/merchant/enquiries.php" method="GET" class="row g-3 align-items-center">
                <div class="col-md-7">
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" name="q" class="form-control form-control-gs" placeholder="Search by citizen name, subject, or message..." value="<?= e($search_q) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-gs" onchange="this.form.submit()">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Inquiries (<?= $count_all ?>)</option>
                        <option value="unread" <?= $status_filter === 'unread' ? 'selected' : '' ?>>Unanswered Only (<?= $count_unread ?>)</option>
                        <option value="replied" <?= $status_filter === 'replied' ? 'selected' : '' ?>>Replied / Answered (<?= $count_replied ?>)</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary-gs btn-sm w-100">Filter</button>
                    <?php if (!empty($search_q) || $status_filter !== 'all'): ?>
                        <a href="<?= BASE_URL ?>/merchant/enquiries.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Inquiries Table -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
            <div class="card-body p-0">
                <?php if (empty($enquiries)): ?>
                    <div class="text-center py-5">
                        <i class="fa-solid fa-inbox text-muted display-4 mb-3"></i>
                        <h5 class="fw-bold text-dark">No Customer Inquiries Found</h5>
                        <p class="text-muted small mb-0">When village citizens submit stock checks from your shop card, inquiries will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Customer & Contact</th>
                                    <th>Subject / Item</th>
                                    <th>Citizen Inquiry Details</th>
                                    <th>Status</th>
                                    <th>Your Response</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enquiries as $enq): ?>
                                    <tr>
                                        <td>
                                            <strong class="text-dark d-block"><?= e($enq['citizen_name']) ?></strong>
                                            <small class="text-muted">
                                                <a href="tel:<?= e($enq['citizen_phone']) ?>" class="text-decoration-none text-success">
                                                    <i class="fa-solid fa-phone me-1"></i><?= e($enq['citizen_phone']) ?>
                                                </a>
                                                <?php if (!empty($enq['citizen_village'])): ?>
                                                    <br><i class="fa-solid fa-location-dot text-danger me-1"></i><?= e($enq['citizen_village']) ?>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <strong class="text-dark small d-block"><?= e($enq['subject']) ?></strong>
                                            <small class="text-muted"><i class="fa-solid fa-clock me-1"></i><?= format_date($enq['created_at'], 'd M Y, h:i A') ?></small>
                                        </td>
                                        <td>
                                            <p class="mb-0 text-dark small" style="max-width: 250px;"><?= nl2br(e($enq['message'])) ?></p>
                                        </td>
                                        <td><?= get_status_badge($enq['status']) ?></td>
                                        <td>
                                            <?php if (!empty($enq['reply_message'])): ?>
                                                <div class="p-2 rounded bg-light border small text-dark" style="max-width: 240px;">
                                                    <i class="fa-solid fa-reply text-primary me-1"></i> <?= e($enq['reply_message']) ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small fst-italic">Awaiting response</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm <?= $enq['status'] === 'unread' ? 'btn-primary-gs' : 'btn-outline-secondary' ?> py-1 px-3 text-nowrap" data-bs-toggle="modal" data-bs-target="#replyModal<?= $enq['id'] ?>">
                                                <i class="fa-solid fa-reply me-1"></i> <?= $enq['status'] === 'replied' ? 'Edit Reply' : 'Reply' ?>
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Modal: Reply to Customer -->
                                    <div class="modal fade" id="replyModal<?= $enq['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold">Reply to <?= e($enq['citizen_name']) ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form action="<?= BASE_URL ?>/merchant/enquiries.php" method="POST" novalidate>
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="reply_enquiry" value="1">
                                                    <input type="hidden" name="enquiry_id" value="<?= $enq['id'] ?>">
                                                    <div class="modal-body">
                                                        <div class="p-3 bg-light rounded-3 mb-3 border">
                                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                                <strong class="text-dark small"><i class="fa-solid fa-user me-1 text-primary"></i> <?= e($enq['citizen_name']) ?></strong>
                                                                <small class="text-muted"><?= format_date($enq['created_at'], 'd M, h:i A') ?></small>
                                                            </div>
                                                            <strong class="text-primary-gs small d-block mb-1"><?= e($enq['subject']) ?></strong>
                                                            <p class="small text-dark mb-0"><?= nl2br(e($enq['message'])) ?></p>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold text-dark">Your Response to Customer *</label>
                                                            <textarea name="reply_message" class="form-control form-control-gs" rows="4" placeholder="Type stock availability, price per unit, or pickup timings..." required><?= e($enq['reply_message'] ?? '') ?></textarea>
                                                        </div>
                                                        <div class="p-2 bg-light rounded small text-muted">
                                                            <i class="fa-solid fa-bell text-warning me-1"></i> When you submit, the citizen will receive an instant portal alert and notification.
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary-gs">Send Response</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
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