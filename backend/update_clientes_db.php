<?php
$mysqli = new mysqli('127.0.0.1', 'root', '', 'BDwisdom');
if ($mysqli->connect_error) {
    die($mysqli->connect_error);
}

// 1. Crear tabla zonas_operacion
$mysqli->query("CREATE TABLE IF NOT EXISTS zonas_operacion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ciudad_nombre VARCHAR(100) NOT NULL,
    nombre_zona VARCHAR(100) NOT NULL,
    activo TINYINT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

$res = $mysqli->query("SELECT COUNT(*) as c FROM zonas_operacion");
$row = $res->fetch_assoc();
if ($row['c'] == 0) {
    $mysqli->query("INSERT INTO zonas_operacion (ciudad_nombre, nombre_zona) VALUES 
    ('Cochabamba', 'Humbolt'),
    ('Cochabamba', '6 de Agosto'),
    ('Cochabamba', 'Vinto'),
    ('Cochabamba', 'Quillacollo'),
    ('Santa Cruz', 'Av. Brasil'),
    ('Santa Cruz', 'Alto San Pedro'),
    ('Santa Cruz', 'Equipetrol'),
    ('La Paz', 'Zona Sur'),
    ('La Paz', 'Miraflores'),
    ('Oruro', 'Zona Central'),
    ('Tarija', 'Zona Central'),
    ('Sucre', 'Zona Central');");
}

// 2. Agregar campos a la tabla clientes
$cols = [
    'zona_operacion_nombre' => 'VARCHAR(100) DEFAULT NULL',
    'comercial_foto_url' => 'TEXT DEFAULT NULL',
    'comercial_fecha_cumpleanos' => 'DATE DEFAULT NULL'
];

foreach ($cols as $col => $def) {
    $c = $mysqli->query("SHOW COLUMNS FROM clientes LIKE '$col'");
    if ($c->num_rows == 0) {
        $mysqli->query("ALTER TABLE clientes ADD COLUMN $col $def");
        echo "Added $col. ";
    }
}

echo "DB_UPDATED_SUCCESSFULLY";
