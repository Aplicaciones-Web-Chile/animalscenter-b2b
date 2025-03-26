<?php
/**
 * Archivo de diagnóstico para identificar problemas en el servidor
 * Este archivo muestra información detallada sobre la configuración del servidor
 */

// Iniciar captura de salida
ob_start();

// Información básica del servidor
echo "<h1>Diagnóstico del Sistema B2B AnimalsCenter</h1>";
echo "<p>Fecha y hora: " . date('Y-m-d H:i:s') . "</p>";

// Verificar si estamos en Hostinger
if (function_exists('isHostinger')) {
    echo "<p>Entorno detectado: " . (isHostinger() ? "Hostinger" : "Local") . "</p>";
} else {
    echo "<p>Función isHostinger no disponible. Incluir config/app.php</p>";
    
    // Intentar cargar la configuración
    $appRoot = dirname(__DIR__);
    if (file_exists($appRoot . '/config/app.php')) {
        include_once $appRoot . '/config/app.php';
        echo "<p>Archivo config/app.php cargado.</p>";
        echo "<p>Entorno detectado después de cargar: " . (isHostinger() ? "Hostinger" : "Local") . "</p>";
    } else {
        echo "<p>Error: No se pudo encontrar el archivo config/app.php</p>";
    }
}

// Información del servidor
echo "<h2>Información del Servidor</h2>";
echo "<ul>";
echo "<li>PHP Version: " . phpversion() . "</li>";
echo "<li>Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'No disponible') . "</li>";
echo "<li>Server Name: " . ($_SERVER['SERVER_NAME'] ?? 'No disponible') . "</li>";
echo "<li>Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'No disponible') . "</li>";
echo "<li>Script Filename: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'No disponible') . "</li>";
echo "<li>Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'No disponible') . "</li>";
echo "</ul>";

// Verificar directorios y permisos
echo "<h2>Directorios y Permisos</h2>";
$directories = [
    'APP_ROOT' => defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__),
    'public' => dirname(__FILE__),
    'config' => dirname(__DIR__) . '/config',
    'logs' => dirname(__DIR__) . '/logs',
];

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Directorio</th><th>Existe</th><th>Permisos</th><th>Escribible</th></tr>";

foreach ($directories as $name => $path) {
    $exists = is_dir($path);
    $perms = $exists ? substr(sprintf('%o', fileperms($path)), -4) : 'N/A';
    $writable = $exists ? is_writable($path) ? 'Sí' : 'No' : 'N/A';
    
    echo "<tr>";
    echo "<td>$name</td>";
    echo "<td>" . ($exists ? 'Sí' : 'No') . "</td>";
    echo "<td>$perms</td>";
    echo "<td>$writable</td>";
    echo "</tr>";
}
echo "</table>";

// Verificar archivos críticos
echo "<h2>Archivos Críticos</h2>";
$files = [
    '.htaccess (raíz)' => dirname(__DIR__) . '/.htaccess',
    '.htaccess (public)' => __DIR__ . '/.htaccess',
    'index.php' => __DIR__ . '/index.php',
    'app.php' => dirname(__DIR__) . '/config/app.php',
    'database.php' => dirname(__DIR__) . '/config/database.php',
    '.env' => dirname(__DIR__) . '/.env',
];

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Archivo</th><th>Existe</th><th>Tamaño</th><th>Última modificación</th></tr>";

foreach ($files as $name => $path) {
    $exists = file_exists($path);
    $size = $exists ? filesize($path) . ' bytes' : 'N/A';
    $modified = $exists ? date('Y-m-d H:i:s', filemtime($path)) : 'N/A';
    
    echo "<tr>";
    echo "<td>$name</td>";
    echo "<td>" . ($exists ? 'Sí' : 'No') . "</td>";
    echo "<td>$size</td>";
    echo "<td>$modified</td>";
    echo "</tr>";
}
echo "</table>";

// Verificar configuración de PHP
echo "<h2>Configuración de PHP</h2>";
$phpSettings = [
    'display_errors' => ini_get('display_errors'),
    'error_reporting' => ini_get('error_reporting'),
    'log_errors' => ini_get('log_errors'),
    'error_log' => ini_get('error_log'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
];

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Configuración</th><th>Valor</th></tr>";

foreach ($phpSettings as $name => $value) {
    echo "<tr>";
    echo "<td>$name</td>";
    echo "<td>$value</td>";
    echo "</tr>";
}
echo "</table>";

// Verificar extensiones de PHP
echo "<h2>Extensiones de PHP</h2>";
$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'curl', 'fileinfo'];
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Extensión</th><th>Cargada</th></tr>";

foreach ($requiredExtensions as $ext) {
    echo "<tr>";
    echo "<td>$ext</td>";
    echo "<td>" . (extension_loaded($ext) ? 'Sí' : 'No') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Verificar conexión a la base de datos
echo "<h2>Prueba de Conexión a la Base de Datos</h2>";
if (function_exists('getDbConnection')) {
    try {
        $db = getDbConnection();
        echo "<p style='color:green;'>✓ Conexión a la base de datos exitosa</p>";
        
        // Mostrar información de la base de datos
        $stmt = $db->query("SELECT VERSION() as version");
        $version = $stmt->fetch()['version'];
        echo "<p>Versión de MySQL: $version</p>";
    } catch (Exception $e) {
        echo "<p style='color:red;'>✗ Error de conexión a la base de datos: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<p>Función getDbConnection no disponible. Incluir config/database.php</p>";
    
    // Intentar cargar la configuración de la base de datos
    if (file_exists(dirname(__DIR__) . '/config/database.php')) {
        include_once dirname(__DIR__) . '/config/database.php';
        echo "<p>Archivo config/database.php cargado.</p>";
        
        if (function_exists('getDbConnection')) {
            try {
                $db = getDbConnection();
                echo "<p style='color:green;'>✓ Conexión a la base de datos exitosa después de cargar database.php</p>";
            } catch (Exception $e) {
                echo "<p style='color:red;'>✗ Error de conexión a la base de datos: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        } else {
            echo "<p>Error: La función getDbConnection no está disponible después de cargar database.php</p>";
        }
    } else {
        echo "<p>Error: No se pudo encontrar el archivo config/database.php</p>";
    }
}

// Verificar errores recientes
echo "<h2>Errores Recientes</h2>";
$logFile = dirname(__DIR__) . '/logs/app.log';
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    if (!empty($logContent)) {
        echo "<pre>" . htmlspecialchars(substr($logContent, -4096)) . "</pre>";
        echo "<p>Mostrando los últimos 4KB del archivo de log. Tamaño total: " . filesize($logFile) . " bytes</p>";
    } else {
        echo "<p>El archivo de log está vacío.</p>";
    }
} else {
    echo "<p>No se encontró el archivo de log en: $logFile</p>";
    
    // Intentar crear el archivo de log
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
        echo "<p>Se creó el directorio de logs.</p>";
    }
    
    $testLog = "Prueba de escritura en el log: " . date('Y-m-d H:i:s') . "\n";
    if (file_put_contents($logFile, $testLog)) {
        echo "<p>Se creó el archivo de log con éxito.</p>";
    } else {
        echo "<p>No se pudo crear el archivo de log. Verificar permisos.</p>";
    }
}

// Capturar la salida
$output = ob_get_clean();

// Guardar una copia del diagnóstico en el log
if (function_exists('logError')) {
    logError("Diagnóstico ejecutado", "INFO", ["url" => $_SERVER['REQUEST_URI'] ?? 'N/A']);
}

// Mostrar el resultado
echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Diagnóstico Sistema B2B AnimalsCenter</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        h1, h2 { color: #333; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th { background-color: #f2f2f2; }
        pre { background-color: #f5f5f5; padding: 10px; overflow: auto; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    $output
</body>
</html>";
