<?php
/**
 * Gestión de sesiones y autenticación
 * Maneja inicio de sesión, cierre de sesión y verificación de permisos
 */

// Iniciar sesión si no está iniciada
function startSession() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Inicia sesión para un usuario
 * 
 * @param array $user Datos del usuario (id, nombre, email, rol, rut)
 * @return bool True si se inició sesión correctamente
 */
function login($user) {
    startSession();
    
    // Generar un nuevo ID de sesión para prevenir ataques de fijación de sesión
    session_regenerate_id(true);
    
    // Almacenar información del usuario en la sesión
    $_SESSION['user'] = [
        'id' => $user['id'],
        'nombre' => $user['nombre'],
        'email' => $user['email'],
        'rol' => $user['rol'],
        'rut' => $user['rut'],
        'last_activity' => time()
    ];
    
    // Registrar el inicio de sesión
    registerLoginActivity($user['id']);
    
    return true;
}

/**
 * Cierra la sesión del usuario actual
 */
function logout() {
    startSession();
    
    // Registrar cierre de sesión si hay usuario
    if (isset($_SESSION['user']['id'])) {
        registerLogoutActivity($_SESSION['user']['id']);
    }
    
    // Destruir todas las variables de sesión
    $_SESSION = [];
    
    // Destruir la cookie de sesión si existe
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    // Destruir la sesión
    session_destroy();
}

/**
 * Verifica si el usuario está autenticado
 * 
 * @return bool True si el usuario está autenticado
 */
function isAuthenticated() {
    startSession();
    return isset($_SESSION['user']) && !sessionExpired();
}

/**
 * Verifica si la sesión ha expirado por inactividad
 * 
 * @return bool True si la sesión ha expirado
 */
function sessionExpired() {
    if (!isset($_SESSION['user']['last_activity'])) {
        return true;
    }
    
    $maxLifetime = SESSION_LIFETIME * 60; // Convertir minutos a segundos
    $elapsed = time() - $_SESSION['user']['last_activity'];
    
    if ($elapsed > $maxLifetime) {
        logout();
        return true;
    }
    
    // Actualizar tiempo de actividad
    $_SESSION['user']['last_activity'] = time();
    return false;
}

/**
 * Verifica si el usuario actual tiene rol de administrador
 * 
 * @return bool True si el usuario es administrador
 */
function isAdmin() {
    startSession();
    return isAuthenticated() && $_SESSION['user']['rol'] === 'admin';
}

/**
 * Verifica si el usuario actual tiene rol de proveedor
 * 
 * @return bool True si el usuario es proveedor
 */
function isProveedor() {
    startSession();
    return isAuthenticated() && $_SESSION['user']['rol'] === 'proveedor';
}

/**
 * Obtiene el RUT del usuario actual
 * 
 * @return string|null RUT del usuario o null si no está autenticado
 */
function getCurrentUserRut() {
    startSession();
    return isAuthenticated() ? $_SESSION['user']['rut'] : null;
}

/**
 * Obtiene información del usuario actual
 * 
 * @return array|null Datos del usuario o null si no está autenticado
 */
function getCurrentUser() {
    startSession();
    return isAuthenticated() ? $_SESSION['user'] : null;
}

/**
 * Registra actividad de inicio de sesión
 * 
 * @param int $userId ID del usuario
 */
function registerLoginActivity($userId) {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    // Aquí podríamos registrar en una tabla de logs si es necesario
    // Por ahora solo registramos en el log del sistema
    error_log("Login: User ID $userId from IP $ipAddress with $userAgent");
}

/**
 * Registra actividad de cierre de sesión
 * 
 * @param int $userId ID del usuario
 */
function registerLogoutActivity($userId) {
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    // Aquí podríamos registrar en una tabla de logs si es necesario
    // Por ahora solo registramos en el log del sistema
    error_log("Logout: User ID $userId from IP $ipAddress");
}

/**
 * Redirige a la página de login si el usuario no está autenticado
 */
function requireLogin() {
    if (!isAuthenticated()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

/**
 * Redirige al dashboard si el usuario ya está autenticado
 */
function redirectIfAuthenticated() {
    if (isAuthenticated()) {
        header('Location: ' . APP_URL . '/dashboard.php');
        exit;
    }
}

/**
 * Verifica que el usuario sea un administrador, redirige en caso contrario
 */
function requireAdmin() {
    requireLogin();
    
    if (!isAdmin()) {
        // El usuario está autenticado pero no es admin
        header('Location: ' . APP_URL . '/unauthorized.php');
        exit;
    }
}
