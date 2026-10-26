<?php
$mysqli = new mysqli('127.0.0.1', 'root', '', 'BDwisdom');
if ($mysqli->connect_error) die($mysqli->connect_error);

$sql = "CREATE TABLE IF NOT EXISTS `inventario_movimientos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `producto_id` INT NOT NULL,
  `almacen_origen_id` INT DEFAULT NULL,
  `almacen_destino_id` INT DEFAULT NULL,
  `tipo_movimiento` ENUM('ENTRADA_IMPORTACION', 'SALIDA_VENTA', 'TRANSFERENCIA', 'AJUSTE', 'MERMA') NOT NULL,
  `cantidad_cajas` INT NOT NULL,
  `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `observacion` TEXT DEFAULT NULL,
  INDEX (`producto_id`),
  INDEX (`almacen_origen_id`),
  INDEX (`tipo_movimiento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($mysqli->query($sql)) {
    echo "Tabla 'inventario_movimientos' creada o verificada exitosamente.\n";
} else {
    echo "Error: " . $mysqli->error . "\n";
}
