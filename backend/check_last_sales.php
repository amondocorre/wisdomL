<?php
$mysqli = new mysqli('127.0.0.1', 'root', '', 'BDwisdom');
if ($mysqli->connect_error) die($mysqli->connect_error);

echo "=== ULTIMOS REGISTROS EN VENTAS ===\n";
$res = $mysqli->query("SELECT * FROM ventas ORDER BY id DESC LIMIT 5");
while ($r = $res->fetch_assoc()) print_r($r);

echo "\n=== ULTIMOS REGISTROS EN VENTA_DOCUMENTOS ===\n";
$res2 = $mysqli->query("SELECT * FROM venta_documentos ORDER BY id DESC LIMIT 5");
while ($r = $res2->fetch_assoc()) print_r($r);

echo "\n=== ULTIMOS REGISTROS EN VENTA_TRANSPORTE ===\n";
$res3 = $mysqli->query("SELECT * FROM venta_transporte ORDER BY id DESC LIMIT 5");
while ($r = $res3->fetch_assoc()) print_r($r);
