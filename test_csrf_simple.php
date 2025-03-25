<?php
/**
 * Script para probar la solución simple de CSRF
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== PRUEBA DE SOLUCIÓN SIMPLE CSRF ===\n\n";

// Forzar registro de errores en stdout
ini_set('display_errors', 1);
ini_set('error_log', 'php://stdout');
error_reporting(E_ALL);


// 1. Simular navegación normal
echo "1. Simulando navegación normal...\n";

// Función para hacer peticiones y mantener cookies
function hacerPeticion($url, $post = null, $cookies = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    if (!empty($cookies)) {
        $cookieString = '';
        foreach ($cookies as $name => $value) {
            $cookieString .= $name . '=' . $value . '; ';
        }
        curl_setopt($ch, CURLOPT_COOKIE, $cookieString);
    }
    
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    }
    
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    
    // Separar headers y body
    $headerSize = $info['header_size'];
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    // Extraer cookies de respuesta
    $cookies = [];
    preg_match_all('/^Set-Cookie:\s*([^;\r\n]*)(?:;|\r?\n|$)/mi', $headers, $matches);
    foreach ($matches[1] as $match) {
        $cookiePos = strpos($match, '=');
        if ($cookiePos !== false) {
            $cookieName = substr($match, 0, $cookiePos);
            $cookieValue = substr($match, $cookiePos + 1);
            $cookies[$cookieName] = $cookieValue;
        }
    }
    
    curl_close($ch);
    
    return [
        'code' => $info['http_code'],
        'body' => $body,
        'headers' => $headers,
        'cookies' => $cookies
    ];
}

// Extraer token CSRF
function extraerToken($html) {
    if (preg_match('/name=["\']csrf_token["\']\s+value=["\'](.*?)["\']/i', $html, $matches)) {
        return $matches[1];
    }
    return null;
}

// Paso 1: Obtener página login y cookie de sesión
$cookies = [];
$loginUrl = 'http://localhost:8080/login.php';

echo "   Solicitando página de login...\n";
$resp = hacerPeticion($loginUrl);
echo "   Código respuesta: {$resp['code']}\n";

// Mostrar cookies recibidas
if (!empty($resp['cookies'])) {
    echo "   Cookies recibidas: " . json_encode($resp['cookies']) . "\n";
    $cookies = array_merge($cookies, $resp['cookies']);
} else {
    echo "   ⚠️ No se recibieron cookies\n";
}

// Extraer token CSRF
$token = extraerToken($resp['body']);
if ($token) {
    echo "   Token CSRF obtenido: {$token}\n";
} else {
    echo "   ❌ No se pudo obtener token CSRF\n";
    // Mostrar parte del HTML
    echo "   HTML parcial recibido:\n" . substr($resp['body'], 0, 200) . "...\n";
    exit(1);
}

// Pausa para asegurar que la sesión se guarde
sleep(1);
echo "   Esperando 1 segundo...\n";

// Paso 2: Enviar formulario de login
echo "\n2. Enviando formulario con credenciales...\n";
$loginData = [
    'username' => 'admin@animalscenter.cl',
    'password' => 'AdminB2B123',
    'csrf_token' => $token,
    'submit' => 'Ingresar'
];

$resp = hacerPeticion($loginUrl, $loginData, $cookies);
echo "   Código de respuesta: {$resp['code']}\n";

// Verificar cookies nuevas/actualizadas
if (!empty($resp['cookies'])) {
    echo "   Cookies recibidas: " . json_encode($resp['cookies']) . "\n";
    $cookies = array_merge($cookies, $resp['cookies']);
}

// Verificar si hubo redirección
$location = null;
if (preg_match('/Location: (.*?)\r?\n/i', $resp['headers'], $matches)) {
    $location = trim($matches[1]);
    echo "   Redirección a: {$location}\n";
    echo "   ✅ Login exitoso con redirección\n";
} else {
    echo "   ❌ No hubo redirección. Posible error de login\n";
    
    // Verificar si hay mensaje de error
    if (strpos($resp['body'], 'CSRF') !== false) {
        echo "   ERROR: Problema con token CSRF detectado\n";
    } elseif (strpos($resp['body'], 'Credenciales') !== false) {
        echo "   ERROR: Credenciales incorrectas\n";
    }
    
    // Mostrar parte del HTML
    echo "   HTML parcial recibido:\n" . substr($resp['body'], 0, 200) . "...\n";
}

// Paso 3: Seguir redirección al dashboard si hubo login exitoso
if ($location) {
    echo "\n3. Accediendo al dashboard...\n";
    $dashboardUrl = 'http://localhost:8080/' . ltrim($location, '/');
    $resp = hacerPeticion($dashboardUrl, null, $cookies);
    
    echo "   Código de respuesta: {$resp['code']}\n";
    
    if ($resp['code'] >= 200 && $resp['code'] < 300) {
        if (strpos($resp['body'], 'Dashboard') !== false || 
            strpos($resp['body'], 'Bienvenido') !== false) {
            echo "   ✅ Acceso al dashboard exitoso\n";
        } else {
            echo "   ⚠️ Acceso al dashboard dudoso\n";
        }
    } else {
        echo "   ❌ Error al acceder al dashboard\n";
    }
    
    // Mostrar cookies actualizadas
    if (!empty($resp['cookies'])) {
        echo "   Cookies recibidas: " . json_encode($resp['cookies']) . "\n";
    }
}

echo "\n=== VERIFICANDO LOGS ===\n";
// Leer últimas 20 líneas del error_log
$errorLogFile = ini_get('error_log');
if (file_exists($errorLogFile)) {
    echo "Últimas entradas de log:\n";
    $logs = explode("\n", shell_exec("tail -n 20 {$errorLogFile}"));
    foreach ($logs as $log) {
        if (strpos($log, 'CSRF') !== false || 
            strpos($log, 'LOGIN') !== false || 
            strpos($log, 'SESSION') !== false) {
            echo "   " . $log . "\n";
        }
    }
} else {
    echo "No se pudo encontrar el archivo de log\n";
}

echo "\n=== FIN DE LA PRUEBA ===\n";