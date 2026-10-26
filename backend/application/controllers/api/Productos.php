<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Productos extends CI_Controller {

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
     * GET /api/productos/listar
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

        $this->db->select('p.*, cat_f.nombre as formato, cat_p.nombre as origen, cat_a.nombre as acabado, cat_u.descripcion as unidad_base_nombre, cat_u.abreviacion as unidad_base_abrev');
        $this->db->from('productos p');
        $this->db->join('cat_formatos cat_f', 'cat_f.id = p.formato_id', 'left');
        $this->db->join('cat_paises cat_p', 'cat_p.id = p.pais_id', 'left');
        $this->db->join('cat_acabados cat_a', 'cat_a.id = p.acabado_id', 'left');
        $this->db->join('cat_unidad_base cat_u', 'cat_u.id = p.unidad_base_id', 'left');
        
        $productos = $this->db->get()->result_array();

        if (count($productos) > 0) {
            $ids = array_column($productos, 'id');
            $this->db->select('pe.producto_id, pe.escala_id, cep.nombre as nombre_escala, cep.rango_min, cep.rango_max, cep.ordenado, cep.precio_cliente_final, pe.precio_caja_bob');
            $this->db->from('precios_escalonados pe');
            $this->db->join('config_escalas_precios cep', 'cep.id = pe.escala_id');
            $this->db->where_in('pe.producto_id', $ids);
            $this->db->order_by('pe.producto_id', 'ASC');
            $this->db->order_by('cep.ordenado', 'ASC');
            $this->db->order_by('cep.rango_min', 'ASC');
            $precios_esc = $this->db->get()->result_array();

            $mapa_precios = [];
            foreach ($precios_esc as $pe) {
                $mapa_precios[$pe['producto_id']][] = $pe;
            }

            foreach ($productos as &$p) {
                $p['precios_escalonados'] = isset($mapa_precios[$p['id']]) ? $mapa_precios[$p['id']] : [];
            }
        }

        echo json_encode($productos);
    }

    /**
     * POST /api/productos/subir_imagen
     */
    public function subir_imagen() {
        // Habilitar CORS
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, Origin, Accept");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");

        $categoria_id = $this->input->post('categoria_id');
        if (empty($categoria_id)) {
            $categoria_id = '1'; // Valor fallback (Porcelanatos)
        }

        $upload_path = FCPATH . 'uploads/productos/';
        if (!is_dir($upload_path)) {
            mkdir($upload_path, 0777, true);
        }

        // Obtener la extensión del archivo original
        $filename = $_FILES['file']['name'];
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        // Generar un nombre único basado en categoria_id + microtime
        $new_name = 'cat_' . $categoria_id . '_' . uniqid() . '.' . $ext;

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
        $file_url = 'uploads/productos/' . $upload_data['file_name'];

        echo json_encode([
            'status' => 'success',
            'file_url' => $file_url,
            'mensaje' => 'Imagen subida exitosamente'
        ]);
    }



    /**
     * GET /api/productos/obtener_parametros
     */
    public function obtener_parametros() {
        $headers = $this->input->request_headers();
        $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
        $usuario = JWT::decode($token);

        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $parametros = [];
        $tablas = [
            'PAIS' => 'cat_paises',
            'FABRICA' => 'cat_fabricas',
            'CLASE' => 'cat_clases',
            'ACABADO' => 'cat_acabados',
            'FORMATO' => 'cat_formatos',
            'COLOR' => 'cat_colores',
            'DISENO' => 'cat_disenos'
        ];

        foreach ($tablas as $tipo => $tabla) {
            $rows = $this->db->select('id, nombre, abreviacion')->get($tabla)->result_array();
            foreach ($rows as $r) {
                $parametros[] = [
                    'id' => $r['id'],
                    'tipo' => $tipo,
                    'nombre' => $r['nombre'],
                    'abreviacion' => $r['abreviacion']
                ];
            }
        }

        // Unidades base
        $unidades = $this->db->select('id, descripcion AS nombre, abreviacion')->get_where('cat_unidad_base', ['estado' => 1])->result_array();
        foreach ($unidades as $u) {
            $parametros[] = [
                'id' => $u['id'],
                'tipo' => 'UNIDAD',
                'nombre' => $u['nombre'],
                'abreviacion' => $u['abreviacion']
            ];
        }

        echo json_encode($parametros);
    }

    /**
     * POST /api/productos/agregar_parametro
     */
    public function agregar_parametro() {
        $headers = $this->input->request_headers();
        $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
        $usuario = JWT::decode($token);

        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);

        if (empty($input['tipo']) || empty($input['nombre']) || empty($input['abreviacion'])) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'Datos de parámetro incompletos']);
            return;
        }

        if ($input['tipo'] === 'UNIDAD') {
            $param_data = [
                'descripcion' => $input['nombre'],
                'abreviacion' => $input['abreviacion'],
                'estado' => 1
            ];
            $this->db->insert('cat_unidad_base', $param_data);
            echo json_encode(['status' => 'success', 'parametro_id' => $this->db->insert_id()]);
            return;
        }

        $tabla_destino = '';
        switch ($input['tipo']) {
            case 'PAIS': $tabla_destino = 'cat_paises'; break;
            case 'FABRICA': $tabla_destino = 'cat_fabricas'; break;
            case 'CLASE': $tabla_destino = 'cat_clases'; break;
            case 'ACABADO': $tabla_destino = 'cat_acabados'; break;
            case 'FORMATO': $tabla_destino = 'cat_formatos'; break;
            case 'COLOR': $tabla_destino = 'cat_colores'; break;
            case 'DISENO': $tabla_destino = 'cat_disenos'; break;
            default:
                $this->output->set_status_header(400);
                echo json_encode(['error' => 'Tipo de parámetro inválido']);
                return;
        }

        $param_data = [
            'nombre' => $input['nombre'],
            'abreviacion' => $input['abreviacion']
        ];

        $this->db->insert($tabla_destino, $param_data);
        echo json_encode(['status' => 'success', 'parametro_id' => $this->db->insert_id()]);
    }

    /**
     * POST /api/productos/crear
     */
    public function crear() {
        $headers = $this->input->request_headers();
        $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
        $usuario = JWT::decode($token);

        if (!$usuario || !in_array($usuario['rol'], ['ADMIN', 'AUTORIZADOR', 'VENDEDOR'])) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }


        $input = json_decode(trim(file_get_contents('php://input')), true);

        if (empty($input['codigo']) || empty($input['nombre']) || !isset($input['m2_por_caja'])) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'Campos obligatorios incompletos']);
            return;
        }

        $product_data = [
            'categoria_id' => 1, 
            'codigo' => $input['codigo'],
            'nombre' => $input['nombre'],
            'unidad_base_id' => isset($input['unidad_base_id']) ? $input['unidad_base_id'] : null,
            'm2_por_caja' => $input['m2_por_caja'],
            'piezas_por_caja' => $input['piezas_por_caja'],
            'peso_caja_kg' => $input['peso_caja_kg'],
            'formato_id' => isset($input['formato_id']) ? $input['formato_id'] : null,
            'acabado_id' => isset($input['acabado_id']) ? $input['acabado_id'] : null,
            'pais_id' => isset($input['pais_id']) ? $input['pais_id'] : null,
            'fabrica_id' => isset($input['fabrica_id']) ? $input['fabrica_id'] : null,
            'clase_id' => isset($input['clase_id']) ? $input['clase_id'] : null,
            'color_id' => isset($input['color_id']) ? $input['color_id'] : null,
            'diseno_id' => isset($input['diseno_id']) ? $input['diseno_id'] : null,
            'shade' => isset($input['shade']) ? $input['shade'] : null,
            'precio_base_bob' => isset($input['precio_base_bob']) ? $input['precio_base_bob'] : 0.00,
            'activo' => isset($input['activo']) ? $input['activo'] : 1,
            'se_fracciona' => isset($input['se_fracciona']) ? (int)$input['se_fracciona'] : 0,
            'venta_por_pieza' => isset($input['venta_por_pieza']) ? (int)$input['venta_por_pieza'] : 0,
            'cajas_por_palet' => (isset($input['cajas_por_palet']) && (int)$input['cajas_por_palet'] > 0) ? (int)$input['cajas_por_palet'] : 60,
            'imagen_url' => isset($input['imagen_url']) ? $input['imagen_url'] : null
        ];

        $this->db->trans_start();
        $this->db->insert('productos', $product_data);
        $producto_id = $this->db->insert_id();

        // Si se enviaron escalas en el payload, guardarlas directamente en la misma transacción
        if (!empty($input['escalas']) && is_array($input['escalas'])) {
            $insert_escalas = [];
            foreach ($input['escalas'] as $esc) {
                if (isset($esc['escala_id']) && isset($esc['precio_caja_bob']) && (float)$esc['precio_caja_bob'] > 0) {
                    $insert_escalas[] = [
                        'producto_id' => $producto_id,
                        'escala_id' => (int)$esc['escala_id'],
                        'precio_caja_bob' => (float)$esc['precio_caja_bob']
                    ];
                }
            }
            if (!empty($insert_escalas)) {
                $this->db->insert_batch('precios_escalonados', $insert_escalas);
            }
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            $this->output->set_status_header(500);
            echo json_encode(['error' => 'No se pudo guardar el producto.']);
            return;
        }

        echo json_encode([
            'status' => 'success',
            'producto_id' => $producto_id,
            'mensaje' => 'Producto registrado exitosamente'
        ]);
    }

    /**
     * POST /api/productos/actualizar
     */
    public function actualizar() {
        $headers = $this->input->request_headers();
        $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
        $usuario = JWT::decode($token);

        if (!$usuario || !in_array($usuario['rol'], ['ADMIN', 'AUTORIZADOR', 'VENDEDOR'])) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);

        if (empty($input['id']) || empty($input['codigo']) || empty($input['nombre'])) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'ID, SKU y nombre requeridos']);
            return;
        }

        $id = (int)$input['id'];
        $product_data = [
            'categoria_id' => 1, // Porcelanatos por defecto, requerido por el constraint FK
            'codigo' => $input['codigo'],
            'nombre' => $input['nombre'],
            'unidad_base_id' => isset($input['unidad_base_id']) ? $input['unidad_base_id'] : null,
            'm2_por_caja' => isset($input['m2_por_caja']) ? (float)$input['m2_por_caja'] : 0.0000,
            'piezas_por_caja' => isset($input['piezas_por_caja']) ? (int)$input['piezas_por_caja'] : 0,
            'peso_caja_kg' => isset($input['peso_caja_kg']) ? (float)$input['peso_caja_kg'] : 0.00,
            'formato_id' => isset($input['formato_id']) ? $input['formato_id'] : null,
            'acabado_id' => isset($input['acabado_id']) ? $input['acabado_id'] : null,
            'pais_id' => isset($input['pais_id']) ? $input['pais_id'] : null,
            'fabrica_id' => isset($input['fabrica_id']) ? $input['fabrica_id'] : null,
            'clase_id' => isset($input['clase_id']) ? $input['clase_id'] : null,
            'color_id' => isset($input['color_id']) ? $input['color_id'] : null,
            'diseno_id' => isset($input['diseno_id']) ? $input['diseno_id'] : null,
            'shade' => isset($input['shade']) ? $input['shade'] : null,
            'precio_base_bob' => isset($input['precio_base_bob']) ? (float)$input['precio_base_bob'] : 0.00,
            'activo' => isset($input['activo']) ? (int)$input['activo'] : 1,
            'se_fracciona' => isset($input['se_fracciona']) ? (int)$input['se_fracciona'] : 0,
            'venta_por_pieza' => isset($input['venta_por_pieza']) ? (int)$input['venta_por_pieza'] : 0,
            'cajas_por_palet' => (isset($input['cajas_por_palet']) && (int)$input['cajas_por_palet'] > 0) ? (int)$input['cajas_por_palet'] : 60,
            'imagen_url' => !empty($input['imagen_url']) ? $input['imagen_url'] : null
        ];

        $this->db->trans_start();

        $this->db->where('id', $id);
        $this->db->update('productos', $product_data);

        // Si se enviaron escalas en el payload, actualizar la tabla precios_escalonados
        if (isset($input['escalas']) && is_array($input['escalas'])) {
            $this->db->where('producto_id', $id);
            $this->db->delete('precios_escalonados');

            $insert_escalas = [];
            foreach ($input['escalas'] as $esc) {
                if (isset($esc['escala_id']) && isset($esc['precio_caja_bob']) && (float)$esc['precio_caja_bob'] > 0) {
                    $insert_escalas[] = [
                        'producto_id' => $id,
                        'escala_id' => (int)$esc['escala_id'],
                        'precio_caja_bob' => (float)$esc['precio_caja_bob']
                    ];
                }
            }
            if (!empty($insert_escalas)) {
                $this->db->insert_batch('precios_escalonados', $insert_escalas);
            }
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            $this->output->set_status_header(500);
            echo json_encode([
                'status' => 'error',
                'error' => 'Error de base de datos al actualizar el producto y sus escalas.'
            ]);
            return;
        }

        echo json_encode([
            'status' => 'success',
            'mensaje' => 'Producto actualizado exitosamente'
        ]);
    }
}
