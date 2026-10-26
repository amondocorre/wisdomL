<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Usuarios extends CI_Controller {

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
        } elseif (function_exists('apache_request_headers')) {
            $apacheHeaders = apache_request_headers();
            if (isset($apacheHeaders['Authorization'])) {
                $tokenHeader = $apacheHeaders['Authorization'];
            } elseif (isset($apacheHeaders['authorization'])) {
                $tokenHeader = $apacheHeaders['authorization'];
            }
        }

        $token = $tokenHeader ? trim(str_ireplace('Bearer', '', $tokenHeader)) : null;
        return JWT::decode($token);
    }

    /**
     * GET /api/usuarios/listar
     */
    public function listar() {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'Sesión inválida o usuario desactivado']);
            return;
        }

        $this->db->select('u.id, u.nombre, u.email, u.rol, u.activo, u.modificado_por, u.modificado_en, u.creado_en, u.foto, u.ubicacion_gps, u.descripcion_direccion, u.telefono, u.observaciones, u.ci, u.expedido, u.genero, u.fecha_nacimiento, u.fecha_ingreso, u.numero_cuenta, u_mod.nombre AS modificado_por_nombre');
        $this->db->from('usuarios u');
        $this->db->join('usuarios u_mod', 'u_mod.id = u.modificado_por', 'left');
        $query = $this->db->get();
        $usuarios = $query->result_array();

        foreach ($usuarios as &$u) {
            $u['activo'] = (int)$u['activo'];
            
            // Ciudades asignadas
            $this->db->select('ciudad_id');
            $this->db->from('usuario_ciudad');
            $this->db->where('usuario_id', $u['id']);
            $rel_ciudades = $this->db->get()->result_array();
            $u['ciudades'] = array_map(function($r) { return (int)$r['ciudad_id']; }, $rel_ciudades);

            // Almacenes asignados
            $this->db->select('ua.almacen_id, a.nombre as almacen_nombre, a.ciudad_id');
            $this->db->from('usuario_almacen ua');
            $this->db->join('almacenes a', 'a.id = ua.almacen_id');
            $this->db->where('ua.usuario_id', $u['id']);
            $rel_almacenes = $this->db->get()->result_array();
            $u['almacenes'] = array_map(function($r) { return (int)$r['almacen_id']; }, $rel_almacenes);
            $u['detalle_almacenes'] = $rel_almacenes;
        }

        echo json_encode($usuarios);
    }

    /**
     * POST /api/usuarios/crear
     */
    public function crear() {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'Sesión inválida o usuario desactivado']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);

        if (empty($input['nombre']) || empty($input['email']) || empty($input['password']) || empty($input['rol'])) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'Nombre, email, contraseña y rol son obligatorios']);
            return;
        }

        $existe = $this->db->get_where('usuarios', ['email' => $input['email']])->row_array();
        if ($existe) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'El correo electrónico ya se encuentra registrado']);
            return;
        }

        $user_data = [
            'nombre' => $input['nombre'],
            'email' => $input['email'],
            'password' => password_hash($input['password'], PASSWORD_BCRYPT),
            'rol' => $input['rol'],
            'activo' => isset($input['activo']) ? (int)$input['activo'] : 1,
            'modificado_por' => (isset($usuario) && isset($usuario['id'])) ? $usuario['id'] : null,
            'modificado_en' => date('Y-m-d H:i:s'),
            'foto' => isset($input['foto']) ? $input['foto'] : null,
            'ubicacion_gps' => isset($input['ubicacion_gps']) ? $input['ubicacion_gps'] : null,
            'descripcion_direccion' => isset($input['descripcion_direccion']) ? $input['descripcion_direccion'] : null,
            'telefono' => isset($input['telefono']) ? $input['telefono'] : null,
            'observaciones' => isset($input['observaciones']) ? $input['observaciones'] : null,
            'ci' => isset($input['ci']) ? $input['ci'] : null,
            'expedido' => isset($input['expedido']) ? $input['expedido'] : null,
            'genero' => isset($input['genero']) ? $input['genero'] : null,
            'fecha_nacimiento' => isset($input['fecha_nacimiento']) && !empty($input['fecha_nacimiento']) ? $input['fecha_nacimiento'] : null,
            'fecha_ingreso' => isset($input['fecha_ingreso']) && !empty($input['fecha_ingreso']) ? $input['fecha_ingreso'] : null,
            'numero_cuenta' => isset($input['numero_cuenta']) ? $input['numero_cuenta'] : null
        ];

        $this->db->insert('usuarios', $user_data);
        $user_id = $this->db->insert_id();

        if (!empty($input['ciudades']) && is_array($input['ciudades'])) {
            foreach ($input['ciudades'] as $ciudad_id) {
                $this->db->insert('usuario_ciudad', [
                    'usuario_id' => $user_id,
                    'ciudad_id' => (int)$ciudad_id
                ]);
            }
        }

        if (!empty($input['almacenes']) && is_array($input['almacenes'])) {
            foreach ($input['almacenes'] as $almacen_id) {
                $this->db->insert('usuario_almacen', [
                    'usuario_id' => $user_id,
                    'almacen_id' => (int)$almacen_id
                ]);
            }
        }

        echo json_encode([
            'status' => 'success',
            'id' => $user_id,
            'mensaje' => 'Usuario registrado exitosamente'
        ]);
    }

    /**
     * POST /api/usuarios/actualizar
     */
    public function actualizar() {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'Sesión inválida o usuario desactivado']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);

        if (empty($input['id']) || empty($input['nombre']) || empty($input['email']) || empty($input['rol'])) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'ID, Nombre, email y rol son requeridos']);
            return;
        }

        $id = $input['id'];

        $this->db->where('email', $input['email']);
        $this->db->where('id !=', $id);
        $existe = $this->db->get('usuarios')->row_array();
        if ($existe) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'El correo electrónico ya está en uso por otro usuario']);
            return;
        }

        $user_data = [
            'nombre' => $input['nombre'],
            'email' => $input['email'],
            'rol' => $input['rol'],
            'activo' => isset($input['activo']) ? (int)$input['activo'] : 1,
            'modificado_por' => (isset($usuario) && isset($usuario['id'])) ? $usuario['id'] : null,
            'modificado_en' => date('Y-m-d H:i:s'),
            'foto' => isset($input['foto']) ? $input['foto'] : null,
            'ubicacion_gps' => isset($input['ubicacion_gps']) ? $input['ubicacion_gps'] : null,
            'descripcion_direccion' => isset($input['descripcion_direccion']) ? $input['descripcion_direccion'] : null,
            'telefono' => isset($input['telefono']) ? $input['telefono'] : null,
            'observaciones' => isset($input['observaciones']) ? $input['observaciones'] : null,
            'ci' => isset($input['ci']) ? $input['ci'] : null,
            'expedido' => isset($input['expedido']) ? $input['expedido'] : null,
            'genero' => isset($input['genero']) ? $input['genero'] : null,
            'fecha_nacimiento' => isset($input['fecha_nacimiento']) && !empty($input['fecha_nacimiento']) ? $input['fecha_nacimiento'] : null,
            'fecha_ingreso' => isset($input['fecha_ingreso']) && !empty($input['fecha_ingreso']) ? $input['fecha_ingreso'] : null,
            'numero_cuenta' => isset($input['numero_cuenta']) ? $input['numero_cuenta'] : null
        ];

        if (!empty($input['password'])) {
            $user_data['password'] = password_hash($input['password'], PASSWORD_BCRYPT);
        }

        $this->db->where('id', $id);
        $this->db->update('usuarios', $user_data);

        // Actualizar Ciudades
        $this->db->delete('usuario_ciudad', ['usuario_id' => $id]);
        if (!empty($input['ciudades']) && is_array($input['ciudades'])) {
            foreach ($input['ciudades'] as $ciudad_id) {
                $this->db->insert('usuario_ciudad', [
                    'usuario_id' => $id,
                    'ciudad_id' => (int)$ciudad_id
                ]);
            }
        }

        // Actualizar Almacenes
        $this->db->delete('usuario_almacen', ['usuario_id' => $id]);
        if (!empty($input['almacenes']) && is_array($input['almacenes'])) {
            foreach ($input['almacenes'] as $almacen_id) {
                $this->db->insert('usuario_almacen', [
                    'usuario_id' => $id,
                    'almacen_id' => (int)$almacen_id
                ]);
            }
        }

        echo json_encode([
            'status' => 'success',
            'mensaje' => 'Usuario actualizado exitosamente'
        ]);
    }

    /**
     * POST /api/usuarios/subir_foto
     */
    public function subir_foto() {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, Origin, Accept");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");

        $upload_path = FCPATH . 'uploads/usuarios/';
        if (!is_dir($upload_path)) {
            mkdir($upload_path, 0777, true);
        }

        $filename = $_FILES['file']['name'];
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $new_name = 'user_' . uniqid() . '.' . $ext;

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
        $file_url = 'uploads/usuarios/' . $upload_data['file_name'];

        echo json_encode([
            'status' => 'success',
            'file_url' => $file_url,
            'mensaje' => 'Imagen subida exitosamente'
        ]);
    }
}
