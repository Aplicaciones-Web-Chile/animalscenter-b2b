<?php
/**
 * Página principal del sistema B2B
 * Redirige a login o dashboard según estado de autenticación
 */

// Incluir manejador de errores (debe ser lo primero)
require_once __DIR__ . '/../includes/ErrorHandler.php';

// Cargar dependencias
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/session.php';

// Verificar estado de autenticación
if (isAuthenticated()) {
    // Usuario autenticado, redirigir al dashboard
    header('Location: dashboard.php');
    exit;
} else {
    // Usuario no autenticado, redirigir a login
    header('Location: login.php');
    exit;
}
