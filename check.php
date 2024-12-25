<?php
$base_dir = __DIR__;
$required_files = [
    'lus.php',
    'includes/class-lus-activator.php',
    'includes/class-lus-database.php',
    'includes/class-lus-loader.php'
];

foreach ($required_files as $file) {
    $full_path = $base_dir . '/' . $file;
    echo "$file: " . (file_exists($full_path) ? "Exists" : "Missing") .
         " | Permissions: " . decoct(fileperms($full_path) & 0777) . "\n";
}