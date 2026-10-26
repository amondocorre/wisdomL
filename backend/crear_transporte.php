<?php
$mysqli = new mysqli('127.0.0.1', 'root', '', 'BDwisdom');
if ($mysqli->connect_error) die($mysqli->connect_error);

$sql1 = "CREATE TABLE IF NOT EXISTS `venta_transporte` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `venta_documento_id` INT NOT NULL,
  `venta_id` INT NOT NULL,
  `tipo_transporte` ENUM('CLIENTE_RECOGE', 'TRANSPORTE_PROPIO', 'TRANSPORTE_EXTERNO') NOT NULL DEFAULT 'CLIENTE_RECOGE',
  `monto_transporte` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `nombre_empresa_chofer` VARCHAR(255) DEFAULT NULL,
  `placa_vehiculo` VARCHAR(50) DEFAULT NULL,
  `telefono_contacto` VARCHAR(50) DEFAULT NULL,
  `observacion` TEXT DEFAULT NULL,
  `fecha_registro` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (`venta_documento_id`),
  INDEX (`venta_id`),
  INDEX (`tipo_transporte`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($mysqli->query($sql1)) {
    echo "Tabla 'venta_transporte' creada exitosamente.\n";
} else {
    echo "Error venta_transporte: " . $mysqli->error . "\n";
}

// Agregar columnas a venta_documentos
$sql2 = "ALTER TABLE `venta_documentos` 
  ADD COLUMN `tiene_transporte` TINYINT(1) NOT NULL DEFAULT 0 AFTER `metodo_pago`,
  ADD COLUMN `tipo_transporte` ENUM('CLIENTE_RECOGE', 'TRANSPORTE_PROPIO', 'TRANSPORTE_EXTERNO') NOT NULL DEFAULT 'CLIENTE_RECOGE' AFTER `tiene_transporte`;";

if ($mysqli->query($sql2)) {
    echo "Columnas agregadas a 'venta_documentos' exitosamente.\n";
} else {
    echo "Info/Error alter venta_documentos: " . $mysqli->error . "\n";
}
