<?php
/**
 * Prueba de la solución CSRF
 * Este script verifica que el token CSRF funciona correctamente ahora
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/session.php';

echo "=== VERIFICANDO SOLUCIÓN DE TOKEN CSRF ===\n\n";

echo "1. Iniciando sesión...\n";
startSession();
$initialSessionId = session_id();
echo "   ID de sesión inicial: $initialSessionId\n\n";

echo "2. Generando token CSRF manualmente...\n";
$token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $token;
echo "   Token generado: $token\n";
echo "   Token almacenado en sesión: " . $_SESSION['csrf_token'] . "\n\n";

echo "3. Cerrando escritura de sesión y cambiando de página...\n";
session_write_close();
echo "   Sesión cerrada para escritura\n\n";

echo "4. Iniciando nueva sesión (simulando nueva página)...\n";
startSession();
$newSessionId = session_id();
echo "   ID de nueva sesión: $newSessionId\n";
echo "   ¿IDs de sesión iguales? " . ($initialSessionId === $newSessionId ? "SÍ" : "NO") . "\n";
echo "   Token en nueva sesión: " . ($_SESSION['csrf_token'] ?? 'NO EXISTE') . "\n";
echo "   ¿Token conservado? " . (($_SESSION['csrf_token'] ?? '') === $token ? "SÍ" : "NO") . "\n\n";

if (isset($_SESSION['csrf_token']) && $_SESSION['csrf_token'] === $token) {
    echo "✅ ÉXITO: La solución funciona correctamente\n";
    echo "El token CSRF se mantiene entre peticiones\n";
} else {
    echo "❌ ERROR: Todavía hay problemas con el token CSRF\n";
    if (!isset($_SESSION['csrf_token'])) {
        echo "El token no existe en la nueva sesión\n";
    } else {
        echo "El token cambió entre peticiones\n";
        echo "Token original: $token\n";
        echo "Token actual: " . $_SESSION['csrf_token'] . "\n";
    }
}

echo "\nCOMPROBANDO CONFIGURACIÓN DE PHP:\n";
echo "session.save_path: " . ini_get('session.save_path') . "\n";
echo "session.use_cookies: " . ini_get('session.use_cookies') . "\n";
echo "session.use_only_cookies: " . ini_get('session.use_only_cookies') . "\n";
echo "session.use_strict_mode: " . ini_get('session.use_strict_mode') . "\n";
echo "session.cookie_lifetime: " . ini_get('session.cookie_lifetime') . "\n";
echo "session.cookie_path: " . ini_get('session.cookie_path') . "\n";
echo "session.cookie_domain: " . ini_get('session.cookie_domain') . "\n";
echo "session.cookie_secure: " . ini_get('session.cookie_secure') . "\n";
echo "session.cookie_httponly: " . ini_get('session.cookie_httponly') . "\n";
echo "session.cookie_samesite: " . ini_get('session.cookie_samesite') . "\n";

echo "\n=== FIN DE LA VERIFICACIÓN ===\n";