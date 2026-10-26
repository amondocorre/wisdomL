<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Logistica extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->library('JWT');
        $this->output->set_content_type('application/json');
    }

    /**
     * Registrar marcación GPS del chofer
     * POST /api/logistica/marcar_gps
     */
    public function marcar_gps() {
        // Validar Token JWT (Rol CHOFER o ADMIN)
        $headers = $this->input->request_headers();
        $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
        $usuario = JWT::decode($token);

        if (!$usuario || !in_array($usuario['rol'], ['CHOFER', 'ADMIN'])) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);

        if (empty($input['hoja_ruta_detalle_id']) || empty($input['tipo_marcacion']) || !isset($input['latitud']) || !isset($input['longitud'])) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'Datos de geolocalización o identificación de ruta incompletos']);
            return;
        }

        $hoja_ruta_detalle_id = $input['hoja_ruta_detalle_id'];
        $tipo_marcacion = $input['tipo_marcacion']; // SALIDA_DEPOSITO, LLEGADA_CLIENTE, CONFIRMACION_ENTREGA
        $latitud = (float) $input['latitud'];
        $longitud = (float) $input['longitud'];

        // Validar que el detalle de ruta exista
        $detalle = $this->db->get_where('hoja_ruta_detalles', ['id' => $hoja_ruta_detalle_id])->row();
        if (!$detalle) {
            $this->output->set_status_header(404);
            echo json_encode(['error' => 'Detalle de hoja de ruta no encontrado']);
            return;
        }

        $this->db->trans_start();

        // 1. Insertar marcación GPS con coordenadas
        $this->db->insert('despachos_marcaciones_gps', [
            'hoja_ruta_detalle_id' => $hoja_ruta_detalle_id,
            'tipo_marcacion' => $tipo_marcacion,
            'latitud' => $latitud,
            'longitud' => $longitud
        ]);

        // 2. Mapear tipo de marcación al estado físico de despacho en la hoja de ruta
        $nuevo_estado_despacho = 'PENDIENTE';
        $nuevo_estado_venta = 'VENDIDO';

        if ($tipo_marcacion === 'SALIDA_DEPOSITO') {
            $nuevo_estado_despacho = 'SALIDA_DEPOSITO';
            $nuevo_estado_venta = 'DESPACHADO'; // Pasa a despachado (físicamente sale de almacén)
        } elseif ($tipo_marcacion === 'LLEGADA_CLIENTE') {
            $nuevo_estado_despacho = 'LLEGADA_CLIENTE';
            $nuevo_estado_venta = 'DESPACHADO';
        } elseif ($tipo_marcacion === 'CONFIRMACION_ENTREGA') {
            $nuevo_estado_despacho = 'ENTREGADO';
            $nuevo_estado_venta = 'ENTREGADO'; // Entregado final al cliente
        }

        // Actualizar estado del despacho en la hoja de ruta
        $this->db->where('id', $hoja_ruta_detalle_id);
        $this->db->update('hoja_ruta_detalles', ['estado_despacho' => $nuevo_estado_despacho]);

        // Actualizar estado general de la venta
        $this->db->where('id', $detalle->venta_id);
        $this->db->update('ventas', ['estado' => $nuevo_estado_venta]);

        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            $this->output->set_status_header(500);
            echo json_encode(['error' => 'Error al procesar la geolocalización']);
            return;
        }

        echo json_encode([
            'status' => 'success',
            'mensaje' => 'Marcación GPS registrada correctamente',
            'estado_despacho' => $nuevo_estado_despacho,
            'estado_venta' => $nuevo_estado_venta
        ]);
    }
}
