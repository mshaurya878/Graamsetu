<?php
// GraamSetu Global Helper Functions

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/csrf.php';

// Safe XSS output sanitizer
function e($string) {
    return htmlspecialchars((string)($string ?? ''), ENT_QUOTES, 'UTF-8');
}

// Flash Message Handlers
function set_flash($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flashes = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flashes;
    }
    return [];
}

function render_flash_messages() {
    $flashes = get_flash();
    $output = '';
    foreach ($flashes as $type => $message) {
        $alertClass = match($type) {
            'success' => 'alert-success',
            'danger', 'error' => 'alert-danger',
            'warning' => 'alert-warning',
            default => 'alert-info',
        };
        $iconClass = match($type) {
            'success' => 'fa-circle-check',
            'danger', 'error' => 'alert-circle',
            'warning' => 'fa-triangle-exclamation',
            default => 'fa-circle-info',
        };
        $output .= '<div class="alert ' . $alertClass . ' alert-dismissible fade show d-flex align-items-center mb-4" role="alert">';
        $output .= '<i class="fa-solid ' . $iconClass . ' me-2 fs-5"></i>';
        $output .= '<div>' . e($message) . '</div>';
        $output .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        $output .= '</div>';
    }
    return $output;
}

// Date Formatter (Friendly Indian Format)
function format_date($date_str, $format = 'd M Y, h:i A') {
    if (empty($date_str)) return 'N/A';
    $dt = new DateTime($date_str);
    return $dt->format($format);
}

// Format Currency in INR
function format_inr($amount) {
    return '₹' . number_format((float)$amount, 2);
}

// Get Badge Markup for Statuses
function get_status_badge($status) {
    return match (strtolower((string)$status)) {
        'verified', 'active', 'accepted', 'completed', 'available', 'resolved' => '<span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>' . ucfirst($status) . '</span>',
        'pending', 'under_review', 'open', 'unread' => '<span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>' . ucfirst(str_replace('_', ' ', $status)) . '</span>',
        'rejected', 'suspended', 'cancelled', 'busy' => '<span class="badge bg-danger"><i class="fa-solid fa-xmark me-1"></i>' . ucfirst($status) . '</span>',
        'off_duty' => '<span class="badge bg-secondary"><i class="fa-solid fa-moon me-1"></i>Off Duty</span>',
        default => '<span class="badge bg-secondary">' . ucfirst($status) . '</span>',
    };
}

// Secure Image Upload Handler
function handle_image_upload($file_array, $target_dir_name = 'uploads') {
    if (!isset($file_array) || $file_array['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $upload_base_dir = __DIR__ . '/../assets/uploads/';
    if (!is_dir($upload_base_dir)) {
        mkdir($upload_base_dir, 0755, true);
    }

    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
    $file_info = pathinfo($file_array['name']);
    $ext = strtolower($file_info['extension'] ?? '');

    if (!in_array($ext, $allowed_extensions)) {
        set_flash('danger', 'Invalid file type. Only JPG, PNG, and WEBP images are allowed.');
        return null;
    }

    if ($file_array['size'] > 2 * 1024 * 1024) { // 2MB Max
        set_flash('danger', 'File size exceeds 2MB limit.');
        return null;
    }

    $new_filename = uniqid('img_', true) . '.' . $ext;
    $destination = $upload_base_dir . $new_filename;

    if (move_uploaded_file($file_array['tmp_name'], $destination)) {
        return 'assets/uploads/' . $new_filename;
    }

    return null;
}
