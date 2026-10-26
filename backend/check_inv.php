<?php
$mysqli = new mysqli('127.0.0.1', 'root', '', 'BDwisdom');
if ($mysqli->connect_error) die($mysqli->connect_error);

echo "=== ESTRUCTURA DE TABLA INVENTARIO_ALMACEN ===\n";
$res = $mysqli->query("DESCRIBE inventario_almacen");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
