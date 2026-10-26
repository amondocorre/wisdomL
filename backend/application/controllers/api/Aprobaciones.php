<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Aprobaciones extends CI_Controller {

    public function __construct() {
        parent::__construct();
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, Origin, Accept");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit(0);
        }

        $this->load->database();
        $this->load->model('Venta_model');
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
     * Listar ventas con precios pendientes de autorización
     * GET /api/aprobaciones/listar_pendientes
     */
    public function listar_pendientes() {
        $usuario = $this->verificar_token();

        if (!$usuario || !in_array($usuario['rol'], ['ADMIN', 'AUTORIZADOR', 'SUPERVISOR'])) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado para visualizar autorizaciones gerenciales']);
            return;
        }

        $this->db->select('v.*, c.tipo_cliente, c.comercial_nombre_comercial, c.constructora_nombre, c.final_nombre_completo, u.nombre as vendedor, ap.precio_propuesto_total, ap.notas_solicitud, ap.id as autorizacion_id, ap.creado_en as fecha_solicitud');
        $this->db->from('ventas v');
        $this->db->join('clientes c', 'v.cliente_id = c.id');
        $this->db->join('usuarios u', 'v.usuario_id = u.id');
        $this->db->join('autorizaciones_precio ap', 'v.id = ap.venta_id');
        $this->db->where('v.estado', 'PENDIENTE_AUTORIZACION');
        $this->db->where('ap.estado', 'PENDIENTE');
        $this->db->order_by('ap.id', 'DESC');

        $solicitudes = $this->db->get()->result_array();

        foreach ($solicitudes as &$s) {
            if ($s['tipo_cliente'] === 'COMERCIAL') {
                $s['cliente'] = $s['comercial_nombre_comercial'];
            } else if ($s['tipo_cliente'] === 'CONSTRUCTORA') {
                $s['cliente'] = $s['constructora_nombre'];
            } else {
                $s['cliente'] = $s['final_nombre_completo'];
            }

            // Cargar productos del detalle de la venta
            $this->db->select('vd.*, p.nombre, p.codigo, p.m2_por_caja, p.imagen_url, cat_f.nombre as formato');
            $this->db->from('venta_detalles vd');
            $this->db->join('productos p', 'p.id = vd.producto_id');
            $this->db->join('cat_formatos cat_f', 'cat_f.id = p.formato_id', 'left');
            $this->db->where('vd.venta_id', $s['id']);
            $s['items'] = $this->db->get()->result_array();
        }

        echo json_encode($solicitudes);
    }

    /**
     * Procesar solicitud de aprobación (APROBAR o RECHAZAR)
     * POST /api/aprobaciones/procesar
     */
    public function procesar() {
        $usuario = $this->verificar_token();

        if (!$usuario || !in_array($usuario['rol'], ['ADMIN', 'AUTORIZADOR', 'SUPERVISOR'])) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'Acción no autorizada']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);

        if (empty($input['autorizacion_id']) || empty($input['accion'])) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'Datos incompletos']);
            return;
        }

        $autorizacion_id = (int)$input['autorizacion_id'];
        $accion = strtoupper(trim($input['accion'])); // APROBADO, RECHAZADO, MODIFICADO
        $notas_resolucion = isset($input['notas_resolucion']) ? trim($input['notas_resolucion']) : '';

        // Obtener la solicitud
        $auth = $this->db->get_where('autorizaciones_precio', ['id' => $autorizacion_id])->row();
        if (!$auth) {
            $this->output->set_status_header(404);
            echo json_encode(['error' => 'Solicitud de autorización no encontrada']);
            return;
        }

        $venta_id = $auth->venta_id;

        $this->db->trans_start();

        // Actualizar tabla de autorizaciones
        $this->db->where('id', $autorizacion_id);
        $this->db->update('autorizaciones_precio', [
            'estado' => $accion,
            'usuario_autorizador_id' => $usuario['id'],
            'notas_resolucion' => $notas_resolucion,
            'fecha_resolucion' => date('Y-m-d H:i:s')
        ]);

        if ($accion === 'APROBADO' || $accion === 'MODIFICADO') {
            // Actualizar estado de la venta a VENDIDO
            $this->db->where('id', $venta_id);
            $this->db->update('ventas', ['estado' => 'VENDIDO']);

            // Al ser aprobada la venta por Gerencia, reservamos el stock (se suma a stock_reservado y resta de disponible)
            $detalles = $this->db->get_where('venta_detalles', ['venta_id' => $venta_id])->result_array();
            foreach ($detalles as $detalle) {
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
                    $this->output->set_status_header(400);
                    echo json_encode(['error' => "Stock insuficiente en almacén para el producto ID {$detalle['producto_id']} al momento de la aprobación."]);
                    return;
                }
            }
        } else {
            // Acción: RECHAZADO
            $this->db->where('id', $venta_id);
            $this->db->update('ventas', ['estado' => 'RECHAZADO']);
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            $this->output->set_status_header(500);
            echo json_encode(['error' => 'Error al procesar la aprobación/rechazo en base de datos.']);
            return;
        }

        echo json_encode([
            'status' => 'success',
            'mensaje' => "Solicitud #$autorizacion_id procesada exitosamente como $accion."
        ]);
    }
}
