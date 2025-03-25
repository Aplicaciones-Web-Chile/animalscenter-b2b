<?php
/**
 * Script simple para probar la funcionalidad de CSRF
 */

// Cargar mínimas dependencias necesarias
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/controllers/AuthController.php';

// No cargar config/app.php para evitar problemas con Dotenv

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Limpiar sesión para comenzar fresco
$_SESSION = [];

echo "=== TEST DE GENERACIÓN Y VALIDACIÓN DE TOKEN CSRF ===\n\n";

// Inicializar el controlador de autenticación
$authController = new AuthController();

// 1. Generar token
echo "1. Generando token CSRF...\n";
$token = $authController->generateCSRFToken();
echo "   Token generado: $token\n";
echo "   Token almacenado en sesión: " . $_SESSION['csrf_token'] . "\n\n";

// 2. Validar token correcto
echo "2. Validando token correcto...\n";
$validResult = $authController->validateCSRFToken($token);
echo "   Resultado esperado: TRUE\n";
echo "   Resultado obtenido: " . ($validResult ? "TRUE" : "FALSE") . "\n\n";

// 3. Validar token incorrecto
echo "3. Validando token incorrecto...\n";
$invalidResult = $authController->validateCSRFToken('token_incorrecto');
echo "   Resultado esperado: FALSE\n";
echo "   Resultado obtenido: " . ($invalidResult ? "TRUE" : "FALSE") . "\n\n";

// 4. Simular persistencia entre peticiones
echo "4. Simulando persistencia entre peticiones...\n";
$sessionData = $_SESSION;
$sessionId = session_id();
echo "   Session ID: $sessionId\n";
echo "   Token antes de cerrar sesión: " . $sessionData['csrf_token'] . "\n";

// Cerrar y reabrir sesión
session_write_close();
session_id($sessionId);
session_start();

echo "   Token después de reabrir sesión: " . ($_SESSION['csrf_token'] ?? 'NO EXISTE') . "\n\n";

// 5. Comparar tokens
echo "5. Verificando si los tokens coinciden...\n";
$tokensMatch = isset($_SESSION['csrf_token']) && $sessionData['csrf_token'] === $_SESSION['csrf_token'];
echo "   ¿Coinciden? " . ($tokensMatch ? "SÍ" : "NO") . "\n\n";

// 6. Probar validación del token original
echo "6. Validando token original después de reabrir sesión...\n";
$stillValidResult = $authController->validateCSRFToken($token);
echo "   Resultado esperado: TRUE\n";
echo "   Resultado obtenido: " . ($stillValidResult ? "TRUE" : "FALSE") . "\n\n";

echo "=== FIN DE LA PRUEBA ===\n";
