<?php
define('BASEPATH', 'dummy');
require_once __DIR__ . '/application/config/database.php';

$db_config = $db['default'];
$dsn = "mysql:host={$db_config['hostname']};dbname={$db_config['database']};charset={$db_config['char_set']}";
try {
    $pdo = new PDO($dsn, $db_config['username'], $db_config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->query("SELECT p.unidad_base_id, cat_u.descripcion, cat_u.abreviacion, COUNT(*) as total FROM productos p LEFT JOIN cat_unidad_base cat_u ON cat_u.id = p.unidad_base_id GROUP BY p.unidad_base_id");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
