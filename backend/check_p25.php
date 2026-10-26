<?php
$mysqli = new mysqli('127.0.0.1', 'root', '', 'BDwisdom');
if ($mysqli->connect_error) die($mysqli->connect_error);

echo "=== PRODUCTO 25 ===\n";
$res = $mysqli->query("SELECT id, nombre, m2_por_caja, precio_base_bob FROM productos WHERE id = 25");
print_r($res->fetch_assoc());

echo "\n=== STOCK PRODUCTO 25 ===\n";
$res2 = $mysqli->query("SELECT * FROM inventario_almacen WHERE producto_id = 25");
while ($r = $res2->fetch_assoc()) print_r($r);
