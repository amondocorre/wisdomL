<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cajas extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->model('Caja_model');
        $this->load->library('JWT');
        $this->output->set_content_type('application/json');

        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, Origin, Accept");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit(0);
        }
    }

    private function verificar_token() {
        $headers = $this->input->request_headers();
        $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
        return JWT::decode($token);
    }

    /**
     * GET /api/cajas/estado
     */
    public function estado() {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $caja = $this->Caja_model->obtener_caja_activa($usuario['id']);
        if ($caja) {
            $resumen = $this->Caja_model->obtener_resumen_caja($caja['id']);
            echo json_encode([
                'activo' => true,
                'caja' => $caja,
                'resumen' => $resumen
            ]);
        } else {
            echo json_encode([
                'activo' => false,
                'message' => 'No hay caja abierta para este usuario.'
            ]);
        }
    }

    /**
     * POST /api/cajas/abrir
     */
    public function abrir() {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);
        $monto_apertura = isset($input['monto_apertura']) ? (float)$input['monto_apertura'] : 0.00;
        $ciudad_id = !empty($input['ciudad_id']) ? (int)$input['ciudad_id'] : null;

        $res = $this->Caja_model->abrir_caja($usuario['id'], $monto_apertura, $ciudad_id);
        if ($res['status']) {
            echo json_encode(['status' => 'success', 'caja_id' => $res['id']]);
        } else {
            $this->output->set_status_header(400);
            echo json_encode(['error' => $res['message']]);
        }
    }

    /**
     * POST /api/cajas/cerrar
     */
    public function cerrar() {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);
        $monto_cierre = isset($input['monto_cierre']) ? (float)$input['monto_cierre'] : 0.00;

        $res = $this->Caja_model->cerrar_caja($usuario['id'], $monto_cierre);
        if ($res['status']) {
            echo json_encode([
                'status' => 'success',
                'reporte' => isset($res['reporte']) ? $res['reporte'] : null
            ]);
        } else {
            $this->output->set_status_header(400);
            echo json_encode(['error' => $res['message']]);
        }
    }

    /**
     * POST /api/cajas/registrar_movimiento
     * Soporta multipart/form-data o JSON para guardar monto, concepto, descripcion y foto comprobante
     */
    public function registrar_movimiento() {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $caja = $this->Caja_model->obtener_caja_activa($usuario['id']);
        if (!$caja) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'Debes tener una caja abierta para registrar movimientos.']);
            return;
        }

        // Obtener datos desde POST (form-data) o JSON
        $tipo = $this->input->post('tipo');
        $concepto = $this->input->post('concepto');
        $monto = $this->input->post('monto');
        $descripcion = $this->input->post('descripcion');

        if (!$tipo || !$concepto || !$monto) {
            $raw = json_decode(trim(file_get_contents('php://input')), true);
            if ($raw) {
                $tipo = isset($raw['tipo']) ? $raw['tipo'] : $tipo;
                $concepto = isset($raw['concepto']) ? $raw['concepto'] : $concepto;
                $monto = isset($raw['monto']) ? $raw['monto'] : $monto;
                $descripcion = isset($raw['descripcion']) ? $raw['descripcion'] : $descripcion;
            }
        }

        $tipo = strtoupper(trim((string)$tipo));
        $concepto = strtoupper(trim((string)$concepto));
        $monto = (float)$monto;
        $descripcion = trim((string)$descripcion);

        if (!in_array($tipo, ['INGRESO', 'EGRESO']) || $monto <= 0) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'Tipo de movimiento o monto inválido.']);
            return;
        }

        // Procesar subida de archivo si existe
        $comprobante_url = '';
        if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
            $upload_path = FCPATH . 'uploads/comprobantes_caja/';
            if (!file_exists($upload_path)) {
                mkdir($upload_path, 0777, true);
            }

            $ext = pathinfo($_FILES['comprobante']['name'], PATHINFO_EXTENSION);
            $ext = strtolower($ext ? $ext : 'jpg');
            $nuevo_nombre = 'comp_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $target_file = $upload_path . $nuevo_nombre;

            if (move_uploaded_file($_FILES['comprobante']['tmp_name'], $target_file)) {
                $comprobante_url = 'uploads/comprobantes_caja/' . $nuevo_nombre;
            }
        }

        $res = $this->Caja_model->registrar_movimiento(
            $caja['id'],
            $usuario['id'],
            $tipo,
            $concepto,
            $monto,
            $descripcion,
            $comprobante_url
        );

        if ($res['status']) {
            echo json_encode([
                'status' => 'success',
                'id' => $res['id'],
                'comprobante_url' => $comprobante_url,
                'resumen' => $this->Caja_model->obtener_resumen_caja($caja['id'])
            ]);
        } else {
            $this->output->set_status_header(400);
            echo json_encode(['error' => $res['message']]);
        }
    }

    /**
     * GET /api/cajas/movimientos
     */
    public function movimientos() {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $caja = $this->Caja_model->obtener_caja_activa($usuario['id']);
        if (!$caja) {
            echo json_encode([]);
            return;
        }

        $movs = $this->Caja_model->obtener_movimientos_caja($caja['id']);
        echo json_encode($movs);
    }

    /**
     * GET /api/cajas/historial
     */
    public function historial() {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $filtros = [
            'usuario_id' => $this->input->get('usuario_id'),
            'ciudad_id' => $this->input->get('ciudad_id'),
            'estado' => $this->input->get('estado'),
            'fecha_desde' => $this->input->get('fecha_desde'),
            'fecha_hasta' => $this->input->get('fecha_hasta')
        ];

        $historial = $this->Caja_model->obtener_historial_cajas($filtros);
        echo json_encode($historial);
    }
}
