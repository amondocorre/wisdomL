<?php
define('BASEPATH', 'dummy');
require_once 'application/config/database.php';
$db_config = $db['default'];

$mysqli = new mysqli($db_config['hostname'], $db_config['username'], $db_config['password'], $db_config['database']);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== AGREGANDO COLUMNA ID AUTO_INCREMENT A inventario_almacen ===\n";

$mysqli->query("SET FOREIGN_KEY_CHECKS=0");

// 1. Eliminar la clave primaria compuesta existente
$q1 = "ALTER TABLE inventario_almacen DROP PRIMARY KEY";
if ($mysqli->query($q1)) {
    echo "1. Clave primaria compuesta removida.\n";
} else {
    echo "1. Nota DROP PRIMARY KEY: " . $mysqli->error . "\n";
}

// 2. Agregar columna id como PRIMARY KEY AUTO_INCREMENT
$q2 = "ALTER TABLE inventario_almacen ADD COLUMN id INT AUTO_INCREMENT PRIMARY KEY FIRST";
if ($mysqli->query($q2)) {
    echo "2. Columna 'id' AUTO_INCREMENT PRIMARY KEY agregada exitosamente.\n";
} else {
    echo "2. Error al agregar 'id': " . $mysqli->error . "\n";
}

// 3. Crear indice UNIQUE para (almacen_id, producto_id)
$q3 = "ALTER TABLE inventario_almacen ADD CONSTRAINT unique_almacen_producto UNIQUE (almacen_id, producto_id)";
if ($mysqli->query($q3)) {
    echo "3. Clave de unicidad UNIQUE(almacen_id, producto_id) creada exitosamente.\n";
} else {
    echo "3. Nota UNIQUE: " . $mysqli->error . "\n";
}

$mysqli->query("SET FOREIGN_KEY_CHECKS=1");

echo "\n=== NUEVA ESTRUCTURA DE inventario_almacen ===\n";
$res = $mysqli->query("DESCRIBE inventario_almacen");
while ($row = $res->fetch_assoc()) {
    echo "{$row['Field']} - {$row['Type']} - Key: {$row['Key']} - Extra: {$row['Extra']}\n";
}
