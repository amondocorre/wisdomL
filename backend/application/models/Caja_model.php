<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Caja_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    public function obtener_caja_activa($usuario_id) {
        return $this->db->select('cs.*, u.nombre as usuario_nombre, u.email as usuario_email, c.nombre as ciudad_nombre')
            ->from('cajas_sesiones cs')
            ->join('usuarios u', 'u.id = cs.usuario_id', 'left')
            ->join('ciudades c', 'c.id = COALESCE(cs.ciudad_id, u.ciudad_id)', 'left')
            ->where('cs.usuario_id', $usuario_id)
            ->where('cs.estado', 'ABIERTA')
            ->get()->row_array();
    }

    public function abrir_caja($usuario_id, $monto_apertura, $ciudad_id = null) {
        $caja_activa = $this->obtener_caja_activa($usuario_id);
        if ($caja_activa) {
            return ['status' => false, 'message' => 'Ya tienes una caja abierta.'];
        }

        // Si no se especifica ciudad_id, obtener la ciudad principal del usuario
        if (!$ciudad_id) {
            $u = $this->db->get_where('usuarios', ['id' => $usuario_id])->row_array();
            $ciudad_id = !empty($u['ciudad_id']) ? (int)$u['ciudad_id'] : 1;
        }

        $data = [
            'usuario_id' => $usuario_id,
            'ciudad_id' => (int)$ciudad_id,
            'monto_apertura' => $monto_apertura,
            'estado' => 'ABIERTA',
            'fecha_apertura' => date('Y-m-d H:i:s')
        ];

        if ($this->db->insert('cajas_sesiones', $data)) {
            return ['status' => true, 'id' => $this->db->insert_id()];
        }
        return ['status' => false, 'message' => 'Error al abrir la caja.'];
    }

    public function cerrar_caja($usuario_id, $monto_cierre) {
        $caja_activa = $this->obtener_caja_activa($usuario_id);
        if (!$caja_activa) {
            return ['status' => false, 'message' => 'No tienes ninguna caja abierta.'];
        }

        $data = [
            'monto_cierre' => $monto_cierre,
            'estado' => 'CERRADA',
            'fecha_cierre' => date('Y-m-d H:i:s')
        ];

        $this->db->where('id', $caja_activa['id']);
        if ($this->db->update('cajas_sesiones', $data)) {
            $resumen = $this->obtener_resumen_caja($caja_activa['id']);
            $caja_cerrada = $this->db->select('cs.*, u.nombre as usuario_nombre, u.email as usuario_email, c.nombre as ciudad_nombre')
                ->from('cajas_sesiones cs')
                ->join('usuarios u', 'u.id = cs.usuario_id', 'left')
                ->join('ciudades c', 'c.id = COALESCE(cs.ciudad_id, u.ciudad_id)', 'left')
                ->where('cs.id', $caja_activa['id'])
                ->get()->row_array();

            $resumen['caja'] = $caja_cerrada;
            $resumen['monto_cierre_real'] = (float)$monto_cierre;
            $resumen['diferencia'] = (float)$monto_cierre - $resumen['monto_esperado'];

            return ['status' => true, 'reporte' => $resumen];
        }
        return ['status' => false, 'message' => 'Error al cerrar la caja.'];
    }

    public function registrar_movimiento($caja_id, $usuario_id, $tipo, $concepto, $monto, $descripcion = '', $comprobante_url = '') {
        $data = [
            'caja_sesion_id' => $caja_id,
            'usuario_id' => $usuario_id,
            'tipo' => strtoupper($tipo),
            'concepto' => strtoupper($concepto),
            'monto' => (float)$monto,
            'descripcion' => $descripcion,
            'comprobante_url' => $comprobante_url,
            'fecha_hora' => date('Y-m-d H:i:s')
        ];

        if ($this->db->insert('caja_movimientos', $data)) {
            return ['status' => true, 'id' => $this->db->insert_id()];
        }
        return ['status' => false, 'message' => 'Error al registrar el movimiento de caja.'];
    }

    public function obtener_movimientos_caja($caja_id) {
        $this->db->select('m.*, u.nombre as usuario_nombre');
        $this->db->from('caja_movimientos m');
        $this->db->join('usuarios u', 'u.id = m.usuario_id', 'left');
        $this->db->where('m.caja_sesion_id', $caja_id);
        $this->db->order_by('m.fecha_hora', 'DESC');
        return $this->db->get()->result_array();
    }

    public function obtener_resumen_caja($caja_id) {
        // Sumar ventas asociadas a la caja
        $this->db->select('COUNT(id) as total_ventas, SUM(total) as monto_total_ventas');
        $this->db->from('ventas');
        $this->db->where('caja_sesion_id', $caja_id);
        $this->db->where('estado !=', 'RECHAZADO');
        $ventas = $this->db->get()->row_array();

        // Obtener desglose de ventas por método de pago (tipo_pago)
        $this->db->select('tipo_pago, COUNT(id) as transacciones, SUM(total) as monto');
        $this->db->from('ventas');
        $this->db->where('caja_sesion_id', $caja_id);
        $this->db->where('estado !=', 'RECHAZADO');
        $this->db->group_by('tipo_pago');
        $ventas_metodo_raw = $this->db->get()->result_array();

        $desglose_pagos = [
            'EFECTIVO' => ['monto' => 0.00, 'transacciones' => 0],
            'QR' => ['monto' => 0.00, 'transacciones' => 0],
            'TRANSFERENCIA' => ['monto' => 0.00, 'transacciones' => 0],
            'DEPOSITO' => ['monto' => 0.00, 'transacciones' => 0],
            'CREDITO' => ['monto' => 0.00, 'transacciones' => 0]
        ];

        foreach ($ventas_metodo_raw as $vm) {
            $key = strtoupper(trim((string)$vm['tipo_pago']));
            if (!isset($desglose_pagos[$key])) {
                $desglose_pagos[$key] = ['monto' => 0.00, 'transacciones' => 0];
            }
            $desglose_pagos[$key]['monto'] = (float)($vm['monto'] ?? 0.00);
            $desglose_pagos[$key]['transacciones'] = (int)($vm['transacciones'] ?? 0);
        }

        // Sumar ingresos de caja
        $this->db->select('SUM(monto) as total_ingresos');
        $this->db->from('caja_movimientos');
        $this->db->where('caja_sesion_id', $caja_id);
        $this->db->where('tipo', 'INGRESO');
        $ing = $this->db->get()->row_array();

        // Sumar egresos de caja
        $this->db->select('SUM(monto) as total_egresos');
        $this->db->from('caja_movimientos');
        $this->db->where('caja_sesion_id', $caja_id);
        $this->db->where('tipo', 'EGRESO');
        $egr = $this->db->get()->row_array();

        $caja = $this->db->get_where('cajas_sesiones', ['id' => $caja_id])->row_array();

        $monto_apertura = (float)($caja['monto_apertura'] ?? 0.00);
        $monto_ventas = (float)($ventas['monto_total_ventas'] ?? 0.00);
        $ventas_efectivo = (float)($desglose_pagos['EFECTIVO']['monto'] ?? 0.00);
        $total_ingresos = (float)($ing['total_ingresos'] ?? 0.00);
        $total_egresos = (float)($egr['total_egresos'] ?? 0.00);

        // El monto en efectivo esperado en caja es apertura + ventas en efectivo + ingresos extra - egresos extra
        $monto_esperado = $monto_apertura + $ventas_efectivo + $total_ingresos - $total_egresos;

        return [
            'caja' => $caja,
            'total_ventas' => (int)($ventas['total_ventas'] ?? 0),
            'monto_ventas' => $monto_ventas,
            'ventas_efectivo' => $ventas_efectivo,
            'desglose_pagos' => $desglose_pagos,
            'total_ingresos' => $total_ingresos,
            'total_egresos' => $total_egresos,
            'monto_esperado' => $monto_esperado,
            'movimientos' => $this->obtener_movimientos_caja($caja_id)
        ];
    }

    public function obtener_historial_cajas($filtros = []) {
        $this->db->select('cs.*, u.nombre as usuario_nombre, u.email as usuario_email, c.nombre as ciudad_nombre');
        $this->db->from('cajas_sesiones cs');
        $this->db->join('usuarios u', 'u.id = cs.usuario_id', 'left');
        $this->db->join('ciudades c', 'c.id = COALESCE(cs.ciudad_id, u.ciudad_id)', 'left');

        if (!empty($filtros['usuario_id'])) {
            $this->db->where('cs.usuario_id', (int)$filtros['usuario_id']);
        }
        if (!empty($filtros['ciudad_id'])) {
            $this->db->where('COALESCE(cs.ciudad_id, u.ciudad_id) =', (int)$filtros['ciudad_id']);
        }
        if (!empty($filtros['estado'])) {
            $this->db->where('cs.estado', $filtros['estado']);
        }
        if (!empty($filtros['fecha_desde'])) {
            $this->db->where('cs.fecha_apertura >=', $filtros['fecha_desde'] . ' 00:00:00');
        }
        if (!empty($filtros['fecha_hasta'])) {
            $this->db->where('cs.fecha_apertura <=', $filtros['fecha_hasta'] . ' 23:59:59');
        }

        $this->db->order_by('cs.id', 'DESC');
        $sesiones = $this->db->get()->result_array();

        foreach ($sesiones as &$s) {
            $resumen = $this->obtener_resumen_caja($s['id']);
            $s['total_ventas'] = $resumen['total_ventas'];
            $s['monto_ventas'] = $resumen['monto_ventas'];
            $s['total_ingresos'] = $resumen['total_ingresos'];
            $s['total_egresos'] = $resumen['total_egresos'];
            $s['monto_esperado'] = $resumen['monto_esperado'];
            $s['monto_cierre_real'] = $s['monto_cierre'] !== null ? (float)$s['monto_cierre'] : null;
            $s['diferencia'] = $s['monto_cierre'] !== null ? ((float)$s['monto_cierre'] - $resumen['monto_esperado']) : 0.00;
            $s['movimientos'] = $resumen['movimientos'];
        }

        return $sesiones;
    }
}
