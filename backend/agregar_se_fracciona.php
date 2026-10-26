<?php
$mysqli = new mysqli('127.0.0.1', 'root', '', 'BDwisdom');
if ($mysqli->connect_error) {
    die("Error de conexión: " . $mysqli->connect_error);
}

$checkCol = $mysqli->query("SHOW COLUMNS FROM `productos` LIKE 'se_fracciona'");
if ($checkCol && $checkCol->num_rows == 0) {
    $sqlAlter = "ALTER TABLE `productos` ADD COLUMN `se_fracciona` TINYINT(1) NOT NULL DEFAULT 0 AFTER `activo`;";
    if ($mysqli->query($sqlAlter)) {
        echo "Columna 'se_fracciona' agregada exitosamente a la tabla 'productos'.\n";
    } else {
        echo "Error al agregar la columna 'se_fracciona': " . $mysqli->error . "\n";
    }
} else {
    echo "La columna 'se_fracciona' ya existe en la tabla 'productos'.\n";
}

$mysqli->close();
