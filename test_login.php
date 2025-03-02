<?php
/**
 * Script de prueba para verificar la autenticación y redirección
 */

// Establecer reporte de errores máximo
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== Test de autenticación en el sistema B2B ===\n";

// Verificar que los archivos básicos existan
echo "\nVerificando archivos esenciales:\n";
$archivos = [
    __DIR__ . '/includes/session.php',
    __DIR__ . '/public/login.php',
    __DIR__ . '/public/dashboard.php'
];

foreach ($archivos as $archivo) {
    if (file_exists($archivo)) {
        echo "✓ El archivo $archivo existe\n";
    } else {
        echo "✗ El archivo $archivo NO existe\n";
    }
}

// Verificar funciones de sesión
echo "\nVerificando funciones de sesión:\n";
require_once __DIR__ . '/includes/session.php';

$funciones = [
    'startSession',
    'login',
    'logout',
    'isAuthenticated',
    'isAdmin',
    'isProveedor',
    'redirectIfAuthenticated',
    'requireLogin'
];

foreach ($funciones as $funcion) {
    if (function_exists($funcion)) {
        echo "✓ La función $funcion existe\n";
    } else {
        echo "✗ La función $funcion NO existe\n";
    }
}

// Probar inicio de sesión
echo "\nProbando inicio de sesión:\n";
session_start();

// Crear un usuario de prueba
$usuario_prueba = [
    'id' => 999,
    'nombre' => 'Usuario de Prueba',
    'email' => 'test@example.com',
    'rol' => 'admin',
    'rut' => '12345678-9'
];

// Intentar iniciar sesión
if (login($usuario_prueba)) {
    echo "✓ Inicio de sesión exitoso\n";
} else {
    echo "✗ Error en inicio de sesión\n";
}

// Verificar si la sesión está activa
if (isAuthenticated()) {
    echo "✓ Usuario autenticado correctamente\n";
} else {
    echo "✗ Error en verificación de autenticación\n";
}

// Verificar datos de sesión
echo "\nDatos de sesión:\n";
if (isset($_SESSION['user'])) {
    echo "✓ user: " . print_r($_SESSION['user'], true) . "\n";
} else {
    echo "✗ No hay datos de usuario en la sesión\n";
}

// Cerrar sesión
logout();
echo "\nCerrando sesión...\n";

// Verificar si la sesión se cerró
if (!isAuthenticated()) {
    echo "✓ Sesión cerrada correctamente\n";
} else {
    echo "✗ Error al cerrar sesión\n";
}

echo "\n=== Fin de las pruebas ===\n";
