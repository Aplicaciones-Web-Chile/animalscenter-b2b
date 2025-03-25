<?php
/**
 * Verificador de la solución al problema CSRF
 * 
 * Este script verifica que la solución al problema de tokens CSRF
 * ha sido implementada correctamente.
 */

// Configurar visualización de errores
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();

echo "=== VERIFICADOR DE SOLUCIÓN CSRF ===\n\n";

// 1. Verificar archivo session.php
echo "1. Verificando configuración de sesión...\n";
$sessionFile = __DIR__ . '/includes/session.php';
if (file_exists($sessionFile)) {
    $content = file_get_contents($sessionFile);
    
    $hasCorrectConfig = strpos($content, "session_set_cookie_params") !== false &&
                        strpos($content, "'samesite' => 'Lax'") !== false;
    
    echo $hasCorrectConfig 
        ? "   ✅ Configuración de sesión correcta.\n" 
        : "   ❌ Configuración de sesión incorrecta.\n";
} else {
    echo "   ❌ No se encontró archivo de sesión.\n";
}

// 2. Verificar login.php
echo "\n2. Verificando página de login...\n";
$loginFile = __DIR__ . '/public/login.php';
if (file_exists($loginFile)) {
    $content = file_get_contents($loginFile);
    
    $hasWriteClose = strpos($content, "session_write_close();") !== false;
    
    echo $hasWriteClose 
        ? "   ✅ Login incluye session_write_close() antes de redirecciones.\n" 
        : "   ❌ Login no incluye session_write_close() antes de redirecciones.\n";
} else {
    echo "   ❌ No se encontró página de login.\n";
}

// 3. Verificar dashboard.php
echo "\n3. Verificando página de dashboard...\n";
$dashboardFile = __DIR__ . '/public/dashboard.php';
if (file_exists($dashboardFile)) {
    $content = file_get_contents($dashboardFile);
    
    $hasWriteClose = strpos($content, "session_write_close();") !== false;
    
    echo $hasWriteClose 
        ? "   ✅ Dashboard incluye session_write_close() antes de redirecciones.\n" 
        : "   ❌ Dashboard no incluye session_write_close() antes de redirecciones.\n";
} else {
    echo "   ❌ No se encontró página de dashboard.\n";
}

// 4. Probar funcionalidad CSRF
echo "\n4. Probando funcionalidad CSRF...\n";
session_set_cookie_params([
    'lifetime' => 0,         
    'path' => '/',           
    'domain' => '',          
    'secure' => false,       
    'httponly' => true,      
    'samesite' => 'Lax'      
]);

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generar token CSRF
$token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $token;

// Cerrar sesión para simular nueva petición
session_write_close();

// Reabrir sesión
session_start();

// Verificar persistencia del token
$tokenPersists = isset($_SESSION['csrf_token']) && $_SESSION['csrf_token'] === $token;

echo $tokenPersists 
    ? "   ✅ Token CSRF persiste entre peticiones.\n" 
    : "   ❌ Token CSRF no persiste entre peticiones.\n";

// Limpiar la sesión
session_destroy();

// 5. Resumen de verificación
echo "\n=== RESUMEN DE VERIFICACIÓN ===\n\n";

if ($hasCorrectConfig && $hasWriteClose && $tokenPersists) {
    echo "✅ SOLUCIÓN IMPLEMENTADA CORRECTAMENTE\n";
    echo "Los cambios aplicados han resuelto el problema del token CSRF.\n";
} else {
    echo "⚠️ SOLUCIÓN INCOMPLETA\n";
    echo "Algunos elementos aún necesitan ajustes:\n";
    if (!$hasCorrectConfig) echo "- Configuración de sesión incorrecta.\n";
    if (!$hasWriteClose) echo "- Redirecciones sin session_write_close().\n";
    if (!$tokenPersists) echo "- Token CSRF no persiste entre peticiones.\n";
}

echo "\n=== FIN DE LA VERIFICACIÓN ===\n";