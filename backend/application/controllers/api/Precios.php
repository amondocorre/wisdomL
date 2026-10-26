<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Precios extends CI_Controller {

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
        $token = null;
        if (isset($headers['Authorization'])) {
            $token = $headers['Authorization'];
        } elseif (isset($headers['authorization'])) {
            $token = $headers['authorization'];
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $token = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $token = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        if ($token) {
            $token = str_replace('Bearer ', '', $token);
            return JWT::decode($token);
        }
        return false;
    }

    /**
     * GET /api/precios/escalas
     * Obtiene las escalas dinámicas globales ordenadas por la columna ordenado
     */
    public function escalas() {
        $this->db->order_by('ordenado', 'ASC');
        $this->db->order_by('rango_min', 'ASC');
        $escalas = $this->db->get('config_escalas_precios')->result_array();
        echo json_encode($escalas);
    }

    /**
     * POST /api/precios/guardar_escala
     * Crea o actualiza una escala incluyendo su posición 'ordenado'
     */
    public function guardar_escala() {
        $usuario = $this->verificar_token();
        if (!$usuario || !in_array($usuario['rol'], ['ADMIN', 'AUTORIZADOR', 'SUPERADMIN'])) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado. La sesión no es válida o rol insuficiente.']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);
        
        $data = [
            'nombre' => $input['nombre'],
            'rango_min' => (int)$input['rango_min'],
            'rango_max' => (int)$input['rango_max'],
            'ordenado' => isset($input['ordenado']) ? (int)$input['ordenado'] : 0,
            'precio_cliente_final' => !empty($input['precio_cliente_final']) ? 1 : 0
        ];

        if (!empty($input['id'])) {
            $this->db->where('id', $input['id']);
            $this->db->update('config_escalas_precios', $data);
        } else {
            // Si es nueva y no se especificó orden, ponerla al final
            if (empty($input['ordenado'])) {
                $max_orden = $this->db->select_max('ordenado')->get('config_escalas_precios')->row()->ordenado;
                $data['ordenado'] = ((int)$max_orden) + 1;
            }
            $this->db->insert('config_escalas_precios', $data);
        }

        echo json_encode(['status' => 'success']);
    }

    /**
     * DELETE /api/precios/eliminar_escala/:id
     */
    public function eliminar_escala($id) {
        $usuario = $this->verificar_token();
        if (!$usuario || !in_array($usuario['rol'], ['ADMIN', 'AUTORIZADOR', 'SUPERADMIN'])) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $this->db->delete('config_escalas_precios', ['id' => $id]);
        echo json_encode(['status' => 'success']);
    }

    /**
     * POST /api/precios/actualizar_matriz
     * Guarda la matriz de productos enviada desde la interfaz (Spreadsheet)
     */
    public function actualizar_matriz() {
        $usuario = $this->verificar_token();
        if (!$usuario || !in_array($usuario['rol'], ['ADMIN', 'AUTORIZADOR', 'SUPERADMIN'])) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);

        if (empty($input['observacion']) || empty($input['productos_cambiados'])) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'Falta observación o matriz de productos.']);
            return;
        }

        $observacion = $input['observacion'];
        $productos_cambiados = $input['productos_cambiados']; // [{producto_id: 1, precios: [{escala_id: 1, precio: 140}, ...]}]

        $this->db->trans_start();

        // 1. Obtener precios anteriores de los productos modificados para auditoría precisa
        $prod_ids = array_column($productos_cambiados, 'producto_id');
        $precios_anteriores = [];
        if (!empty($prod_ids)) {
            $this->db->where_in('producto_id', $prod_ids);
            $query_ant = $this->db->get('precios_escalonados')->result_array();
            foreach ($query_ant as $row) {
                $precios_anteriores[$row['producto_id']][$row['escala_id']] = (float)$row['precio_caja_bob'];
            }
        }

        // 2. Registrar Documento de Auditoría Cabecera
        $this->db->insert('log_cambios_precios_documento', [
            'usuario_id' => $usuario['id'],
            'tipo_cambio' => 'MASIVO_MATRIZ_DINAMICA',
            'modo_aplicado' => 'EDICION_SPREADSHEET',
            'observacion' => $observacion,
            'criterios_aplicados' => json_encode(['cantidad_productos_afectados' => count($productos_cambiados)])
        ]);
        $documento_id = $this->db->insert_id();

        // 3. Aplicar cambios e insertar auditoría detallada
        $detalles_log = [];

        foreach ($productos_cambiados as $prod) {
            $producto_id = (int)$prod['producto_id'];
            
            // Borrar precios antiguos del producto modificado
            $this->db->delete('precios_escalonados', ['producto_id' => $producto_id]);

            // Insertar únicamente las escalas configuradas
            if (!empty($prod['precios'])) {
                $insert_batch = [];
                foreach ($prod['precios'] as $precio_esc) {
                    $escala_id = (int)$precio_esc['escala_id'];
                    $precio = isset($precio_esc['precio']) ? (float)$precio_esc['precio'] : (float)$precio_esc['precio_caja_bob'];
                    if ($escala_id > 0 && $precio > 0) {
                        $insert_batch[] = [
                            'producto_id' => $producto_id,
                            'escala_id' => $escala_id,
                            'precio_caja_bob' => $precio
                        ];

                        $p_anterior = isset($precios_anteriores[$producto_id][$escala_id]) ? $precios_anteriores[$producto_id][$escala_id] : 0.00;
                        $detalles_log[] = [
                            'documento_id' => $documento_id,
                            'producto_id' => $producto_id,
                            'escala_id' => $escala_id,
                            'precio_anterior' => $p_anterior,
                            'precio_nuevo' => $precio
                        ];
                    }
                }
                if (!empty($insert_batch)) {
                    $this->db->insert_batch('precios_escalonados', $insert_batch);
                }
            }
        }

        if (!empty($detalles_log)) {
            $this->db->insert_batch('log_cambios_precios_detalle', $detalles_log);
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            $this->output->set_status_header(500);
            echo json_encode(['error' => 'Error al guardar matriz en base de datos.']);
        } else {
            echo json_encode(['status' => 'success']);
        }
    }

    /**
     * POST /api/precios/actualizar_masivo_grupo
     * Actualiza masivamente los precios_escalonados filtrando por atributos de producto y escala_id.
     */
    public function actualizar_masivo_grupo() {
        $usuario = $this->verificar_token();
        if (!$usuario || !in_array($usuario['rol'], ['ADMIN', 'AUTORIZADOR', 'SUPERADMIN'])) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);

        $escala_id = isset($input['escala_id']) ? (int)$input['escala_id'] : null;
        $modalidad = isset($input['modalidad']) ? $input['modalidad'] : null;
        $valor = isset($input['valor']) ? (float)$input['valor'] : 0.00;
        $observacion = isset($input['observacion']) ? trim($input['observacion']) : 'Actualización masiva por grupo';

        $formato_id = isset($input['formato_id']) ? (int)$input['formato_id'] : null;
        $clase_id = isset($input['clase_id']) ? (int)$input['clase_id'] : null;
        $pais_id = isset($input['pais_id']) ? (int)$input['pais_id'] : null;
        $fabrica_id = isset($input['fabrica_id']) ? (int)$input['fabrica_id'] : null;

        if (!$escala_id || !$modalidad || empty($observacion)) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'Parámetros requeridos: escala_id, modalidad, valor y observación.']);
            return;
        }

        // Obtener productos afectados
        $this->db->select('id, precio_base_bob');
        $this->db->from('productos');
        $this->db->where('activo', 1);

        if (!empty($formato_id)) $this->db->where('formato_id', $formato_id);
        if (!empty($clase_id)) $this->db->where('clase_id', $clase_id);
        if (!empty($pais_id)) $this->db->where('pais_id', $pais_id);
        if (!empty($fabrica_id)) $this->db->where('fabrica_id', $fabrica_id);

        $productos = $this->db->get()->result_array();

        if (empty($productos)) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'No se encontraron productos que coincidan con los filtros seleccionados.']);
            return;
        }

        $prod_ids = array_column($productos, 'id');

        $precios_actuales = [];
        $this->db->where_in('producto_id', $prod_ids);
        $this->db->where('escala_id', $escala_id);
        $query_pe = $this->db->get('precios_escalonados')->result_array();
        foreach ($query_pe as $row) {
            $precios_actuales[$row['producto_id']] = (float)$row['precio_caja_bob'];
        }

        $this->db->trans_start();

        // Registrar cabecera documento
        $this->db->insert('log_cambios_precios_documento', [
            'usuario_id' => $usuario['id'],
            'tipo_cambio' => 'MASIVO_POR_GRUPO',
            'modo_aplicado' => $modalidad,
            'observacion' => $observacion,
            'criterios_aplicados' => json_encode([
                'escala_id' => $escala_id,
                'modalidad' => $modalidad,
                'valor_ajuste' => $valor,
                'filtros' => [
                    'formato_id' => $formato_id,
                    'clase_id' => $clase_id,
                    'pais_id' => $pais_id,
                    'fabrica_id' => $fabrica_id
                ],
                'productos_afectados' => count($productos)
            ])
        ]);
        $documento_id = $this->db->insert_id();

        $afectados = 0;
        $detalles_log = [];

        foreach ($productos as $prod) {
            $prod_id = (int)$prod['id'];
            $precio_anterior = isset($precios_actuales[$prod_id]) ? $precios_actuales[$prod_id] : (float)$prod['precio_base_bob'];

            switch ($modalidad) {
                case 'PRECIO_UNICO':
                    $nuevo_precio = $valor;
                    break;
                case 'AJUSTE_PORCENTUAL':
                    $nuevo_precio = $precio_anterior * (1 + ($valor / 100));
                    break;
                case 'AJUSTE_MONTO_FIJO':
                    $nuevo_precio = $precio_anterior + $valor;
                    break;
                default:
                    $nuevo_precio = $precio_anterior;
                    break;
            }

            $nuevo_precio = round(max(0, $nuevo_precio), 2);

            if ($nuevo_precio > 0) {
                // Upsert en precios_escalonados
                $this->db->where('producto_id', $prod_id);
                $this->db->where('escala_id', $escala_id);
                $query = $this->db->get('precios_escalonados');

                if ($query->num_rows() > 0) {
                    $this->db->where('producto_id', $prod_id);
                    $this->db->where('escala_id', $escala_id);
                    $this->db->update('precios_escalonados', ['precio_caja_bob' => $nuevo_precio]);
                } else {
                    $this->db->insert('precios_escalonados', [
                        'producto_id' => $prod_id,
                        'escala_id' => $escala_id,
                        'precio_caja_bob' => $nuevo_precio
                    ]);
                }

                $detalles_log[] = [
                    'documento_id' => $documento_id,
                    'producto_id' => $prod_id,
                    'escala_id' => $escala_id,
                    'precio_anterior' => $precio_anterior,
                    'precio_nuevo' => $nuevo_precio
                ];
                $afectados++;
            }
        }

        if (!empty($detalles_log)) {
            $this->db->insert_batch('log_cambios_precios_detalle', $detalles_log);
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            $this->output->set_status_header(500);
            echo json_encode(['error' => 'Error al procesar la actualización masiva de precios.']);
            return;
        }

        echo json_encode([
            'status' => 'success',
            'afectados' => $afectados,
            'mensaje' => "Actualización masiva completada para {$afectados} productos."
        ]);
    }

    /**
     * GET /api/precios/obtener_matriz
     * Obtiene los productos filtrados y su matriz de precios actuales normalizada
     */
    public function obtener_matriz() {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $formato_id = $this->input->get('formato_id');
        $pais_id = $this->input->get('pais_id');
        $clase_id = $this->input->get('clase_id');

        $this->db->select('p.id, p.codigo, p.nombre, p.precio_base_bob, cat_f.nombre as formato, cat_p.nombre as origen, cat_c.nombre as clase');
        $this->db->from('productos p');
        $this->db->join('cat_formatos cat_f', 'cat_f.id = p.formato_id', 'left');
        $this->db->join('cat_paises cat_p', 'cat_p.id = p.pais_id', 'left');
        $this->db->join('cat_clases cat_c', 'cat_c.id = p.clase_id', 'left');
        
        if (!empty($formato_id)) {
            $this->db->where('p.formato_id', $formato_id);
        }
        if (!empty($pais_id)) {
            $this->db->where('p.pais_id', $pais_id);
        }
        if (!empty($clase_id)) {
            $this->db->where('p.clase_id', $clase_id);
        }
        $productos = $this->db->get()->result_array();

        if (count($productos) > 0) {
            $ids = array_column($productos, 'id');
            $this->db->select('pe.producto_id, pe.escala_id, cep.nombre as nombre_escala, cep.rango_min, cep.rango_max, cep.ordenado, pe.precio_caja_bob');
            $this->db->from('precios_escalonados pe');
            $this->db->join('config_escalas_precios cep', 'cep.id = pe.escala_id');
            $this->db->where_in('pe.producto_id', $ids);
            $this->db->order_by('pe.producto_id', 'ASC');
            $this->db->order_by('cep.ordenado', 'ASC');
            $this->db->order_by('cep.rango_min', 'ASC');
            $precios_esc = $this->db->get()->result_array();

            // Mapear
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
     * GET /api/precios/bitacora
     * Retorna la lista de documentos de cambios de precios
     */
    public function bitacora() {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $this->db->select('doc.*, u.nombre as usuario_nombre, (SELECT COUNT(DISTINCT d.producto_id) FROM log_cambios_precios_detalle d WHERE d.documento_id = doc.id) as total_productos_afectados');
        $this->db->from('log_cambios_precios_documento doc');
        $this->db->join('usuarios u', 'u.id = doc.usuario_id');
        $this->db->order_by('doc.fecha_registro', 'DESC');
        $query = $this->db->get();

        echo json_encode($query->result_array());
    }

    /**
     * GET /api/precios/bitacora_detalle/:documento_id
     * Obtiene el desglose exacto de productos, escalas, precios anteriores y nuevos
     */
    public function bitacora_detalle($documento_id) {
        $usuario = $this->verificar_token();
        if (!$usuario) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $this->db->select('det.*, p.codigo as producto_codigo, p.nombre as producto_nombre, cep.nombre as escala_nombre, cep.rango_min, cep.rango_max');
        $this->db->from('log_cambios_precios_detalle det');
        $this->db->join('productos p', 'p.id = det.producto_id');
        $this->db->join('config_escalas_precios cep', 'cep.id = det.escala_id');
        $this->db->where('det.documento_id', (int)$documento_id);
        $this->db->order_by('p.nombre', 'ASC');
        $this->db->order_by('cep.ordenado', 'ASC');
        $query = $this->db->get();

        echo json_encode($query->result_array());
    }

    /**
     * POST /api/precios/guardar_escalas_producto
     * Guarda las escalas de precios específicas para un solo producto (normalizado)
     */
    public function guardar_escalas_producto() {
        $usuario = $this->verificar_token();
        if (!$usuario || !in_array($usuario['rol'], ['ADMIN', 'AUTORIZADOR', 'SUPERADMIN'])) {
            $this->output->set_status_header(401);
            echo json_encode(['error' => 'No autorizado']);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);
        
        $producto_id = isset($input['producto_id']) ? (int)$input['producto_id'] : null;
        $escalas = isset($input['escalas']) ? $input['escalas'] : [];

        if (!$producto_id) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'ID de producto requerido']);
            return;
        }

        $this->db->trans_start();

        // Obtener precios anteriores del producto
        $this->db->where('producto_id', $producto_id);
        $precios_anteriores = $this->db->get('precios_escalonados')->result_array();
        $mapa_ant = [];
        foreach ($precios_anteriores as $pa) {
            $mapa_ant[$pa['escala_id']] = (float)$pa['precio_caja_bob'];
        }

        // 1. Eliminar escalas existentes para limpiar
        $this->db->where('producto_id', $producto_id);
        $this->db->delete('precios_escalonados');

        // 2. Insertar las nuevas escalas activadas (con escala_id y precio > 0)
        $insert_data = [];
        $detalles_log = [];

        // Crear documento de auditoría
        $this->db->insert('log_cambios_precios_documento', [
            'usuario_id' => $usuario['id'],
            'tipo_cambio' => 'INDIVIDUAL_PRODUCTO',
            'modo_aplicado' => 'EDICION_FICHA_PRODUCTO',
            'observacion' => isset($input['observacion']) && !empty($input['observacion']) ? trim($input['observacion']) : 'Actualización de escalas desde ficha de producto',
            'criterios_aplicados' => json_encode(['producto_id' => $producto_id])
        ]);
        $documento_id = $this->db->insert_id();

        if (count($escalas) > 0) {
            foreach ($escalas as $escala) {
                if (isset($escala['escala_id'], $escala['precio_caja_bob']) && (float)$escala['precio_caja_bob'] > 0) {
                    $esc_id = (int)$escala['escala_id'];
                    $p_nuevo = (float)$escala['precio_caja_bob'];
                    $p_ant = isset($mapa_ant[$esc_id]) ? $mapa_ant[$esc_id] : 0.00;

                    $insert_data[] = [
                        'producto_id' => $producto_id,
                        'escala_id' => $esc_id,
                        'precio_caja_bob' => $p_nuevo
                    ];

                    $detalles_log[] = [
                        'documento_id' => $documento_id,
                        'producto_id' => $producto_id,
                        'escala_id' => $esc_id,
                        'precio_anterior' => $p_ant,
                        'precio_nuevo' => $p_nuevo
                    ];
                }
            }
            if (count($insert_data) > 0) {
                $this->db->insert_batch('precios_escalonados', $insert_data);
            }
            if (count($detalles_log) > 0) {
                $this->db->insert_batch('log_cambios_precios_detalle', $detalles_log);
            }
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            $this->output->set_status_header(500);
            echo json_encode(['error' => 'Error al guardar las escalas']);
            return;
        }

        echo json_encode(['status' => 'success']);
    }
}
