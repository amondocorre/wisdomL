<?php
$pdo = new PDO('mysql:host=localhost;dbname=BDwisdom;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "1. Asegurando columnas de stock en inventario_almacen...\n";
$cols = $pdo->query("SHOW COLUMNS FROM inventario_almacen")->fetchAll(PDO::FETCH_COLUMN);
$addCols = [
    'stock_fisico' => 'DECIMAL(12, 4) NOT NULL DEFAULT 0.00',
    'stock_reservado' => 'DECIMAL(12, 4) NOT NULL DEFAULT 0.00',
    'stock_disponible' => 'DECIMAL(12, 4) NOT NULL DEFAULT 0.00'
];

foreach ($addCols as $col => $def) {
    if (!in_array($col, $cols)) {
        $pdo->exec("ALTER TABLE inventario_almacen ADD COLUMN $col $def");
        echo "Columna $col agregada.\n";
    }
}

// Si habia stock_cajas viejo y stock_fisico es 0, sincronizar
$pdo->exec("UPDATE inventario_almacen SET stock_fisico = stock_cajas, stock_disponible = stock_cajas WHERE stock_fisico = 0 AND stock_cajas > 0");

echo "2. Población de stock inicial en almacenes para pruebas...\n";
$almacenes = $pdo->query("SELECT id FROM almacenes")->fetchAll(PDO::FETCH_COLUMN);
$productos = $pdo->query("SELECT id FROM productos")->fetchAll(PDO::FETCH_COLUMN);

foreach ($almacenes as $aid) {
    foreach ($productos as $pid) {
        $stmt = $pdo->prepare("INSERT INTO inventario_almacen (almacen_id, producto_id, stock_cajas, stock_fisico, stock_reservado, stock_disponible)
            VALUES (?, ?, 250, 250, 0, 250)
            ON DUPLICATE KEY UPDATE stock_fisico = VALUES(stock_fisico), stock_disponible = VALUES(stock_disponible)");
        $stmt->execute([$aid, $pid]);
    }
}

echo "Inventario actualizado y poblado con éxito.\n";
