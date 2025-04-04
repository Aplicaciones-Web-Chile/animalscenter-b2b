<?php
/**
 * Configuración de la base de datos
 * Este archivo gestiona la conexión a la base de datos MySQL
 */

// Asegurarse de que APP_ROOT esté definido
if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/app.php';
}

/**
 * Establece conexión a la base de datos
 * 
 * @return PDO Objeto de conexión PDO
 * @throws PDOException Si hay error en la conexión
 */
function getDbConnection() {
    // Comprobar si estamos en Hostinger utilizando la función definida en app.php
    if (function_exists('isHostinger') && isHostinger()) {
        // Estamos en Hostinger, usar credenciales de Hostinger
        // Estas credenciales se deben actualizar con los valores reales de Hostinger
        $host = $_ENV['DB_HOST_PRODUCTION'] ?? 'localhost';
        $dbname = $_ENV['DB_NAME_PRODUCTION'] ?? $_ENV['DB_NAME'] ?? 'b2b_database';
        $username = $_ENV['DB_USER_PRODUCTION'] ?? $_ENV['DB_USER'] ?? 'animalscentercl_b2b';
        $password = $_ENV['DB_PASS_PRODUCTION'] ?? $_ENV['DB_PASS'] ?? 'tuPasswordProduccion';
        $port = $_ENV['DB_PORT_PRODUCTION'] ?? $_ENV['DB_PORT'] ?? '3306';
    } else {
        // Estamos en entorno local, usar las variables de entorno definidas
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $dbname = $_ENV['DB_NAME'] ?? 'b2b_database';
        $username = $_ENV['DB_USER'] ?? 'root';
        $password = $_ENV['DB_PASS'] ?? '';
        $port = $_ENV['DB_PORT'] ?? '3306';
    }

    try {
        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        return new PDO($dsn, $username, $password, $options);
    } catch (PDOException $e) {
        // Log error
        error_log("Error de conexión a la BD: " . $e->getMessage());
        
        // En desarrollo mostramos el error, en producción mensaje genérico
        if ($_ENV['APP_ENV'] === 'development') {
            throw $e;
        } else {
            throw new PDOException("Error de conexión a la base de datos. Contacte al administrador.");
        }
    }
}

/**
 * Ejecuta una consulta SQL preparada
 * 
 * @param string $sql Consulta SQL con marcadores de posición
 * @param array $params Parámetros para la consulta preparada
 * @return PDOStatement Resultado de la consulta
 */
function executeQuery($sql, $params = []) {
    $db = getDbConnection();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Obtiene un solo registro de la base de datos
 * 
 * @param string $sql Consulta SQL
 * @param array $params Parámetros
 * @return array|false Registro encontrado o false
 */
function fetchOne($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetch();
}

/**
 * Obtiene múltiples registros de la base de datos
 * 
 * @param string $sql Consulta SQL
 * @param array $params Parámetros
 * @return array Registros encontrados
 */
function fetchAll($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetchAll();
}
