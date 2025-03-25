<?php
/**
 * Gestión de conexión a base de datos
 * 
 * Proporciona una interfaz para la conexión a la base de datos
 * reutilizando la funcionalidad existente en config/database.php
 * 
 * @author AnimalsCenter B2B Development Team
 * @version 1.0
 */

namespace App\Core;

// Cargar configuración de la aplicación
require_once dirname(__DIR__, 2) . '/config/app.php';

class Database {
    private static $instance = null;
    private $connection = null;
    
    /**
     * Constructor privado (patrón Singleton)
     */
    private function __construct() {
        // Incluir la configuración de base de datos existente
        require_once APP_ROOT . '/config/database.php';
        
        // Obtener conexión utilizando la función existente
        $this->connection = getDbConnection();
    }
    
    /**
     * Obtiene la instancia única de la base de datos (Singleton)
     * 
     * @return Database Instancia de la base de datos
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Obtiene la conexión PDO
     * 
     * @return \PDO Conexión PDO
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Ejecuta una consulta SQL preparada
     * 
     * @param string $sql Consulta SQL con marcadores de posición
     * @param array $params Parámetros para la consulta preparada
     * @return \PDOStatement Resultado de la consulta
     */
    public function query($sql, $params = []) {
        // Reutilizamos la función existente
        return executeQuery($sql, $params);
    }
    
    /**
     * Obtiene un solo registro de la base de datos
     * 
     * @param string $sql Consulta SQL
     * @param array $params Parámetros
     * @return array|false Registro encontrado o false
     */
    public function fetchOne($sql, $params = []) {
        // Reutilizamos la función existente
        return fetchOne($sql, $params);
    }
    
    /**
     * Obtiene múltiples registros de la base de datos
     * 
     * @param string $sql Consulta SQL
     * @param array $params Parámetros
     * @return array Registros encontrados
     */
    public function fetchAll($sql, $params = []) {
        // Reutilizamos la función existente
        return fetchAll($sql, $params);
    }
    
    /**
     * Inicia una transacción
     * 
     * @return bool Resultado de la operación
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    /**
     * Confirma una transacción
     * 
     * @return bool Resultado de la operación
     */
    public function commit() {
        return $this->connection->commit();
    }
    
    /**
     * Revierte una transacción
     * 
     * @return bool Resultado de la operación
     */
    public function rollBack() {
        return $this->connection->rollBack();
    }
    
    /**
     * Obtiene el último ID insertado
     * 
     * @param string $name Nombre de la secuencia (opcional)
     * @return string Último ID insertado
     */
    public function lastInsertId($name = null) {
        return $this->connection->lastInsertId($name);
    }
}
