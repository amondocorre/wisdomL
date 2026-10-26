<?php
$pdo = new PDO('mysql:host=localhost;dbname=BDwisdom;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "Actualizando inventario a 300 m2 exactos para todos los productos...\n";
$stmt = $pdo->query("SELECT id FROM productos");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pdo->beginTransaction();
foreach ($productos as $p) {
    $id = $p['id'];
    // Eliminar stock previo de este galpon por si acaso
    $pdo->exec("DELETE FROM inventario_almacen WHERE producto_id = $id AND almacen_id = 1");
    // Insertar 300.00 m2
    $pdo->exec("INSERT INTO inventario_almacen (producto_id, almacen_id, stock_fisico, stock_reservado, stock_disponible) VALUES ($id, 1, 300.0000, 0.0000, 300.0000)");
}
$pdo->commit();

echo "Stock m2 cargado a " . count($productos) . " productos con exito.\n";
