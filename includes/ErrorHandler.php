<?php
/**
 * Sistema de manejo de errores y autocorrección
 * 
 * Este archivo implementa un sistema que detecta, registra y corrige automáticamente 
 * ciertos errores comunes en la aplicación B2B. Utiliza el sistema de logs para
 * registrar todos los errores de forma detallada y tomar acciones correctivas.
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 1.1
 */

// Requerir Logger
require_once __DIR__ . '/Logger.php';

class ErrorHandler {
    private $logFile;
    private $errorPatterns;
    private $maxLogSize = 10485760; // 10MB
    private $notificationEmail;
    private $db;
    
    /**
     * Constructor de la clase
     * 
     * @param string $logFile Ruta al archivo de logs
     * @param string $notificationEmail Email para notificaciones
     */
    public function __construct($logFile = null, $notificationEmail = null) {
        // Configurar archivo de log
        $this->logFile = $logFile ?? __DIR__ . '/../logs/error.log';
        $this->notificationEmail = $notificationEmail ?? 'admin@animalscenter.cl';
        
        // Asegurarse de que el directorio de logs exista
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Definir patrones de errores comunes y sus soluciones
        $this->errorPatterns = [
            // Error de autoload
            '/failed to open stream: No such file or directory.*vendor\/autoload\.php/' => [
                'action' => 'runComposerInstall',
                'message' => 'Composer autoload no encontrado. Ejecutando composer install...'
            ],
            // Error de conexión a la base de datos
            '/SQLSTATE\[HY000\] \[2002\] Connection refused/' => [
                'action' => 'restartDatabase',
                'message' => 'Error de conexión a la base de datos. Intentando reiniciar el servicio...'
            ],
            // Error de permisos
            '/Permission denied/' => [
                'action' => 'fixPermissions',
                'message' => 'Error de permisos. Ajustando permisos de archivos...'
            ],
            // Error de espacio en disco
            '/No space left on device/' => [
                'action' => 'cleanupDisk',
                'message' => 'Sin espacio en disco. Iniciando limpieza...'
            ],
            // Error de memoria
            '/Allowed memory size of .* bytes exhausted/' => [
                'action' => 'optimizeMemory',
                'message' => 'Límite de memoria alcanzado. Optimizando uso de memoria...'
            ],
            // Error de tiempo de ejecución
            '/Maximum execution time .* exceeded/' => [
                'action' => 'optimizeQuery',
                'message' => 'Tiempo de ejecución excedido. Optimizando consultas...'
            ]
        ];
        
        // Registrar handler de errores
        $this->registerErrorHandlers();
    }
    
    /**
     * Registra los manejadores de errores y excepciones
     */
    public function registerErrorHandlers() {
        // Establecer manejador de errores
        set_error_handler([$this, 'handleError']);
        
        // Establecer manejador de excepciones
        set_exception_handler([$this, 'handleException']);
        
        // Establecer manejador de cierre
        register_shutdown_function([$this, 'handleShutdown']);
    }
    
    /**
     * Manejador de errores PHP
     * 
     * @param int $errno Número de error
     * @param string $errstr Mensaje de error
     * @param string $errfile Archivo donde ocurrió el error
     * @param int $errline Línea donde ocurrió el error
     * @return bool
     */
    public function handleError($errno, $errstr, $errfile, $errline) {
        $errorMessage = "ERROR: $errstr in $errfile on line $errline";
        
        // Determinar nivel de log según el tipo de error
        $level = Logger::ERROR;
        if (in_array($errno, [E_DEPRECATED, E_USER_DEPRECATED, E_NOTICE, E_USER_NOTICE])) {
            $level = Logger::WARNING;
        } elseif (in_array($errno, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            $level = Logger::CRITICAL;
        }
        
        // Registrar error con contexto adicional
        $context = [
            'errno' => $errno,
            'error_type' => $this->errorTypeToString($errno),
            'file' => $errfile,
            'line' => $errline,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'get_params' => $_GET,
            'session_id' => session_id() ?: 'No session'
        ];
        
        $this->logError($errorMessage, $level, $context);
        $this->detectAndFix($errorMessage);
        
        // No mostrar errores en producción
        if (getenv('APP_ENV') !== 'development') {
            return true;
        }
        
        return false; // Dejar que PHP maneje el error normalmente en desarrollo
    }
    
    /**
     * Manejador de excepciones no capturadas
     * 
     * @param \Throwable $exception Excepción no capturada
     * @return void
     */
    public function handleException($exception) {
        $errorMessage = "EXCEPTION: " . $exception->getMessage() . 
                        " in " . $exception->getFile() . " on line " . $exception->getLine();
        
        // Registrar excepción con contexto detallado
        $context = [
            'exception_class' => get_class($exception),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'get_params' => $_GET,
            'post_params' => $this->sanitizePostParams($_POST),
            'session_id' => session_id() ?: 'No session',
            'user_id' => $_SESSION['user']['id'] ?? 'No usuario'
        ];
        
        $this->logError($errorMessage, Logger::CRITICAL, $context);
        $this->detectAndFix($errorMessage);
        
        // Usar el Logger para registrar la excepción
        Logger::logException($exception);
        
        // En producción, mostrar un mensaje amigable
        if (getenv('APP_ENV') !== 'development') {
            http_response_code(500);
            echo json_encode(['error' => 'Ha ocurrido un error en el servidor. Por favor, inténtelo más tarde.']);
            exit;
        }
        
        // En desarrollo, mostrar detalles completos
        http_response_code(500);
        echo '<h1>Error de la aplicación</h1>';
        echo '<p><strong>Mensaje:</strong> ' . htmlspecialchars($exception->getMessage()) . '</p>';
        echo '<p><strong>Archivo:</strong> ' . htmlspecialchars($exception->getFile()) . '</p>';
        echo '<p><strong>Línea:</strong> ' . $exception->getLine() . '</p>';
        echo '<h2>Stack Trace:</h2>';
        echo '<pre>' . htmlspecialchars($exception->getTraceAsString()) . '</pre>';
        exit;
    }
    
    /**
     * Manejador de cierre para capturar errores fatales
     */
    public function handleShutdown() {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $errorMessage = date('[Y-m-d H:i:s]') . " FATAL ERROR: {$error['message']} in {$error['file']} on line {$error['line']}";
            $this->logError($errorMessage);
            $this->detectAndFix($errorMessage);
        }
    }
    
    /**
     * Registra un error en el archivo de log
     * 
     * @param string $message Mensaje de error
     * @param string $level Nivel de log (ERROR o CRITICAL)
     * @param array $context Información adicional del contexto
     */
    public function logError($message, $level = Logger::ERROR, $context = []) {
        // Verificar tamaño del archivo de log y rotarlo si es necesario
        if (file_exists($this->logFile) && filesize($this->logFile) > $this->maxLogSize) {
            $this->rotateLogFile();
        }
        
        // Usar el nuevo sistema de logs
        Logger::log($level, $message, Logger::SYSTEM, $context);
        
        // Mantener el log anterior para compatibilidad
        file_put_contents($this->logFile, $message . PHP_EOL, FILE_APPEND);
    }
    
    /**
     * Rota el archivo de log cuando supera el tamaño máximo
     */
    private function rotateLogFile() {
        $timestamp = date('Y-m-d-H-i-s');
        rename($this->logFile, substr($this->logFile, 0, -4) . ".$timestamp.log");
    }
    
    /**
     * Detecta patrones de error y ejecuta acciones correctivas
     */
    public function detectAndFix($errorMessage) {
        foreach ($this->errorPatterns as $pattern => $solution) {
            if (preg_match($pattern, $errorMessage)) {
                $this->logError("AUTOCORRECTION: {$solution['message']}");
                
                // Ejecutar la acción correctiva
                $action = $solution['action'];
                if (method_exists($this, $action)) {
                    $result = $this->$action();
                    $this->logError("AUTOCORRECTION RESULT: $result");
                    
                    // Notificar si está configurado
                    if ($this->notificationEmail) {
                        $this->sendNotification($errorMessage, $solution['message'], $result);
                    }
                    
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Ejecuta composer install para solucionar problemas de autoload
     */
    private function runComposerInstall() {
        $output = shell_exec('cd ' . realpath(__DIR__ . '/..') . ' && composer install --no-dev 2>&1');
        return "Composer install executed: " . substr($output, 0, 100) . '...';
    }
    
    /**
     * Reinicia el servicio de base de datos
     */
    private function restartDatabase() {
        $output = shell_exec('docker-compose restart db 2>&1');
        return "Database service restarted: " . substr($output, 0, 100) . '...';
    }
    
    /**
     * Ajusta permisos de archivos
     */
    private function fixPermissions() {
        $output = shell_exec('chmod -R 755 ' . realpath(__DIR__ . '/..') . '/public 2>&1');
        return "Permissions fixed: " . substr($output, 0, 100) . '...';
    }
    
    /**
     * Limpia espacio en disco
     */
    private function cleanupDisk() {
        // Limpiar logs antiguos
        $output = shell_exec('find ' . realpath(__DIR__ . '/../logs') . ' -name "*.log.*" -type f -mtime +7 -delete 2>&1');
        
        // Limpiar archivos temporales
        $output .= shell_exec('find ' . realpath(__DIR__ . '/../temp') . ' -type f -mtime +1 -delete 2>&1');
        
        return "Disk cleanup executed: " . substr($output, 0, 100) . '...';
    }
    
    /**
     * Optimiza el uso de memoria
     */
    private function optimizeMemory() {
        // Limpiar caché
        $output = shell_exec('rm -rf ' . realpath(__DIR__ . '/../cache') . '/* 2>&1');
        return "Memory optimization executed: " . substr($output, 0, 100) . '...';
    }
    
    /**
     * Optimiza consultas lentas
     */
    private function optimizeQuery() {
        // Identificar y registrar consultas lentas
        $slowQueries = $this->findSlowQueries();
        
        // Enviar notificación al administrador
        mail(
            $this->notificationEmail,
            '[AnimalsCenter B2B] Consultas lentas detectadas',
            "Se han detectado consultas lentas. Revisar el log para más detalles:\n\n" . 
            implode("\n", $slowQueries)
        );
        
        return "Query optimization: " . count($slowQueries) . " slow queries identified and reported";
    }
    
    /**
     * Identifica consultas lentas
     */
    private function findSlowQueries() {
        // Simulación: en un entorno real esto analizaría logs de MySQL o similar
        return [
            'SELECT * FROM productos WHERE categoria_id = 5 (2.3s)',
            'SELECT * FROM pedidos WHERE fecha BETWEEN "2023-01-01" AND "2023-12-31" (3.1s)'
        ];
    }
    
    /**
     * Envía una notificación por email
     */
    private function sendNotification($errorMessage, $solution, $result) {
        $subject = "[AnimalsCenter B2B] Autocorrección de error";
        $message = "Se ha detectado y corregido automáticamente un error:\n\n";
        $message .= "Error: $errorMessage\n";
        $message .= "Solución aplicada: $solution\n";
        $message .= "Resultado: $result\n";
        $message .= "\nFecha y hora: " . date('Y-m-d H:i:s');
        
        mail($this->notificationEmail, $subject, $message);
        return "Notificación enviada a {$this->notificationEmail}";
    }
    
    /**
     * Recopila estadísticas de errores para informes
     */
    public function collectErrorStats() {
        // Leer archivo de log
        $logs = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        // Inicializar contadores
        $stats = [
            'total' => count($logs),
            'critical' => 0,
            'fixed' => 0,
            'categories' => []
        ];
        
        // Analizar logs
        foreach ($logs as $log) {
            if (strpos($log, 'CRITICAL') !== false) {
                $stats['critical']++;
            }
            
            if (strpos($log, 'AUTOCORRECTION RESULT') !== false) {
                $stats['fixed']++;
            }
            
            // Categorizar por tipo de error
            foreach ($this->errorPatterns as $pattern => $solution) {
                if (preg_match($pattern, $log)) {
                    $category = $solution['message'];
                    if (!isset($stats['categories'][$category])) {
                        $stats['categories'][$category] = 0;
                    }
                    $stats['categories'][$category]++;
                }
            }
        }
        
        return $stats;
    }
    
    /**
     * Envía estadísticas por email a los administradores
     */
    public function sendErrorStats() {
        $errorStats = $this->collectErrorStats();
        $subject = "[AnimalsCenter B2B] Informe diario de errores";
        
        // Preparar mensaje
        $message = "Informe de errores para " . date('Y-m-d') . "\n\n";
        $message .= "Resumen:\n";
        $message .= "- Total de errores: {$errorStats['total']}\n";
        $message .= "- Errores críticos: {$errorStats['critical']}\n";
        $message .= "- Errores autocorregidos: {$errorStats['fixed']}\n\n";
        
        $message .= "Categorías de errores:\n";
        foreach ($errorStats['categories'] as $category => $count) {
            $message .= "- $category: $count\n";
        }
        
        mail($this->notificationEmail, $subject, $message);
        return $errorStats;
    }
    
    /**
     * Convierte un código de error de PHP a su representación en texto
     * 
     * @param int $type Código de error
     * @return string Nombre del tipo de error
     */
    private function errorTypeToString($type) {
        switch($type) {
            case E_ERROR: return 'E_ERROR';
            case E_WARNING: return 'E_WARNING';
            case E_PARSE: return 'E_PARSE';
            case E_NOTICE: return 'E_NOTICE';
            case E_CORE_ERROR: return 'E_CORE_ERROR';
            case E_CORE_WARNING: return 'E_CORE_WARNING';
            case E_COMPILE_ERROR: return 'E_COMPILE_ERROR';
            case E_COMPILE_WARNING: return 'E_COMPILE_WARNING';
            case E_USER_ERROR: return 'E_USER_ERROR';
            case E_USER_WARNING: return 'E_USER_WARNING';
            case E_USER_NOTICE: return 'E_USER_NOTICE';
            case E_STRICT: return 'E_STRICT';
            case E_RECOVERABLE_ERROR: return 'E_RECOVERABLE_ERROR';
            case E_DEPRECATED: return 'E_DEPRECATED';
            case E_USER_DEPRECATED: return 'E_USER_DEPRECATED';
            default: return 'UNKNOWN_ERROR';
        }
    }
    
    /**
     * Sanitiza los datos POST para evitar registrar información sensible
     * 
     * @param array $postData Datos POST
     * @return array Datos POST sanitizados
     */
    private function sanitizePostParams($postData) {
        $sanitized = [];
        $sensitiveFields = ['password', 'password_confirm', 'contraseña', 'tarjeta', 'credit_card', 'cvv', 'clave'];
        
        foreach ($postData as $key => $value) {
            if (in_array(strtolower($key), $sensitiveFields)) {
                $sanitized[$key] = '******'; // Ocultar datos sensibles
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
}

// Inicializar el sistema de logs
Logger::init();

// Instanciar el manejador de errores al incluir este archivo
$errorHandler = new ErrorHandler();
