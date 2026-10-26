<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Inventario extends CI_Controller {

    public function __construct() {
        parent::__construct();
        
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, Origin, Accept");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit(0);
        }

        $this->load->library('JWT');
        $this->output->set_content_type('application/json');
    }

    private function verificar_token() {
        $headers = $this->input->request_headers();
        $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
        return JWT::decode($token);
    }

    /**
     * GET /api/inventario/filtros_ubicacion
     * Devuelve los paises, ciudades y almacenes para poblar los selects en cascada
     */
    public function filtros_ubicacion() {
        if (!$this->verificar_token()) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $paises = $this->db->get('logistica_paises')->result_array();
        $ciudades = $this->db->get('ciudades')->result_array();
        $almacenes = $this->db->get('almacenes')->result_array();

        // Agrupar
        $resultado = [];
        foreach ($paises as $p) {
            $ciudades_pais = array_filter($ciudades, function($c) use ($p) {
                return $c['pais_id'] == $p['id'];
            });
            
            $ciudades_arr = [];
            foreach ($ciudades_pais as $cp) {
                $alms = array_filter($almacenes, function($a) use ($cp) {
                    return $a['ciudad_id'] == $cp['id'];
                });
                $cp['almacenes'] = array_values($alms);
                $ciudades_arr[] = $cp;
            }
            
            $p['ciudades'] = $ciudades_arr;
            $resultado[] = $p;
        }

        echo json_encode($resultado);
    }

    /**
     * GET /api/inventario/mis_almacenes
     * Devuelve los almacenes asignados al usuario en sesión
     */
    public function mis_almacenes() {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        // Si es VENDEDOR o tiene asignaciones específicas en usuario_almacen
        $this->db->select('ua.almacen_id, a.nombre, a.ciudad_id, c.nombre as ciudad_nombre');
        $this->db->from('usuario_almacen ua');
        $this->db->join('almacenes a', 'a.id = ua.almacen_id');
        $this->db->join('ciudades c', 'c.id = a.ciudad_id', 'left');
        $this->db->where('ua.usuario_id', $usuario['id']);
        $almacenes_asignados = $this->db->get()->result_array();

        // Si es ADMIN o no tiene almacenes específicos asignados, devolver todos los almacenes de sus ciudades
        if (empty($almacenes_asignados)) {
            $this->db->select('a.id as almacen_id, a.nombre, a.ciudad_id, c.nombre as ciudad_nombre');
            $this->db->from('almacenes a');
            $this->db->join('ciudades c', 'c.id = a.ciudad_id', 'left');
            $almacenes_asignados = $this->db->get()->result_array();
        }

        echo json_encode($almacenes_asignados);
    }

    /**
     * GET /api/inventario/stock
     * Listar inventario consolidado y con detalle por almacén
     */
    public function stock() {
        if (!$this->verificar_token()) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $pais_id = $this->input->get('pais_id');
        $ciudad_id = $this->input->get('ciudad_id');
        $almacen_id = $this->input->get('almacen_id');

        $todos = $this->input->get('todos');
        $catalogo = $this->input->get('catalogo');

        // 1. Obtener almacenes implicados en la búsqueda
        $this->db->select('a.id, a.nombre');
        $this->db->from('almacenes a');
        
        if (!empty($almacen_id)) {
            $this->db->where('a.id', (int)$almacen_id);
        } else if (!empty($ciudad_id)) {
            $this->db->where('a.ciudad_id', (int)$ciudad_id);
        } else if (!empty($pais_id)) {
            $this->db->join('ciudades c', 'c.id = a.ciudad_id');
            $this->db->where('c.pais_id', (int)$pais_id);
        } else if (!empty($todos) || !empty($catalogo)) {
            // Consolidar todos los almacenes de la empresa
        } else {
            // Sin filtro, no mostramos almacenes (retornamos vacio)
            echo json_encode([]);
            return;
        }
        
        $almacenes_implicados = $this->db->get()->result_array();
        
        if (empty($almacenes_implicados)) {
            echo json_encode([]);
            return;
        }

        // Extraer IDs para la consulta de inventario
        $almacen_ids = array_column($almacenes_implicados, 'id');        // 2. Obtener todos los productos activos con shade, formato, procedencia, empaque y m2_por_caja
        $this->db->select('p.id as producto_id, p.id, p.codigo, p.nombre, p.imagen_url, p.precio_base_bob, p.m2_por_caja, p.piezas_por_caja, p.cajas_por_palet, p.shade, p.se_fracciona, p.venta_por_pieza, cat_u.abreviacion as unidad_base_abrev, cat_f.nombre as formato, cat_p.nombre as origen');
        $this->db->from('productos p');
        $this->db->join('cat_formatos cat_f', 'cat_f.id = p.formato_id', 'left');
        $this->db->join('cat_paises cat_p', 'cat_p.id = p.pais_id', 'left');
        $this->db->join('cat_unidad_base cat_u', 'cat_u.id = p.unidad_base_id', 'left');
        $this->db->where('p.activo', 1);
        $this->db->order_by('p.nombre', 'ASC');
        $productos = $this->db->get()->result_array();

        if (count($productos) > 0) {
            $ids = array_column($productos, 'producto_id');
            $this->db->select('pe.producto_id, pe.escala_id, cep.nombre as nombre_escala, cep.rango_min, cep.rango_max, cep.ordenado, cep.precio_cliente_final, pe.precio_caja_bob');
            $this->db->from('precios_escalonados pe');
            $this->db->join('config_escalas_precios cep', 'cep.id = pe.escala_id');
            $this->db->where_in('pe.producto_id', $ids);
            $this->db->order_by('pe.producto_id', 'ASC');
            $this->db->order_by('cep.ordenado', 'ASC');
            $this->db->order_by('cep.rango_min', 'ASC');
            $precios_esc = $this->db->get()->result_array();

            $mapa_precios = [];
            foreach ($precios_esc as $pe) {
                $mapa_precios[$pe['producto_id']][] = $pe;
            }

            foreach ($productos as &$p) {
                $p['precios_escalonados'] = isset($mapa_precios[$p['producto_id']]) ? $mapa_precios[$p['producto_id']] : [];
                // Asegurar valores numéricos por defecto si vienen en 0 o nulos
                $p['cajas_por_palet'] = (isset($p['cajas_por_palet']) && (int)$p['cajas_por_palet'] > 0) ? (int)$p['cajas_por_palet'] : 60;
                $p['m2_por_caja'] = (isset($p['m2_por_caja']) && (float)$p['m2_por_caja'] > 0) ? (float)$p['m2_por_caja'] : 1.44;
            }
        }

        // 3. Obtener el inventario de estos almacenes
        $this->db->select('id, producto_id, almacen_id, stock_fisico, stock_reservado, stock_disponible');
        $this->db->from('inventario_almacen');
        $this->db->where_in('almacen_id', $almacen_ids);
        $inventarios = $this->db->get()->result_array();

        // Crear indice para busqueda rapida en PHP: $inv_idx[producto_id][almacen_id] = fila
        $inv_idx = [];
        foreach ($inventarios as $inv) {
            $inv_idx[$inv['producto_id']][$inv['almacen_id']] = $inv;
        }

        // 4. Construir la respuesta final
        $resultado = [];
        foreach ($productos as $p) {
            $p['stock_fisico'] = 0;
            $p['stock_reservado'] = 0;
            $p['stock_disponible'] = 0;
            $p['detalle_almacenes'] = [];

            foreach ($almacenes_implicados as $almacen) {
                $fila = isset($inv_idx[$p['producto_id']][$almacen['id']]) ? $inv_idx[$p['producto_id']][$almacen['id']] : null;
                
                $f = $fila ? (float)$fila['stock_fisico'] : 0;
                $r = $fila ? (float)$fila['stock_reservado'] : 0;
                $d = $fila ? (float)$fila['stock_disponible'] : 0;

                $p['stock_fisico'] += $f;
                $p['stock_reservado'] += $r;
                $p['stock_disponible'] += $d;

                $p['detalle_almacenes'][] = [
                    'almacen_id' => $almacen['id'],
                    'almacen_nombre' => $almacen['nombre'],
                    'stock_fisico' => $f,
                    'stock_disponible' => $d
                ];
            }
            $resultado[] = $p;
        }

        echo json_encode($resultado);
    }

    /**
     * POST /api/inventario/ajuste
     * Registra un movimiento (Kardex) y actualiza el saldo de inventario_almacen
     */
    public function ajuste() {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);
        
        $producto_id = isset($input['producto_id']) ? (int)$input['producto_id'] : null;
        $almacen_id = isset($input['almacen_id']) ? (int)$input['almacen_id'] : null;
        $tipo_movimiento = isset($input['tipo_movimiento']) ? $input['tipo_movimiento'] : null;
        $cantidad = isset($input['cantidad']) ? (float)$input['cantidad'] : (isset($input['cantidad_cajas']) ? (float)$input['cantidad_cajas'] : null);
        $motivo = isset($input['motivo']) ? $input['motivo'] : '';

        if (!$producto_id || !$almacen_id || !$tipo_movimiento || $cantidad === null) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'Faltan campos requeridos']);
            return;
        }

        $this->db->trans_start();

        // 1. Insertar en log_movimientos_inventario
        $this->db->insert('log_movimientos_inventario', [
            'producto_id' => $producto_id,
            'almacen_id' => $almacen_id,
            'tipo_movimiento' => $tipo_movimiento,
            'cantidad' => $cantidad,
            'motivo_observacion' => $motivo
        ]);

        // 2. Actualizar o Insertar en inventario_almacen
        // Buscar si ya existe la fila
        $this->db->where('producto_id', $producto_id);
        $this->db->where('almacen_id', $almacen_id);
        $row = $this->db->get('inventario_almacen')->row_array();

        // Determinar impacto (+ o -)
        $impacto = 0;
        if (in_array($tipo_movimiento, ['INGRESO_IMPORTACION', 'DEVOLUCION_CLIENTE'])) {
            $impacto = $cantidad_cajas;
        } else if (in_array($tipo_movimiento, ['DESPACHO_VENTA', 'BAJA_MERMA'])) {
            $impacto = -$cantidad_cajas;
        }

        if ($row) {
            // Update
            $this->db->where('producto_id', $producto_id);
            $this->db->where('almacen_id', $almacen_id);
            $this->db->set('stock_fisico', 'stock_fisico + ' . $impacto, FALSE);
            $this->db->set('stock_disponible', 'stock_disponible + ' . $impacto, FALSE);
            $this->db->update('inventario_almacen');
        } else {
            // Insert
            $this->db->insert('inventario_almacen', [
                'producto_id' => $producto_id,
                'almacen_id' => $almacen_id,
                'stock_fisico' => $impacto,
                'stock_reservado' => 0,
                'stock_disponible' => $impacto
            ]);
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            $this->output->set_status_header(500);
            echo json_encode(['error' => 'Error al procesar el inventario']);
            return;
        }

        echo json_encode(['status' => 'success']);
    }

    /**
     * GET /api/inventario/kardex
     * Retorna el historial enriquecido con datos de venta/cliente/vendedor y filtros de fecha
     */
    public function kardex() {
        if (!$this->verificar_token()) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $producto_id = $this->input->get('producto_id');
        $almacen_id = $this->input->get('almacen_id');
        $fecha_desde = $this->input->get('fecha_desde');
        $fecha_hasta = $this->input->get('fecha_hasta');

        $this->db->select('lmi.*, a.nombre as almacen_nombre');
        $this->db->from('log_movimientos_inventario lmi');
        $this->db->join('almacenes a', 'a.id = lmi.almacen_id', 'left');
        $this->db->where('lmi.producto_id', $producto_id);
        
        if ($almacen_id) {
            $this->db->where('lmi.almacen_id', $almacen_id);
        }
        if (!empty($fecha_desde)) {
            $this->db->where('DATE(lmi.fecha_hora) >=', $fecha_desde);
        }
        if (!empty($fecha_hasta)) {
            $this->db->where('DATE(lmi.fecha_hora) <=', $fecha_hasta);
        }

        $this->db->order_by('lmi.fecha_hora', 'DESC');
        $movimientos = $this->db->get()->result_array();

        // Enriquecer movimientos si corresponden a despachos o ventas
        foreach ($movimientos as &$m) {
            $m['venta_id'] = null;
            $m['cliente_nombre'] = null;
            $m['vendedor_nombre'] = null;
            $m['precio_unitario_caja'] = null;
            $m['total_venta'] = null;

            // Extraer venta_id del motivo_observacion mediante Regex (ej. "orden #12" o "venta #8")
            if (preg_match('/(?:orden|venta)\s*#(\d+)/i', $m['motivo_observacion'], $matches)) {
                $venta_id = (int)$matches[1];
                $m['venta_id'] = $venta_id;

                $this->db->select('v.id, v.total, u.nombre as vendedor_nombre, c.tipo_cliente, c.final_nombre_completo, c.comercial_nombre_comercial, c.constructora_nombre');
                $this->db->from('ventas v');
                $this->db->join('usuarios u', 'u.id = v.usuario_id', 'left');
                $this->db->join('clientes c', 'c.id = v.cliente_id', 'left');
                $this->db->where('v.id', $venta_id);
                $venta = $this->db->get()->row_array();

                if ($venta) {
                    $m['vendedor_nombre'] = $venta['vendedor_nombre'];
                    $m['total_venta'] = $venta['total'];

                    // Obtener nombre representativo del cliente
                    if ($venta['tipo_cliente'] === 'COMERCIAL') {
                        $m['cliente_nombre'] = $venta['comercial_nombre_comercial'];
                    } else if ($venta['tipo_cliente'] === 'CONSTRUCTORA') {
                        $m['cliente_nombre'] = $venta['constructora_nombre'];
                    } else {
                        $m['cliente_nombre'] = $venta['final_nombre_completo'];
                    }

                    // Obtener precio_unitario_caja para este producto específico en esa venta
                    $this->db->select('precio_unitario_caja, subtotal');
                    $this->db->from('venta_detalles');
                    $this->db->where('venta_id', $venta_id);
                    $this->db->where('producto_id', $m['producto_id']);
                    $vd = $this->db->get()->row_array();
                    if ($vd) {
                        $m['precio_unitario_caja'] = $vd['precio_unitario_caja'];
                        $m['subtotal_item'] = $vd['subtotal'];
                    }
                }
            }
        }

        echo json_encode($movimientos);
    }

    /**
     * POST /api/inventario/registrar_merma
     * Registra una baja/merma soportando unidades: CAJAS, PIEZAS o M2
     */
    public function registrar_merma() {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);

        $almacen_id = isset($input['almacen_id']) ? (int)$input['almacen_id'] : null;
        $producto_id = isset($input['producto_id']) ? (int)$input['producto_id'] : null;
        $motivo = isset($input['motivo']) ? trim($input['motivo']) : 'ROTURA_EN_ALMACÉN';
        $observacion = isset($input['observacion']) ? trim($input['observacion']) : '';
        $unidad_medida = isset($input['unidad_medida']) ? strtoupper(trim($input['unidad_medida'])) : 'CAJA';

        if (!$almacen_id || !$producto_id) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'Almacén y Producto son obligatorios.']);
            return;
        }

        // Obtener producto para factores de conversión
        $this->db->select('p.*, cat_u.abreviacion as unidad_base_abrev');
        $this->db->from('productos p');
        $this->db->join('cat_unidad_base cat_u', 'cat_u.id = p.unidad_base_id', 'left');
        $this->db->where('p.id', $producto_id);
        $producto = $this->db->get()->row_array();

        if (!$producto) {
            $this->output->set_status_header(404);
            echo json_encode(['error' => 'Producto no encontrado.']);
            return;
        }

        $abrevUpper = strtoupper(trim($producto['unidad_base_abrev'] ?? ''));
        $esPorPieza = ($abrevUpper === 'PZA' || $abrevUpper === 'PIEZA' || $abrevUpper === 'BLS' || $abrevUpper === 'BOLSA') 
                      || ($abrevUpper !== 'M2' && $abrevUpper !== 'M²' && (float)$producto['m2_por_caja'] <= 0);

        $m2_por_caja = (float)($producto['m2_por_caja'] > 0 ? $producto['m2_por_caja'] : 1.0);
        $piezas_por_caja = (int)($producto['piezas_por_caja'] > 0 ? $producto['piezas_por_caja'] : 1);

        $cantidad_digitada = isset($input['cantidad']) ? (float)$input['cantidad'] : (isset($input['cantidad_cajas']) ? (float)$input['cantidad_cajas'] : 0);

        if ($cantidad_digitada <= 0) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'La cantidad ingresada debe ser mayor a 0.']);
            return;
        }

        // Calcular cantidad base a descontar (m2 o piezas) y cajas equivalentes
        $cantidad_base_descontar = 0;
        $cajas_calculadas = 0;

        if ($esPorPieza) {
            if ($unidad_medida === 'CAJA') {
                $cajas_calculadas = ceil($cantidad_digitada);
                $cantidad_base_descontar = $cajas_calculadas * $piezas_por_caja;
            } else { // PIEZA u otra
                $cantidad_base_descontar = $cantidad_digitada;
                $cajas_calculadas = ceil($cantidad_digitada / $piezas_por_caja);
            }
        } else {
            if ($unidad_medida === 'PIEZA') {
                $cajas_calculadas = ceil($cantidad_digitada / $piezas_por_caja);
                $cantidad_base_descontar = round($cajas_calculadas * $m2_por_caja, 4);
            } else if ($unidad_medida === 'M2') {
                $cajas_calculadas = ceil($cantidad_digitada / $m2_por_caja);
                $cantidad_base_descontar = round($cajas_calculadas * $m2_por_caja, 4);
            } else { // CAJA
                $cajas_calculadas = ceil($cantidad_digitada);
                $cantidad_base_descontar = round($cajas_calculadas * $m2_por_caja, 4);
            }
        }

        $this->db->trans_start();

        // 1. Verificar existencia en inventario_almacen
        $this->db->where('almacen_id', $almacen_id);
        $this->db->where('producto_id', $producto_id);
        $inv = $this->db->get('inventario_almacen')->row_array();

        if (!$inv || ((float)$inv['stock_disponible'] + 0.0001) < $cantidad_base_descontar) {
            $this->db->trans_rollback();
            $this->output->set_status_header(400);
            $disp = $inv ? round((float)$inv['stock_disponible'], 2) : 0;
            $unidad_label = $esPorPieza ? 'piezas' : 'm²';
            echo json_encode(['error' => "Stock disponible insuficiente para registrar la baja. Disponible: $disp $unidad_label."]);
            return;
        }

        // 2. Descontar de inventario_almacen
        $nuevo_fisico = max(0, (float)$inv['stock_fisico'] - $cantidad_base_descontar);
        $nuevo_disponible = max(0, (float)$inv['stock_disponible'] - $cantidad_base_descontar);

        $this->db->where('id', $inv['id']);
        $this->db->update('inventario_almacen', [
            'stock_fisico' => $nuevo_fisico,
            'stock_disponible' => $nuevo_disponible
        ]);

        // 3. Registrar en log_movimientos_inventario
        $detalle_motivo = "Baja/Merma ($motivo) - " . ($observacion ?: 'Sin observación adicional');
        $this->db->insert('log_movimientos_inventario', [
            'producto_id' => $producto_id,
            'almacen_id' => $almacen_id,
            'tipo_movimiento' => 'BAJA_MERMA',
            'cantidad' => $cantidad_base_descontar,
            'motivo_observacion' => $detalle_motivo . " [Por: {$usuario['nombre']}]"
        ]);

        // 4. Registrar en inventario_movimientos
        $this->db->insert('inventario_movimientos', [
            'producto_id' => $producto_id,
            'almacen_origen_id' => $almacen_id,
            'tipo_movimiento' => 'MERMA',
            'cantidad_cajas' => (int)$cajas_calculadas,
            'cantidad' => $cantidad_base_descontar,
            'fecha' => date('Y-m-d H:i:s'),
            'observacion' => $detalle_motivo
        ]);

        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            $this->output->set_status_header(500);
            echo json_encode(['error' => 'Error al procesar la baja en base de datos.']);
            return;
        }

        echo json_encode([
            'status' => 'success',
            'mensaje' => 'Baja por merma registrada exitosamente.',
            'cantidad_descontada' => $cantidad_base_descontar,
            'cajas_descontadas' => $cajas_calculadas
        ]);
    }

    /**
     * POST /api/inventario/procesar_devolucion
     */
    public function procesar_devolucion() {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);

        $venta_id = isset($input['venta_id']) ? (int)$input['venta_id'] : null;
        $producto_id = isset($input['producto_id']) ? (int)$input['producto_id'] : null;
        $estado_devolucion = isset($input['estado_devolucion']) ? trim($input['estado_devolucion']) : 'BUEN_ESTADO';
        $observacion = isset($input['observacion']) ? trim($input['observacion']) : '';
        $unidad_medida = isset($input['unidad_medida']) ? strtoupper(trim($input['unidad_medida'])) : 'CAJA';

        if (!$venta_id || !$producto_id) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'ID de Venta y Producto son obligatorios.']);
            return;
        }

        // Obtener la venta y su almacén
        $this->db->select('vd.*, v.ciudad_id, p.m2_por_caja, p.piezas_por_caja, cat_u.abreviacion as unidad_base_abrev');
        $this->db->from('venta_detalles vd');
        $this->db->join('ventas v', 'v.id = vd.venta_id');
        $this->db->join('productos p', 'p.id = vd.producto_id');
        $this->db->join('cat_unidad_base cat_u', 'cat_u.id = p.unidad_base_id', 'left');
        $this->db->where('vd.venta_id', $venta_id);
        $this->db->where('vd.producto_id', $producto_id);
        $v_det = $this->db->get()->row_array();

        if (!$v_det) {
            $this->output->set_status_header(404);
            echo json_encode(['error' => "No se encontró el producto en la venta #$venta_id."]);
            return;
        }

        $almacen_id = (int)$v_det['almacen_id'];
        $m2_por_caja = (float)($v_det['m2_por_caja'] > 0 ? $v_det['m2_por_caja'] : 1.0);
        $piezas_por_caja = (int)($v_det['piezas_por_caja'] > 0 ? $v_det['piezas_por_caja'] : 1);

        $abrevUpper = strtoupper(trim($v_det['unidad_base_abrev'] ?? ''));
        $esPorPieza = ($abrevUpper === 'PZA' || $abrevUpper === 'PIEZA' || $abrevUpper === 'BLS' || $abrevUpper === 'BOLSA') 
                      || ($abrevUpper !== 'M2' && $abrevUpper !== 'M²' && $m2_por_caja <= 0);

        $cantidad_digitada = isset($input['cantidad']) ? (float)$input['cantidad'] : (isset($input['cantidad_cajas']) ? (float)$input['cantidad_cajas'] : 0);

        if ($cantidad_digitada <= 0) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'La cantidad devuelta debe ser mayor a 0.']);
            return;
        }

        $cantidad_base_retorno = 0;
        $cajas_calculadas = 0;

        if ($esPorPieza) {
            if ($unidad_medida === 'CAJA') {
                $cajas_calculadas = ceil($cantidad_digitada);
                $cantidad_base_retorno = $cajas_calculadas * $piezas_por_caja;
            } else {
                $cantidad_base_retorno = $cantidad_digitada;
                $cajas_calculadas = ceil($cantidad_digitada / $piezas_por_caja);
            }
        } else {
            if ($unidad_medida === 'PIEZA') {
                $cajas_calculadas = ceil($cantidad_digitada / $piezas_por_caja);
                $cantidad_base_retorno = round($cajas_calculadas * $m2_por_caja, 4);
            } else if ($unidad_medida === 'M2') {
                $cajas_calculadas = ceil($cantidad_digitada / $m2_por_caja);
                $cantidad_base_retorno = round($cajas_calculadas * $m2_por_caja, 4);
            } else {
                $cajas_calculadas = ceil($cantidad_digitada);
                $cantidad_base_retorno = round($cajas_calculadas * $m2_por_caja, 4);
            }
        }

        $this->db->trans_start();

        // Si el material devuelto está en buen estado, reingresa a stock disponible
        if ($estado_devolucion === 'BUEN_ESTADO') {
            $inv = $this->db->get_where('inventario_almacen', [
                'almacen_id' => $almacen_id,
                'producto_id' => $producto_id
            ])->row_array();

            if ($inv) {
                $this->db->where('id', $inv['id']);
                $this->db->update('inventario_almacen', [
                    'stock_fisico' => (float)$inv['stock_fisico'] + $cantidad_base_retorno,
                    'stock_disponible' => (float)$inv['stock_disponible'] + $cantidad_base_retorno
                ]);
            } else {
                $this->db->insert('inventario_almacen', [
                    'almacen_id' => $almacen_id,
                    'producto_id' => $producto_id,
                    'stock_cajas' => (int)$cajas_calculadas,
                    'stock_fisico' => $cantidad_base_retorno,
                    'stock_reservado' => 0,
                    'stock_disponible' => $cantidad_base_retorno
                ]);
            }
        }

        // Registrar en logs
        $motivo_log = "Devolución de cliente venta #$venta_id ($estado_devolucion) - " . ($observacion ?: 'Sin obs');
        $this->db->insert('log_movimientos_inventario', [
            'producto_id' => $producto_id,
            'almacen_id' => $almacen_id,
            'tipo_movimiento' => 'DEVOLUCION_CLIENTE',
            'cantidad' => $cantidad_base_retorno,
            'motivo_observacion' => $motivo_log . " [Por: {$usuario['nombre']}]"
        ]);

        $this->db->insert('inventario_movimientos', [
            'producto_id' => $producto_id,
            'almacen_origen_id' => $almacen_id,
            'tipo_movimiento' => ($estado_devolucion === 'BUEN_ESTADO' ? 'AJUSTE' : 'MERMA'),
            'cantidad_cajas' => (int)$cajas_calculadas,
            'cantidad' => $cantidad_base_retorno,
            'fecha' => date('Y-m-d H:i:s'),
            'observacion' => $motivo_log
        ]);

        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            $this->output->set_status_header(500);
            echo json_encode(['error' => 'Error al procesar la devolución en base de datos.']);
            return;
        }

        echo json_encode([
            'status' => 'success',
            'mensaje' => 'Devolución registrada correctamente.',
            'cantidad_reingresada' => $cantidad_base_retorno,
            'cajas' => $cajas_calculadas
        ]);
    }
}

