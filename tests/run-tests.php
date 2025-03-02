<?php
/**
 * Script para ejecutar todas las pruebas unitarias
 * 
 * Este script permite ejecutar las pruebas unitarias del sistema B2B,
 * mostrar los resultados y generar un informe.
 */

// Cargar el autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Incluir el manejador de errores
require_once __DIR__ . '/../includes/ErrorHandler.php';

echo "===========================================\n";
echo "    SISTEMA DE PRUEBAS B2B ANIMALSCENTER    \n";
echo "===========================================\n\n";

// Verificar si PHPUnit está instalado
if (!class_exists('PHPUnit\TextUI\Command')) {
    echo "ERROR: PHPUnit no está instalado. Ejecute 'composer install' primero.\n";
    exit(1);
}

// Verificar que la aplicación está en línea
$appStatus = testApplicationStatus();
echo "Estado de la aplicación: " . ($appStatus ? "ONLINE ✓" : "OFFLINE ✗") . "\n";

if (!$appStatus) {
    echo "Intentando reiniciar los servicios...\n";
    shell_exec('docker-compose restart');
    sleep(5);
    $appStatus = testApplicationStatus();
    echo "Estado después de reinicio: " . ($appStatus ? "ONLINE ✓" : "OFFLINE ✗") . "\n";
    
    if (!$appStatus) {
        echo "ERROR: No se pudo conectar con la aplicación. Verifique que los servicios estén funcionando.\n";
        exit(1);
    }
}

// Verificar la conexión a la base de datos
$dbStatus = testDatabaseConnection();
echo "Conexión a la base de datos: " . ($dbStatus ? "OK ✓" : "ERROR ✗") . "\n";

if (!$dbStatus) {
    echo "Intentando reiniciar la base de datos...\n";
    shell_exec('docker-compose restart db');
    sleep(10);
    $dbStatus = testDatabaseConnection();
    echo "Estado de BD después de reinicio: " . ($dbStatus ? "OK ✓" : "ERROR ✗") . "\n";
    
    if (!$dbStatus) {
        echo "ERROR: No se pudo conectar a la base de datos. Verifique la configuración.\n";
        exit(1);
    }
}

echo "\nEjecutando pruebas unitarias...\n\n";

// Ejecutar las pruebas con PHPUnit
$command = __DIR__ . '/../vendor/bin/phpunit --testdox ' . __DIR__;
// Ejecutar pruebas básicas excluyendo las que requieren autenticación
echo "EJECUTANDO PRUEBAS BÁSICAS...\n";
echo shell_exec($command . ' --exclude-group auth');

// Verificar si hay datos de prueba
echo "\nVERIFICANDO DATOS DE PRUEBA...\n";
try {
    $host = getenv('DB_HOST') ?: 'db';
    $dbname = getenv('DB_NAME') ?: 'b2b_database';
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: 'secret';
    
    $db = new PDO("mysql:host=$host;dbname=$dbname", $user, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificar si existen los usuarios de prueba
    $stmt = $db->query("SELECT COUNT(*) FROM usuarios WHERE email IN ('admin@test.com', 'proveedor@test.com')");
    $usuariosPrueba = $stmt->fetchColumn();
    
    if ($usuariosPrueba < 2) {
        echo "⚠️ Datos de prueba no encontrados. Creando usuarios de prueba...\n";
        
        // Crear usuarios de prueba
        $adminHash = password_hash('admin123', PASSWORD_BCRYPT);
        $provHash = password_hash('prov123', PASSWORD_BCRYPT);
        
        // Limpiar y crear usuarios
        $db->exec("SET FOREIGN_KEY_CHECKS=0;");
        $db->exec("TRUNCATE TABLE usuarios;");
        $db->exec("SET FOREIGN_KEY_CHECKS=1;");
        
        $db->exec("INSERT INTO usuarios (nombre, email, password_hash, rol, rut) VALUES 
            ('Admin Prueba', 'admin@test.com', '$adminHash', 'admin', '11.111.111-1'),
            ('Proveedor Prueba', 'proveedor@test.com', '$provHash', 'proveedor', '22.222.222-2')");
            
        echo "✅ Usuarios de prueba creados con éxito.\n";
        
        // Verificar si hay que crear datos adicionales
        echo "Creando datos de productos, ventas y facturas...\n";
        $db->exec("INSERT INTO productos (nombre, sku, stock, precio, proveedor_rut) VALUES 
            ('Alimento para perros Premium', 'DOG-FOOD-001', 100, 15000.00, '22.222.222-2'),
            ('Alimento para gatos Royal', 'CAT-FOOD-001', 80, 12000.00, '22.222.222-2'),
            ('Correa para paseo', 'DOG-ACC-001', 50, 8000.00, '22.222.222-2'),
            ('Juguete para gato', 'CAT-TOY-001', 30, 5000.00, '22.222.222-2')");
            
        $db->exec("INSERT INTO ventas (producto_id, cantidad, fecha, proveedor_rut) VALUES 
            (1, 5, NOW(), '22.222.222-2'),
            (2, 3, DATE_SUB(NOW(), INTERVAL 2 DAY), '22.222.222-2'),
            (3, 2, DATE_SUB(NOW(), INTERVAL 5 DAY), '22.222.222-2'),
            (4, 4, DATE_SUB(NOW(), INTERVAL 10 DAY), '22.222.222-2')");
            
        $db->exec("INSERT INTO facturas (venta_id, monto, estado, fecha, proveedor_rut) VALUES 
            (1, 75000.00, 'pendiente', NOW(), '22.222.222-2'),
            (2, 36000.00, 'pagada', DATE_SUB(NOW(), INTERVAL 1 DAY), '22.222.222-2'),
            (3, 16000.00, 'pendiente', DATE_SUB(NOW(), INTERVAL 4 DAY), '22.222.222-2'),
            (4, 20000.00, 'vencida', DATE_SUB(NOW(), INTERVAL 9 DAY), '22.222.222-2')");
            
        echo "✅ Datos de prueba creados con éxito.\n";
    } else {
        echo "✅ Usuarios de prueba encontrados en la base de datos.\n";
    }
    
    // Mostrar credenciales
    echo "\n============================================\n";
    echo "CREDENCIALES DE PRUEBA:\n";
    echo "Admin: admin@test.com / admin123\n";
    echo "Proveedor: proveedor@test.com / prov123\n";
    echo "============================================\n\n";
    
} catch (PDOException $e) {
    echo "❌ Error al verificar datos de prueba: " . $e->getMessage() . "\n";
}

// Ejecutar pruebas de autenticación
echo "\nEJECUTANDO PRUEBAS DE AUTENTICACIÓN Y FLUJO...\n";
echo shell_exec($command . ' --group auth');

echo "\n===========================================\n";
echo "      ANÁLISIS DE ERRORES DEL SISTEMA      \n";
echo "===========================================\n\n";

// Analizar errores registrados
$errorHandler = new ErrorHandler();
$errorStats = $errorHandler->analyzeErrorLogs();

if (empty($errorStats)) {
    echo "No se han detectado errores en los registros.\n";
} else {
    echo "Estadísticas de errores corregidos automáticamente:\n\n";
    
    foreach ($errorStats as $error => $count) {
        echo "- $error: $count veces\n";
    }
}

echo "\n===========================================\n";
echo "            PRUEBAS COMPLETADAS            \n";
echo "===========================================\n";

/**
 * Verifica si la aplicación está en línea
 * @return bool True si la aplicación está en línea
 */
function testApplicationStatus() {
    $url = 'http://localhost:8080/';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode >= 200 && $httpCode < 500;
}

/**
 * Verifica la conexión a la base de datos
 * @return bool True si se pudo conectar a la base de datos
 */
function testDatabaseConnection() {
    try {
        $host = getenv('DB_HOST') ?: 'db';
        $dbname = getenv('DB_NAME') ?: 'b2b_database';
        $user = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASS') ?: 'secret';
        
        $db = new PDO("mysql:host=$host;dbname=$dbname", $user, $password);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $db->query("SELECT 1");
        return $stmt->fetchColumn() == 1;
    } catch (PDOException $e) {
        return false;
    }
}
