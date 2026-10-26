<?php
/**
 * Script de diagnóstico de conexión y entorno para producción
 * Ubicación: backend/check_status.php
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

define('BASEPATH', '1');

$diagnostic = [
    'fecha' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'extension_mysqli' => extension_loaded('mysqli'),
    'extension_json' => extension_loaded('json'),
    'extension_mbstring' => extension_loaded('mbstring'),
    'database' => []
];

// Cargar configuración de base de datos de CodeIgniter
$databaseFile = __DIR__ . '/application/config/database.php';
if (!file_exists($databaseFile)) {
    $diagnostic['database']['error'] = 'No se encontró el archivo application/config/database.php';
    echo json_encode($diagnostic, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

require_once $databaseFile;
$dbConf = isset($db['default']) ? $db['default'] : null;

if (!$dbConf) {
    $diagnostic['database']['error'] = 'No existe la configuración $db[\'default\']';
    echo json_encode($diagnostic, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$diagnostic['database']['config'] = [
    'hostname' => $dbConf['hostname'],
    'username' => $dbConf['username'],
    'database' => $dbConf['database'],
    'password_configurado' => !empty($dbConf['password']) ? 'SÍ' : 'NO (Vacío)'
];

// Probar conexión MySQL directa con mysqli
$mysqli = @new mysqli(
    $dbConf['hostname'],
    $dbConf['username'],
    $dbConf['password'],
    $dbConf['database']
);

$hostConectado = $dbConf['hostname'];

// Si falló el principal, probar con el host de failover si existe
if ($mysqli->connect_error && !empty($dbConf['failover'][0]['hostname'])) {
    $failoverHost = $dbConf['failover'][0]['hostname'];
    $mysqliFailover = @new mysqli(
        $failoverHost,
        $dbConf['username'],
        $dbConf['password'],
        $dbConf['database']
    );
    if (!$mysqliFailover->connect_error) {
        $mysqli = $mysqliFailover;
        $hostConectado = $failoverHost;
    }
}

if ($mysqli->connect_error) {
    $diagnostic['database']['estado'] = 'ERROR_CONEXION';
    $diagnostic['database']['error_mensaje'] = $mysqli->connect_error;
    $diagnostic['database']['error_codigo'] = $mysqli->connect_errno;
    if (strpos($mysqli->connect_error, 'Access denied') !== false) {
        $diagnostic['database']['sugerencia'] = 'El usuario no tiene permisos para conectarse desde esta IP. Si se conecta desde su PC local, debe autorizar su IP o colocar % (comodín) en cPanel -> "MySQL Remoto" (Remote MySQL). Si está corriendo en el servidor producción, MySQL conectará por localhost.';
    } else {
        $diagnostic['database']['sugerencia'] = 'Verifique el usuario, contraseña y nombre de la base de datos en backend/application/config/database.php.';
    }
} else {
    $diagnostic['database']['estado'] = 'CONECTADO_EXITOSAMENTE';
    $diagnostic['database']['host_activo'] = $hostConectado;
    
    // Contar tablas
    $result = $mysqli->query("SHOW TABLES");
    $tablas = [];
    if ($result) {
        while ($row = $result->fetch_array()) {
            $tablas[] = $row[0];
        }
    }
    $diagnostic['database']['total_tablas'] = count($tablas);
    $diagnostic['database']['tablas_criticas'] = [
        'usuarios' => in_array('usuarios', $tablas),
        'roles' => in_array('roles', $tablas),
        'rol_permisos' => in_array('rol_permisos', $tablas),
        'menus' => in_array('menus', $tablas)
    ];

    if (!in_array('usuarios', $tablas)) {
        $diagnostic['database']['alerta'] = 'La tabla `usuarios` no existe en esta base de datos. Debe importar el archivo bd/BDwisdom.sql.';
    }

    $mysqli->close();
}

echo json_encode($diagnostic, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
