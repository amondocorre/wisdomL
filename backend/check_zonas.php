<?php
$mysqli = new mysqli('127.0.0.1', 'root', '', 'BDwisdom');
if ($mysqli->connect_error) die($mysqli->connect_error);

$res = $mysqli->query("SELECT * FROM zonas_operacion");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
