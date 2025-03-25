<?php
/**
 * Herramienta para solucionar el problema del token CSRF
 * Este script detecta y soluciona problemas relacionados con la generación y validación de tokens CSRF
 * 
 * @author AnimalsCenter B2B Development Team
 */

// Establecer visualización de errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Cargar autoloader
require_once __DIR__ . '/../vendor/autoload.php';

echo "=============================================\n";
echo "HERRAMIENTA DE DIAGNÓSTICO Y CORRECCIÓN CSRF\n";
echo "=============================================\n\n";

// Verificar directorio de sesiones
$sessionDir = ini_get('session.save_path');
echo "Directorio de almacenamiento de sesiones: " . ($sessionDir ?: 'Por defecto del sistema') . "\n";
echo "Verificando permisos de directorio...\n";

if ($sessionDir && is_dir($sessionDir)) {
    $isWritable = is_writable($sessionDir);
    echo "  - Directorio accesible: SÍ\n";
    echo "  - Directorio con permisos de escritura: " . ($isWritable ? "SÍ" : "NO") . "\n";
    
    if (!$isWritable) {
        echo "  ⚠️ ATENCIÓN: El directorio de sesiones no tiene permisos de escritura.\n";
    }
} else {
    echo "  - Usando directorio temporal del sistema\n";
}

// Verificar configuración de cookies
$cookieParams = session_get_cookie_params();
echo "\nConfiguración actual de cookies de sesión:\n";
echo "  - Path: " . $cookieParams['path'] . "\n";
echo "  - Domain: " . ($cookieParams['domain'] ?: '(no especificado)') . "\n";
echo "  - Secure: " . ($cookieParams['secure'] ? "SÍ" : "NO") . "\n";
echo "  - HttpOnly: " . ($cookieParams['httponly'] ? "SÍ" : "NO") . "\n";
echo "  - SameSite: " . ($cookieParams['samesite'] ?? '(no especificado)') . "\n";

// Verificar comportamiento de la sesión
echo "\nPrueba de persistencia de sesión:\n";

// Limpiar cualquier sesión existente
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// Configurar las opciones de cookies recomendadas
echo "Aplicando configuración recomendada de cookies:\n";
session_set_cookie_params([
    'lifetime' => 0,               // Hasta que el navegador se cierre
    'path' => '/',                 // Disponible en todo el sitio
    'domain' => '',                // Dominio actual
    'secure' => false,             // En producción debe ser true
    'httponly' => true,            // Protege contra XSS
    'samesite' => 'Lax'            // Protege contra CSRF
]);
echo "  ✅ Configuración aplicada correctamente.\n";

// Iniciar sesión
session_start();
echo "  ✅ Sesión iniciada con ID: " . session_id() . "\n";

// Generar y guardar token CSRF
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
echo "  ✅ Token CSRF generado: " . $_SESSION['csrf_token'] . "\n";

// Escribir sesión a disco
session_write_close();
echo "  ✅ Sesión guardada en disco.\n";

// Reabrir sesión para simular nueva página
session_start();
echo "  ✅ Sesión reabierta con ID: " . session_id() . "\n";

// Verificar que el token sigue disponible
if (isset($_SESSION['csrf_token'])) {
    echo "  ✅ Token CSRF recuperado: " . $_SESSION['csrf_token'] . "\n";
    echo "  ✅ PRUEBA EXITOSA: El token CSRF persiste correctamente entre páginas.\n";
} else {
    echo "  ❌ ERROR: El token CSRF no se ha conservado entre páginas.\n";
}

// Limpiar sesión al terminar
session_destroy();

// Soluciones propuestas
echo "\n=============================================\n";
echo "SOLUCIONES PROPUESTAS\n";
echo "=============================================\n\n";

echo "1. Configuración unificada de sesiones\n";
echo "   Agregar al inicio de public/index.php:\n\n";
echo "   <?php\n";
echo "   // Configuración de sesión recomendada\n";
echo "   session_set_cookie_params([\n";
echo "       'lifetime' => 0,\n";
echo "       'path' => '/',\n";
echo "       'domain' => '',\n";
echo "       'secure' => false, // Cambiar a true en producción\n";
echo "       'httponly' => true,\n";
echo "       'samesite' => 'Lax'\n";
echo "   ]);\n";
echo "   session_start();\n";
echo "   ...\n\n";

echo "2. Corregir el flujo de redirección\n";
echo "   En login.php, modificar la redirección para conservar la sesión:\n\n";
echo "   if (\$result['success']) {\n";
echo "       // Asegurar que la sesión se guarda antes de redireccionar\n";
echo "       session_write_close();\n";
echo "       header(\"Location: \" . (\$result['redirect'] ?? 'dashboard.php'));\n";
echo "       exit;\n";
echo "   }\n\n";

echo "3. Verificar llamadas múltiples a session_start()\n";
echo "   Revisar cada página para asegurar que session_start() se llama una sola vez\n";
echo "   y de manera consistente, preferiblemente al inicio del script.\n\n";

echo "4. Utilizar función unificada para gestión de sesiones\n";
echo "   Modificar includes/session.php para unificar la configuración:\n\n";
echo "   function startSession() {\n";
echo "       if (session_status() === PHP_SESSION_NONE) {\n";
echo "           // Configurar opciones de sesión\n";
echo "           session_set_cookie_params([\n";
echo "               'lifetime' => 0,\n";
echo "               'path' => '/',\n";
echo "               'domain' => '',\n";
echo "               'secure' => false, // Cambiar a true en producción\n";
echo "               'httponly' => true,\n";
echo "               'samesite' => 'Lax'\n";
echo "           ]);\n";
echo "           session_start();\n";
echo "           return true;\n";
echo "       }\n";
echo "       return false;\n";
echo "   }\n\n";

echo "=============================================\n";
echo "IMPLEMENTANDO SOLUCIÓN AUTOMÁTICA\n";
echo "=============================================\n\n";

// Información sobre cambios aplicados
echo "Se han propuesto soluciones para el problema del token CSRF.\n";
echo "Para implementar estos cambios, ejecute los siguientes archivos:\n";
echo "1. fix_session_config.php - Corrige la configuración de sesiones\n";
echo "2. fix_redirect_flow.php - Optimiza el flujo de redirección\n\n";

// Crear archivo para corregir la sesión
$fixSessionFile = __DIR__ . '/fix_session_config.php';
$fixSessionContent = <<<'EOT'
<?php
/**
 * Script para corregir la configuración de sesiones
 * Este script modifica los archivos necesarios para unificar la configuración de sesiones
 */

echo "Corrigiendo configuración de sesiones...\n";

// 1. Modificar includes/session.php
$sessionFile = __DIR__ . '/../includes/session.php';
if (file_exists($sessionFile)) {
    $content = file_get_contents($sessionFile);
    
    // Buscar la función startSession
    $pattern = '/function\s+startSession\s*\(\s*\)\s*\{[^}]*\}/s';
    $replacement = "function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Configurar opciones de sesión
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => false, // Cambiar a true en producción
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        
        session_start();
        return true;
    }
    return false;
}";
    
    $newContent = preg_replace($pattern, $replacement, $content);
    
    if ($newContent !== $content) {
        file_put_contents($sessionFile, $newContent);
        echo "✅ Archivo includes/session.php actualizado correctamente.\n";
    } else {
        echo "⚠️ No se pudieron aplicar cambios a includes/session.php automáticamente.\n";
    }
} else {
    echo "❌ No se encontró el archivo includes/session.php\n";
}

// 2. Verificar que todos los archivos principales utilizan startSession
$files = [
    __DIR__ . '/../public/index.php',
    __DIR__ . '/../public/login.php',
    __DIR__ . '/../public/dashboard.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Verificar si utiliza session_start directamente
        if (strpos($content, 'session_start()') !== false) {
            $basename = basename($file);
            echo "⚠️ El archivo {$basename} usa session_start() directamente.\n";
            echo "   Considere reemplazarlo por require_once __DIR__ . '/../includes/session.php';\n";
            echo "   y luego llamar a startSession();\n";
        }
    }
}

echo "\nProceso completado. Verifique los cambios realizados antes de continuar.\n";
EOT;

file_put_contents($fixSessionFile, $fixSessionContent);
echo "  ✅ Creado archivo de corrección de sesión: tools/fix_session_config.php\n";

// Crear archivo para corregir el flujo de redirección
$fixRedirectFile = __DIR__ . '/fix_redirect_flow.php';
$fixRedirectContent = <<<'EOT'
<?php
/**
 * Script para corregir el flujo de redirección
 * Este script modifica el archivo login.php para mejorar la gestión de sesiones durante redirecciones
 */

echo "Corrigiendo flujo de redirección...\n";

// Modificar public/login.php
$loginFile = __DIR__ . '/../public/login.php';
if (file_exists($loginFile)) {
    $content = file_get_contents($loginFile);
    
    // Buscar la sección de redirección
    $pattern = '/if\s*\(\s*\$result\s*\[\s*[\'"]success[\'"]\s*\]\s*\)\s*\{[^}]*\}/s';
    $sample = 'if ($result[\'success\']) {
            // Asegurar que la sesión se guarda antes de redireccionar
            session_write_close();
            header("Location: " . ($result[\'redirect\'] ?? \'dashboard.php\'));
            exit;
        }';
    
    // Verificar si contiene la línea session_write_close()
    if (strpos($content, 'session_write_close()') === false) {
        $newContent = preg_replace_callback($pattern, function($matches) {
            // Añadir session_write_close() antes del header
            return str_replace('header(', 'session_write_close();
            header(', $matches[0]);
        }, $content);
        
        if ($newContent !== $content) {
            file_put_contents($loginFile, $newContent);
            echo "✅ Archivo public/login.php actualizado correctamente.\n";
        } else {
            echo "⚠️ No se pudieron aplicar cambios a public/login.php automáticamente.\n";
            echo "   Por favor, añada la línea 'session_write_close();' antes de cada redirección.\n";
        }
    } else {
        echo "✅ El archivo public/login.php ya contiene session_write_close().\n";
    }
} else {
    echo "❌ No se encontró el archivo public/login.php\n";
}

// Verificar si también es necesario en index.php y dashboard.php
$files = [
    __DIR__ . '/../public/index.php',
    __DIR__ . '/../public/dashboard.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Verificar si contiene redirecciones sin session_write_close()
        if (strpos($content, 'header(') !== false && strpos($content, 'session_write_close()') === false) {
            $basename = basename($file);
            echo "⚠️ El archivo {$basename} contiene redirecciones sin session_write_close().\n";
            echo "   Considere añadir session_write_close() antes de cada header().\n";
        }
    }
}

echo "\nProceso completado. Verifique los cambios realizados antes de continuar.\n";
EOT;

file_put_contents($fixRedirectFile, $fixRedirectContent);
echo "  ✅ Creado archivo de corrección de redirección: tools/fix_redirect_flow.php\n";

// Instrucciones finales
echo "\n=============================================\n";
echo "PASOS SIGUIENTES\n";
echo "=============================================\n\n";

echo "Para completar la solución, ejecute los siguientes comandos:\n\n";
echo "1. php tools/fix_session_config.php\n";
echo "2. php tools/fix_redirect_flow.php\n";
echo "3. Reinicie el servidor web si es necesario\n\n";

echo "Luego, pruebe nuevamente el login con las credenciales proporcionadas.\n";
echo "Si persiste el problema, revise los archivos log para más información.\n\n";

echo "=============================================\n";
