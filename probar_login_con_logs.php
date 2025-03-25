<?php
/**
 * Script para probar el inicio de sesión con seguimiento de logs
 * Este script simulará el proceso de login y revisará los logs generados
 */

// Habilitar visualización de errores para debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== PRUEBA DE LOGIN CON REGISTRO DE CSRF ===\n\n";

// Comprobar si el archivo de logs existe
$logFile = __DIR__ . '/csrf_tracking.log';
if (!file_exists($logFile)) {
    file_put_contents($logFile, "=== INICIO DE RASTREO CSRF: " . date('Y-m-d H:i:s') . " ===\n");
    echo "✅ Archivo de log creado: {$logFile}\n\n";
} else {
    // Añadir separador en el log
    file_put_contents($logFile, "\n\n=== NUEVA PRUEBA DE LOGIN: " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND);
    echo "✅ Logs se añadirán a: {$logFile}\n\n";
}

// Limpiar cookies de sesión existentes
echo "1. Limpiando cookies de sesión existentes...\n";
if (isset($_COOKIE['PHPSESSID'])) {
    unset($_COOKIE['PHPSESSID']);
    setcookie('PHPSESSID', '', time() - 3600, '/');
    echo "   ✓ Cookie PHPSESSID eliminada\n";
} else {
    echo "   - No había cookie PHPSESSID activa\n";
}

// Función para hacer peticiones HTTP manteniendo cookies entre llamadas
function hacerPeticion($url, $post = null, $cookies = []) {
    echo "   Solicitando: {$url}\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    // Configurar cookies para enviar
    if (!empty($cookies)) {
        $cookieStr = '';
        foreach ($cookies as $name => $value) {
            $cookieStr .= $name . '=' . $value . '; ';
        }
        curl_setopt($ch, CURLOPT_COOKIE, $cookieStr);
    }
    
    // Configurar datos POST si se proporcionan
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        echo "   Enviando datos POST: " . json_encode($post) . "\n";
    }
    
    // Ejecutar petición
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Procesar las cookies recibidas
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    // Extraer cookies de la respuesta
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $header, $matches);
    $newCookies = [];
    
    foreach ($matches[1] as $item) {
        parse_str($item, $cookie);
        $newCookies = array_merge($newCookies, $cookie);
    }
    
    if (!empty($newCookies)) {
        echo "   Cookies recibidas: " . json_encode($newCookies) . "\n";
    }
    
    curl_close($ch);
    
    return [
        'body' => $body,
        'code' => $httpCode,
        'cookies' => $newCookies
    ];
}

// Extraer token CSRF de la página de login
function extraerCSRFToken($html) {
    if (preg_match('/<input[^>]*name=["\']csrf_token["\'][^>]*value=["\']([^"\']+)["\']/i', $html, $matches)) {
        return $matches[1];
    }
    return null;
}

// Paso 1: Obtener la página de login para iniciar sesión y obtener el token CSRF
echo "\n2. Solicitando página de login...\n";
$urlBase = 'http://localhost:8080';
$cookies = [];

$respuesta = hacerPeticion($urlBase . '/login.php');
if ($respuesta['code'] >= 200 && $respuesta['code'] < 300) {
    echo "   ✓ Página de login recibida correctamente (Código: {$respuesta['code']})\n";
    $cookies = array_merge($cookies, $respuesta['cookies']);
    
    // Extraer token CSRF del formulario
    $csrfToken = extraerCSRFToken($respuesta['body']);
    if ($csrfToken) {
        echo "   ✓ Token CSRF obtenido: {$csrfToken}\n";
    } else {
        echo "   ❌ No se pudo encontrar el token CSRF en la página\n";
        echo "   Contenido HTML recibido:\n";
        echo "   " . htmlspecialchars(substr($respuesta['body'], 0, 500)) . "...\n";
        exit(1);
    }
} else {
    echo "   ❌ Error al obtener la página de login (Código: {$respuesta['code']})\n";
    exit(1);
}

// Esperar un segundo para asegurarnos que la sesión se guarda
sleep(1);
echo "   * Esperando 1 segundo...\n";

// Paso 2: Enviar formulario de login
echo "\n3. Enviando formulario de login...\n";
$credenciales = [
    'username' => 'admin@animalscenter.cl',
    'password' => 'AdminB2B123',
    'csrf_token' => $csrfToken,
    'submit' => 'Ingresar'
];

$respuesta = hacerPeticion($urlBase . '/login.php', $credenciales, $cookies);
if ($respuesta['code'] == 302 || $respuesta['code'] == 301 || $respuesta['code'] == 303) {
    echo "   ✓ Redirección después del login (Código: {$respuesta['code']})\n";
    $cookies = array_merge($cookies, $respuesta['cookies']);
    
    // Extraer URL de redirección
    if (preg_match('/Location:\s*(\S+)/i', implode("\n", $respuesta['headers'] ?? []), $matches)) {
        $redirectUrl = $matches[1];
        echo "   ✓ Redirección a: {$redirectUrl}\n";
    } else {
        // Intentar obtener la URL de redirección del código
        preg_match('/header\s*\(\s*[\'"]Location:\s*([^\'"]+)[\'"]/i', $respuesta['body'], $matches);
        $redirectUrl = $matches[1] ?? '/dashboard.php';
        echo "   ✓ URL de redirección inferida: {$redirectUrl}\n";
    }
} elseif ($respuesta['code'] >= 200 && $respuesta['code'] < 300) {
    echo "   ⚠️ No hubo redirección después del login (Código: {$respuesta['code']})\n";
    
    // Verificar si hay mensaje de error
    if (strpos($respuesta['body'], 'token CSRF') !== false) {
        echo "   ❌ Error de token CSRF detectado en la respuesta\n";
    } elseif (strpos($respuesta['body'], 'incorrectos') !== false || strpos($respuesta['body'], 'inválid') !== false) {
        echo "   ❌ Credenciales incorrectas\n";
    } else {
        echo "   ⚠️ No se pudo determinar el resultado del login\n";
    }
    
    // Mostrar parte de la respuesta HTML
    echo "   Contenido HTML parcial:\n";
    echo "   " . htmlspecialchars(substr($respuesta['body'], 0, 500)) . "...\n";
} else {
    echo "   ❌ Error al enviar el formulario de login (Código: {$respuesta['code']})\n";
}

// Paso 3: Seguir la redirección al dashboard
echo "\n4. Accediendo al dashboard...\n";
$respuesta = hacerPeticion($urlBase . '/dashboard.php', null, $cookies);
if ($respuesta['code'] >= 200 && $respuesta['code'] < 300) {
    echo "   ✓ Dashboard accedido correctamente (Código: {$respuesta['code']})\n";
    $cookies = array_merge($cookies, $respuesta['cookies']);
    
    // Verificar si realmente estamos en el dashboard
    if (strpos($respuesta['body'], 'Bienvenido') !== false || strpos($respuesta['body'], 'Dashboard') !== false) {
        echo "   ✅ ÉXITO: Login completado y acceso al dashboard confirmado\n";
    } else {
        echo "   ❌ No parece ser el dashboard. Posiblemente estamos en la página de login nuevamente\n";
    }
} else {
    echo "   ❌ Error al acceder al dashboard (Código: {$respuesta['code']})\n";
}

// Paso 4: Revisar los logs para identificar el problema
echo "\n5. Analizando logs de rastreo CSRF...\n";
$logs = file_exists($logFile) ? file_get_contents($logFile) : 'Archivo de log no encontrado';

// Buscar patrones en los logs que puedan indicar el problema
$sessionIDs = [];
preg_match_all('/Session ID: ([a-zA-Z0-9]+)/', $logs, $matches);
if (!empty($matches[1])) {
    $sessionIDs = array_unique($matches[1]);
    echo "   ℹ️ IDs de sesión detectados: " . implode(", ", $sessionIDs) . "\n";
    
    if (count($sessionIDs) > 1) {
        echo "   ⚠️ Se detectaron múltiples IDs de sesión. Posible problema con la persistencia de sesión.\n";
    }
}

$tokens = [];
preg_match_all('/Token: ([a-zA-Z0-9]+)/', $logs, $matches);
if (!empty($matches[1])) {
    $tokens = array_unique($matches[1]);
    echo "   ℹ️ Tokens CSRF detectados: " . implode(", ", $tokens) . "\n";
    
    if (count($tokens) > 1) {
        echo "   ⚠️ Se detectaron múltiples tokens CSRF. Posible problema con la generación de tokens.\n";
    }
}

if (strpos($logs, 'Headers enviados: SÍ') !== false) {
    echo "   ⚠️ Se detectó que headers ya habían sido enviados antes de iniciar sesión.\n";
    echo "   Esto puede causar problemas con las cookies de sesión.\n";
}

if (strpos($logs, 'Tokens diferentes') !== false) {
    echo "   ❌ Se detectó validación fallida de tokens CSRF.\n";
    echo "   El token enviado no coincide con el token almacenado en la sesión.\n";
}

echo "\n=== RECOMENDACIONES BASADAS EN EL ANÁLISIS ===\n\n";
echo "1. Verifique el archivo de logs completo: {$logFile}\n";
echo "2. Si el problema está relacionado con cookies o sesiones, asegúrese de que:\n";
echo "   - No hay salida antes de session_start()\n";
echo "   - Se llama a session_write_close() antes de las redirecciones\n";
echo "   - Las cookies de sesión tienen los parámetros correctos\n";
echo "3. Si el problema está relacionado con tokens CSRF, verifique que:\n";
echo "   - El token se almacena correctamente en $_SESSION\n";
echo "   - El token del formulario coincide con el token de la sesión\n";
echo "   - No se regenera el token entre la carga del formulario y su envío\n\n";

echo "=== FIN DE LA PRUEBA DE LOGIN ===\n";
