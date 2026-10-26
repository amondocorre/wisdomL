<?php
$mysqli = new mysqli('127.0.0.1', 'root', '', 'BDwisdom');
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}
$res = $mysqli->query("SHOW COLUMNS FROM productos LIKE 'shade'");
if ($res->num_rows == 0) {
    if ($mysqli->query("ALTER TABLE productos ADD COLUMN shade VARCHAR(50) DEFAULT NULL")) {
        echo "COL_ADDED";
    } else {
        echo "ERROR: " . $mysqli->error;
    }
} else {
    echo "COL_EXISTS";
}
