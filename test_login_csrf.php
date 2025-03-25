<?php
/**
 * Prueba de inicio de sesión con validación CSRF
 * Este script prueba el flujo completo de login con las correcciones realizadas
 */

// Establecer visualización de errores para depuración
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Cargar dependencias
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/controllers/AuthController.php';

echo "=== PRUEBA DE INICIO DE SESIÓN CON CSRF ===\n\n";

// 1. Inicializar sesión
echo "1. Inicializando sesión...\n";
startSession();
echo "   ID de sesión: " . session_id() . "\n\n";

// 2. Crear instancia del controlador de autenticación
echo "2. Creando controlador de autenticación...\n";
$authController = new AuthController();
echo "   Controlador inicializado correctamente\n\n";

// 3. Generar token CSRF
echo "3. Generando token CSRF...\n";
$token = $authController->generateCSRFToken();
echo "   Token generado: $token\n";
echo "   Token en sesión: " . $_SESSION['csrf_token'] . "\n\n";

// 4. Simular cierre y reapertura de sesión (nueva página)
echo "4. Simulando cambio de página (persistencia de sesión)...\n";
$sessionId = session_id();
session_write_close();
echo "   Sesión cerrada\n";

// Reabrir sesión con el mismo ID
session_id($sessionId);
startSession();
echo "   Sesión reabierta con ID: " . session_id() . "\n";
echo "   Token CSRF en nueva página: " . ($_SESSION['csrf_token'] ?? 'NO EXISTE') . "\n\n";

// 5. Validar el token CSRF
echo "5. Validando token CSRF...\n";
$validationResult = $authController->validateCSRFToken($token);
echo "   Resultado esperado: TRUE\n";
echo "   Resultado obtenido: " . ($validationResult ? "TRUE" : "FALSE") . "\n\n";

// 6. Intentar login con el token CSRF
echo "6. Intentando login con credenciales...\n";
$username = 'admin';
$password = 'admin123';

// Verificar primero que el token CSRF es válido
if (!$authController->validateCSRFToken($token)) {
    echo "   ERROR: Token CSRF inválido\n\n";
} else {
    try {
        // Nota: La función login solo acepta 3 parámetros, no 4
        $result = $authController->login($username, $password, false);
        echo "   Resultado de login: " . ($result['success'] ? "EXITOSO" : "FALLIDO") . "\n";
        echo "   Mensaje: " . $result['message'] . "\n";
        echo "   Redirección: " . ($result['redirect'] ?? 'No especificada') . "\n\n";
    } catch (Exception $e) {
        echo "   ERROR: " . $e->getMessage() . "\n\n";
    }
}

// 7. Verificar estado de la sesión después del login
echo "7. Verificando estado de sesión después del login...\n";
// Comprobamos si hay sesión iniciada verificando si existe la variable de sesión
echo "   Usuario en sesión: " . (isset($_SESSION['user']) ? "SÍ" : "NO") . "\n";
if (isset($_SESSION['user'])) {
    echo "   Usuario: " . $_SESSION['user']['nombre'] . "\n";
    echo "   Rol: " . $_SESSION['user']['rol'] . "\n";
}

echo "\n=== FIN DE LA PRUEBA ===\n";
echo "\nLa prueba ha finalizado. Si todas las validaciones son correctas, el problema de CSRF ha sido resuelto.\n";
echo "Puede probar el inicio de sesión en la aplicación web para confirmar.\n";
