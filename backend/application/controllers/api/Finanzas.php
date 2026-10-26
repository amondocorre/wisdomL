<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Finanzas extends CI_Controller {

    public function __construct() {
        parent::__construct();
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, Origin, Accept");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit(0);
        }

        $this->load->database();
        $this->load->library('JWT');
        $this->output->set_content_type('application/json');
    }

    private function verificar_token() {
        $headers = $this->input->request_headers();
        $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
        return JWT::decode($token);
    }

    /**
     * GET /api/finanzas/metodos_pago
     * Lista los métodos de pago configurados en el sistema
     */
    public function metodos_pago() {
        if (!$this->verificar_token()) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $this->db->select('m.*, c.banco as cuenta_banco, c.nro_cuenta as cuenta_numero');
        $this->db->from('finanzas_metodos_pago m');
        $this->db->join('finanzas_cuentas_bancarias c', 'c.id = m.cuenta_id', 'left');
        if ($this->input->get('activos') == 1) {
            $this->db->where('m.estado', 1);
        }
        $metodos = $this->db->get()->result_array();
        echo json_encode($metodos);
    }

    /**
     * GET /api/finanzas/cuentas_bancarias
     * Lista las cuentas bancarias activas
     */
    public function cuentas_bancarias() {
        if (!$this->verificar_token()) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        if ($this->input->get('activos') == 1) {
            $this->db->where('estado', 1);
        }
        $this->db->order_by('banco', 'ASC');
        $cuentas = $this->db->get('finanzas_cuentas_bancarias')->result_array();
        echo json_encode($cuentas);
    }

    /**
     * POST /api/finanzas/crear_cuenta
     * Crea una nueva cuenta bancaria
     */
    public function crear_cuenta() {
        $usuario = $this->verificar_token();
        if (!$usuario || $usuario['rol'] !== 'ADMIN') {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);
        if (empty($input['nombre_titular']) || empty($input['banco']) || empty($input['nro_cuenta'])) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'Nombre, banco y número de cuenta son obligatorios']);
            return;
        }

        $this->db->insert('finanzas_cuentas_bancarias', [
            'nombre_titular' => $input['nombre_titular'],
            'banco' => $input['banco'],
            'nro_cuenta' => $input['nro_cuenta'],
            'estado' => 1
        ]);
        echo json_encode(['status' => 'success', 'id' => $this->db->insert_id()]);
    }

    /**
     * POST /api/finanzas/crear_metodo
     * Crea un nuevo método de pago (opcionalmente enlazado a una cuenta)
     */
    public function crear_metodo() {
        $usuario = $this->verificar_token();
        if (!$usuario || $usuario['rol'] !== 'ADMIN') {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);
        if (empty($input['nombre'])) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'El nombre del método de pago es obligatorio']);
            return;
        }

        $codigo = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '_', $input['nombre']));

        $data = [
            'codigo' => $codigo,
            'nombre' => $input['nombre'],
            'requiere_banco' => isset($input['requiere_banco']) ? $input['requiere_banco'] : 0,
            'permite_combinado' => isset($input['permite_combinado']) ? $input['permite_combinado'] : 0,
            'cuenta_id' => !empty($input['cuenta_id']) ? $input['cuenta_id'] : null,
            'estado' => 1
        ];

        // check if codigo already exists
        $this->db->where('codigo', $codigo);
        if ($this->db->count_all_results('finanzas_metodos_pago') > 0) {
            $data['codigo'] = $codigo . '_' . time();
        }

        $this->db->insert('finanzas_metodos_pago', $data);
        echo json_encode(['status' => 'success', 'id' => $this->db->insert_id()]);
    }

    /**
     * POST /api/finanzas/editar_cuenta
     */
    public function editar_cuenta() {
        $usuario = $this->verificar_token();
        if (!$usuario || $usuario['rol'] !== 'ADMIN') {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);
        if (empty($input['id']) || empty($input['nombre_titular']) || empty($input['banco']) || empty($input['nro_cuenta'])) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'ID, Nombre, banco y número de cuenta son obligatorios']);
            return;
        }

        $this->db->where('id', $input['id']);
        $this->db->update('finanzas_cuentas_bancarias', [
            'nombre_titular' => $input['nombre_titular'],
            'banco' => $input['banco'],
            'nro_cuenta' => $input['nro_cuenta'],
            'estado' => isset($input['estado']) ? (int)$input['estado'] : 1
        ]);
        echo json_encode(['status' => 'success']);
    }

    /**
     * POST /api/finanzas/editar_metodo
     */
    public function editar_metodo() {
        $usuario = $this->verificar_token();
        if (!$usuario || $usuario['rol'] !== 'ADMIN') {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);
        if (empty($input['id']) || empty($input['nombre'])) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'ID y Nombre son obligatorios']);
            return;
        }

        $data = [
            'nombre' => $input['nombre'],
            'cuenta_id' => !empty($input['cuenta_id']) ? $input['cuenta_id'] : null,
            'estado' => isset($input['estado']) ? (int)$input['estado'] : 1
        ];

        $this->db->where('id', $input['id']);
        $this->db->update('finanzas_metodos_pago', $data);
        echo json_encode(['status' => 'success']);
    }
}
