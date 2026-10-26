<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Inventario_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    /**
     * Obtener el stock disponible de un producto en un almacén (en su unidad de medida base: m2 o pza).
     */
    public function obtener_stock($almacen_id, $producto_id) {
        $this->db->where('almacen_id', $almacen_id);
        $this->db->where('producto_id', $producto_id);
        $query = $this->db->get('inventario_almacen');
        if ($query->num_rows() > 0) {
            return (float) $query->row()->stock_disponible;
        }
        return 0.00;
    }

    /**
     * Reservar stock por venta (disminuye stock_disponible y aumenta stock_reservado).
     * El producto continúa físicamente en el galpón/almacén.
     */
    public function reservar_stock($almacen_id, $producto_id, $cantidad_base, $cajas = null) {
        $this->db->where('almacen_id', $almacen_id);
        $this->db->where('producto_id', $producto_id);
        $stock = $this->db->get('inventario_almacen')->row_array();

        $cantidad_base = (float)$cantidad_base;

        if (!$stock || ((float)$stock['stock_disponible'] + 0.0001) < $cantidad_base) {
            return false;
        }

        $nuevo_reservado = (float)$stock['stock_reservado'] + $cantidad_base;
        $nuevo_disponible = (float)$stock['stock_disponible'] - $cantidad_base;

        $this->db->where('almacen_id', $almacen_id);
        $this->db->where('producto_id', $producto_id);
        $actualizado = $this->db->update('inventario_almacen', [
            'stock_reservado' => $nuevo_reservado,
            'stock_disponible' => max(0, $nuevo_disponible)
        ]);

        return $actualizado;
    }

    /**
     * Despacho Físico de Material del Almacén:
     * Disminuye stock_fisico y stock_reservado.
     * Registra la salida física definitiva en el Kardex (columna 'cantidad').
     */
    public function despachar_stock_fisico($almacen_id, $producto_id, $cantidad_base, $observacion = 'Despacho de Material en Galpón', $usuario_id = null, $cajas = null) {
        $this->db->where('almacen_id', $almacen_id);
        $this->db->where('producto_id', $producto_id);
        $stock = $this->db->get('inventario_almacen')->row_array();

        $cantidad_base = (float)$cantidad_base;
        if (!$stock) {
            return false;
        }

        $nuevo_fisico = max(0, (float)$stock['stock_fisico'] - $cantidad_base);
        $nuevo_reservado = max(0, (float)$stock['stock_reservado'] - $cantidad_base);

        $this->db->where('almacen_id', $almacen_id);
        $this->db->where('producto_id', $producto_id);
        $actualizado = $this->db->update('inventario_almacen', [
            'stock_fisico' => $nuevo_fisico,
            'stock_reservado' => $nuevo_reservado
        ]);

        if ($actualizado) {
            // Registrar movimiento en log_movimientos_inventario
            if ($this->db->table_exists('log_movimientos_inventario')) {
                $this->db->insert('log_movimientos_inventario', [
                    'producto_id' => $producto_id,
                    'almacen_id' => $almacen_id,
                    'tipo_movimiento' => 'DESPACHO_VENTA',
                    'cantidad' => $cantidad_base,
                    'motivo_observacion' => $observacion . ($usuario_id ? " (Registrado por Usuario #$usuario_id)" : "")
                ]);
            }

            // Registrar en inventario_movimientos si existe
            if ($this->db->table_exists('inventario_movimientos')) {
                $cajas_calc = ($cajas !== null) ? (int)$cajas : (int)ceil($cantidad_base);
                $this->db->insert('inventario_movimientos', [
                    'producto_id' => $producto_id,
                    'almacen_origen_id' => $almacen_id,
                    'tipo_movimiento' => 'SALIDA_VENTA',
                    'cantidad_cajas' => $cajas_calc,
                    'cantidad' => $cantidad_base,
                    'fecha' => date('Y-m-d H:i:s'),
                    'observacion' => $observacion
                ]);
            }
        }

        return $actualizado;
    }

    /**
     * Alias de compatibilidad
     */
    public function descontar_stock($almacen_id, $producto_id, $cantidad_base, $cajas = null) {
        return $this->reservar_stock($almacen_id, $producto_id, $cantidad_base, $cajas);
    }

    /**
     * Obtener la escala de precios configurada para un producto.
     */
    public function obtener_precio_escala($producto_id, $cajas) {
        $this->db->select('pe.precio_caja_bob');
        $this->db->from('precios_escalonados pe');
        $this->db->join('config_escalas_precios cep', 'cep.id = pe.escala_id');
        $this->db->where('pe.producto_id', $producto_id);
        $this->db->where('cep.rango_min <=', $cajas);
        $this->db->where('cep.rango_max >=', $cajas);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            return (float) $query->row()->precio_caja_bob;
        }

        // Si no coincide, buscar el precio de menor rango configurado o precio base del producto
        $this->db->select('pe.precio_caja_bob');
        $this->db->from('precios_escalonados pe');
        $this->db->join('config_escalas_precios cep', 'cep.id = pe.escala_id');
        $this->db->where('pe.producto_id', $producto_id);
        $this->db->order_by('cep.rango_min', 'ASC');
        $query_base = $this->db->get();
        if ($query_base->num_rows() > 0) {
            return (float) $query_base->row()->precio_caja_bob;
        }
        
        return 0.00; // Sin precio configurado
    }
}
