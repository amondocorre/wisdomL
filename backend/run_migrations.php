<?php
$pdo = new PDO('mysql:host=localhost;dbname=BDwisdom;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "1. Creando tabla usuario_ciudad...\n";
$pdo->exec("CREATE TABLE IF NOT EXISTS usuario_ciudad (
    usuario_id INT NOT NULL,
    ciudad_id INT NOT NULL,
    PRIMARY KEY (usuario_id, ciudad_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (ciudad_id) REFERENCES ciudades(id) ON DELETE CASCADE
) ENGINE=InnoDB;");

echo "2. Agregando columnas faltantes a usuarios...\n";
$cols = $pdo->query("SHOW COLUMNS FROM usuarios")->fetchAll(PDO::FETCH_COLUMN);
$addCols = [
    'foto' => 'VARCHAR(255) NULL',
    'ubicacion_gps' => 'VARCHAR(255) NULL',
    'descripcion_direccion' => 'TEXT NULL',
    'telefono' => 'VARCHAR(50) NULL',
    'observaciones' => 'TEXT NULL',
    'ci' => 'VARCHAR(30) NULL',
    'expedido' => 'VARCHAR(20) NULL',
    'genero' => "ENUM('M', 'F', 'OTRO') DEFAULT 'M'",
    'fecha_nacimiento' => 'DATE NULL',
    'fecha_ingreso' => 'DATE NULL',
    'numero_cuenta' => 'VARCHAR(50) NULL'
];
foreach ($addCols as $col => $def) {
    if (!in_array($col, $cols)) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN $col $def");
        echo "Columna $col agregada a usuarios.\n";
    }
}

echo "3. Insertando asignaciones por defecto de usuario_ciudad...\n";
// Asignar al admin todas las ciudades existentes
$ciudades = $pdo->query("SELECT id FROM ciudades")->fetchAll(PDO::FETCH_COLUMN);
$usuarios = $pdo->query("SELECT id FROM usuarios")->fetchAll(PDO::FETCH_COLUMN);
foreach ($usuarios as $uid) {
    foreach ($ciudades as $cid) {
        $pdo->exec("INSERT IGNORE INTO usuario_ciudad (usuario_id, ciudad_id) VALUES ($uid, $cid)");
    }
}

// Asignar almacenes existentes a usuarios
$almacenes = $pdo->query("SELECT id FROM almacenes")->fetchAll(PDO::FETCH_COLUMN);
foreach ($usuarios as $uid) {
    foreach ($almacenes as $aid) {
        $pdo->exec("INSERT IGNORE INTO usuario_almacen (usuario_id, almacen_id) VALUES ($uid, $aid)");
    }
}

echo "Migraciones completadas.\n";
