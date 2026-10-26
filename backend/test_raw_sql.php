<?php
define('BASEPATH', '1');
require_once 'application/config/database.php';
$db_config = $db['default'];

$mysqli = new mysqli($db_config['hostname'], $db_config['username'], $db_config['password'], $db_config['database']);
if ($mysqli->connect_error) die("DB error: " . $mysqli->connect_error);

// Simular el inicio de transacción
$mysqli->autocommit(FALSE);

echo "1. Insertando en ventas...\n";
$sql1 = "INSERT INTO ventas (cliente_id, usuario_id, proforma_id, estado, tipo_pago, costo_transporte, subtotal, total, caja_sesion_id, ciudad_id)
VALUES (4, 1, NULL, 'VENDIDO', 'EFECTIVO', 0.00, 604.80, 604.80, NULL, 1)";

if (!$mysqli->query($sql1)) {
    echo "ERROR en ventas: " . $mysqli->error . "\n";
    $mysqli->rollback();
    exit;
}

$venta_id = $mysqli->insert_id;
echo "venta_id creado: $venta_id\n";

echo "2. Insertando en venta_detalles...\n";
$sql2 = "INSERT INTO venta_detalles (venta_id, producto_id, almacen_id, cajas_solicitadas, m2_reales, precio_unitario_caja, subtotal)
VALUES ($venta_id, 25, 1, 3, 4.32, 201.60, 604.80)";

if (!$mysqli->query($sql2)) {
    echo "ERROR en venta_detalles: " . $mysqli->error . "\n";
    $mysqli->rollback();
    exit;
}

echo "3. Descontando stock en inventario_almacen...\n";
$sql3 = "UPDATE inventario_almacen SET stock_fisico = stock_fisico - 3, stock_disponible = stock_disponible - 3 WHERE almacen_id = 1 AND producto_id = 25";
if (!$mysqli->query($sql3)) {
    echo "ERROR en inventario_almacen: " . $mysqli->error . "\n";
    $mysqli->rollback();
    exit;
}

echo "4. Insertando en venta_documentos...\n";
$sql4 = "INSERT INTO venta_documentos (venta_id, cliente_id, cliente_nombre, usuario_id, usuario_nombre, almacen_id, almacen_nombre, metodo_pago, tiene_transporte, tipo_transporte, subtotal, costo_transporte, total, anulado, fecha_hora)
VALUES ($venta_id, 4, 'Cliente Prueba', 1, 'Vendedor Prueba', 1, 'Almacen 1', 'EFECTIVO', 0, 'CLIENTE_RECOGE', 604.80, 0.00, 604.80, 0, NOW())";

if (!$mysqli->query($sql4)) {
    echo "ERROR en venta_documentos: " . $mysqli->error . "\n";
    $mysqli->rollback();
    exit;
}

$venta_doc_id = $mysqli->insert_id;
echo "venta_documento_id creado: $venta_doc_id\n";

echo "5. Insertando en venta_transporte...\n";
$sql5 = "INSERT INTO venta_transporte (venta_documento_id, venta_id, tipo_transporte, monto_transporte, nombre_empresa_chofer, placa_vehiculo, telefono_contacto, fecha_registro)
VALUES ($venta_doc_id, $venta_id, 'CLIENTE_RECOGE', 0.00, NULL, NULL, NULL, NOW())";

if (!$mysqli->query($sql5)) {
    echo "ERROR en venta_transporte: " . $mysqli->error . "\n";
    $mysqli->rollback();
    exit;
}

echo "6. Insertando en venta_documento_detalles...\n";
$sql6 = "INSERT INTO venta_documento_detalles (venta_documento_id, venta_id, producto_id, producto_codigo, producto_nombre, almacen_id, inventario_almacen_id, cajas_solicitadas, m2_reales, precio_unitario_caja, precio_unitario_m2, subtotal, fecha_hora)
VALUES ($venta_doc_id, $venta_id, 25, 'JCM63841D', 'Marea Silver Lux', 1, NULL, 3, 4.32, 201.60, 140.00, 604.80, NOW())";

if (!$mysqli->query($sql6)) {
    echo "ERROR en venta_documento_detalles: " . $mysqli->error . "\n";
    $mysqli->rollback();
    exit;
}

echo "TRANSACCION EXITOSA. HACIENDO ROLLBACK PARA NO ALTERAR PRODUCCION...\n";
$mysqli->rollback();
echo "PRUEBA COMPLETADA SIN ERRORES EN LAS TABLAS SQL.\n";
