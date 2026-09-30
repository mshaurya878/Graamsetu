<?php
// Search for is_urgent across all PHP files in graamsetu

$dir = __DIR__ . '/..';
$rdi = new RecursiveDirectoryIterator($dir);
$rii = new RecursiveIteratorIterator($rdi);

echo "Searching for 'is_urgent'...\n";
foreach ($rii as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        if (str_contains($content, 'is_urgent')) {
            echo "Found in: " . $file->getPathname() . "\n";
            $lines = explode("\n", $content);
            foreach ($lines as $i => $line) {
                if (str_contains($line, 'is_urgent')) {
                    echo "  Line " . ($i + 1) . ": " . trim($line) . "\n";
                }
            }
        }
    }
}
