<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Recibos extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->database();
        
        // Cabeceras CORS
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit(0);
        }
    }

    /**
     * Obtener datos completos de un recibo por venta_id o venta_documento_id
     */
    public function obtener_datos() {
        $venta_id = $this->input->get('venta_id');
        $venta_documento_id = $this->input->get('venta_documento_id');

        if (!$venta_id && !$venta_documento_id) {
            $this->output->set_status_header(400);
            echo json_encode(['error' => 'Debe proporcionar venta_id o venta_documento_id']);
            return;
        }

        if ($venta_documento_id) {
            $doc = $this->db->get_where('venta_documentos', ['id' => $venta_documento_id])->row_array();
        } else {
            $doc = $this->db->get_where('venta_documentos', ['venta_id' => $venta_id])->row_array();
        }

        if (!$doc) {
            $this->output->set_status_header(404);
            echo json_encode(['error' => 'Comprobante de venta no encontrado']);
            return;
        }

        $v_id = $doc['venta_id'];
        $v_doc_id = $doc['id'];

        // Obtener detalles con datos del producto y unidad base
        $this->db->select('vdd.*, p.m2_por_caja, p.piezas_por_caja, cat_u.abreviacion as unidad_base_abrev, cat_u.descripcion as unidad_base_nombre');
        $this->db->from('venta_documento_detalles vdd');
        $this->db->join('productos p', 'p.id = vdd.producto_id', 'left');
        $this->db->join('cat_unidad_base cat_u', 'cat_u.id = p.unidad_base_id', 'left');
        $this->db->where('vdd.venta_documento_id', $v_doc_id);
        $detalles = $this->db->get()->result_array();

        // Obtener transporte
        $transporte = $this->db->get_where('venta_transporte', ['venta_documento_id' => $v_doc_id])->row_array();

        // Datos de la empresa / sucursal desde BD
        $conf_empresa = $this->db->get_where('configuracion_empresa', ['id' => 1])->row_array();
        if (!$conf_empresa) {
            $conf_empresa = [
                'nombre' => 'WISDOM SRL',
                'nit' => '4561231',
                'sucursal' => 'OFICINA CENTRAL ',
                'direccion' => 'C GUISNAI ',
                'telefono' => '75900080',
                'ciudad_defecto' => 'Cochabamba'
            ];
        }

        // Obtener ciudad de salida del material
        $ciudad_salida = $conf_empresa['ciudad_defecto'] ?: 'Cochabamba';
        if (!empty($doc['almacen_id'])) {
            $this->db->select('a.nombre as almacen_nombre, c.nombre as ciudad_nombre');
            $this->db->from('almacenes a');
            $this->db->join('ciudades c', 'c.id = a.ciudad_id', 'left');
            $this->db->where('a.id', $doc['almacen_id']);
            $alm_info = $this->db->get()->row_array();
            if ($alm_info && !empty($alm_info['ciudad_nombre'])) {
                $ciudad_salida = $alm_info['ciudad_nombre'];
            }
        }

        $empresa = [
            'nombre' => $conf_empresa['nombre'],
            'nit' => $conf_empresa['nit'],
            'sucursal' => $conf_empresa['sucursal'],
            'punto_venta' => $ciudad_salida,
            'direccion' => $conf_empresa['direccion'],
            'telefono' => $conf_empresa['telefono']
        ];

        // Obtener NIT / CI del cliente
        $cliente = $this->db->get_where('clientes', ['id' => $doc['cliente_id']])->row_array();
        $nit_ci = '0';
        if ($cliente) {
            $nit_ci = !empty($cliente['nit_ci']) ? $cliente['nit_ci'] : '0';
        }

        // Convertir total a literal bolivianos
        $total_num = (float)$doc['total'];
        $monto_literal = $this->numero_a_letras($total_num);

        $metodo_pago = isset($doc['metodo_pago']) ? strtoupper(trim($doc['metodo_pago'])) : 'EFECTIVO';
        $tipo_registro = ($metodo_pago === 'CREDITO') ? 'AL CRÉDITO' : 'AL CONTADO';

        $this->output->set_content_type('application/json');
        echo json_encode([
            'empresa' => $empresa,
            'recibo_nro' => str_pad($v_doc_id, 5, '0', STR_PAD_LEFT),
            'cod_autorizacion' => substr(md5('RECIBO_' . $v_doc_id), 0, 16),
            'fecha' => date('d/m/Y h:i A', strtotime($doc['fecha_hora'])),
            'cliente_nombre' => $doc['cliente_nombre'],
            'cliente_id' => $doc['cliente_id'],
            'nit_ci' => $nit_ci,
            'usuario_nombre' => $doc['usuario_nombre'],
            'almacen_nombre' => $doc['almacen_nombre'],
            'ciudad_salida' => $ciudad_salida,
            'tipo_registro' => $tipo_registro,
            'metodo_pago' => $metodo_pago,
            'monto_literal' => $monto_literal,
            'tiene_transporte' => (int)$doc['tiene_transporte'],
            'tipo_transporte' => $doc['tipo_transporte'],
            'transporte_detalle' => $transporte,
            'detalles' => $detalles,
            'subtotal' => (float)$doc['subtotal'],
            'costo_transporte' => (float)$doc['costo_transporte'],
            'total' => $total_num
        ]);
    }

    /**
     * Convierte un número decimal a formato literal monetario
     */
    private function numero_a_letras($numero) {
        $enteros = floor($numero);
        $centavos = round(($numero - $enteros) * 100);
        $centavos_str = str_pad($centavos, 2, '0', STR_PAD_LEFT) . "/100 BOLIVIANOS";

        $unidades = ['', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE', 'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISEIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE'];
        $decenas = ['', 'DIEZ', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
        $centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

        if ($enteros == 0) {
            return "CERO CON " . $centavos_str;
        }

        $convertir_tres = function($n) use (&$convertir_tres, $unidades, $decenas, $centenas) {
            $output = '';
            if ($n == 100) return 'CIEN';
            if ($n >= 100) {
                $c = floor($n / 100);
                $output .= $centenas[$c] . ' ';
                $n = $n % 100;
            }
            if ($n >= 20) {
                $d = floor($n / 10);
                $u = $n % 10;
                if ($d == 2 && $u > 0) {
                    $output .= 'VEINTI' . $unidades[$u] . ' ';
                } else {
                    $output .= $decenas[$d] . ($u > 0 ? ' Y ' . $unidades[$u] : '') . ' ';
                }
            } else if ($n > 0) {
                $output .= $unidades[$n] . ' ';
            }
            return trim($output);
        };

        $texto = '';
        if ($enteros >= 1000000) {
            $millones = floor($enteros / 1000000);
            $texto .= ($millones == 1 ? 'UN MILLON ' : $convertir_tres($millones) . ' MILLONES ');
            $enteros = $enteros % 1000000;
        }

        if ($enteros >= 1000) {
            $miles = floor($enteros / 1000);
            $texto .= ($miles == 1 ? 'MIL ' : $convertir_tres($miles) . ' MIL ');
            $enteros = $enteros % 1000;
        }

        if ($enteros > 0) {
            $texto .= $convertir_tres($enteros) . ' ';
        }

        return trim($texto) . " CON " . $centavos_str;
    }
}
