<?php
/**
 * Solución simple para el problema de token CSRF
 * Este script aplica una solución directa al problema del token CSRF
 */

// Habilitar visualización de errores para debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== SOLUCIÓN SIMPLE PARA PROBLEMA DE TOKEN CSRF ===\n\n";

// 1. Restaurar archivos de backup si existen
$checkFiles = [
    __DIR__ . '/includes/session.php',
    __DIR__ . '/controllers/AuthController.php',
    __DIR__ . '/public/login.php',
    __DIR__ . '/public/dashboard.php'
];

foreach ($checkFiles as $file) {
    $backups = glob($file . '.bak.*');
    if (!empty($backups)) {
        // Tomar el backup más reciente
        $lastBackup = end($backups);
        echo "Restaurando archivo original desde backup: {$lastBackup}\n";
        copy($lastBackup, $file);
    }
}

// 2. Modificar sesión para hacer debug
$sessionFile = __DIR__ . '/includes/session.php';
$sessionContent = file_get_contents($sessionFile);
$newSessionContent = "<?php
/**
 * Gestión de sesiones y autenticación
 * Maneja inicio de sesión, cierre de sesión y verificación de permisos
 */

// Iniciar sesión si no está iniciada
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Asegurarse que no se han enviado headers
        if (headers_sent(\$file, \$line)) {
            error_log(\"Headers ya enviados en \$file:\$line - No se puede iniciar sesión correctamente\");
            return false;
        }
        
        // Configurar opciones de sesión seguras
        \$cookieParams = [
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => false, // Cambiar a true en producción con HTTPS
            'httponly' => true,
            'samesite' => 'Lax'
        ];
        
        // Configurar parámetros de cookies
        session_set_cookie_params(\$cookieParams);
        
        // Iniciar sesión
        session_start();
        
        // Debug - Guardar ID de sesión e info CSRF en log
        error_log(\"SESSION START - ID: \" . session_id() . 
                 \" - CSRF: \" . (isset(\$_SESSION['csrf_token']) ? 'Presente' : 'Ausente'));
        
        return true;
    }
    return false;
}

/**
 * Inicia sesión para un usuario
 * 
 * @param array \$userData Datos del usuario autenticado
 * @return bool Resultado de la operación
 */
function login(\$userData) {
    startSession();
    
    // Almacenar información de usuario en la sesión
    \$_SESSION['user_id'] = \$userData['id'];
    \$_SESSION['username'] = \$userData['username'] ?? \$userData['email'];
    \$_SESSION['role'] = \$userData['role'];
    \$_SESSION['logged_in'] = true;
    \$_SESSION['last_activity'] = time();
    
    // Registrar en log
    error_log(\"Usuario logueado: \" . \$_SESSION['username'] . \" - Session ID: \" . session_id());
    
    // Forzar escritura de la sesión
    session_write_close();
    
    return true;
}

/**
 * Verifica si el usuario está autenticado
 * 
 * @return bool Estado de autenticación
 */
function isLoggedIn() {
    startSession();
    return isset(\$_SESSION['logged_in']) && \$_SESSION['logged_in'] === true;
}

/**
 * Cierra la sesión del usuario
 * 
 * @return bool Resultado de la operación
 */
function logout() {
    startSession();
    
    // Guardar usuario para logging
    \$username = \$_SESSION['username'] ?? 'unknown';
    
    // Destruir datos de sesión
    \$_SESSION = [];
    
    // Destruir la cookie de sesión
    if (ini_get(\"session.use_cookies\")) {
        \$params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            \$params[\"path\"], \$params[\"domain\"],
            \$params[\"secure\"], \$params[\"httponly\"]
        );
    }
    
    // Destruir la sesión
    session_destroy();
    
    // Registrar en log
    error_log(\"Usuario deslogueado: \" . \$username);
    
    return true;
}
";

file_put_contents($sessionFile, $newSessionContent);
echo "✅ Archivo session.php actualizado con depuración\n";

// 3. Modificar AuthController para arreglar validación CSRF
$authFile = __DIR__ . '/controllers/AuthController.php';
$authPattern = '/public function validateCSRFToken\(\$token\)\s*\{.*?return \$valid;\s*\}/s';
$authReplacement = "public function validateCSRFToken(\$token) {
        // Asegurar que la sesión está iniciada
        require_once __DIR__ . '/../includes/session.php';
        startSession();
        
        // Debug - registrar datos de sesión y token
        error_log(\"VALIDANDO CSRF - Token recibido: \" . \$token . 
                 \" - Token sesión: \" . (\$_SESSION['csrf_token'] ?? 'NO_EXISTE') . 
                 \" - Session ID: \" . session_id());
        
        // No token proporcionado o no hay token en sesión
        if (empty(\$token) || empty(\$_SESSION['csrf_token'])) {
            error_log(\"CSRF FALLIDO - Token vacío o no existe en sesión\");
            return false;
        }
        
        // Comparar los tokens de forma segura
        \$valid = hash_equals(\$_SESSION['csrf_token'], \$token);
        
        if (!\$valid) {
            error_log(\"CSRF FALLIDO - Tokens no coinciden\");
        } else {
            error_log(\"CSRF VÁLIDO - Tokens coinciden\");
        }
        
        return \$valid;
    }";

$authContent = file_get_contents($authFile);
$authContent = preg_replace($authPattern, $authReplacement, $authContent);

// También arreglar generación de token CSRF
$genPattern = '/public function generateCSRFToken\(\)\s*\{.*?return \$token;\s*\}/s';
$genReplacement = "public function generateCSRFToken() {
        // Asegurar que la sesión está iniciada
        require_once __DIR__ . '/../includes/session.php';
        startSession();
        
        // Generar token solo si no existe ya
        if (empty(\$_SESSION['csrf_token'])) {
            // Generar token seguro
            \$token = bin2hex(random_bytes(32));
            \$_SESSION['csrf_token'] = \$token;
            error_log(\"GENERANDO CSRF - Nuevo token: \" . \$token . \" - Session ID: \" . session_id());
        } else {
            \$token = \$_SESSION['csrf_token'];
            error_log(\"CSRF EXISTENTE - Token: \" . \$token . \" - Session ID: \" . session_id());
        }
        
        // Forzar escritura de la sesión para guardar el token
        session_write_close();
        
        return \$token;
    }";

$authContent = preg_replace($genPattern, $genReplacement, $authContent);
file_put_contents($authFile, $authContent);
echo "✅ Archivo AuthController.php actualizado con depuración\n";

// 4. Modificar login.php para debug y arreglar validación
$loginFile = __DIR__ . '/public/login.php';
$loginContent = file_get_contents($loginFile);

// Agregar debug al inicio
$loginDebug = "<?php
// CSRF DEBUG - Login.php
error_log('LOGIN.PHP CARGADO - ' . (\$_SERVER['REQUEST_METHOD'] ?? 'NO_METHOD') . 
         ' - Session ID: ' . (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE ? session_id() : 'NO_SESSION'));

// Incluir archivos necesarios
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../includes/session.php';

// Iniciar sesión siempre al cargar la página
startSession();

// Inicializar controlador de autenticación
\$authController = new AuthController();

// Generar token CSRF al cargar el formulario
\$csrfToken = \$authController->generateCSRFToken();
error_log('LOGIN.PHP - Token generado: ' . \$csrfToken);

// Si el usuario ya está logueado, redirigir al dashboard
if (isLoggedIn()) {
    error_log('LOGIN.PHP - Usuario ya logueado, redirigiendo...');
    session_write_close(); // Importante: guardar la sesión antes de redirigir
    header('Location: dashboard.php');
    exit;
}

// Procesar formulario enviado
\$error = '';
\$username = '';

if (\$_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug - POST recibido
    error_log('LOGIN.PHP - POST recibido: ' . json_encode(\$_POST));
    
    // Verificar token CSRF
    if (!\$authController->validateCSRFToken(\$_POST['csrf_token'] ?? '')) {
        \$error = 'Error de seguridad: token CSRF inválido. Por favor, recargue la página.';
        error_log('LOGIN.PHP - ERROR: CSRF inválido');
    } else {
        // Validar credenciales
        \$username = trim(\$_POST['username'] ?? '');
        \$password = \$_POST['password'] ?? '';
        
        if (empty(\$username) || empty(\$password)) {
            \$error = 'Por favor, complete todos los campos.';
            error_log('LOGIN.PHP - ERROR: Campos incompletos');
        } else {
            // Intentar login
            \$result = \$authController->login(\$username, \$password);
            
            if (\$result['success']) {
                error_log('LOGIN.PHP - Login exitoso, redirigiendo...');
                session_write_close(); // Importante: guardar la sesión antes de redirigir
                header('Location: ' . (\$result['redirect'] ?? 'dashboard.php'));
                exit;
            } else {
                \$error = \$result['message'] ?? 'Credenciales inválidas.';
                error_log('LOGIN.PHP - ERROR: ' . \$error);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang=\"es\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>Inicio de Sesión - AnimalsCenter</title>";

// Reemplazar la parte PHP al inicio del archivo
$loginContent = preg_replace('/^<\?php.*?<head>/s', $loginDebug, $loginContent);

// Asegurarse de que el token CSRF esté en el formulario correctamente
$formPattern = '/<form.*method=["\']POST["\'].*>/i';
$formReplacement = "<form method=\"POST\" action=\"login.php\" class=\"needs-validation\" novalidate>";

$tokenPattern = '/<input.*name=["\']csrf_token["\'].*?>/i';
$tokenReplacement = "<input type=\"hidden\" name=\"csrf_token\" value=\"<?php echo htmlspecialchars(\$csrfToken); ?>\">";

$loginContent = preg_replace($formPattern, $formReplacement, $loginContent);
if (!preg_match($tokenPattern, $loginContent)) {
    // Si no hay input para csrf_token, agregar después de la etiqueta form
    $loginContent = preg_replace('/(<form[^>]*>)/', '$1' . "\n    " . $tokenReplacement, $loginContent);
} else {
    $loginContent = preg_replace($tokenPattern, $tokenReplacement, $loginContent);
}

file_put_contents($loginFile, $loginContent);
echo "✅ Archivo login.php actualizado con depuración\n";

// 5. Crear script para probar la solución
$testFile = __DIR__ . '/test_csrf_simple.php';
$testContent = "<?php
/**
 * Script para probar la solución simple de CSRF
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo \"=== PRUEBA DE SOLUCIÓN SIMPLE CSRF ===\\n\\n\";

// 1. Simular navegación normal
echo \"1. Simulando navegación normal...\\n\";

// Función para hacer peticiones y mantener cookies
function hacerPeticion(\$url, \$post = null, \$cookies = []) {
    \$ch = curl_init(\$url);
    curl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt(\$ch, CURLOPT_HEADER, true);
    
    if (!empty(\$cookies)) {
        \$cookieString = '';
        foreach (\$cookies as \$name => \$value) {
            \$cookieString .= \$name . '=' . \$value . '; ';
        }
        curl_setopt(\$ch, CURLOPT_COOKIE, \$cookieString);
    }
    
    if (\$post !== null) {
        curl_setopt(\$ch, CURLOPT_POST, true);
        curl_setopt(\$ch, CURLOPT_POSTFIELDS, \$post);
    }
    
    \$response = curl_exec(\$ch);
    \$info = curl_getinfo(\$ch);
    
    // Separar headers y body
    \$headerSize = \$info['header_size'];
    \$headers = substr(\$response, 0, \$headerSize);
    \$body = substr(\$response, \$headerSize);
    
    // Extraer cookies de respuesta
    \$cookies = [];
    preg_match_all('/^Set-Cookie:\\s*([^;\\r\\n]*)(?:;|\\r?\\n|$)/mi', \$headers, \$matches);
    foreach (\$matches[1] as \$match) {
        \$cookiePos = strpos(\$match, '=');
        if (\$cookiePos !== false) {
            \$cookieName = substr(\$match, 0, \$cookiePos);
            \$cookieValue = substr(\$match, \$cookiePos + 1);
            \$cookies[\$cookieName] = \$cookieValue;
        }
    }
    
    curl_close(\$ch);
    
    return [
        'code' => \$info['http_code'],
        'body' => \$body,
        'headers' => \$headers,
        'cookies' => \$cookies
    ];
}

// Extraer token CSRF
function extraerToken(\$html) {
    if (preg_match('/name=[\"\\']csrf_token[\"\\']\\s+value=[\"\\'](.*?)[\"\\']/i', \$html, \$matches)) {
        return \$matches[1];
    }
    return null;
}

// Paso 1: Obtener página login y cookie de sesión
\$cookies = [];
\$loginUrl = 'http://localhost:8080/login.php';

echo \"   Solicitando página de login...\\n\";
\$resp = hacerPeticion(\$loginUrl);
echo \"   Código respuesta: {\$resp['code']}\\n\";

// Mostrar cookies recibidas
if (!empty(\$resp['cookies'])) {
    echo \"   Cookies recibidas: \" . json_encode(\$resp['cookies']) . \"\\n\";
    \$cookies = array_merge(\$cookies, \$resp['cookies']);
} else {
    echo \"   ⚠️ No se recibieron cookies\\n\";
}

// Extraer token CSRF
\$token = extraerToken(\$resp['body']);
if (\$token) {
    echo \"   Token CSRF obtenido: {\$token}\\n\";
} else {
    echo \"   ❌ No se pudo obtener token CSRF\\n\";
    // Mostrar parte del HTML
    echo \"   HTML parcial recibido:\\n\" . substr(\$resp['body'], 0, 200) . \"...\\n\";
    exit(1);
}

// Pausa para asegurar que la sesión se guarde
sleep(1);
echo \"   Esperando 1 segundo...\\n\";

// Paso 2: Enviar formulario de login
echo \"\\n2. Enviando formulario con credenciales...\\n\";
\$loginData = [
    'username' => 'admin@animalscenter.cl',
    'password' => 'AdminB2B123',
    'csrf_token' => \$token,
    'submit' => 'Ingresar'
];

\$resp = hacerPeticion(\$loginUrl, \$loginData, \$cookies);
echo \"   Código de respuesta: {\$resp['code']}\\n\";

// Verificar cookies nuevas/actualizadas
if (!empty(\$resp['cookies'])) {
    echo \"   Cookies recibidas: \" . json_encode(\$resp['cookies']) . \"\\n\";
    \$cookies = array_merge(\$cookies, \$resp['cookies']);
}

// Verificar si hubo redirección
\$location = null;
if (preg_match('/Location: (.*?)\\r?\\n/i', \$resp['headers'], \$matches)) {
    \$location = trim(\$matches[1]);
    echo \"   Redirección a: {\$location}\\n\";
    echo \"   ✅ Login exitoso con redirección\\n\";
} else {
    echo \"   ❌ No hubo redirección. Posible error de login\\n\";
    
    // Verificar si hay mensaje de error
    if (strpos(\$resp['body'], 'CSRF') !== false) {
        echo \"   ERROR: Problema con token CSRF detectado\\n\";
    } elseif (strpos(\$resp['body'], 'Credenciales') !== false) {
        echo \"   ERROR: Credenciales incorrectas\\n\";
    }
    
    // Mostrar parte del HTML
    echo \"   HTML parcial recibido:\\n\" . substr(\$resp['body'], 0, 200) . \"...\\n\";
}

// Paso 3: Seguir redirección al dashboard si hubo login exitoso
if (\$location) {
    echo \"\\n3. Accediendo al dashboard...\\n\";
    \$dashboardUrl = 'http://localhost:8080/' . ltrim(\$location, '/');
    \$resp = hacerPeticion(\$dashboardUrl, null, \$cookies);
    
    echo \"   Código de respuesta: {\$resp['code']}\\n\";
    
    if (\$resp['code'] >= 200 && \$resp['code'] < 300) {
        if (strpos(\$resp['body'], 'Dashboard') !== false || 
            strpos(\$resp['body'], 'Bienvenido') !== false) {
            echo \"   ✅ Acceso al dashboard exitoso\\n\";
        } else {
            echo \"   ⚠️ Acceso al dashboard dudoso\\n\";
        }
    } else {
        echo \"   ❌ Error al acceder al dashboard\\n\";
    }
    
    // Mostrar cookies actualizadas
    if (!empty(\$resp['cookies'])) {
        echo \"   Cookies recibidas: \" . json_encode(\$resp['cookies']) . \"\\n\";
    }
}

echo \"\\n=== VERIFICANDO LOGS ===\\n\";
// Leer últimas 20 líneas del error_log
\$errorLogFile = ini_get('error_log');
if (file_exists(\$errorLogFile)) {
    echo \"Últimas entradas de log:\\n\";
    \$logs = explode(\"\\n\", shell_exec(\"tail -n 20 {\$errorLogFile}\"));
    foreach (\$logs as \$log) {
        if (strpos(\$log, 'CSRF') !== false || 
            strpos(\$log, 'LOGIN') !== false || 
            strpos(\$log, 'SESSION') !== false) {
            echo \"   \" . \$log . \"\\n\";
        }
    }
} else {
    echo \"No se pudo encontrar el archivo de log\\n\";
}

echo \"\\n=== FIN DE LA PRUEBA ===\\n\";";

file_put_contents($testFile, $testContent);
echo "✅ Archivo de prueba test_csrf_simple.php creado\n";

echo "\n=== INSTRUCCIONES PARA PROBAR LA SOLUCIÓN ===\n\n";
echo "1. Reinicie el servidor PHP local:\n";
echo "   cd " . __DIR__ . "/public && php -S localhost:8080\n\n";
echo "2. Ejecute el script de prueba:\n";
echo "   php " . __DIR__ . "/test_csrf_simple.php\n\n";
echo "3. Revise los logs de error de PHP para obtener información detallada\n\n";
echo "=== FIN DE LA INSTALACIÓN ===\n";
