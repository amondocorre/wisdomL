<?php
define('BASEPATH', 'dummy');
require_once 'application/config/database.php';
$db_config = $db['default'];

$mysqli = new mysqli($db_config['hostname'], $db_config['username'], $db_config['password'], $db_config['database']);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== FOREIGN KEYS ON inventario_almacen ===\n";
$res = $mysqli->query("
    SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = '{$db_config['database']}'
      AND (TABLE_NAME = 'inventario_almacen' OR REFERENCED_TABLE_NAME = 'inventario_almacen')
");

while ($row = $res->fetch_assoc()) {
    print_r($row);
}
