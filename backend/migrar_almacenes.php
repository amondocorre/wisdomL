<?php
$pdo = new PDO('mysql:host=localhost;dbname=BDwisdom;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "Iniciando creacion de ciudades y reestructuracion de almacenes...\n";

// 1. Crear tabla ciudades
$pdo->exec("CREATE TABLE IF NOT EXISTS ciudades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    estado TINYINT DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;");

// 2. Insertar ciudades principales
$ciudades = ['Cochabamba', 'Santa Cruz', 'La Paz', 'Tarija', 'Oruro', 'Potosi', 'Beni', 'Pando', 'Sucre'];
$stmt = $pdo->prepare("INSERT IGNORE INTO ciudades (nombre) VALUES (?)");
foreach ($ciudades as $c) {
    $stmt->execute([$c]);
}
echo "Tabla ciudades creada y poblada.\n";

// 3. Alterar tabla almacenes (Añadir ciudad_id y cambiar ENUM de tipo)
$cols = $pdo->query("SHOW COLUMNS FROM almacenes")->fetchAll(PDO::FETCH_COLUMN);

// Evitar romper el enum viejo de inmediato. Primero hacemos el alter del enum para soportar todo
$pdo->exec("ALTER TABLE almacenes MODIFY COLUMN tipo ENUM('VENTA', 'GALPON', 'SHOWROOM', 'DEFECTUOSO') DEFAULT 'GALPON'");

if (!in_array('ciudad_id', $cols)) {
    $pdo->exec("ALTER TABLE almacenes ADD COLUMN ciudad_id INT NULL AFTER sucursal_id");
    // Asignamos Cochabamba (ID 1) por defecto a los existentes para no romper
    $pdo->exec("UPDATE almacenes SET ciudad_id = 1 WHERE ciudad_id IS NULL");
    // Hacemos el FK
    $pdo->exec("ALTER TABLE almacenes ADD CONSTRAINT fk_almacen_ciudad FOREIGN KEY (ciudad_id) REFERENCES ciudades(id) ON DELETE RESTRICT");
}

// 4. Actualizar los almacenes de tipo "VENTA" (antiguos) a "SHOWROOM"
$pdo->exec("UPDATE almacenes SET tipo = 'SHOWROOM' WHERE tipo = 'VENTA'");

// Limpiar el ENUM para que solo quede GALPON, SHOWROOM y DEFECTUOSO
$pdo->exec("ALTER TABLE almacenes MODIFY COLUMN tipo ENUM('GALPON', 'SHOWROOM', 'DEFECTUOSO') DEFAULT 'GALPON'");

echo "Almacenes reestructurados con exito.\n";
