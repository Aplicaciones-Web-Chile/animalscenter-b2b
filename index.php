<?php
/**
 * Front Controller principal para el Sistema B2B AnimalsCenter
 * Este archivo redirige todas las solicitudes al directorio public
 */

// Definir la raíz de la aplicación
define('APP_ROOT', __DIR__);

// Verificar si estamos en Hostinger
function isHostingerTemp() {
    return (strpos($_SERVER['DOCUMENT_ROOT'] ?? '', 'public_html') !== false);
}

// Crear un archivo de log para registrar errores
function logErrorTemp($message) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/redirect.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

try {
    // Registrar información del servidor
    $serverInfo = [
        'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] ?? 'No disponible',
        'SCRIPT_FILENAME' => $_SERVER['SCRIPT_FILENAME'] ?? 'No disponible',
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'No disponible',
        'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? 'No disponible',
        'PHP_SELF' => $_SERVER['PHP_SELF'] ?? 'No disponible',
        'HOSTINGER' => isHostingerTemp() ? 'Sí' : 'No'
    ];
    
    logErrorTemp("Acceso a index.php raíz: " . json_encode($serverInfo));
    
    // Determinar la ruta al directorio public
    if (isHostingerTemp()) {
        // En Hostinger
        $publicDir = 'public';
    } else {
        // En local
        $publicDir = 'public';
    }
    
    // Construir la ruta relativa para la redirección
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    
    // Eliminar el nombre del script de la URI si está presente
    if (!empty($scriptName) && strpos($requestUri, $scriptName) === 0) {
        $baseUri = substr($requestUri, strlen($scriptName));
    } else {
        $baseUri = $requestUri;
    }
    
    // Si estamos accediendo directamente a index.php, redirigir a la raíz
    if (empty($baseUri) || $baseUri === '/') {
        $redirectUrl = "./$publicDir/";
    } else {
        $redirectUrl = "./$publicDir$baseUri";
    }
    
    logErrorTemp("Redirigiendo a: $redirectUrl");
    
    // Redirigir al directorio public
    header("Location: $redirectUrl");
    exit;
} catch (Exception $e) {
    // Registrar cualquier error
    logErrorTemp("Error en redirección: " . $e->getMessage());
    
    // Mostrar un mensaje de error
    echo "Ha ocurrido un error al redirigir al directorio public. Por favor, contacte al administrador.";
    echo "<br>Error: " . $e->getMessage();
    exit;
}
