<?php
$page_title = "Manage Products & Inventory | GraamSetu";
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

// ----------------------------------------------------
// 1. Handle Add New Product
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $title = trim($_POST['title'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $unit = trim($_POST['unit'] ?? 'piece');
        $desc = trim($_POST['description'] ?? '');
        $is_available = isset($_POST['is_available']) ? 1 : 0;

        if (empty($title)) {
            set_flash('danger', 'Product title is required.');
        } elseif ($price <= 0) {
            set_flash('danger', 'Product price must be greater than zero.');
        } else {
            $stmt = $db->prepare("
                INSERT INTO products (merchant_id, title, description, price, unit, is_available, is_deleted) 
                VALUES (?, ?, ?, ?, ?, ?, 0)
            ");
            $stmt->execute([$merchant_id, $title, $desc, $price, $unit, $is_available]);

            set_flash('success', "Item '$title' added to your store catalog successfully!");
            header('Location: ' . BASE_URL . '/merchant/products.php');
            exit;
        }
    } else {
        set_flash('danger', 'Security session expired. Please submit the form again.');
    }
}

// ----------------------------------------------------
// 2. Handle Edit Existing Product
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $product_id = (int)$_POST['product_id'];
        $title = trim($_POST['title'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $unit = trim($_POST['unit'] ?? 'piece');
        $desc = trim($_POST['description'] ?? '');
        $is_available = isset($_POST['is_available']) ? 1 : 0;

        if (empty($title)) {
            set_flash('danger', 'Product title cannot be empty.');
        } elseif ($price <= 0) {
            set_flash('danger', 'Product price must be greater than zero.');
        } else {
            // Verify product belongs to current merchant
            $stmt = $db->prepare("
                UPDATE products 
                SET title = ?, description = ?, price = ?, unit = ?, is_available = ?
                WHERE id = ? AND merchant_id = ?
            ");
            $stmt->execute([$title, $desc, $price, $unit, $is_available, $product_id, $merchant_id]);

            set_flash('success', "Item '$title' updated successfully!");
            header('Location: ' . BASE_URL . '/merchant/products.php');
            exit;
        }
    } else {
        set_flash('danger', 'Security session expired. Please submit the form again.');
    }
}

// ----------------------------------------------------
// 3. Handle Soft-Delete / Deactivate Product (Preserves relational history)
// ----------------------------------------------------
if (isset($_GET['soft_delete'])) {
    $pid = (int)$_GET['soft_delete'];
    $del = $db->prepare("UPDATE products SET is_deleted = 1, is_available = 0 WHERE id = ? AND merchant_id = ?");
    $del->execute([$pid, $merchant_id]);
    set_flash('success', 'Product has been deactivated and archived from public listings.');
    header('Location: ' . BASE_URL . '/merchant/products.php');
    exit;
}

// ----------------------------------------------------
// 4. Handle Restore Archived Product
// ----------------------------------------------------
if (isset($_GET['restore'])) {
    $pid = (int)$_GET['restore'];
    $rest = $db->prepare("UPDATE products SET is_deleted = 0, is_available = 1 WHERE id = ? AND merchant_id = ?");
    $rest->execute([$pid, $merchant_id]);
    set_flash('success', 'Product has been restored and marked available.');
    header('Location: ' . BASE_URL . '/merchant/products.php');
    exit;
}

// ----------------------------------------------------
// 5. Handle Toggle Availability / Restock Status
// ----------------------------------------------------
if (isset($_GET['toggle_stock'])) {
    $pid = (int)$_GET['toggle_stock'];
    $upd = $db->prepare("UPDATE products SET is_available = NOT is_available WHERE id = ? AND merchant_id = ?");
    $upd->execute([$pid, $merchant_id]);
    set_flash('success', 'Product availability status updated.');
    header('Location: ' . BASE_URL . '/merchant/products.php');
    exit;
}

// ----------------------------------------------------
// 6. Search & Filter Parameters (Database-driven)
// ----------------------------------------------------
$search = trim($_GET['q'] ?? '');
$stock_filter = trim($_GET['stock'] ?? 'all'); // 'all', 'in_stock', 'out_of_stock', 'archived'

$query_sql = "SELECT * FROM products WHERE merchant_id = :mid";
$query_params = [':mid' => $merchant_id];

if ($stock_filter === 'archived') {
    $query_sql .= " AND is_deleted = 1";
} else {
    $query_sql .= " AND is_deleted = 0";
    if ($stock_filter === 'in_stock') {
        $query_sql .= " AND is_available = 1";
    } elseif ($stock_filter === 'out_of_stock') {
        $query_sql .= " AND is_available = 0";
    }
}

if (!empty($search)) {
    $query_sql .= " AND (title LIKE :q OR description LIKE :q2)";
    $query_params[':q'] = "%$search%";
    $query_params[':q2'] = "%$search%";
}

$query_sql .= " ORDER BY is_available DESC, id DESC";
$stmt = $db->prepare($query_sql);
$stmt->execute($query_params);
$products = $stmt->fetchAll();

// Counts for filter tabs
$total_active = (int)$db->query("SELECT COUNT(*) FROM products WHERE merchant_id = $merchant_id AND is_deleted = 0")->fetchColumn();
$total_in = (int)$db->query("SELECT COUNT(*) FROM products WHERE merchant_id = $merchant_id AND is_available = 1 AND is_deleted = 0")->fetchColumn();
$total_out = (int)$db->query("SELECT COUNT(*) FROM products WHERE merchant_id = $merchant_id AND is_available = 0 AND is_deleted = 0")->fetchColumn();
$total_archived = (int)$db->query("SELECT COUNT(*) FROM products WHERE merchant_id = $merchant_id AND is_deleted = 1")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="dashboard-wrapper">
    <!-- Sidebar -->
    <div class="dashboard-sidebar">
        <div class="sidebar-heading">Merchant Portal</div>
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/merchant/dashboard.php" class="sidebar-link"><i class="fa-solid fa-gauge"></i> Store Overview</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/merchant/products.php" class="sidebar-link active"><i class="fa-solid fa-boxes-stacked"></i> Products Inventory</a></li>
            <li class="sidebar-menu-item"><a href="<?= BASE_URL ?>/merchant/enquiries.php" class="sidebar-link"><i class="fa-solid fa-comments"></i> Customer Inquiries</a></li>
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
                    <i class="fa-solid fa-boxes-stacked text-primary-gs me-2"></i> Products & Inventory Catalog
                </h3>
                <p class="text-muted small mb-0">Manage retail items, pricing per unit, and live in-stock availability for village citizens.</p>
            </div>
            <button class="btn btn-primary-gs btn-sm" data-bs-toggle="modal" data-bs-target="#addProductModal">
                <i class="fa-solid fa-plus me-1"></i> Add Inventory Item
            </button>
        </div>

        <!-- Search & Filter Controls Card -->
        <div class="card border-0 shadow-sm rounded-4 p-3 mb-4 bg-white">
            <form action="<?= BASE_URL ?>/merchant/products.php" method="GET" class="row g-3 align-items-center">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" name="q" class="form-control form-control-gs" placeholder="Search products by item title..." value="<?= e($search) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="stock" class="form-select form-select-gs" onchange="this.form.submit()">
                        <option value="all" <?= $stock_filter === 'all' ? 'selected' : '' ?>>All Active Items (<?= $total_active ?>)</option>
                        <option value="in_stock" <?= $stock_filter === 'in_stock' ? 'selected' : '' ?>>In-Stock Available (<?= $total_in ?>)</option>
                        <option value="out_of_stock" <?= $stock_filter === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock Only (<?= $total_out ?>)</option>
                        <option value="archived" <?= $stock_filter === 'archived' ? 'selected' : '' ?>>Archived / Deactivated (<?= $total_archived ?>)</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary-gs btn-sm flex-grow-1">Search</button>
                    <?php if (!empty($search) || $stock_filter !== 'all'): ?>
                        <a href="<?= BASE_URL ?>/merchant/products.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Inventory List Table -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
            <div class="card-body p-0">
                <?php if (empty($products)): ?>
                    <div class="text-center py-5">
                        <i class="fa-solid fa-box-open text-muted display-4 mb-3"></i>
                        <h5 class="fw-bold text-dark">No Products Found</h5>
                        <p class="text-muted small mb-3">
                            <?= !empty($search) || $stock_filter !== 'all' ? 'No items match your active search filter.' : 'Click "Add Inventory Item" to list products for rural citizens.' ?>
                        </p>
                        <button class="btn btn-primary-gs btn-sm" data-bs-toggle="modal" data-bs-target="#addProductModal">
                            <i class="fa-solid fa-plus me-1"></i> Add Your First Item
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Item Title</th>
                                    <th>Unit Price (â‚¹)</th>
                                    <th>Unit / Packaging</th>
                                    <th>Availability Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $p): ?>
                                    <tr class="<?= $p['is_deleted'] ? 'table-secondary opacity-75' : '' ?>">
                                        <td>
                                            <strong class="text-dark d-block"><?= e($p['title']) ?></strong>
                                            <small class="text-muted"><?= e($p['description'] ?: 'No details provided') ?></small>
                                            <?php if ($p['is_deleted']): ?>
                                                <span class="badge bg-secondary ms-1">Archived</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong class="text-success fs-6">â‚¹<?= number_format($p['price'], 2) ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= e($p['unit']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($p['is_deleted']): ?>
                                                <span class="badge bg-secondary">Deactivated</span>
                                            <?php else: ?>
                                                <a href="<?= BASE_URL ?>/merchant/products.php?toggle_stock=<?= $p['id'] ?>" class="text-decoration-none" title="Click to toggle availability">
                                                    <span class="badge <?= $p['is_available'] ? 'bg-success' : 'bg-danger' ?> py-1 px-2">
                                                        <i class="fa-solid <?= $p['is_available'] ? 'fa-check' : 'fa-xmark' ?> me-1"></i>
                                                        <?= $p['is_available'] ? 'In Stock' : 'Out of Stock' ?>
                                                    </span>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <?php if (!$p['is_deleted']): ?>
                                                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $p['id'] ?>" title="Edit product details">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </button>
                                                    <a href="<?= BASE_URL ?>/merchant/products.php?soft_delete=<?= $p['id'] ?>" class="btn btn-outline-warning" onclick="return confirm('Archive and deactivate <?= htmlspecialchars(addslashes($p['title'])) ?>? This preserves customer history.')" title="Deactivate / Archive">
                                                        <i class="fa-solid fa-box-archive"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="<?= BASE_URL ?>/merchant/products.php?restore=<?= $p['id'] ?>" class="btn btn-outline-success" title="Restore to active catalog">
                                                        <i class="fa-solid fa-trash-arrow-up me-1"></i> Restore
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Modal: Edit Product -->
                                    <?php if (!$p['is_deleted']): ?>
                                    <div class="modal fade" id="editModal<?= $p['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold">Edit Item: <?= e($p['title']) ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form action="<?= BASE_URL ?>/merchant/products.php" method="POST" novalidate>
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="edit_product" value="1">
                                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold text-dark">Product Title *</label>
                                                            <input type="text" name="title" class="form-control form-control-gs" value="<?= e($p['title']) ?>" required>
                                                        </div>
                                                        <div class="row g-2 mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold text-dark">Price (â‚¹) *</label>
                                                                <input type="number" step="0.01" name="price" class="form-control form-control-gs" value="<?= e($p['price']) ?>" min="0.01" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold text-dark">Unit / Packaging *</label>
                                                                <input type="text" name="unit" class="form-control form-control-gs" list="unitOptions" value="<?= e($p['unit']) ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold text-dark">Short Description / Brand</label>
                                                            <textarea name="description" class="form-control form-control-gs" rows="2"><?= e($p['description']) ?></textarea>
                                                        </div>
                                                        <div class="form-check form-switch mb-2">
                                                            <input class="form-check-input" type="checkbox" name="is_available" id="stockSwitch<?= $p['id'] ?>" <?= $p['is_available'] ? 'checked' : '' ?>>
                                                            <label class="form-check-label fw-semibold text-dark" for="stockSwitch<?= $p['id'] ?>">Item is In-Stock and Visible</label>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary-gs">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Datalist for Suggested Units -->
<datalist id="unitOptions">
    <option value="kg">
    <option value="500g Pack">
    <option value="1 Liter Pouch">
    <option value="5 Liter Can">
    <option value="packet">
    <option value="piece">
    <option value="50kg Bag">
    <option value="quintal">
    <option value="bottle">
    <option value="strip">
    <option value="box">
</datalist>

<!-- Modal: Add New Product -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Add New Inventory Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>/merchant/products.php" method="POST" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="add_product" value="1">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Product Title *</label>
                        <input type="text" name="title" class="form-control form-control-gs" placeholder="e.g. Fortune Mustard Oil 1L, DAP Fertilizer 50kg, Paracetamol 500mg" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Price (â‚¹) *</label>
                            <input type="number" step="0.01" name="price" class="form-control form-control-gs" placeholder="e.g. 140.00" min="0.01" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Unit / Packaging *</label>
                            <input type="text" name="unit" class="form-control form-control-gs" list="unitOptions" placeholder="e.g. kg, liter, packet, bag, quintal" value="packet" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Short Description / Brand</label>
                        <textarea name="description" class="form-control form-control-gs" rows="2" placeholder="Quality details, brand, pack size or fresh stock..."></textarea>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="is_available" id="addStockSwitch" checked>
                        <label class="form-check-label fw-semibold text-dark" for="addStockSwitch">Item is In-Stock and Immediately Available</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-gs">Add to Inventory</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (isset($_GET['add'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var myModal = new bootstrap.Modal(document.getElementById('addProductModal'));
    myModal.show();
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>