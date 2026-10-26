<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ventas extends CI_Controller {

    public function __construct() {
        parent::__construct();
        
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, Origin, Accept");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit(0);
        }

        $this->load->model('Venta_model');
        $this->load->model('Inventario_model');
        $this->load->library('JWT');
        
        // Encabezado obligatorio de API REST JSON
        $this->output->set_content_type('application/json');
    }

    /**
     * Endpoint para crear una nueva venta
     * POST /api/ventas/crear_venta
     */
    public function crear_venta() {
        // Validar Token JWT
        $headers = $this->input->request_headers();
        $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
        $usuario = JWT::decode($token);

        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado o sesión expirada']);
            return;
        }

        // Validar caja abierta
        $this->load->model('Caja_model');
        $caja_activa = $this->Caja_model->obtener_caja_activa($usuario['id']);
        if (!$caja_activa) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'Debes abrir caja antes de realizar una venta.']);
            return;
        }

        // Obtener datos del cuerpo de la petición (JSON)
        $input = json_decode(trim(file_get_contents('php://input')), true);

        if (empty($input['cliente_id']) || empty($input['items'])) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'Debe seleccionar un cliente y al menos un producto']);
            return;
        }

        $cliente_id = $input['cliente_id'];
        $sucursal_id = isset($input['sucursal_id']) ? $input['sucursal_id'] : (isset($input['ciudad_id']) ? $input['ciudad_id'] : 1);
        $proforma_id = isset($input['proforma_id']) ? $input['proforma_id'] : null;
        $costo_transporte = isset($input['costo_transporte']) ? (float)$input['costo_transporte'] : 0.00;
        $tipo_pago = isset($input['tipo_pago']) ? $input['tipo_pago'] : 'EFECTIVO';
        $notas_autorizacion = isset($input['notas_autorizacion']) ? $input['notas_autorizacion'] : '';

        $detalles = [];
        $subtotal_venta = 0.00;
        $requiere_autorizacion = false;
        
        // Iterar los ítems enviados (M² solicitados)
        foreach ($input['items'] as $item) {
            $producto_id = $item['producto_id'];
            $almacen_id = $item['almacen_id'];
            $m2_solicitados = (float)$item['m2_solicitados'];
            $precio_ofrecido = (float)$item['precio_unitario_caja']; // Precio acordado (por caja o por pieza según producto)

            // Obtener el producto con su unidad base
            $this->db->select('p.*, cat_u.abreviacion as unidad_base_abrev');
            $this->db->from('productos p');
            $this->db->join('cat_unidad_base cat_u', 'cat_u.id = p.unidad_base_id', 'left');
            $this->db->where('p.id', $producto_id);
            $producto = $this->db->get()->row();

            if (!$producto) {
                $this->output->set_status_header(404);
                echo json_encode(['error' => "Producto con ID $producto_id no encontrado"]);
                return;
            }

            $abrevUpper = strtoupper(trim($producto->unidad_base_abrev ?? ''));
            $esPorPieza = ($abrevUpper === 'PZA' || $abrevUpper === 'PIEZA' || $abrevUpper === 'BLS' || $abrevUpper === 'BOLSA') 
                          || ($abrevUpper !== 'M2' && $abrevUpper !== 'M²' && (float)$producto->m2_por_caja <= 0);

            $m2_caja = (float)($producto->m2_por_caja > 0 ? $producto->m2_por_caja : 1.0);
            $piezas_caja = (int)($producto->piezas_por_caja > 0 ? $producto->piezas_por_caja : 1);

            $esVentaPorPiezaEspecial = (isset($producto->venta_por_pieza) && $producto->venta_por_pieza == 1);
            $se_fracciona = (isset($producto->se_fracciona) && $producto->se_fracciona == 1);

            if ($esPorPieza) {
                // Para productos por PIEZA:
                // $m2_solicitados representa la cantidad física de piezas solicitadas
                $piezas_solicitadas = (float)$m2_solicitados;
                if ($piezas_solicitadas < 1) $piezas_solicitadas = 1;

                $cajas_solicitadas = (int) ceil($piezas_solicitadas / $piezas_caja);
                if ($cajas_solicitadas < 1) $cajas_solicitadas = 1;

                // Cantidad exacta a descontar en inventario = piezas
                $m2_reales = $piezas_solicitadas;

                // El subtotal es: piezas * precio_unitario_pieza (o si se envió precio caja: cajas * precio_caja)
                // Si el precio ofrecido es por pieza:
                $subtotal_item = $piezas_solicitadas * $precio_ofrecido;
            } elseif ($esVentaPorPiezaEspecial) {
                // Productos especiales que se venden por PIEZA pero su inventario es M2
                // $m2_solicitados enviado por POS es el m2 real fraccionado exacto
                $m2_reales = $m2_solicitados;
                $m2_por_pieza = $m2_caja / $piezas_caja;
                $piezas_solicitadas = round($m2_solicitados / $m2_por_pieza);
                
                $cajas_solicitadas = (int) floor($piezas_solicitadas / $piezas_caja);
                
                // El subtotal = piezas * precio por pieza (el POS lo envió en precio_ofrecido)
                $subtotal_item = $piezas_solicitadas * $precio_ofrecido;
            } elseif ($se_fracciona) {
                // Productos de M2 que permiten fraccionar (no obligan caja cerrada)
                // $m2_solicitados es exactamente la cantidad de M2
                $m2_reales = $m2_solicitados;
                $cajas_solicitadas = (int) floor($m2_solicitados / $m2_caja);
                
                $subtotal_item = ($m2_solicitados / $m2_caja) * $precio_ofrecido;
            } else {
                // Para productos por M² (Porcelanatos estándar):
                // REGLA DE REDONDEO: Cajas necesarias usando el ENTERO INMEDIATO SUPERIOR
                $cajas_solicitadas = (int) ceil($m2_solicitados / $m2_caja);
                if ($cajas_solicitadas < 1) {
                    $cajas_solicitadas = 1;
                }
                
                // Los m² reales a descontar corresponden a las cajas completas a despachar
                $m2_reales = round($cajas_solicitadas * $m2_caja, 4);
                $subtotal_item = $cajas_solicitadas * $precio_ofrecido;
            }

            // Consultar matriz escalonada de precios
            $precio_sugerido = $this->Inventario_model->obtener_precio_escala($producto_id, $cajas_solicitadas);

            // Validar si el precio ofrecido es menor que el de la matriz
            if ($precio_ofrecido < $precio_sugerido) {
                $requiere_autorizacion = true;
            }

            // Verificar stock en tiempo real en la unidad de medida base (m2 o piezas)
            $stock_disponible = $this->Inventario_model->obtener_stock($almacen_id, $producto_id);
            
            // Si el almacén enviado no tiene stock suficiente, buscar si otro almacén de la misma ciudad lo tiene
            if (($stock_disponible + 0.0001) < $m2_reales && !empty($sucursal_id)) {
                $this->db->select('ia.almacen_id, ia.stock_disponible');
                $this->db->from('inventario_almacen ia');
                $this->db->join('almacenes a', 'a.id = ia.almacen_id');
                $this->db->where('ia.producto_id', $producto_id);
                $this->db->where('a.ciudad_id', $sucursal_id);
                $this->db->where('ia.stock_disponible >=', $m2_reales);
                $this->db->order_by('ia.stock_disponible', 'DESC');
                $almacen_alt = $this->db->get()->row_array();

                if ($almacen_alt) {
                    $almacen_id = (int)$almacen_alt['almacen_id'];
                    $stock_disponible = (float)$almacen_alt['stock_disponible'];
                }
            }

            if (($stock_disponible + 0.0001) < $m2_reales) {
                $unidad_txt = $esPorPieza ? 'piezas' : 'm²';
                $this->output->set_status_header(400);
                echo json_encode(['error' => "Stock insuficiente para el producto '{$producto->nombre}' en el almacén. Disponible: " . round($stock_disponible, 2) . " {$unidad_txt}, Solicitado: " . round($m2_reales, 2) . " {$unidad_txt}"]);
                return;
            }

            $subtotal_venta += $subtotal_item;

            $detalles[] = [
                'producto_id' => $producto_id,
                'almacen_id' => $almacen_id,
                'cajas_solicitadas' => $cajas_solicitadas,
                'm2_reales' => $m2_reales,
                'precio_unitario_caja' => $precio_ofrecido,
                'subtotal' => $subtotal_item
            ];
        }

        $tipo_transporte = isset($input['tipo_transporte']) ? $input['tipo_transporte'] : 'CLIENTE_RECOGE';
        $tiene_transporte = ($tipo_transporte !== 'CLIENTE_RECOGE') ? 1 : 0;
        $monto_transporte = ($tipo_transporte === 'TRANSPORTE_EXTERNO') ? $costo_transporte : 0.00;

        $total_venta = $subtotal_venta + $costo_transporte;
        $estado = $requiere_autorizacion ? 'PENDIENTE_AUTORIZACION' : 'VENDIDO';

        $venta_data = [
            'cliente_id' => $cliente_id,
            'usuario_id' => $usuario['id'],
            'ciudad_id' => $sucursal_id,
            'proforma_id' => $proforma_id,
            'estado' => $estado,
            'tipo_pago' => $tipo_pago,
            'costo_transporte' => $costo_transporte,
            'monto_transporte' => $monto_transporte,
            'tipo_transporte' => $tipo_transporte,
            'tiene_transporte' => $tiene_transporte,
            'nombre_empresa_chofer' => isset($input['nombre_empresa_chofer']) ? $input['nombre_empresa_chofer'] : null,
            'placa_vehiculo' => isset($input['placa_vehiculo']) ? $input['placa_vehiculo'] : null,
            'telefono_contacto' => isset($input['telefono_contacto']) ? $input['telefono_contacto'] : null,
            'subtotal' => $subtotal_venta,
            'total' => $total_venta
        ];

        // Registrar la venta en la Base de Datos
        $resultado = $this->Venta_model->registrar_venta(
            $venta_data, 
            $detalles, 
            $requiere_autorizacion, 
            $total_venta, 
            $notas_autorizacion
        );

        if (!$resultado['status']) {
            $this->output->set_status_header(500);
            echo json_encode(['error' => $resultado['message']]);
            return;
        }

        echo json_encode([
            'status' => 'success',
            'venta_id' => $resultado['venta_id'],
            'venta_documento_id' => isset($resultado['venta_documento_id']) ? $resultado['venta_documento_id'] : null,
            'estado' => $estado,
            'total' => $total_venta,
            'm2_despachados_detalles' => $detalles,
            'requiere_autorizacion' => $requiere_autorizacion
        ]);
    }

    /**
     * Listar las ventas que el vendedor ha solicitado autorización
     * GET /api/ventas/mis_solicitudes_autorizacion
     */
    public function mis_solicitudes_autorizacion() {
        $headers = $this->input->request_headers();
        $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
        $usuario = JWT::decode($token);

        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado o sesión expirada']);
            return;
        }

        $this->db->select('v.id, v.id as venta_id, v.total, v.subtotal, v.costo_transporte, v.estado, v.tipo_pago, v.fecha_venta, c.id as cliente_id, c.tipo_cliente, c.comercial_nombre_comercial, c.constructora_nombre, c.final_nombre_completo, ap.id as autorizacion_id, ap.estado as estado_autorizacion, ap.precio_propuesto_total, ap.notas_solicitud, ap.notas_resolucion, ap.fecha_resolucion, u_aut.nombre as autorizador_nombre');
        $this->db->from('ventas v');
        $this->db->join('clientes c', 'v.cliente_id = c.id');
        $this->db->join('autorizaciones_precio ap', 'v.id = ap.venta_id');
        $this->db->join('usuarios u_aut', 'u_aut.id = ap.usuario_autorizador_id', 'left');
        
        // Si no es admin, filtrar solo sus propias solicitudes
        if ($usuario['rol'] === 'VENDEDOR') {
            $this->db->where('v.usuario_id', $usuario['id']);
        }
        
        $this->db->order_by('ap.id', 'DESC');
        $solicitudes = $this->db->get()->result_array();

        // Cargar detalles de productos para cada venta
        foreach ($solicitudes as &$sol) {
            $this->db->select('vd.*, p.nombre, p.codigo, p.m2_por_caja, p.imagen_url');
            $this->db->from('venta_detalles vd');
            $this->db->join('productos p', 'p.id = vd.producto_id');
            $this->db->where('vd.venta_id', $sol['venta_id']);
            $sol['items'] = $this->db->get()->result_array();
        }

        echo json_encode($solicitudes);
    }

    /**
     * Procesar cobro final de una venta aprobada por Gerencia
     * POST /api/ventas/procesar_venta_aprobada
     */
    public function procesar_venta_aprobada() {
        $headers = $this->input->request_headers();
        $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
        $usuario = JWT::decode($token);

        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        // Validar caja abierta
        $this->load->model('Caja_model');
        $caja_activa = $this->Caja_model->obtener_caja_activa($usuario['id']);
        if (!$caja_activa) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'Debes abrir caja antes de procesar el cobro de la venta.']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);
        $venta_id = isset($input['venta_id']) ? (int)$input['venta_id'] : null;
        $tipo_pago = isset($input['tipo_pago']) ? $input['tipo_pago'] : 'EFECTIVO';
        $tipo_transporte = isset($input['tipo_transporte']) ? $input['tipo_transporte'] : 'CLIENTE_RECOGE';
        $costo_transporte = isset($input['costo_transporte']) ? (float)$input['costo_transporte'] : 0.00;

        if (!$venta_id) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'ID de venta requerido']);
            return;
        }

        $venta = $this->db->get_where('ventas', ['id' => $venta_id])->row_array();
        if (!$venta) {
            $this->output->set_status_header(404);
            echo json_encode(['error' => 'Venta no encontrada']);
            return;
        }

        // Verificar autorización aprobada
        $auth = $this->db->get_where('autorizaciones_precio', ['venta_id' => $venta_id, 'estado' => 'APROBADO'])->row_array();
        if (!$auth && $venta['estado'] !== 'VENDIDO') {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'La venta aún no ha sido aprobada por Gerencia.']);
            return;
        }

        $this->db->trans_start();

        $total_nuevo = $venta['subtotal'] + $costo_transporte;

        // Actualizar datos de la venta y asignarle la sesión de caja del vendedor que realiza el cobro
        // 1. Actualizar datos de la venta y asignarle la sesión de caja del vendedor que realiza el cobro
        $this->db->where('id', $venta_id);
        $this->db->update('ventas', [
            'estado' => 'VENDIDO',
            'tipo_pago' => $tipo_pago,
            'costo_transporte' => $costo_transporte,
            'total' => $total_nuevo,
            'caja_sesion_id' => $caja_activa['id']
        ]);

        // 2. Verificar si ya existe documento de recibo, o crearlo ahora que fue aprobada
        $doc = $this->db->get_where('venta_documentos', ['venta_id' => $venta_id])->row_array();

        if ($doc) {
            // Actualizar documento existente
            $this->db->where('id', $doc['id']);
            $this->db->update('venta_documentos', [
                'metodo_pago' => $tipo_pago,
                'costo_transporte' => $costo_transporte,
                'total' => $total_nuevo,
                'tipo_transporte' => $tipo_transporte,
                'tiene_transporte' => ($tipo_transporte !== 'CLIENTE_RECOGE') ? 1 : 0
            ]);
            $venta_documento_id = $doc['id'];
        } else {
            // Crear el documento oficial de venta
            $cliente = $this->db->get_where('clientes', ['id' => $venta['cliente_id']])->row_array();
            $cliente_nombre = 'Consumidor Final';
            if ($cliente) {
                if ($cliente['tipo_cliente'] === 'COMERCIAL') {
                    $cliente_nombre = $cliente['comercial_nombre_comercial'] ?: ($cliente['comercial_propietario_nombre'] . ' ' . $cliente['comercial_propietario_apellido']);
                } else if ($cliente['tipo_cliente'] === 'CONSTRUCTORA') {
                    $cliente_nombre = $cliente['constructora_nombre'];
                } else {
                    $cliente_nombre = trim(($cliente['final_nombre_completo'] ?? '') . ' ' . ($cliente['final_apellidos'] ?? ''));
                }
            }

            $usuario_db = $this->db->get_where('usuarios', ['id' => $usuario['id']])->row_array();
            $usuario_nombre = $usuario_db ? $usuario_db['nombre'] : 'Usuario POS';

            $detalles_venta = $this->db->get_where('venta_detalles', ['venta_id' => $venta_id])->result_array();
            $primer_almacen_id = !empty($detalles_venta) ? $detalles_venta[0]['almacen_id'] : null;
            $almacen_nombre = 'Almacén Principal';
            if ($primer_almacen_id) {
                $alm = $this->db->get_where('almacenes', ['id' => $primer_almacen_id])->row_array();
                if ($alm) $almacen_nombre = $alm['nombre'];
            }

            $doc_data = [
                'venta_id' => $venta_id,
                'cliente_id' => $venta['cliente_id'],
                'cliente_nombre' => $cliente_nombre,
                'usuario_id' => $usuario['id'],
                'usuario_nombre' => $usuario_nombre,
                'almacen_id' => $primer_almacen_id,
                'almacen_nombre' => $almacen_nombre,
                'metodo_pago' => $tipo_pago,
                'tiene_transporte' => ($tipo_transporte !== 'CLIENTE_RECOGE') ? 1 : 0,
                'tipo_transporte' => $tipo_transporte,
                'subtotal' => $venta['subtotal'],
                'costo_transporte' => $costo_transporte,
                'total' => $total_nuevo,
                'anulado' => 0,
                'fecha_hora' => date('Y-m-d H:i:s')
            ];
            $this->db->insert('venta_documentos', $doc_data);
            $venta_documento_id = $this->db->insert_id();

            // Insertar detalles del documento
            foreach ($detalles_venta as $v_det) {
                $this->db->select('p.*, cat_u.abreviacion as unidad_base_abrev');
                $this->db->from('productos p');
                $this->db->join('cat_unidad_base cat_u', 'cat_u.id = p.unidad_base_id', 'left');
                $this->db->where('p.id', $v_det['producto_id']);
                $prod = $this->db->get()->row_array();

                $prod_codigo = $prod ? $prod['codigo'] : 'SKU-N/A';
                $prod_nombre = $prod ? $prod['nombre'] : 'Producto Varios';
                $m2_por_caja = $prod ? (float)$prod['m2_por_caja'] : 1.44;
                $piezas_por_caja = ($prod && (int)$prod['piezas_por_caja'] > 0) ? (int)$prod['piezas_por_caja'] : 1;

                $abrevUpper = strtoupper(trim($prod['unidad_base_abrev'] ?? ''));
                $esPorPieza = ($abrevUpper === 'PZA' || $abrevUpper === 'PIEZA' || $abrevUpper === 'BLS' || $abrevUpper === 'BOLSA') 
                              || ($abrevUpper !== 'M2' && $abrevUpper !== 'M²' && $m2_por_caja <= 0);

                if ($esPorPieza) {
                    $precio_unitario_pieza = (float)$v_det['precio_unitario_caja'];
                    $precio_unitario_caja = $precio_unitario_pieza * $piezas_por_caja;
                    $precio_unit_base = $precio_unitario_pieza;
                } else {
                    $precio_unitario_caja = (float)$v_det['precio_unitario_caja'];
                    $precio_unit_base = ($m2_por_caja > 0) ? ($precio_unitario_caja / $m2_por_caja) : $precio_unitario_caja;
                }

                $doc_det_data = [
                    'venta_documento_id' => $venta_documento_id,
                    'venta_id' => $venta_id,
                    'producto_id' => $v_det['producto_id'],
                    'producto_codigo' => $prod_codigo,
                    'producto_nombre' => $prod_nombre,
                    'almacen_id' => $v_det['almacen_id'],
                    'inventario_almacen_id' => isset($v_det['inventario_almacen_id']) ? $v_det['inventario_almacen_id'] : null,
                    'cajas_solicitadas' => $v_det['cajas_solicitadas'],
                    'm2_reales' => $v_det['m2_reales'],
                    'precio_unitario_caja' => $precio_unitario_caja,
                    'precio_unitario_m2' => round($precio_unit_base, 2),
                    'subtotal' => $v_det['subtotal'],
                    'fecha_hora' => date('Y-m-d H:i:s')
                ];
                $this->db->insert('venta_documento_detalles', $doc_det_data);
            }
        }

        // 3. Registrar o actualizar transporte
        $trans_exist = $this->db->get_where('venta_transporte', ['venta_id' => $venta_id])->row_array();
        if ($trans_exist) {
            $this->db->where('id', $trans_exist['id']);
            $this->db->update('venta_transporte', [
                'venta_documento_id' => $venta_documento_id,
                'tipo_transporte' => $tipo_transporte,
                'monto_transporte' => ($tipo_transporte === 'TRANSPORTE_EXTERNO') ? $costo_transporte : 0.00,
                'nombre_empresa_chofer' => isset($input['nombre_empresa_chofer']) ? $input['nombre_empresa_chofer'] : null,
                'placa_vehiculo' => isset($input['placa_vehiculo']) ? $input['placa_vehiculo'] : null,
                'telefono_contacto' => isset($input['telefono_contacto']) ? $input['telefono_contacto'] : null
            ]);
        } else {
            $transporte_data = [
                'venta_documento_id' => $venta_documento_id,
                'venta_id' => $venta_id,
                'tipo_transporte' => $tipo_transporte,
                'monto_transporte' => ($tipo_transporte === 'TRANSPORTE_EXTERNO') ? $costo_transporte : 0.00,
                'nombre_empresa_chofer' => isset($input['nombre_empresa_chofer']) ? $input['nombre_empresa_chofer'] : null,
                'placa_vehiculo' => isset($input['placa_vehiculo']) ? $input['placa_vehiculo'] : null,
                'telefono_contacto' => isset($input['telefono_contacto']) ? $input['telefono_contacto'] : null,
                'fecha_registro' => date('Y-m-d H:i:s')
            ];
            $this->db->insert('venta_transporte', $transporte_data);
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            $this->output->set_status_header(500);
            echo json_encode(['error' => 'Error al registrar el cobro de la venta.']);
            return;
        }

        echo json_encode([
            'status' => 'success',
            'mensaje' => 'Venta cobrada y facturada con éxito.',
            'venta_id' => $venta_id,
            'venta_documento_id' => $doc ? $doc['id'] : null
        ]);
    }
}

