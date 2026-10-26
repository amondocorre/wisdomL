<?php
require 'index.php';
$ci =& get_instance();

echo "Iniciando carga de prueba...\n";

// Crear 3 productos
$productos_prueba = [
    [
        'categoria_id' => 1,
        'codigo' => 'TEST-60-CH-1',
        'nombre' => 'Porcelanato Test 60x60 China Blanco',
        'm2_por_caja' => 1.44,
        'piezas_por_caja' => 4,
        'peso_caja_kg' => 30.00,
        'formato' => '60x60',
        'acabado' => 'Brillante',
        'origen' => 'China',
        'precio_base_bob' => 100.00,
        'cajas_por_palet' => 40
    ],
    [
        'categoria_id' => 1,
        'codigo' => 'TEST-60-CH-2',
        'nombre' => 'Porcelanato Test 60x60 China Gris',
        'm2_por_caja' => 1.44,
        'piezas_por_caja' => 4,
        'peso_caja_kg' => 30.00,
        'formato' => '60x60',
        'acabado' => 'Mate',
        'origen' => 'China',
        'precio_base_bob' => 105.00,
        'cajas_por_palet' => 40
    ],
    [
        'categoria_id' => 1,
        'codigo' => 'TEST-60-CH-3',
        'nombre' => 'Porcelanato Test 60x60 China Negro',
        'm2_por_caja' => 1.44,
        'piezas_por_caja' => 4,
        'peso_caja_kg' => 30.00,
        'formato' => '60x60',
        'acabado' => 'Rustico',
        'origen' => 'China',
        'precio_base_bob' => 110.00,
        'cajas_por_palet' => 40
    ]
];

foreach ($productos_prueba as $prod) {
    // Si existe lo borramos (para que el test sea re-ejecutable)
    $ci->db->where('codigo', $prod['codigo']);
    $ci->db->delete('productos');

    $ci->db->insert('productos', $prod);
    $producto_id = $ci->db->insert_id();

    // Insertar precios escalonados (1 a 40: 140, 41 a 120: 138, 121 a 200: 136)
    $ci->db->delete('precios_escalonados', ['producto_id' => $producto_id]);
    $ci->db->insert('precios_escalonados', ['producto_id' => $producto_id, 'rango_min' => 1, 'rango_max' => 40, 'precio_caja_bob' => 140.00]);
    $ci->db->insert('precios_escalonados', ['producto_id' => $producto_id, 'rango_min' => 41, 'rango_max' => 120, 'precio_caja_bob' => 138.00]);
    $ci->db->insert('precios_escalonados', ['producto_id' => $producto_id, 'rango_min' => 121, 'rango_max' => 200, 'precio_caja_bob' => 136.00]);

    // Insertar Inventario
    // 300 m2 / 1.44 = 208.33 cajas. Redondeamos a 209 cajas físicas.
    $cajas = 209;
    
    // Asumimos almacen_id = 1 (galpon)
    $ci->db->delete('inventario_almacen', ['producto_id' => $producto_id, 'almacen_id' => 1]);
    $ci->db->insert('inventario_almacen', [
        'producto_id' => $producto_id,
        'almacen_id' => 1,
        'stock_fisico' => $cajas,
        'stock_reservado' => 0,
        'stock_disponible' => $cajas
    ]);

    echo "Producto insertado ID: $producto_id\n";
}

echo "Proceso terminado con exito.";
