<?php
$mysqli = new mysqli('127.0.0.1', 'root', '', 'BDwisdom');
if ($mysqli->connect_error) die($mysqli->connect_error);

echo "=== TABLA INVENTARIO_MOVIMIENTOS ===\n";
$res = $mysqli->query("DESCRIBE inventario_movimientos");
if ($res) {
    while ($row = $res->fetch_assoc()) print_r($row);
} else {
    echo "NO EXISTE LA TABLA inventario_movimientos: " . $mysqli->error . "\n";
}
