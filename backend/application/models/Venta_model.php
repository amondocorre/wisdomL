<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Venta_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    /**
     * Crear el registro de una venta y sus detalles bajo transacciones.
     */
    public function registrar_venta($venta_data, $detalles, $requiere_autorizacion, $precio_propuesto_total, $notas_autorizacion) {
        $this->db->trans_start();

        // 1. Insertar Cabecera de Venta (Filtrando únicamente las columnas existentes de 'ventas')
        $this->load->model('Caja_model');
        $caja_activa = $this->Caja_model->obtener_caja_activa($venta_data['usuario_id']);
        if ($caja_activa) {
            $venta_data['caja_sesion_id'] = $caja_activa['id'];
        }

        $ventas_columns = ['cliente_id', 'usuario_id', 'proforma_id', 'estado', 'tipo_pago', 'costo_transporte', 'subtotal', 'total', 'caja_sesion_id', 'ciudad_id', 'sucursal_id'];
        if (!isset($venta_data['sucursal_id']) && isset($venta_data['ciudad_id'])) {
            $venta_data['sucursal_id'] = $venta_data['ciudad_id'];
        }
        if (!isset($venta_data['ciudad_id']) && isset($venta_data['sucursal_id'])) {
            $venta_data['ciudad_id'] = $venta_data['sucursal_id'];
        }
        $venta_data_db = array_intersect_key($venta_data, array_flip($ventas_columns));

        $this->db->insert('ventas', $venta_data_db);
        $venta_id = $this->db->insert_id();

        // 2. Insertar Detalles de Venta y Descontar Inventario
        $this->load->model('Inventario_model');
        $primer_almacen_id = !empty($detalles) ? $detalles[0]['almacen_id'] : null;

        $venta_detalles_columns = ['venta_id', 'producto_id', 'almacen_id', 'cajas_solicitadas', 'm2_reales', 'precio_unitario_caja', 'subtotal'];

        foreach ($detalles as &$detalle) {
            $detalle['venta_id'] = $venta_id;
            $det_db = array_intersect_key($detalle, array_flip($venta_detalles_columns));
            $this->db->insert('venta_detalles', $det_db);

            $inventario_almacen_id = null;

            // Reservar inventario inmediatamente si NO requiere autorización (Estado: VENDIDO)
            // El material continúa físicamente en el galpón y se acumula en 'stock_reservado'
            if (!$requiere_autorizacion) {
                $cantidad_a_reservar = isset($detalle['m2_reales']) ? (float)$detalle['m2_reales'] : (float)$detalle['cajas_solicitadas'];
                $cajas_log = isset($detalle['cajas_solicitadas']) ? (float)$detalle['cajas_solicitadas'] : null;

                $reserva = $this->Inventario_model->reservar_stock(
                    $detalle['almacen_id'], 
                    $detalle['producto_id'], 
                    $cantidad_a_reservar,
                    $cajas_log
                );
                if (!$reserva) {
                    $this->db->trans_rollback();
                    return ['status' => false, 'message' => "Stock insuficiente en el almacén para el producto ID: " . $detalle['producto_id']];
                }
                
                // Obtener ID o referencia del registro de inventario_almacen para la relación
                $invRow = $this->db->get_where('inventario_almacen', [
                    'almacen_id' => $detalle['almacen_id'],
                    'producto_id' => $detalle['producto_id']
                ])->row_array();
                if ($invRow && isset($invRow['id'])) {
                    $inventario_almacen_id = $invRow['id'];
                } else {
                    $inventario_almacen_id = null;
                }
            }

            $detalle['inventario_almacen_id'] = $inventario_almacen_id;
        }

        // 3. Obtener nombres de relaciones para la tabla venta_documentos
        $cliente = $this->db->get_where('clientes', ['id' => $venta_data['cliente_id']])->row_array();
        $cliente_nombre = 'Cliente Desconocido';
        if ($cliente) {
            if ($cliente['tipo_cliente'] === 'COMERCIAL') {
                $cliente_nombre = $cliente['comercial_nombre_comercial'] ?: ($cliente['comercial_propietario_nombre'] . ' ' . $cliente['comercial_propietario_apellido']);
            } else if ($cliente['tipo_cliente'] === 'CONSTRUCTORA') {
                $cliente_nombre = $cliente['constructora_nombre'];
            } else {
                $cliente_nombre = trim($cliente['final_nombre_completo'] . ' ' . $cliente['final_apellidos']);
            }
        }

        $usuario = $this->db->get_where('usuarios', ['id' => $venta_data['usuario_id']])->row_array();
        $usuario_nombre = $usuario ? $usuario['nombre'] : 'Usuario POS';

        $almacen_nombre = 'Almacén Principal';
        if ($primer_almacen_id) {
            $alm = $this->db->get_where('almacenes', ['id' => $primer_almacen_id])->row_array();
            if ($alm) {
                $almacen_nombre = $alm['nombre'];
            }
        }

        $tipo_transporte = isset($venta_data['tipo_transporte']) ? $venta_data['tipo_transporte'] : 'CLIENTE_RECOGE';
        $tiene_transporte = isset($venta_data['tiene_transporte']) ? $venta_data['tiene_transporte'] : 0;
        $monto_transporte = isset($venta_data['monto_transporte']) ? (float)$venta_data['monto_transporte'] : 0.00;

        $venta_documento_id = null;

        // 4. Crear registro en tabla venta_documentos y venta_transporte SOLO si NO requiere autorización (venta directa)
        if (!$requiere_autorizacion) {
            $doc_data = [
                'venta_id' => $venta_id,
                'cliente_id' => $venta_data['cliente_id'],
                'cliente_nombre' => $cliente_nombre,
                'usuario_id' => $venta_data['usuario_id'],
                'usuario_nombre' => $usuario_nombre,
                'almacen_id' => $primer_almacen_id,
                'almacen_nombre' => $almacen_nombre,
                'metodo_pago' => isset($venta_data['tipo_pago']) ? $venta_data['tipo_pago'] : 'EFECTIVO',
                'tiene_transporte' => $tiene_transporte,
                'tipo_transporte' => $tipo_transporte,
                'subtotal' => $venta_data['subtotal'],
                'costo_transporte' => $venta_data['costo_transporte'],
                'total' => $venta_data['total'],
                'anulado' => 0,
                'fecha_hora' => date('Y-m-d H:i:s')
            ];
            $this->db->insert('venta_documentos', $doc_data);
            $venta_documento_id = $this->db->insert_id();

            // 4.1 Insertar registro en tabla venta_transporte
            $transporte_data = [
                'venta_documento_id' => $venta_documento_id,
                'venta_id' => $venta_id,
                'tipo_transporte' => $tipo_transporte,
                'monto_transporte' => $monto_transporte,
                'nombre_empresa_chofer' => isset($venta_data['nombre_empresa_chofer']) ? $venta_data['nombre_empresa_chofer'] : null,
                'placa_vehiculo' => isset($venta_data['placa_vehiculo']) ? $venta_data['placa_vehiculo'] : null,
                'telefono_contacto' => isset($venta_data['telefono_contacto']) ? $venta_data['telefono_contacto'] : null,
                'fecha_registro' => date('Y-m-d H:i:s')
            ];
            $this->db->insert('venta_transporte', $transporte_data);
        }

        // 5. Crear registros en tabla venta_documento_detalles SOLO si se generó el documento de venta
        if (!$requiere_autorizacion && $venta_documento_id) {
            foreach ($detalles as $det) {
                $this->db->select('p.*, cat_u.abreviacion as unidad_base_abrev');
                $this->db->from('productos p');
                $this->db->join('cat_unidad_base cat_u', 'cat_u.id = p.unidad_base_id', 'left');
                $this->db->where('p.id', $det['producto_id']);
                $prod = $this->db->get()->row_array();

                $prod_codigo = $prod ? $prod['codigo'] : 'SKU-N/A';
                $prod_nombre = $prod ? $prod['nombre'] : 'Producto Varios';
                $m2_por_caja = $prod ? (float)$prod['m2_por_caja'] : 1.44;
                $piezas_por_caja = ($prod && (int)$prod['piezas_por_caja'] > 0) ? (int)$prod['piezas_por_caja'] : 1;

                $abrevUpper = strtoupper(trim($prod['unidad_base_abrev'] ?? ''));
                $esPorPieza = ($abrevUpper === 'PZA' || $abrevUpper === 'PIEZA' || $abrevUpper === 'BLS' || $abrevUpper === 'BOLSA') 
                              || ($abrevUpper !== 'M2' && $abrevUpper !== 'M²' && $m2_por_caja <= 0);

                if ($esPorPieza) {
                    // $det['precio_unitario_caja'] almacena el precio unitario por pieza
                    $precio_unitario_pieza = (float)$det['precio_unitario_caja'];
                    $precio_unitario_caja = $precio_unitario_pieza * $piezas_por_caja;
                    $precio_unit_base = $precio_unitario_pieza;
                } else {
                    $precio_unitario_caja = (float)$det['precio_unitario_caja'];
                    $precio_unit_base = ($m2_por_caja > 0) ? ($precio_unitario_caja / $m2_por_caja) : $precio_unitario_caja;
                }

                $doc_det_data = [
                    'venta_documento_id' => $venta_documento_id,
                    'venta_id' => $venta_id,
                    'producto_id' => $det['producto_id'],
                    'producto_codigo' => $prod_codigo,
                    'producto_nombre' => $prod_nombre,
                    'almacen_id' => $det['almacen_id'],
                    'inventario_almacen_id' => isset($det['inventario_almacen_id']) ? $det['inventario_almacen_id'] : null,
                    'cajas_solicitadas' => $det['cajas_solicitadas'],
                    'm2_reales' => $det['m2_reales'],
                    'precio_unitario_caja' => $precio_unitario_caja,
                    'precio_unitario_m2' => round($precio_unit_base, 2),
                    'subtotal' => $det['subtotal'],
                    'fecha_hora' => date('Y-m-d H:i:s')
                ];
                $this->db->insert('venta_documento_detalles', $doc_det_data);
            }
        }

        // 6. Crear solicitud de autorización si el precio está por debajo del escalonado
        if ($requiere_autorizacion) {
            $auth_data = [
                'venta_id' => $venta_id,
                'estado' => 'PENDIENTE',
                'precio_propuesto_total' => $precio_propuesto_total,
                'notas_solicitud' => $notas_autorizacion
            ];
            $this->db->insert('autorizaciones_precio', $auth_data);
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            $db_error = $this->db->error();
            $msg_error = (!empty($db_error) && !empty($db_error['message'])) ? $db_error['message'] : "Error interno al procesar la transacción en la base de datos.";
            return ['status' => false, 'message' => $msg_error];
        }

        return ['status' => true, 'venta_id' => $venta_id, 'venta_documento_id' => $venta_documento_id];
    }

    /**
     * Obtener una venta por ID con detalles.
     */
    public function obtener_venta($venta_id) {
        $venta = $this->db->get_where('ventas', ['id' => $venta_id])->row_array();
        if ($venta) {
            $venta['detalles'] = $this->db->get_where('venta_detalles', ['venta_id' => $venta_id])->result_array();
        }
        return $venta;
    }
}
