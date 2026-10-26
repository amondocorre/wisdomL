<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Roles extends CI_Controller {

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
     * GET /api/roles/listar
     */
    public function listar() {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'Sesión inválida o no autorizada']);
            return;
        }

        $this->db->order_by('id', 'ASC');
        $query = $this->db->get('roles');
        $roles = $query->result_array();

        foreach ($roles as &$r) {
            $r['activo'] = (int)$r['activo'];
            // Contar cuántos usuarios están asignados a este rol
            $this->db->where('rol', $r['nombre']);
            $r['cantidad_usuarios'] = $this->db->count_all_results('usuarios');

            // Contar menús con permiso de acceso visual
            $this->db->where('rol_id', $r['id']);
            $this->db->where('puede_ver', 1);
            $r['cantidad_menus'] = $this->db->count_all_results('rol_permisos');
            if (strtoupper($r['nombre']) === 'ADMIN' && $r['cantidad_menus'] === 0) {
                $r['cantidad_menus'] = $this->db->count_all('menus');
            }
        }

        echo json_encode($roles);
    }

    /**
     * POST /api/roles/crear
     */
    public function crear() {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'Sesión inválida o no autorizada']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);

        if (empty($input['nombre'])) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'El nombre del rol es obligatorio']);
            return;
        }

        $nombre = strtoupper(trim($input['nombre']));
        $descripcion = isset($input['descripcion']) ? trim($input['descripcion']) : '';

        // Verificar si ya existe
        $existe = $this->db->get_where('roles', ['nombre' => $nombre])->row_array();
        if ($existe) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'El rol ingresado ya existe en la base de datos']);
            return;
        }

        $datos = [
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'activo' => 1
        ];

        $this->db->insert('roles', $datos);

        echo json_encode([
            'status' => 'success',
            'mensaje' => 'Rol creado exitosamente',
            'id' => $this->db->insert_id()
        ]);
    }

    /**
     * POST /api/roles/editar
     */
    public function editar() {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'Sesión inválida o no autorizada']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);

        if (empty($input['id']) || empty($input['nombre'])) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'ID y Nombre del rol son requeridos']);
            return;
        }

        $id = (int)$input['id'];
        $nombreNuevo = strtoupper(trim($input['nombre']));
        $descripcion = isset($input['descripcion']) ? trim($input['descripcion']) : '';

        $rolActual = $this->db->get_where('roles', ['id' => $id])->row_array();
        if (!$rolActual) {
            $this->output->set_status_header(404);
            echo json_encode(['error' => 'El rol especificado no existe']);
            return;
        }

        // Verificar si el nuevo nombre choca con otro rol
        $existeOtro = $this->db->where('id !=', $id)->where('nombre', $nombreNuevo)->get('roles')->row_array();
        if ($existeOtro) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'Ya existe otro rol registrado con ese mismo nombre']);
            return;
        }

        $datos = [
            'nombre' => $nombreNuevo,
            'descripcion' => $descripcion
        ];

        $this->db->where('id', $id);
        $this->db->update('roles', $datos);

        // Si cambió el nombre del rol, actualizar en cascada a los usuarios asignados
        if ($rolActual['nombre'] !== $nombreNuevo) {
            $this->db->where('rol', $rolActual['nombre']);
            $this->db->update('usuarios', ['rol' => $nombreNuevo]);
        }

        echo json_encode([
            'status' => 'success',
            'mensaje' => 'Rol actualizado exitosamente'
        ]);
    }

    /**
     * POST /api/roles/toggle_estado
     */
    public function toggle_estado() {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'Sesión inválida o no autorizada']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);

        if (empty($input['id'])) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'ID de rol requerido']);
            return;
        }

        $id = (int)$input['id'];
        $rol = $this->db->get_where('roles', ['id' => $id])->row_array();
        if (!$rol) {
            $this->output->set_status_header(404);
            echo json_encode(['error' => 'El rol no existe']);
            return;
        }

        $nuevoEstado = ((int)$rol['activo'] === 1) ? 0 : 1;

        $this->db->where('id', $id);
        $this->db->update('roles', ['activo' => $nuevoEstado]);

        echo json_encode([
            'status' => 'success',
            'activo' => $nuevoEstado,
            'mensaje' => 'Estado del rol actualizado correctamente'
        ]);
    }

    /**
     * GET /api/roles/menus
     * Obtiene el catálogo completo de menús y módulos del sistema
     */
    public function menus()
    {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'Sesión inválida o no autorizada']);
            return;
        }

        $this->db->order_by('orden', 'ASC');
        $this->db->order_by('id', 'ASC');
        $menus = $this->db->get_where('menus', ['activo' => 1])->result_array();

        echo json_encode($menus);
    }

    /**
     * GET /api/roles/permisos?rol_id=X
     * Obtiene la matriz completa de permisos (ver y editar) de un rol
     */
    public function permisos()
    {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'Sesión inválida o no autorizada']);
            return;
        }

        $roleId = (int)$this->input->get('rol_id');
        if ($roleId <= 0) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'ID de rol inválido']);
            return;
        }

        $role = $this->db->get_where('roles', ['id' => $roleId])->row_array();
        if (!$role) {
            $this->output->set_status_header(404);
            echo json_encode(['error' => 'El rol especificado no existe']);
            return;
        }

        // Obtener todos los menús activos
        $this->db->order_by('orden', 'ASC');
        $this->db->order_by('id', 'ASC');
        $menus = $this->db->get_where('menus', ['activo' => 1])->result_array();

        // Obtener permisos configurados para este rol
        $permisosGuardados = $this->db->get_where('rol_permisos', ['rol_id' => $roleId])->result_array();
        $mapaPermisos = [];
        foreach ($permisosGuardados as $p) {
            $mapaPermisos[$p['menu_id']] = [
                'puede_ver' => (int)$p['puede_ver'],
                'puede_editar' => (int)$p['puede_editar']
            ];
        }

        // Construir resultado unificado
        $isAdmin = (strtoupper($role['nombre']) === 'ADMIN');
        $resultado = [];

        foreach ($menus as $m) {
            $menuId = $m['id'];
            $puedeVer = 0;
            $puedeEditar = 0;

            if (isset($mapaPermisos[$menuId])) {
                $puedeVer = $mapaPermisos[$menuId]['puede_ver'];
                $puedeEditar = $mapaPermisos[$menuId]['puede_editar'];
            } elseif ($isAdmin) {
                $puedeVer = 1;
                $puedeEditar = 1;
            }

            $resultado[] = [
                'menu_id' => $menuId,
                'nombre' => $m['nombre'],
                'descripcion' => $m['descripcion'],
                'categoria' => $m['categoria'],
                'icono' => $m['icono'],
                'orden' => (int)$m['orden'],
                'puede_ver' => $puedeVer,
                'puede_editar' => $puedeEditar
            ];
        }

        echo json_encode([
            'rol' => [
                'id' => (int)$role['id'],
                'nombre' => $role['nombre'],
                'descripcion' => $role['descripcion'],
                'activo' => (int)$role['activo']
            ],
            'permisos' => $resultado
        ]);
    }

    /**
     * POST /api/roles/guardar_permisos
     * Guarda la configuración de permisos (ver y editar) para un rol
     */
    public function guardar_permisos()
    {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'Sesión inválida o no autorizada']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);

        if (empty($input['rol_id']) || !isset($input['permisos']) || !is_array($input['permisos'])) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'Datos incompletos o formato inválido']);
            return;
        }

        $roleId = (int)$input['rol_id'];
        $role = $this->db->get_where('roles', ['id' => $roleId])->row_array();
        if (!$role) {
            $this->output->set_status_header(404);
            echo json_encode(['error' => 'El rol especificado no existe']);
            return;
        }

        // Obtener menús válidos para validar integridad referencial
        $menusValidos = $this->db->select('id')->get('menus')->result_array();
        $validMenuIds = array_column($menusValidos, 'id');

        $this->db->trans_start();

        foreach ($input['permisos'] as $item) {
            if (empty($item['menu_id']) || !in_array($item['menu_id'], $validMenuIds)) {
                continue;
            }

            $menuId = $item['menu_id'];
            $canView = !empty($item['puede_ver']) ? 1 : 0;
            // Si no puede ver, tampoco puede editar
            $canEdit = ($canView && !empty($item['puede_editar'])) ? 1 : 0;

            // Comprobar existencia previa
            $this->db->where('rol_id', $roleId);
            $this->db->where('menu_id', $menuId);
            $existe = $this->db->get('rol_permisos')->row_array();

            if ($existe) {
                $this->db->where('id', $existe['id']);
                $this->db->update('rol_permisos', [
                    'puede_ver' => $canView,
                    'puede_editar' => $canEdit
                ]);
            } else {
                $this->db->insert('rol_permisos', [
                    'rol_id' => $roleId,
                    'menu_id' => $menuId,
                    'puede_ver' => $canView,
                    'puede_editar' => $canEdit
                ]);
            }
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            $this->output->set_status_header(500);
            echo json_encode(['error' => 'No se pudieron guardar los permisos']);
            return;
        }

        echo json_encode([
            'status' => 'success',
            'mensaje' => "Permisos para el rol '{$role['nombre']}' actualizados exitosamente"
        ]);
    }

    /**
     * GET /api/roles/mis_permisos
     * Retorna los permisos de acceso del usuario actualmente autenticado
     */
    public function mis_permisos()
    {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'Sesión inválida o no autorizada']);
            return;
        }

        $rolNombre = isset($usuario->rol) ? strtoupper($usuario->rol) : '';
        $role = $this->db->get_where('roles', ['nombre' => $rolNombre])->row_array();

        // Obtener todos los menús activos
        $this->db->order_by('orden', 'ASC');
        $menus = $this->db->get_where('menus', ['activo' => 1])->result_array();

        $mapaPermisos = [];
        $menusPermitidos = [];
        $isAdmin = ($rolNombre === 'ADMIN');

        if ($role) {
            $permisos = $this->db->get_where('rol_permisos', ['rol_id' => $role['id']])->result_array();
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

        // Si es ADMIN o no tiene permisos asignados todavía, dar acceso total
        if ($isAdmin || empty($mapaPermisos)) {
            foreach ($menus as $m) {
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

        echo json_encode([
            'status' => 'success',
            'rol' => $rolNombre,
            'permisos' => $mapaPermisos,
            'menus_permitidos' => $menusPermitidos
        ]);
    }
}

