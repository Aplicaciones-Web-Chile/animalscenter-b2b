<?php
// Mostrar todos los errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Información básica
echo "<h1>Depuración PHP - Sistema B2B AnimalsCenter</h1>";
echo "<p>Fecha y hora: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>PHP Version: " . phpversion() . "</p>";

// Verificar si podemos incluir los archivos principales
echo "<h2>Prueba de inclusión de archivos principales</h2>";

try {
    echo "<h3>Intentando cargar app.php</h3>";
    require_once dirname(__DIR__) . '/config/app.php';
    echo "<p style='color:green;'>✓ Archivo app.php cargado correctamente</p>";
} catch (Throwable $e) {
    echo "<p style='color:red;'>✗ Error al cargar app.php: " . $e->getMessage() . "</p>";
    echo "<p>Archivo: " . $e->getFile() . " (línea " . $e->getLine() . ")</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

try {
    echo "<h3>Intentando cargar database.php</h3>";
    if (!defined('APP_ROOT')) {
        define('APP_ROOT', dirname(__DIR__));
    }
    require_once dirname(__DIR__) . '/config/database.php';
    echo "<p style='color:green;'>✓ Archivo database.php cargado correctamente</p>";
} catch (Throwable $e) {
    echo "<p style='color:red;'>✗ Error al cargar database.php: " . $e->getMessage() . "</p>";
    echo "<p>Archivo: " . $e->getFile() . " (línea " . $e->getLine() . ")</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// Verificar la estructura de directorios
echo "<h2>Estructura de directorios</h2>";
$directories = [
    'Raíz' => dirname(__DIR__),
    'Config' => dirname(__DIR__) . '/config',
    'Public' => __DIR__,
    'Logs' => dirname(__DIR__) . '/logs',
];

echo "<ul>";
foreach ($directories as $name => $path) {
    echo "<li>$name: ";
    if (is_dir($path)) {
        echo "<span style='color:green;'>Existe</span> (Permisos: " . substr(sprintf('%o', fileperms($path)), -4) . ")";
    } else {
        echo "<span style='color:red;'>No existe</span>";
    }
    echo "</li>";
}
echo "</ul>";

// Verificar archivos críticos
echo "<h2>Archivos críticos</h2>";
$files = [
    'index.php (raíz)' => dirname(__DIR__) . '/index.php',
    'index.php (public)' => __DIR__ . '/index.php',
    'login.php' => __DIR__ . '/login.php',
    'app.php' => dirname(__DIR__) . '/config/app.php',
    'database.php' => dirname(__DIR__) . '/config/database.php',
    '.htaccess (raíz)' => dirname(__DIR__) . '/.htaccess',
    '.htaccess (public)' => __DIR__ . '/.htaccess',
    '.env' => dirname(__DIR__) . '/.env',
];

echo "<ul>";
foreach ($files as $name => $path) {
    echo "<li>$name: ";
    if (file_exists($path)) {
        echo "<span style='color:green;'>Existe</span> (Tamaño: " . filesize($path) . " bytes, Permisos: " . substr(sprintf('%o', fileperms($path)), -4) . ")";
        
        // Mostrar el contenido de .htaccess
        if (strpos($name, '.htaccess') !== false) {
            echo "<br>Contenido:<br><pre>" . htmlspecialchars(file_get_contents($path)) . "</pre>";
        }
    } else {
        echo "<span style='color:red;'>No existe</span>";
    }
    echo "</li>";
}
echo "</ul>";

// Probar la conexión a la base de datos
echo "<h2>Prueba de conexión a la base de datos</h2>";
if (function_exists('getDbConnection')) {
    try {
        $db = getDbConnection();
        echo "<p style='color:green;'>✓ Conexión a la base de datos exitosa</p>";
    } catch (Throwable $e) {
        echo "<p style='color:red;'>✗ Error de conexión a la base de datos: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color:orange;'>La función getDbConnection no está disponible</p>";
}

// Verificar variables de entorno
echo "<h2>Variables de entorno disponibles</h2>";
if (!empty($_ENV)) {
    echo "<ul>";
    foreach ($_ENV as $key => $value) {
        echo "<li>$key: " . htmlspecialchars($value) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No hay variables de entorno disponibles a través de \$_ENV</p>";
    
    // Intentar cargar el archivo .env manualmente
    if (file_exists(dirname(__DIR__) . '/.env')) {
        echo "<p>El archivo .env existe. Intentando leerlo:</p>";
        $envContent = file_get_contents(dirname(__DIR__) . '/.env');
        echo "<pre>" . htmlspecialchars($envContent) . "</pre>";
    }
}

// Verificar si Composer está instalado correctamente
echo "<h2>Verificación de Composer</h2>";
if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    echo "<p style='color:green;'>✓ El archivo vendor/autoload.php existe</p>";
    
    try {
        require_once dirname(__DIR__) . '/vendor/autoload.php';
        echo "<p style='color:green;'>✓ Autoloader de Composer cargado correctamente</p>";
    } catch (Throwable $e) {
        echo "<p style='color:red;'>✗ Error al cargar el autoloader de Composer: " . $e->getMessage() . "</p>";
    }
    
    // Verificar si la clase Dotenv está disponible
    if (class_exists('Dotenv\Dotenv')) {
        echo "<p style='color:green;'>✓ La clase Dotenv está disponible</p>";
    } else {
        echo "<p style='color:red;'>✗ La clase Dotenv no está disponible</p>";
    }
} else {
    echo "<p style='color:red;'>✗ El archivo vendor/autoload.php no existe. Composer no está instalado correctamente.</p>";
}

// Mostrar las variables del servidor
echo "<h2>Variables del servidor</h2>";
echo "<pre>";
print_r($_SERVER);
echo "</pre>";

// Guardar toda esta información en un archivo de log
$logFile = dirname(__DIR__) . '/logs/debug.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

ob_start();
phpinfo();
$phpinfo = ob_get_clean();

$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion(),
    'server_variables' => $_SERVER,
    'env_variables' => $_ENV,
];

file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND);
