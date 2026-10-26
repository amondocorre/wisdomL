<?php
$hosts = ['localhost', '127.0.0.1'];
$mysqli = null;
foreach ($hosts as $host) {
    $mysqli = @new mysqli($host, 'root', '', 'BDwisdom');
    if (!$mysqli->connect_error) {
        echo "Connected via $host to BDwisdom\n";
        break;
    }
}
if (!$mysqli || $mysqli->connect_error) {
    die("Error connecting to database. Please check credentials or port.\n");
}

// 1. Agregar columna
$check = $mysqli->query("SHOW COLUMNS FROM productos LIKE 'venta_por_pieza'");
if ($check->num_rows == 0) {
    if ($mysqli->query("ALTER TABLE productos ADD COLUMN venta_por_pieza TINYINT(1) NOT NULL DEFAULT 0 AFTER se_fracciona;")) {
        echo "Columna venta_por_pieza agregada exitosamente.\n";
    } else {
        echo "Error al agregar columna: " . $mysqli->error . "\n";
    }
} else {
    echo "La columna venta_por_pieza ya existe.\n";
}

// 2. Actualizar productos 60x120
$sql = "UPDATE productos SET venta_por_pieza = 1 WHERE formato_id IN (SELECT id FROM cat_formatos WHERE nombre LIKE '%60x120%' OR nombre LIKE '%60120%' OR abreviacion='60120') OR nombre LIKE '%60x120%' OR codigo LIKE '%60120%'";
if ($mysqli->query($sql)) {
    echo 'Productos actualizados correctamente. Filas afectadas: ' . $mysqli->affected_rows . "\n";
} else {
    // Si falla por abreviacion
    echo "Fallo el primer query, intentando sin abreviacion...\n";
    $sql2 = "UPDATE productos SET venta_por_pieza = 1 WHERE formato_id IN (SELECT id FROM cat_formatos WHERE nombre LIKE '%60x120%' OR nombre LIKE '%60120%') OR nombre LIKE '%60x120%' OR codigo LIKE '%60120%'";
    if ($mysqli->query($sql2)) {
        echo 'Productos actualizados correctamente (query 2). Filas afectadas: ' . $mysqli->affected_rows . "\n";
    } else {
        echo "Error: " . $mysqli->error . "\n";
    }
}
$mysqli->close();
