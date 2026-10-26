<?php
define('BASEPATH', true);
require_once __DIR__ . '/application/config/database.php';
$db_config = $db['default'];

try {
    $pdo = new PDO('mysql:host=' . $db_config['hostname'] . ';dbname=' . $db_config['database'] . ';charset=utf8', $db_config['username'], $db_config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Tabla Documento (Cabecera del cambio)
    $pdo->exec("CREATE TABLE IF NOT EXISTS log_cambios_precios_documento (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        tipo_cambio VARCHAR(50) NOT NULL,
        modo_aplicado VARCHAR(50) NOT NULL,
        observacion TEXT NOT NULL,
        criterios_aplicados TEXT NULL,
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");

    // 2. Tabla Detalle (Producto, Escala, Precio Anterior y Nuevo)
    $pdo->exec("CREATE TABLE IF NOT EXISTS log_cambios_precios_detalle (
        id INT AUTO_INCREMENT PRIMARY KEY,
        documento_id INT NOT NULL,
        producto_id INT NOT NULL,
        escala_id INT NOT NULL,
        precio_anterior DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
        precio_nuevo DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
        FOREIGN KEY (documento_id) REFERENCES log_cambios_precios_documento(id) ON DELETE CASCADE,
        FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
        FOREIGN KEY (escala_id) REFERENCES config_escalas_precios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");

    echo "TABLAS_CREADAS_EXITOSAMENTE\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
