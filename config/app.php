<?php
/**
 * Configuración general de la aplicación
 * Este archivo contiene constantes y configuraciones globales
 */

// Detectar automáticamente el entorno (Hostinger o local)
function isHostinger()
{
    // Verifica si estamos en Hostinger basado en variables de servidor
    return (strpos($_SERVER['DOCUMENT_ROOT'] ?? '', 'public_html') !== false);
}

// Definir la raíz de la aplicación
define('APP_ROOT', dirname(__DIR__));

// Cargar autoloader de Composer
require_once APP_ROOT . '/vendor/autoload.php';

// Cargar variables de entorno si no se han cargado
if (file_exists(APP_ROOT . '/.env') && !isset($_ENV['APP_ENV'])) {
    $dotenv = Dotenv\Dotenv::createImmutable(APP_ROOT);
    $dotenv->load();
}

// Configuración de la aplicación
define('APP_NAME', $_ENV['APP_NAME'] ?? 'Sistema B2B AnimalsCenter');
define('APP_ENV', $_ENV['APP_ENV'] ?? 'production');
define('APP_DEBUG', filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN));
date_default_timezone_set($_ENV['APP_TZ'] ?? 'America/Santiago');

// Detección automática de URL base según el entorno
if (isHostinger()) {
    // En Hostinger
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('APP_URL', $protocol . $host);
    define('PUBLIC_DIR', '/public_html'); // En Hostinger el directorio público se llama public_html
} else {
    // En local
    define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost:8080');
    define('PUBLIC_DIR', '/public'); // En local el directorio público se llama public
}

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
function isAllowedIP()
{
    if (empty(ALLOWED_IPS) || in_array('*', ALLOWED_IPS)) {
        return true;
    }

    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
    return in_array($clientIP, ALLOWED_IPS);
}

/**
 * Función para registrar errores en un archivo centralizado
 *
 * @param string $message Mensaje de error
 * @param string $level Nivel de error (ERROR, WARNING, INFO)
 * @param array $context Contexto adicional
 * @return bool True si se pudo escribir en el log
 */
function logError($message, $level = 'ERROR', $context = [])
{
    // Definir la ruta del archivo de log
    $logFile = APP_ROOT . '/logs/app.log';

    // Crear el directorio de logs si no existe
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }

    // Formatear el mensaje de error
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
    $logMessage = "[$timestamp] [$level] $message$contextStr" . PHP_EOL;

    // Escribir en el archivo de log
    return file_put_contents($logFile, $logMessage, FILE_APPEND);
}

/**
 * Manejador personalizado de errores
 *
 * @param int $errno Número de error
 * @param string $errstr Mensaje de error
 * @param string $errfile Archivo donde ocurrió el error
 * @param int $errline Línea donde ocurrió el error
 * @return bool
 */
function customErrorHandler($errno, $errstr, $errfile, $errline)
{
    // Mapear el número de error a un nivel
    $level = 'ERROR';
    switch ($errno) {
        case E_WARNING:
        case E_USER_WARNING:
            $level = 'WARNING';
            break;
        case E_NOTICE:
        case E_USER_NOTICE:
            $level = 'NOTICE';
            break;
    }

    // Registrar el error
    logError($errstr, $level, [
        'file' => $errfile,
        'line' => $errline,
        'type' => $errno
    ]);

    // Devolver false para que PHP maneje el error de forma estándar
    return false;
}

/**
 * Manejador de excepciones no capturadas
 *
 * @param Throwable $exception La excepción no capturada
 */
function customExceptionHandler($exception)
{
    // Registrar la excepción
    logError($exception->getMessage(), 'CRITICAL', [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);

    // En producción, mostrar un mensaje genérico
    if (APP_ENV !== 'development') {
        echo "<h1>Error del sistema</h1>";
        echo "<p>Ha ocurrido un error inesperado. Por favor, contacte al administrador.</p>";
    } else {
        // En desarrollo, mostrar detalles de la excepción
        echo "<h1>Error: " . htmlspecialchars($exception->getMessage()) . "</h1>";
        echo "<p>Archivo: " . htmlspecialchars($exception->getFile()) . " en la línea " . $exception->getLine() . "</p>";
        echo "<h2>Stack Trace:</h2>";
        echo "<pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
    }

    exit(1);
}

/**
 * Configura el entorno según el modo (desarrollo o producción)
 */
function configureEnvironment()
{
    // En desarrollo mostramos todos los errores
    if (APP_ENV === 'development') {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
    } else {
        ini_set('display_errors', 0);
        ini_set('display_startup_errors', 0);
        error_reporting(E_ALL); // Capturar todos los errores para el log
    }

    // Configurar opciones de sesión
    #ini_set('session.cookie_lifetime', SESSION_LIFETIME * 60);
    #ini_set('session.gc_maxlifetime', SESSION_LIFETIME * 60);

    if (SECURE_COOKIES) {
        ini_set('session.cookie_secure', 1);
        ini_set('session.cookie_httponly', 1);
    }

    // Ajustar configuraciones según el entorno (Hostinger o local)
    if (isHostinger()) {
        // Configuraciones específicas para Hostinger
        ini_set('session.save_path', sys_get_temp_dir());

        // Ajustar rutas si es necesario para Hostinger
        // Ejemplo: define('UPLOADS_DIR', $_SERVER['DOCUMENT_ROOT'] . '/uploads');
    }

    // Establecer manejadores personalizados de errores y excepciones
    set_error_handler('customErrorHandler');
    set_exception_handler('customExceptionHandler');

    // Registrar errores fatales
    register_shutdown_function(function () {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            logError($error['message'], 'FATAL', [
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $error['type']
            ]);
        }
    });
}

// Configurar el entorno
configureEnvironment();
