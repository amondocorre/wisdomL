<?php
$pdo = new PDO('mysql:host=localhost;dbname=BDwisdom;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "1. Creando tabla logistica_paises...\n";
$pdo->exec("CREATE TABLE IF NOT EXISTS logistica_paises (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    estado TINYINT DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;");

// Insertar Bolivia por defecto
$pdo->exec("INSERT IGNORE INTO logistica_paises (id, nombre) VALUES (1, 'Bolivia')");

echo "2. Asegurando columnas en ciudades...\n";
$colsCiudades = $pdo->query("SHOW COLUMNS FROM ciudades")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('pais_id', $colsCiudades)) {
    $pdo->exec("ALTER TABLE ciudades ADD COLUMN pais_id INT NULL DEFAULT 1 AFTER id");
    $pdo->exec("UPDATE ciudades SET pais_id = 1 WHERE pais_id IS NULL");
}

echo "3. Asegurando columnas en almacenes...\n";
$colsAlmacenes = $pdo->query("SHOW COLUMNS FROM almacenes")->fetchAll(PDO::FETCH_COLUMN);
$almacenCols = [
    'telefono' => 'VARCHAR(50) NULL',
    'ubicacion_gps' => 'VARCHAR(255) NULL',
    'foto' => 'VARCHAR(255) NULL',
    'estado' => 'TINYINT DEFAULT 1'
];
foreach ($almacenCols as $col => $def) {
    if (!in_array($col, $colsAlmacenes)) {
        $pdo->exec("ALTER TABLE almacenes ADD COLUMN $col $def");
        echo "Columna $col agregada a almacenes.\n";
    }
}

echo "4. Asegurando columnas en usuarios...\n";
$colsUsuarios = $pdo->query("SHOW COLUMNS FROM usuarios")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('ciudad_id', $colsUsuarios)) {
    $pdo->exec("ALTER TABLE usuarios ADD COLUMN ciudad_id INT NULL DEFAULT 1 AFTER activo");
    $pdo->exec("UPDATE usuarios SET ciudad_id = 1 WHERE ciudad_id IS NULL");
    echo "Columna ciudad_id agregada a usuarios.\n";
}

echo "5. Asegurando columnas en cajas_sesiones...\n";
$colsCajas = $pdo->query("SHOW COLUMNS FROM cajas_sesiones")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('ciudad_id', $colsCajas)) {
    $pdo->exec("ALTER TABLE cajas_sesiones ADD COLUMN ciudad_id INT NULL AFTER usuario_id");
    echo "Columna ciudad_id agregada a cajas_sesiones.\n";
}

echo "Migraciones completadas exitosamente.\n";
