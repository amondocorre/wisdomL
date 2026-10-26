<?php
$mysqli = new mysqli('127.0.0.1', 'root', '', 'BDwisdom');
if ($mysqli->connect_error) die($mysqli->connect_error);

$sql1 = "CREATE TABLE IF NOT EXISTS `venta_documentos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `venta_id` INT NOT NULL,
  `cliente_id` INT NOT NULL,
  `cliente_nombre` VARCHAR(255) NOT NULL,
  `usuario_id` INT NOT NULL,
  `usuario_nombre` VARCHAR(255) NOT NULL,
  `almacen_id` INT DEFAULT NULL,
  `almacen_nombre` VARCHAR(255) DEFAULT NULL,
  `metodo_pago` VARCHAR(50) NOT NULL DEFAULT 'EFECTIVO',
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `costo_transporte` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `anulado` TINYINT(1) NOT NULL DEFAULT 0,
  `usuario_anulo_id` INT DEFAULT NULL,
  `usuario_anulo_nombre` VARCHAR(255) DEFAULT NULL,
  `fecha_anulacion` DATETIME DEFAULT NULL,
  `motivo_anulacion` TEXT DEFAULT NULL,
  `fecha_hora` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`venta_id`),
  INDEX (`cliente_id`),
  INDEX (`usuario_id`),
  INDEX (`anulado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($mysqli->query($sql1)) {
    echo "Tabla 'venta_documentos' creada o verificada exitosamente.\n";
} else {
    echo "Error al crear 'venta_documentos': " . $mysqli->error . "\n";
}

$sql2 = "CREATE TABLE IF NOT EXISTS `venta_documento_detalles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `venta_documento_id` INT NOT NULL,
  `venta_id` INT NOT NULL,
  `producto_id` INT NOT NULL,
  `producto_codigo` VARCHAR(100) DEFAULT NULL,
  `producto_nombre` VARCHAR(255) NOT NULL,
  `almacen_id` INT NOT NULL,
  `inventario_almacen_id` INT DEFAULT NULL,
  `cajas_solicitadas` INT NOT NULL,
  `m2_reales` DECIMAL(12,2) NOT NULL,
  `precio_unitario_caja` DECIMAL(12,2) NOT NULL,
  `precio_unitario_m2` DECIMAL(12,2) DEFAULT NULL,
  `subtotal` DECIMAL(12,2) NOT NULL,
  `fecha_hora` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (`venta_documento_id`),
  INDEX (`venta_id`),
  INDEX (`producto_id`),
  INDEX (`almacen_id`),
  INDEX (`inventario_almacen_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($mysqli->query($sql2)) {
    echo "Tabla 'venta_documento_detalles' creada o verificada exitosamente.\n";
} else {
    echo "Error al crear 'venta_documento_detalles': " . $mysqli->error . "\n";
}
