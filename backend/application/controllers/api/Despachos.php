<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Despachos extends CI_Controller {

    public function __construct() {
        parent::__construct();
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, Origin, Accept");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit(0);
        }

        $this->load->database();
        $this->load->model('Inventario_model');
        $this->load->library('JWT');
        $this->output->set_content_type('application/json');
    }

    private function verificar_token() {
        $headers = $this->input->request_headers();
        $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
        if (!$token && isset($headers['authorization'])) {
            $token = str_replace('Bearer ', '', $headers['authorization']);
        }
        return JWT::decode($token);
    }

    /**
     * GET /api/despachos/listar_pendientes
     * Retorna ventas con estado VENDIDO o PARCIALMENTE_DESPACHADO que tienen saldo pendiente de entrega física
     */
    public function listar_pendientes() {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $almacen_filtro = $this->input->get('almacen_id');

        $this->db->select('v.*, c.final_nombre_completo, c.tipo_cliente, c.comercial_nombre_comercial, c.constructora_nombre, u.nombre as vendedor_nombre');
        $this->db->from('ventas v');
        $this->db->join('clientes c', 'c.id = v.cliente_id');
        $this->db->join('usuarios u', 'u.id = v.usuario_id', 'left');
        $this->db->where_in('v.estado', ['VENDIDO', 'PARCIALMENTE_DESPACHADO']);
        $this->db->order_by('v.id', 'DESC');
        $ventas = $this->db->get()->result_array();

        $resultado = [];

        foreach ($ventas as $v) {
            $this->db->select('vd.*, p.nombre as producto_nombre, p.codigo as producto_codigo, p.m2_por_caja, p.piezas_por_caja, p.cajas_por_palet, cat_u.abreviacion as unidad_base_abrev, a.nombre as almacen_nombre');
            $this->db->from('venta_detalles vd');
            $this->db->join('productos p', 'p.id = vd.producto_id');
            $this->db->join('cat_unidad_base cat_u', 'cat_u.id = p.unidad_base_id', 'left');
            $this->db->join('almacenes a', 'a.id = vd.almacen_id');
            $this->db->where('vd.venta_id', $v['id']);
            if (!empty($almacen_filtro)) {
                $this->db->where('vd.almacen_id', (int)$almacen_filtro);
            }
            $detalles = $this->db->get()->result_array();

            if (empty($detalles)) {
                continue;
            }

            $tiene_saldo_pendiente = false;

            foreach ($detalles as &$det) {
                $m2_sol = (float)$det['m2_reales'];
                $m2_desp = (float)($det['m2_despachados'] ?? 0);
                $m2_pend = max(0, round($m2_sol - $m2_desp, 4));

                $cajas_sol = (float)$det['cajas_solicitadas'];
                $cajas_desp = (float)($det['cajas_despachadas'] ?? 0);
                $cajas_pend = max(0, round($cajas_sol - $cajas_desp, 2));

                $det['m2_pendiente'] = $m2_pend;
                $det['cajas_pendiente'] = $cajas_pend;
                $det['porcentaje_despachado'] = $m2_sol > 0 ? round(($m2_desp / $m2_sol) * 100, 1) : 0;

                if ($m2_pend > 0.0001) {
                    $tiene_saldo_pendiente = true;
                }
            }

            if ($tiene_saldo_pendiente) {
                // Obtener historial de despachos ya realizados de esta venta
                $this->db->select('vdsp.*, u.nombre as despachador_nombre');
                $this->db->from('venta_despachos vdsp');
                $this->db->join('usuarios u', 'u.id = vdsp.usuario_id', 'left');
                $this->db->where('vdsp.venta_id', $v['id']);
                $this->db->order_by('vdsp.id', 'DESC');
                $historial = $this->db->get()->result_array();

                foreach ($historial as &$h) {
                    $this->db->select('vdd.*, p.nombre as producto_nombre, p.codigo as producto_codigo, p.m2_por_caja, cat_u.abreviacion as unidad_base_abrev');
                    $this->db->from('venta_despacho_detalles vdd');
                    $this->db->join('productos p', 'p.id = vdd.producto_id');
                    $this->db->join('cat_unidad_base cat_u', 'cat_u.id = p.unidad_base_id', 'left');
                    $this->db->where('vdd.venta_despacho_id', $h['id']);
                    $h['detalles'] = $this->db->get()->result_array();
                }

                $v['detalles'] = $detalles;
                $v['historial_despachos'] = $historial;
                $resultado[] = $v;
            }
        }

        echo json_encode($resultado);
    }

    /**
     * POST /api/despachos/confirmar_salida
     * Soporta despacho TOTAL o PARCIAL con selección de unidad (M2, CAJA, PALET, PIEZA)
     */
    public function confirmar_salida() {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);

        if (empty($input['venta_id']) || empty($input['items'])) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'Venta ID y lista de ítems a despachar son obligatorios.']);
            return;
        }

        $venta_id = (int)$input['venta_id'];
        $observaciones = isset($input['observaciones']) ? trim($input['observaciones']) : 'Despacho en Galpón';
        $items = $input['items'];

        $venta = $this->db->get_where('ventas', ['id' => $venta_id])->row_array();
        if (!$venta) {
            $this->output->set_status_header(404);
            echo json_encode(['error' => 'Venta no encontrada']);
            return;
        }

        $this->db->trans_start();

        // 1. Crear cabecera de despacho en venta_despachos
        $primer_almacen = 1;
        if (!empty($items) && isset($items[0]['almacen_id'])) {
            $primer_almacen = (int)$items[0]['almacen_id'];
        }

        $this->db->insert('venta_despachos', [
            'venta_id' => $venta_id,
            'almacen_id' => $primer_almacen,
            'usuario_id' => $usuario['id'],
            'tipo_despacho' => 'PARCIAL',
            'observaciones' => $observaciones,
            'fecha_despacho' => date('Y-m-d H:i:s')
        ]);
        $venta_despacho_id = $this->db->insert_id();

        $algun_item_despachado = false;

        // 2. Procesar cada ítem del despacho
        foreach ($items as $it) {
            $venta_detalle_id = (int)$it['venta_detalle_id'];
            $cantidad_a_despachar_base = (float)($it['cantidad_despachar_base'] ?? 0);
            $cajas_a_despachar = (float)($it['cajas_despachar'] ?? 0);
            $unidad = isset($it['unidad_medida']) ? $it['unidad_medida'] : 'M2';

            if ($cantidad_a_despachar_base <= 0) {
                continue; // No se despacha este ítem en esta entrega
            }

            $v_det = $this->db->get_where('venta_detalles', ['id' => $venta_detalle_id])->row_array();
            if (!$v_det) {
                continue;
            }

            $almacen_id = (int)$v_det['almacen_id'];
            $producto_id = (int)$v_det['producto_id'];

            // Descontar físicamente del inventario y del saldo reservado
            $despacho_ok = $this->Inventario_model->despachar_stock_fisico(
                $almacen_id,
                $producto_id,
                $cantidad_a_despachar_base,
                "Despacho físico orden #$venta_id - $observaciones",
                $usuario['id'],
                $cajas_a_despachar
            );

            if (!$despacho_ok) {
                $this->db->trans_rollback();
                $this->output->set_status_header(400);
                echo json_encode(['error' => "No hay suficiente stock físico o reservado para despachar el producto ID $producto_id."]);
                return;
            }

            // Registrar detalle en venta_despacho_detalles
            $this->db->insert('venta_despacho_detalles', [
                'venta_despacho_id' => $venta_despacho_id,
                'venta_detalle_id' => $venta_detalle_id,
                'producto_id' => $producto_id,
                'almacen_id' => $almacen_id,
                'cantidad_despachada_base' => $cantidad_a_despachar_base,
                'cajas_despachadas' => $cajas_a_despachar,
                'unidad_medida' => $unidad
            ]);

            // Actualizar acumulado en venta_detalles
            $nuevo_m2_desp = (float)$v_det['m2_despachados'] + $cantidad_a_despachar_base;
            $nuevo_cajas_desp = (float)$v_det['cajas_despachadas'] + $cajas_a_despachar;

            $this->db->where('id', $venta_detalle_id);
            $this->db->update('venta_detalles', [
                'm2_despachados' => $nuevo_m2_desp,
                'cajas_despachadas' => $nuevo_cajas_desp
            ]);

            $algun_item_despachado = true;
        }

        if (!$algun_item_despachado) {
            $this->db->trans_rollback();
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'Debe ingresar al menos una cantidad mayor a 0 para despachar.']);
            return;
        }

        // 3. Evaluar si la venta completa ya fue despachada en su totalidad
        $todos_detalles = $this->db->get_where('venta_detalles', ['venta_id' => $venta_id])->result_array();
        $todo_completado = true;

        foreach ($todos_detalles as $td) {
            $m2_sol = (float)$td['m2_reales'];
            $m2_desp = (float)$td['m2_despachados'];
            if (($m2_sol - $m2_desp) > 0.0001) {
                $todo_completado = false;
                break;
            }
        }

        $nuevo_estado = $todo_completado ? 'DESPACHADO' : 'PARCIALMENTE_DESPACHADO';

        $this->db->where('id', $venta_id);
        $this->db->update('ventas', ['estado' => $nuevo_estado]);

        $this->db->where('id', $venta_despacho_id);
        $this->db->update('venta_despachos', ['tipo_despacho' => $todo_completado ? 'TOTAL' : 'PARCIAL']);

        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            $this->output->set_status_header(500);
            echo json_encode(['error' => 'Error al procesar el despacho en base de datos.']);
            return;
        }

        echo json_encode([
            'status' => 'success',
            'mensaje' => $todo_completado ? 'Despacho completado en su totalidad.' : 'Despacho parcial registrado exitosamente.',
            'estado_venta' => $nuevo_estado,
            'venta_despacho_id' => $venta_despacho_id
        ]);
    }

    /**
     * GET /api/despachos/reporte_historial
     * Retorna el reporte de despachos realizados filtrado por almacén y rango de fechas
     */
    public function reporte_historial() {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $almacen_id = $this->input->get('almacen_id');
        $fecha_desde = $this->input->get('fecha_desde');
        $fecha_hasta = $this->input->get('fecha_hasta');

        $this->db->select('
            vdd.id as despacho_detalle_id,
            vd.id as despacho_id,
            vd.venta_id,
            vd.fecha_despacho,
            vd.tipo_despacho,
            vd.observaciones as despacho_observaciones,
            u.nombre as despachador_nombre,
            a.id as almacen_id,
            a.nombre as almacen_nombre,
            p.id as producto_id,
            p.codigo as producto_codigo,
            p.nombre as producto_nombre,
            p.imagen_url,
            p.shade,
            p.m2_por_caja,
            p.piezas_por_caja,
            p.cajas_por_palet,
            cat_f.nombre as formato,
            cat_p.nombre as origen,
            cat_u.abreviacion as unidad_base_abrev,
            vdd.cantidad_despachada_base,
            vdd.cajas_despachadas,
            vdd.unidad_medida,
            v.fecha_venta,
            v.tipo_pago,
            v.total as venta_total,
            uv.nombre as vendedor_nombre,
            c.tipo_cliente,
            c.final_nombre_completo,
            c.comercial_nombre_comercial,
            c.constructora_nombre,
            c.nit_ci as cliente_nit_ci,
            v_det.precio_unitario_caja,
            vt.tipo_transporte,
            vt.nombre_empresa_chofer,
            vt.placa_vehiculo,
            vt.telefono_contacto as chofer_telefono
        ');
        $this->db->from('venta_despacho_detalles vdd');
        $this->db->join('venta_despachos vd', 'vd.id = vdd.venta_despacho_id');
        $this->db->join('ventas v', 'v.id = vd.venta_id');
        $this->db->join('venta_detalles v_det', 'v_det.id = vdd.venta_detalle_id', 'left');
        $this->db->join('clientes c', 'c.id = v.cliente_id', 'left');
        $this->db->join('usuarios u', 'u.id = vd.usuario_id', 'left');
        $this->db->join('usuarios uv', 'uv.id = v.usuario_id', 'left');
        $this->db->join('almacenes a', 'a.id = vdd.almacen_id', 'left');
        $this->db->join('productos p', 'p.id = vdd.producto_id');
        $this->db->join('cat_formatos cat_f', 'cat_f.id = p.formato_id', 'left');
        $this->db->join('cat_paises cat_p', 'cat_p.id = p.pais_id', 'left');
        $this->db->join('cat_unidad_base cat_u', 'cat_u.id = p.unidad_base_id', 'left');
        $this->db->join('venta_transporte vt', 'vt.venta_id = v.id', 'left');

        if (!empty($almacen_id)) {
            $this->db->where('vdd.almacen_id', (int)$almacen_id);
        }
        if (!empty($fecha_desde)) {
            $this->db->where('DATE(vd.fecha_despacho) >=', $fecha_desde);
        }
        if (!empty($fecha_hasta)) {
            $this->db->where('DATE(vd.fecha_despacho) <=', $fecha_hasta);
        }

        $this->db->order_by('vd.fecha_despacho', 'DESC');
        $this->db->order_by('vdd.id', 'DESC');
        $despachos = $this->db->get()->result_array();

        // Enriquecer nombre del cliente
        foreach ($despachos as &$d) {
            if ($d['tipo_cliente'] === 'COMERCIAL') {
                $d['cliente_nombre'] = $d['comercial_nombre_comercial'];
            } else if ($d['tipo_cliente'] === 'CONSTRUCTORA') {
                $d['cliente_nombre'] = $d['constructora_nombre'];
            } else {
                $d['cliente_nombre'] = $d['final_nombre_completo'];
            }
        }

        echo json_encode($despachos);
    }
}


