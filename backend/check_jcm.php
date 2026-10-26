<?php
$mysqli = new mysqli('127.0.0.1', 'root', '', 'BDwisdom');
if ($mysqli->connect_error) die($mysqli->connect_error);

$res = $mysqli->query("SELECT * FROM productos WHERE codigo LIKE '%JCM63841D%' OR nombre LIKE '%JCM63841D%'");
while ($row = $res->fetch_assoc()) {
    print_r($row);
    $pId = $row['id'];
    echo "--- PRECIOS ESCALONADOS DE ID $pId ---\n";
    $resEsc = $mysqli->query("SELECT * FROM precios_escalonados WHERE producto_id = $pId");
    while ($esc = $resEsc->fetch_assoc()) {
        print_r($esc);
    }
}
