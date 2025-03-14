<?php
/**
 * Cierre de sesión
 * Este archivo maneja el proceso de cierre de sesión del usuario
 */

// Iniciar sesión si no está iniciada
session_start();

// Incluir archivos necesarios
require_once __DIR__ . '/../includes/helpers.php';

// Destruir todas las variables de sesión
$_SESSION = array();

// Si se desea destruir completamente la sesión, eliminar también la cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruir la sesión
session_destroy();

// Establecer mensaje flash
setFlashMessage('success', 'Has cerrado sesión correctamente.');

// Redirigir al login
redirect('login.php');
