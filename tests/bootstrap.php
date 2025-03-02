<?php
/**
 * Archivo de bootstrap para las pruebas PHPUnit
 * 
 * Este archivo configura el entorno para las pruebas unitarias.
 */

// Cargar autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Cargar variables de entorno
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Configurar entorno para pruebas
define('TESTING', true);

// Definir funciones de ayuda para pruebas
function getBaseUrl() {
    return 'http://localhost:8080';
}

// Configurar una conexión PDO para pruebas
function getTestDatabaseConnection() {
    $host = $_ENV['DB_HOST'] ?? 'db';
    $dbname = $_ENV['DB_NAME'] ?? 'b2b_database';
    $user = $_ENV['DB_USER'] ?? 'root';
    $password = $_ENV['DB_PASS'] ?? 'secret';
    
    try {
        $db = new PDO("mysql:host=$host;dbname=$dbname", $user, $password);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch (PDOException $e) {
        echo "Error de conexión: " . $e->getMessage();
        return null;
    }
}

// Limpiar tablas para pruebas (útil para pruebas que modifican datos)
function cleanupTestTables() {
    $db = getTestDatabaseConnection();
    if (!$db) return;
    
    try {
        // Desactivar verificación de claves foráneas temporalmente
        $db->exec('SET FOREIGN_KEY_CHECKS = 0');
        
        // Tablas a limpiar (sin borrar datos de usuarios)
        $tablesToClean = ['ventas_test', 'facturas_test', 'productos_test'];
        
        foreach ($tablesToClean as $table) {
            // Verificar si la tabla existe
            $stmt = $db->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                $db->exec("TRUNCATE TABLE $table");
            }
        }
        
        // Reactivar verificación de claves foráneas
        $db->exec('SET FOREIGN_KEY_CHECKS = 1');
    } catch (PDOException $e) {
        echo "Error al limpiar tablas: " . $e->getMessage();
    }
}
