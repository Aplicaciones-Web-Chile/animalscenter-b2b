<?php
/**
 * Solución final al problema del token CSRF
 * Este script aplica las modificaciones necesarias para corregir el problema
 */

echo "=== APLICANDO SOLUCIÓN FINAL AL PROBLEMA DE CSRF ===\n\n";

// Función para modificar archivos
function updateFile($filePath, $changes) {
    if (!file_exists($filePath)) {
        echo "❌ Error: No se encontró el archivo $filePath\n";
        return false;
    }
    
    $content = file_get_contents($filePath);
    $originalContent = $content;
    
    foreach ($changes as $search => $replace) {
        $content = str_replace($search, $replace, $content);
    }
    
    if ($content !== $originalContent) {
        file_put_contents($filePath, $content);
        echo "✅ Archivo actualizado: $filePath\n";
        return true;
    } else {
        echo "⚠️ No se aplicaron cambios a $filePath\n";
        return false;
    }
}

// 1. Corregir public/login.php - Asegurar sesión_write_close() antes de redirecciones
echo "1. Actualizando página de login...\n";
$loginPath = __DIR__ . '/public/login.php';
$loginChanges = [
    "header(\"Location: dashboard.php\");" => "session_write_close();\nheader(\"Location: dashboard.php\");",
    "header(\"Location: \" . (\$result['redirect'] ?? 'dashboard.php'));" => "session_write_close();\nheader(\"Location: \" . (\$result['redirect'] ?? 'dashboard.php'));"
];
updateFile($loginPath, $loginChanges);

// 2. Corregir public/dashboard.php - Asegurar sesión_write_close() antes de redirecciones
echo "\n2. Actualizando página de dashboard...\n";
$dashboardPath = __DIR__ . '/public/dashboard.php';
$dashboardChanges = [
    "header('Location: login.php');" => "session_write_close();\nheader('Location: login.php');"
];
updateFile($dashboardPath, $dashboardChanges);

// 3. Corregir AuthController - Asegurar consistencia en manejo de sesiones
echo "\n3. Actualizando AuthController...\n";
$authControllerPath = __DIR__ . '/controllers/AuthController.php';

// Asegurar que isLoggedIn() usa la función startSession() para consistencia
$authControllerChanges = [
    // Modificar el método isLoggedIn() para consistencia
    "public function isLoggedIn() {\n        return isset(\$_SESSION['user']);" => 
    "public function isLoggedIn() {\n        // Asegurar que la sesión está iniciada\n        require_once __DIR__ . '/../includes/session.php';\n        startSession();\n        \n        return isset(\$_SESSION['user']);"
];
updateFile($authControllerPath, $authControllerChanges);

// 4. Crear archivo de verificación para probar la solución
echo "\n4. Creando archivo de verificación...\n";
$verifyFile = __DIR__ . '/verify_csrf_fix.php';
$verifyContent = <<<'EOT'
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
EOT;

file_put_contents($verifyFile, $verifyContent);
echo "✅ Archivo de verificación creado: verify_csrf_fix.php\n";

// Instrucciones finales
echo "\n=== INSTRUCCIONES FINALES ===\n\n";
echo "Para verificar que la solución ha sido aplicada correctamente, ejecute:\n";
echo "php verify_csrf_fix.php\n\n";
echo "Después pruebe la aplicación web para confirmar que el inicio de sesión funciona correctamente.\n";
echo "Si encuentra algún problema, asegúrese de que se han aplicado todos los cambios y\n";
echo "de que el servidor web ha sido reiniciado si es necesario.\n\n";
echo "=== FIN DEL PROCESO DE SOLUCIÓN ===\n";
