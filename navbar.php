<?php
require_once __DIR__ . '/auth.php';
$user = current_user();
$current_page = basename($_SERVER['SCRIPT_NAME']);
?>
<nav class="navbar navbar-expand-lg navbar-graamsetu sticky-top">
    <div class="container">
        <!-- Logo & Brand -->
        <a class="navbar-brand-logo" href="<?= BASE_URL ?>/index.php">
            <div class="logo-badge">
                <i class="fa-solid fa-bridge"></i>
            </div>
            <div>
                <div class="brand-text">Graam<span>Setu</span></div>
                <span class="brand-tagline">Connecting Rural India</span>
            </div>
        </a>

        <!-- Mobile Toggler -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#graamsetuNavbar" aria-controls="graamsetuNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Navbar Items -->
        <div class="collapse navbar-collapse" id="graamsetuNavbar">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link nav-link-gs <?= $current_page === 'index.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php">
                        <i class="fa-solid fa-house me-1"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-gs <?= $current_page === 'about.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/about.php">
                        <i class="fa-solid fa-circle-info me-1"></i> About
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-gs <?= $current_page === 'services.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/services.php">
                        <i class="fa-solid fa-store me-1"></i> Services & Shops
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-gs <?= $current_page === 'contact.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/contact.php">
                        <i class="fa-solid fa-phone me-1"></i> Contact & Helplines
                    </a>
                </li>
            </ul>

            <!-- Right Auth Actions -->
            <div class="d-flex align-items-center gap-2">
                <?php if ($user): ?>
                    <?php
                    $dash_link = match ($user['role_name']) {
                        'admin' => BASE_URL . '/admin/dashboard.php',
                        'merchant' => BASE_URL . '/merchant/dashboard.php',
                        'provider' => BASE_URL . '/provider/dashboard.php',
                        'citizen' => BASE_URL . '/citizen/dashboard.php',
                        default => BASE_URL . '/index.php',
                    };
                    ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-gs dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-user-circle fs-5"></i>
                            <span><?= e($user['full_name']) ?></span>
                            <span class="badge bg-secondary text-capitalize"><?= e($user['role_name']) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                            <li><a class="dropdown-item py-2 fw-semibold" href="<?= $dash_link ?>"><i class="fa-solid fa-gauge me-2 text-primary-gs"></i> Dashboard</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item py-2 text-danger fw-semibold" href="<?= BASE_URL ?>/logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/login.php" class="btn btn-outline-gs">
                        <i class="fa-solid fa-right-to-bracket me-1"></i> Login
                    </a>
                    <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary-gs">
                        <i class="fa-solid fa-user-plus me-1"></i> Register
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
