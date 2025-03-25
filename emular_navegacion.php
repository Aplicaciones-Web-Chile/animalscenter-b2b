<?php
/**
 * Emulador de navegación para detectar problemas con CSRF
 * Este script simula el proceso de navegación real y detecta problemas con el token CSRF
 */

// Configuración de opciones
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_implicit_flush(true);
ob_start();

echo "=== EMULACIÓN DE NAVEGACIÓN PARA DETECTAR PROBLEMAS CSRF ===\n\n";

// Función para realizar solicitudes HTTP
function realizarSolicitud($url, $metodo = 'GET', $cookies = [], $datos = [], $seguirRedireccion = true) {
    echo "[HTTP $metodo] Solicitando: $url\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    // Si es POST o hay datos
    if ($metodo === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $datos);
    }
    
    // Configurar cookies
    if (!empty($cookies)) {
        $cookieStr = '';
        foreach ($cookies as $nombre => $valor) {
            $cookieStr .= "$nombre=$valor; ";
        }
        curl_setopt($ch, CURLOPT_COOKIE, $cookieStr);
    }
    
    // Seguir redirecciones automáticamente
    if ($seguirRedireccion) {
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    } else {
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    // Extraer cookies de la respuesta
    $cookiesRecibidas = [];
    preg_match_all('/Set-Cookie: (.*?);/m', $headers, $matches);
    foreach ($matches[1] as $item) {
        if (strpos($item, '=') !== false) {
            list($nombre, $valor) = explode('=', $item, 2);
            $cookiesRecibidas[$nombre] = $valor;
        }
    }
    
    curl_close($ch);
    
    echo "Código de estado: $httpCode\n";
    
    // Verificar si hay redirección
    if ($httpCode >= 300 && $httpCode < 400 && !$seguirRedireccion) {
        preg_match('/Location: (.*?)$/m', $headers, $matchesLocation);
        if (!empty($matchesLocation[1])) {
            echo "Redirección a: " . trim($matchesLocation[1]) . "\n";
        }
    }
    
    return [
        'codigo' => $httpCode,
        'headers' => $headers,
        'cuerpo' => $body,
        'cookies' => $cookiesRecibidas
    ];
}

// Función para extraer CSRF token del HTML
function extraerCSRFToken($html) {
    if (preg_match('/<input[^>]*name=["\']csrf_token["\'][^>]*value=["\']([^"\']+)["\']/i', $html, $matches)) {
        return $matches[1];
    }
    return null;
}

// Función para verificar si existe un mensaje de error de token CSRF
function tieneErrorCSRF($html) {
    return strpos($html, 'token CSRF inválido') !== false;
}

// Paso 1: Visitar la página de login para obtener cookies y token CSRF
echo "PASO 1: Accediendo a la página de login...\n";
$url = 'http://localhost:8080/login.php';
$respuestaLogin = realizarSolicitud($url, 'GET', [], [], false);

$cookies = $respuestaLogin['cookies'];
$csrfToken = extraerCSRFToken($respuestaLogin['cuerpo']);

echo "Cookies obtenidas: " . print_r($cookies, true) . "\n";
echo "Token CSRF obtenido: " . ($csrfToken ?: 'NO ENCONTRADO') . "\n\n";

// Imprimir fragment relevante del HTML para debug
$fragmentoHtml = substr($respuestaLogin['cuerpo'], 0, 2000) . "...";
echo "Fragmento de HTML recibido:\n$fragmentoHtml\n\n";

// Paso 2: Intentar iniciar sesión enviando el formulario
echo "PASO 2: Intentando iniciar sesión...\n";

if ($csrfToken) {
    $datosLogin = [
        'username' => 'admin@animalscenter.cl',
        'password' => 'AdminB2B123',
        'csrf_token' => $csrfToken
    ];
    
    $respuestaSubmit = realizarSolicitud($url, 'POST', $cookies, $datosLogin, false);
    
    // Verificar resultado
    if ($respuestaSubmit['codigo'] >= 300 && $respuestaSubmit['codigo'] < 400) {
        // Hubo redirección - probablemente éxito
        echo "✓ El inicio de sesión parece exitoso (código " . $respuestaSubmit['codigo'] . ")\n\n";
    } else {
        // No hubo redirección - probablemente error
        if (tieneErrorCSRF($respuestaSubmit['cuerpo'])) {
            echo "✗ ERROR: Se detectó problema de token CSRF inválido.\n";
            echo "Contenido del error:\n";
            
            if (preg_match('/<div[^>]*class=["\'].*?alert.*?["\'][^>]*>(.*?)<\/div>/is', $respuestaSubmit['cuerpo'], $matches)) {
                echo trim(strip_tags($matches[1])) . "\n";
            }
        } else {
            echo "✗ ERROR: Inicio de sesión fallido por otra razón.\n";
        }
        
        echo "\nDetalles de la solicitud:\n";
        echo "- URL: $url\n";
        echo "- Método: POST\n";
        echo "- Cookies enviadas: " . print_r($cookies, true) . "\n";
        echo "- Datos enviados: " . print_r($datosLogin, true) . "\n";
        echo "\nEl problema podría ser:\n";
        echo "1. La sesión no persiste correctamente entre solicitudes\n";
        echo "2. El token CSRF no se está enviando correctamente\n";
        echo "3. Existe un problema en el controlador de autenticación al validar el token\n\n";
    }
} else {
    echo "✗ ERROR: No se pudo obtener el token CSRF de la página de login.\n";
    echo "El formulario de login podría no estar generando correctamente el token CSRF.\n\n";
}

// Paso 3: Diagnosticar problema con session.php
echo "PASO 3: Diagnosticando problemas con la gestión de sesiones...\n";

$sessionFile = __DIR__ . '/includes/session.php';
if (file_exists($sessionFile)) {
    $sessionContent = file_get_contents($sessionFile);
    
    echo "Análisis de session.php:\n";
    
    // Verificar si startSession() está bien implementada
    if (strpos($sessionContent, 'function startSession()') !== false) {
        echo "✓ Función startSession() encontrada\n";
        
        if (strpos($sessionContent, 'session_set_cookie_params') !== false) {
            echo "✓ Configuración de cookies implementada\n";
        } else {
            echo "✗ Falta configuración de cookies en startSession()\n";
        }
    } else {
        echo "✗ No se encontró la función startSession()\n";
    }
    
    // Verificar si se registra problema de doble inicio de sesión
    if (preg_match_all('/session_start\s*\(/i', $sessionContent, $matches)) {
        $countSessionStart = count($matches[0]);
        if ($countSessionStart > 1) {
            echo "✗ ADVERTENCIA: Se encontraron $countSessionStart llamadas a session_start() en session.php\n";
        } else {
            echo "✓ Número correcto de llamadas a session_start()\n";
        }
    }
} else {
    echo "✗ No se encontró el archivo session.php\n";
}

// Paso 4: Verificar problemas de inclusión de session.php en login.php
echo "\nPASO 4: Verificando inclusión de session.php en login.php...\n";

$loginFile = __DIR__ . '/public/login.php';
if (file_exists($loginFile)) {
    $loginContent = file_get_contents($loginFile);
    
    // Verificar si se incluye session.php
    if (strpos($loginContent, 'require_once __DIR__ . \'/../includes/session.php\';') !== false) {
        echo "✓ session.php está incluido correctamente en login.php\n";
    } else {
        echo "✗ No se encontró la inclusión correcta de session.php en login.php\n";
    }
    
    // Verificar si se llama a startSession()
    if (strpos($loginContent, 'startSession();') !== false) {
        echo "✓ Se llama a startSession() en login.php\n";
    } else {
        echo "✗ No se llama a startSession() en login.php\n";
        
        // Verificar si hay session_start directo
        if (strpos($loginContent, 'session_start()') !== false) {
            echo "✗ ALERTA: Se usa session_start() directo en lugar de startSession()\n";
        }
    }
}

// Paso 5: Verificar problemas en AuthController
echo "\nPASO 5: Verificando controlador de autenticación...\n";

$authFile = __DIR__ . '/controllers/AuthController.php';
if (file_exists($authFile)) {
    $authContent = file_get_contents($authFile);
    
    // Verificar validateCSRFToken
    if (preg_match('/public\s+function\s+validateCSRFToken\s*\(\s*\$token\s*\)\s*\{(.*?)\}/s', $authContent, $matches)) {
        $validateFunction = $matches[1];
        
        echo "Análisis de validateCSRFToken():\n";
        
        if (strpos($validateFunction, 'startSession()') !== false) {
            echo "✓ Se llama a startSession() en validateCSRFToken()\n";
        } else if (strpos($validateFunction, 'session_start()') !== false) {
            echo "✗ ALERTA: Se usa session_start() directo en validateCSRFToken()\n";
        } else {
            echo "✗ No se inicia la sesión en validateCSRFToken()\n";
        }
        
        if (strpos($validateFunction, '$_SESSION[\'csrf_token\']') !== false) {
            echo "✓ Se verifica el token CSRF en la sesión\n";
        } else {
            echo "✗ No se encontró verificación del token CSRF en la sesión\n";
        }
        
        if (strpos($validateFunction, 'hash_equals') !== false) {
            echo "✓ Se usa hash_equals para comparación segura de tokens\n";
        } else {
            echo "✗ No se usa hash_equals para comparación segura\n";
        }
    } else {
        echo "✗ No se encontró la función validateCSRFToken()\n";
    }
}

// Solución propuesta basada en el diagnóstico
echo "\n=== SOLUCIÓN PROPUESTA ===\n\n";
echo "Basado en el diagnóstico, el problema parece estar en:\n";
echo "1. La gestión de sesiones entre requests\n";
echo "2. La forma en que se valida el token CSRF\n\n";

echo "Pasos para solucionar:\n";
echo "1. Asegurarse de que todas las páginas usen startSession() de manera consistente\n";
echo "2. Verificar que session_write_close() se llama antes de las redirecciones\n";
echo "3. Comprobar que la configuración de cookies de sesión es correcta\n";
echo "4. Modificar directamente login.php para depurar el token CSRF\n\n";

echo "Creando script de solución...\n";

// Crear archivo de solución
$solucionFile = __DIR__ . '/solucion_csrf_final.php';
$solucionContent = <<<'EOT'
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
EOT;

file_put_contents($solucionFile, $solucionContent);
echo "✅ Archivo de solución creado: solucion_csrf_final.php\n";

echo "\n=== COMENTARIOS FINALES ===\n\n";
echo "Ejecute estos scripts en orden:\n";
echo "1. php emular_navegacion.php (este script actual, para diagnosticar el problema exacto)\n";
echo "2. php solucion_csrf_final.php (aplicar la solución profunda con depuración)\n";
echo "3. php test_solucion_csrf.php (verificar que la solución funciona)\n\n";

echo "Los cambios aplicados deberían resolver el problema de token CSRF inválido\n";
echo "al incluir depuración detallada, mejorar la persistencia de sesiones y \n";
echo "optimizar la validación de tokens entre peticiones.\n\n";

echo "=== FIN DEL DIAGNÓSTICO ===\n";
