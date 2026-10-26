<?php
$mysqli = new mysqli('127.0.0.1', 'root', '', 'BDwisdom');
if ($mysqli->connect_error) die($mysqli->connect_error);

echo "=== TABLA config_escalas_precios ===\n";
$res1 = $mysqli->query("DESCRIBE config_escalas_precios");
while ($r = $res1->fetch_assoc()) {
    print_r($r);
}

echo "=== CONTENIDO config_escalas_precios ===\n";
$res2 = $mysqli->query("SELECT * FROM config_escalas_precios");
while ($r = $res2->fetch_assoc()) {
    print_r($r);
}

echo "=== TABLA precios_escalonados ===\n";
$res3 = $mysqli->query("DESCRIBE precios_escalonados");
while ($r = $res3->fetch_assoc()) {
    print_r($r);
}
