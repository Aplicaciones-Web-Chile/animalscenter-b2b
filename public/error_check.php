<?php
/**
 * Archivo para capturar y mostrar errores detallados
 * Útil para diagnosticar problemas en producción
 */

// Configuración para mostrar todos los errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Función para capturar información del sistema
function getSystemInfo() {
    $info = [
        'PHP Version' => phpversion(),
        'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'No disponible',
        'Document Root' => $_SERVER['DOCUMENT_ROOT'] ?? 'No disponible',
        'Script Filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'No disponible',
        'Request URI' => $_SERVER['REQUEST_URI'] ?? 'No disponible',
        'Server Name' => $_SERVER['SERVER_NAME'] ?? 'No disponible',
        'HTTP Host' => $_SERVER['HTTP_HOST'] ?? 'No disponible',
        'Remote Addr' => $_SERVER['REMOTE_ADDR'] ?? 'No disponible',
        'Server Protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'No disponible',
        'Request Method' => $_SERVER['REQUEST_METHOD'] ?? 'No disponible',
        'Query String' => $_SERVER['QUERY_STRING'] ?? 'No disponible',
        'HTTP Referer' => $_SERVER['HTTP_REFERER'] ?? 'No disponible',
        'HTTP User Agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'No disponible',
        'HTTPS' => isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : 'No disponible',
        'Memory Limit' => ini_get('memory_limit'),
        'Post Max Size' => ini_get('post_max_size'),
        'Upload Max Filesize' => ini_get('upload_max_filesize'),
        'Max Execution Time' => ini_get('max_execution_time') . ' seconds',
        'Default Charset' => ini_get('default_charset'),
        'Current Directory' => getcwd(),
        'Parent Directory' => dirname(__DIR__),
        'Free Disk Space' => function_exists('disk_free_space') ? round(disk_free_space('.') / 1024 / 1024) . ' MB' : 'No disponible',
        'Total Disk Space' => function_exists('disk_total_space') ? round(disk_total_space('.') / 1024 / 1024) . ' MB' : 'No disponible',
    ];
    
    return $info;
}

// Función para verificar archivos críticos
function checkCriticalFiles() {
    $rootDir = dirname(__DIR__);
    $files = [
        'public/index.php' => $rootDir . '/public/index.php',
        'config/app.php' => $rootDir . '/config/app.php',
        'config/database.php' => $rootDir . '/config/database.php',
        '.htaccess (root)' => $rootDir . '/.htaccess',
        '.htaccess (public)' => $rootDir . '/public/.htaccess',
        '.env' => $rootDir . '/.env',
    ];
    
    $results = [];
    foreach ($files as $name => $path) {
        $results[$name] = [
            'exists' => file_exists($path),
            'readable' => is_readable($path),
            'size' => file_exists($path) ? filesize($path) . ' bytes' : 'N/A',
            'modified' => file_exists($path) ? date('Y-m-d H:i:s', filemtime($path)) : 'N/A',
            'permissions' => file_exists($path) ? substr(sprintf('%o', fileperms($path)), -4) : 'N/A',
        ];
    }
    
    return $results;
}

// Función para verificar extensiones de PHP
function checkPhpExtensions() {
    $required = ['pdo', 'pdo_mysql', 'mysqli', 'json', 'mbstring', 'curl', 'fileinfo', 'gd', 'xml', 'zip'];
    $results = [];
    
    foreach ($required as $ext) {
        $results[$ext] = extension_loaded($ext);
    }
    
    return $results;
}

// Función para verificar errores recientes
function getRecentErrors() {
    $logFile = dirname(__DIR__) . '/logs/app.log';
    if (file_exists($logFile) && is_readable($logFile)) {
        $content = file_get_contents($logFile);
        if (!empty($content)) {
            // Obtener las últimas 20 líneas
            $lines = explode(PHP_EOL, $content);
            $lines = array_filter($lines);
            $lines = array_slice($lines, -20);
            return $lines;
        }
    }
    
    return ['No se encontraron logs o el archivo no es accesible'];
}

// Función para verificar la conexión a la base de datos
function checkDatabaseConnection() {
    $result = [
        'status' => 'No verificado',
        'message' => 'No se ha intentado la conexión',
        'details' => []
    ];
    
    // Intentar cargar la configuración de la base de datos
    $dbConfigFile = dirname(__DIR__) . '/config/database.php';
    if (file_exists($dbConfigFile)) {
        try {
            include_once $dbConfigFile;
            
            if (function_exists('getDbConnection')) {
                try {
                    $db = getDbConnection();
                    $result['status'] = 'Éxito';
                    $result['message'] = 'Conexión a la base de datos exitosa';
                    
                    // Obtener información de la base de datos
                    $stmt = $db->query("SELECT VERSION() as version");
                    $version = $stmt->fetch(PDO::FETCH_ASSOC);
                    $result['details']['version'] = $version['version'] ?? 'Desconocida';
                    
                    // Verificar tablas
                    $stmt = $db->query("SHOW TABLES");
                    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    $result['details']['tables'] = $tables;
                    $result['details']['table_count'] = count($tables);
                } catch (PDOException $e) {
                    $result['status'] = 'Error';
                    $result['message'] = 'Error de conexión: ' . $e->getMessage();
                    $result['details']['error_code'] = $e->getCode();
                }
            } else {
                $result['status'] = 'Error';
                $result['message'] = 'La función getDbConnection no está disponible';
            }
        } catch (Throwable $e) {
            $result['status'] = 'Error';
            $result['message'] = 'Error al cargar la configuración: ' . $e->getMessage();
            $result['details']['error_code'] = $e->getCode();
            $result['details']['file'] = $e->getFile();
            $result['details']['line'] = $e->getLine();
        }
    } else {
        $result['status'] = 'Error';
        $result['message'] = 'Archivo de configuración de base de datos no encontrado';
    }
    
    return $result;
}

// Función para probar la carga de archivos críticos
function testIncludeFiles() {
    $rootDir = dirname(__DIR__);
    $files = [
        'config/app.php' => $rootDir . '/config/app.php',
        'config/database.php' => $rootDir . '/config/database.php',
    ];
    
    $results = [];
    foreach ($files as $name => $path) {
        try {
            if (file_exists($path)) {
                ob_start();
                $included = include_once $path;
                $output = ob_get_clean();
                
                $results[$name] = [
                    'status' => $included ? 'Éxito' : 'Error',
                    'message' => $included ? 'Archivo incluido correctamente' : 'El archivo no devolvió true',
                    'output' => !empty($output) ? $output : 'Sin salida',
                ];
            } else {
                $results[$name] = [
                    'status' => 'Error',
                    'message' => 'Archivo no encontrado',
                ];
            }
        } catch (Throwable $e) {
            $results[$name] = [
                'status' => 'Error',
                'message' => 'Excepción: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }
    }
    
    return $results;
}

// Recopilar toda la información
$systemInfo = getSystemInfo();
$criticalFiles = checkCriticalFiles();
$phpExtensions = checkPhpExtensions();
$recentErrors = getRecentErrors();
$dbConnection = checkDatabaseConnection();
$includeTests = testIncludeFiles();

// Guardar la información en un archivo de log
$logFile = dirname(__DIR__) . '/logs/error_check.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'system_info' => $systemInfo,
    'critical_files' => $criticalFiles,
    'php_extensions' => $phpExtensions,
    'db_connection' => $dbConnection,
    'include_tests' => $includeTests,
];

file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND);

// Mostrar la información
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Errores - Sistema B2B AnimalsCenter</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        h1, h2, h3 { color: #333; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        pre { background-color: #f5f5f5; padding: 10px; overflow: auto; }
        .section { margin-bottom: 30px; }
    </style>
</head>
<body>
    <h1>Verificación de Errores - Sistema B2B AnimalsCenter</h1>
    <p>Fecha y hora: <?php echo date('Y-m-d H:i:s'); ?></p>
    
    <div class="section">
        <h2>Información del Sistema</h2>
        <table>
            <tr><th>Parámetro</th><th>Valor</th></tr>
            <?php foreach ($systemInfo as $key => $value): ?>
            <tr>
                <td><?php echo htmlspecialchars($key); ?></td>
                <td><?php echo htmlspecialchars($value); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    
    <div class="section">
        <h2>Archivos Críticos</h2>
        <table>
            <tr>
                <th>Archivo</th>
                <th>Existe</th>
                <th>Legible</th>
                <th>Tamaño</th>
                <th>Modificado</th>
                <th>Permisos</th>
            </tr>
            <?php foreach ($criticalFiles as $name => $info): ?>
            <tr>
                <td><?php echo htmlspecialchars($name); ?></td>
                <td class="<?php echo $info['exists'] ? 'success' : 'error'; ?>">
                    <?php echo $info['exists'] ? 'Sí' : 'No'; ?>
                </td>
                <td class="<?php echo $info['readable'] ? 'success' : 'error'; ?>">
                    <?php echo $info['readable'] ? 'Sí' : 'No'; ?>
                </td>
                <td><?php echo htmlspecialchars($info['size']); ?></td>
                <td><?php echo htmlspecialchars($info['modified']); ?></td>
                <td><?php echo htmlspecialchars($info['permissions']); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    
    <div class="section">
        <h2>Extensiones de PHP</h2>
        <table>
            <tr><th>Extensión</th><th>Cargada</th></tr>
            <?php foreach ($phpExtensions as $ext => $loaded): ?>
            <tr>
                <td><?php echo htmlspecialchars($ext); ?></td>
                <td class="<?php echo $loaded ? 'success' : 'error'; ?>">
                    <?php echo $loaded ? 'Sí' : 'No'; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    
    <div class="section">
        <h2>Prueba de Inclusión de Archivos</h2>
        <table>
            <tr><th>Archivo</th><th>Estado</th><th>Mensaje</th><th>Detalles</th></tr>
            <?php foreach ($includeTests as $name => $result): ?>
            <tr>
                <td><?php echo htmlspecialchars($name); ?></td>
                <td class="<?php echo $result['status'] === 'Éxito' ? 'success' : 'error'; ?>">
                    <?php echo htmlspecialchars($result['status']); ?>
                </td>
                <td><?php echo htmlspecialchars($result['message']); ?></td>
                <td>
                    <?php if (isset($result['output'])): ?>
                        <pre><?php echo htmlspecialchars($result['output']); ?></pre>
                    <?php endif; ?>
                    <?php if (isset($result['file'])): ?>
                        Archivo: <?php echo htmlspecialchars($result['file']); ?><br>
                        Línea: <?php echo htmlspecialchars($result['line']); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    
    <div class="section">
        <h2>Conexión a la Base de Datos</h2>
        <table>
            <tr><th>Estado</th><th>Mensaje</th></tr>
            <tr>
                <td class="<?php echo $dbConnection['status'] === 'Éxito' ? 'success' : 'error'; ?>">
                    <?php echo htmlspecialchars($dbConnection['status']); ?>
                </td>
                <td><?php echo htmlspecialchars($dbConnection['message']); ?></td>
            </tr>
        </table>
        
        <?php if (!empty($dbConnection['details'])): ?>
        <h3>Detalles de la Base de Datos</h3>
        <table>
            <?php foreach ($dbConnection['details'] as $key => $value): ?>
            <tr>
                <th><?php echo htmlspecialchars($key); ?></th>
                <td>
                    <?php if (is_array($value)): ?>
                        <pre><?php echo htmlspecialchars(implode(', ', $value)); ?></pre>
                    <?php else: ?>
                        <?php echo htmlspecialchars($value); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
    
    <div class="section">
        <h2>Errores Recientes</h2>
        <?php if (!empty($recentErrors)): ?>
        <pre><?php echo htmlspecialchars(implode(PHP_EOL, $recentErrors)); ?></pre>
        <?php else: ?>
        <p>No se encontraron errores recientes.</p>
        <?php endif; ?>
    </div>
    
    <div class="section">
        <h2>Prueba de Funcionalidad</h2>
        <p>Intenta acceder a los siguientes enlaces para probar diferentes partes del sistema:</p>
        <ul>
            <li><a href="index.php" target="_blank">Página principal (index.php)</a></li>
            <li><a href="login.php" target="_blank">Página de login (login.php)</a></li>
            <li><a href="diagnostico.php" target="_blank">Página de diagnóstico (diagnostico.php)</a></li>
        </ul>
    </div>
    
    <p>Esta información ha sido guardada en el archivo de log para referencia futura.</p>
</body>
</html>
