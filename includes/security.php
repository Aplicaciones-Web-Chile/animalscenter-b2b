<?php
/**
 * Funciones de seguridad para el sistema B2B de AnimalsCenter
 * 
 * Este archivo contiene todas las funciones relacionadas con la seguridad del sistema,
 * incluyendo validaciones contra SQL Injection, XSS, CSRF, y restricciones de acceso por IP.
 */

/**
 * Valida una dirección IP contra una lista de IPs permitidas
 * 
 * @param string $ip La IP a validar (si se omite, se usa la IP del cliente actual)
 * @param array $allowedIps Lista de IPs permitidas (puede incluir rangos CIDR)
 * @return bool True si la IP está permitida, false si no
 */
function isIpAllowed($ip = null, $allowedIps = []) {
    // Si no se especifica una IP, usar la del cliente actual
    if ($ip === null) {
        $ip = getClientIp();
    }
    
    // Si la lista de IPs permitidas está vacía, consultar configuración
    if (empty($allowedIps)) {
        // Obtener lista de IPs permitidas desde configuración
        require_once __DIR__ . '/../config/app.php';
        if (defined('ALLOWED_IPS') && !empty(ALLOWED_IPS)) {
            $allowedIps = ALLOWED_IPS;
        } else {
            // Si no hay restricción de IPs configurada, permitir todas
            return true;
        }
    }
    
    // Verificar si la IP está en la lista de permitidas
    foreach ($allowedIps as $allowedIp) {
        // Comprobar si es un rango CIDR
        if (strpos($allowedIp, '/') !== false) {
            if (isIpInCidrRange($ip, $allowedIp)) {
                return true;
            }
        } 
        // Comprobar si es una IP exacta
        else if ($ip === $allowedIp) {
            return true;
        }
    }
    
    return false;
}

/**
 * Verifica si una IP está dentro de un rango CIDR
 * 
 * @param string $ip La dirección IP a comprobar
 * @param string $cidr El rango CIDR (por ejemplo, 192.168.1.0/24)
 * @return bool True si la IP está en el rango, false si no
 */
function isIpInCidrRange($ip, $cidr) {
    list($subnet, $mask) = explode('/', $cidr);
    
    $ipLong = ip2long($ip);
    $subnetLong = ip2long($subnet);
    $maskLong = ~((1 << (32 - $mask)) - 1);
    
    return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
}

/**
 * Obtiene la dirección IP real del cliente, considerando proxies
 * 
 * @return string La dirección IP del cliente
 */
function getClientIp() {
    $ipKeys = [
        'HTTP_CF_CONNECTING_IP', // Cloudflare
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];
    
    foreach ($ipKeys as $key) {
        if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
            return $_SERVER[$key];
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Restringe el acceso a una página basado en la IP del cliente
 * 
 * @param array $allowedIps Lista de IPs permitidas
 */
function restrictByIp($allowedIps = []) {
    if (!isIpAllowed(null, $allowedIps)) {
        // Registrar el intento de acceso
        error_log("Intento de acceso bloqueado desde IP: " . getClientIp());
        
        // Enviar código de error 403 y mensaje
        http_response_code(403);
        die("Acceso denegado. Su dirección IP no está autorizada.");
    }
}

/**
 * Valida y limpia un parámetro para prevenir inyección SQL
 * 
 * @param string $param Parámetro a validar
 * @return string Parámetro limpio
 */
function sanitizeSqlParam($param) {
    global $conn;
    
    if (!isset($conn)) {
        require_once __DIR__ . '/../config/database.php';
    }
    
    // Escapar caracteres especiales en una cadena para usar en sentencias SQL
    if ($param !== null && is_string($param)) {
        return mysqli_real_escape_string($conn, $param);
    }
    
    return $param;
}

/**
 * Valida que una solicitud haya sido enviada con el método HTTP especificado
 * 
 * @param string|array $methods Método o métodos HTTP permitidos ('GET', 'POST', etc.)
 * @return bool True si el método es válido, false si no
 */
function validateRequestMethod($methods) {
    if (is_string($methods)) {
        $methods = [$methods];
    }
    
    return in_array($_SERVER['REQUEST_METHOD'], $methods);
}

/**
 * Verifica si una solicitud tiene un token CSRF válido (para formularios)
 * 
 * @return bool True si el token CSRF es válido, false si no
 */
function checkCsrfToken() {
    // Verificar si se ha enviado un token CSRF
    if (!isset($_POST['csrf_token']) || empty($_POST['csrf_token'])) {
        return false;
    }
    
    return validateCsrfToken($_POST['csrf_token']);
}

/**
 * Obliga a usar HTTPS en la solicitud actual
 * 
 * @param bool $permanent Si es true, realiza una redirección 301 (permanente)
 */
function forceHttps($permanent = false) {
    if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
        $location = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header('HTTP/1.1 ' . ($permanent ? '301' : '302') . ' Moved ' . ($permanent ? 'Permanently' : 'Temporarily'));
        header('Location: ' . $location);
        exit;
    }
}

/**
 * Establece encabezados de seguridad HTTP
 */
function setSecurityHeaders() {
    // Evitar que el navegador realice MIME sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Habilitar la protección XSS en navegadores antiguos
    header('X-XSS-Protection: 1; mode=block');
    
    // Evitar que la página se muestre en frames (para prevenir clickjacking)
    header('X-Frame-Options: DENY');
    
    // Política de seguridad de contenido (CSP)
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");
    
    // Política de referencia
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

/**
 * Verifica los permisos de un usuario para acceder a una sección
 * 
 * @param array $allowedRoles Roles permitidos para acceder
 * @return bool True si el usuario tiene permisos, false si no
 */
function hasAccess($allowedRoles = ['admin']) {
    session_start();
    
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['rol'])) {
        return false;
    }
    
    return in_array($_SESSION['user']['rol'], $allowedRoles);
}

/**
 * Restringe el acceso a una página basada en el rol del usuario
 * 
 * @param array $allowedRoles Roles permitidos para acceder
 * @param string $redirectUrl URL a la que redirigir si no tiene acceso
 */
function restrictAccessByRole($allowedRoles = ['admin'], $redirectUrl = '/login.php') {
    if (!hasAccess($allowedRoles)) {
        setFlashMessage('error', 'No tiene permisos para acceder a esta sección.');
        redirect($redirectUrl);
    }
}

/**
 * Registra un intento de acceso no autorizado
 * 
 * @param string $reason Razón del bloqueo
 * @param string $username Usuario que intentó acceder (opcional)
 */
function logUnauthorizedAccess($reason, $username = '') {
    $ip = getClientIp();
    $date = date('Y-m-d H:i:s');
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';
    $logMessage = sprintf(
        "[%s] Acceso no autorizado: %s - IP: %s - Usuario: %s - User-Agent: %s",
        $date,
        $reason,
        $ip,
        $username,
        $userAgent
    );
    
    // Guardar en el archivo de log
    error_log($logMessage, 3, __DIR__ . '/../logs/security.log');
}

/**
 * Función para detectar y prevenir ataques de fuerza bruta
 * 
 * @param string $username Nombre de usuario que intenta acceder
 * @param string $ip IP desde la que se intenta acceder (opcional)
 * @return bool True si se debe permitir el intento, false si se debe bloquear
 */
function preventBruteForce($username, $ip = null) {
    session_start();
    
    if ($ip === null) {
        $ip = getClientIp();
    }
    
    // Inicializar contador de intentos si no existe
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_time'] = time();
    }
    
    // Verificar si se superó el número máximo de intentos
    $maxAttempts = 5;
    $lockoutTime = 15 * 60; // 15 minutos en segundos
    
    // Reiniciar contador si ha pasado el tiempo de bloqueo
    if (time() - $_SESSION['login_time'] > $lockoutTime) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_time'] = time();
    }
    
    // Incrementar contador de intentos
    $_SESSION['login_attempts']++;
    
    // Verificar si se debe bloquear
    if ($_SESSION['login_attempts'] > $maxAttempts) {
        // Registrar el intento de fuerza bruta
        logUnauthorizedAccess("Posible ataque de fuerza bruta - Intentos: {$_SESSION['login_attempts']}", $username);
        return false;
    }
    
    return true;
}
