<?php
$pdo = new PDO('mysql:host=localhost;dbname=BDwisdom;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "Iniciando migracion estructural...\n";

// 1. Crear las 7 tablas de catalogo
$tablas = ['cat_paises', 'cat_fabricas', 'cat_clases', 'cat_acabados', 'cat_formatos', 'cat_colores', 'cat_disenos'];

foreach ($tablas as $t) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$t} (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL UNIQUE,
        abreviacion VARCHAR(30) DEFAULT NULL,
        estado TINYINT DEFAULT 1,
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");
}
echo "Tablas creadas.\n";

// 2. Extraer datos actuales de `productos` para Origen, Formato y Acabado
$origenes = $pdo->query("SELECT DISTINCT origen FROM productos WHERE origen IS NOT NULL AND origen != ''")->fetchAll(PDO::FETCH_COLUMN);
$formatos = $pdo->query("SELECT DISTINCT formato FROM productos WHERE formato IS NOT NULL AND formato != ''")->fetchAll(PDO::FETCH_COLUMN);
$acabados = $pdo->query("SELECT DISTINCT acabado FROM productos WHERE acabado IS NOT NULL AND acabado != ''")->fetchAll(PDO::FETCH_COLUMN);

// 3. Insertarlos en las tablas ignorando duplicados (por el UNIQUE)
$stmtPais = $pdo->prepare("INSERT IGNORE INTO cat_paises (nombre, abreviacion) VALUES (?, ?)");
foreach ($origenes as $o) {
    // Si la cadena es corta, podria ser la abreviacion, pero la usamos como nombre por ahora
    $stmtPais->execute([$o, substr($o, 0, 10)]);
}

$stmtFormato = $pdo->prepare("INSERT IGNORE INTO cat_formatos (nombre) VALUES (?)");
foreach ($formatos as $f) {
    $stmtFormato->execute([$f]);
}

$stmtAcabado = $pdo->prepare("INSERT IGNORE INTO cat_acabados (nombre) VALUES (?)");
foreach ($acabados as $a) {
    $stmtAcabado->execute([$a]);
}
echo "Datos maestros migrados.\n";

// 4. Alterar la tabla productos (añadir llaves)
// Verificar si las columnas ya existen antes de añadirlas para evitar fallos si se ejecuta 2 veces
$cols = $pdo->query("SHOW COLUMNS FROM productos")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('pais_id', $cols)) {
    $pdo->exec("ALTER TABLE productos 
        ADD COLUMN pais_id INT NULL,
        ADD COLUMN fabrica_id INT NULL,
        ADD COLUMN clase_id INT NULL,
        ADD COLUMN acabado_id INT NULL,
        ADD COLUMN formato_id INT NULL,
        ADD COLUMN color_id INT NULL,
        ADD COLUMN diseno_id INT NULL;
    ");
}

// 5. Update masivo relacionando IDs
// Actualizar Pais (Origen)
$pdo->exec("UPDATE productos p JOIN cat_paises c ON p.origen = c.nombre SET p.pais_id = c.id");
// Actualizar Formato
$pdo->exec("UPDATE productos p JOIN cat_formatos c ON p.formato = c.nombre SET p.formato_id = c.id");
// Actualizar Acabado
$pdo->exec("UPDATE productos p JOIN cat_acabados c ON p.acabado = c.nombre SET p.acabado_id = c.id");

echo "Llaves foraneas actualizadas.\n";

// 6. Finalmente, podemos mantener las columnas originales un rato, o borrarlas ya. 
// Es mas seguro borrarlas luego de cambiar el backend, o renombrarlas. 
// Las renombraremos para que si algun codigo viejo intenta usarlas falle y lo reparemos (o para tener backup)
if (in_array('origen', $cols)) {
    $pdo->exec("ALTER TABLE productos CHANGE origen origen_old VARCHAR(50)");
}
if (in_array('formato', $cols)) {
    $pdo->exec("ALTER TABLE productos CHANGE formato formato_old VARCHAR(50)");
}
if (in_array('acabado', $cols)) {
    $pdo->exec("ALTER TABLE productos CHANGE acabado acabado_old VARCHAR(50)");
}

echo "Migracion de base de datos finalizada con exito!\n";
