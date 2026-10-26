<?php
define('BASEPATH', true);
require_once __DIR__ . '/application/config/database.php';
$db_config = $db['default'];

try {
    $pdo = new PDO('mysql:host=' . $db_config['hostname'] . ';dbname=' . $db_config['database'] . ';charset=utf8', $db_config['username'], $db_config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $check = $pdo->query("SHOW COLUMNS FROM config_escalas_precios LIKE 'precio_cliente_final'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE config_escalas_precios ADD COLUMN precio_cliente_final TINYINT(1) NOT NULL DEFAULT 0 AFTER ordenado");
        echo "COLUMNA_PRECIO_CLIENTE_FINAL_AGREGADA\n";
    } else {
        echo "COLUMNA_YA_EXISTE\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
