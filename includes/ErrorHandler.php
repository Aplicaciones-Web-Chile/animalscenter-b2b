<?php
/**
 * Sistema de manejo de errores y autocorrección
 * 
 * Este archivo implementa un sistema que detecta, registra y corrige automáticamente 
 * ciertos errores comunes en la aplicación B2B.
 */

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
     */
    public function handleError($errno, $errstr, $errfile, $errline) {
        $errorMessage = date('[Y-m-d H:i:s]') . " ERROR: $errstr in $errfile on line $errline";
        $this->logError($errorMessage);
        $this->detectAndFix($errorMessage);
        
        // No mostrar errores en producción
        if (getenv('APP_ENV') !== 'development') {
            return true;
        }
        
        return false; // Dejar que PHP maneje el error normalmente en desarrollo
    }
    
    /**
     * Manejador de excepciones no capturadas
     */
    public function handleException($exception) {
        $errorMessage = date('[Y-m-d H:i:s]') . " EXCEPTION: " . $exception->getMessage() . 
                        " in " . $exception->getFile() . " on line " . $exception->getLine() . 
                        "\nStack trace: " . $exception->getTraceAsString();
        
        $this->logError($errorMessage);
        $this->detectAndFix($errorMessage);
        
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
     */
    public function logError($message) {
        // Verificar tamaño del archivo de log y rotarlo si es necesario
        if (file_exists($this->logFile) && filesize($this->logFile) > $this->maxLogSize) {
            $this->rotateLogFile();
        }
        
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
        return "Database service restarted: " . $output;
    }
    
    /**
     * Corrige permisos de archivos
     */
    private function fixPermissions() {
        $output = shell_exec('cd ' . realpath(__DIR__ . '/..') . ' && chmod -R 755 . && find . -type f -exec chmod 644 {} \; 2>&1');
        return "Permissions fixed";
    }
    
    /**
     * Limpia espacio en disco
     */
    private function cleanupDisk() {
        // Limpiar logs antiguos
        $output1 = shell_exec('find ' . realpath(__DIR__ . '/../logs') . ' -type f -name "*.log.*" -mtime +7 -delete 2>&1');
        
        // Limpiar archivos temporales
        $output2 = shell_exec('rm -rf ' . realpath(__DIR__ . '/../tmp') . '/* 2>&1');
        
        return "Disk cleanup completed. Removed old logs and temporary files.";
    }
    
    /**
     * Optimiza el uso de memoria
     */
    private function optimizeMemory() {
        // Incrementar límite de memoria para PHP
        ini_set('memory_limit', '256M');
        
        return "Memory limit increased to 256M";
    }
    
    /**
     * Optimiza consultas para problemas de tiempo de ejecución
     */
    private function optimizeQuery() {
        // Incrementar tiempo máximo de ejecución
        ini_set('max_execution_time', 120);
        
        return "Execution time increased to 120 seconds";
    }
    
    /**
     * Envía una notificación por correo electrónico
     */
    private function sendNotification($errorMessage, $fixMessage, $result) {
        $subject = 'Sistema B2B - Autocorrección de error';
        
        $body = "Se ha detectado y corregido automáticamente un error en el sistema B2B de AnimalsCenter.\n\n";
        $body .= "Error original: \n" . $errorMessage . "\n\n";
        $body .= "Acción correctiva: \n" . $fixMessage . "\n\n";
        $body .= "Resultado: \n" . $result . "\n\n";
        $body .= "Fecha y hora: " . date('Y-m-d H:i:s') . "\n";
        
        // Intentar enviar correo sin bloquear la ejecución
        $headers = 'From: sistema@animalscenter.cl' . "\r\n" .
                   'Reply-To: noreply@animalscenter.cl' . "\r\n" .
                   'X-Mailer: PHP/' . phpversion();
        
        @mail($this->notificationEmail, $subject, $body, $headers);
    }
    
    /**
     * Analiza los registros de error para generar informes
     */
    public function analyzeErrorLogs() {
        if (!file_exists($this->logFile)) {
            return "No log file found";
        }
        
        $logs = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $errorStats = [];
        
        foreach ($logs as $log) {
            foreach ($this->errorPatterns as $pattern => $solution) {
                if (preg_match($pattern, $log)) {
                    $action = $solution['action'];
                    if (!isset($errorStats[$action])) {
                        $errorStats[$action] = 0;
                    }
                    $errorStats[$action]++;
                }
            }
        }
        
        return $errorStats;
    }
}

// Instanciar el manejador de errores al incluir este archivo
$errorHandler = new ErrorHandler();
