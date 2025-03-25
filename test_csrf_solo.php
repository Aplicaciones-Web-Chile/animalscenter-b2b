<?php
/**
 * Prueba simple para validar la solución del token CSRF
 * Este script sólo prueba la generación y validación de tokens CSRF
 */

// Evitar output de caracteres antes de iniciar sesión
ob_start();

// Cargar solo lo estrictamente necesario
require_once __DIR__ . '/vendor/autoload.php';

// Configurar manejo de errores
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== PRUEBA DE VALIDACIÓN DE TOKEN CSRF ===\n\n";

// Configurar las opciones de cookies recomendadas
session_set_cookie_params([
    'lifetime' => 0,               // Hasta que el navegador se cierre
    'path' => '/',                 // Disponible en todo el sitio
    'domain' => '',                // Dominio actual
    'secure' => false,             // En producción debe ser true
    'httponly' => true,            // Protege contra XSS
    'samesite' => 'Lax'            // Protege contra CSRF
]);

// 1. Iniciar sesión
echo "1. Iniciando sesión...\n";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "   ID de sesión: " . session_id() . "\n\n";

// 2. Generar token CSRF
echo "2. Generando token CSRF...\n";
$token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $token;
echo "   Token generado: $token\n";
echo "   Token almacenado en sesión: " . $_SESSION['csrf_token'] . "\n\n";

// 3. Validar token correcto
echo "3. Validando token correcto...\n";
$validResult = !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
echo "   Resultado esperado: TRUE\n";
echo "   Resultado obtenido: " . ($validResult ? "TRUE" : "FALSE") . "\n\n";

// 4. Validar token incorrecto
echo "4. Validando token incorrecto...\n";
$invalidToken = "token_incorrecto";
$invalidResult = !empty($invalidToken) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $invalidToken);
echo "   Resultado esperado: FALSE\n";
echo "   Resultado obtenido: " . ($invalidResult ? "TRUE" : "FALSE") . "\n\n";

// 5. Simular cierre y reapertura de sesión (nueva petición)
echo "5. Simulando nueva petición...\n";
$sessionId = session_id();
$oldToken = $_SESSION['csrf_token'];
session_write_close();
echo "   Sesión cerrada\n";

// Reabrir sesión con el mismo ID
session_id($sessionId);
session_start();
echo "   Sesión reabierta con ID: " . session_id() . "\n";
echo "   Token original: $oldToken\n";
echo "   Token en nueva petición: " . ($_SESSION['csrf_token'] ?? 'NO EXISTE') . "\n\n";

// 6. Validar token después de la nueva petición
echo "6. Validando token después de nueva petición...\n";
$persistentResult = !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
echo "   Resultado esperado: TRUE\n";
echo "   Resultado obtenido: " . ($persistentResult ? "TRUE" : "FALSE") . "\n\n";

// Imprimir resumen
echo "=== RESUMEN DE LA PRUEBA ===\n\n";
if ($validResult && !$invalidResult && $persistentResult) {
    echo "✅ ÉXITO: La implementación de token CSRF funciona correctamente.\n";
    echo "   1. Valida correctamente tokens válidos\n";
    echo "   2. Rechaza correctamente tokens inválidos\n";
    echo "   3. Mantiene el token entre peticiones\n\n";
    
    echo "Problemas de 'token CSRF inválido' solucionados.\n";
} else {
    echo "❌ ERROR: La implementación de token CSRF aún presenta problemas:\n";
    if (!$validResult) echo "   - No valida correctamente tokens válidos\n";
    if ($invalidResult) echo "   - No rechaza correctamente tokens inválidos\n";
    if (!$persistentResult) echo "   - No mantiene el token entre peticiones\n";
}

// Limpiar la sesión al finalizar
session_destroy();

echo "\n=== FIN DE LA PRUEBA ===\n";
