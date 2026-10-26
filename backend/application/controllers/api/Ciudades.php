<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ciudades extends CI_Controller {

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
        $tokenHeader = null;
        if (isset($headers['Authorization'])) {
            $tokenHeader = $headers['Authorization'];
        } elseif (isset($headers['authorization'])) {
            $tokenHeader = $headers['authorization'];
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $tokenHeader = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $tokenHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        $token = $tokenHeader ? str_replace('Bearer ', '', $tokenHeader) : null;
        return JWT::decode($token);
    }

    /**
     * GET /api/ciudades/listar
     */
    public function listar() {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        // 1. Obtener Paises
        $paises = $this->db->get('logistica_paises')->result_array();

        // 2. Para cada país obtener ciudades
        foreach ($paises as &$p) {
            $this->db->where('pais_id', $p['id']);
            $ciudades = $this->db->get('ciudades')->result_array();

            // 3. Para cada ciudad obtener sus almacenes
            foreach ($ciudades as &$c) {
                $this->db->where('ciudad_id', $c['id']);
                $almacenes = $this->db->get('almacenes')->result_array();
                foreach ($almacenes as &$alm) {
                    $alm['estado'] = (int)$alm['estado'];
                }
                $c['almacenes'] = $almacenes;
            }
            $p['ciudades'] = $ciudades;
        }

        echo json_encode($paises);
    }

    /**
     * POST /api/ciudades/crear_pais
     */
    public function crear_pais() {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);
        if (empty($input['nombre'])) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'El nombre del país es obligatorio']);
            return;
        }

        $this->db->insert('logistica_paises', ['nombre' => $input['nombre']]);
        echo json_encode(['status' => 'success', 'id' => $this->db->insert_id()]);
    }

    /**
     * POST /api/ciudades/crear
     */
    public function crear() {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);
        if (empty($input['nombre']) || empty($input['pais_id'])) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'El nombre y el país son obligatorios']);
            return;
        }

        $this->db->insert('ciudades', [
            'nombre' => $input['nombre'],
            'pais_id' => $input['pais_id']
        ]);
        echo json_encode(['status' => 'success', 'id' => $this->db->insert_id()]);
    }

    /**
     * POST /api/ciudades/crear_almacen
     */
    public function crear_almacen() {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);
        if (empty($input['nombre']) || empty($input['ciudad_id'])) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'El nombre y la ciudad son obligatorios']);
            return;
        }

        $this->db->insert('almacenes', [
            'nombre' => $input['nombre'],
            'ciudad_id' => $input['ciudad_id'],
            'tipo' => isset($input['tipo']) ? $input['tipo'] : 'GALPON',
            'direccion' => isset($input['direccion']) ? $input['direccion'] : null,
            'telefono' => isset($input['telefono']) ? $input['telefono'] : null,
            'ubicacion_gps' => isset($input['ubicacion_gps']) ? $input['ubicacion_gps'] : null,
            'foto' => isset($input['foto']) ? $input['foto'] : null,
            'estado' => isset($input['estado']) ? (int)$input['estado'] : 1
        ]);
        echo json_encode(['status' => 'success', 'id' => $this->db->insert_id()]);
    }

    /**
     * POST /api/ciudades/editar_almacen
     */
    public function editar_almacen() {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);
        if (empty($input['id']) || empty($input['nombre']) || empty($input['ciudad_id'])) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'El ID, nombre y la ciudad son obligatorios']);
            return;
        }

        $this->db->where('id', $input['id']);
        $this->db->update('almacenes', [
            'nombre' => $input['nombre'],
            'ciudad_id' => $input['ciudad_id'],
            'tipo' => isset($input['tipo']) ? $input['tipo'] : 'GALPON',
            'direccion' => isset($input['direccion']) ? $input['direccion'] : null,
            'telefono' => isset($input['telefono']) ? $input['telefono'] : null,
            'ubicacion_gps' => isset($input['ubicacion_gps']) ? $input['ubicacion_gps'] : null,
            'foto' => isset($input['foto']) ? $input['foto'] : null,
            'estado' => isset($input['estado']) ? (int)$input['estado'] : 1
        ]);
        echo json_encode(['status' => 'success', 'id' => $input['id']]);
    }

    /**
     * POST /api/ciudades/subir_imagen_almacen
     */
    public function subir_imagen_almacen() {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, Origin, Accept");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");

        $upload_path = FCPATH . 'uploads/almacenes/';
        if (!is_dir($upload_path)) {
            mkdir($upload_path, 0777, true);
        }

        $filename = $_FILES['file']['name'];
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $new_name = 'almacen_' . uniqid() . '.' . $ext;

        $config['upload_path']   = $upload_path;
        $config['allowed_types'] = 'gif|jpg|jpeg|png|webp';
        $config['max_size']      = 4096; // 4MB
        $config['file_name']     = $new_name;

        $this->load->library('upload', $config);

        if (!$this->upload->do_upload('file')) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => $this->upload->display_errors('', '')]);
            return;
        }

        $upload_data = $this->upload->data();
        $file_url = 'uploads/almacenes/' . $upload_data['file_name'];

        echo json_encode([
            'status' => 'success',
            'file_url' => $file_url,
            'mensaje' => 'Imagen subida exitosamente'
        ]);
    }
}
