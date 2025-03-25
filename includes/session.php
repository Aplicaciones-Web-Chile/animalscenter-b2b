<?php
/**
 * Gestión de sesiones y autenticación
 * Maneja inicio de sesión, cierre de sesión y verificación de permisos
 */

// Iniciar sesión si no está iniciada
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Asegurarse que no se han enviado headers
        if (headers_sent($file, $line)) {
            error_log("Headers ya enviados en $file:$line - No se puede iniciar sesión correctamente");
            return false;
        }
        
        // Configurar opciones de sesión seguras
        $cookieParams = [
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => false, // Cambiar a true en producción con HTTPS
            'httponly' => true,
            'samesite' => 'Lax'
        ];
        
        // Configurar parámetros de cookies
        session_set_cookie_params($cookieParams);
        
        // Iniciar sesión
        session_start();
        
        // Debug - Guardar ID de sesión e info CSRF en log
        error_log("SESSION START - ID: " . session_id() . 
                 " - CSRF: " . (isset($_SESSION['csrf_token']) ? 'Presente' : 'Ausente'));
        
        return true;
    }
    return false;
}

/**
 * Inicia sesión para un usuario
 * 
 * @param array $userData Datos del usuario autenticado
 * @return bool Resultado de la operación
 */
function login($userData) {
    startSession();
    
    // Almacenar información de usuario en la sesión
    $_SESSION['user_id'] = $userData['id'];
    $_SESSION['username'] = $userData['username'] ?? $userData['email'];
    $_SESSION['role'] = $userData['role'];
    $_SESSION['logged_in'] = true;
    $_SESSION['last_activity'] = time();
    
    // Registrar en log
    error_log("Usuario logueado: " . $_SESSION['username'] . " - Session ID: " . session_id());
    
    // Forzar escritura de la sesión
    session_write_close();
    
    return true;
}

/**
 * Verifica si el usuario está autenticado
 * 
 * @return bool Estado de autenticación
 */
function isLoggedIn() {
    startSession();
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Verifica si el usuario actual es administrador
 * 
 * @return bool True si el usuario es administrador
 */
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
}

/**
 * Verifica si el usuario actual es proveedor
 * 
 * @return bool True si el usuario es proveedor
 */
function isProveedor() {
    return isLoggedIn() && isset($_SESSION['rol']) && $_SESSION['rol'] === 'proveedor';
}

/**
 * Obtiene el rol del usuario actual
 * 
 * @return string|null Rol del usuario o null si no está autenticado
 */
function getUserRole() {
    return isLoggedIn() ? ($_SESSION['rol'] ?? null) : null;
}

/**
 * Obtiene el ID del usuario actual
 * 
 * @return int|null ID del usuario o null si no está autenticado
 */
function getUserId() {
    return isLoggedIn() ? ($_SESSION['user_id'] ?? null) : null;
}

/**
 * Cierra la sesión del usuario
 * 
 * @return bool Resultado de la operación
 */
function logout() {
    startSession();
    
    // Guardar usuario para logging
    $username = $_SESSION['username'] ?? 'unknown';
    
    // Destruir datos de sesión
    $_SESSION = [];
    
    // Destruir la cookie de sesión
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destruir la sesión
    session_destroy();
    
    // Registrar en log
    error_log("Usuario deslogueado: " . $username);
    
    return true;
}
