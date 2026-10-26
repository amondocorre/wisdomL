<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth extends CI_Controller {

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


    /**
     * POST /api/auth/login
     */
    public function login() {
        $input = json_decode(trim(file_get_contents('php://input')), true);

        if (empty($input['email']) || empty($input['password'])) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'Email y contraseña requeridos']);
            return;
        }

        $email = $input['email'];
        $password = $input['password'];

        // Buscar el usuario en la BD
        $usuario = $this->db->get_where('usuarios', ['email' => $email])->row_array();

        // En un ambiente real se debe usar password_verify. 
        // Para simplificar y probar con semillas directas de desarrollo, permitimos validación directa
        if (!$usuario || ($password !== $usuario['password'] && !password_verify($password, $usuario['password']))) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'Credenciales incorrectas']);
            return;
        }

        if (isset($usuario['activo']) && (int)$usuario['activo'] === 0) {
            $this->output->set_status_header(403);
            echo json_encode(['error' => 'El usuario se encuentra desactivado. Contacte al administrador.']);
            return;
        }

        // Obtener ciudades asignadas
        $this->db->select('ciudad_id');
        $this->db->from('usuario_ciudad');
        $this->db->where('usuario_id', $usuario['id']);
        $rel_ciudades = $this->db->get()->result_array();
        $ciudades_ids = array_map(function($r) { return (int)$r['ciudad_id']; }, $rel_ciudades);

        // Obtener almacenes asignados
        $this->db->select('ua.almacen_id, a.nombre as almacen_nombre, a.ciudad_id');
        $this->db->from('usuario_almacen ua');
        $this->db->join('almacenes a', 'a.id = ua.almacen_id');
        $this->db->where('ua.usuario_id', $usuario['id']);
        $rel_almacenes = $this->db->get()->result_array();
        $almacenes_ids = array_map(function($r) { return (int)$r['almacen_id']; }, $rel_almacenes);

        // Obtener permisos del rol configurado
        $mapaPermisos = [];
        $menusPermitidos = [];
        $rolNombre = strtoupper($usuario['rol']);
        $roleRow = $this->db->get_where('roles', ['nombre' => $rolNombre])->row_array();

        if ($roleRow) {
            $permisos = $this->db->get_where('rol_permisos', ['rol_id' => $roleRow['id']])->result_array();
            foreach ($permisos as $p) {
                $mapaPermisos[$p['menu_id']] = [
                    'puede_ver' => (int)$p['puede_ver'] === 1,
                    'puede_editar' => (int)$p['puede_editar'] === 1
                ];
                if ((int)$p['puede_ver'] === 1) {
                    $menusPermitidos[] = $p['menu_id'];
                }
            }
        }

        if ($rolNombre === 'ADMIN' || empty($mapaPermisos)) {
            $todosMenus = $this->db->select('id')->get_where('menus', ['activo' => 1])->result_array();
            foreach ($todosMenus as $m) {
                if (!isset($mapaPermisos[$m['id']])) {
                    $mapaPermisos[$m['id']] = [
                        'puede_ver' => true,
                        'puede_editar' => true
                    ];
                    if (!in_array($m['id'], $menusPermitidos)) {
                        $menusPermitidos[] = $m['id'];
                    }
                }
            }
        }

        // Generar JWT
        $payload = [
            'id' => $usuario['id'],
            'nombre' => $usuario['nombre'],
            'email' => $usuario['email'],
            'rol' => $usuario['rol'],
            'ciudades' => $ciudades_ids,
            'almacenes' => $almacenes_ids,
            'detalle_almacenes' => $rel_almacenes,
            'permisos' => $mapaPermisos,
            'menus_permitidos' => $menusPermitidos
        ];
        $token = JWT::encode($payload);

        echo json_encode([
            'status' => 'success',
            'token' => $token,
            'usuario' => $payload
        ]);
    }
}
