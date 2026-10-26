<?php
$base_dir = __DIR__;
$upload_path = $base_dir . '/application/views/errors/cli/';
if (!is_dir($upload_path)) {
    mkdir($upload_path, 0777, true);
}

$files = ['error_php.php', 'error_general.php', 'error_db.php', 'error_exception.php', 'error_404.php'];
foreach ($files as $f) {
    $src = $base_dir . '/application/views/html/' . $f;
    $dst = $upload_path . $f;
    if (file_exists($src) && !file_exists($dst)) {
        copy($src, $dst);
        echo "Copiado $f a $dst\n";
    }
}
