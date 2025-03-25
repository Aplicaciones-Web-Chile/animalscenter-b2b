<?php
/**
 * Sistema de registro de eventos (Logger)
 * 
 * Esta clase proporciona métodos para registrar diferentes tipos de eventos
 * del sistema como inicios de sesión, errores, acciones de usuarios, etc.
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 1.0
 */

class Logger {
    // Niveles de log
    const DEBUG = 'DEBUG';
    const INFO = 'INFO';
    const WARNING = 'WARNING';
    const ERROR = 'ERROR';
    const CRITICAL = 'CRITICAL';
    
    // Tipos de eventos
    const AUTH = 'AUTH';
    const TRANSACTION = 'TRANSACTION';
    const ADMIN = 'ADMIN';
    const SYSTEM = 'SYSTEM';
    const SECURITY = 'SECURITY';
    
    /**
     * Directorio donde se almacenarán los archivos de log
     */
    private static $logDir;
    
    /**
     * Inicializa el sistema de logs
     * 
     * @param string $logDir Directorio donde se almacenarán los logs
     * @return void
     */
    public static function init($logDir = null) {
        // Si no se especifica un directorio, usar el predeterminado
        if (is_null($logDir)) {
            $logDir = __DIR__ . '/../logs';
        }
        
        // Asegurarse de que el directorio exista
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        self::$logDir = rtrim($logDir, '/');
        
        // Registro que el sistema de logs está inicializado
        self::debug('Sistema de logs inicializado correctamente', self::SYSTEM);
    }
    
    /**
     * Registra un evento de debug (solo visible en desarrollo)
     * 
     * @param string $message Mensaje a registrar
     * @param string $eventType Tipo de evento
     * @param array $data Datos adicionales para el log
     * @return void
     */
    public static function debug($message, $eventType = self::SYSTEM, $data = []) {
        self::log(self::DEBUG, $message, $eventType, $data);
    }
    
    /**
     * Registra un evento informativo
     * 
     * @param string $message Mensaje a registrar
     * @param string $eventType Tipo de evento
     * @param array $data Datos adicionales para el log
     * @return void
     */
    public static function info($message, $eventType = self::SYSTEM, $data = []) {
        self::log(self::INFO, $message, $eventType, $data);
    }
    
    /**
     * Registra una advertencia
     * 
     * @param string $message Mensaje a registrar
     * @param string $eventType Tipo de evento
     * @param array $data Datos adicionales para el log
     * @return void
     */
    public static function warning($message, $eventType = self::SYSTEM, $data = []) {
        self::log(self::WARNING, $message, $eventType, $data);
    }
    
    /**
     * Registra un error
     * 
     * @param string $message Mensaje a registrar
     * @param string $eventType Tipo de evento
     * @param array $data Datos adicionales para el log
     * @return void
     */
    public static function error($message, $eventType = self::SYSTEM, $data = []) {
        self::log(self::ERROR, $message, $eventType, $data);
    }
    
    /**
     * Registra un error crítico
     * 
     * @param string $message Mensaje a registrar
     * @param string $eventType Tipo de evento
     * @param array $data Datos adicionales para el log
     * @return void
     */
    public static function critical($message, $eventType = self::SYSTEM, $data = []) {
        self::log(self::CRITICAL, $message, $eventType, $data);
    }
    
    /**
     * Registra un evento de autenticación
     * 
     * @param string $message Mensaje a registrar
     * @param string $level Nivel de log
     * @param array $data Datos adicionales para el log
     * @return void
     */
    public static function auth($message, $level = self::INFO, $data = []) {
        self::log($level, $message, self::AUTH, $data);
    }
    
    /**
     * Registra un evento de transacción
     * 
     * @param string $message Mensaje a registrar
     * @param string $level Nivel de log
     * @param array $data Datos adicionales para el log
     * @return void
     */
    public static function transaction($message, $level = self::INFO, $data = []) {
        self::log($level, $message, self::TRANSACTION, $data);
    }
    
    /**
     * Método principal para registrar eventos
     * 
     * @param string $level Nivel de log
     * @param string $message Mensaje a registrar
     * @param string $eventType Tipo de evento
     * @param array $data Datos adicionales para el log
     * @return void
     */
    public static function log($level, $message, $eventType, $data = []) {
        // Verificar que el sistema de logs esté inicializado
        if (empty(self::$logDir)) {
            self::init();
        }
        
        // Obtener información del entorno
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI';
        $userId = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : 'Sistema';
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'CLI';
        
        // Preparar entrada de log
        $logEntry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'event_type' => $eventType,
            'message' => $message,
            'user_id' => $userId,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'request_uri' => $requestUri,
            'data' => $data
        ];
        
        // Convertir a formato JSON
        $logJson = json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // Determinar archivo de log según el tipo de evento
        $logFile = self::$logDir . '/' . strtolower($eventType) . '_' . date('Y-m-d') . '.log';
        
        // Escribir en el archivo de log
        file_put_contents($logFile, $logJson . PHP_EOL, FILE_APPEND);
        
        // Si es un error o crítico, registrar también en el log del sistema
        if ($level == self::ERROR || $level == self::CRITICAL) {
            error_log("[$level] $message");
        }
    }
    
    /**
     * Registra un error de PHP
     * 
     * @param \Throwable $exception Excepción o error a registrar
     * @param array $context Contexto adicional
     * @return void
     */
    public static function logException(\Throwable $exception, $context = []) {
        $message = $exception->getMessage();
        $code = $exception->getCode();
        $file = $exception->getFile();
        $line = $exception->getLine();
        $trace = $exception->getTraceAsString();
        
        $logMessage = "Exception: [$code] $message in $file:$line\nTrace: $trace";
        self::error($logMessage, self::SYSTEM, $context);
    }
}
