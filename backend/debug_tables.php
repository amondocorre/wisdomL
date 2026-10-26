<?php
$mysqli = new mysqli('127.0.0.1', 'root', '', 'BDwisdom');
if ($mysqli->connect_error) die($mysqli->connect_error);

echo "=== TABLA VENTAS ===\n";
$res = $mysqli->query("DESCRIBE ventas");
while ($row = $res->fetch_assoc()) print_r($row);

echo "\n=== TABLA VENTA_DETALLES ===\n";
$res = $mysqli->query("DESCRIBE venta_detalles");
while ($row = $res->fetch_assoc()) print_r($row);

echo "\n=== TABLA VENTA_DOCUMENTOS ===\n";
$res = $mysqli->query("DESCRIBE venta_documentos");
while ($row = $res->fetch_assoc()) print_r($row);

echo "\n=== TABLA VENTA_DOCUMENTO_DETALLES ===\n";
$res = $mysqli->query("DESCRIBE venta_documento_detalles");
while ($row = $res->fetch_assoc()) print_r($row);
