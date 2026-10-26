<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$active_group = 'default';
$query_builder = TRUE;

// Detección automática del entorno: servidor en la nube (wisdom.com.bo) vs remoto/local
$is_production = (
    isset($_SERVER['HTTP_HOST']) && 
    strpos($_SERVER['HTTP_HOST'], 'wisdom.com.bo') !== false
);

// En el servidor de producción (cPanel), la conexión local se hace por 'localhost'.
// Para conexiones remotas o locales desde fuera del servidor, se usa la IP '50.28.39.26'.
$db_hostname = getenv('DB_HOST') ?: ($is_production ? 'localhost' : '50.28.39.26');
$db_username = getenv('DB_USER') ?: 'admin';
$db_password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'Wisdom.2026';
$db_database = getenv('DB_NAME') ?: 'wisdomco_BDWisdom';

$db['default'] = array(
	'dsn'	=> '',
	'hostname' => $db_hostname,
	'username' => $db_username,
	'password' => $db_password,
	'database' => $db_database,
	'dbdriver' => 'mysqli',
	'dbprefix' => '',
	'pconnect' => FALSE,
	'db_debug' => FALSE,
	'cache_on' => FALSE,
	'cachedir' => '',
	'char_set' => 'utf8',
	'dbcollat' => 'utf8_general_ci',
	'swap_pre' => '',
	'encrypt' => FALSE,
	'compress' => FALSE,
	'stricton' => FALSE,
	'failover' => array(
		// Respaldo 1: Intentar con usuario wisdomco_admin si cPanel le agregó el prefijo de cuenta
		array(
			'hostname' => '50.28.39.26',
			'username' => 'wisdomco_admin',
			'password' => '.hackers.2026',
			'database' => 'wisdomco_BDWisdom',
			'dbdriver' => 'mysqli',
			'dbprefix' => '',
			'pconnect' => FALSE,
			'db_debug' => FALSE,
			'cache_on' => FALSE,
			'cachedir' => '',
			'char_set' => 'utf8',
			'dbcollat' => 'utf8_general_ci',
			'swap_pre' => '',
			'encrypt' => FALSE,
			'compress' => FALSE,
			'stricton' => FALSE
		),
		// Respaldo 2: Si estamos en local y el servidor remoto rechaza la IP, usar XAMPP local para no frenar el trabajo
		array(
			'hostname' => 'localhost',
			'username' => 'root',
			'password' => '',
			'database' => 'BDwisdom',
			'dbdriver' => 'mysqli',
			'dbprefix' => '',
			'pconnect' => FALSE,
			'db_debug' => FALSE,
			'cache_on' => FALSE,
			'cachedir' => '',
			'char_set' => 'utf8',
			'dbcollat' => 'utf8_general_ci',
			'swap_pre' => '',
			'encrypt' => FALSE,
			'compress' => FALSE,
			'stricton' => FALSE
		)
	),
	'save_queries' => TRUE
);
//cambios
