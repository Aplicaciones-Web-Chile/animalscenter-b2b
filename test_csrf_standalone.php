<?php
/**
 * Test independiente para probar el token CSRF sin dependencias externas
 * Este script simula los métodos necesarios para generar y validar tokens CSRF
 */

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Limpiar sesión para comenzar fresco
$_SESSION = [];

echo "=== TEST INDEPENDIENTE DE GENERACIÓN Y VALIDACIÓN DE TOKEN CSRF ===\n\n";

/**
 * Genera y almacena token CSRF
 * 
 * @return string Token generado
 */
function generateCSRFToken() {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

/**
 * Verifica si un token CSRF es válido
 * 
 * @param string $token Token a verificar
 * @return bool Resultado de la validación
 */
function validateCSRFToken($token) {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Generar token CSRF
echo "1. Generando token CSRF...\n";
$token = generateCSRFToken();
echo "   Token generado: $token\n";
echo "   Token almacenado en sesión: " . $_SESSION['csrf_token'] . "\n\n";

// Validar token correcto
echo "2. Validando token correcto...\n";
$validResult = validateCSRFToken($token);
echo "   Resultado esperado: TRUE\n";
echo "   Resultado obtenido: " . ($validResult ? "TRUE" : "FALSE") . "\n\n";

// Validar token incorrecto
echo "3. Validando token incorrecto...\n";
$invalidResult = validateCSRFToken('token_incorrecto');
echo "   Resultado esperado: FALSE\n";
echo "   Resultado obtenido: " . ($invalidResult ? "TRUE" : "FALSE") . "\n\n";

// Simular persistencia entre peticiones
echo "4. Simulando persistencia entre peticiones...\n";
$sessionData = $_SESSION;
$sessionId = session_id();
echo "   Session ID: $sessionId\n";
echo "   Token antes de cerrar sesión: " . $sessionData['csrf_token'] . "\n";

// Cerrar y reabrir sesión para simular nueva petición
session_write_close();
session_id($sessionId);
session_start();

echo "   Token después de reabrir sesión: " . ($_SESSION['csrf_token'] ?? 'NO EXISTE') . "\n\n";

// Verificar si los tokens coinciden
echo "5. Verificando si los tokens coinciden...\n";
$tokensMatch = isset($_SESSION['csrf_token']) && $sessionData['csrf_token'] === $_SESSION['csrf_token'];
echo "   ¿Coinciden? " . ($tokensMatch ? "SÍ" : "NO") . "\n\n";

// Validar el token original después de reabrir
echo "6. Validando token original después de reabrir sesión...\n";
$stillValidResult = validateCSRFToken($token);
echo "   Resultado esperado: TRUE\n";
echo "   Resultado obtenido: " . ($stillValidResult ? "TRUE" : "FALSE") . "\n\n";

echo "=== FIN DE LA PRUEBA ===\n";
echo "\n=== ANALIZANDO EL PROBLEMA ===\n";

// Analizar el problema
$sessionValid = session_status() === PHP_SESSION_ACTIVE;
$cookieSet = isset($_COOKIE[session_name()]);
$cookiePath = session_get_cookie_params()['path'];
$cookieSecure = session_get_cookie_params()['secure'];
$cookieSameSite = session_get_cookie_params()['samesite'] ?? 'No definido';

echo "Estado de la sesión: " . ($sessionValid ? "ACTIVA" : "INACTIVA") . "\n";
echo "Cookie de sesión establecida: " . ($cookieSet ? "SÍ" : "NO") . "\n";
echo "Path de la cookie: $cookiePath\n";
echo "Cookie segura: " . ($cookieSecure ? "SÍ" : "NO") . "\n";
echo "Cookie SameSite: $cookieSameSite\n\n";

echo "Posibles causas del problema CSRF:\n";
echo "1. La cookie de sesión no se está manteniendo entre redirecciones\n";
echo "2. El token CSRF se está regenerando en cada página\n";
echo "3. Las configuraciones de sesión son diferentes entre páginas\n";
echo "4. Hay múltiples llamadas a session_start() que reinician la sesión\n\n";

echo "Recomendaciones:\n";
echo "1. Verificar que session_start() se llama una sola vez por request\n";
echo "2. Confirmar que no se está usando session_regenerate_id() indebidamente\n";
echo "3. Revisar las configuraciones de cookie (path, domain, secure, samesite)\n";
echo "4. Asegurar que el token CSRF generado se está pasando correctamente entre páginas\n";
