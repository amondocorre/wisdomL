<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Clientes extends CI_Controller {

    public function __construct() {
        parent::__construct();
        
        // Habilitar CORS
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, Origin, Accept");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit(0);
        }

        $this->load->library('JWT');
        $this->output->set_content_type('application/json');
    }


    /**
     * POST /api/clientes/subir_foto
     */
    public function subir_foto() {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, Origin, Accept");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");

        $upload_path = FCPATH . 'uploads/clientes/';
        if (!is_dir($upload_path)) {
            mkdir($upload_path, 0777, true);
        }

        if (empty($_FILES['file']['name'])) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'No se ha seleccionado ningún archivo']);
            return;
        }

        $filename = $_FILES['file']['name'];
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $new_name = 'cliente_' . uniqid() . '.' . $ext;

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
        $file_url = 'uploads/clientes/' . $upload_data['file_name'];

        echo json_encode([
            'status' => 'success',
            'file_url' => $file_url,
            'mensaje' => 'Foto subida exitosamente'
        ]);
    }

    /**
     * POST /api/clientes/crear
     */
    public function crear() {
        // Validar Token JWT
        $headers = $this->input->request_headers();
        $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
        $usuario = JWT::decode($token);

        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'Sesión no válida o expirada']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);

        if (empty($input['tipo_cliente'])) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'El tipo de cliente es requerido.']);
            return;
        }


        $tipo = $input['tipo_cliente'];
        $nit_ci = $input['nit_ci'];
        $email = isset($input['email']) ? $input['email'] : null;

        $this->db->trans_start();

        // 1. Preparar campos de clientes generales
        $cliente_data = [
            'tipo_cliente' => $tipo,
            'nit_ci' => $nit_ci,
            'email' => $email,
            'ciudad' => isset($input['ciudad']) ? $input['ciudad'] : null,
            'zona_operacion_nombre' => isset($input['zona_operacion_nombre']) ? $input['zona_operacion_nombre'] : null,
            'comercial_foto_url' => isset($input['comercial_foto_url']) ? $input['comercial_foto_url'] : null,
            'comercial_fecha_cumpleanos' => !empty($input['comercial_fecha_cumpleanos']) ? $input['comercial_fecha_cumpleanos'] : null,
            'activo' => isset($input['activo']) ? (int)$input['activo'] : 1
        ];


        // 2. Mapear campos dinámicos por Tipo de Cliente
        if ($tipo === 'COMERCIAL') {
            $cliente_data['comercial_nombre_comercial'] = isset($input['comercial_nombre_comercial']) ? $input['comercial_nombre_comercial'] : null;
            $cliente_data['comercial_nombre_contacto'] = isset($input['comercial_nombre_contacto']) ? $input['comercial_nombre_contacto'] : null;
            $cliente_data['comercial_celular'] = isset($input['comercial_celular']) ? $input['comercial_celular'] : null;
            $cliente_data['comercial_direccion_principal'] = isset($input['comercial_direccion_principal']) ? $input['comercial_direccion_principal'] : null;
            $cliente_data['comercial_latitud'] = isset($input['comercial_latitud']) ? (float)$input['comercial_latitud'] : null;
            $cliente_data['comercial_longitud'] = isset($input['comercial_longitud']) ? (float)$input['comercial_longitud'] : null;
            $cliente_data['comercial_categoria'] = isset($input['comercial_categoria']) ? $input['comercial_categoria'] : 'NUEVO';
        } elseif ($tipo === 'CONSTRUCTORA') {
            $cliente_data['constructora_nombre'] = isset($input['constructora_nombre']) ? $input['constructora_nombre'] : null;
            $cliente_data['constructora_representante'] = isset($input['constructora_representante']) ? $input['constructora_representante'] : null;
            $cliente_data['constructora_telefono'] = isset($input['constructora_telefono']) ? $input['constructora_telefono'] : null;
        } elseif ($tipo === 'CLIENTE_FINAL') {
            $cliente_data['final_nombre_completo'] = isset($input['final_nombre_completo']) ? $input['final_nombre_completo'] : null;
            $cliente_data['final_direccion'] = isset($input['final_direccion']) ? $input['final_direccion'] : null;
            $cliente_data['final_telefono'] = isset($input['final_telefono']) ? $input['final_telefono'] : null;
        }

        // Insertar Cliente Principal
        $this->db->insert('clientes', $cliente_data);
        $cliente_id = $this->db->insert_id();

        // 3. Procesar tablas hijas correspondientes
        if ($tipo === 'COMERCIAL' && !empty($input['vendedores']) && is_array($input['vendedores'])) {
            foreach ($input['vendedores'] as $v) {
                if (!empty($v['nombre_vendedor']) && !empty($v['celular'])) {
                    $this->db->insert('cliente_comercial_vendedores', [
                        'cliente_id' => $cliente_id,
                        'nombre_vendedor' => $v['nombre_vendedor'],
                        'celular' => $v['celular']
                    ]);
                }
            }
        } elseif ($tipo === 'CONSTRUCTORA' && !empty($input['obras']) && is_array($input['obras'])) {
            foreach ($input['obras'] as $o) {
                if (!empty($o['nombre_obra']) && !empty($o['direccion_obra'])) {
                    $this->db->insert('constructora_obras', [
                        'cliente_id' => $cliente_id,
                        'nombre_obra' => $o['nombre_obra'],
                        'direccion_obra' => $o['direccion_obra'],
                        'latitud_gps' => isset($o['latitud_gps']) ? (float)$o['latitud_gps'] : null,
                        'longitud_gps' => isset($o['longitud_gps']) ? (float)$o['longitud_gps'] : null,
                        'contacto_residente_obra' => isset($o['contacto_residente_obra']) ? $o['contacto_residente_obra'] : null
                    ]);
                }
            }
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            $this->output->set_status_header(500);
            echo json_encode(['error' => 'No se pudo crear el registro del cliente y sus datos relacionados.']);
            return;
        }

        echo json_encode([
            'status' => 'success',
            'cliente_id' => $cliente_id,
            'mensaje' => 'Cliente registrado exitosamente'
        ]);
    }

    /**
     * POST /api/clientes/actualizar
     */
    public function actualizar() {
        $headers = $this->input->request_headers();
        $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
        $usuario = JWT::decode($token);

        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);

        if (empty($input['id']) || empty($input['tipo_cliente'])) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'ID y tipo de cliente requeridos']);
            return;
        }

        $cliente_id = $input['id'];
        $tipo = $input['tipo_cliente'];
        $nit_ci = isset($input['nit_ci']) ? $input['nit_ci'] : null;
        $email = isset($input['email']) ? $input['email'] : null;
        $ciudad = isset($input['ciudad']) ? $input['ciudad'] : null;

        $this->db->trans_start();

        $cliente_data = [
            'tipo_cliente' => $tipo,
            'nit_ci' => $nit_ci,
            'email' => $email,
            'ciudad' => $ciudad,
            'zona_operacion_nombre' => isset($input['zona_operacion_nombre']) ? $input['zona_operacion_nombre'] : null,
            'comercial_foto_url' => isset($input['comercial_foto_url']) ? $input['comercial_foto_url'] : null,
            'comercial_fecha_cumpleanos' => !empty($input['comercial_fecha_cumpleanos']) ? $input['comercial_fecha_cumpleanos'] : null,
            'activo' => isset($input['activo']) ? (int)$input['activo'] : 1
        ];

        if ($tipo === 'COMERCIAL') {
            $cliente_data['comercial_nombre_comercial'] = isset($input['comercial_nombre_comercial']) ? $input['comercial_nombre_comercial'] : null;
            $cliente_data['comercial_nombre_contacto'] = isset($input['comercial_nombre_contacto']) ? $input['comercial_nombre_contacto'] : null;
            $cliente_data['comercial_celular'] = isset($input['comercial_celular']) ? $input['comercial_celular'] : null;
            $cliente_data['comercial_direccion_principal'] = isset($input['comercial_direccion_principal']) ? $input['comercial_direccion_principal'] : null;
            $cliente_data['comercial_latitud'] = isset($input['comercial_latitud']) ? $input['comercial_latitud'] : null;
            $cliente_data['comercial_longitud'] = isset($input['comercial_longitud']) ? $input['comercial_longitud'] : null;
            $cliente_data['comercial_categoria'] = isset($input['comercial_categoria']) ? $input['comercial_categoria'] : 'NUEVO';
        } else if ($tipo === 'CONSTRUCTORA') {
            $cliente_data['constructora_nombre'] = isset($input['constructora_nombre']) ? $input['constructora_nombre'] : null;
            $cliente_data['constructora_representante'] = isset($input['constructora_representante']) ? $input['constructora_representante'] : null;
            $cliente_data['constructora_telefono'] = isset($input['constructora_telefono']) ? $input['constructora_telefono'] : null;
        } else if ($tipo === 'CLIENTE_FINAL') {
            $cliente_data['final_nombre_completo'] = isset($input['final_nombre_completo']) ? $input['final_nombre_completo'] : null;
            $cliente_data['final_direccion'] = isset($input['final_direccion']) ? $input['final_direccion'] : null;
            $cliente_data['final_telefono'] = isset($input['final_telefono']) ? $input['final_telefono'] : null;
        }

        $this->db->where('id', $cliente_id);
        $this->db->update('clientes', $cliente_data);

        // Limpiar y re-insertar vendedores u obras asociados
        if ($tipo === 'COMERCIAL') {
            $this->db->delete('cliente_comercial_vendedores', ['cliente_id' => $cliente_id]);
            if (!empty($input['vendedores'])) {
                foreach ($input['vendedores'] as $vend) {
                    if (!empty($vend['nombre_vendedor'])) {
                        $this->db->insert('cliente_comercial_vendedores', [
                            'cliente_id' => $cliente_id,
                            'nombre_vendedor' => $vend['nombre_vendedor'],
                            'celular' => isset($vend['celular']) ? $vend['celular'] : ''
                        ]);
                    }
                }
            }
        } else if ($tipo === 'CONSTRUCTORA') {
            $this->db->delete('constructora_obras', ['cliente_id' => $cliente_id]);
            if (!empty($input['obras'])) {
                foreach ($input['obras'] as $ob) {
                    if (!empty($ob['nombre_obra'])) {
                        $this->db->insert('constructora_obras', [
                            'cliente_id' => $cliente_id,
                            'nombre_obra' => $ob['nombre_obra'],
                            'direccion_obra' => isset($ob['direccion_obra']) ? $ob['direccion_obra'] : '',
                            'latitud_gps' => isset($ob['latitud_gps']) ? $ob['latitud_gps'] : null,
                            'longitud_gps' => isset($ob['longitud_gps']) ? $ob['longitud_gps'] : null,
                            'contacto_residente_obra' => isset($ob['contacto_residente_obra']) ? $ob['contacto_residente_obra'] : ''
                        ]);
                    }
                }
            }
        }


        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            $this->output->set_status_header(500);
            echo json_encode(['error' => 'No se pudo actualizar la información del cliente.']);
            return;
        }

        echo json_encode([
            'status' => 'success',
            'mensaje' => 'Cliente actualizado exitosamente'
        ]);
    }


    /**
     * PUT /api/clientes/cambiar_categoria
     */
    public function cambiar_categoria() {
        $headers = $this->input->request_headers();
        $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
        $usuario = JWT::decode($token);

        if (!$usuario || !in_array($usuario['rol'], ['ADMIN', 'AUTORIZADOR'])) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado para cambiar categorías de clientes comerciales.']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);

        if (empty($input['cliente_id']) || empty($input['categoria'])) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'Datos incompletos']);
            return;
        }

        $cliente_id = $input['cliente_id'];
        $categoria = $input['categoria']; // VIP, MAYORISTA, MINORISTA, NUEVO

        // Validar que sea comercial
        $cliente = $this->db->get_where('clientes', ['id' => $cliente_id])->row();
        if (!$cliente || $cliente->tipo_cliente !== 'COMERCIAL') {
            $this->output->set_status_header(404);
            echo json_encode(['error' => 'Cliente comercial no encontrado']);
            return;
        }

        $this->db->where('id', $cliente_id);
        $this->db->update('clientes', ['comercial_categoria' => $categoria]);

        echo json_encode([
            'status' => 'success',
            'mensaje' => 'Categoría de cliente comercial actualizada correctamente a ' . $categoria
        ]);
    }

    /**
     * GET /api/clientes/listar
     */
    public function listar() {
        $headers = $this->input->request_headers();
        $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
        $usuario = JWT::decode($token);

        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $query = $this->db->get('clientes');
        $clientes = $query->result_array();

        // Cargar información relacionada para cada cliente
        foreach ($clientes as &$c) {
            $c['vendedores'] = array();
            $c['obras'] = array();

            if ($c['tipo_cliente'] === 'COMERCIAL') {
                $c['vendedores'] = $this->db->get_where('cliente_comercial_vendedores', ['cliente_id' => $c['id']])->result_array();
            } else if ($c['tipo_cliente'] === 'CONSTRUCTORA') {
                $c['obras'] = $this->db->get_where('constructora_obras', ['cliente_id' => $c['id']])->result_array();
            }

        }

        echo json_encode($clientes);
    }

    /**
     * GET /api/clientes/listar_zonas
     */
    public function listar_zonas() {
        $headers = $this->input->request_headers();
        $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
        $usuario = JWT::decode($token);

        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $zonas = $this->db->get('zonas_operacion')->result_array();
        echo json_encode($zonas);
    }

    /**
     * POST /api/clientes/crear_zona
     */
    public function crear_zona() {
        $headers = $this->input->request_headers();
        $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
        $usuario = JWT::decode($token);

        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);
        if (empty($input['ciudad_nombre']) || empty($input['nombre_zona'])) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'La ciudad y el nombre de la zona son obligatorios']);
            return;
        }

        $data = [
            'ciudad_nombre' => $input['ciudad_nombre'],
            'nombre_zona' => $input['nombre_zona'],
            'activo' => isset($input['activo']) ? (int)$input['activo'] : 1
        ];

        $this->db->insert('zonas_operacion', $data);
        echo json_encode(['status' => 'success', 'id' => $this->db->insert_id(), 'mensaje' => 'Zona de operación creada']);
    }

    /**
     * POST /api/clientes/actualizar_zona
     */
    public function actualizar_zona() {
        $headers = $this->input->request_headers();
        $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
        $usuario = JWT::decode($token);

        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);
        if (empty($input['id']) || empty($input['ciudad_nombre']) || empty($input['nombre_zona'])) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'ID, ciudad y nombre de la zona requeridos']);
            return;
        }

        $data = [
            'ciudad_nombre' => $input['ciudad_nombre'],
            'nombre_zona' => $input['nombre_zona'],
            'activo' => isset($input['activo']) ? (int)$input['activo'] : 1
        ];

        $this->db->where('id', $input['id']);
        $this->db->update('zonas_operacion', $data);

        echo json_encode(['status' => 'success', 'mensaje' => 'Zona de operación actualizada']);
    }
}


