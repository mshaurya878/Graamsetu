<?php
/**
 * GraamSetu — Automated Output-Order Fixer
 *
 * For every PHP file with header.php/navbar.php included before a header() call,
 * this script moves the HTML-emitting includes to AFTER all PHP processing.
 *
 * Strategy: find the line where require header.php and require navbar.php are,
 * remove them from their current position, and inject them just before the first
 * HTML output (the closing ?> or the first HTML tag line).
 *
 * Files already fixed manually: register.php, login.php
 */

$dir = 'C:\\xampp\\htdocs\\graamsetu';

// Files to fix (relative paths)
$targets = [
    'admin/announcements.php',
    'admin/categories.php',
    'admin/complaints.php',
    'admin/users.php',
    'citizen/complaints.php',
    'citizen/profile.php',
    'citizen/requests.php',
    'citizen/services.php',
    'merchant/business.php',
    'merchant/dashboard.php',
    'merchant/enquiries.php',
    'merchant/products.php',
    'merchant/profile.php',
    'provider/dashboard.php',
    'provider/profile.php',
    'provider/requests.php',
    'provider/services.php',
];

$php_bin = 'C:\\xampp\\php\\php.exe';
$fixed   = 0;
$errors  = 0;

foreach ($targets as $rel) {
    $path = $dir . '\\' . str_replace('/', '\\', $rel);

    if (!file_exists($path)) {
        echo "SKIP (not found): $rel\n";
        continue;
    }

    $original = file_get_contents($path);
    $lines    = explode("\n", $original);

    // Find lines containing the two HTML-output includes
    $header_pattern = '/require(?:_once)?\s+.*header\.php/';
    $navbar_pattern = '/require(?:_once)?\s+.*navbar\.php/';

    $header_line_idx = null;
    $navbar_line_idx = null;
    $header_line_str = '';
    $navbar_line_str = '';

    foreach ($lines as $i => $line) {
        if ($header_line_idx === null && preg_match($header_pattern, $line)) {
            $header_line_idx = $i;
            $header_line_str = rtrim($line);
        }
        if ($navbar_line_idx === null && preg_match($navbar_pattern, $line)) {
            $navbar_line_idx = $i;
            $navbar_line_str = rtrim($line);
        }
    }

    if ($header_line_idx === null || $navbar_line_idx === null) {
        echo "SKIP (no header/navbar includes found): $rel\n";
        continue;
    }

    // Find the closing ?> that ends the PHP block before HTML (or the first line of HTML)
    // We look for a ?> that is followed by HTML content
    $closing_php_idx = null;
    for ($i = max($header_line_idx, $navbar_line_idx) + 1; $i < count($lines); $i++) {
        $trimmed = trim($lines[$i]);
        if ($trimmed === '?>' || str_starts_with($trimmed, '?>')) {
            $closing_php_idx = $i;
            break;
        }
    }

    if ($closing_php_idx === null) {
        echo "SKIP (cannot find closing PHP tag): $rel\n";
        continue;
    }

    // Remove the header.php and navbar.php lines from current position
    // (work backwards so indexes stay valid)
    $indices_to_remove = array_unique([$header_line_idx, $navbar_line_idx]);
    rsort($indices_to_remove);
    foreach ($indices_to_remove as $idx) {
        array_splice($lines, $idx, 1);
        // Adjust closing_php_idx if it was after a removed line
        if ($idx < $closing_php_idx) {
            $closing_php_idx--;
        }
    }

    // Now insert just before the closing ?> line
    // Insert: blank line, header include, navbar include
    $insert_lines = [
        '',
        '// ─── HTML output begins after all server-side processing ───',
        $header_line_str,
        $navbar_line_str,
    ];

    array_splice($lines, $closing_php_idx, 0, $insert_lines);

    $new_content = implode("\n", $lines);

    // Syntax check first
    $tmp = tempnam(sys_get_temp_dir(), 'gs_fix_');
    file_put_contents($tmp, $new_content);
    $syntax = shell_exec('"' . $php_bin . '" -l "' . $tmp . '" 2>&1');
    unlink($tmp);

    if (!str_contains($syntax, 'No syntax errors detected')) {
        echo "[FAIL] Syntax error in modified $rel — skipping. Error: " . trim($syntax) . "\n";
        $errors++;
        continue;
    }

    file_put_contents($path, $new_content);
    echo "[FIXED] $rel\n";
    $fixed++;
}

echo "\n";
echo "Done. Fixed: $fixed, Errors: $errors\n";
