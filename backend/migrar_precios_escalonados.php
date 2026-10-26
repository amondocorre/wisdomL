<?php
$mysqli = new mysqli('127.0.0.1', 'root', '', 'BDwisdom');
if ($mysqli->connect_error) die("Error de conexión: " . $mysqli->connect_error);

echo "Iniciando migración de precios_escalonados...\n";

// 1. Crear tabla temporal normalizada
$mysqli->query("DROP TABLE IF EXISTS precios_escalonados_nueva");

$sqlCreate = "CREATE TABLE precios_escalonados_nueva (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT NOT NULL,
    escala_id INT NOT NULL,
    precio_caja_bob DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    FOREIGN KEY (escala_id) REFERENCES config_escalas_precios(id) ON DELETE CASCADE,
    UNIQUE KEY uq_prod_escala (producto_id, escala_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

if (!$mysqli->query($sqlCreate)) {
    die("Error al crear tabla nueva: " . $mysqli->error . "\n");
}
echo "Tabla precios_escalonados_nueva creada exitosamente.\n";

// 2. Migrar datos existentes mapeando rango_min y rango_max con config_escalas_precios
$sqlMigrar = "INSERT IGNORE INTO precios_escalonados_nueva (producto_id, escala_id, precio_caja_bob, creado_en)
SELECT pe.producto_id, cep.id as escala_id, pe.precio_caja_bob, pe.creado_en
FROM precios_escalonados pe
INNER JOIN config_escalas_precios cep 
    ON pe.rango_min = cep.rango_min AND pe.rango_max = cep.rango_max
WHERE pe.precio_caja_bob > 0;";

if (!$mysqli->query($sqlMigrar)) {
    die("Error al migrar datos: " . $mysqli->error . "\n");
}
echo "Filas migradas exitosamente: " . $mysqli->affected_rows . "\n";

// 3. Reemplazar la tabla vieja por la nueva
$mysqli->query("DROP TABLE IF EXISTS precios_escalonados_backup");
$mysqli->query("RENAME TABLE precios_escalonados TO precios_escalonados_backup, precios_escalonados_nueva TO precios_escalonados");

echo "Migración completada exitosamente. La tabla vieja fue guardada como precios_escalonados_backup.\n";

// 4. Mostrar estructura final
echo "=== ESTRUCTURA FINAL DE precios_escalonados ===\n";
$res = $mysqli->query("DESCRIBE precios_escalonados");
while ($r = $res->fetch_assoc()) {
    print_r($r);
}

echo "=== MUESTRA DE DATOS MIGRADOS (JOIN CON ESCALAS) ===\n";
$resData = $mysqli->query("SELECT pe.*, p.codigo, cep.nombre as escala_nombre, cep.rango_min, cep.rango_max 
FROM precios_escalonados pe
JOIN productos p ON p.id = pe.producto_id
JOIN config_escalas_precios cep ON cep.id = pe.escala_id
LIMIT 5");
while ($r = $resData->fetch_assoc()) {
    print_r($r);
}
