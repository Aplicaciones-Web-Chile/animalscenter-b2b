<?php
/**
 * Script para depurar errores en login.php
 * Este script intenta incluir los componentes uno por uno para identificar el problema
 */

// Configurar para capturar errores
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== DEPURACIÓN DE LOGIN.PHP ===\n\n";

// Incluir solo los componentes esenciales
try {
    echo "1. Incluyendo session.php...\n";
    require_once __DIR__ . '/includes/session.php';
    echo "   ✓ session.php incluido correctamente\n\n";
} catch (Throwable $e) {
    echo "   ❌ ERROR en session.php: " . $e->getMessage() . "\n";
    echo "   Línea: " . $e->getLine() . "\n";
    echo "   Archivo: " . $e->getFile() . "\n\n";
    // Intentar mostrar el código problemático
    $lines = file($e->getFile());
    $start = max(0, $e->getLine() - 5);
    $end = min(count($lines), $e->getLine() + 5);
    echo "   Código problemático:\n";
    for ($i = $start; $i < $end; $i++) {
        echo "   " . ($i + 1) . ": " . $lines[$i];
    }
    echo "\n";
}

try {
    echo "2. Iniciando sesión con startSession()...\n";
    ob_start(); // Capturar cualquier salida para evitar "headers already sent"
    $result = startSession();
    ob_end_clean();
    echo "   ✓ Sesión iniciada: " . ($result ? "SÍ" : "NO") . "\n";
    echo "   ID de sesión: " . session_id() . "\n\n";
} catch (Throwable $e) {
    echo "   ❌ ERROR al iniciar sesión: " . $e->getMessage() . "\n\n";
}

try {
    echo "3. Incluyendo AuthController.php...\n";
    require_once __DIR__ . '/controllers/AuthController.php';
    echo "   ✓ AuthController.php incluido correctamente\n\n";
} catch (Throwable $e) {
    echo "   ❌ ERROR en AuthController.php: " . $e->getMessage() . "\n";
    echo "   Línea: " . $e->getLine() . "\n";
    echo "   Archivo: " . $e->getFile() . "\n\n";
}

try {
    echo "4. Creando instancia de AuthController...\n";
    $authController = new AuthController();
    echo "   ✓ Instancia de AuthController creada correctamente\n\n";
} catch (Throwable $e) {
    echo "   ❌ ERROR al crear AuthController: " . $e->getMessage() . "\n";
    echo "   Línea: " . $e->getLine() . "\n";
    echo "   Archivo: " . $e->getFile() . "\n\n";
    
    // Verificar la clase UserModel que es requerida por AuthController
    try {
        echo "   Verificando UserModel.php...\n";
        require_once __DIR__ . '/models/UserModel.php';
        echo "   ✓ UserModel.php incluido correctamente\n";
    } catch (Throwable $e2) {
        echo "   ❌ ERROR en UserModel.php: " . $e2->getMessage() . "\n";
    }
}

try {
    echo "5. Generando token CSRF...\n";
    // Solo intentar esto si AuthController se creó correctamente
    if (isset($authController) && $authController instanceof AuthController) {
        $token = $authController->generateCSRFToken();
        echo "   ✓ Token CSRF generado: " . $token . "\n\n";
    } else {
        echo "   ⚠️ No se puede generar token CSRF porque AuthController no está disponible\n\n";
    }
} catch (Throwable $e) {
    echo "   ❌ ERROR al generar token CSRF: " . $e->getMessage() . "\n\n";
}

// Verificar la conexión a la base de datos
try {
    echo "6. Verificando conexión a la base de datos...\n";
    
    // Verificar si existe la configuración de base de datos
    if (file_exists(__DIR__ . '/config/database.php')) {
        echo "   ✓ Archivo de configuración de base de datos encontrado\n";
        require_once __DIR__ . '/config/database.php';
        
        // Intentar obtener una conexión si existe la clase Database
        if (class_exists('Database')) {
            $db = Database::getConnection();
            echo "   ✓ Conexión a base de datos creada correctamente\n\n";
        } else {
            echo "   ❌ La clase Database no existe en el archivo database.php\n\n";
        }
    } else {
        echo "   ❌ No se encontró el archivo de configuración de base de datos\n\n";
    }
} catch (Throwable $e) {
    echo "   ❌ ERROR de conexión a la base de datos: " . $e->getMessage() . "\n\n";
}

echo "=== RECOMENDACIONES ===\n\n";
echo "Basado en los resultados anteriores, podría ser necesario:\n";
echo "1. Verificar que todos los archivos requeridos existen y tienen la estructura correcta\n";
echo "2. Comprobar que la conexión a la base de datos funciona correctamente\n";
echo "3. Revisar que la función startSession() no emita salida antes de session_start()\n";
echo "4. Validar que todas las dependencias están disponibles y son compatibles\n\n";

echo "=== FIN DE LA DEPURACIÓN ===\n";
