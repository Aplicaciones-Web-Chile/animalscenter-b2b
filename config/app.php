<?php
/**
 * Configuración general de la aplicación
 * Este archivo contiene constantes y configuraciones globales
 */

// Cargar variables de entorno si no se han cargado
if (file_exists(__DIR__ . '/../.env') && !isset($_ENV['APP_ENV'])) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Configuración de la aplicación
define('APP_NAME', $_ENV['APP_NAME'] ?? 'Sistema B2B AnimalsCenter');
define('APP_ENV', $_ENV['APP_ENV'] ?? 'production');
define('APP_DEBUG', filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN));
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost/b2b');

// Configuración de sesiones
define('SESSION_LIFETIME', intval($_ENV['SESSION_LIFETIME'] ?? 120));
define('SECURE_COOKIES', filter_var($_ENV['SECURE_COOKIES'] ?? false, FILTER_VALIDATE_BOOLEAN));

// Configuración de seguridad
define('ALLOWED_IPS', explode(',', $_ENV['ALLOWED_IPS'] ?? ''));

// Configuración de la API ERP
define('ERP_API_URL', $_ENV['ERP_API_URL'] ?? '');
define('ERP_API_KEY', $_ENV['ERP_API_KEY'] ?? '');
define('ERP_API_TIMEOUT', intval($_ENV['ERP_API_TIMEOUT'] ?? 30));

/**
 * Determina si el usuario actual está en una IP permitida
 * 
 * @return bool True si la IP está permitida o si no hay restricciones
 */
function isAllowedIP() {
    if (empty(ALLOWED_IPS) || in_array('*', ALLOWED_IPS)) {
        return true;
    }
    
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
    return in_array($clientIP, ALLOWED_IPS);
}

/**
 * Configura el entorno según el modo (desarrollo o producción)
 */
function configureEnvironment() {
    // En desarrollo mostramos todos los errores
    if (APP_ENV === 'development') {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
    } else {
        ini_set('display_errors', 0);
        ini_set('display_startup_errors', 0);
        error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
    }
    
    // Configurar opciones de sesión
    ini_set('session.cookie_lifetime', SESSION_LIFETIME * 60);
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME * 60);
    
    if (SECURE_COOKIES) {
        ini_set('session.cookie_secure', 1);
        ini_set('session.cookie_httponly', 1);
    }
}

// Configurar el entorno
configureEnvironment();
