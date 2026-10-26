<?php
define('BASEPATH', true);
require_once __DIR__ . '/application/config/database.php';
$db_config = $db['default'];

try {
    $pdo = new PDO('mysql:host=' . $db_config['hostname'] . ';dbname=' . $db_config['database'] . ';charset=utf8', $db_config['username'], $db_config['password']);
    $stmt = $pdo->prepare('SELECT ia.*, a.nombre as almacen_nombre, a.ciudad_id FROM inventario_almacen ia JOIN almacenes a ON a.id = ia.almacen_id WHERE ia.producto_id = 828');
    $stmt->execute();
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
