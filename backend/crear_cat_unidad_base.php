<?php
$mysqli = new mysqli('127.0.0.1', 'root', '', 'BDwisdom');
if ($mysqli->connect_error) {
    die("Error de conexión: " . $mysqli->connect_error);
}

// 1. Crear tabla cat_unidad_base
$sqlTable = "CREATE TABLE IF NOT EXISTS `cat_unidad_base` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `descripcion` VARCHAR(100) NOT NULL,
  `abreviacion` VARCHAR(20) NOT NULL,
  `estado` TINYINT(1) NOT NULL DEFAULT 1,
  `fecha_registro` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($mysqli->query($sqlTable)) {
    echo "Tabla 'cat_unidad_base' creada/verificada correctamente.\n";
} else {
    echo "Error al crear 'cat_unidad_base': " . $mysqli->error . "\n";
}

// 2. Insertar valores iniciales por defecto si la tabla está vacía
$resCount = $mysqli->query("SELECT COUNT(*) as total FROM `cat_unidad_base`");
$row = $resCount->fetch_assoc();
if ($row['total'] == 0) {
    $sqlSeed = "INSERT INTO `cat_unidad_base` (`descripcion`, `abreviacion`, `estado`) VALUES
        ('Metro Cuadrado', 'M2', 1),
        ('Pieza / Unidad', 'PZA', 1),
        ('Bolsa', 'BLS', 1),
        ('Caja', 'CAJ', 1);";
    if ($mysqli->query($sqlSeed)) {
        echo "Unidades base iniciales insertadas correctamente.\n";
    } else {
        echo "Error al insertar unidades iniciales: " . $mysqli->error . "\n";
    }
}

// 3. Agregar relacion unidad_base_id en la tabla productos
$checkCol = $mysqli->query("SHOW COLUMNS FROM `productos` LIKE 'unidad_base_id'");
if ($checkCol && $checkCol->num_rows == 0) {
    $sqlAlter = "ALTER TABLE `productos` 
        ADD COLUMN `unidad_base_id` INT DEFAULT NULL AFTER `nombre`,
        ADD CONSTRAINT `fk_productos_unidad_base` FOREIGN KEY (`unidad_base_id`) REFERENCES `cat_unidad_base`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;";
    if ($mysqli->query($sqlAlter)) {
        echo "Columna 'unidad_base_id' y Foreign Key agregadas a la tabla 'productos'.\n";
    } else {
        echo "Error al modificar tabla 'productos': " . $mysqli->error . "\n";
    }
} else {
    echo "La columna 'unidad_base_id' ya existe en la tabla 'productos'.\n";
}

$mysqli->close();
