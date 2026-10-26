<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

ob_start();
require_once 'index.php';
$output = ob_get_clean();

$CI =& get_instance();
$CI->load->model('Venta_model');
$CI->load->model('Caja_model');

// Datos de prueba idénticos al request del usuario
$usuario_id = 1; // o buscar de usuario activo
$cliente_id = 4;
$producto_id = 25;
$almacen_id = 1;

$caja = $CI->Caja_model->obtener_caja_activa($usuario_id);
if (!$caja) {
    echo "NO HAY CAJA ABIERTA PARA USUARIO 1. Abriendo caja de prueba...\n";
    $CI->Caja_model->abrir_caja($usuario_id, 100);
}

$venta_data = [
    'cliente_id' => 4,
    'usuario_id' => $usuario_id,
    'sucursal_id' => 1,
    'proforma_id' => null,
    'estado' => 'VENDIDO',
    'tipo_pago' => 'EFECTIVO',
    'costo_transporte' => 0.00,
    'monto_transporte' => 0.00,
    'tipo_transporte' => 'CLIENTE_RECOGE',
    'tiene_transporte' => 0,
    'subtotal' => 201.60,
    'total' => 201.60
];

$detalles = [
    [
        'producto_id' => 25,
        'almacen_id' => 1,
        'm2_solicitados' => 4.32,
        'cajas_solicitadas' => 3,
        'm2_reales' => 4.32,
        'precio_unitario_caja' => 201.6,
        'subtotal' => 604.8
    ]
];

echo "EJECUTANDO registrar_venta...\n";
$res = $CI->Venta_model->registrar_venta($venta_data, $detalles, false, 201.60, '');
print_r($res);

if ($CI->db->error()) {
    print_r($CI->db->error());
}
