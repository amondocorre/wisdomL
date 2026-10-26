<?php
$pdo = new PDO('mysql:host=localhost;dbname=BDwisdom;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$diccionario = [
    'cat_paises' => [
        ['China', 'CH'],
        ['India', 'IN'],
        ['Perú', 'PE'],
        ['Brasil', 'BR'],
        ['Otros', 'OT']
    ],
    'cat_fabricas' => [
        ['Marco', 'MRC'],
        ['Nitco', 'NIT'],
        ['Celima', 'CEL'],
        ['Bellacer', 'BEL'],
        ['Pisopack', 'PIS']
    ],
    'cat_clases' => [
        ['Porcelanato', 'POR'],
        ['Semigres', 'SEM'],
        ['Cerámica Rectificada', 'CRE'],
        ['Cerámica Boleada', 'CBL']
    ],
    'cat_acabados' => [
        ['Vitrificado', 'VIT'],
        ['Pulido', 'PUL'],
        ['Satinado', 'SAT'],
        ['Rústico', 'BRI']
    ],
    'cat_formatos' => [
        ['25x40', '2540'],
        ['30x45', '3045'],
        ['30x60', '3060'],
        ['15x60', '1560'],
        ['15x90', '1590'],
        ['46x46', '4646'],
        ['51x51', '5151'],
        ['60x60', '6060'],
        ['61x61', '6161'],
        ['60x120', '60120'],
        ['18x113', '18113'],
        ['56x113', '56113'],
        ['72x72', '7272'],
        ['74x74', '7474']
    ],
    'cat_colores' => [
        ['Blanco', 'BCO'],
        ['Beige', 'BEI'],
        ['Gris', 'GRI'],
        ['Negro', 'NEG'],
        ['Otros', 'OT']
    ],
    'cat_disenos' => [
        ['Carrara 01', 'CAR01'],
        ['Mármol 02', 'MAR02'],
        ['Madera 03', 'MAD03'],
        ['Cemento 01', 'CON01']
    ]
];

echo "Iniciando carga de diccionario de datos...\n";

foreach ($diccionario as $tabla => $datos) {
    // La columna 'nombre' debe ser UNIQUE para que ON DUPLICATE KEY UPDATE funcione,
    // pero si por algun motivo fallase (ej: el usuario tiene "60x60" y aqui es "60x60"), lo manejaremos
    $stmt = $pdo->prepare("INSERT INTO {$tabla} (nombre, abreviacion) VALUES (?, ?) ON DUPLICATE KEY UPDATE abreviacion = VALUES(abreviacion)");
    
    foreach ($datos as $fila) {
        $nombre = $fila[0];
        $abreviacion = $fila[1];
        
        try {
            $stmt->execute([$nombre, $abreviacion]);
        } catch (Exception $e) {
            echo "Error en {$tabla} con {$nombre}: " . $e->getMessage() . "\n";
        }
    }
}

echo "Diccionario (Nomenclatura oficial) cargado con exito en los catalogos.\n";
