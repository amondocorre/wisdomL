<?php
$mysqli = new mysqli('127.0.0.1', 'root', '', 'BDwisdom');
if ($mysqli->connect_error) {
    die($mysqli->connect_error);
}

$c = $mysqli->query("SHOW COLUMNS FROM clientes LIKE 'activo'");
if ($c->num_rows == 0) {
    $mysqli->query("ALTER TABLE clientes ADD COLUMN activo TINYINT DEFAULT 1");
    echo "ADDED_ACTIVO_COLUMN";
} else {
    echo "ACTIVO_COLUMN_EXISTS";
}
