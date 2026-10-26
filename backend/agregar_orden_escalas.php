<?php
$mysqli = new mysqli('127.0.0.1', 'root', '', 'BDwisdom');
if ($mysqli->connect_error) die("Error: " . $mysqli->connect_error);

// Verificar si la columna 'ordenado' ya existe
$res = $mysqli->query("SHOW COLUMNS FROM config_escalas_precios LIKE 'ordenado'");
if ($res->num_rows == 0) {
    $sql = "ALTER TABLE config_escalas_precios ADD COLUMN ordenado INT NOT NULL DEFAULT 0 AFTER rango_max";
    if ($mysqli->query($sql)) {
        echo "Columna 'ordenado' agregada con éxito.\n";
    } else {
        die("Error al agregar columna: " . $mysqli->error . "\n");
    }
} else {
    echo "La columna 'ordenado' ya existe.\n";
}

// Inicializar el orden correlativo basado en rango_min
$mysqli->query("SET @row_number = 0;");
$mysqli->query("UPDATE config_escalas_precios SET ordenado = (@row_number:=@row_number + 1) ORDER BY rango_min ASC;");

echo "=== ESTADO ACTUAL DE config_escalas_precios ===\n";
$resData = $mysqli->query("SELECT * FROM config_escalas_precios ORDER BY ordenado ASC, rango_min ASC");
while ($r = $resData->fetch_assoc()) {
    print_r($r);
}
