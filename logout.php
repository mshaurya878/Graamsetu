<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';

logout_user();
set_flash('info', 'You have been safely logged out.');
header('Location: ' . BASE_URL . '/login.php');
exit;
