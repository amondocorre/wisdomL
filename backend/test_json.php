<?php
require 'index.php';
$ci =& get_instance();
$ci->db->select('id, codigo, nombre, formato, origen, precio_base_bob');
$productos = $ci->db->get('productos')->result_array();
foreach ($productos as &$p) {
    $ci->db->select('rango_min, rango_max, precio_caja_bob');
    $ci->db->where('producto_id', $p['id']);
    $p['precios_escalonados'] = $ci->db->get('precios_escalonados')->result_array();
}
$json = json_encode($productos);
if ($json === false) {
    echo "JSON Error: " . json_last_error_msg() . "\n";
} else {
    echo "OK: " . count($productos) . " productos. Tamanio JSON: " . strlen($json);
}
