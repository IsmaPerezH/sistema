<?php
/**
 * OctaBank - Configuración Global
 * Sistema Financiero del 8vo Semestre
 */

// Información del sistema
define('APP_NAME', 'OctaBank');
define('APP_SUBTITLE', 'Sistema Financiero del 8vo Semestre');
define('APP_VERSION', '1.0.0');
define('APP_CURRENCY', 'MXN');
define('APP_CURRENCY_SYMBOL', '$');
define('APP_LOCALE', 'es_MX');

// Rutas base
define('BASE_URL', 'http://localhost/sistema');
define('BASE_PATH', dirname(__DIR__));

// Configuración de Base de Datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'octabank');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configuración de sesión
define('SESSION_LIFETIME', 3600); // 1 hora
define('SESSION_NAME', 'octabank_session');

// Prefijo de número de cuenta
define('ACCOUNT_PREFIX', 'OB-');

// Zona horaria
date_default_timezone_set('America/Mexico_City');

// Errores (cambiar a false en producción)
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
