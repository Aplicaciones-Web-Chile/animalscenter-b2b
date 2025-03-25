<?php
/**
 * Script de seguimiento del flujo de token CSRF
 * Este script coloca puntos de control para rastrear el token CSRF en todo el proceso
 */

// Habilitar visualización de errores para debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== RASTREADOR DE FLUJO DE TOKEN CSRF ===\n\n";

// Función para agregar logs a archivos claves
function agregarLog($archivo, $mensaje, $posicion = 'inicio') {
    if (!file_exists($archivo)) {
        echo "❌ Error: El archivo {$archivo} no existe\n";
        return false;
    }

    $contenido = file_get_contents($archivo);
    $respaldo = $archivo . '.bak.' . date('YmdHis');
    file_put_contents($respaldo, $contenido);
    
    // Código para agregar logs
    $logCode = "\n// PUNTO DE CONTROL: {$mensaje}\nerror_log('[CSRF-TRACKER] [{$mensaje}] ' . (isset(\$_SESSION['csrf_token']) ? 'Token: ' . \$_SESSION['csrf_token'] : 'No hay token') . ' - Session ID: ' . session_id());\n";
    
    if ($posicion === 'inicio') {
        // Añadir después de la etiqueta PHP inicial
        $pattern = '/^(<\?php)/';
        $replacement = "$1\n// CSRF TRACKER ACTIVADO\nini_set('log_errors', 1);\nini_set('error_log', __DIR__ . '/csrf_tracking.log');";
        $contenido = preg_replace($pattern, $replacement, $contenido, 1);
    }
    
    // Buscar posiciones específicas para agregar logs
    if (strpos($mensaje, 'login-form') !== false) {
        // En el formulario de login, justo antes de mostrar el formulario
        $pattern = '/<form.*action="[^"]*".*method="POST".*>/i';
        $logHtml = "<!-- CSRF TRACKER: Mostrando formulario con token -->\n<?php error_log('[CSRF-TRACKER] [Mostrando formulario] Token: ' . htmlspecialchars(\$csrfToken) . ' - Session ID: ' . session_id()); ?>\n";
        
        if (preg_match($pattern, $contenido, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1];
            $contenido = substr_replace($contenido, $matches[0][0] . "\n" . $logHtml, $pos, strlen($matches[0][0]));
        }
    } elseif (strpos($mensaje, 'login-submit') !== false) {
        // Buscar el código que procesa el formulario de login
        $pattern = '/if\s*\(\s*isset\s*\(\s*\$_POST\s*\[\s*[\'"]submit[\'"]\s*\]\s*\)\s*\)/i';
        
        if (preg_match($pattern, $contenido, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1];
            $log = "\n// PUNTO DE CONTROL: Procesando login\nerror_log('[CSRF-TRACKER] [Procesando login] POST token: ' . (isset(\$_POST['csrf_token']) ? \$_POST['csrf_token'] : 'No enviado') . ' - Session token: ' . (isset(\$_SESSION['csrf_token']) ? \$_SESSION['csrf_token'] : 'No existe') . ' - Session ID: ' . session_id());\n\n";
            $contenido = substr_replace($contenido, $log . $matches[0][0], $pos, strlen($matches[0][0]));
        }
    } elseif (strpos($mensaje, 'validate-csrf') !== false) {
        // Buscar la función que valida el CSRF token
        $pattern = '/function\s+validateCSRFToken\s*\(\s*\$token\s*\)/i';
        
        if (preg_match($pattern, $contenido, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1];
            $log = "\n// PUNTO DE CONTROL: Validando CSRF Token\nerror_log('[CSRF-TRACKER] [Validando token] Token recibido: ' . \$token . ' - Token en sesión: ' . (isset(\$_SESSION['csrf_token']) ? \$_SESSION['csrf_token'] : 'No existe') . ' - Session ID: ' . session_id());\n\n";
            $contenido = substr_replace($contenido, $matches[0][0] . $log, $pos, strlen($matches[0][0]));
        }
    } elseif (strpos($mensaje, 'generate-csrf') !== false) {
        // Buscar la función que genera el CSRF token
        $pattern = '/function\s+generateCSRFToken\s*\(\s*\)/i';
        
        if (preg_match($pattern, $contenido, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1];
            $log = "\n// PUNTO DE CONTROL: Generando CSRF Token\nerror_log('[CSRF-TRACKER] [Generando token] - Session ID antes: ' . session_id());\n\n";
            $contenido = substr_replace($contenido, $matches[0][0] . $log, $pos, strlen($matches[0][0]));
        }
    } elseif (strpos($mensaje, 'session-start') !== false) {
        // Buscar la función de inicio de sesión
        $pattern = '/function\s+startSession\s*\(\s*\)/i';
        
        if (preg_match($pattern, $contenido, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1];
            $log = "\n// PUNTO DE CONTROL: Iniciando sesión\nerror_log('[CSRF-TRACKER] [Iniciando sesión] Headers enviados: ' . (headers_sent() ? 'SÍ' : 'NO') . ' - PHPSESSID cookie: ' . (isset(\$_COOKIE['PHPSESSID']) ? \$_COOKIE['PHPSESSID'] : 'No existe'));\n\n";
            $contenido = substr_replace($contenido, $matches[0][0] . $log, $pos, strlen($matches[0][0]));
        }
    } elseif (strpos($mensaje, 'session-end') !== false) {
        // Buscar la línea session_start y agregar session_write_close después
        $pattern = '/session_start\s*\(\s*\)\s*;/i';
        
        if (preg_match_all($pattern, $contenido, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][count($matches[0])-1][1] + strlen($matches[0][count($matches[0])-1][0]);
            $log = "\n// PUNTO DE CONTROL: Sesión iniciada\nerror_log('[CSRF-TRACKER] [Sesión iniciada] Session ID: ' . session_id() . ' - Token: ' . (isset(\$_SESSION['csrf_token']) ? \$_SESSION['csrf_token'] : 'No hay'));\n";
            $contenido = substr_replace($contenido, $log, $pos, 0);
        }
    } elseif (strpos($mensaje, 'redirect') !== false) {
        // Buscar todos los header Location
        $pattern = '/header\s*\(\s*[\'"]Location:/i';
        
        if (preg_match_all($pattern, $contenido, $matches, PREG_OFFSET_CAPTURE)) {
            foreach (array_reverse($matches[0]) as $match) {
                $pos = $match[1];
                $log = "\n// PUNTO DE CONTROL: Redirección\nerror_log('[CSRF-TRACKER] [Redirección] Session ID: ' . session_id() . ' - Token: ' . (isset(\$_SESSION['csrf_token']) ? \$_SESSION['csrf_token'] : 'No hay'));\nsession_write_close(); // Asegurar que la sesión se guarda antes de redireccionar\n\n";
                $contenido = substr_replace($contenido, $log . $match[0], $pos, strlen($match[0]));
            }
        }
    } else {
        // Agregar al inicio del archivo si no hay posición específica
        $pattern = '/^(<\?php.*?)(\/\*\*|\n|\/\/)/s';
        if (preg_match($pattern, $contenido, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[1][1] + strlen($matches[1][0]);
            $contenido = substr_replace($contenido, $logCode, $pos, 0);
        } else {
            $contenido = "<?php\n" . $logCode . substr($contenido, 5);
        }
    }
    
    file_put_contents($archivo, $contenido);
    echo "✅ Punto de control añadido a {$archivo}: {$mensaje}\n";
    return true;
}

// Crear archivo de log para rastreo CSRF
$logFile = __DIR__ . '/csrf_tracking.log';
file_put_contents($logFile, "=== INICIO DE RASTREO CSRF: " . date('Y-m-d H:i:s') . " ===\n");
echo "✅ Archivo de log creado: {$logFile}\n\n";

// Agregar puntos de control en archivos clave
echo "Agregando puntos de control para rastreo del token CSRF...\n";

// 1. En session.php
echo "\n1. Modificando session.php...\n";
agregarLog(__DIR__ . '/includes/session.php', 'session-start');
agregarLog(__DIR__ . '/includes/session.php', 'session-end');

// 2. En login.php
echo "\n2. Modificando login.php...\n";
agregarLog(__DIR__ . '/public/login.php', 'login-form');
agregarLog(__DIR__ . '/public/login.php', 'login-submit');
agregarLog(__DIR__ . '/public/login.php', 'redirect');

// 3. En AuthController.php
echo "\n3. Modificando AuthController.php...\n";
agregarLog(__DIR__ . '/controllers/AuthController.php', 'generate-csrf');
agregarLog(__DIR__ . '/controllers/AuthController.php', 'validate-csrf');
agregarLog(__DIR__ . '/controllers/AuthController.php', 'redirect');

// 4. En dashboard.php (si existe)
if (file_exists(__DIR__ . '/public/dashboard.php')) {
    echo "\n4. Modificando dashboard.php...\n";
    agregarLog(__DIR__ . '/public/dashboard.php', 'dashboard-load');
    agregarLog(__DIR__ . '/public/dashboard.php', 'session-check');
}

echo "\n=== INSTRUCCIONES PARA PRUEBAS ===\n\n";
echo "1. Ejecute el siguiente comando para reiniciar el servidor web (si es necesario):\n";
echo "   sudo service apache2 restart  # Para Apache\n";
echo "   sudo service nginx restart    # Para Nginx\n";
echo "   # O si usa el servidor integrado de PHP:\n";
echo "   cd public && php -S localhost:8080\n\n";
echo "2. Intente iniciar sesión en: http://localhost:8080/login.php\n";
echo "3. Revise el archivo de seguimiento: {$logFile}\n";
echo "4. Si desea restaurar los archivos originales, busque los archivos .bak generados\n\n";
echo "=== FIN DE LA INSTALACIÓN DE PUNTOS DE CONTROL ===\n";
