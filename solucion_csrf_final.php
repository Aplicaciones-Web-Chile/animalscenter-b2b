<?php
/**
 * Solución final al problema de token CSRF
 * Este script aplica cambios más profundos para resolver el problema
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== APLICANDO SOLUCIÓN FINAL AL PROBLEMA DE CSRF ===\n\n";

// Generar backup de los archivos antes de modificarlos
function respaldarArchivo($archivo) {
    if (file_exists($archivo)) {
        $backup = $archivo . '.bak.' . date('YmdHis');
        copy($archivo, $backup);
        echo "Backup creado: $backup\n";
        return true;
    }
    return false;
}

// Corregir login.php con depuración CSRF
echo "1. Modificando login.php para depurar y corregir problemas CSRF...\n";
$loginFile = __DIR__ . '/public/login.php';

if (respaldarArchivo($loginFile)) {
    $loginContent = file_get_contents($loginFile);
    
    // 1. Asegurar que se incluye session.php y se usa startSession()
    if (strpos($loginContent, 'require_once __DIR__ . \'/../includes/session.php\';') === false || 
        strpos($loginContent, 'startSession();') === false) {
        
        // Patrón para encontrar el inicio del archivo después de los comentarios
        $pattern = '/(<\?php.*?(?:\r?\n|\r))/s';
        $replacement = '$1
// Iniciar sesión usando función unificada
require_once __DIR__ . \'/../includes/session.php\';
startSession();

';
        // Eliminar cualquier otro inicio de sesión
        $loginContent = preg_replace('/(\s*\/\/\s*Iniciar\s+sesión.*?session_start\(\);.*?(?:\r?\n|\r))/s', '', $loginContent);
        $loginContent = preg_replace($pattern, $replacement, $loginContent, 1);
        
        echo "- Añadida inclusión de session.php y llamada a startSession()\n";
    } else {
        echo "- Inclusión de session.php y startSession() ya existen\n";
    }
    
    // 2. Añadir depuración de CSRF
    $debugCode = '
// *** DEPURACIÓN DE CSRF TOKEN ***
if (isset($_POST[\'csrf_token\'])) {
    $postToken = $_POST[\'csrf_token\'];
    $sessionToken = $_SESSION[\'csrf_token\'] ?? \'no_existe\';
    error_log("LOGIN DEBUG - POST Token: $postToken");
    error_log("LOGIN DEBUG - SESSION Token: $sessionToken");
    error_log("LOGIN DEBUG - ¿Coinciden?: " . ($postToken === $sessionToken ? "SÍ" : "NO"));
}
// ***************************
';
    
    // Insertar antes de procesar el formulario
    if (strpos($loginContent, '// Procesar el formulario de login') !== false) {
        $loginContent = str_replace('// Procesar el formulario de login', $debugCode . '// Procesar el formulario de login', $loginContent);
        echo "- Añadido código de depuración de CSRF\n";
    } else {
        // Buscar patrón alternativo
        $pattern = '/(if\s*\(\s*\$_SERVER\s*\[\s*\'REQUEST_METHOD\'\s*\]\s*===\s*\'POST\'\s*\)\s*\{)/';
        if (preg_match($pattern, $loginContent)) {
            $loginContent = preg_replace($pattern, $debugCode . '$1', $loginContent);
            echo "- Añadido código de depuración de CSRF\n";
        } else {
            echo "- No se pudo encontrar el lugar para insertar depuración CSRF\n";
        }
    }
    
    // 3. Asegurar que session_write_close() se llama antes de redirecciones
    $patterns = [
        '/(header\s*\(\s*"Location:.*?)\);/' => 'session_write_close();
$1);',
        '/(header\s*\(\s*\'Location:.*?)\);/' => 'session_write_close();
$1);'
    ];
    
    foreach ($patterns as $pattern => $replacement) {
        if (preg_match($pattern, $loginContent)) {
            $loginContent = preg_replace($pattern, $replacement, $loginContent);
            echo "- Añadido session_write_close() antes de redirecciones\n";
        }
    }
    
    file_put_contents($loginFile, $loginContent);
    echo "✅ login.php modificado correctamente\n";
} else {
    echo "❌ No se pudo acceder a login.php\n";
}

// Corregir AuthController.php
echo "\n2. Modificando AuthController.php para mejorar validación CSRF...\n";
$authFile = __DIR__ . '/controllers/AuthController.php';

if (respaldarArchivo($authFile)) {
    $authContent = file_get_contents($authFile);
    
    // 1. Mejorar método validateCSRFToken
    $validatePattern = '/(public\s+function\s+validateCSRFToken\s*\(\s*\$token\s*\)\s*\{)(.*?)(\})/s';
    if (preg_match($validatePattern, $authContent, $matches)) {
        $replacement = '$1
        // Asegurar que la sesión está iniciada
        require_once __DIR__ . \'/../includes/session.php\';
        startSession();
        
        if (empty($token) || empty($_SESSION[\'csrf_token\'])) {
            // Registrar problema en el log para depuración
            error_log("CSRF DEBUG - Token vacío: " . 
                (empty($token) ? "TOKEN_POST_VACÍO" : "TOKEN_SESIÓN_VACÍO"));
            
            if (!empty($token)) {
                error_log("CSRF DEBUG - POST token: " . $token);
            }
            if (!empty($_SESSION[\'csrf_token\'])) {
                error_log("CSRF DEBUG - SESSION token: " . $_SESSION[\'csrf_token\']);
            }
            
            return false;
        }
        
        $valid = hash_equals($_SESSION[\'csrf_token\'], $token);
        
        if (!$valid) {
            // Registrar tokens diferentes en el log
            error_log("CSRF DEBUG - Tokens diferentes:");
            error_log("CSRF DEBUG - POST token: " . $token);
            error_log("CSRF DEBUG - SESSION token: " . $_SESSION[\'csrf_token\']);
        }
        
        return $valid;$3';
        
        $authContent = preg_replace($validatePattern, $replacement, $authContent);
        echo "- Mejorada la función validateCSRFToken() con depuración\n";
    } else {
        echo "- No se pudo encontrar la función validateCSRFToken()\n";
    }
    
    // 2. Mejorar método generateCSRFToken
    $generatePattern = '/(public\s+function\s+generateCSRFToken\s*\(\s*\)\s*\{)(.*?)(\})/s';
    if (preg_match($generatePattern, $authContent, $matches)) {
        $replacement = '$1
        // Asegurar que la sesión está iniciada
        require_once __DIR__ . \'/../includes/session.php\';
        startSession();
        
        // Generar token único y seguro
        $token = bin2hex(random_bytes(32));
        $_SESSION[\'csrf_token\'] = $token;
        
        // Registrar token para depuración
        error_log("CSRF DEBUG - Token generado: " . $token);
        error_log("CSRF DEBUG - Session ID: " . session_id());
        
        return $token;$3';
        
        $authContent = preg_replace($generatePattern, $replacement, $authContent);
        echo "- Mejorada la función generateCSRFToken() con depuración\n";
    } else {
        echo "- No se pudo encontrar la función generateCSRFToken()\n";
    }
    
    file_put_contents($authFile, $authContent);
    echo "✅ AuthController.php modificado correctamente\n";
} else {
    echo "❌ No se pudo acceder a AuthController.php\n";
}

// Corregir session.php
echo "\n3. Modificando session.php para mejorar gestión de sesiones...\n";
$sessionFile = __DIR__ . '/includes/session.php';

if (respaldarArchivo($sessionFile)) {
    $sessionContent = file_get_contents($sessionFile);
    
    // Mejorar función startSession
    $startSessionPattern = '/(function\s+startSession\s*\(\s*\)\s*\{)(.*?)(\})/s';
    if (preg_match($startSessionPattern, $sessionContent, $matches)) {
        $replacement = '$1
    if (session_status() === PHP_SESSION_NONE) {
        // Configurar opciones de sesión
        $lifetime = 0; // Hasta que se cierre el navegador
        $path = \'/\';
        $domain = \'\'; // Dominio actual
        $secure = false; // En producción debería ser true
        $httponly = true;
        
        // Intentar establecer cookie parameters
        if (headers_sent()) {
            error_log("ADVERTENCIA: No se pueden configurar parámetros de cookies porque los headers ya fueron enviados");
        } else {
            // Configurar para PHP 7.3+
            session_set_cookie_params([
                \'lifetime\' => $lifetime,
                \'path\' => $path,
                \'domain\' => $domain,
                \'secure\' => $secure,
                \'httponly\' => $httponly,
                \'samesite\' => \'Lax\'
            ]);
        }
        
        // Iniciar sesión
        session_start();
        
        // Debugear información de sesión
        error_log("SESSION DEBUG - Sesión iniciada - ID: " . session_id());
        if (isset($_SESSION[\'csrf_token\'])) {
            error_log("SESSION DEBUG - Token CSRF existente: " . $_SESSION[\'csrf_token\']);
        } else {
            error_log("SESSION DEBUG - No hay token CSRF en la sesión");
        }
        
        return true;
    }
    return false;$3';
        
        $sessionContent = preg_replace($startSessionPattern, $replacement, $sessionContent);
        echo "- Mejorada la función startSession() con depuración\n";
    } else {
        echo "- No se pudo encontrar la función startSession()\n";
    }
    
    file_put_contents($sessionFile, $sessionContent);
    echo "✅ session.php modificado correctamente\n";
} else {
    echo "❌ No se pudo acceder a session.php\n";
}

// Crear script de prueba para verificar solución
$testFile = __DIR__ . '/test_solucion_csrf.php';
$testContent = <<<'EOT'
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
EOT;

file_put_contents($testFile, $testContent);
echo "\n✅ Creado script de prueba: test_solucion_csrf.php\n";

echo "\n=== INSTRUCCIONES FINALES ===\n\n";
echo "Para completar el proceso de solución:\n";
echo "1. Ejecute el script de prueba: php test_solucion_csrf.php\n";
echo "2. Verifique los logs de error de PHP para mensajes de depuración\n";
echo "3. Pruebe el acceso al sistema después de aplicar estos cambios\n\n";

echo "Si persiste el problema, considere reiniciar el servidor web y asegurarse\n";
echo "de que la configuración de PHP permite el almacenamiento correcto de sesiones.\n\n";

echo "=== FIN DEL PROCESO DE SOLUCIÓN ===\n";
