<?php
define('BASEPATH', 'dummy');
require_once 'application/config/database.php';
$db_config = $db['default'];

$mysqli = new mysqli($db_config['hostname'], $db_config['username'], $db_config['password'], $db_config['database']);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== COLUMNS IN inventario_almacen ===\n";
$res = $mysqli->query("DESCRIBE inventario_almacen");
while ($row = $res->fetch_assoc()) {
    echo "{$row['Field']} - {$row['Type']} - Key: {$row['Key']} - Extra: {$row['Extra']}\n";
}
