<?php
// Script para crear la tabla caja_movimientos y preparar la carpeta de uploads de comprobantes

define('BASEPATH', 'dummy');
require_once __DIR__ . '/application/config/database.php';

$db_config = $db['default'];
$dsn = "mysql:host={$db_config['hostname']};dbname={$db_config['database']};charset={$db_config['char_set']}";

try {
    $pdo = new PDO($dsn, $db_config['username'], $db_config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== CREANDO TABLA CAJA_MOVIMIENTOS ===\n";

    $sql = "CREATE TABLE IF NOT EXISTS `caja_movimientos` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `caja_sesion_id` INT NOT NULL,
        `usuario_id` INT NOT NULL,
        `tipo` ENUM('INGRESO', 'EGRESO') NOT NULL,
        `concepto` VARCHAR(50) NOT NULL,
        `monto` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `descripcion` TEXT NULL,
        `comprobante_url` VARCHAR(255) NULL,
        `fecha_hora` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (`caja_sesion_id`),
        INDEX (`usuario_id`),
        INDEX (`tipo`),
        CONSTRAINT `fk_caja_movimientos_sesion` FOREIGN KEY (`caja_sesion_id`) REFERENCES `cajas_sesiones`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT `fk_caja_movimientos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql);
    echo "Tabla 'caja_movimientos' creada/verificada exitosamente.\n";

    // Crear carpeta uploads/comprobantes_caja si no existe
    $uploads_dir = __DIR__ . '/uploads/comprobantes_caja';
    if (!file_exists($uploads_dir)) {
        if (mkdir($uploads_dir, 0777, true)) {
            echo "Carpeta 'uploads/comprobantes_caja' creada correctamente.\n";
        } else {
            echo "Advertencia: No se pudo crear la carpeta 'uploads/comprobantes_caja'.\n";
        }
    } else {
        echo "La carpeta 'uploads/comprobantes_caja' ya existe.\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
